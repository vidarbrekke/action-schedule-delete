<?php

declare(strict_types=1);

/**
 * Handles simple token counting estimation.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes
 */
class Wcac_Token_Counter
{
    /**
     * Estimates the number of tokens in a string.
     *
     * This uses a rough approximation suitable for many models like GPT.
     * A common rule of thumb is ~4 characters per token.
     *
     * @param string $text The text to count tokens for.
     * @return int The estimated number of tokens.
     */
    public function estimate_token_count(string $text): int
    {
        if (empty($text)) {
            return 0;
        }
        // Simple approximation: 1 token is roughly 4 characters.
        // Use mb_strlen for multi-byte character safety.
        return (int) ceil(mb_strlen($text) / 4);
    }
}
