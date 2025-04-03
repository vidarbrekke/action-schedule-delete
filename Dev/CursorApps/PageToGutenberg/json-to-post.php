<?php
/**
 * Plugin Name: URL to Gutenberg - JSON to Post
 * Description: Creates WordPress posts from JSON files in the utg-debug directory
 * Version: 1.0.0
 * Author: UTG Team
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register WP-CLI command if available
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('utg json-to-post', function($args) {
        if (empty($args[0])) {
            WP_CLI::error('Missing filename argument. Usage: wp utg json-to-post <filename>');
            return;
        }
        
        // Get filename from argument
        $filename = $args[0];
        
        // Determine file path
        $uploads_dir = wp_upload_dir();
        $debug_dir = $uploads_dir['basedir'] . '/utg-debug/';
        $json_file = $debug_dir . $filename;
        
        if (!file_exists($json_file)) {
            // Try just the filename as provided
            $json_file = $filename;
            if (!file_exists($json_file)) {
                WP_CLI::error("JSON file not found: {$json_file}");
                return;
            }
        }
        
        // Read file content
        $json_content = file_get_contents($json_file);
        if ($json_content === false) {
            WP_CLI::error("Failed to read file: {$json_file}");
            return;
        }
        
        // Initialize variables
        $post_id = null;
        $blocks = array();
        $title = 'Untitled';
        
        // Extract title from filename
        if (preg_match('/([^\/]+)-final_content-\d+/i', $filename, $matches)) {
            $title = str_replace('-', ' ', $matches[1]);
            $title = ucwords($title);
        }
        
        // Try to decode JSON
        $data = json_decode($json_content, true);
        
        // If JSON decoding worked, process accordingly
        if (json_last_error() === JSON_ERROR_NONE) {
            // Handle different JSON structures
            if (isset($data['blocks']) && is_array($data['blocks'])) {
                // Got correct format with blocks
                WP_CLI::log("Found blocks data in JSON");
                $blocks = $data['blocks'];
                if (isset($data['title'])) {
                    $title = $data['title'];
                }
            } elseif (isset($data['content']) && is_array($data['content'])) {
                // This is a different format with 'content' array
                WP_CLI::log("Found content array in JSON");
                foreach ($data['content'] as $element) {
                    $block = convert_element_to_block($element);
                    if ($block) {
                        $blocks[] = $block;
                    }
                }
                if (isset($data['title'])) {
                    $title = $data['title'];
                }
            } elseif (isset($data[0]) && is_array($data[0])) {
                // Array at root level
                WP_CLI::log("Found array at root level in JSON");
                foreach ($data as $element) {
                    $block = convert_element_to_block($element);
                    if ($block) {
                        $blocks[] = $block;
                    }
                }
            } else {
                // Unknown structure, add as single block
                WP_CLI::log("Unknown JSON structure, using as HTML block");
                $blocks[] = array(
                    'blockName' => 'core/html',
                    'attrs' => array(),
                    'innerBlocks' => array(),
                    'innerHTML' => '<pre>' . wp_json_encode($data, JSON_PRETTY_PRINT) . '</pre>',
                );
            }
        } else {
            // Not valid JSON, treat as HTML
            WP_CLI::log("Invalid JSON, treating as HTML");
            
            // Try to extract content based on common patterns
            $content = $json_content;
            
            // Extract blocks from HTML content
            $serialized_blocks = '';
            
            // First, check if there are already Gutenberg blocks
            if (strpos($content, '<!-- wp:') !== false) {
                WP_CLI::log("Found existing Gutenberg blocks");
                $serialized_blocks = $content;
            } else {
                // Otherwise, parse HTML and create blocks
                WP_CLI::log("Creating blocks from HTML content");
                
                // Clean up the content
                $content = wp_strip_all_tags($content, false);
                
                // Extract headings
                preg_match_all('/<h([1-6])>(.*?)<\/h\1>/i', $content, $headings, PREG_SET_ORDER);
                foreach ($headings as $heading) {
                    $blocks[] = array(
                        'blockName' => 'core/heading',
                        'attrs' => array(
                            'level' => (int) $heading[1]
                        ),
                        'innerBlocks' => array(),
                        'innerHTML' => $heading[0],
                    );
                    $content = str_replace($heading[0], '', $content);
                }
                
                // Extract paragraphs and create blocks
                preg_match_all('/<p>(.*?)<\/p>/i', $content, $paragraphs, PREG_SET_ORDER);
                foreach ($paragraphs as $paragraph) {
                    $blocks[] = array(
                        'blockName' => 'core/paragraph',
                        'attrs' => array(),
                        'innerBlocks' => array(),
                        'innerHTML' => $paragraph[0],
                    );
                    $content = str_replace($paragraph[0], '', $content);
                }
                
                // Extract images
                preg_match_all('/<img[^>]+>/i', $content, $images, PREG_SET_ORDER);
                foreach ($images as $image) {
                    // Extract src attribute
                    if (preg_match('/src="([^"]+)"/i', $image[0], $src)) {
                        // Extract alt text if available
                        $alt = '';
                        if (preg_match('/alt="([^"]*)"/i', $image[0], $alt_matches)) {
                            $alt = $alt_matches[1];
                        }
                        
                        // Filter out small icons, spacers, etc.
                        if (strpos($src[1], 'spacer') === false && strpos($src[1], 'icon') === false) {
                            $blocks[] = array(
                                'blockName' => 'core/image',
                                'attrs' => array(
                                    'url' => $src[1],
                                    'alt' => $alt
                                ),
                                'innerBlocks' => array(),
                                'innerHTML' => $image[0],
                            );
                        }
                    }
                    $content = str_replace($image[0], '', $content);
                }
                
                // Split remaining content into paragraphs
                $remaining_paragraphs = preg_split('/\n\s*\n/', trim($content));
                foreach ($remaining_paragraphs as $paragraph) {
                    $paragraph = trim($paragraph);
                    if (!empty($paragraph)) {
                        $blocks[] = array(
                            'blockName' => 'core/paragraph',
                            'attrs' => array(),
                            'innerBlocks' => array(),
                            'innerHTML' => '<p>' . $paragraph . '</p>',
                        );
                    }
                }
            }
            
            // If no blocks were created, add everything as a single HTML block
            if (empty($blocks) && empty($serialized_blocks)) {
                WP_CLI::log("No blocks identified, using all content as single HTML block");
                $blocks[] = array(
                    'blockName' => 'core/html',
                    'attrs' => array(),
                    'innerBlocks' => array(),
                    'innerHTML' => $content,
                );
            }
        }
        
        // Convert blocks to serialized format if needed
        if (!empty($blocks) && empty($serialized_blocks)) {
            WP_CLI::log("Converting " . count($blocks) . " blocks to serialized format");
            
            foreach ($blocks as $block) {
                $serialized_blocks .= generate_block_html($block);
            }
        }
        
        // Process plain text attributes if present
        if (isset($data['plaintext']) && !empty($data['plaintext'])) {
            WP_CLI::log("Processing plaintext content");
            
            // Decode HTML entities
            $plaintext = html_entity_decode($data['plaintext']);
            
            // Split into paragraphs
            $paragraphs = preg_split('/\n\s*\n/', $plaintext);
            
            // Create paragraph blocks
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if (!empty($paragraph)) {
                    $serialized_blocks .= '<!-- wp:paragraph --><p>' . $paragraph . '</p><!-- /wp:paragraph -->';
                }
            }
        }
        
        // If the serialized_blocks is empty but we have content, add it as a single HTML block
        if (empty($serialized_blocks) && !empty($content)) {
            WP_CLI::log("Falling back to all content as single HTML block");
            $serialized_blocks = '<!-- wp:html -->' . $content . '<!-- /wp:html -->';
        }
        
        // Extract serialized blocks from content
        if (strpos($serialized_blocks, '<!-- wp:') !== false) {
            // Remove any text before the first block
            $serialized_blocks = preg_replace('/^.*?(<!-- wp:)/', '$1', $serialized_blocks, 1);
            
            // Process image blocks
            if (function_exists('utg_process_gutenberg_image_blocks')) {
                WP_CLI::log("Processing image blocks in serialized content");
                $serialized_blocks = utg_process_gutenberg_image_blocks($serialized_blocks);
            } elseif (class_exists('\\UTG\\Generator\\UTG_Media_Handler')) {
                WP_CLI::log("Using UTG_Media_Handler to process image blocks");
                $media_handler = new \UTG\Generator\UTG_Media_Handler();
                if (method_exists($media_handler, 'process_gutenberg_image_blocks')) {
                    $serialized_blocks = $media_handler->process_gutenberg_image_blocks($serialized_blocks);
                }
            }
        }
        
        // Create post data
        $post_data = array(
            'post_title'    => wp_strip_all_tags($title),
            'post_content'  => $serialized_blocks,
            'post_status'   => 'draft',
            'post_author'   => 1,
            'post_type'     => 'post',
            'meta_input'    => array(
                'utg_json_source' => $filename,
                'utg_generated_at' => current_time('mysql'),
            ),
        );
        
        // Check for featured image
        $featured_image_id = null;
        if (isset($data['featured_image']) && !empty($data['featured_image'])) {
            WP_CLI::log("Processing featured image");
            
            if (isset($data['featured_image']['url']) && !empty($data['featured_image']['url'])) {
                // It's a URL
                $alt_text = isset($data['featured_image']['alt']) ? $data['featured_image']['alt'] : '';
                
                // Download remote image
                if (function_exists('utg_download_remote_image')) {
                    $featured_image_id = utg_download_remote_image($data['featured_image']['url'], $alt_text);
                } elseif (class_exists('\\UTG\\Generator\\UTG_Media_Handler')) {
                    $media_handler = new \UTG\Generator\UTG_Media_Handler();
                    if (method_exists($media_handler, 'save_remote_image')) {
                        $featured_image_id = $media_handler->save_remote_image($data['featured_image']['url'], $alt_text);
                    }
                }
            } elseif (is_string($data['featured_image']) && strpos($data['featured_image'], 'data:image') === 0) {
                // It's a base64 image
                if (function_exists('utg_save_base64_image')) {
                    $featured_image_id = utg_save_base64_image($data['featured_image']);
                } elseif (class_exists('\\UTG\\Generator\\UTG_Media_Handler')) {
                    $media_handler = new \UTG\Generator\UTG_Media_Handler();
                    if (method_exists($media_handler, 'save_base64_image')) {
                        $featured_image_id = $media_handler->save_base64_image($data['featured_image']);
                    }
                }
            }
        }
        
        // Create post
        WP_CLI::log("Creating post with title: " . $post_data['post_title']);
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            WP_CLI::error("Failed to create post: " . $post_id->get_error_message());
            return;
        }
        
        // Set featured image if available
        if (!empty($featured_image_id) && !is_wp_error($featured_image_id)) {
            set_post_thumbnail($post_id, $featured_image_id);
        }
        
        WP_CLI::success("Post created with ID: {$post_id}");
        WP_CLI::log("Edit URL: " . admin_url("post.php?post={$post_id}&action=edit"));
        WP_CLI::log("View URL: " . get_permalink($post_id));
        
        return;
    });
}

/**
 * Add admin page for processing JSON files
 */
