<?php
/**
 * Utility class for formatting and truncating context for LLM/RAG retrieval.
 *
 * All context formatting logic should be centralized here for DRYness and maintainability.
 * Future context-related utilities (deduplication, keyword extraction, etc.) should be added as static methods.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes/common
 */
class Wcac_Context_Formatter
{
    /**
     * Truncate a block of text to the first N characters, and append any lines containing specified keywords.
     *
     * @param string $text The full text to truncate.
     * @param int $char_limit Number of characters to always include from the start.
     * @param array $keywords List of keywords to search for (case-insensitive).
     * @param int $max_total_chars Optional. Maximum total length of the result (default: 1200).
     * @return string Truncated and keyword-augmented summary.
     */
    public static function truncate_with_keywords(string $text, int $char_limit, array $keywords, int $max_total_chars = 1200): string
    {
        $summary = mb_substr($text, 0, $char_limit);
        $lines = preg_split('/\r?\n/', $text);
        $matched_lines = [];
        foreach ($lines as $line) {
            foreach ($keywords as $kw) {
                if (stripos($line, $kw) !== false && stripos($summary, trim($line)) === false) {
                    $matched_lines[] = trim($line);
                    break;
                }
            }
        }
        if (!empty($matched_lines)) {
            $summary .= "\n" . implode("\n", array_unique($matched_lines));
        }
        // Optionally trim to avoid excessive length
        if (mb_strlen($summary) > $max_total_chars) {
            $summary = mb_substr($summary, 0, $max_total_chars) . '...';
        }
        return $summary;
    }
} 