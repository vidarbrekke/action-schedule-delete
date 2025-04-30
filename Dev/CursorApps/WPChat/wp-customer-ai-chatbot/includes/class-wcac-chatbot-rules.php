<?php
/**
 * Wcac_ChatbotRules: Centralized scoring and keyword matching logic for the chatbot.
 */
class Wcac_ChatbotRules {
    // Scoring weights (admin-configurable)
    public static float $parent_product_weight = 150.0;
    public static float $variation_product_weight = 1.0;
    public static float $title_match_weight = 25.0;
    public static float $content_match_weight = 5.0;
    public static float $category_match_weight = 25.0;
    public static float $tag_match_weight = 10.0;
    public static float $on_sale_weight = 5.0;
    public static float $direct_title_match_bonus = 100.0;
    public static float $min_score_threshold = 50.0;
    public static int $max_results_returned = 5;
    public static float $negative_keyword_penalty = -3.0;
    // New configurable boosts (replace hardcoded values)
    public static float $multi_field_match_bonus = 50.0;
    public static float $all_keywords_in_title_boost = 50.0;
    public static float $fuzzy_match_boost_high = 10.0;
    public static float $fuzzy_match_boost_medium = 5.0;
    public static float $fuzzy_match_boost_low = 1.0;
    // New configurable setting for title-category match
    public static float $title_category_match_boost = 100.0;
    // New boost for exact product name matches
    public static float $exact_product_name_boost = 200.0;
    // New configurable filtering settings
    public static float $parent_preference_margin = 25.0;
    public static float $relative_score_threshold = 0.4;

    // Example: product-seeking phrases (admin-configurable in future)
    public static array $product_query_phrases = [
        'product', 'item', 'buy', 'purchase', 'order', 'shop', 'store',
        'catalog', 'inventory', 'merchandise', 'goods', 'stock', 'sale',
        'price', 'cost', 'available', 'shipping', 'delivery',
        // Expanded phrases
        'have', 'in stock', 'on sale', 'promotion', 'promotions', 'discount',
        'find', 'looking for', 'search', 'do you have', 'can I get', 'carry',
        'yarn', 'wool', 'cotton', 'brand', 'category',
        'new', 'featured', 'clearance', 'deal', 'special', 'offer', 'offers',
        'best seller', 'top seller', 'recommend', 'suggest', 'restock', 'back in stock'
    ];

    // Common product names to detect as compound keywords
    // Default is empty, loaded from settings in apply_admin_settings
    public static array $compound_product_names = [];
    
    // Yarn-related keywords to detect yarn-specific queries
    public static array $yarn_keywords = [
        'yarn', 'wool', 'cotton', 'linen', 'silk', 'alpaca', 'mohair', 'merino'
    ];

    private static array $last_matched_fields = []; // Added property

    // Getters and setters for each variable
    public static function set_parent_product_weight(float $w) { self::$parent_product_weight = $w; }
    public static function get_parent_product_weight(): float { return self::$parent_product_weight; }
    public static function set_variation_product_weight(float $w) { self::$variation_product_weight = $w; }
    public static function get_variation_product_weight(): float { return self::$variation_product_weight; }
    public static function set_title_match_weight(float $w) { self::$title_match_weight = $w; }
    public static function get_title_match_weight(): float { return self::$title_match_weight; }
    public static function set_content_match_weight(float $w) { self::$content_match_weight = $w; }
    public static function get_content_match_weight(): float { return self::$content_match_weight; }
    public static function set_category_match_weight(float $w) { self::$category_match_weight = $w; }
    public static function get_category_match_weight(): float { return self::$category_match_weight; }
    public static function set_tag_match_weight(float $w) { self::$tag_match_weight = $w; }
    public static function get_tag_match_weight(): float { return self::$tag_match_weight; }
    public static function set_on_sale_weight(float $w) { self::$on_sale_weight = $w; }
    public static function get_on_sale_weight(): float { return self::$on_sale_weight; }
    public static function set_direct_title_match_bonus(float $w) { self::$direct_title_match_bonus = $w; }
    public static function get_direct_title_match_bonus(): float { return self::$direct_title_match_bonus; }
    public static function set_min_score_threshold(float $w) { self::$min_score_threshold = $w; }
    public static function get_min_score_threshold(): float { return self::$min_score_threshold; }
    public static function set_max_results_returned(int $n) { self::$max_results_returned = $n; }
    public static function get_max_results_returned(): int { return self::$max_results_returned; }
    public static function set_negative_keyword_penalty(float $w) { self::$negative_keyword_penalty = $w; }
    public static function get_negative_keyword_penalty(): float { return self::$negative_keyword_penalty; }
    // Getters/Setters for new boosts
    public static function set_multi_field_match_bonus(float $w) { self::$multi_field_match_bonus = $w; }
    public static function get_multi_field_match_bonus(): float { return self::$multi_field_match_bonus; }
    public static function set_all_keywords_in_title_boost(float $w) { self::$all_keywords_in_title_boost = $w; }
    public static function get_all_keywords_in_title_boost(): float { return self::$all_keywords_in_title_boost; }
    public static function set_fuzzy_match_boost_high(float $w) { self::$fuzzy_match_boost_high = $w; }
    public static function get_fuzzy_match_boost_high(): float { return self::$fuzzy_match_boost_high; }
    public static function set_fuzzy_match_boost_medium(float $w) { self::$fuzzy_match_boost_medium = $w; }
    public static function get_fuzzy_match_boost_medium(): float { return self::$fuzzy_match_boost_medium; }
    public static function set_fuzzy_match_boost_low(float $w) { self::$fuzzy_match_boost_low = $w; }
    public static function get_fuzzy_match_boost_low(): float { return self::$fuzzy_match_boost_low; }
    public static function set_title_category_match_boost(float $w) { self::$title_category_match_boost = $w; }
    public static function get_title_category_match_boost(): float { return self::$title_category_match_boost; }

