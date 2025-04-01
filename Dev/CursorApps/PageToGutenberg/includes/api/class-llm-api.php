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
     * Constructor
     */
    public function __construct() {
        $this->settings = new Settings();
        $this->api_key = $this->settings->get('api_key');
        $this->api_endpoint = $this->settings->get('api_endpoint');
        $this->model = $this->settings->get('api_model');
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
} 