add_action('admin_menu', function() {
    add_management_page(
        'Process JSON to Post',
        'JSON to Post',
        'manage_options',
        'utg-json-to-post',
        'utg_json_to_post_page'
    );
});

/**
 * Admin page callback
 */
function utg_json_to_post_page() {
    $message = '';
    $error = '';
    
    if (isset($_POST['utg_json_file']) && check_admin_referer('utg_json_to_post_action', 'utg_json_to_post_nonce')) {
        $filename = sanitize_text_field($_POST['utg_json_file']);
        $result = process_json_file($filename);
        
        if (is_wp_error($result)) {
            $error = $result->get_error_message();
        } else {
            $message = "Post created successfully with ID: {$result['post_id']} | <a href='" . 
                $result['edit_url'] . "'>Edit Post</a> | <a href='" . 
                $result['view_url'] . "'>View Post</a>";
        }
    }
    
    // Get all JSON files in the utg-debug directory
    $json_files = [];
    $base_path = ABSPATH . 'wp-content/uploads/utg-debug/';
    
    if (is_dir($base_path)) {
        $files = scandir($base_path);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json' && strpos($file, 'final_content') !== false) {
                $json_files[] = $file;
            }
        }
        
        // Sort by newest first
        usort($json_files, function($a, $b) use ($base_path) {
            return filemtime($base_path . $b) - 
                   filemtime($base_path . $a);
        });
    }
    
    ?>
    <div class="wrap">
        <h1>Process JSON to Post</h1>
        
        <?php if (!empty($message)): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo $error; ?></p>
            </div>
        <?php endif; ?>
        
        <p>Select a JSON file from the utg-debug directory to process into a WordPress post.</p>
        
        <form method="post">
            <?php wp_nonce_field('utg_json_to_post_action', 'utg_json_to_post_nonce'); ?>
            
            <select name="utg_json_file" style="min-width: 300px;">
                <?php foreach ($json_files as $file): ?>
                    <option value="<?php echo esc_attr($file); ?>"><?php echo esc_html($file); ?></option>
                <?php endforeach; ?>
            </select>
            
            <?php submit_button('Process JSON File'); ?>
        </form>
    </div>
    <?php
}

