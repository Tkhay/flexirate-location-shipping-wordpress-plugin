=== WC Shipping Rate Engine ===
Contributors: onetkhay
Donate link: https://buymeacoffee.com/tiekuasare
Tags: woocommerce, shipping, custom shipping, location-based shipping
Requires at least: 6.8
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
WC requires at least: 9.0
WC tested up to: 10.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom WooCommerce shipping method that calculates rates based on customer-selected delivery areas via an elegant checkout modal.

== Description ==

WC Shipping Rate Engine is a powerful and flexible WooCommerce shipping extension that allows you to create location-based shipping rates using custom area names instead of relying on standard address fields.

Perfect for businesses that need granular control over delivery zones and pricing, this plugin provides:

* **Custom Delivery Areas** - Define unlimited shipping zones with custom names (e.g., "Spintex", "East Legon", "Tema")
* **Individual Pricing** - Set specific shipping costs for each delivery area
* **User-Friendly Modal** - Beautiful modal interface for customers to select their delivery location at checkout
* **Real-Time Updates** - Shipping costs update instantly when customers select their area
* **Multi-Zone Support** - Works seamlessly with WooCommerce shipping zones
* **HPOS Compatible** - Fully compatible with WooCommerce High-Performance Order Storage
* **Validation** - Ensures customers select a delivery area before completing checkout

= How It Works =

1. Admin configures delivery areas and their corresponding shipping costs in WooCommerce Settings
2. During checkout, customers click "Select Your Area" to open an elegant modal
3. Customers choose their delivery location from the list of available areas
4. Shipping cost updates automatically based on the selected area
5. Order proceeds with the correct shipping rate applied

= Perfect For =

* Local delivery businesses
* Regional e-commerce stores
* Businesses with non-standard delivery zones
* Companies operating in areas where postal codes don't accurately reflect delivery costs
* Any store needing custom location-based shipping logic

= Technical Features =

* Fully integrated with WooCommerce shipping zones
* AJAX-powered for smooth user experience
* Session-based location storage
* Automatic shipping cache clearing
* Responsive design works on all devices
* Translation ready with text domain support

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins > Add New
3. Search for "WC Shipping Rate Engine"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins > Add New > Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Activate the plugin

= Configuration =

1. Go to WooCommerce > Settings > Shipping
2. Select a shipping zone or create a new one
3. Click "Add shipping method" and choose "WC Shipping Rate Engine"
4. Configure your default cost and add delivery areas with their respective prices
5. Click "Save changes"

== Frequently Asked Questions ==

= How do I add delivery areas? =

Navigate to WooCommerce > Settings > Shipping, select your shipping zone, click on the WC Shipping Rate Engine method, and use the "Delivery Areas" table to add locations and their costs. Click "Add New Area" to add more rows.

= Can I have different delivery areas for different shipping zones? =

Yes! You can add the WC Shipping Rate Engine method to multiple shipping zones, and each zone can have its own unique set of delivery areas and pricing.

= What happens if a customer doesn't select a delivery area? =

The plugin includes validation that prevents checkout completion until a delivery area is selected. Customers will see an error message prompting them to choose their area.

= Can I customize the modal appearance? =

Yes, the plugin includes CSS files in the assets/css directory that you can customize. You can also add custom CSS through your theme or a custom CSS plugin.

= Is this plugin compatible with HPOS? =

Yes, the plugin is fully compatible with WooCommerce High-Performance Order Storage (Custom Order Tables).

= Does this work with other shipping methods? =

Yes, WC Shipping Rate Engine works alongside other WooCommerce shipping methods. Customers will see all available shipping options at checkout.

= Can I set a default shipping cost? =

Yes, you can set a default cost in the shipping method settings. This will be used if no specific delivery area is selected (though the plugin requires selection at checkout).

= Why is the "Select Your Area" button not showing? =
If you are using a modern Block Theme (like Twenty Twenty-Four), your checkout page might be using the "Checkout Block" instead of the Classic Checkout. This plugin requires the Classic Checkout engine to function.

To fix this:
1. Go to **Pages > Checkout** and edit the page.
2. Delete the **Checkout Block**.
3. Add a **Shortcode** block.
4. Paste this shortcode: `[woocommerce_checkout]`
5. Update the page. The button will now appear.

= Is the plugin translation ready? =

Yes, the plugin uses WordPress localization functions and includes a text domain for easy translation.

== Screenshots ==

1. Admin settings page showing delivery areas configuration
2. Checkout page with "Select Your Area" button
3. Modal displaying available delivery areas with prices
4. Selected delivery area confirmation on checkout

== Changelog ==

= 1.0.0 =
* Initial release
* Custom delivery area management
* Location selection modal at checkout
* Individual pricing per delivery area
* HPOS compatibility
* Multi-zone support
* Automatic shipping cache clearing
* Checkout validation
* Responsive design
* Translation ready

== Upgrade Notice ==

= 1.0.0 =
Initial release of WC Shipping Rate Engine. Install to start using custom location-based shipping rates in your WooCommerce store.

== Additional Information ==

= Developer Notes =

The plugin stores delivery area selections in WooCommerce sessions and includes AJAX handlers for real-time updates. Custom rules are stored per shipping method instance, allowing for flexible multi-zone configurations.

= Support =

For support, feature requests, or bug reports, please visit [https://www.tiekuasare.com/](https://www.tiekuasare.com/)

= Privacy Policy =

This plugin does not collect, store, or transmit any personal data beyond what is already collected by WooCommerce during the normal checkout process.