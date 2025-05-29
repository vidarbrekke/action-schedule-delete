<?php
// phpcs:ignoreFile -- Suppress linter warnings for undefined WordPress core functions/constants in plugin context

// Ensure WordPress option functions are available for linter and runtime
if (!function_exists('update_option') || !function_exists('get_option')) {
    if (defined('ABSPATH')) {
        require_once ABSPATH . 'wp-includes/option.php';
    }
}

require_once plugin_dir_path(__FILE__) . '/../common/wcac-log-helper.php';

/**
 * WCAC Settings Optimizer: Utility for reading and updating RAG scoring parameters.
 */
class Wcac_Settings_Optimizer
{
    /**
     * List of all tunable RAG parameter keys.
     */
    private static array $parameter_keys = [
        'wcac_parent_product_weight',
        'wcac_variation_product_weight',
        'wcac_title_match_weight',
        'wcac_content_match_weight',
        'wcac_category_match_weight',
        'wcac_tag_match_weight',
        'wcac_on_sale_weight',
        'wcac_direct_title_match_bonus',
        'wcac_title_category_match_boost',
        'wcac_exact_product_name_boost',
        'wcac_multi_field_match_bonus',
        'wcac_all_keywords_in_title_boost',
        'wcac_parent_preference_margin',
        'wcac_relative_score_threshold',
        'wcac_max_results_returned',
        'wcac_negative_keyword_penalty',
        'wcac_boost_terms',
        'wcac_devalue_terms',
        'wcac_compound_product_names',
        'wcac_menu_match_weight',
        'wcac_attribute_match_weight',
        'wcac_parent_category_match_weight',
        'wcac_taxonomy_match_weight',
        'wcac_boost_term_value',
        'wcac_devalue_term_value',
        'wcac_recency_boost_multiplier',
        'wcac_outofstock_penalty',
        'wcac_rating_weight',
    ];

    /**
     * Metadata for tunable RAG parameters.
     */
    private static array $parameter_metadata = [
        'wcac_parent_product_weight'      => ['min' => 0,   'max' => 500],
        'wcac_variation_product_weight'   => ['min' => 0,   'max' => 500],
        'wcac_title_match_weight'         => ['min' => 0,   'max' => 100],
        'wcac_content_match_weight'       => ['min' => 0,   'max' => 100],
        'wcac_category_match_weight'      => ['min' => 0,   'max' => 100],
        'wcac_tag_match_weight'           => ['min' => 0,   'max' => 100],
        'wcac_direct_title_match_bonus'   => ['min' => 0,   'max' => 200],
        'wcac_title_category_match_boost' => ['min' => 0,   'max' => 200],
        'wcac_exact_product_name_boost'   => ['min' => 0,   'max' => 300],
        'wcac_multi_field_match_bonus'    => ['min' => 0,   'max' => 200],
        'wcac_all_keywords_in_title_boost'=> ['min' => 0,   'max' => 200],
        'wcac_parent_preference_margin'   => ['min' => 0,   'max' => 100],
        'wcac_relative_score_threshold'   => ['min' => 0,   'max' => 1],
        'wcac_negative_keyword_penalty'   => ['min' => -200,'max' => 0],
        'wcac_menu_match_weight'          => ['min' => 0,   'max' => 100],
        'wcac_attribute_match_weight'     => ['min' => 0,   'max' => 100],
        'wcac_parent_category_match_weight'=>['min' => 0,   'max' => 100],
        'wcac_taxonomy_match_weight'      => ['min' => 0,   'max' => 100],
        'wcac_boost_term_value'           => ['min' => 0,   'max' => 100],
        'wcac_devalue_term_value'         => ['min' => -100,'max' => 0],
    ];

    // New: Explicit list of parameter keys to EXCLUDE from any optimization randomization.
    private static array $excluded_from_optimization_keys = [
        'wcac_max_results_returned',
        'wcac_outofstock_penalty',
        'wcac_recency_boost_multiplier',
        'wcac_rating_weight',
        'wcac_on_sale_weight',
        // Non-integer parameters like 'wcac_boost_terms' are implicitly excluded
        // by the is_numeric check and lack of presence in $parameter_metadata for numeric tuning.
    ];

    // New: For temporarily overriding settings during a test run within the optimizer
    private static ?array $override_settings_for_test_run = null;