/**
 * Process a JSON file to create a WordPress post
 * 
 * @param string $file The filename (without path) in the utg-debug directory
 * @return array|WP_Error Post ID and URLs on success, WP_Error on failure
 */
function process_json_file($file) {
    // Base path for JSON files
    $base_path = ABSPATH . 'wp-content/uploads/utg-debug/';
    $json_file_path = $base_path . $file;
    
    // Verify that the JSON file exists
    if (!file_exists($json_file_path)) {
        return new WP_Error('file_not_found', "JSON file not found: {$json_file_path}");
    }
    
    // Read the JSON file
    $json_content = file_get_contents($json_file_path);
    if (!$json_content) {
        error_log("UTG: Could not read file: $json_file_path");
        return new WP_Error('file_error', "Could not read file: $json_file_path");
    }

    // Extract file name for the title (without extension)
    $title = pathinfo($file, PATHINFO_FILENAME);
    $title = str_replace(['final_content-', '-'], [' ', ' '], $title);
    $title = ucwords($title);

    // Initialize blocks array
    $blocks = array();
    
    // Create a paragraph block with the HTML content
    $content = trim($json_content);
    
    // Check if the content is valid JSON (try to decode it)
    $json_data = json_decode($content, true);
    
    if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
        // If valid JSON with expected structure
        if (!empty($json_data['title'])) {
            $title = $json_data['title'];
        }
        
        // Check for LLM-processed content format first
        if (!empty($json_data['processed_content']) && is_array($json_data['processed_content'])) {
            // This is an LLM-processed content file
            foreach ($json_data['processed_content'] as $element) {
                $block = convert_element_to_block($element);
                if ($block) {
                    $blocks[] = $block;
                }
            }
        } 
        // Check for LLM-processed content as a string containing Gutenberg blocks
        else if (!empty($json_data['processed_content']) && is_string($json_data['processed_content']) && 
                 strpos($json_data['processed_content'], '<!-- wp:') !== false) {
            
            // Extract the serialized blocks from the content
            $processed_content = $json_data['processed_content'];
            
            // Strip any introductory text before the first wp: block
            $first_block_pos = strpos($processed_content, '<!-- wp:');
            if ($first_block_pos > 0) {
                $processed_content = substr($processed_content, $first_block_pos);
            }
            
            // Process any image blocks to download images and update references
            $processed_content = utg_process_gutenberg_image_blocks($processed_content);
            
            // Use the extracted blocks as post content directly
            $post_data = array(
                'post_title'   => $title,
                'post_status'  => 'publish',
                'post_author'  => 1,
                'post_type'    => 'post',
                'post_content' => $processed_content,
                'meta_input'   => array(
                    'utg_json_source' => basename($file),
                    'utg_generated_at' => date('Y-m-d H:i:s'),
                    'utg_llm_processed' => true
                ),
            );
            
            // Create the post
            $post_id = wp_insert_post($post_data, true);
            if (is_wp_error($post_id)) {
                error_log("UTG: Error creating post: " . $post_id->get_error_message());
                return $post_id;
            }
            
            // Return success with post ID and URLs
            return array(
                'post_id'   => $post_id,
                'edit_url'  => admin_url("post.php?post={$post_id}&action=edit"),
                'view_url'  => get_permalink($post_id)
            );
        }
        // Then check for standard content format
        else if (!empty($json_data['content']) && is_array($json_data['content'])) {
            // Process structured content
            foreach ($json_data['content'] as $element) {
                $block = convert_element_to_block($element);
                if ($block) {
                    $blocks[] = $block;
                }
            }
        } 
        // Handle the case when the JSON itself is a content array
        else if (isset($json_data[0]) && is_array($json_data[0]) && isset($json_data[0]['type'])) {
            // Process the array directly
            foreach ($json_data as $element) {
                $block = convert_element_to_block($element);
                if ($block) {
                    $blocks[] = $block;
                }
            }
        }
        else {
            // Add content as a single block if not in expected format
            $blocks[] = array(
                'blockName' => 'core/html',
                'attrs' => array(),
                'innerBlocks' => array(),
                'innerHTML' => $content,
                'innerContent' => array($content)
            );
        }
    } else {
        // Not valid JSON, treat as HTML
        // Look for specific HTML patterns that indicate content sections
        
        // First, try to extract any clear heading sections
        preg_match_all('/<(h[1-6]|p|div)[^>]*>.*?<\/\1>/is', $content, $content_sections);
        
        if (!empty($content_sections[0])) {
            foreach ($content_sections[0] as $section) {
                // Clean up the content
                $clean_content = preg_replace('/<\/?span[^>]*>/', '', $section);
                $clean_content = preg_replace('/<\/?font[^>]*>/', '', $clean_content);
                $clean_content = preg_replace('/<\/?div[^>]*>/', '', $clean_content);
                $clean_content = preg_replace('/<\/?td[^>]*>/', '', $clean_content);
                $clean_content = preg_replace('/<\/?tr[^>]*>/', '', $clean_content);
                $clean_content = preg_replace('/<\/?table[^>]*>/', '', $clean_content);
                $clean_content = preg_replace('/<\/?tbody[^>]*>/', '', $clean_content);
                $clean_content = strip_tags($clean_content, '<h1><h2><h3><h4><h5><h6><p><img><br>');
                $clean_content = trim($clean_content);
                
                if (!empty($clean_content)) {
                    // Determine if it's a heading
                    if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $section, $heading_match)) {
                        $heading_text = trim(strip_tags($heading_match[1]));
                        $level = (int)substr($heading_match[0], 2, 1);
                        
                        if (!empty($heading_text)) {
                            $blocks[] = array(
                                'blockName' => 'core/heading',
                                'attrs' => array('level' => $level),
                                'innerBlocks' => array(),
                                'innerHTML' => "<h{$level}>" . $heading_text . "</h{$level}>",
                                'innerContent' => array("<h{$level}>" . $heading_text . "</h{$level}>")
                            );
                        }
                    } 
                    // Check for paragraphs
                    else if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $section, $p_match)) {
                        $p_text = trim(strip_tags($p_match[1]));
                        if (!empty($p_text)) {
                            $blocks[] = array(
                                'blockName' => 'core/paragraph',
                                'attrs' => array(),
                                'innerBlocks' => array(),
                                'innerHTML' => '<p>' . $p_text . '</p>',
                                'innerContent' => array('<p>' . $p_text . '</p>')
                            );
                        }
                    }
                }
            }
        }
        
        // Extract images
        preg_match_all('/<img[^>]*src="([^"]*)"[^>]*>/is', $content, $image_matches);
        
        if (!empty($image_matches[1])) {
            foreach ($image_matches[1] as $img_index => $img_src) {
                // Extract alt text if available
                $alt_text = '';
                if (preg_match('/alt="([^"]*)"/i', $image_matches[0][$img_index], $alt_match)) {
                    $alt_text = $alt_match[1];
                }
                
                // Only add images that appear to be content images (skip small icons, spacers, etc.)
                if (
                    strpos($img_src, 'spacer.gif') === false && 
                    !preg_match('/width="?[0-9]+"?/i', $image_matches[0][$img_index]) &&
                    !preg_match('/height="?[0-5]+"?/i', $image_matches[0][$img_index])
                ) {
                    // Download the remote image if it's a valid URL
                    if (filter_var($img_src, FILTER_VALIDATE_URL)) {
                        $attachment_id = utg_download_remote_image($img_src, $alt_text);
                        
                        if (!is_wp_error($attachment_id) && $attachment_id > 0) {
                            // Get image details from the attachment
                            $image_src = wp_get_attachment_image_src($attachment_id, 'full');
                            $local_url = $image_src ? $image_src[0] : $img_src;
                            
                            $blocks[] = array(
                                'blockName' => 'core/image',
                                'attrs' => array(
                                    'url' => $local_url,
                                    'alt' => $alt_text,
                                    'id' => $attachment_id
                                ),
                                'innerBlocks' => array(),
                                'innerHTML' => '<figure class="wp-block-image"><img src="' . $local_url . '" alt="' . $alt_text . '" class="wp-image-' . $attachment_id . '"/></figure>',
                                'innerContent' => array('<figure class="wp-block-image"><img src="' . $local_url . '" alt="' . $alt_text . '" class="wp-image-' . $attachment_id . '"/></figure>')
                            );
                            continue;
                        }
                    }
                    
                    // Fallback if download fails or URL is not valid
                    $blocks[] = array(
                        'blockName' => 'core/image',
                        'attrs' => array(
                            'url' => $img_src,
                            'alt' => $alt_text
                        ),
                        'innerBlocks' => array(),
                        'innerHTML' => '<figure class="wp-block-image"><img src="' . $img_src . '" alt="' . $alt_text . '"/></figure>',
                        'innerContent' => array('<figure class="wp-block-image"><img src="' . $img_src . '" alt="' . $alt_text . '"/></figure>')
                    );
                }
            }
        }
        
        // Alternative approach - try to extract content by looking at specific div patterns
        if (empty($blocks)) {
            preg_match_all('/<td class="txtsize" id="elm_\d+"[^>]*>(.*?)<\/td>/is', $content, $div_sections);
            
            if (!empty($div_sections[1])) {
                foreach ($div_sections[1] as $section) {
                    // Check for headings (larger font or bold text)
                    if (
                        strpos($section, 'font-size: 14pt') !== false || 
                        strpos($section, '<b>') !== false ||
                        strpos($section, '<strong>') !== false ||
                        strpos($section, 'font-weight: bold') !== false
                    ) {
                        // Extract heading text
                        if (preg_match('/<(b|strong)[^>]*>(.*?)<\/(b|strong)>/is', $section, $bold_match)) {
                            $heading_text = trim(strip_tags($bold_match[2]));
                        } else if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $section, $p_match)) {
                            $heading_text = trim(strip_tags($p_match[1]));
                        } else {
                            // Try to extract text from the section
                            $heading_text = trim(strip_tags($section));
                        }
                        
                        if (!empty($heading_text)) {
                            $blocks[] = array(
                                'blockName' => 'core/heading',
                                'attrs' => array('level' => 2),
                                'innerBlocks' => array(),
                                'innerHTML' => '<h2>' . $heading_text . '</h2>',
                                'innerContent' => array('<h2>' . $heading_text . '</h2>')
                            );
                        }
                    } 
                    // Check for paragraphs
                    else if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $section, $p_match)) {
                        $p_text = trim(strip_tags($p_match[1]));
                        if (!empty($p_text) && strlen($p_text) > 10) { // Only include meaningful paragraphs
                            $blocks[] = array(
                                'blockName' => 'core/paragraph',
                                'attrs' => array(),
                                'innerBlocks' => array(),
                                'innerHTML' => '<p>' . $p_text . '</p>',
                                'innerContent' => array('<p>' . $p_text . '</p>')
                            );
                        }
                    }
                    
                    // Check for images in this section
                    preg_match_all('/<img[^>]*src="([^"]*)"[^>]*>/is', $section, $img_matches);
                    if (!empty($img_matches[1])) {
                        foreach ($img_matches[1] as $img_index => $img_src) {
                            // Skip small images or icons
                            if (
                                strpos($img_src, 'spacer.gif') === false && 
                                strpos($img_src, 'icon') === false &&
                                !preg_match('/width="?[0-9]+"?/i', $img_matches[0][$img_index]) &&
                                !preg_match('/height="?[0-5]+"?/i', $img_matches[0][$img_index])
                            ) {
                                $alt_text = '';
                                if (preg_match('/alt="([^"]*)"/i', $img_matches[0][$img_index], $alt_match)) {
                                    $alt_text = $alt_match[1];
                                }
                                
                                // Download the remote image if it's a valid URL
                                if (filter_var($img_src, FILTER_VALIDATE_URL)) {
                                    $attachment_id = utg_download_remote_image($img_src, $alt_text);
                                    
                                    if (!is_wp_error($attachment_id) && $attachment_id > 0) {
                                        // Get image details from the attachment
                                        $image_src = wp_get_attachment_image_src($attachment_id, 'full');
                                        $local_url = $image_src ? $image_src[0] : $img_src;
                                        
                                        $blocks[] = array(
                                            'blockName' => 'core/image',
                                            'attrs' => array(
                                                'url' => $local_url,
                                                'alt' => $alt_text,
                                                'id' => $attachment_id
                                            ),
                                            'innerBlocks' => array(),
                                            'innerHTML' => '<figure class="wp-block-image"><img src="' . $local_url . '" alt="' . $alt_text . '" class="wp-image-' . $attachment_id . '"/></figure>',
                                            'innerContent' => array('<figure class="wp-block-image"><img src="' . $local_url . '" alt="' . $alt_text . '" class="wp-image-' . $attachment_id . '"/></figure>')
                                        );
                                        continue;
                                    }
                                }
                                
                                // Fallback if download fails or URL is not valid
                                $blocks[] = array(
                                    'blockName' => 'core/image',
                                    'attrs' => array(
                                        'url' => $img_src,
                                        'alt' => $alt_text
                                    ),
                                    'innerBlocks' => array(),
                                    'innerHTML' => '<figure class="wp-block-image"><img src="' . $img_src . '" alt="' . $alt_text . '"/></figure>',
                                    'innerContent' => array('<figure class="wp-block-image"><img src="' . $img_src . '" alt="' . $alt_text . '"/></figure>')
                                );
                            }
                        }
                    }
                }
            }
        }
        
        // If we still have no blocks, try to extract content from plaintext attributes
        if (empty($blocks)) {
            preg_match_all('/plaintext="([^"]*)"/', $content, $plaintext_matches);
            
            if (!empty($plaintext_matches[1])) {
                foreach ($plaintext_matches[1] as $plaintext) {
                    if (!empty($plaintext) && strlen($plaintext) > 10) {
                        // Replace encoded entities
                        $plaintext = html_entity_decode($plaintext);
                        
                        // Split by double newlines to separate paragraphs
                        $paragraphs = preg_split('/\n\s*\n/', $plaintext);
                        
                        foreach ($paragraphs as $p) {
                            $p = trim($p);
                            if (!empty($p)) {
                                $blocks[] = array(
                                    'blockName' => 'core/paragraph',
                                    'attrs' => array(),
                                    'innerBlocks' => array(),
                                    'innerHTML' => '<p>' . $p . '</p>',
                                    'innerContent' => array('<p>' . $p . '</p>')
                                );
                            }
                        }
                    }
                }
            }
        }
        
        // If still no blocks were created, add the entire content as an HTML block
        if (empty($blocks)) {
            $blocks[] = array(
                'blockName' => 'core/html',
                'attrs' => array(),
                'innerBlocks' => array(),
                'innerHTML' => $content,
                'innerContent' => array($content)
            );
        }
    }
    
    // Create post data
    $post_data = array(
        'post_title'   => $title,
        'post_status'  => 'publish',
        'post_author'  => 1,
        'post_type'    => 'post',
        'post_content' => serialize_blocks($blocks),
        'meta_input'   => array(
            'utg_json_source' => basename($file),
            'utg_generated_at' => date('Y-m-d H:i:s'),
            'gutenberg_blocks' => $blocks  // Store blocks for debugging
        ),
    );

    // Add featured image if available
    if (!empty($json_data['featured_image'])) {
        $featured_image_id = upload_image_from_url($json_data['featured_image']);
        if (!is_wp_error($featured_image_id)) {
            $post_data['meta_input']['_thumbnail_id'] = $featured_image_id;
        }
    }

    // Create the post
    $post_id = wp_insert_post($post_data, true);
    if (is_wp_error($post_id)) {
        error_log("UTG: Error creating post: " . $post_id->get_error_message());
        return $post_id;
    }

    // Return success with post ID and URLs
    return array(
        'post_id'   => $post_id,
        'edit_url'  => admin_url("post.php?post={$post_id}&action=edit"),
        'view_url'  => get_permalink($post_id)
    );
}

