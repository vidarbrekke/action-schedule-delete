<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('PHPUNIT_RUNNING')) {
    exit; // Exit if accessed directly, except during PHPUnit tests
}

if (!function_exists('get_term_by')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (!function_exists('is_wp_error')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}

/**
 * Class Wcac_Query_Analyzer
 *
 * Analyzes the user's query to determine query type and characteristics.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes/retrieval
 */
class Wcac_Query_Analyzer
{
    public function __construct()
    {
    }

    /**
     * Product-seeking phrases.
     * @var array
     */
    private static array $product_query_phrases = [
        'product', 'item', 'buy', 'purchase', 'order', 'shop', 'store',
        'catalog', 'inventory', 'merchandise', 'goods', 'stock', 'sale',
        'price', 'cost', 'available', 'shipping', 'delivery',
        'have', 'in stock', 'on sale', 'promotion', 'promotions', 'discount',
        'find', 'looking for', 'search', 'do you have', 'can I get', 'carry',
        'yarn', 'wool', 'cotton', 'brand', 'category',
        'new', 'featured', 'clearance', 'deal', 'special', 'offer', 'offers',
        'best seller', 'top seller', 'recommend', 'suggest', 'restock', 'back in stock'
    ];

    /**
     * Store info/FAQ-seeking phrases.
     * @var array
     */
    private static array $store_info_phrases = [
        'opening hours', 'hours', 'when are you open', 'return policy', 'returns', 'refund', 'shipping', 'delivery',
        'contact', 'location', 'address', 'customer service', 'support', 'faq', 'help', 'how to order', 'payment',
        'phone', 'email', 'where are you', 'how do i', 'how can i', 'exchange', 'warranty', 'guarantee'
    ];

    /**
     * Retrieves the list of negative keywords from settings.
     *
     * @return array List of negative keywords.
     */
    public static function get_negative_keywords(): array
    {
        $options = get_option('wcac_options', []);
        $keywords_setting = $options['wcac_negative_keywords'] ?? '';
        $keywords = [];
        if (!empty($keywords_setting)) {
            // Split by newline, trim each line, convert to lowercase, remove empty lines
            $keywords = array_filter(array_map('strtolower', array_map('trim', preg_split('/\r\n|\r|\n/', $keywords_setting))));
        }
        // error_log("WCAC DEBUG: Retrieved negative keywords: " . implode(', ', $keywords)); // Optional logging
        return $keywords;
    }

    /**
     * Determine if the query is seeking a product.
     *
     * @param string $message User's query message.
     * @return bool True if product-focused query.
     */
    public function isProductQuery(string $message): bool
    {
        $message_lower = strtolower($message);
        foreach (self::$product_query_phrases as $phrase) {
            if (strpos($message_lower, $phrase) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Determine if the query is broad (requires category/parent-level results).
     *
     * @param string $message User's query message.
     * @return bool True if broad query.
     */
    public function isBroadQuery(string $message): bool
    {
        return ! $this->isProductQuery($message);
    }

    /**
     * Detects the intent of the query: store info, category, parent product, or general product search.
     *
     * @param string $message User's query message.
     * @return array|null Structured intent info, e.g. ['type' => 'store_info', 'phrase' => 'return policy'], or null if no special intent detected.
     */
    public function detect_intent(string $message): ?array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';
        $msg_lc = strtolower(trim($message));
        $msg_lc = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $msg_lc); // Remove punctuation
        $msg_lc = preg_replace('/\s+/', ' ', $msg_lc);
        $msg_lc = trim($msg_lc);
        $log_context = ['query' => $message, 'normalized_query' => $msg_lc];

        // NEW: Attribute Query Intent Detection (Regex)
        $attribute_patterns = [
            // "Where is X made?" / "Where are X made?" / "Where is X manufactured?" etc.
            '/(where|how) (is|are) (.*?) (made|manufactured|produced)/i' => ['attribute' => 'origin'],
            // "What is X made of?" / "What are X made of?" / "Material of X?"
            '/what (is|are) (.*?) made of/i' => ['attribute' => 'material'],
            '/material of (.*?)/i' => ['attribute' => 'material', 'subject_group' => 1], // Different group for subject
            // Add more specific patterns as needed...
        ];

        foreach ($attribute_patterns as $pattern => $data) {
            if (preg_match($pattern, $message, $matches)) {
                $subject_group = $data['subject_group'] ?? (strpos($pattern, '(.*?) ') !== false ? array_search('(.*?)', explode(' ', $pattern)) - 1 : 2); // Heuristic: find (.*?) group, default to 3rd capture group (index 2)
                // A more robust way might be needed if patterns get complex
                // Fallback if heuristic fails, try common groups
                if (!isset($matches[$subject_group])) {
                    $subject_group = $matches[2] ?? ($matches[1] ?? null);
                }
                 $subject = isset($matches[$subject_group]) ? trim($matches[$subject_group]) : null;

                if ($subject) {
                    error_log('WCAC INTENT: Attribute query match for query "' . $message . '" => Subject: ' . $subject . ', Attribute: ' . $data['attribute'] . ' (Pattern: ' . $pattern . ')');
                    return [
                        'type' => 'attribute_query',
                        'subject' => $subject,
                        'attribute_phrase' => $data['attribute']
                    ];
                }
            }
        }

        // 0. Store info/FAQ intent detection (minimal dictionary)
        foreach (self::$store_info_phrases as $phrase) {
            if (strpos($msg_lc, $phrase) !== false) {
                error_log('WCAC INTENT: Store info match for query "' . $message . '" => phrase "' . $phrase . '"');
                return [
                    'type' => 'store_info',
                    'phrase' => $phrase
                ];
            }
        }

        // 1. Scan all unique parent product titles in the index FIRST
        $parent_rows = $wpdb->get_results("SELECT post_id, title FROM $table_name WHERE post_type = 'product' AND title IS NOT NULL", ARRAY_A);
        foreach ($parent_rows as $row) {
            if (!isset($row['title']) || !is_string($row['title'])) {
                error_log('WCAC INTENT: Skipping non-string product title: ' . var_export($row['title'] ?? null, true));
                continue;
            }
            $title_lc = strtolower($row['title']);
            $title_lc = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title_lc);
            $title_lc = preg_replace('/\s+/', ' ', $title_lc);
            $title_lc = trim($title_lc);
            if (!is_string($title_lc)) {
                error_log('WCAC INTENT: Skipping non-string normalized product title: ' . var_export($title_lc, true));
                continue;
            }
            if ($title_lc === $msg_lc || strpos($msg_lc, $title_lc) !== false || strpos($title_lc, $msg_lc) !== false) {
                error_log('WCAC INTENT: Parent product match for query "' . $message . '" => "' . $row['title'] . '" (ID: ' . $row['post_id'] . ')');
                return [
                    'type' => 'parent_product',
                    'id' => $row['post_id'],
                    'name' => $row['title']
                ];
            }
            if (is_string($msg_lc) && is_string($title_lc)) {
                similar_text($msg_lc, $title_lc, $percent);
                if ($percent >= 85) {
                    error_log('WCAC INTENT: Fuzzy parent product match for query "' . $message . '" => "' . $row['title'] . '" (ID: ' . $row['post_id'] . ', similarity: ' . $percent . '%)');
                    return [
                        'type' => 'parent_product',
                        'id' => $row['post_id'],
                        'name' => $row['title']
                    ];
                }
            }
        }

        // 2. Scan all unique categories in the index (only if no parent product match)
        $cat_rows = $wpdb->get_results("SELECT DISTINCT categories FROM $table_name WHERE post_type = 'product' AND categories IS NOT NULL", ARRAY_A);
        $all_categories = [];
        foreach ($cat_rows as $row) {
            $cats = json_decode($row['categories'], true);
            if (is_array($cats)) {
                foreach ($cats as $cat) {
                    if (!is_string($cat)) {
                        error_log('WCAC INTENT: Skipping non-string category value: ' . var_export($cat, true));
                        continue;
                    }
                    $cat_lc = strtolower($cat);
                    $cat_lc = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $cat_lc);
                    $cat_lc = preg_replace('/\s+/', ' ', $cat_lc);
                    $cat_lc = trim($cat_lc);
                    $all_categories[$cat_lc] = $cat; // Map normalized to original
                }
            }
        }
        foreach ($all_categories as $cat_lc => $cat_orig) {
            if (!is_string($cat_lc) || !is_string($cat_orig)) {
                error_log('WCAC INTENT: Skipping non-string category key or value in match: ' . var_export([$cat_lc, $cat_orig], true));
                continue;
            }
            // Only match categories with at least 5 characters
            if (strlen($cat_lc) < 5) {
                continue;
            }
            // Count words in query
            $query_word_count = str_word_count($msg_lc);
            // Require higher similarity for short category names
            $similarity_threshold = strlen($cat_lc) < 8 ? 90 : 80;
            // --- STRICTER CATEGORY INTENT DETECTION ---
            // Only flag as category intent if:
            // 1. Query is very short (<=3 words) and matches category exactly
            // 2. Or, similarity is extremely high (>=95%) for longer queries
            if ($cat_lc === $msg_lc && $query_word_count <= 3) {
                $cat_id = $this->get_category_id_by_name($cat_orig);
                error_log('WCAC INTENT: STRICT CATEGORY MATCH for query "' . $message . '" => "' . $cat_orig . '" (ID: ' . $cat_id . ')');
                return [
                    'type' => 'category',
                    'name' => $cat_orig,
                    'id' => $cat_id
                ];
            }
            if (is_string($msg_lc) && is_string($cat_lc)) {
                similar_text($msg_lc, $cat_lc, $percent);
                if ($percent >= 95) {
                    $cat_id = $this->get_category_id_by_name($cat_orig);
                    error_log('WCAC INTENT: STRICT FUZZY CATEGORY MATCH for query "' . $message . '" => "' . $cat_orig . '" (ID: ' . $cat_id . ', similarity: ' . $percent . '%)');
                    return [
                        'type' => 'category',
                        'name' => $cat_orig,
                        'id' => $cat_id
                    ];
                } elseif ($percent >= $similarity_threshold) {
                    error_log('WCAC INTENT: Fuzzy category match NOT flagged as intent (percent=' . $percent . ', threshold=' . $similarity_threshold . ') for query "' . $message . '" vs category "' . $cat_orig . '"');
                }
            }
        }

        error_log('WCAC INTENT: No intent detected for query "' . $message . '" (normalized: "' . $msg_lc . '")');
        // 3. Default: no special intent detected
        return null;
    }

    /**
     * Helper to get WooCommerce category term ID by name.
     *
     * @param string $cat_name
     * @return int|null
     */
    private function get_category_id_by_name(string $cat_name): ?int
    {
        // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Taxonomy.RestrictedFunctions.get_term_by
        $term = get_term_by('name', $cat_name, 'product_cat');
        // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return ($term && !is_wp_error($term)) ? (int)$term->term_id : null;
    }
}