    /**
     * Get the list of all tunable RAG parameter keys.
     */
    public static function get_parameter_keys(): array
    {
        return self::$parameter_keys;
    }

    /**
     * Get current RAG parameter values as an associative array.
     * If override_settings_for_test_run is set, it returns those.
     * Otherwise, fetches from WordPress options.
     */
    public static function get_parameters(): array
    {
        $final_params = [];
        if (self::$override_settings_for_test_run !== null) {
            // Use override, ensuring all keys from self::$parameter_keys are present,
            // falling back to DB values for any keys not in the override set (though ideally override is complete).
            $db_settings = get_option('wcac_settings', []);
            foreach (self::$parameter_keys as $key) {
                $final_params[$key] = self::$override_settings_for_test_run[$key] ?? ($db_settings[$key] ?? null);
            }
            // error_log('[WCAC Optimizer Debug] Using OVERRIDE parameters: ' . json_encode(self::$override_settings_for_test_run));
            return $final_params;
        }

        $settings = get_option('wcac_settings', []);
        foreach (self::$parameter_keys as $key) {
            $final_params[$key] = $settings[$key] ?? null;
        }
        // error_log('[WCAC Optimizer Debug] Using DB parameters: ' . json_encode($final_params));
        return $final_params;
    }

    /**
     * Sets temporary override parameters for a test run.
     * @param array $params The parameters to override with.
     */
    public static function set_override_parameters_for_test_run(array $params): void
    {
        self::$override_settings_for_test_run = $params;
        // error_log('[WCAC Optimizer Debug] OVERRIDE parameters SET: ' . json_encode($params));
    }

    /**
     * Clears any temporary override parameters.
     */
    public static function clear_override_parameters_for_test_run(): void
    {
        // error_log('[WCAC Optimizer Debug] OVERRIDE parameters CLEARED. Was: ' . json_encode(self::$override_settings_for_test_run));
        self::$override_settings_for_test_run = null;
    }

    /**
     * Update RAG parameters in the options table.
     * Only updates keys present in $new_params.
     */
    public static function set_parameters(array $new_params): void
    {
        $settings = function_exists('get_option') ? get_option('wcac_settings', []) : [];
        wcac_log('WCAC OPTIMIZER: Parameters before update: ' . json_encode($settings), 'info', 'optimizer');
        
        // Ensure all parameters are present in the settings array
        foreach (self::$parameter_keys as $key) {
            if (array_key_exists($key, $new_params)) {
                $settings[$key] = $new_params[$key];
                wcac_log("WCAC OPTIMIZER: Setting $key to " . $new_params[$key], 'info', 'optimizer');
            }
        }
        
        wcac_log('WCAC OPTIMIZER: Parameters to be updated: ' . json_encode($settings), 'info', 'optimizer');
        
        // Ensure update_option is available
        if (!function_exists('update_option')) {
            if (defined('ABSPATH')) {
                require_once ABSPATH . 'wp-includes/option.php';
            }
        }
        
        if (function_exists('update_option')) {
            $result = update_option('wcac_settings', $settings);
            wcac_log('WCAC OPTIMIZER: update_option result: ' . ($result ? 'success' : 'failed'), 'info', 'optimizer');
        } else {
            wcac_log('[WCAC Optimizer] ERROR: update_option function not available at line 85.', 'error', 'optimizer');
        }

        // Verify the update
        $after = function_exists('get_option') ? get_option('wcac_settings', []) : [];
        wcac_log('WCAC OPTIMIZER: Parameters after update: ' . json_encode($after), 'info', 'optimizer');
        
        // Verify each parameter was updated correctly
        foreach (self::$parameter_keys as $key) {
            if (array_key_exists($key, $new_params)) {
                $expected = $new_params[$key];
                $actual = $after[$key] ?? null;
                if ($expected !== $actual) {
                    wcac_log("WCAC OPTIMIZER WARNING: Parameter $key not updated correctly. Expected: $expected, Got: " . ($actual ?? 'null'), 'warning', 'optimizer');
                }
            }
        }
    }

    /**
     * Reset all tunable parameters to their default values (if needed).
     * (Implementation can be added if default values are defined elsewhere.)
     */
    public static function reset_parameters(): void
    {
        // TODO: Implement if default values are available.
    }

