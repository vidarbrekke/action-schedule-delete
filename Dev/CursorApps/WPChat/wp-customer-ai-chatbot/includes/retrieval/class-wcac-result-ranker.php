<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Use absolute path instead of relative path
if (!defined('WCAC_PLUGIN_DIR')) {
    define('WCAC_PLUGIN_DIR', plugin_dir_path(dirname(dirname(__FILE__))));
}
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-chatbot-rules.php';

/**
 * Class Wcac_Result_Ranker
 *
 * Ranks and filters raw search results based on scoring logic.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes/retrieval
 */
class Wcac_Result_Ranker
{
    /**
     * Process raw results and return ranked items.
     *
     * @param array  $rawResults Raw results from a search strategy.
     * @param string $message    User's query message.
     * @return array Array of ranked and filtered results.
     */
    public function rank(array $rawResults, string $message): array
    {
        // Extract keywords for scoring
        $keywords = Wcac_ChatbotRules::extract_keywords($message);

        $scoring_data = [];
        $items_by_id = [];

        foreach ($rawResults as $item) {
            // Determine item ID
            $item_id = $item['id'] ?? ($item['post_id'] ?? null);
            if ($item_id === null) {
                continue; // Skip items without an ID
            }

            // Score the item and get detailed breakdown
            $debug_breakdown = [];
            $score = Wcac_ChatbotRules::score_item($item, $keywords, $message);
            if (isset($item['matched_fields'])) {
                $matched_fields = $item['matched_fields'];
            } else {
                // Try to get from static property if not present
                $matched_fields = method_exists('Wcac_ChatbotRules', 'get_last_matched_fields') ? Wcac_ChatbotRules::get_last_matched_fields() : [];
            }
            // Get debug breakdown if available (score_item logs it, but doesn't return it)
            if (isset($item['scoring_breakdown'])) {
                $debug_breakdown = $item['scoring_breakdown'];
            } elseif (property_exists('Wcac_ChatbotRules', 'last_debug_breakdown')) {
                $debug_breakdown = Wcac_ChatbotRules::$last_debug_breakdown ?? [];
            }

            // Store original item for type/parent lookups
            $items_by_id[$item_id] = $item;

            // Include score, matched_fields, and scoring_breakdown in the scoring data
            $item['score'] = $score;
            $item['matched_fields'] = $matched_fields;
            $item['scoring_breakdown'] = $debug_breakdown;
            $scoring_data[] = $item;
        }

        // Sort scoring_data by score descending before logging and returning
        usort($scoring_data, function ($a, $b) {
            return ($b['score'] <=> $a['score']);
        });

        // Log full scoring data for debug reports
        // \Wcac_Debug_Logger::log_search($message, $keywords, $scoring_data);

        // Delegate to ChatbotRules to process parent/variation boosting and filtering
        $results = Wcac_ChatbotRules::process_search_results($scoring_data, $items_by_id, $message);

        // Logging moved to the main AJAX handler after all processing is complete.
        // \Wcac_Debug_Logger::log_search($message, $keywords, $scoring_data);

        return $results;
    }
}
