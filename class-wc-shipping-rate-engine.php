<?php

/**
 * WC Shipping Rate Engine Class
 *
 * @package WC_Shipping_Rate_Engine
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * WCSRE_Shipping_Rate_Engine class.
 * Defines the shipping method and calculates the rate based on City/Location Name.
 */
class WCSRE_Shipping_Rate_Engine extends WC_Shipping_Method
{

    /**
     * Constructor for your shipping class
     */
    public function __construct($instance_id = 0)
    {
        $this->id                 = 'wc_rate_engine';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('WC Shipping Rate Engine', 'wc-shipping-rate-engine');
        $this->method_description = __('Calculates shipping based on custom location names (City/Town) defined in JSON.', 'wc-shipping-rate-engine');

        $this->init();

        $this->title              = isset($this->settings['title']) ? $this->settings['title'] : $this->method_title;
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
        );
    }

    /**
     * Initializes the settings fields and hooks.
     */
    public function init()
    {
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        // Save settings on admin save
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Defines the fields the store owner can use to configure the method.
     */
    public function init_form_fields()
    {
        $this->instance_form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'wc-shipping-rate-engine'),
                'type'    => 'checkbox',
                'label'   => __('Enable WC Shipping Rate Engine', 'wc-shipping-rate-engine'),
                'default' => 'yes',
            ),
            'title' => array(
                'title'       => __('Method Title', 'wc-shipping-rate-engine'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wc-shipping-rate-engine'),
                'default'     => __('Custom Shipping Rate', 'wc-shipping-rate-engine'),
                'desc_tip'    => true,
            ),
            'rules_json' => array(
                'title'       => __('Shipping Zone Rules (JSON)', 'wc-shipping-rate-engine'),
                'type'        => 'textarea',
                'description' => __('Define your custom zones and flat rate prices here in JSON format. Rules are matched by the City/Town entered by the customer. Use commas to separate multiple city names (e.g., Accra, Tema). Use "*" for a fallback zone.', 'wc-shipping-rate-engine'),
                'default'     => '[]',
                'custom_attributes' => array(
                    'rows' => 15,
                    'cols' => 60,
                ),
            ),
        );
    }

    /**
     * The core function that calculates the shipping rate based on City/Location name.
     *
     * @param array $package The package (cart contents) being shipped.
     */
    public function calculate_shipping($package = array())
    {

        // 1. Get the customer's destination City/Location Name
        $destination_city = $package['destination']['city'];

        // If no city is set, we can't calculate, so return.
        if (empty($destination_city)) {
            return;
        }

        // Clean the input city for case-insensitive matching (lowercase and trim whitespace)
        $clean_destination_city = strtolower(trim($destination_city));

        // 2. Load and decode the rules from settings
        $rules_json = $this->get_option('rules_json', '[]');
        $rules = json_decode($rules_json, true);

        if (! is_array($rules) || empty($rules)) {
            return; // No valid rules defined
        }

        $found_rate = false;
        $shipping_cost = 0.00;
        $rate_label = $this->title;

        // 3. Loop through rules to find a match
        foreach ($rules as $rule) {

            // Basic validation
            if (! isset($rule['location_names'], $rule['cost'])) {
                continue;
            }

            // Clean and prepare the list of locations for the current rule
            $rule_locations = array_map('strtolower', array_map('trim', explode(',', $rule['location_names'])));

            // Check for a wildcard match (all other areas)
            if (in_array('*', $rule_locations)) {
                $shipping_cost = floatval($rule['cost']);
                $rate_label = isset($rule['zone_name']) ? sanitize_text_field($rule['zone_name']) : $this->title;
                $found_rate = true;
                break; // Wildcard found, stop checking
            }

            // Check for a match against the customer's city
            if (in_array($clean_destination_city, $rule_locations)) {
                $shipping_cost = floatval($rule['cost']);
                $rate_label = isset($rule['zone_name']) ? sanitize_text_field($rule['zone_name']) : $this->title;
                $found_rate = true;
                break; // Match found, stop checking
            }
        }

        // 4. If a rate was found, add it to WooCommerce
        if ($found_rate) {
            $rate = array(
                'id'    => $this->get_rate_id(),
                'label' => $rate_label,
                'cost'  => $shipping_cost,
            );
            $this->add_rate($rate);
        }
    }
}