    /**
     * Generate a random parameter set based on a given set of base parameters.
     * Applies +/- 20 integer unit change to tunable numeric parameters, clamped to min/max.
     * Explicitly excluded numeric parameters and all non-numeric parameters are not changed from the base.
     *
     * @param array $base_params Associative array of base parameter values to vary from.
     * @return array The new set of parameters with some potentially randomized.
     */
    private function generate_random_param_set(array $base_params): array
    {
        $new_params = $base_params; // Start by copying all base parameters

        // error_log('[WCAC Optimizer] Generating random parameter set. Base values: ' . json_encode($base_params));

        // Identify keys that are eligible for numeric tuning based on the $base_params
        $tunable_keys = array_filter(self::$parameter_keys, function ($key) use ($base_params) {
            // Rule 1: Must NOT be in the explicit global exclusion list
            if (in_array($key, self::$excluded_from_optimization_keys, true)) {
                // error_log("[WCAC Optimizer] Filter: $key is EXPLICITLY EXCLUDED.");
                return false;
            }

            // Rule 2: Must have metadata defined (for min/max bounds)
            if (!isset(self::$parameter_metadata[$key])) {
                // error_log("[WCAC Optimizer] Filter: $key has NO METADATA for numeric tuning.");
                return false;
            }
            
            // Rule 3: Current value (from base_params) must be numeric to apply numeric randomization.
            // Also, we only apply integer randomization here. Floats like 'relative_score_threshold'
            // are not handled by this +/- 20 logic.
            $current_value = $base_params[$key] ?? null;
            if (!is_numeric($current_value) || is_float($current_value)) {
                 // Also check if the parameter is specifically for floats, even if it's currently an integer
                if ($key === 'wcac_relative_score_threshold' || $key === 'wcac_boost_term_value' || $key === 'wcac_devalue_term_value') {
                    // error_log("[WCAC Optimizer] Filter: $key is a FLOAT parameter (value: " . var_export($current_value, true) . ") and not tuned by integer randomizer.");
                    return false;
                }
                // error_log("[WCAC Optimizer] Filter: $key is NON-NUMERIC or FLOAT (value: " . var_export($current_value, true) . ") and not tuned by integer randomizer.");
                return false;
            }
            
            // error_log("[WCAC Optimizer] Filter: $key IS TUNABLE by integer randomizer.");
            return true; // Parameter is tunable by integer randomization
        });

        // error_log('[WCAC Optimizer] Identified tunable integer keys: ' . json_encode(array_values($tunable_keys)));

        foreach ($tunable_keys as $key) {
            $meta = self::$parameter_metadata[$key];
            $current_value_int = (int)($base_params[$key]); // Treat current value from base_params as integer

            $parameter_min_bound = $meta['min'];
            $parameter_max_bound = $meta['max'];

            // Calculate the +/- 20 range from the current integer value
            $random_range_min = $current_value_int - 20;
            $random_range_max = $current_value_int + 20;

            // Clamp this randomization range to the parameter's absolute min/max bounds
            $clamped_min = max($parameter_min_bound, $random_range_min);
            $clamped_max = min($parameter_max_bound, $random_range_max);

            $final_value = $current_value_int; // Default to current if range is invalid

            if ($clamped_min <= $clamped_max) { // Ensure the range is valid (min <= max)
                $final_value = rand($clamped_min, $clamped_max);
                // error_log("[WCAC Optimizer] Randomized $key: base=$current_value_int, meta_bounds=[$parameter_min_bound, $parameter_max_bound], tuned_range=[$clamped_min, $clamped_max], new_value=$final_value");
            } else {
                $final_value = max($parameter_min_bound, min($parameter_max_bound, $current_value_int));
                // error_log("[WCAC Optimizer] Invalid random range for $key (clamped_min=$clamped_min, clamped_max=$clamped_max). Using clamped original value: $final_value");
            }
            $new_params[$key] = $final_value;
        }

        // error_log('[WCAC Optimizer] Generated random parameter set. Final values for this set: ' . json_encode($new_params));
        return $new_params;
    }

