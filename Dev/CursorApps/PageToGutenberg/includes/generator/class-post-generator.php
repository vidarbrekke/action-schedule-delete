<?php
/**
 * Post Generator class.
 *
 * @package UTG
 */

namespace UTG\Generator;

/**
 * Post Generator - Creates WordPress posts from LLM API responses
 */
class Post_Generator {
    /**
     * Media handler instance
     *
     * @var Media_Handler
     */
    private $media_handler;
    
    /**
     * Settings instance
     *
     * @var UTG_Settings
     */
    private $settings;
    
    /**
     * Constructor
     *
     * @param Media_Handler $media_handler Media handler instance.
     * @param UTG_Settings|null $settings      Optional. Settings instance.
     */
    public function __construct($media_handler, $settings = null) {
        $this->media_handler = $media_handler;
        $this->settings = $settings;
    }
    
    /**
     * Create a post from API response
     *
     * @param array  $response   API response data.
     * @param string $source_url Source URL.
     * @return int|WP_Error Post ID on success, WP_Error on failure.
     */
    public function create_post_from_api_response($response, $source_url) {
        // Validate response
        if (!isset($response['title']) || !isset($response['blocks'])) {
            return new WP_Error('invalid_response', __('Invalid API response format', 'url-to-gutenberg'));
        }
        
        // Allow pre-processing of the response
        $response = apply_filters('utg_pre_process_response', $response, $source_url);
        
        // Process screenshot if available
        $screenshot_id = null;
        if (isset($response['screenshot']) && !empty($response['screenshot'])) {
            $screenshot_id = $this->media_handler->save_base64_image(
                $response['screenshot'],
                'screenshot-' . sanitize_title($response['title']) . '.png'
            );
            
            if (is_wp_error($screenshot_id)) {
                // Log the error but continue
                $this->log_error('Screenshot save failed', $screenshot_id->get_error_message());
            }
        }
        
        // Process any additional images
        $image_ids = array();
        if (isset($response['images']) && is_array($response['images'])) {
            foreach ($response['images'] as $image) {
                if (isset($image['url']) && !empty($image['url'])) {
                    $image_alt = isset($image['alt']) ? $image['alt'] : '';
                    $image_id = $this->media_handler->save_remote_image($image['url'], $image_alt);
                    
                    if (!is_wp_error($image_id)) {
                        $image_ids[$image['url']] = $image_id;
                    } else {
                        $this->log_error('Image save failed', sprintf('Failed to save image from URL: %s - Error: %s', $image['url'], $image_id->get_error_message()));
                    }
                }
            }
        }
        
        // Replace image URLs with WordPress attachments in blocks
        $blocks = $this->process_blocks($response['blocks'], $image_ids);
        
        // Allow modification of blocks before post creation
        $blocks = apply_filters('utg_post_blocks', $blocks, $response, $source_url);
        
        // Get post status setting
        $post_status = $this->settings ? $this->settings->get('default_post_status', 'draft') : 'draft';
        
        // Prepare post data
        $post_data = array(
            'post_title'    => sanitize_text_field($response['title']),
            'post_content'  => $this->blocks_to_post_content($blocks),
            'post_status'   => $post_status,
            'post_type'     => apply_filters('utg_post_type', 'post'),
            'meta_input'    => array(
                'utg_source_url' => esc_url_raw($source_url),
                'utg_generated_at' => current_time('mysql'),
            ),
        );
        
        // Allow modification of post data before creation
        $post_data = apply_filters('utg_post_data', $post_data, $response, $source_url);
        
        // Insert the post
        $post_id = wp_insert_post($post_data, true); // true = return WP_Error on failure
        
        if (is_wp_error($post_id)) {
            $this->log_error('Post creation failed', $post_id->get_error_message());
            return $post_id;
        }
        
        // Set featured image if screenshot is available
        if ($screenshot_id && !is_wp_error($screenshot_id)) {
            set_post_thumbnail($post_id, $screenshot_id);
        }
        
        // Save additional meta data
        update_post_meta($post_id, 'utg_image_count', count($image_ids));
        update_post_meta($post_id, 'utg_block_count', count($blocks));
        
        // Allow post-processing actions
        do_action('utg_post_created', $post_id, $response, $source_url);
        
        $this->log_debug('Post created', sprintf('Created post ID: %d from URL: %s', $post_id, $source_url));
        return $post_id;
    }
    
