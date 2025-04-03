#!/bin/bash

# Process a JSON file on the remote server to create a WordPress post
# Usage: ./process-json.sh filename.json
# Example: ./process-json.sh nijv-zgpvh-campaign-view-com-ad1ec848509c58ac4de2ae7db367042c-final_content-20250402-232736.json

set -e  # Exit on error

# Check if filename is provided
if [ $# -eq 0 ]; then
    echo "Error: No filename specified"
    echo "Usage: $0 filename.json"
    exit 1
fi

JSON_FILENAME=$1
SSH_KEY="/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem"
SSH_USER="staging"
SSH_HOST="45.33.31.79"
WP_PATH="/home/staging/public_html"
TEMP_SCRIPT_PATH="/tmp/process-json-${RANDOM}.php"

# Create a temporary PHP script
cat << 'EOT' > $TEMP_SCRIPT_PATH
<?php
/**
 * Process JSON file to WordPress post
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bootstrap WordPress
require_once('wp-load.php');

// Get filename from command line argument
$filename = $argv[1] ?? '';

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

echo "JSON file contains title: " . $json_data['title'] . "\n";

// Load UTG plugin classes if not already loaded
if (!class_exists('\\UTG\\Generator\\Post_Generator')) {
    echo "Loading UTG plugin classes...\n";
    $plugin_dir = ABSPATH . 'wp-content/plugins/url-to-gutenberg/';
    require_once($plugin_dir . 'includes/class-settings.php');
    require_once($plugin_dir . 'includes/generator/class-media-handler.php');
    require_once($plugin_dir . 'includes/generator/class-post-generator.php');
}

// Create instances of necessary classes
try {
    echo "Creating class instances...\n";
    $settings = new \UTG\Settings();
    $media_handler = new \UTG\Generator\Media_Handler($settings);
    $post_generator = new \UTG\Generator\Post_Generator($media_handler, $settings);

    echo "Creating post with title: " . $json_data['title'] . "\n";

    // Create dummy URL for source tracking
    $source_url = 'https://json-import/' . $filename;

    // Create post from API response
    echo "Calling create_post_from_api_response()...\n";
    $post_id = $post_generator->create_post_from_api_response($json_data, $source_url);

    // Handle the result
    if (is_wp_error($post_id)) {
        echo "Error creating post: " . $post_id->get_error_message() . "\n";
        exit(1);
    }

    echo "Success! Post created with ID: $post_id\n";
    echo "Edit URL: " . admin_url("post.php?post={$post_id}&action=edit") . "\n";
    echo "View URL: " . get_permalink($post_id) . "\n";
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);
EOT

# Transfer the script to the server
echo "Transferring script to server..."
scp -i $SSH_KEY $TEMP_SCRIPT_PATH $SSH_USER@$SSH_HOST:/tmp/process-json.php

# Execute the script on the server
echo "Executing script on server with filename: $JSON_FILENAME"
ssh -i $SSH_KEY $SSH_USER@$SSH_HOST "cd $WP_PATH && php /tmp/process-json.php '$JSON_FILENAME'"

# Clean up
rm $TEMP_SCRIPT_PATH
echo "Processing complete!" 