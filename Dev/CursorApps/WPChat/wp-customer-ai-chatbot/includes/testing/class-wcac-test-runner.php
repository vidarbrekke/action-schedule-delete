<?php

// Ensure wp_upload_dir is available before class definition
if (!function_exists('wp_upload_dir')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}

/**
 * @subpackage Wcac_Customer_AI_Chatbot/includes
 */

// Load necessary dependencies
require_once WCAC_PLUGIN_DIR . 'includes/api/class-wcac-api-handler.php'; // Path updated assuming API Handler moved
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-chatbot-rules.php'; // Path updated
require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-logger.php'; // Moved
require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-case.php'; // Moved
require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-result.php'; // Moved

class Wcac_Test_Runner
{
    /**
     * Centralized option key for all plugin settings (matches live bot).
     */
    public const OPTION_KEY = 'wcac_settings';

    private $test_cases = [];
    private $logger;
    private $test_run;

    /**
     * Initialize a new test runner.
     *
     * @param array $test_cases Array of test cases
     */
    public function __construct(array $test_cases)
    {
        error_log('WCAC TEST RUNNER: CONSTRUCTOR ENTRY');
        error_log('WCAC TEST RUNNER: Initializing test runner with ' . count($test_cases) . ' test cases');
        $this->test_cases = array_filter($test_cases, function($case) {
            return $case instanceof Wcac_Test_Case;
        });
        error_log('WCAC TEST RUNNER: ' . count($this->test_cases) . ' valid test cases found');
        $this->test_run = new Wcac_Test_Run();
        if (!empty($test_cases) && isset($test_cases[0]->expected_results)) {
             error_log("WCAC TEST RUNNER: first test_case expected_results = " . json_encode($test_cases[0]->expected_results));
        }

        // Ensure dependencies are loaded (consider a central autoloader later)
        if (!class_exists('Wcac_TestCase')) {
            require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-case.php'; // Moved
        }
        if (!class_exists('Wcac_ChatbotRules')) {
            // ... existing code ...
        }
    }

    /**
     * Run all test cases in this test run.
     *
     * @return string The run ID
     */
    public function run_tests(): string
    {
        error_log('WCAC TEST RUNNER: Starting test run. Test case count: ' . count($this->test_cases));
        $results = [];
        foreach ($this->test_cases as $i => $test_case) {
            error_log('WCAC TEST RUNNER: Running test case #' . ($i+1) . ' Query: ' . $test_case->get_query());
            try {
                $retriever = new Wcac_Content_Retriever();
                error_log('WCAC TEST RUNNER: Calling retrieve() for query: ' . $test_case->get_query());
                $retrieved = $retriever->retrieve($test_case->get_query(), []);
                error_log('WCAC TEST RUNNER: retrieve() returned ' . count($retrieved) . ' results for query: ' . $test_case->get_query());
            } catch (Exception $e) {
                error_log('WCAC TEST RUNNER: Exception during retrieve(): ' . $e->getMessage());
                $retrieved = [];
            }
            error_log('WCAC TEST RUNNER: Saving result for test case #' . ($i+1));
            $actual_results_full = $retrieved['final_results'] ?? [];
            $actual_result_ids = array_map(function($r) { return isset($r['id']) ? intval($r['id']) : null; }, $actual_results_full);
            $actual_result_ids = array_filter($actual_result_ids, fn($id) => $id !== null);
            error_log('WCAC TEST RUNNER: Actual results (full): ' . json_encode($actual_results_full));
            error_log('WCAC TEST RUNNER: Actual result IDs: ' . json_encode($actual_result_ids));

            // Filter out invalid items before logging
            $actual_results_full = array_filter($actual_results_full, function($item) {
                return is_array($item) && isset($item['id']) && intval($item['id']) > 0;
            });

            error_log('WCAC TEST RUNNER: About to log test result. actual_results_full = ' . json_encode($actual_results_full));
            $is_pass = $this->compare_results($test_case, $actual_result_ids);
            error_log('WCAC TEST RUNNER: Test case #' . ($i+1) . ' - ' . ($is_pass ? 'PASS' : 'FAIL'));

            $score = 0.0; // Score calculation can be added later

            // Defensive: ensure logger is always set
            if (!$this->logger) {
                require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-logger.php';
                $this->logger = new Wcac_Test_Logger();
            }
            $this->logger->log_test_result(
                $this->test_run,
                $test_case,
                $actual_results_full,
                $score,
                $is_pass
            );

            $results[] = [
                'query' => $test_case->get_query(),
                'expected_results' => $test_case->get_expected_results(),
                'actual_results' => $actual_results_full,
                'is_pass' => $is_pass,
                'score' => $score
            ];
        }
        error_log('WCAC TEST RUNNER: Test run complete.');
        return $this->test_run->get_run_id();
    }

