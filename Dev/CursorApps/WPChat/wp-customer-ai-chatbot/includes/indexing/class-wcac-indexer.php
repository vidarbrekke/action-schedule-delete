<?php

declare(strict_types=1);

/**
 * This file is intended to be run within the WordPress environment.
 * All referenced functions such as get_post, get_post_status, taxonomy_exists, has_term, wc_get_product
 * are provided by WordPress core or WooCommerce and are expected to be available.
 */

// Ensure WordPress Admin functions are available if needed
if (! function_exists('is_plugin_active')) {
    // is_plugin_active requires plugin.php
    if (file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    } else {
        // Define dummy function if file not found, to prevent fatal errors but log warning
        // @phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
        function is_plugin_active($plugin)
        {
            error_log('WCAC WARNING: is_plugin_active called but plugin.php not found.');
            return false;
        }
    }
}
if (! function_exists('plugin_dir_path')) {
    // Requires plugin.php
    if (file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    } else {
         // @phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
        function plugin_dir_path($file)
        {
            error_log('WCAC WARNING: plugin_dir_path called but plugin.php not found.');
            return dirname($file) . '/';
        }
    }
}

// At the top, add import for the new rules class
// require_once dirname(__DIR__) . '/retrieval/class-wcac-chatbot-rules.php'; // Relative path causing issues
if (!defined('WCAC_PLUGIN_DIR')) {
    // Define a fallback if not defined earlier (should not happen in normal WP flow)
    define('WCAC_PLUGIN_DIR', plugin_dir_path(__FILE__) . '../../'); // Go up two levels from includes/indexing/
}
// Fix: Update path to point to the correct location where the file was moved
require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-chatbot-rules.php'; // Use constant
// Fix: Update path to point to the correct location where the file was moved
require_once WCAC_PLUGIN_DIR . 'includes/indexing/class-wcac-content-formatter.php';

