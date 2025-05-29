<?php

declare(strict_types=1);

/**
 * Main plugin class for WP Customer AI Chatbot.
 *
 * Handles loading dependencies, registering hooks, and orchestrating admin and public functionality.
 * All business logic is delegated to modular classes in /admin, /public, and /includes.
 *
 * Robust error logging and dependency safety: All dependencies are checked and logged before use.
 *
 * @package    WP_Customer_AI_Chatbot
 * @author     Your Name or Company
 * @since      1.0.0
 */

// Ensure WordPress functions are available
if (! function_exists('plugin_dir_path')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if (! function_exists('plugin_basename')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if (! function_exists('admin_url')) {
    require_once ABSPATH . 'wp-includes/link-template.php';
}
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class Wcac
{
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
    public function __construct()
    {
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
    private function load_dependencies()
    {
        error_log('WCAC DEBUG: Loading dependencies in Wcac class');
        $dependencies_loaded = true;

        // Safe require helper
        $safe_require = function ($file) use (&$dependencies_loaded) {
            error_log('WCAC DEBUG: Attempting to load file: ' . $file);
            if (file_exists($file)) {
                require_once $file;
                error_log('WCAC DEBUG: Successfully loaded file: ' . $file);
                return true;
            }
            error_log('WCAC ERROR: File does not exist: ' . $file);
            $dependencies_loaded = false;
            return false;
        };

        // The class responsible for orchestrating the actions and filters
        $loader_loaded = $safe_require(plugin_dir_path(dirname(__FILE__)) . 'includes/class-wcac-loader.php');

        // If loader isn't loaded, we can't continue
        if (!$loader_loaded) {
            error_log('WCAC ERROR: Failed to load loader, cannot continue.');
            return false;
        }

        // Common utility classes
        $safe_require(plugin_dir_path(dirname(__FILE__)) . 'includes/common/class-wcac-utils.php');
        $safe_require(plugin_dir_path(dirname(__FILE__)) . 'includes/common/class-wcac-chatbot-defaults.php');

        // Admin dependencies
        $admin_settings_loaded = $safe_require(plugin_dir_path(dirname(__FILE__)) . 'admin/class-wcac-admin-settings.php');
        if (!$admin_settings_loaded) {
            error_log('WCAC ERROR: Failed to load admin settings class.');
        }

        // Load the regular admin class if available
        $admin_loaded = $safe_require(plugin_dir_path(dirname(__FILE__)) . 'admin/class-wcac-admin.php');
        if (!$admin_loaded) {
            error_log('WCAC ERROR: Failed to load admin class.');
        }

        // Public dependencies
        $public_loaded = $safe_require(plugin_dir_path(dirname(__FILE__)) . 'public/class-wcac-public.php');
        if (!$public_loaded) {
            error_log('WCAC ERROR: Failed to load public class.');
        }

        // API handler
        $safe_require(plugin_dir_path(dirname(__FILE__)) . 'includes/api/class-wcac-api-handler.php');

        // Create the loader if the class exists
        if (class_exists('Wcac_Loader')) {
            error_log('WCAC DEBUG: Creating Wcac_Loader instance');
            $this->loader = new Wcac_Loader();
            error_log('WCAC DEBUG: Wcac_Loader instance created successfully');
        } else {
            error_log('WCAC ERROR: Wcac_Loader class does not exist');
            $dependencies_loaded = false;
        }

        error_log('WCAC DEBUG: All dependencies loaded: ' . ($dependencies_loaded ? 'YES' : 'NO'));
        return $dependencies_loaded;
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {
        // Guard clause - ensure loader is available
        if (!isset($this->loader) || !$this->loader) {
            error_log('WCAC ERROR: Loader is not set, cannot initialize admin hooks');
            return;
        }

        error_log('WCAC DEBUG: Initializing admin hooks in WCAC class');

        // Ensure plugin_basename is available
        if (!function_exists('plugin_basename')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_basename = function_exists('plugin_basename')
            ? plugin_basename(plugin_dir_path(dirname(__FILE__)) . 'wp-customer-ai-chatbot.php')
            : 'wp-customer-ai-chatbot/wp-customer-ai-chatbot.php';

        error_log('WCAC DEBUG: Using plugin basename: ' . $plugin_basename);

        // Check if admin settings class exists
        if (class_exists('Wcac_Admin_Settings')) {
            error_log('WCAC DEBUG: Wcac_Admin_Settings class exists');

            error_log('WCAC DEBUG: Creating new Wcac_Admin_Settings with basename: ' . $plugin_basename);

            $plugin_admin_settings = new Wcac_Admin_Settings($plugin_basename);

            error_log('WCAC DEBUG: Wcac_Admin_Settings instance created successfully');

            // Register required hooks for admin settings
            $this->loader->add_action('admin_menu', $plugin_admin_settings, 'add_plugin_admin_menu');
            $this->loader->add_action('admin_init', $plugin_admin_settings, 'register_settings');
            $this->loader->add_action('admin_enqueue_scripts', $plugin_admin_settings, 'enqueue_admin_assets');

            error_log('WCAC DEBUG: Admin settings hooks registered successfully');
        } else {
            error_log('WCAC ERROR: Wcac_Admin_Settings class does not exist');
        }

        // Add the Wcac_Admin integration if the class exists
        if (class_exists('Wcac_Admin')) {
            error_log('WCAC DEBUG: Wcac_Admin class exists, initializing');
            $plugin_admin = new Wcac_Admin($this->get_plugin_name(), $this->get_version());

            // Explicitly register the style callback
            $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');

            error_log('WCAC DEBUG: Admin hooks registered successfully');
        } else {
            error_log('WCAC DEBUG: Wcac_Admin class not available');
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {
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
            if (
                isset($this->loader) && is_object($this->loader) &&
                property_exists($this->loader, 'indexer') && isset($this->loader->indexer)
            ) {
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
    public function run()
    {
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
    public function get_plugin_name(): string
    {
        return 'wp-customer-ai-chatbot';
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Wcac_Loader|null    Orchestrates the hooks of the plugin.
     */
    public function get_loader(): ?Wcac_Loader
    {
        return isset($this->loader) ? $this->loader : null;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version(): string
    {
        return $this->version;
    }
}
