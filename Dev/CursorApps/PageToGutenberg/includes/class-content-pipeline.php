<?php
/**
 * Content Pipeline
 *
 * @package UTG
 */

namespace UTG;

use WP_Error;
use UTG\API\LLM_API;
use UTG\Generator\Post_Generator;
use UTG\Generator\Media_Handler;
use UTG\Content_Extractor;

/**
 * Content Pipeline class
 * Orchestrates the complete workflow from URL submission to WordPress post creation
 */
class Content_Pipeline {
    /**
     * Settings instance
     *
     * @var Settings
     */
    private $settings;

    /**
     * LLM API instance
     *
     * @var LLM_API
     */
    private $llm_api;

    /**
     * Post Generator instance
     *
     * @var Post_Generator
     */
    private $post_generator;

    /**
     * Media Handler instance
     *
     * @var Media_Handler
     */
    private $media_handler;

    /**
     * Debug mode
     *
     * @var bool
     */
    private $debug;

    /**
     * Content Extractor instance
     *
     * @var Content_Extractor
     */
    private $content_extractor;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = new Settings();
        $this->debug = $this->settings->get('debug_mode');
        $this->llm_api = new LLM_API($this->settings);
        $this->content_extractor = new Content_Extractor($this->settings);
        $this->media_handler = new Generator\UTG_Media_Handler();
        $this->post_generator = new Generator\Post_Generator($this->media_handler, $this->settings);
        
