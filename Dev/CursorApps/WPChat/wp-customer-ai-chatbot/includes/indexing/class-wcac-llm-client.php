<?php
// Ensure WP_Error is available
if (!class_exists('WP_Error')) {
    if (file_exists(ABSPATH . 'wp-includes/class-wp-error.php')) {
        require_once ABSPATH . 'wp-includes/class-wp-error.php';
    } else {
        class WP_Error {
            public function __construct($code = '', $message = '', $data = '') {
                error_log('WCAC WARNING: WP_Error stub used.');
            }
            public function get_error_message() { return 'WP_Error stub'; }
        }
    }
}
// Ensure WordPress HTTP functions are available
if (!function_exists('wp_remote_post')) {
    if (file_exists(ABSPATH . 'wp-includes/http.php')) {
        require_once ABSPATH . 'wp-includes/http.php';
    } else {
        function wp_remote_post($url, $args = []) {
            error_log('WCAC WARNING: wp_remote_post called but http.php not found. Returning WP_Error.');
            return new WP_Error('http_not_found', 'wp_remote_post not available');
        }
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    if (file_exists(ABSPATH . 'wp-includes/http.php')) {
        require_once ABSPATH . 'wp-includes/http.php';
    } else {
        function wp_remote_retrieve_response_code($response) {
            error_log('WCAC WARNING: wp_remote_retrieve_response_code called but http.php not found. Returning 500.');
            return 500;
        }
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    if (file_exists(ABSPATH . 'wp-includes/http.php')) {
        require_once ABSPATH . 'wp-includes/http.php';
    } else {
        function wp_remote_retrieve_body($response) {
            error_log('WCAC WARNING: wp_remote_retrieve_body called but http.php not found. Returning empty string.');
            return '';
        }
    }
}

class Wcac_LLMClient {
    /**
     * Summarize content using the configured LLM API.
     *
     * @param string $prompt The prompt or content to summarize.
     * @param array $params Optional extra parameters (e.g., model, temperature).
     * @return string The summary text.
     * @throws Exception on API error.
     */
    public static function summarize(string $prompt, array $params = []): string {
        // Fetch LLM API settings from plugin options
        $settings = get_option('wcac_settings', []);
        $api_url = isset($settings['llm_api_url']) ? $settings['llm_api_url'] : '';
        $api_key = isset($settings['llm_api_key']) ? $settings['llm_api_key'] : '';
        $model = isset($settings['llm_model']) ? $settings['llm_model'] : 'gpt-3.5-turbo';
        if (empty($api_url) || empty($api_key)) {
            throw new Exception('LLM API credentials are not configured.');
        }
        $body = array_merge([
            'model' => $model,
            'prompt' => $prompt,
            'max_tokens' => 256,
            'temperature' => 0.7,
        ], $params);
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 30,
        ];
        $response = wp_remote_post($api_url, $args);
        if (is_wp_error($response)) {
            throw new Exception('LLM API request failed: ' . $response->get_error_message());
        }
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200 || !is_array($data)) {
            throw new Exception('LLM API error: ' . print_r($data, true));
        }
        // Adjust this depending on the LLM API response structure
        if (isset($data['choices'][0]['text'])) {
            return trim($data['choices'][0]['text']);
        } elseif (isset($data['summary'])) {
            return trim($data['summary']);
        } else {
            throw new Exception('LLM API did not return a summary.');
        }
    }
} 