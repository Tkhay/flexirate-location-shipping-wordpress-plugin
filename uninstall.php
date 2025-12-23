<?php

/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, die.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

/**
 * 1. Delete plugin-specific options
 */
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'locsh_for_woocom_%'");

/**
 * 2. Delete WooCommerce shipping method settings
 */
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_locship_location_%'");
