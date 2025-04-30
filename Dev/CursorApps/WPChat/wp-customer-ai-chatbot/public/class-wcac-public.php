<?php
declare(strict_types=1);

/**
 * The public-facing functionality of the plugin.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/public
 */
class Wcac_Public {

    private string $plugin_name;
    private string $version;
    private WCAC_API_Handler $api_handler;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct(string $plugin_name, string $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->api_handler = new WCAC_API_Handler();

        add_action('init', [$this, 'register_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_wcac_send_message', [$this, 'handle_send_message_ajax']);
        add_action('wp_ajax_nopriv_wcac_send_message', [$this, 'handle_send_message_ajax']);
        add_action('wp_ajax_wcac_diagnostics', [$this, 'handle_diagnostics_ajax']);
        add_action('wp_ajax_wcac_debug_nonce', [$this, 'handle_debug_nonce_ajax']);
        add_action('wp_ajax_nopriv_wcac_debug_nonce', [$this, 'handle_debug_nonce_ajax']);
    }

    /**
     * Register the shortcode for the chatbot.
     */
    public function register_shortcode(): void {
        add_shortcode('wcac_chatbot', [$this, 'render_chatbot_shortcode']);
    }

    /**
     * Render the chatbot shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string Rendered shortcode HTML.
     */
    public function render_chatbot_shortcode($atts): string {
        $atts = shortcode_atts([], $atts, 'wcac_chatbot');
        ob_start();
        include WCAC_PLUGIN_DIR . 'templates/wcac-chat-widget-template.php';
        return ob_get_clean() ?: '';
    }

    /**
     * Enqueue assets for the public-facing side of the site.
     */
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

    /**
     * Handle the AJAX request to send a message to the chatbot.
     */
    public function handle_send_message_ajax() {
        // --- TEMPORARY DEBUGGING: Force error display ---
        // error_reporting(E_ALL);
        // ini_set('display_errors', '1');
        // --- END TEMPORARY DEBUGGING ---

        error_log("WCAC DEBUG: TOP OF handle_send_message_ajax EXECUTED"); // ADDED FOR DEBUGGING
        
        check_ajax_referer('wcac_chatbot_nonce', 'nonce');
        $user_message = sanitize_text_field($_POST['message'] ?? '');
        error_log('WCAC DEBUG: User query: ' . $user_message);
        
        if (empty($user_message)) {
            wp_send_json_error(['message' => 'No message provided']);
            return;
        }

        // Get conversation history if available
        $conversation = isset($_POST['conversation']) && is_array($_POST['conversation']) 
            ? $_POST['conversation'] 
            : [];
            
        // Security: Sanitize conversation data
        $sanitized_conversation = [];
        foreach ($conversation as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $sanitized_conversation[] = [
                    'role' => sanitize_text_field($msg['role']),
                    'content' => sanitize_textarea_field($msg['content'])
                ];
            }
        }
        
        // Check if this is a product query
        $is_product_query = Wcac_ChatbotRules::is_product_query($user_message);
        error_log("WCAC DEBUG: is_product_query result: " . ($is_product_query ? 'true' : 'false'));
        
        // Process referenced products first (follow-up questions about specific products)
        $referenced_product_name = isset($_POST['referenced_product']) ? sanitize_text_field($_POST['referenced_product']) : '';
        $referenced_product_url = isset($_POST['referenced_product_url']) ? esc_url_raw($_POST['referenced_product_url']) : '';
        
        if ($referenced_product_name && $referenced_product_url) {
            error_log("WCAC DEBUG: Processing product-specific query for: " . $referenced_product_name);
            $this->handle_product_specific_query($user_message, $sanitized_conversation, $referenced_product_name, $referenced_product_url);
            return;
        }
        
        // Get relevant content based on the user's message
        error_log('WCAC DEBUG: BEFORE retrieve_relevant_content call for query: ' . $user_message);
        error_log('WCAC DEBUG: Wcac_ChatbotRules class exists: ' . (class_exists('Wcac_ChatbotRules') ? 'YES' : 'NO'));
        try {
            $relevant_content = Wcac_ChatbotRules::retrieve_relevant_content($user_message, $sanitized_conversation);
            error_log('WCAC DEBUG: AFTER retrieve_relevant_content. Result count: ' . (is_array($relevant_content) ? count($relevant_content) : 'Not an array'));
        } catch (Exception $e) {
            error_log('WCAC ERROR: Exception in retrieve_relevant_content: ' . $e->getMessage());
            error_log('WCAC ERROR: Exception trace: ' . $e->getTraceAsString());
            $relevant_content = [];
        }
        
        // Handle results based on content type and query type
        if (empty($relevant_content)) {
            error_log('WCAC DEBUG: No relevant content found, handling no results');
            $this->handle_no_results_found($user_message, $sanitized_conversation, $is_product_query);
            return;
        }
        
        // Log the top results for debugging
        error_log('WCAC DEBUG: Top search results:');
        $i = 0;
        foreach ($relevant_content as $item) {
            if ($i++ >= 5) break; // Only log the top 5
            error_log('WCAC DEBUG: Result #' . $i . ': ' . ($item['title'] ?? 'No title') . ' - Score: ' . ($item['score'] ?? 'No score') . ' - Type: ' . ($item['type'] ?? 'No type'));
        }
        
        // Process results - prepare response with product/content information
        error_log('WCAC DEBUG: Processing search results');
        $this->process_search_results($relevant_content, $user_message, $sanitized_conversation, $is_product_query);
    }
    
