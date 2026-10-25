<?php

/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, die.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove plugin data for the current site.
 */
function locsh_for_woocom_uninstall_site()
{
    global $wpdb;

    // 1. Plugin-specific options.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('locsh_for_woocom_') . '%'
        )
    );

    // 2. WooCommerce shipping method instance settings.
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like('woocommerce_locship_location_') . '%'
        )
    );

    // 3. Shipping zone method rows left behind in WooCommerce's table.
    $zone_methods_table = $wpdb->prefix . 'woocommerce_shipping_zone_methods';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($zone_methods_table))) === $zone_methods_table) {
        $wpdb->delete($zone_methods_table, array('method_id' => 'locship_location'));
    }
}

if (is_multisite()) {
    foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $locsh_blog_id) {
        switch_to_blog($locsh_blog_id);
        locsh_for_woocom_uninstall_site();
        restore_current_blog();
    }
} else {
    locsh_for_woocom_uninstall_site();
}
