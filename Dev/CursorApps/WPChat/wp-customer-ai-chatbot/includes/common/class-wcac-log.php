<?php
// Linter stubs for WordPress functions if not defined
if (!function_exists('dbDelta')) {
    function dbDelta($sql) { /* no-op for linter */ }
}
if (!function_exists('current_time')) {
    function current_time($type) { return date('Y-m-d H:i:s'); }
}
/**
 * Wcac_Log: Generic plugin log table handler (DB-based, multi-type)
 */
class Wcac_Log {
    const TABLE = 'wcac_log';

    /** Non-error log levels (configurable for future-proofing) */
    const NON_ERROR_LEVELS = ['info', 'success'];

    /** Default log retention per type/level */
    const DEFAULT_RETENTION = 1000;

    /**
     * Ensure the log table exists (id, timestamp, type, level, context, message, extra)
     */
    public static function ensure_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            type VARCHAR(32) NOT NULL,
            level VARCHAR(16) NOT NULL DEFAULT 'info',
            context VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            extra TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY type_idx (type),
            KEY ts_idx (timestamp),
            KEY level_idx (level)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Write a log entry
     */
    public static function write($type, $level, $message, $context = null, $extra = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE;
        $wpdb->insert($table_name, [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'level' => $level,
            'context' => $context,
            'message' => $message,
            'extra' => $extra ? wp_json_encode($extra) : null,
        ]);
    }

    /**
     * Read log entries (optionally filter by type, level, limit)
     */
    public static function read($type = null, $level = null, $limit = 100) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE;
        $where = [];
        $params = [];
        if ($type) {
            $where[] = 'type = %s';
            $params[] = $type;
        }
        if ($level) {
            $where[] = 'level = %s';
            $params[] = $level;
        }
        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = "SELECT * FROM $table_name $where_sql ORDER BY timestamp DESC, id DESC LIMIT %d";
        $params[] = $limit;
        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
    }

    /**
     * Clear all log entries for a given type (or all if null)
     */
    public static function clear($type = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE;
        if ($type) {
            $wpdb->delete($table_name, [ 'type' => $type ], [ '%s' ]);
        } else {
            $wpdb->query("TRUNCATE TABLE $table_name");
        }
    }

    /**
     * Prune log entries for a given type, keeping only the most recent $max_entries.
     * If $type is null, prunes all types.
     */
    public static function prune($type = null, $max_entries = 1000) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE;
        if ($type) {
            // Delete all but the most recent $max_entries for this type
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE type = %s AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM $table_name WHERE type = %s ORDER BY timestamp DESC, id DESC LIMIT %d
                    ) as keep_ids
                )",
                $type, $type, $max_entries
            ));
        } else {
            // For all types, prune each type individually
            $types = $wpdb->get_col("SELECT DISTINCT type FROM $table_name");
            foreach ($types as $t) {
                self::prune($t, $max_entries);
            }
        }
    }

    /**
     * Prune non-error log entries for a given type, keeping only the most recent $max_entries.
     * Keeps all 'error' and 'warning' entries.
     */
    public static function prune_non_errors($type = null, $max_entries = 1000) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE;
        foreach (self::NON_ERROR_LEVELS as $level) {
            if ($type) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE type = %s AND level = %s AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM $table_name WHERE type = %s AND level = %s ORDER BY timestamp DESC, id DESC LIMIT %d
                        ) as keep_ids
                    )",
                    $type, $level, $type, $level, $max_entries
                ));
            } else {
                $types = $wpdb->get_col("SELECT DISTINCT type FROM $table_name");
                foreach ($types as $t) {
                    $wpdb->query($wpdb->prepare(
                        "DELETE FROM $table_name WHERE type = %s AND level = %s AND id NOT IN (
                            SELECT id FROM (
                                SELECT id FROM $table_name WHERE type = %s AND level = %s ORDER BY timestamp DESC, id DESC LIMIT %d
                            ) as keep_ids
                        )",
                        $t, $level, $t, $level, $max_entries
                    ));
                }
            }
        }
    }

    /**
     * Action Scheduler callback for daily log pruning.
     */
    public static function scheduled_prune() {
        self::prune(null, self::DEFAULT_RETENTION);
    }

    /**
     * Fallback prune: Prune if last prune >24h ago, update timestamp, return true if pruned.
     */
    public static function maybe_fallback_prune() {
        $last_prune = (int) get_option('wcac_log_last_prune', 0);
        if (time() - $last_prune > DAY_IN_SECONDS) {
            self::scheduled_prune();
            update_option('wcac_log_last_prune', time());
            return true;
        }
        return false;
    }
} 