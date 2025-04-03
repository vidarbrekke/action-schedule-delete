<?php
/**
 * Test script for JSON to Post conversion
 * 
 * Upload this file to the WordPress root directory and run it directly
 */

// Load WordPress
require_once('wp-load.php');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// At the top of the file after the opening PHP tag
// Add a cache clearing option to test the new LLM implementation
if (isset($_GET['clear_cache']) && $_GET['clear_cache'] === 'yes') {
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '%_transient_%'");
    echo "<div style='background-color: #dff0d8; color: #3c763d; padding: 10px; margin: 10px 0; border-radius: 3px;'>Transient cache cleared successfully. Any new URL processing will use the updated LLM prompts.</div>";
}

function debug_log($message) {
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }
    echo $message . "\n";
}

function get_debug_json_files() {
    $upload_dir = wp_upload_dir();
    $debug_dir = $upload_dir['basedir'] . '/utg-debug/';
    $files = scandir($debug_dir);
    $json_files = [];
    
    foreach ($files as $file) {
        if (strpos($file, '.json') !== false) {
            $json_files[] = $debug_dir . $file;
        }
    }
    
    return $json_files;
}

// Download an image and add it to the media library
function import_remote_image($image_url, $post_id = 0) {
    // Check if we already have this image in the media library
    $existing_attachment_id = attachment_url_to_postid($image_url);
    if ($existing_attachment_id) {
        return $existing_attachment_id;
    }

    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    // Download remote file and add to media library
    $attachment_id = media_sideload_image($image_url, $post_id, null, 'id');
    
    if (is_wp_error($attachment_id)) {
        debug_log("Error importing image: " . $attachment_id->get_error_message());
        return 0;
    }
    
    return $attachment_id;
}

/**
 * Clean block content to properly handle HTML entities and ensure block completeness
 */
