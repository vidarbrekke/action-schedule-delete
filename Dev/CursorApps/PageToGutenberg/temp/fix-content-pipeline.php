<?php
/**
 * Diagnostic and fix script for Content Pipeline issues
 * 
 * Usage:
 * - Upload this file to the server
 * - Run: wp eval-file fix-content-pipeline.php <url>
 */

// Check for arguments
if (!isset($args[0])) {
    WP_CLI::error('Please provide a URL to test.');
    return;
}

$url = $args[0];
WP_CLI::log("Starting diagnostic for URL: $url");

// Step 1: Force debug mode
update_option('utg_settings', ['debug_mode' => 1]);
WP_CLI::log("Debug mode enabled");

// Step 2: Enhance Content_Pipeline with better debugging
class Enhanced_Content_Pipeline extends UTG\Content_Pipeline {
    public function process_url($url, $options = []) {
        // Always ensure we have the process_images option enabled
        $options['process_images'] = true;
        $options['save_debug'] = true;
        
        // Call parent method with custom implementation
        try {
            // Step 1: Get content from URL
            $extractor = new UTG\Content_Extractor($this->settings);
            $extracted_content = $extractor->extract($url);
            if (is_wp_error($extracted_content)) {
                WP_CLI::error("Extraction error: " . $extracted_content->get_error_message());
                return $extracted_content;
            }
            
            WP_CLI::log("Content extracted successfully. Length: " . strlen($extracted_content['content']));
            WP_CLI::log("Images found: " . count($extracted_content['images']));
            
            // Step 2: Process with LLM API
            $llm_options = array_merge($options, [
                'debug' => true
            ]);
            
            WP_CLI::log("Processing with LLM...");
            $llm_result = $this->llm_api->process_url($url, $llm_options);
            
            if (is_wp_error($llm_result)) {
                WP_CLI::error("LLM error: " . $llm_result->get_error_message());
                return $llm_result;
            }
            
            WP_CLI::log("LLM processing successful");
            
            // Save raw LLM output for debugging
            $upload_dir = wp_upload_dir();
            $debug_dir = $upload_dir['basedir'] . '/utg-debug';
            if (!file_exists($debug_dir)) {
                wp_mkdir_p($debug_dir);
            }
            
            $timestamp = date('Ymd-His');
            $llm_filename = $debug_dir . '/llm-response-' . $timestamp . '.llm.json';
            file_put_contents($llm_filename, json_encode($llm_result, JSON_PRETTY_PRINT));
            WP_CLI::log("Raw LLM output saved to: " . $llm_filename);
            
            // Step 3: Process images
            WP_CLI::log("Processing images...");
            $blocks_with_images = $llm_result['content'] ?? '';
            
            // Force process all images regardless of options
            $blocks_with_images = $this->media_handler->process_content_images($blocks_with_images);
            
            WP_CLI::log("Images processed");
            
            // Save processed content for debugging
            $processed_filename = $debug_dir . '/processed-blocks-' . $timestamp . '.html';
            file_put_contents($processed_filename, $blocks_with_images);
            WP_CLI::log("Processed blocks saved to: " . $processed_filename);
            
            // Step 4: Generate WordPress post
            $post_data = [
                'post_title' => $llm_result['title'] ?? '',
                'post_content' => $blocks_with_images,
                'post_status' => isset($options['post_status']) ? $options['post_status'] : 'draft',
                'post_type' => isset($options['post_type']) ? $options['post_type'] : 'post',
            ];
            
            WP_CLI::log("Creating post...");
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                WP_CLI::error("Post creation error: " . $post_id->get_error_message());
                return $post_id;
            }
            
            WP_CLI::success("Post created successfully with ID: " . $post_id);
            
            return [
                'post_id' => $post_id,
                'edit_url' => get_edit_post_link($post_id, 'raw'),
                'view_url' => get_permalink($post_id),
                'title' => $llm_result['title'] ?? '',
                'success' => true,
            ];
            
        } catch (Exception $e) {
            WP_CLI::error("Exception: " . $e->getMessage());
            WP_CLI::log("Stack trace: " . $e->getTraceAsString());
            return new WP_Error('process_exception', $e->getMessage());
        }
    }
}

