<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class Wcac {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Wcac_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;
    
    /**
     * Flag to track if dependencies were properly loaded
     *
     * @since    1.0.0
     * @access   protected
     * @var      bool    $dependencies_loaded    Whether all dependencies were loaded successfully
     */
    protected $dependencies_loaded = false;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->version = defined('WCAC_VERSION') ? WCAC_VERSION : '0.1.0';
        $this->dependencies_loaded = $this->load_dependencies();
        
        // Only proceed if dependencies were loaded
        if ($this->dependencies_loaded) {
            $this->define_admin_hooks();
            $this->define_public_hooks();
        }
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Wcac_Loader. Orchestrates the hooks of the plugin.
     * - Wcac_Admin. Defines all hooks for the admin area.
     * - Wcac_Public. Defines all hooks for the public side of the site.
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    True if all required dependencies were loaded, false otherwise
     */
    private function load_dependencies() {
        $dependencies_loaded = true;
        
        // Safe require helper
        $safe_require = function($file) use (&$dependencies_loaded) {
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
            $dependencies_loaded = false;
            return false;
        };
        
        // The class responsible for orchestrating the actions and filters
        $loader_loaded = $safe_require(plugin_dir_path(dirname(__FILE__)) . 'includes/class-wcac-loader.php');
        
        // If loader isn't loaded, we can't continue
        if (!$loader_loaded) {
            return false;
        }
        
        // Admin dependencies
        $safe_require(plugin_dir_path(dirname(__FILE__)) . 'admin/class-wcac-admin-settings.php');
        
        // Public dependencies
        $safe_require(plugin_dir_path(dirname(__FILE__)) . 'public/class-wcac-public.php');
        
        // API handler
        $safe_require(plugin_dir_path(dirname(__FILE__)) . 'includes/class-wcac-api-handler.php');
        
        // Create the loader if the class exists
        if (class_exists('Wcac_Loader')) {
            $this->loader = new Wcac_Loader();
        } else {
            $dependencies_loaded = false;
        }
        
        return $dependencies_loaded;
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        // Guard clause - ensure loader is available
        if (!isset($this->loader) || !$this->loader) {
            return;
        }
        
        // Check if admin class exists
        if (class_exists('Wcac_Admin_Settings')) {
            $plugin_basename = function_exists('plugin_basename') 
                ? plugin_basename(plugin_dir_path(dirname(__FILE__)) . 'wp-customer-ai-chatbot.php')
                : 'wp-customer-ai-chatbot/wp-customer-ai-chatbot.php';
                
            $plugin_admin = new Wcac_Admin_Settings($plugin_basename);
            
            // Add actions for admin settings - only use add_plugin_admin_menu
            $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
            $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
            
            // Add AJAX handler for index building
            $this->loader->add_action('wp_ajax_wcac_build_index', $plugin_admin, 'handle_build_index_ajax');
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        // Guard clause - ensure loader is available
        if (!isset($this->loader) || !$this->loader) {
            return;
        }
        
        // Check if public class exists
        if (class_exists('Wcac_Public')) {
            $plugin_public = new Wcac_Public($this->get_plugin_name(), $this->get_version());
            
            // Add shortcode
            $this->loader->add_action('init', $plugin_public, 'register_shortcode');
            
            // Enqueue assets
            $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_assets');
            
            // Add AJAX handlers
            $this->loader->add_action('wp_ajax_wcac_send_message', $plugin_public, 'handle_send_message_ajax');
            $this->loader->add_action('wp_ajax_nopriv_wcac_send_message', $plugin_public, 'handle_send_message_ajax');
            $this->loader->add_action('wp_ajax_wcac_diagnostics', $plugin_public, 'handle_diagnostics_ajax');
            $this->loader->add_action('wp_ajax_wcac_debug_nonce', $plugin_public, 'handle_debug_nonce_ajax');
            $this->loader->add_action('wp_ajax_nopriv_wcac_debug_nonce', $plugin_public, 'handle_debug_nonce_ajax');
            
            // Post indexing hooks - only add if the indexer was instantiated and property exists
            if (isset($this->loader) && is_object($this->loader) && 
                property_exists($this->loader, 'indexer') && isset($this->loader->indexer)) {
                $this->loader->add_action('save_post', $this->loader->indexer, 'update_post_in_index', 10, 2);
                $this->loader->add_action('delete_post', $this->loader->indexer, 'remove_post_from_index');
            }
        }
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        // Only run if dependencies were loaded and loader exists
        if ($this->dependencies_loaded && isset($this->loader) && method_exists($this->loader, 'run')) {
            $this->loader->run();
        }
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return 'wp-customer-ai-chatbot';
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Wcac_Loader|null    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return isset($this->loader) ? $this->loader : null;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
} 