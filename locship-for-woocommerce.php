<?php

/**
 * Plugin Name:       LocShip for WooCommerce
 * Description:       Custom shipping zones and calculates rates based on the customer's selected City/Town name via a checkout modal.
 * Version:           1.0.5
 * Author:            Tieku Asare
 * Author URI:        https://www.tiekuasare.com/
 * Text Domain:       locship-for-woocommerce
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 9.0
 * WC tested up to:   11.2.1
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('LOCSH_FOR_WOOCOM_PLUGIN_FILE', __FILE__);
define('LOCSH_FOR_WOOCOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LOCSH_FOR_WOOCOM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LOCSH_FOR_WOOCOM_VERSION', '1.0.5');

// =================================================================
// 1. DECLARE HPOS COMPATIBILITY (FIXES THE ERROR)
// =================================================================
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        // The area selector only works with the classic (shortcode) checkout.
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
    }
});

// =================================================================
// 2. INIT
// =================================================================
function locsh_for_woocom_add_custom_shipping_method($methods)
{
    require_once LOCSH_FOR_WOOCOM_PLUGIN_DIR . 'class-locship-for-woocommerce.php';
    $methods['locship_location'] = 'LOCSH_FOR_WOOCOM_Shipping_Method';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'locsh_for_woocom_add_custom_shipping_method');

// =================================================================
// 3. ENQUEUE ASSETS (FRONTEND)
// =================================================================
function locsh_for_woocom_enqueue_scripts()
{
    if (! is_checkout()) {
        return;
    }

    // CSS
    wp_enqueue_style('locsh-for-woocom-style', LOCSH_FOR_WOOCOM_PLUGIN_URL . 'assets/css/style.css', array(), LOCSH_FOR_WOOCOM_VERSION);

    // JS
    wp_enqueue_script('locsh-for-woocom-script', LOCSH_FOR_WOOCOM_PLUGIN_URL . 'assets/js/script.js', array('jquery'), LOCSH_FOR_WOOCOM_VERSION, true);

    // Pass PHP data to JS
    wp_localize_script(
        'locsh-for-woocom-script',
        'locsh_for_woocom_params',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('locsh_for_woocom_nonce'),
        )
    );
}
add_action('wp_enqueue_scripts', 'locsh_for_woocom_enqueue_scripts');

// =================================================================
// 4. ENQUEUE ASSETS (ADMIN)
// =================================================================
function locsh_for_woocom_admin_scripts()
{
    // Check if we are on WooCommerce Settings -> Shipping Tab
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed for simple screen context check.
    if (isset($_GET['page']) && 'wc-settings' === $_GET['page'] && isset($_GET['tab']) && 'shipping' === $_GET['tab']) {
        wp_enqueue_style('locsh-for-woocom-admin-style', LOCSH_FOR_WOOCOM_PLUGIN_URL . 'assets/css/admin.css', array(), LOCSH_FOR_WOOCOM_VERSION);
        wp_enqueue_script('locsh-for-woocom-admin-script', LOCSH_FOR_WOOCOM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), LOCSH_FOR_WOOCOM_VERSION, true);
    }
}
add_action('admin_enqueue_scripts', 'locsh_for_woocom_admin_scripts');

// =================================================================
// 5. AJAX HANDLER
// =================================================================
function locsh_for_woocom_handle_session_update()
{
    if (
        ! isset($_POST['security']) ||
        ! wp_verify_nonce(sanitize_key(wp_unslash($_POST['security'])), 'locsh_for_woocom_nonce')
    ) {
        wp_send_json_error(__('Security check failed', 'locship-for-woocommerce'));
    }

    if (isset($_POST['location'])) {
        $location = sanitize_text_field(wp_unslash($_POST['location']));

        // Only accept slugs that match a configured delivery area.
        if (! locsh_for_woocom_is_valid_slug($location, locsh_for_woocom_get_instance_id())) {
            wp_send_json_error(__('Invalid delivery area', 'locship-for-woocommerce'));
        }

        if (WC()->session) {
            WC()->session->set('locsh_for_woocom_selected_location', $location);
            if (method_exists(WC()->session, 'save_data')) {
                WC()->session->save_data();
            }
        }
    }
    wp_send_json_success(array('message' => __('Session updated', 'locship-for-woocommerce')));
}
add_action('wp_ajax_locsh_for_woocom_update_session', 'locsh_for_woocom_handle_session_update');
add_action('wp_ajax_nopriv_locsh_for_woocom_update_session', 'locsh_for_woocom_handle_session_update');

// =================================================================
// 6. CACHE CLEARING
// =================================================================
function locsh_for_woocom_clear_shipping_cache()
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
add_action('woocommerce_checkout_update_order_review', 'locsh_for_woocom_clear_shipping_cache', 1);

// =================================================================
// 7. HELPER: GET INSTANCE ID
// =================================================================
function locsh_for_woocom_get_first_active_instance_id()
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

/**
 * Resolve the LocShip instance for the customer's chosen shipping method,
 * falling back to the first active instance.
 */
