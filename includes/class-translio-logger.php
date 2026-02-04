<?php
/**
 * Translio Logger
 *
 * Centralized logging for translation paths and debugging.
 * Logs can be enabled via TRANSLIO_DEBUG constant or translio_debug option.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Logger {

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    /**
     * Log categories for filtering
     */
    const CAT_TRANSLATION = 'translation';
    const CAT_API = 'api';
    const CAT_CONTENT = 'content';
    const CAT_ROUTER = 'router';
    const CAT_ELEMENTOR = 'elementor';
    const CAT_CF7 = 'cf7';
    const CAT_DB = 'db';

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * In-memory log buffer for current request
     */
    private $log_buffer = array();

    /**
     * Whether logging is enabled
     */
    private $enabled = false;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        $this->enabled = $this->is_logging_enabled();

        if ($this->enabled) {
            add_action('shutdown', array($this, 'flush_buffer'));
        }
    }

    /**
     * Check if logging is enabled
     */
    private function is_logging_enabled() {
        // Check constant first
        if (defined('TRANSLIO_DEBUG') && TRANSLIO_DEBUG) {
            return true;
        }

        // Check option
        return (bool) get_option('translio_debug', false);
    }

    /**
     * Log a message
     *
     * @param string $message Log message
     * @param string $level Log level (debug, info, warning, error)
     * @param string $category Log category for filtering
     * @param array $context Additional context data
     */
    public static function log($message, $level = self::LEVEL_INFO, $category = self::CAT_TRANSLATION, $context = array()) {
        $instance = self::instance();

        if (!$instance->enabled) {
            return;
        }

        $entry = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => $context,
            'memory' => memory_get_usage(true),
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : 'CLI',
        );

        $instance->log_buffer[] = $entry;

        // Also log to error_log for immediate visibility during debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Translio][%s][%s] %s %s',
                strtoupper($level),
                $category,
                $message,
                !empty($context) ? json_encode($context) : ''
            ));
        }
    }

    /**
     * Log translation path - tracks the flow of content through translation
     *
     * @param string $object_type Type of object (post, term, option, elementor, cf7)
     * @param int|string $object_id Object identifier
     * @param string $field_name Field being translated
     * @param string $language Target language
     * @param string $source Translation source (cache, tm, api, manual)
     * @param array $extra Additional context
     */
    public static function log_translation_path($object_type, $object_id, $field_name, $language, $source, $extra = array()) {
        $context = array_merge(array(
            'object_type' => $object_type,
            'object_id' => $object_id,
            'field_name' => $field_name,
            'language' => $language,
            'source' => $source,
        ), $extra);

        $message = sprintf(
            'Translation: %s/%s/%s -> %s [%s]',
            $object_type,
            $object_id,
            $field_name,
            $language,
            $source
        );

        self::log($message, self::LEVEL_DEBUG, self::CAT_TRANSLATION, $context);
    }

    /**
     * Log API call
     *
     * @param string $action API action (translate, translate_batch)
     * @param int $text_count Number of texts being translated
     * @param string $language Target language
     * @param float $duration Request duration in seconds
     * @param bool $success Whether the call succeeded
     * @param string $error Error message if failed
     */
    public static function log_api_call($action, $text_count, $language, $duration = 0, $success = true, $error = '') {
        $context = array(
            'action' => $action,
            'text_count' => $text_count,
            'language' => $language,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
        );

        if (!$success && $error) {
            $context['error'] = $error;
        }

        $level = $success ? self::LEVEL_INFO : self::LEVEL_ERROR;
        $message = sprintf(
            'API %s: %d texts -> %s (%dms) %s',
            $action,
            $text_count,
            $language,
            round($duration * 1000),
            $success ? 'OK' : 'FAILED: ' . $error
        );

        self::log($message, $level, self::CAT_API, $context);
    }

    /**
     * Log content extraction
     *
     * @param string $object_type Object type
     * @param int|string $object_id Object ID
     * @param int $field_count Number of fields extracted
     * @param array $field_names Names of extracted fields
     */
    public static function log_extraction($object_type, $object_id, $field_count, $field_names = array()) {
        $context = array(
            'object_type' => $object_type,
            'object_id' => $object_id,
            'field_count' => $field_count,
            'fields' => $field_names,
        );

        $message = sprintf(
            'Extracted %d fields from %s/%s',
            $field_count,
            $object_type,
            $object_id
        );

        self::log($message, self::LEVEL_DEBUG, self::CAT_CONTENT, $context);
    }

    /**
     * Log TM (Translation Memory) hit or miss
     *
     * @param string $text Original text (truncated)
     * @param string $language Target language
     * @param bool $hit Whether TM had a match
     * @param int $similarity Similarity percentage if hit
     */
    public static function log_tm($text, $language, $hit, $similarity = 0) {
        $context = array(
            'text_preview' => mb_substr($text, 0, 50) . (mb_strlen($text) > 50 ? '...' : ''),
            'language' => $language,
            'hit' => $hit,
            'similarity' => $similarity,
        );

        $message = sprintf(
            'TM %s: "%s" -> %s',
            $hit ? 'HIT(' . $similarity . '%)' : 'MISS',
            mb_substr($text, 0, 30),
            $language
        );

        self::log($message, self::LEVEL_DEBUG, self::CAT_TRANSLATION, $context);
    }

    /**
     * Log router decision
     *
     * @param string $url Current URL
     * @param string $language Detected language
     * @param string $action Router action taken
     */
    public static function log_router($url, $language, $action) {
        $context = array(
            'url' => $url,
            'language' => $language,
            'action' => $action,
        );

        $message = sprintf(
            'Router: %s -> lang=%s, action=%s',
            $url,
            $language,
            $action
        );

        self::log($message, self::LEVEL_DEBUG, self::CAT_ROUTER, $context);
    }

    /**
     * Log error
     *
     * @param string $message Error message
     * @param string $category Category
     * @param array $context Additional context
     */
    public static function error($message, $category = self::CAT_TRANSLATION, $context = array()) {
        self::log($message, self::LEVEL_ERROR, $category, $context);
    }

    /**
     * Log warning
     *
     * @param string $message Warning message
     * @param string $category Category
     * @param array $context Additional context
     */
    public static function warning($message, $category = self::CAT_TRANSLATION, $context = array()) {
        self::log($message, self::LEVEL_WARNING, $category, $context);
    }

    /**
     * Log info
     *
     * @param string $message Info message
     * @param string $category Category
     * @param array $context Additional context
     */
    public static function info($message, $category = self::CAT_TRANSLATION, $context = array()) {
        self::log($message, self::LEVEL_INFO, $category, $context);
    }

    /**
     * Log debug
     *
     * @param string $message Debug message
     * @param string $category Category
     * @param array $context Additional context
     */
    public static function debug($message, $category = self::CAT_TRANSLATION, $context = array()) {
        self::log($message, self::LEVEL_DEBUG, $category, $context);
    }

    /**
     * Flush buffer to database on shutdown
     */
    public function flush_buffer() {
        if (empty($this->log_buffer)) {
            return;
        }

        // Store in transient for admin viewing (last 100 entries)
        $existing = get_transient('translio_debug_log');
        if (!is_array($existing)) {
            $existing = array();
        }

        $merged = array_merge($existing, $this->log_buffer);

        // Keep only last 500 entries
        if (count($merged) > 500) {
            $merged = array_slice($merged, -500);
        }

        set_transient('translio_debug_log', $merged, HOUR_IN_SECONDS);

        $this->log_buffer = array();
    }

    /**
     * Get recent logs
     *
     * @param int $limit Number of entries to return
     * @param string $level Filter by level (optional)
     * @param string $category Filter by category (optional)
     * @return array Log entries
     */
    public static function get_logs($limit = 100, $level = null, $category = null) {
        $logs = get_transient('translio_debug_log');

        if (!is_array($logs)) {
            return array();
        }

        // Filter by level
        if ($level) {
            $logs = array_filter($logs, function($entry) use ($level) {
                return $entry['level'] === $level;
            });
        }

        // Filter by category
        if ($category) {
            $logs = array_filter($logs, function($entry) use ($category) {
                return $entry['category'] === $category;
            });
        }

        // Return most recent entries
        return array_slice(array_reverse($logs), 0, $limit);
    }

    /**
     * Clear all logs
     */
    public static function clear_logs() {
        delete_transient('translio_debug_log');
    }

    /**
     * Enable debug logging
     */
    public static function enable() {
        update_option('translio_debug', true);
        self::instance()->enabled = true;
    }

    /**
     * Disable debug logging
     */
    public static function disable() {
        update_option('translio_debug', false);
        self::instance()->enabled = false;
    }

    /**
     * Check if debug is enabled
     */
    public static function is_enabled() {
        return self::instance()->enabled;
    }
}