    public static function get_last_matched_fields(): array { // Added getter
        return self::$last_matched_fields;
    }

    // Added method to detect compound product names in a message
    public static function detect_compound_product_names(string $message): array {
        $message_lc = strtolower($message);
        $detected_products = [];
        
        foreach (self::$compound_product_names as $product_name) {
            if (strpos($message_lc, $product_name) !== false) {
                $detected_products[] = $product_name;
            }
        }
        
        return $detected_products;
    }
    
    // Added method to detect if query contains yarn-related keywords
    public static function has_yarn_keyword(array $keywords): bool {
        foreach ($keywords as $keyword) {
            if (in_array(strtolower($keyword), self::$yarn_keywords)) {
                return true;
            }
        }
        return false;
    }

    // Product query detection
    public static function is_product_query(string $message): bool {
        $message_lower = strtolower($message);
        $matched = false;
        foreach (self::$product_query_phrases as $phrase) {
            if (strpos($message_lower, $phrase) !== false) {
                error_log('WCAC DEBUG: is_product_query matched phrase: ' . $phrase . ' in message: ' . $message);
                $matched = true;
                break;
            } else {
                error_log('WCAC DEBUG: is_product_query checked phrase: ' . $phrase . ' - no match');
            }
        }
        if (!$matched) {
            error_log('WCAC DEBUG: is_product_query found no match for message: ' . $message);
        }
        return $matched;
    }

    /**
     * Calculate score component based on keyword matches in a specific text field.
     * Helper for score_item.
     *
     * @param string $field_content The content of the field (e.g., title, description).
     * @param array $keywords Keywords to search for.
     * @param float $weight The weight multiplier for each match.
     * @param array &$matched_fields Reference to the array tracking matched fields and keywords.
     * @param string $field_name The name of the field being checked (for logging/tracking).
     * @return array ['score' => float, 'match_count' => int]
     */
    private static function _score_field_matches(string $field_content, array $keywords, float $weight, array &$matched_fields, string $field_name): array {
        $score_component = 0.0;
        $match_count = 0;
        $field_content_lower = strtolower($field_content);

        foreach ($keywords as $kw) {
            if (strpos($field_content_lower, strtolower($kw)) !== false) {
                $score_component += $weight;
                $match_count++;
                $matched_fields[$field_name][] = $kw;
            }
        }
        return ['score' => $score_component, 'match_count' => $match_count];
    }

    /**
     * Calculate score component based on keyword matches in category/tag arrays.
     * Helper for score_item.
     *
     * @param array $term_array Array of term names (categories or tags).
     * @param array $keywords Keywords to search for.
     * @param float $weight The weight multiplier for each match.
     * @param array &$matched_fields Reference to the array tracking matched fields and keywords.
     * @param string $field_name The name of the field being checked (e.g., 'category', 'tag').
     * @return array ['score' => float, 'match_count' => int]
     */
    private static function _score_term_matches(array $term_array, array $keywords, float $weight, array &$matched_fields, string $field_name): array {
        $score_component = 0.0;
        $match_count = 0;
        $matched_term_names = []; // Prevent duplicate scoring for same term per keyword

        foreach ($keywords as $kw) {
            $kw_lower = strtolower($kw);
            foreach ($term_array as $term) {
                $term_lower = strtolower($term);
                // Check if keyword is in term AND this specific term hasn't been matched for this keyword yet
                if (strpos($term_lower, $kw_lower) !== false && !in_array($term_lower, $matched_term_names)) {
                    $score_component += $weight;
                    $match_count++;
                    $matched_fields[$field_name][] = "$kw in $term";
                    $matched_term_names[] = $term_lower; // Track this term name for this keyword
                }
            }
            $matched_term_names = []; // Reset for the next keyword
        }
        return ['score' => $score_component, 'match_count' => $match_count];
    }

    /**
     * Calculate boost for exact product name match.
     * Helper for score_item.
     *
     * @param string $title The item title.
     * @param array &$matched_fields Reference to the array tracking matched fields.
     * @return float The calculated boost score.
     */
    private static function _calculate_exact_product_name_boost(string $title, array &$matched_fields): float {
        $boost = 0.0;
        $title_lower = strtolower($title);
        foreach (self::$compound_product_names as $product_name) {
            if ($title_lower === $product_name || 
                stripos($title_lower, $product_name) === 0 || 
                stripos($title_lower, "[$product_name]") !== false) { 
                $boost += self::$exact_product_name_boost;
                $matched_fields['exact_product_match'][] = $product_name;
                error_log("WCAC DEBUG: Applied exact product name boost for '$title' matching '$product_name'");
                break; 
            }
        }
        return $boost;
    }
    
    /**
     * Calculate boost for query containing a compound product name that also appears in the title.
     * Helper for score_item.
     *
     * @param string|null $full_query The full user query.
     * @param string $title The item title.
     * @param array &$matched_fields Reference to the array tracking matched fields.
     * @return float The calculated boost score.
     */
    private static function _calculate_query_product_match_boost(?string $full_query, string $title, array &$matched_fields): float {
        $boost = 0.0;
        if ($full_query) {
            $query_lc = strtolower($full_query);
            $title_lower = strtolower($title);
            foreach (self::$compound_product_names as $product_name) {
                if (strpos($query_lc, $product_name) !== false && strpos($title_lower, $product_name) !== false) {
                    $boost += self::$exact_product_name_boost * 0.5; 
                    $matched_fields['query_product_match'][] = $product_name;
                    error_log("WCAC DEBUG: Applied query-product match boost for '$title' matching query with '$product_name'");
                }
            }
        }
        return $boost;
    }
    
