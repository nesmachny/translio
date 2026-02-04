<?php
/**
 * Plugin Name: Translio
 * Plugin URI: https://translio.to
 * Description: A lightweight WordPress translation plugin with Anthropic API integration for automatic translations.
 * Version: 2.3.8
 * Author: Sergey Nesmachny
 * Author URI: https://nesmachny.net
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: translio
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Suppress PHP 8.1+ deprecation warnings for wp_normalize_path() null parameter issue
// This is a WordPress core bug that occurs when theme paths contain null values
$translio_old_error_handler = set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$translio_old_error_handler) {
    // Only suppress specific deprecation warnings from WordPress core
    if ($errno === E_DEPRECATED && strpos($errstr, 'Passing null') !== false) {
        return true; // Suppress this error
    }
    // Call previous handler for other errors
    if ($translio_old_error_handler) {
        return call_user_func($translio_old_error_handler, $errno, $errstr, $errfile, $errline);
    }
    return false; // Let PHP handle it
}, E_DEPRECATED);

// DEBUG: Profile plugin load time
define('TRANSLIO_START_TIME', microtime(true));

define('TRANSLIO_VERSION', '2.3.8');
define('TRANSLIO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TRANSLIO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TRANSLIO_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio.php';
require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-license.php';
require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-rest-api.php';
require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-updater.php';

// Auto-flush rewrite rules and update DB on version change
add_action('init', function() {
    $stored_version = get_option('translio_version', '');
    if ($stored_version !== TRANSLIO_VERSION) {
        update_option('translio_version', TRANSLIO_VERSION);
        set_transient('translio_flush_rules', true, 60);
        // Run DB migrations (creates new tables if needed)
        require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-db.php';
        Translio_DB::create_tables();

        // Clear license cache to fetch fresh data with new fields
        delete_transient('translio_license_cache');

        // Migrate settings from old WP Lingua plugin
        translio_migrate_from_wp_lingua();

        // Fix object_type for pages (v2.2.1 migration)
        translio_migrate_page_object_types();

        // Migrate to multi-language support (v2.3.0 migration)
        translio_migrate_to_multi_language();
    }
}, 0);

/**
 * Fix object_type for page translations
 * Pages were incorrectly saved with object_type='post' instead of 'page'
 *
 * @since 2.2.1
 */
function translio_migrate_page_object_types() {
    global $wpdb;

    // Check if migration already ran
    if (get_option('translio_migration_page_types_done')) {
        return;
    }

    $translations_table = $wpdb->prefix . 'translio_translations';

    // Get all page IDs
    $page_ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page'");

    if (!empty($page_ids)) {
        $placeholders = implode(',', array_fill(0, count($page_ids), '%d'));

        // Update translations where object_id is a page but object_type is 'post'
        $wpdb->query($wpdb->prepare(
            "UPDATE {$translations_table}
             SET object_type = 'page'
             WHERE object_id IN ({$placeholders})
             AND object_type = 'post'",
            $page_ids
        ));
    }

    // Mark migration as done
    update_option('translio_migration_page_types_done', true);
}

/**
 * Migrate from single secondary language to multi-language support
 *
 * @since 2.3.0
 */
function translio_migrate_to_multi_language() {
    // Check if already migrated
    if (get_option('translio_migrated_to_multi', false)) {
        return;
    }

    // Get legacy single language setting
    $legacy_language = get_option('translio_secondary_language', '');

    if (!empty($legacy_language)) {
        // Check if new option doesn't exist yet
        $new_languages = get_option('translio_secondary_languages', array());

        if (empty($new_languages)) {
            // Migrate to new array format
            update_option('translio_secondary_languages', array($legacy_language));
        }
    }

    // Mark migration as complete
    update_option('translio_migrated_to_multi', true);

    // Flush rewrite rules to register routes for all languages
    set_transient('translio_flush_rules', true, 60);
}

/**
 * Migrate settings and data from old WP Lingua plugin
 */
function translio_migrate_from_wp_lingua() {
    global $wpdb;

    // Migrate options
    $options_map = array(
        'wp_lingua_api_key' => 'translio_api_key',
        'wp_lingua_default_language' => 'translio_default_language',
        'wp_lingua_secondary_language' => 'translio_secondary_language',
        'wp_lingua_delete_data_on_uninstall' => 'translio_delete_data_on_uninstall',
        'wp_lingua_scan_strings' => 'translio_scan_strings',
        'wp_lingua_debug' => 'translio_debug',
    );

    foreach ($options_map as $old_key => $new_key) {
        $old_value = get_option($old_key);
        if ($old_value !== false && get_option($new_key) === false) {
            update_option($new_key, $old_value);
        }
    }

    // Migrate database tables
    $old_translations = $wpdb->prefix . 'wp_lingua_translations';
    $new_translations = $wpdb->prefix . 'translio_translations';
    $old_languages = $wpdb->prefix . 'wp_lingua_languages';
    $new_languages = $wpdb->prefix . 'translio_languages';
    $old_strings = $wpdb->prefix . 'lingua_strings';
    $new_strings = $wpdb->prefix . 'translio_strings';

    // Check if old tables exist and new tables are empty
    $old_trans_exists = $wpdb->get_var("SHOW TABLES LIKE '{$old_translations}'") === $old_translations;
    $new_trans_empty = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$new_translations}") === 0;

    if ($old_trans_exists && $new_trans_empty) {
        // Migrate translations
        $wpdb->query("INSERT INTO {$new_translations} SELECT * FROM {$old_translations}");

        // Migrate languages
        $old_lang_exists = $wpdb->get_var("SHOW TABLES LIKE '{$old_languages}'") === $old_languages;
        $new_lang_empty = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$new_languages}") === 0;
        if ($old_lang_exists && $new_lang_empty) {
            $wpdb->query("INSERT INTO {$new_languages} SELECT * FROM {$old_languages}");
        }

        // Migrate strings
        $old_str_exists = $wpdb->get_var("SHOW TABLES LIKE '{$old_strings}'") === $old_strings;
        $new_str_empty = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$new_strings}") === 0;
        if ($old_str_exists && $new_str_empty) {
            $wpdb->query("INSERT INTO {$new_strings} SELECT * FROM {$old_strings}");
        }
    }
}

function translio_activate() {
    require_once TRANSLIO_PLUGIN_DIR . 'includes/class-translio-db.php';
    Translio_DB::create_tables();
    Translio_DB::seed_languages();
    // Set transient to flush rules on next init (after Router adds its rules)
    set_transient('translio_flush_rules', true, 60);
}

function translio_deactivate() {
    flush_rewrite_rules();
}

register_activation_hook(__FILE__, 'translio_activate');
register_deactivation_hook(__FILE__, 'translio_deactivate');

function translio() {
    return Translio::instance();
}

// Backwards compatibility alias
function wp_lingua() {
    return translio();
}

// Initialize plugin, license manager, REST API, and updater
translio();
translio_license();
translio_rest_api();
translio_updater();
