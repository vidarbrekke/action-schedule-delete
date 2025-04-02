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
     * Send a request to the LLM API
     *
     * @param string $prompt The prompt to send to the API.
     * @param array  $options Additional options for the API request.
     * @return array|WP_Error The API response or WP_Error on failure
     */
    public function send_request($prompt, $options = []) {
        if (empty($this->api_key)) {
            return new \WP_Error('missing_api_key', 'API key is not configured');
        }

        $max_tokens = isset($options['max_tokens']) ? $options['max_tokens'] : $this->settings->get('max_tokens');
        $temperature = isset($options['temperature']) ? $options['temperature'] : $this->settings->get('temperature');

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

        $response = \wp_remote_post(
            $this->api_endpoint,
            [
                'headers' => $headers,
                'body' => json_encode($body),
                'timeout' => 60,
                'data_format' => 'body',
            ]
        );

        if (\is_wp_error($response)) {
            return $response;
        }

        $response_code = \wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_message = \wp_remote_retrieve_response_message($response);
            $body = json_decode(\wp_remote_retrieve_body($response), true);
            
            if (!empty($body['error']['message'])) {
                $error_message .= ' - ' . $body['error']['message'];
            }
            
            return new \WP_Error('api_error', $error_message, ['status' => $response_code]);
        }

        $body = json_decode(\wp_remote_retrieve_body($response), true);
        
        if (empty($body['choices'][0]['message']['content'])) {
            return new \WP_Error('invalid_response', 'Invalid or empty response from API');
        }

        return [
            'content' => $body['choices'][0]['message']['content'],
            'usage' => isset($body['usage']) ? $body['usage'] : [],
            'model' => isset($body['model']) ? $body['model'] : $this->model,
        ];
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
        
        $prompt = $instructions . "\n\nContent:\n" . $content;
        
        $result = $this->send_request($prompt, $options);
        
        if (\is_wp_error($result)) {
            return $result;
        }
        
        // Save to cache if enabled
        if ($cache_enabled) {
            $cache_lifetime = $this->settings->get('cache_lifetime');
            \set_transient($cache_key, $result['content'], $cache_lifetime);
        }
        
        return $result['content'];
    }
    
    /**
     * Process a URL and extract content
     *
     * @param string $url The URL to process
     * @return array|WP_Error The processed content or WP_Error
     */
    public function process_url($url) {
        if ($this->settings->get('debug_mode')) {
            \error_log('[URL to Gutenberg] LLM_API: Starting URL processing for: ' . $url);
        }

        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', 'The provided URL is not valid');
        }

        try {
            // Use content extractor to get page content
            require_once UTG_PLUGIN_DIR . 'includes/class-content-extractor.php';
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

            if ($this->settings->get('debug_mode')) {
                \error_log('[URL to Gutenberg] LLM_API: Content extracted successfully, processing with LLM');
            }

            // Process the extracted content with LLM
            $options = [
                'instructions' => 'Convert the following HTML content into WordPress Gutenberg blocks. 
                    Preserve the structure, formatting, and media. Create a clean, readable output 
                    with appropriate Gutenberg blocks for each content element.',
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