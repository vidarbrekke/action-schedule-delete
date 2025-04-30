<?php
declare(strict_types=1);

/**
 * This file is intended to be run within the WordPress environment.
 * All referenced functions such as get_post, get_post_status, taxonomy_exists, has_term, wc_get_product
 * are provided by WordPress core or WooCommerce and are expected to be available.
 */

// At the top, add import for the new rules class
require_once __DIR__ . '/class-wcac-chatbot-rules.php';

// Ensure this file is only used in a WordPress environment
if (!function_exists('get_post')) {
    exit('This file must be used within WordPress.');
}

/**
 * Handles indexing of WooCommerce products for the chatbot.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes
 */
class Wcac_Indexer {

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
	 * Processes a batch of posts for the index based on settings.
	 * Does NOT fetch posts itself, relies on caller providing IDs.
	 * Handles table truncation and site profile generation only on the first batch.
	 *
	 * @since 0.1.5 (Refactored for Batch Processing)
	 * @param array<int> $post_ids_batch Array of post IDs to process in this batch.
	 * @param bool       $is_first_batch True if this is the very first batch (triggers truncation etc.).
	 * @return array An array containing 'processed_in_batch' (int), 'errors_in_batch' (int), 'last_error_message' (string|null).
	 */
	public function build_index( array $post_ids_batch, bool $is_first_batch ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcac_index';
		error_log("WCAC Indexer Batch: Processing batch. First Batch: " . ($is_first_batch ? 'Yes' : 'No') . ". Batch Size: " . count($post_ids_batch));

		$processed_count = 0;
		$error_count = 0;
		$last_error_message = null;

		// --- Initial Setup for First Batch Only ---
		if ( $is_first_batch ) {
			// Generate Site Profile
			try {
				// error_log('WCAC DEBUG: Attempting to call generate_and_save_site_profile (First Batch).'); // REMOVE DEBUG LOG
				self::generate_and_save_site_profile();
			} catch (Throwable $e) {
				$profile_error = 'Site profile generation failed: ' . $e->getMessage();
				error_log( 'WCAC Indexer Error: Exception during site profile generation - ' . $profile_error );
				// Don't stop indexing for profile error, but maybe log it prominently
				if (!$last_error_message) $last_error_message = $profile_error;
			}

			// Clear Existing Index Table
			try {
				error_log("WCAC Indexer Batch: Clearing existing data from table '{$table_name}' (First Batch).");
				$wpdb->query("TRUNCATE TABLE {$table_name}");
				if (!empty($wpdb->last_error)) {
					throw new Exception("Failed to truncate table. DB Error: " . $wpdb->last_error);
				}
			} catch (Throwable $e) {
				$truncate_error = 'Error clearing index table: ' . $e->getMessage();
				error_log('WCAC Indexer Error: ' . $truncate_error);
				// If truncate fails, we cannot proceed with indexing.
				return ['processed_in_batch' => 0, 'errors_in_batch' => 1, 'last_error_message' => $truncate_error];
			}
		}

		// --- Process and Insert Each Item in the Batch ---
		$parent_product_visibility = []; // Cache for visibility lookups within the batch
		$parent_product_statuses = [];

		foreach ( $post_ids_batch as $post_id ) {
			// Check execution time periodically within the loop to potentially prevent silent timeouts
            // Note: set_time_limit might not work in safe mode or if disabled by hosting.
            @set_time_limit(300); // Reset timer for each item (if possible)

			$post = get_post( $post_id );
			if ( ! $post ) {
				error_log("WCAC Indexer Batch: Skipped Post ID {$post_id} - Post object not found.");
				continue; // Skip this post, don't count as error for now
			}

			// Basic check if we should even attempt formatting based on type (already filtered by get_posts in caller)
			if (!in_array($post->post_type, ['product', 'product_variation', 'page', 'post'])) {
				error_log("WCAC Indexer Batch: Skipped Post ID {$post_id} - Invalid post type '{$post->post_type}'.");
				continue;
			}

			// Visibility/Status Checks (Keep this logic, adapted slightly)
            try {
                $should_skip = false;
                $is_variation = ($post->post_type === 'product_variation');
				$parent_id_check = $is_variation ? $post->post_parent : 0;

                if ($post->post_status !== 'publish' && !$is_variation) { $should_skip = true; }
				if ($post->post_password) { $should_skip = true; }
				if ($is_variation) {
					if ($parent_id_check <= 0) { $should_skip = true; }
					if (!$should_skip && !isset($parent_product_statuses[$parent_id_check])) { $parent_product_statuses[$parent_id_check] = get_post_status($parent_id_check); }
					if (!$should_skip && $parent_product_statuses[$parent_id_check] !== 'publish') { $should_skip = true; }
					if (!$should_skip && !isset($parent_product_visibility[$parent_id_check])) {
						$parent_product_visibility[$parent_id_check] = true; // Assume visible unless proven otherwise
						if (taxonomy_exists('product_visibility')) {
							$hidden_terms = ['exclude-from-catalog', 'exclude-from-search'];
							if (has_term($hidden_terms, 'product_visibility', $parent_id_check)) {
								$parent_product_visibility[$parent_id_check] = false;
							}
						}
					}
					if (!$should_skip && !$parent_product_visibility[$parent_id_check]) { $should_skip = true; }
				}
				elseif ($post->post_type === 'product') {
					if (taxonomy_exists('product_visibility')) {
						$hidden_terms = ['exclude-from-catalog', 'exclude-from-search'];
						if (has_term($hidden_terms, 'product_visibility', $post_id)) { $should_skip = true; }
					}
                    // Also skip parent variable products - variations are handled separately
                    if (!$should_skip && class_exists('WooCommerce')) {
                        $product_obj = wc_get_product($post_id);
                        if ($product_obj && $product_obj->is_type('variable')) {
                             error_log("WCAC Indexer Batch: Skipped Post ID {$post_id} - Parent variable product (handled by variations).");
                             $should_skip = true;
                        }
                    }
				}

                if ($should_skip) {
                    // If skipping, ensure it's removed from index in case it was indexed before
                    $this->remove_content_from_index($post_id, "Skipped during batch build due to visibility/status.");
                    continue; // Move to next post in batch
                }
            } catch (Throwable $vis_error) {
                error_log("WCAC Indexer Batch Error (Visibility Check) for Post ID {$post_id}: " . $vis_error->getMessage());
                $error_count++;
				$last_error_message = "Visibility check error for Post ID {$post_id}: " . $vis_error->getMessage();
                continue; // Skip this item due to error
            }
            // --- End Visibility/Status Checks ---


			// Format data
            $formatted_data = null;
            try {
			    $formatted_data = $this->format_content_for_llm( $post );
            } catch (Throwable $format_error) {
                error_log("WCAC Indexer Batch Error (Formatting) for Post ID {$post_id}: " . $format_error->getMessage());
				$error_count++;
				$last_error_message = "Formatting error for Post ID {$post_id}: " . $format_error->getMessage();
                continue; // Skip this item due to formatting error
            }

			if ( $formatted_data ) {
				// Prepare data for insertion
				$insert_data = [
					'post_id'         => $post_id,
					'post_type'       => $formatted_data['type'],
					'title'           => $formatted_data['title'],
					'content_snippet' => $formatted_data['content'],
					'url'             => $formatted_data['url'],
					'categories'      => !empty($formatted_data['categories']) ? wp_json_encode($formatted_data['categories']) : null, // Use wp_json_encode
					'tags'            => !empty($formatted_data['tags']) ? wp_json_encode($formatted_data['tags']) : null, // Use wp_json_encode
					'regular_price'   => $formatted_data['regular_price'],
					'sale_price'      => $formatted_data['sale_price'],
					'on_sale'         => $formatted_data['on_sale'] ? 1 : 0,
					'parent_id'       => $formatted_data['parent_id'],
				];
				$formats = [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ]; // Matched to insert_data

				// Log data types and lengths before replace (Keep for debugging if needed)
				// $log_data_info = [];
				// foreach ($insert_data as $key => $value) { $log_data_info[$key] = 'Type: ' . gettype($value) . ', Length: ' . (is_string($value) ? strlen($value) : 'N/A'); }
				// error_log("WCAC Indexer Pre-Replace Info [Post ID: {$post_id}]: " . json_encode($log_data_info)); // REMOVE DEBUG LOG

				try {
					// Use $wpdb->replace
					$replace_result = $wpdb->replace($table_name, $insert_data, $formats);

					if ($replace_result === false) {
						// Log actual DB error if replace returns false
						$db_error = $wpdb->last_error ?: 'Unknown DB error (replace returned false)';
						error_log("WCAC Indexer Batch DB Failure: Post ID {$post_id} caused replace failure. WPDB Error: '{$db_error}'");
						$error_count++;
						$last_error_message = "DB replace failed for Post ID {$post_id}: " . $db_error;
						// Continue processing the rest of the batch despite the error
					} else {
						// Only increment processed count on successful DB operation
						$processed_count++;
					}
				} catch (Throwable $e) {
					// Catch fatal errors during the DB operation itself
					error_log("WCAC Indexer Batch EXCEPTION during replace for Post ID {$post_id}: " . $e->getMessage());
					// error_log("WCAC Indexer Batch EXCEPTION Trace: " . $e->getTraceAsString()); // Keep trace if needed, can be long
					$error_count++;
					$last_error_message = 'DB exception during replace for Post ID {$post_id}: ' . $e->getMessage();
					// Continue processing the rest of the batch
				}
			} else {
                 error_log("WCAC Indexer Batch: Skipped Post ID {$post_id} - Formatting returned null or empty.");
                 // Ensure it's removed if formatting fails or post becomes invalid
                 $this->remove_content_from_index($post_id, "Formatting failed or post invalid during batch build.");
            } // end if formatted_data
		} // end foreach post_ids_batch

		error_log("WCAC Indexer Batch: Finished processing batch. Processed: {$processed_count}, Errors: {$error_count}.");

		// Note: We don't update the overall index meta here. The caller (AJAX handler) manages progress.
		return [
			'processed_in_batch' => $processed_count,
			'errors_in_batch' => $error_count,
			'last_error_message' => $last_error_message
		];
	} // End build_index (refactored)