// Ensure WordPress core functions are available (add any missing)
if (! function_exists('get_post')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (! function_exists('get_post_status')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (! function_exists('taxonomy_exists')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (! function_exists('has_term')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (! function_exists('sanitize_text_field')) {
    require_once ABSPATH . 'wp-includes/formatting.php';
}
if (! function_exists('get_term_by')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (! function_exists('wc_attribute_label')) {
    if (defined('WC_ABSPATH')) {
        // Check if file exists before requiring
        if (file_exists(WC_ABSPATH . 'includes/wc-attribute-functions.php')) {
            require_once WC_ABSPATH . 'includes/wc-attribute-functions.php';
        }
    }
}
if (! function_exists('wp_get_post_terms')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (! function_exists('get_the_terms')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (! function_exists('is_wp_error')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (! function_exists('wp_list_pluck')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (! function_exists('get_permalink')) {
    require_once ABSPATH . 'wp-includes/link-template.php';
}
if (! function_exists('get_the_category')) {
    require_once ABSPATH . 'wp-includes/category-template.php';
}
if (! function_exists('get_the_tags')) {
    require_once ABSPATH . 'wp-includes/category-template.php';
}
if (! function_exists('strip_shortcodes')) {
    require_once ABSPATH . 'wp-includes/shortcodes.php';
}
if (! function_exists('wp_strip_all_tags')) {
    require_once ABSPATH . 'wp-includes/formatting.php';
}
if (! function_exists('wc_price')) {
    if (defined('WC_ABSPATH')) {
        // Check if file exists
        if (file_exists(WC_ABSPATH . 'includes/wc-formatting-functions.php')) {
             require_once WC_ABSPATH . 'includes/wc-formatting-functions.php';
        }
    }
}
if (! function_exists('get_term')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (! function_exists('get_bloginfo')) {
    require_once ABSPATH . 'wp-includes/general-template.php';
}
if (! function_exists('get_pages')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (! function_exists('get_nav_menu_locations')) {
    require_once ABSPATH . 'wp-includes/nav-menu.php';
}
if (! function_exists('wp_get_nav_menu_items')) {
    require_once ABSPATH . 'wp-includes/nav-menu.php';
}
if (! function_exists('get_terms')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (! function_exists('get_posts')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (! function_exists('update_option')) {
    require_once ABSPATH . 'wp-includes/option.php';
}
if (! function_exists('current_time')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (! function_exists('absint')) {
    function absint($maybeint)
    {
        return abs(intval($maybeint));
    }
}
if (! function_exists('apply_filters')) {
    require_once ABSPATH . 'wp-includes/plugin.php';
}
if (! function_exists('wp_get_post_tags')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (! function_exists('delete_transient')) {
    require_once ABSPATH . 'wp-includes/option.php';
}
// Ensure WooCommerce functions are available if WooCommerce is active
if (! function_exists('wc_get_product')) {
    if (defined('WC_ABSPATH')) {
        // Check if file exists
        if (file_exists(WC_ABSPATH . 'includes/wc-product-functions.php')) {
            include_once WC_ABSPATH . 'includes/wc-product-functions.php';
        }
    } elseif (function_exists('plugin_dir_path')) {
        // Fallback: try to include from plugin directory if possible
        $wc_plugin_path = WP_PLUGIN_DIR . '/woocommerce/includes/wc-product-functions.php';
        if (file_exists($wc_plugin_path)) {
            include_once $wc_plugin_path;
        }
    }
    // If still not available, define a stub to avoid fatal errors
    if (! function_exists('wc_get_product')) {
        function wc_get_product($post_id)
        {
            return null;
        }
    }
}

// Ensure this file is only used in a WordPress environment
if (!function_exists('get_post')) {
    exit('This file must be used within WordPress.');
}

// Ensure constants are defined for plugin paths
if (! defined('WC_ABSPATH') && defined('ABSPATH')) {
    // Define if not already defined (e.g., in CLI context)
    // Check if WooCommerce is active before defining
    // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
    if (function_exists('is_plugin_active') && @is_plugin_active('woocommerce/woocommerce.php')) {
        // @phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
         define('WC_ABSPATH', plugin_dir_path(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php'));
    } else {
        // Provide a default path but it might be incorrect if WC installed differently
        // @phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
        define('WC_ABSPATH', ABSPATH . 'wp-content/plugins/woocommerce/');
    }
}
if (! defined('WP_PLUGIN_DIR') && defined('ABSPATH')) {
    // @phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
    define('WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins');
}

// Fix requires to reflect new file organization
if (!class_exists('Wcac_ChatbotRules')) {
    require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-chatbot-rules.php';
}

// Linter false positives: WordPress core functions are available at runtime. These require_once statements ensure compatibility for plugin development.
if (!function_exists('get_post')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (!function_exists('update_option')) {
    require_once ABSPATH . 'wp-includes/option.php';
}
if (!function_exists('current_time')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('absint')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}

/**
 * Safely call array_unique with type checking and logging.
 * @param mixed $input The value to deduplicate.
 * @param string $context Context string for logging (e.g., Post ID, field name).
 * @return array
 */
function wcac_safe_array_unique($input, $context = '')
{
    if (!is_array($input)) {
        $type = gettype($input);
        error_log("WCAC SAFE ARRAY UNIQUE: Expected array in $context, got $type. Returning empty array.");
        return [];
    }
    return array_unique($input);
}

/**
 * Handles indexing of WooCommerce products for the chatbot.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes
 */
class Wcac_Indexer
{
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
    public function build_index(array $post_ids_batch, bool $is_first_batch = false): array
    {
        global $wpdb;
        static $term_frequencies = [];
        static $total_products = 0;
        $table_name = $wpdb->prefix . 'wcac_index';
        error_log("WCAC Indexer Batch: Processing batch. First Batch: " . ($is_first_batch ? 'Yes' : 'No') . ". Batch Size: " . count($post_ids_batch));
        error_log('WCAC Indexer DEBUG: build_index called. Batch size: ' . count($post_ids_batch) . '. First 10 IDs: ' . json_encode(array_slice($post_ids_batch, 0, 10)));

        $processed_count = 0;
        $error_count = 0;
        $last_error_message = null;

        // --- Menu propagation map: product_id => [menu_title, ...] ---
        static $menu_propagation_map = null;
        if ($is_first_batch || $menu_propagation_map === null) {
            $menu_propagation_map = [];
            $pseudo_menu_items = [];
            // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.get_nav_menu_locations
            if (function_exists('get_nav_menu_locations') && function_exists('wp_get_nav_menu_items') && function_exists('get_terms')) {
                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.get_nav_menu_locations
                $locations = get_nav_menu_locations();
                $all_menu_ids = array_values($locations);
                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.get_terms
                if (function_exists('get_terms')) {
                    $all_menus = get_terms(['taxonomy' => 'nav_menu', 'hide_empty' => true]);
                } else {
                    $all_menus = [];
                }
                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.is_wp_error
                if (function_exists('is_wp_error') && !is_wp_error($all_menus)) {
                    foreach ($all_menus as $menu) {
                        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.wp_get_nav_menu_items
                        $menu_items = function_exists('wp_get_nav_menu_items') ? wp_get_nav_menu_items($menu->term_id) : [];
                        if ($menu_items) {
                            foreach ($menu_items as $item) {
                                $menu_title = $item->title;
                                if ($item->object == 'product_cat' && $item->object_id && function_exists('get_posts')) {
                                    $cat_id = (int)$item->object_id;
                                    // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.get_posts
                                    $product_ids = function_exists('get_posts') ? get_posts([
                                        'post_type' => ['product', 'product_variation'],
                                        'post_status' => 'publish',
                                        'numberposts' => -1,
                                        'fields' => 'ids',
                                        'tax_query' => [
                                            [
                                                'taxonomy' => 'product_cat',
                                                'field' => 'term_id',
                                                'terms' => $cat_id,
                                                'include_children' => true,
                                            ],
                                        ],
                                    ]) : [];
                                    foreach ($product_ids as $pid) {
                                        $menu_propagation_map[$pid][] = $menu_title;
                                    }
                                    // --- Add pseudo-item for the category itself ---
                                    // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.get_term
                                    $cat_obj = function_exists('get_term') ? get_term($cat_id, 'product_cat') : null;
                                    if ($cat_obj && function_exists('is_wp_error') && !is_wp_error($cat_obj)) {
                                        $term_link = function_exists('get_term_link') ? get_term_link($cat_obj) : '';
                                        $pseudo_menu_items[] = [
                                            'title' => $menu_title,
                                            'type' => 'menu_category',
                                            'url' => $term_link,
                                            'content' => $cat_obj->description ?? '',
                                            'categories' => [$cat_obj->name],
                                            'tags' => [],
                                            'menu_titles' => [$menu_title],
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            // --- Insert pseudo-items into the index table ---
            if (!empty($pseudo_menu_items)) {
                foreach ($pseudo_menu_items as $pseudo) {
                    $insert_data = [
                        'post_id'         => null,
                        'post_type'       => $pseudo['type'],
                        'post_modified'   => null,
                        'title'           => $pseudo['title'],
                        'content_snippet' => $pseudo['content'],
                        'url'             => $pseudo['url'],
                        'categories'      => json_encode($pseudo['categories']),
                        'tags'            => json_encode($pseudo['tags']),
                        'recommended_for' => null,
                        'regular_price'   => null,
                        'sale_price'      => null,
                        'on_sale'         => 0,
                        'parent_id'       => null,
                        'stock_status'    => null,
                        'search_blob'     => strtolower($pseudo['title'] . ' ' . $pseudo['content']),
                        'menu_titles'     => json_encode($pseudo['menu_titles']),
                    ];
                    $formats = [
                        '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%s','%s','%s'
                    ];
                    $wpdb->insert($table_name, $insert_data, $formats);
                }
            }
        }
        // --- End menu propagation map ---

        // --- Initial Setup for First Batch Only ---
        if ($is_first_batch) {
            // Generate Site Profile
            try {
                // error_log('WCAC DEBUG: Attempting to call generate_and_save_site_profile (First Batch).'); // REMOVE DEBUG LOG
                self::generate_and_save_site_profile();
            } catch (Throwable $e) {
                $profile_error = 'Site profile generation failed: ' . $e->getMessage();
                error_log('WCAC Indexer Error: Exception during site profile generation - ' . $profile_error);
                // Don't stop indexing for profile error, but maybe log it prominently
                if (!$last_error_message) {
                    $last_error_message = $profile_error;
                }
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

        foreach ($post_ids_batch as $post_id) {
            // Check execution time periodically within the loop to potentially prevent silent timeouts
            // Note: set_time_limit might not work in safe mode or if disabled by hosting.
            @set_time_limit(300); // Reset timer for each item (if possible)

			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_post_get_post
            $post = function_exists('get_post') ? get_post($post_id) : null;
            if (! $post) {
                error_log("WCAC Indexer Batch: Skipped Post ID {$post_id} - Post object not found.");
                continue; // Skip this post, don't count as error for now
            }

            // --- Visibility/Status Checks (Refactored) ---
            try {
                if ($this->_should_skip_indexing($post, $parent_product_statuses, $parent_product_visibility)) {
                    // Reason already logged, and item removed by _should_skip_indexing
                    continue; // Move to next post in batch
                }
            } catch (Throwable $vis_error) {
                error_log("WCAC Indexer Batch Error (Exclusion Check) for Post ID {$post_id}: " . $vis_error->getMessage());
                $error_count++;
                $last_error_message = "Exclusion check error for Post ID {$post_id}: " . $vis_error->getMessage();
                continue; // Skip this item due to error during check
            }
            // --- End Visibility/Status Checks ---

            // Format data
            $formatted_data = null;
            try {
                $formatted_data = Wcac_ContentFormatter::format($post);
                // Merge propagated menu titles if any
                if (isset($menu_propagation_map[$post_id]) && is_array($formatted_data)) {
                    $existing = isset($formatted_data['menu_titles']) ? $formatted_data['menu_titles'] : [];
                    $formatted_data['menu_titles'] = array_values(array_unique(array_merge($existing, $menu_propagation_map[$post_id])));
                }
            } catch (Throwable $format_error) {
                error_log("WCAC Indexer Batch Error (Formatting) for Post ID {$post_id}: " . $format_error->getMessage());
                $error_count++;
                $last_error_message = "Formatting error for Post ID {$post_id}: " . $format_error->getMessage();
                continue; // Skip this item due to formatting error
            }

            if ($formatted_data) {
                // --- Prepare data for insertion/update (Refactored) ---
                $db_prep = $this->_prepare_db_data($formatted_data, $post_id);
                if (empty($db_prep)) {
                    error_log("WCAC Indexer Batch: Failed to prepare DB data for Post ID {$post_id}.");
                    $error_count++;
                    $last_error_message = "DB data prep failed for Post ID {$post_id}.";
                    continue;
                }
                $insert_data = $db_prep['data'];
                $formats = $db_prep['formats'];
                // --- End Data Preparation ---

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

                // --- TF-IDF: Track term frequencies ---
                $all_text = strtolower($formatted_data['title'] . ' ' . $formatted_data['content_text']);
                if (!empty($formatted_data['categories'])) {
                    $cats = $formatted_data['categories'];
                    if (is_string($cats)) {
                        // Try to decode JSON if possible
                        $decoded = json_decode($cats, true);
                        $cats = is_array($decoded) ? $decoded : [$cats];
                        error_log("[WCAC Indexer] Categories was string for Post ID {$post_id}, auto-converted to array.");
                    }
                    if (is_array($cats)) {
                        $all_text .= ' ' . implode(' ', $cats);
                    } else {
                        error_log("[WCAC Indexer] Categories not array/string for Post ID {$post_id}, skipping in TF-IDF.");
                    }
                }
                if (!empty($formatted_data['tags'])) {
                    $tags = $formatted_data['tags'];
                    if (is_string($tags)) {
                        $decoded = json_decode($tags, true);
                        $tags = is_array($decoded) ? $decoded : [$tags];
                        error_log("[WCAC Indexer] Tags was string for Post ID {$post_id}, auto-converted to array.");
                    }
                    if (is_array($tags)) {
                        $all_text .= ' ' . implode(' ', $tags);
                    } else {
                        error_log("[WCAC Indexer] Tags not array/string for Post ID {$post_id}, skipping in TF-IDF.");
                    }
                }
                $words = wcac_safe_array_unique(preg_split('/\\s+/', $all_text, -1, PREG_SPLIT_NO_EMPTY), "build_index TF-IDF words for Post ID {$post_id}");
                require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-chatbot-rules.php';
                $stopwords = Wcac_ChatbotRules::get_stopwords();
                foreach ($words as $word) {
                    if (strlen($word) > 2 && !in_array($word, $stopwords)) {
                        if (!isset($term_frequencies[$word])) {
                            $term_frequencies[$word] = 0;
                        }
                        $term_frequencies[$word]++;
                    }
                }
                $total_products++;
            } else {
                 error_log("WCAC Indexer Batch: Skipped Post ID {$post_id} - Formatting returned null or empty.");
                 // Ensure it's removed if formatting fails or post becomes invalid
                 $this->remove_content_from_index($post_id, "Formatting failed or post invalid during batch build.");
            } // end if formatted_data
        } // end foreach post_ids_batch

        error_log("WCAC Indexer Batch: Finished processing batch. Processed: {$processed_count}, Errors: {$error_count}.");

        // Note: We don't update the overall index meta here. The caller (AJAX handler) manages progress.
        // At the end of the final batch, save term frequencies
        if (isset($GLOBALS['wcac_indexing_complete']) && $GLOBALS['wcac_indexing_complete']) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.OptionFunctions.update_option
            if (function_exists('update_option')) {
                update_option('wcac_term_frequencies', $term_frequencies, false);
            }
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.OptionFunctions.update_option
            if (function_exists('update_option')) {
                update_option('wcac_total_products', $total_products, false);
            }
        }
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
    public function update_single_content(int $post_id): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';

		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_post_get_post
        $post = function_exists('get_post') ? get_post($post_id) : null;
        if (! $post) {
            $this->remove_content_from_index($post_id, "Post object not found.");
            return;
        }

        // --- Exclusion Checks (Refactored) ---
        try {
            if ($this->_should_skip_indexing($post)) {
                // Reason already logged, and item removed by _should_skip_indexing
                return;
            }
        } catch (Throwable $vis_error) {
            error_log("WCAC Indexer Single Update Error (Exclusion Check) for Post ID {$post_id}: " . $vis_error->getMessage());
            // Don't proceed if exclusion check failed
            return;
        }
        // --- End Exclusion Checks ---

        // If checks passed, format and update/add the content to the custom table
        $formatted_data = Wcac_ContentFormatter::format($post);

        if ($formatted_data) {
            error_log("WCAC Indexer Single Update DB: Updating/Adding Post ID {$post_id} (Type: {$post->post_type}).");

            // --- Prepare data for insertion/update (Refactored) ---
            $db_prep = $this->_prepare_db_data($formatted_data, $post_id);
            if (empty($db_prep)) {
                error_log("WCAC Indexer Single Update: Failed to prepare DB data for Post ID {$post_id}.");
                // Maybe remove from index if data prep fails?
                $this->remove_content_from_index($post_id, "DB data preparation failed.");
                return;
            }
            $insert_data = $db_prep['data'];
            $formats = $db_prep['formats'];
            // --- End Data Preparation ---

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
    private function remove_content_from_index(int $post_id, string $reason): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';

        $delete_result = $wpdb->delete($table_name, [ 'post_id' => $post_id ], [ '%d' ]);

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
    public function update_final_index_meta(?array $final_counts = null): void
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';

        if ($final_counts === null) {
            // Recalculate counts directly from the table if not provided
            error_log("WCAC Indexer Meta: Recalculating final counts from table.");
            $final_counts = [
                'product' => 0, 'page' => 0, 'post' => 0, 'product_variation' => 0, 'total' => 0
            ];
            $results = $wpdb->get_results("SELECT post_type, COUNT(*) as count FROM {$table_name} GROUP BY post_type", ARRAY_A);
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
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.current_time
            'last_updated' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            'counts' => [
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.absint
                'product'           => absint($final_counts['product'] ?? 0),
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.absint
                'page'              => absint($final_counts['page'] ?? 0),
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.absint
                'post'              => absint($final_counts['post'] ?? 0),
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.absint
                'product_variation' => absint($final_counts['product_variation'] ?? 0),
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.DefinedFunctions.absint
                'total'             => absint($final_counts['total'] ?? 0)
            ]
        ];
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.OptionFunctions.update_option
        $updated = update_option(self::META_KEY, $meta, false); // Use autoload 'no'

        if ($updated) {
            error_log("WCAC Indexer Meta: Final index metadata updated. Counts: " . wp_json_encode($meta['counts']));
            // Invalidate the category name cache used by ChatbotRules
            if (function_exists('delete_transient')) {
                delete_transient('wcac_indexed_category_names');
            }
        } else {
            error_log("WCAC Indexer Meta Error: Failed to update final index metadata option.");
        }
    }

    /**
     * Formats post data (product, variation, page, post) into a structure suitable for LLM context.
     *
     * @since 0.1.1 (modified 0.1.5 to use wp_json_encode)
     * @param WP_Post $post WP_Post object for the item to format.
     * @return array|null Formatted data representation of the post, or null if invalid.
     */
    private function format_content_for_llm(WP_Post $post): ?array
    {
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
        if ($content_type === 'product_variation' && class_exists('WooCommerce') && $parent_id > 0) {
            $variation = wc_get_product($post_id);
            $parent_product = wc_get_product($parent_id);

            if (!$variation || !$parent_product) {
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
                    if (empty($term_slug)) {
                        continue; // Skip empty attribute slugs which can happen
                    }
                    // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Taxonomy.RestrictedFunctions.get_term_by
                    $term = get_term_by('slug', $term_slug, $taxonomy);
                    // @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WooCommerce.RestrictedFunctions.wc_attribute_label
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
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_post_terms_get_post_terms
            $parent_term_ids = wp_get_post_terms($parent_id, 'product_cat', ['fields' => 'ids']);
            $categories = $this->get_all_category_names($parent_term_ids, $parent_id, 'product_cat', true); // Always include parents
            $tag_terms = get_the_terms($parent_id, 'product_tag');
            if (! empty($tag_terms) && ! is_wp_error($tag_terms)) {
				// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                $tags = wp_list_pluck($tag_terms, 'name');
            }

            $url = $variation->get_permalink(); // Correct: Use variation's own permalink
            if (empty($url)) { // Fallback if variation permalink fails
                 error_log("WCAC Indexer Format WARNING: Failed to get permalink for variation ID {$post_id}, falling back to parent URL.");
                 $url = $parent_product->get_permalink();
            }

            // --- Index average rating and comment count for variations ---
            $average_rating = method_exists($variation, 'get_average_rating') ? $variation->get_average_rating() : null;
            $comment_count = method_exists($variation, 'get_review_count') ? $variation->get_review_count() : null;

            // --- Brand Extraction for Variations ---
            $brand = Wcac_BrandExtractor::extract_from_product($variation);
            if ($brand) {
                self::add_candidate_brand($brand);
            }
        }
        // --- Handle Simple/Variable Products ---
        elseif ($content_type === 'product' && class_exists('WooCommerce')) {
            $product = wc_get_product($post_id);
            if ($product) {
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
				// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_post_terms_get_post_terms
                $term_ids = wp_get_post_terms($post_id, 'product_cat', ['fields' => 'ids']);
                $categories = $this->get_all_category_names($term_ids, $post_id, 'product_cat', true); // Always include parents
                $tag_terms = get_the_terms($post_id, 'product_tag');
                if (! empty($tag_terms) && ! is_wp_error($tag_terms)) {
					// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                    $tags = wp_list_pluck($tag_terms, 'name');
                }

                $url = $product->get_permalink();

                // --- Index average rating and comment count for products ---
                $average_rating = method_exists($product, 'get_average_rating') ? $product->get_average_rating() : null;
                $comment_count = method_exists($product, 'get_review_count') ? $product->get_review_count() : null;

                // --- Brand Extraction for Products ---
                $brand = Wcac_BrandExtractor::extract_from_product($product);
                if ($brand) {
                    self::add_candidate_brand($brand);
                }
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
            $url = get_permalink($post->ID);

            // Get taxonomies for posts
            if ($content_type === 'post') {
				// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_post_get_post
                $post_categories = get_the_category($post->ID);
                if (!empty($post_categories) && !is_wp_error($post_categories)) {
                    $cat_ids = wp_list_pluck($post_categories, 'term_id');
                    $categories = $this->get_all_category_names($cat_ids, $post_id, 'category', true); // Always include parents
                }
				// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_post_get_post
                $post_tags = get_the_tags($post->ID);
                if (!empty($post_tags) && !is_wp_error($post_tags)) {
					// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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
            // Use DOM-based extraction for robust cleaning and URL preservation
            list($cleaned_text, $url_list) = $this->extract_text_and_urls_from_html($raw_content);
            $main_content_limited = $cleaned_text;
            $urls_string = !empty($url_list) ? 'URLs: ' . implode(' ', $url_list) : '';
        } else {
            $main_content_limited = '';
            $urls_string = '';
        }

        // --- Mine Recommendations from Content ---
        require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-recommendation-miner.php';
        $phrase_map = [
            'socks' => ['recommended for socks', 'sock yarn', 'good for socks', 'suitable for socks'],
            'summer' => ['summer', 'hot weather', 'warm weather'],
            'beginner' => ['beginner', 'easy', 'simple pattern'],
        ];
        $recommendations = Wcac_Recommendation_Miner::mine_recommendations($main_content_limited, $phrase_map);

        // --- Assemble Common Output ---
        $output_lines[] = "Title: {$title}";
        $output_lines[] = "Type: {$content_type}"; // Keep original type (product, product_variation, page, post)
        if ($url) {
            $output_lines[] = "URL: {$url}";
        }

        // Add prices for variations and simple products
        if (($content_type === 'product_variation' || ($content_type === 'product' && !$product->is_type('variable'))) && $regular_price) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WooCommerce.RestrictedFunctions.wc_price
            $output_lines[] = "Regular Price: " . wc_price($regular_price); // Format price
        }
        if (($content_type === 'product_variation' || ($content_type === 'product' && !$product->is_type('variable'))) && $on_sale && $sale_price) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WooCommerce.RestrictedFunctions.wc_price
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
            $output_lines[] = "Content: " . $main_content_limited;
        }
        // Add URLs (if any)
        if (!empty($urls_string)) {
            $output_lines[] = $urls_string;
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

        // --- Menu Hierarchy Extraction ---
        $menu_titles = [];
        if (function_exists('get_nav_menu_locations') && function_exists('wp_get_nav_menu_items')) {
            $locations = get_nav_menu_locations();
            $all_menu_ids = array_values($locations);
            // Also get all menus, not just theme locations
            $all_menus = get_terms(['taxonomy' => 'nav_menu', 'hide_empty' => true]);
            if (!is_wp_error($all_menus)) {
                foreach ($all_menus as $menu) {
                    $menu_items = wp_get_nav_menu_items($menu->term_id);
                    if ($menu_items) {
                        foreach ($menu_items as $item) {
                            if ((int)$item->object_id === (int)$post_id) {
                                // Add this menu item title and all parent menu item titles
                                $current = $item;
                                while ($current) {
                                    $menu_titles[] = $current->title;
                                    $parent_id = $current->menu_item_parent;
                                    $current = $parent_id ? (isset($menu_items[$parent_id]) ? $menu_items[$parent_id] : null) : null;
                                }
                            }
                        }
                    }
                }
            }
        }
        // Add menu titles to search_blob
        if (!empty($menu_titles)) {
            foreach (wcac_safe_array_unique($menu_titles, "format_content_for_llm menu_titles for Post ID {$post_id}") as $menu_title) {
                $search_blob_parts[] = strtolower($menu_title);
            }
        }

        $search_blob = implode(' ', wcac_safe_array_unique($search_blob_parts, "_prepare_db_data search_blob_parts for Post ID {$post_id}"));

        // --- Extract all taxonomy terms (including custom taxonomies) ---
        $all_taxonomies = [];
        if (function_exists('get_object_taxonomies') && function_exists('wp_get_object_terms')) {
            $taxonomies = get_object_taxonomies($post, 'names');
            foreach ($taxonomies as $taxonomy) {
                // Skip built-in categories/tags (already handled)
                if (in_array($taxonomy, ['category', 'post_tag', 'product_cat', 'product_tag'])) {
                    continue;
                }
                $terms = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'names']);
                if (!is_wp_error($terms) && !empty($terms)) {
                    $all_taxonomies[$taxonomy] = $terms;
                }
            }
        }
        // Add taxonomy terms to search_blob
        if (!empty($all_taxonomies)) {
            foreach ($all_taxonomies as $tax => $terms) {
                foreach ($terms as $term) {
                    $search_blob_parts[] = strtolower($term);
                }
            }
        }

        // --- Extract parent categories ---
        $parent_categories = [];
        if (!empty($categories)) {
            foreach ($categories as $cat_name) {
                // Use get_term_by to get parent chain if possible
                $term = function_exists('get_term_by') ? get_term_by('name', $cat_name, 'product_cat') : null;
                if ($term && !is_wp_error($term)) {
                    $ancestors = function_exists('get_ancestors') ? get_ancestors($term->term_id, 'product_cat') : [];
                    foreach ($ancestors as $ancestor_id) {
                        $ancestor_term = get_term($ancestor_id, 'product_cat');
                        if ($ancestor_term && !is_wp_error($ancestor_term)) {
                            $parent_categories[] = $ancestor_term->name;
                        }
                    }
                }
            }
            $parent_categories = array_unique($parent_categories);
        }
        // --- Extract all taxonomy terms ---
        $taxonomies = [];
        if (function_exists('get_object_taxonomies') && function_exists('wp_get_object_terms')) {
            $tax_names = get_object_taxonomies($post, 'names');
            foreach ($tax_names as $taxonomy) {
                $terms = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'names']);
                if (!is_wp_error($terms) && !empty($terms)) {
                    $taxonomies[$taxonomy] = $terms;
                }
            }
        }
        // --- Attributes as text ---
        $attributes_text = '';
        if (!empty($attributes)) {
            $attr_pairs = [];
            foreach ($attributes as $label => $slug) {
                $attr_pairs[] = $label . ': ' . $slug;
            }
            $attributes_text = implode(', ', $attr_pairs);
        }
        // --- Stock status ---
        $stock_status = null;
        if ($content_type === 'product' || $content_type === 'product_variation') {
            $product_obj = isset($product) ? $product : (isset($variation) ? $variation : null);
            if ($product_obj && method_exists($product_obj, 'get_stock_status')) {
                $stock_status = $product_obj->get_stock_status();
            }
        }
        // --- Menu titles already extracted above ---
        // --- Prepare recommended_for as JSON ---
        $recommended_for = !empty($recommendations) ? $recommendations : null;
        // --- Prepare all fields for DB insert ---
        return [
            'title' => $title,
            'type' => $content_type,
            'url' => $url,
            'content_text' => $main_content_limited,
            'categories' => !empty($categories) ? json_encode($categories) : null,
            'parent_category_names' => !empty($parent_categories) ? json_encode($parent_categories) : null,
            'tags' => !empty($tags) ? json_encode($tags) : null,
            'recommended_for' => $recommended_for,
            'attributes_text' => $attributes_text,
            'regular_price' => $regular_price,
            'sale_price' => $sale_price,
            'on_sale' => $on_sale,
            'parent_id' => $parent_id > 0 ? $parent_id : null,
            'stock_status' => $stock_status,
            'menu_titles' => !empty($menu_titles) ? json_encode($menu_titles) : null,
            'taxonomies' => !empty($taxonomies) ? json_encode($taxonomies) : null,
            // Add post_modified if available
            'post_modified' => isset($post->post_modified) ? $post->post_modified : null,
            // --- New fields for rating and comments ---
            'average_rating' => isset($average_rating) ? $average_rating : null,
            'comment_count' => isset($comment_count) ? $comment_count : null,
        ];
    }

    /**
     * Get all category names for a product including parent categories.
     *
     * @param array $term_ids Array of term IDs to process
     * @param int $post_id The post ID (for logging)
     * @param string $taxonomy The taxonomy name, defaults to 'product_cat'
     * @param bool $include_parents Whether to include parent categories, defaults to true
     * @return array Array of unique category names
     */
    private function get_all_category_names(array $term_ids, int $post_id, string $taxonomy = 'product_cat', bool $include_parents = true): array
    {
        $all_category_names = [];
        $debug_info = []; // For logging

        // First collect all direct category names and their parents
        foreach ($term_ids as $term_id) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_post_get_post
            $term = get_term($term_id, $taxonomy);
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            if ($term && !is_wp_error($term)) {
                $direct_cat_name = $term->name;
                $all_category_names[] = $direct_cat_name; // Add direct term name
                $debug_info[$term_id] = ['direct' => $direct_cat_name, 'parents' => []];

                // Add parent term names recursively (if enabled)
                if ($include_parents) {
                    $parent_id_term = $term->parent;
                    while ($parent_id_term != 0) {
						// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_post_get_post
                        $parent_term = get_term($parent_id_term, $taxonomy);
                        if ($parent_term && !is_wp_error($parent_term)) {
                            $parent_name = $parent_term->name;
                            $all_category_names[] = $parent_name;
                            $debug_info[$term_id]['parents'][] = $parent_name;
                            $parent_id_term = $parent_term->parent;
                        } else {
                            break; // Error or no parent found
                        }
                    }
                }
            }
        }

        $unique_names = wcac_safe_array_unique($all_category_names, "get_all_category_names for Post ID {$post_id}");
        $duplicate_count = count($all_category_names) - count($unique_names);

        // Enhanced logging showing full category hierarchy and duplicate detection
        error_log("WCAC Indexer [Post ID: {$post_id}]: Categories - Raw count: " . count($all_category_names) .
                 ", Unique: " . count($unique_names) .
                 ", Duplicates removed: " . $duplicate_count .
                 " - Hierarchy: " . json_encode($debug_info));

        return $unique_names;
    }

    /**
     * Generates a site profile summary and saves it to options.
     *
     * @since 0.1.2
     * @throws Exception If fetching data fails critically.
     */
    private function generate_and_save_site_profile(): void
    {
        // error_log('WCAC DEBUG: Entering generate_and_save_site_profile method.'); // REMOVE DEBUG LOG
        error_log('WCAC Indexer: Generating site profile...');
        $profile_parts = [];

        // Site Info
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_bloginfo_get_bloginfo
        $site_name = get_bloginfo('name');
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_bloginfo_get_bloginfo
        $site_description = get_bloginfo('description');
        if ($site_name) {
            $profile_parts[] = "Site Name: {$site_name}";
        }
        if ($site_description) {
            $profile_parts[] = "Description: {$site_description}";
        }

        // About Pages (Simple Title Match)
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_pages_get_pages
        $about_pages = get_pages(['post_title_like' => 'About', 'post_status' => 'publish', 'number' => 3]); // Limit results
        if (!empty($about_pages)) {
            $about_links = [];
            foreach ($about_pages as $page) {
				// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.get_permalink_get_permalink
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
                foreach ($menu_items as $item) {
                    if (empty($item->menu_item_parent)) { // Top level only
                        $menu_structure[] = $item->title;
                    }
                }
                $profile_parts[] = "Main Menu Items: " . implode(', ', $menu_structure);
            }
        }

        // Top-Level Product Categories
        if (taxonomy_exists('product_cat')) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.VIP.RestrictedFunctions.get_terms_get_terms
            $product_cats = get_terms(['taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'number' => 10]); // Limit
            if (!is_wp_error($product_cats) && !empty($product_cats)) {
				// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                $profile_parts[] = "Main Product Categories: " . implode(', ', wp_list_pluck($product_cats, 'name'));
            }
        }

        // Top-Level Post Categories
		// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.VIP.RestrictedFunctions.get_terms_get_terms
        $post_cats = get_terms(['taxonomy' => 'category', 'parent' => 0, 'hide_empty' => true, 'number' => 10]); // Limit
        if (!is_wp_error($post_cats) && !empty($post_cats)) {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            $profile_parts[] = "Main Post Categories: " . implode(', ', wp_list_pluck($post_cats, 'name'));
        }

        $site_profile_text = implode("\n", array_filter($profile_parts));

        if (empty($site_profile_text)) {
            error_log('WCAC Indexer: Could not generate meaningful site profile content.');
            update_option(self::SITE_PROFILE_KEY, ''); // Save empty string
        } else {
			// @phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.OptionFunctions.update_option
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
    private function prepare_negative_keywords(array $settings): array
    {
        $keywords_raw = $settings['wcac_negative_keywords'] ?? '';
        if (empty($keywords_raw) || !is_string($keywords_raw)) { // Added type check
            return [];
        }
        // Split by newline, trim whitespace, remove empty lines, convert to lowercase for case-insensitive matching
        return array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', strtolower($keywords_raw)))); // Use preg_split
    }

    /**
     * Helper function to get the counts array from the index meta option.
     *
     * @since 0.1.5
     * @return array Associative array of counts per post type.
     */
    public function get_last_meta_counts(): array
    {
        $meta = get_option(self::META_KEY, []);
        return $meta['counts'] ?? ['product' => 0, 'page' => 0, 'post' => 0, 'product_variation' => 0, 'total' => 0];
    }

    /**
     * Remove stopwords from a string or array of strings using the chatbot rules stopword list.
     *
     * @param string|array $input
     * @return string|array
     */
    public static function remove_stopwords($input)
    {
        require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-chatbot-rules.php';
        if (!class_exists('Wcac_ChatbotRules') || !method_exists('Wcac_ChatbotRules', 'get_stopwords')) {
            // Fail gracefully: return input as-is
            return $input;
        }
        $stopwords = Wcac_ChatbotRules::get_stopwords();
        if (is_array($input)) {
            return array_values(array_filter(array_map([self::class, 'remove_stopwords'], $input)));
        }
        $words = preg_split('/\s+/', strtolower($input), -1, PREG_SPLIT_NO_EMPTY);
        $filtered = array_filter($words, function ($w) use ($stopwords) {
            return strlen($w) > 2 && !in_array($w, $stopwords);
        });
        return implode(' ', $filtered);
    }

    /**
     * Checks if a string contains any of the specified negative keywords (case-insensitive).
     *
     * @param string $text The text to check.
     * @param array $keywords List of negative keywords (lowercase expected).
     * @return bool True if a negative keyword is found, false otherwise.
     */
    private function contains_negative_keyword(string $text, array $keywords): bool
    {
        if (empty($keywords) || empty($text)) {
            return false;
        }
        $text_lower = strtolower($text);
        foreach ($keywords as $keyword) {
            // Ensure keyword is not empty after trim in prepare_negative_keywords
            if (!empty($keyword) && strpos($text_lower, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Custom wrapper for stripping tags, primarily using wp_strip_all_tags.
     *
     * @param string $string Input string.
     * @return string String with tags stripped.
     */
    private function custom_strip_tags(string $string): string
    {
        if (function_exists('wp_strip_all_tags')) {
            // Specify true to remove breaks as well, false to keep them (adjust as needed)
            return wp_strip_all_tags($string, true);
        } else {
            // Basic fallback if wp_strip_all_tags is somehow unavailable
            return strip_tags($string);
        }
    }

    /**
     * Checks if a post should be skipped during indexing based on status, type, visibility etc.
     * Calls remove_content_from_index if skipping is necessary.
     *
     * @param WP_Post $post The post object.
     * @param array $parent_product_statuses Cache for parent statuses (used in batch mode).
     * @param array $parent_product_visibility Cache for parent visibility (used in batch mode).
     * @return bool True if the post should be skipped, false otherwise.
     */
    private function _should_skip_indexing(WP_Post $post, array &$parent_product_statuses = [], array &$parent_product_visibility = []): bool
    {
        $post_id = $post->ID;
        $is_variation = ($post->post_type === 'product_variation');
        $reason = '';

        // Check based on settings (for single update context primarily)
        // Note: Batch mode relies on the caller (get_posts) to filter types based on settings first.
        $options = get_option('wcac_settings', []);
        $index_products = !empty($options['wcac_index_products']);
        $index_pages = !empty($options['wcac_index_pages']);
        $index_posts = !empty($options['wcac_index_posts']);

        $should_index_type = false;
        if (($post->post_type === 'product' || $post->post_type === 'product_variation') && $index_products) {
            $should_index_type = true;
        }
        if ($post->post_type === 'page' && $index_pages) {
            $should_index_type = true;
        }
        if ($post->post_type === 'post' && $index_posts) {
            $should_index_type = true;
        }

        if (! $should_index_type) {
            $reason = "Type '{$post->post_type}' is not selected for indexing.";
        }

        // Status checks
        if (!$reason && $post->post_status !== 'publish' && !$is_variation) {
            $reason = "Status is '{$post->post_status}'.";
        }
        if (!$reason && $post->post_password) {
            $reason = "Is password protected.";
        }

        // Visibility checks
        if (!$reason && taxonomy_exists('product_visibility')) {
            $hidden_terms = ['exclude-from-catalog', 'exclude-from-search'];
            if ($is_variation) {
                $parent_id = $post->post_parent;
                if ($parent_id <= 0) {
                    $reason = "Variation has no parent ID.";
                } else {
                    // Check parent status (use cache if available)
                    if (!isset($parent_product_statuses[$parent_id])) {
                        $parent_product_statuses[$parent_id] = get_post_status($parent_id);
                    }
                    if ($parent_product_statuses[$parent_id] !== 'publish') {
                        $reason = "Parent product ID {$parent_id} is not published.";
                    }

                    // Check parent visibility (use cache if available)
                    if (!$reason && !isset($parent_product_visibility[$parent_id])) {
                        $parent_product_visibility[$parent_id] = !has_term($hidden_terms, 'product_visibility', $parent_id);
                    }
                    if (!$reason && !$parent_product_visibility[$parent_id]) {
                         $reason = "Parent product ID {$parent_id} is hidden.";
                    }
                }
                 // Also check variation's own purchasable status
                if (!$reason && class_exists('WooCommerce')) {
                    $variation_obj = wc_get_product($post_id);
                    if (!$variation_obj || !$variation_obj->is_purchasable() || $variation_obj->get_status() !== 'publish') {
                        $reason = !$variation_obj ? "Variation object not found." : (!$variation_obj->is_purchasable() ? "Variation not purchasable." : "Variation status '{$variation_obj->get_status()}'.");
                    }
                }
            } elseif ($post->post_type === 'product') {
                if (has_term($hidden_terms, 'product_visibility', $post_id)) {
                    $reason = "Product has exclude-from-catalog or exclude-from-search visibility.";
                    // If parent product becomes hidden, remove variations too (handle outside this check?)
                }
                 // Skip parent variable products themselves
                if (!$reason && class_exists('WooCommerce')) {
                    $product_obj = wc_get_product($post_id);
                }
            }
        }

        if ($reason) {
            $this->remove_content_from_index($post_id, $reason);
            return true; // Should skip
        }

        return false; // Should not skip
    }

    /**
     * Prepares data array and formats string for DB insertion/replacement.
     *
     * @param array $formatted_data Data returned from format_content_for_llm.
     * @param int $post_id The post ID.
     * @return array ['data' => array, 'formats' => array] or empty array on failure.
     */
    private function _prepare_db_data(array $formatted_data, int $post_id): array
    {
         // Prepare search blob
        $search_blob_parts = [];
        if (!empty($formatted_data['title'])) {
            $search_blob_parts[] = self::remove_stopwords(sanitize_text_field(strtolower($formatted_data['title'])));
        }
        if (!empty($formatted_data['content_text'])) {
            $search_blob_parts[] = self::remove_stopwords(sanitize_text_field(strtolower($formatted_data['content_text'])));
        }
        if (!empty($formatted_data['categories'])) {
            $cats = is_string($formatted_data['categories']) ? json_decode($formatted_data['categories'], true) : $formatted_data['categories'];
            if (is_array($cats)) {
                foreach (self::remove_stopwords($cats) as $cat) {
                    $search_blob_parts[] = strtolower($cat);
                }
            }
        }
        if (!empty($formatted_data['parent_category_names']) && is_array($formatted_data['parent_category_names'])) {
            foreach (self::remove_stopwords($formatted_data['parent_category_names']) as $pcat) {
                $search_blob_parts[] = strtolower($pcat);
            }
        }
        if (!empty($formatted_data['tags'])) {
            $tags = is_string($formatted_data['tags']) ? json_decode($formatted_data['tags'], true) : $formatted_data['tags'];
            if (is_array($tags)) {
                foreach (self::remove_stopwords($tags) as $tag) {
                    $search_blob_parts[] = strtolower($tag);
                }
            }
        }
        if (!empty($formatted_data['attributes_text'])) {
            $search_blob_parts[] = self::remove_stopwords(sanitize_text_field(strtolower($formatted_data['attributes_text'])));
        }
        $search_blob = implode(' ', wcac_safe_array_unique($search_blob_parts, "_prepare_db_data search_blob_parts for Post ID {$post_id}"));

        // Ensure post_type is set
        $post_maybe = function_exists('get_post') ? get_post($post_id) : null;
        $post_type = $post_maybe ? $post_maybe->post_type : 'unknown';
        error_log("WCAC Indexer: post_type missing in formatted data for Post ID {$post_id}, fetched as '{$post_type}'.");

        $insert_data = [
            'post_id'         => $post_id,
            'post_type'       => $post_type,
            'post_modified'   => $formatted_data['post_modified'] ?? null,
            'title'           => $formatted_data['title'] ?? null,
            'content_snippet' => $formatted_data['content_text'] ?? null,
            'url'             => $formatted_data['url'] ?? null,
            'categories'      => $formatted_data['categories'] ?? null,
            'parent_categories' => $formatted_data['parent_category_names'] ?? null,
            'tags'            => $formatted_data['tags'] ?? null,
            'recommended_for' => !empty($formatted_data['recommended_for']) ? wp_json_encode($formatted_data['recommended_for']) : null,
            'attributes_text' => $formatted_data['attributes_text'] ?? null,
            'regular_price'   => $formatted_data['regular_price'] ?? null,
            'sale_price'      => $formatted_data['sale_price'] ?? null,
            'on_sale'         => ($formatted_data['on_sale'] ?? false) ? 1 : 0,
            'parent_id'       => $formatted_data['parent_id'] ?? null,
            'stock_status'    => $formatted_data['stock_status'] ?? null,
            'search_blob'     => $search_blob,
            'menu_titles'     => $formatted_data['menu_titles'] ?? null,
            'taxonomies'      => $formatted_data['taxonomies'] ?? null,
            // --- New fields for rating and comments ---
            'average_rating'  => $formatted_data['average_rating'] ?? null,
            'comment_count'   => $formatted_data['comment_count'] ?? null,
        ];

        $formats = [
            '%d', // post_id
            '%s', // post_type
            '%s', // post_modified
            '%s', // title
            '%s', // content_snippet
            '%s', // url
            '%s', // categories
            '%s', // parent_categories
            '%s', // tags
            '%s', // recommended_for
            '%s', // attributes_text
            '%s', // regular_price
            '%s', // sale_price
            '%d', // on_sale
            '%d', // parent_id
            '%s', // stock_status
            '%s', // search_blob
            '%s', // menu_titles
            '%s', // taxonomies
            '%s', // average_rating
            '%d', // comment_count
        ];

        return ['data' => $insert_data, 'formats' => $formats];
    }

    /**
     * Extracts and sanitizes content for indexing, supporting configurable extraction modes.
     *
     * Extraction modes:
     * - 'lean' (default): Use excerpt/content, strip tags/shortcodes, minimal processing.
     * - 'aggressive': Use parse_blocks for Gutenberg, DOM parsing for HTML-heavy content.
     *
     * The mode can be set via the 'wcac_indexer_extraction_mode' filter.
     */
    private function extract_indexable_content($post)
    {
        $mode = apply_filters('wcac_indexer_extraction_mode', 'lean', $post);
        $raw_content = '';
        $log_prefix = "WCAC Indexer Extraction [Post ID: {$post->ID}]";
        if ($mode === 'aggressive') {
            // Aggressive: Try parse_blocks for Gutenberg, fallback to DOM parsing
            if (function_exists('parse_blocks')) {
                $blocks = parse_blocks($post->post_content);
                $text = $this->extract_text_from_blocks($blocks);
                if (!empty($text)) {
                    $raw_content = $text;
                    error_log("$log_prefix Used parse_blocks for aggressive extraction.");
                }
            }
            if (empty($raw_content)) {
                // Fallback: DOMDocument for HTML
                $raw_content = $this->extract_text_from_html($post->post_content);
                error_log("$log_prefix Used DOMDocument fallback for aggressive extraction.");
            }
        } else {
            // Lean: Use excerpt, then content, strip tags/shortcodes
            $raw_content = $post->post_excerpt;
            if (empty(trim($raw_content))) {
                $raw_content = $post->post_content;
            }
            error_log("$log_prefix Used lean extraction mode.");
        }
        // Always sanitize
        $utf8_content = mb_convert_encoding($raw_content, 'UTF-8', 'UTF-8');
        $no_shortcodes = strip_shortcodes($utf8_content);
        $plain_text = wp_strip_all_tags($no_shortcodes);
        $normalized_text = preg_replace('/\s+/s', ' ', $plain_text);
        $main_content_limited = trim($normalized_text);
        if (empty($main_content_limited)) {
            error_log("$log_prefix Major fields empty after extraction.");
        }
        return $main_content_limited;
    }

    /**
     * Recursively extract text from Gutenberg blocks.
     */
    private function extract_text_from_blocks($blocks)
    {
        $text = '';
        foreach ($blocks as $block) {
            if (isset($block['innerHTML'])) {
                $text .= ' ' . wp_strip_all_tags($block['innerHTML']);
            }
            if (!empty($block['innerBlocks'])) {
                $text .= ' ' . $this->extract_text_from_blocks($block['innerBlocks']);
            }
        }
        return $text;
    }

    /**
     * Extract visible text from HTML using DOMDocument.
     */
    private function extract_text_from_html($html)
    {
        if (empty($html)) {
            return '';
        }
        // Check if DOMDocument class exists
        if (!class_exists('DOMDocument')) {
            error_log('WCAC Indexer Extraction: DOMDocument not available.');
            return '';
        }
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//body//*[not(self::script or self::style)]/text()');
        $text = '';
        foreach ($nodes as $node) {
            $text .= ' ' . $node->nodeValue;
        }
        libxml_clear_errors();
        return $text;
    }

    /**
     * Extract visible text and URLs from HTML using DOMDocument.
     * Strips all HTML, CSS, JS, and preserves only URLs.
     *
     * @param string $html Raw HTML content.
     * @return array [cleaned_text, url_list]
     */
    private function extract_text_and_urls_from_html($html)
    {
        if (empty($html) || !class_exists('DOMDocument')) {
            return ['', []];
        }
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($doc);
        // Remove <script> and <style> elements
        foreach (['script', 'style'] as $tag) {
            foreach ($doc->getElementsByTagName($tag) as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        // Extract visible text
        $nodes = $xpath->query('//body//*[not(self::script or self::style)]/text()');
        $text = '';
        foreach ($nodes as $node) {
            $text .= ' ' . $node->nodeValue;
        }
        // Extract URLs from href/src attributes
        $urls = [];
        foreach (['a' => 'href', 'img' => 'src'] as $tag => $attr) {
            foreach ($doc->getElementsByTagName($tag) as $el) {
                $url = $el->getAttribute($attr);
                if (!empty($url)) {
                    $urls[] = $url;
                }
            }
        }
        libxml_clear_errors();
        return [trim(preg_replace('/\s+/s', ' ', $text)), array_unique($urls)];
    }

    // In format_content_for_llm, replace main content extraction with:
    // $main_content = $this->extract_indexable_content($post);
    // ... and use $main_content for further processing.

    /**
     * Add a candidate brand to the transient/option for later LLM validation.
     * Ensures uniqueness and case-insensitivity.
     *
     * @param string $brand Brand name to add.
     */
    private static function add_candidate_brand(string $brand): void
    {
        $option_key = 'wcac_candidate_brands';
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.OptionFunctions.get_option
        $brands = get_option($option_key, []);
        if (!is_array($brands)) {
            $brands = [];
        }
        $brand_lc = strtolower($brand);
        // Use associative array for uniqueness
        $brands[$brand_lc] = $brand;
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.Functions.OptionFunctions.update_option
        update_option($option_key, $brands, false);
    }

    /**
     * Manually trigger LLM summary queue processing (for admin button).
     */
    public static function trigger_summary_queue_processing(): void
    {
        if (class_exists('ActionScheduler')) {
            as_enqueue_async_action('wcac_process_llm_summary_queue');
        } else {
            self::process_summary_queue();
        }
    }

    /**
     * Get the last summarized content hash for a post.
     */
    private static function get_last_summary_hash(int $post_id): ?string
    {
        return get_post_meta($post_id, '_wcac_summary_hash', true) ?: null;
    }

    /**
     * Set the last summarized content hash for a post.
     */
    private static function set_last_summary_hash(int $post_id, string $hash): void
    {
        update_post_meta($post_id, '_wcac_summary_hash', $hash);
    }

    /**
     * Compute a hash of the content for change detection.
     */
    private static function compute_content_hash(string $content): string
    {
        return md5($content);
    }

    /**
     * Compute a diff between old and new content (simple line diff for now).
     */
    private static function compute_content_diff(string $old, string $new): string
    {
        $old_lines = explode("\n", $old);
        $new_lines = explode("\n", $new);
        $diff = [];
        foreach ($new_lines as $i => $line) {
            if (!isset($old_lines[$i]) || $old_lines[$i] !== $line) {
                $diff[] = '+ ' . $line;
            }
        }
        foreach ($old_lines as $i => $line) {
            if (!isset($new_lines[$i]) || $new_lines[$i] !== $line) {
                $diff[] = '- ' . $line;
            }
        }
        return implode("\n", $diff);
    }

    /**
     * Add a post ID to the LLM summary queue (for background summarization).
     * Sets status to 'pending' in the index table.
     *
     * @param int $post_id
     */
    private static function add_to_summary_queue(int $post_id): void
    {
        $option_key = 'wcac_summary_queue';
        $queue = get_option($option_key, []);
        if (!is_array($queue)) {
            $queue = [];
        }
        if (!in_array($post_id, $queue, true)) {
            $queue[] = $post_id;
            update_option($option_key, $queue, false);
        }
        // Set status to 'pending' in the index table
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';
        $wpdb->update(
            $table_name,
            [ 'llm_summary_status' => 'pending', 'llm_summary_error' => null ],
            [ 'post_id' => $post_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Process the LLM summary queue: for each post ID, generate an incremental summary if content changed.
     * Updates status and error fields in the index table.
     */
    public static function process_summary_queue(): void
    {
        $option_key = 'wcac_summary_queue';
        $queue = get_option($option_key, []);
        if (!is_array($queue) || empty($queue)) {
            return;
        }
        $success_count = 0;
        $fail_count = 0;
        $consecutive_failures = 0;
        $max_consecutive_failures = 5; // Tune as needed
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';
        foreach ($queue as $i => $post_id) {
            $result = Wcac_SummaryHelper::summarize_incremental($post_id);
            if ($result) {
                error_log("WCAC Summary Queue: Successfully summarized Post ID {$post_id}.");
                $success_count++;
                $consecutive_failures = 0;
            } else {
                error_log("WCAC Summary Queue: Failed to summarize Post ID {$post_id}.");
                $fail_count++;
                $consecutive_failures++;
                if ($consecutive_failures >= $max_consecutive_failures) {
                    error_log("WCAC Summary Queue: Aborting after {$consecutive_failures} consecutive failures.");
                    break;
                }
            }
            unset($queue[$i]);
        }
        update_option($option_key, array_values($queue), false);
        error_log("WCAC Summary Queue: Processing complete. Success: {$success_count}, Failures: {$fail_count}.");
    }

    /**
     * Pseudo-method for incremental LLM summarization API call.
     * Replace with actual API integration.
     */
    private static function call_llm_incremental_summary_api(?string $current_summary, string $diff): string
    {
        // TODO: Integrate with actual LLM API (e.g., OpenAI, OpenRouter)
        return 'LLM incremental summary update.\nCurrent summary: ' . ($current_summary ?: '[none]') . '\nDiff: ' . mb_substr($diff, 0, 200) . '...';
    }

    /**
     * Retry failed LLM summaries for the given post IDs.
     * Only re-queues items with status 'error'.
     *
     * @param array $post_ids Array of post IDs to retry.
     * @return int Number of items re-queued.
     */
    public static function retry_failed_summaries(array $post_ids): int
    {
        global $wpdb;
        if (empty($post_ids)) {
            return 0;
        }
        $table_name = $wpdb->prefix . 'wcac_index';
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        // Update all eligible rows in one query
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} SET llm_summary_status = 'pending', llm_summary_error = NULL WHERE llm_summary_status = 'error' AND post_id IN ($placeholders)",
            $post_ids
        ));
        // Update the summary queue option in one go
        $option_key = 'wcac_summary_queue';
        $queue = get_option($option_key, []);
        if (!is_array($queue)) {
            $queue = [];
        }
        // Add all eligible IDs to the queue (avoid duplicates)
        $eligible_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$table_name} WHERE llm_summary_status = 'pending' AND post_id IN ($placeholders)",
            $post_ids
        ));
        $queue = array_unique(array_merge($queue, array_map('intval', $eligible_ids)));
        update_option($option_key, $queue, false);
        return count($eligible_ids);
    }

    /**
     * Ensure optimal indexes exist on the wcac_index table for performance.
     */
    public static function ensure_indexes() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';
        // Add indexes if not present
        $indexes = [
            'post_type' => 'wcac_idx_post_type',
            'llm_summary_status' => 'wcac_idx_llm_status',
            'post_id' => 'wcac_idx_post_id',
        ];
        foreach ($indexes as $col => $idx) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SHOW INDEX FROM {$table_name} WHERE Key_name = %s", $idx
            ));
            if (!$exists) {
                $wpdb->query("CREATE INDEX {$idx} ON {$table_name} ({$col})");
            }
        }
    }
} // End of Wcac_Indexer class

/**
 * Dummy WP_Post docblock for linter.
 * @see https://developer.wordpress.org/reference/classes/wp_post/
 */
if (!class_exists('WP_Post')) {
    class WP_Post
    {
    }
}

// Action Scheduler integration for LLM summary queue (outside class)
if (class_exists('ActionScheduler')) {
    add_action('wcac_process_llm_summary_queue', ['Wcac_Indexer', 'process_summary_queue']);
    if (!as_next_scheduled_action('wcac_process_llm_summary_queue')) {
        as_schedule_recurring_action(time() + 60, 600, 'wcac_process_llm_summary_queue'); // every 10 min
    }
}

// Register custom bulk action for retrying failed LLM summaries
add_filter('bulk_actions-edit-wcac_index', function($bulk_actions) {
    $bulk_actions['wcac_retry_llm_summary'] = __('Retry LLM Summary', 'wcac');
    return $bulk_actions;
});

// Handle the custom bulk action
add_filter('handle_bulk_actions-edit-wcac_index', function($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'wcac_retry_llm_summary') {
        return $redirect_to;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'wcac_index';
    $retried = 0;
    foreach ($post_ids as $post_id) {
        $status = $wpdb->get_var($wpdb->prepare("SELECT llm_summary_status FROM {$table_name} WHERE post_id = %d", $post_id));
        if ($status === 'error') {
            // Re-queue for summarization
            if (method_exists('Wcac_Indexer', 'add_to_summary_queue')) {
                Wcac_Indexer::add_to_summary_queue((int)$post_id);
                $retried++;
            }
        }
    }
    $redirect_to = add_query_arg('wcac_retried', $retried, $redirect_to);
    return $redirect_to;
}, 10, 3);

// Admin notice for retried items
add_action('admin_notices', function() {
    if (!empty($_REQUEST['wcac_retried'])) {
        $count = intval($_REQUEST['wcac_retried']);
        printf('<div id="message" class="updated notice is-dismissible"><p>%s</p></div>', esc_html(sprintf(_n('%d item re-queued for LLM summary.', '%d items re-queued for LLM summary.', $count, 'wcac'), $count)));
    }
});