    /**
     * Score test results: count total matches, pass count, and total expected.
     * @param array $results
     * @return array
     */
    private function score_test_results(array $results): array {
        $pass_count = 0;
        $total_matches = 0;
        $total_expected = 0;
        foreach ($results as $result) {
            $expected = json_decode($result['expected_results'], true) ?: [];
            $actual = json_decode($result['actual_results'], true) ?: [];
            $matches = count(array_intersect(array_map('intval', $expected), array_map('intval', $actual)));
            $total_matches += $matches;
            $total_expected += count($expected);
            if ($result['pass_fail'] === 'pass') {
                $pass_count++;
            }
        }
        return [
            'total_matches' => $total_matches,
            'pass_count' => $pass_count,
            'total_expected' => $total_expected,
        ];
    }

    /**
     * Run a batch of optimizer iterations, tracking the parameter set with the most failed tests.
     * @param int $current_idx
     * @param int $batch_size
     * @param int $total_iterations
     * @param array $initial_session_settings The baseline settings for the entire optimization session.
     * @return array|false An array with batch results or false on error.
     */
    public function run_batch(int $current_idx, int $batch_size, int $total_iterations, array $initial_session_settings)
    {
        try {
            // error_log('[WCAC Optimizer] Starting batch: current_idx=' . $current_idx . ', batch_size=' . $batch_size . ', total=' . $total_iterations);
            // error_log('[WCAC Optimizer] Initial Session Settings for this batch: ' . json_encode($initial_session_settings));

            // Load test cases
            require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-runner.php';
            require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-case.php';

            // Get test cases from the database
            global $wpdb;
            $table = $wpdb->prefix . 'wcac_test_results';
            $run_id = $wpdb->get_var("SELECT run_id FROM {$table} ORDER BY created_at DESC LIMIT 1");
            if (!$run_id) {
                wcac_log('[WCAC Optimizer] No test runs found in database', 'info', 'optimizer');
                return false;
            }

            $rows = $wpdb->get_results($wpdb->prepare("SELECT query, expected_results FROM {$table} WHERE run_id = %s", $run_id), ARRAY_A);
            if (empty($rows)) {
                wcac_log('[WCAC Optimizer] No test cases found for run_id ' . $run_id, 'info', 'optimizer');
                // Attempt to use the latest test cases if the optimizer run_id is not found or empty.
                // This can happen if the optimizer is started before any test run.
                $latest_run_id = $wpdb->get_var("SELECT MAX(run_id) FROM {$table}");
                if ($latest_run_id) {
                    $rows = $wpdb->get_results($wpdb->prepare("SELECT query, expected_results FROM {$table} WHERE run_id = %s", $latest_run_id), ARRAY_A);
                    if (empty($rows)) {
                         wcac_log('[WCAC Optimizer] No test cases found for latest run_id ' . $latest_run_id . ' either.', 'info', 'optimizer');
                         return false; // Still no test cases
                    }
                    wcac_log('[WCAC Optimizer] Using test cases from latest run_id ' . $latest_run_id . ' as fallback.', 'info', 'optimizer');
                } else {
                    wcac_log('[WCAC Optimizer] No test runs found in the database at all.', 'info', 'optimizer');
                    return false; // No test cases to run against
                }
            }

            $test_cases = [];
            foreach ($rows as $row) {
                $query = $row['query'] ?? '';
                $expected = isset($row['expected_results']) ? json_decode($row['expected_results'], true) : [];
                if (!is_array($expected)) {
                    $expected = [$expected];
                }
                $test_cases[] = new Wcac_Test_Case($query, $expected);
            }

            if (empty($test_cases)) {
                wcac_log('[WCAC Optimizer] No valid test cases reconstructed from database', 'info', 'optimizer');
                return false;
            }

            // Get current parameter values - NO LONGER USED as baseline for generate_random_param_set for Fixed Session Baseline
            // $current_params = self::get_parameters(); // This would get latest from DB or override.

            $best_total_matches_for_batch = -1;
            $best_pass_count_for_batch = -1;
            $best_param_set_for_batch = $initial_session_settings; // Initialize with session baseline

            for ($i = 0; $i < $batch_size; $i++) {
                // Generate params based on the fixed initial_session_settings
                $param_set_to_test = $this->generate_random_param_set($initial_session_settings);
                
                // DO NOT save to DB: self::set_parameters($param_set_to_test);
                
                // Use override mechanism to apply these params for the test run
                self::set_override_parameters_for_test_run($param_set_to_test);

                $test_runner = new Wcac_Test_Runner($test_cases);
                $new_run_id = $test_runner->run_tests(); // Test runner will use overridden params

                self::clear_override_parameters_for_test_run(); // Clear override after test

                if (!$new_run_id) {
                    wcac_log('[WCAC Optimizer] Failed to run tests for param set. Param set: ' . json_encode($param_set_to_test), 'warning', 'optimizer');
                    continue;
                }
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT expected_results, actual_results, pass_fail FROM {$table} WHERE run_id = %s",
                    $new_run_id
                ), ARRAY_A);
                $score = $this->score_test_results($results);
                // Prefer higher total_matches, break ties with pass_count
                if ($score['total_matches'] > $best_total_matches_for_batch ||
                    ($score['total_matches'] == $best_total_matches_for_batch && $score['pass_count'] > $best_pass_count_for_batch)) {
                    $best_total_matches_for_batch = $score['total_matches'];
                    $best_pass_count_for_batch = $score['pass_count'];
                    $best_param_set_for_batch = $param_set_to_test;
                    // error_log("[WCAC Optimizer] New best for batch: total_matches=$best_total_matches_for_batch, pass_count=$best_pass_count_for_batch. Params: " . json_encode($best_param_set_for_batch));
                }
            }