    /**
     * Process blocks and replace image URLs with media IDs
     *
     * @param array $blocks    Blocks to process.
     * @param array $image_ids Image IDs indexed by URL.
     * @return array Processed blocks.
     */
    private function process_blocks($blocks, $image_ids) {
        $processed_blocks = array();
        
        foreach ($blocks as $block) {
            // Check if the block uses blockName format or type format
            $uses_blockname_format = isset($block['blockName']);
            $uses_type_format = isset($block['type']);
            
            // Skip blocks with invalid format
            if (!$uses_blockname_format && !$uses_type_format) {
                continue;
            }
            
            // Convert blockName format to type format for consistent processing
            if ($uses_blockname_format && !$uses_type_format) {
                // Map blockName to type
                $block['type'] = $block['blockName'];
                
                // Map attrs to attributes if needed
                if (isset($block['attrs']) && !isset($block['attributes'])) {
                    $block['attributes'] = $block['attrs'];
                }
            }
            
            // Handle image blocks
            if (($block['type'] === 'core/image' || $block['blockName'] === 'core/image') && 
                  (isset($block['attributes']['url']) || isset($block['attrs']['url']))) {
                
                // Handle both attribute formats
                if (isset($block['attributes']['url'])) {
                    $url = $block['attributes']['url'];
                    if (isset($image_ids[$url])) {
                        $block['attributes']['id'] = $image_ids[$url];
                    }
                } elseif (isset($block['attrs']['url'])) {
                    $url = $block['attrs']['url'];
                    if (isset($image_ids[$url])) {
                        $block['attrs']['id'] = $image_ids[$url];
                    }
                }
            }
            
            // Process nested blocks if any
            if (isset($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $block['innerBlocks'] = $this->process_blocks($block['innerBlocks'], $image_ids);
                
                // Set innerContent for blocks with inner blocks
                if (!isset($block['innerContent'])) {
                    // Create a proper innerContent array with one null for each inner block
                    $inner_content = array();
                    $inner_content[] = '';  // Opening content
                    
                    // Add one null for each inner block
                    foreach ($block['innerBlocks'] as $inner_block) {
                        $inner_content[] = null;
                    }
                    
                    $inner_content[] = '';  // Closing content
                    $block['innerContent'] = $inner_content;
                }
            }
            
            // Generate innerHTML if not present
            if (!isset($block['innerHTML'])) {
                $block['innerHTML'] = $this->generate_inner_html($block);
            }
            
            // Set innerContent for blocks without inner blocks
            if (!isset($block['innerContent'])) {
                if (empty($block['innerBlocks'])) {
                    $block['innerContent'] = array($block['innerHTML']);
                } else {
                    // For blocks with inner blocks, create a proper innerContent array
                    // that has opening content, nulls for each inner block, and closing content
                    $inner_content = array();
                    $inner_content[] = '';  // Opening content
                    
                    // Add a null for each inner block
                    foreach ($block['innerBlocks'] as $inner_block) {
                        $inner_content[] = null;
                    }
                    
                    $inner_content[] = '';  // Closing content
                    $block['innerContent'] = $inner_content;
                }
            }
            
            // Allow modification of individual blocks
            $block = apply_filters('utg_process_block', $block, $image_ids);
            
            $processed_blocks[] = $block;
        }
        
        return $processed_blocks;
    }
    
    /**
     * Convert blocks array to post content
     *
     * @param array $blocks Blocks to convert.
     * @return string Post content.
     */
    private function blocks_to_post_content($blocks) {
        $content = '';
        
        foreach ($blocks as $block) {
            // We've already ensured blocks have all required properties during processing
            $content .= serialize_block($block);
        }
        
        return $content;
    }
    
    /**
     * Generate innerHTML for a block based on its type and attributes
     *
     * @param array $block The block to generate innerHTML for
     * @return string The generated innerHTML
     */
    private function generate_inner_html($block) {
        $block_name = isset($block['blockName']) ? $block['blockName'] : (isset($block['type']) ? $block['type'] : '');
        $attributes = isset($block['attrs']) ? $block['attrs'] : (isset($block['attributes']) ? $block['attributes'] : []);
        
        switch ($block_name) {
            case 'core/paragraph':
                $content = isset($attributes['content']) ? $attributes['content'] : '';
                $align = isset($attributes['align']) ? ' align="' . esc_attr($attributes['align']) . '"' : '';
                return "<p{$align}>{$content}</p>";
                
            case 'core/heading':
                $content = isset($attributes['content']) ? $attributes['content'] : '';
                $level = isset($attributes['level']) ? intval($attributes['level']) : 2;
                $align = isset($attributes['align']) ? ' align="' . esc_attr($attributes['align']) . '"' : '';
                return "<h{$level}{$align}>{$content}</h{$level}>";
                
            case 'core/image':
                $url = isset($attributes['url']) ? esc_url($attributes['url']) : '';
                $alt = isset($attributes['alt']) ? esc_attr($attributes['alt']) : '';
                $caption = isset($attributes['caption']) ? $attributes['caption'] : '';
                
                if (!empty($caption)) {
                    return "<figure class=\"wp-block-image\"><img src=\"{$url}\" alt=\"{$alt}\"/><figcaption>{$caption}</figcaption></figure>";
                } else {
                    return "<figure class=\"wp-block-image\"><img src=\"{$url}\" alt=\"{$alt}\"/></figure>";
                }
                
            case 'core/columns':
                $is_stacked = isset($attributes['isStackedOnMobile']) && $attributes['isStackedOnMobile'] ? ' is-stacked-on-mobile' : '';
                return "<div class=\"wp-block-columns{$is_stacked}\"></div>";
                
            case 'core/column':
                $width = isset($attributes['width']) ? $attributes['width'] : '';
                $width_attr = $width ? " style=\"flex-basis:{$width}%\"" : '';
                return "<div class=\"wp-block-column\"{$width_attr}></div>";
                
            case 'core/separator':
                return "<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>";
                
            default:
                // For unknown block types, return a generic div with the block name as a class
                $safe_block_name = str_replace('/', '-', $block_name);
                return "<div class=\"wp-block-{$safe_block_name}\"></div>";
        }
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
} 