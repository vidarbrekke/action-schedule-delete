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
	 * Number of top products to consider for RAG.
	 *
	 * @since 0.1.1
	 */
	private const RAG_TOP_N_PRODUCTS = 5;

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
	 * Retrieve relevant content (products, pages, posts) context based on user message.
	 *
	 * @since 0.1.1
	 * @access private
	 * @param string $user_message The user's chat message.
	 * @param int    $max_context_tokens The approximate maximum number of tokens allowed for the context.
	 * @return array Array of formatted content strings for context.
	 */
	private function retrieve_relevant_content(string $user_message, int $max_context_tokens): array {
		$content_index = get_option( Wcac_Indexer::CONTENT_INDEX_KEY, [] ); // Use new index key
		error_log('WCAC DEBUG: Content index retrieved. Empty: ' . (empty($content_index) ? 'true' : 'false') . ', Type: ' . gettype($content_index) . ', Count: ' . (is_array($content_index) ? count($content_index) : 'N/A'));
		
		if ( empty( $content_index ) || ! is_array( $content_index ) ) {
			error_log('WCAC DEBUG: No valid content index found. Returning empty array.');
			return []; // No index or invalid index found.
		}

		// --- Direct Title Check First ---
		$direct_matches = [];
		$message_lower = strtolower( $user_message );
		
		foreach ( $content_index as $content_id => $content_data ) { // Changed variable name
			if (is_array($content_data) && isset($content_data['title'])) {
				$content_title_lower = strtolower( $content_data['title'] );
				
				// Check if the title is directly mentioned in the user's message
				if ( str_contains( $message_lower, $content_title_lower ) ) {
					error_log('WCAC DEBUG: Direct title match! Content "' . $content_data['title'] . '" (ID: ' . $content_id . ') found in user message');
					$direct_matches[ $content_id ] = [
						'score' => 100, // High score for direct name matches
						'data'  => $content_data,
						'match_type' => 'direct_title'
					];
				}
			}
		}
		
		// --- Keyword Extraction ---
		$stop_words = [
			'a', 'an', 'the', 'in', 'on', 'at', 'is', 'are', 'was', 'were', 'and', 'or', 'but',
			'to', 'of', 'for', 'with', 'as', 'by', 'this', 'that', 'it', 'you', 'i', 'me',
			'my', 'your', 'he', 'she', 'we', 'they', 'what', 'which', 'who', 'whom', 'how',
			'why', 'when', 'where', 'do', 'does', 'did', 'can', 'could', 'will', 'would',
			'should', 'has', 'have', 'had', 'about', 'items', 'product', 'page', 'post', // Keep content type words
			'?', '.', ',', '!', // Punctuation
		];
		
		$message_words = preg_split( '/\s+/', preg_replace('/[^\w\s]/', '', $message_lower) ?? '', -1, PREG_SPLIT_NO_EMPTY ); // Split and remove punctuation
		$keywords = array_diff( $message_words ?: [], $stop_words );
		
		error_log('WCAC DEBUG: User message: "' . $user_message . '"');
		error_log('WCAC DEBUG: Extracted keywords: ' . implode(', ', $keywords));
		
		if ( empty( $keywords ) && empty( $direct_matches ) ) {
			error_log('WCAC DEBUG: No significant keywords found after filtering and no direct title matches.');
			return []; // No significant keywords found.
		}

		// --- Content Scoring ---
		$scored_content = $direct_matches; // Start with direct matches
		$items_checked = count($direct_matches);
		$items_matched = count($direct_matches);
		// $category_match_boost = 50; // No longer needed with differential scoring

		// Define score weights for different field matches
		$score_weights = [
			'title' => 25,
			'category' => 20,
			'tag' => 15,
			'content' => 5, // For matches in description/excerpt etc.
		];

		foreach ( $content_index as $content_id => $content_data ) {
			// Skip if already matched directly by title
			if ( isset( $scored_content[ $content_id ] ) ) {
				continue;
			}

			$items_checked++;
			$current_score = 0;
			$match_found = false;
			$match_details = []; // For debugging

			if ( ! is_array( $content_data ) ) {
				continue; // Skip invalid data
			}

			// Prepare searchable strings (lowercase)
			$searchable_title = isset($content_data['title']) ? strtolower($content_data['title']) : '';
			$searchable_content = isset($content_data['content']) ? strtolower($content_data['content']) : '';
			$searchable_categories = isset($content_data['categories']) ? array_map('strtolower', $content_data['categories']) : [];
			$searchable_tags = isset($content_data['tags']) ? array_map('strtolower', $content_data['tags']) : [];
			$searchable_category_string = implode(' ', $searchable_categories); // Combine categories for easier searching
			$searchable_tag_string = implode(' ', $searchable_tags); // Combine tags

			foreach ( $keywords as $keyword ) {
				$keyword_matched_in_field = false; // Track if this specific keyword matched anywhere

				// Check Title
				if ( $searchable_title && str_contains( $searchable_title, $keyword ) ) {
					$current_score += $score_weights['title'];
					$match_details[] = $keyword . ' (title)';
					$keyword_matched_in_field = true;
				}

				// Check Categories (search combined string and individual names)
				if ( $searchable_category_string && str_contains( strtolower($searchable_category_string), strtolower($keyword) ) ) {
					// Add score only once per keyword per category check, even if multiple categories match
					$current_score += $score_weights['category'];
					$match_details[] = $keyword . ' (category)';
					$keyword_matched_in_field = true;
				}

				// Check Tags (search combined string and individual names)
				if ( $searchable_tag_string && str_contains( $searchable_tag_string, $keyword ) ) {
					// Add score only once per keyword per tag check
					$current_score += $score_weights['tag'];
					$match_details[] = $keyword . ' (tag)';
					$keyword_matched_in_field = true;
				}

				// Check Content (Description/Excerpt etc.) - only if not matched in more specific fields
				// This gives content matches the lowest priority if the same keyword appears elsewhere.
				if ( $searchable_content && str_contains( $searchable_content, $keyword ) ) {
					// Only add content score if the keyword wasn't found in title, category, or tag
					if (!in_array($keyword . ' (title)', $match_details) &&
						!in_array($keyword . ' (category)', $match_details) &&
						!in_array($keyword . ' (tag)', $match_details))
					{
						$current_score += $score_weights['content'];
						$match_details[] = $keyword . ' (content)';
						$keyword_matched_in_field = true;
					} else if (!$keyword_matched_in_field) {
						// If it matched in content BUT ALSO in title/cat/tag earlier,
						// we still need to mark it as matched overall, but don't add the content score again.
						$keyword_matched_in_field = true;
					}

				}

				if ( $keyword_matched_in_field ) {
					$match_found = true; // Mark that at least one keyword matched this item
				}
			} // End foreach keyword

			if ( $match_found && $current_score > 0 ) {
				$items_matched++;
				$scored_content[ $content_id ] = [
					'score' => $current_score,
					'data'  => $content_data,
					'match_type' => 'keyword (' . implode(', ', array_unique($match_details)) . ')', // Store matched fields for debug
				];
			}
		} // End foreach content_index

		error_log('WCAC DEBUG: Scoring complete. Items checked: ' . $items_checked . ', Items matched: ' . $items_matched . ', Direct title matches: ' . count($direct_matches) . ', Total scored: ' . count($scored_content));
		
		if ( empty( $scored_content ) ) {
			error_log('WCAC DEBUG: No content matched keywords or direct titles.');
			return [];
		}

		// Sort by score descending
		uasort( $scored_content, fn( $a, $b ) => $b['score'] <=> $a['score'] );

		// --- Context Assembly & Token Management ---
		$top_content = array_slice( $scored_content, 0, self::RAG_TOP_N_PRODUCTS, true ); // Use constant for N
		
		// Enhanced logging: log titles of top content
		$top_content_info = [];
		foreach ($top_content as $id => $item) {
			$item_title = is_array($item['data']) && isset($item['data']['title']) ? $item['data']['title'] : "Unknown";
			$item_type = is_array($item['data']) && isset($item['data']['type']) ? $item['data']['type'] : "unknown";
			$top_content_info[] = "{$id} ({$item_title}, type: {$item_type}, score: {$item['score']}, match: {$item['match_type']})";
		}
		error_log('WCAC DEBUG: Selected top ' . count($top_content) . ' items: ' . implode(', ', $top_content_info));
		
		$context_strings = [];
		$current_token_count = 0;

		// Estimate tokens for base instructions (adjust if prompt changes)
		$instruction_prompt = "Based on the following potentially relevant content from the website:\n\nPlease answer the user's question:\n\n";
		$instruction_token_estimate = (int) ceil( strlen( $instruction_prompt ) / 4 ); // Rough estimate

		foreach ( $top_content as $content_id => $scored_item ) {
			$content_data = $scored_item['data'];
			
			// Format the context string based on available data
			$formatted_item = [];
			$formatted_item[] = "Type: " . ucfirst($content_data['type'] ?? 'Unknown');
			$formatted_item[] = "Title: " . ($content_data['title'] ?? 'N/A');
			if (isset($content_data['price'])) { // Add price only if it exists (i.e., for products)
				$formatted_item[] = "Price: " . $content_data['price']; 
			}
			// Use the pre-formatted 'text' field for the main content snippet, but maybe truncate it further?
			// For now, let's just use the title, type, price. The LLM gets the full text in the index anyway implicitly.
			// Let's re-evaluate if we need more detail here. A shorter context string is better for token limits.
			// How about: Title, Type, URL, maybe first sentence of 'text'?
			$content_text_snippet = mb_substr( strstr($content_data['text'] ?? '', "\n", true) ?: ($content_data['text'] ?? ''), 0, 150); // Extract first line, limit length
            $formatted_item[] = "Info: " . str_replace("Title: ", "", $content_text_snippet); // Avoid repeating title
			if (isset($content_data['url'])) {
				$formatted_item[] = "URL: " . $content_data['url'];
			}
			
			$context_piece = implode("\n", array_filter($formatted_item));

			$piece_token_estimate = (int) ceil( strlen( $context_piece ) / 4 );

			if ( ( $current_token_count + $piece_token_estimate + $instruction_token_estimate ) <= $max_context_tokens ) {
				$context_strings[] = $context_piece;
				$current_token_count += $piece_token_estimate;
				error_log('WCAC DEBUG: Added content ID ' . $content_id . ' (' . ($content_data['title'] ?? 'N/A') . ') to context. Current token count: ' . $current_token_count);
			} else {
				// Log that an item was skipped due to token limits
				error_log( sprintf( 'WCAC RAG: Content ID %s (%s) skipped for user message due to token limit.', $content_id, ($content_data['title'] ?? 'N/A') ) );
				break; // Stop adding more items if limit reached
			}
		}

		return $context_strings;
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
			$retrieved_context_strings = $this->retrieve_relevant_content( $user_message, self::MAX_RAG_TOKENS );
			error_log('WCAC DEBUG: Retrieved context: ' . (empty($retrieved_context_strings) ? 'No context' : count($retrieved_context_strings) . ' items'));

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
			if ( ! empty( $retrieved_context_strings ) ) {
				error_log('WCAC DEBUG: Using context for prompt');
				$joined_context = implode( "\n---\n", $retrieved_context_strings );
				
				// Build prompt with straightforward string approach
				$prompt_content = "I'm looking for information. Based on the following potentially relevant content from the website:\n\n";
				$prompt_content .= $joined_context;
				$prompt_content .= "\n\n---\n+ User question: ";
				$prompt_content .= $user_message;
				$prompt_content .= "\n\nPlease answer my question directly using *only* the information provided above. \n1. Mention relevant details like titles and prices if available. \n2. When providing URLs, you MUST format them as markdown links using the Title provided in the context, like this: [Example Title](https://example.com/url). Do NOT use the URL as the link title.\n3. Use markdown lists (starting lines with * or -) or separate paragraphs (using double newlines) to structure your answer for readability.";
				
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
			
			$system_content .= "\n---\nIMPORTANT: Answer *only* based on information explicitly provided. Do not reference general knowledge about brands or products unless that information was given to you.";
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

			// 4. Send Success Response
			wp_send_json_success( [ 'reply' => trim( $bot_message ) ] ); // Send raw message directly
			
		} catch (Exception $e) {
			// Catch any exceptions that might occur
			error_log('WCAC FATAL ERROR: Exception in handle_send_message_ajax: ' . $e->getMessage());
			wp_send_json_error(['message' => esc_html__( 'Error: An unexpected error occurred.', 'wp-customer-ai-chatbot' )]);
		}
	}
} 