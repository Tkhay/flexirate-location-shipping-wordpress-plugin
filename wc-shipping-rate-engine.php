<?php

/**
 * Plugin Name: WC Shipping Rate Engine
 * Plugin URI:  https://example.com/
 * Description: Defines custom shipping zones and calculates rates based on the customer's selected City/Town name via a checkout modal.
 * Version:     1.0.7
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
 *
 * @param array $methods Existing shipping methods.
 * @return array Modified shipping methods array.
 */
function wcsre_add_custom_shipping_method($methods)
{
    // 1. Include the class file
    require 'class-wc-shipping-rate-engine.php';

    // 2. Add the class to the array
    $methods['wc_rate_engine'] = 'WCSRE_Shipping_Rate_Engine';

    return $methods;
}
add_filter('woocommerce_shipping_methods', 'wcsre_add_custom_shipping_method');


// =================================================================
// 2. CHECKOUT UI & HOOKS (BUTTON TO POPUP)
// =================================================================

/**
 * Helper to safely get the instance ID of your shipping method.
 * This global iteration is the necessary failsafe when other methods fail.
 */
function wcsre_get_first_active_instance_id()
{
    if (! class_exists('WC_Shipping_Zones')) {
        return 0;
    }

    // 1. Check all user-defined zones
    $shipping_zones = WC_Shipping_Zones::get_zones();

    foreach ($shipping_zones as $zone_id => $zone_data) {
        $methods = $zone_data['shipping_methods'];
        foreach ($methods as $method_instance) {
            if ($method_instance->id === 'wc_rate_engine' && $method_instance->instance_id > 0) {
                return $method_instance->instance_id;
            }
        }
    }

    // 2. Check the Default "Rest of the World" zone (ID 0)
    $default_zone = new WC_Shipping_Zone(0);
    $default_methods = $default_zone->get_shipping_methods();

    foreach ($default_methods as $method_instance) {
        if ($method_instance->id === 'wc_rate_engine' && $method_instance->instance_id > 0) {
            return $method_instance->instance_id;
        }
    }

    return 0;
}


/**
 * 1. Output the "Select Location" button and the hidden field.
 * CRITICAL FIX: Hooked to the order summary section for universal reliability.
 */
function wcsre_add_location_selector_ui()
{
    if (! is_checkout() || is_wc_endpoint_url()) {
        return;
    }

    $instance_id = wcsre_get_first_active_instance_id();

    // CRITICAL: Exit if no active instance is found.
    if (! $instance_id) {
        return;
    }

    // We must ensure the required class is loaded before instantiating
    if (!class_exists('WCSRE_Shipping_Rate_Engine')) {
        require 'class-wc-shipping-rate-engine.php';
    }

    // Get the previously selected slug from the session for display persistence
    $selected_slug = WC()->session->get('wcsre_selected_location') ?? '';

    $shipping_method = new WCSRE_Shipping_Rate_Engine($instance_id);
    $custom_key = 'wcsre_custom_rules_' . $instance_id;
    $location_rules = get_option($custom_key, array());

    $selected_label = '';

    if ($selected_slug) {
        foreach ($location_rules as $rule) {
            if (method_exists($shipping_method, 'generate_location_slug') && $shipping_method->generate_location_slug($rule['location']) === $selected_slug) {
                $selected_label = sanitize_text_field($rule['location']);
                break;
            }
        }
    }

    // START OUTPUT
?>
    <tr class="wcsre-location-row">
        <td colspan="2">
            <div class="wcsre-location-selector-wrap" style="padding: 10px 0;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;"><?php _e('Delivery Area Selection', 'wc-shipping-rate-engine'); ?> <span class="required">*</span></label>

                <input type="hidden" id="wcsre_selected_location" name="wcsre_selected_location" value="<?php echo esc_attr($selected_slug); ?>" />

                <button type="button" class="button alt" id="wcsre-open-modal" style="width: auto;">
                    <?php echo $selected_label ? esc_html($selected_label) : __('Select Your Area', 'wc-shipping-rate-engine'); ?>
                </button>

                <?php if ($selected_label) : ?>
                    <span id="wcsre-selected-display" style="margin-left: 10px; font-weight: bold;"><?php echo esc_html($selected_label); ?></span>
                <?php endif; ?>
            </div>
            <?php wcsre_output_location_modal(); // Output the modal structure 
            ?>
        </td>
    </tr>
<?php
    // END OUTPUT
}
// Use the universal hook inside the order review table (Order Summary)
add_action('woocommerce_review_order_after_shipping', 'wcsre_add_location_selector_ui', 10);


/**
 * 2. Output the Modal (Pop-up) HTML structure.
 */
