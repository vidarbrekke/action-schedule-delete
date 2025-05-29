<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Use absolute path instead of relative path
if (!defined('WCAC_PLUGIN_DIR')) {
    define('WCAC_PLUGIN_DIR', plugin_dir_path(dirname(dirname(__FILE__))));
}
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-search-strategy.php';
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-chatbot-rules.php';
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-attribute-query-expander.php';

/**
 * Class Wcac_Keyword_Search
 *
 * Implements keyword-based search strategy to retrieve content from the index table.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes/retrieval
 */
class Wcac_Keyword_Search implements Wcac_Search_Strategy
{
    /**
     * Execute keyword search against the index table.
     *
     * @param string $message             User's query message.
     * @param array  $conversationHistory Previous conversation history.
     * @return array Array of raw search results.
     */
    public function search(string $message, array $conversationHistory): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';

        // --- Attribute/Variant Query Expansion ---
        // Build attribute map from index (colors, sizes, etc.)
        $catalog_attributes = [];
        $attr_rows = $wpdb->get_results("SELECT attributes FROM {$table_name} WHERE attributes IS NOT NULL", ARRAY_A);
        foreach ($attr_rows as $row) {
            $attrs = json_decode($row['attributes'], true);
            if (is_array($attrs)) {
                foreach ($attrs as $type => $val) {
                    if (!isset($catalog_attributes[$type])) {
                        $catalog_attributes[$type] = [];
                    }
                    if (is_array($val)) {
                        foreach ($val as $v) {
                            if (!in_array($v, $catalog_attributes[$type], true)) {
                                $catalog_attributes[$type][] = $v;
                            }
                        }
                    } elseif (is_string($val) && !in_array($val, $catalog_attributes[$type], true)) {
                        $catalog_attributes[$type][] = $val;
                    }
                }
            }
        }
        $attr_matches = Wcac_Attribute_Query_Expander::expand_query($message, $catalog_attributes);
        $matched_attrs = $attr_matches['matched_attributes'] ?? [];

        // Extract keywords
        $keywords = Wcac_ChatbotRules::extract_keywords($message);
        error_log('WCAC DEBUG: Extracted keywords for query "' . $message . '": ' . json_encode($keywords));
        if (empty($keywords)) {
            return [];
        }

