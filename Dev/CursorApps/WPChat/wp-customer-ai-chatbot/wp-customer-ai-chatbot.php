<?php
/**
 * Plugin Name:       WP Customer AI Chatbot
 * Plugin URI:        https://example.com/wp-customer-ai-chatbot
 * Description:       An AI-powered chatbot for WordPress and WooCommerce sites, leveraging OpenRouter.
 * Version:           0.1.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name or Company
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-customer-ai-chatbot
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Define constants
 */
define('WCAC_VERSION', '0.1.1');
define('WCAC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCAC_PLUGIN_FILE', __FILE__);
define('WCAC_DEBUG', false);

/**
 * Safe require function
 * 
 * @param string $file Path to the file to require
 * @return bool True if file was required successfully, false otherwise
 */
function wcac_safe_require($file) {
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
}

/**
 * Load a file safely with error handling
 * 
 * @param string $file_path Path to the file to load
 * @return bool True if file was loaded successfully, false otherwise
 */
function wcac_safe_require_once($file_path) {
    if (file_exists($file_path)) {
        require_once $file_path;
        return true;
    }
    if (defined('WCAC_DEBUG') && WCAC_DEBUG) {
        error_log('WCAC Error: Required file not found: ' . $file_path);
    }
    return false;
}

/**
 * The code that runs during plugin activation.
 */
function activate_wcac() {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    // Activate dependencies
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-activator.php');
    if (class_exists('Wcac_Activator')) {
        Wcac_Activator::activate();
    }
    
    // Initialize debug logger
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-debug-logger.php');
    if (class_exists('Wcac_Debug_Logger')) {
        Wcac_Debug_Logger::init();
        Wcac_Debug_Logger::create_table();
    }
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wcac() {
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-deactivator.php');
    if (class_exists('Wcac_Deactivator')) {
        Wcac_Deactivator::deactivate();
    }
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'activate_wcac');
register_deactivation_hook(__FILE__, 'deactivate_wcac');

/**
 * Load the plugin text domain for translation.
 */
function wcac_load_textdomain() {
    load_plugin_textdomain(
        'wp-customer-ai-chatbot',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

/**
 * Define a compatibility error message function
 */
function wcac_compatibility_error() {
    echo '<div class="error"><p>WP Customer AI Chatbot requires WordPress version 5.8 or higher and PHP 7.4 or higher.</p></div>';
}

// Minimum requirements check
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', 'wcac_compatibility_error');
    return; // Stop execution
}

/**
 * Handler functions for post operations
 */
function wcac_handle_save_post($post_id, $post) {
    // Implementation will be added later
}

function wcac_handle_delete_post($post_id) {
    // Implementation will be added later
}

function wcac_handle_build_index_ajax() {
    // Security check
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wcac_reindex_nonce')) {
        wp_send_json_error(['message' => esc_html__('Nonce verification failed.', 'wp-customer-ai-chatbot')]);
        return;
    }

    // Permission check
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => esc_html__('You do not have permission to perform this action.', 'wp-customer-ai-chatbot')]);
        return;
    }

    // Load the indexer if not already loaded
    if (!class_exists('Wcac_Indexer')) {
        wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-indexer.php');
    }

    if (class_exists('Wcac_Indexer')) {
        $indexer = new Wcac_Indexer();
        $result = $indexer->build_index();

        if ($result['error'] !== null) {
            wp_send_json_error(['message' => $result['error']]);
        } else {
            wp_send_json_success(['count' => $result['count']]);
        }
    } else {
        wp_send_json_error(['message' => esc_html__('Indexer class not found.', 'wp-customer-ai-chatbot')]);
    }
}

/**
 * Initialize the plugin
 */
function wcac_init() {
    // Load core files
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-loader.php');
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac.php');
    
    // Load debug logger early and initialize it
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-debug-logger.php');
    if (class_exists('Wcac_Debug_Logger')) {
        Wcac_Debug_Logger::init();
    }
    
    // Initialize the plugin
    if (class_exists('Wcac')) {
        $plugin = new Wcac();
        if (method_exists($plugin, 'run')) {
            $plugin->run();
        }
    }
}

/**
 * Set up all hooks and filters
 */
function wcac_setup_hooks() {
    // Load text domain
    add_action('init', 'wcac_load_textdomain');
    
    // Add post hooks
    add_action('save_post', 'wcac_handle_save_post', 10, 2);
    add_action('delete_post', 'wcac_handle_delete_post');
    
    // Add AJAX handlers for index building
    add_action('wp_ajax_wcac_build_index', 'wcac_handle_build_index_ajax');
    
    // Load indexing functions
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/wcac-indexing-functions.php');
}

// Initialize plugin on plugins_loaded and set up hooks
add_action('plugins_loaded', 'wcac_init', 5);
add_action('plugins_loaded', 'wcac_setup_hooks', 10); 