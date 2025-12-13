<?php

/**
 * WC Shipping Rate Engine Class
 *
 * @package WC_Shipping_Rate_Engine
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if the class already exists before declaring it (Safety Fix)
if (! class_exists('WCSRE_Shipping_Rate_Engine')) {

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
            $this->method_description = __('Calculates shipping based on custom location names (City/Town) defined via the admin interface.', 'wc-shipping-rate-engine');

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

            // Save settings on admin save (We override this with process_admin_options)
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Helper function to generate a unique, clean slug for a location.
         * CRITICAL FIX: Made public so it can be called from the global scope (wc-shipping-rate-engine.php).
         * @param string $location_name The location name string.
         * @return string
         */
        public function generate_location_slug($location_name)
        {
            // Uses sanitize_title to create a URL-friendly slug, which works perfectly for a unique key.
            return sanitize_title(str_replace(array(',', ' ', '/'), '_', $location_name));
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
                    'desc_tip' => false,
                ),
                'title' => array(
                    'title'       => __('Method Title', 'wc-shipping-rate-engine'),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'wc-shipping-rate-engine'),
                    'default'     => __('Custom Shipping Rate', 'wc-shipping-rate-engine'),
                    'desc_tip'    => true,
                ),
                // Default Shipping Field (Fallback Cost)
                'default_cost' => array(
                    'title'       => __('Default Shipping Cost', 'wc-shipping-rate-engine'),
                    'type'        => 'price', // WooCommerce type for price input
                    'description' => __('This is the cost applied when the customer does not select an area, or if no area matches the custom rules (your "Outside Accra" rate).', 'wc-shipping-rate-engine'),
                    'default'     => '66.00',
                    'css'         => 'width: 80px;',
                    'desc_tip'    => false,
                ),
                // Custom field type for the repeatable rules interface
                'custom_rules' => array(
                    'title' => __('Other Shipping Areas', 'wc-shipping-rate-engine'),
                    'type'  => 'title', // CRITICAL FIX: Use 'title' type to skip validation
                    'desc_tip' => false,
                ),
            );
        }

        /**
         * CRITICAL FIX: Overrides the parent generate_title_html method to render custom fields.
         */
        public function generate_title_html($key, $data)
        {

            // If the key is NOT our custom field, use the default WooCommerce title generator.
            if ('custom_rules' !== $key) {
                // Call the parent method for standard titles.
                return parent::generate_title_html($key, $data);
            }

            // --- START OF YOUR CUSTOM REPEATER CODE ---

            // Fix for persistence: Ensure instance settings are loaded before accessing custom data
            $this->init_settings();

            // CRITICAL FIX: Load data from our new, reliable custom option key
            $custom_key = 'wcsre_custom_rules_' . $this->instance_id;
            $saved_rules = get_option($custom_key, array());

            $field_key = $this->get_field_key($key);

            ob_start();

?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                </th>
                <td class="forminp">
                    <div id="wcsre_custom_rules_container">

                        <p class="description">
                            <?php esc_html_e('Define specific locations (e.g., "Spintex, Sakumono") and their corresponding cost. These will appear as radio options on the checkout page.', 'wc-shipping-rate-engine'); ?>
                        </p>

                        <table class="widefat wc_input_table sortable" id="wcsre_rules_table" cellspacing="0">
                            <thead>
                                <tr>
                                    <th class="sort">&nbsp;</th>
                                    <th><?php esc_html_e('Location Name(s) (Comma Separated)', 'wc-shipping-rate-engine'); ?></th>
                                    <th><?php esc_html_e('Cost (GH₵)', 'wc-shipping-rate-engine'); ?></th>
                                    <th>&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody class="wcsre-rules-body">
                                <?php
                                $rule_count = 0;
                                if (! empty($saved_rules) && is_array($saved_rules)) :
                                    foreach ($saved_rules as $rule) :
                                        if (! isset($rule['location'], $rule['cost'])) continue;
                                        $rule_count++;
                                ?>
                                        <tr class="wcsre_rule">
                                            <td class="sort"></td>
                                            <td>
                                                <input type="text"
                                                    name="<?php echo esc_attr($field_key); ?>[location][]"
                                                    value="<?php echo esc_attr($rule['location']); ?>"
                                                    placeholder="<?php esc_attr_e('Accra, Tema', 'wc-shipping-rate-engine'); ?>"
                                                    class="input-text regular-input" />
                                            </td>
                                            <td>
                                                <input type="number"
                                                    name="<?php echo esc_attr($field_key); ?>[cost][]"
                                                    value="<?php echo esc_attr(wc_format_localized_price($rule['cost'])); ?>"
                                                    placeholder="0.00"
                                                    step="0.01"
                                                    class="wc_input_price" />
                                            </td>
                                            <td>
                                                <a href="#" class="wcsre-remove-rule" style="color: #a00; text-decoration: none;">&times;</a>
                                            </td>
                                        </tr>
                                    <?php
                                    endforeach;
                                endif;
                                // Always show one empty row if no rules exist
                                if ($rule_count === 0) :
                                    ?>
                                    <tr class="wcsre_rule">
                                        <td class="sort"></td>
                                        <td>
                                            <input type="text"
                                                name="<?php echo esc_attr($field_key); ?>[location][]"
                                                value=""
                                                placeholder="<?php esc_attr_e('Accra, Tema', 'wc-shipping-rate-engine'); ?>"
                                                class="input-text regular-input" />
                                        </td>
                                        <td>
                                            <input type="number"
                                                name="<?php echo esc_attr($field_key); ?>[cost][]"
                                                value=""
                                                placeholder="0.00"
                                                step="0.01"
                                                class="wc_input_price" />
                                        </td>
                                        <td>
                                            <a href="#" class="wcsre-remove-rule" style="color: #a00; text-decoration: none;">&times;</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <button type="button" class="button button-secondary wcsre_add_rule_button" data-key="wcsre_rules_table"><span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Add More', 'wc-shipping-rate-engine'); ?></button>

                    </div>
                    <?php echo $this->get_description_html($data); ?>
                </td>
            </tr>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('.wcsre_add_rule_button').off('click').on('click', function(e) {
                        e.preventDefault();

                        var table_id = $(this).data('key');
                        var $table = $('#' + table_id);
                        var $tbody = $table.find('.wcsre-rules-body');
                        var field_name = $table.closest('td').find('input:first').attr('name').replace(/\[\w+\]\[\]/, '');

                        var new_row = '<tr class="wcsre_rule">';
                        new_row += '<td class="sort"></td>';
                        new_row += '<td><input type="text" name="' + field_name + '[location][]" value="" placeholder="City, Town, Suburb" class="input-text regular-input" /></td>';
                        new_row += '<td><input type="number" name="' + field_name + '[cost][]" value="" placeholder="0.00" step="0.01" class="wc_input_price" /></td>';
                        new_row += '<td><a href="#" class="wcsre-remove-rule" style="color: #a00; text-decoration: none;">&times;</a></td>';
                        new_row += '</tr>';

                        $tbody.append(new_row);
                        $table.trigger('refresh');
                    });

                    $('.wcsre-rules-body').off('click', '.wcsre-remove-rule').on('click', '.wcsre-remove-rule', function(e) {
                        e.preventDefault();
                        $(this).closest('tr').remove();
                    });
                });
            </script>
