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
        
        // Render the URL converter form using the new template
        require_once UTG_PLUGIN_DIR . 'includes/admin/page-converter.php';
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
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $content = '';
        $error = '';
        
        // Process form submission
        if (isset($_POST['url']) && \check_admin_referer('utg_ajax_nonce')) {
            try {
                $url = sanitize_text_field($_POST['url']);
                
                // Log the URL testing attempt
                error_log('UTG: Testing URL: ' . $url);
                
                // Get content directly using our helper method instead of Content_Extractor
                $html_content = $this->get_url_content($url);
                
                if (!$html_content) {
                    error_log('UTG: Failed to retrieve content from URL in test page');
                    $error = __('Failed to retrieve content from the URL. The site may be blocking access or unavailable.', 'url-to-gutenberg');
            } else {
                    // Use our direct DOM extraction method which doesn't require the Content_Extractor class
                    $content = $this->extract_content_with_dom($html_content);
                
                    if (empty($content)) {
                        error_log('UTG: No content could be extracted from the URL in test page');
                        $error = __('No content could be extracted from this URL.', 'url-to-gutenberg');
                } else {
                        error_log('UTG: Successfully extracted content in test page, length: ' . strlen($content));
                }
                }
            } catch (\Exception $e) {
                error_log('UTG: Exception in URL test page: ' . $e->getMessage());
                $error = $e->getMessage();
            }
        }
        
        // We don't need settings here as we're using direct extraction methods
        ?>
        <div class="wrap">
            <h1><?php echo \esc_html(\get_admin_page_title()); ?></h1>
            <form method="post" action="">
                <?php \wp_nonce_field('utg_ajax_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="url"><?php \_e('URL to Test', 'url-to-gutenberg'); ?></label></th>
                        <td>
                            <input name="url" type="text" id="url" value="" class="regular-text">
                            <p class="description"><?php \_e('Enter a URL to test content extraction', 'url-to-gutenberg'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Test URL', 'url-to-gutenberg')); ?>
            </form>
            
            <?php if ($error): ?>
            <div class="error"><p><?php echo \esc_html($error); ?></p></div>
                <?php endif; ?>
                
            <?php if ($content): ?>
            <h2><?php \_e('Extracted Content', 'url-to-gutenberg'); ?></h2>
                    <div class="utg-content-preview">
                <?php echo \wp_kses_post($content); ?>
                    </div>
                <?php endif; ?>
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
     * AJAX handler for converting URLs to Gutenberg blocks
     */
    public function convert_url() {
        // Verify nonce
        $this->verify_ajax_nonce();
        
        // Get URL and parse_only flag
        $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
        $parse_only = isset($_POST['parse_only']) && $_POST['parse_only'] === 'true';
        $cleaning_level = isset($_POST['cleaning_level']) ? sanitize_text_field($_POST['cleaning_level']) : 'standard';
        
        // Validate cleaning level (only accept valid values)
        if (!in_array($cleaning_level, ['standard', 'medium', 'aggressive'])) {
            $cleaning_level = 'standard'; // Default to standard if invalid value provided
        }
        
        error_log('UTG AJAX: Request params - URL: ' . $url . ', Parse only: ' . ($parse_only ? 'true' : 'false') . ', Cleaning level: ' . $cleaning_level);
        
        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            \wp_send_json_error(__('Please enter a valid URL', 'url-to-gutenberg'));
            return;
        }
        
        // Store original debug setting
        $debug_setting = $this->settings->get('debug_mode');
        
        // Always enable debug mode for the AJAX operation
        $this->settings->update(['debug_mode' => true]);
        
        // Create debug directory if it doesn't exist
        $debug_dir = WP_CONTENT_DIR . '/uploads/utg-debug';
        if (!file_exists($debug_dir)) {
            if (!mkdir($debug_dir, 0755, true)) {
                error_log('UTG: Failed to create debug directory: ' . $debug_dir);
            } else {
                error_log('UTG: Created debug directory: ' . $debug_dir);
            }
        }
        
        // For parse-only operations, we'll use a simplified approach
        if ($parse_only) {
            try {
                error_log('UTG AJAX: Starting parse-only extraction for URL: ' . $url);
                
                // Get HTML content using our helper method
                $content = $this->get_url_content($url);
                if (!$content) {
                    error_log('UTG AJAX: Failed to retrieve content from URL');
                    \wp_send_json_error(__('Failed to retrieve content from the URL. The site may be blocking access or unavailable.', 'url-to-gutenberg'));
                    $this->settings->update(['debug_mode' => $debug_setting]);
                return;
            }

                // Create a Content_Extractor instance with settings
                $extractor = new \UTG\Content_Extractor($this->settings);
                
                // Save the raw content to debug directory with proper UTF-8 encoding
                $debug_file = $debug_dir . '/' . $extractor->get_debug_filename($url, 'raw_html');
                // Add UTF-8 BOM (Byte Order Mark) for better encoding recognition
                $utf8_bom = chr(239) . chr(187) . chr(191); // UTF-8 BOM
                @file_put_contents($debug_file, $utf8_bom . $content);
                error_log('UTG AJAX: Raw content saved to: ' . $debug_file);
                
                error_log('UTG AJAX: Retrieved content, length: ' . strlen($content) . ' bytes');
                
                // Extract content with the appropriate cleaning level
                error_log('UTG AJAX: Beginning content extraction' . ($cleaning_level !== 'standard' ? ' with cleaning level: ' . $cleaning_level : ''));
                $extracted = $extractor->extract($url, $cleaning_level);
                
                if (is_wp_error($extracted)) {
                    error_log('UTG AJAX: Content extraction failed: ' . $extracted->get_error_message());
                    \wp_send_json_error(__('Content extraction failed: ', 'url-to-gutenberg') . $extracted->get_error_message());
                    $this->settings->update(['debug_mode' => $debug_setting]);
                return;
            }

                // First, save the raw extracted content (before any cleaning was applied)
                if (!empty($extracted['raw_extracted_content'])) {
                    $raw_extracted_content = $extracted['raw_extracted_content'];
                    $extracted_file = $debug_dir . '/' . $extractor->get_debug_filename($url, 'extracted_article');
                    @file_put_contents($extracted_file, $utf8_bom . $raw_extracted_content);
                    error_log('UTG AJAX: Raw extracted article saved to: ' . $extracted_file);
                }
                
                // Get the final cleaned content
                $extracted_content = $extracted['content'];
                
                if (!$extracted_content || empty($extracted_content)) {
                    error_log('UTG AJAX: No content could be extracted with Content_Extractor');
                    
                    // Try our fallback DOM extraction method
                    error_log('UTG AJAX: Trying fallback DOMDocument extraction');
                    $extracted_content = $this->extract_content_with_dom($content);
                    
                    if (!$extracted_content || empty($extracted_content)) {
                        error_log('UTG AJAX: No content could be extracted with either method');
                        \wp_send_json_error(__('No content could be extracted from this URL.', 'url-to-gutenberg'));
                        $this->settings->update(['debug_mode' => $debug_setting]);
                return;
                    }
                }
                
                // Save the final content with proper UTF-8 encoding using the standardized naming convention
                // Only save this file if we're using the fallback extraction method, not the main Content_Extractor
                // since the Content_Extractor already saves its own debug file with '_cleaned_article' suffix
                if (!isset($extracted) || is_wp_error($extracted)) {
                    $debug_file = $debug_dir . '/' . $extractor->get_debug_filename($url, $cleaning_level . '_cleaned_content');
                    @file_put_contents($debug_file, $utf8_bom . $extracted_content);
                    error_log('UTG AJAX: Fallback ' . ucfirst($cleaning_level) . ' cleaned content saved to: ' . $debug_file);
                } else {
                    // Use the existing debug file that was already created by Content_Extractor
                    $debug_file = $debug_dir . '/' . $extractor->get_debug_filename($url, $cleaning_level . '_cleaned_article');
                }
                
                error_log('UTG AJAX: Content extraction successful, content length: ' . strlen($extracted_content) . ' bytes');
                
                // Process the extracted content to make it more readable
                $preview = strip_tags($extracted_content);
                $preview = substr($preview, 0, 500) . '...';
                
                // Create success response
                $response_data = [
                    'message' => __('Content successfully extracted', 'url-to-gutenberg'),
                    'content' => $extracted_content,
                    'preview' => $preview,
                    'debug_file' => basename($debug_file)
                ];
                
                error_log('UTG AJAX: Sending success response');
                \wp_send_json_success($response_data);
                
            } catch (\Exception $e) {
                error_log('UTG AJAX: Exception during extraction process: ' . $e->getMessage());
                error_log('UTG AJAX: Exception trace: ' . $e->getTraceAsString());
                \wp_send_json_error(__('Error processing content: ', 'url-to-gutenberg') . $e->getMessage());
            } finally {
                // Restore original debug setting
                $this->settings->update(['debug_mode' => $debug_setting]);
            }
                return;
            }

        // Original LLM conversion logic for non-parse-only mode
        try {
            error_log('UTG AJAX: Starting URL conversion for: ' . $url);
            
            // Get HTML content using our helper method
            $content = $this->get_url_content($url);
            if (!$content) {
                error_log('UTG AJAX: Failed to retrieve content from URL');
                \wp_send_json_error(__('Failed to retrieve content from the URL. The site may be blocking access or unavailable.', 'url-to-gutenberg'));
                $this->settings->update(['debug_mode' => $debug_setting]);
                return;
            }

            // Use the content extractor to get the relevant part of the page
            $extractor = new \UTG\Content_Extractor($this->settings);
            
            // Save the raw content to debug directory
            $debug_file = $debug_dir . '/' . $extractor->get_debug_filename($url, 'raw_html');
            $utf8_bom = chr(239) . chr(187) . chr(191); // UTF-8 BOM
            @file_put_contents($debug_file, $utf8_bom . $content);
            error_log('UTG AJAX: Raw content saved to: ' . $debug_file);
            
            error_log('UTG AJAX: Retrieved content, length: ' . strlen($content) . ' bytes');
            
            $extracted = $extractor->extract($url, $cleaning_level);
            
            if (is_wp_error($extracted)) {
                error_log('UTG AJAX: Content extraction failed: ' . $extracted->get_error_message());
                
                // Try our fallback extraction method
                error_log('UTG AJAX: Trying fallback DOMDocument extraction');
                $extracted_content = $this->extract_content_with_dom($content);
                
                if (!$extracted_content || empty($extracted_content)) {
                    error_log('UTG AJAX: Fallback extraction also failed');
                    \wp_send_json_error(__('No content could be extracted from this URL.', 'url-to-gutenberg'));
                    $this->settings->update(['debug_mode' => $debug_setting]);
                    return;
                }
            } else {
                // First, save the raw extracted content (before any cleaning was applied)
                if (!empty($extracted['raw_extracted_content'])) {
                    $raw_extracted_content = $extracted['raw_extracted_content'];
                    $extracted_file = $debug_dir . '/' . $extractor->get_debug_filename($url, 'extracted_article');
                    @file_put_contents($extracted_file, $utf8_bom . $raw_extracted_content);
                    error_log('UTG AJAX: Raw extracted article saved to: ' . $extracted_file);
                }
                
                // Get the final cleaned content
                $extracted_content = $extracted['content'];
            }
            
            // Save the final content with proper UTF-8 encoding using the standardized naming convention
            // Only save this file if we're using the fallback extraction method, not the main Content_Extractor
            // since the Content_Extractor already saves its own debug file with '_cleaned_article' suffix
            if (!isset($extracted) || is_wp_error($extracted)) {
                $debug_file = $debug_dir . '/' . $extractor->get_debug_filename($url, $cleaning_level . '_cleaned_content');
                @file_put_contents($debug_file, $utf8_bom . $extracted_content);
                error_log('UTG AJAX: Fallback ' . ucfirst($cleaning_level) . ' cleaned content saved to: ' . $debug_file);
            } else {
                // Use the existing debug file that was already created by Content_Extractor
                $debug_file = $debug_dir . '/' . $extractor->get_debug_filename($url, $cleaning_level . '_cleaned_article');
            }
            
            error_log('UTG AJAX: Content extraction successful, content length: ' . strlen($extracted_content) . ' bytes');
            
            // Use API for extraction
            $api = new \UTG\API\LLM_API($this->settings);
            
            // Get the default model from settings
            $model = $this->settings->get('default_model', 'gpt-4o');
            error_log('UTG AJAX: Using model from settings: ' . $model);
            
            $prompt = 'Convert the following HTML content into WordPress Gutenberg blocks. ' .
                      'Preserve the meaning, formatting, and structure of the content. ' .
                      'Content to convert: ' . $extracted_content;
            
            $result = $api->send_request($prompt);
            
            if (\is_wp_error($result)) {
                error_log('UTG AJAX: API error: ' . $result->get_error_message());
                \wp_send_json_error(__('API Error: ', 'url-to-gutenberg') . $result->get_error_message());
                $this->settings->update(['debug_mode' => $debug_setting]);
                return;
            }
            
            // Get the response content
            $converted_content = $result['content'];
            
            // Save the converted content to a debug file
            $debug_file = $debug_dir . '/converted_content_' . uniqid() . '.html';
            @file_put_contents($debug_file, $converted_content);
            error_log('UTG AJAX: Converted content saved to: ' . $debug_file);
            
            // Create a preview version
            $preview = substr(strip_tags($converted_content), 0, 300) . '...';
            
            // Send the success response
            \wp_send_json_success([
                'message' => __('URL successfully converted to Gutenberg blocks', 'url-to-gutenberg'),
                'content' => $converted_content,
                'preview' => $preview,
                'debug_file' => basename($debug_file),
                'model_used' => $model
            ]);

        } catch (\Exception $e) {
            error_log('UTG AJAX: Exception during conversion process: ' . $e->getMessage());
            error_log('UTG AJAX: Exception trace: ' . $e->getTraceAsString());
            \wp_send_json_error(__('Error converting URL: ', 'url-to-gutenberg') . $e->getMessage());
        } finally {
            // Restore original debug setting
            $this->settings->update(['debug_mode' => $debug_setting]);
        }
    }
    
    /**
     * Extract content using DOMDocument as a fallback method
     * 
     * @param string $html The HTML content to extract from
     * @return string The extracted content
     */
    private function extract_content_with_dom($html) {
        // Backup libxml error handling state
        $internal_errors = libxml_use_internal_errors(true);
        
        try {
            error_log('UTG DOM: Using DOMDocument for extraction');
            
            // Create a new DOMDocument
            $dom = new \DOMDocument('1.0', 'UTF-8');
            
            // Load the HTML content with proper encoding
            error_log('UTG DOM: Loading HTML into DOMDocument');
            
            // Add UTF-8 meta tag if not present to help with encoding
            if (strpos($html, '<meta charset="utf-8"') === false && 
                strpos($html, '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"') === false) {
                $html = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html;
            }
            
            // Load with LIBXML_HTML_NOIMPLIED to prevent adding extra tags
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            
            // Check for parse errors
            $errors = libxml_get_errors();
            if (!empty($errors)) {
                error_log('UTG DOM: ' . count($errors) . ' parsing errors found, continuing anyway');
                libxml_clear_errors();
            }
            
            // Remove comment nodes
            $xpath = new \DOMXPath($dom);
            $comments = $xpath->query('//comment()');
            foreach ($comments as $comment) {
                $comment->parentNode->removeChild($comment);
            }
            
            // Extract content
            $body = $dom->getElementsByTagName('body')->item(0);
            
            if (!$body) {
                error_log('UTG DOM: No body tag found in HTML');
                // Try extracting what we can without a body tag
                return $this->fallback_extraction($html);
            }
            
            // Try to find main content (common content containers)
            $content_containers = array(
                'article',
                'main',
                'div[id="content"]',
                'div[class="content"]',
                'div[class*="content"]',
                'div[id="main"]',
                'div[class="main"]',
                'div[class*="article"]',
                'div[class*="post"]'
            );
            
            error_log('UTG DOM: Searching for content containers');
            $content = '';
            $content_element = null;
            
            foreach ($content_containers as $container) {
                error_log('UTG DOM: Trying to find: ' . $container);
                $xpath = new \DOMXPath($dom);
                
                if (strpos($container, '[') !== false) {
                    // Handle attribute selectors
                    list($tag, $attr) = explode('[', $container);
                    $attr = str_replace(']', '', $attr);
                    list($attr_name, $attr_value) = explode('=', $attr);
                    
                    // Handle wildcard attribute selectors
                    if (strpos($attr_value, '*') !== false) {
                        $attr_value = str_replace('"', '', $attr_value);
                        $attr_value = str_replace('*', '', $attr_value);
                        $nodes = $xpath->query("//{$tag}[contains(@{$attr_name}, '{$attr_value}')]");
                    } else {
                        $attr_value = str_replace('"', '', $attr_value);
                        $nodes = $xpath->query("//{$tag}[@{$attr_name}='{$attr_value}']");
                    }
                } else {
                    // Simple tag selector
                    $nodes = $xpath->query("//{$container}");
                }
                
                if ($nodes && $nodes->length > 0) {
                    error_log('UTG DOM: Found content in ' . $container . ' (' . $nodes->length . ' nodes)');
                    $content_element = $nodes->item(0);
                    break;
                }
            }
            
            // If no specific container found, use the first div with substantial content
            if (!$content_element) {
                error_log('UTG DOM: No specific content container found, looking for substantial divs');
                $divs = $xpath->query('//div');
                
                $best_div = null;
                $best_score = 0;
                
                foreach ($divs as $div) {
                    // Skip divs that are likely to be navigation, sidebar, etc.
                    $class = $div->getAttribute('class');
                    $id = $div->getAttribute('id');
                    
                    if (preg_match('/(nav|menu|header|footer|sidebar|comment|widget)/i', $class . ' ' . $id)) {
                        continue;
                    }
                    
                    // Score based on content length and presence of paragraphs
                    $paragraphs = $xpath->query('.//p', $div);
                    $text_length = strlen($div->textContent);
                    $score = $text_length + ($paragraphs->length * 100);
                    
                    if ($score > $best_score && $text_length > 200) {
                        $best_score = $score;
                        $best_div = $div;
                    }
                }
                
                if ($best_div) {
                    error_log('UTG DOM: Found best div content with score: ' . $best_score);
                    $content_element = $best_div;
                } else {
                    // If still no good div, use body as last resort
                    $content_element = $body;
                }
            }
            
            // Now we have the main content element, let's clean it up
            if ($content_element) {
                // Remove unwanted elements
                $this->remove_unwanted_elements($content_element, $xpath);
                
                // Get the cleaned HTML
                $content = $dom->saveHTML($content_element);
            } else {
                // Use the body content as a last resort
                $this->remove_unwanted_elements($body, $xpath);
                $content = $dom->saveHTML($body);
            }
            
            // Fix character encoding issues
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // Replace problematic character sequences
            $replacements = array(
                'â€™' => "'",  // Right single quotation mark
                'â€"' => "—",  // Em dash
                'â€œ' => '"',  // Left double quotation mark
                'â€' => '"',   // Right double quotation mark
                'â€¦' => '…',  // Ellipsis
                'â€˜' => "'",  // Left single quotation mark
                'â€¢' => '•',  // Bullet
                'â€º' => '›',  // Single right-pointing angle quotation mark
                'â€¹' => '‹',  // Single left-pointing angle quotation mark
                'Â' => ' '     // Non-breaking space
            );
            
            $content = str_replace(array_keys($replacements), array_values($replacements), $content);
            
            // If content is empty or just whitespace, try fallback method
            if (trim(strip_tags($content)) === '') {
                error_log('UTG DOM: Extracted content was empty, trying fallback method');
                return $this->fallback_extraction($html);
            }
            
            // Final cleanup
            $content = preg_replace('/^\s*<!DOCTYPE[^>]*>/i', '', $content); // Remove doctype
            
            return $content;
            
        } catch (\Exception $e) {
            error_log('UTG DOM: Error in DOMDocument extraction: ' . $e->getMessage());
            return $this->fallback_extraction($html);
        } finally {
            // Restore libxml error handling state
            libxml_use_internal_errors($internal_errors);
        }
    }
    
    /**
     * Remove unwanted elements from a DOM node
     * 
     * @param \DOMNode $node The node to clean
     * @param \DOMXPath $xpath XPath object for queries
     */
    private function remove_unwanted_elements($node, $xpath) {
        // Elements to remove by tag name
        $unwanted_tags = array(
            'script', 'style', 'iframe', 'noscript', 'form',
            'header', 'footer', 'nav', 'aside', 'object', 'embed',
            'select', 'button', 'meta', 'link', 'head'
        );
        
        // Remove elements by tag name
        foreach ($unwanted_tags as $tag) {
            $elements = $xpath->query('.//' . $tag, $node);
            $this->remove_nodes($elements);
        }
        
        // Remove elements by class/id patterns
        $patterns = array(
            'contains(@class, "nav")', 
            'contains(@class, "menu")',
            'contains(@class, "sidebar")',
            'contains(@class, "footer")',
            'contains(@class, "header")',
            'contains(@class, "banner")',
            'contains(@class, "ad-")',
            'contains(@class, "widget")',
            'contains(@class, "share")',
            'contains(@class, "social")',
            'contains(@class, "comment")',
            'contains(@id, "nav")',
            'contains(@id, "menu")',
            'contains(@id, "sidebar")',
            'contains(@id, "footer")',
            'contains(@id, "header")',
            'contains(@id, "banner")',
            'contains(@id, "ad-")'
        );
        
        $query = './/*[' . implode(' or ', $patterns) . ']';
        $elements = $xpath->query($query, $node);
        $this->remove_nodes($elements);
    }
    
    /**
     * Helper method to safely remove nodes from a NodeList
     * 
     * @param \DOMNodeList $nodes Nodes to remove
     */
    private function remove_nodes($nodes) {
        $to_remove = array();
        
        // First collect all nodes
        foreach ($nodes as $node) {
            $to_remove[] = $node;
        }
        
        // Then remove them (to avoid issues with the live NodeList)
        foreach ($to_remove as $node) {
            if ($node && $node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
    }
    
    /**
     * Fallback extraction method for when DOMDocument fails
     * 
     * @param string $html The HTML content
     * @return string Extracted content
     */
    private function fallback_extraction($html) {
        error_log('UTG DOM: Using fallback extraction method');
        
        // Strip script and style tags with content
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        
        // Remove comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        
        // Try to extract body content
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
            $content = $matches[1];
        } else {
            $content = $html;
        }
        
        // Clean up the content further
        $content = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $content);
        $content = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $content);
        $content = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $content);
        $content = preg_replace('/<aside[^>]*>.*?<\/aside>/is', '', $content);
        
        // If the content is still empty or too short, return a message
        if (strlen(trim(strip_tags($content))) < 50) {
            return '<p>Could not extract meaningful content from this URL. The page may use JavaScript to load content or have access restrictions.</p>';
        }
        
        return $content;
    }
    
    /**
     * Get content from a URL
     * 
     * @param string $url The URL to fetch
     * @return string|bool The content or false on error
     */
    private function get_url_content($url) {
        try {
            error_log('UTG URL: Fetching content from URL: ' . $url);
            
            // Parse the URL to get the domain for referer
            $parsed_url = parse_url($url);
            $domain = isset($parsed_url['host']) ? $parsed_url['host'] : '';
            $origin = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' . $domain : 'https://' . $domain;
            
            // Advanced browser simulation with common headers
            $args = array(
                'timeout'     => 30,
                'redirection' => 10,
                'sslverify'   => true,
                'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
                'headers'     => array(
                    'Accept'            => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language'   => 'en-US,en;q=0.9',
                    'Accept-Encoding'   => 'gzip, deflate, br',
                    'Referer'           => $origin,
                    'Origin'            => $origin,
                    'Cache-Control'     => 'max-age=0',
                    'Sec-Ch-Ua'         => '"Not A(Brand";v="99", "Google Chrome";v="121", "Chromium";v="121"',
                    'Sec-Ch-Ua-Mobile'  => '?0',
                    'Sec-Ch-Ua-Platform'=> '"Windows"',
                    'Sec-Fetch-Dest'    => 'document',
                    'Sec-Fetch-Mode'    => 'navigate',
                    'Sec-Fetch-Site'    => 'same-origin',
                    'Sec-Fetch-User'    => '?1',
                    'Upgrade-Insecure-Requests' => '1',
                ),
                'cookies'     => array(), // Will store cookies from the response
            );
            
            // First attempt - standard WordPress HTTP API
            $response = wp_remote_get($url, $args);
            
            // Check for errors
            if (is_wp_error($response)) {
                error_log('UTG URL: WP_Error in wp_remote_get: ' . $response->get_error_message());
                
                // Try alternative method - cURL directly
                return $this->fetch_with_curl($url, $args['user-agent'], $args['headers']);
            }
            
            // Check if we got a 200 response
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                error_log('UTG URL: Non-200 HTTP status code: ' . $http_code);
                
                // If we got a redirect, try direct cURL as fallback
                if ($http_code >= 300 && $http_code < 400) {
                    error_log('UTG URL: Received redirect response, trying direct cURL method');
                    return $this->fetch_with_curl($url, $args['user-agent'], $args['headers']);
                }
                
                return false;
            }
            
            // Get the response body
            $content = wp_remote_retrieve_body($response);
            
            // Check if the response is empty
            if (empty($content)) {
                error_log('UTG URL: Empty response body');
                return $this->fetch_with_curl($url, $args['user-agent'], $args['headers']);
            }
            
            error_log('UTG URL: Successfully retrieved content, length: ' . strlen($content) . ' bytes');
            
            // Check for common paywalls or consent screens
            if (
                strpos($content, 'window.dataLayer') !== false && 
                (strpos($content, 'paywall') !== false || strpos($content, 'premium') !== false)
            ) {
                error_log('UTG URL: Possible paywall detected');
            }
            
            if (
                strpos($content, 'consent') !== false && 
                (strpos($content, 'cookie') !== false || strpos($content, 'gdpr') !== false)
            ) {
                error_log('UTG URL: Possible consent screen detected');
            }
            
            return $content;
            
        } catch (\Exception $e) {
            error_log('UTG URL: Exception getting content: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fetch content using cURL directly
     * 
     * @param string $url The URL to fetch
     * @param string $user_agent The user agent to use
     * @param array $headers Additional headers
     * @return string|bool The content or false on error
     */
    private function fetch_with_curl($url, $user_agent, $headers = []) {
        if (!function_exists('curl_init')) {
            error_log('UTG URL: cURL not available for fallback');
            return false;
        }
        
        error_log('UTG URL: Attempting to fetch with direct cURL');
        
        // Convert headers array to cURL format
        $header_lines = [];
        foreach ($headers as $key => $value) {
            $header_lines[] = "$key: $value";
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Accept all encodings
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header_lines);
        curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/utg_cookie.txt'); // Store cookies
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/utg_cookie.txt'); // Use stored cookies
        
        $content = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($content === false) {
            error_log('UTG URL: cURL error: ' . $error);
            return false;
        }
        
        if ($http_code !== 200) {
            error_log('UTG URL: cURL HTTP error: ' . $http_code);
            return false;
        }
        
        error_log('UTG URL: Successfully retrieved content with cURL, length: ' . strlen($content) . ' bytes');
        return $content;
    }
    
    /**
     * Helper method to log debug messages
     */
    private function log_debug($message) {
        if ($this->settings->get('debug_mode')) {
            error_log('[UTG Debug] ' . $message);
        }
    }
    
    /**
     * Verify AJAX nonce
     */
    private function verify_ajax_nonce() {
        if (!isset($_POST['security']) || !\wp_verify_nonce($_POST['security'], 'utg_ajax_nonce')) {
            error_log('UTG AJAX: Security check failed - invalid or missing nonce');
            \wp_send_json_error(array('message' => __('Security check failed', 'url-to-gutenberg')));
            exit;
        }
        
        // Check capabilities
        if (!\current_user_can('manage_options')) {
            error_log('UTG AJAX: Security check failed - insufficient permissions');
            \wp_send_json_error(array('message' => __('You do not have permission to perform this action', 'url-to-gutenberg')));
            exit;
        }
        
        error_log('UTG AJAX: Security check passed');
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
            'utg-admin',
            UTG_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            UTG_VERSION,
            true
        );
        
        // Get debug mode setting
        $debug_mode = (bool) $this->settings->get('debug_mode', false);
        
        // Add script parameters
        \wp_localize_script('utg-admin', 'utgVars', array(
            'ajaxUrl' => \admin_url('admin-ajax.php'),
            'nonce' => \wp_create_nonce('utg_ajax_nonce'),
            'testingText' => \__('Testing connection...', 'url-to-gutenberg'),
            'successText' => \__('Connection successful!', 'url-to-gutenberg'),
            'errorText' => \__('Error: ', 'url-to-gutenberg'),
            'debugMode' => $debug_mode,
            'defaultModel' => $this->settings->get('default_model', 'gpt-4o'),
            'i18n' => array(
                'processingUrl' => \__('Processing URL...', 'url-to-gutenberg'),
                'enterValidUrl' => \__('Please enter a valid URL', 'url-to-gutenberg'),
                'securityError' => \__('Security check failed', 'url-to-gutenberg'),
                'serverError' => \__('Could not connect to the server. Please try again.', 'url-to-gutenberg')
            )
        ));
    }
} 