        // Build WHERE clauses for keyword matches in title or content_snippet only (broader fetch)
        $where_clauses = [];
        $params = [];
        foreach ($keywords as $kw) {
            $like = '%' . $wpdb->esc_like($kw) . '%';
            $where_clauses[] = '(title LIKE %s OR content_snippet LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        $where_sql = implode(' OR ', $where_clauses);

        // Limit initial fetch to avoid performance spikes
        $limit = 200;
        $sql = "SELECT * FROM {$table_name} WHERE {$where_sql} LIMIT %d";
        $params[] = $limit;
        $query = $wpdb->prepare($sql, ...$params);
        $rows = $wpdb->get_results($query, ARRAY_A);

        // Convert JSON fields and normalize ID key
        $results = [];
        // --- TF-IDF and Boost/Devalue Setup ---
        $term_frequencies = get_option('wcac_term_frequencies', []);
        $total_products = (int) get_option('wcac_total_products', 1);
        $settings = get_option('wcac_settings', []);
        $boost_terms = isset($settings['wcac_boost_terms']) ? (array)$settings['wcac_boost_terms'] : [];
        $devalue_terms = isset($settings['wcac_devalue_terms']) ? (array)$settings['wcac_devalue_terms'] : [];
        foreach ($rows as $row) {
            $row['id'] = $row['post_id'];
            $row['categories'] = ! empty($row['categories']) ? json_decode($row['categories'], true) : [];
            $row['tags']       = ! empty($row['tags'])       ? json_decode($row['tags'], true)       : [];
            if (!is_array($row['categories'])) {
                $row['categories'] = [];
            }
            if (!is_array($row['tags'])) {
                $row['tags'] = [];
            }
            // --- PHP-side category/tag filtering ---
            $matched = false;
            $match_fields = [];
            $title_str = isset($row['title']) && $row['title'] !== null ? $row['title'] : '';
            $content_str = isset($row['content_snippet']) && $row['content_snippet'] !== null ? $row['content_snippet'] : '';
            foreach ($keywords as $kw) {
                $kw_lc = strtolower($kw);
                // --- Compute TF-IDF weight ---
                $df = isset($term_frequencies[$kw_lc]) ? $term_frequencies[$kw_lc] : 0;
                $idf = log(1 + $total_products / (1 + $df));
                $multiplier = $idf;
                if (in_array($kw_lc, $boost_terms)) {
                    $multiplier *= 2.0;
                } elseif (in_array($kw_lc, $devalue_terms)) {
                    $multiplier *= 0.5;
                }
                // Title/content already matched in SQL, but double-check for fuzzy/partial
                if (strpos(strtolower($title_str), $kw_lc) !== false) {
                    $matched = true;
                    $match_fields[] = "title:$kw (w=" . round($multiplier, 2) . ")";
                }
                if (strpos(strtolower($content_str), $kw_lc) !== false) {
                    $matched = true;
                    $match_fields[] = "content:$kw (w=" . round($multiplier, 2) . ")";
                }
                // Check categories
                foreach ($row['categories'] as $cat) {
                    $cat_lc = is_string($cat) ? strtolower($cat) : '';
                    if (strpos($cat_lc, $kw_lc) !== false) {
                        $matched = true;
                        $match_fields[] = "category:$kw in $cat (w=" . round($multiplier, 2) . ")";
                    }
                    // Fuzzy match
                    similar_text($kw_lc, $cat_lc, $percent);
                    if ($percent >= 60) {
                        $matched = true;
                        $match_fields[] = "category_fuzzy:$kw~$cat ($percent%) (w=" . round($multiplier, 2) . ")";
                    }
                }
                // Check tags
                foreach ($row['tags'] as $tag) {
                    $tag_lc = is_string($tag) ? strtolower($tag) : '';
                    if (strpos($tag_lc, $kw_lc) !== false) {
                        $matched = true;
                        $match_fields[] = "tag:$kw in $tag (w=" . round($multiplier, 2) . ")";
                    }
                    // Fuzzy match
                    similar_text($kw_lc, $tag_lc, $percent);
                    if ($percent >= 60) {
                        $matched = true;
                        $match_fields[] = "tag_fuzzy:$kw~$tag ($percent%) (w=" . round($multiplier, 2) . ")";
                    }
                }
            }
            error_log('WCAC DEBUG: Product ' . $row['id'] . ' - ' . $title_str . ' | Categories: ' . json_encode($row['categories']) . ' | Tags: ' . json_encode($row['tags']) . ' | Matched fields: ' . json_encode($match_fields));
            if ($matched) {
                $results[] = $row;
            }
        }

        // --- Attribute/Variant Result Expansion ---
        if (!empty($matched_attrs)) {
            $expanded_results = $results;
            $seen_ids = array_column($results, 'id');
            foreach ($results as $row) {
                // If this is a variant, fetch and add its parent product
                if (!empty($row['parent_id'])) {
                    $parent = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE post_id = %d", $row['parent_id']), ARRAY_A);
                    if ($parent && !in_array($parent['post_id'], $seen_ids, true)) {
                        $parent['id'] = $parent['post_id'];
                        $expanded_results[] = $parent;
                        $seen_ids[] = $parent['post_id'];
                    }
                }
                // If this is a parent, fetch and add all its variants
                $variants = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} WHERE parent_id = %d", $row['id']), ARRAY_A);
                foreach ($variants as $variant) {
                    if ($variant && !in_array($variant['post_id'], $seen_ids, true)) {
                        $variant['id'] = $variant['post_id'];
                        $expanded_results[] = $variant;
                        $seen_ids[] = $variant['post_id'];
                    }
                }
            }
            $results = $expanded_results;
        }

        // --- Recommended For Context Boosting ---
        $recommendation_contexts = ['socks', 'summer', 'beginner']; // Keep in sync with indexer
        $query_lc = strtolower($message);
        $matched_contexts = array_filter($recommendation_contexts, function ($ctx) use ($query_lc) {
            return strpos($query_lc, $ctx) !== false;
        });
        if (!empty($matched_contexts)) {
            foreach ($matched_contexts as $ctx) {
                $rec_rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE recommended_for LIKE %s",
                    '%' . $wpdb->esc_like($ctx) . '%'
                ), ARRAY_A);
                foreach ($rec_rows as $rec_row) {
                    $already = false;
                    foreach ($results as $r) {
                        if ($r['id'] == $rec_row['post_id']) {
                            $already = true;
                            break;
                        }
                    }
                    if (!$already) {
                        $rec_row['score'] = ($rec_row['score'] ?? 0) + 50; // Boost score for recommended
                        $results[] = $rec_row;
                    }
                }
            }
        }
        return $results;
    }
}
