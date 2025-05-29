<?php
/**
 * WP Customer AI Chatbot Main Plugin File
 *
 * Note: This plugin conditionally loads WordPress core functions as needed. Linter warnings for undefined functions can be safely ignored, as all functions are available at runtime in a proper WordPress environment.
 *
 * @package    WP_Customer_AI_Chatbot
 * @author     Social Intent
 * @license    GPL-2.0+
 * @link       https://socialintent.com
 * @since      0.1.0
 */
if (defined('WCAC_DEBUG') && WCAC_DEBUG) {
    error_log('[WCAC DEBUG] Plugin file loaded: ' . date('Y-m-d H:i:s'));
}
// phpcs:ignoreFile -- Suppress linter warnings for undefined WordPress core functions/constants in plugin context
/**
 * Plugin Name:       WP Customer AI Chatbot
 * Plugin URI:        https://example.com/wp-customer-ai-chatbot
 * Description:       An AI-powered chatbot for WordPress and WooCommerce sites, leveraging OpenRouter.
 * Version:           1.1.0
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
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define Core Plugin Constants EARLY
// Ensure WordPress functions are available first
if ( ! function_exists( 'plugin_dir_path' ) ) {
    // Check ABSPATH existence for safety
    if ( defined('ABSPATH') && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    } else {
        // Minimal fallback if WP environment is not fully loaded (e.g., some CLI)
        // This might not be perfectly accurate in all edge cases.
        define('WCAC_PLUGIN_DIR', dirname(__FILE__) . '/'); 
    }
}
if ( ! defined('WCAC_PLUGIN_DIR') ) { // Define only if the fallback above didn't run
    define('WCAC_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
     if ( defined('ABSPATH') && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    } else {
         // Minimal fallback
        // This is harder to guess reliably without WP context
        // We'll define it later inside run_wcac_customer_ai_chatbot if needed
    }
}
if ( function_exists('plugin_dir_url') ) { // Define only if function exists
     if ( ! defined('WCAC_PLUGIN_URL') ) {
        define('WCAC_PLUGIN_URL', plugin_dir_url(__FILE__));
    }
}

// Suppress PHP deprecation and notice warnings in CLI context
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
    @ini_set('display_errors', 'Off');
}

// Ensure WordPress functions are available before any usage
if ( ! function_exists( 'plugin_dir_path' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( ! function_exists( 'get_posts' ) ) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if ( ! function_exists( 'register_activation_hook' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
    require_once ABSPATH . 'wp-includes/l10n.php';
}
if ( ! function_exists( 'plugin_basename' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( ! function_exists( 'add_action' ) ) {
    require_once ABSPATH . 'wp-includes/plugin.php';
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}
if ( ! function_exists( 'wp_unslash' ) ) {
    require_once ABSPATH . 'wp-includes/formatting.php';
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if ( ! function_exists( 'set_transient' ) ) {
    require_once ABSPATH . 'wp-includes/option.php';
}
if ( ! function_exists( 'get_transient' ) ) {
    require_once ABSPATH . 'wp-includes/option.php';
}
if ( ! function_exists( 'delete_transient' ) ) {
    require_once ABSPATH . 'wp-includes/option.php';
}
if ( ! function_exists( 'wp_send_json_success' ) ) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if ( ! function_exists( 'get_post_type' ) ) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if ( ! function_exists( 'wp_die' ) ) {
    require_once ABSPATH . 'wp-includes/functions.php';
}

/**
 * Define constants
 */
// @phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
define('WCAC_VERSION', '0.1.2');
// @phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
// REMOVE THESE - Defined earlier
// define('WCAC_PLUGIN_DIR', plugin_dir_path(__FILE__));
// @phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
// REMOVE THESE - Defined earlier
// define('WCAC_PLUGIN_URL', plugin_dir_url(__FILE__));
// @phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
define('WCAC_PLUGIN_FILE', __FILE__);
// @phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
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

require_once __DIR__ . '/includes/class-wcac-activator.php';

/**
 * The code that runs during plugin activation.
 *
 * Loads activator class, initializes debug logger, and creates required tables.
 *
 * @since 0.1.0
 */