function clean_block_content($content) {
    // Decode HTML entities - do this first to handle encoded blocks
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // First, check if the content ends with a truncated group block
    if (preg_match('/<!-- wp:group\s+\{"[^}]*$/ms', $content)) {
        // Special case: truncated group block at the end of the content
        // Replace it with a complete group block
        $content = preg_replace('/<!-- wp:group\s+\{"[^}]*$/ms', "<!-- wp:group -->\n<!-- /wp:group -->", $content);
    }
    
    // Fix truncated group blocks where the JSON attributes are incomplete
    // This looks for group blocks with incomplete JSON and repairs them
    $pattern = '/<!-- wp:group\s+\{"[^}]*$/m';
    $content = preg_replace_callback($pattern, function($matches) {
        // Found a truncated group block, replace with a simple version
        return '<!-- wp:group -->';
    }, $content);
    
    // Convert HTML encoding that might have been double-encoded
    $content = preg_replace_callback('/&lt;!--\s*wp:([^\s>]+)(\s+({.*?}))?\s*--&gt;/s', function($matches) {
        $block_name = $matches[1];
        $attributes = isset($matches[3]) ? $matches[3] : '';
        return "<!-- wp:{$block_name}{$attributes} -->";
    }, $content);
    
    // Convert closing tags too
    $content = preg_replace('/&lt;!--\s*\/wp:([^\s>]+)\s*--&gt;/s', '<!-- /wp:$1 -->', $content);
    
    // Find all opening block tags
    $opening_tags = [];
    preg_match_all('/<!-- wp:([^\s\/]+)(\s+({.*?}))?\s*-->/s', $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $block_name = $match[1][0];
        $position = $match[0][1];
        $full_tag = $match[0][0];
        $attributes = isset($match[3][0]) ? $match[3][0] : '';
        
        $opening_tags[] = [
            'name' => $block_name,
            'position' => $position,
            'full_tag' => $full_tag,
            'attributes' => $attributes
        ];
    }
    
    // Find all closing block tags
    $closing_tags = [];
    preg_match_all('/<!-- \/wp:([^\s]+)\s*-->/s', $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $block_name = $match[1][0];
        $position = $match[0][1];
        
        $closing_tags[] = [
            'name' => $block_name,
            'position' => $position
        ];
    }
    
    // Create a map of closing tags for each block type
    $closing_map = [];
    foreach ($closing_tags as $tag) {
        if (!isset($closing_map[$tag['name']])) {
            $closing_map[$tag['name']] = [];
        }
        $closing_map[$tag['name']][] = $tag['position'];
    }
    
    // Check for unclosed blocks
    $unclosed_blocks = [];
    
    foreach ($opening_tags as $tag) {
        $block_name = $tag['name'];
        $position = $tag['position'];
        
        // Check if there's a corresponding closing tag after this position
        $closed = false;
        
        if (isset($closing_map[$block_name])) {
            foreach ($closing_map[$block_name] as $key => $closing_position) {
                if ($closing_position > $position) {
                    // Found a closing tag, remove it from map to avoid reuse
                    unset($closing_map[$block_name][$key]);
                    if (empty($closing_map[$block_name])) {
                        unset($closing_map[$block_name]);
                    }
                    $closed = true;
                    break;
                }
            }
        }
        
        if (!$closed) {
            $unclosed_blocks[] = [
                'name' => $block_name,
                'position' => $position,
                'attributes' => $tag['attributes']
            ];
        }
    }
    
    // Add closing tags for all unclosed blocks
    if (!empty($unclosed_blocks)) {
        // Sort by position descending to close nested blocks in the right order
        usort($unclosed_blocks, function($a, $b) {
            return $b['position'] - $a['position'];
        });
        
        $content .= "\n";
        foreach ($unclosed_blocks as $block) {
            $content .= "<!-- /wp:{$block['name']} -->\n";
        }
    }
    
    // Check for any block with invalid JSON attributes and fix it
    $content = preg_replace_callback('/<!-- wp:([^\s\/]+)(\s+({.*?}))?\s*-->/s', function($matches) {
        $block_name = $matches[1];
        $has_attrs = isset($matches[2]) && trim($matches[2]) !== '';
        
        if ($has_attrs) {
            $attributes = $matches[2];
            // Try to parse the JSON
            $json = json_decode(trim($attributes), true);
            
            if ($json === null) {
                // Invalid JSON, return a clean version
                return "<!-- wp:{$block_name} -->";
            }
        }
        
        return $matches[0]; // No changes needed
    }, $content);
    
    // Make sure all blocks are properly closed
    $final_content = '';
    $lines = explode("\n", $content);
    $open_blocks = [];
    
    foreach ($lines as $line) {
        // Skip lines that contain only incomplete blocks
        if (preg_match('/<!-- wp:group\s+\{"[^}]*$/', $line)) {
            continue;
        }
        
        $final_content .= $line . "\n";
        
        // Check for opening block
        if (preg_match('/<!-- wp:([^\s\/]+)/', $line, $matches)) {
            $block_name = $matches[1];
            $open_blocks[] = $block_name;
        }
        
        // Check for closing block
        if (preg_match('/<!-- \/wp:([^\s]+)/', $line, $matches)) {
            $block_name = $matches[1];
            // Remove this block from the stack
            if (($key = array_search($block_name, $open_blocks)) !== false) {
                array_splice($open_blocks, $key, 1);
            }
        }
    }
    
    // Close any remaining open blocks
    if (!empty($open_blocks)) {
        foreach (array_reverse($open_blocks) as $block_name) {
            $final_content .= "<!-- /wp:{$block_name} -->\n";
        }
    }
    
    return $final_content;
}

// Parse WordPress block comments into structured blocks
function parse_block_comments($content) {
    // First clean the block content to fix any issues
    $content = clean_block_content($content);
    
    // Regular expression to extract block comments
    $pattern = '/<!-- wp:([^\s]+)\s*({[^}]*})?\s*-->(.*?)<!-- \/wp:\1 -->/s';
    preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
    
    $blocks = [];
    
    foreach ($matches as $match) {
        $blockName = 'core/' . $match[1];
        $attrs = isset($match[2]) ? json_decode($match[2], true) : [];
        $innerHTML = trim($match[3]);
        
        // Handle any nested blocks
        $innerBlocks = [];
        
        // Check if this block has inner blocks
        if (strpos($innerHTML, '<!-- wp:') !== false) {
            // Extract inner blocks and remove them from innerHTML
            $innerPattern = '/<!-- wp:([^\s]+)\s*({[^}]*})?\s*-->(.*?)<!-- \/wp:\1 -->/s';
            preg_match_all($innerPattern, $innerHTML, $innerMatches, PREG_SET_ORDER);
            
            foreach ($innerMatches as $innerMatch) {
                $innerBlockName = 'core/' . $innerMatch[1];
                $innerAttrs = isset($innerMatch[2]) ? json_decode($innerMatch[2], true) : [];
                $innerInnerHTML = trim($innerMatch[3]);
                
                $innerBlock = [
                    'blockName' => $innerBlockName,
                    'attrs' => $innerAttrs ?: [],
                    'innerBlocks' => [],
                    'innerHTML' => $innerInnerHTML,
                    'innerContent' => [$innerInnerHTML]
                ];
                
                $innerBlocks[] = $innerBlock;
                
                // Remove this inner block from the parent's innerHTML
                $innerHTML = str_replace($innerMatch[0], '', $innerHTML);
            }
            
            $innerHTML = trim($innerHTML);
        }
        
        // Create a block structure
        $block = [
            'blockName' => $blockName,
            'attrs' => $attrs ?: [],
            'innerBlocks' => $innerBlocks,
            'innerHTML' => $innerHTML,
            'innerContent' => $innerHTML ? [$innerHTML] : []
        ];
        
        $blocks[] = $block;
    }
    
    return $blocks;
}