/**
 * Convert an element from the JSON to a Gutenberg block
 */
function convert_element_to_block($element) {
    if (empty($element['type'])) {
        return null;
    }

    switch ($element['type']) {
        case 'paragraph':
            return array(
                'blockName' => 'core/paragraph',
                'attrs' => array(),
                'innerBlocks' => array(),
                'innerHTML' => '<p>' . wp_kses_post($element['content']) . '</p>',
                'innerContent' => array('<p>' . wp_kses_post($element['content']) . '</p>')
            );
            
        case 'heading':
            $level = isset($element['level']) ? intval($element['level']) : 2;
            if ($level < 1 || $level > 6) $level = 2;
            
            return array(
                'blockName' => 'core/heading',
                'attrs' => array(
                    'level' => $level
                ),
                'innerBlocks' => array(),
                'innerHTML' => "<h{$level}>" . wp_kses_post($element['content']) . "</h{$level}>",
                'innerContent' => array("<h{$level}>" . wp_kses_post($element['content']) . "</h{$level}>")
            );
            
        case 'image':
            $src = isset($element['src']) ? esc_url($element['src']) : '';
            $alt = isset($element['alt']) ? esc_attr($element['alt']) : '';
            
            // Handle remote images by downloading them to the WordPress media library
            if (!empty($src) && filter_var($src, FILTER_VALIDATE_URL)) {
                $attachment_id = utg_download_remote_image($src, $alt);
                
                if (!is_wp_error($attachment_id) && $attachment_id > 0) {
                    // Get image details from the attachment
                    $image_src = wp_get_attachment_image_src($attachment_id, 'full');
                    $image_url = $image_src ? $image_src[0] : $src;
                    
                    return array(
                        'blockName' => 'core/image',
                        'attrs' => array(
                            'url' => $image_url,
                            'alt' => $alt,
                            'id' => $attachment_id
                        ),
                        'innerBlocks' => array(),
                        'innerHTML' => '<figure class="wp-block-image"><img src="' . $image_url . '" alt="' . $alt . '" class="wp-image-' . $attachment_id . '"/></figure>',
                        'innerContent' => array('<figure class="wp-block-image"><img src="' . $image_url . '" alt="' . $alt . '" class="wp-image-' . $attachment_id . '"/></figure>')
                    );
                }
            }
            
            // Fallback to the original method if download fails or URL is not valid
            return array(
                'blockName' => 'core/image',
                'attrs' => array(
                    'url' => $src,
                    'alt' => $alt
                ),
                'innerBlocks' => array(),
                'innerHTML' => '<figure class="wp-block-image"><img src="' . $src . '" alt="' . $alt . '"/></figure>',
                'innerContent' => array('<figure class="wp-block-image"><img src="' . $src . '" alt="' . $alt . '"/></figure>')
            );
            
        case 'list':
            if (empty($element['items']) || !is_array($element['items'])) {
                return null;
            }
            
            $list_type = isset($element['list_type']) && $element['list_type'] === 'ordered' ? 'ol' : 'ul';
            $items_html = '';
            
            foreach ($element['items'] as $item) {
                $items_html .= '<li>' . wp_kses_post($item) . '</li>';
            }
            
            return array(
                'blockName' => $list_type === 'ol' ? 'core/list' : 'core/list',
                'attrs' => array(
                    'ordered' => $list_type === 'ol'
                ),
                'innerBlocks' => array(),
                'innerHTML' => "<{$list_type}>{$items_html}</{$list_type}>",
                'innerContent' => array("<{$list_type}>{$items_html}</{$list_type}>")
            );
            
        case 'table':
            if (empty($element['rows']) || !is_array($element['rows'])) {
                return null;
            }
            
            $has_header = !empty($element['has_header']);
            $table_html = '<figure class="wp-block-table"><table>';
            
            foreach ($element['rows'] as $row_index => $row) {
                if ($row_index === 0 && $has_header) {
                    $table_html .= '<thead><tr>';
                    foreach ($row as $cell) {
                        $table_html .= '<th>' . wp_kses_post($cell) . '</th>';
                    }
                    $table_html .= '</tr></thead><tbody>';
                } else {
                    if ($row_index === 0) {
                        $table_html .= '<tbody>';
                    }
                    $table_html .= '<tr>';
                    foreach ($row as $cell) {
                        $table_html .= '<td>' . wp_kses_post($cell) . '</td>';
                    }
                    $table_html .= '</tr>';
                }
            }
            
            $table_html .= '</tbody></table></figure>';
            
            return array(
                'blockName' => 'core/table',
                'attrs' => array(
                    'hasFixedLayout' => false,
                    'head' => $has_header ? array(0) : array()
                ),
                'innerBlocks' => array(),
                'innerHTML' => $table_html,
                'innerContent' => array($table_html)
            );
            
        default:
            // For unknown element types, create a paragraph block
            return array(
                'blockName' => 'core/paragraph',
                'attrs' => array(),
                'innerBlocks' => array(),
                'innerHTML' => '<p>' . wp_kses_post(isset($element['content']) ? $element['content'] : '') . '</p>',
                'innerContent' => array('<p>' . wp_kses_post(isset($element['content']) ? $element['content'] : '') . '</p>')
            );
    }
    
    return null;
}