function activate_wcac() {
    // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    // Activate dependencies
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-activator.php');
    if (class_exists('Wcac_Activator')) {
        Wcac_Activator::activate();
    }
    
    // Initialize debug logger - use the correct path after reorganization
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/common/class-wcac-debug-logger.php');
    if (!class_exists('Wcac_Debug_Logger')) {
        // Try alternate paths in case of reorganization
        wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-debug-logger.php');
    }
    
    if (class_exists('Wcac_Debug_Logger')) {
        Wcac_Debug_Logger::init();
        Wcac_Debug_Logger::create_table();
    } else {
        error_log('WCAC ERROR: Failed to load Debug Logger class. Please check file paths.');
    }

    if (class_exists('Wcac_Indexer')) {
        Wcac_Indexer::ensure_indexes();
    } else {
        require_once plugin_dir_path(__FILE__) . 'includes/indexing/class-wcac-indexer.php';
        if (class_exists('Wcac_Indexer')) {
            Wcac_Indexer::ensure_indexes();
        }
    }
}

/**
 * The code that runs during plugin deactivation.
 *
 * Loads deactivator class and performs cleanup.
 *
 * @since 0.1.0
 */
function deactivate_wcac() {
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-deactivator.php');
    if (class_exists('Wcac_Deactivator')) {
        Wcac_Deactivator::deactivate();
    }
}

// Ensure Wcac_Activator is loaded before registering activation hook
register_activation_hook(__FILE__, ['Wcac_Activator', 'activate']);
// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
register_deactivation_hook( __FILE__, 'deactivate_wcac' );

/**
 * Load the plugin text domain for translation.
 *
 * @since 0.1.0
 */