    /**
     * Calculate boost if the query directly matches the title.
     * Helper for score_item.
     *
     * @param string|null $full_query The full user query.
     * @param string $title The item title.
     * @param array &$matched_fields Reference to the array tracking matched fields.
     * @return array ['boost' => float, 'direct_match' => bool]
     */
    private static function _calculate_direct_title_match_boost(?string $full_query, string $title, array &$matched_fields): array {
        $boost = 0.0;
        $direct_match = false;
        if ($full_query && strtolower($full_query) === strtolower($title)) {
            $boost += self::$direct_title_match_bonus; // Assuming this is the intended bonus
            $direct_match = true;
            $matched_fields['direct_title'][] = strtolower($full_query);
        }
        // Bonus if full query is *part* of the title (was separate check before)
        elseif ($full_query && stripos(strtolower($title), strtolower($full_query)) !== false) {
             $boost += self::$direct_title_match_bonus * 0.5; // Apply partial bonus
             $matched_fields['direct_title_partial_match'] = ['bonus'];
        }
        return ['boost' => $boost, 'direct_match' => $direct_match];
    }
    
    /**
     * Calculate boost if item matches keywords across multiple fields.
     * Helper for score_item.
     *
     * @param array $matched_fields Array tracking matched fields.
     * @return array ['boost' => float, 'multi_match' => bool]
     */
    private static function _calculate_multi_field_boost(array $matched_fields): array {
        $boost = 0.0;
        $multi_match = false;
        $fields_matched_count = 0;
        foreach (['title', 'content', 'category', 'tag'] as $field) {
            if (!empty($matched_fields[$field])) {
                $fields_matched_count++;
            }
        }
        if ($fields_matched_count >= 2) {
            $boost += self::$multi_field_match_bonus;
            $multi_match = true;
            $matched_fields['multi_field_match'] = [$fields_matched_count . ' fields'];
        }
        return ['boost' => $boost, 'multi_match' => $multi_match];
    }

    /**
     * Calculate boost if title and category both have matches.
     * Helper for score_item.
     */
    private static function _calculate_title_category_boost(array $matched_fields): float {
        $boost = 0.0;
        $has_title_match = !empty($matched_fields['title']);
        $has_category_match = !empty($matched_fields['category']);
        if ($has_title_match && $has_category_match) {
            $boost += self::$title_category_match_boost;
            $matched_fields['title_category_boost'] = ['matches both title and category'];
        }
        return $boost;
    }
    
    /**
     * Calculate boost if all keywords are found in the title.
     * Helper for score_item.
     */
    private static function _calculate_all_keywords_in_title_boost(array $keywords, int $title_match_count, array &$matched_fields): float {
        $boost = 0.0;
        if (!empty($keywords) && $title_match_count >= count($keywords)) {
            $boost += self::$all_keywords_in_title_boost;
            $matched_fields['all_keywords_in_title'] = ['bonus'];
        }
        return $boost;
    }

    /**
     * Calculate score component based on product type.
     * Helper for score_item.
     */
    private static function _calculate_type_weight(string $type, array &$matched_fields): float {
        $score = 0.0;
        if ($type === 'product') {
            $score += self::$parent_product_weight;
            $matched_fields['type_boost'] = ['parent_product'];
        } elseif ($type === 'product_variation') {
            $score += self::$variation_product_weight;
            $matched_fields['type_boost'] = ['product_variation'];
        }
        return $score;
    }

    /**
     * Calculate score component based on sale status.
     * Helper for score_item.
     */
    private static function _calculate_sale_weight(bool $on_sale, array &$matched_fields): float {
        $score = 0.0;
        if ($on_sale) {
            $score += self::$on_sale_weight;
            $matched_fields['on_sale'] = ['bonus'];
        }
        return $score;
    }

