<?php
declare(strict_types=1);

// Stub ABSPATH for static analysis
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '' );
}

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Stub WP_DEBUG constant if not defined (for static analysis)
if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', false );
}

// phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound, WordPress.WP.AlternativeFunctions.dbDelta_dbDelta
// This file is only intended to be run within the WordPress environment, where ABSPATH and dbDelta are defined.

/**
 * Debug logger class for WP Customer AI Chatbot.
 */
class Wcac_Debug_Logger {
    private static $initialized = false;
    private static $table_name;

    /**
     * Initialize the debug logger.
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        global $wpdb;
        self::$table_name = $wpdb->prefix . 'wcac_debug_logs';
        self::$initialized = true;
        
        // Create table if it doesn't exist
        self::create_table();
    }

    /**
     * Create the debug logs table.
     */
    public static function create_table(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . self::$table_name . "'");
        
        // If table doesn't exist, create it
        if (!$table_exists) {
            $sql = "CREATE TABLE " . self::$table_name . " (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                query text NOT NULL,
                keywords text NOT NULL,
                products_data longtext NOT NULL,
                raw_candidates_data longtext NULL,
                final_results_data longtext NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            // Only run in WordPress context
            if (defined('ABSPATH')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                if (function_exists('dbDelta')) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.dbDelta_dbDelta
                    dbDelta($sql);
                } else {
                    error_log('WCAC ERROR: dbDelta function not available after including upgrade.php');
                }
                // Verify table was created
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . self::$table_name . "'");
                if (!$table_exists) {
                    error_log('WCAC ERROR: Failed to create debug logs table using dbDelta');
                    // Fallback to direct SQL
                    $result = $wpdb->query($sql);
                    if ($result === false) {
                        error_log('WCAC ERROR: Failed to create debug logs table using direct SQL: ' . $wpdb->last_error);
                    }
                }
            } else {
                error_log('WCAC ERROR: WordPress functions not available for table creation');
            }
        } else {
            // Table exists, check if it has all required columns
            self::verify_table_columns();
        }
    }
    
    /**
     * Verify table columns and add any missing columns.
     */
    private static function verify_table_columns(): void {
        global $wpdb;
        $table_name = self::$table_name;
        
        // Get existing columns
        $existing_columns_data = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}", ARRAY_A);
        if (!$existing_columns_data) {
            error_log('WCAC ERROR: Could not get columns for ' . $table_name);
            return;
        }
        
        // Convert to simple array of column names
        $column_names = array_map(fn($col) => $col['Field'], $existing_columns_data);
        
        // Columns to ensure exist
        $required_columns = [
            'raw_candidates_data' => 'longtext NULL', 
            'final_results_data' => 'longtext NULL'
        ];

        // Add missing required columns
        foreach ($required_columns as $col_name => $col_definition) {
            if (!in_array($col_name, $column_names)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name, $col_name, $col_definition constructed safely
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN {$col_name} {$col_definition}");
                error_log("WCAC DEBUG: Added missing column '{$col_name}' to {$table_name}.");
            }
        }
    }

    /**
     * Test the debug log table and functionality.
     * 
     * @return array Test results with details of success/failure
     */
    public static function test_logging(): array {
        if (!self::$initialized) {
            self::init();
        }
        
        global $wpdb;
        $results = [
            'success' => false,
            'error' => '',
            'details' => []
        ];
        
        // 1. Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . self::$table_name . "'");
        $results['details']['table_exists'] = (bool)$table_exists;
        
        if (!$table_exists) {
            // Try to create the table
            self::create_table();
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . self::$table_name . "'");
            $results['details']['table_created'] = (bool)$table_exists;
            
            if (!$table_exists) {
                $results['error'] = 'Failed to create debug logs table';
                return $results;
            }
        }
        
        // 2. Verify columns
        self::verify_table_columns();
        
        // 3. Check permissions by attempting a write
        $test_data = [
            'query' => 'TEST QUERY',
            'keywords' => json_encode(['test']),
            'products_data' => json_encode([]),
            'raw_candidates_data' => json_encode([['id'=>1, 'title'=>'Raw Candidate']]),
            'final_results_data' => json_encode([['id'=>1, 'title'=>'Final Result', 'score'=>100]]),
        ];
        
        $formats = [
            '%s', // query
            '%s', // keywords
            '%s', // products_data
            '%s', // raw_candidates_data
            '%s'  // final_results_data
        ];
        
        $insert_result = $wpdb->insert(
            self::$table_name,
            $test_data,
            $formats
        );
        
        $results['details']['insert_success'] = (bool)$insert_result;
        if ($insert_result === false) {
            $results['error'] = 'Failed to insert test data: ' . $wpdb->last_error;
            return $results;
        }
        
        // 4. Check if the data was written successfully
        $test_id = $wpdb->insert_id;
        $retrieved = $wpdb->get_row("SELECT * FROM " . self::$table_name . " WHERE id = " . $test_id, ARRAY_A);
        $results['details']['retrieve_success'] = (bool)$retrieved;
        
        if (!$retrieved) {
            $results['error'] = 'Failed to retrieve test data after insert';
            return $results;
        }
        
        // Success!
        $results['success'] = true;
        
        // Clean up the test entry
        $wpdb->delete(self::$table_name, ['id' => $test_id]);
        
        return $results;
    }

    /**
     * Check if debug logging is enabled in plugin settings.
     * Centralized here for all logger methods.
     */
    private static function is_debug_enabled(): bool {
        $options = get_option('wcac_settings', []);
        return !empty($options['wcac_enable_debug_logging']) || (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * Log a search query and its results.
     *
     * @param string $query The search query
     * @param array $keywords The extracted keywords
     * @param array $products_data The product scoring data (includes score breakdown)
     * @param array $raw_candidates Optional: Raw candidates list before scoring
     * @param array $final_results Optional: Final filtered list after scoring
     * @return bool True on success, false on failure
     */
    public static function log_search(string $query, array $keywords, array $products_data, array $raw_candidates = [], array $final_results = []): bool {
        // --- Centralized debug toggle: skip logging if disabled ---
        if (!self::is_debug_enabled()) {
            return false;
        }
        if (!self::$initialized) {
            self::init();
        }

        global $wpdb;
        
        // Verify table exists before attempting to write
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . self::$table_name . "'");
        if (!$table_exists) {
            error_log('WCAC ERROR: Debug logs table does not exist. Attempting to create it.');
            self::create_table();
            
            // Check again to ensure it was created
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '" . self::$table_name . "'");
            if (!$table_exists) {
                error_log('WCAC ERROR: Failed to create debug logs table after attempted creation.');
                return false;
            }
        }
        
        // Always verify columns before inserting
        self::verify_table_columns();
        
        $options = get_option('wcac_settings', []);
        // Safe check for WP_DEBUG - only used if defined
        // @phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
        $debug_logging_enabled = !empty($options['wcac_enable_debug_logging']) || (defined('WP_DEBUG') && WP_DEBUG);
        
        $data_to_insert = [
            'query' => $query,
            'keywords' => wp_json_encode($keywords ?: []),
            'products_data' => wp_json_encode($products_data ?: []),
            'raw_candidates_data' => wp_json_encode($raw_candidates ?: []),
            'final_results_data' => wp_json_encode($final_results ?: [])
        ];

        $formats = [
            '%s', // query
            '%s', // keywords
            '%s', // products_data
            '%s', // raw_candidates_data
            '%s'  // final_results_data
        ];
        
        // Log attempt
        error_log('WCAC DEBUG: Attempting to insert log with query: ' . $query);
        
        // Insert the data
        $result = $wpdb->insert(
            self::$table_name,
            $data_to_insert,
            $formats
        );
        
        if ($result === false) {
            error_log('WCAC ERROR: Failed to insert debug log. WPDB error: ' . $wpdb->last_error . ' | Data: ' . json_encode([
                'query' => $query,
                'keywords' => $keywords,
                'debug_logging_enabled' => $debug_logging_enabled
            ]));
            return false;
        }
        
        error_log('WCAC DEBUG: Successfully inserted log entry with ID: ' . $wpdb->insert_id);
        return true;
    }

    /**
     * Get all debug logs.
     *
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array Array of debug logs
     */
    public static function get_logs(int $page = 1, int $per_page = 10): array {
        if (!self::$initialized) {
            self::init();
        }

        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::$table_name . " ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        return $logs ?: [];
    }

    /**
     * Get total number of logs.
     *
     * @return int Total number of logs
     */
    public static function get_total_logs(): int {
        if (!self::$initialized) {
            self::init();
        }

        global $wpdb;
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::$table_name);
    }

    /**
     * Delete specific logs.
     *
     * @param array $ids Array of log IDs to delete
     * @return int|false Number of rows deleted, or false on error
     */
    public static function delete_logs(array $ids) {
        if (!self::$initialized || empty($ids)) {
            return false;
        }

        global $wpdb;
        
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM " . self::$table_name . " WHERE id IN ($placeholders)",
                $ids
            )
        );
    }

    /**
     * Delete all logs from the debug logs table.
     *
     * @return int|false Number of rows deleted, or false on error
     */
    public static function delete_all_logs() {
        if (!self::$initialized) {
            self::init();
        }
        global $wpdb;
        // Use TRUNCATE for efficiency
        $result = $wpdb->query("TRUNCATE TABLE " . self::$table_name);
        return $result;
    }

    // Always ensure table schema is up to date on init
    public static function ensure_table_schema(): void {
        self::create_table();
    }
}

// Suppress linter warnings for WordPress constants/functions used only in WP context
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
} 