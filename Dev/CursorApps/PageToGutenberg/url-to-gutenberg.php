<?php
/**
 * Plugin Name: URL to Gutenberg
 * Plugin URI: https://example.com/url-to-gutenberg
 * Description: Convert web content into Gutenberg blocks using a hybrid extraction system.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: url-to-gutenberg
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package UTG
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Track plugin loading globally to prevent multiple initializations
global $utg_plugin_loaded;
if (isset($utg_plugin_loaded) && $utg_plugin_loaded === true) {
    return;
}
$utg_plugin_loaded = true;

// Define plugin constants.
define( 'UTG_PLUGIN_FILE', __FILE__ );
define( 'UTG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UTG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UTG_VERSION', '1.0.0' );

// Composer autoloader.
if ( file_exists( UTG_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once UTG_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Display admin notice if Composer dependencies are missing.
    add_action( 'admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php esc_html_e( 'URL to Gutenberg is missing required dependencies. Please run "composer install" in the plugin directory.', 'url-to-gutenberg' ); ?>
            </p>
        </div>
        <?php
    } );
    return;
}

// Include required files.
require_once UTG_PLUGIN_DIR . 'includes/class-settings.php';
require_once UTG_PLUGIN_DIR . 'includes/class-content-extractor.php';
require_once UTG_PLUGIN_DIR . 'includes/api/class-llm-api.php';
require_once UTG_PLUGIN_DIR . 'includes/class-url-to-gutenberg.php';
require_once UTG_PLUGIN_DIR . 'includes/class-content-pipeline.php';
// Load additional component files
require_once UTG_PLUGIN_DIR . 'includes/content/class-content-optimizer.php';
require_once UTG_PLUGIN_DIR . 'includes/templates/class-template-manager.php';
require_once UTG_PLUGIN_DIR . 'includes/admin/class-admin.php';
require_once UTG_PLUGIN_DIR . 'includes/generator/class-post-generator.php';
require_once UTG_PLUGIN_DIR . 'includes/generator/class-media-handler.php';

/**
 * Load plugin textdomain.
 */
