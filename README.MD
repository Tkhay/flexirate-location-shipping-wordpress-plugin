# FlexiRate Location Shipping for WooCommerce

A flexible WooCommerce shipping extension that enables location-based shipping rates using custom delivery areas with an elegant checkout modal interface.

![WordPress Plugin Version](https://img.shields.io/badge/version-1.0.0-blue)
![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue)
![WooCommerce](https://img.shields.io/badge/WooCommerce-9.0%2B-purple)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)
![License](https://img.shields.io/badge/license-GPLv2%2B-green)

## 🎯 Overview

Perfect for businesses that need granular control over delivery zones and pricing beyond standard postal codes. Define custom areas (like neighborhoods, districts, or regions) and set individual shipping costs for each location.

## ✨ Key Features

- **Custom Delivery Areas** - Unlimited shipping zones with custom names
- **Individual Pricing** - Specific shipping costs per area
- **Elegant Modal Interface** - User-friendly location selector at checkout
- **Real-Time Updates** - Instant shipping cost calculations
- **HPOS Compatible** - Works with WooCommerce High-Performance Order Storage
- **Multi-Zone Support** - Use across multiple WooCommerce shipping zones
- **Checkout Validation** - Ensures delivery area selection before order completion

## 📋 Requirements

- WordPress 6.9 or higher
- WooCommerce 9.0 or higher
- PHP 7.4 or higher
- Classic Checkout (not Block-based Checkout)

## 🚀 Installation

### For End Users

Download from the [WordPress Plugin Directory](https://wordpress.org/plugins/) (coming soon) or install directly from your WordPress admin panel.

See [readme.txt](readme.txt) for detailed installation instructions.

### For Developers

```bash
# Clone the repository
git clone https://github.com/yourusername/flexirate-location-shipping.git

# Navigate to your WordPress plugins directory
cd wp-content/plugins/

# Move or symlink the plugin
mv /path/to/flexirate-location-shipping ./

# Activate via WP-CLI (optional)
wp plugin activate flexirate-location-shipping
```

## ⚙️ Configuration

1. Navigate to **WooCommerce → Settings → Shipping**
2. Select or create a shipping zone
3. Add **FlexiRate Location Shipping for WooCommerce** as a shipping method
4. Configure delivery areas and their costs
5. Save changes

## 🔧 Development Setup

```bash
# Install dependencies (if any)
composer install  # If using Composer

# Watch for changes during development
# Add your build commands here if applicable
```

## 📁 Project Structure

```
flexirate-location-shipping/
├── assets/
│   ├── css/           # Stylesheets
│   └── js/            # JavaScript files
├── includes/          # Core plugin files
├── languages/         # Translation files
├── flexirate-location-shipping.php  # Main plugin file
├── readme.txt         # WordPress.org readme
└── README.md          # This file
```

## 🐛 Known Issues

- **Block Checkout Compatibility**: Currently requires Classic Checkout (`[woocommerce_checkout]` shortcode). Block-based checkout support is planned for a future release.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 Changelog

See [readme.txt](readme.txt) for detailed changelog.

## 📄 License

This project is licensed under the GPLv2 or later - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

## 💬 Support

- **Email**: hello@tiekuasare.com
- **Website**: [tiekuasare.com](https://www.tiekuasare.com/)
- **Buy Me a Coffee**: [Support the project](https://buymeacoffee.com/tiekuasare)

## 👨‍💻 Author

**onetkhay**

---

For end-user documentation, FAQs, and WordPress.org specific information, please refer to [readme.txt](readme.txt).