	/**
	 * Updates a single post/page/product/variation in the index table.
     * (This function remains largely unchanged, used for save_post hook)
	 *
	 * @since 0.1.1 (Modified 0.1.4 to use custom table)
	 * @param int $post_id The ID of the post to update.
	 */
	public function update_single_content( int $post_id ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcac_index';

		$post = get_post( $post_id );
		if ( ! $post ) {
			$this->remove_content_from_index($post_id, "Post object not found.");
			return;
		}

		// --- Exclusion Checks (Same as in build_index) ---
		$is_variation = ($post->post_type === 'product_variation');
		$options = get_option( 'wcac_settings', [] );
		$index_products = !empty($options['wcac_index_products']);
		$index_pages = !empty($options['wcac_index_pages']);
		$index_posts = !empty($options['wcac_index_posts']);

		$should_index_type = false;
		if ($post->post_type === 'product' && $index_products) $should_index_type = true;
		if ($post->post_type === 'product_variation' && $index_products) $should_index_type = true;
		if ($post->post_type === 'page' && $index_pages) $should_index_type = true;
		if ($post->post_type === 'post' && $index_posts) $should_index_type = true;

		if (! $should_index_type) {
			$this->remove_content_from_index($post_id, "Type '{$post->post_type}' is not selected for indexing.");
			return;
		}
		if ($post->post_status !== 'publish' && !$is_variation) {
			$this->remove_content_from_index($post_id, "Status is '{$post->post_status}'.");
			return;
		}
		if ($post->post_password) {
			$this->remove_content_from_index($post_id, "Is password protected.");
			return;
		}
		if ( $post->post_type === 'product' && taxonomy_exists('product_visibility') ) {
			$hidden_terms = ['exclude-from-catalog', 'exclude-from-search'];
			if ( has_term( $hidden_terms, 'product_visibility', $post ) ) {
				$this->remove_content_from_index($post_id, "Product has exclude-from-catalog or exclude-from-search visibility.");
				if (class_exists('WooCommerce')) { $product = wc_get_product($post_id); if ($product && $product->is_type('variable')) { foreach ($product->get_children() as $variation_id) { $this->remove_content_from_index($variation_id, "Parent product ID {$post_id} became hidden."); } } }
				return;
			}
		}
		if ($is_variation) {
			$parent_id = $post->post_parent; if ($parent_id <= 0) { $this->remove_content_from_index($post_id, "Variation has no parent ID."); return; }
			$parent_post = get_post($parent_id); if (!$parent_post || $parent_post->post_status !== 'publish') { $this->remove_content_from_index($post_id, "Parent product ID {$parent_id} is not published."); return; }
			if (taxonomy_exists('product_visibility')) { $hidden_terms = ['exclude-from-catalog', 'exclude-from-search']; if (has_term($hidden_terms, 'product_visibility', $parent_id)) { $this->remove_content_from_index($post_id, "Parent product ID {$parent_id} is hidden."); return; } }
			if (class_exists('WooCommerce')) { $variation_obj = wc_get_product($post_id); if (!$variation_obj || !$variation_obj->is_purchasable() || $variation_obj->get_status() !== 'publish') { $reason = !$variation_obj ? "Variation object not found." : (!$variation_obj->is_purchasable() ? "Variation not purchasable." : "Variation status '{$variation_obj->get_status()}'."); $this->remove_content_from_index($post_id, $reason); return; } }
		}
		// --- End Exclusion Checks ---

		// If checks passed, format and update/add the content to the custom table
		$formatted_data = $this->format_content_for_llm( $post );

		if ( $formatted_data ) {
			error_log("WCAC Indexer Single Update DB: Updating/Adding Post ID {$post_id} (Type: {$post->post_type}).");
			// Prepare data for insertion/update
			$insert_data = [
				'post_id'         => $post_id,
				'post_type'       => $formatted_data['type'],
				'title'           => $formatted_data['title'],
				'content_snippet' => $formatted_data['content'],
				'url'             => $formatted_data['url'],
				'categories'      => !empty($formatted_data['categories']) ? wp_json_encode($formatted_data['categories']) : null,
				'tags'            => !empty($formatted_data['tags']) ? wp_json_encode($formatted_data['tags']) : null,
				'regular_price'   => $formatted_data['regular_price'],
				'sale_price'      => $formatted_data['sale_price'],
				'on_sale'         => $formatted_data['on_sale'] ? 1 : 0,
				'parent_id'       => $formatted_data['parent_id'],
			];
			$formats = [
				'%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d',
			];

			$replace_result = $wpdb->replace($table_name, $insert_data, $formats);

			if ($replace_result === false) {
				error_log("WCAC Indexer Single Update DB Error: Failed to replace row for post ID {$post_id}. Error: " . $wpdb->last_error);
			}
		} else {
			// Formatting failed, ensure it's removed from the DB table
			$this->remove_content_from_index($post_id, "Formatting failed or returned empty.");
		}
	}

