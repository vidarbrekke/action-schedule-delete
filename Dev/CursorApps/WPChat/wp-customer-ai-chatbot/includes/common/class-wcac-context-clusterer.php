<?php

/**
 * WCAC Context Clusterer: Utility for context compression (clustering similar items).
 *
 * Supports multiple algorithms: by title, by category, fuzzy matching, etc.
 */
class Wcac_ContextClusterer
{
    /**
     * Compress context by clustering similar items using the selected algorithm.
     *
     * @param array $items Array of associative arrays (products/content).
     * @param string $algorithm Algorithm to use ('title', 'category', 'fuzzy').
     * @return array Compressed array with one representative per cluster.
     */
    public static function compress(array $items, string $algorithm = 'title'): array
    {
        error_log('WCAC CLUSTER DEBUG: compress() called. Algorithm=' . $algorithm . ', Item count=' . count($items));
        $sample = array_slice($items, 0, 5);
        $sample_titles = array_map(function ($item) {
            return $item['title'] ?? $item['name'] ?? 'NO_TITLE';
        }, $sample);
        error_log('WCAC CLUSTER DEBUG: Sample item titles: ' . print_r($sample_titles, true));
        switch ($algorithm) {
            case 'category':
                return self::compressByCategory($items);
            case 'fuzzy':
                return self::compressByFuzzy($items);
            case 'title':
            default:
                return self::compressByTitle($items);
        }
    }

    // --- Utility: Aggressive normalization for titles ---
    private static function normalize_title($title)
    {
        // Remove bracketed content (e.g., [Super Bulky])
        $title = preg_replace('/\[[^\]]*\]/', '', $title);
        // Remove punctuation
        $title = preg_replace('/[^a-z0-9 ]+/i', '', $title);
        // Collapse whitespace
        $title = preg_replace('/\s+/', ' ', $title);
        // Lowercase and trim
        return strtolower(trim($title));
    }

    // --- Algorithm 1: By Aggressively Normalized Title, then Fuzzy Merge ---
    private static function compressByTitle(array $items): array
    {
        $clusters = [];
        $norm_to_item = [];
        $debug_log = [];
        foreach ($items as $item) {
            $raw_title = $item['title'] ?? $item['name'] ?? '';
            $norm_title = self::normalize_title($raw_title);
            if (!$norm_title) {
                $norm_title = (string)($item['id'] ?? '');
            }
            $debug_log[] = "RAW: '$raw_title' => NORM: '$norm_title'";
            // Keep the highest score or most complete info
            if (!isset($norm_to_item[$norm_title])) {
                $norm_to_item[$norm_title] = $item;
            } elseif ((isset($item['score']) && (!isset($norm_to_item[$norm_title]['score']) || $item['score'] > $norm_to_item[$norm_title]['score']))) {
                $norm_to_item[$norm_title] = $item;
            }
        }
        error_log('WCAC CLUSTER DEBUG: Normalized titles before fuzzy merge: ' . print_r($debug_log, true));
        // Fuzzy merge pass: merge near-duplicates
        $norm_titles = array_keys($norm_to_item);
        $used = array_fill_keys($norm_titles, false);
        $threshold = 4; // Levenshtein threshold
        $cluster_log = [];
        foreach ($norm_titles as $i => $titleA) {
            if ($used[$titleA]) {
                continue;
            }
            $itemA = $norm_to_item[$titleA];
            $merged = [$titleA];
            for ($j = $i + 1; $j < count($norm_titles); $j++) {
                $titleB = $norm_titles[$j];
                if ($used[$titleB]) {
                    continue;
                }
                if (levenshtein($titleA, $titleB) <= $threshold) {
                    $itemB = $norm_to_item[$titleB];
                    if ((isset($itemB['score']) && (!isset($itemA['score']) || $itemB['score'] > $itemA['score']))) {
                        $itemA = $itemB;
                    }
                    $used[$titleB] = true;
                    $merged[] = $titleB;
                }
            }
            $clusters[] = $itemA;
            $used[$titleA] = true;
            $cluster_log[] = "Cluster: [" . implode(", ", $merged) . "] => " . ($itemA['title'] ?? $itemA['name'] ?? 'Unknown');
        }
        error_log('WCAC CLUSTER DEBUG: Final clusters after fuzzy merge: ' . print_r($cluster_log, true));
        return $clusters;
    }

    // --- Algorithm 2: By Category ---
    private static function compressByCategory(array $items): array
    {
        $clusters = [];
        foreach ($items as $item) {
            $cat = isset($item['categories']) && is_array($item['categories']) && count($item['categories']) > 0
                ? strtolower(preg_replace('/[^a-z0-9]+/i', '', $item['categories'][0]))
                : 'uncategorized';
            if (!isset($clusters[$cat])) {
                $clusters[$cat] = $item;
            } else {
                if ((isset($item['score']) && (!isset($clusters[$cat]['score']) || $item['score'] > $clusters[$cat]['score']))) {
                    $clusters[$cat] = $item;
                }
            }
        }
        return array_values($clusters);
    }

    // --- Algorithm 3: Fuzzy Matching (Levenshtein) ---
    private static function compressByFuzzy(array $items): array
    {
        $threshold = 3; // Max Levenshtein distance for clustering
        $clusters = [];
        foreach ($items as $item) {
            $title = isset($item['title']) ? strtolower($item['title']) : '';
            $found = false;
            foreach ($clusters as $key => $rep) {
                if (levenshtein($title, strtolower($rep['title'] ?? '')) <= $threshold) {
                    // Replace with higher score
                    if ((isset($item['score']) && (!isset($rep['score']) || $item['score'] > $rep['score']))) {
                        $clusters[$key] = $item;
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $clusters[] = $item;
            }
        }
        return array_values($clusters);
    }
}
