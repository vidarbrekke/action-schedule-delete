<?php
/**
 * WP-CLI command to test the JSON to post conversion
 */

// Register command
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('utg test-json', function() {
        // Define the JSON file
        $json_file = 'nijv-zgpvh-campaign-view-com-ad1ec848509c58ac4de2ae7db367042c-final_content-20250403-013508.json';
        
        // Process the file
        WP_CLI::log("Processing file: $json_file");
        $result = process_json_file($json_file);
        
        // Check result
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        } else {
            WP_CLI::success("Post created successfully with ID: " . $result['post_id']);
            WP_CLI::log("Edit URL: " . $result['edit_url']);
            WP_CLI::log("View URL: " . $result['view_url']);
        }
    });
} 