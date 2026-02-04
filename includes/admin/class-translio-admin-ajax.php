<?php
/**
 * Translio Admin AJAX Handlers
 *
 * All AJAX handlers for the admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin_Ajax {

    public function __construct() {
        // Development helpers
        add_action('wp_ajax_translio_import_test_pages', array($this, 'import_test_pages'));

        // General AJAX handlers
        add_action('wp_ajax_translio_save_translation', array($this, 'save_translation'));
        add_action('wp_ajax_translio_translate_single', array($this, 'translate_single'));
        add_action('wp_ajax_translio_translate_all', array($this, 'translate_all'));
        add_action('wp_ajax_translio_translate_changes', array($this, 'translate_changes'));
        add_action('wp_ajax_translio_get_progress', array($this, 'get_progress'));
        add_action('wp_ajax_translio_translate_single_post', array($this, 'translate_single_post'));

        // Strings AJAX handlers
        add_action('wp_ajax_translio_save_string', array($this, 'save_string'));
        add_action('wp_ajax_translio_translate_strings', array($this, 'translate_strings'));
        add_action('wp_ajax_translio_clear_strings', array($this, 'clear_strings'));
        add_action('wp_ajax_translio_bulk_translate_strings', array($this, 'bulk_translate_strings'));
        add_action('wp_ajax_translio_scan_files', array($this, 'scan_files'));

        // Translation Memory AJAX handlers
        add_action('wp_ajax_translio_tm_suggest', array($this, 'tm_suggest'));
        add_action('wp_ajax_translio_tm_stats', array($this, 'tm_stats'));

        // Taxonomy AJAX handlers
        add_action('wp_ajax_translio_translate_term_field', array($this, 'translate_term_field'));
        add_action('wp_ajax_translio_translate_taxonomy_term', array($this, 'translate_taxonomy_term'));

        // Media AJAX handlers
        add_action('wp_ajax_translio_translate_media_all_fields', array($this, 'translate_media_all_fields'));

        // WooCommerce AJAX handlers
        add_action('wp_ajax_translio_translate_wc_attribute', array($this, 'translate_wc_attribute'));
        add_action('wp_ajax_translio_save_wc_attribute', array($this, 'save_wc_attribute'));

        // Options AJAX handlers
        add_action('wp_ajax_translio_translate_option', array($this, 'translate_option'));

        // Elementor AJAX handlers
        add_action('wp_ajax_translio_save_elementor_translation', array($this, 'save_elementor_translation'));
        add_action('wp_ajax_translio_translate_elementor_field', array($this, 'translate_elementor_field'));
        add_action('wp_ajax_translio_translate_elementor_all', array($this, 'translate_elementor_all'));

        // Contact Form 7 AJAX handlers
        add_action('wp_ajax_translio_translate_cf7_field', array($this, 'translate_cf7_field'));
        add_action('wp_ajax_translio_translate_cf7_all', array($this, 'translate_cf7_all'));

        // Divi AJAX handlers
        add_action('wp_ajax_translio_save_divi_translations', array($this, 'save_divi_translations'));
        add_action('wp_ajax_translio_translate_divi_field', array($this, 'translate_divi_field'));
        add_action('wp_ajax_translio_translate_divi_all', array($this, 'translate_divi_all'));

        // Avada AJAX handlers
        add_action('wp_ajax_translio_save_avada_translations', array($this, 'save_avada_translations'));
        add_action('wp_ajax_translio_translate_avada_field', array($this, 'translate_avada_field'));
        add_action('wp_ajax_translio_translate_avada_all', array($this, 'translate_avada_all'));
    }

    /**
     * Get language code from request or fallback to admin preference
     *
     * @return string Language code or empty string if no languages configured
     */
    private function get_language_from_request() {
        // Check POST parameter first
        if (isset($_POST['language_code'])) {
            $lang = sanitize_text_field($_POST['language_code']);
            if (translio()->is_secondary_language($lang)) {
                return $lang;
            }
        }

        // Fallback to admin language selector preference
        return Translio_Admin::get_admin_language();
    }

    /**
     * Save translation manually
     */
    public function save_translation() {
        check_ajax_referer('translio_nonce', 'nonce');

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(array('message' => __('Translation language not configured', 'translio')));
        }

        $object_type = isset($_POST['object_type']) ? sanitize_text_field($_POST['object_type']) : 'post';

        // Permission check based on object type
        if ($object_type === 'option' || $object_type === 'widget') {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Permission denied. Only administrators can edit site options.', 'translio')));
            }
        } else {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array('message' => __('Permission denied', 'translio')));
            }
        }

        if ($object_type === 'option' || $object_type === 'widget') {
            $object_id = isset($_POST['object_id']) ? absint($_POST['object_id']) : 0;
            $field_name = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
            $translated_content = isset($_POST['translation']) ? wp_kses_post($_POST['translation']) : '';

            if (!$object_id || !$field_name) {
                wp_send_json_error(array('message' => __('Invalid parameters', 'translio')));
            }

            $original_content = '';
            if ($object_type === 'option') {
                $original_content = get_option($field_name, '');
            }

            $result = Translio_DB::save_translation(
                $object_id,
                $object_type,
                $field_name,
                $secondary_language,
                $original_content,
                $translated_content,
                false
            );
        } elseif ($object_type === 'term') {
            $term_id = isset($_POST['object_id']) ? absint($_POST['object_id']) : 0;
            $field_name = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
            $translated_content = isset($_POST['translation']) ? wp_kses_post($_POST['translation']) : '';

            if (!$term_id || !$field_name) {
                wp_send_json_error(array('message' => __('Invalid parameters', 'translio')));
            }

            $term = get_term($term_id);
            if (!$term || is_wp_error($term)) {
                wp_send_json_error(array('message' => __('Term not found', 'translio')));
            }

            $original_content = '';
            if ($field_name === 'name') {
                $original_content = $term->name;
            } elseif ($field_name === 'description') {
                $original_content = $term->description;
            }

            $result = Translio_DB::save_translation(
                $term_id,
                'term',
                $field_name,
                $secondary_language,
                $original_content,
                $translated_content,
                false
            );
        } elseif ($object_type === 'attachment') {
            // Handle attachment/media translations
            $attachment_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $field_name = isset($_POST['field_name']) ? sanitize_text_field($_POST['field_name']) : '';
            $translated_content = isset($_POST['translated_content']) ? wp_kses_post($_POST['translated_content']) : '';

            if (!$attachment_id || !$field_name) {
                wp_send_json_error(array('message' => __('Invalid parameters', 'translio')));
            }

            $attachment = get_post($attachment_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                wp_send_json_error(array('message' => __('Attachment not found', 'translio')));
            }

            $original_content = '';
            switch ($field_name) {
                case 'alt':
                    $original_content = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                    break;
                case 'title':
                    $original_content = $attachment->post_title;
                    break;
                case 'caption':
                    $original_content = $attachment->post_excerpt;
                    break;
                case 'description':
                    $original_content = $attachment->post_content;
                    break;
            }

            $result = Translio_DB::save_translation(
                $attachment_id,
                'attachment',
                $field_name,
                $secondary_language,
                $original_content,
                $translated_content,
                false
            );

            if ($result !== false) {
                wp_send_json_success(array('message' => __('Saved', 'translio')));
            } else {
                wp_send_json_error(array('message' => __('Error saving translation', 'translio')));
            }
            return;
        } else {
            $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $field_name = isset($_POST['field_name']) ? sanitize_text_field($_POST['field_name']) : '';
            $translated_content = isset($_POST['translated_content']) ? wp_kses_post($_POST['translated_content']) : '';

            if (!$post_id || !$field_name) {
                wp_send_json_error(array('message' => __('Invalid parameters', 'translio')));
            }

            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error(array('message' => __('Post not found', 'translio')));
            }

            $original_content = '';
            switch ($field_name) {
                case 'title':
                    $original_content = $post->post_title;
                    break;
                case 'content':
                    $original_content = $post->post_content;
                    break;
                case 'excerpt':
                    $original_content = $post->post_excerpt;
                    break;
            }

            $result = Translio_DB::save_translation(
                $post_id,
                $post->post_type,
                $field_name,
                $secondary_language,
                $original_content,
                $translated_content,
                false
            );
        }

        if ($result !== false) {
            wp_send_json_success(array('message' => __('Saved', 'translio')));
        } else {
            wp_send_json_error(array('message' => __('Error saving translation', 'translio')));
        }
    }

    /**
     * Translate single field via API
     */
    public function translate_single() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $field_name = isset($_POST['field_name']) ? sanitize_text_field($_POST['field_name']) : '';

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(array('message' => __('Translation language not configured', 'translio')));
        }

        $api = Translio_API::instance();
        $object_type = isset($_POST['object_type']) ? sanitize_text_field($_POST['object_type']) : 'post';

        if ($field_name === 'all') {
            $result = $api->translate_post($post_id, $secondary_language);
        } else {
            $content = '';

            if ($object_type === 'widget') {
                $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
            } else {
                $post = get_post($post_id);
                if (!$post) {
                    wp_send_json_error(array('message' => __('Post not found', 'translio')));
                }

                // Use actual post type instead of trusting client-sent value
                $object_type = $post->post_type;

                if ($object_type === 'attachment') {
                    switch ($field_name) {
                        case 'alt':
                            $content = get_post_meta($post_id, '_wp_attachment_image_alt', true);
                            break;
                        case 'title':
                            $content = $post->post_title;
                            break;
                        case 'caption':
                            $content = $post->post_excerpt;
                            break;
                        case 'description':
                            $content = $post->post_content;
                            break;
                    }
                } else {
                    switch ($field_name) {
                        case 'title':
                            $content = $post->post_title;
                            break;
                        case 'content':
                            $content = $post->post_content;
                            break;
                        case 'excerpt':
                            $content = $post->post_excerpt;
                            break;
                        case 'seo_title':
                            if (defined('WPSEO_VERSION')) {
                                $content = get_post_meta($post_id, '_yoast_wpseo_title', true);
                            } elseif (class_exists('RankMath')) {
                                $content = get_post_meta($post_id, 'rank_math_title', true);
                            } elseif (defined('AIOSEO_VERSION')) {
                                $content = get_post_meta($post_id, '_aioseo_title', true);
                            }
                            break;
                        case 'seo_description':
                            if (defined('WPSEO_VERSION')) {
                                $content = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                            } elseif (class_exists('RankMath')) {
                                $content = get_post_meta($post_id, 'rank_math_description', true);
                            } elseif (defined('AIOSEO_VERSION')) {
                                $content = get_post_meta($post_id, '_aioseo_description', true);
                            }
                            break;
                        default:
                            if (strpos($field_name, 'meta_') === 0) {
                                $meta_key = substr($field_name, 5);
                                $content = get_post_meta($post_id, $meta_key, true);

                                if (!empty($content) && Translio_Utils::should_skip_meta_translation($meta_key, $content)) {
                                    wp_send_json_error(array('message' => __('This field does not need translation (URL, style, or technical value)', 'translio')));
                                }
                            }
                            break;
                    }
                }
            }

            if (empty($content)) {
                wp_send_json_error(array('message' => __('No content to translate', 'translio')));
            }

            $secondary_language = $this->get_language_from_request();
            if (empty($secondary_language)) {
                wp_send_json_error(array('message' => __('Secondary language not configured', 'translio')));
            }

            $translated = $api->translate_text($content, $secondary_language);

            if (is_wp_error($translated)) {
                wp_send_json_error(array('message' => $translated->get_error_message()));
            }

            Translio_DB::save_translation(
                $post_id,
                $object_type,
                $field_name,
                $secondary_language,
                $content,
                $translated,
                true
            );

            $result = array($field_name => $translated);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Translated', 'translio'),
            'translations' => $result,
        ));
    }

    /**
     * Translate all untranslated content
     */
    public function translate_all() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(array('message' => __('Translation language not configured', 'translio')));
        }

        $batch_size = 5;
        $untranslated = Translio_DB::get_untranslated_posts($secondary_language, $batch_size);

        if (empty($untranslated)) {
            delete_transient('translio_translate_progress');
            wp_send_json_success(array(
                'done' => true,
                'message' => __('All content has been translated', 'translio'),
            ));
        }

        $api = Translio_API::instance();
        $translated_count = 0;

        foreach ($untranslated as $item) {
            $result = $api->translate_post($item->ID, $secondary_language);
            if (!is_wp_error($result)) {
                $translated_count++;
            }
        }

        $stats = Translio_DB::get_translation_stats($secondary_language);

        wp_send_json_success(array(
            'done' => false,
            'translated' => $translated_count,
            'stats' => $stats,
            'message' => sprintf(__('Translated %d items', 'translio'), $translated_count),
        ));
    }

    /**
     * Translate changed content
     */
    public function translate_changes() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(array('message' => __('Translation language not configured', 'translio')));
        }

        $batch_size = 5;
        $needs_update = Translio_DB::get_posts_needing_update($secondary_language, $batch_size);

        if (empty($needs_update)) {
            wp_send_json_success(array(
                'done' => true,
                'message' => __('All translations are up to date', 'translio'),
            ));
        }

        $api = Translio_API::instance();
        $translated_count = 0;

        foreach ($needs_update as $item) {
            $result = $api->translate_post($item->ID, $secondary_language);
            if (!is_wp_error($result)) {
                $translated_count++;
            }
        }

        $stats = Translio_DB::get_translation_stats($secondary_language);

        wp_send_json_success(array(
            'done' => false,
            'translated' => $translated_count,
            'stats' => $stats,
            'message' => sprintf(__('Updated %d items', 'translio'), $translated_count),
        ));
    }

    /**
     * Get translation progress
     */
    public function get_progress() {
        check_ajax_referer('translio_nonce', 'nonce');

        $secondary_language = $this->get_language_from_request();
        $stats = Translio_DB::get_translation_stats($secondary_language);

        wp_send_json_success($stats);
    }

    /**
     * Translate a single post (all fields)
     */
    public function translate_single_post() {
        check_ajax_referer('translio_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID.', 'translio'));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(__('Post not found.', 'translio'));
        }

        // Handle Contact Form 7 forms separately
        if ($post->post_type === 'wpcf7_contact_form') {
            $_POST['form_id'] = $post_id;
            $_POST['skip_nonce_check'] = true;
            $this->translate_cf7_all();
            return;
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(__('No secondary language configured.', 'translio'));
        }

        $api = Translio_API::instance();
        $translated_fields = array();

        $fields = array(
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
        );

        $to_translate = array();
        foreach ($fields as $field => $value) {
            if (!empty($value)) {
                $existing = Translio_DB::get_translation($post_id, 'post', $field, $secondary_language);
                if (!$existing || empty($existing->translated_content)) {
                    $to_translate[$field] = $value;
                }
            }
        }

        // Translate basic fields if any need translation
        $translations = array();
        if (!empty($to_translate)) {
            $context = sprintf('Post type: %s, Title: %s', $post->post_type, $post->post_title);
            $translations = $api->translate_batch($to_translate, $secondary_language, $context);

            if (is_wp_error($translations)) {
                wp_send_json_error($translations->get_error_message());
            }
        }

        foreach ($translations as $field => $translated) {
            if (!empty($translated) && isset($to_translate[$field])) {
                Translio_DB::save_translation(
                    $post_id,
                    'post',
                    $field,
                    $secondary_language,
                    $to_translate[$field],
                    $translated,
                    true
                );
                $translated_fields[] = $field;
            }
        }

        // Translate meta fields
        $meta_translations = $api->translate_post_meta($post_id, $secondary_language);
        if (!is_wp_error($meta_translations) && !empty($meta_translations)) {
            $translated_fields = array_merge($translated_fields, array_keys($meta_translations));
        }

        wp_send_json_success(array(
            'message' => __('Translated', 'translio'),
            'translated' => count($translated_fields),
            'fields' => $translated_fields
        ));
    }

    /**
     * Save string translation
     */
    public function save_string() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $original = isset($_POST['original']) ? sanitize_text_field($_POST['original']) : '';
        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : 'default';
        $translation = isset($_POST['translation']) ? sanitize_text_field($_POST['translation']) : '';
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'string';

        if (empty($original)) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'translio')));
        }

        $string_id = Translio_Utils::generate_hash_id('gettext', $original, $domain);

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(array('message' => __('Translation language not configured', 'translio')));
        }

        $object_type = ($type === 'nav_link') ? 'block_item' : 'string';
        $field_name = ($type === 'nav_link') ? 'label' : 'text';

        $result = Translio_DB::save_translation(
            $string_id,
            $object_type,
            $field_name,
            $secondary_language,
            $original,
            $translation,
            false
        );

        if ($result !== false) {
            wp_send_json_success(array('message' => __('Saved', 'translio'), 'string_id' => $string_id));
        } else {
            wp_send_json_error(array('message' => __('Error saving', 'translio')));
        }
    }

    /**
     * Translate multiple strings via API
     */
    public function translate_strings() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $strings_json = isset($_POST['strings']) ? stripslashes($_POST['strings']) : '[]';
        $strings = json_decode($strings_json, true);

        if (empty($strings)) {
            wp_send_json_error(array('message' => __('No strings to translate', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(array('message' => __('Translation language not configured', 'translio')));
        }

        $api = Translio_API::instance();
        $translations = array();
        $errors = array();

        foreach ($strings as $string) {
            $translated = $api->translate_text($string['text'], $secondary_language, 'UI string or navigation label');

            if (is_wp_error($translated)) {
                $errors[] = $translated->get_error_message();
                continue;
            }

            $type = isset($string['type']) ? $string['type'] : 'string';
            $object_type = ($type === 'nav_link') ? 'block_item' : 'string';
            $field_name = ($type === 'nav_link') ? 'label' : 'text';

            Translio_DB::save_translation(
                $string['id'],
                $object_type,
                $field_name,
                $secondary_language,
                $string['text'],
                $translated,
                true
            );
            $translations[$string['id']] = $translated;
        }

        // If all failed, return error with first error message
        if (empty($translations) && !empty($errors)) {
            wp_send_json_error(array(
                'message' => $errors[0],
                'errors' => array_unique($errors),
            ));
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Translated %d strings', 'translio'), count($translations)),
            'translations' => $translations,
            'errors' => !empty($errors) ? array_unique($errors) : null,
        ));
    }

    /**
     * Clear all scanned strings
     */
    public function clear_strings() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'translio'));
        }

        $result = Translio_DB::clear_scanned_strings();

        if ($result !== false) {
            wp_send_json_success(array('message' => __('All strings cleared', 'translio')));
        } else {
            wp_send_json_error(__('Error clearing strings', 'translio'));
        }
    }

    /**
     * Bulk translate all untranslated strings
     */
    public function bulk_translate_strings() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'translio'));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(__('Translation language not configured', 'translio'));
        }

        $untranslated = Translio_DB::get_scanned_strings(array(
            'translated' => 'no',
            'language_code' => $secondary_language,
            'limit' => 100,
        ));

        if (empty($untranslated)) {
            wp_send_json_success(array('translated' => 0, 'message' => __('No untranslated strings found', 'translio')));
        }

        $api = Translio_API::instance();
        $translated_count = 0;

        foreach ($untranslated as $string) {
            $translated = $api->translate_text($string->string_text, $secondary_language, 'UI string');

            if (!is_wp_error($translated) && !empty($translated)) {
                Translio_DB::save_translation(
                    $string->object_id,
                    'string',
                    'text',
                    $secondary_language,
                    $string->string_text,
                    $translated,
                    true
                );
                $translated_count++;
            }

            usleep(100000); // 100ms delay
        }

        wp_send_json_success(array(
            'translated' => $translated_count,
            'message' => sprintf(__('Translated %d strings', 'translio'), $translated_count),
        ));
    }

    /**
     * Scan theme/plugin files for translatable strings
     */
    public function scan_files() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'translio'));
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'theme';
        $strings_found = 0;
        $files_scanned = 0;

        // Gettext function patterns to match
        $patterns = array(
            '/__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]+)[\'"])?\s*\)/',           // __('text', 'domain')
            '/_e\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]+)[\'"])?\s*\)/',           // _e('text', 'domain')
            '/esc_html__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]+)[\'"])?\s*\)/',   // esc_html__('text', 'domain')
            '/esc_html_e\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]+)[\'"])?\s*\)/',   // esc_html_e('text', 'domain')
            '/esc_attr__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]+)[\'"])?\s*\)/',   // esc_attr__('text', 'domain')
            '/esc_attr_e\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]+)[\'"])?\s*\)/',   // esc_attr_e('text', 'domain')
        );

        // Get directories to scan - use options instead of wp_get_theme() to avoid PHP 8.1+ warnings
        $directories = array();
        if ($type === 'theme') {
            // Get theme slugs from options (avoids wp_normalize_path deprecation warnings)
            $stylesheet = get_option('stylesheet');
            $template = get_option('template');
            // Use WP_CONTENT_DIR directly to avoid get_theme_root() which may call wp_normalize_path()
            $theme_root = WP_CONTENT_DIR . '/themes';

            $stylesheet_dir = $theme_root . '/' . $stylesheet;
            $template_dir = $theme_root . '/' . $template;

            $directories[] = array(
                'path' => $stylesheet_dir,
                'domain' => $stylesheet ?: 'theme',
            );
            // Include parent theme if child theme
            if ($template && $template !== $stylesheet) {
                $directories[] = array(
                    'path' => $template_dir,
                    'domain' => $template ?: 'theme',
                );
            }
        } else {
            // Scan active plugins
            $active_plugins = get_option('active_plugins', array());
            foreach ($active_plugins as $plugin) {
                $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin);
                if (is_dir($plugin_dir) && dirname($plugin) !== '.') {
                    // Try to get text domain from plugin header
                    $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
                    $domain = !empty($plugin_data['TextDomain']) ? $plugin_data['TextDomain'] : dirname($plugin);
                    $directories[] = array(
                        'path' => $plugin_dir,
                        'domain' => $domain,
                    );
                }
            }
        }

        // Scan each directory
        foreach ($directories as $dir_info) {
            $dir_path = $dir_info['path'];
            $default_domain = $dir_info['domain'];

            if (!is_dir($dir_path)) {
                continue;
            }

            // Get all PHP files recursively
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir_path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files_scanned++;
                    $content = file_get_contents($file->getPathname());

                    foreach ($patterns as $pattern) {
                        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                            foreach ($matches as $match) {
                                $text = $match[1];
                                $domain = isset($match[2]) && !empty($match[2]) ? $match[2] : $default_domain;

                                // Skip empty or very short strings
                                if (empty($text) || strlen($text) < 2) {
                                    continue;
                                }

                                // Skip strings that look like code or placeholders
                                if (preg_match('/^[\$%{}]|^[a-z_]+$/', $text)) {
                                    continue;
                                }

                                // Generate unique ID based on text + domain
                                $object_id = crc32($text . '|' . $domain);

                                // Record the string
                                Translio_DB::record_scanned_string($object_id, $text, $domain);
                                $strings_found++;
                            }
                        }
                    }
                }
            }
        }

        wp_send_json_success(array(
            'files_scanned' => $files_scanned,
            'strings_found' => $strings_found,
            'message' => sprintf(
                __('Scanned %d files, found %d translatable strings', 'translio'),
                $files_scanned,
                $strings_found
            ),
        ));
    }

    /**
     * Get Translation Memory suggestions
     */
    public function tm_suggest() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $text = isset($_POST['text']) ? sanitize_textarea_field($_POST['text']) : '';
        $min_similarity = isset($_POST['min_similarity']) ? absint($_POST['min_similarity']) : 70;

        if (empty($text)) {
            wp_send_json_error(array('message' => __('No text provided', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(array('message' => __('Secondary language not configured', 'translio')));
        }

        $matches = Translio_DB::tm_find_fuzzy($text, $secondary_language, $min_similarity, 5);

        $exact = Translio_DB::tm_find_exact($text, $secondary_language);
        if ($exact) {
            array_unshift($matches, array(
                'id' => $exact->id,
                'original' => $exact->original_content,
                'translated' => $exact->translated_content,
                'similarity' => 100,
                'object_type' => $exact->object_type,
                'field_name' => $exact->field_name,
            ));
        }

        wp_send_json_success(array(
            'matches' => $matches,
            'query_length' => mb_strlen($text),
        ));
    }

    /**
     * Get Translation Memory statistics
     */
    public function tm_stats() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_success(array('stats' => null));
        }

        $stats = Translio_DB::tm_get_stats($secondary_language);

        wp_send_json_success(array(
            'stats' => array(
                'total_entries' => (int) $stats->total_entries,
                'unique_originals' => (int) $stats->unique_originals,
                'total_chars' => (int) $stats->total_chars,
            ),
            'language' => $secondary_language,
        ));
    }

    /**
     * Translate single term field
     */
    public function translate_term_field() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $text = isset($_POST['text']) ? sanitize_textarea_field($_POST['text']) : '';

        if (!$term_id || !$field || empty($text)) {
            wp_send_json_error(array('message' => __('Invalid parameters', 'translio')));
        }

        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            wp_send_json_error(array('message' => __('Term not found', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(array('message' => __('Secondary language not configured', 'translio')));
        }

        $api = Translio_API::instance();
        $context = ($field === 'name') ? 'Category/tag/brand name' : 'Category/tag/brand description';
        $translated = $api->translate_text($text, $secondary_language, $context);

        if (is_wp_error($translated)) {
            wp_send_json_error(array('message' => $translated->get_error_message()));
        }

        Translio_DB::save_translation(
            $term_id,
            'term',
            $field,
            $secondary_language,
            $text,
            $translated,
            true
        );

        wp_send_json_success(array('translation' => $translated));
    }

    /**
     * Translate complete taxonomy term (name + description)
     */
    public function translate_taxonomy_term() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
        $fields = isset($_POST['fields']) ? (array) $_POST['fields'] : array('name');

        if (!$term_id) {
            wp_send_json_error(array('message' => __('Invalid term ID', 'translio')));
        }

        global $wpdb;
        $term_row = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, tt.taxonomy, tt.description
             FROM {$wpdb->terms} t
             JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE t.term_id = %d",
            $term_id
        ));

        if (!$term_row) {
            wp_send_json_error(array('message' => __('Term not found', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(array('message' => __('Secondary language not configured', 'translio')));
        }

        $api = Translio_API::instance();
        $translations = array();

        foreach ($fields as $field) {
            $field = sanitize_text_field($field);

            if ($field === 'name' && !empty($term_row->name)) {
                $context = 'Category/tag/brand name';
                $translated = $api->translate_text($term_row->name, $secondary_language, $context);

                if (!is_wp_error($translated)) {
                    Translio_DB::save_translation(
                        $term_id,
                        'term',
                        'name',
                        $secondary_language,
                        $term_row->name,
                        $translated,
                        true
                    );
                    $translations['name'] = $translated;
                }
            }

            if ($field === 'description' && !empty($term_row->description)) {
                $context = 'Category/tag/brand description';
                $translated = $api->translate_text($term_row->description, $secondary_language, $context);

                if (!is_wp_error($translated)) {
                    Translio_DB::save_translation(
                        $term_id,
                        'term',
                        'description',
                        $secondary_language,
                        $term_row->description,
                        $translated,
                        true
                    );
                    $translations['description'] = $translated;
                }
            }
        }

        if (empty($translations)) {
            wp_send_json_error(array('message' => __('No fields to translate or translation failed', 'translio')));
        }

        wp_send_json_success(array(
            'translations' => $translations,
            'term_id' => $term_id
        ));
    }

    /**
     * Translate all fields of a media item
     */
    public function translate_media_all_fields() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

        if (!$attachment_id) {
            wp_send_json_error(array('message' => __('Invalid attachment ID', 'translio')));
        }

        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_send_json_error(array('message' => __('Attachment not found', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(array('message' => __('Secondary language not configured', 'translio')));
        }

        $api = Translio_API::instance();
        $translations = array();
        $mime_type = get_post_mime_type($attachment_id);
        $is_image = strpos($mime_type, 'image/') === 0;

        $alt_text = $is_image ? get_post_meta($attachment_id, '_wp_attachment_image_alt', true) : '';
        $title = $attachment->post_title;
        $caption = $attachment->post_excerpt;
        $description = $attachment->post_content;

        if ($is_image && !empty($alt_text)) {
            $existing = Translio_DB::get_translation($attachment_id, 'attachment', 'alt', $secondary_language);
            if (!$existing || empty($existing->translated_content)) {
                $translated = $api->translate_text($alt_text, $secondary_language, 'Image alt text');
                if (!is_wp_error($translated)) {
                    Translio_DB::save_translation($attachment_id, 'attachment', 'alt', $secondary_language, $alt_text, $translated, true);
                    $translations['alt'] = $translated;
                }
            }
        }

        if (!empty($title)) {
            $existing = Translio_DB::get_translation($attachment_id, 'attachment', 'title', $secondary_language);
            if (!$existing || empty($existing->translated_content)) {
                $translated = $api->translate_text($title, $secondary_language, 'Media title');
                if (!is_wp_error($translated)) {
                    Translio_DB::save_translation($attachment_id, 'attachment', 'title', $secondary_language, $title, $translated, true);
                    $translations['title'] = $translated;
                }
            }
        }

        if (!empty($caption)) {
            $existing = Translio_DB::get_translation($attachment_id, 'attachment', 'caption', $secondary_language);
            if (!$existing || empty($existing->translated_content)) {
                $translated = $api->translate_text($caption, $secondary_language, 'Media caption');
                if (!is_wp_error($translated)) {
                    Translio_DB::save_translation($attachment_id, 'attachment', 'caption', $secondary_language, $caption, $translated, true);
                    $translations['caption'] = $translated;
                }
            }
        }

        if (!empty($description)) {
            $existing = Translio_DB::get_translation($attachment_id, 'attachment', 'description', $secondary_language);
            if (!$existing || empty($existing->translated_content)) {
                $translated = $api->translate_text($description, $secondary_language, 'Media description');
                if (!is_wp_error($translated)) {
                    Translio_DB::save_translation($attachment_id, 'attachment', 'description', $secondary_language, $description, $translated, true);
                    $translations['description'] = $translated;
                }
            }
        }

        wp_send_json_success(array(
            'translations' => $translations,
            'attachment_id' => $attachment_id
        ));
    }

    /**
     * Translate WooCommerce attribute label
     */
    public function translate_wc_attribute() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $attr_id = isset($_POST['attr_id']) ? intval($_POST['attr_id']) : 0;
        $original = isset($_POST['original']) ? sanitize_text_field($_POST['original']) : '';

        if (!$attr_id || empty($original)) {
            wp_send_json_error(array('message' => __('Invalid request', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (!$secondary_language) {
            wp_send_json_error(array('message' => __('Secondary language not set', 'translio')));
        }

        $api = Translio_API::instance();
        $translated = $api->translate_text($original, $secondary_language);

        if (is_wp_error($translated)) {
            wp_send_json_error(array('message' => $translated->get_error_message()));
        }

        Translio_DB::save_translation(
            $attr_id,
            'wc_attribute',
            'label',
            $secondary_language,
            $original,
            $translated,
            true
        );

        wp_send_json_success(array(
            'translation' => $translated,
            'message' => __('Translated', 'translio'),
        ));
    }

    /**
     * Save WC attribute translation
     */
    public function save_wc_attribute() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $attr_id = isset($_POST['attr_id']) ? intval($_POST['attr_id']) : 0;
        $translation = isset($_POST['translation']) ? sanitize_text_field($_POST['translation']) : '';

        if (!$attr_id) {
            wp_send_json_error(array('message' => __('Invalid attribute ID', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (!$secondary_language) {
            wp_send_json_error(array('message' => __('Secondary language not set', 'translio')));
        }

        // Get original label
        global $wpdb;
        $attr = $wpdb->get_row($wpdb->prepare(
            "SELECT attribute_label FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d",
            $attr_id
        ));

        if (!$attr) {
            wp_send_json_error(array('message' => __('Attribute not found', 'translio')));
        }

        Translio_DB::save_translation(
            $attr_id,
            'wc_attribute',
            'label',
            $secondary_language,
            $attr->attribute_label,
            $translation,
            false
        );

        wp_send_json_success(array('message' => __('Saved', 'translio')));
    }

    /**
     * Translate site option
     */
    public function translate_option() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $option_name = isset($_POST['option_name']) ? sanitize_text_field($_POST['option_name']) : '';
        $original = isset($_POST['original']) ? sanitize_text_field($_POST['original']) : '';

        if (empty($option_name) || empty($original)) {
            wp_send_json_error(array('message' => __('Invalid request', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (!$secondary_language) {
            wp_send_json_error(array('message' => __('Secondary language not set', 'translio')));
        }

        $api = Translio_API::instance();
        $translated = $api->translate_text($original, $secondary_language);

        if (is_wp_error($translated)) {
            wp_send_json_error(array('message' => $translated->get_error_message()));
        }

        Translio_DB::save_translation(
            1,
            'option',
            $option_name,
            $secondary_language,
            $original,
            $translated,
            true
        );

        wp_send_json_success(array(
            'translation' => $translated,
            'message' => __('Translated', 'translio'),
        ));
    }

    /**
     * Save Elementor translation
     */
    public function save_elementor_translation() {
        check_ajax_referer('translio_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $element_id = isset($_POST['element_id']) ? sanitize_text_field($_POST['element_id']) : '';
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $original = isset($_POST['original']) ? wp_kses_post($_POST['original']) : '';
        $translated = isset($_POST['translated']) ? wp_kses_post($_POST['translated']) : '';

        if (!$post_id || !$element_id || !$field) {
            wp_send_json_error(__('Missing required fields.', 'translio'));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(__('No secondary language configured.', 'translio'));
        }

        $result = Translio_Elementor::save_translation($post_id, $element_id, $field, $secondary_language, $original, $translated);

        if ($result) {
            Translio_Elementor::clear_cache($post_id);
            wp_send_json_success(array('saved' => true));
        } else {
            wp_send_json_error(__('Failed to save translation.', 'translio'));
        }
    }

    /**
     * Translate single Elementor field via API
     */
    public function translate_elementor_field() {
        check_ajax_referer('translio_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $element_id = isset($_POST['element_id']) ? sanitize_text_field($_POST['element_id']) : '';
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $original = isset($_POST['original']) ? wp_kses_post($_POST['original']) : '';

        if (!$post_id || !$element_id || !$field || !$original) {
            wp_send_json_error(__('Missing required fields.', 'translio'));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(__('No secondary language configured.', 'translio'));
        }

        $api = Translio_API::instance();
        $context = sprintf('Elementor %s widget, %s field',
            Translio_Elementor::get_widget_label(sanitize_text_field($_POST['widget_type'] ?? 'widget')),
            Translio_Elementor::get_field_label($field)
        );

        $translated = $api->translate_text($original, $secondary_language, $context);

        if (is_wp_error($translated)) {
            wp_send_json_error($translated->get_error_message());
        }

        Translio_Elementor::save_translation($post_id, $element_id, $field, $secondary_language, $original, $translated);
        Translio_Elementor::clear_cache($post_id);

        wp_send_json_success(array('translated' => $translated));
    }

    /**
     * Translate all Elementor fields for a post
     */
    public function translate_elementor_all() {
        check_ajax_referer('translio_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(__('Invalid post ID.', 'translio'));
        }

        $secondary_language = $this->get_language_from_request();
        if (empty($secondary_language)) {
            wp_send_json_error(__('No secondary language configured.', 'translio'));
        }

        $strings = Translio_Elementor::extract_translatable_strings($post_id);
        $api = Translio_API::instance();
        $translated_count = 0;

        foreach ($strings as $string) {
            $existing = Translio_Elementor::get_translation($post_id, $string['element_id'], $string['field'], $secondary_language);
            if ($existing && !empty($existing->translated_content)) {
                continue;
            }

            $context = sprintf('Elementor %s widget, %s field',
                Translio_Elementor::get_widget_label($string['widget_type']),
                Translio_Elementor::get_field_label($string['field'])
            );

            $translated = $api->translate_text($string['content'], $secondary_language, $context);

            if (!is_wp_error($translated)) {
                Translio_Elementor::save_translation($post_id, $string['element_id'], $string['field'], $secondary_language, $string['content'], $translated);
                $translated_count++;
            }
        }

        Translio_Elementor::clear_cache($post_id);

        wp_send_json_success(array(
            'translated' => $translated_count,
            'total' => count($strings)
        ));
    }

    /**
     * Translate single CF7 field
     */
    public function translate_cf7_field() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $original = isset($_POST['original']) ? wp_unslash($_POST['original']) : '';

        if (!$form_id || empty($field) || empty($original)) {
            wp_send_json_error(array('message' => __('Invalid request', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (!$secondary_language) {
            wp_send_json_error(array('message' => __('Secondary language not set', 'translio')));
        }

        $api = Translio_API::instance();

        $context = '';
        if ($field === 'form') {
            $context = 'This is a Contact Form 7 form template. Translate only the human-readable text (labels, placeholders, button text). Keep all CF7 shortcode tags like [text your-name], [email your-email], [submit "Send"] exactly as they are - only translate the text inside quotes if present.';
        } elseif (strpos($field, 'mail_') === 0) {
            $context = 'This is an email template. Translate the text but keep placeholders like [your-name], [your-email] exactly as they are.';
        }

        $translated = $api->translate_text($original, $secondary_language, $context);

        if (is_wp_error($translated)) {
            wp_send_json_error(array('message' => $translated->get_error_message()));
        }

        $object_type = 'cf7_form';
        $field_name = $field;

        if (strpos($field, 'mail_') === 0) {
            $object_type = 'cf7_mail';
            $field_name = str_replace('mail_', '', $field);
        } elseif (strpos($field, 'message_') === 0) {
            $object_type = 'cf7_message';
            $field_name = str_replace('message_', '', $field);
        }

        Translio_DB::save_translation(
            $form_id,
            $object_type,
            $field_name,
            $secondary_language,
            $original,
            $translated,
            true
        );

        wp_send_json_success(array(
            'translation' => $translated,
            'message' => __('Translated', 'translio'),
        ));
    }

    /**
     * Translate all CF7 form fields
     */
    public function translate_cf7_all() {
        $skip_nonce = isset($_POST['skip_nonce_check']) && $_POST['skip_nonce_check'];
        if (!$skip_nonce) {
            check_ajax_referer('translio_nonce', 'nonce');
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;

        if (!$form_id) {
            wp_send_json_error(array('message' => __('Invalid form ID', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        if (!$secondary_language) {
            wp_send_json_error(array('message' => __('Secondary language not set', 'translio')));
        }

        $form = get_post($form_id);
        if (!$form) {
            wp_send_json_error(array('message' => __('Form not found', 'translio')));
        }

        $form_content = '';
        $mail = array();
        $messages = array();

        if (class_exists('WPCF7_ContactForm')) {
            $cf7_form = WPCF7_ContactForm::get_instance($form_id);
            if ($cf7_form) {
                $form_content = $cf7_form->prop('form');
                $mail = $cf7_form->prop('mail');
                $messages = $cf7_form->prop('messages');
            }
        }

        if (empty($form_content)) {
            $form_content = get_post_meta($form_id, '_form', true);
            if (empty($form_content) && !empty($form->post_content)) {
                $form_content = $form->post_content;
            }
        }
        if (empty($mail) || !is_array($mail)) {
            $mail = get_post_meta($form_id, '_mail', true);
            if (!is_array($mail)) $mail = array();
        }
        if (empty($messages) || !is_array($messages)) {
            $messages = get_post_meta($form_id, '_messages', true);
            if (!is_array($messages)) $messages = array();
        }

        if (empty($form_content) && empty($form->post_title)) {
            wp_send_json_error(array('message' => sprintf('Form %d has no translatable content', $form_id)));
        }

        $api = Translio_API::instance();
        $translations = array();
        $originals = array();

        $to_translate = array();

        if (!empty($form->post_title)) {
            $to_translate[] = array(
                'id' => 'title',
                'text' => $form->post_title,
                'context' => 'Contact form title'
            );
            $originals['title'] = $form->post_title;
        }

        if (!empty($form_content)) {
            $to_translate[] = array(
                'id' => 'form',
                'text' => $form_content,
                'context' => Translio_Utils::get_translation_context('cf7_form')
            );
            $originals['form'] = $form_content;
        }

        if (!empty($mail['subject'])) {
            $to_translate[] = array(
                'id' => 'mail_subject',
                'text' => $mail['subject'],
                'context' => Translio_Utils::get_translation_context('cf7_mail')
            );
            $originals['mail_subject'] = $mail['subject'];
        }
        if (!empty($mail['body'])) {
            $to_translate[] = array(
                'id' => 'mail_body',
                'text' => $mail['body'],
                'context' => Translio_Utils::get_translation_context('cf7_mail')
            );
            $originals['mail_body'] = $mail['body'];
        }

        if (!empty($messages)) {
            foreach ($messages as $key => $message) {
                if (!empty($message)) {
                    $to_translate[] = array(
                        'id' => 'message_' . $key,
                        'text' => $message,
                        'context' => Translio_Utils::get_translation_context('cf7_message')
                    );
                    $originals['message_' . $key] = $message;
                }
            }
        }

        $translated_batch = $api->translate_batch($to_translate, $secondary_language);

        if (is_wp_error($translated_batch)) {
            wp_send_json_error(array('message' => $translated_batch->get_error_message()));
        }

        foreach ($translated_batch as $field => $translated) {
            if (empty($translated)) continue;

            $object_type = 'cf7_form';
            $field_name = $field;
            $original = isset($originals[$field]) ? $originals[$field] : '';

            if (strpos($field, 'mail_') === 0) {
                $object_type = 'cf7_mail';
                $field_name = str_replace('mail_', '', $field);
            } elseif (strpos($field, 'message_') === 0) {
                $object_type = 'cf7_message';
                $field_name = str_replace('message_', '', $field);
            }

            Translio_DB::save_translation(
                $form_id,
                $object_type,
                $field_name,
                $secondary_language,
                $original,
                $translated,
                true
            );

            $translations[$field] = $translated;
        }

        wp_send_json_success(array(
            'message' => __('All fields translated', 'translio'),
            'translations' => $translations,
        ));
    }

    // ========================================
    // DIVI AJAX HANDLERS
    // ========================================

    /**
     * Save Divi translations
     */
    public function save_divi_translations() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $translations = isset($_POST['translations']) ? $_POST['translations'] : array();

        if (!$post_id || empty($translations)) {
            wp_send_json_error(array('message' => __('Missing data', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        $fields = Translio_Divi::extract_fields($post_id);
        $saved = 0;

        foreach ($translations as $field_id => $translated) {
            $translated = sanitize_textarea_field(wp_unslash($translated));

            if (empty($translated)) continue;

            // Get original content
            $original = isset($fields[$field_id]) ? $fields[$field_id]['value'] : '';

            if (!empty($original)) {
                Translio_DB::save_translation(
                    $post_id,
                    'divi',
                    $field_id,
                    $secondary_language,
                    $original,
                    $translated,
                    false // Not auto-translated
                );
                $saved++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Saved %d translations', 'translio'), $saved),
            'saved' => $saved,
        ));
    }

    /**
     * Translate single Divi field
     */
    public function translate_divi_field() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $field_id = isset($_POST['field_id']) ? sanitize_text_field($_POST['field_id']) : '';

        if (!$post_id || empty($field_id)) {
            wp_send_json_error(array('message' => __('Missing data', 'translio')));
        }

        $fields = Translio_Divi::extract_fields($post_id);

        if (!isset($fields[$field_id])) {
            wp_send_json_error(array('message' => __('Field not found', 'translio')));
        }

        $field = $fields[$field_id];
        $secondary_language = $this->get_language_from_request();

        $api = Translio_API::instance();

        // Determine context based on field
        $module_name = str_replace(array('et_pb_', '_'), array('', ' '), $field['module']);
        $context = "Divi " . ucwords($module_name) . " - " . $field['field'];

        $translated = $api->translate_text($field['value'], $secondary_language, $context);

        if (is_wp_error($translated)) {
            wp_send_json_error(array('message' => $translated->get_error_message()));
        }

        // Save translation
        Translio_DB::save_translation(
            $post_id,
            'divi',
            $field_id,
            $secondary_language,
            $field['value'],
            $translated,
            true
        );

        wp_send_json_success(array(
            'translation' => $translated,
            'field_id' => $field_id,
        ));
    }

    /**
     * Translate all Divi fields for a post
     */
    public function translate_divi_all() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Post ID required', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        $fields = Translio_Divi::extract_fields($post_id);

        if (empty($fields)) {
            wp_send_json_error(array('message' => __('No Divi content found', 'translio')));
        }

        $api = Translio_API::instance();
        $translations = array();
        $errors = array();

        // Prepare batch translation
        $to_translate = array();
        foreach ($fields as $field_id => $field) {
            $module_name = str_replace(array('et_pb_', '_'), array('', ' '), $field['module']);
            $context = "Divi " . ucwords($module_name) . " - " . $field['field'];

            $to_translate[] = array(
                'id' => $field_id,
                'text' => $field['value'],
                'context' => $context,
            );
        }

        // Batch translate
        $batch_result = $api->translate_batch($to_translate, $secondary_language);

        if (is_wp_error($batch_result)) {
            wp_send_json_error(array('message' => $batch_result->get_error_message()));
        }

        // Save translations
        foreach ($fields as $field_id => $field) {
            if (isset($batch_result[$field_id])) {
                Translio_DB::save_translation(
                    $post_id,
                    'divi',
                    $field_id,
                    $secondary_language,
                    $field['value'],
                    $batch_result[$field_id],
                    true
                );
                $translations[$field_id] = $batch_result[$field_id];
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Translated %d fields', 'translio'), count($translations)),
            'translations' => $translations,
        ));
    }

    // ========================================
    // AVADA AJAX HANDLERS
    // ========================================

    /**
     * Save Avada translations
     */
    public function save_avada_translations() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $translations = isset($_POST['translations']) ? $_POST['translations'] : array();

        if (!$post_id || empty($translations)) {
            wp_send_json_error(array('message' => __('Missing data', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        $fields = Translio_Avada::extract_fields($post_id);
        $saved = 0;

        foreach ($translations as $field_id => $translated) {
            $translated = sanitize_textarea_field(wp_unslash($translated));

            if (empty($translated)) continue;

            // Get original content
            $original = isset($fields[$field_id]) ? $fields[$field_id]['value'] : '';

            if (!empty($original)) {
                Translio_DB::save_translation(
                    $post_id,
                    'avada',
                    $field_id,
                    $secondary_language,
                    $original,
                    $translated,
                    false // Not auto-translated
                );
                $saved++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Saved %d translations', 'translio'), $saved),
            'saved' => $saved,
        ));
    }

    /**
     * Translate single Avada field
     */
    public function translate_avada_field() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $field_id = isset($_POST['field_id']) ? sanitize_text_field($_POST['field_id']) : '';

        if (!$post_id || empty($field_id)) {
            wp_send_json_error(array('message' => __('Missing data', 'translio')));
        }

        $fields = Translio_Avada::extract_fields($post_id);

        if (!isset($fields[$field_id])) {
            wp_send_json_error(array('message' => __('Field not found', 'translio')));
        }

        $field = $fields[$field_id];
        $secondary_language = $this->get_language_from_request();

        $api = Translio_API::instance();

        // Determine context based on field
        $element_name = str_replace(array('fusion_', '_'), array('', ' '), $field['element']);
        $context = "Avada " . ucwords($element_name) . " - " . $field['field'];

        $translated = $api->translate_text($field['value'], $secondary_language, $context);

        if (is_wp_error($translated)) {
            wp_send_json_error(array('message' => $translated->get_error_message()));
        }

        // Save translation
        Translio_DB::save_translation(
            $post_id,
            'avada',
            $field_id,
            $secondary_language,
            $field['value'],
            $translated,
            true
        );

        wp_send_json_success(array(
            'translation' => $translated,
            'field_id' => $field_id,
        ));
    }

    /**
     * Translate all Avada fields for a post
     */
    public function translate_avada_all() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Post ID required', 'translio')));
        }

        $secondary_language = $this->get_language_from_request();
        $fields = Translio_Avada::extract_fields($post_id);

        if (empty($fields)) {
            wp_send_json_error(array('message' => __('No Avada content found', 'translio')));
        }

        $api = Translio_API::instance();
        $translations = array();

        // Prepare batch translation
        $to_translate = array();
        foreach ($fields as $field_id => $field) {
            $element_name = str_replace(array('fusion_', '_'), array('', ' '), $field['element']);
            $context = "Avada " . ucwords($element_name) . " - " . $field['field'];

            $to_translate[] = array(
                'id' => $field_id,
                'text' => $field['value'],
                'context' => $context,
            );
        }

        // Batch translate
        $batch_result = $api->translate_batch($to_translate, $secondary_language);

        if (is_wp_error($batch_result)) {
            wp_send_json_error(array('message' => $batch_result->get_error_message()));
        }

        // Save translations
        foreach ($fields as $field_id => $field) {
            if (isset($batch_result[$field_id])) {
                Translio_DB::save_translation(
                    $post_id,
                    'avada',
                    $field_id,
                    $secondary_language,
                    $field['value'],
                    $batch_result[$field_id],
                    true
                );
                $translations[$field_id] = $batch_result[$field_id];
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Translated %d fields', 'translio'), count($translations)),
            'translations' => $translations,
        ));
    }

    /**
     * Import test pages for development/testing
     */
    public function import_test_pages() {
        check_ajax_referer('translio_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $pages = array(
            array(
                'title' => 'Artificial Intelligence',
                'content' => '<p><strong>Artificial intelligence (AI)</strong> is the capability of computational systems to perform tasks typically associated with human intelligence, such as learning, reasoning, problem-solving, perception, and decision-making.</p>
<p>It is a field of research in computer science that develops and studies methods and software that enable machines to perceive their environment and use learning and intelligence to take actions that maximize their chances of achieving defined goals.</p>
<h2>History and Development</h2>
<p>The field of AI research was founded at a workshop held at Dartmouth College in 1956. Since then, it has experienced several waves of optimism, followed by disappointment and loss of funding, followed by new approaches, success, and renewed funding.</p>
<h2>Applications</h2>
<p>AI is used in a wide variety of applications including: natural language processing, image and speech recognition, autonomous vehicles, medical diagnosis, financial trading, and many more.</p>'
            ),
            array(
                'title' => 'Climate Change',
                'content' => '<p><strong>Present-day climate change</strong> includes both global warming—the ongoing increase in global average temperature—and its wider effects on Earth\'s climate system.</p>
<p>Climate change in a broader sense also includes previous long-term changes to Earth\'s climate. The modern-day rise in global temperatures is driven by human activities, especially fossil fuel burning since the Industrial Revolution.</p>
<h2>Causes</h2>
<p>Fossil fuel use, deforestation, and some agricultural and industrial practices release greenhouse gases. These gases absorb some of the heat that the Earth radiates after it warms from sunlight, warming the lower atmosphere.</p>
<h2>Effects</h2>
<p>Climate change has led to rising sea levels, more frequent extreme weather events, changes in precipitation patterns, and shifts in ecosystems worldwide.</p>'
            ),
            array(
                'title' => 'Renewable Energy',
                'content' => '<p><strong>Renewable energy</strong> is energy made from renewable natural resources that are replenished on a human timescale. The most widely used renewable energy types are solar energy, wind power, and hydropower.</p>
<h2>Types of Renewable Energy</h2>
<ul>
<li><strong>Solar Energy</strong> - Captured through photovoltaic panels or solar thermal systems</li>
<li><strong>Wind Power</strong> - Generated by wind turbines on land or offshore</li>
<li><strong>Hydropower</strong> - Produced by flowing water in rivers or dams</li>
<li><strong>Geothermal</strong> - Heat extracted from the Earth\'s interior</li>
</ul>
<h2>Benefits</h2>
<p>Renewable energy is often deployed together with further electrification. This has several benefits: electricity can move heat and vehicles efficiently and is clean at the point of consumption.</p>'
            ),
            array(
                'title' => 'Space Exploration',
                'content' => '<p><strong>Space exploration</strong> is the physical investigation of outer space by uncrewed robotic space probes and through human spaceflight.</p>
<h2>History</h2>
<p>The exploration of space began with the launch of Sputnik 1 by the Soviet Union in 1957, marking the start of the Space Age. This was followed by the first human in space, Yuri Gagarin, in 1961, and the Apollo 11 Moon landing in 1969.</p>
<h2>Current Missions</h2>
<ul>
<li>The International Space Station (ISS)</li>
<li>Mars exploration with rovers and planned human missions</li>
<li>Asteroid and comet studies</li>
<li>Deep space telescopes like James Webb</li>
</ul>'
            ),
            array(
                'title' => 'Quantum Computing',
                'content' => '<p><strong>A quantum computer</strong> is a computer that exploits superposed and entangled states. Quantum computers can be viewed as sampling from quantum systems that evolve in ways that may be described as operating on an enormous number of possibilities simultaneously.</p>
<h2>How It Works</h2>
<ul>
<li><strong>Superposition</strong> - A qubit can be in multiple states at once</li>
<li><strong>Entanglement</strong> - Qubits can be correlated with each other</li>
<li><strong>Interference</strong> - Quantum states can be combined to amplify correct answers</li>
</ul>
<h2>Applications</h2>
<p>A large-scale quantum computer could break some widely used public-key cryptographic schemes and aid physicists in performing physical simulations.</p>'
            ),
        );

        $created = 0;
        $results = array();

        foreach ($pages as $page_data) {
            $existing = get_page_by_title($page_data['title'], OBJECT, 'page');

            if ($existing) {
                $results[] = array('status' => 'exists', 'title' => $page_data['title'], 'id' => $existing->ID);
                continue;
            }

            $page_id = wp_insert_post(array(
                'post_title'   => $page_data['title'],
                'post_content' => $page_data['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => get_current_user_id(),
            ));

            if ($page_id && !is_wp_error($page_id)) {
                $results[] = array('status' => 'created', 'title' => $page_data['title'], 'id' => $page_id);
                $created++;
            } else {
                $results[] = array('status' => 'error', 'title' => $page_data['title'], 'id' => 0);
            }
        }

        wp_send_json_success(array(
            'message' => sprintf('Created %d new pages', $created),
            'created' => $created,
            'results' => $results,
        ));
    }
}
