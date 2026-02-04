<?php
/**
 * Translio Divi Integration
 * Handles translation of Divi page builder content
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Divi {

    /**
     * Divi module shortcode tags with translatable attributes
     * shortcode_tag => [attribute => field_type]
     * Types: LINE (single line), VISUAL (HTML/rich text), AREA (multiline)
     */
    private static $module_fields = array(
        // Text modules
        'et_pb_text' => array(
            '_content' => 'VISUAL', // Inner content
        ),
        'et_pb_code' => array(
            // Code module - skip, contains code
        ),

        // Headings & Titles
        'et_pb_post_title' => array(
            // Dynamic - uses post title
        ),

        // Buttons
        'et_pb_button' => array(
            'button_text' => 'LINE',
            'button_url' => false, // Skip URLs
        ),

        // Call to Action
        'et_pb_cta' => array(
            'title' => 'LINE',
            'button_text' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Blurb (Icon + Text)
        'et_pb_blurb' => array(
            'title' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Image
        'et_pb_image' => array(
            'alt' => 'LINE',
            'title_text' => 'LINE',
        ),

        // Slider & Slides
        'et_pb_slider' => array(),
        'et_pb_slide' => array(
            'heading' => 'LINE',
            'button_text' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Fullwidth Slider
        'et_pb_fullwidth_slider' => array(),
        'et_pb_fullwidth_slide' => array(
            'heading' => 'LINE',
            'button_text' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Post Slider
        'et_pb_post_slider' => array(),

        // Tabs
        'et_pb_tabs' => array(),
        'et_pb_tab' => array(
            'title' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Accordion
        'et_pb_accordion' => array(),
        'et_pb_accordion_item' => array(
            'title' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Toggle
        'et_pb_toggle' => array(
            'title' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Counter
        'et_pb_number_counter' => array(
            'title' => 'LINE',
            'number' => false, // Skip numbers
        ),
        'et_pb_circle_counter' => array(
            'title' => 'LINE',
        ),

        // Bar Counter
        'et_pb_counters' => array(),
        'et_pb_counter' => array(
            '_content' => 'LINE', // Counter label
        ),

        // Testimonial
        'et_pb_testimonial' => array(
            'author' => 'LINE',
            'job_title' => 'LINE',
            'company_name' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Pricing Table
        'et_pb_pricing_tables' => array(),
        'et_pb_pricing_table' => array(
            'title' => 'LINE',
            'subtitle' => 'LINE',
            'currency' => 'LINE',
            'per' => 'LINE',
            'sum' => 'LINE',
            'button_text' => 'LINE',
            '_content' => 'VISUAL', // Features list
        ),

        // Team Member
        'et_pb_team_member' => array(
            'name' => 'LINE',
            'position' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Blog
        'et_pb_blog' => array(
            // Dynamic content
        ),

        // Contact Form
        'et_pb_contact_form' => array(
            'title' => 'LINE',
            'submit_button_text' => 'LINE',
            'success_message' => 'LINE',
        ),
        'et_pb_contact_field' => array(
            'field_title' => 'LINE',
            'field_id' => false, // Skip IDs
        ),

        // Signup (Email Optin)
        'et_pb_signup' => array(
            'title' => 'LINE',
            'button_text' => 'LINE',
            'description' => 'VISUAL',
            'footer_content' => 'VISUAL',
            'success_message' => 'LINE',
        ),

        // Audio
        'et_pb_audio' => array(
            'title' => 'LINE',
            'artist_name' => 'LINE',
        ),

        // Video
        'et_pb_video' => array(
            // No translatable text typically
        ),

        // Fullwidth Header
        'et_pb_fullwidth_header' => array(
            'title' => 'LINE',
            'subhead' => 'LINE',
            'button_one_text' => 'LINE',
            'button_two_text' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Fullwidth Menu
        'et_pb_fullwidth_menu' => array(
            // Menu handled separately
        ),

        // Sidebar
        'et_pb_sidebar' => array(
            // Widget content - complex
        ),

        // Divider with text
        'et_pb_divider' => array(
            // No text
        ),

        // Search
        'et_pb_search' => array(
            'placeholder' => 'LINE',
            'button_text' => 'LINE',
        ),

        // Social Follow
        'et_pb_social_media_follow' => array(),
        'et_pb_social_media_follow_network' => array(
            '_content' => 'LINE', // Network name/label
        ),

        // Map
        'et_pb_map' => array(
            'title' => 'LINE',
        ),
        'et_pb_map_pin' => array(
            'title' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Portfolio
        'et_pb_portfolio' => array(),
        'et_pb_fullwidth_portfolio' => array(),

        // Filterable Portfolio
        'et_pb_filterable_portfolio' => array(),

        // Gallery
        'et_pb_gallery' => array(),

        // Shop
        'et_pb_shop' => array(),

        // Comments
        'et_pb_comments' => array(),

        // Login
        'et_pb_login' => array(
            'title' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Woo modules
        'et_pb_wc_title' => array(),
        'et_pb_wc_description' => array(),
        'et_pb_wc_add_to_cart' => array(),
        'et_pb_wc_price' => array(),
    );

    /**
     * Check if post has Divi content
     */
    public static function has_divi_content($post_id) {
        // Check if Divi builder is used
        $use_builder = get_post_meta($post_id, '_et_pb_use_builder', true);
        if ($use_builder === 'on') {
            return true;
        }

        // Also check if content contains Divi shortcodes
        $post = get_post($post_id);
        if ($post && strpos($post->post_content, '[et_pb_') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Check if Divi theme/plugin is active
     */
    public static function is_divi_active() {
        // Check for Divi Builder plugin first (no theme calls needed)
        if (defined('ET_BUILDER_PLUGIN_VERSION') || defined('ET_BUILDER_VERSION')) {
            return true;
        }

        // Check for Divi theme using options instead of wp_get_theme()
        // This avoids PHP 8.1+ deprecation warnings from wp_normalize_path()
        $stylesheet = get_option('stylesheet');
        $template = get_option('template');

        return ($stylesheet === 'Divi' || $template === 'Divi');
    }

    /**
     * Extract translatable fields from Divi content
     *
     * @param int $post_id Post ID
     * @return array Array of ['field_id' => [...field data...]]
     */
    public static function extract_fields($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        $content = $post->post_content;
        $fields = array();
        $index = 0;

        // Parse all Divi shortcodes
        $pattern = '/\[et_pb_([a-z_]+)([^\]]*)\](.*?)\[\/et_pb_\1\]|\[et_pb_([a-z_]+)([^\]]*)\]/s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $is_self_closing = !empty($match[4][0]);
            $tag = $is_self_closing ? $match[4][0] : $match[1][0];
            $shortcode_tag = 'et_pb_' . $tag;
            $attributes_str = $is_self_closing ? $match[5][0] : $match[2][0];
            $inner_content = $is_self_closing ? '' : $match[3][0];
            $offset = $match[0][1];

            // Skip structural elements
            if (in_array($shortcode_tag, array('et_pb_section', 'et_pb_row', 'et_pb_column', 'et_pb_row_inner', 'et_pb_column_inner'))) {
                continue;
            }

            // Get field definitions for this module
            $module_fields = isset(self::$module_fields[$shortcode_tag]) ? self::$module_fields[$shortcode_tag] : array();

            // Parse attributes
            $attributes = self::parse_shortcode_attributes($attributes_str);

            // Extract translatable attributes
            foreach ($module_fields as $attr_name => $field_type) {
                if ($field_type === false) continue; // Skip this attribute

                if ($attr_name === '_content') {
                    // Inner content
                    if (!empty(trim(strip_tags($inner_content)))) {
                        // Skip if inner content contains nested Divi shortcodes (will be processed separately)
                        if (strpos($inner_content, '[et_pb_') === false) {
                            $fields['divi_' . $index . '_content'] = array(
                                'module' => $shortcode_tag,
                                'field' => '_content',
                                'type' => $field_type,
                                'value' => $inner_content,
                                'offset' => $offset,
                            );
                            $index++;
                        }
                    }
                } elseif (isset($attributes[$attr_name]) && !empty(trim($attributes[$attr_name]))) {
                    $fields['divi_' . $index . '_' . $attr_name] = array(
                        'module' => $shortcode_tag,
                        'field' => $attr_name,
                        'type' => $field_type,
                        'value' => $attributes[$attr_name],
                        'offset' => $offset,
                    );
                    $index++;
                }
            }

            // Also extract admin_label if it looks like custom text
            if (isset($attributes['admin_label']) && !empty($attributes['admin_label'])) {
                // Skip default labels like "Text", "Button", etc.
                $default_labels = array('Text', 'Button', 'Image', 'Blurb', 'Slide', 'Tab', 'Item', 'Row', 'Section', 'Column');
                if (!in_array($attributes['admin_label'], $default_labels)) {
                    // This might be custom text worth translating, but usually admin_label is for backend only
                    // Skip for now
                }
            }
        }

        return $fields;
    }

    /**
     * Parse shortcode attributes string into array
     */
    private static function parse_shortcode_attributes($attr_string) {
        $attributes = array();

        // Match attribute="value" or attribute='value'
        preg_match_all('/([a-z_-]+)=["\']([^"\']*)["\']|([a-z_-]+)=([^\s\]]+)/i', $attr_string, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!empty($match[1])) {
                $attributes[$match[1]] = $match[2];
            } elseif (!empty($match[3])) {
                $attributes[$match[3]] = $match[4];
            }
        }

        return $attributes;
    }

    /**
     * Count translatable fields in Divi content
     */
    public static function count_translatable_fields($post_id) {
        $fields = self::extract_fields($post_id);
        return count($fields);
    }

    /**
     * Count translated fields
     */
    public static function count_translated_fields($post_id, $language_code) {
        $fields = self::extract_fields($post_id);
        $translated = 0;

        foreach ($fields as $field_id => $field_data) {
            $translation = Translio_DB::get_translation($post_id, 'divi', $field_id, $language_code);
            if ($translation && !empty($translation->translated_content)) {
                $translated++;
            }
        }

        return $translated;
    }

    /**
     * Get translation status for all fields
     */
    public static function get_fields_status($post_id, $language_code) {
        $fields = self::extract_fields($post_id);
        $status = array();

        foreach ($fields as $field_id => $field_data) {
            $translation = Translio_DB::get_translation($post_id, 'divi', $field_id, $language_code);

            $status[$field_id] = array(
                'module' => $field_data['module'],
                'field' => $field_data['field'],
                'type' => $field_data['type'],
                'original' => $field_data['value'],
                'translated' => $translation ? $translation->translated_content : '',
                'is_translated' => $translation && !empty($translation->translated_content),
                'needs_update' => $translation && $translation->original_hash !== md5($field_data['value']),
            );
        }

        return $status;
    }

    /**
     * Translate all Divi fields for a post
     */
    public static function translate_post($post_id, $language_code = null) {
        if (!$language_code) {
            $secondary_languages = translio()->get_secondary_languages();
            $language_code = !empty($secondary_languages) ? $secondary_languages[0] : '';
        }

        if (empty($language_code)) {
            return array();
        }

        $fields = self::extract_fields($post_id);
        if (empty($fields)) {
            return array();
        }

        $api = Translio_API::instance();
        $translations = array();

        // Prepare batch translation
        $texts_to_translate = array();
        foreach ($fields as $field_id => $field_data) {
            $context = self::get_field_context($field_data['module'], $field_data['field']);
            $texts_to_translate[] = array(
                'id' => $field_id,
                'text' => $field_data['value'],
                'context' => $context,
            );
        }

        // Batch translate
        $batch_result = $api->translate_batch($texts_to_translate, $language_code);

        if (is_wp_error($batch_result)) {
            return $batch_result;
        }

        // Save translations
        foreach ($fields as $field_id => $field_data) {
            if (isset($batch_result[$field_id])) {
                Translio_DB::save_translation(
                    $post_id,
                    'divi',
                    $field_id,
                    $language_code,
                    $field_data['value'],
                    $batch_result[$field_id],
                    true
                );
                $translations[$field_id] = $batch_result[$field_id];
            }
        }

        return $translations;
    }

    /**
     * Get context hint for field
     */
    private static function get_field_context($module, $field) {
        $module_names = array(
            'et_pb_text' => 'Text Block',
            'et_pb_button' => 'Button',
            'et_pb_cta' => 'Call to Action',
            'et_pb_blurb' => 'Blurb/Feature Box',
            'et_pb_slide' => 'Slider Slide',
            'et_pb_tab' => 'Tab',
            'et_pb_accordion_item' => 'Accordion Item',
            'et_pb_toggle' => 'Toggle',
            'et_pb_testimonial' => 'Testimonial',
            'et_pb_pricing_table' => 'Pricing Table',
            'et_pb_team_member' => 'Team Member',
            'et_pb_contact_form' => 'Contact Form',
            'et_pb_contact_field' => 'Form Field',
            'et_pb_fullwidth_header' => 'Header Section',
            'et_pb_signup' => 'Email Signup Form',
            'et_pb_login' => 'Login Form',
        );

        $field_names = array(
            'title' => 'Title',
            'heading' => 'Heading',
            'button_text' => 'Button Text',
            'button_one_text' => 'Button 1 Text',
            'button_two_text' => 'Button 2 Text',
            '_content' => 'Content',
            'subtitle' => 'Subtitle',
            'subhead' => 'Subheading',
            'author' => 'Author Name',
            'job_title' => 'Job Title',
            'company_name' => 'Company Name',
            'position' => 'Position',
            'name' => 'Name',
            'field_title' => 'Field Label',
            'success_message' => 'Success Message',
            'submit_button_text' => 'Submit Button',
            'placeholder' => 'Placeholder Text',
        );

        $module_name = isset($module_names[$module]) ? $module_names[$module] : str_replace('et_pb_', '', $module);
        $field_name = isset($field_names[$field]) ? $field_names[$field] : $field;

        return "Divi {$module_name} - {$field_name}";
    }

    /**
     * Apply translations to Divi content
     * Used when rendering translated page
     */
    public static function apply_translations($content, $post_id, $language_code) {
        if (empty($content) || strpos($content, '[et_pb_') === false) {
            return $content;
        }

        $fields = self::extract_fields($post_id);

        // Get all translations for this post
        $translations = array();
        foreach ($fields as $field_id => $field_data) {
            $translation = Translio_DB::get_translation($post_id, 'divi', $field_id, $language_code);
            if ($translation && !empty($translation->translated_content)) {
                $translations[$field_id] = array(
                    'original' => $field_data['value'],
                    'translated' => $translation->translated_content,
                    'field' => $field_data['field'],
                );
            }
        }

        if (empty($translations)) {
            return $content;
        }

        // Apply translations
        foreach ($translations as $field_id => $trans) {
            if ($trans['field'] === '_content') {
                // Replace inner content - need to be careful with nested shortcodes
                $content = str_replace($trans['original'], $trans['translated'], $content);
            } else {
                // Replace attribute value
                $pattern = '/(' . preg_quote($trans['field'], '/') . '=["\'])' . preg_quote($trans['original'], '/') . '(["\'])/';
                $content = preg_replace($pattern, '$1' . addslashes($trans['translated']) . '$2', $content);
            }
        }

        return $content;
    }
}