function utg_load_textdomain() {
    load_plugin_textdomain( 'url-to-gutenberg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'utg_load_textdomain' );

/**
 * Initialize plugin.
 */
function utg_init() {
    // Get plugin instance to initialize everything.
    \UTG\URL_To_Gutenberg::get_instance();
}
add_action( 'plugins_loaded', 'utg_init', 15 );

/**
 * Add a hook to cleanup duplicate menu items
 * This runs after all menu registration has completed
 */
function utg_cleanup_duplicate_menus() {
    global $submenu;
    
    // Check for both old and new menu slugs
    $menu_slugs = array('url-to-gutenberg', 'utg');
    
    foreach ($menu_slugs as $menu_slug) {
        // If no submenus exist for this slug, continue to next
        if (!isset($submenu[$menu_slug]) || !is_array($submenu[$menu_slug])) {
            continue;
        }
        
        // Create a map to track which menu slugs we've seen
        $seen_slugs = array();
        $cleaned_submenu = array();
        
        foreach ($submenu[$menu_slug] as $index => $menu_item) {
            // Skip if not a valid menu item
            if (!isset($menu_item[2]) || empty($menu_item[2])) {
                continue;
            }
            
            $slug = $menu_item[2];
            
            // If we haven't seen this slug before, keep it
            if (!isset($seen_slugs[$slug])) {
                $seen_slugs[$slug] = true;
                $cleaned_submenu[] = $menu_item;
            }
            // Otherwise, this is a duplicate - skip it
        }
        
        // Replace the submenu with our cleaned version
        if (!empty($cleaned_submenu)) {
            $submenu[$menu_slug] = $cleaned_submenu;
        }
    }
}
// Run this after all admin_menu hooks have been processed
add_action('admin_menu', 'utg_cleanup_duplicate_menus', 999);

/**
 * Create required directories on plugin activation.
 */
function utg_activate() {
    // Create assets directory if it doesn't exist.
    $assets_dir = UTG_PLUGIN_DIR . 'assets';
    if ( ! file_exists( $assets_dir ) ) {
        wp_mkdir_p( $assets_dir );
        wp_mkdir_p( $assets_dir . '/css' );
        wp_mkdir_p( $assets_dir . '/js' );
    }
    
    // Create templates directory if it doesn't exist.
    $templates_dir = UTG_PLUGIN_DIR . 'templates';
    if ( ! file_exists( $templates_dir ) ) {
        wp_mkdir_p( $templates_dir );
    }
}
register_activation_hook( __FILE__, 'utg_activate' );

/**
 * Add empty index.php files to directories to prevent directory listing.
 */
function utg_create_index_files() {
    $directories = [
        UTG_PLUGIN_DIR,
        UTG_PLUGIN_DIR . 'includes',
        UTG_PLUGIN_DIR . 'includes/api',
        UTG_PLUGIN_DIR . 'assets',
        UTG_PLUGIN_DIR . 'assets/css',
        UTG_PLUGIN_DIR . 'assets/js',
        UTG_PLUGIN_DIR . 'templates',
    ];
    
    foreach ( $directories as $dir ) {
        if ( file_exists( $dir ) && ! file_exists( $dir . '/index.php' ) ) {
            file_put_contents( $dir . '/index.php', '<?php // Silence is golden.' );
        }
    }
}
add_action( 'admin_init', 'utg_create_index_files' );

/**
 * Create default CSS and JS files if they don't exist.
 */
function utg_create_default_assets() {
    // Default CSS file.
    $css_file = UTG_PLUGIN_DIR . 'assets/css/admin.css';
    if ( ! file_exists( $css_file ) ) {
        $css_content = "/**
 * URL to Gutenberg Admin Styles
 */
.utg-container {
    display: flex;
    margin-top: 20px;
}

.utg-form-container {
    flex: 2;
    margin-right: 30px;
}

.utg-sidebar {
    flex: 1;
}

.utg-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-bottom: 20px;
    padding: 15px;
}

.utg-form-field {
    margin-bottom: 15px;
}

.utg-form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.utg-form-submit {
    margin-top: 20px;
}

.utg-loading {
    margin-top: 20px;
    display: flex;
    align-items: center;
}

.utg-loading-text {
    margin-left: 10px;
}

.utg-result {
    margin-top: 20px;
    background: #f0f6fc;
    border-left: 4px solid #2271b1;
    padding: 15px;
}

.utg-result-actions {
    margin-top: 15px;
}

.utg-error {
    margin-top: 20px;
}";
        file_put_contents( $css_file, $css_content );
    }
    
    // Default JS file.
    $js_file = UTG_PLUGIN_DIR . 'assets/js/admin.js';
    if ( ! file_exists( $js_file ) ) {
        $js_content = "/**
 * URL to Gutenberg Admin JavaScript
 */
jQuery(document).ready(function($) {
    $('#utg-url-form').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading
        $('#utg-result').hide();
        $('#utg-error').hide();
        $('#utg-loading').show();
        
        // Get URL
        var url = $('#utg-url').val();
        
        // Send AJAX request
        $.ajax({
            url: utg_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'utg_process_url',
                url: url,
                nonce: utg_vars.nonce
            },
            success: function(response) {
                $('#utg-loading').hide();
                
                if (response.success) {
                    // Show result
                    $('#utg-result .utg-result-content').html('<p>' + response.data.message + '</p>');
                    $('#utg-edit-post').attr('href', response.data.edit_url);
                    $('#utg-result').show();
                } else {
                    // Show error
                    $('#utg-error').html('<p>' + utg_vars.error_text + ' ' + response.data.message + '</p>');
                    $('#utg-error').show();
                }
            },
            error: function() {
                $('#utg-loading').hide();
                $('#utg-error').html('<p>' + utg_vars.error_text + ' An unknown error occurred.</p>');
                $('#utg-error').show();
            }
        });
    });
});";
        file_put_contents( $js_file, $js_content );
    }
}
add_action( 'admin_init', 'utg_create_default_assets' );

/**
 * Create a composer.json file if it doesn't exist.
 */
function utg_create_composer_file() {
    $composer_file = UTG_PLUGIN_DIR . 'composer.json';
    if ( ! file_exists( $composer_file ) ) {
        $composer_content = '{
    "name": "wordpress/url-to-gutenberg",
    "description": "Convert web content into Gutenberg blocks using a hybrid extraction system",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4",
        "hstanleycrow/easyphparticleextractor": "^1.0",
        "symfony/panther": "^2.0",
        "symfony/css-selector": "^5.4|^6.0",
        "php-webdriver/webdriver": "^1.12",
        "symfony/process": "^5.4|^6.0"
    },
    "autoload": {
        "psr-4": {
            "UTG\\": "includes/"
        }
    }
}';
        file_put_contents( $composer_file, $composer_content );
    }
}
register_activation_hook( __FILE__, 'utg_create_composer_file' );

// Add a filter hook for the settings page rendering
add_filter('utg_admin_render_settings', function($result) {
    // If not on the settings page, return early
    if (!is_admin() || !isset($_GET['page']) || $_GET['page'] !== 'url-to-gutenberg-settings') {
        return $result;
    }
    
    return null; // Let the default rendering handle it
}, 10, 1); 