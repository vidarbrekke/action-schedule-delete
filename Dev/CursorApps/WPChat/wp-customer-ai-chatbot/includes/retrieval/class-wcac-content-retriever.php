<?php

declare(strict_types=1);

// Ensure all required WordPress functions are loaded before any code runs
if (defined('ABSPATH')) {
    if (!function_exists('apply_filters')) {
        require_once ABSPATH . 'wp-includes/plugin.php';
    }
    if (!function_exists('get_page_by_path')) {
        require_once ABSPATH . 'wp-includes/post.php';
    }
    if (!function_exists('get_permalink')) {
        require_once ABSPATH . 'wp-includes/link-template.php';
    }
    if (!function_exists('get_term_link')) {
        require_once ABSPATH . 'wp-includes/taxonomy.php';
    }
    if (!function_exists('is_wp_error')) {
        require_once ABSPATH . 'wp-includes/functions.php';
    }
    if (!class_exists('WP_Query')) {
        require_once ABSPATH . 'wp-includes/class-wp-query.php';
    }
}

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Use full plugin directory path for includes
if (!defined('WCAC_PLUGIN_DIR')) {
    define('WCAC_PLUGIN_DIR', plugin_dir_path(dirname(dirname(__FILE__))));
}

// Mark this file as being loaded to prevent circular dependencies
define('WCAC_LOADING_CONTENT_RETRIEVER', true);

// Load required classes with absolute paths to avoid relative path issues
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-search-strategy.php';
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-keyword-search.php';
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-vector-search.php';
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-result-ranker.php';
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-query-analyzer.php';

// Only load chatbot rules if not already being loaded (to prevent circular dependencies)
if (!defined('WCAC_LOADING_CHATBOT_RULES')) {
    require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-chatbot-rules.php';
}

// Add at the top, after other require_once statements
require_once WCAC_PLUGIN_DIR . 'includes/common/context-utils.php';
require_once WCAC_PLUGIN_DIR . 'includes/common/class-wcac-query-pipeline.php';
require_once WCAC_PLUGIN_DIR . 'includes/common/query-steps.php';

/**
 * Class Wcac_Content_Retriever
 *
 * Orchestrates different search strategies and ranking to get final relevant content.
 *
 * Extensible: Add new search strategies by extending the strategies array or injecting via constructor (for advanced/test use).
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes/retrieval
 */
class Wcac_Content_Retriever
{
    /** @var Wcac_Search_Strategy[] */
    private array $strategies = [];
    private Wcac_Result_Ranker $ranker;
    private Wcac_Query_Analyzer $analyzer;

    public function __construct()
    {
        // Initialize default strategies
        $this->strategies = [
            new Wcac_Keyword_Search(),
            new Wcac_Vector_Search(),
        ];
        $this->ranker = new Wcac_Result_Ranker();
        $this->analyzer = new Wcac_Query_Analyzer();
    }

