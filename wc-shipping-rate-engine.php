<?php

/**
 * Plugin Name: WC Shipping Rate Engine
 * Plugin URI:  https://example.com/
 * Description: Defines custom shipping zones and calculates rates based on the customer's selected City/Town name via a checkout modal.
 * Version:     1.0.8
 * Author:      Your Name
 * Author URI:  https://example.com/
 * Text Domain: wc-shipping-rate-engine
 * Domain Path: /languages
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Global constant for plugin file path
define('WCSRE_PLUGIN_FILE', __FILE__);

// =================================================================
// 1. PLUGIN SETUP
// =================================================================

/**
 * Register the WC Shipping Rate Engine method.
 */
function wcsre_add_custom_shipping_method($methods)
{
    require_once 'class-wc-shipping-rate-engine.php';
    $methods['wc_rate_engine'] = 'WCSRE_Shipping_Rate_Engine';
    return $methods;
}
add_filter('woocommerce_shipping_methods', 'wcsre_add_custom_shipping_method');


// =================================================================
// 2. CHECKOUT UI & HOOKS
// =================================================================

/**
 * Helper to safely get the instance ID by iterating zones.
 */
function wcsre_get_first_active_instance_id()
{
    if (! class_exists('WC_Shipping_Zones')) {
        return 0;
    }

    // Check user zones
    $shipping_zones = WC_Shipping_Zones::get_zones();
    foreach ($shipping_zones as $zone_id => $zone_data) {
        foreach ($zone_data['shipping_methods'] as $method) {
            if ($method->id === 'wc_rate_engine' && $method->instance_id > 0) {
                return $method->instance_id;
            }
        }
    }

    // Check default zone
    $default_zone = new WC_Shipping_Zone(0);
    foreach ($default_zone->get_shipping_methods() as $method) {
        if ($method->id === 'wc_rate_engine' && $method->instance_id > 0) {
            return $method->instance_id;
        }
    }

    return 0;
}

/**
 * Add Custom CSS for the Modal and Button
 * This creates the modern "Tailwind-like" look using standard CSS.
 */
