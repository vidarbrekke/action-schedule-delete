<?php
/**
 * Admin class.
 *
 * @package UTG
 */

namespace UTG\Admin;

use UTG\API\LLM_API;
use UTG\Generator\Post_Generator;
use UTG\Settings;

/**
 * Admin functionality for URL to Gutenberg
 */
class UTG_Admin {
    /**
     * API instance
     *
     * @var UTG_LLM_API
     */
    private $api;
    
    /**
     * Post Generator instance
     *
     * @var UTG_Post_Generator
     */
    private $post_generator;
    
    /**
     * Settings instance
     *
     * @var UTG_Settings
     */
    private $settings;
    
    /**
     * Constructor
     *
     * @param UTG_LLM_API       $api            API instance.
     * @param UTG_Post_Generator $post_generator Post generator instance.
     * @param UTG_Settings      $settings       Settings instance.
     */
    public function __construct($api, $post_generator, $settings) {
        $this->api = $api;
        $this->post_generator = $post_generator;
        $this->settings = $settings;
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add admin menu
        \add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        \add_action('admin_init', array($this, 'register_settings'));
        
        // Add scripts and styles
        \add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add AJAX handlers
        \add_action('wp_ajax_utg_test_api_connection', array($this, 'test_api_connection'));
        \add_action('wp_ajax_utg_convert_url', array($this, 'convert_url'));
        
        // Add admin notices
        \add_action('admin_notices', array($this, 'admin_notices'));
        
        // Allow others to add hooks
        \do_action('utg_admin_init', $this);
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        \register_setting(
            'utg_settings',  // Option group
            'utg_settings',  // Option name
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array(
                    'api_key' => '',
                    'default_model' => 'openai/gpt-4-turbo',
                    'api_endpoint' => 'https://openrouter.ai/api/v1',
                    'debug_mode' => false,
                    'cache_enabled' => true,
                    'cache_expiration' => 86400, // 24 hours
                    'default_post_status' => 'draft'
                )
            )
        );
    }
    
    /**
     * Sanitize settings before saving
     * 
     * @param array $input The settings to sanitize.
     * @return array Sanitized settings.
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // API Key
        $sanitized['api_key'] = isset($input['api_key']) ? \sanitize_text_field($input['api_key']) : '';
        
        // Default Model
        $sanitized['default_model'] = isset($input['default_model']) ? \sanitize_text_field($input['default_model']) : 'openai/gpt-4-turbo';
        
        // API Endpoint
        $sanitized['api_endpoint'] = isset($input['api_endpoint']) ? \esc_url_raw($input['api_endpoint']) : 'https://openrouter.ai/api/v1';
        
        // Debug Mode (boolean)
        $sanitized['debug_mode'] = isset($input['debug_mode']) && $input['debug_mode'] ? true : false;
        
        // Cache Enabled (boolean)
        $sanitized['cache_enabled'] = isset($input['cache_enabled']) && $input['cache_enabled'] ? true : false;
        
        // Cache Expiration (integer)
        $sanitized['cache_expiration'] = isset($input['cache_expiration']) ? \absint($input['cache_expiration']) : 86400;
        
        // Default Post Status (select)
        $valid_statuses = array('draft', 'publish', 'pending', 'private');
        $sanitized['default_post_status'] = isset($input['default_post_status']) && \in_array($input['default_post_status'], $valid_statuses) ? 
            $input['default_post_status'] : 'draft';
        
        // Allow filtering of sanitized settings
        return \apply_filters('utg_sanitize_settings', $sanitized, $input);
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu
        $main_page = \add_menu_page(
            __('URL to Gutenberg', 'url-to-gutenberg'),
            __('URL to Gutenberg', 'url-to-gutenberg'),
            'manage_options',
            'url-to-gutenberg',
            array($this, 'render_url_converter_page'),
            'dashicons-admin-page',
            30
        );
        
        // Settings submenu
        $settings_page = \add_submenu_page(
            'url-to-gutenberg',
            __('Settings', 'url-to-gutenberg'),
            __('Settings', 'url-to-gutenberg'),
            'manage_options',
            'url-to-gutenberg-settings',
            array($this, 'render_settings_page')
        );
        
        // URL Test submenu
        $test_page = \add_submenu_page(
            'url-to-gutenberg',
            __('URL Test', 'url-to-gutenberg'),
            __('URL Test', 'url-to-gutenberg'),
            'manage_options',
            'url-to-gutenberg-test',
            array($this, 'render_url_test_page')
        );
        
        // Add help tabs and meta boxes when pages are loaded
        \add_action("load-$main_page", array($this, 'add_converter_help_tabs'));
        \add_action("load-$settings_page", array($this, 'add_settings_help_tabs'));
        \add_action("load-$test_page", array($this, 'add_test_help_tabs'));
        
        // Allow others to add menu items
        \do_action('utg_admin_menu');
    }
    
    /**
     * Add help tabs for the converter page
     */
    public function add_converter_help_tabs() {
        $screen = \get_current_screen();
        
        $screen->add_help_tab(array(
            'id'      => 'utg-converter-help',
            'title'   => __('URL Converter', 'url-to-gutenberg'),
            'content' => '<p>' . __('Enter a URL to convert it to a WordPress post using Gutenberg blocks.', 'url-to-gutenberg') . '</p>' .
                        '<p>' . __('The plugin will use the LLM API to analyze the page and generate a structured layout.', 'url-to-gutenberg') . '</p>',
        ));
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('For more information:', 'url-to-gutenberg') . '</strong></p>' .
            '<p><a href="https://openrouter.ai/docs" target="_blank">' . __('OpenRouter Documentation', 'url-to-gutenberg') . '</a></p>'
        );
    }
    
    /**
     * Add help tabs for the settings page
     */
    public function add_settings_help_tabs() {
        $screen = \get_current_screen();
        
        $screen->add_help_tab(array(
            'id'      => 'utg-settings-help',
            'title'   => __('API Settings', 'url-to-gutenberg'),
            'content' => '<p>' . __('Configure your LLM API credentials here.', 'url-to-gutenberg') . '</p>' .
                        '<p>' . __('You need an API key from OpenRouter or another compatible provider.', 'url-to-gutenberg') . '</p>',
        ));
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('For more information:', 'url-to-gutenberg') . '</strong></p>' .
            '<p><a href="https://openrouter.ai/docs" target="_blank">' . __('OpenRouter Documentation', 'url-to-gutenberg') . '</a></p>'
        );
    }
    
    /**
     * Add help tabs for the URL test page
     */
    public function add_test_help_tabs() {
        $screen = \get_current_screen();
        
        $screen->add_help_tab(array(
            'id'      => 'utg-test-help',
            'title'   => __('URL Test', 'url-to-gutenberg'),
            'content' => '<p>' . __('This page allows you to test the URL processing feature.', 'url-to-gutenberg') . '</p>' .
                        '<p>' . __('Enter a URL to see the raw content that would be sent to the LLM API.', 'url-to-gutenberg') . '</p>',
        ));
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('For more information:', 'url-to-gutenberg') . '</strong></p>' .
            '<p><a href="https://openrouter.ai/docs" target="_blank">' . __('OpenRouter Documentation', 'url-to-gutenberg') . '</a></p>'
        );
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Check if we're on our plugin's page
        $screen = \get_current_screen();
        if (!$screen || !\in_array($screen->id, array('toplevel_page_url-to-gutenberg', 'url-to-gutenberg_page_url-to-gutenberg-settings'))) {
            return;
        }
        
        // Check if API is configured
        if (!$this->settings->is_configured() && $screen->id === 'toplevel_page_url-to-gutenberg') {
            $settings_url = \admin_url('admin.php?page=url-to-gutenberg-settings');
            echo '<div class="notice notice-warning is-dismissible"><p>';
            printf(
                __('URL to Gutenberg Converter requires API credentials. Please <a href="%s">configure your settings</a> first.', 'url-to-gutenberg'),
                \esc_url($settings_url)
            );
            echo '</p></div>';
        }
        
        // Display any custom notices
        \do_action('utg_admin_notices', $screen);
    }
    
    /**
     * Render URL converter page
     */
    public function render_url_converter_page() {
        // Check if API key is configured
        if (!$this->settings->is_configured()) {
            echo '<div class="wrap"><h1>' . \esc_html(\get_admin_page_title()) . '</h1>';
            echo '<div class="notice notice-error"><p>';
            printf(
                __('API key not configured. Please <a href="%s">configure your API settings</a> first.', 'url-to-gutenberg'),
                \admin_url('admin.php?page=url-to-gutenberg-settings')
            );
            echo '</p></div></div>';
            return;
        }
        
        // Render the URL converter form
        require_once UTG_PLUGIN_DIR . 'includes/admin/views/url-converter.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Allow filtering of the settings page rendering
        if (apply_filters('utg_admin_render_settings', null) === false) {
            return;
        }
        
        // Create a try/catch block to handle potential errors
        try {
            // Ensure we have a settings object
            if (!isset($this->settings) || !is_object($this->settings)) {
                // Try to create a new settings object
                $this->settings = new \UTG\Settings();
            }
            
            // Explicitly extract settings to a local variable to avoid scope issues
            $settings = $this->settings;
            
            // Make sure we have a valid settings object
            if (!is_object($settings) || !method_exists($settings, 'get')) {
                throw new \Exception(__('Settings object not found or invalid', 'url-to-gutenberg'));
            }
            
            // Include the view file with settings in scope
            require_once UTG_PLUGIN_DIR . 'includes/admin/views/settings.php';
            
        } catch (\Exception $e) {
            // Display a user-friendly error message
            echo '<div class="wrap">';
            echo '<h1>' . \esc_html(\get_admin_page_title()) . '</h1>';
            echo '<div class="notice notice-error"><p>';
            printf(
                __('Error loading settings page: %s. Please contact plugin support.', 'url-to-gutenberg'),
                \esc_html($e->getMessage())
            );
            echo '</p></div>';
            
            // Add debug information in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<h3>' . __('Debug Information', 'url-to-gutenberg') . '</h3>';
                echo '<pre>';
                echo \esc_html($e->getTraceAsString());
                echo '</pre>';
                
                // Dump the state of the Admin object
                echo '<h3>' . __('Admin Object State', 'url-to-gutenberg') . '</h3>';
                echo '<pre>';
                echo \esc_html(print_r($this, true));
                echo '</pre>';
            }
            
            echo '</div>';
        }
    }
    
    /**
     * Render URL test page
     */
    public function render_url_test_page() {
        // Check if API key is configured
        if (!$this->settings->is_configured()) {
            echo '<div class="wrap"><h1>' . \esc_html(\get_admin_page_title()) . '</h1>';
            echo '<div class="notice notice-error"><p>';
            printf(
                __('API key not configured. Please <a href="%s">configure your API settings</a> first.', 'url-to-gutenberg'),
                \admin_url('admin.php?page=url-to-gutenberg-settings')
            );
            echo '</p></div></div>';
            return;
        }
        
        // Process form submission
        $url = '';
        $content = '';
        $error = '';
        
        if (isset($_POST['url']) && \check_admin_referer('utg_url_test')) {
            $url = \esc_url_raw($_POST['url']);
            
            if (empty($url)) {
                $error = __('Please enter a valid URL', 'url-to-gutenberg');
            } else {
                // Fetch the URL content
                $result = $this->api->fetch_url_content($url);
                
                if (is_wp_error($result)) {
                    $error = $result->get_error_message();
                } else {
                    $content = $result;
                }
            }
        }
        
        // Render the test form
        ?>
        <div class="wrap">
            <h1><?php echo \esc_html(\get_admin_page_title()); ?></h1>
            
            <div class="utg-test-form">
                <h2><?php \_e('Test URL Processing', 'url-to-gutenberg'); ?></h2>
                
                <?php if (!empty($error)) : ?>
                    <div class="notice notice-error">
                        <p><?php echo \esc_html($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <?php \wp_nonce_field('utg_url_test'); ?>
                    
                    <div class="utg-form-field">
                        <label for="utg-url"><?php \_e('Enter URL', 'url-to-gutenberg'); ?></label>
                        <input type="url" id="utg-url" name="url" class="regular-text" 
                               value="<?php echo \esc_attr($url); ?>" 
                               placeholder="https://example.com/page-to-test">
                        <p class="description">
                            <?php \_e('Enter the full URL of the page you want to test.', 'url-to-gutenberg'); ?>
                        </p>
                    </div>
                    
                    <div class="utg-form-actions">
                        <button type="submit" class="button button-primary">
                            <?php \_e('Fetch URL Content', 'url-to-gutenberg'); ?>
                        </button>
                    </div>
                </form>
                
                <?php if (!empty($content)) : ?>
                    <div class="utg-content-preview">
                        <h3><?php \_e('URL Content Preview', 'url-to-gutenberg'); ?></h3>
                        <p>
                            <?php 
                            printf(
                                __('Content length: %s characters', 'url-to-gutenberg'),
                                \number_format(strlen($content))
                            ); 
                            ?>
                        </p>
                        <div class="utg-content-sample">
                            <h4><?php \_e('Sample of Content (first 1000 characters):', 'url-to-gutenberg'); ?></h4>
                            <pre><?php echo \esc_html(\substr($content, 0, 1000)); ?>...</pre>
                        </div>
                        
                        <h3><?php \_e('Extracted Data', 'url-to-gutenberg'); ?></h3>
                        <?php
                        // Extract title
                        $title = '';
                        if (\preg_match('/<title[^>]*>(.*?)<\/title>/is', $content, $matches)) {
                            $title = \trim($matches[1]);
                        }
                        
                        // Extract meta description
                        $description = '';
                        if (\preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $content, $matches) ||
                            \preg_match('/<meta[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']description["\'][^>]*>/i', $content, $matches)) {
                            $description = \trim($matches[1]);
                        }
                        ?>
                        
                        <table class="widefat">
                            <tr>
                                <th><?php \_e('Title', 'url-to-gutenberg'); ?></th>
                                <td><?php echo \esc_html($title); ?></td>
                            </tr>
                            <tr>
                                <th><?php \_e('Meta Description', 'url-to-gutenberg'); ?></th>
                                <td><?php echo \esc_html($description); ?></td>
                            </tr>
                        </table>
                        
                        <h3><?php \_e('Process This URL', 'url-to-gutenberg'); ?></h3>
                        <p>
                            <?php \_e('You can now process this URL to create a WordPress post:', 'url-to-gutenberg'); ?>
                        </p>
                        <form method="post" action="<?php echo \admin_url('admin.php?page=url-to-gutenberg'); ?>">
                            <input type="hidden" name="utg-url" value="<?php echo \esc_attr($url); ?>">
                            <?php \wp_nonce_field('utg_convert_url'); ?>
                            <button type="submit" class="button button-primary">
                                <?php \_e('Process URL', 'url-to-gutenberg'); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Test API connection (AJAX handler)
     */
    public function test_api_connection() {
        // Verify nonce
        $this->verify_ajax_nonce();
        
        // Test API connection
        $result = $this->api->test_connection();
        
        if (is_wp_error($result)) {
            \wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        \wp_send_json_success(array('message' => __('Connection successful!', 'url-to-gutenberg')));
    }
    
    /**
     * Convert URL to Gutenberg blocks.
     */
    public function convert_url() {
        try {
            error_log('UTG: Starting URL conversion process');

            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'utg_convert_url')) {
                error_log('UTG: Nonce verification failed');
                wp_send_json_error('Invalid nonce');
                return;
            }

            // Check user permissions
            if (!current_user_can('edit_posts')) {
                error_log('UTG: User does not have required permissions');
                wp_send_json_error('Insufficient permissions');
                return;
            }

            // Get URL from POST data
            $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
            if (empty($url)) {
                error_log('UTG: Empty URL provided');
                wp_send_json_error('URL cannot be empty');
                return;
            }

            error_log('UTG: Processing URL: ' . $url);

            // Process URL
            $api = new \UTG\API\LLM_API($this->settings);
            $response = $api->process_url($url);

            error_log('UTG: API Response: ' . print_r($response, true));

            if (is_wp_error($response)) {
                error_log('UTG: API Error: ' . $response->get_error_message());
                wp_send_json_error($response->get_error_message());
                return;
            }

            // Create post
            $post_id = wp_insert_post([
                'post_title' => $response['title'],
                'post_content' => $response['content'],
                'post_status' => 'draft',
                'post_type' => 'post'
            ]);

            if (is_wp_error($post_id)) {
                error_log('UTG: Post creation error: ' . $post_id->get_error_message());
                wp_send_json_error('Failed to create post: ' . $post_id->get_error_message());
                return;
            }

            error_log('UTG: Post created successfully with ID: ' . $post_id);

            wp_send_json_success([
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw')
            ]);

        } catch (\Exception $e) {
            error_log('UTG: Exception in convert_url: ' . $e->getMessage());
            error_log('UTG: Exception trace: ' . $e->getTraceAsString());
            wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify AJAX nonce
     */
    private function verify_ajax_nonce() {
        if (!isset($_POST['nonce']) || !\wp_verify_nonce($_POST['nonce'], 'utg_ajax_nonce')) {
            \wp_send_json_error(array('message' => __('Security check failed', 'url-to-gutenberg')));
            exit;
        }
        
        // Check capabilities
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'url-to-gutenberg')));
            exit;
        }
    }
    
    /**
     * Check if current page is a plugin page
     *
     * @param string $hook Current admin page hook.
     * @return bool Whether the current page is a plugin page.
     */
    public function is_plugin_page($hook) {
        $plugin_pages = array(
            'toplevel_page_url-to-gutenberg',
            'url-to-gutenberg_page_url-to-gutenberg-settings',
            'url-to-gutenberg_page_url-to-gutenberg-test',
        );
        
        // Allow filtering of plugin pages
        $plugin_pages = \apply_filters('utg_plugin_pages', $plugin_pages);
        
        return \in_array($hook, $plugin_pages);
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook The current admin page.
     */
    public function enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (!$this->is_plugin_page($hook)) {
            return;
        }
        
        // Enqueue CSS
        \wp_enqueue_style(
            'utg-admin-css',
            UTG_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            UTG_VERSION
        );
        
        // Enqueue JavaScript
        \wp_enqueue_script(
            'utg-admin-js',
            UTG_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            UTG_VERSION,
            true
        );
        
        // Get debug mode setting
        $debug_mode = (bool) $this->settings->get('debug_mode', false);
        
        // Add script parameters
        \wp_localize_script('utg-admin-js', 'utgParams', array(
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('utg_ajax_nonce'),
            'testingText' => \__('Testing connection...', 'url-to-gutenberg'),
            'successText' => \__('Connection successful!', 'url-to-gutenberg'),
            'errorText' => \__('Error: ', 'url-to-gutenberg'),
            'debugMode' => $debug_mode,
            'i18n' => array(
                'processingUrl' => \__('Processing URL...', 'url-to-gutenberg'),
                'enterValidUrl' => \__('Please enter a valid URL', 'url-to-gutenberg'),
                'securityError' => \__('Security check failed', 'url-to-gutenberg'),
                'serverError' => \__('Could not connect to the server. Please try again.', 'url-to-gutenberg')
            )
        ));
    }
} 