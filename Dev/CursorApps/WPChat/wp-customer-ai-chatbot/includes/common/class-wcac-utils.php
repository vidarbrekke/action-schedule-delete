<?php
/**
 * WCAC Utility Functions
 * Centralized helpers for safe string/array operations.
 */

if (!function_exists('wcac_safe_split')) {
    /**
     * Safely split a value (string or array) into an array of trimmed, non-empty strings.
     *
     * @param string $delimiter Delimiter for splitting (e.g., "\n", ",").
     * @param mixed $value The value to split (string, array, or other).
     * @return array Array of trimmed, non-empty strings.
     */
    function wcac_safe_split($delimiter, $value) {
        if (is_array($value)) {
            $value = implode($delimiter, $value);
        } elseif (!is_string($value)) {
            $value = '';
        }
        $parts = explode($delimiter, $value);
        return array_values(array_filter(array_map('trim', $parts), function($s) { return $s !== ''; }));
    }
} 

if (!function_exists('wcac_get_llm_params')) {
    /**
     * Fetch and validate all LLM parameters from plugin settings.
     *
     * @return array Associative array of LLM parameters (model, temperature, top_p, max_tokens, frequency_penalty, presence_penalty)
     */
    function wcac_get_llm_params() {
        $settings = get_option('wcac_settings', []);
        return [
            'model' => $settings['wcac_model'] ?? 'gpt-3.5-turbo',
            'temperature' => isset($settings['wcac_temperature']) ? max(0.0, min(2.0, (float)$settings['wcac_temperature'])) : 0.7,
            'top_p' => isset($settings['wcac_top_p']) ? max(0.0, min(1.0, (float)$settings['wcac_top_p'])) : 1.0,
            'max_tokens' => isset($settings['wcac_max_tokens']) ? max(128, min(4096, (int)$settings['wcac_max_tokens'])) : 1024,
            'frequency_penalty' => isset($settings['wcac_frequency_penalty']) ? max(-2.0, min(2.0, (float)$settings['wcac_frequency_penalty'])) : 0.0,
            'presence_penalty' => isset($settings['wcac_presence_penalty']) ? max(-2.0, min(2.0, (float)$settings['wcac_presence_penalty'])) : 0.0,
        ];
    }
} 