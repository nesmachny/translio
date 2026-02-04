<?php
/**
 * Translio Admin Class (Refactored)
 *
 * Main admin class that loads modules and registers menus/scripts.
 * All page rendering and AJAX handling is delegated to modules.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Admin {

    // Module instances
    private $ajax;
    private $dashboard;
    private $settings;
    private $content;
    private $strings;
    private $taxonomies;
    private $media;
    private $options;
    private $wc;
    private $elementor;
    private $divi;
    private $avada;
    private $cf7;

    public function __construct() {
        $this->load_modules();

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_footer', array($this, 'render_feedback_modal'));

        // AJAX handler for feedback
        add_action('wp_ajax_translio_send_feedback', array($this, 'ajax_send_feedback'));
    }

    /**
     * Load all admin module classes
     */
    private function load_modules() {
        $dir = TRANSLIO_PLUGIN_DIR . 'includes/admin/';

        require_once $dir . 'class-translio-admin-ajax.php';
        require_once $dir . 'class-translio-admin-dashboard.php';
        require_once $dir . 'class-translio-admin-settings.php';
        require_once $dir . 'class-translio-admin-content.php';
        require_once $dir . 'class-translio-admin-strings.php';
        require_once $dir . 'class-translio-admin-taxonomies.php';
        require_once $dir . 'class-translio-admin-media.php';
        require_once $dir . 'class-translio-admin-options.php';
        require_once $dir . 'class-translio-admin-wc.php';
        require_once $dir . 'class-translio-admin-elementor.php';
        require_once $dir . 'class-translio-admin-divi.php';
        require_once $dir . 'class-translio-admin-avada.php';
        require_once $dir . 'class-translio-admin-cf7.php';

        // Initialize modules
        $this->ajax = new Translio_Admin_Ajax();       // AJAX handlers register themselves
        $this->dashboard = new Translio_Admin_Dashboard();
        $this->settings = new Translio_Admin_Settings();
        $this->content = new Translio_Admin_Content();
        $this->strings = new Translio_Admin_Strings();
        $this->taxonomies = new Translio_Admin_Taxonomies();
        $this->media = new Translio_Admin_Media();
        $this->options = new Translio_Admin_Options();
        $this->wc = new Translio_Admin_WC();
        $this->elementor = new Translio_Admin_Elementor();
        $this->divi = new Translio_Admin_Divi();
        $this->avada = new Translio_Admin_Avada();
        $this->cf7 = new Translio_Admin_CF7();
    }

    /**
     * Register admin menu pages
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Translio', 'translio'),
            __('Translio', 'translio'),
            'edit_posts',
            'translio',
            array($this->dashboard, 'render_page'),
            'dashicons-translation',
            30
        );

        // Dashboard (same as main)
        add_submenu_page(
            'translio',
            __('Dashboard', 'translio'),
            __('Dashboard', 'translio'),
            'edit_posts',
            'translio',
            array($this->dashboard, 'render_page')
        );

        // Hidden: Import test pages (for development)
        add_submenu_page(
            null,
            'Import Test Pages',
            'Import Test Pages',
            'edit_posts',
            'translio-import-test',
            array($this, 'render_import_test_page')
        );

        // All Content
        add_submenu_page(
            'translio',
            __('All Content', 'translio'),
            __('All Content', 'translio'),
            'edit_posts',
            'translio-content',
            array($this->content, 'render_content_page')
        );

        // Translate Post (hidden)
        add_submenu_page(
            null,
            __('Translate', 'translio'),
            __('Translate', 'translio'),
            'edit_posts',
            'translio-translate',
            array($this->content, 'render_translate_page')
        );

        // Theme Strings
        add_submenu_page(
            'translio',
            __('Theme Strings', 'translio'),
            __('Theme Strings', 'translio'),
            'manage_options',
            'translio-strings',
            array($this->strings, 'render_page')
        );

        // Taxonomies
        add_submenu_page(
            'translio',
            __('Taxonomies', 'translio'),
            __('Taxonomies', 'translio'),
            'edit_posts',
            'translio-taxonomies',
            array($this->taxonomies, 'render_page')
        );

        // Media
        add_submenu_page(
            'translio',
            __('Media', 'translio'),
            __('Media', 'translio'),
            'edit_posts',
            'translio-media',
            array($this->media, 'render_page')
        );

        // Translate Term (hidden)
        add_submenu_page(
            null,
            __('Translate Term', 'translio'),
            __('Translate Term', 'translio'),
            'edit_posts',
            'translio-translate-term',
            array($this->taxonomies, 'render_translate_term_page')
        );

        // Translate Media (hidden)
        add_submenu_page(
            null,
            __('Translate Media', 'translio'),
            __('Translate Media', 'translio'),
            'edit_posts',
            'translio-translate-media',
            array($this->media, 'render_translate_media_page')
        );

        // WooCommerce Attributes (only if WooCommerce is active)
        if (function_exists('wc_get_attribute_taxonomies')) {
            add_submenu_page(
                'translio',
                __('WC Attributes', 'translio'),
                __('WC Attributes', 'translio'),
                'edit_posts',
                'translio-wc-attributes',
                array($this->wc, 'render_page')
            );
        }

        // Site Options
        add_submenu_page(
            'translio',
            __('Site Options', 'translio'),
            __('Site Options', 'translio'),
            'manage_options',
            'translio-options',
            array($this->options, 'render_page')
        );

        // Elementor (only if Elementor is active)
        if (class_exists('Translio_Elementor') && Translio_Elementor::is_active()) {
            add_submenu_page(
                'translio',
                __('Elementor', 'translio'),
                __('Elementor', 'translio'),
                'edit_posts',
                'translio-elementor',
                array($this->elementor, 'render_page')
            );

            add_submenu_page(
                null,
                __('Translate Elementor', 'translio'),
                __('Translate Elementor', 'translio'),
                'edit_posts',
                'translio-translate-elementor',
                array($this->elementor, 'render_translate_page')
            );
        }

        // Divi (only if Divi is active)
        if (class_exists('Translio_Divi') && Translio_Divi::is_divi_active()) {
            add_submenu_page(
                'translio',
                __('Divi', 'translio'),
                __('Divi', 'translio'),
                'edit_posts',
                'translio-divi',
                array($this->divi, 'render_page')
            );

            add_submenu_page(
                null,
                __('Translate Divi', 'translio'),
                __('Translate Divi', 'translio'),
                'edit_posts',
                'translio-translate-divi',
                array($this->divi, 'render_translate_page')
            );
        }

        // Avada (only if Avada is active)
        if (class_exists('Translio_Avada') && Translio_Avada::is_avada_active()) {
            add_submenu_page(
                'translio',
                __('Avada', 'translio'),
                __('Avada', 'translio'),
                'edit_posts',
                'translio-avada',
                array($this->avada, 'render_page')
            );

            add_submenu_page(
                null,
                __('Translate Avada', 'translio'),
                __('Translate Avada', 'translio'),
                'edit_posts',
                'translio-translate-avada',
                array($this->avada, 'render_translate_page')
            );
        }

        // Contact Form 7 (only if CF7 is active)
        if (class_exists('WPCF7')) {
            add_submenu_page(
                'translio',
                __('Contact Forms', 'translio'),
                __('Contact Forms', 'translio'),
                'edit_posts',
                'translio-cf7',
                array($this->cf7, 'render_page')
            );

            add_submenu_page(
                null,
                __('Translate Contact Form', 'translio'),
                __('Translate Contact Form', 'translio'),
                'edit_posts',
                'translio-translate-cf7',
                array($this->cf7, 'render_translate_page')
            );
        }

        // Settings (last item in menu)
        add_submenu_page(
            'translio',
            __('Settings', 'translio'),
            __('Settings', 'translio'),
            'manage_options',
            'translio-settings',
            array($this->settings, 'render_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (empty($hook) || strpos($hook, 'translio') === false) {
            return;
        }

        wp_enqueue_style(
            'translio-admin',
            TRANSLIO_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            TRANSLIO_VERSION
        );

        wp_enqueue_script(
            'translio-admin',
            TRANSLIO_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery'),
            TRANSLIO_VERSION,
            true
        );

        wp_localize_script('translio-admin', 'translioAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'adminUrl' => admin_url(),
            'nonce' => wp_create_nonce('translio_nonce'),
            'adminEmail' => get_option('admin_email'),
            'siteUrl' => home_url(),
            'strings' => array(
                'saving' => __('Saving...', 'translio'),
                'saved' => __('Saved', 'translio'),
                'error' => __('Error saving', 'translio'),
                'translating' => __('Translating...', 'translio'),
                'translated' => __('Translated', 'translio'),
                'translate' => __('Translate', 'translio'),
                'done' => __('Done!', 'translio'),
                'complete' => __('Complete!', 'translio'),
                'errors' => __('errors:', 'translio'),
                'selectItem' => __('Please select at least one item to translate.', 'translio'),
                'confirmTranslate' => __('Translate selected items?', 'translio'),
                'confirmTranslateAll' => __('This will translate all untranslated content. Continue?', 'translio'),
                'confirmTranslateChanges' => __('This will translate content that has been modified. Continue?', 'translio'),
                'feedbackSending' => __('Sending...', 'translio'),
                'feedbackSent' => __('Thank you for your feedback!', 'translio'),
                'feedbackError' => __('Failed to send feedback. Please try again.', 'translio'),
            ),
        ));
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('translio_settings', 'translio_license_domain', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_license_domain'),
            'default' => '',
        ));

        register_setting('translio_settings', 'translio_default_language', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'en',
        ));

        register_setting('translio_settings', 'translio_secondary_language', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_secondary_language'),
            'default' => '',
        ));

        // New: Multiple secondary languages (array)
        register_setting('translio_settings', 'translio_secondary_languages', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_secondary_languages'),
            'default' => array(),
        ));

        register_setting('translio_settings', 'translio_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'encrypt_api_key'),
            'default' => '',
        ));

        register_setting('translio_settings', 'translio_switcher_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_switcher_settings'),
            'default' => array(),
        ));
    }

    /**
     * Sanitize switcher settings
     */
    public function sanitize_switcher_settings($value) {
        if (!is_array($value)) {
            return array();
        }

        $sanitized = array();

        $sanitized['enable_menu'] = !empty($value['enable_menu']);
        $sanitized['menu_location'] = isset($value['menu_location']) ? sanitize_text_field($value['menu_location']) : 'primary';
        $sanitized['menu_style'] = isset($value['menu_style']) && in_array($value['menu_style'], array('dropdown', 'inline')) ? $value['menu_style'] : 'dropdown';
        $sanitized['show_flags'] = !empty($value['show_flags']);
        $sanitized['show_names'] = !empty($value['show_names']);

        return $sanitized;
    }

    /**
     * Sanitize license domain
     */
    public function sanitize_license_domain($value) {
        $value = sanitize_text_field($value);

        // Remove protocol if present
        $value = preg_replace('#^https?://#', '', $value);

        // Remove trailing slash
        $value = rtrim($value, '/');

        // Remove path if present (keep only domain)
        $value = preg_replace('#/.*$#', '', $value);

        return strtolower($value);
    }

    /**
     * Sanitize secondary language and flush rewrite rules if changed
     * (Legacy - kept for backward compatibility)
     */
    public function sanitize_secondary_language($value) {
        $value = sanitize_text_field($value);
        $old_value = translio()->get_setting('secondary_language');

        if ($value !== $old_value) {
            set_transient('translio_flush_rules', true, 60);
        }

        return $value;
    }

    /**
     * Sanitize secondary languages array
     *
     * @param mixed $languages Input value (should be array)
     * @return array Sanitized array of language codes (max 4)
     */
    public function sanitize_secondary_languages($languages) {
        if (!is_array($languages)) {
            return array();
        }

        $available = array_keys(Translio::get_available_languages());
        $default = get_option('translio_default_language', 'en');

        // Filter valid languages, exclude default, limit to 4
        $valid = array();
        foreach ($languages as $lang) {
            $lang = sanitize_text_field($lang);
            if (in_array($lang, $available, true) && $lang !== $default && !in_array($lang, $valid, true)) {
                $valid[] = $lang;
            }
            if (count($valid) >= 4) {
                break;
            }
        }

        // Get old value to check if changed
        $old_value = get_option('translio_secondary_languages', array());
        if ($valid !== $old_value) {
            // Trigger rewrite rules flush
            set_transient('translio_flush_rules', true, 60);

            // Clear main class settings cache
            translio()->clear_settings_cache();
        }

        return $valid;
    }

    /**
     * Encrypt API key for storage
     */
    public function encrypt_api_key($value) {
        $value = (string) $value;
        if (empty($value) || trim($value) === '') {
            return get_option('translio_api_key', '');
        }

        if (strpos($value, 'sk-') === 0) {
            $key = wp_salt('auth');
            $iv = substr(md5(wp_salt('secure_auth')), 0, 16);

            $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);

            if ($encrypted === false) {
                return get_option('translio_api_key', '');
            }

            return base64_encode($encrypted);
        }

        if (preg_match('/^[A-Za-z0-9+\/=]+$/', $value) && strlen($value) > 50) {
            return $value;
        }

        return get_option('translio_api_key', '');
    }

    /**
     * Decrypt API key for use
     */
    public static function decrypt_api_key() {
        $encrypted = (string) get_option('translio_api_key', '');

        if (empty($encrypted)) {
            return '';
        }

        if (strpos($encrypted, 'sk-') === 0) {
            return $encrypted;
        }

        $key = wp_salt('auth');
        $iv = substr(md5(wp_salt('secure_auth')), 0, 16);

        $decoded = base64_decode($encrypted, true);

        if ($decoded === false) {
            return $encrypted;
        }

        $decrypted = openssl_decrypt($decoded, 'AES-256-CBC', $key, 0, $iv);

        if ($decrypted === false) {
            return '';
        }

        return $decrypted;
    }

    /**
     * Get current admin language (from URL param or user preference)
     *
     * @return string Language code or empty if no secondary languages
     */
    public static function get_admin_language() {
        $secondary_languages = translio()->get_secondary_languages();

        if (empty($secondary_languages)) {
            return '';
        }

        // Check URL parameter first
        if (isset($_GET['translio_lang'])) {
            $lang = sanitize_text_field($_GET['translio_lang']);
            if (in_array($lang, $secondary_languages, true)) {
                // Save user preference
                update_user_meta(get_current_user_id(), 'translio_admin_language', $lang);
                return $lang;
            }
        }

        // Check user preference
        $user_pref = get_user_meta(get_current_user_id(), 'translio_admin_language', true);
        if (!empty($user_pref) && in_array($user_pref, $secondary_languages, true)) {
            return $user_pref;
        }

        // Default to first secondary language
        return $secondary_languages[0];
    }

    /**
     * Render language selector tabs for admin pages
     *
     * @param string $current_page Page slug for building URLs
     */
    public static function render_language_selector($current_page = '') {
        $secondary_languages = translio()->get_secondary_languages();

        // No selector needed for single or no languages
        if (count($secondary_languages) <= 1) {
            return;
        }

        $current_lang = self::get_admin_language();
        $languages = Translio::get_available_languages();
        $default_lang = translio()->get_default_language();

        ?>
        <div class="translio-admin-language-selector">
            <ul class="translio-language-tabs">
                <?php foreach ($secondary_languages as $lang_code):
                    $lang_data = isset($languages[$lang_code]) ? $languages[$lang_code] : array('name' => $lang_code, 'native' => $lang_code);
                    $url = add_query_arg('translio_lang', $lang_code);
                    $is_active = ($lang_code === $current_lang);
                ?>
                <li class="translio-language-tab <?php echo $is_active ? 'active' : ''; ?>">
                    <a href="<?php echo esc_url($url); ?>">
                        <span class="translio-lang-indicator">
                            <?php echo esc_html(strtoupper($default_lang)); ?> → <?php echo esc_html(strtoupper($lang_code)); ?>
                        </span>
                        <?php echo esc_html($lang_data['name']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <style>
        .translio-admin-language-selector {
            margin: 15px 0 20px;
        }
        .translio-language-tabs {
            display: flex;
            gap: 0;
            list-style: none;
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #c3c4c7;
        }
        .translio-language-tab a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            text-decoration: none;
            color: #50575e;
            border: 1px solid transparent;
            border-bottom: none;
            margin-bottom: -1px;
            background: #f0f0f1;
            border-radius: 4px 4px 0 0;
            font-weight: 500;
        }
        .translio-language-tab.active a {
            background: #fff;
            border-color: #c3c4c7;
            color: #1d2327;
        }
        .translio-language-tab a:hover {
            color: #135e96;
            background: #f6f7f7;
        }
        .translio-language-tab.active a:hover {
            background: #fff;
            color: #1d2327;
        }
        .translio-language-tab .translio-lang-indicator {
            background: #dcdcde;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .translio-language-tab.active .translio-lang-indicator {
            background: #2271b1;
            color: #fff;
        }
        </style>
        <?php
    }

    /**
     * Render import test pages (development helper)
     */
    public function render_import_test_page() {
        include TRANSLIO_PLUGIN_DIR . 'import-test-pages.php';
    }

    /**
     * Render feedback modal HTML
     */
    public function render_feedback_modal() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'translio') === false) {
            return;
        }
        ?>
        <div id="translio-feedback-modal" class="translio-modal" style="display: none;">
            <div class="translio-modal-overlay"></div>
            <div class="translio-modal-content translio-feedback-modal-content">
                <button type="button" class="translio-modal-close">&times;</button>
                <h2><?php esc_html_e('Feedback', 'translio'); ?></h2>
                <p class="translio-feedback-description">
                    <?php esc_html_e('Translio is in active development and there may be bugs or inaccuracies. If you have found incorrect behavior, incompatibility with other plugins or themes, or perhaps you have ideas for improving functionality, please leave your feedback. This will help our team make the service even better.', 'translio'); ?>
                </p>
                <form id="translio-feedback-form">
                    <div class="translio-form-row">
                        <label for="translio-feedback-email"><?php esc_html_e('Email', 'translio'); ?></label>
                        <input type="email" id="translio-feedback-email" name="email" required>
                    </div>
                    <div class="translio-form-row">
                        <label for="translio-feedback-message"><?php esc_html_e('Message', 'translio'); ?></label>
                        <textarea id="translio-feedback-message" name="message" rows="6" maxlength="2000" required placeholder="<?php esc_attr_e('Describe the issue or your suggestion...', 'translio'); ?>"></textarea>
                        <span class="translio-char-count"><span id="translio-char-current">0</span>/2000</span>
                    </div>
                    <div class="translio-form-actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Send Feedback', 'translio'); ?></button>
                    </div>
                </form>
                <div id="translio-feedback-success" style="display: none;">
                    <div class="translio-success-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <h3><?php esc_html_e('Thank you!', 'translio'); ?></h3>
                    <p><?php esc_html_e('Your feedback has been sent successfully. We appreciate your help in improving Translio!', 'translio'); ?></p>
                    <button type="button" class="button translio-modal-close-btn"><?php esc_html_e('Close', 'translio'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for sending feedback
     */
    public function ajax_send_feedback() {
        check_ajax_referer('translio_nonce', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        if (empty($email) || empty($message)) {
            wp_send_json_error(array('message' => __('Email and message are required.', 'translio')));
        }

        // Limit message length to prevent abuse
        if (strlen($message) > 2000) {
            $message = substr($message, 0, 2000);
        }

        // Gather site info
        $site_url = home_url();
        $site_name = get_bloginfo('name');
        $wp_version = get_bloginfo('version');
        $php_version = phpversion();
        $plugin_version = TRANSLIO_VERSION;

        // Build email
        $to = 'go@translio.to';
        $subject = sprintf('[Translio Feedback] from %s', $site_url);

        $body = "New feedback from Translio plugin:\n\n";
        $body .= "From: {$email}\n";
        $body .= "Site: {$site_url}\n";
        $body .= "Site Name: {$site_name}\n";
        $body .= "Plugin Version: {$plugin_version}\n";
        $body .= "WordPress: {$wp_version}\n";
        $body .= "PHP: {$php_version}\n";
        $body .= "Time: " . current_time('mysql') . " UTC\n\n";
        $body .= "Message:\n";
        $body .= "----------------------------------------\n";
        $body .= $message . "\n";
        $body .= "----------------------------------------\n";

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $email,
        );

        $sent = wp_mail($to, $subject, $body, $headers);

        if ($sent) {
            wp_send_json_success(array('message' => __('Feedback sent successfully.', 'translio')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send feedback.', 'translio')));
        }
    }
}
