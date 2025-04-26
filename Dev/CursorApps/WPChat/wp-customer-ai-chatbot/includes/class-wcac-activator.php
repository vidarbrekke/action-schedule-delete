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
	 * @since    0.1.0
	 */
	public static function activate(): void {
		// Set default options if they don't exist yet.
		if ( false === get_option( 'wcac_settings' ) ) {
			$default_settings = [
				'wcac_api_key' => '',
                // Add other default settings here as needed in the future
			];
			add_option( 'wcac_settings', $default_settings );
		}

		// Placeholder for other activation tasks (e.g., flushing rewrite rules if CPTs were added)
	}

} 