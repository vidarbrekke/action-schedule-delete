<?php
declare(strict_types=1);

/**
 * Handles indexing of WooCommerce products for the chatbot.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes
 */
class Wcac_Indexer {

	/**
	 * Option key where the combined content index is stored.
	 *
	 * @since 0.1.1
	 * @var string
	 */
	const CONTENT_INDEX_KEY = 'wcac_content_index';

	/**
	 * Option key for index metadata (like last updated time and counts).
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const META_KEY = 'wcac_index_meta';

	/**
	 * Option key for storing the generated site profile.
	 *
	 * @since 0.1.2
	 * @var string
	 */
	const SITE_PROFILE_KEY = 'wcac_site_profile';

	/**
	 * Builds or rebuilds the entire content index based on settings.
	 *
	 * @since 0.1.1
	 * @return array An array containing 'counts' (array of counts per post type) and 'error' (if any).
	 */
	public function build_index(): array {
		error_log('WCAC DEBUG: Entering build_index method.');
		$index = [];
		$counts = ['product' => 0, 'page' => 0, 'post' => 0, 'product_variation' => 0, 'total' => 0];
		$error = null;

		try {
			// --- Generate and Save Site Profile --- (New in 0.1.2)
			error_log('WCAC DEBUG: Attempting to call generate_and_save_site_profile.');
			self::generate_and_save_site_profile();
		} catch ( \Throwable $e ) {
			error_log( 'WCAC Indexer Error: Exception during site profile generation - ' . $e->getMessage() . " on line " . $e->getLine() );
			// Continue with indexing even if profile generation fails
			$error = 'Site profile generation failed: ' . $e->getMessage();
		}

		try {
			// Get settings to determine which post types to index
			$options = get_option( 'wcac_settings', [] );
			error_log('WCAC Indexer: Reading settings - ' . json_encode($options)); // Log the options read
			$post_types_to_index = [];
			if ( ! empty( $options['wcac_index_products'] ) ) {
				$post_types_to_index[] = 'product';
				$post_types_to_index[] = 'product_variation'; // Also index variations if products are indexed
			}
			if ( ! empty( $options['wcac_index_pages'] ) ) {
				$post_types_to_index[] = 'page';
			}
			if ( ! empty( $options['wcac_index_posts'] ) ) {
				$post_types_to_index[] = 'post';
			}

			if ( empty( $post_types_to_index ) ) {
				error_log('WCAC Indexer: No content types selected for indexing in settings.');
				update_option( self::CONTENT_INDEX_KEY, [] );
				$this->update_index_meta( ['total' => 0] ); // Update meta with zero counts
				return ['counts' => ['total' => 0], 'error' => 'No content types selected'];
			}

			$args = [
				'post_type'      => $post_types_to_index,
				'post_status'    => 'publish', // Variations inherit status, this mainly applies to products/pages/posts
				'posts_per_page' => -1, // Get all selected items
				'fields'         => 'ids', // Only get IDs for efficiency
				'has_password'   => false, // Exclude password protected posts/pages
				// Note: tax_query for product_visibility only applies to 'product' type, not variations directly.
				// We'll need to check the parent product's visibility later for variations.
			];

			$post_ids = get_posts( $args );
			error_log('WCAC Indexer DEBUG: get_posts returned ' . count($post_ids) . ' IDs for types [' . implode(', ', $post_types_to_index) . ']. Sample: [' . implode(', ', array_slice($post_ids, 0, 10)) . ']');

			if ( empty( $post_ids ) ) {
				// No content found for selected types
				update_option( self::CONTENT_INDEX_KEY, [] );
				$this->update_index_meta( ['total' => 0] );
				return ['counts' => ['total' => 0], 'error' => null];
			}

			// Pre-fetch parent product statuses and visibility for variations to avoid repeated checks
			$parent_product_visibility = [];
			$parent_product_statuses = [];

			foreach ( $post_ids as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}

				// --- Visibility/Status Checks ---
				$is_variation = ($post->post_type === 'product_variation');
				$parent_id = $is_variation ? $post->post_parent : 0;

				// 1. Basic Status & Password Check
				if ($post->post_status !== 'publish' && !$is_variation) { // Variations inherit status but check anyway later if needed
					error_log("WCAC Indexer Filter: Skipping Post ID {$post_id} (type: {$post->post_type}) due to status: {$post->post_status}");
					continue;
				}
				if ($post->post_password) {
					error_log("WCAC Indexer Filter: Skipping Post ID {$post_id} (type: {$post->post_type}) due to password protection.");
					continue;
				}

				// 2. Parent Product Check (for Variations)
				if ($is_variation) {
					if ($parent_id <= 0) {
						error_log("WCAC Indexer Filter: Skipping Variation ID {$post_id} because it has no parent ID.");
						continue; // Skip orphan variations
					}

					// Check parent status (cache results)
					if (!isset($parent_product_statuses[$parent_id])) {
						$parent_product_statuses[$parent_id] = get_post_status($parent_id);
					}
					if ($parent_product_statuses[$parent_id] !== 'publish') {
						error_log("WCAC Indexer Filter: Skipping Variation ID {$post_id} because parent product ID {$parent_id} status is '{$parent_product_statuses[$parent_id]}'.");
						continue;
					}

					// Check parent visibility (cache results)
					if (!isset($parent_product_visibility[$parent_id])) {
						$parent_product_visibility[$parent_id] = true; // Assume visible unless excluded
						if (taxonomy_exists('product_visibility')) {
							$hidden_terms = ['exclude-from-catalog', 'exclude-from-search'];
							if (has_term($hidden_terms, 'product_visibility', $parent_id)) {
								$parent_product_visibility[$parent_id] = false;
							}
						}
					}
					if (!$parent_product_visibility[$parent_id]) {
						error_log("WCAC Indexer Filter: Skipping Variation ID {$post_id} because parent product ID {$parent_id} is hidden (exclude-from-catalog or exclude-from-search).");
						continue;
					}
				}
				// 3. Parent Product Check (for Products - this is redundant but safe)
				elseif ($post->post_type === 'product') {
					if (taxonomy_exists('product_visibility')) {
						$hidden_terms = ['exclude-from-catalog', 'exclude-from-search'];
						if (has_term($hidden_terms, 'product_visibility', $post_id)) {
							error_log("WCAC Indexer Filter: Skipping Product ID {$post_id} because it is hidden (exclude-from-catalog or exclude-from-search).");
							continue;
						}
					}
				}
				// --- End Visibility/Status Checks ---

				$formatted_data = $this->format_content_for_llm( $post );
				if ( $formatted_data ) {
					$index[ $post_id ] = $formatted_data;
					// Use null coalescing to safely increment counts
					$counts[$post->post_type] = ($counts[$post->post_type] ?? 0) + 1;
					$counts['total']++;
				}
			}

			// Store the combined index in wp_options
			update_option( self::CONTENT_INDEX_KEY, $index, false );
			wp_cache_delete( self::CONTENT_INDEX_KEY, 'options' ); // Clear cache
			$this->update_index_meta( $counts );

		} catch ( \Throwable $e ) {
			$error = 'Error during indexing: ' . $e->getMessage();
			error_log( 'WCAC Indexer Error: ' . $error );
			// Don't delete existing index on error
		}

