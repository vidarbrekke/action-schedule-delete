<?php
declare(strict_types=1);

require_once WCAC_PLUGIN_DIR . 'includes/class-wcac-token-counter.php';

/**
 * Handles the public-facing functionality of the plugin.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/public
 */

// Compatibility layer for functions that might be undefined in some environments
if (!function_exists('wp_strip_all_tags')) {
    /**
     * Properly strip all HTML tags including script and style
     *
     * @param string $string String containing HTML tags
     * @return string The processed string.
     */
    function wp_strip_all_tags($string) {
        return strip_tags($string);
    }
}

if (!function_exists('wp_json_encode')) {
    /**
     * Encode a variable into JSON.
     *
     * @param mixed $data Variable (usually an array or object) to encode as JSON.
     * @return string|false The JSON encoded string, or false if it cannot be encoded.
     */
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

// Fallback for error handling
if (!function_exists('wp_send_json_error')) {
    /**
     * Send a JSON error response back to an Ajax request.
     *
     * @param mixed $data Data to encode as JSON, then print and die.
     */
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
    /**
     * Send a JSON success response back to an Ajax request.
     *
     * @param mixed $data Data to encode as JSON, then print and die.
     */
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

// Fallback for WP_Error if not available
if (!class_exists('WP_Error')) {
    /**
     * Simple implementation of WP_Error for environments where WordPress core is not fully loaded.
     */
    class WP_Error {
        private $code;
        private $message;
        
        /**
         * Constructor.
         *
         * @param string $code Error code
         * @param string $message Error message
         */
        public function __construct($code, $message) {
            $this->code = $code;
            $this->message = $message;
        }
        
        /**
         * Get the error code.
         *
         * @return string Error code
         */
        public function get_error_code() {
            return $this->code;
        }
        
        /**
         * Get the error message.
         *
         * @return string Error message
         */
        public function get_error_message() {
            return $this->message;
        }
    }
}

// Fallback for wp_list_pluck if not available
if (!function_exists('wp_list_pluck')) {
    /**
     * Pluck a certain field out of each object in a list.
     *
     * @param array      $list      List of objects or arrays
     * @param int|string $field     Field from the object to place instead of the entire object
     * @param int|string $index_key Optional. Field from the object to use as keys for the new array.
     * @return array Array containing only the specified fields from the object list.
     */
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
	private const RAG_TOP_N_PRODUCTS = 10;

	/**
	 * Number of results to fetch initially from DB before filtering.
	 *
	 * @since 0.1.5
	 */
	private const RAG_INITIAL_FETCH_COUNT = 25;

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
		
		// Register diagnostic endpoint (admin only)
		add_action( 'wp_ajax_wcac_diagnostics', [ $this, 'handle_diagnostics_ajax' ] );
		
		// Add nonce debugging endpoint
		add_action( 'wp_ajax_wcac_debug_nonce', [ $this, 'handle_debug_nonce_ajax' ] );
		add_action( 'wp_ajax_nopriv_wcac_debug_nonce', [ $this, 'handle_debug_nonce_ajax' ] );
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
	 * Register and enqueue CSS and JavaScript for the public-facing side of the site.
	 *
	 * @since 0.1.0
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
			wp_localize_script( $this->plugin_name, 'wcac_params', $script_data );

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
	 * Retrieve relevant content based on the user's message
	 *
	 * @param string $message The user message to find content for
	 * @return array Array of context strings or structured array with context and metadata
	 */
	public function retrieve_relevant_content($message) {
		global $wpdb;
		
		// Define table names to avoid undefined variable errors
		$postmeta_table_name = $wpdb->postmeta;
		
		// Clean and parse the message
		$message = strtolower(trim($message));
		$words = preg_split('/\s+/', $message);
		
		// Initialize the return structure
		$result = array(
			'context' => array(),
			'category_focus' => null,
			'is_product_query' => false
		);
		
		// Extract keywords, removing common stop words
		$stop_words = array('i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', 'your', 
			'yours', 'yourself', 'yourselves', 'he', 'him', 'his', 'himself', 'she', 'her', 'hers', 
			'herself', 'it', 'its', 'itself', 'they', 'them', 'their', 'theirs', 'themselves', 
			'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'is', 'are', 
			'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'having', 'do', 'does', 
			'did', 'doing', 'a', 'an', 'the', 'and', 'but', 'if', 'or', 'because', 'as', 'until', 
			'while', 'of', 'at', 'by', 'for', 'with', 'about', 'against', 'between', 'into', 
			'through', 'during', 'before', 'after', 'above', 'below', 'to', 'from', 'up', 'down', 
			'in', 'out', 'on', 'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here', 
			'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more', 
			'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 
			'than', 'too', 'very', 's', 't', 'can', 'will', 'just', 'don', 'should', 'now');
		
		// Keep important product-related words that might be filtered as stop words
		$important_terms = array('have', 'has', 'sell', 'selling', 'buy', 'purchase', 'want', 'need', 'looking', 'search');
		
		// Common product category terms that we should preserve
		$product_category_terms = array('yarn', 'wool', 'pattern', 'book', 'kit', 'needle', 'hook', 'accessory');
		
		// List of common product query patterns
		$product_query_patterns = array(
			'do you have', 'do you sell', 'are you selling', 'i\'m looking for', 
			'looking for', 'i need', 'i want', 'can i buy', 'can i purchase',
			'do you carry', 'where can i find', 'i\'m searching for', 'show me'
		);
		
		// Check if the message matches any product query patterns
		foreach ($product_query_patterns as $pattern) {
			if (strpos($message, $pattern) !== false) {
				$result['is_product_query'] = true;
				error_log("WCAC Debug - Detected product query pattern: '$pattern' in message: '$message'");
				break;
			}
		}
		
		// Filter keywords
		$keywords = array();
		foreach ($words as $word) {
			// Remove trailing punctuation (escape double quote)
			$cleaned_word = rtrim($word, '.?,!;:\'"'); 
			
			// Keep word if it's not a stop word or if it's an important term/category
			if (!in_array($cleaned_word, $stop_words) || in_array($cleaned_word, $important_terms) || in_array($cleaned_word, $product_category_terms)) {
				// Use the cleaned word (without trailing punctuation)
				if (!empty($cleaned_word)) { // Ensure it's not empty after trimming
					$keywords[] = $cleaned_word;
				}
			}
		}
		
		// Remove duplicates
		$keywords = array_unique($keywords);
		
		error_log('WCAC Debug - Raw message words: ' . implode(', ', $words));
		error_log('WCAC Debug - Extracted keywords (cleaned): ' . implode(', ', $keywords));
		
		// Prepare keywords specifically for the database query (remove words likely irrelevant for FT search)
		// No longer needed for LIKE search, use original keywords
		// $db_keywords = array_diff($keywords, ['have']); // Remove 'have' etc. from DB search string
		// $db_search_string = implode(' ', $db_keywords);
		// error_log('WCAC Debug - Keywords for DB search: ' . $db_search_string);
		
		// If no keywords after filtering, use the original words (limited to first 5)
		if (empty($keywords)) {
			$keywords = array_slice($words, 0, 5);
			// $db_search_string = implode(' ', $db_keywords);
			error_log('WCAC Debug - No specific keywords, using first 5 words: ' . implode(', ', $keywords));
		}
		
		// Check and potentially add FULLTEXT indexes
		$table_name = $wpdb->prefix . 'wcac_index';
		// $this->ensure_fulltext_indexes($table_name); // No longer needed for LIKE

		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
		
		if ($table_exists) {
			error_log("WCAC Debug - Index table '$table_name' exists. Using primary LIKE query.");

			// Prepare keywords and bindings for LIKE query
			if (empty($keywords)) {
				error_log('WCAC Debug - No keywords for primary query.');
				return $result;
			}

			$like_conditions = [];
			$bindings = [];
			foreach ($keywords as $keyword) {
				if (strlen($keyword) >= 2) { // Allow slightly shorter keywords for LIKE
					$like_pattern = '%' . $wpdb->esc_like($keyword) . '%';
					// Check title, content, and the JSON fields for categories/tags
					$like_conditions[] = "(tbl.title LIKE %s OR tbl.content_snippet LIKE %s OR tbl.categories LIKE %s OR tbl.tags LIKE %s)";
					$bindings[] = $like_pattern;
					$bindings[] = $like_pattern;
					$bindings[] = $like_pattern; // For categories JSON
					$bindings[] = $like_pattern; // For tags JSON
				}
			}

			if (empty($like_conditions)) {
				error_log('WCAC Debug - No valid keywords for LIKE search after filtering.');
				return $result;
			}

			$where_sql = implode(' OR ', $like_conditions);

			// --- Scoring simplification for LIKE ---
			// LIKE doesn't provide a relevance score. We order by sales.
			// We don't need a complex score_sql, just select the columns.
            // $score_sql = "0 as relevance_score"; // Placeholder relevance score - OLD

            // --- Add simple relevance scoring for LIKE based on match location ---
            $score_components = [];
            $score_bindings = []; // Separate bindings for scoring part
            foreach ($keywords as $keyword) {
                if (strlen($keyword) >= 2) {
                    $like_pattern = '%' . $wpdb->esc_like($keyword) . '%';
                    // Score higher for title, then category/tag, then content
                    $score_components[] = "IF(tbl.title LIKE %s, 10, 0)";
                    $score_components[] = "IF(tbl.categories LIKE %s OR tbl.tags LIKE %s, 5, 0)";
                    $score_components[] = "IF(tbl.content_snippet LIKE %s, 1, 0)";
                    // Add bindings for scoring
                    $score_bindings[] = $like_pattern; // Title
                    $score_bindings[] = $like_pattern; // Categories/Tags (first LIKE)
                    $score_bindings[] = $like_pattern; // Categories/Tags (second LIKE)
                    $score_bindings[] = $like_pattern; // Content
                }
            }
            // Sum the scores for all keywords
            $keyword_score_sql_base = !empty($score_components) ? implode(' + ', $score_components) : "0";
            
            // Handle case with no keyword score components to avoid SQL errors
            if (empty($score_components)) {
                $score_sql = "0 as relevance_score";
                $score_bindings = []; // Ensure bindings are empty if no score calculated
            } else {
                // If we have score components, calculate with conditional parent boost
                // Note: We embed $keyword_score_sql_base directly as it doesn't need separate binding here
                $score_sql = "(({$keyword_score_sql_base}) + IF(tbl.post_type = 'product' AND ({$keyword_score_sql_base}) > 5, 10, 0)) as relevance_score";
                // $score_bindings were already calculated in the loop above and remain valid
            }

			// Join for sales data remains the same
            $joins = " LEFT JOIN {$wpdb->postmeta} pm_sales ON tbl.post_id = pm_sales.post_id AND pm_sales.meta_key = 'total_sales' ";
            $select_sales = ", COALESCE(pm_sales.meta_value, 0) as total_sales";
            // Order by calculated relevance score, then sales, then maybe by ID
            $order_by = "ORDER BY relevance_score DESC, total_sales DESC, tbl.post_id DESC"; 

			// Final SQL Query using LIKE on wp_wcac_index table
			$sql = $wpdb->prepare(
				"SELECT tbl.*, {$score_sql} {$select_sales} 
				 FROM {$table_name} as tbl
	             {$joins}
				 WHERE ({$where_sql}) 
				 {$order_by}
				 LIMIT %d",
					// Bindings for SCORE + Bindings for WHERE + Limit - NEW CORRECT ORDER
					array_merge($score_bindings, $score_bindings, $bindings, [self::RAG_INITIAL_FETCH_COUNT])
				);

				error_log("WCAC Debug - Primary LIKE Query SQL: " . $sql);
				$raw_results = $wpdb->get_results($sql);

				// Log the raw result IDs from primary query
                $result_ids = [];
                if ($raw_results) { $result_ids = wp_list_pluck($raw_results, 'post_id'); }
                error_log("WCAC Debug - Primary query initial result count: " . count($raw_results) . ", Post IDs: " . implode(', ', $result_ids));

				// --- PHP-based Post-Processing and Re-sorting ---
				$processed_results = [];
                $yarn_keywords = array_intersect($keywords, ['yarn', 'wool', 'garn']); // Keywords indicating user wants yarn

				if ($raw_results) {
					foreach ($raw_results as $row) {
						// Decode categories first to check for yarn boost
                        $post_categories = !is_null($row->categories) ? (json_decode($row->categories, true) ?: []) : [];
                        $relevance_score = $row->relevance_score ?? 0;

                        // Add boost if user asked for yarn and this product IS yarn (case-insensitive check)
                        if (!empty($yarn_keywords)) {
                            $is_yarn_product = false;
                            foreach ($post_categories as $cat) {
                                if (stripos($cat, 'Yarn') !== false || stripos($cat, 'Garn') !== false) {
                                    $is_yarn_product = true;
                                    break;
                                }
                            }
                            if ($is_yarn_product) {
                                $relevance_score += 20; // Add significant yarn boost
                                error_log("WCAC Debug - Applied yarn boost to Post ID: {$row->post_id}");
                            }
                        }

                        // Store processed data including the potentially boosted score
						$processed_results[] = (
							(object) [
								'ID' => $row->post_id,
								'post_title' => $row->title,
								'post_content' => $row->content_snippet,
								'post_excerpt' => '',
								'post_type' => $row->post_type,
								'categories_json' => $row->categories, // Keep original JSON
								'tags_json' => $row->tags, // Keep original JSON
								'url' => $row->url,
                                'regular_price' => $row->regular_price,
                                'sale_price' => $row->sale_price,
                                'on_sale' => $row->on_sale,
								'relevance_score' => $relevance_score, // Use potentially boosted score
								'total_sales' => $row->total_sales ?? 0
							]
						);
					}

                    // Re-sort results based on the final relevance score (including PHP boost)
                    usort($processed_results, function($a, $b) {
                        // Primary sort: relevance score descending
                        if ($a->relevance_score != $b->relevance_score) {
                            return ($a->relevance_score < $b->relevance_score) ? 1 : -1;
                        }
                        // Secondary sort: total sales descending
                        if ($a->total_sales != $b->total_sales) {
                            return ($a->total_sales < $b->total_sales) ? 1 : -1;
                        }
                        // Tertiary sort: post ID descending (optional, for stable sort)
                        return ($a->ID < $b->ID) ? 1 : -1;
                    });
				}

                // Take the top N results after re-sorting
				$results = array_slice($processed_results, 0, self::RAG_TOP_N_PRODUCTS); 

                // --- Re-calculate Category Focus based on FINAL top results ---
                $category_counts = array();
                foreach($results as $final_post) {
                    // Use already processed object structure
                    $cats = !is_null($final_post->categories_json) ? (json_decode($final_post->categories_json, true) ?: []) : []; 
                    if (!empty($cats) && is_array($cats)) {
                        foreach ($cats as $cat_name) {
                            // Ensure $cat_name is a string before using as key
                            if (is_string($cat_name)) {
                                if (!isset($category_counts[$cat_name])) $category_counts[$cat_name] = 0;
                                $category_counts[$cat_name]++;
                            }
                        }
                    }
                }
                $result['category_focus'] = null;
                if (!empty($category_counts)) {
                    arsort($category_counts);
                    $top_category = key($category_counts);
                    $top_count = reset($category_counts);
                    // Ensure $top_category is valid before proceeding
                    if ($top_count >= 3 && is_string($top_category)) { 
                        $result['category_focus'] = $top_category;
                        error_log("WCAC Debug - Final Category focus detected: {$top_category} ({$top_count} products)");
                    }
                }

			} else {
				// Fallback Query Logic (label added for goto)
				use_fallback_query:
				error_log("WCAC Debug - Index table '$table_name' does not exist, using fallback query including taxonomies.");
				
				// Simple fallback query if the index table doesn't exist
				$conditions = array();
				$bindings = array();
				
				foreach ($keywords as $keyword) {
					if (strlen($keyword) >= 3) { // Only use keywords of sufficient length
						$like_term = '%' . $wpdb->esc_like($keyword) . '%';
						$conditions[] = "(p.post_title LIKE %s OR p.post_content LIKE %s OR t.name LIKE %s)";
						$bindings[] = $like_term;
						$bindings[] = $like_term;
						$bindings[] = $like_term; // Add binding for term name
					}
				}
				
				if (empty($conditions)) {
					error_log("WCAC Debug - No valid keywords for fallback search");
					return $result;
				}
				
				// Include JOINs for taxonomies
				$joins = "
					LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
					LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy IN ('product_cat', 'product_tag')
					LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				";
				
				$sql = "SELECT DISTINCT p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_type 
					FROM {$wpdb->posts} p
					{$joins}
					WHERE p.post_type = 'product' 
					AND p.post_status = 'publish'
					AND (" . implode(' OR ', $conditions) . ")
					LIMIT 10"; // Keep a reasonable limit
					
				$prepared_sql = $wpdb->prepare($sql, $bindings);
				
				// Log the prepared SQL and bindings
				error_log("WCAC Debug - Fallback SQL Query: " . $prepared_sql);
				// error_log("WCAC Debug - Fallback Bindings: " . wp_json_encode($bindings)); // Optional: log bindings if needed
				
				$results = $wpdb->get_results($prepared_sql);
				
				// Log the raw result IDs
				$result_ids = wp_list_pluck($results, 'ID');
				error_log("WCAC Debug - Fallback query result count: " . count($results) . ", IDs: " . implode(', ', $result_ids));
			}
			
			// Early return if no results
			if (empty($results)) {
				error_log("WCAC Debug - No relevant products found for query: " . $message);
				return $result;
			}
			
			// Process results (needs adjustment for primary query structure)
			$context_strings = array();
			// $category_counts = array(); // Moved category count logic above

            // --- Add Focused Category Link to Context --- 
            if (!empty($result['category_focus'])) {
                // Ensure category focus is a string before using get_term_by
                if (is_string($result['category_focus'])) {
                    $term = get_term_by('name', $result['category_focus'], 'product_cat');
                    if ($term && !is_wp_error($term)) {
                        $term_link = get_term_link($term);
                        if ($term_link && !is_wp_error($term_link)) {
                            // Prepend the category link to the context strings array
                            array_unshift($context_strings, "Focused Category: [{$result['category_focus']}]({$term_link})");
                            error_log("WCAC Debug - Added focused category link to context: {$term_link}");
                        }
                    }
                }
            }
			
			foreach ($results as $post) {
				// Get post ID
				$post_id = $post->ID;
				
				// Get categories and tags - handle JSON from primary query or terms from fallback
				$categories = [];
				$tags = [];
				if (isset($post->categories_json)) { // From primary query
					$categories = !is_null($post->categories_json) ? (json_decode($post->categories_json, true) ?: []) : [];
					$tags = !is_null($post->tags_json) ? (json_decode($post->tags_json, true) ?: []) : [];
				} else { // From fallback query
					$categories = wp_get_post_terms($post_id, 'product_cat', array('fields' => 'names'));
					$tags = wp_get_post_terms($post_id, 'product_tag', array('fields' => 'names'));
				}
				
				// Count categories for category focus detection
				if (!empty($categories) && is_array($categories)) {
					foreach ($categories as $category) {
						if (!isset($category_counts[$category])) {
							$category_counts[$category] = 0;
						}
						$category_counts[$category]++;
					}
				}
				
				// Format categories and tags for output
				$categories_str = !empty($categories) && is_array($categories) ? implode(', ', $categories) : '';
				$tags_str = !empty($tags) && is_array($tags) ? implode(', ', $tags) : '';
				
				// Get product price - handle data from primary or fallback
				$price = '';
                if (isset($post->regular_price)) { // From primary query (wcac_index)
                    // Format price based on stored values
                    if ($post->on_sale && !empty($post->sale_price)) {
                        $price = wc_price($post->sale_price) . ' <del>' . wc_price($post->regular_price) . '</del>';
                    } elseif (!empty($post->regular_price)) {
                        $price = wc_price($post->regular_price);
                    }
                } else { // From fallback query (needs wc_get_product)
                    $product = wc_get_product($post_id);
                    if ($product) {
                        $price = $product->get_price_html();
                    }
                }
                $price = strip_tags($price); // Strip tags regardless of source
				
				// Create a summary of the post
				// Use content_snippet from primary query, or excerpt/content from fallback
				$summary = '';
                if (!empty($post->post_content)) { // content_snippet is mapped to post_content for processing
                    $summary = wp_trim_words($post->post_content, 30, '...');
                } elseif (!empty($post->post_excerpt)) { // Fallback check
                    $summary = wp_trim_words($post->post_excerpt, 30, '...');
                }
				
				// Include score if available
				$score_info = isset($post->relevance_score) ? sprintf(" (Score: %.2f)", $post->relevance_score) : '';
				$sales_info = isset($post->total_sales) ? " (Sales: {$post->total_sales})" : '';
				
				// Format the context string
				$context_strings[] = "Product: {$post->post_title}\n" . 
					"URL: {$post->url}\n" .
					"Categories: {$categories_str}\n" . 
					"Tags: {$tags_str}\n" . 
					"Price: {$price}\n" . 
					"Description: {$summary}\n" . 
					"Product ID: {$post_id}{$score_info}{$sales_info}";
				
				// Debug log
				error_log("WCAC Debug - Added to context: Post ID: {$post_id}, Title: {$post->post_title}, Score: " . 
					(isset($post->relevance_score) ? sprintf("%.2f", $post->relevance_score) : '0.00') .
					", Sales: " . (isset($post->total_sales) ? $post->total_sales : 'N/A'));
			}
			
			// Determine if there's a category focus (if 3+ products share the same category)
			$result['category_focus'] = null;
			if (!empty($category_counts)) {
				arsort($category_counts);
				$top_category = key($category_counts);
				$top_count = reset($category_counts);
				if ($top_count >= 3) {
					$result['category_focus'] = $top_category;
					error_log("WCAC Debug - Category focus detected: {$top_category} ({$top_count} products)");
				}
			}
			
			$result['context'] = $context_strings;
			return $result;
		}
	/**
	 * Handle AJAX request for the chatbot
	 */
	public function handle_send_message_ajax() {
		try {
			// Verify nonce and security - track detailed error in case of failure
			if (!isset($_POST['nonce'])) {
				error_log('WCAC Error - Missing nonce parameter in AJAX request');
				wp_send_json_error('Security verification failed: Missing nonce parameter', 403);
				return;
			}
			
			if (!check_ajax_referer('wcac_chatbot_nonce', 'nonce', false)) {
				error_log('WCAC Error - Nonce verification failed in AJAX request. Value: ' . sanitize_text_field($_POST['nonce']));
				wp_send_json_error('Security verification failed: Invalid nonce', 403);
				return;
			}
			error_log('WCAC Debug - Nonce verified successfully.'); // Log nonce success

			// Get and sanitize message
			if (empty($_POST['message'])) {
				error_log('WCAC Error - No message provided in AJAX request');
				wp_send_json_error('No message provided', 400);
				return;
			}

			$message = sanitize_text_field($_POST['message']);
			error_log('WCAC Debug - Received message: ' . $message);
			
			// Get context information based on the message
			error_log('WCAC Debug - Attempting to retrieve relevant content...');
			$context_data = $this->retrieve_relevant_content($message);
			error_log('WCAC Debug - Retrieved relevant content. Is product query: ' . ($context_data['is_product_query'] ?? 'N/A') . ', Context items: ' . count($context_data['context'] ?? []));
			$context_strings = $context_data['context'] ?? [];
			$category_focus = $context_data['category_focus'] ?? '';
			$is_product_query = $context_data['is_product_query'] ?? false;
			
			// Build the system prompt
			error_log('WCAC Debug - Building system prompt...');
			$system_prompt = $this->build_system_prompt($context_strings);
			error_log('WCAC Debug - System prompt built. Length: ' . strlen($system_prompt));

			// Prepare messages for the API request
			$messages = [
				[
					'role' => 'system',
					'content' => $system_prompt,
				],
				[
					'role' => 'user',
					'content' => $message,
				],
			];
			error_log('WCAC Debug - Prepared messages array.');

			// Get the previously configured API information
			$options = get_option('wcac_settings', array()); // Get the entire options array
			error_log('WCAC Debug - Retrieved settings options: ' . wp_json_encode($options)); // Log the raw options
			$api_key = $options['wcac_api_key'] ?? ''; // Get the API key from the options array
			$model = $options['wcac_model'] ?? 'gpt-3.5-turbo'; // Get model from options array
			$api_url = $options['wcac_api_url'] ?? 'https://openrouter.ai/api/v1/chat/completions'; // Get URL or use OpenRouter default
			$max_tokens = intval($options['wcac_max_tokens'] ?? 500); // Get max_tokens from options array
			$temperature = floatval($options['wcac_temperature'] ?? 0.7); // Get temperature from options array

			// Log API configuration for debugging
			error_log('WCAC Debug - Retrieved settings: ' . wp_json_encode($options)); // Log the whole array
			error_log('WCAC Debug - API Key exists: ' . (!empty($api_key) ? 'Yes' : 'No') . ', Model: ' . $model . ', URL: ' . $api_url);

			// Check if API key is missing
			if (empty($api_key)) {
				error_log('WCAC Error - API key is empty after retrieving options.');
				wp_send_json_error('The chatbot is not properly configured. Please contact the site administrator.', 500);
				return;
			}

			// Prepare API settings
			$api_settings = [
				'temperature' => $temperature,
				'max_tokens' => $max_tokens,
			];
			error_log('WCAC Debug - Prepared API settings array.');

			// Make the API request
			error_log('WCAC Debug - Attempting to call send_to_llm_api...');
			// Pass the retrieved API URL along with other parameters
			$response = $this->send_to_llm_api($messages, $api_settings, $api_key, $model, $api_url);
			error_log('WCAC Debug - Call to send_to_llm_api completed.');
			
			// Rest of the method remains the same
			if (is_wp_error($response)) {
				$error_code = $response->get_error_code();
				$error_message = $response->get_error_message();
				
				error_log('WCAC API Error: ' . $error_code . ' - ' . $error_message);
				
				// Send appropriate user-friendly messages based on error type
				if ($error_code === 'http_request_failed' && strpos($error_message, 'cURL error 28') !== false) {
					wp_send_json_error('The request timed out. Please try again.', 504);
				} elseif ($error_code === 'authentication_failed') {
					wp_send_json_error('There was an issue authenticating with the AI service.', 403);
				} elseif ($error_code === 'too_many_requests') {
					wp_send_json_error('The AI service is currently experiencing high demand. Please try again later.', 429);
				} else {
					wp_send_json_error('Sorry, I encountered an error processing your request: ' . $error_message, 500);
				}
				return;
			}

			// Check HTTP status code
			$status_code = wp_remote_retrieve_response_code($response);
			if ($status_code !== 200) {
				$body = wp_remote_retrieve_body($response);
				error_log('WCAC Error - API returned non-200 status: ' . $status_code);
				error_log('WCAC Error - API response body: ' . $body);
				
				if ($status_code === 401 || $status_code === 403) {
					wp_send_json_error('Authentication error with the AI service. Please check your API key.', 403);
				} else if ($status_code === 429) {
					wp_send_json_error('The AI service is currently experiencing high demand. Please try again later.', 429);
				} else {
					wp_send_json_error('The AI service returned an error. Please try again later.', $status_code);
				}
				return;
			}

			// Process the API response
			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			if (empty($data) || empty($data['choices'][0]['message']['content'])) {
				error_log('WCAC Error: Invalid or empty API response: ' . wp_json_encode($data));
				wp_send_json_error('Sorry, I received an empty response. Please try again.', 500);
				return;
			}

			// Get the assistant's message from the API response
			$assistant_message = $data['choices'][0]['message']['content'];

			// Send the response back to the user
			wp_send_json_success([
				'message' => $assistant_message,
				'has_context' => !empty($context_strings),
				'is_product_query' => $is_product_query,
				'category_focus' => $category_focus,
			]);
			
		} catch (Exception $e) {
			// Catch any unexpected exceptions
			error_log('WCAC Error - Uncaught exception in AJAX handler: ' . $e->getMessage());
			error_log('WCAC Error - Exception trace: ' . $e->getTraceAsString());
			wp_send_json_error('Sorry, an unexpected error occurred. Please try again later.', 500);
		}
	}

	/**
	 * Handle AJAX request for diagnostic information
	 */
	public function handle_diagnostics_ajax() {
		// Verify user has admin privileges
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized access');
			return;
		}
		
		// Get diagnostic information
		$diagnostics = $this->get_diagnostic_info();
		
		// Add additional connectivity tests
		$diagnostics['can_connect_to_api'] = $this->test_api_connectivity();
		
		wp_send_json_success($diagnostics);
	}
	
	/**
	 * Test connectivity to the API
	 * 
	 * @return bool True if can connect to API, false otherwise
	 */
	private function test_api_connectivity() {
		$api_url = get_option('wcac_api_url', 'https://api.openai.com/v1/chat/completions');
		
		// Simple connection test
		$test_request = wp_remote_get(
			str_replace('/chat/completions', '', $api_url), 
			array(
				'timeout' => 5,
				'redirection' => 5,
				'httpversion' => '1.1'
			)
		);
		
		if (is_wp_error($test_request)) {
			error_log('WCAC Diagnostics - API connectivity test failed: ' . $test_request->get_error_message());
			return false;
		}
		
		$response_code = wp_remote_retrieve_response_code($test_request);
		error_log('WCAC Diagnostics - API connectivity test response code: ' . $response_code);
		
		// Even a 401 or 404 means we could connect to the server
		return $response_code > 0;
	}

	/**
	 * Build the system prompt with context and instructions
	 *
	 * @param array $context_strings Array of context strings
	 * @return string The complete system prompt
	 */
	private function build_system_prompt($context_strings) {
		// Get the store name and description
		$store_name = get_bloginfo('name');
		$store_description = get_bloginfo('description');
		
		// Build the prompt
		$system_prompt = "You are a helpful AI-powered shopping assistant for the online store '{$store_name}'";
		
		if (!empty($store_description)) {
			$system_prompt .= " which {$store_description}";
		}
		
		$system_prompt .= ".\n\n";
		
		// Add main instructions
		$system_prompt .= "INSTRUCTIONS:\n";
		$system_prompt .= "1. You are a professional and friendly shopping assistant who helps customers find products and answer questions about the store.\n";
		$system_prompt .= "2. ALWAYS use the information provided in CONTEXT to answer questions about products. If the product isn't in the context, say you don't have information about that specific product.\n";
		$system_prompt .= "3. When listing products from the context, ALWAYS format them as clickable markdown links: [Product Name](Product URL). Include the price if available. List 3-5 relevant products if multiple match.\n";
		$system_prompt .= "4. Keep responses helpful, concise, and friendly.\n";
		$system_prompt .= "5. Do not make up information about products or inventory.\n\n";
		
		// Add context if available
		if (!empty($context_strings)) {
			$system_prompt .= "CONTEXT (product information from the store database):\n";
			$system_prompt .= implode("\n\n", $context_strings);
		} else {
			$system_prompt .= "CONTEXT: No specific product information is available for this query.\n";
		}
		
		return $system_prompt;
	}

	/**
	 * Format a context item for inclusion in the system prompt
	 *
	 * @param array $item The item data
	 * @return string Formatted context string
	 */
	private function format_context_item($item) {
		$context = "Product: {$item['post_title']}\n";
		
		// Add price if available
		if (!empty($item['price'])) {
			$context .= "Price: {$item['price']}\n";
		}
		
		// Add categories if available
		if (!empty($item['categories'])) {
			$context .= "Categories: {$item['categories']}\n";
		}
		
		// Add tags if available
		if (!empty($item['tags'])) {
			$context .= "Tags: {$item['tags']}\n";
		}
		
		// Add excerpt or content if available
		if (!empty($item['post_excerpt'])) {
			$context .= "Description: {$item['post_excerpt']}\n";
		} elseif (!empty($item['post_content'])) {
			// Use a shortened version of content if excerpt isn't available
			$content = wp_strip_all_tags($item['post_content']);
			$content = substr($content, 0, 150) . (strlen($content) > 150 ? '...' : '');
			$context .= "Description: {$content}\n";
		}
		
		// Add URL if available
		if (!empty($item['url'])) {
			$context .= "URL: {$item['url']}\n";
		}
		
		return $context;
	}

	/**
	 * Send a simple message to the LLM API
	 *
	 * @param string $system_prompt The system prompt to provide context
	 * @param string $user_message The user's message
	 * @param array $conversation Optional conversation history
	 * @return string The assistant's response
	 */
	public function send_simple_llm_message($system_prompt, $user_message, $conversation = array()) {
		// Get API settings from options
		$options = get_option('wcac_settings', array());
		$api_key = $options['wcac_api_key'] ?? '';
		$model = $options['wcac_model'] ?? 'gpt-3.5-turbo'; // Default if not set
		$temperature = floatval($options['wcac_temperature'] ?? 0.7);
		$max_tokens = intval($options['wcac_max_tokens'] ?? 500);
		$top_p = floatval($options['wcac_top_p'] ?? 1.0);
		$frequency_penalty = floatval($options['wcac_frequency_penalty'] ?? 0);
		$presence_penalty = floatval($options['wcac_presence_penalty'] ?? 0);

		if (empty($api_key)) {
			error_log('WCAC Error - API key is missing in send_simple_llm_message');
			return 'I apologize, but I am not configured properly. Please contact the site administrator.';
		}

		// Initialize the messages array with the system prompt
		$messages = array(
			array(
				'role' => 'system',
				'content' => $system_prompt
			)
		);

		// Add conversation history if provided
		if (!empty($conversation)) {
			foreach ($conversation as $msg) { // Use different var name
				$messages[] = array(
					'role' => $msg['role'],
					'content' => $msg['content']
				);
			}
		} else {
			// Just add the current user message if no history
			$messages[] = array(
				'role' => 'user',
				'content' => $user_message
			);
		}

		// Prepare the API settings array
		$api_settings = [
			'temperature' => $temperature,
			'max_tokens' => $max_tokens,
			'top_p' => $top_p,
			'frequency_penalty' => $frequency_penalty,
			'presence_penalty' => $presence_penalty,
		];

		// Log the request for debugging
		error_log('WCAC Debug - Sending simple LLM request: ' . wp_json_encode(array(
			'model' => $model,
			'settings' => $api_settings,
			'messages_count' => count($messages)
		)));

		// Use the centralized API sending function
		$response = $this->send_to_llm_api($messages, $api_settings, $api_key, $model);

		// Check for errors
		if (is_wp_error($response)) {
			error_log('WCAC Error - API request failed in send_simple_llm_message: ' . $response->get_error_message());
			return 'I apologize, but I encountered an error while processing your request. Please try again later.';
		}

		// Check HTTP status code
		$status_code = wp_remote_retrieve_response_code($response);
		if ($status_code !== 200) {
			$body = wp_remote_retrieve_body($response);
			error_log('WCAC Error - API returned non-200 status in send_simple_llm_message: ' . $status_code . ' - Body: ' . $body);
			return 'I apologize, but the AI service returned an error. Please try again later.';
		}

		// Parse the response
		$response_body = wp_remote_retrieve_body($response);
		$response_data = json_decode($response_body, true);

		// Check if the response is valid
		if (isset($response_data['error'])) {
			error_log('WCAC Error - API returned error in send_simple_llm_message: ' . wp_json_encode($response_data['error']));
			return 'I apologize, but I encountered an error while processing your request. Please try again later.';
		}

		if (!isset($response_data['choices'][0]['message']['content'])) {
			error_log('WCAC Error - Unexpected API response format in send_simple_llm_message: ' . $response_body);
			return 'I apologize, but I received an unexpected response format. Please try again later.';
		}

		// Get the assistant's response
		$assistant_response = $response_data['choices'][0]['message']['content'];
		
		// Log token usage if available
		if (isset($response_data['usage'])) {
			// Ensure class exists before using it
			if (!class_exists('WCAC_Token_Counter')) {
				require_once WCAC_PLUGIN_DIR . 'includes/class-wcac-token-counter.php';
			}
			if (class_exists('WCAC_Token_Counter')) {
				$token_counter = new WCAC_Token_Counter();
				error_log('WCAC Debug - Token usage (simple message): ' . wp_json_encode($response_data['usage']));
				error_log('WCAC Debug - Prompt tokens (simple message, estimated): ' . $token_counter->estimate_token_count($system_prompt));
			}
		}

		return $assistant_response;
	}
	
	/**
	 * Calculate token usage for a given text
	 *
	 * @param string $text The text to calculate token usage for
	 * @return int The token count
	 */
	private function calculate_tokens($text) {
		// Implementation of token calculation based on the model used
		// This is a placeholder and should be replaced with the actual tokenization logic
		// For example, using a tokenizer library or a custom implementation
		return strlen($text) / 4; // Placeholder calculation, actual implementation needed
	}

	/**
	 * Extract keywords from a message
	 *
	 * @param string $message The message to extract keywords from
	 * @return array Array of keywords
	 */
	private function extract_keywords($message) {
		// Convert to lowercase for better matching
		$message = strtolower($message);
		error_log('WCAC Debug - Original message: ' . $message);
		
		// Define common stop words to filter out
		$stop_words = array(
			'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 
			'any', 'are', 'aren\'t', 'as', 'at', 'be', 'because', 'been', 'before', 'being', 
			'below', 'between', 'both', 'but', 'by', 'can', 'can\'t', 'cannot', 'could', 'couldn\'t', 
			'did', 'didn\'t', 'do', 'does', 'doesn\'t', 'doing', 'don\'t', 'down', 'during', 'each', 
			'few', 'for', 'from', 'further', 'had', 'hadn\'t', 'has', 'hasn\'t', 'have', 'haven\'t', 
			'having', 'he', 'he\'d', 'he\'ll', 'he\'s', 'her', 'here', 'here\'s', 'hers', 'herself', 
			'him', 'himself', 'his', 'how', 'how\'s', 'i', 'i\'d', 'i\'ll', 'i\'m', 'i\'ve', 'if', 'in', 
			'into', 'is', 'isn\'t', 'it', 'it\'s', 'its', 'itself', 'let\'s', 'me', 'more', 'most', 
			'mustn\'t', 'my', 'myself', 'no', 'nor', 'not', 'of', 'off', 'on', 'once', 'only', 'or', 
			'other', 'ought', 'our', 'ours', 'ourselves', 'out', 'over', 'own', 'same', 'shan\'t', 
			'she', 'she\'d', 'she\'ll', 'she\'s', 'should', 'shouldn\'t', 'so', 'some', 'such', 'than', 
			'that', 'that\'s', 'the', 'their', 'theirs', 'them', 'themselves', 'then', 'there', 'there\'s', 
			'these', 'they', 'they\'d', 'they\'ll', 'they\'re', 'they\'ve', 'this', 'those', 'through', 
			'to', 'too', 'under', 'until', 'up', 'very', 'was', 'wasn\'t', 'we', 'we\'d', 'we\'ll', 
			'we\'re', 'we\'ve', 'were', 'weren\'t', 'what', 'what\'s', 'when', 'when\'s', 'where', 
			'where\'s', 'which', 'while', 'who', 'who\'s', 'whom', 'why', 'why\'s', 'with', 'won\'t', 
			'would', 'wouldn\'t', 'you', 'you\'d', 'you\'ll', 'you\'re', 'you\'ve', 'your', 'yours', 
			'yourself', 'yourselves'
		);
		
		// Product-seeking phrases that indicate a product query
		$product_seeking_phrases = array(
			'do you have', 'do you sell', 'looking for', 'searching for', 
			'i need', 'i want', 'can i buy', 'can i get', 'where can i find',
			'do you stock', 'in stock', 'available', 'carry', 'product', 'item',
			'purchase', 'buy', 'want to buy', 'interested in', 'shop for'
		);
		
		// Check if this is a product query
		$is_product_query = false;
		foreach ($product_seeking_phrases as $phrase) {
			if (strpos($message, $phrase) !== false) {
				$is_product_query = true;
				error_log('WCAC Debug - Detected product query phrase: ' . $phrase);
				break;
			}
		}
		
		// Split message into words
		$words = preg_split('/\s+/', $message);
		
		// Filter out stop words
		$filtered_words = array_filter($words, function($word) use ($stop_words) {
			return !in_array($word, $stop_words) && strlen($word) > 1;
		});
		
		error_log('WCAC Debug - Raw message words: ' . implode(', ', $words));
		error_log('WCAC Debug - Filtered words: ' . implode(', ', $filtered_words));
		
		// If this is a product query but we have no filtered words, use a more aggressive approach
		if ($is_product_query && empty($filtered_words)) {
			error_log('WCAC Debug - Product query detected but no keywords found. Using minimal filtering.');
			
			// Use minimal stop words for product queries to keep more potential keywords
			$minimal_stop_words = array('a', 'an', 'the', 'is', 'are', 'do', 'does', 'did', 'has', 'have', 'had');
			
			$filtered_words = array_filter($words, function($word) use ($minimal_stop_words) {
				return !in_array($word, $minimal_stop_words) && strlen($word) > 1;
			});
			
			error_log('WCAC Debug - Minimal filtered words: ' . implode(', ', $filtered_words));
		}
		
		return array_values($filtered_words);
	}

	/**
	 * Send a request to the LLM API (via OpenRouter or directly)
	 *
	 * @param array $messages The messages to send to the API
	 * @param array $api_settings The API settings to use (temperature, max_tokens, etc.)
	 * @param string $api_key The API key to use.
	 * @param string $api_model The model to use.
	 * @param string $api_url The API URL to use.
	 * @return array|WP_Error The API response or WP_Error on failure
	 */
	private function send_to_llm_api($messages, $api_settings, string $api_key, string $api_model, string $api_url) {
		// Use the provided API URL, default if empty
		if (empty($api_url)) {
			$api_url = 'https://openrouter.ai/api/v1/chat/completions'; // Sensible default
			error_log('WCAC Debug - API URL not provided, using default: ' . $api_url);
		}

		// Set default model if not provided (should generally be passed)
		if (empty($api_model)) {
			$api_model = 'gpt-3.5-turbo';
			error_log('WCAC Debug - API model not passed, using default: ' . $api_model);
		}
		
		// Verify API key
		if (empty($api_key)) {
			error_log('WCAC Error - API key is missing or empty when calling send_to_llm_api');
			return new WP_Error('missing_api_key', 'API key is not configured');
		}
		
		// Ensure API settings have valid values to prevent errors
		$temperature = isset($api_settings['temperature']) ? floatval($api_settings['temperature']) : 0.7;
		$max_tokens = isset($api_settings['max_tokens']) ? intval($api_settings['max_tokens']) : 800;
		$top_p = isset($api_settings['top_p']) ? floatval($api_settings['top_p']) : 1.0;
		$frequency_penalty = isset($api_settings['frequency_penalty']) ? floatval($api_settings['frequency_penalty']) : 0;
		$presence_penalty = isset($api_settings['presence_penalty']) ? floatval($api_settings['presence_penalty']) : 0;
		
		// Prepare request data
		$data = array(
			'model' => $api_model, // Use passed model
			'messages' => $messages,
			'temperature' => $temperature,
			'max_tokens' => $max_tokens,
			'top_p' => $top_p,
			'frequency_penalty' => $frequency_penalty,
			'presence_penalty' => $presence_penalty,
		);
		
		// Prepare request arguments
		$args = array(
			'method' => 'POST',
			'timeout' => 60,
			'redirection' => 5,
			'httpversion' => '1.1',
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key, // Use passed API key
				// Recommended OpenRouter headers (optional but good practice)
				'HTTP-Referer' => get_site_url(), 
                'X-Title' => get_bloginfo('name'),
			),
			'body' => json_encode($data),
		);
		
		// Log request details right before sending
		$log_headers = $args['headers'];
		if (isset($log_headers['Authorization'])) {
			$log_headers['Authorization'] = 'Bearer sk-...' . substr($api_key, -4); // Mask key for logging
		}
		error_log('WCAC Debug - Sending LLM Request to URL: ' . $api_url);
		error_log('WCAC Debug - LLM Request Headers: ' . json_encode($log_headers));
		error_log('WCAC Debug - LLM Request Body: ' . $args['body']);

		// Make the request using the provided URL
		$response = wp_remote_request($api_url, $args);
		
		// Log response status
		if (!is_wp_error($response)) {
			$status = wp_remote_retrieve_response_code($response);
			error_log('WCAC Debug - API Response Status: ' . $status);
			
			// If response is not 200, log more details to help troubleshooting
			if ($status !== 200) {
				$body = wp_remote_retrieve_body($response);
				error_log('WCAC Error - API Response Body: ' . substr($body, 0, 500) . (strlen($body) > 500 ? '...' : ''));
				error_log('WCAC Error - API Response Headers: ' . json_encode(wp_remote_retrieve_headers($response)));
			}
		} else {
			error_log('WCAC Error - API Request Error: ' . $response->get_error_message());
		}
		
		return $response;
	}

	/**
	 * Get diagnostic information about the plugin
	 * This function can be called to check if the plugin is functioning correctly
	 * 
	 * @return array Diagnostic information
	 */
	public function get_diagnostic_info() {
		$diagnostics = array(
			'plugin_version' => $this->version,
			'php_version' => phpversion(),
			'wordpress_version' => get_bloginfo('version'),
			'is_token_counter_available' => class_exists('WCAC_Token_Counter'),
			'has_api_key' => !empty(get_option('wcac_api_key', '')),
			'has_api_url' => !empty(get_option('wcac_api_url', '')),
			'has_api_model' => !empty(get_option('wcac_model', '')),
			'is_custom_prompt_set' => !empty(get_option('wcac_system_prompt', '')),
			'has_products' => $this->count_products() > 0,
			'php_memory_limit' => ini_get('memory_limit'),
			'php_max_execution_time' => ini_get('max_execution_time')
		);
		
		return $diagnostics;
	}
	
	/**
	 * Count the number of published products
	 *
	 * @return int Number of published products
	 */
	private function count_products() {
		global $wpdb;
		
		$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
		
		return intval($count);
	}

	/**
	 * Handle AJAX request for nonce debugging
	 */
	public function handle_debug_nonce_ajax() {
		try {
			// Log all request details
			error_log('WCAC Debug - Nonce debugging request received');
			error_log('WCAC Debug - Request method: ' . $_SERVER['REQUEST_METHOD']);
			error_log('WCAC Debug - User agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'));
			error_log('WCAC Debug - Referer: ' . ($_SERVER['HTTP_REFERER'] ?? 'None'));
			
			// Check if nonce is present
			if (empty($_REQUEST['nonce'])) {
				error_log('WCAC Debug - No nonce provided in request');
				wp_send_json_error([
					'status' => 'error',
					'message' => 'No nonce provided',
					'error_code' => 'missing_nonce'
				]);
				return;
			}
			
			// Get the nonce from the request
			$nonce = sanitize_text_field($_REQUEST['nonce']);
			error_log('WCAC Debug - Received nonce: ' . $nonce);
			
			// Verify the nonce
			$verify_result = wp_verify_nonce($nonce, 'wcac_chatbot_nonce');
			error_log('WCAC Debug - Nonce verification result: ' . ($verify_result ? $verify_result : 'Failed'));
			
			// Generate a new nonce for testing
			$new_nonce = wp_create_nonce('wcac_chatbot_nonce');
			error_log('WCAC Debug - Generated new nonce: ' . $new_nonce);
			
			// Return detailed information
			wp_send_json_success([
				'status' => 'success',
				'received_nonce' => $nonce,
				'verification_result' => $verify_result ? 'valid' : 'invalid',
				'new_nonce' => $new_nonce,
				'nonce_age' => $verify_result, // 1 or 2 if valid
				'server_time' => time(),
			]);
			
		} catch (Exception $e) {
			error_log('WCAC Error - Exception in nonce debug: ' . $e->getMessage());
			wp_send_json_error([
				'status' => 'error',
				'message' => 'Exception during nonce verification',
				'error' => $e->getMessage()
			]);
		}
	}
} 
