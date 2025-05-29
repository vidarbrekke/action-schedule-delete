<?php

declare(strict_types=1);

/**
 * WCAC Relationship Cache: Universal, lean relationship table for RAG retrieval.
 * Stores entity-to-entity relationships for fast, scalable cross-entity search.
 */
class Wcac_Relationship_Cache
{
    const TABLE = 'wp_wcac_relationships';

    /**
     * Create the relationship table (call on plugin activation).
     */
    public static function create_table()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wcac_relationships';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_id BIGINT UNSIGNED NOT NULL,
            from_type VARCHAR(32) NOT NULL,
            to_id BIGINT UNSIGNED NOT NULL,
            to_type VARCHAR(32) NOT NULL,
            relationship VARCHAR(32) NOT NULL,
            KEY from_idx (from_id, from_type),
            KEY to_idx (to_id, to_type),
            KEY rel_idx (relationship)
        ) $charset_collate;";
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta($sql);
    }

    /**
     * Add or update a relationship (idempotent for same from/to/type/rel).
     */
    public static function upsert(int $from_id, string $from_type, int $to_id, string $to_type, string $relationship): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wcac_relationships';
        $wpdb->replace($table, [
            'from_id' => $from_id,
            'from_type' => $from_type,
            'to_id' => $to_id,
            'to_type' => $to_type,
            'relationship' => $relationship
        ]);
    }

    /**
     * Fetch related entities by from_id/type/relationship.
     */
    public static function get_related(int $from_id, string $from_type, ?string $relationship = null): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wcac_relationships';
        $sql = "SELECT * FROM $table WHERE from_id = %d AND from_type = %s";
        $args = [$from_id, $from_type];
        if ($relationship) {
            $sql .= " AND relationship = %s";
            $args[] = $relationship;
        }
        return $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
    }

    /**
     * Fetch all entities that point to a given to_id/type/relationship.
     */
    public static function get_reverse_related(int $to_id, string $to_type, ?string $relationship = null): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wcac_relationships';
        $sql = "SELECT * FROM $table WHERE to_id = %d AND to_type = %s";
        $args = [$to_id, $to_type];
        if ($relationship) {
            $sql .= " AND relationship = %s";
            $args[] = $relationship;
        }
        return $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
    }
}