		return ['counts' => $counts, 'error' => $error];
	}

	/**
	 * Updates a single post/page/product/variation in the index if its type is selected in settings.
	 *
	 * @since 0.1.1 (modified 0.1.3 for variations)
	 * @param int $post_id The ID of the post to update.
	 */
	public function update_single_content( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			$this->remove_content_from_index($post_id, "Post object not found."); // Ensure removal if post deleted
			return;
		}

		$is_variation = ($post->post_type === 'product_variation');
		$options = get_option( 'wcac_settings', [] );
		$index_products = !empty($options['wcac_index_products']);
		$index_pages = !empty($options['wcac_index_pages']);
		$index_posts = !empty($options['wcac_index_posts']);

		// --- Start Exclusion Checks ---

		// 1. Check if post type should be indexed based on settings
		$should_index_type = false;
		if ($post->post_type === 'product' && $index_products) $should_index_type = true;
		if ($post->post_type === 'product_variation' && $index_products) $should_index_type = true; // Variations depend on product setting
		if ($post->post_type === 'page' && $index_pages) $should_index_type = true;
		if ($post->post_type === 'post' && $index_posts) $should_index_type = true;

		if (! $should_index_type) {
			$this->remove_content_from_index($post_id, "Type '{$post->post_type}' is not selected for indexing.");
			return;
		}

		// 2. Basic Status & Password Check
		// Variations have 'publish' status but rely on parent status and their own 'enabled' state.
		if ($post->post_status !== 'publish' && !$is_variation) {
			$this->remove_content_from_index($post_id, "Status is '{$post->post_status}'.");
			return;
		}
		if ($post->post_password) {
			$this->remove_content_from_index($post_id, "Is password protected.");
			return;
		}

		// 3. WooCommerce Visibility Checks
		if ( $post->post_type === 'product' && taxonomy_exists('product_visibility') ) {
			$hidden_terms = ['exclude-from-catalog', 'exclude-from-search'];
			if ( has_term( $hidden_terms, 'product_visibility', $post ) ) {
				$this->remove_content_from_index($post_id, "Product has exclude-from-catalog or exclude-from-search visibility.");
				// Also remove its variations if the parent product becomes hidden
				if (class_exists('WooCommerce')) {
					$product = wc_get_product($post_id);
					if ($product && $product->is_type('variable')) {
						$variation_ids = $product->get_children();
						foreach ($variation_ids as $variation_id) {
							$this->remove_content_from_index($variation_id, "Parent product ID {$post_id} became hidden.");
						}
					}
				}
				return;
			}
		}

		// 4. Variation Specific Checks
		if ($is_variation) {
			$parent_id = $post->post_parent;
			if ($parent_id <= 0) {
				$this->remove_content_from_index($post_id, "Variation has no parent ID.");
				return; // Orphan variation
			}

			// Check parent status and visibility
			$parent_post = get_post($parent_id);
			if (!$parent_post || $parent_post->post_status !== 'publish') {
				$this->remove_content_from_index($post_id, "Parent product ID {$parent_id} is not published.");
				return;
			}
			if (taxonomy_exists('product_visibility')) {
				$hidden_terms = ['exclude-from-catalog', 'exclude-from-search'];
				if (has_term($hidden_terms, 'product_visibility', $parent_id)) {
					$this->remove_content_from_index($post_id, "Parent product ID {$parent_id} is hidden.");
					return;
				}
			}

			// Check if variation itself is enabled (WooCommerce specific)
			if (class_exists('WooCommerce')) {
				$variation_obj = wc_get_product($post_id);
				// Variation might be deleted or disabled
				if (!$variation_obj || !$variation_obj->is_purchasable() || $variation_obj->get_status() !== 'publish') {
					// get_status might be 'private' if disabled
					$reason = !$variation_obj ? "Variation object not found (maybe deleted)." :
							  (!$variation_obj->is_purchasable() ? "Variation is not purchasable." :
							  "Variation status is '{$variation_obj->get_status()}'.");
					$this->remove_content_from_index($post_id, $reason);
					return;
				}
			}
		}
		// --- End Exclusion Checks ---


		// If all checks passed, format and update/add the content
		$index = get_option( self::CONTENT_INDEX_KEY, [] );
		$formatted_data = $this->format_content_for_llm( $post );

		if ( $formatted_data ) {
			error_log("WCAC Indexer Single Update: Updating/Adding Post ID {$post_id} (Type: {$post->post_type}).");
			$index[ $post_id ] = $formatted_data;
			update_option( self::CONTENT_INDEX_KEY, $index, false );
			wp_cache_delete( self::CONTENT_INDEX_KEY, 'options' ); // Clear cache
		} else {
			// Formatting failed or returned empty, remove if exists
			$this->remove_content_from_index($post_id, "Formatting failed or returned empty.");
		}

		// Note: Meta count won't be updated here for performance, rely on full rebuilds.
	}

	/**
	 * Helper function to remove an item from the index and log the reason.
	 *
	 * @since 0.1.3
	 * @param int    $post_id The ID of the post/variation to remove.
	 * @param string $reason  The reason for removal (for logging).
	 */
	private function remove_content_from_index(int $post_id, string $reason): void {
		$index = get_option( self::CONTENT_INDEX_KEY, [] );
		if (isset($index[$post_id])) {
			error_log("WCAC Indexer Remove: Removing Post ID {$post_id}. Reason: {$reason}");
			unset( $index[ $post_id ] );
			update_option( self::CONTENT_INDEX_KEY, $index, false );
			wp_cache_delete( self::CONTENT_INDEX_KEY, 'options' ); // Clear cache
		}
	}

	/**
	 * Updates the index metadata (counts and timestamp).
	 *
	 * @since 0.1.1 (modified 0.1.3 for variations)
	 * @param array $counts Array of counts per post type (e.g., ['product'=>10, 'page'=>5, 'total'=>15]).
	 */
	private function update_index_meta( array $counts ): void {
		$meta = [
			'last_updated' => current_time( 'mysql' ),
			'counts' => [
				'product'           => absint($counts['product'] ?? 0),
				'page'              => absint($counts['page'] ?? 0),
				'post'              => absint($counts['post'] ?? 0),
				'product_variation' => absint($counts['product_variation'] ?? 0), // Add variation count
				'total'             => absint($counts['total'] ?? 0)
			]
		];
		update_option( self::META_KEY, $meta, false );
	}

	/**
	 * Formats post data (product, variation, page, post) into a structure suitable for LLM context.
	 *
	 * @since 0.1.1 (modified 0.1.3 for variations)
	 * @param WP_Post $post WP_Post object for the item to format.
	 * @return array|null Formatted data representation of the post, or null if invalid.
	 */
	private function format_content_for_llm( WP_Post $post ): ?array {
		$output_lines = [];
		$title = $post->post_title; // Base title
		$content_type = $post->post_type;
		$post_id = $post->ID;
		$url = null;
		$parent_id = $post->post_parent;
		$parent_product = null; // For variations

		$categories = [];
		$tags = [];
		$regular_price = null;
		$sale_price = null;
		$on_sale = false;
		$attributes = []; // For variations
		$main_content = ''; // Initialize main content

		// --- Handle Variations ---
		if ( $content_type === 'product_variation' && class_exists('WooCommerce') && $parent_id > 0 ) {
			$variation = wc_get_product( $post_id );
			$parent_product = wc_get_product( $parent_id );

			if ( !$variation || !$parent_product ) {
				error_log("WCAC Indexer Format Error: Could not get variation or parent product object for variation ID {$post_id}.");
				return null; // Cannot format without WC objects
			}

			// ** Crucial Fix: Get sale status and prices DIRECTLY from the variation object **
			$regular_price = $variation->get_regular_price();
			$sale_price = $variation->get_sale_price();
			$on_sale = $variation->is_on_sale();
			error_log("WCAC Indexer Format DEBUG [Variation ID: {$post_id}]: Fetched variation sale status: " . ($on_sale ? 'YES' : 'NO') . ", Sale Price: {$sale_price}, Regular Price: {$regular_price}");


			// Use parent title as base, append attributes
			$title = $parent_product->get_name();
			$variation_attributes_raw = $variation->get_variation_attributes(false); // Get variation attributes (slugs)
			$attribute_strings = [];
			// Ensure attributes is an array before iterating
            if (is_array($variation_attributes_raw)) {
                foreach ($variation_attributes_raw as $taxonomy => $term_slug) {
                    if (empty($term_slug)) continue; // Skip empty attribute slugs which can happen
                    $term = get_term_by('slug', $term_slug, $taxonomy);
                    $term_name = $term ? $term->name : $term_slug; // Get term name, fallback to slug
                    $attribute_label = wc_attribute_label($taxonomy, $parent_product); // Get nice label
                    $attribute_strings[] = $attribute_label . ': ' . $term_name;
                    $title .= ' - ' . $term_name; // Append attribute term name to title
					// Store attribute label: slug pair for potential scoring later if needed
					$attributes[$attribute_label] = $term_slug;
                }
            }
			if (!empty($attribute_strings)) {
				$output_lines[] = "Attributes: " . implode(', ', $attribute_strings);
			}


			// Get content: Use parent's description/excerpt if variation's is empty
			$main_content = trim(wp_strip_all_tags(strip_shortcodes($variation->get_description())));
			if (empty($main_content)) {
				$main_content = trim($parent_product->get_short_description()); // Try parent excerpt first
				if (empty($main_content)) {
					$main_content = wp_strip_all_tags(strip_shortcodes($parent_product->get_description())); // Then parent content
				}
			}

			// Get categories and tags from parent
			$parent_term_ids = wp_get_post_terms( $parent_id, 'product_cat', ['fields' => 'ids'] );
			$categories = $this->get_all_category_names($parent_term_ids, $parent_id);
			$tag_terms = get_the_terms( $parent_id, 'product_tag' );
			if ( ! empty( $tag_terms ) && ! is_wp_error( $tag_terms ) ) {
				$tags = wp_list_pluck( $tag_terms, 'name' );
			}

			$url = $parent_product->get_permalink(); // Variation uses parent URL

		}
		// --- Handle Simple/Variable Products ---
		elseif ( $content_type === 'product' && class_exists('WooCommerce') ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				// Only index variable products if they have children, otherwise skip parent entirely
				if ($product->is_type('variable')) { // && !$product->has_child()) { <-- Removing this check, index parent even if no variations? Revisit. Let's skip parent for now.
					error_log("WCAC Indexer Format: Skipping variable product parent ID {$post_id}. Variations are indexed separately.");
					return null; // Skip indexing the parent variable product itself
				}
				// If it's not variable, it must be simple/other type we handle
				// Get Prices and Sale Status for simple products
				$regular_price = $product->get_regular_price();
				$sale_price = $product->get_sale_price();
				$on_sale = $product->is_on_sale();


				// Content: Use excerpt first, then description
				$main_content = trim($product->get_short_description());
				if (empty($main_content)) {
					$main_content = wp_strip_all_tags(strip_shortcodes($product->get_description()));
				}

				// Get Categories and Tags
				$term_ids = wp_get_post_terms( $post_id, 'product_cat', ['fields' => 'ids'] );
				$categories = $this->get_all_category_names($term_ids, $post_id);
				$tag_terms = get_the_terms( $post_id, 'product_tag' );
				if ( ! empty( $tag_terms ) && ! is_wp_error( $tag_terms ) ) {
					$tags = wp_list_pluck( $tag_terms, 'name' );
				}

				$url = $product->get_permalink();
			} else {
				error_log("WCAC Indexer Format Error: Could not get product object for product ID {$post_id}.");
				return null;
			}
		}
		// --- Handle Pages & Posts ---
		elseif ($content_type === 'page' || $content_type === 'post') {
			// Content - Prioritize excerpt, then content
			$main_content = trim($post->post_excerpt);
			if (empty($main_content)) {
				$main_content = wp_strip_all_tags(strip_shortcodes($post->post_content));
			}
			$url = get_permalink( $post->ID );

			// Get taxonomies for posts
			if ($content_type === 'post') {
				$post_categories = get_the_category($post->ID);
				if (!empty($post_categories) && !is_wp_error($post_categories)) {
					$categories = wp_list_pluck($post_categories, 'name');
				}
				$post_tags = get_the_tags($post->ID);
				if (!empty($post_tags) && !is_wp_error($post_tags)) {
					$tags = wp_list_pluck($post_tags, 'name');
				}
			}
		}
		// --- Unknown Type ---
		else {
			error_log("WCAC Indexer Format Error: Unhandled post type '{$content_type}' for post ID {$post_id}.");
			return null;
		}

		// --- Assemble Common Output ---
		$output_lines[] = "Title: {$title}";
		$output_lines[] = "Type: {$content_type}"; // Keep original type (product, product_variation, page, post)
		if ($url) {
			$output_lines[] = "URL: {$url}";
		}

		// Add prices for variations and simple products
		if (($content_type === 'product_variation' || ($content_type === 'product' && !$product->is_type('variable'))) && $regular_price) {
			$output_lines[] = "Regular Price: " . wc_price($regular_price); // Format price
		}
		if (($content_type === 'product_variation' || ($content_type === 'product' && !$product->is_type('variable'))) && $on_sale && $sale_price) {
			$output_lines[] = "Sale Price: " . wc_price($sale_price); // Format price
			$output_lines[] = "Status: ON SALE";
		} elseif ($on_sale) {
			// If on_sale is true but no specific sale price (e.g., parent variable product), keep the status message
			// This was potentially added earlier for variable products
			if (!in_array("Status: Some variations MAY be ON SALE", $output_lines)) {
				 $output_lines[] = "Status: ON SALE"; // Should ideally only happen for variations now
			}
		}

		// Add main content snippet
		$main_content_limited = substr( $main_content, 0, 500 ); // Limit length
		if (!empty($main_content_limited)) {
			$output_lines[] = "Content: " . preg_replace('/\s+/s', ' ', $main_content_limited); // Normalize whitespace
		}

		// Add categories and tags
		if (!empty($categories)) {
			$output_lines[] = "Categories: " . implode(', ', $categories);
		}
		if (!empty($tags)) {
			$output_lines[] = "Tags: " . implode(', ', $tags);
		}

		// Combine lines into the text blob for LLM
		$combined_text = implode("\n", $output_lines);

		// --- Apply Negative Keywords ---
		$settings = get_option( 'wcac_settings', [] );
		$negative_keywords = $this->prepare_negative_keywords($settings);
		if (!empty($negative_keywords)) {
			$combined_text = str_ireplace($negative_keywords, '', $combined_text);
			// Re-apply to individual fields stored for scoring:
			$title = str_ireplace($negative_keywords, '', $title);
			$categories = array_map(fn($cat) => str_ireplace($negative_keywords, '', $cat), $categories);
			$tags = array_map(fn($tag) => str_ireplace($negative_keywords, '', $tag), $tags);
			$main_content_limited = str_ireplace($negative_keywords, '', $main_content_limited);
			// Prices are numerical, skip them
			// Apply to attribute keys/values? Attributes array stores 'Label' => 'slug'
            $cleaned_attributes = [];
            foreach ($attributes as $label => $slug) {
                $cleaned_label = str_ireplace($negative_keywords, '', $label);
                $cleaned_slug = str_ireplace($negative_keywords, '', $slug);
                if (!empty($cleaned_label) && !empty($cleaned_slug)) {
                     $cleaned_attributes[$cleaned_label] = $cleaned_slug;
                }
            }
            $attributes = $cleaned_attributes;
		}

		// Ensure we don't return null prices, use empty string maybe? Or keep null? Let's keep null.
        $regular_price = !empty($regular_price) ? $regular_price : null;
        $sale_price = !empty($sale_price) ? $sale_price : null;


		$result = [
			'title' => $title, // Will include variation attributes
			'type' => $content_type,
			'url' => $url,
			'text' => $combined_text, // The full text blob for LLM context
			'content' => $main_content_limited, // Shorter content for keyword scoring
			'categories' => array_values(array_filter($categories)), // Store cleaned categories for scoring
			'tags' => array_values(array_filter($tags)), // Store cleaned tags for scoring
			'attributes' => $attributes, // Store cleaned attributes Label => Slug map
			'regular_price' => $regular_price, // Store numeric price or null
			'sale_price' => $sale_price, // Store numeric price or null
			'on_sale' => $on_sale, // Store boolean
			'parent_id' => $parent_id > 0 ? $parent_id : null, // Store parent ID for variations
		];
		error_log("WCAC Indexer Format DEBUG [{$content_type} ID: {$post_id}]: Storing data: " . json_encode($result));
		return $result;
	}

	/**
	 * Helper function to get all category names, including parents.
	 *
	 * @since 0.1.3
	 * @param array $term_ids Array of direct term IDs.
	 * @param int $post_id The post ID (for logging context).
	 * @param string $taxonomy The taxonomy slug (default 'product_cat').
	 * @return array Unique list of all category names (direct and parents).
	 */
	private function get_all_category_names(array $term_ids, int $post_id, string $taxonomy = 'product_cat'): array {
		$all_category_names = [];
		
		// First collect all direct category names and their parents
		foreach ($term_ids as $term_id) {
			$term = get_term($term_id, $taxonomy);
			if ($term && !is_wp_error($term)) {
				$direct_cat_name = $term->name;
				$all_category_names[] = $direct_cat_name; // Add direct term name
				$parent_names_found = []; // Track parents for logging
				
				// Add parent term names recursively
				$parent_id_term = $term->parent;
				while ($parent_id_term != 0) {
					$parent_term = get_term($parent_id_term, $taxonomy);
					if ($parent_term && !is_wp_error($parent_term)) {
						$parent_name = $parent_term->name;
						$all_category_names[] = $parent_name;
						$parent_names_found[] = $parent_name;
						$parent_id_term = $parent_term->parent;
					} else {
						break; // Error or no parent found
					}
				}
				// Log the found categories for this term
				error_log("WCAC Indexer DEBUG [Post ID: {$post_id}]: Term '{$direct_cat_name}' (ID: {$term_id}) - Found Parents: " . (!empty($parent_names_found) ? implode(', ', $parent_names_found) : 'None'));
			}
		}

		// Now add the main "Yarn" category explicitly if any category contains "Yarn" in its name
		// This ensures products in yarn-related categories also match "yarn" search
		$yarn_category_match = false;
		foreach ($all_category_names as $cat_name) {
			if (stripos($cat_name, 'yarn') !== false) {
				$yarn_category_match = true;
				break;
			}
		}
		
		// Add the main "Yarn" category if this is a yarn-related product
		if ($yarn_category_match) {
			// Try to get the main Yarn category
			$yarn_terms = get_terms([
				'taxonomy' => $taxonomy,
				'name' => 'Yarn',
				'hide_empty' => false,
				'number' => 1
			]);
			
			if (!is_wp_error($yarn_terms) && !empty($yarn_terms)) {
				// Add the main Yarn category name
				$all_category_names[] = 'Yarn';
				error_log("WCAC Indexer DEBUG [Post ID: {$post_id}]: Added main 'Yarn' category to yarn-related product");
			}
		}
		
		$unique_names = array_unique($all_category_names);
		error_log("WCAC Indexer DEBUG [Post ID: {$post_id}]: Final unique categories stored: " . (!empty($unique_names) ? implode(', ', $unique_names) : 'None'));
		return $unique_names;
	}

	/**
	 * Generates a site profile summary and saves it to options.
	 *
	 * @since 0.1.2
	 * @throws Exception If fetching data fails critically.
	 */
	private function generate_and_save_site_profile(): void {
		error_log('WCAC DEBUG: Entering generate_and_save_site_profile method.');
		error_log('WCAC Indexer: Generating site profile...');
		$profile_parts = [];

		// Site Info
		$site_name = get_bloginfo('name');
		$site_description = get_bloginfo('description');
		if ($site_name) $profile_parts[] = "Site Name: {$site_name}";
		if ($site_description) $profile_parts[] = "Description: {$site_description}";

		// About Pages (Simple Title Match)
		$about_pages = get_pages(['post_title_like' => 'About', 'post_status' => 'publish', 'number' => 3]); // Limit results
		if (!empty($about_pages)) {
			$about_links = [];
			foreach($about_pages as $page) {
				$about_links[] = sprintf("%s (%s)", $page->post_title, get_permalink($page->ID));
			}
			$profile_parts[] = "About Pages: " . implode(', ', $about_links);
		}

		// Main Navigation Menu (First Found)
		$menu_locations = get_nav_menu_locations();
		$primary_menu_id = $menu_locations['primary'] ?? ($menu_locations['main'] ?? ($menu_locations['header'] ?? null)); // Common theme locations
		if ($primary_menu_id) {
			$menu_items = wp_get_nav_menu_items($primary_menu_id);
			if (!empty($menu_items)) {
				$menu_structure = [];
				// Simple list for now, could be hierarchical later
				foreach($menu_items as $item) {
					if (empty($item->menu_item_parent)) { // Top level only
						$menu_structure[] = $item->title;
					}
				}
				$profile_parts[] = "Main Menu Items: " . implode(', ', $menu_structure);
			}
		}

		// Top-Level Product Categories
		if (taxonomy_exists('product_cat')) {
			$product_cats = get_terms(['taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'number' => 10]); // Limit
			if (!is_wp_error($product_cats) && !empty($product_cats)) {
				$profile_parts[] = "Main Product Categories: " . implode(', ', wp_list_pluck($product_cats, 'name'));
			}
		}

		// Top-Level Post Categories
		$post_cats = get_terms(['taxonomy' => 'category', 'parent' => 0, 'hide_empty' => true, 'number' => 10]); // Limit
		if (!is_wp_error($post_cats) && !empty($post_cats)) {
			$profile_parts[] = "Main Post Categories: " . implode(', ', wp_list_pluck($post_cats, 'name'));
		}

		$site_profile_text = implode("\n", array_filter($profile_parts));

		if (empty($site_profile_text)) {
			error_log('WCAC Indexer: Could not generate meaningful site profile content.');
			update_option(self::SITE_PROFILE_KEY, ''); // Save empty string
		} else {
			$update_success = update_option(self::SITE_PROFILE_KEY, $site_profile_text);
			if ($update_success) {
				error_log('WCAC Indexer: Successfully generated and saved site profile.');
				error_log('WCAC Profile Content: ' . $site_profile_text);
			} else {
				error_log('WCAC Indexer Error: Failed to save site profile to options table.');
			}
		}
	}

	/**
	 * Prepare negative keywords from settings.
	 *
	 * @since 0.1.1
	 * @param array $settings Plugin settings.
	 * @return array List of negative keywords.
	 */
	private function prepare_negative_keywords(array $settings): array {
		$keywords_raw = $settings['wcac_negative_keywords'] ?? '';
		if (empty($keywords_raw)) {
			return [];
		}
		// Split by newline, trim whitespace, remove empty lines, convert to lowercase for case-insensitive matching
		return array_filter(array_map('trim', explode("\n", strtolower($keywords_raw))));
	}
} 