    /**
     * Score a single item based on how well it matches the keywords.
     * 
     * @param array $item The item data containing title, content, categories, etc.
     * @param array $keywords The extracted keywords to match against
     * @param string|null $full_query Optional full user query for direct title matching
     * @return float The calculated relevance score
     */
    public static function score_item(array $item, array $keywords, ?string $full_query = null): float {
        // Use error_log for debugging
        error_log("WCAC DEBUG (Scoring Detail): Starting score_item for " . ($item['post_id'] ?? 'Unknown ID') . 
            " - " . ($item['title'] ?? 'Unknown Title') . ", keywords: " . implode(', ', $keywords));
        
        $total_score = 0.0;
        $debug_breakdown = []; // Array to hold debug info for logging

        // Ensure required fields are present
        $title = $item['title'] ?? '';
        $content = $item['content_snippet'] ?? '';
        $categories = isset($item['categories']) && is_array($item['categories']) ? $item['categories'] : [];
        $tags = isset($item['tags']) && is_array($item['tags']) ? $item['tags'] : [];
        $on_sale = (bool) ($item['on_sale'] ?? false);
        $type = $item['post_type'] ?? 'unknown'; // Default type if not set

        // Reset matched fields for this specific item scoring instance
        $matched_fields_local = [];

        // Base score based on type (variation vs parent)
        $type_weight_score = self::_calculate_type_weight($type, $matched_fields_local);
        $total_score += $type_weight_score;
        $debug_breakdown['type_weight'] = $type_weight_score;

        // Score field matches
        $title_matches = self::_score_field_matches($title, $keywords, self::$title_match_weight, $matched_fields_local, 'title');
        $total_score += $title_matches['score'];
        $debug_breakdown['title_matches_score'] = $title_matches['score'];
        $debug_breakdown['title_match_count'] = $title_matches['match_count'];

        $content_matches = self::_score_field_matches($content, $keywords, self::$content_match_weight, $matched_fields_local, 'content');
        $total_score += $content_matches['score'];
        $debug_breakdown['content_matches_score'] = $content_matches['score'];

        // Score term matches
        $category_matches = self::_score_term_matches($categories, $keywords, self::$category_match_weight, $matched_fields_local, 'category');
        $total_score += $category_matches['score'];
        $debug_breakdown['category_matches_score'] = $category_matches['score'];
        
        $tag_matches = self::_score_term_matches($tags, $keywords, self::$tag_match_weight, $matched_fields_local, 'tag');
        $total_score += $tag_matches['score'];
        $debug_breakdown['tag_matches_score'] = $tag_matches['score'];

        // Log field score breakdown
        error_log("WCAC DEBUG (Scoring Detail): Field scores for item ID " . ($item['post_id'] ?? 'Unknown') . " - " . 
            "title=" . $title_matches['score'] . " (" . $title_matches['match_count'] . " matches), " . 
            "content=" . $content_matches['score'] . ", " . 
            "category=" . $category_matches['score'] . ", " .  
            "tag=" . $tag_matches['score']);

        // Boosts and adjustments
        $direct_title_boost_info = self::_calculate_direct_title_match_boost($full_query, $title, $matched_fields_local);
        $total_score += $direct_title_boost_info['boost'];
        $debug_breakdown['direct_title_boost'] = $direct_title_boost_info['boost'];
        
        $multi_field_boost_info = self::_calculate_multi_field_boost($matched_fields_local);
        $total_score += $multi_field_boost_info['boost'];
        $debug_breakdown['multi_field_boost'] = $multi_field_boost_info['boost'];

        $all_keywords_boost = self::_calculate_all_keywords_in_title_boost($keywords, $title_matches['match_count'], $matched_fields_local);
        $total_score += $all_keywords_boost;
        $debug_breakdown['all_keywords_in_title_boost'] = $all_keywords_boost;

        $title_category_boost = self::_calculate_title_category_boost($matched_fields_local);
        $total_score += $title_category_boost;
        $debug_breakdown['title_category_boost'] = $title_category_boost;

        // Exact product name boost
        $exact_name_boost = self::_calculate_exact_product_name_boost($title, $matched_fields_local);
        $total_score += $exact_name_boost;
        $debug_breakdown['exact_product_name_boost'] = $exact_name_boost;
        
        // Query-Product Title Match Boost (e.g., "buy Tynn Peer Gynt yarn")
        $query_product_boost = self::_calculate_query_product_match_boost($full_query, $title, $matched_fields_local);
        $total_score += $query_product_boost;
        $debug_breakdown['query_product_match_boost'] = $query_product_boost;

        // On sale weight
        $sale_weight_score = self::_calculate_sale_weight($on_sale, $matched_fields_local);
        $total_score += $sale_weight_score;
        $debug_breakdown['sale_weight'] = $sale_weight_score;

        // Log boost breakdown
        error_log("WCAC DEBUG (Scoring Detail): Boost scores for item ID " . ($item['post_id'] ?? 'Unknown') . " - " . 
            "direct_title=" . $direct_title_boost_info['boost'] . ", " . 
            "multi_field=" . $multi_field_boost_info['boost'] . ", " . 
            "all_keywords=" . $all_keywords_boost . ", " . 
            "title_category=" . $title_category_boost . ", " . 
            "exact_name=" . $exact_name_boost . ", " . 
            "query_product=" . $query_product_boost . ", " . 
            "sale=" . $sale_weight_score);

        // Negative keyword penalty (if implemented)
        // $total_score += self::_calculate_negative_penalty($content, $keywords);

        // Update the class property with the fields matched during this specific call
        self::$last_matched_fields = $matched_fields_local; 

        // Log the detailed breakdown BEFORE returning the score
        error_log("WCAC DEBUG (Scoring Detail): FINAL SCORE for item ID " . ($item['post_id'] ?? 'Unknown') . 
            " - \"" . $title . "\" (type=" . $type . ") = " . $total_score . 
            ", matched_fields=" . json_encode($matched_fields_local));

        return max(0.0, $total_score); // Ensure score is not negative
    }

    /**
     * Builds the WHERE clause for the index search query.
     * Helper for retrieve_relevant_content.
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     * @param array $keywords Keywords to search for.
     * @return string SQL WHERE clause component.
     */
    private static function _build_search_where_clause(array $keywords): string {
        global $wpdb;
        $conditions = [];
        if (empty($keywords)) {
            return '1=0'; // Return no results if no keywords
        }
        
        // Add conditions for each keyword in title, content, categories, and tags
        foreach ($keywords as $keyword) {
            $escaped_keyword = '%' . $wpdb->esc_like($keyword) . '%';
            $conditions[] = $wpdb->prepare("title LIKE %s", $escaped_keyword);
            $conditions[] = $wpdb->prepare("content_snippet LIKE %s", $escaped_keyword);
            $conditions[] = $wpdb->prepare("categories LIKE %s", $escaped_keyword);
            $conditions[] = $wpdb->prepare("tags LIKE %s", $escaped_keyword);
        }
        
        return '(' . implode(' OR ', $conditions) . ')';
    }

