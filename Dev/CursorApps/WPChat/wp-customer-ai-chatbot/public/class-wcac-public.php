<?php
declare(strict_types=1);

/**
 * Handles the public-facing functionality of the plugin.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/public
 */
class Wcac_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private string $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private string $version;

	/**
	 * Approximate tokens allowed for RAG context.
	 *
	 * @since 0.1.1
	 */
	private const MAX_RAG_TOKENS = 1000;

	/**
	 * Number of *distinct* products/items to finally include in context.
	 *
	 * @since 0.1.1
	 */
	private const RAG_TOP_N_PRODUCTS = 5;

	/**
	 * Number of results to fetch initially from DB before filtering.
	 *
	 * @since 0.1.5
	 */
	private const RAG_INITIAL_FETCH_COUNT = 15;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.1.0
	 * @param    string    $plugin_name The name of the plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( string $plugin_name, string $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'init', [ $this, 'register_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Register AJAX actions
		add_action( 'wp_ajax_wcac_send_message', [ $this, 'handle_send_message_ajax' ] );
		add_action( 'wp_ajax_nopriv_wcac_send_message', [ $this, 'handle_send_message_ajax' ] ); // For logged-out users
	}

	/**
	 * Register the [wcac_chatbot] shortcode.
	 *
	 * @since 0.1.0
	 */
	public function register_shortcode(): void {
		add_shortcode( 'wcac_chatbot', [ $this, 'render_chatbot_shortcode' ] );
	}

	/**
	 * Render the chatbot interface for the shortcode.
	 *
	 * @since 0.1.0
	 * @param array|string $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function render_chatbot_shortcode( $atts ): string {
		// Ensure attributes are array type.
		$atts = shortcode_atts( [], $atts, 'wcac_chatbot' );

		ob_start();
		// Use include to make variables from this scope available in the template.
		include WCAC_PLUGIN_DIR . 'templates/wcac-chat-widget-template.php';
		return ob_get_clean() ?: ''; // Return empty string if ob_get_clean fails
	}

	/**
	 * Enqueue scripts and styles for the public-facing side of the site.
	 *
	 * Only loads if the wcac_chatbot shortcode is found in the main post content.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_assets(): void {
		global $post;

		// Only load on singular pages/posts where the shortcode exists in the content.
		if ( is_singular() && is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'wcac_chatbot' ) ) {
			wp_enqueue_style(
				$this->plugin_name,
				WCAC_PLUGIN_URL . 'assets/css/wcac-public.css',
				[],
				$this->version,
				'all'
			);

			wp_enqueue_script(
				$this->plugin_name,
				WCAC_PLUGIN_URL . 'assets/js/wcac-public.js',
				[], // Dependencies
				$this->version,
				true // Load in footer
			);

			// Localize script data
			$script_data = [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wcac_chatbot_nonce' ),
			];
			wp_localize_script( $this->plugin_name, 'wcac_chatbot_data', $script_data );

			// Add custom CSS inline
			$options = get_option('wcac_settings');
			$custom_css = $options['wcac_custom_css'] ?? '';
			if ( ! empty( $custom_css ) ) {
				// Basic sanitization already done on save, just ensure it's valid CSS-like structure
				$sanitized_css = wp_strip_all_tags( $custom_css ); // Redundant if sanitization is robust, but safe
				if ( ! empty( $sanitized_css ) ) {
					wp_add_inline_style( $this->plugin_name, $sanitized_css );
				}
			}
		}
	}

	/**
	 * Retrieves and filters relevant content based on the user message and token limits.
	 *
	 * @param string $user_message The user's input message.
	 * @param int $max_context_tokens The maximum number of tokens allowed for the context.
	 * @return array An array containing 'context_strings' (formatted strings for the prompt) and 'raw_results' (the filtered data objects). Returns an empty array structure if no content is found or on error.
	 */
	private function retrieve_relevant_content(string $user_message, int $max_context_tokens): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcac_index';
		error_log("WCAC Retrieve: Using table '{$table_name}'. User message: " . $user_message);

		// --- Keyword Extraction (Same as before) ---
		$message_lower = strtolower( $user_message );
		$stop_words = [
			'a', 'an', 'the', 'in', 'on', 'at', 'is', 'are', 'was', 'were', 'and', 'or', 'but',
			'to', 'of', 'for', 'with', 'as', 'by', 'this', 'that', 'it', 'you', 'i', 'me',
			'my', 'your', 'he', 'she', 'we', 'they', 'what', 'which', 'who', 'whom', 'how',
			'why', 'when', 'where', 'do', 'does', 'did', 'can', 'could', 'will', 'would',
			'should', 'has', 'have', 'had', 'about', 'items', 'product', 'page', 'post',
			'?', '.', ',', '!',
		];
		$message_words = preg_split( '/\s+/', preg_replace('/[^\w\s]/', '', $message_lower) ?? '', -1, PREG_SPLIT_NO_EMPTY );
		$keywords = array_diff( $message_words ?: [], $stop_words );
		error_log('WCAC Retrieve: Extracted keywords: ' . implode(', ', $keywords));
		$sale_intent = preg_match('/\b(sale|discount|offer|deal|clearance)\b/i', $user_message);
		$yarn_intent = preg_match('/\b(yarn|wool|fiber|thread|knitting|crochet)\b/i', $user_message);

		if ( empty( $keywords ) && !$sale_intent && !$yarn_intent ) {
			error_log('WCAC Retrieve: No significant keywords or intents detected.');
			return ['context_strings' => [], 'raw_results' => []];
		}

		// --- Build SQL Query (Refactored Approach) ---
		$score_weights = [
			'title' => 25,
			'category' => 20,
			'tag' => 15,
			'content' => 5,
			'on_sale_boost' => 40,
			'yarn_category_boost' => 25,
			'specific_attribute_boost' => 150, // Very high boost for specific matches
		];

		// --- Dynamic Score Adjustment for Variant Attributes ---
		$attribute_pattern_detected = false;
		$specific_attribute_term = null;
		// Simple check for standalone 4+ digit numbers (potential color codes)
		if (preg_match('/\b(\w*\s*\d{4,}\s*\w*)\b/', $user_message, $matches)) { 
			$attribute_pattern_detected = true;
			// Try to capture the word before/after the number if present
			$potential_term = trim($matches[1]);
			 // Basic filtering - avoid if it looks like just a year
			if (!preg_match('/^(19|20)\d{2}$/', $potential_term)) {
				 $specific_attribute_term = $potential_term;
				 error_log("WCAC Retrieve: Specific attribute term identified: '{$specific_attribute_term}'");
			} else {
				 error_log("WCAC Retrieve: Detected number looks like a year, not treating as specific attribute.");
			}

			// Apply a *moderate* general title boost just for seeing the pattern
			$original_title_weight = $score_weights['title'];
			$score_weights['title'] *= 1.5; // Moderate general boost
			error_log("WCAC Retrieve: Attribute pattern detected. Boosting general title score weight from {$original_title_weight} to {$score_weights['title']}.");
		}
		// --- End Dynamic Score Adjustment ---

		$select_fields = "post_id, post_type, title, content_snippet, url, categories, tags, regular_price, sale_price, on_sale, parent_id";
		
        $score_sql_parts = [];
        $where_sql_parts = ["1=1"]; 
        $score_bindings = [];
        $where_bindings = [];
       
        // --- Add SPECIFIC Attribute Score Boost (if term identified) ---
        if ($specific_attribute_term !== null) {
            $score_sql_parts[] = "(CASE WHEN title LIKE %s THEN %d ELSE 0 END)";
            $score_bindings[] = '%' . $wpdb->esc_like($specific_attribute_term) . '%';
            $score_bindings[] = $score_weights['specific_attribute_boost'];
            error_log("WCAC Retrieve: Adding specific attribute score boost SQL part for term: '{$specific_attribute_term}'");
        }
        // --- End Specific Attribute Score Boost ---

		// Add scoring based on keyword matches
		if (!empty($keywords)) {
			$keyword_title_cases = [];
            $keyword_content_cases = [];
            $keyword_category_cases = [];
            $keyword_tag_cases = [];
            
            $keyword_score_bindings_block = []; // Collect score bindings for all keywords here
            $keyword_where_bindings_or = []; // Collect bindings for the main keyword OR block

			$keyword_where_or_parts = []; // Collect raw SQL for the main keyword OR block

			foreach ($keywords as $i => $keyword) {
				$keyword_text_like = '%' . $wpdb->esc_like($keyword) . '%';
                $keyword_json_like = '%"' . $wpdb->esc_like($keyword) . '"%';

				// Build parts for scoring CASE statements (RAW SQL with placeholders)
				$keyword_title_cases[]    = "WHEN title LIKE %s THEN %d";
                $keyword_content_cases[]  = "WHEN content_snippet LIKE %s THEN %d";
                $keyword_category_cases[] = "WHEN categories LIKE %s THEN %d";
                $keyword_tag_cases[]      = "WHEN tags LIKE %s THEN %d";

                // Add bindings for these SCORE parts (Text, Int, Text, Int, Json, Int, Json, Int)
                array_push($keyword_score_bindings_block,
                    $keyword_text_like, $score_weights['title'],
                    $keyword_text_like, $score_weights['content'],
                    $keyword_json_like, $score_weights['category'],
                    $keyword_json_like, $score_weights['tag']
                );

                // Build parts for the WHERE OR block (RAW SQL with placeholders)
                $keyword_where_or_parts[] = "(title LIKE %s OR content_snippet LIKE %s OR categories LIKE %s OR tags LIKE %s)";
                // Add bindings for this OR part (Text, Text, Json, Json)
                array_push($keyword_where_bindings_or, $keyword_text_like, $keyword_text_like, $keyword_json_like, $keyword_json_like);
			}

            // Assemble final SCORE calculations from keyword parts
            if (!empty($keyword_title_cases))    $score_sql_parts[] = "(CASE " . implode(" ", $keyword_title_cases) . " ELSE 0 END)";
            if (!empty($keyword_content_cases))  $score_sql_parts[] = "(CASE " . implode(" ", $keyword_content_cases) . " ELSE 0 END)";
            if (!empty($keyword_category_cases)) $score_sql_parts[] = "(CASE " . implode(" ", $keyword_category_cases) . " ELSE 0 END)";
            if (!empty($keyword_tag_cases))      $score_sql_parts[] = "(CASE " . implode(" ", $keyword_tag_cases) . " ELSE 0 END)";
            
            // Add collected keyword score bindings to main score bindings
            $score_bindings = array_merge($score_bindings, $keyword_score_bindings_block);

            // Assemble final WHERE OR block from keyword parts
            if (!empty($keyword_where_or_parts)) {
                 $where_sql_parts[] = "(" . implode(" OR ", $keyword_where_or_parts) . ")";
                 // Add collected keyword WHERE bindings to main where bindings
                 $where_bindings = array_merge($where_bindings, $keyword_where_bindings_or);
            }
		}

		// Add sale intent boost
		if ($sale_intent) {
            // Add raw SQL part for score calculation
			$score_sql_parts[] = "(CASE WHEN on_sale = 1 THEN %d ELSE 0 END)";
            // Add binding for score calculation
            $score_bindings[] = $score_weights['on_sale_boost'];
		}

		// Add yarn intent boost
		if ($yarn_intent) {
			$yarn_like = '%"yarn"%';
			$wool_like = '%"wool"%';
			$fiber_like = '%"fiber"%';

			// Add raw SQL part for score calculation
			$score_sql_parts[] = "(CASE WHEN categories LIKE %s THEN %d ELSE 0 END)";
			// Add bindings for score calculation
            array_push($score_bindings, $yarn_like, $score_weights['yarn_category_boost']);

			// Add raw SQL part for WHERE clause 
			$where_sql_parts[] = "(categories LIKE %s OR categories LIKE %s OR categories LIKE %s)";
			// Add bindings for WHERE clause
            array_push($where_bindings, $yarn_like, $wool_like, $fiber_like);
		}

		// Combine final SQL parts
		$score_sql_final = !empty($score_sql_parts) ? implode(" + ", $score_sql_parts) : "0";
        $where_sql_final = implode(" AND ", $where_sql_parts);

        // --- Assemble final $bindings array in correct order (Scores, then WHEREs, then Limit) --- 
        $final_bindings = array_merge($score_bindings, $where_bindings);

		// Build final SQL string using raw parts
        $sql = "SELECT {$select_fields}, ({$score_sql_final}) AS relevance_score FROM {$table_name} WHERE {$where_sql_final} HAVING relevance_score > 0 ORDER BY relevance_score DESC LIMIT %d";

		// Use INITIAL fetch count for the DB query
        $limit = self::RAG_INITIAL_FETCH_COUNT;
		$final_bindings[] = $limit; 

		// Prepare the statement just once
        error_log("WCAC Retrieve DEBUG: Final SQL String before prepare: " . $sql);
        error_log("WCAC Retrieve DEBUG: Final Bindings Array before prepare: " . print_r($final_bindings, true));
		$prepared_sql = $wpdb->prepare($sql, $final_bindings);
        // Log the result *after* prepare for comparison
		error_log("WCAC Retrieve: Prepared SQL (after prepare call): " . $prepared_sql);

        // --- Execute Query & Filter Results --- 
		$initial_results = $wpdb->get_results($prepared_sql);

        // === Logging Raw DB Results ===
        if (!empty($initial_results)) {
            error_log("WCAC Retrieve DEBUG: Raw DB Results: " . print_r($initial_results, true));
        } else if (empty($initial_results)) {
             error_log("WCAC Retrieve DEBUG: DB query returned empty results.");
         }
        if ($wpdb->last_error) {
            error_log("WCAC Retrieve DB Error: " . $wpdb->last_error);
            // We should still return here if there's an error, even after logging
            return ['context_strings' => [], 'raw_results' => []]; 
        }
        // === END DEBUGGING ADDITION ===

		if (empty($initial_results)) {
			error_log("WCAC Retrieve: No relevant content found initially.");
			return ['context_strings' => [], 'raw_results' => []];
		}
        
        error_log('WCAC Retrieve: Found ' . count($initial_results) . ' potentially relevant items initially.');
        
        // === Revised Filtering Logic ===
        $filtered_results = [];
        $target_variant = null;
        $seen_parent_ids = [];
        $seen_post_ids = [];

        // If a specific attribute was searched for, find the best matching variant first
        if ($specific_attribute_term !== null) {
            $best_variant_score = -1;
            foreach ($initial_results as $item) {
                // Check if title contains the specific term (case-insensitive)
                if (isset($item->title) && stripos($item->title, $specific_attribute_term) !== false) {
                    // Simple score check - could be more sophisticated
                    if ($item->relevance_score > $best_variant_score) {
                        $target_variant = $item;
                        $best_variant_score = $item->relevance_score;
                    }
                }
            }
            if ($target_variant) {
                error_log("WCAC Retrieve: Prioritizing target variant based on attribute term: " . $target_variant->title);
                $filtered_results[] = $target_variant;
                $seen_post_ids[$target_variant->post_id] = true;
                if (isset($target_variant->parent_id) && $target_variant->parent_id > 0) {
                    $seen_parent_ids[$target_variant->parent_id] = true; // Mark parent as seen if target is a variant
                }
            }
        }

        // Fill remaining slots with other distinct items from initial results
        foreach ($initial_results as $item) {
            if (count($filtered_results) >= self::RAG_TOP_N_PRODUCTS) {
                break; 
            }

            $post_id = $item->post_id;
            // Skip if it's the target variant we already added
            if ($target_variant && $post_id === $target_variant->post_id) {
                continue;
            }

            // Skip if we've already seen this post ID
            if (isset($seen_post_ids[$post_id])) {
                continue;
            }

            $is_variation = isset($item->parent_id) && $item->parent_id > 0;
            $parent_id = $is_variation ? $item->parent_id : null;

            // Skip if it's a variation of a parent we've already included (either via target or another item)
            if ($is_variation && isset($seen_parent_ids[$parent_id])) {
                 continue;
            }
            
            // Add the distinct item
            $filtered_results[] = $item;
            $seen_post_ids[$post_id] = true;
            if ($is_variation) {
                $seen_parent_ids[$parent_id] = true; // Mark parent as seen
            }
        }
        error_log('WCAC Retrieve: Filtered down to ' . count($filtered_results) . ' distinct items for context.');
        // === End Revised Filtering Logic ===
        
		// --- Context Assembly & Token Management --- 
        $product_context_strings = []; 
		$current_token_count = 0;
		$instruction_prompt = "Based on the following potentially relevant content from the website:

Please answer the user's question:

";
		$instruction_token_estimate = (int) ceil( strlen( $instruction_prompt ) / 4 );

		// --- Format Product/Post Context (Use $filtered_results) ---
		foreach ( $filtered_results as $row ) { // <--- Iterate over filtered results
			// Reconstruct a data structure similar to the old $content_data for formatting
			$content_data = [
				'type'          => $row->post_type,
				'title'         => $row->title,
				'on_sale'       => (bool) $row->on_sale,
				'regular_price' => $row->regular_price,
				'sale_price'    => $row->sale_price,
				'content'       => $row->content_snippet, // Use the snippet
				'url'           => $row->url,
				// Note: categories/tags aren't directly used in formatting below, could skip reconstruction if needed
				'categories'    => !empty($row->categories) ? json_decode($row->categories, true) : [],
				'tags'          => !empty($row->tags) ? json_decode($row->tags, true) : [],
				'parent_id'     => $row->parent_id,
			];

			// Format the context string (using the same logic as before, adapted variable names)
			$formatted_item = [];
			$item_type = $content_data['type'] ?? 'Unknown';
			$item_title = $content_data['title'] ?? 'N/A';
			$item_on_sale = $content_data['on_sale'] ?? false;
			$item_regular_price = $content_data['regular_price'] ?? null;
			$item_sale_price = $content_data['sale_price'] ?? null;

			error_log("WCAC Context Format: Processing Post ID {$row->post_id} (Type: {$item_type}, Title: {$item_title}, Score: {$row->relevance_score})");

			$formatted_item[] = "Type: " . ucfirst($item_type);
			$formatted_item[] = "Title: " . $item_title;

			// Add price information if available
			if ($item_regular_price !== null && function_exists('wc_price')) { // Check if WC is active
				try {
					$regular_price_formatted = wc_price($item_regular_price);
					if ($item_on_sale && $item_sale_price !== null) {
						$sale_price_formatted = wc_price($item_sale_price);
						$formatted_item[] = "Status: ON SALE";
						$formatted_item[] = "Sale Price: " . $sale_price_formatted;
						$formatted_item[] = "Regular Price: " . $regular_price_formatted;
					} else {
						$formatted_item[] = "Price: " . $regular_price_formatted;
					}
				} catch (\Throwable $e) {
					error_log("WCAC ERROR: wc_price failed during context formatting for Post ID {$row->post_id}. Error: " . $e->getMessage());
					// Fallback to raw numbers if wc_price fails
					if ($item_on_sale && $item_sale_price !== null) {
						$formatted_item[] = "Status: ON SALE"; $formatted_item[] = "Sale Price: {$item_sale_price}"; $formatted_item[] = "Regular Price: {$item_regular_price}";
					} else {
						$formatted_item[] = "Price: {$item_regular_price}";
					}
				}
			} elseif ($item_regular_price !== null) { // Fallback if wc_price doesn't exist
				if ($item_on_sale && $item_sale_price !== null) {
					$formatted_item[] = "Status: ON SALE"; $formatted_item[] = "Sale Price: {$item_sale_price}"; $formatted_item[] = "Regular Price: {$item_regular_price}";
				} else {
					$formatted_item[] = "Price: {$item_regular_price}";
				}
			} elseif ($item_on_sale) {
				$formatted_item[] = "Status: ON SALE";
			}

			// Add content snippet
			if (!empty($content_data['content'])) {
				$formatted_item[] = "Info: " . $content_data['content'];
			}

			// Add URL
			if (!empty($content_data['url'])) {
				$formatted_item[] = "URL: " . $content_data['url'];
			}

			$context_piece = implode("\n", array_filter($formatted_item));
			$piece_token_estimate = (int) ceil( strlen( $context_piece ) / 4 );

			if ( ( $current_token_count + $piece_token_estimate + $instruction_token_estimate ) <= $max_context_tokens ) {
				$product_context_strings[] = $context_piece; 
				$current_token_count += $piece_token_estimate;
				error_log('WCAC Context Format: Added context piece for Post ID ' . $row->post_id . '. Current token count: ' . $current_token_count);
			} else {
				error_log( sprintf( 'WCAC RAG: Content ID %s (%s) skipped for user message due to token limit.', $row->post_id, $item_title ) );
				break;
			}
		}

        // === Revised Category Finding Logic ===
        $category_context_string = '';
        if (!empty($keywords) && function_exists('get_terms')) {
            $best_match_term = null;
            $highest_match_score = -1; // Use a score instead of just count

            // Get non-numeric keywords for better category matching
            $non_numeric_keywords = array_filter($keywords, function($kw) {
                 // Keep if not purely numeric OR if it's the specific attribute term itself
                 global $specific_attribute_term; // Access the variable from outer scope
                 return !is_numeric($kw) || ($specific_attribute_term !== null && strcasecmp($kw, $specific_attribute_term) === 0);
            });
            
            $search_query = implode(' ', $non_numeric_keywords);
            if (empty($search_query) && $specific_attribute_term) { // Fallback if only numeric keywords + attribute
                $search_query = $specific_attribute_term; // Search based on attribute term if no other words
            } elseif (empty($search_query)) {
                 $search_query = implode(' ', $keywords); // Absolute fallback to all keywords
            }

            error_log("WCAC Category Search: Using search query: '{$search_query}'");

            $args = [
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'search'     => $search_query, 
                'number'     => 10 // Fetch a few more potential categories
            ];
            $potential_terms = get_terms($args);

            if (!is_wp_error($potential_terms) && !empty($potential_terms)) {
                foreach ($potential_terms as $term) {
                    $current_score = 0;
                    // Score based on matching non-numeric keywords (case-insensitive)
                    foreach ($non_numeric_keywords as $keyword) {
                        if (stripos($term->name, $keyword) !== false) {
                            $current_score += 5; // Base score for keyword match
                             // Boost if specific attribute term is also in category name
                             if ($specific_attribute_term && stripos($term->name, $specific_attribute_term) !== false) {
                                 $current_score += 10;
                             }
                        }
                    }
                    
                    // Prioritize shorter names if scores are equal (less likely to be overly broad)
                    if ($current_score > $highest_match_score || 
                       ($current_score === $highest_match_score && strlen($term->name) < strlen($best_match_term->name))) {
                        $highest_match_score = $current_score;
                        $best_match_term = $term;
                    }
                }
            }
            
            // Fallback to first keyword if still no match (less likely with broader search)
            // ... (keep existing fallback logic if needed, but might be less necessary now) ...

            // Format the best term found (if any)
            if ($best_match_term && $highest_match_score > 0 && function_exists('get_term_link')) {
                $term_link = get_term_link($best_match_term);
                if (!is_wp_error($term_link) && $term_link) {
                    $category_context_string = "Relevant Category: [" . $best_match_term->name . "](" . $term_link . ")";
                    error_log("WCAC Context Format: Found relevant category: " . $best_match_term->name . " with score: " . $highest_match_score);
                }
            }
        }
        // === End Revised Category Finding Logic ===

        // --- Combine Category and Product Context ---
        $final_context_strings = $product_context_strings;
        if (!empty($category_context_string)) {
            // Estimate token count for category string
            $category_token_estimate = (int) ceil( strlen( $category_context_string ) / 4 );
            // Check if adding category context exceeds limit (unlikely but possible)
             if (($current_token_count + $category_token_estimate + $instruction_token_estimate ) <= $max_context_tokens) {
                 array_unshift($final_context_strings, $category_context_string); // Add category to the beginning
                 $current_token_count += $category_token_estimate; // Update token count
                 error_log("WCAC Context Format: Prepended category context. Total token count: " . $current_token_count);
             } else {
                 error_log("WCAC Context Format: Skipping category context due to token limit.");
             }
        }

		// Log the final combined context string
        $final_joined_context_for_log = implode("\n---\n", $final_context_strings);
        error_log("WCAC Prompt DEBUG: Final Joined Context String: " . $final_joined_context_for_log);

		return ['context_strings' => $final_context_strings, 'raw_results' => $filtered_results];
	}

	/**
	 * Handle the AJAX request to send a message to the chatbot.
	 *
	 * @since 0.1.0
	 */
	public function handle_send_message_ajax(): void {
		// Add error reporting at the beginning of function
		error_log('WCAC DEBUG: Starting handle_send_message_ajax function');
		
		try {
			// 1. Security Check (Nonce)
			check_ajax_referer( 'wcac_chatbot_nonce', 'nonce' );
			error_log('WCAC DEBUG: Nonce verification passed');

			// 2. Get User Message
			$user_message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
			if ( empty( $user_message ) ) {
				error_log('WCAC DEBUG: Empty message received');
				wp_send_json_error(['message' => esc_html__( 'Error: No message received.', 'wp-customer-ai-chatbot' )]);
				return;
			}
			error_log('WCAC DEBUG: User message: ' . $user_message);

			// 3. Get API Key
			$options = get_option( 'wcac_settings' );
			error_log('WCAC DEBUG: Retrieved options array: ' . print_r($options, true)); // Log the entire options array
			error_log('WCAC DEBUG: Checking for key wcac_api_key...');
			
			$api_key = $options['wcac_api_key'] ?? null;
			$api_model = $options['wcac_openai_model'] ?? 'gpt-3.5-turbo'; // Keep this key if it's separate

			if ( ! $api_key ) {
				error_log('WCAC DEBUG: API key check failed! Key wcac_api_key was not found or was empty in the retrieved options.');
				wp_send_json_error(['message' => esc_html__( 'Error: Chatbot is not configured correctly. Missing API key.', 'wp-customer-ai-chatbot' )]);
				return;
			}
			error_log('WCAC DEBUG: API key found (not logging actual key)');

			// --- Retrieve RAG Context ---
			error_log('WCAC DEBUG: About to retrieve context');

			// Force cache clear before fetching to diagnose potential stale cache issue
			// wp_cache_delete( Wcac_Indexer::CONTENT_INDEX_KEY, 'options' ); // Also remove this cache clear attempt
			// error_log('WCAC DEBUG: Attempted forceful cache clear for ' . Wcac_Indexer::CONTENT_INDEX_KEY);
			$retrieved_context = $this->retrieve_relevant_content( $user_message, self::MAX_RAG_TOKENS );
			error_log('WCAC DEBUG: Retrieved context: ' . (empty($retrieved_context['context_strings']) ? 'No context' : count($retrieved_context['context_strings']) . ' items'));

            // === ADDED FOR DEBUGGING ===
            // Log the actual array before imploding, just in case implode fails silently or context is lost later
            error_log('WCAC Prompt DEBUG: Context Strings Array in handler: ' . print_r($retrieved_context['context_strings'], true));
            // === END DEBUGGING ADDITION ===

			// --- Get Base System Prompt from Settings (with default) ---
			$default_prompt_template = <<<'EOT'
You are a helpful and knowledgeable assistant for the online store {{store_name}}. Your goal is to answer customer questions accurately based *only* on the context provided below regarding products, pages, and posts.

**Response Guidelines:**
- Speak directly as a representative of the store (use 'we', 'our' when referring to {{store_name}}).
- Be friendly, helpful, and confident in your answers based on the provided information.
- Do NOT identify yourself as an AI, bot, or language model.
- Do NOT use phrases like 'Based on the information provided', 'As an AI', 'According to the context...', 'The most relevant information is...', or similar hedging/meta-commentary language. 
- Do NOT introduce your answer by referencing the context or your process. Start the answer directly.
- Mention the most relevant item (the one with the highest score) from the context first in your response, if applicable.
- Format your answers clearly using markdown, especially for lists and links ([Link Text](URL)). Ensure lists use double newlines between items for proper paragraph spacing.
- Provide concise answers, focusing on the user's query and the relevant information found in the context.
- If the context includes product prices, mention them when relevant to the user's query.
- Answer *only* based on information explicitly provided. Do not reference general knowledge about brands or products unless that information was given to you in the context.
EOT;
			$base_system_prompt_template = $options['wcac_system_prompt'] ?? $default_prompt_template;
			error_log('WCAC DEBUG: Retrieved base system prompt template. Preview: ' . substr($base_system_prompt_template, 0, 50) . '...');

			// --- Replace Placeholders in Base Prompt ---
			$store_name = get_bloginfo('name');
			if (empty($store_name)) {
				$store_name = 'the store'; // Fallback if site name is not set
				error_log('WCAC WARNING: Could not retrieve site name via get_bloginfo. Using fallback.');
			}
			$base_system_prompt = str_replace('{{store_name}}', $store_name, $base_system_prompt_template);
			error_log('WCAC DEBUG: Processed base system prompt. Preview: ' . substr($base_system_prompt, 0, 50) . '...');

			// --- Get Site Specific Prompt from Settings (Optional) ---
			$site_prompt = $options['wcac_site_prompt'] ?? '';

			// --- Build prompt with context if available ---
			error_log('WCAC DEBUG: Building prompt');
			if ( ! empty( $retrieved_context['context_strings'] ) ) {
				error_log('WCAC DEBUG: Using context for prompt');
				$joined_context = implode( "\n---\n", $retrieved_context['context_strings'] );
				
				// --- Revised Prompt Instructions ---
				$prompt_content = <<<EOT
**Website Context:**
---
{$joined_context}
---

**User Question:** {$user_message}

**Instructions:**
1.  **Base your entire answer *only* on the Website Context provided above.** Do not add information from external knowledge.
2.  **If a 'Relevant Category' link is provided, mention it first.**
3.  **List the specific product examples provided** using their Titles. **If a product has a URL, format its mention as a Markdown link:** `[Title](URL)`.
4.  Use Markdown lists or separate paragraphs for clarity when listing multiple items.
5.  Answer directly as the store representative. Avoid phrases like "Based on the context..." or "They belong to...". **Do not add any introductory classifications before listing items.**

Answer:
EOT;
				// --- End Revised Prompt Instructions ---

				error_log('WCAC DEBUG: Context prompt built successfully');
			} else {
				// If no product context
				error_log('WCAC DEBUG: No context available, using fallback prompt');
				$prompt_content = "User question: ";
				$prompt_content .= $user_message;
				$prompt_content .= "\n\nNo specific content context was found related to the user's question. Please answer the question based on your general instructions.";
			}
			error_log('WCAC DEBUG: Prompt built successfully');

			// --- Get Site Profile ---
			error_log('WCAC DEBUG: About to retrieve site profile');
			if (!class_exists('Wcac_Indexer')) {
				$indexer_path = WCAC_PLUGIN_DIR . 'includes/class-wcac-indexer.php';
				if (file_exists($indexer_path)) {
					require_once $indexer_path;
					error_log('WCAC DEBUG: Manually loaded Indexer class');
				} else {
					error_log('WCAC ERROR: Cannot find Indexer class file');
					wp_send_json_error(['message' => 'Error: Core plugin file missing']);
					return;
				}
			}
			
			// Try using constant or fallback to string if class isn't loaded properly
			$profile_key = defined('Wcac_Indexer::SITE_PROFILE_KEY') ? Wcac_Indexer::SITE_PROFILE_KEY : 'wcac_site_profile';
			$site_profile = get_option($profile_key, '');
			error_log('WCAC DEBUG: Site profile retrieved: ' . (empty($site_profile) ? 'Empty' : 'Found, length: ' . strlen($site_profile)));
			
			// --- Combine Prompts and Profile for Final System Content ---
			error_log('WCAC DEBUG: Building system content');
			$system_content = $base_system_prompt;
			
			if (!empty($site_prompt)) {
				$system_content .= "\n---\nSite Specific Instructions:\n" . $site_prompt;
			}
			
			if (!empty($site_profile)) {
				$system_content .= "\n---\nAbout This Site:\n" . $site_profile;
			}
			
			// --- Revised System Prompt Constraint ---
            $system_content .= "\n---\nIMPORTANT: Answer *only* based on information explicitly provided in the Website Context section of the user prompt. Do not reference general knowledge unless it was given in the context.";
            // --- End Revised System Prompt Constraint ---

			error_log('WCAC DEBUG: System content built successfully');
			
			// --- Call OpenRouter API ---
			error_log('WCAC DEBUG: Preparing API request');
			$api_endpoint = 'https://openrouter.ai/api/v1/chat/completions';
			$model_to_use = 'nousresearch/nous-hermes-2-mixtral-8x7b-dpo';

			$request_body = [
				'model' => $model_to_use,
				'messages' => [
					['role' => 'system', 'content' => $system_content],
					['role' => 'user', 'content' => $prompt_content]
				]
			];
			error_log('WCAC DEBUG: Request body prepared');

			$request_args = [
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $request_body ),
				'timeout' => 30, // seconds
			];
			error_log('WCAC DEBUG: About to make API request');

			$response = wp_remote_post( $api_endpoint, $request_args );
			error_log('WCAC DEBUG: API request completed');

			// --- Handle API Response ---
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				error_log('WCAC ERROR: API request failed - ' . $error_message);
				wp_send_json_error(['message' => esc_html__( 'Error: Could not connect to the AI service.', 'wp-customer-ai-chatbot' )]);
				return;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body_raw = wp_remote_retrieve_body( $response );
			error_log('WCAC DEBUG: API response code: ' . $response_code);
			
			if ($response_code !== 200) {
				error_log('WCAC ERROR: API response error - ' . $response_body_raw);
				wp_send_json_error(['message' => esc_html__( 'Error: The AI service returned an error.', 'wp-customer-ai-chatbot' )]);
				return;
			}
			
			$response_body = json_decode( $response_body_raw );
			
			if (!$response_body || !isset($response_body->choices[0]->message->content)) {
				error_log('WCAC ERROR: Invalid API response format - ' . substr($response_body_raw, 0, 100) . '...');
				wp_send_json_error(['message' => esc_html__( 'Error: Received an invalid response from the AI service.', 'wp-customer-ai-chatbot' )]);
				return;
			}
			
			$bot_message = $response_body->choices[0]->message->content;
			error_log('WCAC DEBUG: Successfully retrieved AI response (raw): ' . $bot_message);

			// --- BEGIN Server-Side Link Insertion ---
            $context_items = $retrieved_context['raw_results'] ?? []; // Get the raw items used for context
            if (!empty($context_items)) {
                error_log('WCAC DEBUG: Starting server-side link insertion. Context items: ' . count($context_items));
                foreach ($context_items as $item) {
                    if (isset($item->title) && !empty($item->title) && isset($item->url) && !empty($item->url)) {
                        $title = $item->title;
                        $url = $item->url;

                        // Escape title for use in regex, especially for characters like [, ], (, )
                        $escaped_title = preg_quote($title, '/');

                        // Regex Explanation:
                        // Match the exact escaped title, ensuring it's treated as a whole word (\b).
                        // We are removing the complex lookbehind due to PHP limitations.
                        // This simpler regex will find the title. We will add checks later
                        // to ensure we don't replace it if it's already part of a link.
                        // Match the literal title as a whole word (using word boundaries \b)
                        $pattern = '/\\b(' . $escaped_title . ')\\b/';
                        $replacement = '[$1]('. $url . ')';

                        // Find all matches of the plain title
                        if (preg_match_all($pattern, $bot_message, $matches, PREG_OFFSET_CAPTURE)) {
                            $replacement_occurred = false;
                            // Iterate matches in reverse order to avoid offset issues after replacements
                            for ($i = count($matches[0]) - 1; $i >= 0; $i--) {
                                $match_info = $matches[0][$i];
                                $match_text = $match_info[0];
                                $match_offset = $match_info[1];

                                // Check characters immediately around the match to see if it's already linked
                                $is_already_linked = false;
                                $char_before = ($match_offset > 0) ? $bot_message[$match_offset - 1] : '';
                                $link_pattern_after = '/^\\]\\([^\\)]*\\)/'; // Matches ](...) immediately after

                                if ($char_before === '[') {
                                     // Check if the text immediately after the matched title looks like the end of a markdown link
                                     $text_after_match_offset = $match_offset + strlen($match_text);
                                     if (preg_match($link_pattern_after, substr($bot_message, $text_after_match_offset))) {
                                         $is_already_linked = true;
                                     }
                                }

                                // If not already linked, perform the replacement for this specific match
                                if (!$is_already_linked) {
                                    $bot_message = substr_replace($bot_message, '[' . $match_text . '](' . $url . ')', $match_offset, strlen($match_text));
                                    $replacement_occurred = true;
                                    error_log('WCAC DEBUG: Replaced title "' . $title . '" with link at offset ' . $match_offset);
                                    // Since we replace only the *first* valid non-linked occurrence from the end, break after one success.
                                    break; 
                                }
                            }
                             if (!$replacement_occurred) {
                                 error_log('WCAC DEBUG: Title "' . $title . '" found, but all occurrences were already linked.');
                             }

                        } else {
                             error_log('WCAC DEBUG: Title "' . $title . '" not found as plain text.');
                        }
                    }
                }
                error_log('WCAC DEBUG: Finished server-side link insertion.');
            } else {
                 error_log('WCAC DEBUG: No context items found, skipping server-side link insertion.');
            }
            // --- END Server-Side Link Insertion ---

			// 4. Send Success Response
			wp_send_json_success( [ 'reply' => trim( $bot_message ) ] ); // Send raw message directly
			
		} catch (Exception $e) {
			// Catch any exceptions that might occur
			error_log('WCAC FATAL ERROR: Exception in handle_send_message_ajax: ' . $e->getMessage());
			wp_send_json_error(['message' => esc_html__( 'Error: An unexpected error occurred.', 'wp-customer-ai-chatbot' )]);
		}
	}
} 