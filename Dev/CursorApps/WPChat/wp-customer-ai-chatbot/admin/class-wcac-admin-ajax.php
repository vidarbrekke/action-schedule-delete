<?php

declare(strict_types=1);

/**
 * Handles all AJAX actions for WP Customer AI Chatbot admin.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/admin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
// The following functions are provided by WordPress core and are available in the plugin context:
// add_action, add_filter, current_user_can, wp_verify_nonce, wp_send_json_success, wp_send_json_error, wp_die, get_option, update_option, wp_upload_dir, error_log
// phpcs:enable
// Ensure WordPress core functions are available for AJAX security and permissions
if (!function_exists('add_action')) {
    require_once ABSPATH . 'wp-includes/plugin.php';
}
if (!function_exists('current_user_can')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}
if (!function_exists('wp_verify_nonce')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}

// Ensure WordPress functions are available
if (!function_exists('get_option')) {
    require_once ABSPATH . 'wp-includes/option.php';
}

// Include necessary WCAC classes
require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-csv-parser.php';
require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-run.php';
require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-runner.php';
require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-case.php';
require_once WCAC_PLUGIN_DIR . 'includes/optimizer/class-wcac-settings-optimizer.php';
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-chatbot-rules.php';
require_once WCAC_PLUGIN_DIR . 'includes/indexing/class-wcac-indexer.php';
require_once WCAC_PLUGIN_DIR . 'includes/common/class-wcac-utils.php';

if (!defined('ABSPATH')) {
    exit;
}

// Ensure all required WordPress AJAX functions and constants are available
if (!function_exists('wp_send_json_error')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('wp_send_json_success')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('wp_verify_nonce')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}
if (!function_exists('wp_upload_dir')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('sanitize_file_name')) {
    require_once ABSPATH . 'wp-includes/formatting.php';
}
if (!function_exists('wp_rand')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('get_current_user_id')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}
if (!function_exists('update_option')) {
    require_once ABSPATH . 'wp-includes/option.php';
}
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', dirname(ABSPATH) . '/wp-content');
}

class Wcac_Admin_Ajax
{
    /**
     * Register all AJAX actions for the plugin.
     */
    public static function register(): void
    {
        add_action('wp_ajax_wcac_get_scoring_glossary', [self::class, 'ajax_get_scoring_glossary']);
        add_action('wp_ajax_wcac_delete_test_results', [self::class, 'ajax_delete_test_results']);
        add_action('wp_ajax_wcac_handle_upload_test_csv', [self::class, 'handle_upload_test_csv_ajax']);
        add_action('wp_ajax_wcac_suggest_settings', [self::class, 'ajax_suggest_settings']);
        add_action('wp_ajax_wcac_run_optimizer', [self::class, 'ajax_run_optimizer']);
        add_action('wp_ajax_wcac_apply_synonyms', [self::class, 'ajax_apply_synonyms']);
        add_action('wp_ajax_wcac_apply_settings', [self::class, 'ajax_apply_settings']);
        add_action('wp_ajax_wcac_get_optimizer_nonce', [self::class, 'ajax_get_optimizer_nonce']);
        add_action('wp_ajax_wcac_delete_selected_debug_logs', [self::class, 'ajax_delete_selected_debug_logs']);
        add_action('wp_ajax_wcac_delete_all_debug_logs', [self::class, 'ajax_delete_all_debug_logs']);
        add_action('wp_ajax_wcac_clear_all_test_history', [self::class, 'ajax_clear_all_test_history']);
        add_action('wp_ajax_wcac_clear_debug_log', [self::class, 'ajax_clear_debug_log']);
    }

    // --- Copy all AJAX handler methods from Wcac_Admin_Settings here, unchanged for now ---
    // ... existing code ...

    public static function ajax_delete_test_results()
    {
        error_log('[WCAC_AJAX] Entered ajax_delete_test_results');
        try {
            check_ajax_referer('wcac_delete_test_results');
            // phpcs:ignore WordPress.WP.Capabilities.current_user_can_current_user_can
            if (!current_user_can('manage_options')) {
                error_log('[WCAC_AJAX] AJAX delete: Unauthorized user');
                wp_send_json_error(['message' => 'Unauthorized']);
            }
            $ids = isset($_POST['ids']) ? json_decode(stripslashes($_POST['ids']), true) : [];
            if (!is_array($ids) || empty($ids)) {
                error_log('[WCAC_AJAX] AJAX delete: No IDs provided. Raw: ' . print_r($_POST['ids'] ?? '', true));
                wp_send_json_error(['message' => 'No IDs provided']);
            }
            global $wpdb;
            $table = $wpdb->prefix . 'wcac_test_results';
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $ids_int = array_map('intval', $ids);
            $sql = $wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", ...$ids_int);
            $result = $wpdb->query($sql);
            if ($result === false) {
                error_log('[WCAC_AJAX] AJAX delete: SQL error: ' . $wpdb->last_error . ' | SQL: ' . $sql);
                wp_send_json_error(['message' => 'SQL error: ' . $wpdb->last_error]);
            }
            error_log('[WCAC_AJAX] AJAX delete: Success, deleted ' . $result . ' rows.');
            wp_send_json_success();
        } catch (Exception $e) {
            error_log('[WCAC_AJAX] Exception in ajax_delete_test_results: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()], 500);
        }
    }

    public static function handle_upload_test_csv_ajax()
    {
        error_log('[WCAC_AJAX] TOP OF HANDLER: handle_upload_test_csv_ajax called');
        error_log('[WCAC_AJAX] Entered handle_upload_test_csv_ajax');
        try {
            // Security: Check nonce and permissions
            if (
                !isset(
                    $_POST['wcac_upload_test_csv_nonce']
                ) ||
                !wp_verify_nonce($_POST['wcac_upload_test_csv_nonce'], 'wcac_upload_test_csv')
            ) {
                error_log('[WCAC_AJAX] Invalid or missing nonce');
                wp_send_json_error(['message' => 'Security check failed (invalid nonce).'], 403);
            }
            if (!current_user_can('manage_options')) {
                error_log('[WCAC_AJAX] Unauthorized user');
                wp_send_json_error(['message' => 'Unauthorized'], 403);
            }

            // --- Delete old test results and optimizer logs ---
            global $wpdb;
            $table = $wpdb->prefix . 'wcac_test_results';
            $wpdb->query("TRUNCATE TABLE $table");
            error_log('[WCAC_AJAX] Deleted all old test results from DB.');
            $uploads = wp_upload_dir();
            foreach (glob($uploads['basedir'] . '/wcac_test_upload_*.csv') as $file) {
                @unlink($file);
            }
            foreach (glob($uploads['basedir'] . '/optimizer_log_*.csv') as $file) {
                @unlink($file);
            }
            error_log('[WCAC_AJAX] Deleted old test CSVs and optimizer logs.');

            // File validation
            if (!isset($_FILES['wcac_test_csv']) || $_FILES['wcac_test_csv']['error'] !== UPLOAD_ERR_OK) {
                error_log('[WCAC_AJAX] No file uploaded or upload error: ' . print_r($_FILES['wcac_test_csv'] ?? '', true));
                wp_send_json_error(['message' => 'No file uploaded or upload error.'], 400);
            }
            $file = $_FILES['wcac_test_csv'];
            $tmp_name = $file['tmp_name'];
            $name = sanitize_file_name($file['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                error_log('[WCAC_AJAX] Uploaded file is not a CSV: ' . $name);
                wp_send_json_error(['message' => 'Uploaded file must be a .csv file.'], 400);
            }

            // Move to a temp location (WordPress uploads dir)
            $dest = $uploads['basedir'] . '/wcac_test_upload_' . time() . '_' . wp_rand(1000, 9999) . '.csv';
            if (!move_uploaded_file($tmp_name, $dest)) {
                error_log('[WCAC_AJAX] Failed to move uploaded file to: ' . $dest);
                wp_send_json_error(['message' => 'Failed to move uploaded file.'], 500);
            }

            // Parse CSV
            require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-csv-parser.php';
            $parser = new Wcac_Csv_Parser();
            $test_cases = $parser->parse($dest);
            error_log('[WCAC_AJAX] After CSV parse: test_cases count = ' . (is_array($test_cases) ? count($test_cases) : 'not array'));
            if (empty($test_cases)) {
                error_log('[WCAC_AJAX] test_cases is empty. Raw CSV path: ' . $dest . ', file_exists: ' . (file_exists($dest) ? 'yes' : 'no'));
                $csv_content = @file_get_contents($dest);
                error_log('[WCAC_AJAX] Raw CSV content: ' . substr($csv_content, 0, 1000)); // Log first 1000 chars
            }

            // Run tests
            require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-runner2.php';
            error_log('[WCAC_AJAX] About to instantiate Wcac_Test_Runner2');
            // Convert Wcac_Test_Case objects to arrays for the new runner
            $test_cases_arr = array_map(function($tc) {
                return [
                    'query' => method_exists($tc, 'get_query') ? $tc->get_query() : $tc['query'],
                    'expected_results' => method_exists($tc, 'get_expected_results') ? $tc->get_expected_results() : $tc['expected_results'],
                ];
            }, $test_cases);
            $runner = new Wcac_Test_Runner2($test_cases_arr);
            $results = $runner->run();
            error_log('[WCAC_AJAX] handle_upload_test_csv_ajax completed successfully, results count=' . count($results));
            wp_send_json_success(['message' => 'Tests completed successfully.', 'results' => $results]);
        } catch (Throwable $e) {
            error_log('[WCAC_AJAX] Exception in handle_upload_test_csv_ajax: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()], 500);
        }
    }

    public static function ajax_run_optimizer()
    {
        $start_time = microtime(true);
        error_log('[WCAC_AJAX] === Starting Optimizer ===');
        try {
            error_log('[WCAC_AJAX] Incoming POST: ' . print_r($_POST, true));
            check_ajax_referer('wcac_optimizer_batching', 'nonce');
            if (!current_user_can('manage_options')) {
                error_log('[WCAC_AJAX] Unauthorized user');
                wp_send_json_error(['message' => 'Unauthorized'], 403);
            }

            $batch_size = isset($_POST['batch_size']) ? max(1, intval($_POST['batch_size'])) : 5;
            $current_idx = isset($_POST['current_idx']) ? intval($_POST['current_idx']) : 0;
            $total_iterations = isset($_POST['iterations']) ? intval($_POST['iterations']) : 200; // Renamed for clarity

            error_log('[WCAC_AJAX] Running optimizer batch: current_idx=' . $current_idx . ', batch_size=' . $batch_size . ', total_iterations=' . $total_iterations);

            require_once WCAC_PLUGIN_DIR . 'includes/optimizer/class-wcac-settings-optimizer.php';
            $optimizer = new Wcac_Settings_Optimizer();

            // Session state management
            $session_transient_key = 'wcac_optimizer_session_state';
            $session_state = get_transient($session_transient_key);

            $initial_session_settings = [];
            $session_best_settings = [];
            $session_best_score_details = ['total_matches' => -1, 'pass_count' => -1]; // Initialize with a very low score

            if ($current_idx === 0 || $session_state === false) {
                // Start of a new session or transient expired/missing
                error_log('[WCAC_AJAX] Optimizer: Starting new session or re-initializing state.');
                $initial_session_settings = Wcac_Settings_Optimizer::get_parameters(); // Get current DB settings as baseline
                $session_best_settings = $initial_session_settings;
                // Optionally, score the initial settings here if needed, or assume first random set will be better.
                // For now, best score is initialized to be worse than any possible outcome.
                $session_state = [
                    'initial_settings' => $initial_session_settings,
                    'best_settings' => $session_best_settings,
                    'best_score_details' => $session_best_score_details,
                    'total_iterations' => $total_iterations // Store total iterations for consistency
                ];
                set_transient($session_transient_key, $session_state, DAY_IN_SECONDS); // Store for 1 day
            } else {
                // Continuing an existing session
                error_log('[WCAC_AJAX] Optimizer: Continuing existing session. Loaded state: ' . json_encode($session_state));
                $initial_session_settings = $session_state['initial_settings'];
                $session_best_settings = $session_state['best_settings'];
                $session_best_score_details = $session_state['best_score_details'];
                // Ensure total_iterations from POST matches the session, or handle discrepancy
                if ($session_state['total_iterations'] != $total_iterations) {
                    error_log('[WCAC_AJAX] WARNING: Total iterations mismatch between POST (' . $total_iterations . ') and session (' . $session_state['total_iterations'] . '). Using session value.');
                    $total_iterations = $session_state['total_iterations'];
                }
            }
            
            // Pass the initial_session_settings (fixed baseline) to run_batch
            $result = $optimizer->run_batch($current_idx, $batch_size, $total_iterations, $initial_session_settings);

            if (!$result) {
                error_log('[WCAC_AJAX] Optimizer batch failed');
                wp_send_json_error(['message' => 'Optimizer failed to run batch. Check database connection, test data, and PHP error logs.'], 500);
            }

            error_log('[WCAC_AJAX] Optimizer batch raw result: ' . print_r($result, true));

            // Compare this batch's best score with the session's overall best score
            $batch_best_score = $result['best_score_details_for_batch'] ?? ['total_matches' => -1, 'pass_count' => -1];
            $batch_settings_suggestions = $result['settings_suggestions'] ?? [];

            if ( ($batch_best_score['total_matches'] > $session_best_score_details['total_matches']) ||
                 ($batch_best_score['total_matches'] == $session_best_score_details['total_matches'] && $batch_best_score['pass_count'] > $session_best_score_details['pass_count']) ) 
            {
                error_log('[WCAC_AJAX] Optimizer: New session best found. Batch score: ' . json_encode($batch_best_score) . '. Previous session best: ' . json_encode($session_best_score_details));
                $session_best_settings = $batch_settings_suggestions;
                $session_best_score_details = $batch_best_score;
                $session_state['best_settings'] = $session_best_settings;
                $session_state['best_score_details'] = $session_best_score_details;
            } else {
                 error_log('[WCAC_AJAX] Optimizer: Batch best (' . json_encode($batch_best_score) . ') did not beat session best (' . json_encode($session_best_score_details) . ').');
            }


            $responseData = [
                'progress' => $result['progress'],
                'current_idx' => $result['current_idx'],
                'total' => $total_iterations, // Use consistent total_iterations
                'done' => $result['done'],
                // 'settings_suggestions' will be the overall session best when done
            ];

            if ($result['done']) {
                error_log('[WCAC_AJAX] Optimizer session completed. Final best settings: ' . json_encode($session_best_settings));
                // Save the overall best settings to the database
                $apply_result = Wcac_Settings_Optimizer::set_parameters($session_best_settings);
                if (!$apply_result && !empty(Wcac_Settings_Optimizer::$last_error)) { // Assuming a static error property or similar
                     error_log('[WCAC_AJAX] Error applying final settings: ' . Wcac_Settings_Optimizer::$last_error);
                     // Decide if to send error or just log. For now, log and proceed.
                } else if (!$apply_result) {
                    error_log('[WCAC_AJAX] Failed to apply final best settings to DB, but no specific error message from optimizer.');
                } else {
                    error_log('[WCAC_AJAX] Successfully applied final best settings to DB.');
                }
                $responseData['settings_suggestions'] = $session_best_settings; // Send final best to JS
                delete_transient($session_transient_key); // Clean up session state
                error_log('[WCAC_AJAX] Optimizer session transient deleted.');
            } else {
                // Update session state for the next batch
                set_transient($session_transient_key, $session_state, DAY_IN_SECONDS);
                error_log('[WCAC_AJAX] Optimizer session state updated for next batch.');
                // For intermediate steps, we might not want to send suggestions, or send batch best.
                // Let's send the current overall best settings found so far.
                $responseData['settings_suggestions'] = $session_best_settings; 
            }

            $responseData['new_nonce'] = wp_create_nonce('wcac_optimizer_batching');

            $response = [
                'success' => true,
                'data' => $responseData
            ];

            error_log('[WCAC_AJAX] Sending response: ' . json_encode($response));
            wp_send_json($response);

        } catch (Throwable $e) {
            error_log('[WCAC_AJAX] Optimizer error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    public static function ajax_get_optimizer_nonce()
    {
        try {
            // This function is the source of the initial nonces for the JS.
            // It should provide all necessary nonces for the optimizer workflow.
            error_log('[WCAC_AJAX] ajax_get_optimizer_nonce user ID: ' . get_current_user_id());
            // No specific referer check here as it's just fetching nonces, but user must be able to manage options.
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized to fetch nonces'], 403);
            }
            
            $nonces = [
                'optimizer_batch_nonce' => wp_create_nonce('wcac_optimizer_batching'),
                'optimizer_apply_settings_nonce' => wp_create_nonce('wcac_optimizer_apply_settings'),
                'optimizer_download_log_nonce' => wp_create_nonce('wcac_optimizer_download_log'),
                'optimizer_delete_log_nonce' => wp_create_nonce('wcac_optimizer_delete_log')
            ];
            wp_send_json_success($nonces); // Send all nonces
        } catch (Throwable $e) {
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()], 500);
        }
    }

    public static function ajax_apply_synonyms()
    {
        error_log('[WCAC_AJAX] Entered ajax_apply_synonyms');
        try {
            // Security: Use unified nonce check - this is not part of optimizer, use 'wcac_admin' or a specific one.
            check_ajax_referer('wcac_admin', 'nonce'); 
            if (!current_user_can('manage_options')) {
                error_log('[WCAC_AJAX] Unauthorized user for apply_synonyms');
                wp_send_json_error(['message' => 'Unauthorized'], 403);
            }
            // Log incoming POST data
            error_log('[WCAC_AJAX] $_POST: ' . print_r($_POST, true));
            $synonyms = $_POST['synonyms'] ?? null;
            if (!$synonyms) {
                error_log('[WCAC_AJAX] No synonyms provided');
                wp_send_json_error(['message' => 'No synonyms provided.'], 400);
            }
            // If sent as JSON string, decode
            if (is_string($synonyms)) {
                $synonyms = json_decode($synonyms, true);
            }
            if (!is_array($synonyms)) {
                error_log('[WCAC_AJAX] Synonyms not an array');
                wp_send_json_error(['message' => 'Invalid synonyms format.'], 400);
            }
            // Flatten and merge with existing synonym map
            $option_key = 'wcac_settings';
            $options = get_option($option_key, []);
            $existing = isset($options['wcac_synonym_map']) ? $options['wcac_synonym_map'] : '';
            $existing_lines = array_filter(array_map('trim', wcac_safe_split("\n", $existing)));
            $new_lines = [];
            foreach ($synonyms as $group) {
                if (is_array($group)) {
                    $new_lines[] = implode(',', array_map('trim', $group));
                }
            }
            $all_lines = array_unique(array_merge($existing_lines, $new_lines));
            $options['wcac_synonym_map'] = implode("\n", $all_lines);
            update_option($option_key, $options);
            error_log('[WCAC_AJAX] Synonyms updated. Total groups: ' . count($all_lines));
            wp_send_json_success(['message' => 'Synonyms saved.', 'count' => count($all_lines)]);
        } catch (Throwable $e) {
            error_log('[WCAC_AJAX] Exception in ajax_apply_synonyms: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()], 500);
        }
    }

    public static function ajax_apply_settings()
    {
        error_log('[WCAC_AJAX] Entered ajax_apply_settings');
        try {
            error_log('[WCAC_AJAX] Step 1: Checking nonce and permissions');
            try {
                check_ajax_referer('wcac_optimizer_apply_settings', 'nonce');
            } catch (Throwable $e) {
                error_log('[WCAC_AJAX] Nonce check failed: ' . $e->getMessage());
                wp_send_json_error(['message' => 'Nonce check failed', 'error' => $e->getMessage()], 403);
            }
            if (!current_user_can('manage_options')) {
                error_log('[WCAC_AJAX] Unauthorized user for apply_settings');
                wp_send_json_error(['message' => 'Unauthorized'], 403);
            }

            error_log('[WCAC_AJAX] Step 2: Reading POST data');
            $settings_json = $_POST['settings'] ?? null;
            if (!$settings_json) {
                error_log('[WCAC_AJAX] No settings provided');
                wp_send_json_error(['message' => 'No settings provided.'], 400);
            }

            error_log('[WCAC_AJAX] Step 3: Stripping slashes from JSON');
            $settings_json_stripped = stripslashes($settings_json);
            error_log('[WCAC_AJAX] Stripped JSON: ' . $settings_json_stripped);

            error_log('[WCAC_AJAX] Step 4: Decoding JSON');
            $settings = null;
            try {
                $settings = json_decode($settings_json_stripped, true);
            } catch (Throwable $e) {
                error_log('[WCAC_AJAX] JSON decode error: ' . $e->getMessage());
                wp_send_json_error(['message' => 'JSON decode error', 'error' => $e->getMessage()], 400);
            }
            if (!is_array($settings)) {
                error_log('[WCAC_AJAX] Settings not an array after json_decode');
                error_log('[WCAC_AJAX] Original POST[settings]: ' . $settings_json);
                error_log('[WCAC_AJAX] Stripped string: ' . $settings_json_stripped);
                wp_send_json_error(['message' => 'Invalid settings format received.'], 400);
            }
            error_log('[WCAC_AJAX] Decoded settings: ' . print_r($settings, true));

            error_log('[WCAC_AJAX] Step 5: Validating settings array');
            $allowed_array_keys = ['wcac_boost_terms', 'wcac_devalue_terms'];
            foreach ($settings as $key => $value) {
                // Allow arrays only for specific keys
                if (is_array($value) && !in_array($key, $allowed_array_keys, true)) {
                     error_log('[WCAC_AJAX] Invalid array value for key ' . $key . ': ' . print_r($value, true));
                     wp_send_json_error(['message' => 'Invalid array value for key ' . $key], 400);
                } 
                // For non-array values, ensure they are scalar or null
                else if (!is_array($value) && !is_scalar($value) && !is_null($value)) {
                    error_log('[WCAC_AJAX] Invalid non-scalar/non-null value for key ' . $key . ': ' . print_r($value, true));
                    wp_send_json_error(['message' => 'Invalid value type for key ' . $key], 400);
                }
            }

            error_log('[WCAC_AJAX] Step 6: Applying settings');
            try {
                require_once WCAC_PLUGIN_DIR . 'includes/optimizer/class-wcac-settings-optimizer.php';
                $result = Wcac_Settings_Optimizer::apply_settings($settings);
            } catch (Throwable $e) {
                error_log('[WCAC_AJAX] Exception in apply_settings: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
                wp_send_json_error(['message' => 'Exception in apply_settings', 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
            }
            error_log('[WCAC_AJAX] apply_settings result: ' . var_export($result, true));
            if (!$result) {
                error_log('[WCAC_AJAX] Failed to apply settings');
                wp_send_json_error(['message' => 'Failed to apply settings.'], 500);
            }

            error_log('[WCAC_AJAX] Step 7: Settings applied successfully');
            wp_send_json_success(['message' => 'Settings applied successfully.']);

        } catch (Throwable $e) {
            error_log('[WCAC_AJAX] Outer exception in ajax_apply_settings: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            wp_send_json_error(['message' => 'Outer exception: ' . $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    public static function ajax_delete_selected_debug_logs()
    {
        error_log('[WCAC_AJAX] Entered ajax_delete_selected_debug_logs');
        try {
            check_ajax_referer('wcac_delete_selected_debug_logs');
            if (!function_exists('current_user_can')) {
                require_once ABSPATH . 'wp-includes/pluggable.php';
            }
            if (!current_user_can('manage_options')) {
                error_log('[WCAC_AJAX] Unauthorized user for debug log delete');
                wp_send_json_error(['message' => 'Unauthorized'], 403);
            }
            $ids = isset($_POST['ids']) ? json_decode(stripslashes($_POST['ids']), true) : [];
            if (!is_array($ids) || empty($ids)) {
                error_log('[WCAC_AJAX] No IDs provided for debug log delete. Raw: ' . print_r($_POST['ids'] ?? '', true));
                wp_send_json_error(['message' => 'No IDs provided'], 400);
            }
            global $wpdb;
            $table = $wpdb->prefix . 'wcac_debug_logs';
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $ids_int = array_map('intval', $ids);
            $sql = $wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", ...$ids_int);
            $result = $wpdb->query($sql);
            if ($result === false) {
                error_log('[WCAC_AJAX] SQL error deleting debug logs: ' . $wpdb->last_error . ' | SQL: ' . $sql);
                wp_send_json_error(['message' => 'SQL error: ' . $wpdb->last_error], 500);
            }
            error_log('[WCAC_AJAX] Deleted ' . $result . ' debug log rows.');
            wp_send_json_success(['deleted' => $result]);
        } catch (Throwable $e) {
            error_log('[WCAC_AJAX] Exception in ajax_delete_selected_debug_logs: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()], 500);
        }
    }

    /**
     * AJAX handler to clear all test result history.
     */
    public static function ajax_clear_all_test_history()
    {
        error_log('[WCAC_AJAX] Entered ajax_clear_all_test_history');
        try {
            // Security checks
            check_ajax_referer('wcac_clear_all_test_history_action', 'nonce');
            if (!current_user_can('manage_options')) {
                error_log('[WCAC_AJAX] Clear All History: Unauthorized user');
                wp_send_json_error(['message' => 'Unauthorized']);
            }

            global $wpdb;
            $table_name = $wpdb->prefix . 'wcac_test_results';

            // Truncate the table
            $result = $wpdb->query("TRUNCATE TABLE {$table_name}");

            if ($result === false) {
                error_log('[WCAC_AJAX] Clear All History: TRUNCATE query failed. Error: ' . $wpdb->last_error);
                wp_send_json_error(['message' => 'Database error during truncate: ' . $wpdb->last_error]);
            } else {
                error_log('[WCAC_AJAX] Clear All History: Success, table truncated.');
                wp_send_json_success(['message' => 'All test result history cleared.']);
            }
        } catch (Throwable $e) {
            error_log('[WCAC_AJAX] Exception in ajax_clear_all_test_history: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()], 500);
        }
    }

    /**
     * AJAX handler to delete all debug logs.
     */
    public static function ajax_delete_all_debug_logs()
    {
        error_log('[WCAC_AJAX] Entered ajax_delete_all_debug_logs');
        try {
            check_ajax_referer('wcac_delete_all_debug_logs');
            if (!current_user_can('manage_options')) {
                error_log('[WCAC_AJAX] Unauthorized user for debug log delete all');
                wp_send_json_error(['message' => 'Unauthorized'], 403);
            }

            $result = Wcac_Debug_Logger::delete_all_logs();
            if ($result === false) {
                error_log('[WCAC_AJAX] Failed to delete all debug logs');
                wp_send_json_error(['message' => 'Failed to delete all debug logs'], 500);
            }

            error_log('[WCAC_AJAX] Successfully deleted all debug logs');
            wp_send_json_success(['message' => 'All debug logs deleted successfully']);
        } catch (Throwable $e) {
            error_log('[WCAC_AJAX] Exception in ajax_delete_all_debug_logs: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Exception: ' . $e->getMessage()], 500);
        }
    }

    /**
     * AJAX handler to clear the standard debug.log file.
     */
    public static function ajax_clear_debug_log()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }
        $debug_log = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log)) {
            $result = file_put_contents($debug_log, '');
            if ($result === false) {
                wp_send_json_error(['message' => 'Failed to clear debug.log'], 500);
            }
            wp_send_json_success(['message' => 'debug.log cleared successfully.']);
        } else {
            wp_send_json_success(['message' => 'debug.log does not exist.']);
        }
    }
}
