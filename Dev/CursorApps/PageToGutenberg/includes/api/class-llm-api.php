<?php
/**
 * LLM API class
 *
 * @package UTG
 */

namespace UTG\API;

use UTG\Settings;

/**
 * Class LLM_API
 * Handles communication with Language Learning Model APIs
 */
class LLM_API {

    /**
     * @var bool
     */
    private $use_cache;

    /**
     * @var \UTG\Settings
     */
    private $settings;

    /**
     * API key for the service
     *
     * @var string
     */
    private $api_key;

    /**
     * API endpoint URL
     *
     * @var string
     */
    private $api_endpoint;

    /**
     * Model to use for API requests
     *
     * @var string
     */
    private $api_model;

    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug;

    /**
     * Max tokens
     *
     * @var int
     */
    private $max_tokens;

    /**
     * Temperature
     *
     * @var float
     */
    private $temperature;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new Settings();
        $this->api_key = $this->settings->get('api_key');
        $this->api_endpoint = rtrim($this->settings->get('api_endpoint'), '/');
        if (!str_ends_with($this->api_endpoint, '/chat/completions')) {
            $this->api_endpoint .= '/chat/completions';
        }
        $this->api_model = $this->settings->get('api_model');
        $this->max_tokens = $this->settings->get('max_tokens');
        $this->temperature = $this->settings->get('temperature', 0.1);
        $this->debug = (bool) $this->settings->get('debug_mode', false);
        $this->use_cache = (bool) $this->settings->get('use_cache', true);
    }

    /**
     * Send a request to the API
     *
     * @param string $prompt The prompt to send to the API.
     * @param array  $options Additional options for the API request.
     * @return array|\WP_Error The API response or WP_Error on failure
     */
    public function send_request($prompt, $options = []) {
        if (empty($this->api_key)) {
            \error_log('UTG: API key is missing in send_request');
            return new \WP_Error('missing_api_key', 'API key is not configured');
        }

        $max_tokens = isset($options['max_tokens']) ? $options['max_tokens'] : $this->max_tokens;
        $temperature = isset($options['temperature']) ? $options['temperature'] : $this->temperature;
        $debug_mode = $this->debug;

        if ($debug_mode) {
            \error_log('UTG: Preparing API request with endpoint: ' . $this->api_endpoint);
            \error_log('UTG: Using model: ' . $this->api_model);
            \error_log('UTG: Max tokens: ' . $max_tokens);
            \error_log('UTG: Temperature: ' . $temperature);
            \error_log('UTG: Prompt length: ' . strlen($prompt));
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
            'HTTP-Referer' => 'openrouter.ai',
            'X-Title' => 'URL to Gutenberg'
        ];

        $body = [
            'model' => $this->api_model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a specialized conversion tool that transforms web page content into WordPress Gutenberg blocks. Your ONLY task is to output raw Gutenberg block markup. 

CRITICAL REQUIREMENTS:
1. NEVER include any explanatory text, introductions, or descriptions in your output
2. NEVER include separator lines, dashes, or any other non-Gutenberg elements
3. NEVER include the original HTML in your response
4. ALWAYS ensure every block has complete JSON attributes with properly closed braces {}
5. ALWAYS include proper opening and closing tags for every block
6. PAY SPECIAL ATTENTION to the final blocks in your response to ensure they are not truncated
7. Your output should start immediately with the first Gutenberg block comment and end with the last closing block comment
8. DO NOT shorten or truncate your answer; continue until your response is complete
9. You MUST process the ENTIRE content, not just part of it
10. CRITICAL: Process ALL images and content sections completely - even lengthy content must be converted fully
11. CRITICALLY IMPORTANT: YOU MUST CONVERT THE ENTIRE DOCUMENT TO THE VERY END

WORDPRESS BLOCK FORMAT REQUIREMENTS:
1. For image blocks, use the format: <!-- wp:image {"align":"center","sizeSlug":"large"} --> and avoid adding custom attributes
2. For separator blocks, use the standard format: <!-- wp:separator {"className":"is-style-wide"} --> <hr class="wp-block-separator is-style-wide"/> <!-- /wp:separator -->
3. For colored separators, use proper WordPress color classes instead of inline styles
4. Always use standard Gutenberg block attributes as defined in WordPress core - do not create custom attributes
5. For styling elements with colors, use WordPress color classes (has-text-color has-[color-name]-color) rather than inline style attributes
6. Never use has-alpha-channel-opacity in your attributes as it can cause validation errors

Any deviation from these rules will cause the output to be unusable.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : 12000,
            'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.1,
        ];

        try {
            if ($debug_mode) {
                \error_log('UTG: Sending API request to: ' . $this->api_endpoint);
            }

            /** @var array|\WP_Error $response */
            $response = \wp_remote_post(
                $this->api_endpoint,
                [
                    'headers' => $headers,
                    'body' => \wp_json_encode($body),
                    'timeout' => 60,
                    'data_format' => 'body',
                ]
            );

            if (\is_wp_error($response)) {
                \error_log('UTG: wp_remote_post error: ' . $response->get_error_message());
                return $response;
            }

            $response_code = \wp_remote_retrieve_response_code($response);
            \error_log('UTG: API response code: ' . $response_code);
            
            if ($response_code !== 200) {
                $body_raw = \wp_remote_retrieve_body($response);
                \error_log('UTG: API error response body: ' . $body_raw);
                
                $body = json_decode($body_raw, true);
                $error_message = 'API Error';
                
                if (!empty($body['error']['message'])) {
                    $error_message = $body['error']['message'];
                }
                
                \error_log('UTG: API error: ' . $error_message);
                return new \WP_Error('api_error', $error_message, ['status' => $response_code]);
            }

            $body_raw = \wp_remote_retrieve_body($response);
            if (empty($body_raw)) {
                \error_log('UTG: Empty API response body');
                return new \WP_Error('empty_response', 'Empty response from API');
            }

            // Try to decode the response
            $body = json_decode($body_raw, true);
            if ($body === null) {
                $json_error = json_last_error_msg();
                \error_log('UTG: JSON decoding error: ' . $json_error);
                \error_log('UTG: First 1000 chars of response: ' . substr($body_raw, 0, 1000));
                
                return new \WP_Error('json_decode_error', 'Failed to decode API response: ' . $json_error);
            }
            
            if (empty($body['choices'][0]['message']['content'])) {
                \error_log('UTG: Missing content in API response');
                \error_log('UTG: Response structure: ' . print_r($body, true));
                return new \WP_Error('invalid_response', 'Invalid or empty response from API');
            }

            return [
                'content' => $body['choices'][0]['message']['content'],
                'usage' => isset($body['usage']) ? $body['usage'] : [],
                'model' => isset($body['model']) ? $body['model'] : $this->api_model,
            ];
        } catch (\Exception $e) {
            \error_log('UTG: Exception in send_request: ' . $e->getMessage());
            \error_log('UTG: Stack trace: ' . $e->getTraceAsString());
            return new \WP_Error('api_exception', $e->getMessage());
        }
    }
    
    /**
     * Sanitize content for JSON encoding
     * 
     * @param string $content Content to sanitize
     * @return string Sanitized content
     */
    private function sanitize_for_json($content) {
        // Remove control characters that break JSON
        $content = preg_replace('/[\x00-\x1F\x7F]/u', '', $content);
        
        // Remove Unicode line and paragraph separators
        $content = str_replace(["\xE2\x80\xA8", "\xE2\x80\xA9"], '', $content);
        
        // Escape backslashes that might break JSON
        $content = str_replace('\\', '\\\\', $content);
        
        // Escape quotes
        $content = str_replace('"', '\"', $content);
        
        // Replace other potentially problematic characters
        $content = str_replace(["\r", "\n\n\n\n", "\n\n\n"], ["\n", "\n\n", "\n\n"], $content);
        
        return $content;
    }

    /**
     * Process page content through the LLM API
     * 
     * @param string|array $content The content to process
     * @param array{model?: string} $options Processing options
     * @return \WP_Error|array The processed content or error
     */
    public function process_page_content($content, $options = []) {
        if (empty($content)) {
            return new \WP_Error('empty_content', 'Content cannot be empty');
        }

        // Handle array content
        if (is_array($content)) {
            if (!isset($content['content'])) {
                if ($this->debug) {
                    error_log('UTG: Content array missing content key');
                }
                return new \WP_Error('invalid_content', 'Content array must have a content key');
            }
            $content = $content['content'];
        }

        // Ensure content is a string
        if (!is_string($content)) {
            if ($this->debug) {
                error_log('UTG: Content must be a string, got ' . gettype($content));
            }
            return new \WP_Error('invalid_content', 'Content must be a string');
        }

        // Generate cache key using model from options if available
        $model = isset($options['model']) ? $options['model'] : '';
        $cache_key = md5($content . $model);
        
        // Try to get from cache if enabled
        if ($this->use_cache) {
            $cached_result = \get_transient($cache_key);
            if ($cached_result !== false) {
                if ($this->debug) {
                    \error_log('UTG: Retrieved content from cache');
                }
                return $cached_result;
            }
        }
        
        // Base instructions for content conversion
        $base_instructions = isset($options['instructions']) 
            ? $options['instructions'] 
            : 'Convert the following HTML content into WordPress Gutenberg blocks while preserving the structure, formatting, and media. Focus on creating a clean, readable output.';
        
        // Enhanced instructions for proper Gutenberg block formatting
        $gutenberg_instructions = "\n\nFOLLOW THESE CRITICAL REQUIREMENTS STRICTLY:
1. Output ONLY raw Gutenberg block comments - nothing else
2. START your response with the FIRST block comment and END with the LAST closing block comment
3. DO NOT include ANY explanatory text, separator lines, or original HTML
4. Every block MUST have proper opening and closing tags
5. All JSON attributes MUST be complete with properly closed braces
6. DO NOT truncate the final block in your response
7. Produce ONLY valid Gutenberg blocks that can be pasted directly into WordPress";
        
        // Check for Campaign Monitor content
        $is_campaign_monitor = (strpos($content, 'campaign-view.com') !== false || 
                               strpos($content, 'campaignmonitor') !== false);
        
        if ($is_campaign_monitor && $this->debug) {
            \error_log('UTG: Detected Campaign Monitor content, applying special processing instructions');
            // Add campaign monitor specific instructions
            $base_instructions .= "\n\nThis is an email from Campaign Monitor. Convert it into clean, structured Gutenberg blocks. Pay special attention to handling tables, images, and formatting. Remove any excessive styling but maintain the content structure.";
        }
        
        // Check content length
        if ($this->debug) {
            \error_log('UTG: Content length for processing: ' . strlen($content));
        }
        
        // Sanitize content for better processing
        $content = $this->sanitize_content_for_processing($content);
        
        // Combine all instructions with content
        $prompt = $base_instructions . $gutenberg_instructions . "\n\nContent:\n" . $content;
        
        /** @var array{content: string, usage?: array, model?: string}|\WP_Error $result */
        $result = $this->send_request($prompt, [
            'max_tokens' => isset($options['max_tokens']) ? $options['max_tokens'] : 12000,
            'temperature' => isset($options['temperature']) ? $options['temperature'] : 0.1
        ]);
        
        if (\is_wp_error($result)) {
            \error_log('UTG: Error from send_request: ' . $result->get_error_message());
            return $result;
        }
        
        // Additional sanitization for Campaign Monitor content
        if ($is_campaign_monitor) {
            // Remove any potentially problematic characters from the response
            $result = $this->sanitize_for_json($result['content']);
            
            if ($this->debug) {
                \error_log('UTG: Applied extra sanitization for Campaign Monitor content');
            }
        } else {
            $result = $result['content'];
        }
        
        // Save to cache if enabled
        if ($this->use_cache) {
            $cache_lifetime = $this->settings->get('cache_lifetime');
            \set_transient($cache_key, $result, $cache_lifetime);
        }
        
        return $result;
    }
    
    /**
     * Sanitize HTML content for processing
     * 
     * @param string $content HTML content to sanitize
     * @return string Sanitized content
     */
    private function sanitize_content_for_processing($content) {
        // Check for Campaign Monitor content
        $is_campaign_monitor = (strpos($content, 'campaign-view.com') !== false || 
                               strpos($content, 'campaignmonitor') !== false);
                               
        if ($is_campaign_monitor) {
            // Extra cleaning for Campaign Monitor content
            
            // Remove MSO conditional comments
            $content = preg_replace('/<!--\[if[^>]*>.*?<!\[endif\]-->/s', '', $content);
            
            // Remove base64 images
            $content = preg_replace('/src="data:image\/[^"]+"/i', 'src="#"', $content);
            
            // Simplify table structures
            $content = preg_replace('/<table[^>]*>/i', '<div class="table-container">', $content);
            $content = preg_replace('/<\/table>/i', '</div>', $content);
            $content = preg_replace('/<tr[^>]*>/i', '<div class="table-row">', $content);
            $content = preg_replace('/<\/tr>/i', '</div>', $content);
            $content = preg_replace('/<td[^>]*>/i', '<div class="table-cell">', $content);
            $content = preg_replace('/<\/td>/i', '</div>', $content);
        }
        
        // Remove scripts, as they're not useful for content extraction
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        
        // Remove style tags
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
        
        // Remove comments
        $content = preg_replace('/<!--(.|\s)*?-->/', '', $content);
        
        // Remove excessive whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove control characters that might break JSON
        $content = preg_replace('/[\x00-\x1F\x7F]/u', '', $content);
        
        // Truncate if too large for processing
        $max_length = 32000; // Safe maximum for most API endpoints
        if (strlen($content) > $max_length) {
            $content = substr($content, 0, $max_length);
        }
        
        return $content;
    }
    
    /**
     * Process a URL and extract content
     *
     * @param string $url The URL to process
     * @param array $options Optional parameters including parse_only flag
     * @return array|WP_Error The processed content or WP_Error
     */
    public function process_url($url, $options = []) {
        if ($this->debug) {
            \error_log('[URL to Gutenberg] LLM_API: Starting URL processing for: ' . $url);
        }

        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', 'The provided URL is not valid');
        }

        try {
            // Use content extractor to get page content
            if (!class_exists('\\UTG\\Content_Extractor')) {
                require_once UTG_PLUGIN_DIR . 'includes/class-content-extractor.php';
                
                if (!class_exists('\\UTG\\Content_Extractor')) {
                    \error_log('UTG: Failed to load Content_Extractor class');
                    return new \WP_Error('class_not_found', 'Content extractor class could not be loaded');
                }
            }
            
            $extractor = new \UTG\Content_Extractor($this->settings);
            
            if ($this->debug) {
                \error_log('[URL to Gutenberg] LLM_API: Extracting content from URL');
            }
            
            $content = $extractor->extract($url);

            if (\is_wp_error($content)) {
                if ($this->debug) {
                    \error_log('[URL to Gutenberg] LLM_API: Content extraction error: ' . $content->get_error_message());
                }
                return $content;
            }

            if (!isset($content['content']) || empty($content['content'])) {
                if ($this->debug) {
                    \error_log('[URL to Gutenberg] LLM_API: No content extracted from URL');
                }
                return new \WP_Error('no_content', 'No content could be extracted from the URL');
            }
            
            // Check if we're in parse-only mode (set by admin class)
            $parse_only = !empty($options['parse_only']);
            
            if ($parse_only) {
                if ($this->debug) {
                    \error_log('[URL to Gutenberg] LLM_API: Parse-only mode, skipping LLM processing');
                }
                
                // Return the extracted content without LLM processing
                return [
                    'content' => $content['content'],
                    'title' => $content['title'],
                    'images' => $content['images'] ?? [],
                    'url' => $url,
                    'parse_only' => true
                ];
            }

            if ($this->debug) {
                \error_log('[URL to Gutenberg] LLM_API: Content extracted successfully, length: ' . strlen($content['content']));
                \error_log('[URL to Gutenberg] LLM_API: Processing with LLM...');
            }

            // Generate domain-specific instructions
            $domain = parse_url($url, PHP_URL_HOST);
            $instructions = $this->get_domain_specific_instructions($domain);
            
            // Process the extracted content with LLM
            $options = [
                'instructions' => $instructions,
                'max_tokens' => $this->max_tokens,
                'temperature' => $this->temperature,
            ];

            $result = $this->process_page_content($content['content'], $options);

            if (\is_wp_error($result)) {
                if ($this->debug) {
                    \error_log('[URL to Gutenberg] LLM_API: Content processing error: ' . $result->get_error_message());
                }
                return $result;
            }

            if ($this->debug) {
                \error_log('[URL to Gutenberg] LLM_API: Content processed successfully');
            }

            // Return processed content along with metadata
            return [
                'content' => $result,
                'title' => $content['title'],
                'images' => $content['images'] ?? [],
                'url' => $url,
            ];
            
        } catch (\Exception $e) {
            if ($this->debug) {
                \error_log('[URL to Gutenberg] LLM_API: Exception: ' . $e->getMessage());
                \error_log('[URL to Gutenberg] LLM_API: Stack trace: ' . $e->getTraceAsString());
            }
            return new \WP_Error('processing_error', $e->getMessage());
        }
    }

    /**
     * Get domain-specific instructions for content processing
     *
     * @param string $domain The domain being processed
     * @return string Customized instructions
     */
    private function get_domain_specific_instructions($domain) {
        // Base instructions for all domains
        $base_instructions = 'Convert the following HTML content into WordPress Gutenberg blocks. 
            Preserve the structure, formatting, and media. Create a clean, readable output 
            with appropriate Gutenberg blocks for each content element.';
        
        // Check for specific domains and customize instructions
        if (strpos($domain, 'campaign-view.com') !== false) {
            $instructions = 'This appears to be an email newsletter from Campaign Monitor. 
                Convert the following HTML content into WordPress Gutenberg blocks, preserving the 
                structure and formatting. Pay special attention to promotional sections, 
                buttons, and multi-column layouts. Use appropriate Gutenberg blocks for 
                each content element including buttons, columns, and images with captions.
                
                CRITICAL REQUIREMENTS FOR YOUR OUTPUT:
                1. START immediately with the first block comment (<!-- wp:) and END with the last closing block comment
                2. Output ONLY the raw Gutenberg blocks - no explanatory text, notes, or separator lines
                3. NEVER include phrases like "Below is one way to translate" or any other introduction
                4. NEVER include dashed lines, asterisks, or any other separator elements
                5. Every block MUST have proper opening and closing tags, especially group blocks
                6. All JSON attributes MUST be complete with properly closed braces
                7. Output MUST be valid and complete - no truncated blocks or attributes
                8. NEVER include the original HTML in your response';
            return $instructions;
        }
        
        // Default instructions
        return $base_instructions;
    }

    /**
     * Test the API connection with a minimal request
     * 
     * @return array{success: bool, message: string} Success or error information
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'API key is required.'
            );
        }

        $endpoint = $this->api_endpoint;
        $body = array(
            'model' => $this->api_model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Test connection'
                )
            )
        );

        $response = \wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer' => 'openrouter.ai',
                    'X-Title' => 'URL to Gutenberg'
                ),
                'body'    => json_encode($body),
                'timeout' => 15,
            )
        );

        // Check for connection errors
        if ( \is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return array(
                'success' => false,
                'message' => 'Error: ' . $error_message
            );
        }

        // Get the response code
        $response_code = \wp_remote_retrieve_response_code( $response );
        
        // Handle successful response
        if ( $response_code === 200 ) {
            return array(
                'success' => true,
                'message' => 'Connection to the API was successful.'
            );
        }
        
        // Handle error response
        $error_message = $response_code . ' Error';
        $body = \wp_remote_retrieve_body( $response );
        $body_data = \json_decode( $body, true );
        
        if ( isset( $body_data['error']['message'] ) ) {
            $error_message = $body_data['error']['message'];
        }
        
        return array(
            'success' => false,
            'message' => 'Error: ' . $error_message . ' (Status code: ' . $response_code . ')',
        );
    }

    /**
     * Log API request and response details for debugging
     *
     * @param string $type Either 'request' or 'response'
     * @param array $payload The request payload
     * @param array|\WP_Error|null $response The API response (for response logs only)
     */
    private function log_api_interaction($type, $payload, $response = null) {
        // Temporarily disabled to fix 500 errors
        return;
        
        // Original code below is kept for reference but not executed
        /*
        if (!$this->debug) {
            return;
        }
        
        try {
            // Get WordPress uploads directory
            $upload_dir = \wp_upload_dir();
            if (isset($upload_dir['error']) && $upload_dir['error'] !== false) {
                error_log('UTG: Error getting uploads directory: ' . $upload_dir['error']);
                return;
            }
            
            // Create a logs directory in the uploads folder
            $logs_dir = $upload_dir['basedir'] . '/utg-logs';
            if (!file_exists($logs_dir)) {
                if (!mkdir($logs_dir, 0755, true)) {
                    error_log('UTG: Failed to create logs directory at ' . $logs_dir);
                    return;
                }
                
                // Add index.php to prevent directory listing
                file_put_contents($logs_dir . '/index.php', '<?php // Silence is golden');
                
                // Add .htaccess for additional security
                file_put_contents($logs_dir . '/.htaccess', 'Deny from all');
            }
            
            // Generate a unique log file name
            $timestamp = date('Y-m-d_H-i-s');
            $log_file = $logs_dir . '/api_' . $type . '_' . $timestamp . '.log';
            
            // Format the log content
            $log_content = "=== URL To Gutenberg API " . strtoupper($type) . " LOG ===\n";
            $log_content .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
            
            if ($type === 'request') {
                // Log request details
                $log_content .= "API Endpoint: " . $this->api_endpoint . "\n";
                $log_content .= "Model: " . $this->api_model . "\n\n";
                
                // Log headers
                $log_content .= "Headers:\n";
                $log_content .= "Content-Type: application/json\n";
                $log_content .= "Authorization: Bearer " . $this->mask_api_key($this->api_key) . "\n\n";
                
                // Log payload (masking any sensitive data)
                $log_content .= "Payload:\n";
                $log_content .= json_encode($payload, JSON_PRETTY_PRINT) . "\n";
            } else if ($response) {
                // Log response details
                $status_code = isset($response['response']['code']) ? $response['response']['code'] : 'unknown';
                $log_content .= "Status Code: " . $status_code . "\n\n";
                
                // For headers, just note that we're not logging them to avoid dependency issues
                $log_content .= "Headers: (not logged to avoid dependency issues)\n\n";
                
                // Log response body
                $body = isset($response['body']) ? $response['body'] : '';
                $log_content .= "Body:\n";
                
                // Try to format JSON for better readability
                $json_body = json_decode($body);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $log_content .= json_encode($json_body, JSON_PRETTY_PRINT) . "\n";
                } else {
                    // Not valid JSON, might be HTML or other format
                    $log_content .= "Non-JSON response, first 1000 characters:\n";
                    $log_content .= substr($body, 0, 1000) . "\n";
                    if (strlen($body) > 1000) {
                        $log_content .= "... (response truncated, total length: " . strlen($body) . " bytes)\n";
                    }
                }
            }
            
            // Write to log file
            if (!file_put_contents($log_file, $log_content)) {
                error_log('UTG: Failed to write to log file: ' . $log_file);
            } else {
                error_log('UTG: Created ' . $type . ' log file: ' . $log_file);
                error_log('UTG: Log file location: ' . $log_file);
            }
        } catch (\Exception $e) {
            error_log('UTG: Error creating API log file: ' . $e->getMessage());
        }
        */
    }
    
    /**
     * Mask API key for security in logs
     *
     * @param string $api_key The API key to mask
     * @return string The masked API key
     */
    private function mask_api_key($api_key) {
        if (strlen($api_key) <= 8) {
            return '********';
        }
        return substr($api_key, 0, 4) . '...' . substr($api_key, -4);
    }

    /**
     * Set the model to use for API requests
     *
     * @param string $model The model identifier
     * @return void
     */
    public function set_model($model) {
        if (!empty($model)) {
            $this->api_model = $model;
            if ($this->debug) {
                \error_log('UTG: Model set to: ' . $model);
            }
        }
    }
} 