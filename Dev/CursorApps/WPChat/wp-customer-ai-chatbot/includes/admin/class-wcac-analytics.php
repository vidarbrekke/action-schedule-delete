<?php
// Ensure set_transient is available
if (!function_exists('set_transient')) {
    require_once ABSPATH . 'wp-includes/option.php';
}

class Wcac_Analytics {
    /**
     * Get summary statistics for the index.
     * @param string|null $since (e.g., '-7 days') for recent stats
     * @param string|null $type  (e.g., 'product') for type-specific stats
     * Returns array: total, by type, error rates, recent activity, etc.
     */
    public static function get_summary_stats($since = null, $type = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'wcac_index';
        $where = [];
        $params = [];
        if ($type) {
            $where[] = 'post_type = %s';
            $params[] = $type;
        }
        if ($since) {
            $where[] = 'last_updated > %s';
            $params[] = date('Y-m-d H:i:s', strtotime($since));
        }
        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // --- Profiling wrappers ---
        $profile_query = function($label, $fn) {
            $start = microtime(true);
            $result = $fn();
            $elapsed = microtime(true) - $start;
            set_transient("wcac_query_profile_{$label}", $elapsed, 600); // 10 min
            return $result;
        };

        $stats = [
            'total' => $profile_query('total', function() use ($wpdb, $table, $where_sql, $params) {
                return (int) $wpdb->get_var($params ? $wpdb->prepare("SELECT COUNT(*) FROM $table $where_sql", ...$params) : "SELECT COUNT(*) FROM $table $where_sql");
            }),
            'by_type' => [],
            'error_count' => $profile_query('error_count', function() use ($wpdb, $table, $where_sql, $params) {
                return (int) $wpdb->get_var($params ? $wpdb->prepare("SELECT COUNT(*) FROM $table $where_sql AND llm_summary_status = 'error'", ...$params) : "SELECT COUNT(*) FROM $table $where_sql AND llm_summary_status = 'error'");
            }),
            'success_count' => $profile_query('success_count', function() use ($wpdb, $table, $where_sql, $params) {
                return (int) $wpdb->get_var($params ? $wpdb->prepare("SELECT COUNT(*) FROM $table $where_sql AND llm_summary_status = 'success'", ...$params) : "SELECT COUNT(*) FROM $table $where_sql AND llm_summary_status = 'success'");
            }),
            'recent_indexed' => $since ? null : (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE last_updated > %s",
                date('Y-m-d H:i:s', strtotime('-7 days'))
            )),
        ];
        $types = $wpdb->get_results("SELECT post_type, COUNT(*) as count FROM $table GROUP BY post_type", ARRAY_A);
        foreach ($types as $row) {
            $stats['by_type'][$row['post_type']] = (int) $row['count'];
        }
        return $stats;
    }
} 