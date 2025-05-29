=== WC Quantity Multiples ===
Contributors: yourname
Donate link: https://example.com/donate/
Tags: woocommerce, quantity, multiples, bulk, wholesale
Requires at least: 5.0
Tested up to: 6.7.2
Requires PHP: 7.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Force WooCommerce product quantities to be purchased in multiples of 10 (or a custom value).

== Description ==

WC Quantity Multiples is a lightweight WooCommerce extension that forces customers to purchase products in specific quantity increments (by default, multiples of 10).

The plugin is perfect for wholesalers, bulk suppliers, or any store where products should be sold in specific quantity batches.

= Features =

* Force product quantities to be in multiples of 10 (or any number you choose)
* Apply rules to all products or only specific ones
* Configure quantity enforcement by product categories
* Automatic adjustment of invalid quantities in cart
* Clear user notifications when quantities are adjusted
* Responsive design and user-friendly interface
* Compatible with WooCommerce variable products
* Lightweight code with minimal performance impact

= How It Works =

The plugin modifies the quantity input fields on product, cart, and checkout pages to enforce the selected multiple. If a customer tries to add a non-compliant quantity to their cart, the plugin automatically rounds up to the nearest valid multiple and displays a notification.

= Stock Level Awareness =

WC Quantity Multiples is also stock-aware. If a product's stock drops below 10 units, the plugin will automatically disable the multiple enforcement for that product, allowing customers to purchase the remaining stock without restrictions.

= Developer Friendly =

The plugin provides several filters and actions to customize its behavior:

* `wcqm_product_quantity_multiple` - Modify the quantity multiple for a specific product
* `woocommerce_quantity_input_args` - Used to modify the quantity input arguments
* `woocommerce_add_to_cart_validation` - For validating cart quantities
* `woocommerce_update_cart_validation` - For validating cart updates

== Installation ==

1. Upload the `wc-quantity-multiples` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Quantity Multiples to configure settings

== Frequently Asked Questions ==

= Does this work with variable products? =

Yes, the plugin works with simple, variable, and grouped products.

= Can I set different multiples for different products? =

Currently, the plugin uses a single multiple value for all products. However, this can be customized with custom code using the `wcqm_product_quantity_multiple` filter.

= What happens if a customer tries to add a non-compliant quantity? =

The quantity is automatically adjusted to the nearest valid multiple (rounded up), and a notification is shown to the customer.

= Does this plugin work with AJAX add-to-cart? =

Yes, the plugin is fully compatible with AJAX add-to-cart functionality.

= Is it compatible with other WooCommerce extensions? =

The plugin is designed to be compatible with most WooCommerce extensions. If you encounter any compatibility issues, please report them in the support forum.

== Screenshots ==

1. Admin settings page
2. Product page with enforced quantity
3. Cart page showing quantity adjustment
4. Checkout validation

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release 