            // DO NOT save batch best to DB here: self::set_parameters($best_param_set_for_batch);
            // The Optimizer_Job will handle saving the overall session best at the very end.
            // error_log('[WCAC Optimizer] Batch best param set: ' . json_encode($best_param_set_for_batch) . ', with total_matches=' . $best_total_matches_for_batch . ', pass_count=' . $best_pass_count_for_batch);
            // error_log('[WCAC Optimizer] Batch complete: ' . $batch_size . ' iterations run.');

            // Calculate progress
            $progress = min(100, round(($current_idx + $batch_size) / $total_iterations * 100));
            $next_idx = $current_idx + $batch_size;
            $done = $next_idx >= $total_iterations;

            $result = [
                'progress' => $progress,
                'current_idx' => $next_idx,
                'done' => $done,
                'settings_suggestions' => $best_param_set_for_batch, // This is the best from THIS batch
                'best_score_details_for_batch' => [ // Include the score details
                    'total_matches' => $best_total_matches_for_batch,
                    'pass_count' => $best_pass_count_for_batch,
                    // We might not have total_expected from score_test_results directly if it's just returning counts
                    // Let's assume score_test_results returns a structure that includes these if needed, or adjust later.
                    // For now, focusing on matches and pass_count for comparison.
                ]
            ];

