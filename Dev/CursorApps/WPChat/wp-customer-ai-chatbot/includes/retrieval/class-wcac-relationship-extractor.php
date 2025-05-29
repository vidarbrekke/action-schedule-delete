<?php

declare(strict_types=1);

// Linter false positive: WordPress core functions (get_post, get_post_meta, wp_get_post_parent_id) are available at runtime. These require_once statements ensure compatibility for plugin development.
require_once ABSPATH . 'wp-includes/post.php'; // get_post, wp_get_post_parent_id
require_once ABSPATH . 'wp-includes/meta.php'; // get_post_meta
/**
 * WCAC Relationship Extractor: Extracts and caches relationships for RAG retrieval.
 * Scans both structured metadata and unstructured content for entity relationships.
 */
class Wcac_Relationship_Extractor
{
    /**
     * Extract and cache relationships for a given post/product/pattern.
     * @param int $post_id
     * @param string $post_type
     * @param array $known_entities [ 'yarn' => [id => name, ...], ... ]
     * @return void
     */
    public static function extract_and_cache(int $post_id, string $post_type, array $known_entities = []): void
    {
        if (!function_exists('get_post')) {
            require_once ABSPATH . 'wp-includes/post.php';
        }
        if (!function_exists('get_post_meta')) {
            require_once ABSPATH . 'wp-includes/meta.php';
        }
        if (!function_exists('wp_get_post_parent_id')) {
            require_once ABSPATH . 'wp-includes/post.php';
        }
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        // 1. Structured: Custom fields/taxonomies (example: yarn_used, product attributes)
        $relationships = [];
        // Example: yarn_used custom field (array of yarn IDs)
        $yarn_used = get_post_meta($post_id, 'yarn_used', true);
        if (is_array($yarn_used)) {
            foreach ($yarn_used as $yarn_id) {
                $relationships[] = [
                    'from_id' => $post_id,
                    'from_type' => $post_type,
                    'to_id' => $yarn_id,
                    'to_type' => 'yarn',
                    'relationship' => 'uses',
                ];
            }
        }
        // Example: WooCommerce product attributes (variants)
        if ($post_type === 'product') {
            if (!function_exists('wp_get_post_parent_id')) {
                require_once ABSPATH . 'wp-includes/post.php';
            }
            $parent_id = wp_get_post_parent_id($post_id);
            if ($parent_id) {
                $relationships[] = [
                    'from_id' => $post_id,
                    'from_type' => 'product',
                    'to_id' => $parent_id,
                    'to_type' => 'product',
                    'relationship' => 'variant_of',
                ];
            }
        }
        // 2. Unstructured: Scan content for known entity names/slugs
        $content = strtolower($post->post_content ?? '');
        foreach ($known_entities as $entity_type => $entities) {
            foreach ($entities as $entity_id => $entity_name) {
                $name_lc = strtolower($entity_name);
                if ($name_lc && strpos($content, $name_lc) !== false) {
                    $relationships[] = [
                        'from_id' => $post_id,
                        'from_type' => $post_type,
                        'to_id' => $entity_id,
                        'to_type' => $entity_type,
                        'relationship' => 'mentions',
                    ];
                }
            }
        }
        // 3. Update the relationship cache
        foreach ($relationships as $rel) {
            Wcac_Relationship_Cache::upsert(
                $rel['from_id'],
                $rel['from_type'],
                $rel['to_id'],
                $rel['to_type'],
                $rel['relationship']
            );
        }
    }
}
