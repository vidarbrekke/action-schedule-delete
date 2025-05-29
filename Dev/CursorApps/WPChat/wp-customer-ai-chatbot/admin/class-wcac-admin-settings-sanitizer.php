<?php
declare(strict_types=1);

/**
 * Handles sanitization of settings for WP Customer AI Chatbot.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/admin
 */
class Wcac_Admin_Settings_Sanitizer
{
    private string $option_key;
    private array $scoring_field_defaults_map = []; // To store key => default_value

    /**
     * Constructor.
     *
     * @param string $option_key The key for plugin options.
     */
    public function __construct(string $option_key)
    {
        $this->option_key = $option_key;
        // Fetch and map scoring field defaults for easy lookup
        if (class_exists('Wcac_Admin_Settings_Registrar') && method_exists('Wcac_Admin_Settings_Registrar', 'get_all_scoring_field_definitions')) {
            $all_definitions = Wcac_Admin_Settings_Registrar::get_all_scoring_field_definitions();
            foreach ($all_definitions as $def) {
                if (isset($def['key']) && isset($def['default'])) {
                    $this->scoring_field_defaults_map[$def['key']] = $def['default'];
                }
            }
        }
    }

    /**
     * Sanitize settings array.
     *
     * @param array $input The settings array.
     * @return array Sanitized settings array.
     */
    public function sanitize_settings(array $input): array
    {
        if (WP_DEBUG) {
            error_log('WCAC DEBUG: sanitize_settings input: ' . print_r($input, true));
        }

        $persisted_options = get_option($this->option_key, []);
        if (!is_array($persisted_options)) {
            $persisted_options = [];
        }
        $sanitized_output = $persisted_options; // Start with all existing options

        $optimizer_keys = [];
        if (defined('WCAC_PLUGIN_DIR') && file_exists(WCAC_PLUGIN_DIR . 'includes/optimizer/class-wcac-settings-optimizer.php')) {
            require_once WCAC_PLUGIN_DIR . 'includes/optimizer/class-wcac-settings-optimizer.php';
            if (class_exists('Wcac_Settings_Optimizer') && method_exists('Wcac_Settings_Optimizer', 'get_parameter_keys')) {
                $optimizer_keys = Wcac_Settings_Optimizer::get_parameter_keys();
            }
        }

        $allowed_plugin_keys = [
            'wcac_api_key', 'wcac_enable_debug_logging', 'wcac_negative_keywords',
            'wcac_system_prompt', 'wcac_site_prompt', 'wcac_custom_css', 'wcac_synonym_map',
            'wcac_boost_terms', 'wcac_devalue_terms', 'wcac_temperature', 'wcac_top_p',
            'wcac_max_tokens', 'wcac_frequency_penalty', 'wcac_presence_penalty', 'wcac_model',
            'wcac_index_products', 'wcac_index_pages', 'wcac_index_posts',
            'wcac_context_compression_algorithm',
            'wcac_parent_product_weight', 'wcac_variation_product_weight', 'wcac_title_match_weight',
            'wcac_content_match_weight', 'wcac_category_match_weight', 'wcac_tag_match_weight',
            'wcac_on_sale_weight', 'wcac_menu_match_weight', 'wcac_attribute_match_weight',
            'wcac_parent_category_match_weight', 'wcac_taxonomy_match_weight', 'wcac_rating_weight',
            'wcac_title_category_match_bonus', 'wcac_exact_product_name_boost', 'wcac_multi_field_match_bonus',
            'wcac_all_keywords_title_boost', 'wcac_parent_preference_margin', 'wcac_relative_score_threshold',
            'wcac_negative_keyword_penalty', 'wcac_boost_term_score_value', 'wcac_devalue_term_score_value',
            'wcac_max_results_returned', 'wcac_out_of_stock_penalty', 'wcac_recency_boost_multiplier'
        ];
        $all_known_keys = array_unique(array_merge($optimizer_keys, $allowed_plugin_keys));

        // Define types of keys for easier processing
        $checkbox_keys = ['wcac_index_products', 'wcac_index_pages', 'wcac_index_posts', 'wcac_enable_debug_logging'];
        $text_area_keys = ['wcac_system_prompt', 'wcac_site_prompt', 'wcac_negative_keywords', 'wcac_synonym_map'];
        $json_keys = ['wcac_boost_terms', 'wcac_devalue_terms'];
        $llm_float_params = [
            'wcac_temperature' => ['min' => 0.0, 'max' => 2.0, 'default' => 0.7],
            'wcac_top_p' => ['min' => 0.0, 'max' => 1.0, 'default' => 1.0],
            'wcac_frequency_penalty' => ['min' => -2.0, 'max' => 2.0, 'default' => 0.0],
            'wcac_presence_penalty' => ['min' => -2.0, 'max' => 2.0, 'default' => 0.0],
        ];
        // Identify numeric scoring keys (all other known wcac_ keys not in the above categories)
        $other_specific_keys = ['wcac_api_key', 'wcac_custom_css', 'wcac_max_tokens', 'wcac_model', 'wcac_context_compression_algorithm'];
        $numeric_scoring_keys = array_diff($all_known_keys, $checkbox_keys, $text_area_keys, $json_keys, array_keys($llm_float_params), $other_specific_keys);

        // Process all known keys
        foreach ($all_known_keys as $key) {
            // Extra debug for specific scoring keys
            if ($key === 'wcac_relative_score_threshold' || $key === 'wcac_max_results_returned') {
                if (WP_DEBUG) error_log("[WCAC SANITIZER DEBUG] Processing key: {$key}. Input value: " . (isset($input[$key]) ? print_r($input[$key], true) : 'NOT SET IN INPUT'));
            }

            if (isset($input[$key])) { // Key was submitted in $input
                if (in_array($key, $checkbox_keys, true)) {
                    $sanitized_output[$key] = !empty($input[$key]) ? 1 : 0;
                } elseif ($key === 'wcac_api_key') {
                    $sanitized_output[$key] = sanitize_text_field($input[$key]);
                } elseif ($key === 'wcac_custom_css') {
                    $sanitized_output[$key] = wp_strip_all_tags(stripslashes($input[$key]));
                } elseif (in_array($key, $text_area_keys, true)) {
                    if ($key === 'wcac_negative_keywords') {
                        if (!function_exists('wcac_safe_split')) {
                            function wcac_safe_split($delimiter, $string) { return explode($delimiter, $string); }
                        }
                        $lines = wcac_safe_split("\\n", $input[$key]);
                        $sanitized_lines = array_map('sanitize_text_field', $lines);
                        $sanitized_output[$key] = implode("\\n", $sanitized_lines);
                    } else {
                        $sanitized_output[$key] = sanitize_textarea_field($input[$key]);
                    }
                } elseif (in_array($key, $json_keys, true)) {
                    $decoded = json_decode(stripslashes($input[$key]), true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $sanitized_json_data = [];
                        foreach ($decoded as $k_json => $v_json) {
                            $sanitized_k = sanitize_text_field((string)$k_json);
                            if (is_numeric($v_json)) {
                                $sanitized_v = floatval($v_json);
                            } elseif (is_string($v_json)) {
                                $sanitized_v = sanitize_text_field($v_json);
                            } else {
                                $sanitized_v = $v_json;
                            }
                            $sanitized_json_data[$sanitized_k] = $sanitized_v;
                        }
                        $sanitized_output[$key] = json_encode($sanitized_json_data);
                    } else {
                        $sanitized_output[$key] = '{}';
                        if (WP_DEBUG) error_log("WCAC DEBUG: Invalid JSON for {$key}");
                    }
                } elseif (array_key_exists($key, $llm_float_params)) {
                    $param_config = $llm_float_params[$key];
                    $val = (float)sanitize_text_field($input[$key]);
                    $sanitized_output[$key] = max($param_config['min'], min($param_config['max'], $val));
                } elseif ($key === 'wcac_max_tokens') {
                    $sanitized_output[$key] = max(0, (int)sanitize_text_field($input[$key]));
                } elseif ($key === 'wcac_model') {
                    $sanitized_output[$key] = sanitize_text_field($input[$key]);
                } elseif ($key === 'wcac_context_compression_algorithm') {
                    $valid_algos = ['none', 'title', 'category', 'fuzzy'];
                    $algo = sanitize_text_field($input[$key]);
                    $sanitized_output[$key] = in_array($algo, $valid_algos, true) ? $algo : 'none';
                } elseif (in_array($key, $numeric_scoring_keys, true)) {
                    if ($input[$key] === '') { // User explicitly cleared the field
                        // Use the structural default from registrar if available, otherwise fallback to 0.0
                        $default_for_empty = $this->scoring_field_defaults_map[$key] ?? 0.0;
                        // The Wcac_Utils check was non-functional and has been removed.
                        $sanitized_output[$key] = $default_for_empty;
                    } else {
                        $value = filter_var($input[$key], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                        $sanitized_numeric_value = (null !== $value && $value !== false && $value !== '') ? (float)$value : ($this->scoring_field_defaults_map[$key] ?? 0.0);
                        $sanitized_output[$key] = $sanitized_numeric_value;
                        if ($key === 'wcac_relative_score_threshold' || $key === 'wcac_max_results_returned') {
                            if (WP_DEBUG) error_log("[WCAC SANITIZER DEBUG] Key: {$key} was in input. Raw input: {$input[$key]}. Sanitized numeric value: {$sanitized_numeric_value}");
                        }
                    }
                } else {
                    // If key is in input but not covered by specific logic (should not happen for known keys)
                    if (WP_DEBUG) error_log("WCAC WARNING: Known key '{$key}' was in input but not sanitized by specific logic.");
                    // Fallback: sanitize as text field, or skip
                    // For safety, let's skip rather than assume text field for potentially structured data
                }
            } else { // Key was NOT submitted in $input
                if (in_array($key, $checkbox_keys, true)) {
                    // This specific checkbox was not submitted, so it implies it's false
                    $sanitized_output[$key] = 0;
                }
                // For other non-checkbox keys not in $input, their value from $persisted_options is kept.
                // If it's a brand new key not in $persisted_options either, the final loop for defaults (below) handles it.
                if ($key === 'wcac_relative_score_threshold' || $key === 'wcac_max_results_returned') {
                    if (WP_DEBUG) error_log("[WCAC SANITIZER DEBUG] Key: {$key} was NOT in input. Value from persisted_options: " . (isset($persisted_options[$key]) ? print_r($persisted_options[$key], true) : 'NOT SET IN PERSISTED') . ". Retained in sanitized_output: " . (isset($sanitized_output[$key]) ? print_r($sanitized_output[$key], true) : 'NOT SET'));
                }
            }
        }

        // --- Checkbox normalization: ensure all checkboxes are always set ---
        // For every checkbox field, if missing from input, set to 0 (unchecked)
        foreach ($checkbox_keys as $checkbox_key) {
            if (!isset($input[$checkbox_key])) {
                $sanitized_output[$checkbox_key] = 0;
            }
        }

        if (WP_DEBUG) {
            error_log('WCAC DEBUG: sanitize_settings output: ' . print_r($sanitized_output, true));
            // Specifically log the state of indexing checkboxes before returning
            if (isset($sanitized_output['wcac_index_products'])) {
                error_log('[WCAC SANITIZER FINAL] wcac_index_products: ' . ($sanitized_output['wcac_index_products'] ? 'true' : 'false'));
            } else {
                error_log('[WCAC SANITIZER FINAL] wcac_index_products: NOT SET');
            }
            if (isset($sanitized_output['wcac_index_pages'])) {
                error_log('[WCAC SANITIZER FINAL] wcac_index_pages: ' . ($sanitized_output['wcac_index_pages'] ? 'true' : 'false'));
            } else {
                error_log('[WCAC SANITIZER FINAL] wcac_index_pages: NOT SET');
            }
            if (isset($sanitized_output['wcac_index_posts'])) {
                error_log('[WCAC SANITIZER FINAL] wcac_index_posts: ' . ($sanitized_output['wcac_index_posts'] ? 'true' : 'false'));
            } else {
                error_log('[WCAC SANITIZER FINAL] wcac_index_posts: NOT SET');
            }
        }
        return $sanitized_output;
    }
} 