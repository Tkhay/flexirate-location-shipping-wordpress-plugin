<?php

/**
 * Plugin Name:       LocShip for WooCommerce
 * Description:       Custom shipping zones and calculates rates based on the customer's selected City/Town name via a checkout modal.
 * Version:           1.0.0
 * Author:            Tieku Asare
 * Author URI:        https://www.tiekuasare.com/
 * Text Domain:       locship-woocommerce
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 9.0
 * WC tested up to:   10.4.2
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('LS_PLUGIN_FILE', __FILE__);
define('LS_PLUGIN_URL', plugin_dir_url(__FILE__));

// =================================================================
// 1. DECLARE HPOS COMPATIBILITY (FIXES THE ERROR)
// =================================================================
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// =================================================================
// 2. INIT
// =================================================================
function ls_add_custom_shipping_method($methods)
{
    require_once 'class-locship-woocommerce.php';
    $methods['locship_location'] = 'LS_Shipping_Method';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'ls_add_custom_shipping_method');

// =================================================================
// 3. ENQUEUE ASSETS (FRONTEND)
// =================================================================
function ls_enqueue_scripts()
{
    if (! is_checkout()) {
        return;
    }

    // CSS
    wp_enqueue_style('ls-style', LS_PLUGIN_URL . 'assets/css/style.css', array(), '1.0.0');

    // JS
    wp_enqueue_script('ls-script', LS_PLUGIN_URL . 'assets/js/script.js', array('jquery'), '1.0.0', true);

    // Pass PHP data to JS
    wp_localize_script(
        'ls-script',
        'ls_params',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ls_nonce'),
        )
    );
}
add_action('wp_enqueue_scripts', 'ls_enqueue_scripts');

// =================================================================
// 4. ENQUEUE ASSETS (ADMIN)
// =================================================================
function ls_admin_scripts()
{
    // Check if we are on WooCommerce Settings -> Shipping Tab
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed for simple screen context check.
    if (isset($_GET['page']) && 'wc-settings' === $_GET['page'] && isset($_GET['tab']) && 'shipping' === $_GET['tab']) {
        wp_enqueue_style('ls-admin-style', LS_PLUGIN_URL . 'assets/css/admin.css', array(), '1.0.0');
        wp_enqueue_script('ls-admin-script', LS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), '1.0.0', true);
    }
}
add_action('admin_enqueue_scripts', 'ls_admin_scripts');

// =================================================================
// 5. AJAX HANDLER
// =================================================================
function ls_handle_session_update()
{
    // Fix: Unslash and sanitize security nonce before verification
    if (
        ! isset($_POST['security']) ||
        ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['security'])), 'ls_nonce')
    ) {
        wp_send_json_error('Security check failed');
    }

    if (isset($_POST['location'])) {
        // Fix: Unslash before sanitization
        $location = sanitize_text_field(wp_unslash($_POST['location']));

        if (WC()->session) {
            WC()->session->set('ls_selected_location', $location);
            if (method_exists(WC()->session, 'save_data')) {
                WC()->session->save_data();
            }
        }
    }
    wp_send_json_success(array('message' => 'Session updated'));
}
add_action('wp_ajax_ls_update_session', 'ls_handle_session_update');
add_action('wp_ajax_nopriv_ls_update_session', 'ls_handle_session_update');

// =================================================================
// 6. CACHE CLEARING
// =================================================================
function ls_clear_shipping_cache()
{
    if (! WC()->cart) {
        return;
    }
    $packages = WC()->cart->get_shipping_packages();
    foreach ($packages as $key => $package) {
        $shipping_session_key = 'shipping_for_package_' . $key;
        if (WC()->session) {
            WC()->session->__unset($shipping_session_key);
        }
    }
}
add_action('woocommerce_checkout_update_order_review', 'ls_clear_shipping_cache', 1);

// =================================================================
// 7. HELPER: GET INSTANCE ID
// =================================================================
function ls_get_first_active_instance_id()
{
    if (! class_exists('WC_Shipping_Zones')) {
        return 0;
    }
    $shipping_zones = WC_Shipping_Zones::get_zones();
    foreach ($shipping_zones as $zone_id => $zone_data) {
        foreach ($zone_data['shipping_methods'] as $method) {
            if ('locship_location' === $method->id && $method->instance_id > 0) {
                return $method->instance_id;
            }
        }
    }
    $default_zone = new WC_Shipping_Zone(0);
    foreach ($default_zone->get_shipping_methods() as $method) {
        if ('locship_location' === $method->id && $method->instance_id > 0) {
            return $method->instance_id;
        }
    }
    return 0;
}

// =================================================================
// 8. UI: ADD BUTTON & MODAL
// =================================================================
function ls_add_location_selector_ui()
{
    if (! is_checkout() || is_wc_endpoint_url()) {
        return;
    }
    $instance_id = ls_get_first_active_instance_id();
    if (! $instance_id) {
        return;
    }

    if (! class_exists('LS_Shipping_Method')) {
        require_once 'class-locship-woocommerce.php';
    }

    $shipping_method = new LS_Shipping_Method($instance_id);
    $custom_key     = 'ls_custom_rules_' . $instance_id;
    $location_rules = get_option($custom_key, array());

    $selected_slug  = WC()->session->get('ls_selected_location') ?? '';
    $selected_label = '';

    if ($selected_slug) {
        foreach ($location_rules as $rule) {
            if (method_exists($shipping_method, 'generate_location_slug') && $shipping_method->generate_location_slug($rule['location']) === $selected_slug) {
                $selected_label = sanitize_text_field($rule['location']);
                break;
            }
        }
    }
?>
    <tr class="ls-location-row">
        <td colspan="2">
            <div class="ls-location-selector-wrap">
                <label style="font-weight: bold; display: block; margin-bottom: 5px; color: #374151;">
                    <?php esc_html_e('Delivery Area Selection', 'locship-woocommerce'); ?> <span style="color: red;">*</span>
                </label>
                <input type="hidden" id="ls_selected_location" name="ls_selected_location" value="<?php echo esc_attr($selected_slug); ?>" />
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" id="ls-open-modal">
                        <?php echo $selected_label ? esc_html($selected_label) : esc_html__('Select Your Area', 'locship-woocommerce'); ?>
                    </button>
                    <?php if ($selected_label) : ?>
                        <span id="ls-selected-display" style="font-size: 14px; font-weight: 600; color: #047857;">✓ <?php echo esc_html($selected_label); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php // Modal moved to footer 
            ?>
        </td>
    </tr>
<?php
}
add_action('woocommerce_review_order_after_shipping', 'ls_add_location_selector_ui', 10);

// =================================================================
// 9. OUTPUT MODAL HTML
// =================================================================
function ls_output_location_modal()
{
    if (! is_checkout()) {
        return;
    }
    $instance_id = ls_get_first_active_instance_id();
    if (! $instance_id) {
        return;
    }
    $custom_key     = 'ls_custom_rules_' . $instance_id;
    $location_rules = get_option($custom_key, array());
    if (! class_exists('LS_Shipping_Method')) {
        require_once 'class-locship-woocommerce.php';
    }
    $shipping_method = new LS_Shipping_Method($instance_id);
?>
    <div id="ls-modal">
        <div class="ls-modal-content">
            <div class="ls-modal-header">
                <h3><?php esc_html_e('Select Your Delivery Area', 'locship-woocommerce'); ?></h3>
            </div>
            <div class="ls-modal-body">
                <?php if ($location_rules) : foreach ($location_rules as $rule) :
                        $label = sanitize_text_field($rule['location']);
                        $slug  = method_exists($shipping_method, 'generate_location_slug') ? $shipping_method->generate_location_slug($label) : '';
                        $cost  = wc_price(floatval($rule['cost']));
                ?>
                        <label class="ls-radio-label">
                            <input type="radio" name="ls_location_select" value="<?php echo esc_attr($slug); ?>" data-label="<?php echo esc_attr($label); ?>" />
                            <span class="ls-label-text"><?php echo esc_html($label); ?></span>
                            <span class="ls-price-badge"><?php echo wp_kses_post($cost); ?></span>
                        </label>
                    <?php endforeach;
                else : ?>
                    <div style="padding: 20px; text-align: center; color: #6b7280;"><?php esc_html_e('No locations configured.', 'locship-woocommerce'); ?></div>
                <?php endif; ?>
            </div>
            <div class="ls-modal-footer">
                <button type="button" id="ls-close-modal"><?php esc_html_e('Cancel', 'locship-woocommerce'); ?></button>
                <button type="button" id="ls-confirm-selection"><?php esc_html_e('Confirm Selection', 'locship-woocommerce'); ?></button>
            </div>
        </div>
    </div>
<?php
}
add_action('wp_footer', 'ls_output_location_modal');

// =================================================================
// 10. VALIDATION
// =================================================================
function ls_validate_delivery_selection()
{
    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    $is_our_method  = false;
    if (! empty($chosen_methods)) {
        foreach ($chosen_methods as $method) {
            if (false !== strpos($method, 'locship_location')) {
                $is_our_method = true;
                break;
            }
        }
    }
    if (! $is_our_method) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Hooked into woocommerce_checkout_process, which handles the nonce check.
    $posted_location = isset($_POST['ls_selected_location']) ? sanitize_text_field(wp_unslash($_POST['ls_selected_location'])) : '';

    if (empty($posted_location)) {
        wc_add_notice(__('Please click "Select Your Area" and choose a delivery location.', 'locship-woocommerce'), 'error');
    }
    $session_location = WC()->session->get('ls_selected_location');
    if (! empty($posted_location) && empty($session_location)) {
        WC()->session->set('ls_selected_location', $posted_location);
    }
}
add_action('woocommerce_checkout_process', 'ls_validate_delivery_selection');