/**
 * Generate Gutenberg block HTML from block structure
 *
 * @param array $block The block structure
 * @return string The HTML with Gutenberg block comments
 */
function generate_block_html($block) {
    if (empty($block['blockName'])) {
        return $block['innerHTML'] ?? '';
    }
    
    $attrs_json = !empty($block['attrs']) ? ' ' . json_encode($block['attrs']) : '';
    $opening_comment = "<!-- wp:{$block['blockName']}{$attrs_json} -->";
    $closing_comment = "<!-- /wp:{$block['blockName']} -->";
    
    $inner_content = '';
    if (!empty($block['innerContent'])) {
        foreach ($block['innerContent'] as $index => $content) {
            if ($content === null && isset($block['innerBlocks'][$index])) {
                $inner_content .= generate_block_html($block['innerBlocks'][$index]);
            } else {
                $inner_content .= $content;
            }
        }
    } elseif (!empty($block['innerHTML'])) {
        $inner_content = $block['innerHTML'];
    }
    
    return $opening_comment . $inner_content . $closing_comment;
}

/**
 * Process table content
 */
function process_table_content($content) {
    $html = '';
    $has_header = false;
    
    foreach ($content as $row_element) {
        if (isset($row_element['tag']) && $row_element['tag'] === 'tr') {
            $row_html = '<tr>';
            
            if (isset($row_element['content']) && is_array($row_element['content'])) {
                foreach ($row_element['content'] as $cell_element) {
                    $is_header = isset($cell_element['tag']) && $cell_element['tag'] === 'th';
                    $cell_tag = $is_header ? 'th' : 'td';
                    
                    if (!$has_header && $is_header) {
                        $has_header = true;
                    }
                    
                    $cell_content = '';
                    if (isset($cell_element['content'])) {
                        if (is_string($cell_element['content'])) {
                            $cell_content = $cell_element['content'];
                        } elseif (is_array($cell_element['content'])) {
                            $cell_content = convert_content_to_html($cell_element['content']);
                        }
                    }
                    
                    $row_html .= "<{$cell_tag}>{$cell_content}</{$cell_tag}>";
                }
            }
            
            $row_html .= '</tr>';
            $html .= $row_html;
        }
    }
    
    return $html;
}

