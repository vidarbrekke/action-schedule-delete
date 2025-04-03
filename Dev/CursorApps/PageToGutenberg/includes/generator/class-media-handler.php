<?php
/**
 * Media Handler class.
 *
 * @package UTG
 */

namespace UTG\Generator;

/**
 * Media Handler - Handles saving images to the WordPress Media Library
 */
class UTG_Media_Handler {
    /**
     * Settings instance
     *
     * @var UTG_Settings|null
     */
    private $settings;

    /**
     * Constructor
     *
     * @param UTG_Settings|null $settings Optional. Settings instance.
     */
    public function __construct($settings = null) {
        $this->settings = $settings;
    }

    /**
     * Save base64 encoded image to Media Library
     *
     * @param string $base64_data Base64-encoded image data.
     * @param string $filename    Optional. Filename to use. Default 'utg-image.png'.
     * @return int|WP_Error Attachment ID on success, WP_Error on failure.
     */
    public function save_base64_image($base64_data, $filename = 'utg-image.png') {
        // Ensure we have valid base64 data
        if (strpos($base64_data, ';base64,') !== false) {
            list(, $base64_data) = explode(';base64,', $base64_data);
        }
        
        $decoded_data = base64_decode($base64_data);
        
        if ($decoded_data === false) {
            $this->log_error('Base64 decode failed', 'Invalid base64 image data for file: ' . $filename);
            return new WP_Error('invalid_base64', __('Invalid base64 image data', 'url-to-gutenberg'));
        }
        
        // Get WordPress upload directory
        $upload_dir = wp_upload_dir();
        if (isset($upload_dir['error']) && !empty($upload_dir['error'])) {
            $this->log_error('Upload directory error', $upload_dir['error']);
            return new WP_Error('upload_dir_error', $upload_dir['error']);
        }
        
        // Create temporary file
        $temp_file = wp_tempnam($filename);
        if (!$temp_file) {
            $this->log_error('Temp file creation failed', 'Could not create temporary file for: ' . $filename);
            return new WP_Error('temp_file_error', __('Could not create temporary file', 'url-to-gutenberg'));
        }
        
        // Write decoded data to temp file
        $bytes_written = file_put_contents($temp_file, $decoded_data);
        if ($bytes_written === false) {
            @unlink($temp_file);
            $this->log_error('File write failed', 'Could not write to temporary file: ' . $temp_file);
            return new WP_Error('file_write_error', __('Could not write to temporary file', 'url-to-gutenberg'));
        }
        
        // Determine MIME type
        $filetype = wp_check_filetype($filename, null);
        if (empty($filetype['type'])) {
            @unlink($temp_file);
            $this->log_error('MIME type detection failed', 'Could not determine MIME type for: ' . $filename);
            return new WP_Error('mime_type_error', __('Could not determine file type', 'url-to-gutenberg'));
        }
        
        // Prepare attachment data
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filename)),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        // Insert attachment into WordPress Media Library
        $attachment_id = wp_insert_attachment($attachment, $temp_file);
        
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            $this->log_error('Attachment creation failed', $attachment_id->get_error_message());
            return $attachment_id;
        }
        
        // Generate metadata for the attachment
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $temp_file);
        
        if (empty($attachment_data)) {
            $this->log_error('Metadata generation failed', 'Could not generate attachment metadata for: ' . $attachment_id);
            // Don't return error here, as the attachment was created successfully
        }
        
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // Clean up
        @unlink($temp_file);
        
        $this->log_debug('Image saved', sprintf('Base64 image saved with ID: %d, filename: %s', $attachment_id, $filename));
        return $attachment_id;
    }
    
    /**
     * Save remote image to Media Library
     *
     * @param string $url      URL of the image to download.
     * @param string $alt_text Optional. Alt text for the image.
     * @return int|WP_Error Attachment ID on success, WP_Error on failure.
     */
    public function save_remote_image($url, $alt_text = '') {
        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->log_error('Invalid URL', 'Invalid image URL: ' . substr($url, 0, 100));
            return new WP_Error('invalid_url', __('Invalid image URL', 'url-to-gutenberg'));
        }
        
        // Check if image with this URL has already been imported
        $existing_attachment = $this->get_attachment_by_url($url);
        if ($existing_attachment) {
            $this->log_debug('Using existing image', sprintf('Found existing image with ID: %d for URL: %s', $existing_attachment, $url));
            return $existing_attachment;
        }
        
        // Download image to temp file
        $temp_file = download_url($url);
        
        if (is_wp_error($temp_file)) {
            $this->log_error('Download failed', sprintf('Failed to download image from URL: %s - Error: %s', $url, $temp_file->get_error_message()));
            return $temp_file;
        }
        
        // Get file name from URL
        $filename = basename(parse_url($url, PHP_URL_PATH));
        if (empty($filename)) {
            $filename = 'utg-image-' . time() . '.jpg';
        }
        
        // Determine file type
        $filetype = wp_check_filetype($filename, null);
        if (empty($filetype['type'])) {
            @unlink($temp_file);
            $this->log_error('MIME type detection failed', 'Could not determine MIME type for: ' . $filename);
            return new WP_Error('mime_type_error', __('Could not determine file type', 'url-to-gutenberg'));
        }
        
        // Prepare attachment data
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_text_field($alt_text ?: preg_replace('/\.[^.]+$/', '', $filename)),
            'post_content'   => '',
            'post_excerpt'   => sanitize_text_field($alt_text),
            'post_status'    => 'inherit',
            'meta_input'     => array(
                '_utg_source_url' => esc_url_raw($url),
            ),
        );
        
        // Insert attachment into WordPress Media Library
        $attachment_id = wp_insert_attachment($attachment, $temp_file);
        
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            $this->log_error('Attachment creation failed', sprintf('Failed to create attachment for URL: %s - Error: %s', $url, $attachment_id->get_error_message()));
            return $attachment_id;
        }
        
        // Generate metadata for the attachment
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $temp_file);
        
        if (empty($attachment_data)) {
            $this->log_error('Metadata generation failed', 'Could not generate attachment metadata for: ' . $attachment_id);
            // Don't return error here, as the attachment was created successfully
        }
        
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // Clean up
        @unlink($temp_file);
        
        $this->log_debug('Remote image saved', sprintf('Image from URL: %s saved with ID: %d', $url, $attachment_id));
        return $attachment_id;
    }
    
    /**
     * Get attachment ID by URL
     *
     * @param string $url URL of the image.
     * @return int|false Attachment ID if found, false otherwise.
     */
    private function get_attachment_by_url($url) {
        global $wpdb;
        
        // Check in meta for our custom field first
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_utg_source_url' AND meta_value = %s LIMIT 1",
            $url
        ));
        
        if ($attachment_id) {
            return (int) $attachment_id;
        }
        
        // Fall back to standard WordPress method
        $attachment_id = attachment_url_to_postid($url);
        
        if ($attachment_id) {
            return $attachment_id;
        }
        
        return false;
    }
    
    /**
     * Log error message
     *
     * @param string $title   Error title.
     * @param string $message Error message.
     */
    private function log_error($title, $message) {
        if ($this->settings && $this->settings->get('debug_mode')) {
            error_log(sprintf('[URL to Gutenberg] ERROR: %s - %s', $title, $message));
        }
    }
    
    /**
     * Log debug message
     *
     * @param string $title   Debug title.
     * @param string $message Debug message.
     */
    private function log_debug($title, $message) {
        if ($this->settings && $this->settings->get('debug_mode')) {
            error_log(sprintf('[URL to Gutenberg] DEBUG: %s - %s', $title, $message));
        }
    }

    /**
     * Process image blocks in serialized Gutenberg content
     *
     * @param string $content Serialized Gutenberg blocks content.
     * @return string Updated content with local image references.
     */
    public function process_gutenberg_image_blocks($content) {
        if (empty($content)) {
            return $content;
        }
        
        $this->log_debug('Processing image blocks', 'Starting to process image blocks in content');
        
        // Pattern to match image blocks
        $pattern = '/(<!-- wp:image[^>]*?-->)[\s\S]*?<img[^>]*?src="([^"]+)"[^>]*?\/?>[\s\S]*?(<!-- \/wp:image -->)/i';
        
        $processed_content = preg_replace_callback($pattern, function($matches) {
            $opening_tag = $matches[1];
            $img_url = $matches[2];
            $closing_tag = $matches[3];
            $block_content = $matches[0];
            
            // Skip if it's not a valid URL
            if (empty($img_url) || !filter_var($img_url, FILTER_VALIDATE_URL)) {
                $this->log_debug('Invalid image URL', 'Skipping invalid URL: ' . substr($img_url, 0, 100));
                return $block_content;
            }
            
            // Extract alt text if available
            $alt_text = '';
            if (preg_match('/alt="([^"]*)"/', $block_content, $alt_matches)) {
                $alt_text = $alt_matches[1];
            }
            
            // Download the image and get the attachment ID
            $attachment_id = $this->save_remote_image($img_url, $alt_text);
            
            if (is_wp_error($attachment_id)) {
                $this->log_error('Image download failed', 'Failed to download image: ' . $img_url . ' - ' . $attachment_id->get_error_message());
                return $block_content;
            }
            
            // Get the new local URL for the image
            $local_url = wp_get_attachment_url($attachment_id);
            
            if (!$local_url) {
                $this->log_error('Failed to get local URL', 'Could not get local URL for attachment ID: ' . $attachment_id);
                return $block_content;
            }
            
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
            
            $this->log_debug('Image block processed', 'Processed image block with URL: ' . $img_url);
            return $updated_block_content;
            
        }, $content);
        
        $this->log_debug('Image blocks processing complete', 'Finished processing image blocks in content');
        
        return $processed_content;
    }
} 