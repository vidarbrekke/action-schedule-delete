<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Wcac_Customer_AI_Chatbot
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// TODO: Add cleanup code here (delete options, transients, custom tables, etc.)
// Example: delete_option('wcac_settings'); 