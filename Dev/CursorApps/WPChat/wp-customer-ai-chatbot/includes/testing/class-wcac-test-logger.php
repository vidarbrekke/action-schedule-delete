<?php

/**
 * Logs test results to the database.
 */
class Wcac_Test_Logger
{
    /**
     * Log a test result to the database.
     *
     * @param Wcac_Test_Run $test_run The test run object
     * @param Wcac_Test_Case $test_case The test case object
     * @param array $actual_results Array of actual result IDs
     * @param float $score The test score
     * @param bool $is_pass Whether the test passed
     * @return bool True if logging was successful, false otherwise
     */
    public function log_test_result(Wcac_Test_Run $test_run, Wcac_Test_Case $test_case, array $actual_results, float $score, bool $is_pass): bool
    {
        try {
            error_log('WCAC TEST LOGGER: Logging test result');
            error_log('WCAC TEST LOGGER: Test run ID: ' . $test_run->get_run_id());
            error_log('WCAC TEST LOGGER: Query: ' . $test_case->get_query());
            error_log('WCAC TEST LOGGER: Expected results: ' . json_encode($test_case->get_expected_results()));
            error_log('WCAC TEST LOGGER: Actual results: ' . json_encode($actual_results));
            error_log('WCAC TEST LOGGER: Score: ' . $score);
            error_log('WCAC TEST LOGGER: Pass/Fail: ' . ($is_pass ? 'PASS' : 'FAIL'));

            global $wpdb;
            $table = $wpdb->prefix . 'wcac_test_results';

            $data = [
                'run_id' => $test_run->get_run_id(),
                'query' => $test_case->get_query(),
                'expected_results' => json_encode($test_case->get_expected_results()),
                'actual_results' => json_encode($actual_results),
                'score' => $score,
                'pass_fail' => $is_pass ? 'pass' : 'fail',
                'comment' => $test_case->get_comment(),
                'created_at' => current_time('mysql')
            ];

            $formats = [
                'run_id' => '%s',
                'query' => '%s',
                'expected_results' => '%s',
                'actual_results' => '%s',
                'score' => '%f',
                'pass_fail' => '%s',
                'comment' => '%s',
                'created_at' => '%s'
            ];

            error_log('WCAC TEST LOGGER: About to insert. Data: ' . json_encode($data));
            $result = $wpdb->insert($table, $data, array_values($formats));
            if ($result === false) {
                error_log('WCAC TEST LOGGER: Failed to insert test result. WPDB Error: ' . $wpdb->last_error);
                return false;
            }

            error_log('WCAC TEST LOGGER: Successfully logged test result');
            return true;

        } catch (Throwable $e) {
            error_log('WCAC TEST LOGGER: Exception in log_test_result: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log a simple test result to the database (for Wcac_Test_Runner2).
     * @param string $query
     * @param array $expected
     * @param array $actual
     * @param bool $is_pass
     * @return bool
     */
    public function log_test_result_simple($query, $expected, $actual, $is_pass, $run_id = null) {
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'wcac_test_results';
            $data = [
                'run_id' => $run_id ?: uniqid('run_', true),
                'query' => $query,
                'expected_results' => json_encode($expected),
                'actual_results' => json_encode($actual),
                'is_pass' => $is_pass ? 1 : 0,
                'created_at' => current_time('mysql'),
            ];
            $result = $wpdb->insert($table, $data);
            if ($result === false) {
                error_log('WCAC TEST LOGGER: Failed to insert simple test result. WPDB Error: ' . $wpdb->last_error);
                return false;
            }
            return true;
        } catch (Throwable $e) {
            error_log('WCAC TEST LOGGER: Exception in log_test_result_simple: ' . $e->getMessage());
            return false;
        }
    }

    // REMOVED get_test_results and get_total_test_results - These belong in a data access/repository class, not the logger
}