    /**
     * Execute a test query and return the full result objects (not just IDs).
     *
     * @param string $query The search query
     * @return array Retrieval output with final_results and raw_candidates
     */
    private function execute_test_query_full(string $query): array
    {
        error_log('WCAC TEST RUNNER: Executing test query (full): ' . $query);
        try {
            require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-content-retriever.php';
            $retriever = new Wcac_Content_Retriever();
            $retrieval_output = $retriever->retrieve($query, []);
            if (!isset($retrieval_output['final_results']) || !is_array($retrieval_output['final_results'])) {
                error_log('WCAC TEST RUNNER: Content retriever did not return expected final_results array');
                return ['final_results' => [], 'raw_candidates' => []];
            }
            error_log('WCAC TEST RUNNER: Raw results (full): ' . json_encode($retrieval_output['final_results']));
            return $retrieval_output;
        } catch (Throwable $e) {
            error_log('WCAC TEST RUNNER: Error executing test query (full): ' . $e->getMessage());
            return ['final_results' => [], 'raw_candidates' => []];
        }
    }

    /**
     * Compare actual results with expected results.
     *
     * @param Wcac_Test_Case $test_case The test case
     * @param array $actual_results Array of actual result IDs
     * @return bool True if the test passed, false otherwise
     */
    private function compare_results(Wcac_Test_Case $test_case, array $actual_results): bool
    {
        error_log('WCAC TEST RUNNER: Comparing results');
        error_log('WCAC TEST RUNNER: Expected: ' . json_encode($test_case->get_expected_results()));
        error_log('WCAC TEST RUNNER: Actual: ' . json_encode($actual_results));

        $expected = array_map('intval', $test_case->get_expected_results());
        $actual = array_map('intval', $actual_results);

        // Check if all expected results are in the actual results
        $missing = array_diff($expected, $actual);
        if (!empty($missing)) {
            error_log('WCAC TEST RUNNER: Missing expected results: ' . json_encode($missing));
            return false;
        }

        // Check if all expected results appear in the first N positions
        // where N is the number of expected results
        $expected_count = count($expected);
        $actual_slice = array_slice($actual, 0, $expected_count);
        $found_all = true;
        foreach ($expected as $expected_id) {
            if (!in_array($expected_id, $actual_slice)) {
                $found_all = false;
                break;
            }
        }

        if (!$found_all) {
            error_log('WCAC TEST RUNNER: Expected results not found in top ' . $expected_count . ' positions');
            return false;
        }

        error_log('WCAC TEST RUNNER: Test passed');
        return true;
    }

    /**
     * Run all test cases from the latest test run.
     *
     * @return string|null The run ID if successful, null otherwise
     */
    public static function run_all()
    {
        error_log('WCAC TEST RUNNER: run_all() method entered');

        // Load and apply latest settings from DB
        if (class_exists('Wcac_ChatbotRules')) {
            $settings = get_option(self::OPTION_KEY, []);
            Wcac_ChatbotRules::apply_admin_settings($settings);
        }

        // Log the current RAG parameters in use
        $params = get_option(self::OPTION_KEY, []);
        error_log('WCAC TEST RUNNER: Parameters in use at test run: ' . json_encode($params));

        global $wpdb;
        $table = $wpdb->prefix . 'wcac_test_results';

        // Get the latest run_id
        $run_id = $wpdb->get_var("SELECT run_id FROM $table ORDER BY created_at DESC LIMIT 1");
        if (!$run_id) {
            error_log('WCAC: No test runs found in wcac_test_results table.');
            return null;
        }

        // Fetch all test cases for this run_id
        $rows = $wpdb->get_results($wpdb->prepare("SELECT query, expected_results, comment FROM $table WHERE run_id = %s", $run_id), ARRAY_A);
        if (empty($rows)) {
            error_log('WCAC: No test cases found for run_id ' . $run_id . ' in wcac_test_results table.');
            return null;
        }

        require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-case.php';
        $test_cases = [];
        foreach ($rows as $row) {
            $query = $row['query'] ?? '';
            $expected = isset($row['expected_results']) ? json_decode($row['expected_results'], true) : [];
            if (!is_array($expected)) {
                $expected = [$expected];
            }
            $comment = $row['comment'] ?? '';
            $test_cases[] = new Wcac_Test_Case($query, $expected, $comment);
        }

        if (empty($test_cases)) {
            error_log('WCAC: No valid test cases reconstructed from latest test run.');
            return null;
        }

        $runner = new self($test_cases);
        $new_run_id = $runner->run_tests();
        error_log('WCAC TEST RUNNER: run_all() completed. New run ID: ' . $new_run_id);
        return $new_run_id;
    }
}
