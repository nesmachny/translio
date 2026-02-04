<?php
/**
 * Main Translio class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio {

    private static $instance = null;

    private $current_language = null;
    private $settings = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Set instance first to prevent recursion if child classes call translio()
        self::$instance = $this;

        $this->load_dependencies();
        $this->init_hooks();

        // DEBUG: Log total init time
        if (defined('TRANSLIO_START_TIME')) {
            add_action('admin_footer', function() {
                $time = round((microtime(true) - TRANSLIO_START_TIME) * 1000, 2);
                echo "<!-- Translio total load: {$time}ms -->";
            });
        }
    }

    private function load_dependencies() {
        require_once TRANSLIO_PLUGIN_DIR . 'includes/interface-translio-translatable.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-logger.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-utils.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-db.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-admin.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-router.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-api.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-content.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-elementor.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-divi.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-avada.php';
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-switcher.php';
    }

    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('init', array($this, 'detect_language'), 1);
        add_action('init', array($this, 'maybe_flush_rules'), 99);

        if (is_admin()) {
            new Translio_Admin();
        }

        // Frontend: Router + Content + Switcher
        if (!is_admin()) {
            new Translio_Router();
            new Translio_Content();
            translio_switcher();
        }
    }

    /**
     * Check if we need to flush rewrite rules (after settings change)
     */
    public function maybe_flush_rules() {
        if (get_transient('translio_flush_rules')) {
            delete_transient('translio_flush_rules');
            Translio_Router::flush_rules();
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain('translio', false, dirname(TRANSLIO_PLUGIN_BASENAME) . '/languages');
    }

    public function detect_language() {
        $default_lang = get_option('translio_default_language', 'en');
        $this->current_language = $default_lang;

        if (!is_admin()) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
            $path = trim(parse_url($request_uri, PHP_URL_PATH), '/');
            $segments = explode('/', $path);

            if (!empty($segments[0])) {
                $potential_lang = $segments[0];
                $active_languages = $this->get_active_languages();
                $lang_codes = wp_list_pluck($active_languages, 'code');

                if (in_array($potential_lang, $lang_codes) && $potential_lang !== $default_lang) {
                    $this->current_language = $potential_lang;
                }
            }
        }
    }

    public function get_current_language() {
        return $this->current_language;
    }

    public function set_current_language($lang) {
        $this->current_language = $lang;
    }

    public function get_default_language() {
        return $this->get_setting('default_language');
    }

    /**
     * Get first secondary language (backward compatibility)
     *
     * @return string First secondary language code or empty string
     */
    public function get_secondary_language() {
        $languages = $this->get_secondary_languages();
        return !empty($languages) ? $languages[0] : '';
    }

    /**
     * Get all secondary (translation) languages
     *
     * @return array Array of language codes (max 4)
     */
    public function get_secondary_languages() {
        $languages = get_option('translio_secondary_languages', array());

        if (empty($languages) || !is_array($languages)) {
            // Backward compat: check legacy single language setting
            $legacy = get_option('translio_secondary_language', '');
            if (!empty($legacy)) {
                $languages = array($legacy);
            } else {
                $languages = array();
            }
        }

        // Limit to maximum 4 languages
        return array_slice($languages, 0, 4);
    }

    /**
     * Check if a language code is a configured secondary language
     *
     * @param string $code Language code to check
     * @return bool
     */
    public function is_secondary_language($code) {
        return in_array($code, $this->get_secondary_languages(), true);
    }

    /**
     * Get all plugin settings (cached)
     *
     * @return array
     */
    public function get_settings() {
        if (is_null($this->settings)) {
            $this->settings = array(
                'default_language'    => get_option('translio_default_language', 'en'),
                'secondary_language'  => get_option('translio_secondary_language', ''), // Legacy, for backward compat
                'secondary_languages' => $this->get_secondary_languages(), // New: array of languages
                'scan_strings'        => get_option('translio_scan_strings', true),
                'debug'               => get_option('translio_debug', false),
            );
        }
        return $this->settings;
    }

    /**
     * Get a single setting value
     *
     * @param string $key Setting key
     * @return mixed|null
     */
    public function get_setting($key) {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : null;
    }

    /**
     * Clear settings cache (call after settings update)
     */
    public function clear_settings_cache() {
        $this->settings = null;
    }

    /**
     * Get all active languages (default + all secondary languages)
     *
     * @return array Array of language objects from database
     */
    public function get_active_languages() {
        global $wpdb;
        $table = $wpdb->prefix . 'translio_languages';

        $default = $this->get_default_language();
        $secondary_languages = $this->get_secondary_languages();

        if (empty($secondary_languages)) {
            return $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE code = %s", $default)
            );
        }

        // Build array of all language codes (default first, then secondary in order)
        $all_codes = array_merge(array($default), $secondary_languages);
        $all_codes = array_unique($all_codes); // Remove duplicates just in case

        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($all_codes), '%s'));

        // Build FIELD() for ordering
        $field_placeholders = implode(',', array_fill(0, count($all_codes), '%s'));

        // Merge parameters: codes for IN, codes for FIELD ordering
        $params = array_merge($all_codes, $all_codes);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE code IN ({$placeholders}) ORDER BY FIELD(code, {$field_placeholders})",
                $params
            )
        );
    }

    public function is_default_language() {
        return $this->current_language === $this->get_default_language();
    }

    public static function get_translatable_post_types() {
        // Get public post types (returns associative array like ['post' => 'post'])
        $public_types = get_post_types(array('public' => true), 'names');

        // Add block theme types that need translation
        $block_types = array(
            'wp_navigation',
            'wp_template_part',
            'wp_block',
        );

        // Add WooCommerce types that are not public but need translation
        $wc_types = array();
        if (class_exists('WooCommerce')) {
            $wc_types[] = 'shop_coupon';  // Coupons (not public by default)
        }

        // Add Contact Form 7 forms
        $cf7_types = array();
        if (class_exists('WPCF7')) {
            $cf7_types[] = 'wpcf7_contact_form';
        }

        // Convert associative array to values and merge
        $all_types = array_merge(array_values($public_types), $block_types, $wc_types, $cf7_types);

        // Remove attachment - usually not needed for translation
        $all_types = array_diff($all_types, array('attachment'));

        // Return unique values with reset indexes
        return array_values(array_unique($all_types));
    }

    public static function get_available_languages() {
        return array(
            'en' => array('name' => 'English', 'native' => 'English'),
            'es' => array('name' => 'Spanish', 'native' => 'Español'),
            'zh' => array('name' => 'Chinese', 'native' => '中文'),
            'hi' => array('name' => 'Hindi', 'native' => 'हिन्दी'),
            'ar' => array('name' => 'Arabic', 'native' => 'العربية'),
            'pt' => array('name' => 'Portuguese', 'native' => 'Português'),
            'ru' => array('name' => 'Russian', 'native' => 'Русский'),
            'ja' => array('name' => 'Japanese', 'native' => '日本語'),
            'de' => array('name' => 'German', 'native' => 'Deutsch'),
            'fr' => array('name' => 'French', 'native' => 'Français'),
            'ko' => array('name' => 'Korean', 'native' => '한국어'),
            'it' => array('name' => 'Italian', 'native' => 'Italiano'),
            'tr' => array('name' => 'Turkish', 'native' => 'Türkçe'),
            'vi' => array('name' => 'Vietnamese', 'native' => 'Tiếng Việt'),
            'pl' => array('name' => 'Polish', 'native' => 'Polski'),
            'uk' => array('name' => 'Ukrainian', 'native' => 'Українська'),
            'nl' => array('name' => 'Dutch', 'native' => 'Nederlands'),
            'th' => array('name' => 'Thai', 'native' => 'ไทย'),
            'id' => array('name' => 'Indonesian', 'native' => 'Bahasa Indonesia'),
            'he' => array('name' => 'Hebrew', 'native' => 'עברית'),
        );
    }
}
