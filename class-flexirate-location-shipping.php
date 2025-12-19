<?php
if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('FRLS_Shipping_Method')) {
    class FRLS_Shipping_Method extends WC_Shipping_Method
    {

        public function __construct($instance_id = 0)
        {
            $this->id                 = 'flexirate_location';
            $this->instance_id        = absint($instance_id);
            $this->method_title       = __('FlexiRate Location Shipping', 'flexirate-location-shipping');
            $this->method_description = __('Calculates shipping based on custom location names.', 'flexirate-location-shipping');
            $this->init();
            $this->title             = isset($this->settings['title']) ? $this->settings['title'] : $this->method_title;
            $this->supports          = array('shipping-zones', 'instance-settings');
        }

        public function init()
        {
            $this->init_form_fields();
            $this->init_settings();
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        public function generate_location_slug($location_name)
        {
            return sanitize_title(str_replace(array(',', ' ', '/'), '_', $location_name));
        }

        public function init_form_fields()
        {
            $this->instance_form_fields = array(
                'enabled'       => array('title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable', 'default' => 'yes'),
                'title'         => array('title' => 'Title', 'type' => 'text', 'default' => 'Custom Shipping Rate'),
                'default_cost'  => array('title' => 'Default Cost', 'type' => 'price', 'default' => '66.00'),
                'custom_rules'  => array('title' => 'Delivery Areas', 'type' => 'title', 'description' => 'Manage your locations and their costs below.'),
            );
        }

        public function generate_title_html($key, $data)
        {
            if ('custom_rules' !== $key) {
                return parent::generate_title_html($key, $data);
            }
            $this->init_settings();
            $custom_key = 'frls_custom_rules_' . $this->instance_id;
            $saved_rules = get_option($custom_key, array());
            $field_key  = $this->get_field_key($key);
            ob_start();
?>
            <tr valign="top">
                <th scope="row" class="titledesc"><?php echo esc_html($data['title']); ?></th>
                <td class="forminp">
                    <table class="widefat" id="frls_rules_table">
                        <thead>
                            <tr>
                                <th style="width: 60%;"><?php esc_html_e('Location Name', 'flexirate-location-shipping'); ?></th>
                                <th style="width: 30%;"><?php esc_html_e('Price (GH₵)', 'flexirate-location-shipping'); ?></th>
                                <th style="width: 10%;"></th>
                            </tr>
                        </thead>
                        <tbody class="frls-rules-body">
                            <?php
                            $rules_to_show = (! empty($saved_rules)) ? $saved_rules : array(array('location' => '', 'cost' => ''));
                            foreach ($rules_to_show as $rule) :
                            ?>
                                <tr class="frls_rule">
                                    <td><input type="text" name="<?php echo esc_attr($field_key); ?>[location][]" value="<?php echo esc_attr($rule['location']); ?>" placeholder="e.g. Spintex" /></td>
                                    <td><input type="number" name="<?php echo esc_attr($field_key); ?>[cost][]" value="<?php echo esc_attr($rule['cost']); ?>" placeholder="0.00" step="0.01" /></td>
                                    <td><a href="#" class="frls-remove-rule">&times;</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button class="button button-secondary frls_add_rule_button">Add New Area</button>
                </td>
            </tr>
<?php return ob_get_clean();
        }

        public function process_admin_options()
        {
            $settings = parent::process_admin_options();

            $location_field_key = $this->get_field_key('custom_rules');

            /*
			 * Retrieve posted data with filter_input. Requesting an array via FILTER_REQUIRE_ARRAY
			 * avoids direct indexing of $_POST (the pattern the WordPress plugin checker flags).
			 * If filter_input doesn't return an array (e.g. returns null on some SAPIs), treat as empty.
			 */
            $posted_data = filter_input(INPUT_POST, $location_field_key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            if (! is_array($posted_data)) {
                $posted_data = array();
            }

            $processed = array();

            if (! empty($posted_data['location']) && is_array($posted_data['location'])) {
                foreach ($posted_data['location'] as $i => $loc) {
                    if ('' === $loc || ! isset($posted_data['cost'][$i])) {
                        continue;
                    }

                    $sanitized_location = sanitize_text_field($loc);
                    $sanitized_cost     = wc_format_decimal($posted_data['cost'][$i]);

                    $processed[] = array(
                        'location' => $sanitized_location,
                        'cost'     => $sanitized_cost,
                    );
                }
            }

            update_option('frls_custom_rules_' . $this->instance_id, $processed);
            return $settings;
        }

        public function calculate_shipping($package = array())
        {
            $selected_slug  = WC()->session->get('frls_selected_location') ?? '';
            $shipping_cost  = floatval($this->get_option('default_cost', 0));
            $rate_label     = $this->title;

            $rules = get_option('frls_custom_rules_' . $this->instance_id, array());
            if (! empty($rules)) {
                foreach ($rules as $rule) {
                    if ($selected_slug === $this->generate_location_slug($rule['location'])) {
                        $shipping_cost = floatval($rule['cost']);
                        $rate_label    = $rule['location'];
                        break;
                    }
                }
            }
            $this->add_rate(
                array(
                    'id'    => $this->get_rate_id(),
                    'label' => $rate_label,
                    'cost'  => $shipping_cost,
                )
            );
        }
    }
}