function wcac_load_textdomain() {
    // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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
 * Handler function for post save operations.
 *
 * @param int $post_id The post ID.
 * @param WP_Post $post The post object.
 *
 * Note: This is a placeholder for future extension. Actual post-save logic is handled in the indexer.
 */
function wcac_handle_save_post($post_id, $post) {
    // Placeholder for extension. See Wcac_Indexer for actual implementation.
}

/**
 * Handler function for post delete operations.
 *
 * @param int $post_id The post ID.
 *
 * Note: This is a placeholder for future extension. Actual post-delete logic is handled in the indexer.
 */
function wcac_handle_delete_post($post_id) {
    // Placeholder for extension. See Wcac_Indexer for actual implementation.
}

function wcac_get_all_indexable_post_ids() {
    // Fetch all post IDs to be indexed, according to settings
    $options = get_option('wcac_settings', []);
    $types = [];
    if (!empty($options['wcac_index_products'])) {
        $types[] = 'product';
        $types[] = 'product_variation';
    }
    if (!empty($options['wcac_index_pages'])) {
        $types[] = 'page';
    }
    if (!empty($options['wcac_index_posts'])) {
        $types[] = 'post';
    }
    if (empty($types)) return [];
    $args = [
        'post_type' => $types,
        'post_status' => 'any',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => false,
        'no_found_rows' => true,
    ];
    // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts
    $ids = get_posts($args);
    return is_array($ids) ? $ids : [];
}

function wcac_handle_build_index_ajax() {
    $debug_log_file = WCAC_PLUGIN_DIR . 'wcac_ajax_debug.log';
    try {
        error_log('[WCAC_AJAX] Handler called for wcac_build_index');
        // Security check
        error_log('[WCAC] Incoming nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'MISSING'));
        // Ensure required WP functions are loaded
        if (!function_exists('wp_verify_nonce')) {
            require_once ABSPATH . 'wp-includes/pluggable.php';
        }
        if (!function_exists('sanitize_text_field')) {
            require_once ABSPATH . 'wp-includes/formatting.php';
        }
        if (!function_exists('wp_unslash')) {
            require_once ABSPATH . 'wp-includes/formatting.php';
        }
        if (!function_exists('current_user_can')) {
            require_once ABSPATH . 'wp-includes/pluggable.php';
        }
        if (!function_exists('wp_send_json_error')) {
            require_once ABSPATH . 'wp-includes/functions.php';
        }
        if (!function_exists('wp_send_json_success')) {
            require_once ABSPATH . 'wp-includes/functions.php';
        }
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'wcac_reindex_nonce')) {
            error_log('[WCAC] Nonce verification failed. POST: ' . print_r($_POST, true));
            file_put_contents($debug_log_file, "[".date('c')."] Nonce verification failed. POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
            wp_send_json_error(['message' => esc_html__('Nonce verification failed.', 'wp-customer-ai-chatbot')]);
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('You do not have permission to perform this action.', 'wp-customer-ai-chatbot')]);
            return;
        }
        if (!class_exists('Wcac_Indexer')) {
            wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-indexer.php');
        }
        if (!class_exists('Wcac_Indexer')) {
            wp_send_json_error(['message' => esc_html__('Indexer class not found.', 'wp-customer-ai-chatbot')]);
            return;
        }
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $batch_size = 100;
        $is_first_batch = ($offset === 0);
        $transient_key = 'wcac_index_post_ids';
        error_log("[WCAC DEBUG] Indexer AJAX called: offset={$offset}, batch_size={$batch_size}, is_first_batch=" . ($is_first_batch ? 'YES' : 'NO'));
        if ($is_first_batch) {
            $all_post_ids = wcac_get_all_indexable_post_ids();
            set_transient($transient_key, $all_post_ids, 60 * 30); // 30 min
        } else {
            $all_post_ids = get_transient($transient_key);
            if (!is_array($all_post_ids)) {
                error_log('[WCAC DEBUG] Indexer AJAX: Transient missing or expired.');
                file_put_contents($debug_log_file, "[".date('c')."] Indexing session expired. POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
                wp_send_json_error(['message' => esc_html__('Indexing session expired. Please restart.', 'wp-customer-ai-chatbot')]);
                return;
            }
        }
        $total = count($all_post_ids);
        $batch = array_slice($all_post_ids, $offset, $batch_size);
        error_log("[WCAC DEBUG] Indexer AJAX: total={$total}, batch_length=" . count($batch) . ", offset={$offset}");
        $indexer = new Wcac_Indexer();
        $result = $indexer->build_index($batch, $is_first_batch);
        $processed = min($offset + count($batch), $total);
        $errors = $result['errors_in_batch'] ?? 0;
        $last_batch_error = $result['last_error_message'] ?? null;

        if ($processed < $total) {
            $next_offset = $offset + $batch_size;
            $status = 'in_progress';
            error_log("[WCAC DEBUG] Indexer AJAX: Sending in_progress, processed={$processed}, next_offset={$next_offset}");
        } else {
            $next_offset = null;
            $status = 'complete';
            error_log("[WCAC DEBUG] Indexer AJAX: Sending complete, processed={$processed}, total={$total}");
            delete_transient($transient_key);
            $indexer->update_final_index_meta();
        }

        wp_send_json_success([
            'status' => $status,
            'processed' => $processed,
            'total' => $total,
            'errors' => $errors,
            'last_batch_error' => $last_batch_error,
            'next_offset' => $next_offset,
            'counts' => ($status === 'complete') ? $indexer->get_last_meta_counts() : null,
        ]);
    } catch (Throwable $e) {
        $msg = '[WCAC_AJAX] Exception in wcac_handle_build_index_ajax: ' . $e->getMessage();
        $trace = $e->getTraceAsString();
        error_log($msg . "\n" . $trace);
        file_put_contents($debug_log_file, "[".date('c')."] Exception: $msg\n$trace\n", FILE_APPEND);
        if (function_exists('wp_send_json_error')) {
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage(), 'trace' => $trace], 500);
        } else {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'data' => ['message' => 'Exception: ' . $e->getMessage(), 'trace' => $trace]]);
            exit;
        }
    }
}

/**
 * Set up all hooks and filters
 */