    /**
     * Handle a specific product query
     */
    private function handle_product_specific_query($user_message, $conversation, $product_name, $product_url) {
        // Get the product ID from the URL
        $referenced_product_data = [
            'name' => $product_name,
            'url' => $product_url
        ];
        
        $product_id = 0;
        if (function_exists('url_to_postid')) {
            $product_id = url_to_postid($referenced_product_data['url']);
        }
        
        // Fallback/Alternative: Extract slug from URL if url_to_postid fails
        if (!$product_id) {
            $path_parts = explode('/', rtrim($referenced_product_data['url'], '/'));
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
                $product_name = $referenced_product_data['name'];
                
                // Create a prompt for LLM to summarize the product based on the question
                $summary_prompt = "You are a helpful shopping assistant. Answer the customer's question about the product '{$product_name}'.\n\nProduct description: {$product_description}\n\nAnswer in a friendly, conversational way. If you don't know something specific, suggest they check the full product page.";
                
                $llm_response = $this->api_handler->send_simple_llm_message($summary_prompt, $user_message, $conversation);
                
                if (!is_wp_error($llm_response) && is_string($llm_response)) {
                    wp_send_json_success(['message' => $llm_response]);
                } else {
                    $error_message = is_wp_error($llm_response) ? $llm_response->get_error_message() : 'LLM did not return a valid string response.';
                    error_log("WCAC ERROR: Failed to get LLM summary for follow-up: " . $error_message);
                    // Fallback message - provide link directly
                    wp_send_json_success(['message' => "I found the product '{$product_name}'. You can find more details here: [{$product_name}]({$referenced_product_data['url']})"]);
                }
                return;
            }
        }
        
