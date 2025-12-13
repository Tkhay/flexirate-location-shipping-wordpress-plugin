<?php

/**
 * Plugin Name: WC Shipping Rate Engine
 * Plugin URI:  https://example.com/
 * Description: Defines custom shipping zones and calculates rates based on rules.
 * Version:     1.0.0
 * Author:      Tieku Asare
 * Author URI:  https://example.com/
 * Text Domain: wc-shipping-rate-engine
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Global constant for plugin file path
define('WCSRE_PLUGIN_FILE', __FILE__); 

// --- Class Inclusion and Registration Function ---

/**
 * Register the WC Shipping Rate Engine method.
 *
 * @param array $methods Existing shipping methods.
 * @return array Modified shipping methods array.
 */
function wcsre_add_custom_shipping_method($methods)
{ // Renamed function prefix
    // 1. Include the class file
    require 'class-wc-shipping-rate-engine.php';

    // 2. Add the class to the array
    $methods['wc_rate_engine'] = 'WCSRE_Shipping_Rate_Engine';

    return $methods;
}
add_filter('woocommerce_shipping_methods', 'wcsre_add_custom_shipping_method');
