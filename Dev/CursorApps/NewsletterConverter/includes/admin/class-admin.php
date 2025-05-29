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
use UTG\Content_Pipeline;

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
        \add_action('wp_ajax_utg_test_pipeline', array($this, 'test_pipeline'));
        \add_action('wp_ajax_utg_read_debug_file', array($this, 'read_debug_file'));
        \add_action('wp_ajax_utg_test_llm_response', array($this, 'test_llm_response'));
        \add_action('wp_ajax_utg_process_url', array($this, 'process_url'));
        
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
            'api_endpoint' => 'https://openrouter.ai/api/v1',
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
        $sanitized['api_endpoint'] = isset($input['api_endpoint']) ? \esc_url_raw($input['api_endpoint']) : 'https://openrouter.ai/api/v1';
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
        
        // Pipeline Test submenu
        $pipeline_page = \add_submenu_page(
            'url-to-gutenberg',
            __('Pipeline Test', 'url-to-gutenberg'),
            __('Pipeline Test', 'url-to-gutenberg'),
            'manage_options',
            'url-to-gutenberg-pipeline',
            array($this, 'render_pipeline_test_page')
        );
        
        // Debug Dashboard submenu
        $debug_page = \add_submenu_page(
            'url-to-gutenberg',
            __('Debug Dashboard', 'url-to-gutenberg'),
            __('Debug Dashboard', 'url-to-gutenberg'),
            'manage_options',
            'url-to-gutenberg-debug',
            array($this, 'render_debug_dashboard_page')
        );
        
        // Add help tabs and meta boxes when pages are loaded
        \add_action("load-$main_page", array($this, 'add_converter_help_tabs'));
        \add_action("load-$settings_page", array($this, 'add_settings_help_tabs'));
        \add_action("load-$test_page", array($this, 'add_test_help_tabs'));
        \add_action("load-$pipeline_page", array($this, 'add_pipeline_help_tabs'));
        \add_action("load-$debug_page", array($this, 'add_debug_help_tabs'));
        
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
     * Add help tabs for the pipeline test page
     */
    public function add_pipeline_help_tabs() {
        $screen = \get_current_screen();
        
        $screen->add_help_tab(array(
            'id'      => 'utg-pipeline-help',
            'title'   => __('Pipeline Test', 'url-to-gutenberg'),
            'content' => '<p>' . __('This page tests the complete pipeline from URL to WordPress post.', 'url-to-gutenberg') . '</p>' .
                        '<p>' . __('Enter a URL to process it through the full workflow and create a post.', 'url-to-gutenberg') . '</p>',
        ));
        
        $screen->set_help_sidebar(
            '<p><strong>' . __('For more information:', 'url-to-gutenberg') . '</strong></p>' .
            '<p><a href="https://openrouter.ai/docs" target="_blank">' . __('OpenRouter Documentation', 'url-to-gutenberg') . '</a></p>'
        );
    }
    
    /**
     * Add help tabs for the debug dashboard page
     */
    public function add_debug_help_tabs() {
        $screen = \get_current_screen();
        
        $screen->add_help_tab(array(
            'id'      => 'utg-debug-help',
            'title'   => __('Debug Dashboard', 'url-to-gutenberg'),
            'content' => '<p>' . __('This page shows debug information from both the URL conversion process and the Pipeline test.', 'url-to-gutenberg') . '</p>' .
                         '<p>' . __('You can view detailed logs, extracted articles, and LLM outputs for debugging purposes.', 'url-to-gutenberg') . '</p>',
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
            $this->verify_ajax_nonce('utg_test_api_connection');
            
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
     * Convert URL to Gutenberg blocks.
     */
    public function convert_url() {
        try {
            \error_log('UTG: Starting URL conversion process');

            // Verify nonce and user capabilities
            $this->verify_ajax_nonce('utg_convert_url');

            // Get URL from POST data
            $url = isset($_POST['url']) ? \esc_url_raw($_POST['url']) : '';
            if (empty($url)) {
                \error_log('UTG: Empty URL provided');
                \wp_send_json_error('URL cannot be empty');
                return;
            }

            \error_log('UTG: Processing URL: ' . $url);

            // Use the Content Pipeline to process the URL
            $pipeline = new Content_Pipeline();
            $result = $pipeline->process_url($url);

            if (\is_wp_error($result)) {
                \error_log('UTG: Pipeline Error: ' . $result->get_error_message());
                \wp_send_json_error($result->get_error_message());
                return;
            }

            \error_log('UTG: Post created successfully with ID: ' . $result['post_id']);

            \wp_send_json_success([
                'post_id' => $result['post_id'],
                'edit_url' => $result['edit_url'],
                'view_url' => $result['view_url'],
                'message' => \__('Post created successfully!', 'url-to-gutenberg')
            ]);

        } catch (\Exception $e) {
            \error_log('UTG: Exception in convert_url: ' . $e->getMessage());
            \error_log('UTG: Exception trace: ' . $e->getTraceAsString());
            \wp_send_json_error('An error occurred: ' . $e->getMessage());
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
     *
     * @param string $action The nonce action.
     */
    private function verify_ajax_nonce($action) {
        // Support both 'nonce' and 'security' parameter for backwards compatibility
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field($_REQUEST['nonce']) : '';
        $security = isset($_REQUEST['security']) ? sanitize_text_field($_REQUEST['security']) : '';
        
        $check_nonce = !empty($nonce) ? \wp_verify_nonce($nonce, $action) : false;
        $check_security = !empty($security) ? \wp_verify_nonce($security, $action) : false;
        
        if (!$check_nonce && !$check_security) {
            \wp_send_json_error(__('Security check failed.', 'url-to-gutenberg'));
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
            'nonce' => \wp_create_nonce('utg_test_api_connection'),
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
    
    /**
     * Render pipeline test page
     */
    public function render_pipeline_test_page() {
        include UTG_PLUGIN_DIR . 'includes/admin/views/pipeline-test.php';
    }
    
    /**
     * Test the pipeline workflow
     */
    public function test_pipeline() {
        $this->verify_ajax_nonce('utg_pipeline_test');
        
        // Get parameters
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $post_status = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft';
        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
        
        if (empty($url)) {
            \wp_send_json_error(__('URL is required.', 'url-to-gutenberg'));
            return;
        }
        
        // Process URL with full pipeline
        $start_time = microtime(true);
        
        // Create pipeline instance
        $pipeline = new \UTG\Content_Pipeline();
        
        // Process the URL
        $result = $pipeline->process_url($url, [
            'post_status' => $post_status,
            'post_type' => $post_type,
            'process_images' => true,
            'save_debug' => true,
        ]);
        
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);
        
        if (is_wp_error($result)) {
            \wp_send_json_error($result->get_error_message());
            return;
        }
        
        // Add execution time to the result
        $result['execution_time'] = $execution_time;
        
        \wp_send_json_success($result);
    }
    
    /**
     * Read debug file contents via AJAX
     */
    public function read_debug_file() {
        $this->verify_ajax_nonce('utg_read_debug_file');
        
        // Validate the file path
        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        if (empty($file_path)) {
            \wp_send_json_error(__('File path is required.', 'url-to-gutenberg'));
            return;
        }
        
        // Security check: Make sure the file is within allowed directories
        $upload_dir = \wp_upload_dir();
        $allowed_dirs = [
            $upload_dir['basedir'] . '/wc-logs',
            $upload_dir['basedir'] . '/utg-debug',
            $upload_dir['basedir'] . '/wc-logs/utg-logs',
        ];
        
        $is_allowed = false;
        foreach ($allowed_dirs as $allowed_dir) {
            if (strpos($file_path, $allowed_dir) === 0) {
                $is_allowed = true;
                break;
            }
        }
        
        if (!$is_allowed) {
            \wp_send_json_error(__('Access to this file is not allowed.', 'url-to-gutenberg'));
            return;
        }
        
        // Check if file exists
        if (!file_exists($file_path) || !is_file($file_path)) {
            \wp_send_json_error(__('File does not exist.', 'url-to-gutenberg'));
            return;
        }
        
        // Get file content
        $content = file_get_contents($file_path);
        if ($content === false) {
            \wp_send_json_error(__('Failed to read file.', 'url-to-gutenberg'));
            return;
        }

        // Determine file type for special handling
        $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
        
        // Special handling for JSON files - validate it's good JSON
        if ($file_extension === 'json') {
            $json_decoded = json_decode($content);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // If invalid JSON, wrap in an error message
                $error_message = 'Invalid JSON: ' . json_last_error_msg();
                \wp_send_json_success("$error_message\n\nRaw content:\n$content");
                return;
            }
            // Return the raw JSON - the JavaScript side will format it
        }
        
        // Special handling for HTML files - ensure valid HTML structure
        if ($file_extension === 'html') {
            // If HTML doesn't have basic structure, add it
            if (strpos($content, '<html') === false) {
                $content = "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"UTF-8\">\n<title>Extracted Content</title>\n</head>\n<body>\n$content\n</body>\n</html>";
            }
        }
        
        \wp_send_json_success($content);
    }
    
    /**
     * Render the debug dashboard page
     */
    public function render_debug_dashboard_page() {
        include UTG_PLUGIN_DIR . 'includes/admin/views/debug-dashboard.php';
    }

    /**
     * Test LLM response directly from a JSON file
     * 
     * This allows testing the output of the LLM directly without 
     * going through the extraction steps
     */
    public function test_llm_response() {
        $this->verify_ajax_nonce('utg_test_llm_response');
        
        if (empty($_POST['file_path'])) {
            wp_send_json_error('File path is required');
            return;
        }
        
        $file_path = sanitize_text_field($_POST['file_path']);
        
        // Verify the file exists and is in allowed directory
        $upload_dir = wp_upload_dir();
        $utg_debug_dir = $upload_dir['basedir'] . '/utg-debug';
        
        if (!file_exists($file_path) || !is_file($file_path)) {
            wp_send_json_error('File does not exist');
            return;
        }
        
        // Security check - ensure file is in the allowed directory
        if (strpos($file_path, $utg_debug_dir) !== 0) {
            wp_send_json_error('File path not allowed');
            return;
        }
        
        // Get file content
        $content = file_get_contents($file_path);
        if ($content === false) {
            wp_send_json_error('Could not read file');
            return;
        }
        
        // Log the raw content for debugging
        if ($this->settings->get('debug_mode')) {
            $this->log_debug('Testing LLM response from file: ' . basename($file_path));
            $this->log_debug('Raw content length: ' . strlen($content) . ' bytes');
        }
        
        // Try to parse content as JSON with more robust error handling
        // First, try direct decoding
        $json_data = json_decode($content, true);
        
        // If that fails, try cleaning the content first
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to fix common JSON issues
            $cleaned_content = $this->clean_json_content($content);
            $json_data = json_decode($cleaned_content, true);
            
            // If still failing, display the error and raw content
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_msg = 'Invalid JSON content: ' . json_last_error_msg();
                // For debug mode, provide more details
                if ($this->settings->get('debug_mode')) {
                    $this->log_debug($error_msg);
                    $this->log_debug('Failed JSON content sample: ' . substr($content, 0, 500) . '...');
                }
                
                // Send an error response with more details
                $error_response = '<div class="notice notice-error"><p>' . esc_html($error_msg) . '</p></div>';
                $error_response .= '<h4>File Content Preview</h4>';
                $error_response .= '<pre style="max-height: 300px; overflow: auto; background: #f5f5f5; padding: 10px;">' 
                    . esc_html(substr($content, 0, 1000)) 
                    . (strlen($content) > 1000 ? '... (truncated)' : '') 
                    . '</pre>';
                $error_response .= '<p>Try to fix the JSON file manually or regenerate the content.</p>';
                
                wp_send_json_error($error_response);
                return;
            }
        }
        
        // Create a post generator instance
        $post_generator = new \UTG\Generator\Post_Generator();
        
        // Process the LLM content directly
        $processed_content = '';
        
        try {
            // Generate basic HTML preview from the JSON content
            if (isset($json_data['blocks']) && is_array($json_data['blocks'])) {
                $html = '<div class="utg-preview">';
                
                // Extract the title if available
                if (!empty($json_data['title'])) {
                    $html .= '<h1 class="utg-preview-title">' . esc_html($json_data['title']) . '</h1>';
                }
                
                // Process blocks
                foreach ($json_data['blocks'] as $block) {
                    $block_html = $post_generator->render_block_preview($block);
                    $html .= $block_html;
                }
                
                $html .= '</div>';
                $processed_content = $html;
            } else {
                // Check for alternative JSON formats
                if (isset($json_data['content']) && is_array($json_data['content'])) {
                    // Alternative format with content array
                    $html = '<div class="utg-preview">';
                    
                    // Extract the title if available
                    if (!empty($json_data['title'])) {
                        $html .= '<h1 class="utg-preview-title">' . esc_html($json_data['title']) . '</h1>';
                    }
                    
                    // Process content items
                    foreach ($json_data['content'] as $item) {
                        if (is_array($item)) {
                            $block_html = $post_generator->render_block_preview($item);
                            $html .= $block_html;
                        }
                    }
                    
                    $html .= '</div>';
                    $processed_content = $html;
                } else {
                    // Show the JSON structure for debugging
                    $processed_content = '<div class="notice notice-error"><p>Invalid JSON structure: missing or invalid "blocks" array</p></div>';
                    $processed_content .= '<h4>JSON Structure:</h4>';
                    $processed_content .= '<pre style="max-height: 300px; overflow: auto; background: #f5f5f5; padding: 10px;">' 
                        . esc_html(print_r($json_data, true)) 
                        . '</pre>';
                }
            }
        } catch (\Exception $e) {
            $processed_content = '<div class="notice notice-error"><p>Error processing LLM content: ' . esc_html($e->getMessage()) . '</p></div>';
        }
        
        // Include some CSS styles for the preview
        $processed_content = '
        <style>
            .utg-preview {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                max-width: 800px;
                margin: 0 auto;
                line-height: 1.6;
            }
            .utg-preview-title {
                font-size: 32px;
                margin-bottom: 20px;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            .utg-preview img {
                max-width: 100%;
                height: auto;
            }
            .utg-preview blockquote {
                border-left: 4px solid #ccc;
                padding-left: 15px;
                margin-left: 0;
                color: #666;
            }
            .utg-preview pre {
                background: #f6f6f6;
                padding: 15px;
                overflow: auto;
            }
            .utg-preview-block {
                margin-bottom: 20px;
                border: 1px dashed #ddd;
                padding: 10px;
                position: relative;
            }
            .utg-preview-block-type {
                position: absolute;
                top: -10px;
                right: 10px;
                background: #f1f1f1;
                padding: 2px 6px;
                font-size: 10px;
                color: #666;
            }
        </style>' . $processed_content;
        
        wp_send_json_success($processed_content);
    }
    
    /**
     * Clean JSON content to fix common issues
     *
     * @param string $content Raw JSON content
     * @return string Cleaned content
     */
    private function clean_json_content($content) {
        // Remove comments (both // and /* */ style)
        $content = preg_replace('!/\*.*?\*/!s', '', $content);
        $content = preg_replace('!//.*!', '', $content);
        
        // Remove trailing commas in arrays and objects
        $content = preg_replace('/,\s*([\]}])/m', '$1', $content);
        
        // Remove BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        // Remove non-UTF8 characters
        $content = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $content);
        
        // Remove any leading/trailing whitespace
        $content = trim($content);
        
        // Ensure content is surrounded by curly braces if it seems to be an object
        if ($content && $content[0] !== '{' && $content[0] !== '[') {
            $content = '{' . $content . '}';
        }
        
        return $content;
    }
} 