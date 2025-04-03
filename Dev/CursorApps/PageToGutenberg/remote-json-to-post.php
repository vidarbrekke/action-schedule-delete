<?php
/**
 * JSON to Post - Convert a JSON file from utg-debug to a WordPress post
 * 
 * This script is intended to be deployed and run on the remote server.
 */

if (!defined('ABSPATH')) {
    die('This script must be run within WordPress.');
}

// Process command line arguments if run via CLI
$filename = '';
if (defined('WP_CLI') && WP_CLI) {
    $filename = isset($args[0]) ? $args[0] : '';
} elseif (isset($_GET['filename'])) {
    // Or process GET parameter if run via browser
    $filename = sanitize_text_field($_GET['filename']);
}

if (empty($filename)) {
    echo "Error: Missing filename parameter.\n";
    exit(1);
}

// Base path for JSON files
$base_path = ABSPATH . 'wp-content/uploads/utg-debug/';
$json_file_path = $base_path . $filename;

// Verify that the JSON file exists
if (!file_exists($json_file_path)) {
    echo "Error: JSON file not found: $json_file_path\n";
    exit(1);
}

echo "Processing JSON file: $json_file_path\n";

// Read and parse the JSON file
$json_content = file_get_contents($json_file_path);
if (!$json_content) {
    echo "Error: Could not read JSON file.\n";
    exit(1);
}

$json_data = json_decode($json_content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Error: Invalid JSON file: " . json_last_error_msg() . "\n";
    exit(1);
}

// Verify required fields
if (!isset($json_data['title'])) {
    echo "Error: JSON file missing 'title' field.\n";
    exit(1);
}

// Make sure the UTG plugin is loaded and active
if (!class_exists('\\UTG\\Generator\\Post_Generator')) {
    echo "Error: URL to Gutenberg plugin classes not found. Is the plugin active?\n";
    exit(1);
}

// Create instances of necessary classes
$settings = new \UTG\Settings();
$media_handler = new \UTG\Generator\Media_Handler($settings);
$post_generator = new \UTG\Generator\Post_Generator($media_handler, $settings);

echo "Creating post with title: " . $json_data['title'] . "\n";

// Create dummy URL for source tracking
$source_url = 'https://json-import/' . $filename;

// Create post from API response
$post_id = $post_generator->create_post_from_api_response($json_data, $source_url);

// Handle the result
if (is_wp_error($post_id)) {
    echo "Error creating post: " . $post_id->get_error_message() . "\n";
    exit(1);
}

echo "Success! Post created with ID: $post_id\n";
echo "Edit URL: " . admin_url("post.php?post={$post_id}&action=edit") . "\n";
echo "View URL: " . get_permalink($post_id) . "\n";

exit(0); 