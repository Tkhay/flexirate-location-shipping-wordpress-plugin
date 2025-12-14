<?php
if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WCSRE_Shipping_Rate_Engine')) {
    class WCSRE_Shipping_Rate_Engine extends WC_Shipping_Method
    {

        public function __construct($instance_id = 0)
        {
            $this->id = 'wc_rate_engine';
            $this->instance_id = absint($instance_id);
            $this->method_title = __('WC Shipping Rate Engine', 'wc-shipping-rate-engine');
            $this->method_description = __('Calculates shipping based on custom location names.', 'wc-shipping-rate-engine');
            $this->init();
            $this->title = isset($this->settings['title']) ? $this->settings['title'] : $this->method_title;
            $this->supports = array('shipping-zones', 'instance-settings');
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
                'enabled' => array('title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable', 'default' => 'yes'),
                'title' => array('title' => 'Title', 'type' => 'text', 'default' => 'Custom Shipping Rate'),
                'default_cost' => array('title' => 'Default Cost', 'type' => 'price', 'default' => '66.00'),
                'custom_rules' => array('title' => 'Delivery Areas', 'type' => 'title', 'description' => 'Manage your locations and their costs below.'),
            );
        }

        public function generate_title_html($key, $data)
        {
            if ('custom_rules' !== $key) return parent::generate_title_html($key, $data);
            $this->init_settings();
            $custom_key = 'wcsre_custom_rules_' . $this->instance_id;
            $saved_rules = get_option($custom_key, array());
            $field_key = $this->get_field_key($key);
            ob_start();
?>
            <tr valign="top">
                <th scope="row" class="titledesc"><?php echo esc_html($data['title']); ?></th>
                <td class="forminp">
                    <table class="widefat" id="wcsre_rules_table">
                        <thead>
                            <tr>
                                <th style="width: 60%;"><?php esc_html_e('Location Name', 'wc-shipping-rate-engine'); ?></th>
                                <th style="width: 30%;"><?php esc_html_e('Price (GH₵)', 'wc-shipping-rate-engine'); ?></th>
                                <th style="width: 10%;"></th>
                            </tr>
                        </thead>
                        <tbody class="wcsre-rules-body">
                            <?php
                            $rules_to_show = (!empty($saved_rules)) ? $saved_rules : array(array('location' => '', 'cost' => ''));
                            foreach ($rules_to_show as $rule) : ?>
                                <tr class="wcsre_rule">
                                    <td><input type="text" name="<?php echo esc_attr($field_key); ?>[location][]" value="<?php echo esc_attr($rule['location']); ?>" placeholder="e.g. Spintex" /></td>
                                    <td><input type="number" name="<?php echo esc_attr($field_key); ?>[cost][]" value="<?php echo esc_attr($rule['cost']); ?>" placeholder="0.00" step="0.01" /></td>
                                    <td><a href="#" class="wcsre-remove-rule">&times;</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button class="button button-secondary wcsre_add_rule_button">Add New Area</button>
                </td>
            </tr>
<?php return ob_get_clean();
        }

        public function process_admin_options()
        {
            $settings = parent::process_admin_options();
            $location_field_key = $this->get_field_key('custom_rules');
            $data = isset($_POST[$location_field_key]) ? (array) wp_unslash($_POST[$location_field_key]) : array();
            $processed = array();
            if (!empty($data['location'])) {
                foreach ($data['location'] as $i => $loc) {
                    if (!empty($loc) && isset($data['cost'][$i])) {
                        $processed[] = array('location' => sanitize_text_field($loc), 'cost' => wc_format_decimal($data['cost'][$i]));
                    }
                }
            }
            update_option('wcsre_custom_rules_' . $this->instance_id, $processed);
            return $settings;
        }

        public function calculate_shipping($package = array())
        {
            $selected_slug = WC()->session->get('wcsre_selected_location') ?? '';
            $shipping_cost = floatval($this->get_option('default_cost', 0));
            $rate_label = $this->title;

            $rules = get_option('wcsre_custom_rules_' . $this->instance_id, array());
            if (!empty($rules)) {
                foreach ($rules as $rule) {
                    if ($selected_slug === $this->generate_location_slug($rule['location'])) {
                        $shipping_cost = floatval($rule['cost']);
                        $rate_label = $rule['location'];
                        break;
                    }
                }
            }
            $this->add_rate(array('id' => $this->get_rate_id(), 'label' => $rate_label, 'cost' => $shipping_cost));
        }
    }
}
