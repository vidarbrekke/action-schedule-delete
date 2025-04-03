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
            array($this, 'sanitize_settings')
        );

        // Default settings
        $defaults = array(
            'api_key' => '',
            'api_endpoint' => 'https://api.openrouter.ai/api/v1/chat/completions',
            'default_model' => 'anthropic/claude-3-sonnet',
            'default_post_status' => 'draft',
            'cache_enabled' => true,
            'cache_expiration' => 3600,
            'debug_mode' => false
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
        
        // Sanitize input values
        $sanitized['api_key'] = isset($input['api_key']) ? \sanitize_text_field($input['api_key']) : '';
        $sanitized['api_endpoint'] = isset($input['api_endpoint']) ? \esc_url_raw($input['api_endpoint']) : 'https://api.openrouter.ai/api/v1/chat/completions';
        $sanitized['default_model'] = isset($input['default_model']) ? \sanitize_text_field($input['default_model']) : 'anthropic/claude-3-sonnet';
        
        // Debug Mode (boolean)
        $sanitized['debug_mode'] = isset($input['debug_mode']) && $input['debug_mode'] ? true : false;
        
        // Cache Enabled (boolean)
        $sanitized['cache_enabled'] = isset($input['cache_enabled']) && $input['cache_enabled'] ? true : false;
        
        // Cache Expiration (integer)
        $sanitized['cache_expiration'] = isset($input['cache_expiration']) ? \absint($input['cache_expiration']) : 3600;
        
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
        try {
            // Verify nonce
            $this->verify_ajax_nonce();
            
            // Log debug information
            $debug_mode = $this->settings->get('debug_mode', false);
            if ($debug_mode) {
                error_log('UTG: Starting API connection test');
            }
            
            // Make sure the API object is initialized
            if (!$this->api) {
                error_log('UTG: API object not available');
                \wp_send_json_error(array('message' => 'API handler not properly initialized'));
                return;
            }
            
            // Test API connection
            $result = $this->api->test_connection();
            
            if ($debug_mode) {
                error_log('UTG: API test result: ' . print_r($result, true));
            }
            
            if (\is_wp_error($result)) {
                error_log('UTG: API test failed with WP_Error: ' . $result->get_error_message());
                \wp_send_json_error(array('message' => $result->get_error_message()));
                return;
            }
            
            // Handle array response format from test_connection
            if (is_array($result) && isset($result['success'])) {
                if ($result['success'] === false) {
                    error_log('UTG: API test returned success=false with message: ' . $result['message']);
                    \wp_send_json_error(array('message' => $result['message']));
                    return;
                }
                
                error_log('UTG: API test successful');
                \wp_send_json_success(array('message' => $result['message']));
                return;
            }
            
            // If we got here without a proper response format, return an error
            error_log('UTG: API test returned unexpected response format');
            \wp_send_json_error(array('message' => 'Unexpected response format from API test'));
            
        } catch (\Exception $e) {
            error_log('UTG: Exception in test_api_connection: ' . $e->getMessage());
            \wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Convert URL AJAX handler
     */
    public function convert_url() {
        $this->verify_ajax_nonce();
        
        $debug_mode = $this->settings->get('debug_mode', false);
        if ($debug_mode) {
            $this->log_debug('Starting URL conversion');
        }
        
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $parse_only = isset($_POST['parse_only']) ? filter_var($_POST['parse_only'], FILTER_VALIDATE_BOOLEAN) : false;
        $cleaning_level = isset($_POST['cleaning_level']) ? sanitize_text_field($_POST['cleaning_level']) : 'standard';
        
        if (empty($url)) {
            wp_send_json_error('URL cannot be empty');
            return;
        }
        
        if ($debug_mode) {
            $this->log_debug(sprintf('Processing URL: %s (Parse Only: %s, Cleaning Level: %s)', 
                $url, $parse_only ? 'Yes' : 'No', $cleaning_level));
        }
        
        // Get content from URL
        $content_extractor = new \UTG\Content_Extractor($this->settings);
        $extracted_content = $content_extractor->extract($url, $cleaning_level);
        
        if (is_wp_error($extracted_content)) {
            $this->log_debug('Content extraction failed: ' . $extracted_content->get_error_message());
            wp_send_json_error('Content extraction failed: ' . $extracted_content->get_error_message());
            return;
        }
        
        // Create debug file path if in debug mode
        $debug_file_path = '';
        if ($debug_mode) {
            $debug_file_name = $content_extractor->get_debug_filename($url, 'final_content');
            $debug_file_path = basename($debug_file_name);
            $this->log_debug('Debug file created: ' . $debug_file_path);
        }
        
        // If parse only, return the extracted HTML content
        if ($parse_only) {
            $this->log_debug('Parse only mode - returning raw extracted content');
            
            // Format the content for display
            $content_html = isset($extracted_content['content']) ? $extracted_content['content'] : '';
            $content_preview = substr(strip_tags($content_html), 0, 200) . '...';
            
            wp_send_json_success([
                'message' => 'Content extracted successfully',
                'content' => $content_html,
                'preview' => $content_preview,
                'debug_file' => $debug_file_path,
            ]);
            return;
        }
        
        // If not parse only, send to LLM API for block conversion
        $this->log_debug('Processing content with LLM API');
        
        // Get the model from settings with fallback to default
        $model = $this->settings->get('default_model', 'openai/gpt-4-turbo');
        $this->log_debug('Using model for conversion: ' . $model);
        
        // Set the model for the API instance
        $this->api->set_model($model);
        
        // Process with the API using the enhanced process_page_content method
        // Pass the extracted content object and source URL to provide more context
        $processed_content = $this->api->process_page_content(
            $extracted_content, 
            [
                'url' => $url,
                'temperature' => 0.1, // Lower temperature for more consistent and complete results
                'max_tokens' => 12000
            ]
        );
        
        if (is_wp_error($processed_content)) {
            $this->log_debug('API processing failed: ' . $processed_content->get_error_message());
            wp_send_json_error('API processing failed: ' . $processed_content->get_error_message());
            return;
        }
        
        // Save LLM-processed content to debug file if in debug mode
        if ($debug_mode) {
            $llm_debug_file_name = $content_extractor->get_debug_filename($url, 'llm_processed_content');
            $llm_debug_file_path = wp_upload_dir()['basedir'] . '/utg-debug/' . basename($llm_debug_file_name);
            
            // Create a structured data array with all the relevant information
            $debug_data = [
                'title' => isset($extracted_content['title']) ? $extracted_content['title'] : '',
                'processed_content' => $processed_content,
                'images' => isset($extracted_content['images']) ? $extracted_content['images'] : [],
                'processed_timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Write the debug file
            file_put_contents($llm_debug_file_path, json_encode($debug_data, JSON_PRETTY_PRINT));
            $this->log_debug('LLM-processed content debug file created: ' . basename($llm_debug_file_name));
        }
        
        // Create post from the processed content if requested
        if (isset($_POST['create_post']) && $_POST['create_post']) {
            $this->log_debug('Creating post from processed content');
            
            $post_generator = new \UTG\Generator\Post_Generator($this->media_handler, $this->settings);
            $post_id = $post_generator->create_post_from_api_response($processed_content, $url);
            
            if (is_wp_error($post_id)) {
                $this->log_debug('Post creation failed: ' . $post_id->get_error_message());
                wp_send_json_error('Post creation failed: ' . $post_id->get_error_message());
                return;
            }
            
            wp_send_json_success([
                'message' => 'Post created successfully',
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'debug_file' => $debug_file_path,
            ]);
            return;
        }
        
        // If no post creation requested, return the processed blocks as serialized content
        $blocks_content = '';
        if (isset($processed_content['blocks']) && is_array($processed_content['blocks'])) {
            foreach ($processed_content['blocks'] as $block) {
                $blocks_content .= serialize_block($block);
            }
        } else {
            // Fallback in case the API returned a different structure
            $blocks_content = isset($processed_content['content']) ? $processed_content['content'] : '';
        }
        
        $content_preview = substr(strip_tags($blocks_content), 0, 200) . '...';
        
        wp_send_json_success([
            'message' => 'Content processed successfully',
            'content' => $blocks_content,
            'preview' => $content_preview,
            'debug_file' => $debug_file_path,
        ]);
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