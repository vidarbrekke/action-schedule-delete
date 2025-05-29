<?php

declare(strict_types=1);

require_once __DIR__ . '/class-wcac-search-strategy.php';

/**
 * Class Wcac_Vector_Search
 *
 * Implements vector-based search strategy for semantic retrieval.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes/retrieval
 */
class Wcac_Vector_Search implements Wcac_Search_Strategy
{
    /**
     * Execute semantic/vector search against the index.
     *
     * @param string $message             User's query message.
     * @param array  $conversationHistory Previous conversation history.
     * @return array Array of raw search results.
     */
    public function search(string $message, array $conversationHistory): array
    {
        // TODO: Implement vector search logic (e.g., call LLM embeddings)
        return [];
    }
}
