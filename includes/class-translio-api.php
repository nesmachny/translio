<?php
/**
 * Translio API Class
 * Handles translation via Translio proxy or direct Anthropic API (BYOAI)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_API {

    /**
     * Singleton instance
     */
    private static $instance = null;

    private $api_key;
    private $api_url = 'https://api.anthropic.com/v1/messages';
    private $proxy_url = 'https://api.translio.to/wp-json/translio/v1';
    private $model = 'claude-3-haiku-20240307';
    private $use_proxy = false;

    /**
     * Get maximum content length before chunking (model-dependent)
     * Haiku: 4096 max output tokens ≈ 12000 chars
     * Sonnet: 8192 max output tokens ≈ 24000 chars
     */
    private function get_max_content_length() {
        $model = (string) $this->model;
        return (strpos($model, 'haiku') !== false) ? 12000 : 20000;
    }

    /**
     * Get singleton instance
     *
     * @return Translio_API
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->api_key = Translio_Admin::decrypt_api_key();

        // Determine translation mode
        $license = translio_license();

        if ($license->is_byoai_mode() && !empty($this->api_key)) {
            // Pro BYOAI user with configured API key - use direct Anthropic
            $this->use_proxy = false;
        } else {
            // Use Translio proxy (credits-based)
            $this->use_proxy = true;
        }
    }

    /**
     * Check if API is configured for translation
     * Returns true if either BYOAI (with API key) or proxy (with valid license) is ready
     *
     * @return bool True if ready for translation
     */
    public function is_configured() {
        if ($this->use_proxy) {
            // Proxy mode requires valid license
            return translio_license()->is_valid();
        }
        // BYOAI mode requires API key
        return !empty($this->api_key);
    }

    /**
     * Check if using proxy mode
     *
     * @return bool True if using Translio proxy
     */
    public function is_proxy_mode() {
        return $this->use_proxy;
    }

    /**
     * Check if user has remaining quota
     *
     * @param int $estimated_tokens Estimated tokens needed (optional)
     * @return bool|WP_Error True if can translate, WP_Error if quota exceeded
     */
    public function check_quota($estimated_tokens = 0) {
        $license = translio_license();

        if (!$license->can_translate($estimated_tokens)) {
            return new WP_Error(
                'quota_exceeded',
                sprintf(
                    __('Monthly token limit reached. Used: %s / %s. Please upgrade your plan.', 'translio'),
                    Translio_License::format_tokens($license->get_tokens_used()),
                    Translio_License::format_tokens($license->get_monthly_quota())
                )
            );
        }

        return true;
    }

    /**
     * Translate text with Translation Memory support
     *
     * @param string $text Text to translate
     * @param string $target_language Target language code
     * @param string $context Optional context hint
     * @param bool $use_tm Whether to use Translation Memory (default true)
     * @param int $tm_threshold Minimum similarity for TM match (default 95 for auto-use)
     * @return string|WP_Error Translated text or error
     */
    public function translate_text($text, $target_language, $context = '', $use_tm = true, $tm_threshold = 100) {
        if (!$this->is_configured()) {
            return new WP_Error('api_not_configured', __('API key not configured', 'translio'));
        }

        if (empty(trim($text))) {
            return '';
        }

        // TODO: Re-enable quota check when monetization is ready
        // $quota_check = $this->check_quota();
        // if (is_wp_error($quota_check)) {
        //     return $quota_check;
        // }

        // Check Translation Memory first (only for high-confidence matches)
        if ($use_tm) {
            $tm_match = Translio_DB::tm_get_translation($text, $target_language, $tm_threshold);
            if ($tm_match && $tm_match['similarity'] >= $tm_threshold) {
                Translio_Logger::log_tm($text, $target_language, true, $tm_match['similarity']);
                return $tm_match['translation'];
            }
        }

        // Check if content needs chunking (too long for single API call)
        if ($this->needs_chunking($text)) {
            Translio_Logger::debug('Content exceeds ' . $this->get_max_content_length() . ' chars, using chunked translation', Translio_Logger::CAT_API);
            return $this->translate_chunked($text, $target_language, $context);
        }

        // Standard translation for normal-length content
        return $this->translate_text_direct($text, $target_language, $context);
    }

    /**
     * Batch translate multiple texts in a single API call
     * Uses Translation Memory to skip already translated texts
     *
     * @param array $texts Array of ['id' => 'unique_id', 'text' => 'text to translate', 'context' => 'optional context']
     * @param string $target_language Target language code
     * @param bool|string $use_tm_or_context Boolean for TM usage, or string for global context (backward compat)
     * @param int $tm_threshold Minimum similarity for TM match (default 100)
     * @return array|WP_Error Array of ['id' => 'translated text'] or WP_Error
     */
    public function translate_batch($texts, $target_language, $use_tm_or_context = true, $tm_threshold = 100) {
        if (!$this->is_configured()) {
            return new WP_Error('api_not_configured', __('API key not configured', 'translio'));
        }

        // Handle backward compatibility: if 3rd param is string, it's a global context
        $use_tm = true;
        $global_context = '';
        if (is_string($use_tm_or_context)) {
            $global_context = $use_tm_or_context;
            $use_tm = true;
        } elseif (is_bool($use_tm_or_context)) {
            $use_tm = $use_tm_or_context;
        }

        // Normalize input format: support both ['field' => 'text'] and [['id' => 'field', 'text' => 'text']]
        $normalized = array();
        foreach ($texts as $key => $value) {
            if (is_array($value) && isset($value['text'])) {
                // Already in correct format: ['id' => 'x', 'text' => 'y']
                if (!empty(trim($value['text']))) {
                    $normalized[] = $value;
                }
            } elseif (is_string($value) && !empty(trim($value))) {
                // Simple format: ['field_name' => 'text value']
                $normalized[] = array(
                    'id' => $key,
                    'text' => $value,
                    'context' => $global_context
                );
            }
        }
        $texts = $normalized;

        if (empty($texts)) {
            return array();
        }

        $translations = array();
        $texts_to_translate = array();

        // Check Translation Memory for each text
        if ($use_tm) {
            foreach ($texts as $item) {
                $tm_match = Translio_DB::tm_get_translation($item['text'], $target_language, $tm_threshold);
                if ($tm_match && $tm_match['similarity'] >= $tm_threshold) {
                    // Found in TM - use cached translation
                    $translations[$item['id']] = $tm_match['translation'];
                    Translio_Logger::log_tm($item['text'], $target_language, true, $tm_match['similarity']);
                } else {
                    // Not in TM - need to translate
                    $texts_to_translate[] = $item;
                }
            }

            // If all found in TM, return early
            if (empty($texts_to_translate)) {
                Translio_Logger::info('All ' . count($translations) . ' texts found in TM - no API call needed', Translio_Logger::CAT_API);
                return $translations;
            }

            Translio_Logger::debug(count($translations) . ' from TM, ' . count($texts_to_translate) . ' need API translation', Translio_Logger::CAT_API);
        } else {
            $texts_to_translate = array_values($texts);
        }

        // If only one text needs translation, use regular method with context
        if (count($texts_to_translate) === 1) {
            $item = reset($texts_to_translate);
            $context = $item['context'] ?? $global_context;
            $result = $this->translate_text($item['text'], $target_language, $context, false); // skip TM check
            if (is_wp_error($result)) {
                return $result;
            }
            $translations[$item['id']] = $result;
            return $translations;
        }

        // Check if all items have the same special context (like CF7)
        // If so, use individual translations to preserve context
        $has_special_context = false;
        foreach ($texts_to_translate as $item) {
            $ctx = $item['context'] ?? '';
            if (!empty($ctx) && (
                stripos($ctx, 'Contact Form') !== false ||
                stripos($ctx, 'shortcode') !== false ||
                stripos($ctx, 'mail-tag') !== false
            )) {
                $has_special_context = true;
                break;
            }
        }

        // For special contexts (CF7, etc.), translate individually to preserve context
        if ($has_special_context) {
            Translio_Logger::debug('Using individual translations for special context (CF7/shortcodes)', Translio_Logger::CAT_CF7);
            foreach ($texts_to_translate as $item) {
                $context = $item['context'] ?? $global_context;
                $result = $this->translate_text($item['text'], $target_language, $context, false);
                if (is_wp_error($result)) {
                    Translio_Logger::error('Error translating ' . $item['id'] . ': ' . $result->get_error_message(), Translio_Logger::CAT_CF7);
                    continue; // Skip failed items but continue with others
                }
                $translations[$item['id']] = $result;
            }
            return $translations;
        }

        // Use proxy batch translation if in proxy mode
        if ($this->use_proxy) {
            $proxy_result = $this->translate_batch_via_proxy($texts_to_translate, $target_language);
            if (is_wp_error($proxy_result)) {
                return $proxy_result;
            }
            return array_merge($translations, $proxy_result);
        }

        // BYOAI mode - use direct Anthropic API
        $languages = Translio::get_available_languages();
        $source_lang = translio()->get_setting('default_language');

        $source_name = isset($languages[$source_lang]) ? $languages[$source_lang]['name'] : $source_lang;
        $target_name = isset($languages[$target_language]) ? $languages[$target_language]['name'] : $target_language;

        // Build batch prompt with context hints if available
        $system_prompt = "You are a translation engine. Translate multiple texts from {$source_name} to {$target_name}.\n" .
                         "CRITICAL RULES:\n" .
                         "1. Return ONLY a valid JSON object: {\"id1\": \"translation1\", \"id2\": \"translation2\"}\n" .
                         "2. Use the exact same IDs provided in the input\n" .
                         "3. PRESERVE ALL HTML EXACTLY: tags, attributes, WordPress blocks (<!-- wp:... -->), shortcodes\n" .
                         "4. Only translate text content, never modify HTML markup\n" .
                         "5. Brand names and technical terms remain unchanged\n" .
                         "6. Output valid JSON only, no markdown code blocks";

        // Add global context if provided
        if (!empty($global_context)) {
            $system_prompt .= "\n\nContext: " . $global_context;
        }

        // Build input - include context hints for each item if available
        $input = array();
        $has_item_contexts = false;
        foreach ($texts_to_translate as $item) {
            $input[$item['id']] = $item['text'];
            if (!empty($item['context'])) {
                $has_item_contexts = true;
            }
        }

        // If items have individual contexts, add them to user content
        $user_content = "Translate these texts:\n";
        if ($has_item_contexts) {
            $input_with_context = array();
            foreach ($texts_to_translate as $item) {
                $entry = array('text' => $item['text']);
                if (!empty($item['context'])) {
                    $entry['hint'] = $item['context'];
                }
                $input_with_context[$item['id']] = $entry;
            }
            $user_content .= wp_json_encode($input_with_context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $user_content .= "\n\nReturn JSON with same IDs, containing only the translated text (not the hint).";
        } else {
            $user_content .= wp_json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $response = $this->make_request($system_prompt, $user_content);

        if (is_wp_error($response)) {
            return $response;
        }

        $translated_text = $this->extract_translation($response);

        if (is_wp_error($translated_text)) {
            return $translated_text;
        }

        // Parse JSON response
        // Remove potential markdown code block markers
        $translated_text = preg_replace('/^```json\s*/', '', $translated_text);
        $translated_text = preg_replace('/\s*```$/', '', $translated_text);
        $translated_text = trim($translated_text);

        // Try parsing as-is first
        $api_translations = json_decode($translated_text, true);

        // If failed, try to fix control characters
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Method: decode with JSON_INVALID_UTF8_SUBSTITUTE flag (PHP 7.2+)
            $api_translations = json_decode($translated_text, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            Translio_Logger::debug('Batch JSON parse failed, falling back to individual translation', Translio_Logger::CAT_API);

            // Fallback: translate each item individually
            foreach ($texts_to_translate as $item) {
                $context = $item['context'] ?? $global_context;
                $result = $this->translate_text($item['text'], $target_language, $context, false);
                if (!is_wp_error($result)) {
                    $translations[$item['id']] = $result;
                }
            }
            return $translations;
        }

        // Clean up excessive escaping from API translations
        foreach ($api_translations as $key => $value) {
            $api_translations[$key] = $this->cleanup_translation($value);
        }

        // Merge TM translations with API translations
        return array_merge($translations, $api_translations);
    }

    public function translate_post($post_id, $target_language = '') {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found', 'translio'));
        }

        // If no target language specified, use first secondary language
        if (empty($target_language)) {
            $secondary_languages = translio()->get_secondary_languages();
            if (!empty($secondary_languages)) {
                $target_language = $secondary_languages[0];
            }
        }

        if (empty($target_language)) {
            return new WP_Error('no_target_language', __('Target language not configured', 'translio'));
        }

        // Collect all fields for batch translation
        $texts_to_translate = array();

        if (!empty($post->post_title)) {
            $texts_to_translate[] = array(
                'id' => 'title',
                'text' => $post->post_title,
                'context' => 'Page/post title'
            );
        }

        if (!empty($post->post_content)) {
            $texts_to_translate[] = array(
                'id' => 'content',
                'text' => $post->post_content,
                'context' => 'Main page/post content'
            );
        }

        if (!empty($post->post_excerpt)) {
            $texts_to_translate[] = array(
                'id' => 'excerpt',
                'text' => $post->post_excerpt,
                'context' => 'Short description/excerpt'
            );
        }

        if (empty($texts_to_translate)) {
            return array();
        }

        // Batch translate all fields in one API call
        $batch_result = $this->translate_batch($texts_to_translate, $target_language);

        if (is_wp_error($batch_result)) {
            return $batch_result;
        }

        $translations = array();

        // Save translations to DB (use actual post type, not hard-coded 'post')
        $post_type = $post->post_type;

        if (isset($batch_result['title'])) {
            Translio_DB::save_translation(
                $post_id,
                $post_type,
                'title',
                $target_language,
                $post->post_title,
                $batch_result['title'],
                true
            );
            $translations['title'] = $batch_result['title'];
        }

        if (isset($batch_result['content'])) {
            Translio_DB::save_translation(
                $post_id,
                $post_type,
                'content',
                $target_language,
                $post->post_content,
                $batch_result['content'],
                true
            );
            $translations['content'] = $batch_result['content'];

            // For wp_navigation posts, also extract and translate individual navigation-link labels
            if ($post_type === 'wp_navigation') {
                $nav_links_result = $this->translate_navigation_links($post->post_content, $target_language);
                if (!is_wp_error($nav_links_result)) {
                    $translations['nav_links'] = $nav_links_result;
                }
            }
        }

        if (isset($batch_result['excerpt'])) {
            Translio_DB::save_translation(
                $post_id,
                $post_type,
                'excerpt',
                $target_language,
                $post->post_excerpt,
                $batch_result['excerpt'],
                true
            );
            $translations['excerpt'] = $batch_result['excerpt'];
        }

        // Translate meta fields
        $meta_translations = $this->translate_post_meta($post_id, $target_language);
        if (is_wp_error($meta_translations)) {
            Translio_Logger::error('Meta translation failed: ' . $meta_translations->get_error_message(), Translio_Logger::CAT_API);
            // Continue with basic translations, don't fail the whole request
        } elseif (!empty($meta_translations)) {
            $translations = array_merge($translations, $meta_translations);
            Translio_Logger::debug('Translated ' . count($meta_translations) . ' meta fields', Translio_Logger::CAT_API);
        }

        return $translations;
    }

    /**
     * Translate post meta fields (ACF, custom fields, etc.)
     *
     * @param int $post_id Post ID
     * @param string $target_language Target language code
     * @return array|WP_Error Array of translations or error
     */
    public function translate_post_meta($post_id, $target_language) {
        // Use shared utility method for extracting meta fields
        $meta_fields = Translio_Utils::extract_post_meta_fields($post_id);

        Translio_Logger::debug('Found ' . count($meta_fields) . ' meta fields for post ' . $post_id, Translio_Logger::CAT_API);

        if (empty($meta_fields)) {
            return array();
        }

        // Get actual post type
        $post_type = get_post_type($post_id);
        if (!$post_type) {
            $post_type = 'post'; // Fallback
        }

        // Fields are already filtered by Utils, prepare for batch translation
        $texts_to_translate = array();
        foreach ($meta_fields as $field) {
            $texts_to_translate[] = array(
                'id' => $field['id'],
                'text' => $field['text'],
                'context' => $field['context']
            );
            Translio_Logger::debug('Meta field to translate: ' . $field['id'] . ' = ' . substr($field['text'], 0, 50), Translio_Logger::CAT_API);
        }

        if (empty($texts_to_translate)) {
            Translio_Logger::debug('No meta texts to translate after filtering', Translio_Logger::CAT_API);
            return array();
        }

        // Batch translate
        Translio_Logger::debug('Batch translating ' . count($texts_to_translate) . ' meta fields', Translio_Logger::CAT_API);
        $batch_result = $this->translate_batch($texts_to_translate, $target_language);

        if (is_wp_error($batch_result)) {
            Translio_Logger::error('Meta batch translate failed: ' . $batch_result->get_error_message(), Translio_Logger::CAT_API);
            return $batch_result;
        }

        Translio_Logger::debug('Meta batch result keys: ' . implode(', ', array_keys($batch_result)), Translio_Logger::CAT_API);

        $translations = array();

        // Save meta translations
        foreach ($meta_fields as $field) {
            $batch_key = $field['id'];
            if (isset($batch_result[$batch_key])) {
                Translio_DB::save_translation(
                    $post_id,
                    $post_type,
                    $batch_key,
                    $target_language,
                    $field['text'],
                    $batch_result[$batch_key],
                    true
                );
                $translations[$batch_key] = $batch_result[$batch_key];
                Translio_Logger::debug('Saved meta translation: ' . $batch_key, Translio_Logger::CAT_API);
            } else {
                Translio_Logger::warning('Missing batch result for: ' . $batch_key, Translio_Logger::CAT_API);
            }
        }

        Translio_Logger::debug('Returning ' . count($translations) . ' meta translations', Translio_Logger::CAT_API);
        return $translations;
    }

    /**
     * Extract and translate navigation link labels from wp_navigation content
     */
    public function translate_navigation_links($content, $target_language) {
        // Parse blocks to find navigation-link blocks
        $blocks = parse_blocks($content);
        $labels = $this->extract_navigation_labels($blocks);

        if (empty($labels)) {
            return array();
        }

        $translations = array();

        foreach ($labels as $label) {
            // Use hash-based field_name for uniqueness (no collisions like CRC32)
            // object_id=0 for global navigation items, field_name contains the hash
            $field_key = 'nav_' . substr(hash('sha256', $label), 0, 16);
            $existing = Translio_DB::get_translation(0, 'navigation', $field_key, $target_language);

            if ($existing && !empty($existing->translated_content)) {
                $translations[$label] = $existing->translated_content;
                continue;
            }

            // Translate the label
            $translated = $this->translate_text($label, $target_language, Translio_Utils::get_translation_context('navigation'));

            if (!is_wp_error($translated) && !empty($translated)) {
                Translio_DB::save_translation(
                    0,              // Global navigation items
                    'navigation',   // New object_type
                    $field_key,     // Hash-based unique key
                    $target_language,
                    $label,
                    $translated,
                    true
                );
                $translations[$label] = $translated;
            }
        }

        return $translations;
    }

    /**
     * Recursively extract labels from navigation-link blocks
     */
    private function extract_navigation_labels($blocks) {
        $labels = array();

        foreach ($blocks as $block) {
            if ($block['blockName'] === 'core/navigation-link' && !empty($block['attrs']['label'])) {
                $labels[] = $block['attrs']['label'];
            }

            // Check inner blocks (for submenus)
            if (!empty($block['innerBlocks'])) {
                $labels = array_merge($labels, $this->extract_navigation_labels($block['innerBlocks']));
            }
        }

        return array_unique($labels);
    }

    public function translate_term($term_id, $taxonomy, $target_language = '') {
        $term = get_term($term_id, $taxonomy);

        if (!$term || is_wp_error($term)) {
            return new WP_Error('term_not_found', __('Term not found', 'translio'));
        }

        // If no target language specified, use first secondary language
        if (empty($target_language)) {
            $secondary_languages = translio()->get_secondary_languages();
            if (!empty($secondary_languages)) {
                $target_language = $secondary_languages[0];
            }
        }

        if (empty($target_language)) {
            return new WP_Error('no_target_language', __('Target language not configured', 'translio'));
        }

        // Collect fields for batch translation
        $texts_to_translate = array();

        if (!empty($term->name)) {
            $texts_to_translate[] = array(
                'id' => 'name',
                'text' => $term->name,
                'context' => 'Category/tag name'
            );
        }

        if (!empty($term->description)) {
            $texts_to_translate[] = array(
                'id' => 'description',
                'text' => $term->description,
                'context' => 'Category/tag description'
            );
        }

        if (empty($texts_to_translate)) {
            return array();
        }

        // Batch translate
        $batch_result = $this->translate_batch($texts_to_translate, $target_language);

        if (is_wp_error($batch_result)) {
            return $batch_result;
        }

        $translations = array();

        if (isset($batch_result['name'])) {
            Translio_DB::save_translation(
                $term_id,
                'term',
                'name',
                $target_language,
                $term->name,
                $batch_result['name'],
                true
            );
            $translations['name'] = $batch_result['name'];
        }

        if (isset($batch_result['description'])) {
            Translio_DB::save_translation(
                $term_id,
                'term',
                'description',
                $target_language,
                $term->description,
                $batch_result['description'],
                true
            );
            $translations['description'] = $batch_result['description'];
        }

        return $translations;
    }

    /**
     * Make API request with retry logic for 429/500/502/503/529 errors
     *
     * @param string $system_prompt System prompt
     * @param string $content User content
     * @param int $attempt Current attempt number (internal use)
     * @return array|WP_Error API response or error
     */
    private function make_request($system_prompt, $content, $attempt = 1) {
        $max_retries = 5; // Increased from 3 for overload errors
        $retryable_codes = array(429, 500, 502, 503, 529);
        $start_time = microtime(true);

        Translio_Logger::debug('Starting API request (attempt ' . $attempt . '/' . $max_retries . ')', Translio_Logger::CAT_API);

        // Haiku supports max 4096, Sonnet supports 8192
        $model = (string) $this->model;
        $max_tokens = (strpos($model, 'haiku') !== false) ? 4096 : 8192;

        $body = array(
            'model' => $this->model,
            'max_tokens' => $max_tokens,
            'system' => $system_prompt,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $content,
                ),
            ),
        );

        $response = wp_remote_post($this->api_url, array(
            'timeout' => 90, // Increased timeout
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => wp_json_encode($body),
        ));

        // Network/connection error - retry
        if (is_wp_error($response)) {
            $duration = microtime(true) - $start_time;
            Translio_Logger::log_api_call('translate', 0, '', $duration, false, $response->get_error_message());

            if ($attempt < $max_retries) {
                $delay = $this->get_retry_delay($attempt, 0);
                Translio_Logger::warning('Retrying in ' . $delay . ' seconds...', Translio_Logger::CAT_API);
                sleep($delay);
                return $this->make_request($system_prompt, $content, $attempt + 1);
            }

            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);
        $duration = microtime(true) - $start_time;

        Translio_Logger::debug('Response code: ' . $response_code . ' (' . round($duration * 1000) . 'ms)', Translio_Logger::CAT_API);

        // Check if retryable error
        if (in_array($response_code, $retryable_codes) && $attempt < $max_retries) {
            // Check for Retry-After header
            $retry_after = wp_remote_retrieve_header($response, 'retry-after');

            // Use longer delay for overload (529) errors
            $delay = $retry_after
                ? min((int) $retry_after, 120)
                : $this->get_retry_delay($attempt, $response_code);

            Translio_Logger::warning('Rate limited/Server error (' . $response_code . '). Retrying in ' . $delay . ' seconds...', Translio_Logger::CAT_API);
            sleep($delay);
            return $this->make_request($system_prompt, $content, $attempt + 1);
        }

        if ($response_code !== 200) {
            $error_message = isset($data['error']['message'])
                ? $data['error']['message']
                : __('API request failed', 'translio');

            $error_type = isset($data['error']['type']) ? $data['error']['type'] : 'unknown';

            // Log full error details for debugging
            Translio_Logger::error('API Error: ' . $response_code . ' - ' . $error_type . ' - ' . $error_message, Translio_Logger::CAT_API);
            Translio_Logger::debug('API Key (first 10 chars): ' . substr($this->api_key, 0, 10) . '...', Translio_Logger::CAT_API);
            Translio_Logger::debug('Model: ' . $this->model, Translio_Logger::CAT_API);

            if (!empty($response_body)) {
                Translio_Logger::debug('Full response: ' . substr($response_body, 0, 500), Translio_Logger::CAT_API);
            }

            // Add user-friendly message for specific errors
            if ($response_code === 529) {
                $error_message = __('Anthropic servers are currently overloaded. Please try again in a few minutes.', 'translio');
            } elseif ($response_code === 403) {
                $error_message = __('API access denied. Please check your API key is valid and has access to the model.', 'translio') . ' (' . $error_type . ')';
            } elseif ($response_code === 401) {
                $error_message = __('Invalid API key. Please check your Anthropic API key in settings.', 'translio');
            }

            Translio_Logger::log_api_call('translate', 0, '', $duration, false, $error_message . ' (Code: ' . $response_code . ')');
            return new WP_Error('api_error', $error_message . ' (Code: ' . $response_code . ')');
        }

        // Track token usage from API response
        if (isset($data['usage'])) {
            $input_tokens = isset($data['usage']['input_tokens']) ? (int) $data['usage']['input_tokens'] : 0;
            $output_tokens = isset($data['usage']['output_tokens']) ? (int) $data['usage']['output_tokens'] : 0;
            $total_tokens = $input_tokens + $output_tokens;

            if ($total_tokens > 0) {
                translio_license()->add_tokens_used($total_tokens);
                Translio_Logger::debug('Tokens used: ' . $total_tokens . ' (in: ' . $input_tokens . ', out: ' . $output_tokens . ')', Translio_Logger::CAT_API);
            }
        }

        Translio_Logger::log_api_call('translate', 1, '', $duration, true);
        return $data;
    }

    /**
     * Calculate exponential backoff delay
     *
     * @param int $attempt Attempt number (1-based)
     * @param int $error_code HTTP error code (for customizing delay)
     * @return int Delay in seconds
     */
    private function get_retry_delay($attempt, $error_code = 0) {
        // Longer delays for overload errors (529)
        if ($error_code === 529) {
            // For 529: 10, 20, 40, 60, 60 seconds
            $base_delay = min(10 * pow(2, $attempt - 1), 60);
        } else {
            // Standard exponential backoff: 2^attempt + random jitter
            // Attempt 1: 2-3 sec, Attempt 2: 4-5 sec, etc.
            $base_delay = pow(2, $attempt);
        }

        $jitter = mt_rand(0, 2000) / 1000; // 0-2 sec jitter
        return min($base_delay + $jitter, 60); // Max 60 seconds
    }

    private function extract_translation($response) {
        if (isset($response['content'][0]['text'])) {
            return trim($response['content'][0]['text']);
        }

        return new WP_Error('invalid_response', __('Invalid API response format', 'translio'));
    }

    public function test_connection() {
        if (!$this->is_configured()) {
            if ($this->use_proxy) {
                return new WP_Error('not_configured', __('No valid license. Please activate your license key.', 'translio'));
            }
            return new WP_Error('not_configured', __('API key not configured. Please add your Anthropic API key.', 'translio'));
        }

        $result = $this->translate_text('Hello', 'es');

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }

    /**
     * Get translation mode info for display
     *
     * @return array Mode information
     */
    public function get_mode_info() {
        return array(
            'mode' => $this->use_proxy ? 'proxy' : 'byoai',
            'mode_label' => $this->use_proxy ? __('Credits (Translio Proxy)', 'translio') : __('BYOAI (Own API Key)', 'translio'),
            'is_configured' => $this->is_configured(),
        );
    }

    /**
     * Check if content needs to be chunked
     *
     * @param string $content Content to check
     * @return bool True if chunking needed
     */
    private function needs_chunking($content) {
        return strlen($content) > $this->get_max_content_length();
    }

    /**
     * Translate long content by splitting into chunks
     *
     * @param string $content Long content to translate
     * @param string $target_language Target language code
     * @param string $context Optional context
     * @return string|WP_Error Translated content or error
     */
    public function translate_chunked($content, $target_language, $context = '') {
        $chunks = $this->split_into_chunks($content);

        Translio_Logger::debug('Chunking content into ' . count($chunks) . ' parts', Translio_Logger::CAT_API);

        $translated_chunks = array();

        foreach ($chunks as $index => $chunk) {
            $chunk_context = $context;
            if (count($chunks) > 1) {
                $chunk_context .= sprintf(' (Part %d of %d)', $index + 1, count($chunks));
            }

            // Translate this chunk (disable TM for partial content)
            $translated = $this->translate_text_direct($chunk, $target_language, $chunk_context);

            if (is_wp_error($translated)) {
                Translio_Logger::error('Chunk ' . ($index + 1) . ' translation failed: ' . $translated->get_error_message(), Translio_Logger::CAT_API);
                return $translated;
            }

            $translated_chunks[] = $translated;
        }

        return implode("\n\n", $translated_chunks);
    }

    /**
     * Direct translation without chunking check (internal use)
     *
     * @param string $text Text to translate
     * @param string $target_language Target language code
     * @param string $context Optional context
     * @return string|WP_Error Translated text or error
     */
    private function translate_text_direct($text, $target_language, $context = '') {
        // Use proxy if in proxy mode
        if ($this->use_proxy) {
            return $this->translate_via_proxy($text, $target_language, $context);
        }

        // BYOAI mode - use direct Anthropic API
        $languages = Translio::get_available_languages();
        $source_lang = translio()->get_setting('default_language');

        $source_name = isset($languages[$source_lang]) ? $languages[$source_lang]['name'] : $source_lang;
        $target_name = isset($languages[$target_language]) ? $languages[$target_language]['name'] : $target_language;

        $system_prompt = "You are a translation engine. Translate from {$source_name} to {$target_name}.\n\n" .
                         "CRITICAL RULES:\n" .
                         "1. Output ONLY the translated content, nothing else\n" .
                         "2. PRESERVE ALL HTML EXACTLY: tags, attributes, WordPress blocks (<!-- wp:... -->), shortcodes [like this]\n" .
                         "3. Only translate text BETWEEN HTML tags, never modify the tags themselves\n" .
                         "4. Keep the same line breaks and whitespace structure\n" .
                         "5. Do NOT add explanations or notes\n" .
                         "6. Brand names and technical terms (WordPress, Next.js, React, etc.) remain unchanged";

        if (!empty($context)) {
            $system_prompt .= " Context: {$context}";
        }

        $response = $this->make_request($system_prompt, $text);

        if (is_wp_error($response)) {
            return $response;
        }

        $translation = $this->extract_translation($response);

        if (is_wp_error($translation)) {
            return $translation;
        }

        // Clean up excessive escaping
        return $this->cleanup_translation($translation);
    }

    /**
     * Translate text via Translio proxy (credits-based)
     *
     * @param string $text Text to translate
     * @param string $target_language Target language code
     * @param string $context Optional context
     * @return string|WP_Error Translated text or error
     */
    private function translate_via_proxy($text, $target_language, $context = '') {
        $license_key = translio_license()->get_license_key();
        if (!$license_key) {
            return new WP_Error('no_license', __('No license key configured. Please activate your license.', 'translio'));
        }

        $source_lang = translio()->get_setting('default_language');
        $start_time = microtime(true);

        Translio_Logger::debug('Sending translation via proxy', Translio_Logger::CAT_API);

        $body = array(
            'text' => $text,
            'target_lang' => $target_language,
            'source_lang' => $source_lang,
        );

        if (!empty($context)) {
            $body['context'] = $context;
        }

        $response = wp_remote_post($this->proxy_url . '/translate', array(
            'timeout' => 90,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-License-Key' => $license_key,
            ),
            'body' => wp_json_encode($body),
        ));

        $duration = microtime(true) - $start_time;

        if (is_wp_error($response)) {
            Translio_Logger::log_api_call('translate_proxy', 0, '', $duration, false, $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        Translio_Logger::debug('Proxy response code: ' . $code . ' (' . round($duration * 1000) . 'ms)', Translio_Logger::CAT_API);

        if ($code === 401) {
            return new WP_Error('invalid_license', __('Invalid or expired license key', 'translio'));
        }

        if ($code === 402) {
            $balance = isset($data['data']['balance']) ? $data['data']['balance'] : 0;
            return new WP_Error('insufficient_credits', sprintf(
                __('Insufficient credits. Current balance: %s characters', 'translio'),
                Translio_License::format_tokens($balance)
            ));
        }

        if ($code === 403) {
            return new WP_Error('domain_mismatch', __('License is activated on a different domain', 'translio'));
        }

        if ($code !== 200 || !isset($data['translation'])) {
            $error_msg = isset($data['message']) ? $data['message'] : __('Translation failed', 'translio');
            Translio_Logger::log_api_call('translate_proxy', 0, '', $duration, false, $error_msg);
            return new WP_Error('proxy_error', $error_msg);
        }

        // Clear credits cache to get fresh balance
        delete_transient('translio_credits_cache');

        Translio_Logger::log_api_call('translate_proxy', strlen($text), '', $duration, true);

        // Clean up excessive escaping from translation
        return $this->cleanup_translation($data['translation']);
    }

    /**
     * Clean up excessive escaping from translation text
     *
     * Sometimes translations contain multiple levels of escaped quotes (\\\\\" instead of ")
     * This function normalizes them to proper quotes.
     *
     * @param string $text Translation text
     * @return string Cleaned text
     */
    private function cleanup_translation($text) {
        if (empty($text) || !is_string($text)) {
            return $text;
        }

        // Replace multiple backslashes followed by quote with just a quote
        // Pattern: 2+ backslashes before a quote -> single quote
        $text = preg_replace('/\\\\{2,}"/', '"', $text);
        $text = preg_replace("/\\\\{2,}'/", "'", $text);

        // Also handle cases where there's still \\" or \'
        $text = str_replace('\\"', '"', $text);
        $text = str_replace("\\'", "'", $text);

        return $text;
    }

    /**
     * Translate batch via Translio proxy (credits-based)
     *
     * @param array $texts Array of texts with ids
     * @param string $target_language Target language code
     * @return array|WP_Error Array of translations or error
     */
    private function translate_batch_via_proxy($texts, $target_language) {
        $license_key = translio_license()->get_license_key();
        if (!$license_key) {
            return new WP_Error('no_license', __('No license key configured', 'translio'));
        }

        $source_lang = translio()->get_setting('default_language');
        $start_time = microtime(true);

        // Prepare texts for batch API
        $batch_texts = array();
        foreach ($texts as $item) {
            $batch_texts[] = array(
                'id' => $item['id'],
                'text' => $item['text'],
            );
        }

        $response = wp_remote_post($this->proxy_url . '/translate/batch', array(
            'timeout' => 120,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-License-Key' => $license_key,
            ),
            'body' => wp_json_encode(array(
                'texts' => $batch_texts,
                'target_lang' => $target_language,
                'source_lang' => $source_lang,
            )),
        ));

        $duration = microtime(true) - $start_time;

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 402) {
            $balance = isset($data['data']['balance']) ? $data['data']['balance'] : 0;
            return new WP_Error('insufficient_credits', sprintf(
                __('Insufficient credits. Current balance: %s characters', 'translio'),
                Translio_License::format_tokens($balance)
            ));
        }

        if ($code !== 200 || !isset($data['translations'])) {
            $error_msg = isset($data['message']) ? $data['message'] : __('Batch translation failed', 'translio');
            return new WP_Error('proxy_error', $error_msg);
        }

        // Clear credits cache
        delete_transient('translio_credits_cache');

        Translio_Logger::log_api_call('translate_batch_proxy', count($texts), '', $duration, true);

        // Clean up excessive escaping from all translations
        $translations = $data['translations'];
        foreach ($translations as $key => $value) {
            $translations[$key] = $this->cleanup_translation($value);
        }

        return $translations;
    }

    /**
     * Split content into translatable chunks
     * Preserves Gutenberg block boundaries or paragraph boundaries
     *
     * @param string $content Content to split
     * @return array Array of content chunks
     */
    private function split_into_chunks($content) {
        $content = (string) $content;
        // For Gutenberg content, split by blocks
        if (strpos($content, '<!-- wp:') !== false) {
            return $this->split_gutenberg_blocks($content);
        }

        // For classic editor, split by paragraphs/headings
        return $this->split_paragraphs($content);
    }

    /**
     * Split Gutenberg content by top-level block boundaries
     * Handles nested blocks correctly by tracking depth
     *
     * @param string $content Gutenberg content
     * @return array Array of chunks
     */
    private function split_gutenberg_blocks($content) {
        // Use WordPress parse_blocks if available (WP 5.0+)
        if (function_exists('parse_blocks')) {
            $blocks = parse_blocks($content);

            if (empty($blocks)) {
                return $this->split_paragraphs($content);
            }

            $chunks = array();
            $current_chunk = '';
            $max_length = $this->get_max_content_length();

            foreach ($blocks as $block) {
                // Serialize block back to HTML
                $block_html = serialize_block($block);

                // Skip empty blocks
                if (empty(trim($block_html))) {
                    continue;
                }

                // If adding this block exceeds limit, save current and start new
                if (!empty($current_chunk) && (strlen($current_chunk) + strlen($block_html)) > $max_length) {
                    $chunks[] = trim($current_chunk);
                    $current_chunk = $block_html;
                } else {
                    $current_chunk .= $block_html;
                }
            }

            if (!empty(trim($current_chunk))) {
                $chunks[] = trim($current_chunk);
            }

            return empty($chunks) ? array($content) : $chunks;
        }

        // Fallback: split by block comment markers
        // Find positions of all <!-- wp: and <!-- /wp: markers
        $lines = explode("\n", $content);
        $blocks = array();
        $current_block = '';
        $depth = 0;

        foreach ($lines as $line) {
            // Check for opening block (not self-closing)
            if (preg_match('/<!-- wp:[a-z0-9\/-]+/', $line) && strpos($line, '/-->') === false) {
                if ($depth === 0 && !empty(trim($current_block))) {
                    $blocks[] = trim($current_block);
                    $current_block = '';
                }
                $depth++;
            }

            $current_block .= $line . "\n";

            // Check for closing block
            if (preg_match('/<!-- \/wp:[a-z0-9\/-]+/', $line)) {
                $depth--;
                if ($depth === 0) {
                    $blocks[] = trim($current_block);
                    $current_block = '';
                }
            }

            // Self-closing block
            if (preg_match('/<!-- wp:[a-z0-9\/-]+.*\/-->/', $line)) {
                if ($depth === 0) {
                    $blocks[] = trim($current_block);
                    $current_block = '';
                }
            }
        }

        // Don't forget remaining content
        if (!empty(trim($current_block))) {
            $blocks[] = trim($current_block);
        }

        if (empty($blocks)) {
            return $this->split_paragraphs($content);
        }

        // Now combine blocks into chunks respecting max length
        $chunks = array();
        $current_chunk = '';
        $max_length = $this->get_max_content_length();

        foreach ($blocks as $block) {
            if (!empty($current_chunk) && (strlen($current_chunk) + strlen($block)) > $max_length) {
                $chunks[] = $current_chunk;
                $current_chunk = $block;
            } else {
                $current_chunk .= ($current_chunk ? "\n\n" : '') . $block;
            }
        }

        if (!empty(trim($current_chunk))) {
            $chunks[] = trim($current_chunk);
        }

        return empty($chunks) ? array($content) : $chunks;
    }

    /**
     * Split classic editor content by paragraphs
     *
     * @param string $content HTML content
     * @return array Array of chunks
     */
    private function split_paragraphs($content) {
        // Split by paragraph or heading tags
        $parts = preg_split('/(<\/p>|<\/h[1-6]>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (count($parts) <= 1) {
            // No paragraphs found, split by double newlines
            $parts = preg_split('/\n\n+/', $content);
        }

        $chunks = array();
        $current_chunk = '';
        $max_length = $this->get_max_content_length();

        foreach ($parts as $part) {
            if (!empty($current_chunk) && (strlen($current_chunk) + strlen($part)) > $max_length) {
                $chunks[] = trim($current_chunk);
                $current_chunk = $part;
            } else {
                $current_chunk .= $part;
            }
        }

        if (!empty(trim($current_chunk))) {
            $chunks[] = trim($current_chunk);
        }

        return empty($chunks) ? array($content) : $chunks;
    }
}
