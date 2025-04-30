<?php
declare(strict_types=1);

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
                settings longtext NULL,
                llm_params longtext NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id)
            ) $charset_collate;";
            
            // Only run in WordPress context
            if (defined('ABSPATH') && function_exists('dbDelta')) {
                // @phpcs:ignore WordPress.WP.AlternativeFunctions.dbDelta_dbDelta, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                // @phpcs:ignore WordPress.WP.AlternativeFunctions.dbDelta_dbDelta
                dbDelta($sql);
                
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
        
        // Get existing columns
        $existing_columns = $wpdb->get_results("SHOW COLUMNS FROM " . self::$table_name, ARRAY_A);
        if (!$existing_columns) {
            error_log('WCAC ERROR: Could not get columns for ' . self::$table_name);
            return;
        }
        
        // Convert to simple array of column names
        $column_names = array_map(function($col) {
            return $col['Field'];
        }, $existing_columns);
        
        // Check for settings column
        if (!in_array('settings', $column_names)) {
            $wpdb->query("ALTER TABLE " . self::$table_name . " ADD COLUMN settings longtext NULL AFTER products_data");
            error_log('WCAC DEBUG: Added missing settings column to ' . self::$table_name);
        }
        
        // Check for llm_params column
        if (!in_array('llm_params', $column_names)) {
            $wpdb->query("ALTER TABLE " . self::$table_name . " ADD COLUMN llm_params longtext NULL AFTER settings");
            error_log('WCAC DEBUG: Added missing llm_params column to ' . self::$table_name);
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
            'settings' => json_encode(['test' => true]),
            'llm_params' => json_encode(['test' => true]),
        ];
        
        $insert_result = $wpdb->insert(
            self::$table_name,
            $test_data,
            [
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            ]
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
     * Log a search query and its results, including settings and LLM params.
     *
     * @param string $query The search query
     * @param array $keywords The extracted keywords
     * @param array $products_data The product scoring data
     * @param array $settings The scoring and plugin settings in effect
     * @param array $llm_params The LLM parameters used for the query
     * @return bool True on success, false on failure
     */
    public static function log_search(string $query, array $keywords, array $products_data, array $settings = [], array $llm_params = []): bool {
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
        
        // Prepare data for insert
        $data = [
            'query' => $query,
            'keywords' => json_encode($keywords),
        ];
        
        $formats = [
            '%s', // query
            '%s', // keywords
        ];
        
        if ($debug_logging_enabled) {
            $data['products_data'] = json_encode($products_data);
            $data['settings'] = json_encode($settings);
            $data['llm_params'] = json_encode($llm_params);
            
            $formats[] = '%s'; // products_data
            $formats[] = '%s'; // settings
            $formats[] = '%s'; // llm_params
        } else {
            $data['products_data'] = json_encode([]);
            
            $formats[] = '%s'; // products_data
            
            // Check if columns exist before setting values
            $columns = $wpdb->get_results("SHOW COLUMNS FROM " . self::$table_name . " LIKE 'settings'");
            if (!empty($columns)) {
                $data['settings'] = null;
                $formats[] = '%s';
            }
            
            $columns = $wpdb->get_results("SHOW COLUMNS FROM " . self::$table_name . " LIKE 'llm_params'");
            if (!empty($columns)) {
                $data['llm_params'] = null;
                $formats[] = '%s';
            }
        }
        
        // Log attempt
        error_log('WCAC DEBUG: Attempting to insert log with query: ' . $query);
        
        // Insert the data
        $result = $wpdb->insert(
            self::$table_name,
            $data,
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