    /**
     * Retrieves relevant content based on a user message
     *
     * @param string $message The user message to find content for
     * @param array $conversation_history Previous conversation history
     * @return array Array of relevant content items
     */
    public static function retrieve_relevant_content($message, $conversation_history) {
        global $wpdb;
        
        // Use standard error_log for debugging - will go to debug.log
        error_log("WCAC DEBUG (Scoring): ====== Starting retrieve_relevant_content for message: " . $message . " ======");

        // Get settings
        $table_name = $wpdb->prefix . 'wcac_index';
        $keywords = self::extract_keywords($message);
        
        if (empty($keywords)) {
            error_log("WCAC DEBUG (Scoring): No keywords extracted from message: " . $message);
            return [];
        }
        
        error_log("WCAC DEBUG (Scoring): Extracted keywords: " . implode(', ', $keywords));
        
        // Build SQL query for keyword search
        $where_clause = self::_build_search_where_clause($keywords);
        
        $query = "SELECT DISTINCT post_id, post_type, title, content_snippet, url, 
                        categories, tags, regular_price, sale_price, on_sale, parent_id
                 FROM {$table_name} 
                 WHERE {$where_clause}";
        
        // NOTE: $wpdb->prepare is NOT used for the main query structure here because 
        // the WHERE clause is already fully prepared by _build_search_where_clause.
        // This addresses the previous PHP Notice about incorrect prepare usage.

        error_log('WCAC DEBUG (Scoring): SQL Query: ' . $query);
        $results = $wpdb->get_results($query, 'ARRAY_A');

        // Check for DB errors or empty results
        if (!is_array($results)) {
            $db_error = $wpdb->last_error;
            error_log('WCAC ERROR (Scoring): Database query failed or returned non-array. WPDB Error: ' . ($db_error ? $db_error : 'None'));
            return []; // Return empty array on DB error
        }
        
        error_log('WCAC DEBUG (Scoring): Number of DB results from index: ' . count($results));
        
        // Score results based on keyword matches
        $scoring_data = [];
        $category_counts = [];
        
        foreach ($results as $item) {
            $full_query = $message; // Fix linter: provide full_query for scoring breakdown
            // Skip items without title or content
            if (empty($item['title']) || empty($item['content_snippet'])) {
                error_log('WCAC DEBUG (Scoring): Skipping item ID ' . ($item['post_id'] ?? 'N/A') . ' due to empty title or content.');
                continue;
            }
            
            // Reset matched fields tracker for this specific item before scoring
            self::$last_matched_fields = []; 
            
            // --- BEGIN: Collect full scoring breakdown ---
            $score = 0.0;
            $scoring_breakdown = [
                'type_weight' => 0.0,
                'title_matches_score' => 0.0,
                'title_match_count' => 0,
                'content_matches_score' => 0.0,
                'category_matches_score' => 0.0,
                'tag_matches_score' => 0.0,
                'direct_title_boost' => 0.0,
                'multi_field_boost' => 0.0,
                'all_keywords_in_title_boost' => 0.0,
                'title_category_boost' => 0.0,
                'exact_product_name_boost' => 0.0,
                'query_product_match_boost' => 0.0,
                'sale_weight' => 0.0,
                // Add more factors here as needed
            ];
            $matched_fields_for_this_item = [];
            $title = $item['title'] ?? '';
            $content = $item['content_snippet'] ?? '';
            $categories = isset($item['categories']) && is_array($item['categories']) ? $item['categories'] : [];
            $tags = isset($item['tags']) && is_array($item['tags']) ? $item['tags'] : [];
            $on_sale = (bool) ($item['on_sale'] ?? false);
            $type = $item['post_type'] ?? 'unknown';
            // Type weight
            $type_weight_score = self::_calculate_type_weight($type, $matched_fields_for_this_item);
            $score += $type_weight_score;
            $scoring_breakdown['type_weight'] = $type_weight_score;
            // Title matches
            $title_matches = self::_score_field_matches($title, $keywords, self::$title_match_weight, $matched_fields_for_this_item, 'title');
            $score += $title_matches['score'];
            $scoring_breakdown['title_matches_score'] = $title_matches['score'];
            $scoring_breakdown['title_match_count'] = $title_matches['match_count'];
            // Content matches
            $content_matches = self::_score_field_matches($content, $keywords, self::$content_match_weight, $matched_fields_for_this_item, 'content');
            $score += $content_matches['score'];
            $scoring_breakdown['content_matches_score'] = $content_matches['score'];
            // Category matches
            $category_matches = self::_score_term_matches($categories, $keywords, self::$category_match_weight, $matched_fields_for_this_item, 'category');
            $score += $category_matches['score'];
            $scoring_breakdown['category_matches_score'] = $category_matches['score'];
            // Tag matches
            $tag_matches = self::_score_term_matches($tags, $keywords, self::$tag_match_weight, $matched_fields_for_this_item, 'tag');
            $score += $tag_matches['score'];
            $scoring_breakdown['tag_matches_score'] = $tag_matches['score'];
            // Direct title boost
            $direct_title_boost_info = self::_calculate_direct_title_match_boost($full_query, $title, $matched_fields_for_this_item);
            $score += $direct_title_boost_info['boost'];
            $scoring_breakdown['direct_title_boost'] = $direct_title_boost_info['boost'];
            // Multi-field boost
            $multi_field_boost_info = self::_calculate_multi_field_boost($matched_fields_for_this_item);
            $score += $multi_field_boost_info['boost'];
            $scoring_breakdown['multi_field_boost'] = $multi_field_boost_info['boost'];
            // All keywords in title boost
            $all_keywords_boost = self::_calculate_all_keywords_in_title_boost($keywords, $title_matches['match_count'], $matched_fields_for_this_item);
            $score += $all_keywords_boost;
            $scoring_breakdown['all_keywords_in_title_boost'] = $all_keywords_boost;
            // Title-category boost
            $title_category_boost = self::_calculate_title_category_boost($matched_fields_for_this_item);
            $score += $title_category_boost;
            $scoring_breakdown['title_category_boost'] = $title_category_boost;
            // Exact product name boost
            $exact_name_boost = self::_calculate_exact_product_name_boost($title, $matched_fields_for_this_item);
            $score += $exact_name_boost;
            $scoring_breakdown['exact_product_name_boost'] = $exact_name_boost;
            // Query-product match boost
            $query_product_boost = self::_calculate_query_product_match_boost($full_query, $title, $matched_fields_for_this_item);
            $score += $query_product_boost;
            $scoring_breakdown['query_product_match_boost'] = $query_product_boost;
            // On sale weight
            $sale_weight_score = self::_calculate_sale_weight($on_sale, $matched_fields_for_this_item);
            $score += $sale_weight_score;
            $scoring_breakdown['sale_weight'] = $sale_weight_score;
            // Store matched fields for this item
            self::$last_matched_fields = $matched_fields_for_this_item;
            // Add to scoring data with all relevant information
            $scoring_data[] = [
                'id' => $item['post_id'],
                'type' => $item['post_type'],
                'title' => $item['title'],
                'content' => $item['content_snippet'],
                'categories' => $categories,
                'score' => max(0.0, $score),
                'tags' => $tags,
                'parent_id' => $item['parent_id'],
                'url' => $item['url'],
                'matched_fields' => $matched_fields_for_this_item,
                'scoring_breakdown' => $scoring_breakdown,
            ];
        }
        
        // Process search results to ensure proper parent-variation relationships
        // and apply final scoring adjustments.
        $filtered_items = self::process_search_results($scoring_data, $results);
        
        // Log final results before returning
        error_log('WCAC DEBUG (Scoring): Final processed results count: ' . count($filtered_items));
        
        // ADDED: Log search to database if debug logger is available
        if (class_exists('Wcac_Debug_Logger')) {
            $settings = get_option('wcac_settings', []);
            if (!is_array($settings)) { $settings = []; }
            $llm_params = [
                'temperature' => $settings['wcac_temperature'] ?? null,
                'top_p' => $settings['wcac_top_p'] ?? null,
                'max_tokens' => $settings['wcac_max_tokens'] ?? null,
                'frequency_penalty' => $settings['wcac_frequency_penalty'] ?? null,
                'presence_penalty' => $settings['wcac_presence_penalty'] ?? null,
                'model' => $settings['wcac_model'] ?? null,
            ];
            
            // Attempt logging with better error handling
            try {
                $log_result = Wcac_Debug_Logger::log_search($message, $keywords, $filtered_items, $settings, $llm_params);
                if ($log_result === false) {
                    error_log('WCAC ERROR: Failed to log search for query: ' . $message);
                }
            } catch (\Throwable $e) {
                error_log('WCAC ERROR: Exception while logging search: ' . $e->getMessage());
                // Try creating the table as a failsafe
                try {
                    if (method_exists('Wcac_Debug_Logger', 'create_table')) {
                        Wcac_Debug_Logger::create_table();
                        // Try logging again after table creation
                        Wcac_Debug_Logger::log_search($message, $keywords, $filtered_items, $settings, $llm_params);
                    }
                } catch (\Throwable $inner_e) {
                    error_log('WCAC ERROR: Failed to recover from logging error: ' . $inner_e->getMessage());
                }
            }
        }

        return $filtered_items;
    }
    
