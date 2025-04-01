<?php
/**
 * Main plugin class.
 *
 * @package UTG
 */

namespace UTG;

use UTG\API\LLM_API;
use UTG\Content\Content_Optimizer;
use UTG\Templates\UTG_Template_Manager as Template_Manager;
use UTG\Admin\UTG_Admin;
use UTG\Generator\Post_Generator;
use UTG\Generator\Media_Handler;
use UTG\Generator\UTG_Content_Optimizer;

/**
 * Class URL_To_Gutenberg
 * 
 * Main plugin class that ties together all components.
 */
class URL_To_Gutenberg {

    /**
     * Plugin version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Settings instance.
     *
     * @var Settings
     */
    private $settings;

    /**
     * Content extractor instance.
     *
     * @var Content_Extractor
     */
    private $extractor;

    /**
     * LLM API instance.
     *
     * @var LLM_API
     */
    private $llm_api;

    /**
     * Content optimizer instance.
     *
     * @var Content_Optimizer
     */
    private $optimizer;

    /**
     * Template manager instance.
     *
     * @var Template_Manager
     */
    private $template_manager;

    /**
     * Admin instance.
     *
     * @var UTG_Admin
     */
    private $admin;

    /**
     * Post generator instance.
     *
     * @var Post_Generator
     */
    private $post_generator;

    /**
     * Media handler instance.
     *
     * @var Media_Handler
     */
    private $media_handler;

    /**
     * Plugin instance.
     *
     * @var self
     */
    private static $instance = null;

    /**
     * Get plugin instance.
     *
     * @return self Plugin instance.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        // Initialize plugin components
        $this->init();
        
        // Register hooks
        $this->register_hooks();
    }

    /**
     * Initialize plugin components.
     */
    private function init() {
        // Load settings
        $this->settings = new Settings();
        
        // Initialize content extractor
        $this->extractor = new Content_Extractor($this->settings);
        
        // Initialize content optimizer
        $this->optimizer = new Generator\UTG_Content_Optimizer($this->settings);
        
        // Initialize LLM API
        $this->llm_api = new API\LLM_API($this->settings, $this->extractor, $this->optimizer);
        
        // Initialize template manager
        $this->template_manager = new Templates\UTG_Template_Manager();
        
        // Initialize media handler
        $this->media_handler = new Generator\UTG_Media_Handler();
        
        // Initialize post generator
        $this->post_generator = new Generator\Post_Generator($this->media_handler);
        
        // Initialize admin interface
        $this->admin = new UTG_Admin($this->llm_api, $this->post_generator, $this->settings);
    }

    /**
     * Register WordPress hooks.
     */
    private function register_hooks() {
        // Activation hook
        \register_activation_hook( UTG_PLUGIN_FILE, [ $this, 'activate' ] );
        
        // Deactivation hook
        \register_deactivation_hook( UTG_PLUGIN_FILE, [ $this, 'deactivate' ] );
        
        // Admin menu
        \add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        
        // Admin scripts and styles
        \add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // AJAX actions
        \add_action( 'wp_ajax_utg_process_url', [ $this, 'ajax_process_url' ] );
        
        // Add settings link to plugin page
        \add_filter( 'plugin_action_links_' . \plugin_basename( UTG_PLUGIN_FILE ), [ $this, 'add_settings_link' ] );
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Create default settings
        $this->settings->load();
        $this->settings->save();
        
        // Ensure dependencies
        $this->check_dependencies();
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Nothing specific to do on deactivation at this point
    }

