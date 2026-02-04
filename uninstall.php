<?php
/**
 * Translio Uninstall
 *
 * Fired when the plugin is uninstalled.
 * Only deletes data if translio_delete_data option is enabled.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only delete data if explicitly requested in settings
$delete_data = get_option('translio_delete_data_on_uninstall', false);

if ($delete_data) {
    global $wpdb;

    // Drop custom tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}translio_translations");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}translio_languages");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}translio_strings");

    // Also drop old wp_lingua tables if exist (migration cleanup)
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wp_lingua_translations");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wp_lingua_languages");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}lingua_strings");

    // Delete all options
    delete_option('translio_default_language');
    delete_option('translio_secondary_language');
    delete_option('translio_api_key');
    delete_option('translio_delete_data_on_uninstall');
    delete_option('translio_scan_strings');
    delete_option('translio_version');

    // Delete transients
    delete_transient('translio_translate_progress');
    delete_transient('translio_flush_rules');
}

// Always clear rewrite rules
flush_rewrite_rules();