function wcsre_add_checkout_styles()
{
    if (! is_checkout()) {
        return;
    }
?>
    <style>
        /* Container styling */
        .wcsre-location-selector-wrap {
            margin-top: 15px;
            padding: 15px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }

        /* The main "Select Area" button */
        #wcsre-open-modal {
            background-color: #111827;
            /* Dark/Black */
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

        /* Modal Overlay/Backdrop */
        #wcsre-modal {
            display: none;
            /* Hidden by default */
            position: fixed;
            z-index: 99999;
            /* On top of everything */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            /* Semi-transparent black */
            backdrop-filter: blur(2px);
            /* Blur effect */
        }

        /* Modal Content Box */
        .wcsre-modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            /* 10% from the top and centered */
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: wcsreFadeIn 0.3s;
            overflow: hidden;
            font-family: inherit;
        }

        @keyframes wcsreFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modal Header */
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

        /* Modal Body (Scrollable List) */
        .wcsre-modal-body {
            max-height: 50vh;
            overflow-y: auto;
        }

        /* List Items */
        .wcsre-radio-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background 0.15s;
            margin: 0 !important;
            /* Override theme defaults */
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

        /* Modal Footer */
        .wcsre-modal-footer {
            padding: 16px 20px;
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
            text-align: right;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Footer Buttons */
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


/**
 * 1. Output the UI (Button & Hidden Field)
 */
function wcsre_add_location_selector_ui()
{
    if (! is_checkout() || is_wc_endpoint_url()) {
        return;
    }

    $instance_id = wcsre_get_first_active_instance_id();
    if (! $instance_id) {
        return;
    }

    if (!class_exists('WCSRE_Shipping_Rate_Engine')) {
        require_once 'class-wc-shipping-rate-engine.php';
    }

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

    // Output HTML
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
                        <span id="wcsre-selected-display" style="font-size: 14px; font-weight: 600; color: #047857;">
                            ✓ <?php echo esc_html($selected_label); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php wcsre_output_location_modal(); ?>
        </td>
    </tr>
<?php
}
// Universal hook for Order Summary
add_action('woocommerce_review_order_after_shipping', 'wcsre_add_location_selector_ui', 10);


/**
 * 2. Output the Modal Structure
 */
function wcsre_output_location_modal()
{
    $instance_id = wcsre_get_first_active_instance_id();
    if (! $instance_id) {
        return;
    }

    $custom_key = 'wcsre_custom_rules_' . $instance_id;
    $location_rules = get_option($custom_key, array());

    if (!class_exists('WCSRE_Shipping_Rate_Engine')) {
        require_once 'class-wc-shipping-rate-engine.php';
    }
    $shipping_method = new WCSRE_Shipping_Rate_Engine($instance_id);

?>
    <div id="wcsre-modal">
        <div class="wcsre-modal-content">

            <div class="wcsre-modal-header">
                <h3><?php _e('Select Your Delivery Area', 'wc-shipping-rate-engine'); ?></h3>
            </div>

            <div class="wcsre-modal-body">
                <?php if ($location_rules) : ?>
                    <?php foreach ($location_rules as $rule) :
                        $label = sanitize_text_field($rule['location']);
                        $slug = method_exists($shipping_method, 'generate_location_slug') ? $shipping_method->generate_location_slug($label) : '';
                        $cost = wc_price(floatval($rule['cost']));
                    ?>
                        <label class="wcsre-radio-label">
                            <span style="display: flex; align-items: center;">
                                <input type="radio"
                                    name="wcsre_location_select"
                                    value="<?php echo esc_attr($slug); ?>"
                                    data-label="<?php echo esc_attr($label); ?>" />
                                <span style="font-weight: 500; color: #111827;"><?php echo esc_html($label); ?></span>
                            </span>
                            <span class="wcsre-price-badge">
                                <?php echo wp_kses_post($cost); ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div style="padding: 20px; text-align: center; color: #6b7280;">
                        <?php _e('No locations configured.', 'wc-shipping-rate-engine'); ?>
                    </div>
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

/**
 * 3. Handle Session Saving
 */
function wcsre_update_location_session($post_data)
{
    if (! isset($_POST['wcsre_selected_location'])) {
        return;
    }

    $selected_location = sanitize_text_field(wp_unslash($_POST['wcsre_selected_location']));

    if (WC()->session) {
        WC()->session->set('wcsre_selected_location', $selected_location);
    }

    if (method_exists(WC()->session, 'save_data')) {
        WC()->session->save_data();
    }
    wp_send_json_success(array('location' => $selected_location));
}
add_action('woocommerce_checkout_update_order_review', 'wcsre_update_location_session', 10, 1);


/**
 * 4. JavaScript (with Event Delegation Fix)
 */
function wcsre_checkout_scripts()
{
    if (is_checkout() && ! is_wc_endpoint_url()) :
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {

                // OPEN MODAL (Delegated)
                $('body').on('click', '#wcsre-open-modal', function(e) {
                    e.preventDefault();

                    var $modal = $('#wcsre-modal');
                    var $hidden_field = $('#wcsre_selected_location');
                    var $radio_options = $('input[name="wcsre_location_select"]');

                    var current_slug = $hidden_field.val();
                    $radio_options.prop('checked', false);
                    if (current_slug) {
                        $radio_options.filter('[value="' + current_slug + '"]').prop('checked', true);
                    }

                    $modal.fadeIn(200); // Fade in animation
                });

                // CLOSE ACTIONS
                $('body').on('click', '#wcsre-close-modal', function(e) {
                    e.preventDefault();
                    $('#wcsre-modal').fadeOut(200);
                });

                $(window).on('click', function(e) {
                    if (e.target.id === 'wcsre-modal') {
                        $('#wcsre-modal').fadeOut(200);
                    }
                });

                // CONFIRM SELECTION
                $('body').on('click', '#wcsre-confirm-selection', function(e) {
                    e.preventDefault();

                    var $radio_options = $('input[name="wcsre_location_select"]');
                    var selected_radio = $radio_options.filter(':checked');

                    if (selected_radio.length) {
                        var selected_slug = selected_radio.val();
                        var selected_label = selected_radio.data('label');

                        // Update input and display
                        $('#wcsre_selected_location').val(selected_slug);

                        // Close modal
                        $('#wcsre-modal').fadeOut(200);

                        // Update button text to feedback state
                        $('#wcsre-open-modal').text('Updating...');

                        // Trigger WooCommerce refresh
                        $('body').trigger('update_checkout');
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