/**
 * Convert content array to HTML
 */
function convert_content_to_html($content) {
    $html = '';
    
    if (is_array($content)) {
        foreach ($content as $element) {
            if (is_string($element)) {
                $html .= $element;
            } elseif (isset($element['tag'])) {
                $tag = $element['tag'];
                $element_content = '';
                
                if (isset($element['content'])) {
                    if (is_string($element['content'])) {
                        $element_content = $element['content'];
                    } elseif (is_array($element['content'])) {
                        $element_content = convert_content_to_html($element['content']);
                    }
                }
                
                // Process attributes
                $attributes = '';
                if (isset($element['attributes']) && is_array($element['attributes'])) {
                    foreach ($element['attributes'] as $attr => $value) {
                        $attributes .= " {$attr}=\"{$value}\"";
                    }
                }
                
                // Self-closing tags
                if (in_array($tag, ['img', 'br', 'hr'])) {
                    $html .= "<{$tag}{$attributes} />";
                } else {
                    $html .= "<{$tag}{$attributes}>{$element_content}</{$tag}>";
                }
            }
        }
    }
    
    return $html;
}

/**
 * Download a remote image and add it to the WordPress media library
 *
 * @param string $image_url URL of the remote image
 * @param string $alt Alt text for the image
 * @return int|WP_Error Attachment ID on success, WP_Error on failure
 */
