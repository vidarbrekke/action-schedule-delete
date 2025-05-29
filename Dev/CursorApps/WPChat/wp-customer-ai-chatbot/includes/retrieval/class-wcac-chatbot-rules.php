<?php
// phpcs:ignoreFile -- Suppress linter warnings for undefined WordPress core functions/constants in plugin context
/**
 * Wcac_ChatbotRules: Centralized scoring and keyword matching logic for the chatbot.
 */
declare(strict_types=1);
if (!defined('ABSPATH') && !defined('PHPUNIT_RUNNING')) {
    exit; // Exit if accessed directly, except during PHPUnit tests
}

/**
 * This file is intended to be run within the WordPress environment.
 */

// Required for Wcac_Query_Analyzer if used directly
require_once __DIR__ . '/class-wcac-query-analyzer.php';

// Avoid circular dependencies - only require content-retriever if not already in the process of loading it
if (!class_exists('Wcac_Content_Retriever') && !defined('WCAC_LOADING_CONTENT_RETRIEVER')) {
    define('WCAC_LOADING_CHATBOT_RULES', true);
    require_once __DIR__ . '/class-wcac-content-retriever.php';
}

// Ensure WP functions are loaded -- these might be needed even if ABSPATH isn't defined (e.g., CLI tests)
// Guard with function_exists to prevent redeclaration errors
if (!function_exists('get_option')) {
    if (defined('ABSPATH')) require_once ABSPATH . 'wp-includes/option.php';
}
if (!function_exists('get_ancestors')) {
    if (defined('ABSPATH')) require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (!function_exists('get_term')) {
    if (defined('ABSPATH')) require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (!function_exists('is_wp_error')) {
    if (defined('ABSPATH')) require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('get_transient')) {
    if (defined('ABSPATH')) require_once ABSPATH . 'wp-includes/option.php';
}
if (!function_exists('set_transient')) {
    if (defined('ABSPATH')) require_once ABSPATH . 'wp-includes/option.php';
}

// Add at the top, after other requires
require_once WCAC_PLUGIN_DIR . 'includes/common/class-wcac-utils.php';
require_once WCAC_PLUGIN_DIR . 'includes/common/context-utils.php';
require_once WCAC_PLUGIN_DIR . 'includes/common/class-wcac-scoring-utils.php';

// Ensure WordPress functions are available for linter/static analysis
if (!function_exists('apply_filters')) {
    require_once ABSPATH . 'wp-includes/plugin.php';
    if (!function_exists('apply_filters')) {
        function apply_filters($tag, $value) { return $value; }
    }
}
if (!function_exists('get_page_by_path')) {
    require_once ABSPATH . 'wp-includes/post.php';
    if (!function_exists('get_page_by_path')) {
        function get_page_by_path($path) { return null; }
    }
}
if (!function_exists('is_wp_error')) {
    require_once ABSPATH . 'wp-includes/functions.php';
    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing) { return false; }
    }
}
if (!function_exists('get_permalink')) {
    require_once ABSPATH . 'wp-includes/link-template.php';
    if (!function_exists('get_permalink')) {
        function get_permalink($id) { return ''; }
    }
}

class Wcac_ChatbotRules {
    /**
     * Centralized option key for all plugin settings.
     */
    public const OPTION_KEY = 'wcac_settings';

    // Scoring weights (admin-configurable)
    public static float $parent_product_weight = 150.0;
    public static float $variation_product_weight = 1.0;
    public static float $title_match_weight = 15.0;
    public static float $content_match_weight = 15.0;
    public static float $category_match_weight = 35.0;
    public static float $tag_match_weight = 15.0;
    public static float $on_sale_weight = 5.0;
    public static float $direct_title_match_bonus = 100.0;
    public static int $max_results_returned = 5;
    public static float $negative_keyword_penalty = -3.0;
    // New configurable boosts (replace hardcoded values)
    public static float $multi_field_match_bonus = 75.0;
    public static float $all_keywords_in_title_boost = 50.0;
    // public static float $fuzzy_match_boost_high = 10.0; // Removed - Not used
    // public static float $fuzzy_match_boost_medium = 5.0; // To be hardcoded
    // public static float $fuzzy_match_boost_low = 1.0; // To be hardcoded
    // New configurable setting for title-category match
    public static float $title_category_match_boost = 150.0;
    // New boost for exact product name matches
    public static float $exact_product_name_boost = 200.0;
    // New configurable filtering settings
    public static float $parent_preference_margin = 25.0;
    public static float $relative_score_threshold = 0.4;
    public static float $menu_match_weight = 30.0; // New: boost for menu matches
    public static float $attribute_match_weight = 20.0; // New
    public static float $parent_category_match_weight = 20.0; // New
    public static float $taxonomy_match_weight = 10.0; // New
    public static float $outofstock_penalty = 0.0; // Penalty for out-of-stock items (default 0, range -200 to 0)
    // New values for query-time boost/devalue terms
    public static float $boost_term_value = 15.0;
    public static float $devalue_term_value = -15.0;
    public static float $recency_boost_multiplier = 10.0; // Default value
    public static int $recency_window_days = 30; // Fixed window for now
    public static float $rating_weight = 20.0; // New: weight for rating boost

    // Common product names to detect as compound keywords
    // Default is empty, loaded from settings in apply_admin_settings
    public static array $compound_product_names = [];
    
    private static array $last_matched_fields = []; // Added property
    public static array $last_debug_breakdown = [];

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
    public static function set_max_results_returned(int $n) { self::$max_results_returned = $n; }
    public static function get_max_results_returned(): int { return self::$max_results_returned; }
    public static function set_negative_keyword_penalty(float $w) { self::$negative_keyword_penalty = $w; }
    public static function get_negative_keyword_penalty(): float { return self::$negative_keyword_penalty; }
    // Getters/Setters for new boosts
    public static function set_multi_field_match_bonus(float $w) { self::$multi_field_match_bonus = $w; }
    public static function get_multi_field_match_bonus(): float { return self::$multi_field_match_bonus; }
    public static function set_all_keywords_in_title_boost(float $w) { self::$all_keywords_in_title_boost = $w; }
    public static function get_all_keywords_in_title_boost(): float { return self::$all_keywords_in_title_boost; }
    // public static function set_fuzzy_match_boost_high(float $w) { /* self::$fuzzy_match_boost_high = $w; */ } // Removed - Not used
    // public static function set_fuzzy_match_boost_medium(float $w) { /* self::$fuzzy_match_boost_medium = $w; */ } // To be removed
    // public static function get_fuzzy_match_boost_medium(): float { /* return self::$fuzzy_match_boost_medium; */ } // To be removed
    // public static function set_fuzzy_match_boost_low(float $w) { /* self::$fuzzy_match_boost_low = $w; */ } // To be removed
    // public static function get_fuzzy_match_boost_low(): float { /* return self::$fuzzy_match_boost_low; */ } // To be removed
    public static function set_title_category_match_boost(float $w) { self::$title_category_match_boost = $w; }
    public static function get_title_category_match_boost(): float { return self::$title_category_match_boost; }
    public static function set_menu_match_weight(float $w) { self::$menu_match_weight = $w; }
    public static function get_menu_match_weight(): float { return self::$menu_match_weight; }
    public static function set_exact_product_name_boost(float $w) { self::$exact_product_name_boost = $w; }
    public static function set_attribute_match_weight(float $w) { self::$attribute_match_weight = $w; }
    public static function get_attribute_match_weight(): float { return self::$attribute_match_weight; }
    public static function set_parent_category_match_weight(float $w) { self::$parent_category_match_weight = $w; }
    public static function get_parent_category_match_weight(): float { return self::$parent_category_match_weight; }
    public static function set_taxonomy_match_weight(float $w) { self::$taxonomy_match_weight = $w; }
    public static function get_taxonomy_match_weight(): float { return self::$taxonomy_match_weight; }
    public static function set_outofstock_penalty(float $w) { self::$outofstock_penalty = max(-200.0, min(0.0, $w)); }
    public static function get_outofstock_penalty(): float { return self::$outofstock_penalty; }
    // Getters/Setters for new boost/devalue values
    public static function set_boost_term_value(float $w) { self::$boost_term_value = $w; }
    public static function get_boost_term_value(): float { return self::$boost_term_value; }
    public static function set_devalue_term_value(float $w) { self::$devalue_term_value = min(0.0, $w); } // Ensure <= 0
    public static function get_devalue_term_value(): float { return self::$devalue_term_value; }
    public static function set_recency_boost_multiplier(float $w) { self::$recency_boost_multiplier = $w; }
    public static function get_recency_boost_multiplier(): float { return self::$recency_boost_multiplier; }

