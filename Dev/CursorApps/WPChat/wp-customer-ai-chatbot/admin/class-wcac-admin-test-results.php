<?php

declare(strict_types=1);

/**
 * Renders the Test Results admin page for WP Customer AI Chatbot.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/admin
 */

use WPMailSMTP\Vendor\Psr\Log\LoggerInterface;

require_once dirname(__DIR__) . '/includes/testing/class-wcac-test-results-exporter.php';
require_once WCAC_PLUGIN_DIR . 'includes/common/context-utils.php';

if (!function_exists('wp_die')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('wp_nonce_field')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('check_admin_referer')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}

class Wcac_Admin_Test_Results
{
    /**
     * Render the Test Results page.
     */
    public static function render(): void
    {
        error_log('[WCAC_TEST_RESULTS_PAGE] Attempting to render Test Results page - TOP OF RENDER METHOD.');

        // Add body class for optimizer page
        add_filter('admin_body_class', function($classes) {
            return $classes . ' wcac-optimizer-page';
        });

        // phpcs:ignore WordPress.WP.Capabilities.current_user_can_current_user_can
        if (!current_user_can('manage_options')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.DiscouragedFunctions.wp_die_wp_die
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-customer-ai-chatbot'));
        }
        // Ensure required classes are loaded
        require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-logger.php';
        require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-result-formatter.php';
        require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-run.php';
        require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-case.php';

        // Fetch results using $wpdb
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_test_results';

        // --- NEW LOGIC: Fetch only the latest test run ---
        $latest_run_id = $wpdb->get_var("SELECT run_id FROM {$table_name} ORDER BY created_at DESC LIMIT 1");
        $test_results = []; // Initialize
        $run_id_display = 'N/A'; // For display

        if ($latest_run_id) {
            $run_id_display = esc_html($latest_run_id);
            // Fetch all results for the latest run_id
            $results_query = $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE run_id = %s ORDER BY id ASC", // Order by ID or query order?
                $latest_run_id
            );
            $test_results = $wpdb->get_results($results_query, ARRAY_A);
        } else {
            // No runs found
            $test_results = [];
        }
        // --- END NEW LOGIC ---

        echo '<div class="notice notice-info">';
        echo '<p><strong>Export Results:</strong> Download a CSV of all test queries, expected and actual results, pass/fail status, and scoring breakdowns for the selected test run. Use this for regression tracking, sharing with collaborators, or further analysis in Excel or other tools.</p>';
        echo '<ul>
          <li><strong>query</strong>: The user query tested</li>
          <li><strong>expected_results</strong>: The expected product/content IDs (from your CSV)</li>
          <li><strong>actual_results</strong>: The IDs returned by the chatbot</li>
          <li><strong>score</strong>: Numeric match score</li>
          <li><strong>is_pass</strong>: 1 if the actual results matched the expected, 0 otherwise</li>
          <li><strong>breakdown</strong>: JSON with detailed scoring for each result</li>
          <li><strong>created_at</strong>: Timestamp of the test run</li>
        </ul>';
        echo '</div>';

