<?php
declare(strict_types=1);

/**
 * Handles all API communication with language models.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes
 */
class WCAC_API_Handler {
    /**
     * Send a message to the LLM API
     *
     * @param array  $messages     The messages to send to the API.
     * @param array  $api_settings API settings from the plugin options.
     * @param string $api_key      The API key to use.
     * @param string $api_model    The model to use.
     * @param string $api_url      The URL of the API endpoint.
     * 
     * @return mixed The API response or WP_Error on failure.
     */
    public function send_to_llm_api($messages, $api_settings, string $api_key, string $api_model, string $api_url) {
        if (empty($api_key) || empty($api_model)) {
            return new WP_Error('api_config_missing', 'API key or model not configured');
        }

        // Default parameters
        $temperature = floatval($api_settings['wcac_temperature'] ?? '0.7');
        $max_tokens = intval($api_settings['wcac_max_tokens'] ?? '800');
        $top_p = floatval($api_settings['wcac_top_p'] ?? '0.9');
        $frequency_penalty = floatval($api_settings['wcac_frequency_penalty'] ?? '0');
        $presence_penalty = floatval($api_settings['wcac_presence_penalty'] ?? '0');

        // Prepare request data
        $request_data = [
            'messages' => $messages,
            'model' => $api_model,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens,
            'top_p' => $top_p,
            'frequency_penalty' => $frequency_penalty,
            'presence_penalty' => $presence_penalty,
            'stream' => false
        ];

        // Log request details for debugging (excluding the full content)
        error_log('WCAC API: Sending request to ' . $api_url . ' for model ' . $api_model);
        
        // Prepare the request headers
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ];

        // Make the API request
        $response = wp_remote_post(
            $api_url,
            [
                'headers' => $headers,
                'body' => wp_json_encode($request_data),
                'timeout' => 120, // Extended timeout for potentially long API calls
                'data_format' => 'body'
            ]
        );

        // Check for request errors
        if (is_wp_error($response)) {
            error_log('WCAC API Error: ' . $response->get_error_message());
            return $response;
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Log response details for debugging
        error_log('WCAC API: Response code: ' . $response_code);

        // Check for successful response
        if ($response_code < 200 || $response_code >= 300) {
            error_log('WCAC API HTTP Error: ' . $response_code . ' - ' . $response_body);
            return new WP_Error(
                'api_response_error',
                'API request failed with code ' . $response_code . ': ' . $response_body
            );
        }

        // Decode the response
        $decoded_response = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('WCAC API JSON Error: ' . json_last_error_msg() . ' in response: ' . substr($response_body, 0, 100) . '...');
            return new WP_Error('api_response_decode_error', 'Failed to decode API response: ' . json_last_error_msg());
        }

        // Extract the content from the response
        if (isset($decoded_response['choices'][0]['message']['content'])) {
            return $decoded_response['choices'][0]['message']['content'];
        } else {
            error_log('WCAC API Format Error: Unexpected response format: ' . substr($response_body, 0, 100) . '...');
            return new WP_Error('api_response_format_error', 'Unexpected API response format');
        }
    }

    /**
     * Send a simple message to the LLM API with system prompt and user message
     *
     * @param string $system_prompt    The system prompt to use.
     * @param string $user_message     The user message to send.
     * @param array  $conversation     Previous conversation history.
     * @param array  $items_to_link    Items to link in the response.
     * 
     * @return mixed The API response or WP_Error on failure.
     */
    public function send_simple_llm_message($system_prompt, $user_message, $conversation = array(), $items_to_link = []) {
        // Get API settings
        $api_settings = get_option('wcac_settings', []);
        $api_key = isset($api_settings['wcac_api_key']) ? $api_settings['wcac_api_key'] : '';
        $api_model = isset($api_settings['wcac_model']) ? $api_settings['wcac_model'] : 'nousresearch/nous-hermes-2-mixtral-8x7b-dpo';
        $api_url = isset($api_settings['wcac_api_url']) ? $api_settings['wcac_api_url'] : 'https://openrouter.ai/api/v1/chat/completions';

        if (empty($api_key)) {
            return new WP_Error('api_key_missing', 'API key not configured');
        }

        // Build messages array for API call
        $messages = [
            ['role' => 'system', 'content' => $system_prompt]
        ];

        // Add conversation history if provided
        if (!empty($conversation) && is_array($conversation)) {
            foreach ($conversation as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = [
                        'role' => $msg['role'],
                        'content' => $msg['content']
                    ];
                }
            }
        }

        // Add the current user message
        $messages[] = ['role' => 'user', 'content' => $user_message];

        try {
            $response = $this->send_to_llm_api($messages, $api_settings, $api_key, $api_model, $api_url);
            
            // If it's an error, return it directly
            if (is_wp_error($response)) {
                return $response;
            }

            /* // ORPHANED? This linking logic doesn't seem to be used by Wcac_Public
            // Process the response if we have items to link
            if (!empty($items_to_link) && is_string($response)) {
                // Simple post-processing to ensure referenced products are properly linked
                foreach ($items_to_link as $item) {
                    if (isset($item['name']) && isset($item['url'])) {
                        $name_pattern = preg_quote($item['name'], '/');
                        // Replace product mentions with markdown links, but only if they aren't already in a markdown link
                        $response = preg_replace(
                            '/(?<!\]\()(?<!\[)(' . $name_pattern . ')(?!\]\()(?!\])/i',
                            '[${1}](' . $item['url'] . ')',
                            $response
                        );
                    }
                }
            }
            */

            return $response;
        } catch (Exception $e) {
            error_log('WCAC API Exception: ' . $e->getMessage());
            return new WP_Error('api_request_failed', $e->getMessage());
        }
    }

    /**
     * Test API connectivity
     *
     * @return mixed True on success, WP_Error on failure.
     */
    public function test_api_connectivity() {
        // Get API settings
        $api_settings = get_option('wcac_settings', []);
        $api_key = isset($api_settings['wcac_api_key']) ? $api_settings['wcac_api_key'] : '';
        $api_model = isset($api_settings['wcac_model']) ? $api_settings['wcac_model'] : 'nousresearch/nous-hermes-2-mixtral-8x7b-dpo';
        $api_url = isset($api_settings['wcac_api_url']) ? $api_settings['wcac_api_url'] : 'https://openrouter.ai/api/v1/chat/completions';

        if (empty($api_key)) {
            return new WP_Error('api_key_missing', 'API key not configured');
        }

        // Create a simple test message
        $test_message = [
            ['role' => 'system', 'content' => 'Respond with "OK" if you can read this message.'],
            ['role' => 'user', 'content' => 'Connection test'],
        ];

        try {
            $response = $this->send_to_llm_api($test_message, $api_settings, $api_key, $api_model, $api_url);
            return is_wp_error($response) ? $response : true;
        } catch (Exception $e) {
            return new WP_Error('api_test_failed', $e->getMessage());
        }
    }

    /**
     * Estimate the number of tokens in a text
     *
     * @param string $text The text to estimate tokens for.
     * @return int Estimated token count.
     */
    /* // UNUSED? This doesn't seem to be called in the RAG flow.
    public function calculate_tokens($text) {
        $token_counter = new WCAC_Token_Counter();
        return $token_counter->estimate_token_count($text);
    }
    */
} 