    public static function get_last_matched_fields(): array { // Added getter
        return self::$last_matched_fields;
    }

    // Added method to detect compound product names in a message
    public static function detect_compound_product_names(string $message): array {
        $message_lc = strtolower($message);
        $detected_products = [];
        
        // Ensure compound_product_names is loaded (might not be if apply_admin_settings hasn't run)
        if (empty(self::$compound_product_names)) {
            $options = get_option('wcac_options', []);
            $compound_names_setting = $options['compound_product_names'] ?? '';
            self::$compound_product_names = !empty($compound_names_setting) ? array_map('trim', wcac_safe_split("\n", $compound_names_setting)) : [];
            self::$compound_product_names = array_filter(self::$compound_product_names); // Ensure no empty strings
        }

        foreach (self::$compound_product_names as $product_name) {
             if (!empty($product_name) && strpos($message_lc, $product_name) !== false) {
                $detected_products[] = $product_name;
            }
        }
        
        return $detected_products;
    }
    
    /**
     * Check if two strings are similar within a given Levenshtein distance.
     *
     * @param string $needle The string to search for (keyword).
     * @param string $haystack The string to search within (field content or term).
     * @param int $max_distance The maximum allowed Levenshtein distance (e.g., 1 or 2).
     * @return bool True if the distance is within the threshold, false otherwise.
     */
    private static function _is_fuzzy_match(string $needle, string $haystack, int $max_distance = 1): bool {
        // Optimization: Quick check for length difference
        if (abs(strlen($needle) - strlen($haystack)) > $max_distance) {
            return false;
        }
        // Optimization: If needle is short, require smaller distance
        if (strlen($needle) <= 3 && $max_distance > 0) {
           $max_distance = 0; // Require exact match for very short words
        }
        if (strlen($needle) <= 5 && $max_distance > 1) {
           $max_distance = 1;
        }
        
        $distance = levenshtein($needle, $haystack);
        return $distance >= 0 && $distance <= $max_distance;
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
            $kw_lc = strtolower($kw);
            // Use mb_stripos for potentially multi-byte strings, fallback to stripos
            $pos = function_exists('mb_stripos') ? mb_stripos($field_content_lower, $kw_lc) : stripos($field_content_lower, $kw_lc);

            if ($pos !== false) {
                $score_component += $weight;
                $match_count++;
                $matched_fields[$field_name][] = $kw;
            } else {
                // --- Fuzzy match if not exact --- 
                // Convert field content to array of words for better fuzzy matching against individual words
                $field_words = preg_split('/\s+/', $field_content_lower, -1, PREG_SPLIT_NO_EMPTY);
                $fuzzy_matched = false;
                foreach ($field_words as $word) {
                     // Allow distance 1 for medium boost, distance 2 for low boost
                     if (self::_is_fuzzy_match($kw_lc, $word, 1)) {
                         $boost = 7.5; // Hardcoded value for medium boost (was 5.0)
                         $score_component += $boost;
                         $matched_fields[$field_name . '_fuzzy_d1'][] = $kw . ' (~1 vs ' . $word . ')';
                         $fuzzy_matched = true;
                         break; // Match found, stop checking words for this keyword
                     } elseif (self::_is_fuzzy_match($kw_lc, $word, 2)) {
                         $boost = 2.5; // Hardcoded value for low boost (was 1.0)
                         $score_component += $boost;
                         $matched_fields[$field_name . '_fuzzy_d2'][] = $kw . ' (~2 vs ' . $word . ')';
                         $fuzzy_matched = true;
                         break; // Match found, stop checking words for this keyword
                     }
                }
            }
        }
        return ['score' => $score_component, 'match_count' => $match_count];
    }

    /**
     * Calculate score component based on keyword matches in category/tag arrays.
     * Applies fuzzy matching to individual terms.
     *
     * @param array $term_array Array of term strings (e.g., ['Sweaters', 'Wool']).
     * @param array $keywords Keywords to search for.
     * @param float $weight The weight multiplier for each match.
     * @param array &$matched_fields Reference to the array tracking matched fields and keywords.
     * @param string $field_name The name of the field being checked (for logging/tracking).
     * @return array ['score' => float, 'match_count' => int]
     */
    private static function _score_term_matches(array $term_array, array $keywords, float $weight, array &$matched_fields, string $field_name): array {
        $score_component = 0.0;
        $match_count = 0;
        $processed_keywords = []; // Track keywords already matched (exact or fuzzy) to avoid double counting

        foreach ($keywords as $kw) {
            $kw_lc = strtolower($kw);
            if (in_array($kw_lc, $processed_keywords)) continue; // Skip if already processed

            $found_match = false;
            $best_fuzzy_match_level = 0; // 0=none, 1=fuzzy_d1 (5.0), 2=fuzzy_d2 (1.0)
            $matched_term_for_fuzzy = '';

            foreach ($term_array as $term) {
                $term_lc = strtolower($term);

                // 1. Check for exact match (full keyword vs full term)
                if ($kw_lc === $term_lc) {
                    $score_component += $weight;
                    $match_count++;
                    $matched_fields[$field_name][] = $kw . ' (exact)';
                    $processed_keywords[] = $kw_lc;
                    $found_match = true;
                    break; // Exact match found for this keyword, move to next keyword
                }

                // 2. Check if keyword is a substring of the term (if not an exact match)
                // Use mb_stripos for potentially multi-byte strings, fallback to stripos
                 $pos = function_exists('mb_stripos') ? mb_stripos($term_lc, $kw_lc) : stripos($term_lc, $kw_lc);
                 if ($pos !== false) {
                     $score_component += $weight; // Still count as a match if substring
                     $match_count++;
                     $matched_fields[$field_name][] = $kw . ' (in ' . $term . ')';
                     $processed_keywords[] = $kw_lc;
                     $found_match = true;
                     break; // Substring match found, move to next keyword
                 }

                // 3. Check for fuzzy match (only if no exact/substring match yet for this keyword)
                if (!$found_match) { // Only consider fuzzy if no stronger match was found for this kw-term pair yet
                    if (self::_is_fuzzy_match($kw_lc, $term_lc, 1)) { // dist 1
                        if ($best_fuzzy_match_level < 1 || $best_fuzzy_match_level == 0) { // Prefer dist 1 over dist 2 or no match
                           $best_fuzzy_match_level = 1;
                           $matched_term_for_fuzzy = $term;
                        }
                    } elseif (self::_is_fuzzy_match($kw_lc, $term_lc, 2)) { // dist 2
                        if ($best_fuzzy_match_level < 2 && $best_fuzzy_match_level == 0) { // Prefer dist 2 only if no dist 1 or no match yet
                           $best_fuzzy_match_level = 2;
                           $matched_term_for_fuzzy = $term;
                        }
                    }
                }
            }

            // Apply score for the best fuzzy match found for this keyword (if any and no exact/substring match)
            if (!$found_match && $best_fuzzy_match_level > 0) {
                if ($best_fuzzy_match_level === 1) {
                    $boost = 7.5; // Hardcoded medium boost (was 5.0)
                    $score_component += $boost;
                    $matched_fields[$field_name . '_fuzzy_d1'][] = $kw . ' (~1 vs ' . $matched_term_for_fuzzy . ')';
                } elseif ($best_fuzzy_match_level === 2) { // $best_fuzzy_match_level === 2
                    $boost = 2.5; // Hardcoded low boost (was 1.0)
                    $score_component += $boost;
                    $matched_fields[$field_name . '_fuzzy_d2'][] = $kw . ' (~2 vs ' . $matched_term_for_fuzzy . ')';
                }
                $processed_keywords[] = $kw_lc; // Mark keyword as processed due to fuzzy match
            }
        }
        return ['score' => $score_component, 'match_count' => $match_count];
    }

    /**
     * Calculate boost if the item title exactly matches one of the known compound product names.
     *
     * @param string $title The item's title.
     * @param array &$matched_fields Reference to tracking array.
     * @return float The calculated boost.
     */
    private static function _calculate_exact_product_name_boost(string $title, array &$matched_fields): float {
        $boost = 0.0;
        $title_lc = strtolower($title);

         // Ensure compound_product_names is loaded
         if (empty(self::$compound_product_names)) {
            $options = get_option('wcac_options', []);
            $compound_names_setting = $options['compound_product_names'] ?? '';
            self::$compound_product_names = !empty($compound_names_setting) ? array_map('trim', wcac_safe_split("\n", $compound_names_setting)) : [];
            self::$compound_product_names = array_filter(self::$compound_product_names); // Ensure no empty strings
         }

        foreach (self::$compound_product_names as $product_name) {
             if (!empty($product_name) && $title_lc === $product_name) {
                 $boost = self::$exact_product_name_boost;
                 $matched_fields['exact_product_name_boost'][] = $title . ' (matches ' . $product_name . ')';
                 break; // Found exact match
             }
        }
        return $boost;
    }

    /**
     * Calculate boost if the full query approximately matches the title.
     *
     * @param string|null $full_query The original user query.
     * @param string $title The item's title.
     * @param array &$matched_fields Reference to tracking array.
     * @return array ['boost' => float, 'match_count_bonus' => int]
     */
    private static function _calculate_direct_title_match_boost(?string $full_query, string $title, array &$matched_fields): array {
        $boost = 0.0;
        $match_count_bonus = 0;
        if ($full_query !== null && !empty($full_query) && !empty($title)) {
            $query_norm = trim(strtolower($full_query));
            $title_norm = trim(strtolower($title));

            // Check for exact match first
            if ($query_norm === $title_norm) {
                $boost = self::$direct_title_match_bonus;
                $match_count_bonus = count(explode(' ', $query_norm)); // Bonus for each word in exact match
                $matched_fields['direct_title_match'][] = $full_query . ' (exact)';
            } 
            // Removed fuzzy check here - rely on keyword scoring for partial/fuzzy title matches
            // else {
            //     // Check for fuzzy match (e.g., Levenshtein distance <= 2)
            //     if (self::_is_fuzzy_match($query_norm, $title_norm, 2)) {
            //         $boost = self::$direct_title_match_bonus * 0.5; // Lesser boost for fuzzy
            //         $matched_fields['direct_title_match'][] = $full_query . ' (~2 vs ' . $title . ')';
            //     }
            // }
        }
        return ['boost' => $boost, 'match_count_bonus' => $match_count_bonus];
    }

    /**
     * Calculate boost if keywords match across multiple fields.
     *
     * @param array $matched_fields The tracking array for matched fields.
     * @return array ['boost' => float, 'unique_fields_matched' => int]
     */
    private static function _calculate_multi_field_boost(array $matched_fields): array {
        $boost = 0.0;
        // Count unique base field names (ignore _fuzzy suffixes for counting fields)
        $unique_fields = [];
        foreach (array_keys($matched_fields) as $field_key) {
            // Remove known boost suffixes before counting
            $base_field = preg_replace('/(_fuzzy_d1|_fuzzy_d2|_boost|_match|_weight|_bonus|_penalty)$/', '', $field_key);
            // Exclude keys that are purely informational boosts/penalties
            if (!in_array($base_field, ['exact_product_name', 'query_product_match', 'direct_title', 'multi_field', 'all_keywords_in_title', 'title_category', 'type', 'sale', 'negative_keyword'])) {
                 if (!empty($matched_fields[$field_key])) { // Only count if there were matches
                    $unique_fields[$base_field] = true;
                 }
            }
        }
        $unique_field_count = count($unique_fields);

        if ($unique_field_count > 1) {
            // Apply bonus multiplier based on the number of unique fields matched
            // Example: 2 fields = 1 * bonus, 3 fields = 2 * bonus, etc.
            $boost = self::$multi_field_match_bonus * ($unique_field_count - 1);
            $matched_fields['multi_field_boost'][] = $unique_field_count . ' fields matched';
        }
        return ['boost' => $boost, 'unique_fields_matched' => $unique_field_count];
    }

    /**
     * Calculate boost if a keyword matched in both title and category.
     *
     * @param array $matched_fields Tracking array.
     * @return float Boost value.
     */
    private static function _calculate_title_category_boost(array $matched_fields): float {
        $boost = 0.0;
        $title_matches = $matched_fields['title'] ?? [];
        $category_matches = $matched_fields['categories'] ?? [];
        // Consider fuzzy matches too?
        $title_matches = array_merge($title_matches, $matched_fields['title_fuzzy_d1'] ?? [], $matched_fields['title_fuzzy_d2'] ?? []);
        $category_matches = array_merge($category_matches, $matched_fields['categories_fuzzy_d1'] ?? [], $matched_fields['categories_fuzzy_d2'] ?? []);

        // Normalize keywords (remove fuzzy markers like ' (~1)') for comparison
        $normalize = function($kw) { return trim(preg_replace('/\(~(\d+).*/', '', $kw)); };
        $title_keywords = array_unique(array_map($normalize, $title_matches));
        $category_keywords = array_unique(array_map($normalize, $category_matches));

        $common_keywords = array_intersect($title_keywords, $category_keywords);

        if (!empty($common_keywords)) {
            $boost = self::$title_category_match_boost;
            $matched_fields['title_category_boost'][] = 'Keywords in title & category: ' . implode(', ', $common_keywords);
        }
        return $boost;
    }

    /**
     * Calculate boost if all extracted keywords are found in the title.
     *
     * @param array $keywords All keywords extracted from the query.
     * @param int $title_match_count Count of keywords matched exactly in the title.
     * @param array &$matched_fields Tracking array.
     * @return float Boost value.
     */
    private static function _calculate_all_keywords_in_title_boost(array $keywords, int $title_match_count, array &$matched_fields): float {
        $boost = 0.0;
         if (!empty($keywords) && $title_match_count === count($keywords)) {
             $boost = self::$all_keywords_in_title_boost;
             $matched_fields['all_keywords_in_title_boost'][] = 'All keywords matched in title';
         }
        return $boost;
    }

    /**
     * Get weight based on product type (parent vs variation).
     *
     * @param string $type 'parent' or 'variation'.
     * @param array &$matched_fields Tracking array.
     * @return float Weight value.
     */
    private static function _calculate_type_weight(string $type, array &$matched_fields): float {
        $weight = 0.0;
        if ($type === 'parent') {
            $weight = self::$parent_product_weight;
            $matched_fields['type_weight'][] = 'Parent product';
        } elseif ($type === 'variation') {
            $weight = self::$variation_product_weight;
             $matched_fields['type_weight'][] = 'Variation product';
        } else {
             $matched_fields['type_weight'][] = 'Unknown product type';
        }
        return $weight;
    }

    /**
     * Get weight based on sale status.
     *
     * @param bool $on_sale Whether the item is on sale.
     * @param array &$matched_fields Tracking array.
     * @return float Weight value.
     */
    private static function _calculate_sale_weight(bool $on_sale, array &$matched_fields): float {
        $weight = 0.0;
        if ($on_sale) {
            $weight = self::$on_sale_weight;
            $matched_fields['sale_weight'][] = 'On Sale';
        } else {
             $matched_fields['sale_weight'][] = 'Not on Sale';
        }
        return $weight;
    }

    /**
     * Helper to get the best content for retrieval/scoring: prefer llm_summary if available.
     *
     * @param array $item Indexed item data.
     * @return string
     */
    private static function get_best_content(array $item): string
    {
        if (!empty($item['llm_summary'])) {
            return $item['llm_summary'];
        }
        return $item['content_snippet'] ?? '';
    }

    /**
     * Calculate the relevance score for a single item based on keyword matches and rules.
     *
     * @param array $item Associative array representing the indexed item data.
     * @param array $keywords Keywords extracted from the user query.
     * @param string|null $full_query The original user query (optional, for direct title match).
     * @return float The calculated score.
     */
    public static function score_item(array $item, array $keywords, ?string $full_query = null): float {
        self::apply_admin_settings(get_option('wcac_options', []));
        $score = 0.0;
        $matched_fields = [];

        // Use dedicated scorer classes
        $scorers = [
            new TitleScorer(self::$title_match_weight),
            new ContentScorer(self::$content_match_weight),
            new CategoryScorer(self::$category_match_weight),
            new TagScorer(self::$tag_match_weight),
            new MenuScorer(self::$menu_match_weight),
            new AttributeScorer(self::$attribute_match_weight),
            new ParentCategoryScorer(self::$parent_category_match_weight),
            new TaxonomyScorer(self::$taxonomy_match_weight),
            new RecencyScorer(self::$recency_boost_multiplier),
            new SaleStatusScorer(self::$on_sale_weight),
            new StockStatusScorer(self::$outofstock_penalty),
            new RatingScorer(self::$rating_weight), // <-- Add rating scorer here
            new TypeScorer(self::$parent_product_weight, self::$variation_product_weight),
            new NegativeKeywordScorer(self::$negative_keyword_penalty),
            // MultiFieldMatchScorer must be last, as it depends on matched_fields
        ];

        foreach ($scorers as $scorer) {
            $result = $scorer->score($item, $keywords, $full_query);
            $score += $result['score'];
            if (!empty($result['matched_fields'])) {
                $matched_fields = array_merge($matched_fields, $result['matched_fields']);
            }
        }
        // Add MultiFieldMatchScorer after all others
        $multi_field_scorer = new MultiFieldMatchScorer(self::$multi_field_match_bonus);
        $result = $multi_field_scorer->score($item, $keywords, $full_query, $matched_fields);
        $score += $result['score'];
        if (!empty($result['matched_fields'])) {
            $matched_fields = array_merge($matched_fields, $result['matched_fields']);
        }

        // ... handle special cases (recency, negative keywords, etc.) ...

        // Store breakdown for debug logs
        $item_key = $item['post_id'] ?? spl_object_hash((object)$item);
        self::$last_debug_breakdown[$item_key] = [
            'final_score' => $score,
            'matched_fields' => $matched_fields,
        ];
        self::$last_matched_fields[$item_key] = $matched_fields;

        return $score;
    }

    /**
     * High-level function to retrieve relevant content based on a user message.
     *
     * @param string $message The user's message.
     * @param array $conversation_history The preceding conversation turns.
     * @return array An array containing relevant content snippets or product info.
     */
    public static function retrieve_relevant_content(string $message, array $conversation_history): array {
        // 1. Analyze the query - FIX: Use existing methods instead of missing analyze()
        $query_analyzer = new Wcac_Query_Analyzer();
        $keywords = self::extract_keywords($message); // Get keywords directly
        $intent_analysis = $query_analyzer->detect_intent($message); // Detect specific intent
        $intent = $intent_analysis['type'] ?? 'product_search'; // Default intent
        $filters = []; // TODO: Implement filter detection if needed later

        // Ensure admin settings are loaded before proceeding (includes compound names, stopwords)
        $options = get_option('wcac_options', []); 
        self::apply_admin_settings($options);
        error_log("WCAC DEBUG: retrieve_relevant_content called. Intent: {$intent}, Keywords: " . implode(', ', $keywords));

        // --- Handle Specific Intents (Return early if needed) ---
        // FIX: Restore logic for store_info intent
        if ($intent === 'store_info') {
            error_log("WCAC DEBUG: Store info intent detected. Fetching relevant pages.");
            $faq_content = [];
            
            // Define OBJECT constant if not defined (e.g., specific CLI context?)
            if (!defined('OBJECT')) {
                define('OBJECT', 'OBJECT');
            }
            
            // Logic adapted from Wcac_Content_Retriever
            $admin_slugs = $options['wcac_store_info_slugs'] ?? ''; // Assuming slug setting is in wcac_options
            if (!empty($admin_slugs) && is_string($admin_slugs)) {
                $faq_slugs = array_filter(array_map('trim', preg_split('/[\s,]+/u', $admin_slugs)));
            } else {
                // Default slugs if not set in admin
                // Ensure WordPress functions are loaded for linter
                if (!function_exists('apply_filters')) {
                    if (defined('ABSPATH')) require_once ABSPATH . 'wp-includes/plugin.php';
                }
                // phpcs:ignore WordPress.WP.GlobalFunctionsOverride.Prohibited
                $faq_slugs = (function_exists('apply_filters'))
                    ? apply_filters('wcac_store_info_slugs', [
                        'about', 'shipping', 'returns', 'contact', 'faq', 'customer-service', 'payment', 'privacy', 'terms'
                    ])
                    : [
                        'about', 'shipping', 'returns', 'contact', 'faq', 'customer-service', 'payment', 'privacy', 'terms'
                    ];
            }
            
            foreach ($faq_slugs as $slug) {
                if (!function_exists('get_page_by_path')) {
                    if (defined('ABSPATH')) require_once ABSPATH . 'wp-includes/post.php';
                }
                if (!function_exists('is_wp_error')) {
                    if (defined('ABSPATH')) require_once ABSPATH . 'wp-includes/functions.php';
                }
                // phpcs:ignore WordPress.WP.GlobalFunctionsOverride.Prohibited
                $page = (function_exists('get_page_by_path')) ? get_page_by_path($slug, OBJECT, ['post', 'page']) : null; // Check posts and pages
                // phpcs:ignore WordPress.WP.GlobalFunctionsOverride.Prohibited
                if ($page && (!function_exists('is_wp_error') || !is_wp_error($page)) && $page->post_status === 'publish') {
                    // Fetch content from index table for consistency?
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'wcac_index';
                    $indexed_data = $wpdb->get_row($wpdb->prepare("SELECT post_id, title, url, raw_post_content FROM {$table_name} WHERE post_id = %d", $page->ID), ARRAY_A);
                    
                    if ($indexed_data && !empty($indexed_data['raw_post_content'])) {
                        if (!function_exists('get_permalink')) {
                            if (defined('ABSPATH')) require_once ABSPATH . 'wp-includes/link-template.php';
                        }
                        $content_snippet = mb_substr(wp_strip_all_tags($indexed_data['raw_post_content']), 0, 300) . '...'; // Longer snippet for info
                         $faq_content[] = [
                            'type' => 'store_info',
                            'id' => $page->ID,
                            'title' => $indexed_data['title'] ?? $page->post_title, // Prefer indexed title
                            // phpcs:ignore WordPress.WP.GlobalFunctionsOverride.Prohibited
                            'url' => (function_exists('get_permalink') ? get_permalink($page->ID) : '#'), // Prefer indexed URL
                            'content_snippet' => $content_snippet, // Provide snippet
                            'score' => 9999, // High score for direct intent match
                         ];
                    }
                }
            }
            // Also perform a keyword search limited to pages/posts if keywords exist?
            // For now, just return directly matched slugs.
            return ['type' => 'info_results', 'results' => $faq_content, 'debug_keywords' => $keywords];
        }
        // TODO: Add logic for category intent if needed here?

        // If no keywords extracted for product search, clarify
        if (empty($keywords) && $intent === 'product_search') {
            error_log("WCAC DEBUG: No keywords extracted for product search intent.");
            if (empty($filters)) {
                return ['type' => 'clarification', 'message' => 'I couldn\'t identify specific products or features. Could you provide more details?'];
            }
        }

        // 2. Retrieve candidate items from the index - FIX: Implement DB query here
        // $retriever = new Wcac_Content_Retriever(); // Don't need retriever instance anymore
        // $candidate_items = $retriever->retrieve_candidates($keywords, $filters, $message); // Old call to missing method
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';
        $candidate_items = [];

        if (!empty($keywords)) {
            // Basic keyword search using LIKE (Consider FULLTEXT later)
            // Build WHERE clause dynamically
            $where_clauses = [];
            $params = [];
            // Search across title, search_blob, categories, tags, menu_titles
            $search_fields = ['title', 'search_blob', 'categories', 'tags', 'menu_titles'];
            foreach ($keywords as $kw) {
                $kw_like = '%' . $wpdb->esc_like(trim($kw)) . '%';
                $field_wheres = [];
                foreach ($search_fields as $field) {
                    $field_wheres[] = "{$field} LIKE %s";
                    $params[] = $kw_like;
                }
                // Match if keyword found in ANY of the fields
                $where_clauses[] = '(' . implode(' OR ', $field_wheres) . ')'; 
            }
            
            // Combine keyword clauses (e.g., (field LIKE %kw1% OR ...) OR (field LIKE %kw2% OR ...) )
            $sql_where = implode(' OR ', $where_clauses);
            
            // Add filters (basic example for 'on_sale')
            if (isset($filters['on_sale']) && $filters['on_sale'] === true) {
                 $sql_where .= " AND on_sale = 1";
            }
             // TODO: Add more filter logic (category, tags, price range etc.)

            // Limit initial candidates - scoring will refine further
            // Increase limit slightly as scoring might eliminate some
             $candidate_limit = 100; 
            
            // Construct the full query
            $sql = "SELECT * FROM {$table_name} WHERE {$sql_where} LIMIT %d";
            $params[] = $candidate_limit;

            try {
                $query = $wpdb->prepare($sql, ...$params);
                error_log("WCAC DEBUG: Candidate Query SQL: " . $query); // Log the query
                $candidate_items = $wpdb->get_results($query, ARRAY_A);
                if ($wpdb->last_error) {
                    error_log("WCAC DB ERROR retrieving candidates: " . $wpdb->last_error);
                    $candidate_items = []; // Ensure empty on error
                }
            } catch (Throwable $dbEx) {
                 error_log("WCAC DB EXCEPTION retrieving candidates: " . $dbEx->getMessage());
                 $candidate_items = []; // Ensure empty on exception
            }
        } else {
             error_log("WCAC DEBUG: Skipping candidate retrieval as no keywords were provided.");
        }

        error_log("WCAC DEBUG: Retrieved " . count($candidate_items) . " candidate items from DB.");

        // 3. Score and rank the candidates
        $scoring_data = [];
        $items_by_id = []; // Initialize $items_by_id as an empty array
        
        if (!empty($candidate_items)) {
             // Sort items initially? Maybe by post_modified or a simple pre-score?
             // For now, score all candidates.
            
            self::$last_debug_breakdown = []; // Clear previous debug info
            self::$last_matched_fields = []; // Clear previous matched fields
            
            // $items_by_id = []; // Moved initialization outside the if block
            foreach ($candidate_items as $item) {
                if (!isset($item['post_id'])) continue; // Skip items without post_id
                $item_id = (int)$item['post_id']; // Ensure integer ID
                
                // Store full item data keyed by ID for later processing
                // Ensure it's stored *before* scoring in case scoring fails?
                // Store it regardless of score, process_search_results needs it.
                $items_by_id[$item_id] = $item; 
                
                $score = self::score_item($item, $keywords, $message); // Pass original message
                $scoring_data[] = [
                    'id' => $item_id,
                    'score' => $score,
                    'parent_id' => $item['parent_id'] ?? 0, // Ensure parent_id exists
                    'title' => $item['title'] ?? 'N/A' // Include title for easier debugging
                ];
                // $items_by_id[$item['post_id']] = $item; // Already stored above
                error_log("WCAC DEBUG: Scored item ID {$item_id} ({$item['title']}): Score = {$score}");
            }

            // Sort by score descending
            usort($scoring_data, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
        } else {
             error_log("WCAC DEBUG: No candidate items found for keywords: " . implode(', ', $keywords));
        }

        // 4. Process results (filtering, applying parent preference, formatting)
        // $items_by_id should now always be an array (potentially empty)
        $final_results = self::process_search_results($scoring_data, $items_by_id, $message);
         error_log("WCAC DEBUG: Final results count after processing: " . count($final_results));

        // 5. Format the response for the LLM
        // This might involve selecting top N results, summarizing, etc.
        // For now, return the processed, ranked list with scores and debug info
        return [
            'type' => 'product_results',
            'results' => $final_results, // Contains filtered, ranked items with details
            'debug_keywords' => $keywords,
            'debug_scores' => $scoring_data, // Raw scores before processing
            'debug_breakdown' => self::$last_debug_breakdown, // Detailed scoring breakdown
            'debug_matched_fields' => self::$last_matched_fields // Matched fields per item
        ];
    }

    /**
     * Extracts potential keywords from a user message.
     *
     * @param string $message User's query.
     * @param array|null $settings Optional settings (e.g., stopwords, compound names).
     * @return array List of extracted keywords.
     */
    public static function extract_keywords($message, $settings = null) {
        // Load settings if not provided
        if ($settings === null) {
            $settings = get_option(self::OPTION_KEY, []);
            // Ensure compound names are loaded into the static property if needed
            self::apply_admin_settings($settings); 
        }

        // --- Synonym Map Expansion ---
        $synonym_map_raw = $settings['wcac_synonym_map'] ?? '';
        if (!empty($synonym_map_raw)) {
            error_log('WCAC DEBUG: Synonym map loaded in extract_keywords: ' . substr($synonym_map_raw, 0, 200));
        } else {
            error_log('WCAC DEBUG: Synonym map is empty in extract_keywords.');
        }
        $synonym_lines = wcac_safe_split("\n", $synonym_map_raw);
        $synonym_groups = [];
        $synonym_lookup = [];
        if (!empty($synonym_lines)) {
            foreach ($synonym_lines as $line) {
                $terms = wcac_safe_split(',', strtolower($line));
                if (count($terms) > 1) {
                    $synonym_groups[] = $terms;
                    foreach ($terms as $term) {
                        $synonym_lookup[$term] = $terms;
                    }
                } elseif (count($terms) === 1) {
                    $term = $terms[0];
                    $synonym_groups[] = [$term];
                    $synonym_lookup[$term] = [$term];
                }
            }
        }

        // Get stopwords from settings or use default
        $stopwords = self::get_stopwords($settings);
        $compound_names = self::$compound_product_names; // Use static property loaded by apply_admin_settings

        // Normalize message: lowercase, remove punctuation (except maybe hyphens within words?)
        $normalized_message = strtolower($message);
        preg_match_all('/[\p{L}\p{N}\'-]+/u', $normalized_message, $matches);
        $words = $matches[0] ?? [];
        
        // --- Compound Keyword Handling ---
        $keywords = [];
        $message_remaining = $normalized_message . ' ';
        usort($compound_names, function($a, $b) {
            return strlen($b) <=> strlen($a);
        });
        foreach ($compound_names as $compound_name) {
             if (empty($compound_name)) continue;
             $compound_placeholder = str_replace(' ', '_', $compound_name);
             $pos = function_exists('mb_strpos') ? mb_strpos($message_remaining, $compound_name . ' ') : strpos($message_remaining, $compound_name . ' ');
             if ($pos !== false) {
                 $keywords[] = $compound_name;
                 $message_remaining = str_replace($compound_name, $compound_placeholder, $message_remaining);
             }
        }
        $cleaned_remaining = preg_replace('/[[:punct:]]/', ' ', $message_remaining);
        $remaining_words = preg_split('/\s+/', $cleaned_remaining, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($remaining_words as $word) {
            if (strpos($word, '_') !== false) continue;
            if (!in_array($word, $stopwords)) {
                $keywords[] = $word;
            }
        }
        // Remove duplicates and potential empty strings
        $keywords = array_values(array_filter(array_unique($keywords)));

        // --- Synonym Expansion (bi-directional) ---
        $expanded_keywords = [];
        foreach ($keywords as $kw) {
            $kw_lc = strtolower($kw);
            if (isset($synonym_lookup[$kw_lc])) {
                foreach ($synonym_lookup[$kw_lc] as $syn) {
                    $expanded_keywords[] = $syn;
                }
            } else {
                $expanded_keywords[] = $kw_lc;
            }
        }
        $expanded_keywords = array_values(array_filter(array_unique($expanded_keywords)));

        error_log("WCAC DEBUG: Extracted Keywords (with synonyms): " . implode(', ', $expanded_keywords));
        return $expanded_keywords;
    }

    /**
     * Retrieves the list of stopwords.
     *
     * @param array|null $settings Plugin settings array.
     * @return array List of stopwords.
     */
    public static function get_stopwords($settings = null) {
        if ($settings === null) {
            $settings = get_option(self::OPTION_KEY, []);
        }
        $stopwords_setting = $settings['stopwords'] ?? '';
        $default_stopwords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'do', 'does', 'for', 'from', 'how', 'i', 
            'in', 'is', 'it', 'my', 'of', 'on', 'or', 'so', 'that', 'the', 'this', 'to', 'was', 'what', 
            'when', 'where', 'who', 'will', 'with', 'you', 'your', 'hello', 'hi', 'looking', 'search',
            'find', 'have', 'need', 'want', 'about', 'can', 'could', 'give', 'me', 'show', 'tell', 'price'
            // Add more common conversational/search terms
        ];
        $custom_stopwords = wcac_safe_split("\n", strtolower($stopwords_setting));
        // Merge default and custom, ensure uniqueness
        return array_unique(array_merge($default_stopwords, $custom_stopwords));
    }


    /**
     * Applies admin-configured settings to the static properties of this class.
     *
     * @param array $options Associative array of options, typically from get_option('wcac_options').
     */
    public static function apply_admin_settings($options) {
        error_log("WCAC DEBUG: apply_admin_settings called."); // Log entry

        if (empty($options)) {
            // Try fetching options again if passed empty, maybe from a direct call?
            $options = get_option(self::OPTION_KEY, []);
            if (empty($options)) {
                 error_log("WCAC DEBUG: apply_admin_settings received empty options even after get_option. Using defaults."); // Log empty case
                return; // Exit if no options found
            }
             error_log("WCAC DEBUG: apply_admin_settings initially empty, but loaded options via get_option.");
        }

        // Log received options for inspection
        // Use wp_json_encode for potentially better formatting in logs if available, fallback to print_r
        $log_options = (function_exists('wp_json_encode')) ? wp_json_encode($options) : print_r($options, true);
        error_log("WCAC DEBUG: apply_admin_settings received options: " . $log_options);

        // --- Apply Scoring Weights ---
        // FIX: Use the correct option keys WITH the 'wcac_' prefix
        // Use null coalescing operator (??) to safely access array keys and provide defaults from current static value
        // Existing weights
        self::$parent_product_weight = (float)($options['wcac_parent_product_weight'] ?? self::$parent_product_weight);
        self::$variation_product_weight = (float)($options['wcac_variation_product_weight'] ?? self::$variation_product_weight);
        self::$title_match_weight = (float)($options['wcac_title_match_weight'] ?? self::$title_match_weight);
        self::$content_match_weight = (float)($options['wcac_content_match_weight'] ?? self::$content_match_weight);
        self::$category_match_weight = (float)($options['wcac_category_match_weight'] ?? self::$category_match_weight);
        self::$tag_match_weight = (float)($options['wcac_tag_match_weight'] ?? self::$tag_match_weight);
        self::$on_sale_weight = (float)($options['wcac_on_sale_weight'] ?? self::$on_sale_weight);
        self::$direct_title_match_bonus = (float)($options['wcac_direct_title_match_bonus'] ?? self::$direct_title_match_bonus);
        self::$max_results_returned = (int)($options['wcac_max_results_returned'] ?? self::$max_results_returned);
        self::$negative_keyword_penalty = (float)($options['wcac_negative_keyword_penalty'] ?? self::$negative_keyword_penalty);
        self::$menu_match_weight = (float)($options['wcac_menu_match_weight'] ?? self::$menu_match_weight); // Added Menu Match Weight
        if (isset($options['wcac_attribute_match_weight'])) self::$attribute_match_weight = floatval($options['wcac_attribute_match_weight']);
        if (isset($options['wcac_parent_category_match_weight'])) self::$parent_category_match_weight = floatval($options['wcac_parent_category_match_weight']);
        if (isset($options['wcac_taxonomy_match_weight'])) self::$taxonomy_match_weight = floatval($options['wcac_taxonomy_match_weight']);

        // New boosts
        self::$multi_field_match_bonus = (float)($options['wcac_multi_field_match_bonus'] ?? self::$multi_field_match_bonus);
        self::$all_keywords_in_title_boost = (float)($options['wcac_all_keywords_in_title_boost'] ?? self::$all_keywords_in_title_boost);
        // self::$fuzzy_match_boost_high = (float)($options['wcac_fuzzy_match_boost_high'] ?? self::$fuzzy_match_boost_high); // Removed - Not used
        // self::$fuzzy_match_boost_medium = (float)($options['wcac_fuzzy_match_boost_medium'] ?? self::$fuzzy_match_boost_medium); // Removed - Hardcoded
        // self::$fuzzy_match_boost_low = (float)($options['wcac_fuzzy_match_boost_low'] ?? self::$fuzzy_match_boost_low); // Removed - Hardcoded
        self::$title_category_match_boost = (float)($options['wcac_title_category_match_boost'] ?? self::$title_category_match_boost);
        self::$exact_product_name_boost = (float)($options['wcac_exact_product_name_boost'] ?? self::$exact_product_name_boost);

        // New Filtering settings
        self::$parent_preference_margin = (float)($options['wcac_parent_preference_margin'] ?? self::$parent_preference_margin);
        // Ensure relative score threshold is clamped between 0.0 and 1.0
        $relative_threshold = (float)($options['wcac_relative_score_threshold'] ?? self::$relative_score_threshold);
        self::$relative_score_threshold = max(0.0, min(1.0, $relative_threshold)); 
        
        // Compound product names
        $compound_names_setting = $options['wcac_compound_product_names'] ?? '';
        self::$compound_product_names = array_map('strtolower', wcac_safe_split("\n", $compound_names_setting));

        // Out-of-Stock Penalty
        self::$outofstock_penalty = isset($options['wcac_outofstock_penalty']) ? max(-200.0, min(0.0, (float)$options['wcac_outofstock_penalty'])) : self::$outofstock_penalty;

        // Load new boost/devalue values
        self::$boost_term_value = (float)($options['wcac_boost_term_value'] ?? self::$boost_term_value);
        self::$devalue_term_value = min(0.0, (float)($options['wcac_devalue_term_value'] ?? self::$devalue_term_value)); // Ensure <= 0

        // Recency Boost
        self::$recency_boost_multiplier = isset($options['wcac_recency_boost_multiplier']) ? floatval($options['wcac_recency_boost_multiplier']) : self::$recency_boost_multiplier;
        // Fixed window for now
        self::$recency_window_days = isset($options['wcac_recency_window_days']) ? intval($options['wcac_recency_window_days']) : self::$recency_window_days;

        // Rating Weight
        self::$rating_weight = isset($options['wcac_rating_weight']) ? floatval($options['wcac_rating_weight']) : self::$rating_weight;

        // Log final applied weights for verification
        error_log("WCAC DEBUG: apply_admin_settings finished. Title weight: " . self::$title_match_weight . ", Relative Threshold: " . self::$relative_score_threshold . ", Compound names count: " . count(self::$compound_product_names));
    }

    /**
     * Process scored search results: apply filtering, parent preference, limit.
     *
     * @param array $scoring_data Sorted array of ['id' => int, 'score' => float, 'parent_id' => int, 'title' => string].
     * @param array $items_by_id Associative array of full item data keyed by post_id.
     * @param string|null $original_query Original user query for context.
     * @return array Final list of results to be presented.
     */
    public static function process_search_results(array $scoring_data, array $items_by_id, ?string $original_query = null): array {
        if (empty($scoring_data)) {
            return [];
        }
        
        $final_results = [];
        $included_ids = []; // Track IDs already included to avoid duplicates
        $parent_scores = []; // Track scores of parent products
        $max_score = $scoring_data[0]['score']; // Highest score from the sorted list

        error_log("WCAC DEBUG: Processing results. Max score: {$max_score}, Relative threshold: " . self::$relative_score_threshold);

        foreach ($scoring_data as $result) {
            $current_id = $result['id'];
            $current_score = $result['score'];
            $parent_id = $result['parent_id'] ?? 0;
            $is_parent = ($parent_id === 0);

            // 1. Apply Relative Score Threshold Filter
            // Check if score is 0 or below the relative threshold compared to max score
            if ($current_score <= 0 || ($max_score > 0 && ($current_score / $max_score) < self::$relative_score_threshold)) {
                error_log("WCAC DEBUG: Item ID {$current_id} ({$result['title']}) filtered out by relative score. Score: {$current_score}");
                continue; // Skip low-scoring items
            }

            // 2. Parent Preference Logic
            if ($is_parent) {
                // Store parent score
                $parent_scores[$current_id] = $current_score;
                // If this parent is already included (e.g., a variation was added first), skip adding parent again
                if (isset($included_ids[$current_id])) {
                     error_log("WCAC DEBUG: Parent ID {$current_id} already included via variation, skipping duplicate.");
                    continue;
                }
                $final_results[] = $result; // Add parent provisionally
                $included_ids[$current_id] = true;
                 error_log("WCAC DEBUG: Added Parent ID {$current_id}. Score: {$current_score}");

            } else { // Is Variation
                $parent_score = $parent_scores[$parent_id] ?? null;

                // Check if parent exists and its score is known
                if ($parent_score !== null) {
                    // Compare variation score with parent score + margin
                    if ($current_score >= ($parent_score + self::$parent_preference_margin)) {
                        // Variation is significantly better, prefer it
                        // Remove parent if it was added provisionally
                        foreach ($final_results as $key => $final_res) {
                            if ($final_res['id'] === $parent_id) {
                                unset($final_results[$key]);
                                 error_log("WCAC DEBUG: Replacing Parent ID {$parent_id} with better Variation ID {$current_id}. Variation Score: {$current_score}, Parent Score: {$parent_score}");
                                break;
                            }
                        }
                        // Add variation if not already included
                        if (!isset($included_ids[$current_id])) {
                            $final_results[] = $result;
                            $included_ids[$current_id] = true;
                            // Also mark the parent ID as included because we chose its variation
                            $included_ids[$parent_id] = true; 
                             error_log("WCAC DEBUG: Added better Variation ID {$current_id}. Score: {$current_score}");
                        }
                    } else {
                        // Parent score is better or within margin, prefer parent (already added or will be added)
                         error_log("WCAC DEBUG: Preferring Parent ID {$parent_id} over Variation ID {$current_id}. Variation Score: {$current_score}, Parent Score: {$parent_score}");
                        // Ensure parent ID is marked as included (even if parent itself got filtered out by score earlier)
                        $included_ids[$parent_id] = true; 
                    }
                } else {
                     // Parent score not found (maybe parent wasn't retrieved or scored low)
                     // Add variation if not already included and its parent hasn't been included
                     if (!isset($included_ids[$current_id]) && !isset($included_ids[$parent_id])) {
                        $final_results[] = $result;
                        $included_ids[$current_id] = true;
                        // Also mark the parent ID as included because we added its variation
                        $included_ids[$parent_id] = true; 
                         error_log("WCAC DEBUG: Added Variation ID {$current_id} (parent score unknown/low). Score: {$current_score}");
                    }
                }
            }

            // 3. Limit Results (apply after processing all potential candidates)
            // We'll apply the limit after the loop
        }

        // --- Context Compression Integration ---
        $algorithm = (string) get_option('wcac_context_compression_algorithm', 'title');
        if ($algorithm !== 'none' && class_exists('Wcac_ContextClusterer')) {
            $final_results = Wcac_ContextClusterer::compress($final_results, $algorithm);
        }

        // Re-sort final results by score after parent preference logic
        usort($final_results, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Apply limit
        $limited_results = array_slice($final_results, 0, self::$max_results_returned);
        error_log("WCAC DEBUG: Limited results to " . count($limited_results) . " items.");

        // 4. Hydrate with necessary details for display/LLM
        $output_results = [];
        foreach ($limited_results as $res) {
            $item_id = $res['id'];
            if (isset($items_by_id[$item_id])) {
                $full_item = $items_by_id[$item_id];
                $output_results[] = [
                    'id' => $item_id,
                    'title' => $full_item['title'] ?? 'N/A',
                    'url' => $full_item['url'] ?? '#',
                    'score' => $res['score'],
                    'parent_id' => $full_item['parent_id'] ?? 0,
                    // Add other relevant fields needed by LLM or display
                    'categories' => $full_item['categories'] ?? '',
                    'tags' => $full_item['tags'] ?? '',
                    'on_sale' => $full_item['on_sale'] ?? false,
                    // Maybe a snippet? Requires care not to be too long.
                     'content_snippet' => mb_substr(wp_strip_all_tags($full_item['raw_post_content'] ?? ''), 0, 150) . '...' // Example snippet
                ];
            } else {
                 error_log("WCAC WARNING: Item ID {$item_id} not found in items_by_id during final hydration.");
            }
        }

        return $output_results;
    }
    
    /**
     * Formats the debug score breakdown into a readable string.
     *
     * @param array $breakdown The score breakdown array for an item.
     * @return string Formatted string representation.
     */
    public static function format_score_breakdown(array $breakdown): string {
        $output = "Final Score: {$breakdown['final_score']}\n";
        $output .= "(Initial Score before type/sale weights: {$breakdown['initial_score']})\n";
        $output .= "Matched Fields & Boosts:\n";
        if (empty($breakdown['matched_fields'])) {
            $output .= "  (None)\n";
        } else {
            $total_score = 0;
            foreach ($breakdown['matched_fields'] as $field => $data) {
                $score = $data['score'];
                // Ensure score is numeric
                if (is_string($score)) {
                    $score = floatval($score);
                }
                $details = $data['details'];
                $class = str_replace('_match', '', $field);
                $label = ucfirst(str_replace('_', ' ', str_replace('_match', '', $field)));
                $scoreDisplay = (is_float($score) || is_int($score)) ? number_format($score, 2) : 'N/A';
                echo '<tr class="score-row score-' . esc_attr($class) . '">';
                echo '<td>' . esc_html($label) . '</td>';
                echo '<td>' . esc_html($scoreDisplay) . '</td>';
                echo '<td>' . esc_html($details) . '</td>';
                echo '</tr>';
                // Only add to total if numeric
                if (is_numeric($score)) {
                    $total_score += $score;
                }
            }
        }
        return $output;
    }

    /**
     * Search for products based on a query.
     *
     * @param string $query The search query
     * @return array Array of search results
     */
    public function search_products(string $query): array
    {
        error_log('WCAC ChatbotRules: Searching products for query: ' . $query);

        try {
            if (!class_exists('Wcac_Content_Retriever')) {
                require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-content-retriever.php';
            }

            $retriever = new Wcac_Content_Retriever();
            $retrieval_output = $retriever->retrieve($query, []);

            if (!isset($retrieval_output['final_results']) || !is_array($retrieval_output['final_results'])) {
                error_log('WCAC ChatbotRules: Content retriever did not return expected final_results array');
                return [];
            }

            $results = $retrieval_output['final_results'];
            error_log('WCAC ChatbotRules: Raw results: ' . json_encode($results));

            $processed_results = [];
            $seen_ids = [];

            foreach ($results as $result) {
                if (!isset($result['id'])) {
                    continue;
                }

                $id = intval($result['id']);
                if (in_array($id, $seen_ids, true)) {
                    continue;
                }

                $processed_results[] = $result;
                $seen_ids[] = $id;
            }

            error_log('WCAC ChatbotRules: Processed results: ' . json_encode($processed_results));
            return $processed_results;

        } catch (Throwable $e) {
            error_log('WCAC ChatbotRules: Error searching products: ' . $e->getMessage());
            return [];
        }
    }

}

// Example scorer class
class TitleScorer {
    private $weight;
    public function __construct($weight) { $this->weight = $weight; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        $title = ScoringUtils::normalize($item['title'] ?? '');
        foreach ($keywords as $kw) {
            $kw_norm = ScoringUtils::normalize($kw);
            if (ScoringUtils::match_keyword($title, $kw_norm)) {
                $score += $this->weight;
                $matched_fields['title'][] = $kw;
            }
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class ContentScorer {
    private $weight;
    public function __construct($weight) { $this->weight = $weight; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        $content = ScoringUtils::normalize($item['content_snippet'] ?? $item['search_blob'] ?? '');
        foreach ($keywords as $kw) {
            $kw_norm = ScoringUtils::normalize($kw);
            if (ScoringUtils::match_keyword($content, $kw_norm)) {
                $score += $this->weight;
                $matched_fields['content'][] = $kw;
            }
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class CategoryScorer {
    private $weight;
    public function __construct($weight) { $this->weight = $weight; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        $categories = ScoringUtils::normalize(ScoringUtils::split_terms($item['categories'] ?? []));
        foreach ($keywords as $kw) {
            $kw_norm = ScoringUtils::normalize($kw);
            foreach ($categories as $cat) {
                if (ScoringUtils::match_keyword($cat, $kw_norm)) {
                    $score += $this->weight;
                    $matched_fields['categories'][] = $kw;
                    break;
                }
            }
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class TagScorer {
    private $weight;
    public function __construct($weight) { $this->weight = $weight; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        $tags = ScoringUtils::normalize(ScoringUtils::split_terms($item['tags'] ?? []));
        foreach ($keywords as $kw) {
            $kw_norm = ScoringUtils::normalize($kw);
            foreach ($tags as $tag) {
                if (ScoringUtils::match_keyword($tag, $kw_norm)) {
                    $score += $this->weight;
                    $matched_fields['tags'][] = $kw;
                    break;
                }
            }
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class MenuScorer {
    private $weight;
    public function __construct($weight) { $this->weight = $weight; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        $menu_titles = ScoringUtils::normalize(ScoringUtils::split_terms($item['menu_titles'] ?? []));
        foreach ($keywords as $kw) {
            $kw_norm = ScoringUtils::normalize($kw);
            foreach ($menu_titles as $title) {
                if (ScoringUtils::match_keyword($title, $kw_norm)) {
                    $score += $this->weight;
                    $matched_fields['menu_titles'][] = $kw;
                    break;
                }
            }
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class AttributeScorer {
    private $weight;
    public function __construct($weight) { $this->weight = $weight; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        $attributes = ScoringUtils::normalize(ScoringUtils::split_terms($item['attributes_text'] ?? []));
        foreach ($keywords as $kw) {
            $kw_norm = ScoringUtils::normalize($kw);
            foreach ($attributes as $attr) {
                if (ScoringUtils::match_keyword($attr, $kw_norm)) {
                    $score += $this->weight;
                    $matched_fields['attributes'][] = $kw;
                    break;
                }
            }
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class ParentCategoryScorer {
    private $weight;
    public function __construct($weight) { $this->weight = $weight; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        $parent_categories = ScoringUtils::normalize(ScoringUtils::split_terms($item['parent_category_names'] ?? []));
        foreach ($keywords as $kw) {
            $kw_norm = ScoringUtils::normalize($kw);
            foreach ($parent_categories as $cat) {
                if (ScoringUtils::match_keyword($cat, $kw_norm)) {
                    $score += $this->weight;
                    $matched_fields['parent_category_names'][] = $kw;
                    break;
                }
            }
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class TaxonomyScorer {
    private $weight;
    public function __construct($weight) { $this->weight = $weight; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        $terms = [];
        if (!empty($item['taxonomies'])) {
            if (is_array($item['taxonomies'])) {
                foreach ($item['taxonomies'] as $tax => $tax_terms) {
                    if (is_array($tax_terms)) {
                        $terms = array_merge($terms, $tax_terms);
                    } else {
                        $terms[] = $tax_terms;
                    }
                }
            } elseif (is_string($item['taxonomies'])) {
                $decoded = json_decode($item['taxonomies'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $tax => $tax_terms) {
                        if (is_array($tax_terms)) {
                            $terms = array_merge($terms, $tax_terms);
                        } else {
                            $terms[] = $tax_terms;
                        }
                    }
                } else {
                    $terms = preg_split('/[|,]/', $item['taxonomies']);
                }
            }
        }
        $terms = ScoringUtils::normalize($terms);
        foreach ($keywords as $kw) {
            $kw_norm = ScoringUtils::normalize($kw);
            foreach ($terms as $term) {
                if (ScoringUtils::match_keyword($term, $kw_norm)) {
                    $score += $this->weight;
                    $matched_fields['taxonomies'][] = $kw;
                    break;
                }
            }
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class RecencyScorer {
    private $weight;
    public function __construct($weight) {
        $this->weight = $weight;
    }
    public function score($item, $keywords, $full_query = null) {
        // Assume $item['modified'] is a timestamp or date string
        if (empty($item['modified'])) {
            return ['score' => 0, 'matched_fields' => []];
        }
        $modified = is_numeric($item['modified']) ? (int)$item['modified'] : strtotime($item['modified']);
        $now = time();
        $days_ago = ($now - $modified) / 86400;
        // Exponential decay: boost recent items, fade older ones
        $decay = exp(-0.1 * $days_ago); // tune decay rate as needed
        $score = $this->weight * $decay;
        return [
            'score' => $score,
            'matched_fields' => $score > 0 ? ['modified'] : []
        ];
    }
}

class SaleStatusScorer {
    private $weight;
    public function __construct($weight) { $this->weight = $weight; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        if (!empty($item['on_sale']) && $item['on_sale']) {
            $score += $this->weight;
            $matched_fields['on_sale'][] = 'on_sale';
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class StockStatusScorer {
    private $penalty;
    public function __construct($penalty) { $this->penalty = $penalty; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        // Penalize if out_of_stock is true or stock_status is 'outofstock'
        $is_out = false;
        if (!empty($item['out_of_stock']) && $item['out_of_stock']) {
            $is_out = true;
        } elseif (!empty($item['stock_status']) && ScoringUtils::normalize($item['stock_status']) === 'outofstock') {
            $is_out = true;
        }
        if ($is_out) {
            $score += $this->penalty;
            $matched_fields['out_of_stock'][] = 'out_of_stock';
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class TypeScorer {
    private $parent_weight;
    private $variation_weight;
    public function __construct($parent_weight, $variation_weight) {
        $this->parent_weight = $parent_weight;
        $this->variation_weight = $variation_weight;
    }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        $type = ScoringUtils::normalize($item['type'] ?? null);
        if ($type === 'parent') {
            $score += $this->parent_weight;
            $matched_fields['type'][] = 'parent';
        } elseif ($type === 'variation') {
            $score += $this->variation_weight;
            $matched_fields['type'][] = 'variation';
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class NegativeKeywordScorer {
    private $penalty;
    public function __construct($penalty) { $this->penalty = $penalty; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        // Assume negative keywords are provided in $item['negative_keywords'] as array or comma-separated string
        $negatives = ScoringUtils::normalize(ScoringUtils::split_terms($item['negative_keywords'] ?? []));
        $penalized = false;
        foreach ($negatives as $neg) {
            if (empty($neg)) continue;
            // Check if negative keyword appears in title, content, or tags
            $fields = [
                ScoringUtils::normalize($item['title'] ?? ''),
                ScoringUtils::normalize($item['content_snippet'] ?? $item['search_blob'] ?? ''),
                ScoringUtils::normalize(is_array($item['tags']) ? implode(' ', $item['tags']) : ($item['tags'] ?? '')),
            ];
            foreach ($fields as $field) {
                if (ScoringUtils::match_keyword($field, $neg)) {
                    $score += $this->penalty;
                    $matched_fields['negative_keywords'][] = $neg;
                    $penalized = true;
                    break 2; // Only penalize once per item
                }
            }
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}

class MultiFieldMatchScorer {
    private $bonus;
    public function __construct($bonus) { $this->bonus = $bonus; }
    public function score($item, $keywords, $full_query, $matched_fields = []) {
        $score = 0.0;
        $fields = [];
        foreach (array_keys($matched_fields) as $field_key) {
            // Remove known boost suffixes before counting
            $base_field = preg_replace('/(_fuzzy_d1|_fuzzy_d2|_boost|_match|_weight|_bonus|_penalty)$/', '', $field_key);
            // Exclude keys that are purely informational boosts/penalties
            if (!in_array($base_field, ['exact_product_name', 'query_product_match', 'direct_title', 'multi_field', 'all_keywords_in_title', 'title_category', 'type', 'sale', 'negative_keyword'])) {
                if (!empty($matched_fields[$field_key])) {
                    $fields[$base_field] = true;
                }
            }
        }
        $unique_field_count = count($fields);
        if ($unique_field_count > 1) {
            $score = $this->bonus * ($unique_field_count - 1);
            return [
                'score' => $score,
                'matched_fields' => ['multi_field_boost' => [$unique_field_count . ' fields matched']]
            ];
        }
        return ['score' => 0, 'matched_fields' => []];
    }
}

// Add this class near other scorer classes
class RatingScorer {
    private $weight;
    public function __construct($weight) { $this->weight = $weight; }
    public function score($item, $keywords, $full_query) {
        $score = 0.0;
        $matched_fields = [];
        if (isset($item['average_rating']) && is_numeric($item['average_rating'])) {
            // Normalize rating to [0,1] (assuming WooCommerce ratings are 0-5)
            $normalized = max(0, min(1, $item['average_rating'] / 5.0));
            $score = $this->weight * $normalized;
            $matched_fields['average_rating'][] = $item['average_rating'];
        }
        return ['score' => $score, 'matched_fields' => $matched_fields];
    }
}