        // Add export button form (place near the top, after help section)
        if ($latest_run_id) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="wcac_export_test_results">';
            echo '<input type="hidden" name="wcac_test_run_id" value="' . esc_attr($latest_run_id) . '">';
            wp_nonce_field('wcac_export_test_results', 'wcac_export_nonce');
            echo '<input type="submit" class="button button-primary" value="Export Results (CSV)">';
            echo '</form>';
        }

        ?>
        <div class="wrap wcac-test-results-page">
            <h1><?php echo esc_html__('Test Results', 'wp-customer-ai-chatbot'); ?></h1>
            <p style="font-size:14px; color:#666; max-width:700px;">This page shows the results from the <strong>latest</strong> test run. Uploading a new CSV file will delete previous results and run new tests. <a href="admin.php?page=wcac-debug-logs">Switch to Debug Logs</a></p>
             <?php if ($latest_run_id) : ?>
                 <p style="font-style: italic;">Showing results for Run ID: <strong><?php echo $run_id_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong></p>
             <?php endif; ?>

            <!-- CSV Upload Form -->
            <form id="wcac-csv-upload-form" enctype="multipart/form-data" method="post" style="display:inline-block; margin-right:20px;">
                <input type="file" name="wcac_test_csv" id="wcac_test_csv" accept=".csv" required />
                <button type="submit" class="button button-secondary">Upload & Run Tests</button> <?php // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.EscapeOutput.OutputNotEscaped
                wp_nonce_field('wcac_upload_test_csv', 'wcac_upload_test_csv_nonce'); ?>
            </form>
            <span id="wcac-csv-upload-status" style="margin-left:10px;"></span>
            <div style="margin-top:4px; font-size:12px; color:#666;">CSV must have a <strong>query</strong> column and <strong>expected_results</strong> (comma-separated IDs). Other columns (e.g., <strong>comment</strong>) allowed.</div>

            <?php if (!empty($test_results)) : ?>
                <div style="margin: 18px 0 12px 0;">
                    <button id="wcac-optimize" class="button button-primary" type="button">Optimize</button>
                    <?php wp_nonce_field('wcac_optimizer', 'wcac_optimizer_nonce'); ?>
                    <button id="wcac-clear-debug-log" class="button" type="button" style="margin-left:12px;">Clear debug.log</button>
                    <div id="wcac-clear-debug-log-result" style="display:inline-block; margin-left:10px; color:#333; font-size:13px;"></div>
                </div>
                <div id="wcac-optimizer-modal" style="display:none;"></div>
            <?php endif; ?>

            <!-- Test Results Table -->
            <form id="wcac-test-results-form" method="post">
                 <button type="button" id="wcac-delete-selected" class="button button-danger" style="margin-top:15px; margin-bottom:10px;">Delete Selected</button>
                 <button type="button" id="wcac-clear-all-history" class="button" style="margin-top:15px; margin-bottom:10px; margin-left: 10px;">Clear All History</button>
                 <?php wp_nonce_field('wcac_clear_all_test_history_action', 'wcac_clear_all_test_history_nonce'); ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="wcac-select-all"></th>
                            <th><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo esc_html__('Query', 'wp-customer-ai-chatbot'); ?></th>
                            <th><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo esc_html__('Expected', 'wp-customer-ai-chatbot'); ?></th>
                            <th><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo esc_html__('Actual Results', 'wp-customer-ai-chatbot'); ?></th>
                            <th><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo esc_html__('Pass/Fail', 'wp-customer-ai-chatbot'); ?></th>
                            <th><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo esc_html__('Scoring Breakdown', 'wp-customer-ai-chatbot'); ?></th>
                            <th><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            echo esc_html__('Timestamp', 'wp-customer-ai-chatbot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($test_results)) :
                            $column_count = 7; ?>
                            <tr><td colspan="<?php echo $column_count; ?>"><em><?php echo esc_html__('No test results yet. Upload a CSV to get started.', 'wp-customer-ai-chatbot'); ?></em></td></tr>
                        <?php else :
                            foreach ($test_results as $result) :
                                $expected_data = json_decode($result['expected_results'] ?? '', true);
                                $actual_data   = json_decode($result['actual_results'] ?? '', true);
                                ?>
                                <tr data-id="<?php echo esc_attr($result['id']); ?>">
                                    <td><input type="checkbox" class="wcac-select-row" value="<?php echo esc_attr($result['id']); ?>"></td>
                                    <td><?php echo esc_html($result['query'] ?? ''); ?></td>
                                    <td><?php echo Wcac_Test_Result_Formatter::format_expected_results($expected_data); ?></td>
                                    <td><?php echo Wcac_Test_Result_Formatter::format_actual_results($actual_data, $expected_data); ?></td>
                                    <td><?php echo Wcac_Test_Result_Formatter::format_pass_fail($actual_data, $expected_data); ?></td>
                                    <td><?php echo Wcac_Test_Result_Formatter::format_scoring_breakdown($actual_data); ?></td>
                                    <td><?php echo esc_html($result['created_at'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- JavaScript for Upload and Delete -->
        <!-- Removed native JS handler for #wcac-csv-upload-form to prevent double submission. All upload logic is now handled by admin/js/wcac-admin.js. -->
        <?php
    }
}

// Only register the export handler if in a WordPress context
if (function_exists('add_action')) {
    add_action('admin_post_wcac_export_test_results', function () {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('wcac_export_test_results', 'wcac_export_nonce');
        $run_id = isset($_POST['wcac_test_run_id']) ? intval($_POST['wcac_test_run_id']) : 0;
        if (!$run_id) {
            wp_die('No test run selected.');
        }
        if (!class_exists('Wcac_Test_Results_Exporter')) {
            require_once dirname(__DIR__) . '/includes/testing/class-wcac-test-results-exporter.php';
        }
        Wcac_Test_Results_Exporter::export_csv($run_id);
    });
}