    /**
     * Retrieve content based on a query.
     *
     * @param string $query The search query
     * @param array $params Additional options for retrieval
     * @return array Array containing final_results and raw_candidates
     */
    public function retrieve(string $query, array $params = []): array
    {
        error_log('WCAC RETRIEVER: retrieve() called. Query: ' . $query);
        $settings = get_option('wcac_settings', []);
        // Ensure wp_get_current_user is loaded before use
        if (!function_exists('wp_get_current_user') && defined('ABSPATH')) {
            require_once ABSPATH . 'wp-includes/pluggable.php';
        }
        $user_id = 0;
        $user_roles = [];
        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user) {
                $user_id = $user->ID;
                $user_roles = $user->roles;
            }
        }
        error_log('WCAC RETRIEVER: retrieve() called. Query: ' . $query . ' | Settings: ' . json_encode($settings) . ' | User ID: ' . $user_id . ' | Roles: ' . json_encode($user_roles));

        error_log('WCAC Content Retriever: Starting retrieval for query: ' . $query);

        // Use the pluggable query pipeline
        $pipeline = new Wcac_Query_Pipeline([
            'wcac_synonym_expansion_step',
            // Add more steps as needed
        ]);
        $context = $pipeline->process($query);
        $keywords = $context['keywords'] ?? [];
        error_log('WCAC RETRIEVER: Extracted keywords: ' . json_encode($keywords));

        try {
            global $wpdb;
            $table = $wpdb->prefix . 'wcac_index';

            // Get all indexed content
            $sql = "SELECT * FROM {$table}";
            $results = $wpdb->get_results($sql, ARRAY_A);

            if (!is_array($results)) {
                error_log('WCAC Content Retriever: No results found in index');
                return ['final_results' => [], 'raw_candidates' => []];
            }

            error_log('WCAC Content Retriever: Found ' . count($results) . ' indexed items');

            // Score each result
            $scored_results = [];
            $candidate_count = 0;
            // Defensive: ensure $results is always an array
            if (!is_array($results)) {
                $results = [];
            }
            foreach ($results as $result) {
                $candidate_count++;
                $score = $this->score_result($result, $keywords, $query, $user_id, $user_roles, $settings);
                $scored_results[] = array_merge($result, ['score' => $score]);
                }
            error_log('WCAC RETRIEVER: Candidate count: ' . $candidate_count);
            error_log('WCAC RETRIEVER: Scored results: ' . json_encode($scored_results));

            // Sort by score descending
            usort($scored_results, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            error_log('WCAC Content Retriever: Scored and sorted ' . count($scored_results) . ' results');

            // Use params or defaults
            $score_threshold = isset($params['score_threshold']) ? floatval($params['score_threshold']) : 0.1;
            $max_results = isset($params['max_results']) ? intval($params['max_results']) : 20;
            $filtered = array_filter($scored_results, fn($r) => $r['score'] > $score_threshold);
            $top_results = array_slice($filtered, 0, $max_results);

            // After scoring all candidates, log their titles and scores
            if (isset($top_results) && is_array($top_results)) {
                foreach ($top_results as $item) {
                    $title = $item['title'] ?? 'N/A';
                    $score = $item['score'] ?? 'N/A';
                    error_log("WCAC RETRIEVER: Candidate: ID=" . $item['id'] . ", Title='$title', Score=$score");
                }
            }

            // Normalize each result before returning
            $normalized_results = array_map(function($item) {
                return [
                    'id'    => $item['post_id'] ?? $item['id'] ?? 0,
                    'title' => $item['title'] ?? '',
                    'score' => $item['score'] ?? 0.0,
                    // Add other fields as needed
                ];
            }, $top_results);
            // Debug log each normalized result
            foreach ($normalized_results as $item) {
                error_log("WCAC RETRIEVER: Normalized result: ID={$item['id']}, Title='{$item['title']}', Score={$item['score']}");
            }
            return [
                'final_results' => $normalized_results,
                'raw_candidates' => $results
            ];

        } catch (Throwable $e) {
            error_log('WCAC Content Retriever: Error during retrieval: ' . $e->getMessage());
            return ['final_results' => [], 'raw_candidates' => []];
        }
    }

    /**
     * Score a result based on how well it matches the expanded keywords.
     *
     * @param array $result The result to score
     * @param array $keywords The expanded keywords (from pipeline)
     * @param string $query The original query
     * @param int $user_id The current user's ID
     * @param array $user_roles The current user's roles
     * @param array $settings The plugin settings
     * @return float The score
     */
    private function score_result(array $result, array $keywords, string $query, int $user_id, array $user_roles, array $settings): float
    {
        $title = $result['title'] ?? '';
        $type = $result['post_type'] ?? '';
        $id = $result['post_id'] ?? '';
        $score = 0.0;

        // Score title matches
        foreach ($keywords as $kw) {
            if (!empty($title) && stripos($title, $kw) !== false) {
                $score += 1.0;
            }
        }

        // Score content matches
        if (isset($result['content'])) {
            $content = strtolower($result['content']);
            foreach ($keywords as $kw) {
                if (strpos($content, $kw) !== false) {
                    $score += 5;
                }
            }
        }

        // Score category matches
        if (isset($result['categories'])) {
            $categories = strtolower($result['categories']);
            foreach ($keywords as $kw) {
                if (strpos($categories, $kw) !== false) {
                    $score += 15;
                }
            }
        }

        error_log('WCAC SCORE DEBUG: Keywords: ' . implode(',', $keywords) . ' | Item: [' . $type . '] ' . $title . ' (ID: ' . $id . ') | Score: ' . $score);
        return $score;
    }
}