        // If we can't find the product or extract useful info, fall back to general search
        wp_send_json_success(['message' => "I couldn't find detailed information about {$product_name}. You can view it here: [{$product_name}]({$product_url})"]);
    }
    
    /**
     * Handle the case where no search results were found
     */
    private function handle_no_results_found($user_message, $conversation, $is_product_query) {
        $api_settings = get_option('wcac_settings', []);
        
        // For product queries with no results, try to suggest refinements
        if ($is_product_query) {
            $refine_prompt = "The user asked: '" . $user_message . "'. No products were found. Suggest a more specific search term or product category that would help find relevant products. Respond with only the search term or category, nothing else.";
            $refine_response = $this->api_handler->send_simple_llm_message($refine_prompt, $user_message, $conversation);
            if (is_wp_error($refine_response)) {
                error_log('WCAC: LLM refine step error: ' . $refine_response->get_error_message());
                wp_send_json_error(['message' => 'Sorry, I could not find any products. Please try a more specific query.']);
                return;
            }
            
            wp_send_json_success(['message' => "I couldn't find any products matching your query. You might try searching for " . $refine_response . " instead."]);
            return;
        }
        
        // For non-product queries, use general response
        $general_prompt = $this->build_system_prompt($this->get_site_summary_content());
        $response = $this->api_handler->send_simple_llm_message($general_prompt, $user_message, $conversation);
        
        if (is_wp_error($response)) {
            error_log('WCAC: General query LLM call failed: ' . $response->get_error_message());
            wp_send_json_error(['message' => 'Sorry, I had trouble answering that question. Can you please rephrase?']);
        } elseif (is_string($response)) {
            wp_send_json_success(['message' => $response]);
        }
    }
    
    /**
     * Process search results and generate a response
     */
    private function process_search_results($relevant_content, $user_message, $conversation, $is_product_query) {
        // Extract top product hits and categories
        $top_products = [];
        $category_counts = [];
        $category_to_term = [];
        $best_category = null;
        $best_category_url = null;
        
        // Process category information from results
        foreach ($relevant_content as $item) {
            if (function_exists('wp_get_post_terms')) {
                $cat_terms = wp_get_post_terms($item['id'], 'product_cat', ['fields' => 'all']);
                if (is_array($cat_terms)) {
                    foreach ($cat_terms as $cat) {
                        $cat_name = $cat->name;
                        if (!isset($category_counts[$cat_name])) {
                            $category_counts[$cat_name] = 0;
                            $category_to_term[$cat_name] = $cat;
                        }
                        $category_counts[$cat_name]++;
                    }
                }
            }
            
            $prod_name = $item['title'] ?? '';
            $prod_url = '';
            if (function_exists('get_permalink')) {
                $prod_url = get_permalink($item['id']);
            }
            if ($prod_name && $prod_url) {
                $top_products[] = ['name' => $prod_name, 'url' => $prod_url];
            }
        }
        
        // Find the best category
        if (!empty($category_counts)) {
            $best_category_name = array_search(max($category_counts), $category_counts);
            $best_category_term = $category_to_term[$best_category_name] ?? null;
            if ($best_category_term && function_exists('get_term_link')) {
                $best_category_url = get_term_link($best_category_term);
                $best_category = $best_category_name;
            }
        }
        
        // Generate response with product list and intro text
        if (!empty($top_products)) {
            // 1. Collect keywords that matched for context
            $matched_keywords = [];
            foreach ($relevant_content as $item) {
                if (!empty($item['matched_fields'])) {
                    foreach ($item['matched_fields'] as $field => $keywords) {
                        $matched_keywords = array_merge($matched_keywords, $keywords);
                    }
                }
            }
            $matched_keywords = array_unique($matched_keywords);
            
            // 2. Build context strings from top results
            $context_strings = [];
            foreach (array_slice($relevant_content, 0, 5) as $item) {
                $context_strings[] = $this->format_context_item($item);
            }
            
            // 3. Build system prompt with context
            $system_prompt = $this->build_system_prompt(
                $context_strings, 
                $user_message, 
                $matched_keywords, 
                $best_category, 
                $best_category_url
            );
            
            // 4. Call LLM for intro ONLY. No items_to_link needed here.
            error_log('WCAC DEBUG: Before api_handler->send_simple_llm_message (intro)');
            try {
                $intro_response = $this->api_handler->send_simple_llm_message($system_prompt, $user_message, $conversation);
                error_log('WCAC DEBUG: After api_handler->send_simple_llm_message (intro). Response type: ' . gettype($intro_response));
                if (!is_wp_error($intro_response) && is_string($intro_response)) {
                    $llm_intro = trim($intro_response) . "\n\n"; // Add line breaks after intro
                } else {
                    // Fallback intro if LLM fails
                    $llm_intro = "Here are some products related to your query:\n\n";
                    error_log('WCAC: LLM intro generation failed. Using default. Error: ' . (is_wp_error($intro_response) ? $intro_response->get_error_message() : 'Non-string response'));
                }
            } catch (Exception $e) {
                $llm_intro = "Here are some products related to your query:\n\n";
            }
            
            // 5. Build formatted product list (using max results from settings)
            $response_list = [$llm_intro];
            $max_products = Wcac_ChatbotRules::get_max_results_returned();
            $loop_index = 0;
            
            foreach (array_slice($top_products, 0, $max_products) as $product) {
                $name = $product['name'] ?? 'Product';
                $url = $product['url'] ?? '#';
                // Optionally fetch a short description here if needed
                $markdown_link = "- [" . esc_html($name) . "](" . esc_url($url) . ")"; 
                
                // Log details for the first 3 items
                if ($loop_index < 3) {
                    error_log("WCAC DEBUG (Search Response): Product {$loop_index}: {$name}, URL: {$url}");
                }
                
                $response_list[] = $markdown_link;
                $loop_index++;
            }
            
            // Add best category link if available
            if ($best_category && $best_category_url) {
                $response_list[] = "\nSee all products in the category: [" . esc_html($best_category) . "](" . esc_url($best_category_url) . ")";
            }
            
            // Combine LLM intro with PHP list
            $final_response = implode("\n", $response_list);
            wp_send_json_success(['message' => $final_response, 'debug_titles' => $top_products]);
        }
    }
    
    /**
     * Handle the AJAX request for chatbot diagnostics.
     */
    public function handle_diagnostics_ajax() {
        check_ajax_referer('wcac_chatbot_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
            return;
        }
        
        $diagnostic_info = $this->get_diagnostic_info();
        wp_send_json_success($diagnostic_info);
    }
    
    /**
     * Handle the AJAX request to verify nonce and debug connectivity.
     */
    public function handle_debug_nonce_ajax() {
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        
        // Verify the nonce
        $verification_result = wp_verify_nonce($nonce, 'wcac_chatbot_nonce') ? 'valid' : 'invalid';
        
        // Always send a new nonce back
        $new_nonce = wp_create_nonce('wcac_chatbot_nonce');
        
        wp_send_json_success([
            'verification_result' => $verification_result,
            'new_nonce' => $new_nonce
        ]);
    }
    
    /**
     * Test the API connectivity.
     *
     * @return mixed True on success, WP_Error on failure.
     */
    private function test_api_connectivity() {
        return $this->api_handler->test_api_connectivity();
    }
    
    /**
     * Build the system prompt for the LLM.
     *
     * @param array|string $context_strings  Context to include in the prompt.
     * @param string       $user_query       The user's query.
     * @param array        $matched_keywords Keywords that matched the query.
     * @param string|null  $best_category    The best matching category.
     * @param string|null  $best_category_url The URL for the best category.
     * @return string System prompt for the LLM.
     */
    private function build_system_prompt($context_strings, $user_query = '', $matched_keywords = [], $best_category = null, $best_category_url = null) {
        $api_settings = get_option('wcac_settings', []);
        
        // Start with either custom system prompt or default
        $system_prompt = !empty($api_settings['wcac_system_prompt']) 
            ? $api_settings['wcac_system_prompt'] 
            : "You are a helpful shopping assistant for an e-commerce store. Help users find products they're looking for and answer questions based on the context provided. Keep responses concise but friendly. When referring to products, use the exact product names provided.";
        
        $system_prompt .= "\n\nContext:\n";
        
        // Add context strings (could be site summary or product details)
        if (is_array($context_strings)) {
            $system_prompt .= implode("\n\n", $context_strings);
        } else {
            $system_prompt .= $context_strings;
        }
        
        // Add keyword context if available
        if (!empty($matched_keywords) && !empty($user_query)) {
            $system_prompt .= "\n\nMatched search terms: " . implode(", ", $matched_keywords);
        }
        
        // Add best category if available
        if ($best_category && $best_category_url) {
            $system_prompt .= "\n\nBest matching category: {$best_category}";
        }
        
        // Instructions for response format
        $system_prompt .= "\n\nYour response should be concise and focus on the user's query. Do not list products in your response as those will be added separately. Focus on providing helpful information about the products or answering the user's specific question.";
        
        return $system_prompt;
    }
    
    /**
     * Format a context item for the LLM.
     *
     * @param array $item The context item to format.
     * @return string Formatted context item.
     */
    private function format_context_item($item) {
        $output = "Product: " . ($item['title'] ?? 'Unknown');
        
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
        
        if ($url) {
            $output .= " (URL: {$url})";
        }
        
        if ($price_info) {
            $output .= $price_info;
        }
        
        if (!empty($item['content'])) {
            $output .= "\nDescription: " . substr($item['content'], 0, 300);
            if (strlen($item['content']) > 300) {
                $output .= "...";
            }
        }
        
        if (!empty($item['categories'])) {
            $output .= "\nCategories: " . implode(", ", $item['categories']);
        }
        
        return $output;
    }
    
    /**
     * Get summary content for the site.
     *
     * @return string Site summary content.
     */
    private function get_site_summary_content(): string {
        // Try to get pre-built site profile from options
        $site_profile = get_option('wcac_site_profile', '');
        if (!empty($site_profile)) {
            return $site_profile;
        }

        $site_content = '';
        
        // Fallback: Get some basic pages
        if (function_exists('get_pages')) {
            $all_pages_raw = get_pages(['post_type' => 'page', 'post_status' => 'publish']);
            
            if (is_wp_error($all_pages_raw)) {
                error_log('WCAC: Error fetching pages: ' . $all_pages_raw->get_error_message());
                return ''; 
            }
            
            // Target high-value pages
            $priority_pages = [
                'about' => 0,
                'contact' => 0,
                'home' => 0,
                'faq' => 0,
                'shop' => 0,
                'products' => 0
            ];
            
            $all_pages = [];
            foreach ($all_pages_raw as $page) {
                $title_lower = strtolower($page->post_title);
                $found = false;
                
                // Check for priority pages
                foreach ($priority_pages as $key => $val) {
                    if (strpos($title_lower, $key) !== false) {
                        $priority_pages[$key] = $page;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $all_pages[] = $page;
                }
            }
            
            // Process priority pages first
            $processed_pages = [];
            foreach ($priority_pages as $key => $page) {
                if (is_object($page)) {
                    $processed_pages[] = $page;
                }
            }
            
            // Add some random other pages to fill out context (if needed)
            if (count($processed_pages) < 3 && !empty($all_pages)) {
                $max_additional = 3 - count($processed_pages);
                $random_pages = array_slice($all_pages, 0, $max_additional);
                $processed_pages = array_merge($processed_pages, $random_pages);
            }
            
            // Generate content summary
            foreach ($processed_pages as $page) {
                if (isset($page->post_content) && !empty($page->post_content)) {
                    $title = $page->post_title;
                    // Process shortcodes and apply content filters before stripping tags
                    $processed_content = function_exists('apply_filters') ? apply_filters('the_content', $page->post_content) : $page->post_content;
                    $content_raw = wp_strip_all_tags($processed_content);
                    $truncated_content = $content_raw;
                    
                    // Truncate if needed
                    if (strlen($content_raw) > 500) {
                        $truncated_content = substr($content_raw, 0, 500) . "...";
                    }
                    
                    $site_content .= "Page: {$title}\nContent: {$truncated_content}\n\n";
                }
            }
        }
        
        return $site_content;
    }
    
    /**
     * Get the main product categories.
     *
     * @return array Main product categories.
     */
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
    
    /**
     * Get diagnostic information about the plugin.
     *
     * @return array Diagnostic information.
     */
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

    /**
     * Count the number of products in the store.
     *
     * @return int Number of published products.
     */
    private function count_products() {
        $count = wp_count_posts('product');
        return $count->publish ?? 0;
    }
}