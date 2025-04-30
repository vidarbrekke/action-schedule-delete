<?php
declare(strict_types=1);

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes
 */
class Wcac_Activator {

	/**
	 * Activation logic.
	 *
	 * @since    0.1.0 (Modified 0.1.4 to add table creation)
	 */
	public static function activate(): void {
		// error_log("WCAC Activator DEBUG: Entering activate() method."); // Remove this log
		// Set default options if they don't exist yet.
		if ( false === get_option( 'wcac_settings' ) ) {
			$default_settings = [
				'wcac_api_key' => '',
				'wcac_index_products' => true, // Default to indexing products
				'wcac_index_pages' => false,
				'wcac_index_posts' => false,
				'wcac_negative_keywords' => '',
				'wcac_system_prompt' => '', // Let default be handled in Wcac_Public
				'wcac_site_prompt' => '',
				'wcac_custom_css' => '',
				// LLM API Parameters
				'wcac_temperature' => '0.7',    // Default temperature
				'wcac_top_p' => '0.9',         // Default top_p
				'wcac_max_tokens' => '800',    // Default max tokens
				'wcac_frequency_penalty' => '0', // Default frequency penalty
				'wcac_presence_penalty' => '0',  // Default presence penalty
				'wcac_model' => 'nousresearch/nous-hermes-2-mixtral-8x7b-dpo', // Default model
			];
			add_option( 'wcac_settings', $default_settings );
		}

		self::create_index_table();

		// Placeholder for other activation tasks (e.g., flushing rewrite rules if CPTs were added)
	}

	/**
	 * Create the custom database table for the index.
	 *
	 * @since 0.1.4
	 * @access private
	 */
	private static function create_index_table(): void {
		// error_log("WCAC Activator DEBUG: Entering create_index_table function."); // Keep original log if desired, or remove
		global $wpdb;
		$table_name = $wpdb->prefix . 'wcac_index';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			post_id BIGINT UNSIGNED NOT NULL,
			post_type VARCHAR(20) NOT NULL,
			title TEXT NULL,
			content_snippet TEXT NULL,
			url VARCHAR(2083) NULL,
			categories TEXT NULL, -- Storing as JSON
			tags TEXT NULL, -- Storing as JSON
			regular_price VARCHAR(20) NULL,
			sale_price VARCHAR(20) NULL,
			on_sale TINYINT(1) NOT NULL DEFAULT 0,
			parent_id BIGINT UNSIGNED NULL,
			last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (post_id),
			KEY post_type (post_type),
			KEY on_sale (on_sale),
			KEY parent_id (parent_id),
			FULLTEXT KEY title_content (title, content_snippet)
		) $charset_collate;";
		// error_log("WCAC Activator DEBUG: SQL prepared: " . $sql);

		// Include upgrade.php for dbDelta function
		// error_log("WCAC Activator DEBUG: Attempting to include upgrade.php. ABSPATH is: " . (defined('ABSPATH') ? ABSPATH : 'Not Defined'));
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		// error_log("WCAC Activator DEBUG: upgrade.php included successfully.");

		// error_log("WCAC Activator DEBUG: Attempting to call dbDelta.");
		dbDelta( $sql );
		// error_log("WCAC Activator DEBUG: dbDelta finished. Checking for WPDB errors.");

		// Check for errors after dbDelta
		// if ( ! empty( $wpdb->last_error ) ) {
		//     error_log( 'WCAC Activator WPDB Error after dbDelta: ' . $wpdb->last_error );
		// } else {
		//      error_log( 'WCAC Activator DEBUG: No WPDB error detected after dbDelta.' );
		// }

		error_log("WCAC Activator: Attempted to create/update custom table: {$table_name}"); // Keep final original log
	}

} 