function locsh_for_woocom_get_instance_id()
{
    $session = function_exists('WC') ? WC()->session : null;
    if ($session) {
        $chosen = $session->get('chosen_shipping_methods');
        if (is_array($chosen)) {
            foreach ($chosen as $method) {
                if (is_string($method) && 0 === strpos($method, 'locship_location:')) {
                    $instance_id = absint(substr($method, strlen('locship_location:')));
                    if ($instance_id) {
                        return $instance_id;
                    }
                }
            }
        }
    }
    return locsh_for_woocom_get_first_active_instance_id();
}

/**
 * Check that a slug matches one of the configured delivery areas of an instance.
 */
function locsh_for_woocom_is_valid_slug($slug, $instance_id)
{
    if ('' === $slug || ! $instance_id) {
        return false;
    }
    if (! class_exists('LOCSH_FOR_WOOCOM_Shipping_Method')) {
        require_once LOCSH_FOR_WOOCOM_PLUGIN_DIR . 'class-locship-for-woocommerce.php';
    }
    $shipping_method = new LOCSH_FOR_WOOCOM_Shipping_Method($instance_id);
    $rules           = get_option('locsh_for_woocom_custom_rules_' . $instance_id, array());
    foreach ((array) $rules as $rule) {
        if ($shipping_method->generate_location_slug($rule['location']) === $slug) {
            return true;
        }
    }
    return false;
}

// =================================================================
// 8. UI: ADD BUTTON & MODAL
// =================================================================
function locsh_for_woocom_add_location_selector_ui()
{
    if (! is_checkout() || is_wc_endpoint_url()) {
        return;
    }
    $instance_id = locsh_for_woocom_get_instance_id();
    if (! $instance_id) {
        return;
    }

    if (! class_exists('LOCSH_FOR_WOOCOM_Shipping_Method')) {
        require_once LOCSH_FOR_WOOCOM_PLUGIN_DIR . 'class-locship-for-woocommerce.php';
    }

    $shipping_method = new LOCSH_FOR_WOOCOM_Shipping_Method($instance_id);
    $custom_key     = 'locsh_for_woocom_custom_rules_' . $instance_id;
    $location_rules = get_option($custom_key, array());

    $selected_slug  = WC()->session ? (WC()->session->get('locsh_for_woocom_selected_location') ?? '') : '';
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
    <tr class="locsh-for-woocom-location-row">
        <td colspan="2">
            <div class="locsh-for-woocom-location-selector-wrap">
                <label class="locsh-for-woocom-area-label">
                    <?php esc_html_e('Delivery Area Selection', 'locship-for-woocommerce'); ?> <span class="locsh-for-woocom-required">*</span>
                </label>
                <input type="hidden" id="locsh_for_woocom_selected_location" name="locsh_for_woocom_selected_location" value="<?php echo esc_attr($selected_slug); ?>" />
                <div class="locsh-for-woocom-button-row">
                    <button type="button" id="locsh-for-woocom-open-modal">
                        <?php echo $selected_label ? esc_html($selected_label) : esc_html__('Select Your Area', 'locship-for-woocommerce'); ?>
                    </button>
                    <?php if ($selected_label) : ?>
                        <span id="locsh-for-woocom-selected-display">✓ <?php echo esc_html($selected_label); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php // Modal moved to footer 
            ?>
        </td>
    </tr>
<?php
}
add_action('woocommerce_review_order_after_shipping', 'locsh_for_woocom_add_location_selector_ui', 10);

