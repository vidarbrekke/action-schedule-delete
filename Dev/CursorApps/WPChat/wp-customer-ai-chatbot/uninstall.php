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

// Ensure required WordPress functions are loaded
if (!function_exists('delete_option')) {
	require_once ABSPATH . 'wp-includes/option.php';
}
if (!function_exists('delete_site_option')) {
	require_once ABSPATH . 'wp-includes/option.php';
}
if (!function_exists('delete_transient')) {
	require_once ABSPATH . 'wp-includes/option.php';
}
if (!function_exists('delete_site_transient')) {
	require_once ABSPATH . 'wp-includes/option.php';
}
if (!function_exists('is_multisite')) {
	require_once ABSPATH . 'wp-includes/load.php';
}
if (!function_exists('switch_to_blog')) {
	require_once ABSPATH . 'wp-includes/ms-blogs.php';
}
if (!function_exists('restore_current_blog')) {
	require_once ABSPATH . 'wp-includes/ms-blogs.php';
}

function wcac_drop_custom_tables($wpdb) {
	$tables = [
		$wpdb->prefix . 'wcac_index',
		$wpdb->prefix . 'wcac_debug_logs',
		$wpdb->prefix . 'wcac_relationships',
	];
	foreach ($tables as $table) {
		$wpdb->query("DROP TABLE IF EXISTS $table");
	}
}

function wcac_run_uninstall_cleanup() {
	global $wpdb;
	// Delete plugin options
	$options = [
		'wcac_settings',
		'wcac_index_meta',
		'wcac_site_profile',
		'wcac_options',
	];
	foreach ($options as $opt) {
		delete_option($opt);
		delete_site_option($opt);
	}
	// Delete transients
	$transients = [
		'wcac_indexed_category_names',
	];
	foreach ($transients as $transient) {
		delete_transient($transient);
		delete_site_transient($transient);
	}
	// Drop custom tables if they exist
	if (function_exists('is_multisite') && is_multisite()) {
		$blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
		foreach ($blog_ids as $blog_id) {
			switch_to_blog($blog_id);
			wcac_drop_custom_tables($wpdb);
			restore_current_blog();
		}
	} else {
		wcac_drop_custom_tables($wpdb);
	}
}

wcac_run_uninstall_cleanup();

// TODO: Add cleanup code here (delete options, transients, custom tables, etc.)
// Example: delete_option('wcac_settings'); 