<?php
declare(strict_types=1);

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://customer-ai-chatbot.wp
 * @since      1.0.0
 *
 * @package    WP_Customer_AI_Chatbot
 * @subpackage WP_Customer_AI_Chatbot/admin
 */

/**
 * This file is intended to be run within the WordPress environment.
 * Functions such as add_action, add_filter, add_menu_page, register_setting, esc_html__, and admin_url
 * are provided by WordPress core and are available when this plugin is loaded by WordPress.
 *
 * If you see linter errors for undefined functions, ensure you are running this code within WordPress.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for
 * the admin area functionality of the plugin.
 *
 * @package    WP_Customer_AI_Chatbot
 * @subpackage WP_Customer_AI_Chatbot/admin
 * @author     WP Customer AI Chatbot Team
 */
class Wcac_Admin {

    private string $plugin_name;
    private string $version;
    private $active_tab;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct(string $plugin_name, string $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        // Remove determination of active tab if not needed
        // $this->active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general'; 

        // REMOVED: Conflicting actions handled by Wcac_Admin_Settings
        // add_action('admin_menu', [$this, 'add_plugin_admin_menu']);
        // add_action('admin_init', [$this, 'register_settings']);
        
        // Keep style enqueue for now
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']); 
    }

    // REMOVED: add_plugin_admin_menu method (handled by Wcac_Admin_Settings)
    /*
    public function add_plugin_admin_menu(): void {
        add_menu_page(
            'WP Customer AI Chatbot Settings',
            'AI Chatbot',
            'manage_options',
            'wcac-settings',
            [$this, 'display_plugin_admin_page'],
            'dashicons-format-chat'
        );
    }
    */

    /**
     * Enqueue styles for the admin area.
     * TODO: Check if this hook is still correct or if it should be merged 
     *       into Wcac_Admin_Settings::enqueue_admin_assets
     */
    public function enqueue_styles($hook): void {
        // The hook name likely needs updating if Wcac_Admin_Settings now defines the page
        // Get the correct hook from Wcac_Admin_Settings? 
        // For now, let's assume the old hook might still work or needs fixing later.
        error_log('WCAC Admin Class Enqueue Hook: ' . $hook); // Add log to check hook
        if ('toplevel_page_wcac-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-admin',
            WCAC_PLUGIN_URL . 'admin/css/wcac-admin.css',
            [],
            $this->version
        );
    }

    // REMOVED: display_plugin_admin_page method (handled by Wcac_Admin_Settings)
    /* 
    public function display_plugin_admin_page(): void {
        include_once WCAC_PLUGIN_DIR . 'admin/partials/wcac-admin-display.php';
    }
    */

    // REMOVED: register_settings method and all associated render methods 
    // (handled by Wcac_Admin_Settings)
    /*
    public function register_settings(): void {
        // ... entire old register_settings logic ...
    }

    // ... all old render_* methods ...
    */

    // REMOVED: validate_settings method (handled by Wcac_Admin_Settings if needed, 
    // currently bypassed there)
    /*
    public function validate_settings(array $input): array {
       // ... old validation logic ...
       return $input; 
    }
    */

    /**
     * Get all available post types for indexing.
     */
    private function get_post_types(): array {
        $post_types = get_post_types(['public' => true], 'objects');
        $options = [];
        
        foreach ($post_types as $post_type) {
            if ($post_type->name !== 'attachment') {
                $options[$post_type->name] = $post_type->labels->name;
            }
        }
        
        return $options;
    }

    /**
     * Get all available taxonomies for indexing.
     */
    private function get_taxonomies(): array {
        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $options = [];
        
        foreach ($taxonomies as $taxonomy) {
            $options[$taxonomy->name] = $taxonomy->labels->name;
        }
        
        return $options;
    }
} 