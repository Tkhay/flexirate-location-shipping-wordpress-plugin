<?php

/**
 * Plugin Name: WC Shipping Rate Engine
 * Description: Defines custom shipping zones and calculates rates based on the customer's selected City/Town name via a checkout modal.
 * Version:     1.1.2
 * Author:      Tieku Asare
 * Author URI:  https://tiekuasare.com/
 * Text Domain: wc-shipping-rate-engine
 * Domain Path: /languages
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('WCSRE_PLUGIN_FILE', __FILE__);

// =================================================================
// 1. INITIALIZATION
// =================================================================

function wcsre_add_custom_shipping_method($methods)
{
    require_once 'class-wc-shipping-rate-engine.php';
    $methods['wc_rate_engine'] = 'WCSRE_Shipping_Rate_Engine';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'wcsre_add_custom_shipping_method');

/**
 * Enqueue Scripts & Pass Data
 */
function wcsre_enqueue_scripts()
{
    if (! is_checkout()) return;

    wp_register_script('wcsre-script', '', [], '', true);
    wp_localize_script('wcsre-script', 'wcsre_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('wcsre_nonce'),
    ));
    wp_enqueue_script('wcsre-script');
}
add_action('wp_enqueue_scripts', 'wcsre_enqueue_scripts');


// =================================================================
// 2. BACKEND LOGIC (SESSION & CACHE HANDLING)
// =================================================================

/**
 * Step 1: The Custom AJAX Handler
 * Saves session data BEFORE the standard checkout update runs.
 */
function wcsre_handle_session_update()
{
    if (! isset($_POST['security']) || ! wp_verify_nonce($_POST['security'], 'wcsre_nonce')) {
        wp_send_json_error('Security check failed');
    }

    if (isset($_POST['location'])) {
        $location = sanitize_text_field($_POST['location']);

        if (WC()->session) {
            WC()->session->set('wcsre_selected_location', $location);
            if (method_exists(WC()->session, 'save_data')) {
                WC()->session->save_data();
            }
        }
    }

    wp_send_json_success(array('message' => 'Session updated'));
}
add_action('wp_ajax_wcsre_update_session', 'wcsre_handle_session_update');
add_action('wp_ajax_nopriv_wcsre_update_session', 'wcsre_handle_session_update');

/**
 * CRITICAL FIX: Force Clear Shipping Cache
 * This makes WooCommerce "forget" the old price (66.00) so it recalculates using our new session data.
 */
function wcsre_clear_shipping_cache()
{
    if (! WC()->cart) return;

    $packages = WC()->cart->get_shipping_packages();
    foreach ($packages as $key => $package) {
        $shipping_session_key = 'shipping_for_package_' . $key;
        if (WC()->session) {
            WC()->session->__unset($shipping_session_key);
        }
    }
}
// Run this early (priority 1) during the checkout update
add_action('woocommerce_checkout_update_order_review', 'wcsre_clear_shipping_cache', 1);


// =================================================================
// 3. UI RENDERING & STYLES
// =================================================================

function wcsre_get_first_active_instance_id()
{
    if (! class_exists('WC_Shipping_Zones')) {
        return 0;
    }
    $shipping_zones = WC_Shipping_Zones::get_zones();
    foreach ($shipping_zones as $zone_id => $zone_data) {
        foreach ($zone_data['shipping_methods'] as $method) {
            if ($method->id === 'wc_rate_engine' && $method->instance_id > 0) return $method->instance_id;
        }
    }
    $default_zone = new WC_Shipping_Zone(0);
    foreach ($default_zone->get_shipping_methods() as $method) {
        if ($method->id === 'wc_rate_engine' && $method->instance_id > 0) return $method->instance_id;
    }
    return 0;
}

function wcsre_add_checkout_styles()
{
    if (! is_checkout()) {
        return;
    }
?>
    <style>
        .wcsre-location-selector-wrap {
            margin-top: 15px;
            padding: 15px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }

        #wcsre-open-modal {
            background-color: #111827;
            color: #ffffff;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }

        #wcsre-open-modal:hover {
            background-color: #374151;
        }

        #wcsre-modal {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .wcsre-modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            font-family: inherit;
        }

        .wcsre-modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            background-color: #fff;
        }

        .wcsre-modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #111827;
        }

        .wcsre-modal-body {
            max-height: 50vh;
            overflow-y: auto;
        }

        .wcsre-radio-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background 0.15s;
            margin: 0 !important;
            width: 100%;
        }

        .wcsre-radio-label:hover {
            background-color: #f3f4f6;
        }

        .wcsre-radio-label input[type="radio"] {
            margin-right: 10px;
        }

        .wcsre-price-badge {
            background-color: #f3f4f6;
            color: #374151;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
        }

        .wcsre-modal-footer {
            padding: 16px 20px;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            text-align: right;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        #wcsre-confirm-selection {
            background-color: #111827;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        #wcsre-close-modal {
            background-color: #fff;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
<?php
}
add_action('wp_head', 'wcsre_add_checkout_styles');

