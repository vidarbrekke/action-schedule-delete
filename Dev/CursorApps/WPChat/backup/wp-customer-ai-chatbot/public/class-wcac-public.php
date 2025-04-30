<?php
declare(strict_types=1);

require_once WCAC_PLUGIN_DIR . 'includes/class-wcac-token-counter.php';
require_once WCAC_PLUGIN_DIR . 'includes/class-wcac-chatbot-rules.php';

/**
 * Handles the public-facing functionality of the plugin.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/public
 */

// Compatibility layer for functions that might be undefined in some environments
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string) {
        return strip_tags($string);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        $response = ['success' => false];
        if (isset($data)) {
            $response['data'] = $data;
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        $response = ['success' => true];
        if (isset($data)) {
            $response['data'] = $data;
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        
        public function __construct($code, $message) {
            $this->code = $code;
            $this->message = $message;
        }
        
        public function get_error_code() {
            return $this->code;
        }
        
        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('wp_list_pluck')) {
    function wp_list_pluck($list, $field, $index_key = null) {
        if (!is_array($list)) {
            return array();
        }
        $newlist = array();
        if (!$index_key) {
            foreach ($list as $key => $value) {
                if (is_object($value)) {
                    if (isset($value->{$field})) {
                        $newlist[$key] = $value->{$field};
                    }
                } else {
                    if (isset($value[$field])) {
                        $newlist[$key] = $value[$field];
                    }
                }
            }
            return $newlist;
        }
        foreach ($list as $value) {
            if (is_object($value)) {
                if (isset($value->{$field}) && isset($value->{$index_key})) {
                    $newlist[$value->{$index_key}] = $value->{$field};
                }
            } else {
                if (isset($value[$field]) && isset($value[$index_key])) {
                    $newlist[$value[$index_key]] = $value[$field];
                }
            }
        }
        return $newlist;
    }
}

class Wcac_Public {
    private string $plugin_name;
    private string $version;
    private const MAX_RAG_TOKENS = 1000;
    private const RAG_TOP_N_PRODUCTS = 10;
    private const RAG_INITIAL_FETCH_COUNT = 25;

    public function __construct(string $plugin_name, string $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('init', [$this, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_wcac_send_message', [$this, 'handle_send_message_ajax']);
        add_action('wp_ajax_nopriv_wcac_send_message', [$this, 'handle_send_message_ajax']);
        add_action('wp_ajax_wcac_diagnostics', [$this, 'handle_diagnostics_ajax']);
        add_action('wp_ajax_wcac_debug_nonce', [$this, 'handle_debug_nonce_ajax']);
        add_action('wp_ajax_nopriv_wcac_debug_nonce', [$this, 'handle_debug_nonce_ajax']);
    }

    public function register_shortcode(): void {
        add_shortcode('wcac_chatbot', [$this, 'render_chatbot_shortcode']);
    }

    public function render_chatbot_shortcode($atts): string {
        $atts = shortcode_atts([], $atts, 'wcac_chatbot');
        ob_start();
        include WCAC_PLUGIN_DIR . 'templates/wcac-chat-widget-template.php';
        return ob_get_clean() ?: '';
    }

    public function enqueue_assets(): void {
        if (!is_admin()) {
            wp_enqueue_style(
                $this->plugin_name,
                WCAC_PLUGIN_URL . 'assets/css/wcac-public.css',
                [],
                $this->version
            );

            wp_enqueue_script(
                $this->plugin_name,
                WCAC_PLUGIN_URL . 'assets/js/wcac-public.js',
                ['jquery'],
                $this->version,
                true
            );

            wp_localize_script(
                $this->plugin_name,
                'wcac_params',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wcac_chatbot_nonce'),
                ]
            );
        }
    }

    public function retrieve_relevant_content($message, $conversation_history) {
        global $wpdb;
        $index_table_name = $wpdb->prefix . 'wcac_index';
        $keywords = $this->extract_keywords($message);
        error_log('WCAC DEBUG (Index Search): Extracted keywords: ' . wp_json_encode($keywords));

        if (empty($keywords)) {
            error_log('WCAC DEBUG (Index Search): No keywords extracted, returning empty result.');
            return []; // Return empty array indicating no processing needed
        }

        // --- Step 1: Query the Index Table --- 
        $fetch_limit = 200; // Limit initial DB fetch for performance
        $where_clauses = [];
        foreach ($keywords as $keyword) {
            // Match against title OR content_snippet initially
            $where_clauses[] = $wpdb->prepare("(title LIKE %s OR content_snippet LIKE %s)",
                '%' . $wpdb->esc_like($keyword) . '%',
                '%' . $wpdb->esc_like($keyword) . '%'
            );
        }
        $where_sql = '1=1'; 
        if (!empty($where_clauses)) {
            // Use OR logic for initial broad fetch; scoring will refine relevance
            $where_sql = '(' . implode(' OR ', $where_clauses) . ')'; 
        }

        // Prepare the query to select relevant columns from the index table
        $query = $wpdb->prepare("
            SELECT post_id, post_type, title, content_snippet, url, categories, tags, regular_price, sale_price, on_sale, parent_id
            FROM {$index_table_name}
            WHERE (post_type = 'product' OR post_type = 'product_variation')
            AND {$where_sql}
            LIMIT %d
        ", $fetch_limit);

        error_log('WCAC DEBUG (Index Search): SQL Query: ' . $query);
        $results = $wpdb->get_results($query, ARRAY_A); // Fetch results as associative array
        error_log('WCAC DEBUG (Index Search): Number of DB results from index: ' . count($results));
        
        // Log raw titles for debugging
        $raw_result_titles = [];
        if (!empty($results)) {
            $raw_result_titles = wp_list_pluck($results, 'title');
            error_log('WCAC DEBUG (Index Search): Raw DB Result Titles: ' . wp_json_encode($raw_result_titles));
        } else {
            error_log('WCAC DEBUG (Index Search): No raw DB results found from index.');
             // Directly send no results response if DB query is empty
             wp_send_json_error(['message' => 'Sorry, I could not find any relevant products matching your query in the index. Please try a different search or ask the administrator to re-index the content.']);
             return; // Exit handled by wp_send_json_error
        }
        $debug_titles = $raw_result_titles; 
        
        // Keep the raw titles for debugging output
        $debug_titles = $raw_result_titles; 

        if (empty($results)) {
            error_log('WCAC DEBUG: No DB results for query.');
            return [];
        }

        // --- Step 2: Score Results in PHP --- 
        $scoring_data = []; // Array for sorting 
        $items_by_id = []; // Temporary store for item data during scoring
        $user_query_lc = strtolower($message);

        // Define scoring parameters (simplified, access static properties)
        $keywords = self::extract_keywords($message); // Make sure keywords are extracted
        $user_query_lc = strtolower($message);

        // Remove temporary type keyword list and related logic
        // $type_keywords = ['yarn', 'wool', ...]; // REMOVED
        // $query_type_keywords = array_intersect($keywords, $type_keywords); // REMOVED
        // $query_contains_type = !empty($query_type_keywords); // REMOVED
        // $type_match_boost = 50.0; // REMOVED
        // $type_mismatch_penalty = -200.0; // REMOVED

        // ... (rest of the scoring loop initialization) ...

        foreach ($results as $item_data) { // $results is now an array of arrays
            $item_id = (int)$item_data['post_id'];
            $items_by_id[$item_id] = $item_data; // Store raw data from index
            
            $score = 0;
            $matched_keywords_details = [];
            
            $title_raw = $item_data['title'] ?? '';
            $title_lc = strtolower($title_raw);
            $snippet_lc = strtolower($item_data['content_snippet'] ?? '');
            
            // Decode JSON categories and tags from index
            $categories = json_decode($item_data['categories'] ?? '[]', true);
            $tags = json_decode($item_data['tags'] ?? '[]', true); 
            if (!is_array($categories)) $categories = [];
            if (!is_array($tags)) $tags = [];
            $categories_lc = array_map('strtolower', $categories);
            $tags_lc = array_map('strtolower', $tags);

            $item_on_sale = !empty($item_data['on_sale']); // Check boolean value
            $item_type = $item_data['post_type'];

            // --- ADDED: Apply Product Type Weight with multipliers --- 
            if ($item_type === 'product') {
                // Apply parent product weight directly from Wcac_ChatbotRules
                $score += Wcac_ChatbotRules::$parent_product_weight;
                $matched_keywords_details['type_boost'] = ['parent_product']; // Log boost
            } elseif ($item_type === 'product_variation') {
                $score += Wcac_ChatbotRules::$variation_product_weight;
                $matched_keywords_details['type_boost'] = ['product_variation']; // Log boost
            }
            // --- END Product Type Weight --- 

            // --- Simplified Keyword Scoring Loop ---
            $matched_kw_count = 0;
            foreach ($keywords as $kw) {
                $kw_lc = strtolower($kw);
                if (strlen($kw_lc) <= 2) continue; 

                $keyword_score_contribution = 0;
                $fields_matched_this_kw = [];
                // Use word boundary regex for safer matching
                $pattern = '/' . '\\b' . preg_quote($kw_lc, '/') . '\\b' . '/i'; 

                // Check Title - use Wcac_ChatbotRules value directly
                if (preg_match($pattern, $title_lc)) {
                    $keyword_score_contribution += Wcac_ChatbotRules::$title_match_weight;
                    $fields_matched_this_kw[] = 'title';
                }
                // Check Snippet (using indexed content_snippet) - use rules value directly
                if (preg_match($pattern, $snippet_lc)) {
                    $keyword_score_contribution += Wcac_ChatbotRules::$content_match_weight;
                    $fields_matched_this_kw[] = 'snippet';
                }
                // Check Categories (decoded from index) using rules value directly
                $cat_match_found = false;
                foreach($categories_lc as $cat) {
                    if (preg_match($pattern, $cat)) {
                        $keyword_score_contribution += Wcac_ChatbotRules::$category_match_weight;
                        $fields_matched_this_kw[] = 'category';
                        $cat_match_found = true;
                        break; // Only score once per keyword per category list
                    }
                }
                // Check Tags (decoded from index) using rules value directly
                $tag_match_found = false;
                 foreach($tags_lc as $tag) {
                    if (preg_match($pattern, $tag)) {
                        // Avoid double-counting if already matched in category
                        if (!$cat_match_found) {
                            $keyword_score_contribution += Wcac_ChatbotRules::$tag_match_weight;
                        }
                        $fields_matched_this_kw[] = 'tag';
                        $tag_match_found = true;
                        break; // Only score once per keyword per tag list
                    }
                }

                if ($keyword_score_contribution > 0) {
                    $score += $keyword_score_contribution;
                    // Use array_unique to avoid duplicate field names if matched in both cat & tag
                    $matched_keywords_details[$kw_lc] = array_unique($fields_matched_this_kw); 
                    $matched_kw_count++;
                }
            }
            // --- End Simplified Keyword Scoring Loop ---

            // --- Apply Bonuses / Penalties based on indexed data ---
            // On Sale Bonus - use rules value directly
            if ($item_on_sale) {
                $score += Wcac_ChatbotRules::$on_sale_weight;
                $matched_keywords_details['on_sale'] = ['bonus']; // Log bonus application
            }
            
            // Direct Title Match Bonus (check if full user query is in indexed title)
            if (stripos($title_lc, $user_query_lc) !== false) { // Use stripos for case-insensitive
                $score += Wcac_ChatbotRules::$direct_title_match_bonus;
                $matched_keywords_details['direct_title_match'] = ['bonus'];
            }

            // --- Product-specific boost section REMOVED ---

            error_log('WCAC DEBUG (Index Search): Final Score for ID ' . $item_id . ': ' . $score . '. Matched Keywords: ' . wp_json_encode($matched_keywords_details));

            // Store data for sorting
            $scoring_data[] = ['id' => $item_id, 'score' => $score, 'title' => $title_raw]; // Store title for tie-breaking
        }
        // --- End Scoring Loop ---

        // --- Step 3: Sort and Filter Scored Data --- 
        error_log('WCAC DEBUG (Index Search): Sorting ' . count($scoring_data) . ' items based on score.');
        
        // First sort by score and title
        usort($scoring_data, function($a, $b) { 
            // Primary sort by score descending
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            // Secondary sort by title ascending for tie-breaking
            return strcmp($a['title'], $b['title']);
        });

        // --- Parent-Product Preference Filter ---
        // Give parent products a boost when their scores are close to variations
        // This helps ensure parent products appear before their variations
        if (count($scoring_data) > 1) {
            $parent_products = [];
            $variations = [];
            
            // Separate parents and variations
            foreach ($scoring_data as $key => $item) {
                $item_id = $item['id'];
                $item_post_type = isset($items_by_id[$item_id]['post_type']) ? $items_by_id[$item_id]['post_type'] : '';
                
                if ($item_post_type === 'product') {
                    $parent_products[$item_id] = $key;
                } elseif ($item_post_type === 'product_variation') {
                    $parent_id = isset($items_by_id[$item_id]['parent_id']) ? $items_by_id[$item_id]['parent_id'] : 0;
                    if ($parent_id > 0) {
                        $variations[$item_id] = [
                            'key' => $key,
                            'parent_id' => $parent_id
                        ];
                    }
                }
            }
            
            // For each variation, check if parent exists and if scores are close
            foreach ($variations as $variation_id => $variation_data) {
                $parent_id = $variation_data['parent_id'];
                if (isset($parent_products[$parent_id])) {
                    $parent_key = $parent_products[$parent_id];
                    $variation_key = $variation_data['key'];
                    
                    $parent_score = $scoring_data[$parent_key]['score'];
                    $variation_score = $scoring_data[$variation_key]['score'];
                    
                    // If variation score is higher than parent, but within 20% margin
                    // and the variation ranks higher in the list
                    $parent_preference_margin = 1.2; // 20% margin
                    if ($variation_score > $parent_score && 
                        $variation_score <= $parent_score * $parent_preference_margin && 
                        $variation_key < $parent_key) {
                        
                        // Swap them: move parent ahead of variation by adding a small boost
                        // The +1 ensures the parent will rank higher than the variation
                        $scoring_data[$parent_key]['score'] = $variation_score + 1;
                        
                        error_log('WCAC DEBUG (Index Search): Parent-preference applied - boosted parent product ID ' . 
                                  $parent_id . ' ahead of variation ID ' . $variation_id);
                    }
                }
            }
            
            // Re-sort after applying parent preference
            usort($scoring_data, function($a, $b) { 
                // Primary sort by score descending
                if ($b['score'] !== $a['score']) {
                    return $b['score'] <=> $a['score'];
                }
                // Secondary sort by title ascending for tie-breaking
                return strcmp($a['title'], $b['title']);
            });
        }
        // --- End Parent-Product Preference Filter ---

        // Log top sorted results
        $log_final_sorted = [];
        foreach (array_slice($scoring_data, 0, 5) as $res) { 
            $log_final_sorted[] = 'ID ' . $res['id'] . ' (' . round($res['score'], 2) . ') - ' . $res['title'];
        }
        error_log('WCAC DEBUG (Index Search): Top 5 results after sort: ' . wp_json_encode($log_final_sorted)); 

        // --- Filter by Relative Score Threshold --- 
        $filtered_scoring_data = [];
        if (!empty($scoring_data)) {
            $top_score = $scoring_data[0]['score'];
            
            // Define relative threshold - this should be moved to Wcac_ChatbotRules in the future
            $relative_score_threshold = 0.25; // Default relative threshold (25% of top score)
            $min_absolute_score = Wcac_ChatbotRules::$min_score_threshold; 

            if ($top_score > 0) { 
                $score_cutoff = max($min_absolute_score, $top_score * $relative_score_threshold);
                error_log('WCAC DEBUG (Index Search): Applying score threshold. Top score: ' . round($top_score, 2) . ', Cutoff: ' . round($score_cutoff, 2));
                
                foreach ($scoring_data as $data) {
                    if ($data['score'] >= $score_cutoff) {
                        $filtered_scoring_data[] = $data; 
                    } else {
                        error_log('WCAC DEBUG (Index Search): Filtering out item ID ' . $data['id'] . ' by score threshold (Score: ' . round($data['score'], 2) . ')');
                    }
                }
            } else {
                // If all scores are zero or negative, take top 5 results
                $filtered_scoring_data = array_slice($scoring_data, 0, 5);
                error_log('WCAC DEBUG (Index Search): Top score was zero or negative. Taking top 5 raw sorted items.');
            }
        } else {
             error_log('WCAC DEBUG (Index Search): Scoring data was empty before threshold filter.');
        }
        
        // Limit to max results defined in rules
        $max_results = Wcac_ChatbotRules::$max_results_returned;
        $filtered_scoring_data = array_slice($filtered_scoring_data, 0, $max_results);
        
        error_log('WCAC DEBUG (Index Search): Final items after score threshold and limit: ' . count($filtered_scoring_data));
        // --- End Relative Score Filter ---

        // --- Step 4: Reconstruct Final Product List for Response --- 
        $max_results_returned = Wcac_ChatbotRules::get_max_results_returned(); 
        $final_sorted_items_meta = array_slice($filtered_scoring_data, 0, $max_results_returned);

        // We need the URL and Name (Title) for the final response.
        // We get this from the $items_by_id lookup using the ID from $final_sorted_items_meta.
        $final_top_products = [];
        $final_category_counts = []; // Recalculate based on filtered results
        $final_category_to_url = []; // Store category URL if we can find it

        foreach ($final_sorted_items_meta as $data) {
            $item_id = $data['id'];
            if (isset($items_by_id[$item_id])) {
                $item_index_data = $items_by_id[$item_id]; // Get the full data row from index
                $prod_name = $item_index_data['title'] ?? 'Product Name Unavailable';
                $prod_url = $item_index_data['url'] ?? '#'; // URL is directly from index
                
                // Ensure name and URL are valid before adding
                if (!empty(trim($prod_name)) && !empty($prod_url) && $prod_url !== '#') {
                    $final_top_products[] = ['name' => $prod_name, 'url' => $prod_url];
                } else {
                     error_log('WCAC DEBUG (Index Search): Skipping product ID ' . $item_id . ' due to missing name or URL in index data.');
                     continue; // Skip this item if essential data is missing
                }

                // Recalculate best category from *indexed* categories for context
                $item_categories = json_decode($item_index_data['categories'] ?? '[]', true);
                if (is_array($item_categories)) {
                    foreach ($item_categories as $cat_name) {
                         $cat_name = trim($cat_name);
                         if (!empty($cat_name)) {
                             $final_category_counts[$cat_name] = ($final_category_counts[$cat_name] ?? 0) + 1;
                             // Attempt to get category URL (requires live lookup)
                             if (!isset($final_category_to_url[$cat_name])) { // Only look up once per category name
                                 if (function_exists('get_term_by')) {
                                     $term = get_term_by('name', $cat_name, 'product_cat');
                                     if ($term && function_exists('get_term_link')) {
                                         $link = get_term_link($term);
                                         if (!is_wp_error($link)) {
                                             $final_category_to_url[$cat_name] = (string)$link;
                                         }
                                     }
                                 }
                             }
                         }
                     }
                 }
            } else {
                 error_log('WCAC DEBUG (Index Search): Could not find full index data for filtered ID: ' . $item_id);
            }
        }
        
        $final_best_category_name = null;
        $final_best_category_url = null; 
        if (!empty($final_category_counts)) {
            arsort($final_category_counts);
            $final_best_category_name = array_key_first($final_category_counts);
            // Get the URL we hopefully found during the loop
            $final_best_category_url = $final_category_to_url[$final_best_category_name] ?? null;
        }

        error_log('WCAC DEBUG (Index Search): Final top_items array for response: ' . wp_json_encode(array_map(function($p) { return $p['name']; }, $final_top_products)));
        error_log('WCAC DEBUG (Index Search): Best category found: ' . ($final_best_category_name ?? 'None'));

        // --- Step 5: Construct Final Response --- 
        if (empty($final_top_products)) {
             // Handle case where filtering/validation left no products
             wp_send_json_error(['message' => 'Sorry, I could not find any suitable products matching your query in the index. Please try a different search.']);
             return; 
        }

        // Prepare LLM intro prompt (same as before)
        $intro_prompt = "You are a friendly e-commerce store assistant. The user asked: '" . $message . "'. Respond ONLY with a brief, friendly introductory sentence acknowledging their query. For example: 'Okay, I found these items related to your query:' or 'Certainly, here are some products matching your request:'. Do NOT list any products or categories yourself.";
        
        $llm_intro = '';
        try {
            // Pass conversation history for context
            $intro_response = $this->send_simple_llm_message($intro_prompt, $message, $conversation);
            if (!is_wp_error($intro_response) && is_string($intro_response)) {
                $llm_intro = trim($intro_response) . "\n\n";
            } else {
                $llm_intro = "Here are some products related to your query:\n\n";
                error_log('WCAC (Index Search): LLM intro generation failed. Using default. Error: ' . (is_wp_error($intro_response) ? $intro_response->get_error_message() : 'Non-string response'));
            }
        } catch (Exception $e) {
             $llm_intro = "Here are some products related to your query:\n\n";
             error_log('WCAC (Index Search): Exception during LLM intro generation: ' . $e->getMessage());
        }

        // Build the product list in PHP using data from index
        $response_list = [];
        $loop_index = 0;
        foreach ($final_top_products as $product) {
            $name = $product['name']; // Already validated
            $url = $product['url'];   // Already validated
            $markdown_link = "- [" . esc_html($name) . "](" . esc_url($url) . ")"; 
            
            // Log first few items for debugging
            if ($loop_index < 3) {
                error_log("WCAC DEBUG (Index Search - PHP Link Gen): Index {$loop_index} | Name: {$name} | URL: {$url} | Markdown: {$markdown_link}");
            }
            $response_list[] = $markdown_link;
            $loop_index++;
        }
        
        // Add best category link if name and URL were found
        if ($final_best_category_name && $final_best_category_url) {
             $response_list[] = "\nSee all products in the category: [" . esc_html($final_best_category_name) . "](" . esc_url($final_best_category_url) . ")";
        } elseif ($final_best_category_name) {
             // Fallback if URL lookup failed but we have the name
             $response_list[] = "\nRelated category: " . esc_html($final_best_category_name);
        }

        // Combine LLM intro with PHP list
        $final_response = $llm_intro . implode("\n", $response_list);
        
        // Send the successful response as JSON
         wp_send_json_success(['message' => $final_response, 'debug_raw_titles' => $debug_titles]);
         // Exit is handled by wp_send_json_success

        // Should not be reached
        return [];
    }

    private function is_product_query($message) {
        return Wcac_ChatbotRules::is_product_query($message);
    }

    private function get_site_summary_content(): string {
        $site_context = '';
        $max_len_per_page = 1000; // Limit per page 
        $total_context_char_limit = 8000; // Limit total characters for context (approx 2k tokens)
        $priority_titles = ['about', 'contact', 'faq', 'hours', 'address', 'terms', 'shipping', 'returns']; // Lowercase titles to prioritize

        if (function_exists('get_pages')) {
            $all_pages_raw = get_pages(['post_type' => 'page', 'post_status' => 'publish']);

            if (is_wp_error($all_pages_raw)) {
                error_log('WCAC: Error fetching pages: ' . $all_pages_raw->get_error_message());
                return ''; 
            }

            if (empty($all_pages_raw)) {
                error_log('WCAC: No published pages found to build general context.');
                return '';
            }

            error_log('WCAC: Found ' . count($all_pages_raw) . ' published pages for general context.');

            // Separate into priority and other pages
            $priority_pages = [];
            $other_pages = [];
            foreach ($all_pages_raw as $page) {
                if ($page && !empty($page->post_title)) {
                    $title_lc = strtolower($page->post_title);
                    $is_priority = false;
                    foreach ($priority_titles as $p_title) {
                        if (strpos($title_lc, $p_title) !== false) {
                            $priority_pages[] = $page;
                            $is_priority = true;
                            break;
                        }
                    }
                    if (!$is_priority) {
                        $other_pages[] = $page;
                    }
                } else {
                     error_log("WCAC: Skipped a fetched page object during sorting due to missing title.");
                }
            }
            
            // Combine lists, priority first
            $all_pages_sorted = array_merge($priority_pages, $other_pages);

            foreach ($all_pages_sorted as $page) {
                 // Check if adding this page would exceed the total limit
                 // Estimate added length: title + content + formatting chars
                 $estimated_added_length = 0;
                 if ($page && !empty($page->post_content) && !empty($page->post_title)) {
                     $title = $page->post_title;
                     // Process shortcodes and apply content filters before stripping tags
                     $processed_content = function_exists('apply_filters') ? apply_filters('the_content', $page->post_content) : $page->post_content;
                     $content_raw = wp_strip_all_tags($processed_content);
                     $truncated_content = $content_raw;
                     
                     // Truncate individual page content if needed
                     if (strlen($truncated_content) > $max_len_per_page) {
                         $truncated_content = substr($truncated_content, 0, $max_len_per_page);
                         $last_space = strrpos($truncated_content, ' ');
                         if ($last_space !== false) {
                             $truncated_content = substr($truncated_content, 0, $last_space);
                         }
                         $truncated_content .= '...';
                     }

                     // Calculate length to be added (including formatting)
                     if (!empty(trim($truncated_content))) {
                        $estimated_added_length = strlen("\n\n--- From page: {$title} ---\n") + strlen($truncated_content);
                     }
                 }
                 
                 // Add content only if it fits within the total limit and has content
                 if ($estimated_added_length > 0 && (strlen($site_context) + $estimated_added_length) <= $total_context_char_limit) {
                     $site_context .= "\n\n--- From page: {$title} ---\n" . $truncated_content;
                     error_log("WCAC: Added content from page '{$title}' (ID: {$page->ID}) to general context. Current total length: " . strlen($site_context));
                 } elseif ($estimated_added_length > 0) {
                     error_log("WCAC: Skipped page '{$title}' (ID: {$page->ID}) because adding it would exceed total context limit ({$total_context_char_limit} chars).");
                     // Optional: break here if you want to stop checking pages once limit is hit
                     // break;
                 } else {
                      // Log why it might be skipped if not due to limit (e.g., empty after stripping tags)
                      if ($page && !empty($page->post_title) && empty(trim($content_raw ?? ''))) {
                          error_log("WCAC: Skipped page '{$page->post_title}' (ID: {$page->ID}) due to empty content after stripping tags.");
                      }
                 }
            }
        } else {
            error_log('WCAC: get_pages() function does not exist.');
            return '';
        }

        if (empty($site_context)) {
            error_log('WCAC: Could not find any suitable page content for general context within limits.');
        } else {
            error_log('WCAC: Final total general context length: ' . strlen($site_context));
        }

        return $site_context;
    }

    private function get_main_product_categories(): array {
        $main_categories = [];
        if (function_exists('get_terms')) {
            $terms = get_terms(['taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true]);
            if (!is_wp_error($terms) && is_array($terms)) {
                foreach ($terms as $term) {
                    $link = function_exists('get_term_link') ? get_term_link($term) : '';
                    if (!is_wp_error($link) && $link) {
                        $main_categories[] = ['name' => $term->name, 'url' => $link];
                    }
                }
            }
        }
        return $main_categories;
    }

    private function is_broad_query($message): bool {
        $message_lc = trim(strtolower($message));
        // Define very generic, exact broad queries
        $exact_broad_queries = [
            'products', 
            'what products',
            'what do you sell',
            'what you sell',
            'what do you offer',
            'what you offer',
            'what products do you have',
            'what products you have',
            'what do you have',
            'what you have',
            'show products',
            'list products',
            'show all products',
            'list all products',
            'what does your store carry',
            'what your store carry' // Added this variation
        ];

        // Check for exact matches or matches ending with a question mark
        foreach ($exact_broad_queries as $query) {
            if ($message_lc === $query || $message_lc === $query . '?') {
                return true;
            }
        }
        
        // Add a simple length check as a fallback - very short queries are often broad
        if (str_word_count($message_lc) <= 2 && ($message_lc === 'offer' || $message_lc === 'products' || $message_lc === 'sell' || $message_lc === 'have')) {
            return true;
        }

        return false; // Default to specific query path
    }

    public function handle_send_message_ajax() {
        check_ajax_referer('wcac_chatbot_nonce', 'nonce');
        $user_message = sanitize_text_field($_POST['message'] ?? '');
        error_log('WCAC DEBUG: User query: ' . $user_message);
        $debug_titles = []; // Initialize debug titles
        if (empty($user_message)) {
            wp_send_json_error(['message' => 'Empty message received']);
            return;
        }
        $conversation = isset($_POST['conversation']) ? json_decode(stripslashes($_POST['conversation']), true) : [];
        if (!is_array($conversation)) {
            $conversation = [];
        }

        // --- Check for Follow-up Intent --- 
        $is_follow_up = false;
        $referenced_product_data = null;
        $follow_up_keywords = ['more about', 'tell me about', 'details on', 'what about'];
        $ordinal_references = ['first' => 0, 'second' => 1, 'third' => 2, 'fourth' => 3, 'fifth' => 4, 'last' => -1]; // Map ordinals to indices
        $user_message_lc = strtolower($user_message);

        foreach ($follow_up_keywords as $phrase) {
            if (strpos($user_message_lc, $phrase) !== false) {
                // Potential follow-up detected. Now try to find a reference.
                if (!empty($conversation)) {
                    // Get the last assistant message
                    $last_assistant_message = null;
                    for ($i = count($conversation) - 1; $i >= 0; $i--) {
                        if ($conversation[$i]['role'] === 'assistant') {
                            $last_assistant_message = $conversation[$i]['content'];
                            break;
                        }
                    }

                    if ($last_assistant_message) {
                        // Attempt to extract markdown links from the last message
                        preg_match_all('/- \[(.*?)\]\((.*?)\)/m', $last_assistant_message, $matches, PREG_SET_ORDER);

                        if (!empty($matches)) {
                            $product_links = $matches; // Array of [full_match, name, url]

                            // Check for ordinal references ("first", "second", "last")
                            foreach ($ordinal_references as $ordinal => $index) {
                                if (strpos($user_message_lc, $ordinal) !== false) {
                                    $actual_index = ($index === -1) ? count($product_links) - 1 : $index;
                                    if (isset($product_links[$actual_index])) {
                                        $referenced_product_data = [
                                            'name' => $product_links[$actual_index][1],
                                            'url' => $product_links[$actual_index][2]
                                        ];
                                        $is_follow_up = true;
                                        error_log("WCAC DEBUG: Follow-up detected (ordinal '{$ordinal}'). Referenced Product: " . json_encode($referenced_product_data));
                                        break 2; // Exit both loops
                                    }
                                }
                            }

                            // TODO: Add check for named references (matching product name from list)
                        }
                    }
                }
                break; // Exit follow-up keyword loop once one is found
            }
        }
        // --- End Follow-up Intent Check ---

        // --- Handle Follow-up Logic ---
        if ($is_follow_up && $referenced_product_data) {
            error_log("WCAC DEBUG: Handling follow-up request for: " . $referenced_product_data['name']);

            // Extract Product ID from URL (assuming standard WordPress permalink structure)
            $product_id = 0;
            if (function_exists('url_to_postid')) {
                 $product_id = url_to_postid($referenced_product_data['url']);
            }
            // Fallback/Alternative: Extract slug from URL and use get_page_by_path if url_to_postid fails or isn't available
            if (!$product_id) {
                 $path = parse_url($referenced_product_data['url'], PHP_URL_PATH);
                 $path_parts = explode('/', trim($path, '/'));
                 $slug = end($path_parts);
                 if ($slug && function_exists('get_page_by_path')) {
                     $post = get_page_by_path($slug, OBJECT, 'product');
                     if ($post) {
                         $product_id = $post->ID;
                     }
                 }
            }

            if ($product_id) {
                $product_post = function_exists('get_post') ? get_post($product_id) : null;
                if ($product_post && $product_post->post_type === 'product') {
                    $product_description = wp_strip_all_tags($product_post->post_content);
                    $product_name = $referenced_product_data['name']; // Use name from context

                    // More explicit prompt for follow-up summarization
                    $summary_prompt = "You are a helpful e-commerce store assistant. The user previously saw a list of products including '{$product_name}'. They now asked: '{$user_message}'.\n\nYour task is to answer the user's question or provide more details based *EXCLUSIVELY* on the following product description. \n\n**CRITICAL INSTRUCTIONS:**\n1. Use ONLY the text provided below under 'Product Description'.\n2. Do NOT list any other products.\n3. Do NOT include any URLs unless the user specifically asks for a link or the description itself contains one.\n4. Do NOT perform a new product search.\n5. Provide a concise summary or answer focused on the user's query.\n\nProduct Name: {$product_name}\nProduct Description: {$product_description}";

                    error_log("WCAC DEBUG: Sending follow-up summary prompt to LLM for product ID: {$product_id}");
                    $llm_response = $this->send_simple_llm_message($summary_prompt, $user_message, $conversation);

                    if (!is_wp_error($llm_response) && is_string($llm_response)) {
                         wp_send_json_success(['message' => $llm_response]);
                    } else {
                         $error_message = is_wp_error($llm_response) ? $llm_response->get_error_message() : 'LLM did not return a valid string response.';
                         error_log("WCAC ERROR: Failed to get LLM summary for follow-up: " . $error_message);
                         // Fallback message - provide link directly
                         wp_send_json_success(['message' => "I found the product '{$product_name}'. You can find more details here: [{$product_name}]({$referenced_product_data['url']})"]);
                    }
                } else {
                    error_log("WCAC ERROR: Could not retrieve valid product post for follow-up ID: {$product_id}");
                    wp_send_json_error(['message' => "Sorry, I couldn't retrieve the details for that product right now."]);
                }
            } else {
                error_log("WCAC ERROR: Could not determine product ID from URL for follow-up: " . $referenced_product_data['url']);
                // Fallback - maybe try standard search?
                // For now, send error
                 wp_send_json_error(['message' => "Sorry, I had trouble identifying the specific product from the link."]);
            }
            return; // Exit after handling follow-up
        }
        // --- End Handle Follow-up Logic ---

        // --- Handle Broad Query with Category Summary ---
        if ($this->is_broad_query($user_message)) {
            error_log('WCAC: Detected broad query, fetching main categories and summary content.');
            $main_categories = $this->get_main_product_categories();
            $site_summary = $this->get_site_summary_content();

            if (empty($main_categories) && empty($site_summary)) {
                error_log('WCAC: No main categories or summary content found for broad query. Falling back to standard search.');
                // Fall back to standard product search if nothing found
            } else {
                $category_list_str = '';
                if (!empty($main_categories)) {
                    $category_links = [];
                    foreach ($main_categories as $cat) {
                        $category_links[] = '[' . (string)$cat['name'] . '](' . (string)$cat['url'] . ')'; // Cast
                    }
                    $category_list_str = "\n\nMain Categories:\n* " . implode("\n* ", $category_links);
                }
                
                $summary_prompt = "You are a helpful e-commerce store assistant. The user asked a broad question: '" . $user_message . "'. Briefly introduce the store using the provided summary, list the main product categories available, and encourage the user to ask about a specific category for more details."
                                . $site_summary 
                                . $category_list_str;

                error_log('WCAC: Sending category summary prompt to LLM.');
                try {
                    // Pass the main categories for post-processing
                    $response = $this->send_simple_llm_message($summary_prompt, $user_message, $conversation, $main_categories);
                    
                    // Post-processing is now done inside send_simple_llm_message

                    if (is_wp_error($response)) {
                        error_log('WCAC: Category summary LLM call failed: ' . $response->get_error_message());
                        wp_send_json_error(['message' => 'Sorry, I had trouble summarizing our categories. Please ask about a specific product.']);
                        return;
                    }
                    if (!is_string($response)) {
                        error_log('WCAC: Category summary LLM returned non-string: ' . print_r($response, true));
                        wp_send_json_error(['message' => 'Unexpected response type when summarizing categories.']);
                        return;
                    }
                    wp_send_json_success(['message' => $response, 'debug_raw_titles' => $debug_titles]);
                    return; // Exit after handling broad query
                } catch (Exception $e) {
                    error_log('WCAC: Exception during category summary: ' . $e->getMessage());
                    wp_send_json_error(['message' => 'Error processing category summary request.']);
                    return; // Exit after handling broad query
                }
            }
        }

        // --- Check if it's likely NOT a product query --- 
        // Use the existing helper function for now
        if (!$this->is_product_query($user_message)) {
            error_log('WCAC: Query identified as likely non-product/general question.');
            $site_summary = $this->get_site_summary_content(); // Get general store info

            $general_prompt = "You are a helpful e-commerce store assistant. The user asked a general question: '{$user_message}'. Answer the question based on your general knowledge or the provided store information. Do NOT search for or list products unless the question explicitly asks for them.\n\nStore Information:{$site_summary}";

            error_log('WCAC: Sending general query prompt to LLM.');
            try {
                // Pass conversation history for context even in general questions
                $response = $this->send_simple_llm_message($general_prompt, $user_message, $conversation);

                if (is_wp_error($response)) {
                    error_log('WCAC: General query LLM call failed: ' . $response->get_error_message());
                    wp_send_json_error(['message' => 'Sorry, I had trouble answering that question. Can you please rephrase?']);
                } elseif (is_string($response)) {
                    wp_send_json_success(['message' => $response]);
                } else {
                     error_log('WCAC: General query LLM returned non-string: ' . print_r($response, true));
                     wp_send_json_error(['message' => 'Unexpected response type when answering the question.']);
                }
            } catch (Exception $e) {
                 error_log('WCAC: Exception during general query handling: ' . $e->getMessage());
                 wp_send_json_error(['message' => 'Error processing your general question.']);
            }
            return; // Exit after handling general query
        }
        // --- End General Query Handling ---

        // --- Standard Product Search & Optional Refinement ---
        // This block is now only reached if it wasn't a follow-up, broad query, or identified general question.
        error_log('WCAC DEBUG: Query identified as product-related. Performing standard product search for query: ' . $user_message);
        // Pass conversation history to the retrieval function - FIXED
        $retrieval_result = $this->retrieve_relevant_content($user_message, $conversation);
        // NOTE: retrieve_relevant_content now handles sending the JSON response directly if results are found.
        // It returns an empty array or exits via wp_send_json_* if no products or an error occurs.
        // The code below this point might only be reached if retrieve_relevant_content returns [] initially, 
        // prompting the refinement logic. Let's review if this section is still necessary given retrieve_relevant_content now sends the response.
        
        // TODO: Review if the following block (lines ~908-1083) is still necessary given retrieve_relevant_content now sends the response.
        // It seems designed for the older flow where retrieve_relevant_content returned data to be processed here.
        // Keeping it for now, but it might be redundant or cause issues if retrieve_relevant_content already sent a response.

        $relevant_content = $retrieval_result['top_items'] ?? []; // This key might not exist if retrieve_relevant_content sent JSON directly
        $debug_titles = $retrieval_result['raw_titles'] ?? []; // Capture raw titles for debugging
        error_log('WCAC DEBUG: Standard search returned ' . count($relevant_content) . ' products from raw count of ' . count($debug_titles));
        $context_strings = [];
        $keywords = $this->extract_keywords($user_message);
        $category_counts = [];
        $category_to_term = [];
        $top_products = [];
        foreach ($relevant_content as $item) {
            if (function_exists('wp_get_post_terms')) {
                $cat_terms = wp_get_post_terms($item['id'], 'product_cat', ['fields' => 'all']);
                if (is_array($cat_terms)) {
                    foreach ($cat_terms as $cat) {
                        $cat_name = $cat->name;
                        $category_counts[$cat_name] = ($category_counts[$cat_name] ?? 0) + 1;
                        $category_to_term[$cat_name] = $cat;
                    }
                }
            }
            $prod_name = isset($item['title']) ? wp_strip_all_tags($item['title']) : '';
            $prod_url = '';
            if (function_exists('get_permalink')) {
                $prod_url = get_permalink($item['id']);
            }
            if ($prod_name && $prod_url) {
                $top_products[] = ['name' => $prod_name, 'url' => $prod_url];
            }
        }
        $best_category = null;
        $best_category_url = null;
        if (!empty($category_counts)) {
            arsort($category_counts);
            $best_category_name = array_key_first($category_counts);
            $best_category_term = $category_to_term[$best_category_name] ?? null;
            if ($best_category_term && function_exists('get_term_link')) {
                $best_category_url = get_term_link($best_category_term);
                $best_category = $best_category_name;
            }
        }
        foreach ($relevant_content as $item) {
            $context = $this->format_context_item($item);
            $matches = [];
            $title = strtolower($item['title']);
            $content = strtolower($item['content_snippet']);
            foreach ($keywords as $kw) {
                if (strpos($title, $kw) !== false || strpos($content, $kw) !== false) {
                    $matches[] = $kw;
                }
            }
            if (!empty($matches)) {
                $context .= "\nRelevant to your query: " . implode(', ', $matches);
            }
            $context_strings[] = $context;
        }
        // --- Self-refining fallback ---
        if (empty($relevant_content)) {
            error_log('WCAC: No products found, asking LLM for a refined search term.');
            $refine_prompt = "The user asked: '" . $user_message . "'. No products were found. Suggest a more specific search term or product category that would help find relevant products. Respond with only the search term or category, nothing else.";
            $refine_response = $this->send_simple_llm_message($refine_prompt, $user_message, $conversation);
            if (is_wp_error($refine_response)) {
                error_log('WCAC: LLM refine step error: ' . $refine_response->get_error_message());
                wp_send_json_error(['message' => 'Sorry, I could not find any products. Please try a more specific query.']);
                return;
            }
            $refined_term = trim(strip_tags($refine_response));
            error_log('WCAC: LLM suggested refined search term: ' . $refined_term);
            // Try searching again with the refined term
            $retrieval_result = $this->retrieve_relevant_content($refined_term, $conversation);
            $relevant_content = $retrieval_result['top_items'] ?? [];
            $debug_titles = $retrieval_result['raw_titles'] ?? []; // Update debug titles after refinement too
            error_log('WCAC DEBUG: Refined search returned ' . count($relevant_content) . ' products from raw count of ' . count($debug_titles));
            $context_strings = [];
            $keywords = $this->extract_keywords($refined_term);
            $category_counts = [];
            $category_to_term = [];
            $top_products = [];
            foreach ($relevant_content as $item) {
                if (function_exists('wp_get_post_terms')) {
                    $cat_terms = wp_get_post_terms($item['id'], 'product_cat', ['fields' => 'all']);
                    if (is_array($cat_terms)) {
                        foreach ($cat_terms as $cat) {
                            $cat_name = (string)$cat->name; // Cast name
                            $category_counts[$cat_name] = ($category_counts[$cat_name] ?? 0) + 1;
                            $category_to_term[$cat_name] = $cat;
                        }
                    }
                }
                $prod_name = isset($item['title']) ? wp_strip_all_tags((string)$item['title']) : ''; // Cast title
                $prod_url = '';
                if (function_exists('get_permalink')) {
                    $prod_url = (string)get_permalink($item['id']); // Cast URL
                }
                if ($prod_name && $prod_url) {
                    $top_products[] = ['name' => $prod_name, 'url' => $prod_url];
                }
            }
            $best_category = null;
            $best_category_url = null;
            if (!empty($category_counts)) {
                arsort($category_counts);
                $best_category_name = array_key_first($category_counts);
                $best_category_term = $category_to_term[$best_category_name] ?? null;
                if ($best_category_term && function_exists('get_term_link')) {
                    $best_category_url = (string)get_term_link($best_category_term); // Cast URL
                    $best_category = (string)$best_category_name; // Cast name
                }
            }
            foreach ($relevant_content as $item) {
                $context = $this->format_context_item($item);
                $matches = [];
                $title = strtolower((string)$item['title']); // Cast title
                $content = strtolower((string)$item['content_snippet']); // Cast content
                foreach ($keywords as $kw) {
                    if (strpos($title, (string)$kw) !== false || strpos($content, (string)$kw) !== false) { // Cast keyword
                        $matches[] = (string)$kw; // Cast keyword
                    }
                }
                if (!empty($matches)) {
                    $context .= "\nRelevant to your query: " . implode(', ', $matches);
                }
                $context_strings[] = $context;
            }
            if (empty($relevant_content)) {
                error_log('WCAC: No products found after LLM refinement.');
                wp_send_json_error(['message' => 'Sorry, I could not find any products even after refining the search. Please try a different query.']);
                return;
            }
            // Update the user message for the final LLM call
            $user_message = $refined_term;
        }
        
        // --- PHP Constructs the Response List ---
        // Limit the number of products shown in the response
        $products_to_display = array_slice($top_products, 0, 10);
        
        // Prepare a minimal prompt for the LLM (just for an intro sentence)
        $intro_prompt = "You are a friendly e-commerce store assistant. The user asked: '" . $user_message . "'. Respond ONLY with a brief, friendly introductory sentence acknowledging their query. For example: 'Okay, I found these items related to your query:' or 'Certainly, here are some products matching your request:'. Do NOT list any products or categories yourself.";
        
        $llm_intro = '';
        try {
            // Call LLM for intro ONLY. No items_to_link needed here.
            $intro_response = $this->send_simple_llm_message($intro_prompt, $user_message, $conversation);
            if (!is_wp_error($intro_response) && is_string($intro_response)) {
                $llm_intro = trim($intro_response) . "\n\n"; // Add line breaks after intro
            } else {
                // Fallback intro if LLM fails
                $llm_intro = "Here are some products related to your query:\n\n";
                error_log('WCAC: LLM intro generation failed. Using default. Error: ' . (is_wp_error($intro_response) ? $intro_response->get_error_message() : 'Non-string response'));
            }
        } catch (Exception $e) {
             $llm_intro = "Here are some products related to your query:\n\n";
             error_log('WCAC: Exception during LLM intro generation: ' . $e->getMessage());
        }

        // Build the product list in PHP
        $response_list = [];
        if (!empty($products_to_display)) {
            $loop_index = 0;
            foreach ($products_to_display as $product) {
                $name = $product['name'] ?? 'Product Name Unavailable';
                $url = $product['url'] ?? '#';
                // Optionally fetch a short description here if needed
                $markdown_link = "- [" . esc_html($name) . "](" . esc_url($url) . ")"; 
                
                // Log details for the first 3 items
                if ($loop_index < 3) {
                    error_log("WCAC DEBUG (PHP Link Gen): Index {$loop_index} | Name: {$name} | URL: {$url} | Markdown: {$markdown_link}");
                }
                
                $response_list[] = $markdown_link;
                $loop_index++;
            }
        } else {
            // Handle case where $top_products was empty (already handled by refine logic, but as safety)
            wp_send_json_error(['message' => 'Sorry, I could not find any relevant products.']);
            return;
        }
        
        // Add best category link if available
        if ($best_category && $best_category_url) {
             $response_list[] = "\nSee all products in the category: [" . esc_html($best_category) . "](" . esc_url($best_category_url) . ")";
        }

        // Combine LLM intro with PHP list
        $final_response = $llm_intro . implode("\n", $response_list);
        wp_send_json_success(['message' => $final_response, 'debug_raw_titles' => $debug_titles]);
    }

    public function handle_diagnostics_ajax() {
        check_ajax_referer('wcac_chatbot_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }
        
        $diagnostic_info = $this->get_diagnostic_info();
        wp_send_json_success($diagnostic_info);
    }

    public function handle_debug_nonce_ajax() {
        check_ajax_referer('wcac_chatbot_nonce', 'nonce');
        
        wp_send_json_success([
            'verification_result' => 'valid',
            'new_nonce' => wp_create_nonce('wcac_chatbot_nonce')
        ]);
    }

    private function test_api_connectivity() {
        $api_settings = get_option('wcac_settings', []);
        $api_key = $api_settings['wcac_api_key'] ?? '';
        $api_model = $api_settings['wcac_model'] ?? 'gpt-3.5-turbo';
        $api_url = $api_settings['wcac_api_url'] ?? 'https://api.openai.com/v1/chat/completions';

        if (empty($api_key)) {
            return new WP_Error('api_key_missing', 'API key is not configured');
        }

        $test_message = [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Test connection']
        ];

        try {
            $response = $this->send_to_llm_api($test_message, $api_settings, $api_key, $api_model, $api_url);
            return is_wp_error($response) ? $response : true;
        } catch (Exception $e) {
            return new WP_Error('api_test_failed', $e->getMessage());
        }
    }

    private function build_system_prompt($context_strings, $user_query = '', $matched_keywords = [], $best_category = null, $best_category_url = null) {
        $options = get_option('wcac_settings', []);
        $site_prompt = isset($options['wcac_site_prompt']) ? trim($options['wcac_site_prompt']) : '';
        $base_prompt = "You are a helpful e-commerce store assistant. ";
        if (!empty($site_prompt)) {
            $base_prompt .= "\n" . $site_prompt . "\n";
        }
        if (empty($context_strings)) {
            return $base_prompt . "I don't have any specific product information to share at the moment. " .
                   "I can help with general questions or you can try asking about specific products.";
        }
        $context = implode("\n\n", $context_strings);
        $category_line = '';
        if ($best_category && $best_category_url) {
            $category_line = "\nThe best-matching category for your query is: {$best_category}\n";
        }
        $prompt = $base_prompt .
            "The user asked: '" . $user_query . "'.\n" .
            "The following products matched the query or its synonyms.\n" .
            "Please answer the user's question in a friendly and concise manner. Greet the user if appropriate. List only the most relevant product(s) for the user's question using the product names from the information below. If there is no exact match, suggest the closest product(s) with a brief explanation. Avoid lengthy commentary about why a product is a match.\n" .
            $category_line .
            "If the user asks for the full selection, or if you mention a group of products, mention the most relevant category name provided above.\n\n" .
            "Use ONLY the following product information to answer the user's question. You MUST list the specific, relevant product names found below. Do NOT just say you have products, list the actual product names.\n\nProduct Information:\n" .
            $context;
        return $prompt;
    }

    private function format_context_item($item) {
        $title = wp_strip_all_tags($item['title']);
        $content = wp_strip_all_tags($item['content_snippet']);
        $url = '';
        if (function_exists('get_permalink')) {
            $url = get_permalink($item['id']);
        }
        // Get product price if WooCommerce is active
        $price_info = '';
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($item['id']);
            if ($product) {
                $price_info = " - Price: " . $product->get_price_html();
                $price_info = wp_strip_all_tags($price_info);
            }
        }
        // Truncate content if too long while preserving whole words
        $max_content_length = 200;
        if (strlen($content) > $max_content_length) {
            $content = substr($content, 0, $max_content_length);
            $content = substr($content, 0, strrpos($content, ' ')) . '...';
        }
        $context = "Product: {$title}{$price_info}\nDescription: {$content}";
        if (!empty($url)) {
            $context .= "\nURL: {$url}";
        }
        return $context;
    }

    /**
     * Sends a message to the LLM API and handles basic response parsing.
     *
     * @param string $system_prompt The system prompt to send.
     * @param string $user_message The current user message.
     * @param array $conversation The conversation history.
     * @param array $items_to_link Optional array of items ( ['name' => ..., 'url' => ...] ) to ensure are linked in the response.
     * @return string|WP_Error The LLM response content or a WP_Error on failure.
     */
    public function send_simple_llm_message($system_prompt, $user_message, $conversation = array(), $items_to_link = []) {
        $api_settings = get_option('wcac_settings', []);
        $api_key = $api_settings['wcac_api_key'] ?? '';
        $api_model = $api_settings['wcac_model'] ?? 'gpt-3.5-turbo';
        $api_url = $api_settings['wcac_api_url'] ?? 'https://openrouter.ai/api/v1/chat/completions';

        if (empty($api_key)) {
            error_log('WCAC: API key is not configured');
            return new WP_Error('api_key_missing', 'API key is not configured');
        }

        // Initialize messages array with system prompt
        $messages = [
            ['role' => 'system', 'content' => $system_prompt]
        ];

        // Add conversation history (user/assistant turns)
        if (is_array($conversation) && !empty($conversation)) {
            foreach ($conversation as $turn) {
                if (isset($turn['role']) && isset($turn['content'])) {
                    $messages[] = [
                        'role' => $turn['role'],
                        'content' => $turn['content']
                    ];
                }
            }
        }

        // Add the new user message (if not already present as last turn)
        if (empty($messages) || $messages[count($messages)-1]['role'] !== 'user' || $messages[count($messages)-1]['content'] !== $user_message) {
            $messages[] = ['role' => 'user', 'content' => $user_message];
        }

        $max_conversation_tokens = 2000;
        $current_tokens = $this->calculate_tokens($system_prompt);
        
        try {
            $response = $this->send_to_llm_api($messages, $api_settings, $api_key, $api_model, $api_url);
            if (is_wp_error($response)) {
                error_log('WCAC: API request failed - ' . $response->get_error_message());
                return $response;
            }
            
            // Parse the response
            $response_data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('WCAC: Failed to parse API response - ' . json_last_error_msg() . ' | Raw Response: ' . $response);
                return new WP_Error('json_parse_error', 'Failed to parse API response');
            }
            
            if (!isset($response_data['choices'][0]['message']['content'])) {
                error_log('WCAC: Invalid API response format - ' . $response);
                return new WP_Error('invalid_response', 'Invalid response format from API');
            }
            $content = $response_data['choices'][0]['message']['content'];
            
            // --- Consolidated Post-processing for links ---
            error_log('WCAC DEBUG: Raw LLM response before linking: ' . $content); // Log raw response
            error_log('WCAC DEBUG: Items to link: ' . wp_json_encode($items_to_link)); // Log items to link

            if (!empty($items_to_link)) {
                foreach ($items_to_link as $item) {
                    $name = isset($item['name']) ? (string)$item['name'] : null;
                    $url = isset($item['url']) ? (string)$item['url'] : null;
                    
                    if ($name && $url) {
                        // Fix potential malformed links first (common LLM errors)
                        $content = preg_replace('/\[\[' . preg_quote($name, '/') . '\]\]\(([^)]+)\)/', '[' . $name . ']($1)', $content);
                        $content = preg_replace('/\[' . preg_quote($name, '/') . '\]\]\(([^)]+)\)/', '[' . $name . ']($1)', $content);
                        $content = preg_replace('/\[\[' . preg_quote($name, '/') . '\]\(([^)]+)\)/', '[' . $name . ']($1)', $content);
                        
                        // Add link if name is not already linked
                        // Original pattern: '/(?<!\[)' . preg_quote($name, '/') . '(?! \]\[^\)]*\))/ui';
                        // Enhanced pattern with word boundaries:
                        $pattern = '/\b(?<!\[)' . preg_quote($name, '/') . '(?!\s?\[[^\)]*\))\b/ui';
                        $replacement = '[' . $name . '](' . $url . ')';
                        // Check if the name already exists as a link before attempting replacement
                        if (!preg_match('/\[' . preg_quote($name, '/') . '\]\([^)]*\)/ui', $content)) { 
                            $content = preg_replace($pattern, $replacement, $content, 1); 
                        }
                        
                        // Remove raw URLs for the item if they appear outside markdown links
                        $content = preg_replace('/(?<!\]\()' . preg_quote($url, '/') . '(?!\))/i', '', $content);
                    }
                }
            }
            // --- End consolidated post-processing ---

            error_log('WCAC DEBUG: LLM response after linking: ' . $content); // Log response after linking

            return $content;
        } catch (Exception $e) {
            error_log('WCAC: Exception during API request - ' . $e->getMessage());
            return new WP_Error('api_request_failed', $e->getMessage());
        }
    }

    private function calculate_tokens($text) {
        // Rough estimation: 1 token ≈ 4 characters
        return (int) (strlen($text) / 4);
    }

    private function extract_keywords($message) {
        $stop_words = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
            'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
            'to', 'was', 'were', 'will', 'with', 'the', 'this', 'but', 'they',
            'have', 'had', 'what', 'when', 'where', 'who', 'which', 'why', 'how',
            'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some',
            'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than',
            'too', 'very', 'can', 'do', 'if', 'i', 'you', 'your', 'about',
            'above', 'after', 'again', 'against', 'am', 'could', 'did', 'does',
            'doing', 'down', 'during', 'before', 'being', 'below', 'between',
            'further', 'here', 'herself', 'himself', 'into', 'myself', 'once',
            'or', 'ourselves', 'out', 'over', 'should', 'these', 'those',
            'through', 'under', 'until', 'up', 'while', 'would', 'there',
            'their', 'then', 'them'
        ];

        // Convert message to lowercase and split into words
        $words = preg_split('/\W+/', strtolower($message), -1, PREG_SPLIT_NO_EMPTY);
        // Remove stop words and short words
        $keywords = array_filter($words, function($word) use ($stop_words) {
            return !in_array($word, $stop_words) && strlen($word) > 2;
        });
        $keywords = array_unique(array_values($keywords));

        // Synonym expansion from admin settings
        $options = get_option('wcac_settings', []);
        $syn_map_raw = $options['wcac_synonym_map'] ?? '';
        $synonym_groups = [];
        if (!empty($syn_map_raw)) {
            $lines = preg_split('/\r?\n/', $syn_map_raw);
            foreach ($lines as $line) {
                $group = array_filter(array_map('trim', explode(',', strtolower($line))));
                if (count($group) > 1) {
                    $synonym_groups[] = $group;
                }
            }
        }
        // Build a map for quick lookup
        $synonym_lookup = [];
        foreach ($synonym_groups as $group) {
            foreach ($group as $word) {
                $synonym_lookup[$word] = $group;
            }
        }
        // Expand only for words present in the query
        $expanded = [];
        foreach ($keywords as $kw) {
            if (isset($synonym_lookup[$kw])) {
                $expanded = array_merge($expanded, $synonym_lookup[$kw]);
            } else {
                $expanded[] = $kw;
            }
        }
        $keywords = array_unique($expanded);
        error_log('WCAC DEBUG: Extracted keywords (with synonyms): ' . wp_json_encode($keywords));
        return $keywords;
    }

    private function send_to_llm_api($messages, $api_settings, string $api_key, string $api_model, string $api_url) {
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ];

        $data = [
            'model' => $api_model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];
        // Add individual admin UI parameters if set
        if (isset($api_settings['wcac_temperature']) && $api_settings['wcac_temperature'] !== '') {
            $data['temperature'] = floatval($api_settings['wcac_temperature']);
        }
        if (isset($api_settings['wcac_top_p']) && $api_settings['wcac_top_p'] !== '') {
            $data['top_p'] = floatval($api_settings['wcac_top_p']);
        }
        if (isset($api_settings['wcac_max_tokens']) && $api_settings['wcac_max_tokens'] !== '') {
            $data['max_tokens'] = intval($api_settings['wcac_max_tokens']);
        }
        if ($data['max_tokens'] > 2048) {
            $data['max_tokens'] = 2048;
        }
        if (isset($api_settings['wcac_frequency_penalty']) && $api_settings['wcac_frequency_penalty'] !== '') {
            $data['frequency_penalty'] = floatval($api_settings['wcac_frequency_penalty']);
        }
        if (isset($api_settings['wcac_presence_penalty']) && $api_settings['wcac_presence_penalty'] !== '') {
            $data['presence_penalty'] = floatval($api_settings['wcac_presence_penalty']);
        }
        // Add any custom parameters from settings (overrides above if present)
        $custom_params = $api_settings['custom_params'] ?? '';
        if (!empty($custom_params)) {
            $custom_params_array = json_decode($custom_params, true);
            if (is_array($custom_params_array)) {
                $data = array_merge($data, $custom_params_array);
            }
        }
        error_log('WCAC DEBUG: LLM API payload: ' . json_encode($data));

        $ch = curl_init($api_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            error_log("WCAC: CURL Error - " . $curl_error);
            return new WP_Error('curl_error', 'Failed to connect to API: ' . $curl_error);
        }

        if ($http_code !== 200) {
            error_log("WCAC: API Error - HTTP $http_code - $response");
            return new WP_Error('api_error', "API request failed with HTTP code $http_code");
        }

        return $response;
    }

    public function get_diagnostic_info() {
        $api_settings = get_option('wcac_settings', []);
        $api_connectivity = $this->test_api_connectivity();
        $product_count = $this->count_products();
        
        return [
            'plugin_version' => $this->version,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'api_configured' => !empty($api_settings['wcac_api_key']),
            'api_model' => $api_settings['wcac_model'] ?? 'default',
            'api_url' => $api_settings['wcac_api_url'] ?? 'default',
            'api_connectivity' => is_wp_error($api_connectivity) ? 
                $api_connectivity->get_error_message() : 'Connected',
            'product_count' => $product_count,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'timezone' => wp_timezone_string(),
        ];
    }

    private function count_products() {
        $count = wp_count_posts('product');
        return $count->publish ?? 0;
    }
}