<?php
/**
 * Plugin Name: WC Quantity Multiples
 * Plugin URI: https://example.com/wc-quantity-multiples
 * Description: Forces users to select product quantities in multiples of 10
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: wc-quantity-multiples
 * Domain Path: /languages
 * WC requires at least: 3.0.0
 * WC tested up to: 9.7.1
 *
 * @package WC_Quantity_Multiples
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WCQM_VERSION', '1.0.0');
define('WCQM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCQM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCQM_PLUGIN_FILE', __FILE__);

/**
 * Check if WooCommerce is active
 */
function wcqm_is_woocommerce_active() {
    if (!function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    return is_plugin_active('woocommerce/woocommerce.php');
}

/**
 * Include necessary WordPress core files if not already included
 */
function wcqm_include_core_files() {
    // Include necessary WordPress core files
    if (!function_exists('wp_add_inline_script')) {
        include_once(ABSPATH . 'wp-includes/script-loader.php');
    }
    if (!function_exists('wc_add_notice')) {
        include_once(WP_PLUGIN_DIR . '/woocommerce/includes/wc-notice-functions.php');
    }
}

/**
 * Initialize the plugin
 */
function wcqm_init() {
    // Check if WooCommerce is active
    if (!wcqm_is_woocommerce_active()) {
        add_action('admin_notices', 'wcqm_woocommerce_missing_notice');
        return;
    }

    // Include necessary core files
    wcqm_include_core_files();

    // Load required files
    require_once WCQM_PLUGIN_DIR . 'includes/class-wcqm-rules-manager.php';
    require_once WCQM_PLUGIN_DIR . 'includes/class-wcqm-product.php';
    require_once WCQM_PLUGIN_DIR . 'includes/class-wcqm-cart.php';
    require_once WCQM_PLUGIN_DIR . 'includes/class-wcqm-checkout.php';
    require_once WCQM_PLUGIN_DIR . 'includes/class-wcqm-settings.php';

    // Initialize components in correct order
    $rules_manager = WCQM_Rules_Manager::instance();
    
    // Initialize product class with rules manager
    WCQM_Product::instance($rules_manager);
    
    // Initialize other components
    $cart = new WCQM_Cart($rules_manager);
    $checkout = new WCQM_Checkout($rules_manager);
    $settings = new WCQM_Settings($rules_manager);
    
    // Add settings link to plugins page
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wcqm_add_settings_link');

    // Load text domain for translations
    load_plugin_textdomain('wc-quantity-multiples', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

/**
 * Add settings link to plugins page
 */
function wcqm_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-quantity-multiples') . '">' . __('Settings', 'wc-quantity-multiples') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Display WooCommerce missing notice
 */
function wcqm_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('WC Quantity Multiples requires WooCommerce to be installed and active.', 'wc-quantity-multiples'); ?></p>
    </div>
    <?php
}

// Initialize plugin on plugins loaded (priority 20 to ensure WooCommerce is fully loaded)
add_action('plugins_loaded', 'wcqm_init', 20); 