function utg_download_remote_image($image_url, $alt = '') {
    // Check if the UTG_Media_Handler class exists and use it
    if (class_exists('\\UTG\\Generator\\UTG_Media_Handler')) {
        $media_handler = new \UTG\Generator\UTG_Media_Handler();
        if (method_exists($media_handler, 'save_remote_image')) {
            return $media_handler->save_remote_image($image_url, $alt);
        }
    }
    
    // Fallback implementation
    
    // Validate URL
    if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
        return new WP_Error('invalid_url', __('Invalid image URL', 'url-to-gutenberg'));
    }
    
    // Check if image with this URL has already been imported
    $existing_attachment = utg_get_attachment_by_url($image_url);
    if ($existing_attachment) {
        return $existing_attachment;
    }
    
    // Download image to temp file
    $temp_file = download_url($image_url);
    
    if (is_wp_error($temp_file)) {
        return $temp_file;
    }
    
    // Get file name from URL
    $filename = basename(parse_url($image_url, PHP_URL_PATH));
    if (empty($filename)) {
        $filename = 'utg-image-' . time() . '.jpg';
    }
    
    // Determine file type
    $filetype = wp_check_filetype($filename, null);
    if (empty($filetype['type'])) {
        @unlink($temp_file);
        return new WP_Error('mime_type_error', __('Could not determine file type', 'url-to-gutenberg'));
    }
    
    // Prepare attachment data
    $attachment = array(
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_text_field($alt ?: preg_replace('/\.[^.]+$/', '', $filename)),
        'post_content'   => '',
        'post_excerpt'   => sanitize_text_field($alt),
        'post_status'    => 'inherit',
        'meta_input'     => array(
            '_utg_source_url' => esc_url_raw($image_url),
        ),
    );
    
    // Insert attachment into WordPress Media Library
    $attachment_id = wp_insert_attachment($attachment, $temp_file);
    
    if (is_wp_error($attachment_id)) {
        @unlink($temp_file);
        return $attachment_id;
    }
    
    // Generate metadata for the attachment
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attachment_data = wp_generate_attachment_metadata($attachment_id, $temp_file);
    
    wp_update_attachment_metadata($attachment_id, $attachment_data);
    
    // Clean up
    @unlink($temp_file);
    
    return $attachment_id;
}