    /**
     * Check plugin dependencies.
     */
    private function check_dependencies() {
        $missing = [];
        
        // Check for Composer dependencies
        if ( ! class_exists( 'fivefilters\\Readability\\Readability' ) ) {
            $missing[] = 'fivefilters/readability';
        }
        
        if ( ! class_exists( 'Symfony\\Component\\Panther\\Client' ) ) {
            $missing[] = 'symfony/panther';
        }
        
        // Log dependency issues
        if ( ! empty( $missing ) ) {
            \error_log( 'URL to Gutenberg: Missing dependencies: ' . implode( ', ', $missing ) );
            \add_action( 'admin_notices', function() use ( $missing ) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <?php \esc_html_e( 'URL to Gutenberg is missing required dependencies:', 'url-to-gutenberg' ); ?>
                        <strong><?php echo \esc_html( implode( ', ', $missing ) ); ?></strong>
                    </p>
                    <p>
                        <?php \esc_html_e( 'Please run composer install in the plugin directory.', 'url-to-gutenberg' ); ?>
                    </p>
                </div>
                <?php
            } );
        }
    }

    /**
     * Add admin menu items.
     */
    public function add_admin_menu() {
        \add_menu_page(
            \__( 'URL to Gutenberg', 'url-to-gutenberg' ),
            \__( 'URL to Gutenberg', 'url-to-gutenberg' ),
            'edit_posts',
            'url-to-gutenberg',
            [ $this, 'render_main_page' ],
            'dashicons-clipboard'
        );
        
        \add_submenu_page(
            'url-to-gutenberg',
            \__( 'Settings', 'url-to-gutenberg' ),
            \__( 'Settings', 'url-to-gutenberg' ),
            'manage_options',
            'url-to-gutenberg-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( ! in_array( $hook, [ 'toplevel_page_url-to-gutenberg', 'url-to-gutenberg_page_url-to-gutenberg-settings' ] ) ) {
            return;
        }
        
        \wp_enqueue_style(
            'utg-admin-style',
            plugin_dir_url( UTG_PLUGIN_FILE ) . 'assets/css/admin.css',
            [],
            self::VERSION
        );
        
        \wp_enqueue_script(
            'utg-admin-script',
            plugin_dir_url( UTG_PLUGIN_FILE ) . 'assets/js/admin.js',
            [ 'jquery' ],
            self::VERSION,
            true
        );
        
        \wp_localize_script( 'utg-admin-script', 'utg_vars', [
            'ajax_url' => \admin_url( 'admin-ajax.php' ),
            'nonce' => \wp_create_nonce( 'utg_nonce' ),
            'processing_text' => \__( 'Processing URL...', 'url-to-gutenberg' ),
            'error_text' => \__( 'An error occurred:', 'url-to-gutenberg' ),
        ] );
    }

    /**
     * Render main plugin page.
     */
    public function render_main_page() {
        include plugin_dir_path( UTG_PLUGIN_FILE ) . 'templates/main-page.php';
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        // Save settings if form submitted
        if ( isset( $_POST['utg_save_settings'] ) && check_admin_referer( 'utg_settings_nonce', 'utg_settings_nonce' ) ) {
            $settings = [];
            
            // API Key
            if ( isset( $_POST['utg_api_key'] ) ) {
                $settings['api_key'] = sanitize_text_field( $_POST['utg_api_key'] );
            }
            
            // API Base URL
            if ( isset( $_POST['utg_api_base_url'] ) ) {
                $settings['api_base_url'] = sanitize_text_field( $_POST['utg_api_base_url'] );
            }
            
            // Default Model
            if ( isset( $_POST['utg_default_model'] ) ) {
                $settings['default_model'] = sanitize_text_field( $_POST['utg_default_model'] );
            }
            
            // Debug Mode
            $settings['debug_mode'] = isset( $_POST['utg_debug_mode'] );
            
            // Min Content Length
            if ( isset( $_POST['utg_min_content_length'] ) ) {
                $settings['min_content_length'] = absint( $_POST['utg_min_content_length'] );
            }
            
            // Min Image Count
            if ( isset( $_POST['utg_min_image_count'] ) ) {
                $settings['min_image_count'] = absint( $_POST['utg_min_image_count'] );
            }
            
            // Save settings
            $this->settings->update( $settings );
            
            // Show success message
            \add_settings_error(
                'utg_settings',
                'utg_settings_saved',
                \__( 'Settings saved successfully.', 'url-to-gutenberg' ),
                'updated'
            );
        }
        
        include plugin_dir_path( UTG_PLUGIN_FILE ) . 'templates/settings-page.php';
    }

    /**
     * AJAX handler for processing a URL.
     */
    public function ajax_process_url() {
        // Verify nonce
        if ( ! \wp_verify_nonce( $_POST['nonce'], 'utg_nonce' ) ) {
            \wp_send_json_error( [ 'message' => \__( 'Security check failed.', 'url-to-gutenberg' ) ] );
            return;
        }
        
        // Check URL
        if ( empty( $_POST['url'] ) ) {
            \wp_send_json_error( [ 'message' => \__( 'URL is required.', 'url-to-gutenberg' ) ] );
            return;
        }
        
        $url = sanitize_text_field( $_POST['url'] );
        
        // Extract content
        $content = $this->extractor->extract( $url );
        
        // Check for extraction errors
        if ( \is_wp_error( $content ) ) {
            \wp_send_json_error( [ 'message' => $content->get_error_message() ] );
            return;
        }
        
        // Process with LLM
        $processed = $this->llm_api->process_content( $url, $content );
        
        // Check for LLM errors
        if ( \is_wp_error( $processed ) ) {
            \wp_send_json_error( [ 'message' => $processed->get_error_message() ] );
            return;
        }
        
        // Create draft post with the blocks
        $post_id = $this->create_draft_post( $content['title'], $processed['blocks'] );
        
        if ( \is_wp_error( $post_id ) ) {
            \wp_send_json_error( [ 'message' => $post_id->get_error_message() ] );
            return;
        }
        
        \wp_send_json_success( [
            'post_id' => $post_id,
            'edit_url' => \get_edit_post_link( $post_id, 'raw' ),
            'message' => \__( 'Content converted and draft created successfully.', 'url-to-gutenberg' ),
        ] );
    }

    /**
     * Create a draft post with Gutenberg blocks.
     *
     * @param string $title  Post title.
     * @param array  $blocks Gutenberg blocks.
     * @return int|\WP_Error Post ID or error.
     */
    private function create_draft_post( $title, $blocks ) {
        // Convert blocks to post content
        $post_content = '';
        
        if ( ! empty( $blocks ) ) {
            $post_content = \serialize_blocks( $blocks );
        }
        
        // Create post
        $post_data = [
            'post_title'    => $title,
            'post_content'  => $post_content,
            'post_status'   => 'draft',
            'post_type'     => 'post',
            'post_author'   => \get_current_user_id(),
        ];
        
        $post_id = \wp_insert_post( $post_data, true );
        
        return $post_id;
    }

    /**
     * Add settings link to plugin page.
     *
     * @param array $links Plugin action links.
     * @return array Modified plugin action links.
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=url-to-gutenberg-settings' ),
            \__( 'Settings', 'url-to-gutenberg' )
        );
        
        array_unshift( $links, $settings_link );
        
        return $links;
    }

    /**
     * Get plugin settings instance.
     *
     * @return Settings Settings instance.
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get content extractor instance.
     *
     * @return Content_Extractor Content extractor instance.
     */
    public function get_extractor() {
        return $this->extractor;
    }

    /**
     * Get LLM API instance.
     *
     * @return LLM_API LLM API instance.
     */
    public function get_llm_api() {
        return $this->llm_api;
    }
} 