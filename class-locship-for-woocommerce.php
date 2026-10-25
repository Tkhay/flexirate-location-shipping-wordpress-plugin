<?php
if (! defined('ABSPATH')) {
    exit;
}

class LOCSH_FOR_WOOCOM_Shipping_Method extends WC_Shipping_Method
{

    public function __construct($instance_id = 0)
    {
        $this->id                 = 'locship_location';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('LocShip', 'locship-for-woocommerce');
        $this->method_description = __('Calculates shipping based on custom location names.', 'locship-for-woocommerce');
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
            'enabled'       => array('title' => __('Enable/Disable', 'locship-for-woocommerce'), 'type' => 'checkbox', 'label' => __('Enable', 'locship-for-woocommerce'), 'default' => 'yes'),
            'title'         => array('title' => __('Title', 'locship-for-woocommerce'), 'type' => 'text', 'default' => __('Custom Shipping Rate', 'locship-for-woocommerce')),
            'default_cost'  => array('title' => __('Default Cost', 'locship-for-woocommerce'), 'type' => 'price', 'default' => '66.00'),
            'custom_rules'  => array('title' => __('Delivery Areas', 'locship-for-woocommerce'), 'type' => 'title', 'description' => __('Manage your locations and their costs below.', 'locship-for-woocommerce')),
        );
    }

    public function generate_title_html($key, $data)
    {
        if ('custom_rules' !== $key) {
            return parent::generate_title_html($key, $data);
        }
        $this->init_settings();
        $custom_key = 'locsh_for_woocom_custom_rules_' . $this->instance_id;
        $saved_rules = get_option($custom_key, array());
        $field_key  = $this->get_field_key($key);
        ob_start();
?>
        <tr valign="top">
            <th scope="row" class="titledesc"><?php echo esc_html($data['title']); ?></th>
            <td class="forminp">
                <table class="widefat" id="locsh_for_woocom_rules_table">
                    <thead>
                        <tr>
                            <th style="width: 60%;"><?php esc_html_e('Location Name', 'locship-for-woocommerce'); ?></th>
                            <?php /* translators: %s: currency symbol, e.g. $ */ ?>
                            <th style="width: 30%;"><?php echo wp_kses_post(sprintf(esc_html__('Price (%s)', 'locship-for-woocommerce'), get_woocommerce_currency_symbol())); ?></th>
                            <th style="width: 10%;"></th>
                        </tr>
                    </thead>
                    <tbody class="locsh-for-woocom-rules-body">
                        <?php
                        $rules_to_show = (! empty($saved_rules)) ? $saved_rules : array(array('location' => '', 'cost' => ''));
                        foreach ($rules_to_show as $rule) :
                        ?>
                            <tr class="locsh_for_woocom_rule">
                                <td><input type="text" name="<?php echo esc_attr($field_key); ?>[location][]" value="<?php echo esc_attr($rule['location']); ?>" placeholder="<?php echo esc_attr__('e.g. Spintex', 'locship-for-woocommerce'); ?>" maxlength="100" required /></td>
                                <td><input type="number" name="<?php echo esc_attr($field_key); ?>[cost][]" value="<?php echo esc_attr($rule['cost']); ?>" placeholder="0.00" step="0.01" min="0" required /></td>
                                <td><a href="#" class="locsh-for-woocom-remove-rule">&times;</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button class="button button-secondary locsh_for_woocom_add_rule_button"><?php esc_html_e('Add New Area', 'locship-for-woocommerce'); ?></button>
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
                // Skip if location is empty OR cost is not set OR cost is empty
                if ('' === $loc || ! isset($posted_data['cost'][$i]) || '' === $posted_data['cost'][$i]) {
                    continue;
                }

                // Sanitize and truncate location to 100 characters
                $sanitized_location = substr(sanitize_text_field($loc), 0, 100);
                // Convert to float first to remove leading zeros, then format as decimal
                $sanitized_cost     = wc_format_decimal((float) $posted_data['cost'][$i]);

                // Only add if cost is greater than or equal to 0
                if ($sanitized_cost >= 0) {
                    $processed[] = array(
                        'location' => $sanitized_location,
                        'cost'     => $sanitized_cost,
                    );
                }
            }
        }

        update_option('locsh_for_woocom_custom_rules_' . $this->instance_id, $processed);
        return $settings;
    }

    public function calculate_shipping($package = array())
    {
        $selected_slug  = WC()->session ? (WC()->session->get('locsh_for_woocom_selected_location') ?? '') : '';
        $shipping_cost  = floatval($this->get_option('default_cost', 0));
        $rate_label     = $this->title;

        $rules = get_option('locsh_for_woocom_custom_rules_' . $this->instance_id, array());
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
