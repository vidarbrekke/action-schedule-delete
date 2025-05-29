<?php
/**
 * Simple fix script for Content Pipeline issues
 * 
 * Usage:
 * - Upload this file to the server
 * - Run: wp eval-file simple-fix.php <url>
 */

// Check for arguments
if (!isset($args[0])) {
    WP_CLI::error('Please provide a URL to test.');
    return;
}

$url = $args[0];
WP_CLI::log("Starting processing for URL: $url");

// Force debug mode
update_option('utg_settings', ['debug_mode' => true]);
WP_CLI::log("Debug mode enabled");

// Get the Content_Pipeline instance and modify the URL processing parameters
$pipeline = new UTG\Content_Pipeline();
$result = $pipeline->process_url($url, [
    'process_images' => true,
    'save_debug' => true
]);

// Save the LLM result separately
if (!is_wp_error($result)) {
    WP_CLI::success("Pipeline completed successfully");
    WP_CLI::log("Post ID: " . $result['post_id']);
    WP_CLI::log("Edit URL: " . $result['edit_url']);
    WP_CLI::log("View URL: " . $result['view_url']);
} else {
    WP_CLI::error("Pipeline error: " . $result->get_error_message());
} 