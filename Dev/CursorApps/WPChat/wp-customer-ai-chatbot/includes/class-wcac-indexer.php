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
		$counts = ['product' => 0, 'page' => 0, 'post' => 0, 'total' => 0];
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
				'post_status'    => 'publish',
				'posts_per_page' => -1, // Get all selected posts/pages/products
				'fields'         => 'ids', // Only get IDs for efficiency
				'has_password'   => false, // Exclude password protected posts/pages
				'tax_query'      => [ // Exclude hidden products
					[
						'taxonomy' => 'product_visibility',
						'field'    => 'slug', // Use slug instead of name
						'terms'    => ['exclude-from-catalog', 'exclude-from-search'],
						'operator' => 'NOT IN',
					],
				],
			];

			$post_ids = get_posts( $args );
			error_log('WCAC Indexer DEBUG: get_posts returned ' . count($post_ids) . ' IDs. Sample: [' . implode(', ', array_slice($post_ids, 0, 10)) . ']'); // Log count and sample IDs

			if ( empty( $post_ids ) ) {
                // No content found for selected types
                update_option( self::CONTENT_INDEX_KEY, [] );
                $this->update_index_meta( ['total' => 0] );
                return ['counts' => ['total' => 0], 'error' => null];
            }

			$known_hidden_ids = [99593, 98277]; // Add known hidden IDs here

			foreach ( $post_ids as $post_id ) {
				// Check if this ID is one of the known hidden ones
				if (in_array($post_id, $known_hidden_ids)) {
					error_log("WCAC Indexer DEBUG: Query returned known hidden product ID: {$post_id}. This should NOT happen if tax_query is working.");
				}

				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}

				$formatted_data = $this->format_content_for_llm( $post );
				if ( $formatted_data ) {
					$index[ $post_id ] = $formatted_data;
					$counts[ $post->post_type ]++;
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
	 * Updates a single post/page/product in the index if its type is selected in settings.
	 *
	 * @since 0.1.1
	 * @param int $post_id The ID of the post to update.
	 */
	public function update_single_content( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// --- Start Exclusion Checks ---
		// 1. Check if post type should be indexed at all
		$options = get_option( 'wcac_settings', [] );
		$should_index_type = false;
		if ($post->post_type === 'product' && !empty($options['wcac_index_products'])) $should_index_type = true;
		if ($post->post_type === 'page' && !empty($options['wcac_index_pages'])) $should_index_type = true;
		if ($post->post_type === 'post' && !empty($options['wcac_index_posts'])) $should_index_type = true;

		if (! $should_index_type) {
			// If this type is not indexed, ensure it's removed if it exists
			$index = get_option( self::CONTENT_INDEX_KEY, [] );
			if (isset($index[$post_id])) {
				error_log("WCAC Indexer Single Update: Removing Post ID {$post_id} because type '{$post->post_type}' is not selected for indexing.");
				unset($index[$post_id]);
				update_option(self::CONTENT_INDEX_KEY, $index, false);
				wp_cache_delete( self::CONTENT_INDEX_KEY, 'options' ); // Clear cache
				// Note: Meta count won't be updated here for performance, rely on full rebuilds.
			}
			return;
		}

		// 2. Check if post is published and not password protected
		if ( $post->post_status !== 'publish' || $post->post_password ) {
			// Post is deleted, not published, or password protected, remove from index
			$index = get_option( self::CONTENT_INDEX_KEY, [] );
			if (isset($index[$post_id])) {
				error_log("WCAC Indexer Single Update: Removing Post ID {$post_id} due to status '{$post->post_status}' or being password protected.");
				unset( $index[ $post_id ] );
				update_option( self::CONTENT_INDEX_KEY, $index, false );
				wp_cache_delete( self::CONTENT_INDEX_KEY, 'options' ); // Clear cache
			}
			return;
		}

		// 3. Check WooCommerce hidden visibility (if applicable)
		if ( $post->post_type === 'product' && taxonomy_exists('product_visibility') ) {
			$hidden_terms = [
				'exclude-from-catalog',
				'exclude-from-search'
			];
			if ( has_term( $hidden_terms, 'product_visibility', $post ) ) {
				// Product is hidden, remove from index
				$index = get_option( self::CONTENT_INDEX_KEY, [] );
				error_log("WCAC Indexer DEBUG [update_single_content]: has_term check returned TRUE for Post ID {$post_id}."); // Log has_term result
				if (isset($index[$post_id])) {
					error_log("WCAC Indexer Single Update: Removing Product ID {$post_id} because it has exclude-from-catalog or exclude-from-search visibility.");
					unset( $index[ $post_id ] );
					update_option( self::CONTENT_INDEX_KEY, $index, false );
					wp_cache_delete( self::CONTENT_INDEX_KEY, 'options' ); // Clear cache
				}
				return;
			}
		}
		// --- End Exclusion Checks ---

		// If all checks passed, format and update/add the content
		$index = get_option( self::CONTENT_INDEX_KEY, [] );

		// *** ADD DEBUG LOGGING HERE ***
		if (in_array($post_id, [99593, 98277])) {
			error_log("WCAC Indexer DEBUG [update_single_content]: Processing known hidden ID {$post_id}. Exclusion checks PASSED unexpectedly. Formatting and adding/updating.");
		}
		// *** END DEBUG LOGGING ***

		$formatted_data = $this->format_content_for_llm( $post );

		if ( $formatted_data ) {
			error_log("WCAC Indexer Single Update: Updating/Adding Post ID {$post_id}.");
			$index[ $post_id ] = $formatted_data;
		} else {
			// Formatting failed or returned empty, remove if exists
			error_log("WCAC Indexer Single Update: Removing Post ID {$post_id} because formatting failed.");
			unset( $index[ $post_id ] );
		}

		update_option( self::CONTENT_INDEX_KEY, $index, false );
		wp_cache_delete( self::CONTENT_INDEX_KEY, 'options' ); // Clear cache
        // Note: Meta count won't be updated here for performance, rely on full rebuilds.
	}

    /**
     * Updates the index metadata (counts and timestamp).
     *
     * @since 0.1.1
     * @param array $counts Array of counts per post type (e.g., ['product'=>10, 'page'=>5, 'total'=>15]).
     */
    private function update_index_meta( array $counts ): void {
        $meta = [
            'last_updated' => current_time( 'mysql' ),
            'counts' => [
				'product' => absint($counts['product'] ?? 0),
				'page'    => absint($counts['page'] ?? 0),
				'post'    => absint($counts['post'] ?? 0),
				'total'   => absint($counts['total'] ?? 0)
			]
        ];
        update_option( self::META_KEY, $meta, false );
    }

	/**
	 * Formats post data (product, page, post) into a structure suitable for LLM context.
	 *
	 * @since 0.1.1
	 * @param WP_Post $post WP_Post object.
	 * @return array|null Formatted data representation of the post, or null if invalid.
	 */
	private function format_content_for_llm( WP_Post $post ): ?array {
		$output_lines = [];
		$title = $post->post_title;
		$content_type = $post->post_type;
		$url = get_permalink( $post->ID );

		// Basic info
		$output_lines[] = "Title: {$title}";
		$output_lines[] = "Type: {$content_type}";
		if ($url) {
			$output_lines[] = "URL: {$url}";
		}

		// Content - Prioritize excerpt, then content
		$main_content = trim($post->post_excerpt);
		if (empty($main_content)) {
			$main_content = wp_strip_all_tags(strip_shortcodes($post->post_content));
		}
		$main_content = substr( $main_content, 0, 500 ); // Limit length
		if (!empty($main_content)) {
			$output_lines[] = "Content: " . preg_replace('/\s+/s', ' ', $main_content); // Normalize whitespace
		}

		$categories = [];
		$tags = [];
		$price = null;

		// Product specific data
		if ( $content_type === 'product' && class_exists('WooCommerce') ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				// Get Price
				$price = $product->get_price_html();
				if (!empty($price)) {
					$output_lines[] = "Price: " . wp_strip_all_tags($price);
				}

				// Get All Categories (Direct and Parents)
				$term_ids = wp_get_post_terms( $post->ID, 'product_cat', ['fields' => 'ids'] );
				$all_category_names = [];
				foreach ($term_ids as $term_id) {
					$term = get_term($term_id, 'product_cat');
					if ($term && !is_wp_error($term)) {
						$direct_cat_name = $term->name;
						$all_category_names[] = $direct_cat_name; // Add direct term name
						$parent_names_found = []; // Track parents for logging
						// Add parent term names recursively
						$parent_id = $term->parent;
						while ($parent_id != 0) {
							$parent_term = get_term($parent_id, 'product_cat');
							if ($parent_term && !is_wp_error($parent_term)) {
								$parent_name = $parent_term->name;
								$all_category_names[] = $parent_name;
								$parent_names_found[] = $parent_name;
								$parent_id = $parent_term->parent;
							} else {
								break; // Error or no parent found
							}
						}
						// Log the found categories for this term
						error_log("WCAC Indexer DEBUG [Post ID: {$post->ID}]: Term '{$direct_cat_name}' (ID: {$term_id}) - Found Parents: " . (!empty($parent_names_found) ? implode(', ', $parent_names_found) : 'None'));
					}
				}
				$categories = array_unique($all_category_names); // Remove duplicates
				error_log("WCAC Indexer DEBUG [Post ID: {$post->ID}]: Final unique categories stored: " . (!empty($categories) ? implode(', ', $categories) : 'None'));

				// Get Tags
				$tag_terms = get_the_terms( $post->ID, 'product_tag' );
				if ( ! empty( $tag_terms ) && ! is_wp_error( $tag_terms ) ) {
					$tags = wp_list_pluck( $tag_terms, 'name' );
				}
			}
		} elseif ($content_type === 'post') {
			// Get categories and tags for posts
			$post_categories = get_the_category($post->ID);
			if (!empty($post_categories) && !is_wp_error($post_categories)) {
				$categories = wp_list_pluck($post_categories, 'name');
			}
			$post_tags = get_the_tags($post->ID);
			if (!empty($post_tags) && !is_wp_error($post_tags)) {
				$tags = wp_list_pluck($post_tags, 'name');
			}
		} // No specific taxonomies fetched for pages currently

		if (!empty($categories)) {
			$output_lines[] = "Categories: " . implode(', ', $categories);
		}
		if (!empty($tags)) {
			$output_lines[] = "Tags: " . implode(', ', $tags);
		}

		$combined_text = implode("\n", $output_lines);

		// --- Apply Negative Keywords --- (Moved logic here, before final array creation)
		$settings = get_option( 'wcac_settings', [] );
		$negative_keywords = $this->prepare_negative_keywords($settings);
		if (!empty($negative_keywords)) {
			$combined_text = str_ireplace($negative_keywords, '', $combined_text);
			// Also apply to title, categories, tags before storing separately if needed for scoring?
			// For now, only applying to the main text blob sent to LLM.
			// Re-apply to individual fields stored for scoring:
			$title = str_ireplace($negative_keywords, '', $title);
			$categories = array_map(function($cat) use ($negative_keywords) {
				return str_ireplace($negative_keywords, '', $cat);
			}, $categories);
			$tags = array_map(function($tag) use ($negative_keywords) {
				return str_ireplace($negative_keywords, '', $tag);
			}, $tags);
			$main_content = str_ireplace($negative_keywords, '', $main_content);
			$price = $price ? str_ireplace($negative_keywords, '', $price) : null;
		}

		return [
			'title' => $title,
			'type' => $content_type,
			'url' => $url,
			'text' => $combined_text, // The full text blob for LLM context
			'content' => $main_content, // Shorter content for keyword scoring
			'categories' => array_values(array_filter($categories)), // Store cleaned categories for scoring
			'tags' => array_values(array_filter($tags)), // Store cleaned tags for scoring
			'price' => $price, // Store cleaned price
		];
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