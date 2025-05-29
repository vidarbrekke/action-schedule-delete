<?php

/**
 * WP-CLI commands for WCAC plugin automation.
 */

if (defined('WP_CLI') && WP_CLI) {
    error_log('WCAC CLI: Loading CLI class');
    /**
     * Run the RAG test suite and output the run ID.
     *
     * ## OPTIONS
     *
     * [--csv=<file>]
     * : Optional path to a CSV file of test cases to use (defaults to admin-uploaded or built-in).
     *
     * ## EXAMPLES
     *
     *     wp wcac run_tests
     *     wp wcac run_tests --csv=tests/my_test_cases.csv
     */
    class WCAC_CLI_Command
    {
        public function __construct() {
            error_log('WCAC CLI: CLI command class instantiated');
        }

        /**
         * Test command to verify CLI is working.
         *
         * ## EXAMPLES
         *
         *     wp wcac test
         */
        public function test() {
            WP_CLI::success('WCAC CLI is working!');
        }

        /**
         * Run the RAG test suite.
         *
         * @param array $args
         * @param array $assoc_args
         */
        public function run_tests($args, $assoc_args)
        {
            // Optionally accept a CSV file
            $csv_file = $assoc_args['csv'] ?? null;
            if ($csv_file && file_exists($csv_file)) {
                // Use the provided CSV file for test cases
                // (Assumes a method exists to import/parse and run tests from CSV)
                $run_id = Wcac_Test_Runner::run_from_csv($csv_file);
            } else {
                // Use the default/admin-uploaded test suite
                $run_id = Wcac_Test_Runner::run_all();
            }
            if ($run_id) {
                WP_CLI::success("Test run complete. Run ID: $run_id");
            } else {
                WP_CLI::error("Test run failed or no run ID returned.");
            }
        }

        /**
         * Score test results for a given run ID.
         *
         * ## OPTIONS
         *
         * --run_id=<id>
         * : The run ID to score.
         *
         * [--top_n=<n>]
         * : The number of top results to consider (default: 10).
         *
         * ## EXAMPLES
         *
         *     wp wcac score_results --run_id=123 --top_n=10
         */
        public function score_results($args, $assoc_args)
        {
            global $wpdb;
            $run_id = isset($assoc_args['run_id']) ? intval($assoc_args['run_id']) : 0;
            $top_n = isset($assoc_args['top_n']) ? intval($assoc_args['top_n']) : 10;
            if (!$run_id) {
                WP_CLI::error('You must provide a valid --run_id.');
            }
            $table = $wpdb->prefix . 'wcac_test_results';
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE run_id = %d", $run_id));
            if (!$results) {
                WP_CLI::error("No results found for run_id $run_id");
            }
            $total = count($results);
            $pass = 0;
            foreach ($results as $row) {
                $expected = json_decode($row->expected_results, true);
                $actual = json_decode($row->actual_results, true);
                if (!is_array($expected)) {
                    $expected = [$expected];
                }
                if (!is_array($actual)) {
                    $actual = [$actual];
                }
                // Check if any expected result is in the top N actual results
                $found = false;
                foreach ($expected as $exp) {
                    if (in_array($exp, array_slice($actual, 0, $top_n))) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    $pass++;
                }
            }
            $rate = $total ? round(100 * $pass / $total, 2) : 0;
            WP_CLI::success("$pass/$total tests passed (expected in top $top_n). Pass rate: $rate%.");
        }

        /**
         * Run random search optimization for RAG scoring parameters.
         *
         * ## OPTIONS
         *
         * [--iterations=<n>]
         * : Number of random samples to try (default: 20)
         *
         * ## EXAMPLES
         *
         *     wp wcac optimize_random --iterations=20
         */
        public function optimize_random($args, $assoc_args)
        {
            global $wpdb;
            $ranges = [
                'wcac_parent_product_weight' => [100, 200],
                'wcac_variation_product_weight' => [0, 10],
                'wcac_title_match_weight' => [0, 100],
                'wcac_content_match_weight' => [0, 100],
                'wcac_category_match_weight' => [0, 100],
                'wcac_tag_match_weight' => [0, 100],
                'wcac_on_sale_weight' => [0, 100],
                'wcac_direct_title_match_bonus' => [0, 200],
                'wcac_title_category_match_boost' => [0, 200],
                'wcac_exact_product_name_boost' => [0, 300],
                'wcac_multi_field_match_bonus' => [50, 200],
                'wcac_all_keywords_in_title_boost' => [0, 200],
                'wcac_parent_preference_margin' => [0, 100],
                'wcac_relative_score_threshold' => [0, 1],
                'wcac_max_results_returned' => [5, 20],
                'wcac_negative_keyword_penalty' => [-200, 0],
                'wcac_menu_match_weight' => [0, 100],
                'wcac_attribute_match_weight' => [0, 100],
                'wcac_parent_category_match_weight' => [0, 100],
                'wcac_taxonomy_match_weight' => [0, 100],
                'wcac_boost_term_value' => [0, 100],
                'wcac_devalue_term_value' => [-100, 0],
                'wcac_recency_boost_multiplier' => [0, 100],
                'wcac_outofstock_penalty' => [-200, 0],
                'wcac_rating_weight' => [0, 100]
            ];
            // Get latest run_id and count test cases
            $table = $wpdb->prefix . 'wcac_test_results';
            $run_id = $wpdb->get_var("SELECT run_id FROM $table ORDER BY created_at DESC LIMIT 1");
            $test_count = 0;
            if ($run_id) {
                $test_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE run_id = %s", $run_id));
            }
            if (!$test_count) {
                $test_count = 5; // fallback
            }
            $iterations = max(10, $test_count * 2);
            if (isset($assoc_args['iterations'])) {
                $iterations = max(10, intval($assoc_args['iterations']));
            }
            WP_CLI::log("Running $iterations optimizer iterations for $test_count test cases.");
            $log_file = WP_PLUGIN_DIR . '/wp-customer-ai-chatbot/optimizer_log.csv';
            file_put_contents($log_file, "iteration,run_id,score,params\n"); // header
            $best_score = -1;
            $best_params = [];
            // Build grid of parameter values (1/10th increments)
            $param_grid = [];
            foreach ($ranges as $key => [$min, $max]) {
                $steps = 10;
                $step_size = ($max - $min) / $steps;
                $values = [];
                for ($i = 0; $i <= $steps; $i++) {
                    $values[] = is_int($min) && is_int($max)
                        ? round($min + $i * $step_size)
                        : round($min + $i * $step_size, 2);
                }
                $param_grid[$key] = array_unique($values);
            }
            // For now, sample random combinations from the grid
            $grid_keys = array_keys($param_grid);
            for ($i = 0; $i < $iterations; $i++) {
                $params = [];
                foreach ($grid_keys as $key) {
                    $values = $param_grid[$key];
                    $params[$key] = $values[array_rand($values)];
                }
                Wcac_Settings_Optimizer::set_parameters($params);
                WP_CLI::log("[$i] Updated parameters: " . json_encode($params));
                // Run test suite (default/admin-uploaded test suite)
                $run_cmd = 'wp wcac run_tests';
                $output = [];
                $return_var = 0;
                exec($run_cmd, $output, $return_var);
                $run_id = null;
                foreach ($output as $line) {
                    if (preg_match('/Run ID: (\d+)/', $line, $m)) {
                        $run_id = $m[1];
                        break;
                    }
                }
                if (!$run_id) {
                    WP_CLI::warning("Test run failed. Output:\n" . implode("\n", $output));
                    continue;
                }
                WP_CLI::log("Run ID: $run_id");
                // Score results
                $score_cmd = "wp wcac score_results --run_id=$run_id --top_n=10";
                $score_output = [];
                exec($score_cmd, $score_output);
                $score_line = end($score_output);
                preg_match('/Pass rate: ([\d.]+)%/', $score_line, $m);
                $score = isset($m[1]) ? floatval($m[1]) : 0;
                WP_CLI::log("Score: $score");
                // Log
                $log_row = [$i, $run_id, $score, json_encode($params)];
                file_put_contents($log_file, implode(",", $log_row) . "\n", FILE_APPEND);
                if ($score > $best_score) {
                    $best_score = $score;
                    $best_params = $params;
                }
            }
            // Update plugin settings with best parameters
            Wcac_Settings_Optimizer::set_parameters($best_params);
            WP_CLI::success("Best score: $best_score");
            WP_CLI::success("Best parameters: " . json_encode($best_params));
            WP_CLI::success("CSV log written to $log_file. You can visualize this file in Excel or Python.");
            // Placeholder: LLM analysis of failed test cases
            WP_CLI::log("[TODO] LLM analysis of failed test cases will be integrated here.");

            // Clear debug.log after optimization finishes
            $debug_log_path = WP_CONTENT_DIR . '/debug.log';
            WP_CLI::log("Attempting to clear debug log at: " . $debug_log_path);
            if (file_exists($debug_log_path)) {
                WP_CLI::log("debug.log exists. Checking if writable...");
                if (is_writable($debug_log_path)) {
                    WP_CLI::log("debug.log is writable. Attempting file_put_contents...");
                    // Remove @ to see potential errors/warnings
                    $result = file_put_contents($debug_log_path, '');
                    if ($result === false) {
                        WP_CLI::warning("file_put_contents FAILED to clear debug.log at $debug_log_path - check file permissions or other errors.");
                    } else {
                        WP_CLI::success("file_put_contents successfully cleared debug.log (returned: " . var_export($result, true) . ").");
                    }
                } else {
                     WP_CLI::warning("debug.log exists but is NOT writable at $debug_log_path.");
                }
            } else {
                 WP_CLI::log("debug.log not found at $debug_log_path, skipping clear.");
            }
        }
    }
    WP_CLI::add_command('wcac', 'WCAC_CLI_Command');
}
