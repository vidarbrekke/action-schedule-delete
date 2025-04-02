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
     * Settings instance
     *
     * @var Settings
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
    private $model;

    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug;

    /**
     * Cache enabled
     *
     * @var bool
     */
    private $use_cache;

    /**
     * Constructor
     */
    public function __construct( $settings ) {
        $this->settings = $settings;
        $this->api_key = $this->settings->get( 'api_key' );
        $this->api_endpoint = $this->settings->get( 'api_endpoint' );
        $this->model = $this->settings->get('default_model');
        $this->debug = (bool) $this->settings->get( 'debug_mode', false );
        $this->use_cache = (bool) $this->settings->get( 'use_cache', true );
    }

    /**
     * Send a request to the API
     *
     * @param string $prompt The prompt to send to the API.
     * @param array  $options Additional options for the API request.
     * @return array|WP_Error The API response or WP_Error on failure
     */
    public function send_request($prompt, $options = []) {
        if (empty($this->api_key)) {
            \error_log('UTG: API key is missing in send_request');
            return new \WP_Error('missing_api_key', 'API key is not configured');
        }

        $max_tokens = isset($options['max_tokens']) ? $options['max_tokens'] : $this->settings->get('max_tokens');
        $temperature = isset($options['temperature']) ? $options['temperature'] : $this->settings->get('temperature');
        $debug_mode = $this->settings->get('debug_mode', false);

        if ($debug_mode) {
            \error_log('UTG: Preparing API request with endpoint: ' . $this->api_endpoint);
            \error_log('UTG: Using model: ' . $this->model);
            \error_log('UTG: Max tokens: ' . $max_tokens);
            \error_log('UTG: Temperature: ' . $temperature);
            \error_log('UTG: Prompt length: ' . strlen($prompt));
        }

        // Truncate prompt if it's too long to avoid API errors
        $max_prompt_length = 32000; // Safe limit for most models
        if (strlen($prompt) > $max_prompt_length) {
            \error_log('UTG: Prompt too long (' . strlen($prompt) . ' chars), truncating to ' . $max_prompt_length);
            $prompt = substr($prompt, 0, $max_prompt_length);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
        ];

        $body = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant that converts web page content into well-structured WordPress Gutenberg blocks.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
        ];

        // Convert body to JSON and check for errors 
        $json_body = json_encode($body);
        if ($json_body === false) {
            $json_error = json_last_error_msg();
            \error_log('UTG: JSON encoding error: ' . $json_error);
            
            // Try to sanitize the prompt and retry
            \error_log('UTG: Attempting to sanitize prompt and retry JSON encoding');
            $body['messages'][1]['content'] = $this->sanitize_for_json($prompt);
            $json_body = json_encode($body);
            
            if ($json_body === false) {
                \error_log('UTG: JSON encoding still failed after sanitizing prompt');
                return new \WP_Error('json_encode_error', 'Failed to encode API request: ' . $json_error);
            }
        }

        try {
            if ($debug_mode) {
                \error_log('UTG: Sending API request to: ' . $this->api_endpoint);
            }

            $response = \wp_remote_post(
                $this->api_endpoint,
                [
                    'headers' => $headers,
                    'body' => $json_body,
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
                $error_message = \wp_remote_retrieve_response_message($response);
                $body_raw = \wp_remote_retrieve_body($response);
                \error_log('UTG: API error response body: ' . $body_raw);
                
                $body = json_decode($body_raw, true);
                
                if (!empty($body['error']['message'])) {
                    $error_message .= ' - ' . $body['error']['message'];
                }
                
                \error_log('UTG: API error: ' . $error_message);
                return new \WP_Error('api_error', $error_message, ['status' => $response_code]);
            }

            $body_raw = \wp_remote_retrieve_body($response);
            if (empty($body_raw)) {
                \error_log('UTG: Empty API response body');
                return new \WP_Error('empty_response', 'Empty response from API');
            }
            
            if ($debug_mode) {
                \error_log('UTG: API raw response length: ' . strlen($body_raw));
            }
            
            // Try to decode the response
            $body = json_decode($body_raw, true);
            if ($body === null) {
                $json_error = json_last_error_msg();
                \error_log('UTG: JSON decoding error: ' . $json_error);
                \error_log('UTG: First 1000 chars of response: ' . substr($body_raw, 0, 1000));
                
                // Additional error handling for specific JSON errors
                if (json_last_error() === JSON_ERROR_SYNTAX) {
                    // Log more detailed information about the syntax error
                    \error_log('UTG: JSON syntax error detected. This usually means malformed JSON.');
                    
                    // Try to identify problematic characters (often control characters or invalid Unicode)
                    $sanitized_body_raw = $this->sanitize_for_json($body_raw);
                    
                    // Try with a higher level of sanitization
                    if (strpos($body_raw, 'campaign-view.com') !== false || 
                        strpos($body_raw, 'campaignmonitor') !== false) {
                        \error_log('UTG: Detected Campaign Monitor content in response, applying aggressive sanitization');
                        $sanitized_body_raw = preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}]/u', '', $sanitized_body_raw);
                    }
                    
                    // Try decoding the sanitized response
                    $body = json_decode($sanitized_body_raw, true);
                    
                    if ($body === null) {
                        // If still failing, strip all non-ASCII characters as a last resort
                        \error_log('UTG: Attempting more aggressive JSON sanitization');
                        $ascii_only = preg_replace('/[^\x20-\x7E]/', '', $body_raw);
                        $body = json_decode($ascii_only, true);
                        
                        if ($body === null) {
                            \error_log('UTG: All JSON decoding attempts failed');
                            return new \WP_Error('json_decode_error', 'Failed to decode API response: ' . $json_error);
                        }
                    }
                    
                    \error_log('UTG: Successfully recovered JSON after sanitization');
                } else {
                    // For other JSON errors, try standard sanitization
                    $sanitized_body = $this->sanitize_for_json($body_raw);
                    $body = json_decode($sanitized_body, true);
                    
                    if ($body === null) {
                        \error_log('UTG: JSON decoding still failed after sanitizing');
                        return new \WP_Error('json_decode_error', 'Failed to decode API response: ' . $json_error);
                    }
                }
            }
            
            if (empty($body['choices'][0]['message']['content'])) {
                \error_log('UTG: Missing content in API response');
                \error_log('UTG: Response structure: ' . print_r($body, true));
                return new \WP_Error('invalid_response', 'Invalid or empty response from API');
            }

            return [
                'content' => $body['choices'][0]['message']['content'],
                'usage' => isset($body['usage']) ? $body['usage'] : [],
                'model' => isset($body['model']) ? $body['model'] : $this->model,
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
     * Process a web page content with the LLM
     *
     * @param string $content HTML content to process.
     * @param array  $options Processing options.
     * @return string|WP_Error The processed content or WP_Error
     */
    public function process_page_content($content, $options = []) {
        $debug_mode = $this->settings->get('debug_mode');
        $cache_enabled = $this->settings->get('cache_enabled');
        $cache_key = 'utg_content_' . md5($content . serialize($options));
        
        // Try to get from cache if enabled
        if ($cache_enabled) {
            $cached_result = \get_transient($cache_key);
            if ($cached_result !== false) {
                if ($debug_mode) {
                    \error_log('UTG: Retrieved content from cache');
                }
                return $cached_result;
            }
        }
        
        $instructions = isset($options['instructions']) 
            ? $options['instructions'] 
            : 'Convert the following HTML content into WordPress Gutenberg blocks while preserving the structure, formatting, and media. Focus on creating a clean, readable output.';
        
        // Check for Campaign Monitor content
        $is_campaign_monitor = (strpos($content, 'campaign-view.com') !== false || 
                               strpos($content, 'campaignmonitor') !== false);
        
        if ($is_campaign_monitor && $debug_mode) {
            \error_log('UTG: Detected Campaign Monitor content, applying special processing instructions');
            // Add campaign monitor specific instructions
            $instructions .= "\n\nThis is an email from Campaign Monitor. Convert it into clean, structured Gutenberg blocks. Pay special attention to handling tables, images, and formatting. Remove any excessive styling but maintain the content structure.";
        }
        
        // Check content length
        if ($debug_mode) {
            \error_log('UTG: Content length for processing: ' . strlen($content));
        }
        
        // Sanitize content for better processing
        $content = $this->sanitize_content_for_processing($content);
        
        $prompt = $instructions . "\n\nContent:\n" . $content;
        
        $result = $this->send_request($prompt, $options);
        
        if (\is_wp_error($result)) {
            \error_log('UTG: Error from send_request: ' . $result->get_error_message());
            return $result;
        }
        
        // Additional sanitization for Campaign Monitor content
        if ($is_campaign_monitor) {
            // Remove any potentially problematic characters from the response
            $result['content'] = $this->sanitize_for_json($result['content']);
            
            if ($debug_mode) {
                \error_log('UTG: Applied extra sanitization for Campaign Monitor content');
            }
        }
        
        // Save to cache if enabled
        if ($cache_enabled) {
            $cache_lifetime = $this->settings->get('cache_lifetime');
            \set_transient($cache_key, $result['content'], $cache_lifetime);
        }
        
        return $result['content'];
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
        if ($this->settings->get('debug_mode')) {
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
            
            if ($this->settings->get('debug_mode')) {
                \error_log('[URL to Gutenberg] LLM_API: Extracting content from URL');
            }
            
            $content = $extractor->extract($url);

            if (\is_wp_error($content)) {
                if ($this->settings->get('debug_mode')) {
                    \error_log('[URL to Gutenberg] LLM_API: Content extraction error: ' . $content->get_error_message());
                }
                return $content;
            }

            if (!isset($content['content']) || empty($content['content'])) {
                if ($this->settings->get('debug_mode')) {
                    \error_log('[URL to Gutenberg] LLM_API: No content extracted from URL');
                }
                return new \WP_Error('no_content', 'No content could be extracted from the URL');
            }
            
            // Check if we're in parse-only mode (set by admin class)
            $parse_only = !empty($options['parse_only']);
            
            if ($parse_only) {
                if ($this->settings->get('debug_mode')) {
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

            if ($this->settings->get('debug_mode')) {
                \error_log('[URL to Gutenberg] LLM_API: Content extracted successfully, length: ' . strlen($content['content']));
                \error_log('[URL to Gutenberg] LLM_API: Processing with LLM...');
            }

            // Generate domain-specific instructions
            $domain = parse_url($url, PHP_URL_HOST);
            $instructions = $this->get_domain_specific_instructions($domain);
            
            // Process the extracted content with LLM
            $options = [
                'instructions' => $instructions,
                'max_tokens' => $this->settings->get('max_tokens', 2000),
                'temperature' => $this->settings->get('temperature', 0.7),
            ];

            $result = $this->process_page_content($content['content'], $options);

            if (\is_wp_error($result)) {
                if ($this->settings->get('debug_mode')) {
                    \error_log('[URL to Gutenberg] LLM_API: Content processing error: ' . $result->get_error_message());
                }
                return $result;
            }

            if ($this->settings->get('debug_mode')) {
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
            if ($this->settings->get('debug_mode')) {
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
                each content element including buttons, columns, and images with captions.';
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
        // Check if we have an API key
        if ( empty( $this->api_key ) ) {
            return array(
                'success' => false,
                'message' => 'API key is missing. Please add an API key in the settings.'
            );
        }

        // Check if we have an endpoint
        $endpoint = ! empty( $this->api_endpoint ) ? $this->api_endpoint : 'https://api.openai.com/v1/chat/completions';

        // Prepare a minimal test request
        $body = array(
            'model' => $this->model ? $this->model : 'gpt-3.5-turbo',
            'messages' => array(
                    array(
                    'role' => 'user',
                    'content' => 'This is a connection test.'
                )
            ),
            'max_tokens' => 5
        );

        // Send a minimal request to test connectivity
        $response = \wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => json_encode( $body ),
                'timeout' => 15,
            )
        );

        // Check for connection errors
        if ( \is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => 'Error: ' . $response->get_error_message()
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
} 