	/**
	 * Helper function to remove an item from the index table and log the reason.
	 *
	 * @since 0.1.3 (Modified 0.1.4 to use custom table)
	 * @param int    $post_id The ID of the post/variation to remove.
	 * @param string $reason  The reason for removal (for logging).
	 */
	private function remove_content_from_index(int $post_id, string $reason): void {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcac_index';

		$delete_result = $wpdb->delete( $table_name, [ 'post_id' => $post_id ], [ '%d' ] );

		if ($delete_result !== false) {
			// Log success only if rows were actually deleted (or 0 if it didn't exist)
			error_log("WCAC Indexer Remove DB: Attempted removal for Post ID {$post_id}. Reason: {$reason}. Rows affected: {$delete_result}");
		} else {
			// Log error if the delete query itself failed
			error_log("WCAC Indexer Remove DB Error: Failed to execute delete query for post ID {$post_id}. Error: " . $wpdb->last_error);
		}
	}

	/**
	 * Updates the index metadata (counts and timestamp).
	 * This should ideally be called only once at the very end of a successful batch process.
	 *
	 * @since 0.1.1 (Modified 0.1.5 for Batch Processing)
	 * @param array|null $final_counts Array of final counts per post type, or null to recalculate from table.
	 */
	public function update_final_index_meta( ?array $final_counts = null ): void {
        global $wpdb;
		$table_name = $wpdb->prefix . 'wcac_index';

        if ($final_counts === null) {
            // Recalculate counts directly from the table if not provided
            error_log("WCAC Indexer Meta: Recalculating final counts from table.");
            $final_counts = [
                'product' => 0, 'page' => 0, 'post' => 0, 'product_variation' => 0, 'total' => 0
            ];
            $results = $wpdb->get_results( "SELECT post_type, COUNT(*) as count FROM {$table_name} GROUP BY post_type", ARRAY_A );
            if ($results) {
                foreach ($results as $row) {
                    if (isset($final_counts[$row['post_type']])) {
                        $final_counts[$row['post_type']] = (int) $row['count'];
                    }
                }
                $final_counts['total'] = array_sum($final_counts);
            } else {
                 error_log("WCAC Indexer Meta Error: Could not query counts from table {$table_name}. Error: " . $wpdb->last_error);
                 // Leave counts as 0 if query fails
            }
        }

		$meta = [
			'last_updated' => current_time( 'mysql' ),
			'counts' => [
				'product'           => absint($final_counts['product'] ?? 0),
				'page'              => absint($final_counts['page'] ?? 0),
				'post'              => absint($final_counts['post'] ?? 0),
				'product_variation' => absint($final_counts['product_variation'] ?? 0),
				'total'             => absint($final_counts['total'] ?? 0)
			]
		];
		update_option( self::META_KEY, $meta, false ); // Use autoload 'no'
        error_log("WCAC Indexer Meta: Final index metadata updated. Counts: " . wp_json_encode($meta['counts']));
	}

