<?php
/**
 * Wcac_ChatbotRules: Centralized scoring and keyword matching logic for the chatbot.
 */
class Wcac_ChatbotRules {
    // Scoring weights (admin-configurable)
    public static float $parent_product_weight = 2.0;
    public static float $variation_product_weight = 1.0;
    public static float $title_match_weight = 3.0;
    public static float $content_match_weight = 1.5;
    public static float $category_match_weight = 1.0;
    public static float $tag_match_weight = 1.0;
    public static float $on_sale_weight = 2.0;
    public static float $direct_title_match_bonus = 5.0; // Exact keyword == title match
    public static float $min_score_threshold = 0.0;
    public static int $max_results_returned = 5;
    public static float $negative_keyword_penalty = -3.0;
    // New configurable boosts (replace hardcoded values)
    public static float $multi_field_match_bonus = 5.0; // Bonus for keyword matching multiple fields (was 20.0)
    public static float $all_keywords_in_title_boost = 10.0; // Bonus if all keywords found in title (public class, was 150 or 2*direct_title)
    public static float $fuzzy_match_boost_high = 10.0; // Fuzzy title match >= 60% (public class, was 50)
    public static float $fuzzy_match_boost_medium = 5.0; // Fuzzy title match >= 40% (public class, was 20)
    public static float $fuzzy_match_boost_low = 1.0; // Fuzzy title match >= 30% (public class, was 5)

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

    public static function get_last_matched_fields(): array { // Added getter
        return self::$last_matched_fields;
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

    // Scoring function for indexed items
    public static function score_item(array $item, array $keywords): float {
        $score = 0.0;
        $title = strtolower($item['title'] ?? '');
        $content = strtolower($item['content'] ?? '');
        $categories = array_map('strtolower', $item['categories'] ?? []);
        $tags = array_map('strtolower', $item['tags'] ?? []);
        $type = $item['type'] ?? '';
        $on_sale = !empty($item['on_sale']);

        $title_matches = 0;
        $content_matches = 0;
        $category_matches = 0;
        $tag_matches = 0;
        $direct_title_match = false;
        $multi_field_match = false;
        $matched_fields = [];
        self::$last_matched_fields = []; // Reset for this item

        foreach ($keywords as $kw) {
            $kw = strtolower($kw);
            if ($kw === $title) {
                $score += self::$direct_title_match_bonus;
                $direct_title_match = true;
                $matched_fields['direct_title'][] = $kw;
            }
            if (strpos($title, $kw) !== false) {
                $score += self::$title_match_weight;
                $title_matches++;
                $matched_fields['title'][] = $kw;
            }
            if (strpos($content, $kw) !== false) {
                $score += self::$content_match_weight;
                $content_matches++;
                $matched_fields['content'][] = $kw;
            }
            foreach ($categories as $cat) {
                if (strpos($cat, $kw) !== false) {
                    $score += self::$category_match_weight; // Removed * 2 multiplier
                    $category_matches++;
                    $matched_fields['category'][] = $kw;
                }
            }
            foreach ($tags as $tag) {
                if (strpos($tag, $kw) !== false) {
                    $score += self::$tag_match_weight; // Removed * 2 multiplier
                    $tag_matches++;
                    $matched_fields['tag'][] = $kw;
                }
            }
        }
        // Product type weighting
        if ($type === 'product') {
            $score += self::$parent_product_weight;
        } elseif ($type === 'product_variation') {
            $score += self::$variation_product_weight;
        }
        // On sale weighting
        if ($on_sale) {
            $score += self::$on_sale_weight;
        }
        // Multi-field match bonus: if at least one keyword matches in two or more fields
        $fields_matched = 0;
        foreach (['title', 'content', 'category', 'tag'] as $field) {
            if (!empty($matched_fields[$field])) {
                $fields_matched++;
            }
        }
        if ($fields_matched >= 2) {
            $score += self::$multi_field_match_bonus; // Use configurable bonus (was 20.0)
            $multi_field_match = true;
        }
        // Debug log for score breakdown
        error_log('WCAC DEBUG: Score breakdown for "' . ($item['title'] ?? '') . '": ' .
            'direct_title=' . ($direct_title_match ? '1' : '0') .
            ', title_matches=' . $title_matches .
            ', content_matches=' . $content_matches .
            ', category_matches=' . $category_matches .
            ', tag_matches=' . $tag_matches .
            ', multi_field_match=' . ($multi_field_match ? '1' : '0') .
            ', type=' . $type .
            ', on_sale=' . ($on_sale ? '1' : '0') .
            ', total_score=' . $score .
            ', matched_fields=' . wp_json_encode($matched_fields)
        );
        // TODO: Add negative keyword penalty logic if needed
        self::$last_matched_fields = $matched_fields; // Store matched fields for retrieval
        return $score;
    }

    /**
     * Apply admin settings to update all rule/scoring variables at runtime.
     * Call this after loading or updating settings from the admin UI.
     * @param array $options The settings array from wp_options.
     */
    public static function apply_admin_settings(array $options): void {
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
        // Load new configurable boosts
        if (isset($options['wcac_multi_field_match_bonus'])) self::$multi_field_match_bonus = (float)$options['wcac_multi_field_match_bonus'];
        if (isset($options['wcac_all_keywords_in_title_boost'])) self::$all_keywords_in_title_boost = (float)$options['wcac_all_keywords_in_title_boost'];
        if (isset($options['wcac_fuzzy_match_boost_high'])) self::$fuzzy_match_boost_high = (float)$options['wcac_fuzzy_match_boost_high'];
        if (isset($options['wcac_fuzzy_match_boost_medium'])) self::$fuzzy_match_boost_medium = (float)$options['wcac_fuzzy_match_boost_medium'];
        if (isset($options['wcac_fuzzy_match_boost_low'])) self::$fuzzy_match_boost_low = (float)$options['wcac_fuzzy_match_boost_low'];
    }
} 