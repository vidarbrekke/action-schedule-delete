<?php

declare(strict_types=1);

/**
 * WCAC Recommendation Miner: Scans content for recommendation phrases and synonyms.
 * Flags entities as "recommended for" contexts (e.g., socks, summer, beginners).
 */
class Wcac_Recommendation_Miner
{
    /**
     * Mine recommendations from content using a phrase/synonym map.
     * @param string $content
     * @param array $phrase_map [ 'context' => [ 'phrase1', 'phrase2', ... ], ... ]
     * @return array List of detected recommendation contexts (e.g., ['socks', 'summer'])
     */
    public static function mine_recommendations(string $content, array $phrase_map): array
    {
        $content_lc = strtolower($content);
        $detected = [];
        foreach ($phrase_map as $context => $phrases) {
            foreach ($phrases as $phrase) {
                $phrase_lc = strtolower($phrase);
                if ($phrase_lc && strpos($content_lc, $phrase_lc) !== false) {
                    $detected[] = $context;
                    break; // Only need one phrase to match per context
                }
            }
        }
        return array_unique($detected);
    }
}