        // Initialize log directories when in debug mode
        if ($this->debug) {
            $this->initialize_log_directories();
        }
    }
    
    /**
     * Initialize log directories
     * Creates necessary directories for logs and debug files
     */
    private function initialize_log_directories() {
        // Get WordPress uploads directory
        $upload_dir = \wp_upload_dir();
        if (isset($upload_dir['error']) && $upload_dir['error'] !== false) {
            \error_log('UTG Pipeline: Failed to get uploads directory: ' . $upload_dir['error']);
            return;
        }
        
        // Create debug directory in uploads
        $debug_dir = $upload_dir['basedir'] . '/utg-debug';
        if (!file_exists($debug_dir)) {
            if (!\mkdir($debug_dir, 0755, true)) {
                \error_log('UTG Pipeline: Failed to create debug directory: ' . $debug_dir);
            } else {
                \error_log('UTG Pipeline: Created debug directory: ' . $debug_dir);
                // Create an index.php file to prevent directory listing
                \file_put_contents($debug_dir . '/index.php', '<?php // Silence is golden');
            }
        }
        
        // Create WC logs directory
        $wc_logs_dir = $upload_dir['basedir'] . '/wc-logs';
        if (!file_exists($wc_logs_dir)) {
            if (!\mkdir($wc_logs_dir, 0755, true)) {
                \error_log('UTG Pipeline: Failed to create WC logs directory: ' . $wc_logs_dir);
            } else {
                \error_log('UTG Pipeline: Created WC logs directory: ' . $wc_logs_dir);
                // Create an index.php file to prevent directory listing
                \file_put_contents($wc_logs_dir . '/index.php', '<?php // Silence is golden');
            }
        }
        
        // Create UTG logs directory within WC logs
        $utg_logs_dir = $wc_logs_dir . '/utg-logs';
        if (!file_exists($utg_logs_dir)) {
            if (!\mkdir($utg_logs_dir, 0755, true)) {
                \error_log('UTG Pipeline: Failed to create UTG logs directory: ' . $utg_logs_dir);
            } else {
                \error_log('UTG Pipeline: Created UTG logs directory: ' . $utg_logs_dir);
                // Create an index.php file to prevent directory listing
                \file_put_contents($utg_logs_dir . '/index.php', '<?php // Silence is golden');
                
                // Create a test log file to ensure the directory shows up
                $test_log_file = $utg_logs_dir . '/utg-init-' . date('Y-m-d') . '.log';
                \file_put_contents($test_log_file, "UTG Debug Mode Initialized: " . date('Y-m-d H:i:s') . "\n");
            }
        }
    }

    /**
     * Process a URL
     *
     * @param string $url     The URL to process
     * @param array  $options Processing options
     * @return array|\WP_Error The result data or error
     */
    public function process_url($url, $options = []) {
        if ($this->debug) {
            \error_log('UTG Pipeline: Starting URL processing: ' . $url);
        }

        try {
            // Step 1: Get content from URL
            $extractor = new Content_Extractor($this->settings);
            $extracted_content = $extractor->extract($url);
            if (is_wp_error($extracted_content)) {
                return $extracted_content;
            }

            // Step 2: Process with LLM API
            $llm_options = array_merge($options, [
                'debug' => $this->debug
            ]);
            $llm_result = $this->llm_api->process_url($url, $llm_options);
            if (is_wp_error($llm_result)) {
                return $llm_result;
            }

            // Step 3: Process images
            $blocks_with_images = $llm_result['content'] ?? '';
            if (isset($options['process_images']) && $options['process_images']) {
                $blocks_with_images = $this->media_handler->process_content_images($blocks_with_images);
            }

            // Save debug info if needed
            if (isset($options['save_debug']) && $options['save_debug']) {
                $this->save_debug_output($extracted_content, $llm_result, $blocks_with_images);
            }

            // Step 4: Generate WordPress post
            $post_data = [
                'post_title' => $llm_result['title'] ?? '',
                'post_content' => $blocks_with_images,
                'post_status' => isset($options['post_status']) ? $options['post_status'] : 'draft',
                'post_type' => isset($options['post_type']) ? $options['post_type'] : 'post',
            ];

            $post_id = \wp_insert_post($post_data);
            if (\is_wp_error($post_id)) {
                return $post_id;
            }

            return [
                'post_id' => $post_id,
                'edit_url' => \get_edit_post_link($post_id, 'raw'),
                'view_url' => \get_permalink($post_id),
                'title' => $llm_result['title'] ?? '',
                'success' => true,
            ];
        } catch (\Exception $e) {
            \error_log('UTG Pipeline: Exception in process_url: ' . $e->getMessage());
            \error_log('UTG Pipeline: Stack trace: ' . $e->getTraceAsString());
            return new \WP_Error('process_exception', $e->getMessage());
        }
    }

    /**
     * Parse content into WordPress blocks
     *
     * @param string $content The content to parse
     * @return string|\WP_Error Formatted block content or WP_Error
     */
    private function parse_content_to_blocks($content) {
        try {
            // Clean the content to ensure proper block structure
            $cleaned_content = $this->clean_block_content($content);
            
            // Verify that content contains valid Gutenberg blocks
            if (strpos($cleaned_content, '<!-- wp:') === false) {
                return new \WP_Error('invalid_blocks', 'Content does not contain valid Gutenberg blocks');
            }
            
            return $cleaned_content;
        } catch (\Exception $e) {
            \error_log('UTG Pipeline: Exception in parse_content_to_blocks: ' . $e->getMessage());
            return new \WP_Error('parse_exception', $e->getMessage());
        }
    }

    /**
     * Process images in blocks 
     *
     * @param string $content Block content with images
     * @return string|\WP_Error Block content with processed images
     */
    private function process_images($content) {
        try {
            // Use the Media Handler to process images
            $processed_content = $this->media_handler->process_content_images($content);
            return $processed_content;
        } catch (\Exception $e) {
            \error_log('UTG Pipeline: Exception in process_images: ' . $e->getMessage());
            return new \WP_Error('image_processing_exception', $e->getMessage());
        }
    }

    /**
     * Clean block content to fix common issues
     *
     * @param string $content Block content to clean
     * @return string Cleaned block content
     */
    private function clean_block_content($content) {
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Fix truncated group blocks
        if (preg_match('/<!-- wp:group\s+\{"[^}]*$/ms', $content)) {
            $content = preg_replace('/<!-- wp:group\s+\{"[^}]*$/ms', "<!-- wp:group -->\n<!-- /wp:group -->", $content);
        }
        
        // Fix truncated group blocks with incomplete JSON
        $pattern = '/<!-- wp:group\s+\{"[^}]*$/m';
        $content = preg_replace_callback($pattern, function($matches) {
            return '<!-- wp:group -->';
        }, $content);
        
        // Convert HTML encoding for block comments
        $content = preg_replace_callback('/&lt;!--\s*wp:([^\s>]+)(\s+({.*?}))?\s*--&gt;/s', function($matches) {
            $block_name = $matches[1];
            $attributes = isset($matches[3]) ? $matches[3] : '';
            return "<!-- wp:{$block_name}{$attributes} -->";
        }, $content);
        
        // Convert closing tags
        $content = preg_replace('/&lt;!--\s*\/wp:([^\s>]+)\s*--&gt;/s', '<!-- /wp:$1 -->', $content);
        
        // Fix empty group blocks with background-color but no content
        $empty_group_pattern = '/(<!-- wp:group\s+\{[^}]*"backgroundColor":"[^"]*"[^}]*\}\s*-->)\s*(<div[^>]*has-background[^>]*>\s*<\/div>)\s*(<!-- \/wp:group -->)/is';
        $content = preg_replace_callback($empty_group_pattern, function($matches) {
            $opening_tag = $matches[1];
            $div_with_class = $matches[2];
            $closing_tag = $matches[3];
            
            // Replace empty div with div containing a non-breaking space
            $fixed_div = str_replace('></div>', '>&nbsp;</div>', $div_with_class);
            
            // Add minimum height to ensure visibility
            $fixed_div = str_replace('class="', 'class="min-height-30px ', $fixed_div);
            
            return $opening_tag . "\n" . $fixed_div . "\n" . $closing_tag;
        }, $content);
        
        // Also fix empty group blocks with style attribute
        $empty_styled_group_pattern = '/(<!-- wp:group\s+\{[^}]*\}\s*-->)\s*(<div[^>]*style="[^"]*background-color:[^"]*"[^>]*>\s*<\/div>)\s*(<!-- \/wp:group -->)/is';
        $content = preg_replace_callback($empty_styled_group_pattern, function($matches) {
            $opening_tag = $matches[1];
            $div_with_style = $matches[2];
            $closing_tag = $matches[3];
            
            // Replace empty div with div containing a non-breaking space
            $fixed_div = str_replace('></div>', '>&nbsp;</div>', $div_with_style);
            
            // Add minimum height inline style
            $fixed_div = str_replace('style="', 'style="min-height:30px; ', $fixed_div);
            
            return $opening_tag . "\n" . $fixed_div . "\n" . $closing_tag;
        }, $content);
        
        // Ensure all blocks are properly closed
        $final_content = '';
        $lines = explode("\n", $content);
        $open_blocks = [];
        
        foreach ($lines as $line) {
            // Skip lines with incomplete blocks
            if (preg_match('/<!-- wp:group\s+\{"[^}]*$/', $line)) {
                continue;
            }
            
            $final_content .= $line . "\n";
            
            // Track opening blocks
            if (preg_match('/<!-- wp:([^\s\/]+)/', $line, $matches)) {
                $block_name = $matches[1];
                $open_blocks[] = $block_name;
            }
            
            // Track closing blocks
            if (preg_match('/<!-- \/wp:([^\s]+)/', $line, $matches)) {
                $block_name = $matches[1];
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

    /**
     * Save debug output to files
     *
     * @param array  $content            The extracted content
     * @param array  $llm_result         The LLM processing result
     * @param string $blocks_with_images The blocks with images replaced
     */
    private function save_debug_output($content, $llm_result, $blocks_with_images) {
        if (!$this->debug) {
            return;
        }
        
        $upload_dir = \wp_upload_dir();
        $debug_dir = $upload_dir['basedir'] . '/utg-debug';
        
        // Create debug directory if it doesn't exist
        if (!file_exists($debug_dir)) {
            \wp_mkdir_p($debug_dir);
        }
        
        // Create .htaccess file to protect debug files if it doesn't exist
        $htaccess_file = $debug_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Deny from all\n");
        }
        
        // Create a unique filename based on domain
        $url = $llm_result['url'] ?? $content['url'] ?? '';
        $domain = $url ? preg_replace('/[^\w\-.]/', '-', parse_url($url, PHP_URL_HOST)) : 'unknown';
        $timestamp = date('Ymd-His');
        
        // Save extracted article as HTML
        if (!empty($content['content'])) {
            $html_file = $debug_dir . '/' . $domain . '-extracted_article-' . $timestamp . '.html';
            file_put_contents($html_file, $content['content']);
        }
        
        // Save LLM output as JSON
        if (!empty($llm_result['content'])) {
            $json_file = $debug_dir . '/' . $domain . '-final_content-' . $timestamp . '.json';
            file_put_contents($json_file, $llm_result['content']);
        }
        
        // Save detailed debug info for advanced debugging
        $debug_info = [
            'url' => $url,
            'timestamp' => date('Y-m-d H:i:s'),
            'content_title' => $content['title'] ?? '',
            'content_length' => strlen($content['content'] ?? ''),
            'images_count' => count($content['images'] ?? []),
            'llm_content_length' => strlen($llm_result['content'] ?? ''),
            'blocks_with_images_length' => strlen($blocks_with_images),
            'llm_model' => $llm_result['model'] ?? '',
            'llm_usage' => $llm_result['usage'] ?? [],
        ];
        
        $debug_file = $debug_dir . '/' . $domain . '-debug_info-' . $timestamp . '.json';
        file_put_contents($debug_file, json_encode($debug_info, JSON_PRETTY_PRINT));
    }
} 