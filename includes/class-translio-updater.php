<?php
/**
 * Translio Plugin Updater
 *
 * Handles automatic updates from api.translio.to update server.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Translio_Updater {

    private static $instance = null;

    /**
     * Update server URL
     */
    const UPDATE_URL = 'https://api.translio.to/wp-json/translio/v1';

    /**
     * Plugin slug
     */
    const SLUG = 'translio';

    /**
     * Cache key for update data
     */
    const CACHE_KEY = 'translio_update_data';

    /**
     * Cache expiration in seconds (12 hours)
     */
    const CACHE_EXPIRATION = 43200;

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
        // Check for updates
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));

        // Plugin info popup
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);

        // After update cleanup
        add_action('upgrader_process_complete', array($this, 'after_update'), 10, 2);
    }

    /**
     * Check for plugin updates
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->get_remote_version();

        if ($remote && version_compare(TRANSLIO_VERSION, $remote->version, '<')) {
            $response = new stdClass();
            $response->slug = self::SLUG;
            $response->plugin = TRANSLIO_PLUGIN_BASENAME;
            $response->new_version = $remote->version;
            $response->tested = $remote->tested;
            $response->package = $remote->download_url;
            $response->url = 'https://translio.to';
            $response->icons = array(
                '1x' => 'https://translio.to/icon-128.png',
                '2x' => 'https://translio.to/icon-256.png',
            );
            $response->banners = array(
                'low' => 'https://translio.to/banner-772x250.png',
                'high' => 'https://translio.to/banner-1544x500.png',
            );
            $response->requires = $remote->requires;
            $response->requires_php = $remote->requires_php;

            $transient->response[TRANSLIO_PLUGIN_BASENAME] = $response;
        }

        return $transient;
    }

    /**
     * Plugin info popup
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== self::SLUG) {
            return $result;
        }

        $remote = $this->get_plugin_info();

        if (!$remote) {
            return $result;
        }

        $info = new stdClass();
        $info->name = $remote->name;
        $info->slug = $remote->slug;
        $info->version = $remote->version;
        $info->tested = $remote->tested;
        $info->requires = $remote->requires;
        $info->requires_php = $remote->requires_php;
        $info->author = $remote->author;
        $info->author_profile = $remote->author_profile;
        $info->download_link = $remote->download_link;
        $info->trunk = $remote->download_link;
        $info->last_updated = $remote->last_updated;
        $info->sections = (array) $remote->sections;
        $info->banners = (array) $remote->banners;
        $info->icons = (array) $remote->icons;

        return $info;
    }

    /**
     * Get remote version info
     */
    private function get_remote_version() {
        $cached = get_transient(self::CACHE_KEY);

        if ($cached !== false) {
            return $cached;
        }

        $license_key = get_option('translio_license_key', '');

        $response = wp_remote_get(
            add_query_arg(
                array(
                    'slug' => self::SLUG,
                    'version' => TRANSLIO_VERSION,
                    'license_key' => $license_key,
                ),
                self::UPDATE_URL . '/update-check'
            ),
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            )
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data) || !isset($data->version)) {
            return false;
        }

        set_transient(self::CACHE_KEY, $data, self::CACHE_EXPIRATION);

        return $data;
    }

    /**
     * Get full plugin info from server
     */
    private function get_plugin_info() {
        $response = wp_remote_get(
            add_query_arg(
                array('slug' => self::SLUG),
                self::UPDATE_URL . '/plugin-info'
            ),
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/json',
                ),
            )
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body);
    }

    /**
     * Clear update cache after plugin update
     */
    public function after_update($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            $plugins = isset($options['plugins']) ? $options['plugins'] : array();

            if (in_array(TRANSLIO_PLUGIN_BASENAME, $plugins)) {
                delete_transient(self::CACHE_KEY);
            }
        }
    }

    /**
     * Force check for updates
     */
    public static function force_check() {
        delete_transient(self::CACHE_KEY);
        delete_site_transient('update_plugins');
        wp_update_plugins();
    }
}

/**
 * Get updater instance
 */
function translio_updater() {
    return Translio_Updater::instance();
}