    /**
     * Extract keywords from a message for search purposes
     *
     * @param string $message The message to extract keywords from
     * @param array|null $settings Optional settings
     * @return array Array of extracted keywords
     */
    public static function extract_keywords($message, $settings = null) {
        // Remove punctuation and normalize spaces
        $cleaned_message = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $message);
        $cleaned_message = preg_replace('/\s+/', ' ', $cleaned_message);
        $cleaned_message = trim(strtolower($cleaned_message));
        
        // Split into words
        $words = explode(' ', $cleaned_message);
        
        // Get stopwords (common words to ignore)
        $stopwords = self::get_stopwords();
        
        // Apply compound detection for things like product names
        $compound_keywords = self::detect_compound_product_names($message);
        
        // Filter out stopwords, very short words, and numbers-only
        $keywords = [];
        foreach ($words as $word) {
            if (
                strlen($word) > 2 && // More than 2 characters
                !in_array($word, $stopwords) && // Not a stopword
                !is_numeric($word) && // Not just a number 
                !preg_match('/^\d+$/', $word) // Also not just a number (alternative check)
            ) {
                $keywords[] = $word;
            }
        }
        
        // Add compound keywords
        $keywords = array_merge($keywords, $compound_keywords);
        
        // Remove duplicates
        $keywords = array_unique($keywords);
        
        // Cut off at a reasonable number of keywords
        if (count($keywords) > 15) {
            $keywords = array_slice($keywords, 0, 15);
        }
        
