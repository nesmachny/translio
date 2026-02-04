<?php
/**
 * Translio Translatable Interface
 *
 * Abstract interface for content types that can be translated.
 * All content type handlers should implement this interface for consistency.
 */

if (!defined('ABSPATH')) {
    exit;
}

interface Translio_Translatable {

    /**
     * Get the object type identifier
     * Used in database as object_type field
     *
     * @return string Object type (e.g., 'post', 'term', 'option', 'elementor', 'cf7_form')
     */
    public static function get_object_type();

    /**
     * Extract translatable fields from an object
     *
     * @param int|string $object_id Object identifier
     * @return array Array of translatable fields with format:
     *               [
     *                   'id' => 'field_identifier',
     *                   'text' => 'original text',
     *                   'context' => 'translation context hint',
     *                   'label' => 'Human-readable label'
     *               ]
     */
    public static function extract_fields($object_id);

    /**
     * Get translation for a specific field
     *
     * @param int|string $object_id Object identifier
     * @param string $field_name Field name
     * @param string $language_code Target language code
     * @return object|null Translation object or null
     */
    public static function get_translation($object_id, $field_name, $language_code);

    /**
     * Save translation for a specific field
     *
     * @param int|string $object_id Object identifier
     * @param string $field_name Field name
     * @param string $language_code Target language code
     * @param string $original Original content
     * @param string $translated Translated content
     * @param bool $is_auto Whether translation was auto-generated
     * @return bool Success status
     */
    public static function save_translation($object_id, $field_name, $language_code, $original, $translated, $is_auto = false);

    /**
     * Count total translatable fields for an object
     *
     * @param int|string $object_id Object identifier
     * @return int Number of translatable fields
     */
    public static function count_fields($object_id);

    /**
     * Count translated fields for an object
     *
     * @param int|string $object_id Object identifier
     * @param string $language_code Target language code
     * @return int Number of translated fields
     */
    public static function count_translated($object_id, $language_code);

    /**
     * Get translation status for an object
     *
     * @param int|string $object_id Object identifier
     * @param string $language_code Target language code
     * @return string Status: 'none', 'partial', 'complete'
     */
    public static function get_status($object_id, $language_code);

    /**
     * Check if this handler supports the given object
     *
     * @param int|string $object_id Object identifier
     * @return bool Whether this handler can process the object
     */
    public static function supports($object_id);
}

/**
 * Abstract base class implementing common functionality
 */
abstract class Translio_Translatable_Base implements Translio_Translatable {

    /**
     * Default implementation of count_fields
     */
    public static function count_fields($object_id) {
        $fields = static::extract_fields($object_id);
        return count($fields);
    }

    /**
     * Default implementation of count_translated
     */
    public static function count_translated($object_id, $language_code) {
        $fields = static::extract_fields($object_id);
        $translated = 0;

        foreach ($fields as $field) {
            $translation = static::get_translation($object_id, $field['id'], $language_code);
            if ($translation && !empty($translation->translated_content)) {
                $translated++;
            }
        }

        return $translated;
    }

    /**
     * Default implementation of get_status
     */
    public static function get_status($object_id, $language_code) {
        $total = static::count_fields($object_id);

        if ($total === 0) {
            return 'none';
        }

        $translated = static::count_translated($object_id, $language_code);

        if ($translated === 0) {
            return 'none';
        } elseif ($translated >= $total) {
            return 'complete';
        } else {
            return 'partial';
        }
    }

    /**
     * Default implementation of get_translation using DB
     */
    public static function get_translation($object_id, $field_name, $language_code) {
        return Translio_DB::get_translation(
            $object_id,
            static::get_object_type(),
            $field_name,
            $language_code
        );
    }

    /**
     * Default implementation of save_translation using DB
     */
    public static function save_translation($object_id, $field_name, $language_code, $original, $translated, $is_auto = false) {
        return Translio_DB::save_translation(
            $object_id,
            static::get_object_type(),
            $field_name,
            $language_code,
            $original,
            $translated,
            $is_auto
        );
    }

    /**
     * Translate all fields for an object
     *
     * @param int|string $object_id Object identifier
     * @param string $language_code Target language code
     * @return array|WP_Error Translations array or error
     */
    public static function translate_all($object_id, $language_code) {
        $fields = static::extract_fields($object_id);

        if (empty($fields)) {
            return array();
        }

        $api = Translio_API::instance();
        $result = $api->translate_batch($fields, $language_code);

        if (is_wp_error($result)) {
            return $result;
        }

        // Save translations
        $translations = array();
        foreach ($fields as $field) {
            if (isset($result[$field['id']])) {
                static::save_translation(
                    $object_id,
                    $field['id'],
                    $language_code,
                    $field['text'],
                    $result[$field['id']],
                    true
                );
                $translations[$field['id']] = $result[$field['id']];
            }
        }

        return $translations;
    }
}
