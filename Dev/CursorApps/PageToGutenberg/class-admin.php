<?php
/**
 * Admin class.
 *
 * @package UTG
 */

namespace UTG\Admin;

use UTG\API\LLM_API;
use UTG\Generator\Post_Generator;
use UTG\Settings;

/**
 * Admin functionality for URL to Gutenberg
 */
class UTG_Admin {
    // ... existing code ...

    /**
     * Convert URL to Gutenberg blocks.
     */
    public function convert_url() {
        try {
            error_log('UTG: Starting URL conversion process');

            // Verify nonce and user capabilities
            $this->verify_ajax_nonce();

            // Get URL from POST data
            $url = isset($_POST['url']) ? \esc_url_raw($_POST['url']) : '';
            if (empty($url)) {
                error_log('UTG: Empty URL provided');
                \wp_send_json_error('URL cannot be empty');
                return;
            }

            error_log('UTG: Processing URL: ' . $url);

            // Process URL
            $api = new \UTG\API\LLM_API($this->settings);
            $response = $api->process_url($url);

            error_log('UTG: API Response: ' . print_r($response, true));

            if (\is_wp_error($response)) {
                error_log('UTG: API Error: ' . $response->get_error_message());
                \wp_send_json_error($response->get_error_message());
                return;
            }

            // Create post
            $post_id = \wp_insert_post([
                'post_title' => $response['title'],
                'post_content' => $response['content'],
                'post_status' => 'draft',
                'post_type' => 'post'
            ]);

            if (\is_wp_error($post_id)) {
                error_log('UTG: Post creation error: ' . $post_id->get_error_message());
                \wp_send_json_error('Failed to create post: ' . $post_id->get_error_message());
                return;
            }

            error_log('UTG: Post created successfully with ID: ' . $post_id);

            \wp_send_json_success([
                'post_id' => $post_id,
                'edit_url' => \get_edit_post_link($post_id, 'raw'),
                'view_url' => \get_permalink($post_id),
                'message' => \__('Post created successfully!', 'url-to-gutenberg')
            ]);

        } catch (\Exception $e) {
            error_log('UTG: Exception in convert_url: ' . $e->getMessage());
            error_log('UTG: Exception trace: ' . $e->getTraceAsString());
            \wp_send_json_error('An error occurred: ' . $e->getMessage());
        }
    }

    // ... rest of the existing code ...
} 