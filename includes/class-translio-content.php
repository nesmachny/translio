<?php
/**
 * Translio Content Class
 * Handles frontend content filtering and translation display
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Content {

    private $secondary_language;

    /**
     * Recursion guard flags to prevent infinite loops in meta filters
     */
    private static $filtering_alt_meta = false;
    private static $filtering_post_meta = false;

    public function __construct() {
        // Check if any secondary languages are configured
        $secondary_languages = translio()->get_secondary_languages();
        if (empty($secondary_languages)) {
            return;
        }

        // Store first language for backward compatibility property access
        $this->secondary_language = $secondary_languages[0];

        // Content filters
        add_filter('the_title', array($this, 'filter_title'), 10, 2);
        add_filter('the_content', array($this, 'filter_content'), 10);
        add_filter('the_excerpt', array($this, 'filter_excerpt'), 10);
        add_filter('get_the_excerpt', array($this, 'filter_get_excerpt'), 10, 2);
        add_filter('single_post_title', array($this, 'filter_single_title'), 10, 2);
        add_filter('the_title_rss', array($this, 'filter_rss_title'), 10);

        // SEO meta
        add_filter('document_title_parts', array($this, 'filter_document_title'), 10);
        add_filter('wp_title', array($this, 'filter_wp_title'), 10, 2);

        // SEO plugins support (Yoast, Rank Math, AIOSEO)
        add_filter('wpseo_title', array($this, 'filter_seo_title'), 10);
        add_filter('wpseo_metadesc', array($this, 'filter_seo_description'), 10);
        add_filter('rank_math/frontend/title', array($this, 'filter_seo_title'), 10);
        add_filter('rank_math/frontend/description', array($this, 'filter_seo_description'), 10);
        add_filter('aioseo_title', array($this, 'filter_seo_title'), 10);
        add_filter('aioseo_description', array($this, 'filter_seo_description'), 10);

        // Term/category filters
        add_filter('single_term_title', array($this, 'filter_term_title'), 10);
        add_filter('term_name', array($this, 'filter_term_name'), 10, 2);
        add_filter('get_term', array($this, 'filter_get_term'), 10, 2);

        // WooCommerce product attributes
        add_filter('woocommerce_attribute', array($this, 'filter_wc_attribute'), 10, 3);
        add_filter('woocommerce_attribute_label', array($this, 'filter_wc_attribute_label'), 10, 3);

        // Site options (blogname, blogdescription)
        add_filter('option_blogname', array($this, 'filter_blogname'), 10);
        add_filter('option_blogdescription', array($this, 'filter_blogdescription'), 10);

        // Media alt text, caption, title, description
        add_filter('wp_get_attachment_image_attributes', array($this, 'filter_attachment_image_attributes'), 10, 3);
        add_filter('get_post_metadata', array($this, 'filter_attachment_alt_meta'), 10, 4);
        add_filter('wp_get_attachment_caption', array($this, 'filter_attachment_caption'), 10, 2);
        add_filter('the_title', array($this, 'filter_attachment_title'), 10, 2);
        add_filter('prepend_attachment', array($this, 'filter_attachment_description'), 10, 2);

        // Preload translations when post is set up (for galleries, featured images, etc.)
        add_action('the_post', array($this, 'preload_post_translations'), 10);

        // Navigation menu
        add_filter('wp_nav_menu_objects', array($this, 'filter_nav_menu_items'), 10, 2);

        // Widget titles
        add_filter('widget_title', array($this, 'filter_widget_title'), 10);

        // Widget content (text, custom HTML widgets)
        add_filter('widget_text', array($this, 'filter_widget_text'), 10, 3);
        add_filter('widget_text_content', array($this, 'filter_widget_text'), 10, 3);
        add_filter('widget_custom_html_content', array($this, 'filter_widget_html'), 10, 3);

        // Block rendering - for wp_navigation blocks
        add_filter('render_block', array($this, 'filter_render_block'), 10, 2);

        // Pre-render block - modify block attributes before rendering
        add_filter('pre_render_block', array($this, 'filter_pre_render_block'), 10, 2);

        // Theme string translations via gettext - PERMANENTLY DISABLED
        // gettext is called thousands of times per request, causing major slowdown
        // String translations will be handled via output buffer or manual scanning instead

        // Final content filter for page-list links in navigation
        add_filter('render_block_core/navigation', array($this, 'filter_navigation_block'), 10, 2);

        // Final output buffer filter for footer and other template parts
        add_action('template_redirect', array($this, 'start_output_buffer'), 1);
        add_action('shutdown', array($this, 'end_output_buffer'), 0);

        // Language switcher
        add_shortcode('translio_switcher', array($this, 'render_language_switcher'));
        add_action('wp_body_open', array($this, 'render_floating_switcher'));
        add_action('wp_head', array($this, 'add_switcher_styles'));

        // Elementor content filter
        if (Translio_Elementor::is_active()) {
            add_filter('elementor/frontend/the_content', array($this, 'filter_elementor_content'), 10);
        }

        // Contact Form 7 filter
        if (class_exists('WPCF7')) {
            add_filter('wpcf7_contact_form_properties', array($this, 'filter_cf7_form_properties'), 10, 2);
        }

        // Custom meta fields (ACF, theme meta boxes) filter
        add_filter('get_post_metadata', array($this, 'filter_post_meta'), 10, 4);
    }

    /**
     * Add CSS styles for language switcher
     */
    public function add_switcher_styles() {
        if (is_admin()) {
            return;
        }
        ?>
        <style id="translio-switcher-css">
        .translio-switcher {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .translio-switcher a {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            background: #f0f0f0;
            transition: all 0.2s ease;
        }
        .translio-switcher a:hover {
            background: #e0e0e0;
        }
        .translio-switcher a.active {
            background: #0073aa;
            color: #fff;
        }
        .translio-switcher .flag {
            width: 20px;
            height: 14px;
            border-radius: 2px;
            object-fit: cover;
        }

        /* Floating switcher */
        .translio-floating-switcher {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            background: #fff;
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        }
        .translio-floating-switcher .translio-switcher {
            flex-direction: column;
        }
        .translio-floating-switcher .translio-switcher a {
            width: 100%;
            justify-content: flex-start;
        }

        /* Admin bar adjustment */
        .admin-bar .translio-floating-switcher {
            top: 112px;
        }
        </style>
        <?php
    }

    /**
     * Render floating language switcher
     */
    public function render_floating_switcher() {
        if (is_admin()) {
            return;
        }

        // Check if floating switcher is enabled (default: yes)
        $show_floating = get_option('translio_show_floating_switcher', '1');
        if ($show_floating !== '1') {
            return;
        }

        echo '<div class="translio-floating-switcher">';
        echo $this->render_language_switcher(array('style' => 'flags'));
        echo '</div>';
    }

    /**
     * Render language switcher shortcode
     *
     * @param array $atts Shortcode attributes
     *   - style: 'flags', 'text', 'both' (default: 'both')
     *   - class: additional CSS class
     * @return string HTML output
     */
    public function render_language_switcher($atts = array()) {
        $atts = shortcode_atts(array(
            'style' => 'both',
            'class' => '',
        ), $atts);

        $default_lang = translio()->get_setting('default_language');
        $secondary_lang = $this->get_translation_language();
        $current_lang = translio()->get_current_language();

        if (empty($secondary_lang)) {
            return '';
        }

        $languages = Translio::get_available_languages();

        // Get current page URL for both languages
        $default_url = $this->get_current_url_for_language($default_lang);
        $secondary_url = $this->get_current_url_for_language($secondary_lang);

        $output = '<div class="translio-switcher ' . esc_attr($atts['class']) . '">';

        // Default language link
        $output .= $this->render_language_link(
            $default_lang,
            $languages[$default_lang],
            $default_url,
            $current_lang === $default_lang,
            $atts['style']
        );

        // Secondary language link
        $output .= $this->render_language_link(
            $secondary_lang,
            $languages[$secondary_lang],
            $secondary_url,
            $current_lang === $secondary_lang,
            $atts['style']
        );

        $output .= '</div>';

        return $output;
    }

    /**
     * Render single language link
     */
    private function render_language_link($code, $lang_data, $url, $is_active, $style) {
        $class = $is_active ? 'active' : '';
        $flag = $this->get_flag_emoji($code);

        $output = '<a href="' . esc_url($url) . '" class="' . $class . '" hreflang="' . esc_attr($code) . '">';

        if ($style === 'flags' || $style === 'both') {
            $output .= '<span class="flag">' . $flag . '</span>';
        }

        if ($style === 'text' || $style === 'both') {
            $output .= '<span class="lang-name">' . esc_html($lang_data['native_name'] ?? $lang_data['name']) . '</span>';
        }

        $output .= '</a>';

        return $output;
    }

    /**
     * Get flag emoji for language code
     */
    private function get_flag_emoji($lang_code) {
        $flags = array(
            'en' => '🇬🇧',
            'es' => '🇪🇸',
            'zh' => '🇨🇳',
            'hi' => '🇮🇳',
            'ar' => '🇸🇦',
            'pt' => '🇵🇹',
            'ru' => '🇷🇺',
            'ja' => '🇯🇵',
            'de' => '🇩🇪',
            'fr' => '🇫🇷',
            'ko' => '🇰🇷',
            'it' => '🇮🇹',
            'tr' => '🇹🇷',
            'vi' => '🇻🇳',
            'pl' => '🇵🇱',
            'uk' => '🇺🇦',
            'nl' => '🇳🇱',
            'th' => '🇹🇭',
            'id' => '🇮🇩',
            'he' => '🇮🇱',
        );

        return $flags[$lang_code] ?? '🌐';
    }

    /**
     * Get current page URL for specific language
     */
    private function get_current_url_for_language($target_lang) {
        $default_lang = translio()->get_setting('default_language');
        $secondary_lang = $this->get_translation_language();
        $home_url = home_url('/');

        // Get current URL
        $current_url = home_url(add_query_arg(array()));

        // Remove existing language prefix if present
        $path = str_replace($home_url, '', $current_url);

        // Check if path starts with secondary language
        if (strpos($path, $secondary_lang . '/') === 0) {
            $path = substr($path, strlen($secondary_lang) + 1);
        }

        // Build URL for target language
        if ($target_lang === $default_lang) {
            return $home_url . ltrim($path, '/');
        } else {
            return $home_url . $target_lang . '/' . ltrim($path, '/');
        }
    }

    /**
     * Start output buffering to catch footer content
     */
    public function start_output_buffer() {
        if ($this->is_translated_request()) {
            ob_start(array($this, 'translate_output_buffer'));
        }
    }

    /**
     * End output buffering
     */
    public function end_output_buffer() {
        if ($this->is_translated_request() && ob_get_level() > 0) {
            ob_end_flush();
        }
    }

    /**
     * Translate content in output buffer
     */
    public function translate_output_buffer($html) {
        $html = (string) $html;
        if (empty($html)) {
            return $html;
        }

        // Translate navigation-item labels in footer
        $html = $this->translate_navigation_labels_in_html($html);

        // Translate headings (About, Privacy, Social)
        $html = $this->translate_headings_in_html($html);

        // Translate theme strings (Read more, Continue reading, etc.)
        $html = $this->translate_theme_strings_in_html($html);

        return $html;
    }

    /**
     * Translate common theme strings in HTML output
     * This is an optimized alternative to gettext filter
     */
    private function translate_theme_strings_in_html($html) {
        // Get all scanned strings that have translations
        $strings = $this->get_translated_theme_strings();

        if (empty($strings)) {
            return $html;
        }

        // Sort by length descending to replace longer strings first
        usort($strings, function($a, $b) {
            return mb_strlen($b['original']) - mb_strlen($a['original']);
        });

        foreach ($strings as $string) {
            // Only replace visible text (between tags or in specific attributes)
            // Pattern: >Original< or title="Original" or aria-label="Original"
            $patterns = array(
                '/>' . preg_quote($string['original'], '/') . '</',
                '/title="' . preg_quote($string['original'], '/') . '"/',
                '/aria-label="' . preg_quote($string['original'], '/') . '"/',
                '/placeholder="' . preg_quote($string['original'], '/') . '"/',
                '/value="' . preg_quote($string['original'], '/') . '"/',
            );

            $replacements = array(
                '>' . esc_html($string['translated']) . '<',
                'title="' . esc_attr($string['translated']) . '"',
                'aria-label="' . esc_attr($string['translated']) . '"',
                'placeholder="' . esc_attr($string['translated']) . '"',
                'value="' . esc_attr($string['translated']) . '"',
            );

            $html = preg_replace($patterns, $replacements, $html);
        }

        return $html;
    }

    /**
     * Get theme strings that have translations (cached)
     */
    private function get_translated_theme_strings() {
        static $cached_strings = null;

        if ($cached_strings !== null) {
            return $cached_strings;
        }

        global $wpdb;
        $translations_table = Translio_DB::get_translations_table();

        // Get all 'string' type translations for current language
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT original_content, translated_content
                 FROM {$translations_table}
                 WHERE object_type = 'string'
                 AND field_name = 'text'
                 AND language_code = %s
                 AND translated_content IS NOT NULL
                 AND translated_content != ''
                 AND CHAR_LENGTH(original_content) >= 3
                 LIMIT 500",
                $this->get_translation_language()
            )
        );

        $cached_strings = array();
        foreach ($results as $row) {
            $cached_strings[] = array(
                'original' => $row->original_content,
                'translated' => $row->translated_content,
            );
        }

        return $cached_strings;
    }

    /**
     * Translate navigation link labels in HTML
     */
    private function translate_navigation_labels_in_html($html) {
        // Pattern for: <span class="wp-block-navigation-item__label">Text</span>
        $pattern = '/<span class="wp-block-navigation-item__label">([^<]+)<\/span>/';

        return preg_replace_callback($pattern, function($matches) {
            $original = trim($matches[1]);
            $object_id = Translio_Utils::generate_hash_id('nav_link', $original);

            $translation = Translio_DB::get_translation($object_id, 'block_item', 'label', $this->get_translation_language());

            if ($translation && !empty($translation->translated_content)) {
                return '<span class="wp-block-navigation-item__label">' . esc_html($translation->translated_content) . '</span>';
            }

            return $matches[0];
        }, $html);
    }

    /**
     * Translate headings in HTML (for footer sections like About, Privacy, Social)
     */
    private function translate_headings_in_html($html) {
        // Pattern for: <h2 class="...">Text</h2>
        $pattern = '/<h([1-6])([^>]*)>([^<]+)<\/h\1>/';

        return preg_replace_callback($pattern, function($matches) {
            $level = $matches[1];
            $attrs = $matches[2];
            $original = trim($matches[3]);

            $object_id = Translio_Utils::generate_hash_id('nav_link', $original);
            $translation = Translio_DB::get_translation($object_id, 'block_item', 'label', $this->get_translation_language());

            if ($translation && !empty($translation->translated_content)) {
                return '<h' . $level . $attrs . '>' . esc_html($translation->translated_content) . '</h' . $level . '>';
            }

            return $matches[0];
        }, $html);
    }

    private function is_translated_request() {
        if (is_admin()) {
            return false;
        }

        $current_lang = translio()->get_current_language();
        $default_lang = translio()->get_default_language();

        return $current_lang !== $default_lang && translio()->is_secondary_language($current_lang);
    }

    /**
     * Get the current language for translations
     * Uses URL-detected language for frontend, first secondary for fallback
     */
    private function get_translation_language() {
        $current = translio()->get_current_language();
        if (!empty($current) && translio()->is_secondary_language($current)) {
            return $current;
        }
        return $this->secondary_language;
    }

    public function filter_title($title, $post_id = null) {
        $title = (string) $title;
        if (!$this->is_translated_request() || empty($post_id)) {
            return $title;
        }

        // Get actual post type
        $post_type = get_post_type($post_id);
        if (!$post_type) {
            $post_type = 'post';
        }

        $translation = Translio_DB::get_translation($post_id, $post_type, 'title', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            Translio_Logger::log_translation_path($post_type, $post_id, 'title', $this->get_translation_language(), 'db');
            return $translation->translated_content;
        }

        return $title;
    }

    /**
     * Preload translations for post and its attachments
     * Called on 'the_post' action to batch load translations
     */
    public function preload_post_translations($post) {
        if (!$this->is_translated_request() || !$post) {
            return;
        }

        $items = array();
        $post_type = $post->post_type;

        // Post fields (use actual post type)
        $items[] = array('object_id' => $post->ID, 'object_type' => $post_type, 'field_name' => 'title');
        $items[] = array('object_id' => $post->ID, 'object_type' => $post_type, 'field_name' => 'content');
        $items[] = array('object_id' => $post->ID, 'object_type' => $post_type, 'field_name' => 'excerpt');

        // Featured image
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $items[] = array('object_id' => $thumbnail_id, 'object_type' => 'attachment', 'field_name' => 'alt');
            $items[] = array('object_id' => $thumbnail_id, 'object_type' => 'attachment', 'field_name' => 'caption');
            $items[] = array('object_id' => $thumbnail_id, 'object_type' => 'attachment', 'field_name' => 'title');
        }

        // Gallery images from content (basic detection)
        if (has_block('gallery', $post->post_content) || has_shortcode($post->post_content, 'gallery')) {
            // Extract attachment IDs from galleries
            $gallery_ids = $this->extract_gallery_attachment_ids($post->post_content);
            foreach ($gallery_ids as $att_id) {
                $items[] = array('object_id' => $att_id, 'object_type' => 'attachment', 'field_name' => 'alt');
                $items[] = array('object_id' => $att_id, 'object_type' => 'attachment', 'field_name' => 'caption');
            }
        }

        // Batch load all translations
        if (!empty($items)) {
            Translio_DB::get_translations_batch($items, $this->get_translation_language());
        }
    }

    /**
     * Extract attachment IDs from gallery blocks and shortcodes
     */
    private function extract_gallery_attachment_ids($content) {
        $ids = array();

        // Gallery block: ids attribute
        if (preg_match_all('/wp:gallery[^}]*"ids":\[([^\]]+)\]/', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $gallery_ids = array_map('intval', explode(',', $match));
                $ids = array_merge($ids, $gallery_ids);
            }
        }

        // Gallery shortcode: ids="1,2,3"
        if (preg_match_all('/\[gallery[^\]]*ids=["\']([^"\']+)["\']/', $content, $matches)) {
            foreach ($matches[1] as $match) {
                $gallery_ids = array_map('intval', explode(',', $match));
                $ids = array_merge($ids, $gallery_ids);
            }
        }

        return array_unique(array_filter($ids));
    }

    public function filter_content($content) {
        $content = (string) $content;
        if (!$this->is_translated_request()) {
            return $content;
        }

        global $post;

        if (!$post) {
            return $content;
        }

        // First check for full content translation (use actual post type)
        $post_type = $post->post_type;
        $translation = Translio_DB::get_translation($post->ID, $post_type, 'content', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            Translio_Logger::log_translation_path($post_type, $post->ID, 'content', $this->get_translation_language(), 'db');
            return $translation->translated_content;
        }

        // Apply Divi field-level translations if Divi content detected
        if (class_exists('Translio_Divi') && Translio_Divi::has_divi_content($post->ID)) {
            $content = Translio_Divi::apply_translations($content, $post->ID, $this->get_translation_language());
        }

        // Apply Avada field-level translations if Avada content detected
        if (class_exists('Translio_Avada') && Translio_Avada::has_avada_content($post->ID)) {
            $content = Translio_Avada::apply_translations($content, $post->ID, $this->get_translation_language());
        }

        return $content;
    }

    public function filter_excerpt($excerpt) {
        $excerpt = (string) $excerpt;
        if (!$this->is_translated_request()) {
            return $excerpt;
        }

        global $post;

        if (!$post) {
            return $excerpt;
        }

        $translation = Translio_DB::get_translation($post->ID, $post->post_type, 'excerpt', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $excerpt;
    }

    public function filter_get_excerpt($excerpt, $post) {
        $excerpt = (string) $excerpt;
        if (!$this->is_translated_request() || !$post) {
            return $excerpt;
        }

        $translation = Translio_DB::get_translation($post->ID, $post->post_type, 'excerpt', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $excerpt;
    }

    public function filter_single_title($title, $post = null) {
        $title = (string) $title;
        if (!$this->is_translated_request()) {
            return $title;
        }

        if (!$post) {
            global $post;
        }

        if (!$post) {
            return $title;
        }

        $translation = Translio_DB::get_translation($post->ID, $post->post_type, 'title', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $title;
    }

    public function filter_rss_title($title) {
        $title = (string) $title;
        if (!$this->is_translated_request()) {
            return $title;
        }

        global $post;

        if (!$post) {
            return $title;
        }

        $translation = Translio_DB::get_translation($post->ID, $post->post_type, 'title', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $title;
    }

    public function filter_document_title($title_parts) {
        if (!$this->is_translated_request()) {
            return $title_parts;
        }

        if (is_singular() && isset($title_parts['title'])) {
            global $post;

            if ($post) {
                $translation = Translio_DB::get_translation($post->ID, $post->post_type, 'title', $this->get_translation_language());

                if ($translation && !empty($translation->translated_content)) {
                    $title_parts['title'] = $translation->translated_content;
                }
            }
        }

        // Handle category/tag archive titles
        if ((is_category() || is_tag() || is_tax()) && isset($title_parts['title'])) {
            $term = get_queried_object();
            if ($term && isset($term->term_id)) {
                $translation = Translio_DB::get_translation($term->term_id, 'term', 'name', $this->get_translation_language());

                if ($translation && !empty($translation->translated_content)) {
                    $title_parts['title'] = $translation->translated_content;
                }
            }
        }

        return $title_parts;
    }

    public function filter_wp_title($title, $sep) {
        $title = (string) $title;
        if (!$this->is_translated_request()) {
            return $title;
        }

        if (is_singular()) {
            global $post;

            if ($post) {
                $translation = Translio_DB::get_translation($post->ID, $post->post_type, 'title', $this->get_translation_language());

                if ($translation && !empty($translation->translated_content)) {
                    return $translation->translated_content . ' ' . $sep . ' ';
                }
            }
        }

        return $title;
    }

    /**
     * Filter SEO title (Yoast, Rank Math, AIOSEO)
     */
    public function filter_seo_title($title) {
        $title = (string) $title;
        if (!$this->is_translated_request()) {
            return $title;
        }

        global $post;

        if ($post) {
            $post_type = $post->post_type;

            // First try SEO-specific field
            $translation = Translio_DB::get_translation($post->ID, $post_type, 'seo_title', $this->get_translation_language());

            if ($translation && !empty($translation->translated_content)) {
                return $translation->translated_content;
            }

            // Fallback to regular title
            $translation = Translio_DB::get_translation($post->ID, $post_type, 'title', $this->get_translation_language());

            if ($translation && !empty($translation->translated_content)) {
                return $translation->translated_content;
            }
        }

        return $title;
    }

    /**
     * Filter SEO description (Yoast, Rank Math, AIOSEO)
     */
    public function filter_seo_description($description) {
        $description = (string) $description;
        if (!$this->is_translated_request()) {
            return $description;
        }

        global $post;

        if ($post) {
            $post_type = $post->post_type;

            // First try SEO-specific field
            $translation = Translio_DB::get_translation($post->ID, $post_type, 'seo_description', $this->get_translation_language());

            if ($translation && !empty($translation->translated_content)) {
                return $translation->translated_content;
            }

            // Fallback to excerpt
            $translation = Translio_DB::get_translation($post->ID, $post_type, 'excerpt', $this->get_translation_language());

            if ($translation && !empty($translation->translated_content)) {
                return $translation->translated_content;
            }
        }

        return $description;
    }

    public function filter_term_title($title) {
        $title = (string) $title;
        if (!$this->is_translated_request()) {
            return $title;
        }

        $term = get_queried_object();

        if (!$term || !isset($term->term_id)) {
            return $title;
        }

        $translation = Translio_DB::get_translation($term->term_id, 'term', 'name', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $title;
    }

    public function filter_term_name($name, $term_id) {
        $name = (string) $name;
        if (!$this->is_translated_request()) {
            return $name;
        }

        $translation = Translio_DB::get_translation($term_id, 'term', 'name', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $name;
    }

    /**
     * Filter attachment image attributes (alt text)
     */
    public function filter_attachment_image_attributes($attr, $attachment, $size) {
        if (!$this->is_translated_request()) {
            return $attr;
        }

        if (isset($attr['alt']) && !empty($attr['alt'])) {
            $attachment_id = is_object($attachment) ? $attachment->ID : $attachment;

            $translation = Translio_DB::get_translation($attachment_id, 'attachment', 'alt', $this->get_translation_language());

            if ($translation && !empty($translation->translated_content)) {
                $attr['alt'] = $translation->translated_content;
            }
        }

        return $attr;
    }

    /**
     * Filter attachment alt meta (for get_post_meta calls)
     */
    public function filter_attachment_alt_meta($value, $object_id, $meta_key, $single) {
        $meta_key = (string) $meta_key;
        if ($meta_key !== '_wp_attachment_image_alt') {
            return $value;
        }

        if (!$this->is_translated_request()) {
            return $value;
        }

        // Prevent infinite recursion
        if (self::$filtering_alt_meta) {
            return $value;
        }

        // Get original value first using recursion guard
        self::$filtering_alt_meta = true;
        $original_alt = get_post_meta($object_id, '_wp_attachment_image_alt', true);
        self::$filtering_alt_meta = false;

        if (empty($original_alt)) {
            return $value;
        }

        $translation = Translio_DB::get_translation($object_id, 'attachment', 'alt', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $single ? $translation->translated_content : array($translation->translated_content);
        }

        return $value;
    }

    /**
     * Filter attachment caption
     */
    public function filter_attachment_caption($caption, $post_id) {
        $caption = (string) $caption;
        if (!$this->is_translated_request() || empty($caption)) {
            return $caption;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'attachment') {
            return $caption;
        }

        $translation = Translio_DB::get_translation($post_id, 'attachment', 'caption', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $caption;
    }

    /**
     * Filter attachment title (only for attachments)
     */
    public function filter_attachment_title($title, $post_id) {
        $title = (string) $title;
        if (!$this->is_translated_request()) {
            return $title;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'attachment') {
            return $title;
        }

        $translation = Translio_DB::get_translation($post_id, 'attachment', 'title', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $title;
    }

    /**
     * Filter attachment description (post_content for attachments)
     */
    public function filter_attachment_description($content, $post_id = 0) {
        $content = (string) $content;
        if (!$this->is_translated_request()) {
            return $content;
        }

        // Get post ID if not provided (the_content filter)
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'attachment') {
            return $content;
        }

        $translation = Translio_DB::get_translation($post_id, 'attachment', 'description', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $content;
    }

    public function filter_get_term($term, $taxonomy) {
        if (!$this->is_translated_request() || !is_object($term)) {
            return $term;
        }

        $name_translation = Translio_DB::get_translation($term->term_id, 'term', 'name', $this->get_translation_language());

        if ($name_translation && !empty($name_translation->translated_content)) {
            $term->name = $name_translation->translated_content;
        }

        $desc_translation = Translio_DB::get_translation($term->term_id, 'term', 'description', $this->get_translation_language());

        if ($desc_translation && !empty($desc_translation->translated_content)) {
            $term->description = $desc_translation->translated_content;
        }

        return $term;
    }

    /**
     * Filter WooCommerce attribute values (terms)
     */
    public function filter_wc_attribute($value, $attribute, $values) {
        $value = (string) $value;
        if (!$this->is_translated_request()) {
            return $value;
        }

        // $values contains term slugs, we need to translate term names
        if ($attribute->is_taxonomy()) {
            $taxonomy = $attribute->get_taxonomy();
            $translated_values = array();

            foreach ($values as $term_slug) {
                $term = get_term_by('slug', $term_slug, $taxonomy);
                if ($term) {
                    $translation = Translio_DB::get_translation($term->term_id, 'term', 'name', $this->get_translation_language());
                    if ($translation && !empty($translation->translated_content)) {
                        $translated_values[] = $translation->translated_content;
                    } else {
                        $translated_values[] = $term->name;
                    }
                } else {
                    $translated_values[] = $term_slug;
                }
            }

            return implode(', ', $translated_values);
        }

        return $value;
    }

    /**
     * Filter WooCommerce attribute label (name of attribute like "Color", "Size")
     */
    public function filter_wc_attribute_label($label, $name, $product) {
        $label = (string) $label;
        if (!$this->is_translated_request()) {
            return $label;
        }

        // Try to get translation for attribute label
        // Attribute labels are stored as wc_attribute taxonomy name
        $attribute_id = wc_attribute_taxonomy_id_by_name($name);
        if ($attribute_id) {
            $translation = Translio_DB::get_translation($attribute_id, 'wc_attribute', 'label', $this->get_translation_language());
            if ($translation && !empty($translation->translated_content)) {
                return $translation->translated_content;
            }
        }

        return $label;
    }

    /**
     * Filter blogname option
     */
    public function filter_blogname($value) {
        $value = (string) $value;
        if (!$this->is_translated_request()) {
            return $value;
        }

        $translation = Translio_DB::get_translation(1, 'option', 'blogname', $this->get_translation_language());
        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $value;
    }

    /**
     * Filter blogdescription option
     */
    public function filter_blogdescription($value) {
        $value = (string) $value;
        if (!$this->is_translated_request()) {
            return $value;
        }

        $translation = Translio_DB::get_translation(1, 'option', 'blogdescription', $this->get_translation_language());
        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $value;
    }

    public function filter_nav_menu_items($items, $args) {
        if (!$this->is_translated_request()) {
            return $items;
        }

        // Collect all IDs for batch preloading (fixes N+1 query problem)
        $menu_item_ids = array();
        $post_ids = array();

        foreach ($items as $item) {
            $menu_item_ids[] = $item->ID;
            if ($item->object_id && $item->type === 'post_type') {
                $post_ids[] = $item->object_id;
            }
        }

        // Preload all translations in a single query
        $all_ids = array_unique(array_merge($menu_item_ids, $post_ids));
        Translio_DB::preload_menu_translations($all_ids, $this->get_translation_language());

        // Now iterate - all get_translation calls will hit the cache
        foreach ($items as $item) {
            // Try to translate menu item title
            $translation = Translio_DB::get_translation($item->ID, 'menu_item', 'title', $this->get_translation_language());

            if ($translation && !empty($translation->translated_content)) {
                $item->title = $translation->translated_content;
            } elseif ($item->object_id && $item->type === 'post_type') {
                // Fall back to post title translation (use actual post type)
                $linked_post_type = get_post_type($item->object_id);
                if (!$linked_post_type) {
                    $linked_post_type = 'post';
                }
                $post_translation = Translio_DB::get_translation($item->object_id, $linked_post_type, 'title', $this->get_translation_language());

                if ($post_translation && !empty($post_translation->translated_content)) {
                    $item->title = $post_translation->translated_content;
                }
            }

            // Update URL to include language prefix
            if (!empty($item->url)) {
                $home_url = home_url('/');
                $current_lang = $this->get_translation_language();
                if (strpos($item->url, $home_url) === 0 && strpos($item->url, '/' . $current_lang . '/') === false) {
                    $relative = str_replace($home_url, '', $item->url);
                    $item->url = $home_url . $current_lang . '/' . ltrim($relative, '/');
                }
            }
        }

        return $items;
    }

    public function filter_widget_title($title) {
        $title = (string) $title;
        if (!$this->is_translated_request() || empty($title)) {
            return $title;
        }

        // Widgets don't have reliable IDs, so we use a SHA256-based hash of the title
        $widget_hash = Translio_Utils::generate_hash_id('widget', $title);

        $translation = Translio_DB::get_translation($widget_hash, 'widget', 'title', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $title;
    }

    /**
     * Filter text widget content
     */
    public function filter_widget_text($text, $instance = array(), $widget = null) {
        $text = (string) $text;
        if (!$this->is_translated_request() || empty($text)) {
            return $text;
        }

        // Use SHA256 hash of original content as ID
        $widget_hash = Translio_Utils::generate_hash_id('widget_text', md5($text));

        $translation = Translio_DB::get_translation($widget_hash, 'widget', 'content', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $text;
    }

    /**
     * Filter custom HTML widget content
     */
    public function filter_widget_html($content, $instance = array(), $widget = null) {
        $content = (string) $content;
        if (!$this->is_translated_request() || empty($content)) {
            return $content;
        }

        // Use SHA256 hash of original content as ID
        $widget_hash = Translio_Utils::generate_hash_id('widget_html', md5($content));

        $translation = Translio_DB::get_translation($widget_hash, 'widget', 'html', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $content;
    }

    /**
     * Filter block rendering to apply translations
     */
    public function filter_render_block($block_content, $block) {
        $block_content = (string) $block_content;
        if (!$this->is_translated_request()) {
            return $block_content;
        }

        // Handle navigation-link blocks
        if ($block['blockName'] === 'core/navigation-link') {
            return $this->translate_navigation_link_block($block_content, $block);
        }

        // Handle page-list block items (rendered as navigation links)
        if ($block['blockName'] === 'core/page-list') {
            return $this->translate_page_list_block($block_content);
        }

        return $block_content;
    }

    /**
     * Filter entire navigation block output
     */
    public function filter_navigation_block($block_content, $block) {
        $block_content = (string) $block_content;
        if (!$this->is_translated_request()) {
            return $block_content;
        }

        // Translate page-list links within navigation
        $block_content = $this->translate_page_list_block($block_content);

        // Add language prefix to all internal links
        $block_content = $this->add_language_prefix_to_links($block_content);

        return $block_content;
    }

    /**
     * Pre-render block filter - can return early to override rendering
     */
    public function filter_pre_render_block($pre_render, $block) {
        // We use render_block filter instead for modifications
        return $pre_render;
    }

    /**
     * Translate navigation link block content
     */
    private function translate_navigation_link_block($block_content, $block) {
        if (empty($block['attrs']['label'])) {
            return $block_content;
        }

        $original_label = $block['attrs']['label'];

        // Check for translation in our custom block_item storage
        $translation = Translio_DB::get_translation(
            Translio_Utils::generate_hash_id('nav_link', $original_label),
            'block_item',
            'label',
            $this->get_translation_language()
        );

        if ($translation && !empty($translation->translated_content)) {
            // Replace the label in the rendered HTML
            $block_content = $this->replace_link_label($block_content, $original_label, $translation->translated_content);
        }

        // Also update the URL to include language prefix
        $block_content = $this->add_language_prefix_to_links($block_content);

        return $block_content;
    }

    /**
     * Translate page-list block content
     */
    private function translate_page_list_block($block_content) {
        // Page list renders page titles as links
        // We need to find each link and translate based on page title or URL
        if (empty($block_content)) {
            return $block_content;
        }

        // Match links with class containing wp-block-pages-list__item__link
        // Pattern captures: full match, href URL, link text
        $pattern = '/<a[^>]*class="[^"]*wp-block-pages-list__item__link[^"]*"[^>]*href="([^"]*)"[^>]*>([^<]+)<\/a>/i';

        $block_content = preg_replace_callback($pattern, function($matches) {
            $href = $matches[1];
            $original_title = trim($matches[2]);

            // Try to find page by URL slug
            $path = parse_url($href, PHP_URL_PATH);
            $slug = trim($path, '/');

            // Get page by slug
            $page = get_page_by_path($slug);

            if (!$page) {
                // Fallback: try by title
                $page = get_page_by_title($original_title);
            }

            if ($page) {
                $translation = Translio_DB::get_translation(
                    $page->ID,
                    'post',
                    'title',
                    $this->get_translation_language()
                );

                if ($translation && !empty($translation->translated_content)) {
                    // Replace the title in the link
                    $new_link = str_replace('>' . $original_title . '<', '>' . esc_html($translation->translated_content) . '<', $matches[0]);
                    return $new_link;
                }
            }

            return $matches[0];
        }, $block_content);

        // Add language prefix to URLs
        $block_content = $this->add_language_prefix_to_links($block_content);

        return $block_content;
    }

    /**
     * Replace link label in HTML
     */
    private function replace_link_label($html, $original, $translated) {
        // Navigation links have structure like:
        // <a class="wp-block-navigation-item__content" href="...">Label</a>
        $pattern = '/(<a[^>]*class="[^"]*wp-block-navigation-item__content[^"]*"[^>]*>)' . preg_quote($original, '/') . '(<\/a>)/i';
        $replacement = '${1}' . esc_html($translated) . '${2}';

        return preg_replace($pattern, $replacement, $html);
    }

    /**
     * Filter gettext translations for theme/plugin strings
     */
    public function filter_gettext($translated, $text, $domain) {
        $text = (string) $text;
        $translated = (string) $translated;
        // Skip empty strings and very short strings (likely placeholders)
        if (empty($text) || strlen($text) < 2) {
            return $translated;
        }

        // Skip internal WordPress domains that shouldn't be translated by plugin
        $skip_domains = array('translio'); // Our own plugin
        if (in_array($domain, $skip_domains)) {
            return $translated;
        }

        // Get allowed domains (dynamic based on theme + common plugins)
        $allowed_domains = $this->get_allowed_gettext_domains();
        if (!in_array($domain, $allowed_domains)) {
            return $translated;
        }

        // Record string for later translation (only on frontend, not admin)
        if (!is_admin() && $this->should_record_strings()) {
            $this->record_gettext_string($text, $domain);
        }

        // If not translated request, just return original
        if (!$this->is_translated_request()) {
            return $translated;
        }

        // Check for translation
        $object_id = Translio_Utils::generate_hash_id('gettext', $text, $domain);
        $translation = Translio_DB::get_translation($object_id, 'string', 'text', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $translated;
    }

    /**
     * Filter gettext with context translations
     */
    public function filter_gettext_with_context($translated, $text, $context, $domain) {
        $text = (string) $text;
        $translated = (string) $translated;
        // Skip empty strings
        if (empty($text) || strlen($text) < 2) {
            return $translated;
        }

        // Skip internal domains
        $skip_domains = array('translio');
        if (in_array($domain, $skip_domains)) {
            return $translated;
        }

        // Get allowed domains
        $allowed_domains = $this->get_allowed_gettext_domains();
        if (!in_array($domain, $allowed_domains)) {
            return $translated;
        }

        // Record string for later translation (only on frontend)
        if (!is_admin() && $this->should_record_strings()) {
            $this->record_gettext_string($text, $domain, $context);
        }

        // If not translated request, just return original
        if (!$this->is_translated_request()) {
            return $translated;
        }

        // First try with full context hash
        $object_id = Translio_Utils::generate_hash_id('gettext', $text . '_' . $context, $domain);
        $translation = Translio_DB::get_translation($object_id, 'string', 'text', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        // Fallback: try without context
        $object_id_no_context = Translio_Utils::generate_hash_id('gettext', $text, $domain);
        $translation = Translio_DB::get_translation($object_id_no_context, 'string', 'text', $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        return $translated;
    }

    /**
     * Get dynamically allowed gettext domains (current theme + child + common plugins)
     */
    private function get_allowed_gettext_domains() {
        static $domains = null;

        if ($domains === null) {
            $domains = array('default'); // WordPress core

            // Get theme domains from options instead of wp_get_theme()
            // This avoids PHP 8.1+ deprecation warnings from wp_normalize_path()
            $stylesheet = get_option('stylesheet');
            $template = get_option('template');

            if ($stylesheet) {
                $domains[] = $stylesheet;
            }
            // Parent theme if child theme
            if ($template && $template !== $stylesheet) {
                $domains[] = $template;
            }

            // Common plugins (add more as needed)
            $common_plugins = array(
                'woocommerce',
                'contact-form-7',
                'elementor',
                'wpforms-lite',
                'yoast-seo',
                'rank-math',
            );

            foreach ($common_plugins as $plugin_domain) {
                $domains[] = $plugin_domain;
            }

            // Allow filtering for custom domains
            $domains = apply_filters('translio_allowed_domains', array_unique(array_filter($domains)));
        }

        return $domains;
    }

    /**
     * Check if we should record strings (scanning mode)
     */
    private function should_record_strings() {
        // Record strings if scanning is enabled in settings
        return get_option('translio_scan_strings', true);
    }

    /**
     * Record gettext string to database for later translation
     */
    private function record_gettext_string($text, $domain, $context = '') {
        // Skip very long strings (probably content, not UI strings)
        if (strlen($text) > 500) {
            return;
        }

        // Skip strings that look like HTML or code
        if (preg_match('/<[^>]+>/', $text) || preg_match('/^[\s\d\W]+$/', $text)) {
            return;
        }

        // Use static cache to avoid duplicate DB calls in same request
        static $recorded = array();
        $cache_key = md5($text . '_' . $domain . '_' . $context);
        if (isset($recorded[$cache_key])) {
            return;
        }
        $recorded[$cache_key] = true;

        // Calculate object_id using SHA256
        if (!empty($context)) {
            $object_id = Translio_Utils::generate_hash_id('gettext', $text . '_' . $context, $domain);
        } else {
            $object_id = Translio_Utils::generate_hash_id('gettext', $text, $domain);
        }

        // Check if already exists in scanned strings table
        Translio_DB::record_scanned_string($object_id, $text, $domain, $context);
    }

    /**
     * Add language prefix to internal links
     */
    private function add_language_prefix_to_links($html) {
        $home_url = home_url('/');
        $lang_prefix = '/' . $this->get_translation_language() . '/';

        // Find all href attributes and add language prefix if internal
        return preg_replace_callback(
            '/href=["\'](' . preg_quote($home_url, '/') . ')([^"\']*)["\']/',
            function($matches) use ($lang_prefix) {
                $url = $matches[1] . $matches[2];
                // Check if language prefix already exists
                if (strpos($url, $lang_prefix) === false) {
                    return 'href="' . $matches[1] . ltrim($lang_prefix, '/') . $matches[2] . '"';
                }
                return $matches[0];
            },
            $html
        );
    }

    public static function get_translated_content($post_id, $field, $language_code = null) {
        if (!$language_code) {
            $language_code = translio()->get_current_language();
        }

        // Get actual post type
        $post_type = get_post_type($post_id);
        if (!$post_type) {
            $post_type = 'post';
        }

        $translation = Translio_DB::get_translation($post_id, $post_type, $field, $language_code);

        if ($translation && !empty($translation->translated_content)) {
            return $translation->translated_content;
        }

        $post = get_post($post_id);

        if (!$post) {
            return '';
        }

        switch ($field) {
            case 'title':
                return $post->post_title;
            case 'content':
                return $post->post_content;
            case 'excerpt':
                return $post->post_excerpt;
            default:
                return '';
        }
    }

    /**
     * Filter Elementor content on frontend
     * Replaces widget content with translations
     */
    public function filter_elementor_content($content) {
        $content = (string) $content;
        if (!$this->is_translated_request()) {
            return $content;
        }

        global $post;
        if (!$post) {
            return $content;
        }

        // Check if this post has Elementor data
        if (!Translio_Elementor::has_elementor_data($post->ID)) {
            return $content;
        }

        // Get all translatable strings and their translations
        $strings = Translio_Elementor::extract_translatable_strings($post->ID);

        // Preload all translations in a single query (fixes N+1 problem)
        Translio_Elementor::preload_translations($post->ID, $strings, $this->get_translation_language());

        foreach ($strings as $string) {
            // Now this will hit the cache instead of making a DB query
            $translation = Translio_Elementor::get_translation(
                $post->ID,
                $string['element_id'],
                $string['field'],
                $this->get_translation_language()
            );

            if ($translation && !empty($translation->translated_content)) {
                // Replace original content with translation in the HTML output
                $original = $string['content'];
                $translated = $translation->translated_content;

                // For simple text, do direct replacement
                // Be careful with HTML content - need to match structure
                if ($string['type'] === 'LINE') {
                    // Simple text replacement
                    $content = str_replace(
                        '>' . esc_html($original) . '<',
                        '>' . esc_html($translated) . '<',
                        $content
                    );
                } else {
                    // HTML content - sanitize to prevent XSS while preserving allowed HTML
                    $sanitized_translated = wp_kses_post($translated);
                    $content = str_replace($original, $sanitized_translated, $content);
                }
            }
        }

        return $content;
    }

    /**
     * Filter Contact Form 7 form properties for translation
     * Replaces form content, messages, and mail templates with translations
     */
    public function filter_cf7_form_properties($properties, $form) {
        if (!$this->is_translated_request()) {
            return $properties;
        }

        $form_id = $form->id();

        // Translate form content
        $form_translation = Translio_DB::get_translation($form_id, 'cf7_form', 'form', $this->get_translation_language());
        if ($form_translation && !empty($form_translation->translated_content)) {
            $properties['form'] = $form_translation->translated_content;
        }

        // Translate mail subject
        if (!empty($properties['mail']['subject'])) {
            $mail_subject_translation = Translio_DB::get_translation($form_id, 'cf7_mail', 'subject', $this->get_translation_language());
            if ($mail_subject_translation && !empty($mail_subject_translation->translated_content)) {
                $properties['mail']['subject'] = $mail_subject_translation->translated_content;
            }
        }

        // Translate mail body
        if (!empty($properties['mail']['body'])) {
            $mail_body_translation = Translio_DB::get_translation($form_id, 'cf7_mail', 'body', $this->get_translation_language());
            if ($mail_body_translation && !empty($mail_body_translation->translated_content)) {
                $properties['mail']['body'] = $mail_body_translation->translated_content;
            }
        }

        // Translate messages
        if (!empty($properties['messages']) && is_array($properties['messages'])) {
            foreach ($properties['messages'] as $key => $message) {
                $msg_translation = Translio_DB::get_translation($form_id, 'cf7_message', $key, $this->get_translation_language());
                if ($msg_translation && !empty($msg_translation->translated_content)) {
                    $properties['messages'][$key] = $msg_translation->translated_content;
                }
            }
        }

        return $properties;
    }

    /**
     * Filter post meta values for translations
     * Supports ACF fields, theme meta boxes, and other custom meta
     */
    public function filter_post_meta($value, $object_id, $meta_key, $single) {
        // Skip if not a translated request
        if (!$this->is_translated_request()) {
            return $value;
        }

        // Skip null or empty meta keys
        $meta_key = (string) $meta_key;
        if (empty($meta_key)) {
            return $value;
        }

        // Skip internal/system meta keys
        if (strpos($meta_key, '_') === 0) {
            return $value;
        }

        // Skip if we're in admin area
        if (is_admin()) {
            return $value;
        }

        // Prevent infinite recursion
        if (self::$filtering_post_meta) {
            return $value;
        }

        // Get the original value first using recursion guard
        self::$filtering_post_meta = true;
        $original_value = get_post_meta($object_id, $meta_key, true);
        self::$filtering_post_meta = false;

        // Skip empty values or non-string values
        if (empty($original_value) || !is_string($original_value)) {
            return $value;
        }

        // Skip numeric-only values (IDs, etc.)
        if (is_numeric($original_value)) {
            return $value;
        }

        // Skip very short values
        if (strlen($original_value) < 3) {
            return $value;
        }

        // Skip URLs
        if (filter_var($original_value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // Skip file paths (starting with / or containing file extensions)
        if (strpos($original_value, '/') === 0 || preg_match('/\.(jpg|jpeg|png|gif|webp|svg|pdf|mp4|mp3|zip)$/i', $original_value)) {
            return $value;
        }

        // Skip color codes
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $original_value)) {
            return $value;
        }

        // Check for translation (use actual post type)
        $post_type = get_post_type($object_id);
        if (!$post_type) {
            $post_type = 'post';
        }

        $translation = Translio_DB::get_translation($object_id, $post_type, 'meta_' . $meta_key, $this->get_translation_language());

        if ($translation && !empty($translation->translated_content)) {
            return $single ? $translation->translated_content : array($translation->translated_content);
        }

        return $value;
    }
}
