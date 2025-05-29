<?php

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
// Suppress linter errors for WordPress core functions (add_action, add_shortcode, shortcode_atts, is_admin)
declare(strict_types=1);

// Ensure WordPress functions are available for linter/static analysis
if (!function_exists('add_shortcode')) {
    require_once ABSPATH . 'wp-includes/shortcodes.php';
}
if (!function_exists('shortcode_atts')) {
    require_once ABSPATH . 'wp-includes/shortcodes.php';
}
if (!function_exists('wp_send_json_error')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('esc_url_raw')) {
    require_once ABSPATH . 'wp-includes/formatting.php';
}
if (!function_exists('wp_send_json_success')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('url_to_postid')) {
    require_once ABSPATH . 'wp-includes/link-template.php';
}
if (!function_exists('get_page_by_path')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (!function_exists('get_post')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (! function_exists('add_action')) {
    require_once ABSPATH . 'wp-includes/plugin.php';
}
if (! function_exists('add_shortcode')) {
    require_once ABSPATH . 'wp-includes/shortcodes.php';
}
if (! function_exists('shortcode_atts')) {
    require_once ABSPATH . 'wp-includes/shortcodes.php';
}
if (! function_exists('is_admin')) {
    require_once ABSPATH . 'wp-includes/load.php';
}
if (! function_exists('wp_enqueue_style')) {
    require_once ABSPATH . 'wp-includes/script-loader.php';
}
if (! function_exists('wp_enqueue_script')) {
    require_once ABSPATH . 'wp-includes/script-loader.php';
}
if (! function_exists('wp_localize_script')) {
    require_once ABSPATH . 'wp-includes/script-loader.php';
}
if (! function_exists('admin_url')) {
    require_once ABSPATH . 'wp-includes/link-template.php';
}
if (! function_exists('wp_create_nonce')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}
if (! function_exists('check_ajax_referer')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}
if (! function_exists('sanitize_text_field')) {
    require_once ABSPATH . 'wp-includes/formatting.php';
}
if (! function_exists('sanitize_textarea_field')) {
    require_once ABSPATH . 'wp-includes/formatting.php';
}
if (! function_exists('wp_strip_all_tags')) {
    require_once ABSPATH . 'wp-includes/formatting.php';
}
if (! function_exists('is_wp_error')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (! function_exists('current_user_can')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}
if (! function_exists('wp_verify_nonce')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}
if (! function_exists('get_option')) {
    require_once ABSPATH . 'wp-includes/option.php';
}
if (! function_exists('get_permalink')) {
    require_once ABSPATH . 'wp-includes/link-template.php';
}
if (! function_exists('apply_filters')) {
    require_once ABSPATH . 'wp-includes/plugin.php';
}
if (! function_exists('get_pages')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (! function_exists('get_terms')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (! function_exists('get_term_link')) {
    require_once ABSPATH . 'wp-includes/link-template.php';
}
if (! function_exists('get_bloginfo')) {
    require_once ABSPATH . 'wp-includes/general-template.php';
}
if (! function_exists('wp_timezone_string')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (! function_exists('wp_count_posts')) {
    require_once ABSPATH . 'wp-includes/post.php';
}

// Include WooCommerce functions if needed
if (! function_exists('wc_get_product')) {
    if (defined('WC_ABSPATH') && file_exists(WC_ABSPATH . 'includes/wc-product-functions.php')) {
        include_once WC_ABSPATH . 'includes/wc-product-functions.php';
    } // Add else block or further checks if necessary
}
if (! function_exists('wc_price')) {
    if (defined('WC_ABSPATH') && file_exists(WC_ABSPATH . 'includes/wc-formatting-functions.php')) {
        include_once WC_ABSPATH . 'includes/wc-formatting-functions.php';
    }
}

// Add at the top, after other require_once statements
require_once WCAC_PLUGIN_DIR . 'includes/common/context-utils.php';

/**
 * The public-facing functionality of the plugin.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/public
 */
class Wcac_Public
{
    private string $plugin_name;
    private string $version;
    /** @var WCAC_API_Handler */
    private WCAC_API_Handler $api_handler;
    /** @var Wcac_Query_Analyzer */
    private Wcac_Query_Analyzer $query_analyzer;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct(string $plugin_name, string $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->api_handler = new WCAC_API_Handler();
        $this->query_analyzer = new Wcac_Query_Analyzer();

        if (function_exists('add_action')) {
            add_action('wp_ajax_wcac_send_message', [ $this, 'handle_send_message_ajax' ]);
            add_action('wp_ajax_nopriv_wcac_send_message', [ $this, 'handle_send_message_ajax' ]);
            add_action('wp_ajax_wcac_diagnostics', [ $this, 'handle_diagnostics_ajax' ]);
            add_action('wp_ajax_wcac_debug_nonce', [ $this, 'handle_debug_nonce_ajax' ]);
            add_action('wp_ajax_nopriv_wcac_debug_nonce', [ $this, 'handle_debug_nonce_ajax' ]);
        }
        if (function_exists('add_shortcode')) {
            add_shortcode('wcac_chatbot', [ $this, 'render_chatbot_shortcode' ]);
        }
    }

    /**
     * Register the shortcode for the chatbot.
     */
    public function register_shortcode(): void
    {
        add_shortcode('wcac_chatbot', [$this, 'render_chatbot_shortcode']);
    }

    /**
     * Render the chatbot shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string Rendered shortcode HTML.
     */
    public function render_chatbot_shortcode($atts): string
    {
        if (function_exists('shortcode_atts')) {
            $atts = shortcode_atts([
                'title' => 'AI Chatbot',
                'placeholder' => 'Ask a question...'
            ], $atts, 'wcac_chatbot');
        }
        ob_start();
        include WCAC_PLUGIN_DIR . 'templates/wcac-chat-widget-template.php';
        return ob_get_clean() ?: '';
    }

    /**
     * Enqueue assets for the public-facing side of the site.
     */
    public function enqueue_assets(): void
    {
        if (function_exists('is_admin') && is_admin()) {
            return;
        }
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
    public function handle_send_message_ajax()
    {
        // Suppress PHP error display to avoid breaking JSON responses
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
        // Begin output buffering to catch any stray output
        ob_start();
        error_log("WCAC DEBUG: TOP OF handle_send_message_ajax EXECUTED"); // ADDED FOR DEBUGGING
        try {
            check_ajax_referer('wcac_chatbot_nonce', 'nonce');
            $user_message = sanitize_text_field($_POST['message'] ?? '');
            if (empty($user_message)) {
                ob_clean();
                wp_send_json_error(['response' => 'Empty message received']);
                return;
            }
            $conversation = isset($_POST['conversation']) ? json_decode(stripslashes($_POST['conversation']), true) : [];
            if (!is_array($conversation)) {
                $conversation = [];
            }
            // --- Context Reuse for Simple Follow-ups ---
            $simple_affirmatives = ['yes', 'yeah', 'yep', 'ok', 'okay', 'sure', 'please', 'alright', 'go on', 'tell me more'];
            $normalized_input = strtolower(trim($user_message));
            $reuse_rag_context = false;
            $last_rag_context = null;
            // Find the last assistant message with rag_context
            for ($i = count($conversation) - 1; $i >= 0; $i--) {
                if (isset($conversation[$i]['role']) && $conversation[$i]['role'] === 'assistant' && !empty($conversation[$i]['rag_context'])) {
                    $last_rag_context = $conversation[$i]['rag_context'];
                    break;
                }
            }
            // If input is a simple affirmation and we have a previous rag_context, reuse it
            if (in_array($normalized_input, $simple_affirmatives, true) && $last_rag_context) {
                $reuse_rag_context = true;
            }
            // --- Check for product-specific follow-up (existing logic) ---
            $referenced_product_name = isset($_POST['referenced_product']) ? sanitize_text_field($_POST['referenced_product']) : '';
            $referenced_product_url = isset($_POST['referenced_product_url']) ? esc_url_raw($_POST['referenced_product_url']) : '';
            if ($referenced_product_name && $referenced_product_url) {
                ob_clean();
                $this->handle_product_specific_query($user_message, $conversation, $referenced_product_name, $referenced_product_url);
                return;
            }
            // --- RAG Retrieval or Context Reuse ---
            $focused_query = $user_message;
            $intent = $this->query_analyzer->detect_intent($user_message);
            if ($intent && isset($intent['type']) && $intent['type'] === 'parent_product' && !empty($intent['name'])) {
                $focused_query = $intent['name'];
            }
            $context_strings = [];
            $relevant_content = [];
            $raw_candidates = [];
            $settings = get_option('wcac_settings', []);
            $compression_algorithm = $settings['wcac_context_compression_algorithm'] ?? 'none';
            if ($reuse_rag_context) {
                $context_strings = is_array($last_rag_context) ? $last_rag_context : [$last_rag_context];
            } else {
                // Standard RAG retrieval
                $retriever = new Wcac_Content_Retriever();
                $retrieval_output = $retriever->retrieve($focused_query, $conversation);
                $relevant_content = $retrieval_output['final_results'] ?? [];
                $raw_candidates = $retrieval_output['raw_candidates'] ?? [];
                foreach ($relevant_content as $item) {
                    $context_strings[] = wcac_format_context_item($item, $compression_algorithm);
                }
            }
            // --- Build prompt and call LLM ---
            $best_category_name = null;
            $best_category_url = null;
            if (!empty($relevant_content) && $relevant_content[0]['type'] === 'category') {
                $best_category_name = $relevant_content[0]['name'] ?? null;
                $best_category_url = $relevant_content[0]['url'] ?? null;
            }
            $prompt_info = $this->build_system_prompt($context_strings, $user_message, [], $best_category_name, $best_category_url);
            $llm_response = $this->api_handler->send_simple_llm_message($prompt_info['system_prompt'], $user_message, $conversation);
            ob_clean();
            // --- Store assistant turn with rag_context ---
            $conversation[] = [
                'role' => 'assistant',
                'content' => $llm_response,
                'rag_context' => $context_strings
            ];
            // --- Logging and response ---
            $keywords = class_exists('Wcac_ChatbotRules') ? Wcac_ChatbotRules::extract_keywords($user_message) : [];
            if (class_exists('Wcac_Debug_Logger')) {
                Wcac_Debug_Logger::log_search(
                    $user_message,
                    $keywords,
                    $relevant_content,
                    $raw_candidates,
                    $relevant_content
                );
            }
            wp_send_json_success([
                'response' => $llm_response,
                'products' => $relevant_content,
                'context' => $context_strings,
                'is_canned' => false
            ]);
            return;
        } catch (Throwable $e) {
            ob_clean();
            error_log('WCAC ERROR: Exception in handle_send_message_ajax: ' . $e->getMessage());
            error_log('WCAC ERROR: Exception trace: ' . $e->getTraceAsString());
            wp_send_json_error(['response' => 'A server error occurred: ' . $e->getMessage()]);
            return;
        }
        error_log("WCAC DEBUG: END OF handle_send_message_ajax EXECUTED");
    }

    /**
     * Handle a specific product query
     */
    private function handle_product_specific_query($user_message, $conversation, $product_name, $product_url)
    {
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
            // Defensive: ensure url is a string
            $url = $referenced_product_data['url'] ?? '';
            if (is_array($url)) {
                $url = implode('/', $url);
            }
            $path_parts = explode('/', rtrim($url, '/'));
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
                    error_log('WCAC DEBUG: Sending JSON success (product-specific LLM response)');
                    wp_send_json_success([
                        'response' => $llm_response,
                        'products' => [],
                        'context' => [],
                    ]);
                } else {
                    $error_message = is_wp_error($llm_response) ? $llm_response->get_error_message() : 'LLM did not return a valid string response.';
                    error_log("WCAC ERROR: Failed to get LLM summary for follow-up: " . $error_message);
                    error_log('WCAC DEBUG: Sending JSON success (product-specific fallback link)');
                    wp_send_json_success([
                        'response' => "I found the product '{$product_name}'. You can find more details here: [{$product_name}]({$referenced_product_data['url']})",
                        'products' => [],
                        'context' => [],
                    ]);
                }
                return;
            }
        }

        // If we can't find the product or extract useful info, fall back to general search
        error_log('WCAC DEBUG: Sending JSON success (product-specific general fallback)');
        wp_send_json_success([
            'response' => "I couldn't find detailed information about {$product_name}. You can view it here: [{$product_name}]({$product_url})",
            'products' => [],
            'context' => [],
        ]);
    }

    /**
     * Handle the AJAX request for chatbot diagnostics.
     */
    public function handle_diagnostics_ajax()
    {
        check_ajax_referer('wcac_chatbot_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['response' => 'Unauthorized access']);
            return;
        }

        $diagnostic_info = $this->get_diagnostic_info();
        wp_send_json_success($diagnostic_info);
    }

    /**
     * Handle the AJAX request to verify nonce and debug connectivity.
     */
    public function handle_debug_nonce_ajax()
    {
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
    private function test_api_connectivity()
    {
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
    private function build_system_prompt($context_strings, $user_query = '', $matched_keywords = [], $best_category = null, $best_category_url = null)
    {
        $api_settings = get_option('wcac_settings', []);

        // Start with either custom system prompt or default
        $default_prompt_text = "You are a helpful and friendly shopping assistant for our store. 
Your goal is to help customers find products they are looking for and answer questions about the store's products and policies based *only* on the provided context snippets.

CORE RULES:
1.  **Context Analysis:** Carefully read all provided context snippets (product details, page content) before answering.
2.  **Information Source:** ONLY use information explicitly present in the context snippets. Do NOT make assumptions or use external knowledge.
3.  **Content Type Priority:**
    - For broad product queries: Show main/parent products first, not variations
    - For non-product queries (policies, info): Prioritize relevant pages and posts
    - For specific product queries: Only show variations if the query explicitly matches variation attributes (color, size) or names
4.  **Product Recommendations:**
    - Recommend items found in context that best match the user's intent
    - For broad queries, focus on main product lines rather than specific variants
    - Only suggest variations when specifically asked about them
    - Limit recommendations to 5 items unless asked for more
5.  **Complex Query Handling:**
    - For ambiguous or complex queries, analyze the user's intent carefully
    - Use your understanding to recommend the most relevant content type (products, pages, or posts)
    - Briefly explain your reasoning when it helps clarify the response
6.  **Response Style:**
    - Be concise and friendly
    - Keep product descriptions brief but informative
    - Mention available variations only when relevant
    - Use exact product names from the context
7.  **Missing Information:**
    - If context doesn't contain the answer, clearly state that
    - Suggest related categories or topics if available
    - Never invent or assume information
8.  **Links and URLs:**
    - Only provide URLs found in the context snippets
    - Use proper markdown format for links
9.  **Categories:**
    - When showing multiple products, mention their category if provided
    - For broad queries, suggest exploring relevant categories
10.  **No Hallucination:**
    - Never report or imply information that is not present in the provided context snippets.
    - If the answer is not in the context, say so clearly.";

        if (class_exists('Wcac_Chatbot_Defaults') && method_exists('Wcac_Chatbot_Defaults', 'get_default_system_prompt')) {
            $default_prompt_text = Wcac_Chatbot_Defaults::get_default_system_prompt();
        }

        $system_prompt = !empty($api_settings['wcac_system_prompt'])
            ? $api_settings['wcac_system_prompt']
            : $default_prompt_text;

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

        return [
            'system_prompt' => $system_prompt,
            'best_category' => $best_category,
            'best_category_url' => $best_category_url
        ];
    }

    /**
     * Get summary content for the site.
     *
     * @return string Site summary content.
     */
    private function get_site_summary_content(): string
    {
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
    private function get_main_product_categories(): array
    {
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
    public function get_diagnostic_info()
    {
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
    private function count_products()
    {
        $count = wp_count_posts('product');
        return $count->publish ?? 0;
    }
}
