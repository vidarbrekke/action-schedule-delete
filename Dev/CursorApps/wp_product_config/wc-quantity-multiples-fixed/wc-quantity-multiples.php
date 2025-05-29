<?php
/**
 * Plugin Name: WC Quantity Multiples
 * Plugin URI: https://wordpress.org/plugins/wc-quantity-multiples/
 * Description: Force WooCommerce product quantities to be purchased in specific multiples based on product or category rules.
 * Version: 1.1.0
 * Author: WC Quantity Multiples Team
 * Author URI: https://wordpress.org/plugins/wc-quantity-multiples/
 * Text Domain: wc-quantity-multiples
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.7.1
 *
 * @package WC_Quantity_Multiples
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WCQM_VERSION', '1.1.0');
define('WCQM_PLUGIN_FILE', __FILE__);
define('WCQM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCQM_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
function wcqm_is_woocommerce_active() {
    $active_plugins = (array) get_option('active_plugins', array());
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
    }
    return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

/**
 * Initialize the plugin
 */
function wcqm_init() {
    // Load text domain for translations
    load_plugin_textdomain('wc-quantity-multiples', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Include required files
    require_once WCQM_PLUGIN_DIR . 'includes/class-wcqm-rules-manager.php';
    require_once WCQM_PLUGIN_DIR . 'includes/class-wcqm-settings.php';
    require_once WCQM_PLUGIN_DIR . 'includes/class-wcqm-product.php';
    require_once WCQM_PLUGIN_DIR . 'includes/class-wcqm-cart.php';
    require_once WCQM_PLUGIN_DIR . 'includes/class-wcqm-checkout.php';
    
    // Initialize components
    WCQM_Rules_Manager::instance();
    WCQM_Settings::instance();
    
    // Add settings link to plugins page
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wcqm_add_settings_link');
    
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', 'wcqm_enqueue_scripts');
    add_action('admin_enqueue_scripts', 'wcqm_admin_enqueue_scripts');
}

/**
 * Add settings link to plugin list
 */
function wcqm_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-quantity-multiples') . '">' . __('Settings', 'wc-quantity-multiples') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Enqueue frontend scripts and styles
 */
function wcqm_enqueue_scripts() {
    if (!is_woocommerce() && !is_cart() && !is_checkout()) {
        return;
    }

    wp_enqueue_style(
        'wcqm-style',
        WCQM_PLUGIN_URL . 'assets/css/wcqm-style.css',
        array(),
        WCQM_VERSION
    );

    wp_enqueue_script(
        'wcqm-quantity',
        WCQM_PLUGIN_URL . 'assets/js/wcqm-quantity.js',
        array('jquery'),
        WCQM_VERSION,
        true
    );

    // Localize script with rules data
    $rules_manager = WCQM_Rules_Manager::instance();
    wp_localize_script(
        'wcqm-quantity',
        'wcqm_rules',
        $rules_manager->get_all_rules_data()
    );
}

/**
 * Enqueue admin scripts and styles
 */
function wcqm_admin_enqueue_scripts($hook) {
    // Only load on our settings page
    if (!in_array($hook, array('toplevel_page_wc-quantity-multiples', 'woocommerce_page_wc-settings'))) {
        return;
    }

    wp_enqueue_style(
        'wcqm-admin-style',
        WCQM_PLUGIN_URL . 'assets/css/wcqm-admin.css',
        array(),
        WCQM_VERSION
    );

    wp_enqueue_script(
        'wcqm-admin',
        WCQM_PLUGIN_URL . 'assets/js/wcqm-admin.js',
        array('jquery'),
        WCQM_VERSION,
        true
    );

    // Pass data to JavaScript
    wp_localize_script(
        'wcqm-admin',
        'wcqm_admin_data',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcqm_admin_nonce')
        )
    );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function wcqm_admin_notice_wc_not_active() {
    ?>
    <div class="error">
        <p><?php _e('WC Quantity Multiples requires WooCommerce to be installed and active.', 'wc-quantity-multiples'); ?></p>
    </div>
    <?php
}

// Check if WooCommerce is active
if (wcqm_is_woocommerce_active()) {
    // Initialize plugin
    add_action('plugins_loaded', 'wcqm_init');
} else {
    // Show admin notice if WooCommerce is not active
    add_action('admin_notices', 'wcqm_admin_notice_wc_not_active');
}

/**
 * Activation hook
 */
function wcqm_activate() {
    if (!wcqm_is_woocommerce_active()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('WC Quantity Multiples requires WooCommerce to be installed and active.', 'wc-quantity-multiples'));
    }
    
    // Initialize default settings if they don't exist
    if (!get_option('wcqm_settings')) {
        update_option('wcqm_settings', array(
            'rules' => array(),
            'default_multiple' => 10
        ));
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'wcqm_activate');

/**
 * Deactivation hook
 */
function wcqm_deactivate() {
    // Clean up if needed
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'wcqm_deactivate');

// Add log cleanup and management
register_activation_hook(__FILE__, 'wcqm_cleanup_logs');
add_action('admin_init', 'wcqm_maybe_cleanup_logs');

/**
 * Clean up debug logs on plugin activation
 */
function wcqm_cleanup_logs() {
    // Clear the debug log if it's over 1MB
    $debug_log = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($debug_log) && filesize($debug_log) > 1000000) {
        // Truncate the file but keep header
        $log_content = file_get_contents($debug_log, false, null, 0, 200);
        $log_content .= "\n\n[" . date('Y-m-d H:i:s') . "] WC Quantity Multiples: Debug log cleared during plugin activation\n";
        file_put_contents($debug_log, $log_content);
    }
}

/**
 * Maybe clean up logs during admin init
 */
function wcqm_maybe_cleanup_logs() {
    // Only run once a day - use transient to track
    if (false === get_transient('wcqm_log_cleanup')) {
        // Set transient for 24 hours
        set_transient('wcqm_log_cleanup', 'yes', 24 * HOUR_IN_SECONDS);
        
        // Clean up the debug log if it's too large
        $debug_log = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log) && filesize($debug_log) > 5000000) { // 5MB
            // Keep the last 100KB of the log
            $log_content = file_get_contents($debug_log, false, null, -100000);
            
            // Add a separator
            $log_content = "\n\n[" . date('Y-m-d H:i:s') . "] WC Quantity Multiples: Debug log truncated. Previous entries removed.\n\n" . $log_content;
            
            // Write back to file
            file_put_contents($debug_log, $log_content);
        }
    }
} 