// Step 3: Create enhanced Media Handler
class Enhanced_Media_Handler extends UTG\Generator\UTG_Media_Handler {
    public function process_content_images($content) {
        WP_CLI::log("Enhanced Media Handler: Processing images");
        
        if (empty($content)) {
            return $content;
        }
        
        // Pattern to match image blocks
        $pattern = '/(<!-- wp:image[^>]*?-->)[\s\S]*?<img[^>]*?src="([^"]+)"[^>]*?\/?>[\s\S]*?(<!-- \/wp:image -->)/i';
        
        // Find all matches first to see what we're working with
        preg_match_all($pattern, $content, $all_matches, PREG_SET_ORDER);
        WP_CLI::log("Found " . count($all_matches) . " image blocks");
        
        foreach ($all_matches as $index => $match) {
            WP_CLI::log("Image $index: " . substr($match[2], 0, 50));
        }
        
        // Now process them
        $processed_content = preg_replace_callback($pattern, function($matches) {
            $opening_tag = $matches[1];
            $img_url = $matches[2];
            $closing_tag = $matches[3];
            $block_content = $matches[0];
            
            WP_CLI::log("Processing image: " . $img_url);
            
            // Skip if it's not a valid URL
            if (empty($img_url) || !filter_var($img_url, FILTER_VALIDATE_URL)) {
                WP_CLI::warning("Invalid image URL: " . substr($img_url, 0, 100));
                return $block_content;
            }
            
            // Extract alt text if available
            $alt_text = '';
            if (preg_match('/alt="([^"]*)"/', $block_content, $alt_matches)) {
                $alt_text = $alt_matches[1];
            }
            
            // Download the image and get the attachment ID
            WP_CLI::log("Downloading image: " . $img_url);
            $attachment_id = $this->save_remote_image($img_url, $alt_text);
            
            if (is_wp_error($attachment_id)) {
                WP_CLI::warning("Failed to download image: " . $img_url . " - " . $attachment_id->get_error_message());
                return $block_content;
            }
            
            WP_CLI::log("Image downloaded and saved with ID: " . $attachment_id);
            
            // Get the new local URL for the image
            $local_url = wp_get_attachment_url($attachment_id);
            
            if (!$local_url) {
                WP_CLI::warning("Failed to get local URL for attachment ID: " . $attachment_id);
                return $block_content;
            }
            
            WP_CLI::log("Local URL: " . $local_url);
            
            // Update the src attribute in the img tag
            $updated_block_content = preg_replace('/src="[^"]+"/', 'src="' . esc_url($local_url) . '"', $block_content);
            
            // Add class="wp-image-{id}" if not already present
            if (strpos($updated_block_content, 'class="') !== false) {
                $updated_block_content = preg_replace('/class="([^"]*)"/', 'class="$1 wp-image-' . $attachment_id . '"', $updated_block_content);
            } else {
                $updated_block_content = preg_replace('/<img/', '<img class="wp-image-' . $attachment_id . '"', $updated_block_content);
            }
            
            // Update the JSON attributes in the opening tag to include the ID
            if (strpos($opening_tag, 'data-id=') === false) {
                $updated_opening_tag = str_replace('<!-- wp:image', '<!-- wp:image {"id":' . $attachment_id . '}', $opening_tag);
                $updated_block_content = str_replace($opening_tag, $updated_opening_tag, $updated_block_content);
            } else {
                // Replace existing ID
                $updated_block_content = preg_replace('/data-id="[^"]+"/', 'data-id="' . $attachment_id . '"', $updated_block_content);
            }
            
            WP_CLI::log("Image block updated");
            return $updated_block_content;
            
        }, $content);
        
        WP_CLI::log("All image blocks processed");
        
        return $processed_content;
    }
}

// Step 4: Run with enhanced classes
$settings = new UTG\Settings();
$media_handler = new Enhanced_Media_Handler($settings);
$pipeline = new Enhanced_Content_Pipeline();

// Inject the enhanced Media Handler into the pipeline
$reflection = new ReflectionClass($pipeline);
$property = $reflection->getProperty('media_handler');
$property->setAccessible(true);
$property->setValue($pipeline, $media_handler);

// Process the URL
WP_CLI::log("Running pipeline with URL: $url");
$result = $pipeline->process_url($url, ['save_debug' => true, 'process_images' => true]);

if (is_wp_error($result)) {
    WP_CLI::error("Pipeline error: " . $result->get_error_message());
} else {
    WP_CLI::success("Pipeline completed successfully");
    WP_CLI::log("Post ID: " . $result['post_id']);
    WP_CLI::log("Edit URL: " . $result['edit_url']);
    WP_CLI::log("View URL: " . $result['view_url']);
}
