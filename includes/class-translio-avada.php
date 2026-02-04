<?php
/**
 * Translio Avada (Fusion Builder) Integration
 * Handles translation of Avada/Fusion Builder content
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Avada {

    /**
     * Fusion Builder element shortcode tags with translatable attributes
     * shortcode_tag => [attribute => field_type]
     * Types: LINE (single line), VISUAL (HTML/rich text), AREA (multiline)
     */
    private static $element_fields = array(
        // Text Elements
        'fusion_text' => array(
            '_content' => 'VISUAL',
        ),
        'fusion_title' => array(
            '_content' => 'LINE',
        ),

        // Buttons
        'fusion_button' => array(
            '_content' => 'LINE', // Button text
            'link' => false, // Skip URLs
        ),
        'fusion_tagline_box' => array(
            'title' => 'LINE',
            'description' => 'VISUAL',
            'button' => 'LINE',
        ),

        // Call to Action
        'fusion_content_boxes' => array(),
        'fusion_content_box' => array(
            'title' => 'LINE',
            '_content' => 'VISUAL',
            'link_text' => 'LINE',
        ),

        // Alert
        'fusion_alert' => array(
            '_content' => 'VISUAL',
        ),

        // Accordion/FAQ
        'fusion_accordion' => array(),
        'fusion_toggle' => array(
            'title' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Tabs
        'fusion_tabs' => array(),
        'fusion_tab' => array(
            'title' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Slider
        'fusion_slider' => array(),
        'fusion_slide' => array(
            'heading' => 'LINE',
            'caption' => 'VISUAL',
            'button_1' => 'LINE',
            'button_2' => 'LINE',
        ),

        // Blog
        'fusion_blog' => array(),

        // Testimonials
        'fusion_testimonials' => array(),
        'fusion_testimonial' => array(
            'name' => 'LINE',
            'company' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Counters
        'fusion_counters_circle' => array(),
        'fusion_counter_circle' => array(
            '_content' => 'LINE',
        ),
        'fusion_counters_box' => array(),
        'fusion_counter_box' => array(
            '_content' => 'LINE',
            'unit' => 'LINE',
            'unit_pos' => false,
        ),

        // Progress Bar
        'fusion_progress' => array(
            '_content' => 'LINE', // Bar label
            'unit' => 'LINE',
        ),

        // Person/Team Member
        'fusion_person' => array(
            'name' => 'LINE',
            'title' => 'LINE',
            '_content' => 'VISUAL',
        ),

        // Flip Boxes
        'fusion_flip_boxes' => array(),
        'fusion_flip_box' => array(
            'title_front' => 'LINE',
            'title_back' => 'LINE',
            '_content' => 'VISUAL', // Back content
        ),

        // Pricing Table
        'fusion_pricing_table' => array(),
        'fusion_pricing_column' => array(
            'title' => 'LINE',
            'standout' => 'LINE',
        ),
        'fusion_pricing_price' => array(
            'currency' => 'LINE',
            'price' => 'LINE',
            'time' => 'LINE',
        ),
        'fusion_pricing_row' => array(
            '_content' => 'LINE',
        ),
        'fusion_pricing_footer' => array(
            '_content' => 'LINE',
        ),

        // Modal
        'fusion_modal' => array(
            'title' => 'LINE',
            '_content' => 'VISUAL',
        ),
        'fusion_modal_text_link' => array(
            '_content' => 'LINE',
        ),

        // Popover
        'fusion_popover' => array(
            'title' => 'LINE',
            'content' => 'VISUAL',
            '_content' => 'LINE', // Trigger text
        ),

        // Tooltip
        'fusion_tooltip' => array(
            'title' => 'LINE',
            '_content' => 'LINE',
        ),

        // Highlight
        'fusion_highlight' => array(
            '_content' => 'LINE',
        ),

        // Dropcap
        'fusion_dropcap' => array(
            '_content' => 'LINE',
        ),

        // Checklist
        'fusion_checklist' => array(),
        'fusion_li_item' => array(
            '_content' => 'LINE',
        ),

        // Image
        'fusion_imageframe' => array(
            'alt' => 'LINE',
        ),

        // Gallery
        'fusion_gallery' => array(),

        // Image Carousel
        'fusion_images' => array(),
        'fusion_image' => array(
            'alt' => 'LINE',
        ),

        // Layer Slider
        'fusion_layerslider' => array(),

        // Rev Slider
        'fusion_slider_revolution' => array(),

        // Video
        'fusion_video' => array(),

        // YouTube/Vimeo
        'fusion_youtube' => array(),
        'fusion_vimeo' => array(),

        // Google Map
        'fusion_map' => array(
            'address' => 'LINE',
        ),

        // Social Links
        'fusion_social_links' => array(),

        // Sharing Box
        'fusion_sharing' => array(
            'title' => 'LINE',
        ),

        // Recent Posts
        'fusion_recent_posts' => array(),

        // Events
        'fusion_events' => array(),

        // Menu
        'fusion_menu' => array(),

        // Woo Elements
        'fusion_woo_shortcodes' => array(),

        // Form Elements
        'fusion_form' => array(
            'submit_text' => 'LINE',
        ),
        'fusion_form_text' => array(
            'label' => 'LINE',
            'placeholder' => 'LINE',
        ),
        'fusion_form_textarea' => array(
            'label' => 'LINE',
            'placeholder' => 'LINE',
        ),
        'fusion_form_email' => array(
            'label' => 'LINE',
            'placeholder' => 'LINE',
        ),
        'fusion_form_phone' => array(
            'label' => 'LINE',
            'placeholder' => 'LINE',
        ),
        'fusion_form_select' => array(
            'label' => 'LINE',
            'placeholder' => 'LINE',
        ),
        'fusion_form_checkbox' => array(
            'label' => 'LINE',
        ),
        'fusion_form_radio' => array(
            'label' => 'LINE',
        ),
        'fusion_form_submit' => array(
            '_content' => 'LINE',
        ),

        // Breadcrumbs
        'fusion_breadcrumbs' => array(),

        // Search
        'fusion_search' => array(
            'placeholder' => 'LINE',
        ),
    );

    /**
     * Check if post has Avada/Fusion Builder content
     */
    public static function has_avada_content($post_id) {
        $post = get_post($post_id);
        if ($post && strpos($post->post_content, '[fusion_') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Check if Avada theme or Fusion Builder is active
     */
    public static function is_avada_active() {
        // Check for Fusion Builder plugin first (no theme calls needed)
        if (defined('FUSION_BUILDER_VERSION') || class_exists('FusionBuilder')) {
            return true;
        }

        // Check for Avada theme using options instead of wp_get_theme()
        // This avoids PHP 8.1+ deprecation warnings from wp_normalize_path()
        $stylesheet = get_option('stylesheet');
        $template = get_option('template');

        return ($stylesheet === 'Avada' || $template === 'Avada');
    }

    /**
     * Extract translatable fields from Avada/Fusion content
     */
    public static function extract_fields($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        $content = $post->post_content;
        $fields = array();
        $index = 0;

        // Parse all Fusion shortcodes
        $pattern = '/\[fusion_([a-z_]+)([^\]]*)\](.*?)\[\/fusion_\1\]|\[fusion_([a-z_]+)([^\]]*)\]/s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $is_self_closing = !empty($match[4][0]);
            $tag = $is_self_closing ? $match[4][0] : $match[1][0];
            $shortcode_tag = 'fusion_' . $tag;
            $attributes_str = $is_self_closing ? $match[5][0] : $match[2][0];
            $inner_content = $is_self_closing ? '' : $match[3][0];
            $offset = $match[0][1];

            // Skip structural elements
            if (in_array($shortcode_tag, array('fusion_builder_container', 'fusion_builder_row', 'fusion_builder_column', 'fusion_builder_row_inner', 'fusion_builder_column_inner', 'fusion_builder_blank_page'))) {
                continue;
            }

            // Get field definitions for this element
            $element_fields = isset(self::$element_fields[$shortcode_tag]) ? self::$element_fields[$shortcode_tag] : array();

            // Parse attributes
            $attributes = self::parse_shortcode_attributes($attributes_str);

            // Extract translatable attributes
            foreach ($element_fields as $attr_name => $field_type) {
                if ($field_type === false) continue;

                if ($attr_name === '_content') {
                    // Inner content
                    if (!empty(trim(strip_tags($inner_content)))) {
                        // Skip if inner content contains nested Fusion shortcodes
                        if (strpos($inner_content, '[fusion_') === false) {
                            $fields['avada_' . $index . '_content'] = array(
                                'element' => $shortcode_tag,
                                'field' => '_content',
                                'type' => $field_type,
                                'value' => $inner_content,
                                'offset' => $offset,
                            );
                            $index++;
                        }
                    }
                } elseif (isset($attributes[$attr_name]) && !empty(trim($attributes[$attr_name]))) {
                    $fields['avada_' . $index . '_' . $attr_name] = array(
                        'element' => $shortcode_tag,
                        'field' => $attr_name,
                        'type' => $field_type,
                        'value' => $attributes[$attr_name],
                        'offset' => $offset,
                    );
                    $index++;
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
     * Count translatable fields
     */
    public static function count_translatable_fields($post_id) {
        return count(self::extract_fields($post_id));
    }

    /**
     * Count translated fields
     */
    public static function count_translated_fields($post_id, $language_code) {
        $fields = self::extract_fields($post_id);
        $translated = 0;

        foreach ($fields as $field_id => $field_data) {
            $translation = Translio_DB::get_translation($post_id, 'avada', $field_id, $language_code);
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
            $translation = Translio_DB::get_translation($post_id, 'avada', $field_id, $language_code);

            $status[$field_id] = array(
                'element' => $field_data['element'],
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
     * Get context hint for field
     */
    private static function get_field_context($element, $field) {
        $element_names = array(
            'fusion_text' => 'Text Block',
            'fusion_title' => 'Title',
            'fusion_button' => 'Button',
            'fusion_tagline_box' => 'Tagline Box',
            'fusion_content_box' => 'Content Box',
            'fusion_alert' => 'Alert',
            'fusion_toggle' => 'Toggle/Accordion',
            'fusion_tab' => 'Tab',
            'fusion_slide' => 'Slider Slide',
            'fusion_testimonial' => 'Testimonial',
            'fusion_counter_circle' => 'Circle Counter',
            'fusion_counter_box' => 'Counter Box',
            'fusion_progress' => 'Progress Bar',
            'fusion_person' => 'Person/Team Member',
            'fusion_flip_box' => 'Flip Box',
            'fusion_pricing_column' => 'Pricing Column',
            'fusion_modal' => 'Modal',
            'fusion_popover' => 'Popover',
            'fusion_tooltip' => 'Tooltip',
            'fusion_checklist' => 'Checklist',
            'fusion_li_item' => 'List Item',
        );

        $field_names = array(
            'title' => 'Title',
            'heading' => 'Heading',
            'name' => 'Name',
            'company' => 'Company',
            '_content' => 'Content',
            'description' => 'Description',
            'caption' => 'Caption',
            'button' => 'Button Text',
            'button_1' => 'Button 1',
            'button_2' => 'Button 2',
            'link_text' => 'Link Text',
            'label' => 'Label',
            'placeholder' => 'Placeholder',
            'submit_text' => 'Submit Button',
            'title_front' => 'Front Title',
            'title_back' => 'Back Title',
        );

        $element_name = isset($element_names[$element]) ? $element_names[$element] : str_replace('fusion_', '', $element);
        $field_name = isset($field_names[$field]) ? $field_names[$field] : $field;

        return "Avada {$element_name} - {$field_name}";
    }

    /**
     * Apply translations to Avada content
     */
    public static function apply_translations($content, $post_id, $language_code) {
        if (empty($content) || strpos($content, '[fusion_') === false) {
            return $content;
        }

        $fields = self::extract_fields($post_id);

        $translations = array();
        foreach ($fields as $field_id => $field_data) {
            $translation = Translio_DB::get_translation($post_id, 'avada', $field_id, $language_code);
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

        foreach ($translations as $field_id => $trans) {
            if ($trans['field'] === '_content') {
                $content = str_replace($trans['original'], $trans['translated'], $content);
            } else {
                $pattern = '/(' . preg_quote($trans['field'], '/') . '=["\'])' . preg_quote($trans['original'], '/') . '(["\'])/';
                $content = preg_replace($pattern, '$1' . addslashes($trans['translated']) . '$2', $content);
            }
        }

        return $content;
    }
}
