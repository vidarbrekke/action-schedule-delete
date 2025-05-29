<?php
declare(strict_types=1);
// Ensure add_option is available before any use
if (!function_exists('add_option')) {
    require_once ABSPATH . 'wp-includes/option.php';
}
// Centralized WordPress function guards
if (!function_exists('get_option')) {
    require_once ABSPATH . 'wp-includes/option.php';
}
if (!function_exists('is_admin')) {
    require_once ABSPATH . 'wp-includes/load.php';
}
if (!defined('WP_CLI')) {
    define('WP_CLI', false);
}
if (!function_exists('dbDelta')) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes
 */
class Wcac_Activator
{
    /**
     * Schedule daily log pruning with Action Scheduler.
     */
    private static function schedule_log_pruning(): void
    {
        // Remove all stubs for as_next_scheduled_action and as_schedule_recurring_action
    }

    /**
     * Generic helper for table creation.
     */
    private static function create_table($table_name, $schema) {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name ($schema) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        if (function_exists('dbDelta')) {
            dbDelta($sql);
        }
        error_log("WCAC Activator: Attempted to create/update custom table: {$table_name}");
    }

    /**
     * Activation logic.
     *
     * @since    0.1.0 (Modified 0.1.4 to add table creation)
     */
    public static function activate(): void
    {
        // error_log("WCAC Activator DEBUG: Entering activate() method."); // Remove this log
        // Set default options if they don't exist yet.
        if (false === get_option('wcac_settings')) {
            $default_settings = [
                'wcac_api_key' => '',
                'wcac_index_products' => true, // Default to indexing products
                'wcac_index_pages' => false,
                'wcac_index_posts' => false,
                'wcac_negative_keywords' => '',
                'wcac_system_prompt' => '', // Let default be handled in Wcac_Public
                'wcac_site_prompt' => '',
                'wcac_custom_css' => '',
                // LLM API Parameters
                'wcac_temperature' => '0.7',    // Default temperature
                'wcac_top_p' => '0.9',         // Default top_p
                'wcac_max_tokens' => '800',    // Default max tokens
                'wcac_frequency_penalty' => '0', // Default frequency penalty
                'wcac_presence_penalty' => '0',  // Default presence penalty
                'wcac_model' => 'nousresearch/nous-hermes-2-mixtral-8x7b-dpo', // Default model
            ];
            if (function_exists('add_option')) {
                add_option('wcac_settings', $default_settings);
            }
        }

        global $wpdb;
        $tables = [
            $wpdb->prefix . 'wcac_index' => "
                post_id BIGINT UNSIGNED NOT NULL,
                post_type VARCHAR(20) NOT NULL,
                post_modified DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
                title TEXT NULL,
                content_snippet LONGTEXT NULL,
                url VARCHAR(2083) NULL,
                categories TEXT NULL,
                parent_categories TEXT NULL,
                tags TEXT NULL,
                recommended_for TEXT NULL,
                attributes_text TEXT NULL,
                regular_price VARCHAR(32) NULL,
                sale_price VARCHAR(32) NULL,
                on_sale TINYINT(1) DEFAULT 0,
                parent_id BIGINT UNSIGNED NULL,
                stock_status VARCHAR(20) NULL,
                search_blob LONGTEXT NULL,
                menu_titles TEXT NULL,
                taxonomies TEXT NULL,
                average_rating FLOAT NULL,
                comment_count INT NULL,
                llm_summary_status VARCHAR(20) NULL,
                llm_summary_error TEXT NULL,
                llm_retry_count INT NULL,
                llm_next_attempt DATETIME NULL,
                PRIMARY KEY  (post_id),
                KEY post_type (post_type),
                KEY llm_summary_status (llm_summary_status),
                KEY parent_id (parent_id)
            ",
            $wpdb->prefix . 'wcac_test_results' => "
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                run_id VARCHAR(64) NOT NULL,
                query TEXT NOT NULL,
                expected_results TEXT NOT NULL,
                actual_results LONGTEXT NULL,
                score FLOAT DEFAULT 0,
                pass_fail VARCHAR(8) DEFAULT 'fail',
                comment TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY run_id (run_id),
                KEY created_at (created_at)
            ",
            $wpdb->prefix . 'wcac_debug_logs' => "
                id bigint(20) NOT NULL AUTO_INCREMENT,
                query text NOT NULL,
                keywords text NOT NULL,
                products_data longtext NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                KEY created_at (created_at)
            ",
            $wpdb->prefix . 'wcac_feedback' => "
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                chat_id VARCHAR(64) NULL,
                message_id VARCHAR(64) NULL,
                feedback ENUM('up','down') NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY chat_id (chat_id),
                KEY message_id (message_id)
            ",
        ];
        foreach ($tables as $name => $schema) {
            self::create_table($name, $schema);
        }

        // Placeholder for other activation tasks (e.g., flushing rewrite rules if CPTs were added)

        // Logger setup
        require_once WCAC_PLUGIN_DIR . 'includes/common/class-wcac-debug-logger.php';
        if (class_exists('Wcac_Debug_Logger')) {
            Wcac_Debug_Logger::init();
            Wcac_Debug_Logger::create_table();
        }

        // Ensure indexes
        require_once WCAC_PLUGIN_DIR . 'includes/indexing/class-wcac-indexer.php';
        if (class_exists('Wcac_Indexer')) {
            Wcac_Indexer::ensure_indexes();
        }

        // Schedule daily log pruning with Action Scheduler
        self::schedule_log_pruning();
    }
}