function wcsre_output_location_modal()
{
    $instance_id = wcsre_get_first_active_instance_id();
    if (! $instance_id) {
        return;
    }
    $custom_key = 'wcsre_custom_rules_' . $instance_id;
    $location_rules = get_option($custom_key, array());

    // Get an instance of the class to use the public slug generator
    if (!class_exists('WCSRE_Shipping_Rate_Engine')) {
        require 'class-wc-shipping-rate-engine.php';
    }
    $shipping_method = new WCSRE_Shipping_Rate_Engine($instance_id);

?>
    <div id="wcsre-modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 8px;">
            <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 0;"><?php _e('Select Your Delivery Area', 'wc-shipping-rate-engine'); ?></h3>
            <div id="wcsre-radio-options" style="max-height: 400px; overflow-y: auto; padding-right: 15px;">
                <?php if ($location_rules) : ?>
                    <?php foreach ($location_rules as $rule) :
                        $label = sanitize_text_field($rule['location']);
                        $slug = method_exists($shipping_method, 'generate_location_slug') ? $shipping_method->generate_location_slug($label) : '';
                        $cost = wc_price(floatval($rule['cost']));
                    ?>
                        <label style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px dashed #eee;">
                            <span style="font-weight: 500;">
                                <input type="radio" name="wcsre_location_select" value="<?php echo esc_attr($slug); ?>" data-label="<?php echo esc_attr($label); ?>" style="margin-right: 10px;" />
                                <?php echo esc_html($label); ?>
                            </span>
                            <span style="font-weight: bold; color: #0073aa;"><?php echo esc_html($cost); ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p><?php _e('No custom locations are defined. Please configure them in WooCommerce Shipping settings.', 'wc-shipping-rate-engine'); ?></p>
                <?php endif; ?>
            </div>
            <div style="margin-top: 20px; text-align: right;">
                <button type="button" class="button" id="wcsre-close-modal" style="margin-right: 10px;"><?php _e('Cancel', 'wc-shipping-rate-engine'); ?></button>
                <button type="button" class="button button-primary" id="wcsre-confirm-selection"><?php _e('Confirm Selection', 'wc-shipping-rate-engine'); ?></button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 3. AJAX handler to save the selected location slug to the WooCommerce session.
 */
function wcsre_update_location_session($post_data)
{
    // Check if the custom location field is present in the POST data
    if (! isset($_POST['wcsre_selected_location'])) {
        return;
    }

    $selected_location = sanitize_text_field(wp_unslash($_POST['wcsre_selected_location']));

    if (WC()->session) {
        // Save the selection to the session (this is what the calculate_shipping function reads)
        WC()->session->set('wcsre_selected_location', $selected_location);
    }

    // We explicitly call the save method here to ensure the session is updated immediately
    if (method_exists(WC()->session, 'save_data')) {
        WC()->session->save_data();
    }

    // Returning success, but WooCommerce's update_checkout handles the actual rate calculation.
    wp_send_json_success(array('location' => $selected_location));
}
// Hook into WooCommerce's standard checkout update process
add_action('woocommerce_checkout_update_order_review', 'wcsre_update_location_session', 10, 1);


/**
 * 4. Add the JavaScript to manage the modal and trigger shipping update.
 */
function wcsre_checkout_scripts()
{
    if (is_checkout() && ! is_wc_endpoint_url()) :
    ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var $modal = $('#wcsre-modal');
                var $hidden_field = $('#wcsre_selected_location');
                var $display_button = $('#wcsre-open-modal');
                var $radio_options = $('input[name="wcsre_location_select"]');
                var $selected_display = $('#wcsre-selected-display');

                // --- Modal Visibility ---
                $display_button.on('click', function(e) {
                    e.preventDefault();
                    // Ensure the currently selected radio button is checked when opening
                    var current_slug = $hidden_field.val();
                    $radio_options.prop('checked', false);
                    if (current_slug) {
                        $radio_options.filter('[value="' + current_slug + '"]').prop('checked', true);
                    }
                    $modal.show();
                });

                $('#wcsre-close-modal').on('click', function() {
                    $modal.hide();
                });

                // Close modal when clicking outside
                $modal.on('click', function(e) {
                    if (e.target.id === 'wcsre-modal') {
                        $modal.hide();
                    }
                });

                // --- Selection and Shipping Update ---
                $('#wcsre-confirm-selection').on('click', function() {
                    var selected_radio = $radio_options.filter(':checked');

                    if (selected_radio.length) {
                        var selected_slug = selected_radio.val();
                        var selected_label = selected_radio.data('label');

                        // 1. Update the hidden input field
                        $hidden_field.val(selected_slug);

                        // 2. Update the button text/label 
                        $display_button.text('<?php echo esc_js(__('Change Area', 'wc-shipping-rate-engine')); ?>');

                        // 3. Update the display span next to the button
                        if ($selected_display.length === 0) {
                            $display_button.after('<span id="wcsre-selected-display" style="margin-left: 10px; font-weight: bold;"></span>');
                            $selected_display = $('#wcsre-selected-display');
                        }
                        $selected_display.text(selected_label);

                        // 4. Hide the modal
                        $modal.hide();

                        // 5. Trigger the WooCommerce checkout refresh (CRITICAL STEP)
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