        return $keywords;
    }
    
    /**
     * Get default stopwords (common words to ignore in search)
     *
     * @return array Array of stopwords
     */
    private static function get_stopwords() {
        return [
            'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are', 'aren\'t', 
            'as', 'at', 'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by', 'can\'t', 
            'cannot', 'could', 'couldn\'t', 'did', 'didn\'t', 'do', 'does', 'doesn\'t', 'doing', 'don\'t', 'down', 
            'during', 'each', 'few', 'for', 'from', 'further', 'had', 'hadn\'t', 'has', 'hasn\'t', 'have', 'haven\'t', 
            'having', 'he', 'he\'d', 'he\'ll', 'he\'s', 'her', 'here', 'here\'s', 'hers', 'herself', 'him', 'himself', 
            'his', 'how', 'how\'s', 'i', 'i\'d', 'i\'ll', 'i\'m', 'i\'ve', 'if', 'in', 'into', 'is', 'isn\'t', 'it', 
            'it\'s', 'its', 'itself', 'let\'s', 'me', 'more', 'most', 'mustn\'t', 'my', 'myself', 'no', 'nor', 'not', 
            'of', 'off', 'on', 'once', 'only', 'or', 'other', 'ought', 'our', 'ours', 'ourselves', 'out', 'over', 'own', 
            'same', 'shan\'t', 'she', 'she\'d', 'she\'ll', 'she\'s', 'should', 'shouldn\'t', 'so', 'some', 'such', 'than', 
            'that', 'that\'s', 'the', 'their', 'theirs', 'them', 'themselves', 'then', 'there', 'there\'s', 'these', 
            'they', 'they\'d', 'they\'ll', 'they\'re', 'they\'ve', 'this', 'those', 'through', 'to', 'too', 'under', 
            'until', 'up', 'very', 'was', 'wasn\'t', 'we', 'we\'d', 'we\'ll', 'we\'re', 'we\'ve', 'were', 'weren\'t', 
            'what', 'what\'s', 'when', 'when\'s', 'where', 'where\'s', 'which', 'while', 'who', 'who\'s', 'whom', 'why', 
            'why\'s', 'with', 'won\'t', 'would', 'wouldn\'t', 'you', 'you\'d', 'you\'ll', 'you\'re', 'you\'ve', 'your', 
            'yours', 'yourself', 'yourselves',
            // Ecommerce specific stopwords
            'looking', 'need', 'want', 'find', 'search', 'searching', 'seeking', 'get', 'buy', 'purchase', 'order',
            'shipping', 'delivery', 'price', 'cost', 'cheap', 'expensive', 'best', 'top', 'recommend', 'recommended'
        ];
    }

    /**
     * Apply admin settings to the class properties
     *
     * @param array $options Array of options to apply
     */
    public static function apply_admin_settings($options) {
        // Load and process Compound Product Names
        if (isset($options['wcac_compound_product_names'])) {
            $names_string = trim($options['wcac_compound_product_names']);
            if (!empty($names_string)) {
                // Split by newline, trim each line, convert to lowercase, remove empty lines
                self::$compound_product_names = array_filter(array_map(function($name) {
                    return trim(strtolower($name));
                }, preg_split('/\r\n|\r|\n/', $names_string)));
                error_log('WCAC DEBUG: Loaded Compound Product Names from settings: ' . wp_json_encode(self::$compound_product_names));
            } else {
                self::$compound_product_names = []; // Ensure it's empty if setting is empty
            }
        } else {
             self::$compound_product_names = []; // Ensure it's empty if setting not present
        }

        if (isset($options['wcac_parent_product_weight'])) self::$parent_product_weight = (float)$options['wcac_parent_product_weight'];
        if (isset($options['wcac_variation_product_weight'])) self::$variation_product_weight = (float)$options['wcac_variation_product_weight'];
        if (isset($options['wcac_title_match_weight'])) self::$title_match_weight = (float)$options['wcac_title_match_weight'];
        if (isset($options['wcac_content_match_weight'])) self::$content_match_weight = (float)$options['wcac_content_match_weight'];
        if (isset($options['wcac_category_match_weight'])) self::$category_match_weight = (float)$options['wcac_category_match_weight'];
        if (isset($options['wcac_tag_match_weight'])) self::$tag_match_weight = (float)$options['wcac_tag_match_weight'];
        if (isset($options['wcac_on_sale_weight'])) self::$on_sale_weight = (float)$options['wcac_on_sale_weight'];
        if (isset($options['wcac_direct_title_match_bonus'])) self::$direct_title_match_bonus = (float)$options['wcac_direct_title_match_bonus'];
        if (isset($options['wcac_min_score_threshold'])) self::$min_score_threshold = (float)$options['wcac_min_score_threshold'];
        if (isset($options['wcac_max_results_returned'])) self::$max_results_returned = (int)$options['wcac_max_results_returned'];
        if (isset($options['wcac_negative_keyword_penalty'])) self::$negative_keyword_penalty = (float)$options['wcac_negative_keyword_penalty'];
        if (isset($options['wcac_multi_field_match_bonus'])) self::$multi_field_match_bonus = (float)$options['wcac_multi_field_match_bonus'];
        if (isset($options['wcac_all_keywords_in_title_boost'])) self::$all_keywords_in_title_boost = (float)$options['wcac_all_keywords_in_title_boost'];
        if (isset($options['wcac_fuzzy_match_boost_high'])) self::$fuzzy_match_boost_high = (float)$options['wcac_fuzzy_match_boost_high'];
        if (isset($options['wcac_fuzzy_match_boost_medium'])) self::$fuzzy_match_boost_medium = (float)$options['wcac_fuzzy_match_boost_medium'];
        if (isset($options['wcac_fuzzy_match_boost_low'])) self::$fuzzy_match_boost_low = (float)$options['wcac_fuzzy_match_boost_low'];
        if (isset($options['wcac_title_category_match_boost'])) self::$title_category_match_boost = (float)$options['wcac_title_category_match_boost'];
        if (isset($options['wcac_exact_product_name_boost'])) self::$exact_product_name_boost = (float)$options['wcac_exact_product_name_boost'];
        // Load new configurable filtering settings
        if (isset($options['wcac_parent_preference_margin'])) self::$parent_preference_margin = (float)$options['wcac_parent_preference_margin'];
        if (isset($options['wcac_relative_score_threshold'])) self::$relative_score_threshold = max(0.0, min(1.0, (float)$options['wcac_relative_score_threshold'])); // Clamp between 0.0 and 1.0
    }

    /**
     * Process search results to ensure proper parent-variation relationships
     * and apply final scoring adjustments.
     *
     * @param array $scoring_data Array of scored items
     * @param array $items_by_id Lookup array of items by ID
     * @return array Processed and filtered results
     */
    private static function process_search_results(array $scoring_data, array $items_by_id): array {
        // Use error_log for debugging
        error_log("WCAC DEBUG (Scoring): ====== Starting process_search_results with " . count($scoring_data) . " items ======");

        // Separate parents and variations
        $parent_products = [];
        $variations = [];
        $other_items = [];
        
        foreach ($scoring_data as $item) {
            $item_id = $item['id'];
            $item_type = $items_by_id[$item_id]['post_type'] ?? '';
            $parent_id = $items_by_id[$item_id]['parent_id'] ?? 0;
            
            if ($item_type === 'product' && empty($parent_id)) {
                $parent_products[$item_id] = $item;
                error_log("WCAC DEBUG (Scoring): Item ID " . $item_id . " - " . $item['title'] . " classified as PARENT PRODUCT, score=" . $item['score']);
            } elseif ($item_type === 'product_variation' && !empty($parent_id)) {
                if (!isset($variations[$parent_id])) {
                    $variations[$parent_id] = [];
                }
                $variations[$parent_id][] = $item;
                error_log("WCAC DEBUG (Scoring): Item ID " . $item_id . " - " . $item['title'] . " classified as VARIATION of parent " . $parent_id . ", score=" . $item['score']);
            } else {
                $other_items[] = $item;
                error_log("WCAC DEBUG (Scoring): Item ID " . $item_id . " - " . $item['title'] . " classified as OTHER, type=" . $item_type . ", score=" . $item['score']);
            }
        }
        
        // Process each parent and its variations
        foreach ($variations as $parent_id => $parent_variations) {
            if (isset($parent_products[$parent_id])) {
                $parent_score = $parent_products[$parent_id]['score'];
                $highest_variation_score = 0;
                $variation_specific_query = false;
                
                error_log("WCAC DEBUG (Scoring): Processing parent ID " . $parent_id . " with score=" . $parent_score . " and " . count($parent_variations) . " variations");
                
                // Check if any variation has attribute-specific matches
                foreach ($parent_variations as $variation) {
                    $highest_variation_score = max($highest_variation_score, $variation['score']);
                    // Check if variation has specific attribute matches in title
                    if (isset($variation['matched_fields']['title'])) {
                        foreach ($variation['matched_fields']['title'] as $match) {
                            if (strpos(strtolower($match), 'attribute:') !== false) {
                                $variation_specific_query = true;
                                error_log("WCAC DEBUG (Scoring): Found attribute-specific match in variation ID " . $variation['id'] . ": " . $match);
                                break 2;
                            }
                        }
                    }
                }
                
                error_log("WCAC DEBUG (Scoring): Parent ID " . $parent_id . " - variation_specific_query=" . ($variation_specific_query ? 'true' : 'false') . ", highest_variation_score=" . $highest_variation_score);
                
                // Apply parent preference logic
                if (!$variation_specific_query) {
                    // For broad queries, ensure parent ranks higher
                    if ($highest_variation_score > $parent_score) {
                        $boost_amount = $highest_variation_score - $parent_score + self::$parent_preference_margin;
                        $parent_products[$parent_id]['score'] += $boost_amount;
                        error_log("WCAC DEBUG (Scoring): Boosting parent ID " . $parent_id . " by " . $boost_amount . ", new score=" . $parent_products[$parent_id]['score']);
                    }
                }
            }
        }
        
        // Combine all items back together
        $all_items = array_values($parent_products);
        foreach ($variations as $variation_set) {
            $all_items = array_merge($all_items, $variation_set);
        }
        $all_items = array_merge($all_items, $other_items);
        
        error_log("WCAC DEBUG (Scoring): Combined " . count($all_items) . " total items: " . count($parent_products) . " parents, " . count($variations) . " variation sets, " . count($other_items) . " other items");
        
        // Sort by score (descending) and then by title for equal scores
        usort($all_items, function($a, $b) {
            if ($a['score'] == $b['score']) {
                return strcmp($a['title'], $b['title']);
            }
            return ($b['score'] <=> $a['score']);
        });
        
        // Log the sorted items
        error_log("WCAC DEBUG (Scoring): --- Sorted items (top 10) ---");
        for ($i = 0; $i < min(10, count($all_items)); $i++) {
            $item = $all_items[$i];
            error_log("WCAC DEBUG (Scoring): " . ($i+1) . ". ID " . $item['id'] . " - " . $item['title'] . " (type=" . $item['type'] . ", score=" . $item['score'] . ")");
        }
        
        // Apply relative score threshold
        $filtered_items = [];
        if (!empty($all_items)) {
            $top_score = $all_items[0]['score'];
            $min_acceptable_score = $top_score * self::$relative_score_threshold;
            
            error_log("WCAC DEBUG (Scoring): Applying relative score threshold. Top score=" . $top_score . ", threshold=" . self::$relative_score_threshold . ", min acceptable score=" . $min_acceptable_score);
            
            foreach ($all_items as $item) {
                if ($item['score'] >= $min_acceptable_score) {
                    $filtered_items[] = $item;
                } else {
                    error_log("WCAC DEBUG (Scoring): Filtered out ID " . $item['id'] . " - " . $item['title'] . " with score " . $item['score'] . " (below threshold)");
                }
            }
        }
        
        error_log("WCAC DEBUG (Scoring): Final filtered results: " . count($filtered_items) . " items");
        error_log("WCAC DEBUG (Scoring): --- Final filtered items ---");
        foreach ($filtered_items as $i => $item) {
            error_log("WCAC DEBUG (Scoring): " . ($i+1) . ". ID " . $item['id'] . " - " . $item['title'] . " (type=" . $item['type'] . ", score=" . $item['score'] . ")");
        }
        
        return $filtered_items;
    }

    /**
     * Log check to verify that our enhanced logging is active
     */
    public static function log_check() {
        error_log('WCAC DEBUG (Scoring): Wcac_ChatbotRules class loaded - Enhanced logging active');
    }
} 
