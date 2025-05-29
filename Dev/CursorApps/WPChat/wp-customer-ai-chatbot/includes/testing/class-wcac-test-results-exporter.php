<?php

/**
 * WCAC Test Results Exporter
 * Handles exporting test results as CSV for a given test run.
 */

if (!class_exists('Wcac_Test_Results_Exporter')) {
    class Wcac_Test_Results_Exporter
    {
        /**
         * Output CSV for a given test run ID.
         *
         * @param int $run_id
         * @param wpdb|null $wpdb
         */
        public static function export_csv($run_id, $wpdb = null)
        {
            if (!$wpdb) {
                global $wpdb;
            }
            $table = $wpdb->prefix . 'wcac_test_results';
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE run_id = %d ORDER BY id ASC",
                $run_id
            ), ARRAY_A);

            // Output headers
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="wcac_test_results_run_' . $run_id . '.csv"');
            $out = fopen('php://output', 'w');
            // Write header
            fputcsv($out, [
                'query',
                'expected_results',
                'actual_results',
                'score',
                'is_pass',
                'breakdown',
                'created_at',
            ]);
            foreach ($results as $row) {
                // Optionally pretty-print breakdown JSON
                $breakdown = $row['breakdown'];
                if ($breakdown && is_string($breakdown)) {
                    $breakdown = json_encode(json_decode($breakdown, true), JSON_UNESCAPED_UNICODE);
                }
                fputcsv($out, [
                    $row['query'],
                    $row['expected_results'],
                    $row['actual_results'],
                    $row['score'],
                    $row['is_pass'],
                    $breakdown,
                    $row['created_at'],
                ]);
            }
            fclose($out);
            exit;
        }
    }
}
