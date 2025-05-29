=== WC Quantity Multiples ===
Contributors: yourname
Tags: woocommerce, quantity, multiples, increments, bulk ordering, wholesale
Requires at least: 5.4
Tested up to: 6.7.2
Requires PHP: 7.4
WC requires at least: 4.0.0
WC tested up to: 9.7.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Force WooCommerce product quantities to be purchased in specific multiples.

== Description ==

WC Quantity Multiples allows you to enforce specific quantity increments for your WooCommerce products. Instead of allowing customers to purchase any quantity, you can restrict them to buying in multiples of 3, 5, 10, or any other number you choose.

This plugin is perfect for wholesale stores, bulk product sellers, or any shop that needs to sell products in specific quantity increments.

Features:

* Set a default quantity multiple for all products (default is 10)
* Create product-specific quantity multiple rules
* Create category-specific quantity multiple rules
* Product rules override category rules when both exist
* Automatic quantity adjustment to the nearest valid multiple
* Clear messaging for customers
* Smart stock level awareness (rules only apply when stock exceeds 10 units)
* Performance optimized for stores with large product catalogs
* Compatible with all product types (simple, variable, grouped)
* Works with AJAX cart updates and mini-cart

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wc-quantity-multiples` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce > Quantity Multiples to configure the default multiple and create rules.

== Frequently Asked Questions ==

= How do I set a default quantity multiple? =

Go to WooCommerce > Quantity Multiples and enter your desired default multiple in the provided field. This will apply to all products that don't have specific rules.

= Can I set different multiples for different products? =

Yes, you can create product-specific rules in the plugin settings. You can also create category rules that apply to all products in a specific category.

= What happens if a customer tries to add a product with an invalid quantity? =

The quantity will automatically be adjusted to the nearest valid multiple, and a message will be displayed to inform the customer of the change.

= Do product-specific rules override category rules? =

Yes, if both a product-specific rule and a category rule apply to a product, the product-specific rule takes precedence.

= What happens when a product has low stock? =

When a product's stock level falls below 10 units, the quantity multiple rule is automatically disabled for that product, allowing customers to purchase any available quantity.

= How does the plugin handle products in multiple categories with different rules? =

For products that belong to multiple categories with different rules, the plugin applies the rule with the smallest multiple value to make it easier for customers to purchase.

= Is this plugin compatible with variable products? =

Yes, the plugin works with all product types, including simple, variable, and grouped products.

= Does this plugin slow down my store? =

No, the plugin is optimized for performance with efficient caching and minimal database queries. It's designed to work well even with large product catalogs of 1000+ products.

== Screenshots ==

1. Admin settings page for managing quantity multiples.
2. Product page with quantity multiple notice and adjusted input field.
3. Cart page with quantities enforced according to the rules.

== Changelog ==

= 1.0.0 =
* Initial release with support for product and category-based quantity rules
* Smart stock level awareness for low stock items
* Performance optimized for large product catalogs
* Full compatibility with WooCommerce 4.0.0 - 9.7.1

== Upgrade Notice ==

= 1.0.0 =
Initial release of WC Quantity Multiples. Enforces product quantities to be purchased in specific multiples, with smart stock handling and performance optimization. 