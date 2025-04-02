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
        
        if (isset($_POST['url']) && \check_admin_referer('utg_ajax_nonce')) {
            $url = sanitize_text_field($_POST['url']);
            $content_extractor = new \UTG\Content_Extractor($this->settings);
            $content = $content_extractor->extract($url);
            
            if (\is_wp_error($content)) {
                $error = $content->get_error_message();
            }
        }
        
        $settings = $this->settings->get_all();
        
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
                
                // Save the raw content to debug directory with proper UTF-8 encoding
                $debug_file = $debug_dir . '/raw_content_' . uniqid() . '.html';
                // Add UTF-8 BOM (Byte Order Mark) for better encoding recognition
                $utf8_bom = chr(239) . chr(187) . chr(191); // UTF-8 BOM
                @file_put_contents($debug_file, $utf8_bom . $content);
                error_log('UTG AJAX: Raw content saved to: ' . $debug_file);
                
                error_log('UTG AJAX: Retrieved content, length: ' . strlen($content) . ' bytes');
                
                // Try to use DOMDocument for basic extraction
                error_log('UTG AJAX: Beginning DOMDocument extraction process');
                $extracted_content = $this->extract_content_with_dom($content);
                
                if (!$extracted_content || empty($extracted_content)) {
                    error_log('UTG AJAX: No content could be extracted with DOMDocument');
                    \wp_send_json_error(__('No content could be extracted from this URL.', 'url-to-gutenberg'));
                    $this->settings->update(['debug_mode' => $debug_setting]);
                    return;
                }
                
                // Save the extracted content for debugging with proper UTF-8 encoding
                $debug_file = $debug_dir . '/extracted_content_' . uniqid() . '.html';
                @file_put_contents($debug_file, $utf8_bom . $extracted_content);
                error_log('UTG AJAX: Extracted content saved to: ' . $debug_file);
                
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
        // ... existing code ...
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
                foreach ($errors as $error) {
                    error_log('UTG DOM: Parse error: ' . $error->message);
                }
                libxml_clear_errors();
            }
            
            // Extract content
            $body = $dom->getElementsByTagName('body')->item(0);
            
            if (!$body) {
                error_log('UTG DOM: No body tag found in HTML');
                return '<p>Error: No body tag found in HTML document.</p>';
            }
            
            // Try to find main content (common content containers)
            $content_containers = array(
                'article',
                'main',
                'div[id="content"]',
                'div[class="content"]',
                'div[class*="content"]',
                'div[id="main"]',
                'div[class="main"]'
            );
            
            error_log('UTG DOM: Searching for content containers');
            $content = '';
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
                    $node = $nodes->item(0);
                    $content = $dom->saveHTML($node);
                    break;
                }
            }
            
            // If no specific container found, use the body content
            if (empty($content)) {
                error_log('UTG DOM: No specific content container found, using body content');
                $content = $dom->saveHTML($body);
            }
            
            // Remove common non-content elements
            error_log('UTG DOM: Cleaning HTML content');
            $clean_content = $this->clean_html_content($content);
            
            // Fix character encoding issues by properly decoding HTML entities
            $clean_content = html_entity_decode($clean_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
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
            
            $clean_content = str_replace(array_keys($replacements), array_values($replacements), $clean_content);
            
            return $clean_content;
            
        } catch (\Exception $e) {
            error_log('UTG DOM: Error in DOMDocument extraction: ' . $e->getMessage());
            error_log('UTG DOM: Exception trace: ' . $e->getTraceAsString());
            return '<p>Error extracting content: ' . $e->getMessage() . '</p>';
        } finally {
            // Restore libxml error handling state
            libxml_use_internal_errors($internal_errors);
        }
    }
    
    /**
     * Clean HTML content by removing unwanted elements
     * 
     * @param string $html The HTML content to clean
     * @return string The cleaned HTML content
     */
    private function clean_html_content($html) {
        try {
            error_log('UTG CLEAN: Starting HTML cleaning process');
            
            // Use DOMDocument to clean the HTML
            $dom = new \DOMDocument('1.0', 'UTF-8');
            libxml_use_internal_errors(true);
            
            // Ensure UTF-8 encoding
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
            
            // Load with proper encoding options
            @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            
            // Elements to remove
            $elements_to_remove = array(
                'script', 'style', 'iframe', 'noscript', 'form',
                'header', 'footer', 'nav', 'aside', 'object', 'embed'
            );
            
            // Process each element type
            foreach ($elements_to_remove as $tag_name) {
                $elements = $dom->getElementsByTagName($tag_name);
                
                // We need to remove nodes in reverse order to avoid changing the node list during iteration
                $nodes_to_remove = array();
                for ($i = 0; $i < $elements->length; $i++) {
                    $nodes_to_remove[] = $elements->item($i);
                }
                
                foreach ($nodes_to_remove as $node) {
                    if ($node && $node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }
            
            // Also remove elements with common ad/nav/sidebar class names
            $xpath = new \DOMXPath($dom);
            $class_patterns = array(
                'contains(@class, "ad")', 
                'contains(@class, "ads")',
                'contains(@class, "banner")',
                'contains(@class, "sidebar")',
                'contains(@class, "menu")',
                'contains(@class, "navigation")',
                'contains(@class, "nav-")',
                'contains(@class, "share")',
                'contains(@class, "social")',
                'contains(@class, "comment")',
                'contains(@id, "sidebar")',
                'contains(@id, "menu")',
                'contains(@id, "nav")',
                'contains(@id, "ad-")',
                'contains(@id, "ads-")'
            );
            
            // Build XPath query for elements with these classes/ids
            $class_query = '//div[' . implode(' or ', $class_patterns) . ']';
            
            // Get matching nodes
            $nodes = $xpath->query($class_query);
            $nodes_to_remove = array();
            
            for ($i = 0; $i < $nodes->length; $i++) {
                $nodes_to_remove[] = $nodes->item($i);
            }
            
            foreach ($nodes_to_remove as $node) {
                if ($node && $node->parentNode) {
                    $node->parentNode->removeChild($node);
                }
            }
            
            // Get the cleaned HTML
            $clean_html = $dom->saveHTML();
            
            // Additional cleanup with simple string replacements
            $clean_html = preg_replace('/<a[^>]*>(.*?)<\/a>/is', '$1', $clean_html); // Remove links but keep their content
            $clean_html = preg_replace('/\s+/', ' ', $clean_html); // Normalize whitespace
            
            // Further ensure proper character encoding
            $clean_html = html_entity_decode($clean_html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            error_log('UTG CLEAN: HTML cleaning complete, length: ' . strlen($clean_html) . ' bytes');
            
            return $clean_html;
            
        } catch (\Exception $e) {
            error_log('UTG CLEAN: Error cleaning HTML: ' . $e->getMessage());
            return $html; // Return original on error
        }
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
            
            // Simulate a browser request
            $args = array(
                'timeout'     => 30,
                'redirection' => 5,
                'sslverify'   => true,
                'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'headers'     => array(
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Cache-Control'   => 'no-cache',
                    'Pragma'          => 'no-cache',
                ),
            );
            
            // Use WordPress HTTP API
            $response = wp_remote_get($url, $args);
            
            // Check for errors
            if (is_wp_error($response)) {
                error_log('UTG URL: WP_Error in wp_remote_get: ' . $response->get_error_message());
                return false;
            }
            
            // Check if we got a 200 response
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                error_log('UTG URL: Non-200 HTTP status code: ' . $http_code);
                return false;
            }
            
            // Get the response body
            $content = wp_remote_retrieve_body($response);
            
            // Check if the response is empty
            if (empty($content)) {
                error_log('UTG URL: Empty response body');
                return false;
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