function wcsre_add_location_selector_ui()
{
    if (! is_checkout() || is_wc_endpoint_url()) {
        return;
    }
    $instance_id = wcsre_get_first_active_instance_id();
    if (! $instance_id) {
        return;
    }

    if (!class_exists('WCSRE_Shipping_Rate_Engine')) require_once 'class-wc-shipping-rate-engine.php';

    $shipping_method = new WCSRE_Shipping_Rate_Engine($instance_id);
    $custom_key = 'wcsre_custom_rules_' . $instance_id;
    $location_rules = get_option($custom_key, array());

    $selected_slug = WC()->session->get('wcsre_selected_location') ?? '';
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
    <tr class="wcsre-location-row">
        <td colspan="2">
            <div class="wcsre-location-selector-wrap">
                <label style="font-weight: bold; display: block; margin-bottom: 5px; color: #374151;">
                    <?php _e('Delivery Area Selection', 'wc-shipping-rate-engine'); ?> <span style="color:red;">*</span>
                </label>
                <input type="hidden" id="wcsre_selected_location" name="wcsre_selected_location" value="<?php echo esc_attr($selected_slug); ?>" />
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="button" id="wcsre-open-modal">
                        <?php echo $selected_label ? esc_html($selected_label) : __('Select Your Area', 'wc-shipping-rate-engine'); ?>
                    </button>
                    <?php if ($selected_label) : ?>
                        <span id="wcsre-selected-display" style="font-size: 14px; font-weight: 600; color: #047857;">✓ <?php echo esc_html($selected_label); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php wcsre_output_location_modal(); ?>
        </td>
    </tr>
<?php
}
add_action('woocommerce_review_order_after_shipping', 'wcsre_add_location_selector_ui', 10);

function wcsre_output_location_modal()
{
    $instance_id = wcsre_get_first_active_instance_id();
    if (! $instance_id) return;
    $custom_key = 'wcsre_custom_rules_' . $instance_id;
    $location_rules = get_option($custom_key, array());
    if (!class_exists('WCSRE_Shipping_Rate_Engine')) require_once 'class-wc-shipping-rate-engine.php';
    $shipping_method = new WCSRE_Shipping_Rate_Engine($instance_id);
?>
    <div id="wcsre-modal">
        <div class="wcsre-modal-content">
            <div class="wcsre-modal-header">
                <h3><?php _e('Select Your Delivery Area', 'wc-shipping-rate-engine'); ?></h3>
            </div>
            <div class="wcsre-modal-body">
                <?php if ($location_rules) : foreach ($location_rules as $rule) :
                        $label = sanitize_text_field($rule['location']);
                        $slug = method_exists($shipping_method, 'generate_location_slug') ? $shipping_method->generate_location_slug($label) : '';
                        $cost = wc_price(floatval($rule['cost']));
                ?>
                        <label class="wcsre-radio-label">
                            <span style="display: flex; align-items: center;">
                                <input type="radio" name="wcsre_location_select" value="<?php echo esc_attr($slug); ?>" data-label="<?php echo esc_attr($label); ?>" />
                                <span style="font-weight: 500; color: #111827;"><?php echo esc_html($label); ?></span>
                            </span>
                            <span class="wcsre-price-badge"><?php echo wp_kses_post($cost); ?></span>
                        </label>
                    <?php endforeach;
                else : ?>
                    <div style="padding: 20px; text-align: center; color: #6b7280;"><?php _e('No locations configured.', 'wc-shipping-rate-engine'); ?></div>
                <?php endif; ?>
            </div>
            <div class="wcsre-modal-footer">
                <button type="button" id="wcsre-close-modal"><?php _e('Cancel', 'wc-shipping-rate-engine'); ?></button>
                <button type="button" id="wcsre-confirm-selection"><?php _e('Confirm Selection', 'wc-shipping-rate-engine'); ?></button>
            </div>
        </div>
    </div>
    <?php
}