function wcac_setup_hooks() {
    // Load text domain
    // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    add_action('init', 'wcac_load_textdomain');
    
    // Add post hooks
    // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    add_action('save_post', 'wcac_handle_save_post', 10, 2);
    // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    add_action('delete_post', 'wcac_handle_delete_post');
    
    // Load indexing functions
    wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/wcac-indexing-functions.php');

    // --- WCAC Relationship Extraction Integration ---
    add_action('save_post', function($post_id, $post, $update) {
        // Only run for products and patterns (customize as needed)
        $post_type = get_post_type($post_id);
        if (!in_array($post_type, ['product', 'pattern'])) return;
        // Build known entities for matching (yarns, products, etc.)
        $known_entities = [];
        // Example: get all yarns (customize as needed)
        $yarns = get_posts([
            'post_type' => 'yarn',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);
        if ($yarns) {
            $known_entities['yarn'] = [];
            foreach ($yarns as $yarn_id) {
                $known_entities['yarn'][$yarn_id] = get_the_title($yarn_id);
            }
        }
        // Example: get all products
        $products = get_posts([
            'post_type' => 'product',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);
        if ($products) {
            $known_entities['product'] = [];
            foreach ($products as $prod_id) {
                $known_entities['product'][$prod_id] = get_the_title($prod_id);
            }
        }
        // Extract and cache relationships
        if (class_exists('Wcac_Relationship_Extractor')) {
            Wcac_Relationship_Extractor::extract_and_cache($post_id, $post_type, $known_entities);
        }
    }, 20, 3);

    // --- End WCAC Relationship Extraction Integration ---
}

/**
 * Runs the plugin.
 */
function run_wcac_customer_ai_chatbot() {
    try {
        error_log('WCAC DEBUG: Starting plugin initialization - ' . date('Y-m-d H:i:s'));
        
        // Load debug logger early
        if (!class_exists('Wcac_Debug_Logger')) {
            error_log('WCAC DEBUG: Loading debug logger class');
            
            // Try new location first, then fall back to old location
            $debug_logger_loaded = false;
            if (file_exists(WCAC_PLUGIN_DIR . 'includes/common/class-wcac-debug-logger.php')) {
                wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/common/class-wcac-debug-logger.php');
                $debug_logger_loaded = class_exists('Wcac_Debug_Logger');
                error_log('WCAC DEBUG: Attempted to load debug logger from common/ directory: ' . ($debug_logger_loaded ? 'SUCCESS' : 'FAILED'));
            }
            
            if (!$debug_logger_loaded && file_exists(WCAC_PLUGIN_DIR . 'includes/class-wcac-debug-logger.php')) {
                wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac-debug-logger.php');
                $debug_logger_loaded = class_exists('Wcac_Debug_Logger');
                error_log('WCAC DEBUG: Attempted to load debug logger from includes/ directory: ' . ($debug_logger_loaded ? 'SUCCESS' : 'FAILED'));
            }
            
            if ($debug_logger_loaded) {
                Wcac_Debug_Logger::init();
                error_log('WCAC DEBUG: Debug logger initialized');
            } else {
                error_log('WCAC ERROR: Failed to load debug logger class from any location');
            }
        }
        
        // Load main plugin class
        if (!class_exists('Wcac')) {
            error_log('WCAC DEBUG: Loading main plugin class');
            wcac_safe_require_once(WCAC_PLUGIN_DIR . 'includes/class-wcac.php');
        }
        
        // Check for admin area
        if (is_admin()) {
            error_log('WCAC DEBUG: Running in admin context');
            
            // Pre-load admin classes to ensure they're available
            if (!class_exists('Wcac_Admin_Settings') && file_exists(WCAC_PLUGIN_DIR . 'admin/class-wcac-admin-settings.php')) {
                wcac_safe_require_once(WCAC_PLUGIN_DIR . 'admin/class-wcac-admin-settings.php');
                error_log('WCAC DEBUG: Pre-loaded Wcac_Admin_Settings class: ' . (class_exists('Wcac_Admin_Settings') ? 'SUCCESS' : 'FAILED'));
            }
            
            if (!class_exists('Wcac_Admin') && file_exists(WCAC_PLUGIN_DIR . 'admin/class-wcac-admin.php')) {
                wcac_safe_require_once(WCAC_PLUGIN_DIR . 'admin/class-wcac-admin.php');
                error_log('WCAC DEBUG: Pre-loaded Wcac_Admin class: ' . (class_exists('Wcac_Admin') ? 'SUCCESS' : 'FAILED'));
            }
        }
        
        // Initialize plugin if class exists
        if (class_exists('Wcac')) {
            error_log('WCAC DEBUG: Wcac class found, creating instance');
            $plugin = new Wcac();
            $plugin->run();
            error_log('WCAC DEBUG: Plugin initialized and running');
        } else {
            error_log('WCAC ERROR: Wcac class not found, cannot initialize plugin');
        }
    } catch (Throwable $e) {
        error_log('WCAC FATAL: Exception during plugin initialization: ' . $e->getMessage());
        error_log('WCAC FATAL: ' . $e->getTraceAsString());
        
        // If in admin context, try to show a helpful message
        if (is_admin() && function_exists('add_action')) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="error"><p><strong>WP Customer AI Chatbot Error:</strong> ' . 
                     esc_html($e->getMessage()) . '</p>' .
                     '<p>Please check server logs for details or contact support.</p></div>';
            });
        }
    }
}

