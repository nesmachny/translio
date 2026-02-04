<?php
/**
 * Translio Utils Class
 * Shared utility methods for translation logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Utils {

    /**
     * Check if a meta field should be skipped for translation
     *
     * This method is used by both API and Admin classes to ensure
     * consistent filtering of non-translatable meta fields.
     *
     * @param string $key Meta key
     * @param string $value Meta value
     * @return bool True if should skip translation
     */
    public static function should_skip_meta_translation($key, $value) {
        // Skip empty values
        if (empty($value) || !is_string($value)) {
            return true;
        }

        $value = trim((string) $value);

        // Skip URLs (full URLs)
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return true;
        }

        // Skip values starting with / (relative URLs/paths)
        if (strpos($value, '/') === 0) {
            return true;
        }

        // Skip values starting with # (anchors or hex colors)
        if (strpos($value, '#') === 0) {
            return true;
        }

        // Skip numeric-only values
        if (is_numeric($value)) {
            return true;
        }

        // Skip very short values (likely IDs, codes)
        if (strlen($value) < 3) {
            return true;
        }

        // Skip common non-translatable values (CSS classes, positions, etc.)
        $skip_values = array(
            'primary', 'secondary', 'default', 'none', 'left', 'right', 'center',
            'top', 'bottom', 'middle', 'true', 'false', 'yes', 'no', 'on', 'off',
            'inherit', 'auto', 'normal', 'bold', 'italic', 'underline',
            'block', 'inline', 'flex', 'grid', 'hidden', 'visible',
            'absolute', 'relative', 'fixed', 'static',
            'small', 'medium', 'large', 'xl', 'xxl',
        );
        if (in_array(strtolower($value), $skip_values)) {
            return true;
        }

        // Skip key patterns that indicate non-translatable content
        $skip_key_patterns = array(
            '_link', '_url', '_uri', '_href',
            '_id', '_ids', '_key', '_keys',
            '_class', '_classes', '_style', '_styles',
            '_color', '_colors', '_background', '_bg',
            '_size', '_width', '_height', '_margin', '_padding', '_border',
            '_image', '_images', '_icon', '_icons',
            '_video', '_audio', '_file', '_files', '_media',
            '_font', '_weight', '_align', '_position',
            '_target', '_rel', '_type', '_format',
        );
        foreach ($skip_key_patterns as $pattern) {
            if (stripos($key, $pattern) !== false) {
                return true;
            }
        }

        // Skip values that look like CSS/technical values
        if (preg_match('/^-?\d+(\.\d+)?(px|em|rem|%|vh|vw|pt|cm|mm)$/', $value)) {
            return true;
        }

        // Skip color codes (hex)
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
            return true;
        }

        // Skip RGB/RGBA colors
        if (preg_match('/^rgba?\s*\(/', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Extract translatable fields from a post
     *
     * @param WP_Post $post Post object
     * @return array Array of translatable fields with id, text, context
     */
    public static function extract_post_fields($post) {
        $fields = array();

        if (!empty($post->post_title)) {
            $fields[] = array(
                'id' => 'title',
                'text' => $post->post_title,
                'context' => 'Page/post title'
            );
        }

        if (!empty($post->post_content)) {
            $fields[] = array(
                'id' => 'content',
                'text' => $post->post_content,
                'context' => 'Main page/post content - preserve all HTML tags and shortcodes'
            );
        }

        if (!empty($post->post_excerpt)) {
            $fields[] = array(
                'id' => 'excerpt',
                'text' => $post->post_excerpt,
                'context' => 'Short description/excerpt'
            );
        }

        return $fields;
    }

    /**
     * Extract translatable meta fields from a post
     *
     * @param int $post_id Post ID
     * @return array Array of translatable meta fields
     */
    public static function extract_post_meta_fields($post_id) {
        $fields = array();
        $all_meta = get_post_meta($post_id);

        if (empty($all_meta)) {
            return $fields;
        }

        // Meta keys to always exclude (WordPress internals, plugin internals)
        $excluded_prefixes = array(
            '_edit_', '_wp_', '_menu_', '_thumbnail', '_encloseme', '_pingme',
            '_oembed_', '_elementor_', '_wpb_', '_yoast_', '_aioseo_',
            'rank_math_', '_genesis_', '_et_', '_fl_', '_bricks_', '_translio_',
        );

        $excluded_exact = array(
            'classic-editor-remember', 'inline_featured_image', '_edit_lock',
            '_edit_last', '_wp_page_template', '_wp_trash_meta_status',
            '_wp_trash_meta_time', '_wp_old_slug', '_wp_old_date',
        );

        foreach ($all_meta as $meta_key => $meta_values) {
            // Skip empty values
            if (empty($meta_values[0])) {
                continue;
            }

            $meta_value = $meta_values[0];

            // Skip excluded prefixes
            $skip = false;
            foreach ($excluded_prefixes as $prefix) {
                if (strpos($meta_key, $prefix) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            // Skip exact matches
            if (in_array($meta_key, $excluded_exact)) continue;

            // Skip underscore-prefixed keys (internal)
            if (strpos($meta_key, '_') === 0) continue;

            // Skip serialized/array data
            if (is_serialized($meta_value)) continue;

            // Skip if not a string
            if (!is_string($meta_value)) continue;

            // Use shared skip logic
            if (self::should_skip_meta_translation($meta_key, $meta_value)) {
                continue;
            }

            // Create label from key
            $label = self::format_meta_key_label($meta_key);

            $fields[] = array(
                'id' => 'meta_' . $meta_key,
                'key' => $meta_key,
                'text' => trim($meta_value),
                'label' => $label,
                'context' => 'Custom field: ' . $label
            );
        }

        return $fields;
    }

    /**
     * Format meta key as human-readable label
     *
     * @param string $meta_key Meta key
     * @return string Formatted label
     */
    public static function format_meta_key_label($meta_key) {
        // Remove common prefixes
        $label = preg_replace('/^(meta_|custom_|field_|acf_)/', '', $meta_key);

        // Replace underscores and hyphens with spaces
        $label = str_replace(array('_', '-'), ' ', $label);

        // Capitalize words
        $label = ucwords($label);

        return $label;
    }

    /**
     * Get context for specific content types
     *
     * @param string $type Content type (cf7_form, cf7_mail, post, term, etc.)
     * @param string $field Field name
     * @return string Context string for translation
     */
    public static function get_translation_context($type, $field = '') {
        $contexts = array(
            'cf7_form' => 'This is a Contact Form 7 form template. ' .
                'Translate only the human-readable text (labels, placeholders, button text). ' .
                'Keep all CF7 shortcode tags like [text your-name], [email your-email], ' .
                '[submit "Send"], [textarea message] exactly as they are - do not translate inside brackets. ' .
                'Only translate the visible labels and placeholder text.',

            'cf7_mail' => 'This is an email template for Contact Form 7. ' .
                'Keep all mail-tags like [your-name], [your-email], [your-message] exactly as they are. ' .
                'Translate only the surrounding text.',

            'cf7_message' => 'This is a Contact Form 7 message (success/error message). ' .
                'Translate the message text.',

            'post_title' => 'Page/post title',
            'post_content' => 'Main page/post content - preserve all HTML tags and shortcodes',
            'post_excerpt' => 'Short description/excerpt',

            'term_name' => 'Category/tag name',
            'term_description' => 'Category/tag description',

            'media_alt' => 'Image alt text for accessibility',
            'media_title' => 'Media file title',
            'media_caption' => 'Media caption',
            'media_description' => 'Media description',

            'option' => 'WordPress site option/setting',
            'wc_attribute' => 'WooCommerce product attribute label',
            'navigation' => 'Navigation menu link label',
            'theme_string' => 'Theme/plugin translatable string',
        );

        if (isset($contexts[$type])) {
            return $contexts[$type];
        }

        // Try type_field combination
        $combined = $type . '_' . $field;
        if (isset($contexts[$combined])) {
            return $contexts[$combined];
        }

        return '';
    }

    /**
     * Generate a unique numeric hash ID for string-based identification
     *
     * Uses SHA256 to avoid collisions (unlike CRC32 which has only 32 bits)
     * Returns a large positive integer suitable for database object_id field
     *
     * @param string $prefix Prefix for the hash (e.g., 'widget', 'nav_link', 'gettext')
     * @param string $value The value to hash
     * @param string $suffix Optional suffix (e.g., domain for gettext)
     * @return int Positive integer hash ID
     */
    public static function generate_hash_id($prefix, $value, $suffix = '') {
        $input = $prefix . '_' . $value;
        if (!empty($suffix)) {
            $input .= '_' . $suffix;
        }

        // Use SHA256 and take first 15 hex chars (60 bits) to fit in PHP int
        // This gives us 2^60 possible values vs 2^32 for CRC32
        $hash = hash('sha256', $input);
        $hex_portion = substr($hash, 0, 15);

        return abs(hexdec($hex_portion));
    }
}
