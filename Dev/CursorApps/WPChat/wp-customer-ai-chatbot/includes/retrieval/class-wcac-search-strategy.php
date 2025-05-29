<?php

declare(strict_types=1);

/**
 * Interface Wcac_Search_Strategy
 *
 * Defines a search strategy for retrieving relevant content items.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes/retrieval
 */
interface Wcac_Search_Strategy
{
    /**
     * Execute the search strategy.
     *
     * @param string $message              User's query message.
     * @param array  $conversationHistory  Previous conversation history.
     * @return array Array of raw search results.
     */
    public function search(string $message, array $conversationHistory): array;
}