// Check if we're in WP-CLI context
if (defined('WP_CLI') && WP_CLI) {
    error_log('WCAC: Running in WP-CLI context');
    error_log('WCAC: Plugin directory: ' . WCAC_PLUGIN_DIR);
    error_log('WCAC: Plugin file: ' . __FILE__);
    error_log('WCAC: WP_CLI version: ' . WP_CLI_VERSION);
}

// Load CLI commands if WP_CLI is available
if (defined('WP_CLI') && WP_CLI) {
    error_log('WCAC: WP_CLI is defined, attempting to load CLI class');
    $cli_file = WCAC_PLUGIN_DIR . 'includes/cli/class-wcac-cli.php';
    error_log('WCAC: CLI file path: ' . $cli_file);
    if (file_exists($cli_file)) {
        error_log('WCAC: CLI file exists, loading it');
        wcac_safe_require_once($cli_file);
        error_log('WCAC: CLI file loaded');
    } else {
        error_log('WCAC ERROR: CLI file does not exist at: ' . $cli_file);
    }
}

// Hook into WordPress init to ensure WordPress is fully loaded
add_action('plugins_loaded', 'run_wcac_customer_ai_chatbot');

// Always register the AJAX handler for CSV upload at the root level
add_action('wp_ajax_wcac_handle_upload_test_csv', ['Wcac_Admin_Ajax', 'handle_upload_test_csv_ajax']);

// Always register the AJAX handler at the root level to ensure it is available in all contexts
add_action('wp_ajax_wcac_build_index', 'wcac_handle_build_index_ajax');

// Always register the export handler when the plugin is active.
add_action('admin_post_wcac_export_test_results', function() {
    // Security: Only allow admins
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    // Security: Nonce check
    if (!isset($_POST['wcac_export_nonce']) || !wp_verify_nonce($_POST['wcac_export_nonce'], 'wcac_export_test_results')) {
        wp_die('Nonce check failed');
    }
    $run_id = isset($_POST['wcac_test_run_id']) ? $_POST['wcac_test_run_id'] : '';
    if (!$run_id) {
        wp_die('No test run selected.');
    }
    // Load the exporter class
    require_once __DIR__ . '/includes/testing/class-wcac-test-results-exporter.php';
    Wcac_Test_Results_Exporter::export_csv($run_id);
});

// Register Action Scheduler callback for daily log pruning
add_action('wcac_log_prune_daily', ['Wcac_Log', 'scheduled_prune']);

// REMOVE other plugins_loaded hooks for wcac_init and wcac_setup_hooks
/*
add_action('plugins_loaded', 'wcac_init', 5);
add_action('plugins_loaded', 'wcac_setup_hooks', 10);
*/

// REMOVE Global require blocks
// REMOVE wcac_run_plugin function definition and hook

// Keep wcac_setup_hooks definition, but it might be redundant if hooks are added inside Wcac class
// Consider refactoring hook additions into the Wcac class later.
/*
function wcac_setup_hooks() {
    // ... (hooks were here) ...
}
*/ 