// =================================================================
// 4. JAVASCRIPT (THE TWO-STEP AJAX UPDATE)
// =================================================================

function wcsre_checkout_scripts()
{
    if (is_checkout() && ! is_wc_endpoint_url()) :
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {

                // OPEN MODAL
                $('body').on('click', '#wcsre-open-modal', function(e) {
                    e.preventDefault();
                    var current_slug = $('#wcsre_selected_location').val();
                    $('input[name="wcsre_location_select"]').prop('checked', false);
                    if (current_slug) {
                        $('input[name="wcsre_location_select"][value="' + current_slug + '"]').prop('checked', true);
                    }
                    $('#wcsre-modal').fadeIn(200);
                });

                // CLOSE MODAL
                $('body').on('click', '#wcsre-close-modal', function(e) {
                    e.preventDefault();
                    $('#wcsre-modal').fadeOut(200);
                });
                $(window).on('click', function(e) {
                    if (e.target.id === 'wcsre-modal') {
                        $('#wcsre-modal').fadeOut(200);
                    }
                });

                // CONFIRM SELECTION (THE TWO-STEP FIX)
                $('body').on('click', '#wcsre-confirm-selection', function(e) {
                    e.preventDefault();
                    var selected_radio = $('input[name="wcsre_location_select"]:checked');

                    if (selected_radio.length) {
                        var selected_slug = selected_radio.val();
                        var selected_label = selected_radio.data('label');

                        $('#wcsre_selected_location').val(selected_slug);
                        $('#wcsre-open-modal').text('Updating...');
                        $('#wcsre-modal').fadeOut(200);

                        // STEP 1: Custom AJAX to save the session securely
                        $.ajax({
                            type: 'POST',
                            url: wcsre_params.ajax_url,
                            data: {
                                action: 'wcsre_update_session',
                                security: wcsre_params.nonce,
                                location: selected_slug
                            },
                            success: function(response) {
                                if (response.success) {
                                    // STEP 2: Trigger standard WooCommerce update
                                    // Now that session is saved and cache is cleared (by PHP), this works!
                                    $('body').trigger('update_checkout');

                                    // Update button label purely for visual feedback
                                    setTimeout(function() {
                                        $('#wcsre-open-modal').text(selected_label);
                                        if ($('#wcsre-selected-display').length) {
                                            $('#wcsre-selected-display').text('✓ ' + selected_label);
                                        }
                                    }, 1500);
                                } else {
                                    alert('Error updating location');
                                    $('#wcsre-open-modal').text('Try Again');
                                }
                            },
                            error: function() {
                                alert('Connection error');
                                $('#wcsre-open-modal').text('Try Again');
                            }
                        });

                    } else {
                        alert('<?php echo esc_js(__('Please select a delivery area.', 'wc-shipping-rate-engine')); ?>');
                    }
                });
            });
        </script>
<?php
    endif;
}
add_action('wp_footer', 'wcsre_checkout_scripts');

// =================================================================
// 5. SAFETY VALIDATION (PREVENTS SKIPPED SHIPPING)
// =================================================================

/**
 * Stop checkout if no location is selected or if session data is missing.
 */
function wcsre_validate_delivery_selection()
{
    // 1. Check if the "WC Shipping Rate Engine" method is actually chosen for shipping
    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    $is_our_method  = false;

    if (! empty($chosen_methods)) {
        foreach ($chosen_methods as $method) {
            if (strpos($method, 'wc_rate_engine') !== false) {
                $is_our_method = true;
                break;
            }
        }
    }

    // If our method isn't being used, we don't need to validate
    if (! $is_our_method) {
        return;
    }

    // 2. Check if the hidden input field has a value
    $posted_location = isset($_POST['wcsre_selected_location']) ? sanitize_text_field($_POST['wcsre_selected_location']) : '';

    if (empty($posted_location)) {
        wc_add_notice(__('Please click "Select Your Area" and choose a delivery location to continue.', 'wc-shipping-rate-engine'), 'error');
    }

    // 3. FAILSAFE: Re-save session if it was missed due to network error
    $session_location = WC()->session->get('wcsre_selected_location');

    if (! empty($posted_location) && empty($session_location)) {
        WC()->session->set('wcsre_selected_location', $posted_location);
    }
}
add_action('woocommerce_checkout_process', 'wcsre_validate_delivery_selection');