	/**
	 * Formats post data (product, variation, page, post) into a structure suitable for LLM context.
	 *
	 * @since 0.1.1 (modified 0.1.5 to use wp_json_encode)
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
		$raw_content = ''; // Store raw content for processing

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
			$raw_content = $variation->get_description(); // Get raw variation description
            if (empty(trim($raw_content))) {
                $raw_content = $parent_product->get_short_description(); // Try parent excerpt raw
                if (empty(trim($raw_content))) {
                    $raw_content = $parent_product->get_description(); // Then parent content raw
                }
            }

			// Get categories and tags from parent
			$parent_term_ids = wp_get_post_terms( $parent_id, 'product_cat', ['fields' => 'ids'] );
			$categories = $this->get_all_category_names($parent_term_ids, $parent_id);
			$tag_terms = get_the_terms( $parent_id, 'product_tag' );
			if ( ! empty( $tag_terms ) && ! is_wp_error( $tag_terms ) ) {
				$tags = wp_list_pluck( $tag_terms, 'name' );
			}

			$url = $variation->get_permalink(); // Correct: Use variation's own permalink
			if (empty($url)) { // Fallback if variation permalink fails
				 error_log("WCAC Indexer Format WARNING: Failed to get permalink for variation ID {$post_id}, falling back to parent URL.");
				 $url = $parent_product->get_permalink();
			}

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
				$raw_content = $product->get_short_description(); // Get raw excerpt
				if (empty(trim($raw_content))) {
					$raw_content = $product->get_description(); // Get raw description
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
			$raw_content = $post->post_excerpt; // Get raw excerpt
			if (empty(trim($raw_content))) {
				$raw_content = $post->post_content; // Get raw content
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

		// --- Sanitize and Prepare Main Content Snippet ---
		if (!empty($raw_content)) {
			// 1. Attempt to convert to valid UTF-8, discarding invalid characters
			$utf8_content = mb_convert_encoding($raw_content, 'UTF-8', 'UTF-8');
			// 2. Strip shortcodes
			$no_shortcodes = strip_shortcodes($utf8_content);
			// 3. Strip all HTML tags
			$plain_text = wp_strip_all_tags($no_shortcodes);
			// 4. Normalize whitespace
			$normalized_text = preg_replace('/\s+/s', ' ', $plain_text);
			// 5. Trim and limit length using mb_substr for multibyte safety
			$main_content_limited = mb_substr(trim($normalized_text), 0, 450, 'UTF-8'); // Shortened limit slightly
		} else {
			$main_content_limited = '';
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
		if (!empty($main_content_limited)) {
			$output_lines[] = "Content: " . $main_content_limited; // Use the sanitized, limited content
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
			'content' => $main_content_limited, // Assign sanitized, limited content
			'categories' => array_values(array_filter($categories)), // Store cleaned categories for scoring
			'tags' => array_values(array_filter($tags)), // Store cleaned tags for scoring
			'regular_price' => $regular_price, // Store numeric price or null
			'sale_price' => $sale_price, // Store numeric price or null
			'on_sale' => $on_sale, // Store boolean
			'parent_id' => $parent_id > 0 ? $parent_id : null, // Store parent ID for variations
		];
		// error_log("WCAC Indexer Format DEBUG [{$content_type} ID: {$post_id}]: Storing data: " . wp_json_encode($result)); // REMOVE DEBUG LOG
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
				// error_log("WCAC Indexer DEBUG [Post ID: {$post_id}]: Term '{$direct_cat_name}' (ID: {$term_id}) - Found Parents: " . (!empty($parent_names_found) ? implode(', ', $parent_names_found) : 'None')); // REMOVE DEBUG LOG
			}
		}

		// Product-specific category logic REMOVED
		// No hard-coded categories are added to keep the code universal

		$unique_names = array_unique($all_category_names);
		// error_log("WCAC Indexer DEBUG [Post ID: {$post_id}]: Final unique categories stored: " . (!empty($unique_names) ? implode(', ', $unique_names) : 'None')); // REMOVE DEBUG LOG
		return $unique_names;
	}

	/**
	 * Generates a site profile summary and saves it to options.
	 *
	 * @since 0.1.2
	 * @throws Exception If fetching data fails critically.
	 */
	private function generate_and_save_site_profile(): void {
		// error_log('WCAC DEBUG: Entering generate_and_save_site_profile method.'); // REMOVE DEBUG LOG
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

	/**
	 * Helper function to get the counts array from the index meta option.
	 *
	 * @since 0.1.5
	 * @return array Associative array of counts per post type.
	 */
	public function get_last_meta_counts(): array {
		$meta = get_option(self::META_KEY, []);
		return $meta['counts'] ?? ['product' => 0, 'page' => 0, 'post' => 0, 'product_variation' => 0, 'total' => 0];
	}
} 