<?php

            return ob_get_clean();
        } // End generate_title_html (Custom override)


        /**
         * Override the default options processing to handle the custom repeatable fields.
         */
        public function process_admin_options()
        {

            // 1. Let the parent class handle the standard fields (enabled, title, default_cost, etc.)
            $settings = parent::process_admin_options();

            // --- LOCATION RULES PROCESSING ---

            $location_field_key = $this->get_field_key('custom_rules');
            $custom_rules_data = isset($_POST[$location_field_key]) ? (array) wp_unslash($_POST[$location_field_key]) : array();

            $processed_location_rules = array();

            if (! empty($custom_rules_data['location'])) {
                $locations = $custom_rules_data['location'];
                $costs     = $custom_rules_data['cost'];

                foreach ($locations as $i => $location_string) {
                    $cost_raw = isset($costs[$i]) ? $costs[$i] : '';

                    if (! empty($location_string) && is_numeric(wc_clean($cost_raw))) {
                        $processed_location_rules[] = array(
                            'location' => sanitize_text_field($location_string),
                            'cost'     => wc_format_decimal($cost_raw),
                        );
                    }
                }
            }

            // CRITICAL PERSISTENCE FIX: Save the location rules array to a dedicated, simplified option key
            $location_custom_key = 'wcsre_custom_rules_' . $this->instance_id;
            update_option($location_custom_key, $processed_location_rules, 'yes');

            return $settings;
        }

        /**
         * The core function that calculates the shipping rate based on a selected location slug.
         */
        public function calculate_shipping($package = array())
        {

            // 1. Get the selected location slug from the custom session variable
            // FIXED: Using Null Coalescing Operator (??) to prevent PHP warning.
            $selected_location_slug = WC()->session->get('wcsre_selected_location') ?? '';

            $default_cost     = floatval($this->get_option('default_cost', 0.00));
            $shipping_cost    = $default_cost;
            $rate_label       = $this->title;


            // 2. Load the structured location rules
            $location_custom_key = 'wcsre_custom_rules_' . $this->instance_id;
            $location_rules = get_option($location_custom_key, array());

            $match_found = false;

            // 3. Loop through rules to find a match based on the slug
            if (! empty($location_rules) && is_array($location_rules)) {

                foreach ($location_rules as $rule) {

                    if (! isset($rule['location'], $rule['cost'])) {
                        continue;
                    }

                    // Generate the slug for the current rule for matching
                    $rule_slug = $this->generate_location_slug($rule['location']);

                    // Check for a match against the customer's SELECTED slug
                    if ($selected_location_slug === $rule_slug) {
                        $shipping_cost = floatval($rule['cost']);
                        $rate_label = sanitize_text_field($rule['location']);
                        $match_found = true;
                        break;
                    }
                }
            }

            // 4. Add the calculated or default rate to WooCommerce
            $this->add_rate(array(
                'id'    => $this->get_rate_id(),
                'label' => $rate_label,
                'cost'  => $shipping_cost,
            ));
        }
    }
}