/**
 * Get attachment ID by URL
 *
 * @param string $url URL of the image.
 * @return int|false Attachment ID if found, false otherwise.
 */
function utg_get_attachment_by_url($url) {
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
 * Process Gutenberg image blocks in content string
 * This function searches for image blocks and downloads remote images
 *
 * @param string $content Gutenberg blocks content string
 * @return string Updated content with proper image references
 */
function utg_process_gutenberg_image_blocks($content) {
    if (empty($content)) {
        return $content;
    }
    
    // Check if the UTG_Media_Handler class exists and use it
    if (class_exists('\\UTG\\Generator\\UTG_Media_Handler')) {
        $media_handler = new \UTG\Generator\UTG_Media_Handler();
        if (method_exists($media_handler, 'process_gutenberg_image_blocks')) {
            return $media_handler->process_gutenberg_image_blocks($content);
        }
    }
    
    // Fall back to using the utg_download_remote_image function directly
    
    // Pattern to match image blocks
    $pattern = '/(<!-- wp:image[^>]*?-->)[\s\S]*?<img[^>]*?src="([^"]+)"[^>]*?\/?>[\s\S]*?(<!-- \/wp:image -->)/i';
    
    $processed_content = preg_replace_callback($pattern, function($matches) {
        $opening_tag = $matches[1];
        $img_url = $matches[2];
        $closing_tag = $matches[3];
        $block_content = $matches[0];
        
        // Skip if it's not a valid URL
        if (empty($img_url) || !filter_var($img_url, FILTER_VALIDATE_URL)) {
            return $block_content;
        }
        
        // Extract alt text if available
        $alt_text = '';
        if (preg_match('/alt="([^"]*)"/', $block_content, $alt_matches)) {
            $alt_text = $alt_matches[1];
        }
        
        // Download the image and get the attachment ID
        $attachment_id = utg_download_remote_image($img_url, $alt_text);
        
        if (is_wp_error($attachment_id) || !$attachment_id) {
            return $block_content;
        }
        
        // Get the new local URL for the image
        $local_url = wp_get_attachment_url($attachment_id);
        
        if (!$local_url) {
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
        
        return $updated_block_content;
        
    }, $content);
    
    return $processed_content;
} 