// =================================================================
// 9. OUTPUT MODAL HTML
// =================================================================
function locsh_for_woocom_output_location_modal()
{
    if (! is_checkout()) {
        return;
    }
    $instance_id = locsh_for_woocom_get_instance_id();
    if (! $instance_id) {
        return;
    }
    $custom_key     = 'locsh_for_woocom_custom_rules_' . $instance_id;
    $location_rules = get_option($custom_key, array());
    if (! class_exists('LOCSH_FOR_WOOCOM_Shipping_Method')) {
        require_once LOCSH_FOR_WOOCOM_PLUGIN_DIR . 'class-locship-for-woocommerce.php';
    }
    $shipping_method = new LOCSH_FOR_WOOCOM_Shipping_Method($instance_id);
?>
    <div id="locsh-for-woocom-modal">
        <div class="locsh-for-woocom-modal-content">
            <div class="locsh-for-woocom-modal-header">
                <h3><?php esc_html_e('Select Your Delivery Area', 'locship-for-woocommerce'); ?></h3>
            </div>
            <div class="locsh-for-woocom-modal-body">
                <?php if ($location_rules) : foreach ($location_rules as $rule) :
                        $label = sanitize_text_field($rule['location']);
                        $slug  = method_exists($shipping_method, 'generate_location_slug') ? $shipping_method->generate_location_slug($label) : '';
                        $cost  = wc_price(floatval($rule['cost']));
                ?>
                        <label class="locsh-for-woocom-radio-label">
                            <input type="radio" name="locsh_for_woocom_location_select" value="<?php echo esc_attr($slug); ?>" data-label="<?php echo esc_attr($label); ?>" />
                            <span class="locsh-for-woocom-label-text"><?php echo esc_html($label); ?></span>
                            <span class="locsh-for-woocom-price-badge"><?php echo wp_kses_post($cost); ?></span>
                        </label>
                    <?php endforeach;
                else : ?>
                    <div class="locsh-for-woocom-no-locations"><?php esc_html_e('No locations configured.', 'locship-for-woocommerce'); ?></div>
                <?php endif; ?>
            </div>
            <div class="locsh-for-woocom-modal-footer">
                <button type="button" id="locsh-for-woocom-close-modal"><?php esc_html_e('Cancel', 'locship-for-woocommerce'); ?></button>
                <button type="button" id="locsh-for-woocom-confirm-selection"><?php esc_html_e('Confirm Selection', 'locship-for-woocommerce'); ?></button>
            </div>
        </div>
    </div>
<?php
}
add_action('wp_footer', 'locsh_for_woocom_output_location_modal');

// =================================================================
// 10. VALIDATION
// =================================================================
function locsh_for_woocom_validate_delivery_selection()
{
    if (! WC()->session) {
        return;
    }
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
    $posted_location = isset($_POST['locsh_for_woocom_selected_location']) ? sanitize_text_field(wp_unslash($_POST['locsh_for_woocom_selected_location'])) : '';

    if (empty($posted_location)) {
        wc_add_notice(__('Please click "Select Your Area" and choose a delivery location.', 'locship-for-woocommerce'), 'error');
        return;
    }

    if (! locsh_for_woocom_is_valid_slug($posted_location, locsh_for_woocom_get_instance_id())) {
        wc_add_notice(__('The selected delivery area is not valid. Please choose an area again.', 'locship-for-woocommerce'), 'error');
        return;
    }

    if (WC()->session->get('locsh_for_woocom_selected_location') !== $posted_location) {
        WC()->session->set('locsh_for_woocom_selected_location', $posted_location);
    }
}
add_action('woocommerce_checkout_process', 'locsh_for_woocom_validate_delivery_selection');