// Process the JSON file
function process_llm_json_file($file_path) {
    if (!file_exists($file_path)) {
        debug_log("File does not exist: $file_path");
        return;
    }
    
    $json_content = file_get_contents($file_path);
    $data = json_decode($json_content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        debug_log("Invalid JSON in file: $file_path");
        return;
    }
    
    // Extract content from the processed_content field
    if (isset($data['processed_content'])) {
        debug_log("Successfully extracted processed_content from JSON.");
        $content = $data['processed_content'];
        
        // Ensure proper encoding
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Check if the content appears to contain WordPress block comments
        if (strpos($content, '<!-- wp:') !== false || strpos($content, '&lt;!-- wp:') !== false) {
            debug_log("Content contains Gutenberg block comments. Parsing...");
            
            // Clean up the content, fix HTML entities and formatting
            $content = clean_block_content($content);
            
            // Get title from the content or use default
            $title = "Swatching Without the Sighs: Tips for Perfect Fit and Fabric";
            if (preg_match('/<!-- wp:heading[^>]*-->\s*<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $content, $title_matches)) {
                $title = strip_tags($title_matches[1]);
            }
            debug_log("Using title: $title");
            
            // Process and import any images in the content
            debug_log("Processing and importing remote images...");
            $image_pattern = '/<!-- wp:image[^>]*\"url\":\"([^\"]+)\"[^>]*-->/';
            preg_match_all($image_pattern, $content, $image_matches);
            
            $images = [];
            if (!empty($image_matches[1])) {
                foreach ($image_matches[1] as $image_url) {
                    $images[] = [
                        'src' => $image_url,
                        'alt' => '',
                        'width' => 'auto',
                        'height' => 'auto'
                    ];
                }
            } else {
                debug_log("No images found using primary regex pattern.");
            }
            
            // Parse the block comments into structured blocks
            $blocks = parse_block_comments($content);
            debug_log("Parsed " . count($blocks) . " blocks from content.");
            
            // Display a sample of the content for debugging
            debug_log("Content sample (first 150 chars): " . substr($content, 0, 150));
            
            // Create a temporary post to let WordPress parse the content
            debug_log("Creating temporary post...");
            $post_id = wp_insert_post([
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => 'draft',
                'post_type' => 'post'
            ]);
            
            if (is_wp_error($post_id)) {
                debug_log("Error creating temporary post: " . $post_id->get_error_message());
                return;
            }
            
            debug_log("Temporary post created with ID: $post_id");
            
            // Get the post to ensure blocks are parsed
            $post = get_post($post_id);
            
            // Get the parsed blocks
            $parsed_blocks = parse_blocks($post->post_content);
            debug_log("Number of blocks parsed by WordPress: " . count($parsed_blocks));
            
            // If we have blocks from WordPress's parser, use those
            if (!empty($parsed_blocks)) {
                $blocks = $parsed_blocks;
            }
            
            // Create structured JSON for json-to-post.php
            $formatted_json = [
                'title' => $title,
                'blocks' => $blocks
            ];
            
            // Save the formatted JSON
            $upload_dir = wp_upload_dir();
            $output_file = $upload_dir['basedir'] . '/utg-debug/formatted-from-llm-' . date('YmdHis') . '.json';
            file_put_contents($output_file, json_encode($formatted_json, JSON_PRETTY_PRINT));
            
            $relative_path = str_replace(ABSPATH, '', $output_file);
            debug_log("Formatted JSON saved to: $relative_path");
            debug_log("You can now run: wp utg json-to-post $relative_path");
            
            // Clean up
            wp_delete_post($post_id, true);
            debug_log("Temporary post deleted.");
        } else {
            debug_log("Content does not contain WordPress block comments.");
            debug_log("Content sample: " . substr($content, 0, 150));
        }
    } else {
        debug_log("File does not contain processed_content field.");
    }
}

// Main execution
$file_path = '/home/staging/public_html/wp-content/uploads/utg-debug/nijv-zgpvh-campaign-view-com-c808f40bcdc68fa9bc699c09e2fc92be-llm_processed_content-20250403-155736.json';
process_llm_json_file($file_path); 