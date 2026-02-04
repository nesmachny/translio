<?php
/**
 * Translio Elementor Integration
 * Handles translation of Elementor page builder content
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Elementor {

    /**
     * Translatable widget fields mapping
     * widget_type => [field_name => field_type]
     * Types: LINE (single line), VISUAL (HTML/rich text), AREA (multiline plain text)
     */
    private static $widget_fields = array(
        // Text widgets
        'heading' => array(
            'title' => 'LINE',
        ),
        'text-editor' => array(
            'editor' => 'VISUAL',
        ),
        'button' => array(
            'text' => 'LINE',
        ),

        // Image widgets
        'image' => array(
            'caption' => 'LINE',
        ),
        'image-box' => array(
            'title_text' => 'LINE',
            'description_text' => 'VISUAL',
        ),
        'image-gallery' => array(
            // Gallery captions handled separately
        ),

        // Icon widgets
        'icon-box' => array(
            'title_text' => 'LINE',
            'description_text' => 'VISUAL',
        ),
        'icon-list' => array(
            // Repeater: items[].text
        ),

        // Call to action
        'call-to-action' => array(
            'title' => 'LINE',
            'description' => 'VISUAL',
            'button' => 'LINE',
            'ribbon_title' => 'LINE',
        ),

        // Testimonials
        'testimonial' => array(
            'testimonial_content' => 'VISUAL',
            'testimonial_name' => 'LINE',
            'testimonial_job' => 'LINE',
        ),

        // Tabs, Accordion, Toggle
        'tabs' => array(
            // Repeater: tabs[].tab_title, tabs[].tab_content
        ),
        'accordion' => array(
            // Repeater: tabs[].tab_title, tabs[].tab_content
        ),
        'toggle' => array(
            // Repeater: tabs[].tab_title, tabs[].tab_content
        ),

        // Alert
        'alert' => array(
            'alert_title' => 'LINE',
            'alert_description' => 'VISUAL',
        ),

        // Counter
        'counter' => array(
            'title' => 'LINE',
            'suffix' => 'LINE',
            'prefix' => 'LINE',
        ),

        // Progress bar
        'progress' => array(
            'title' => 'LINE',
            'inner_text' => 'LINE',
        ),

        // Animated headline
        'animated-headline' => array(
            'before_text' => 'LINE',
            'highlighted_text' => 'LINE',
            'rotating_text' => 'AREA', // Newline separated
            'after_text' => 'LINE',
        ),

        // Price table
        'price-table' => array(
            'heading' => 'LINE',
            'sub_heading' => 'LINE',
            'period' => 'LINE',
            'button_text' => 'LINE',
            'footer_additional_info' => 'VISUAL',
            'ribbon_title' => 'LINE',
            // Repeater: features_list[].item_text
        ),

        // Price list
        'price-list' => array(
            // Repeater: price_list[].title, price_list[].description, price_list[].price
        ),

        // Flip box
        'flip-box' => array(
            'title_text_a' => 'LINE',
            'description_text_a' => 'VISUAL',
            'title_text_b' => 'LINE',
            'description_text_b' => 'VISUAL',
            'button_text' => 'LINE',
        ),

        // Slides
        'slides' => array(
            // Repeater: slides[].heading, slides[].description, slides[].button_text
        ),

        // Form
        'form' => array(
            'form_name' => 'LINE',
            'button_text' => 'LINE',
            'success_message' => 'LINE',
            'error_message' => 'LINE',
            'required_field_message' => 'LINE',
            'invalid_message' => 'LINE',
            // Repeater: form_fields[].field_label, form_fields[].placeholder
        ),

        // Nav menu
        'nav-menu' => array(
            // Menu items translated separately via nav_menu filters
        ),

        // Posts/Archive
        'posts' => array(
            'nothing_found_message' => 'LINE',
        ),

        // Search form
        'search-form' => array(
            'placeholder' => 'LINE',
        ),

        // Login form
        'login' => array(
            'button_text' => 'LINE',
            'user_label' => 'LINE',
            'user_placeholder' => 'LINE',
            'password_label' => 'LINE',
            'password_placeholder' => 'LINE',
        ),

        // Blockquote
        'blockquote' => array(
            'blockquote_content' => 'VISUAL',
            'tweet_button_label' => 'LINE',
        ),
    );

    /**
     * Repeater fields mapping
     * widget_type => [repeater_field => [item_fields]]
     */
    private static $repeater_fields = array(
        'icon-list' => array(
            'icon_list' => array('text'),
        ),
        'tabs' => array(
            'tabs' => array('tab_title', 'tab_content'),
        ),
        'accordion' => array(
            'tabs' => array('tab_title', 'tab_content'),
        ),
        'toggle' => array(
            'tabs' => array('tab_title', 'tab_content'),
        ),
        'price-table' => array(
            'features_list' => array('item_text'),
        ),
        'price-list' => array(
            'price_list' => array('title', 'description', 'price'),
        ),
        'slides' => array(
            'slides' => array('heading', 'description', 'button_text'),
        ),
        'form' => array(
            'form_fields' => array('field_label', 'placeholder'),
        ),
        'social-icons' => array(
            'social_icon_list' => array('text'),
        ),
    );

    /**
     * Check if Elementor is active
     */
    public static function is_active() {
        return defined('ELEMENTOR_VERSION') || class_exists('\Elementor\Plugin');
    }

    /**
     * Check if post has Elementor data
     */
    public static function has_elementor_data($post_id) {
        $data = get_post_meta($post_id, '_elementor_data', true);
        return !empty($data) && $data !== '[]';
    }

    /**
     * Get Elementor data for a post
     */
    public static function get_elementor_data($post_id) {
        $data = get_post_meta($post_id, '_elementor_data', true);
        if (empty($data)) {
            return array();
        }

        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Extract all translatable strings from Elementor data
     *
     * @param int $post_id Post ID
     * @return array Array of translatable items with element_id, field, content, type
     */
    public static function extract_translatable_strings($post_id) {
        $data = self::get_elementor_data($post_id);
        if (empty($data)) {
            return array();
        }

        $strings = array();
        self::extract_from_elements($data, $strings);

        return $strings;
    }

    /**
     * Recursively extract translatable strings from elements
     */
    private static function extract_from_elements($elements, &$strings) {
        foreach ($elements as $element) {
            $element_id = isset($element['id']) ? $element['id'] : '';
            $widget_type = isset($element['widgetType']) ? $element['widgetType'] : '';
            $settings = isset($element['settings']) ? $element['settings'] : array();

            // Extract from widget settings
            if (!empty($widget_type) && !empty($element_id)) {
                // Regular fields
                if (isset(self::$widget_fields[$widget_type])) {
                    foreach (self::$widget_fields[$widget_type] as $field => $type) {
                        if (!empty($settings[$field])) {
                            $content = $settings[$field];
                            // Skip if it's just whitespace
                            if (is_string($content) && trim(strip_tags($content)) !== '') {
                                $strings[] = array(
                                    'element_id' => $element_id,
                                    'widget_type' => $widget_type,
                                    'field' => $field,
                                    'content' => $content,
                                    'type' => $type,
                                    'is_repeater' => false,
                                );
                            }
                        }
                    }
                }

                // Repeater fields
                if (isset(self::$repeater_fields[$widget_type])) {
                    foreach (self::$repeater_fields[$widget_type] as $repeater_name => $item_fields) {
                        if (!empty($settings[$repeater_name]) && is_array($settings[$repeater_name])) {
                            foreach ($settings[$repeater_name] as $index => $item) {
                                $item_id = isset($item['_id']) ? $item['_id'] : $index;
                                foreach ($item_fields as $field) {
                                    if (!empty($item[$field])) {
                                        $content = $item[$field];
                                        if (is_string($content) && trim(strip_tags($content)) !== '') {
                                            $strings[] = array(
                                                'element_id' => $element_id,
                                                'widget_type' => $widget_type,
                                                'field' => $repeater_name . '.' . $item_id . '.' . $field,
                                                'content' => $content,
                                                'type' => 'LINE',
                                                'is_repeater' => true,
                                                'repeater_name' => $repeater_name,
                                                'item_id' => $item_id,
                                                'item_field' => $field,
                                            );
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Recurse into nested elements
            if (!empty($element['elements']) && is_array($element['elements'])) {
                self::extract_from_elements($element['elements'], $strings);
            }
        }
    }

    /**
     * Get field_name for database storage (includes element_id to ensure uniqueness)
     * Format: element_id:field_name
     */
    public static function get_field_key($element_id, $field) {
        return $element_id . ':' . $field;
    }

    /**
     * Save Elementor field translation
     * Uses post_id as object_id and element_id:field as field_name for uniqueness
     */
    public static function save_translation($post_id, $element_id, $field, $language_code, $original, $translated, $is_auto = false) {
        $field_key = self::get_field_key($element_id, $field);

        return Translio_DB::save_translation(
            $post_id,           // Use actual post_id (no CRC32!)
            'elementor',
            $field_key,         // element_id:field format
            $language_code,
            $original,
            $translated,
            $is_auto
        );
    }

    /**
     * Get Elementor field translation
     */
    public static function get_translation($post_id, $element_id, $field, $language_code) {
        $field_key = self::get_field_key($element_id, $field);
        return Translio_DB::get_translation($post_id, 'elementor', $field_key, $language_code);
    }

    /**
     * Preload all translations for a post's Elementor content
     * Call this before iterating through strings to avoid N+1 queries
     *
     * @param int $post_id Post ID
     * @param array $strings Array from extract_translatable_strings
     * @param string $language_code Target language
     */
    public static function preload_translations($post_id, $strings, $language_code) {
        if (empty($strings)) {
            return;
        }

        $items = array();
        foreach ($strings as $string) {
            $items[] = array(
                'object_id' => $post_id,
                'object_type' => 'elementor',
                'field_name' => self::get_field_key($string['element_id'], $string['field'])
            );
        }

        // This loads all translations in a single query and caches them
        Translio_DB::get_translations_batch($items, $language_code);
    }

    /**
     * Migration: Convert old CRC32-based translations to new format
     * Call this once during plugin update
     */
    public static function migrate_old_translations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'translio_translations';

        // This is a placeholder - actual migration would need to:
        // 1. Find all elementor translations
        // 2. Map old CRC32 object_ids to post_id + element_id:field
        // 3. Update or insert new format records
        // 4. Delete old format records

        // For now, we just log that migration is needed
        error_log('Translio: Elementor translation format has changed. Old translations may need migration.');
    }

    /**
     * Get translated Elementor data for frontend rendering
     */
    public static function get_translated_data($post_id, $language_code) {
        $data = self::get_elementor_data($post_id);
        if (empty($data)) {
            return $data;
        }

        // Apply translations recursively
        self::apply_translations_to_elements($data, $post_id, $language_code);

        return $data;
    }

    /**
     * Recursively apply translations to elements
     */
    private static function apply_translations_to_elements(&$elements, $post_id, $language_code) {
        foreach ($elements as &$element) {
            $element_id = isset($element['id']) ? $element['id'] : '';
            $widget_type = isset($element['widgetType']) ? $element['widgetType'] : '';

            if (!empty($widget_type) && !empty($element_id) && isset($element['settings'])) {
                // Regular fields
                if (isset(self::$widget_fields[$widget_type])) {
                    foreach (self::$widget_fields[$widget_type] as $field => $type) {
                        if (!empty($element['settings'][$field])) {
                            $translation = self::get_translation($post_id, $element_id, $field, $language_code);
                            if ($translation && !empty($translation->translated_content)) {
                                $element['settings'][$field] = $translation->translated_content;
                            }
                        }
                    }
                }

                // Repeater fields
                if (isset(self::$repeater_fields[$widget_type])) {
                    foreach (self::$repeater_fields[$widget_type] as $repeater_name => $item_fields) {
                        if (!empty($element['settings'][$repeater_name]) && is_array($element['settings'][$repeater_name])) {
                            foreach ($element['settings'][$repeater_name] as $index => &$item) {
                                $item_id = isset($item['_id']) ? $item['_id'] : $index;
                                foreach ($item_fields as $field) {
                                    if (!empty($item[$field])) {
                                        $full_field = $repeater_name . '.' . $item_id . '.' . $field;
                                        $translation = self::get_translation($post_id, $element_id, $full_field, $language_code);
                                        if ($translation && !empty($translation->translated_content)) {
                                            $item[$field] = $translation->translated_content;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Recurse into nested elements
            if (!empty($element['elements']) && is_array($element['elements'])) {
                self::apply_translations_to_elements($element['elements'], $post_id, $language_code);
            }
        }
    }

    /**
     * Count translatable fields for a post
     */
    public static function count_translatable_fields($post_id) {
        $strings = self::extract_translatable_strings($post_id);
        return count($strings);
    }

    /**
     * Count translated fields for a post
     */
    public static function count_translated_fields($post_id, $language_code) {
        $strings = self::extract_translatable_strings($post_id);
        $translated = 0;

        foreach ($strings as $string) {
            $translation = self::get_translation($post_id, $string['element_id'], $string['field'], $language_code);
            if ($translation && !empty($translation->translated_content)) {
                $translated++;
            }
        }

        return $translated;
    }

    /**
     * Get translation status for a post
     * Returns: 'complete', 'partial', 'none'
     */
    public static function get_translation_status($post_id, $language_code) {
        $total = self::count_translatable_fields($post_id);
        if ($total === 0) {
            return 'none';
        }

        $translated = self::count_translated_fields($post_id, $language_code);

        if ($translated === 0) {
            return 'none';
        } elseif ($translated >= $total) {
            return 'complete';
        } else {
            return 'partial';
        }
    }

    /**
     * Clear Elementor cache after translation update
     */
    public static function clear_cache($post_id = null) {
        if (class_exists('\Elementor\Plugin')) {
            if ($post_id) {
                // Clear specific post cache
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            } else {
                // Clear all cache
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }
        }
    }

    /**
     * Get widget type label
     */
    public static function get_widget_label($widget_type) {
        $labels = array(
            'heading' => 'Heading',
            'text-editor' => 'Text Editor',
            'button' => 'Button',
            'image' => 'Image',
            'image-box' => 'Image Box',
            'icon-box' => 'Icon Box',
            'icon-list' => 'Icon List',
            'call-to-action' => 'Call to Action',
            'testimonial' => 'Testimonial',
            'tabs' => 'Tabs',
            'accordion' => 'Accordion',
            'toggle' => 'Toggle',
            'alert' => 'Alert',
            'counter' => 'Counter',
            'progress' => 'Progress Bar',
            'animated-headline' => 'Animated Headline',
            'price-table' => 'Price Table',
            'price-list' => 'Price List',
            'flip-box' => 'Flip Box',
            'slides' => 'Slides',
            'form' => 'Form',
            'posts' => 'Posts',
            'search-form' => 'Search Form',
            'login' => 'Login Form',
            'blockquote' => 'Blockquote',
        );

        return isset($labels[$widget_type]) ? $labels[$widget_type] : ucfirst(str_replace('-', ' ', $widget_type));
    }

    /**
     * Get field label
     */
    public static function get_field_label($field) {
        $labels = array(
            'title' => 'Title',
            'text' => 'Text',
            'editor' => 'Content',
            'description' => 'Description',
            'description_text' => 'Description',
            'title_text' => 'Title',
            'button_text' => 'Button Text',
            'heading' => 'Heading',
            'tab_title' => 'Tab Title',
            'tab_content' => 'Tab Content',
            'item_text' => 'Item Text',
            'field_label' => 'Field Label',
            'placeholder' => 'Placeholder',
            'caption' => 'Caption',
            'testimonial_content' => 'Content',
            'testimonial_name' => 'Name',
            'testimonial_job' => 'Job Title',
            'alert_title' => 'Title',
            'alert_description' => 'Description',
            'before_text' => 'Before Text',
            'after_text' => 'After Text',
            'highlighted_text' => 'Highlighted Text',
            'rotating_text' => 'Rotating Text',
            'ribbon_title' => 'Ribbon Title',
            'sub_heading' => 'Sub Heading',
            'period' => 'Period',
            'footer_additional_info' => 'Footer Info',
            'price' => 'Price',
            'success_message' => 'Success Message',
            'error_message' => 'Error Message',
            'nothing_found_message' => 'Nothing Found Message',
        );

        // Handle repeater field paths like "slides.abc123.heading"
        $parts = explode('.', $field);
        $last_part = end($parts);

        return isset($labels[$last_part]) ? $labels[$last_part] : ucfirst(str_replace('_', ' ', $last_part));
    }
}
