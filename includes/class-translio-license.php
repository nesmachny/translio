<?php
/**
 * Translio License Manager
 *
 * Handles license validation and credit management via api.translio.to
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_License {

    private static $instance = null;

    const API_URL = 'https://api.translio.to/wp-json/translio/v1';
    const CACHE_EXPIRATION = HOUR_IN_SECONDS;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hook for AJAX license actions
        add_action('wp_ajax_translio_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_translio_deactivate_license', array($this, 'ajax_deactivate_license'));
        add_action('wp_ajax_translio_refresh_license', array($this, 'ajax_refresh_license'));
        add_action('wp_ajax_translio_register_free', array($this, 'ajax_register_free'));
        add_action('wp_ajax_translio_resend_verification', array($this, 'ajax_resend_verification'));
    }

    /**
     * Get current license key
     *
     * @return string|false License key or false if not set
     */
    public function get_license_key() {
        return get_option('translio_license_key', false);
    }

    /**
     * Get current domain (normalized)
     *
     * @return string Domain without www prefix
     */
    public function get_current_domain() {
        $url = get_site_url();
        $domain = wp_parse_url($url, PHP_URL_HOST);
        $domain = preg_replace('/^www\./', '', $domain);
        return $domain;
    }

    /**
     * Get cached license data
     *
     * @param bool $force Force refresh from API
     * @return object|null License data or null
     */
    public function get_license_data($force = false) {
        if (!$force) {
            $cached = get_transient('translio_license_cache');
            if ($cached !== false) {
                return $cached;
            }
        }

        $license_key = $this->get_license_key();
        if (!$license_key) {
            return null;
        }

        $data = $this->validate();
        if ($data && isset($data->valid) && $data->valid) {
            set_transient('translio_license_cache', $data, self::CACHE_EXPIRATION);
            update_option('translio_license_data', $data);
        }

        return $data;
    }

    /**
     * Validate license with API server
     *
     * @return object|WP_Error License data or error
     */
    public function validate() {
        $license_key = $this->get_license_key();
        if (!$license_key) {
            return new WP_Error('no_license', __('No license key configured', 'translio'));
        }

        $domain = $this->get_current_domain();

        $response = wp_remote_post(self::API_URL . '/validate', array(
            'body' => wp_json_encode(array(
                'license_key' => $license_key,
                'domain' => $domain,
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));

        if ($code === 401) {
            delete_transient('translio_license_cache');
            return new WP_Error('invalid_license', __('Invalid license key', 'translio'));
        }

        if ($code === 403) {
            $bound_domain = isset($body->data->bound_domain) ? $body->data->bound_domain : '';
            return new WP_Error('domain_mismatch', sprintf(
                __('License is activated on a different domain: %s', 'translio'),
                $bound_domain
            ));
        }

        if ($code !== 200) {
            return new WP_Error('api_error', __('License validation failed', 'translio'));
        }

        return $body;
    }

    /**
     * Activate license on current domain
     *
     * @param string $license_key License key to activate
     * @return object|WP_Error Activation result or error
     */
    public function activate($license_key) {
        $domain = $this->get_current_domain();

        $response = wp_remote_post(self::API_URL . '/activate', array(
            'body' => wp_json_encode(array(
                'license_key' => $license_key,
                'domain' => $domain,
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));

        if ($code === 401) {
            return new WP_Error('invalid_license', __('Invalid license key', 'translio'));
        }

        if ($code === 403) {
            $bound_domain = isset($body->data->bound_domain) ? $body->data->bound_domain : '';
            return new WP_Error('domain_mismatch', sprintf(
                __('License is already activated on: %s. Deactivate it first.', 'translio'),
                $bound_domain
            ));
        }

        if ($code !== 200 || !isset($body->success) || !$body->success) {
            $message = isset($body->message) ? $body->message : __('Activation failed', 'translio');
            return new WP_Error('activation_failed', $message);
        }

        // Save license key and data
        update_option('translio_license_key', $license_key);
        update_option('translio_license_data', $body);
        delete_transient('translio_license_cache');

        return $body;
    }

    /**
     * Deactivate license from current domain
     *
     * @return object|WP_Error Deactivation result or error
     */
    public function deactivate() {
        $license_key = $this->get_license_key();
        if (!$license_key) {
            return new WP_Error('no_license', __('No license key to deactivate', 'translio'));
        }

        $domain = $this->get_current_domain();

        $response = wp_remote_post(self::API_URL . '/deactivate', array(
            'body' => wp_json_encode(array(
                'license_key' => $license_key,
                'domain' => $domain,
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 15,
        ));

        // Clear local data regardless of API response
        delete_option('translio_license_key');
        delete_option('translio_license_data');
        delete_transient('translio_license_cache');
        delete_transient('translio_credits_cache');

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        return $body;
    }

    /**
     * Get credit balance from API
     *
     * @param bool $force Force refresh
     * @return object|WP_Error Credits data or error
     */
    public function get_credits($force = false) {
        if (!$force) {
            $cached = get_transient('translio_credits_cache');
            if ($cached !== false) {
                return $cached;
            }
        }

        $license_key = $this->get_license_key();
        if (!$license_key) {
            return new WP_Error('no_license', __('No license key configured', 'translio'));
        }

        $response = wp_remote_get(self::API_URL . '/credits', array(
            'headers' => array(
                'X-License-Key' => $license_key,
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));

        if ($code !== 200) {
            return new WP_Error('api_error', __('Failed to get credits', 'translio'));
        }

        // Cache for 5 minutes
        set_transient('translio_credits_cache', $body, 5 * MINUTE_IN_SECONDS);

        return $body;
    }

    /**
     * Get available credit packages for purchase
     *
     * @param bool $force Force refresh
     * @return array|WP_Error Packages data or error
     */
    public function get_packages($force = false) {
        if (!$force) {
            $cached = get_transient('translio_packages_cache');
            if ($cached !== false) {
                return $cached;
            }
        }

        $response = wp_remote_get(self::API_URL . '/packages', array(
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['packages'])) {
            return new WP_Error('api_error', __('Failed to get packages', 'translio'));
        }

        // Cache for 1 hour
        set_transient('translio_packages_cache', $body, HOUR_IN_SECONDS);

        return $body;
    }

    /**
     * Check if license is valid
     *
     * @return bool True if license is valid
     */
    public function is_valid() {
        $data = $this->get_license_data();
        return $data && isset($data->valid) && $data->valid;
    }

    /**
     * Check if user is on a paying plan (Pro BYOAI)
     *
     * @return bool True if on paid plan
     */
    public function is_paying() {
        $data = $this->get_license_data();
        if (!$data || !isset($data->plan)) {
            return false;
        }
        return in_array($data->plan, array('pro-byoai-monthly', 'pro-byoai-yearly'), true);
    }

    /**
     * Check if user is on free plan
     *
     * @return bool True if on free plan
     */
    public function is_free_plan() {
        return !$this->is_paying();
    }

    /**
     * Get current plan name
     *
     * @return string Plan name
     */
    public function get_plan_name() {
        $data = $this->get_license_data();
        return $data && isset($data->plan) ? $data->plan : 'free';
    }

    /**
     * Get plan title for display
     *
     * @return string Localized plan title
     */
    public function get_plan_title() {
        $plan = $this->get_plan_name();
        $titles = array(
            'free' => __('Free', 'translio'),
            'pro-byoai-monthly' => __('Pro BYOAI (Monthly)', 'translio'),
            'pro-byoai-yearly' => __('Pro BYOAI (Yearly)', 'translio'),
        );
        return isset($titles[$plan]) ? $titles[$plan] : ucfirst(str_replace('-', ' ', $plan));
    }

    /**
     * Get credit balance
     *
     * @return int Credit balance
     */
    public function get_credit_balance() {
        $credits = $this->get_credits();
        if (is_wp_error($credits)) {
            return 0;
        }
        return isset($credits->balance) ? (int) $credits->balance : 0;
    }

    /**
     * Get formatted credit balance
     *
     * @return string Formatted balance (e.g., "50K")
     */
    public function get_formatted_balance() {
        $credits = $this->get_credits();
        if (is_wp_error($credits)) {
            return '0';
        }
        return isset($credits->formatted) ? $credits->formatted : self::format_tokens($this->get_credit_balance());
    }

    /**
     * Check if user can translate (has credits or is BYOAI)
     *
     * @param int $estimated_chars Estimated characters to translate
     * @return bool True if can translate
     */
    public function can_translate($estimated_chars = 0) {
        // BYOAI users have unlimited translations
        if ($this->is_paying()) {
            return true;
        }

        // Credit users need balance
        return $this->get_credit_balance() >= $estimated_chars;
    }

    /**
     * Check if user should use BYOAI mode (own API key)
     *
     * @return bool True if BYOAI mode
     */
    public function is_byoai_mode() {
        return $this->is_paying();
    }

    /**
     * Get license info array for display
     *
     * @return array License information
     */
    public function get_license_info() {
        $data = $this->get_license_data();
        $credits = $this->get_credits();
        $balance = is_wp_error($credits) ? 0 : (isset($credits->balance) ? $credits->balance : 0);
        $formatted = is_wp_error($credits) ? '0' : (isset($credits->formatted) ? $credits->formatted : '0');

        // Subscription info for Pro BYOAI users
        $subscription = $data && isset($data->subscription) ? $data->subscription : null;
        $subscription_started = '';
        $subscription_expires = '';
        $subscription_active = false;
        $days_remaining = 0;

        if ($subscription) {
            $subscription_started = isset($subscription->started_at) ? $subscription->started_at : '';
            $subscription_expires = isset($subscription->expires_at) ? $subscription->expires_at : '';
            $subscription_active = isset($subscription->is_active) ? $subscription->is_active : false;
            $days_remaining = isset($subscription->days_left) ? (int) $subscription->days_left : 0;
        }

        // Credit packages breakdown
        $credit_packages = array();
        if ($data && isset($data->credit_packages) && is_array($data->credit_packages)) {
            $credit_packages = $data->credit_packages;
        }

        return array(
            'license_key' => $this->get_license_key(),
            'valid' => $data && isset($data->valid) && $data->valid,
            'email' => $data && isset($data->email) ? $data->email : '',
            'email_verified' => $data && isset($data->email_verified) ? $data->email_verified : false,
            'plan' => $this->get_plan_name(),
            'plan_title' => $this->get_plan_title(),
            'is_paying' => $this->is_paying(),
            'is_byoai' => $this->is_byoai_mode(),
            'balance' => $balance,
            'formatted_balance' => $formatted,
            'domain' => $this->get_current_domain(),
            'bound_domain' => $data && isset($data->domain) ? $data->domain : '',
            'features' => $data && isset($data->features) ? $data->features : array(),
            // Subscription info
            'subscription_started' => $subscription_started,
            'subscription_expires' => $subscription_expires,
            'subscription_active' => $subscription_active,
            'days_remaining' => $days_remaining,
            // Credit packages
            'credit_packages' => $credit_packages,
        );
    }

    /**
     * Format number for display
     *
     * @param int $number Number to format
     * @return string Formatted number (e.g., "50K", "1.5M")
     */
    public static function format_tokens($number) {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        }
        if ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        return number_format($number);
    }

    /**
     * AJAX: Activate license
     */
    public function ajax_activate_license() {
        check_ajax_referer('translio_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';

        if (empty($license_key)) {
            wp_send_json_error(array('message' => __('Please enter a license key', 'translio')));
        }

        // Validate format
        if (!preg_match('/^trl_[a-f0-9]{32}$/', $license_key)) {
            wp_send_json_error(array('message' => __('Invalid license key format. Expected: trl_xxxxxxxx...', 'translio')));
        }

        $result = $this->activate($license_key);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('License activated successfully!', 'translio'),
            'license_info' => $this->get_license_info(),
        ));
    }

    /**
     * AJAX: Deactivate license
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('translio_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $result = $this->deactivate();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('License deactivated successfully', 'translio'),
        ));
    }

    /**
     * AJAX: Refresh license data
     */
    public function ajax_refresh_license() {
        check_ajax_referer('translio_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        // Clear cache and re-fetch
        delete_transient('translio_license_cache');
        delete_transient('translio_credits_cache');

        $data = $this->get_license_data(true);

        if (is_wp_error($data)) {
            wp_send_json_error(array('message' => $data->get_error_message()));
        }

        wp_send_json_success(array(
            'license_info' => $this->get_license_info(),
        ));
    }

    /**
     * AJAX: Register free license
     */
    public function ajax_register_free() {
        check_ajax_referer('translio_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address', 'translio')));
        }

        $domain = $this->get_current_domain();
        $site_name = get_bloginfo('name');

        // Call API to register
        $response = wp_remote_post(self::API_URL . '/register-free', array(
            'body' => wp_json_encode(array(
                'email' => $email,
                'domain' => $domain,
                'site_name' => $site_name,
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('Connection error. Please try again.', 'translio')));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));

        // Handle error responses
        if ($code !== 200) {
            $message = isset($body->message) ? $body->message : __('Registration failed. Please try again.', 'translio');
            wp_send_json_error(array('message' => $message));
        }

        if (!isset($body->success) || !$body->success || !isset($body->license_key)) {
            $message = isset($body->message) ? $body->message : __('Registration failed. Please try again.', 'translio');
            wp_send_json_error(array('message' => $message));
        }

        // Auto-activate the received license key
        $license_key = $body->license_key;
        $result = $this->activate($license_key);

        if (is_wp_error($result)) {
            // Still return success with license key for manual activation
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Your license key: %s (auto-activation failed, please activate manually)', 'translio'),
                    $license_key
                ),
                'license_key' => $license_key,
                'balance' => isset($body->balance) ? $body->balance : 20000,
            ));
        }

        $message = isset($body->message) ? $body->message : __('Welcome to Translio! Your free license has been activated.', 'translio');

        wp_send_json_success(array(
            'message' => $message,
            'license_key' => $license_key,
            'balance' => isset($body->balance) ? $body->balance : 20000,
            'is_new' => isset($body->is_new) ? $body->is_new : true,
            'email_verified' => isset($body->email_verified) ? $body->email_verified : false,
            'verification_sent' => isset($body->verification_sent) ? $body->verification_sent : false,
        ));
    }

    /**
     * AJAX: Resend verification email
     */
    public function ajax_resend_verification() {
        check_ajax_referer('translio_license_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'translio')));
        }

        $license_key = $this->get_license_key();

        if (empty($license_key)) {
            wp_send_json_error(array('message' => __('No license key found', 'translio')));
        }

        // Call API to resend verification
        $response = wp_remote_post(self::API_URL . '/resend-verification', array(
            'body' => wp_json_encode(array(
                'license_key' => $license_key,
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('Connection error. Please try again.', 'translio')));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));

        if ($code !== 200) {
            $message = isset($body->message) ? $body->message : __('Failed to send verification email.', 'translio');
            wp_send_json_error(array('message' => $message));
        }

        wp_send_json_success(array(
            'message' => isset($body->message) ? $body->message : __('Verification email sent!', 'translio'),
        ));
    }

    // =========================================================================
    // Backwards compatibility methods (for existing code that uses old API)
    // =========================================================================

    /**
     * @deprecated Use get_credit_balance() instead
     */
    public function get_monthly_quota() {
        return $this->get_credit_balance();
    }

    /**
     * @deprecated Use get_credit_balance() instead
     */
    public function get_tokens_remaining() {
        return $this->get_credit_balance();
    }

    /**
     * @deprecated No longer tracks locally - credits managed by API server
     */
    public function get_tokens_used() {
        return 0;
    }

    /**
     * @deprecated No longer tracks locally - credits managed by API server
     */
    public function add_tokens_used($tokens) {
        // No-op: credits are deducted by API server
        return 0;
    }

    /**
     * @deprecated Use get_formatted_balance() instead
     */
    public function get_usage_percentage() {
        return 0;
    }
}

/**
 * Get license manager instance
 */
function translio_license() {
    return Translio_License::instance();
}