            // error_log('[WCAC Optimizer] Batch result: ' . print_r($result, true));
            return $result;
        } catch (Throwable $e) {
            wcac_log('[WCAC Optimizer] Error in run_batch: ' . $e->getMessage(), 'error', 'optimizer');
            return false;
        }
    }

    /**
     * Apply settings to the database.
     *
     * @param array $settings The settings to apply
     * @return bool True if successful, false otherwise
     */
    public static function apply_settings(array $settings): bool
    {
        try {
            global $wpdb;
            // Ensure option functions are available
            if (!function_exists('get_option')) {
                if (defined('ABSPATH')) {
                    require_once ABSPATH . 'wp-includes/option.php';
                }
            }
            if (!function_exists('update_option')) {
                if (defined('ABSPATH')) {
                    require_once ABSPATH . 'wp-includes/option.php';
                }
            }

            wcac_log('[WCAC Optimizer] Incoming settings to apply: ' . json_encode($settings), 'info', 'optimizer');
            $current_settings = get_option('wcac_settings', []);
            wcac_log('[WCAC Optimizer] Current settings before update: ' . json_encode($current_settings), 'info', 'optimizer');
            $new_settings = array_merge($current_settings, $settings);
            wcac_log('[WCAC Optimizer] Merged settings to save: ' . json_encode($new_settings), 'info', 'optimizer');
            // Check if $new_settings is serializable
            $serialized = @serialize($new_settings);
            if ($serialized === false) {
                wcac_log('[WCAC Optimizer] ERROR: $new_settings is not serializable!', 'error', 'optimizer');
                return false;
            }
            // Ensure update_option is available
            if (!function_exists('update_option')) {
                if (defined('ABSPATH')) {
                    require_once ABSPATH . 'wp-includes/option.php';
                }
            }

            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.OptionFunctions.update_option
            $result = false; // Default to false
            if (function_exists('update_option')) { // Ensure check is present
                $result = update_option('wcac_settings', $new_settings);
            } else {
                wcac_log('[WCAC Optimizer] ERROR: update_option function not available at line 271.', 'error', 'optimizer');
            }
            wcac_log('[WCAC Optimizer] update_option result: ' . var_export($result, true), 'info', 'optimizer');
            wcac_log('[WCAC Optimizer] $wpdb->last_error: ' . (isset($wpdb->last_error) ? $wpdb->last_error : 'N/A'), 'info', 'optimizer');

            // Ensure get_option is available
            if (!function_exists('get_option')) {
                if (defined('ABSPATH')) {
                    require_once ABSPATH . 'wp-includes/option.php';
                }
            }
            $after_settings = get_option('wcac_settings', []);
            wcac_log('[WCAC Optimizer] Settings after update: ' . json_encode($after_settings), 'info', 'optimizer');

            if ($result) {
                wcac_log('[WCAC Optimizer] Settings applied successfully', 'success', 'optimizer');
                return true;
            } else {
                // Check for DB error
                if (!empty($wpdb->last_error)) {
                    wcac_log('[WCAC Optimizer] DB error on update_option: ' . $wpdb->last_error, 'error', 'optimizer');
                    return false;
                }
                // If settings are already equal, treat as success
                if ($after_settings == $new_settings) {
                    wcac_log('[WCAC Optimizer] No change needed, settings already up to date. Returning true.', 'info', 'optimizer');
                    return true;
                }
                // Otherwise, log the diff and treat as failure
                if ($after_settings != $new_settings) {
                    $diff = array_diff_assoc($new_settings, $after_settings);
                    wcac_log('[WCAC Optimizer] DIFF: ' . json_encode($diff), 'warning', 'optimizer');
                }
                wcac_log('[WCAC Optimizer] Failed to apply settings: update_option returned false and settings not updated.', 'error', 'optimizer');
                return false;
            }
        } catch (Throwable $e) {
            wcac_log('[WCAC Optimizer] Error applying settings: ' . $e->getMessage() . ' | ' . $e->getTraceAsString(), 'error', 'optimizer');
            return false;
        }
    }

    /**
     * Get suggested settings based on test results.
     *
     * @param float $pass_rate The current pass rate
     * @return array The suggested settings
     */
    private function get_suggested_settings(float $pass_rate): array
    {
        wcac_log('[WCAC Optimizer] Generating settings suggestions for pass rate: ' . $pass_rate, 'info', 'optimizer');

        // Base settings that work well in most cases
        $suggestions = [
            'wcac_parent_product_weight' => 150,
            'wcac_variation_product_weight' => 5,
            'wcac_title_match_weight' => 15,
            'wcac_content_match_weight' => 15,
            'wcac_category_match_weight' => 35,
            'wcac_tag_match_weight' => 15,
            'wcac_direct_title_match_bonus' => 100,
            'wcac_title_category_match_boost' => 150,
            'wcac_exact_product_name_boost' => 200,
            'wcac_multi_field_match_bonus' => 75,
            'wcac_all_keywords_in_title_boost' => 50,
            'wcac_parent_preference_margin' => 10,
            'wcac_relative_score_threshold' => 0.5,
            'wcac_max_results_returned' => 10,
            'wcac_negative_keyword_penalty' => 0.5,
            'wcac_boost_terms' => 1.5,
            'wcac_devalue_terms' => 0.5,
            'wcac_compound_product_names' => 1.0,
            'wcac_menu_match_weight' => 10,
            'wcac_attribute_match_weight' => 10,
            'wcac_parent_category_match_weight' => 10,
            'wcac_taxonomy_match_weight' => 10,
            'wcac_boost_term_value' => 15.0,
            'wcac_devalue_term_value' => -15.0,
        ];

        // If pass rate is low, increase weights to make matching more lenient
        if ($pass_rate < 50) {
            $suggestions['wcac_title_match_weight'] = 20;
            $suggestions['wcac_content_match_weight'] = 20;
            $suggestions['wcac_category_match_weight'] = 40;
            $suggestions['wcac_tag_match_weight'] = 20;
        }

        wcac_log('[WCAC Optimizer] Generated settings suggestions: ' . json_encode($suggestions), 'info', 'optimizer');
        return $suggestions;
    }
}
