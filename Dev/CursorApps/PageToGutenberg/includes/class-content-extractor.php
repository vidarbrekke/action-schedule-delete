<?php
/**
 * Content Extractor class.
 *
 * @package UTG
 */

namespace UTG;

use WP_Error;
use hstanleycrow\EasyPHPArticleExtractor\ArticleExtractor;

/**
 * Class Content_Extractor
 * 
 * Handles content extraction from URLs using EasyPHPArticleExtractor
 */
class Content_Extractor {

    /**
     * Settings instance.
     *
     * @var Settings
     */
    private $settings;

    /**
     * Constructor.
     *
     * @param Settings $settings Settings instance.
     */
    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Extract content from a URL.
     *
     * @param string $url The URL to extract content from.
     * @param string $cleaning_level The level of HTML cleaning to apply ('standard', 'medium', 'aggressive').
     * @return array|\WP_Error The extracted content or an error.
     */
    public function extract( $url, $cleaning_level = 'standard' ) {
        if (empty($url)) {
            error_log('UTG: Empty URL provided to Content_Extractor');
            return new \WP_Error('invalid_url', 'URL cannot be empty');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            error_log('UTG: Invalid URL format: ' . $url);
            return new \WP_Error('invalid_url', 'Invalid URL format');
        }

        error_log('UTG: Starting content extraction for URL: ' . $url);
        $debug_mode = $this->settings->get('debug_mode', false);

        if ($cleaning_level !== 'standard') {
            error_log('UTG: Using ' . $cleaning_level . ' cleaning mode');
        }

        try {
            // Set a robust user agent string
            $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.82 Safari/537.36';
            
            // First attempt: Use ArticleExtractor direct methods with the URL
            error_log('UTG: Attempting to extract content using ArticleExtractor with URL');
            $extractor = new ArticleExtractor($url, $user_agent);
            
            try {
                $text = $extractor->article();
                $title = $extractor->title();
                
                if (!empty($text)) {
                    error_log('UTG: Content extracted successfully with ArticleExtractor');
                    
                    // Save extracted content for debugging if enabled
                    if ($debug_mode) {
                        $this->save_debug_content('extracted_article', $url, $text);
                        $this->save_debug_content('extracted_title', $url, $title);
                    }
                    
                    // Store the raw extracted content before cleaning
                    $raw_extracted_content = $text;
                    
                    // Apply the appropriate level of cleaning
                    if ($cleaning_level === 'aggressive') {
                        $text = $this->aggressive_html_cleaning($text);
                        if ($debug_mode) {
                            $this->save_debug_content('aggressive_cleaned_article', $url, $text);
                        }
                    } else if ($cleaning_level === 'medium') {
                        $text = $this->medium_html_cleaning($text);
                        if ($debug_mode) {
                            $this->save_debug_content('medium_cleaned_article', $url, $text);
                        }
                    } else {
                        // Standard level uses the original text but we should still save for debugging
                        if ($debug_mode) {
                            $this->save_debug_content('standard_cleaned_article', $url, $text);
                        }
                    }
                    
                    // Extract images 
                    $images = $this->extract_images_from_html($text, $url);
                    
                    $content = [
                        'title' => $title ? $title : basename($url),
                        'content' => $text,
                        'raw_extracted_content' => $raw_extracted_content,
                        'images' => $images,
                        'author' => '',
                        'published_date' => ''
                    ];
                    
                    // Save final content for debugging if enabled
                    if ($debug_mode) {
                        $this->save_debug_content('final_content', $url, json_encode($content, JSON_PRETTY_PRINT));
                    }
                    
                    return $content;
                }
            } catch (\Exception $e) {
                error_log('UTG: Exception in first extraction attempt: ' . $e->getMessage());
            }
            
            error_log('UTG: Direct extraction did not return valid content, trying to fetch HTML first');
            
            // Second attempt: Fetch HTML manually and use other extraction methods
            $html = $this->fetch_html_content($url, $user_agent);
            
            if ($html === false) {
                error_log('UTG: Failed to fetch HTML content');
                return new \WP_Error('fetch_failed', 'Failed to fetch content from URL');
            }
            
            // Save raw HTML for debugging if enabled
            if ($debug_mode) {
                $this->save_debug_content('raw_html', $url, $html);
            }
            
            // Try creating a new extractor with the fetched HTML
            error_log('UTG: Attempting extraction with manually fetched HTML');
            $new_extractor = new ArticleExtractor($url, $user_agent);
            
            // Try to extract content using the new HTML (we'll need to access private methods if possible)
            try {
                // Extract using basic metadata extraction
                preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $title_match);
                $extracted_title = isset($title_match[1]) ? trim($title_match[1]) : basename($url);
                
                // Try to extract body content
                preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $body_match);
                
                if (!empty($body_match[1])) {
                    $extracted_content = $body_match[1];
                    
                    error_log("UTG: Extracted title and body content manually");
                    
                    if ($debug_mode) {
                        $this->save_debug_content('extracted_body', $url, $extracted_content);
                    }
                    
                    // Clean the content
                    $cleaned_content = $this->basic_html_cleaning($extracted_content);
                    
                    if ($debug_mode) {
                        $this->save_debug_content('cleaned_content', $url, $cleaned_content);
                    }
                    
                    $content = [
                        'title' => $extracted_title,
                        'content' => $cleaned_content,
                        'images' => $this->extract_images($html, $url),
                        'author' => '',
                        'published_date' => ''
                    ];
                    
                    return $content;
                }
            } catch (\Exception $e) {
                error_log('UTG: Exception in second extraction attempt: ' . $e->getMessage());
            }
            
            // Final fallback: Basic HTML cleaning of the entire document
            error_log('UTG: All extraction methods failed, attempting basic HTML cleaning of entire document');
            $cleaned_content = $this->basic_html_cleaning($html);
            
            // Save fallback content for debugging if enabled
            if ($debug_mode) {
                $this->save_debug_content('fallback_cleaning', $url, $cleaned_content);
            }
            
            $content = [
                'title' => isset($extracted_title) ? $extracted_title : basename($url),
                'content' => $cleaned_content,
                'images' => $this->extract_images($html, $url),
                'author' => '',
                'published_date' => ''
            ];
            
            return $content;

        } catch (\Exception $e) {
            error_log('UTG: Exception during content extraction: ' . $e->getMessage());
            
            if ($debug_mode) {
                $this->save_debug_content('extraction_error', $url, $e->getMessage() . "\n\n" . $e->getTraceAsString());
            }
            
            return new \WP_Error('extraction_failed', 'Content extraction failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Fetch HTML content from URL using multiple methods.
     * 
     * @param string $url The URL to fetch.
     * @param string $user_agent User agent string to use.
     * @return string|false The fetched HTML content or false on failure.
     */
    private function fetch_html_content($url, $user_agent) {
        // First try file_get_contents
        $context = stream_context_create([
            'http' => [
                'user_agent' => $user_agent,
                'timeout' => 30,
                'follow_location' => 1,
                'ignore_errors' => true,
                'header' => "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n" .
                            "Accept-Language: en-US,en;q=0.5\r\n"
            ]
        ]);

        $html = @file_get_contents($url, false, $context);
        
        if ($html !== false && strlen($html) > 100) {
            error_log('UTG: Content fetched successfully with file_get_contents');
            return $html;
        }
        
        // Fallback to cURL if available
        if (function_exists('curl_init')) {
            error_log('UTG: Attempting to fetch content using cURL as fallback');
            $html = $this->fetch_with_curl($url, $user_agent);
            
            if ($html !== false && strlen($html) > 100) {
                error_log('UTG: Content fetched successfully with cURL');
                return $html;
            }
        }
        
        error_log('UTG: Failed to fetch content from URL using all available methods');
        return false;
    }
    
    /**
     * Basic HTML cleaning for fallback extraction.
     * 
     * @param string $html The HTML content to clean.
     * @return string The cleaned HTML content.
     */
    private function basic_html_cleaning($html) {
        // Create a DOM document
        $dom = new \DOMDocument();
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        // Remove script and style elements
        $scripts = $dom->getElementsByTagName('script');
        $styles = $dom->getElementsByTagName('style');
        
        $remove_elements = [];
        
        // We need to collect elements first before removing them
        for ($i = $scripts->length - 1; $i >= 0; $i--) {
            $remove_elements[] = $scripts->item($i);
        }
        
        for ($i = $styles->length - 1; $i >= 0; $i--) {
            $remove_elements[] = $styles->item($i);
        }
        
        // Remove unwanted elements
        foreach ($remove_elements as $element) {
            $element->parentNode->removeChild($element);
        }
        
        // Try to extract main content (body or article tag)
        $body = $dom->getElementsByTagName('body')->item(0);
        $article = $dom->getElementsByTagName('article')->item(0);
        $main = $dom->getElementsByTagName('main')->item(0);
        $content = $dom->getElementsByTagName('div');
        
        $content_element = null;
        
        if ($article) {
            $content_element = $article;
        } elseif ($main) {
            $content_element = $main;
        } elseif ($body) {
            $content_element = $body;
        } else {
            // Look for content in divs with common content class names
            foreach ($content as $div) {
                $class = $div->getAttribute('class');
                $id = $div->getAttribute('id');
                
                // Check for common content class/id names
                if (preg_match('/(content|article|main|text|body)/i', $class) || 
                    preg_match('/(content|article|main|text|body)/i', $id)) {
                    $content_element = $div;
                    break;
                }
            }
        }
        
        if ($content_element) {
            // Get inner HTML of content element
            $inner_html = $dom->saveHTML($content_element);
            
            // Special handling for Campaign Monitor emails
            if (strpos($inner_html, 'campaign-view.com') !== false || 
                strpos($inner_html, 'campaignmonitor') !== false) {
                error_log('UTG: Detected Campaign Monitor email, applying specialized cleaning');
                
                // Replace nested tables with divs to simplify structure
                $inner_html = preg_replace('/<table[^>]*>/i', '<div class="table-container">', $inner_html);
                $inner_html = preg_replace('/<\/table>/i', '</div>', $inner_html);
                $inner_html = preg_replace('/<tr[^>]*>/i', '<div class="table-row">', $inner_html);
                $inner_html = preg_replace('/<\/tr>/i', '</div>', $inner_html);
                $inner_html = preg_replace('/<td[^>]*>/i', '<div class="table-cell">', $inner_html);
                $inner_html = preg_replace('/<\/td>/i', '</div>', $inner_html);
                
                // Remove MSO conditional comments that can break parsing
                $inner_html = preg_replace('/<!--\[if[^>]*>.*?<!\[endif\]-->/s', '', $inner_html);
                
                // Remove base64 images which can be huge
                $inner_html = preg_replace('/src="data:image\/[^"]+"/i', 'src="#"', $inner_html);
            } else {
                // Standard table handling for non-Campaign Monitor content
                $inner_html = preg_replace('/<table[^>]*>/i', '<div>', $inner_html);
                $inner_html = preg_replace('/<\/table>/i', '</div>', $inner_html);
                $inner_html = preg_replace('/<tr[^>]*>/i', '<div>', $inner_html);
                $inner_html = preg_replace('/<\/tr>/i', '</div>', $inner_html);
                $inner_html = preg_replace('/<td[^>]*>/i', '<div>', $inner_html);
                $inner_html = preg_replace('/<\/td>/i', '</div>', $inner_html);
            }
            
            // Remove potentially problematic attributes
            $inner_html = preg_replace('/\s+style\s*=\s*"[^"]*"/i', '', $inner_html);
            $inner_html = preg_replace('/\s+class\s*=\s*"[^"]*"/i', '', $inner_html);
            $inner_html = preg_replace('/\s+id\s*=\s*"[^"]*"/i', '', $inner_html);
            
            // Remove HTML comments
            $inner_html = preg_replace('/<!--.*?-->/s', '', $inner_html);
            
            // Remove control characters that might break JSON
            $inner_html = preg_replace('/[\x00-\x1F\x7F]/u', '', $inner_html);
            
            // Remove Unicode line and paragraph separators
            $inner_html = str_replace(["\xE2\x80\xA8", "\xE2\x80\xA9"], '', $inner_html);
            
            // Clean up multiple spaces and line breaks
            $inner_html = preg_replace('/\s+/', ' ', $inner_html);
            
            return $inner_html;
        }
        
        // If we couldn't find a content element, just return the cleaned body
        $body_html = $dom->saveHTML($body);
        
        // Apply minimal cleaning to body
        $body_html = preg_replace('/[\x00-\x1F\x7F]/u', '', $body_html);
        $body_html = str_replace(["\xE2\x80\xA8", "\xE2\x80\xA9"], '', $body_html);
        
        return $body_html;
    }
    
    /**
     * Extract images from HTML content specifically for metadata.
     * 
     * @param string $html The HTML content.
     * @param string $url The base URL.
     * @return array Array of image information.
     */
    private function extract_images_from_html($html, $url) {
        $images = [];
        
        // Create a DOM document
        $dom = new \DOMDocument();
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        // Find all img tags
        $img_tags = $dom->getElementsByTagName('img');
        
        foreach ($img_tags as $img) {
            $src = $img->getAttribute('src');
            
            // Skip if no src attribute
            if (empty($src)) {
                continue;
            }
            
            // Make src absolute if it's relative
            if (strpos($src, 'http') !== 0) {
                $src = $this->make_absolute_url($src, $url);
            }
            
            // Get alt text
            $alt = $img->getAttribute('alt');
            
            // Get image dimensions
            $width = $img->getAttribute('width');
            $height = $img->getAttribute('height');
            
            $images[] = [
                'src' => $src,
                'alt' => $alt,
                'width' => $width,
                'height' => $height,
            ];
        }
        
        return $images;
    }

    /**
     * Fetch content using cURL as a fallback method.
     * 
     * @param string $url The URL to fetch.
     * @param string $user_agent User agent string to use.
     * @return string|false The fetched HTML content or false on failure.
     */
    private function fetch_with_curl($url, $user_agent) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_HEADER, false);
        
        // Accept headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5'
        ]);
        
        $html = curl_exec($ch);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        if ($html === false) {
            error_log('UTG: cURL error: ' . $error);
            return false;
        }
        
        if ($info['http_code'] >= 400) {
            error_log('UTG: cURL HTTP error: ' . $info['http_code']);
            return false;
        }
        
        return $html;
    }

    /**
     * Extract images from HTML content.
     *
     * @param string $html The HTML content.
     * @param string $url The base URL.
     * @return array Array of image information.
     */
    private function extract_images($html, $url) {
        $images = [];
        
        // Create a DOM document
        $dom = new \DOMDocument();
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        // Find all img tags
        $img_tags = $dom->getElementsByTagName('img');
        
        foreach ($img_tags as $img) {
            $src = $img->getAttribute('src');
            
            // Skip if no src attribute
            if (empty($src)) {
                continue;
            }
            
            // Make src absolute if it's relative
            if (strpos($src, 'http') !== 0) {
                $src = $this->make_absolute_url($src, $url);
            }
            
            // Get alt text
            $alt = $img->getAttribute('alt');
            
            // Get image dimensions
            $width = $img->getAttribute('width');
            $height = $img->getAttribute('height');
            
            $images[] = [
                'src' => $src,
                'alt' => $alt,
                'width' => $width,
                'height' => $height,
            ];
        }
        
        return $images;
    }

    /**
     * Convert relative URL to absolute URL.
     *
     * @param string $rel_url The relative URL.
     * @param string $base_url The base URL.
     * @return string The absolute URL.
     */
    private function make_absolute_url($rel_url, $base_url) {
        // Parse the base URL
        $parsed_url = parse_url($base_url);
        
        // If the relative URL starts with //, it's a protocol-relative URL
        if (strpos($rel_url, '//') === 0) {
            return $parsed_url['scheme'] . ':' . $rel_url;
        }
        
        // If the relative URL starts with /, it's relative to the root
        if (strpos($rel_url, '/') === 0) {
            return $parsed_url['scheme'] . '://' . $parsed_url['host'] . $rel_url;
        }
        
        // Otherwise, it's relative to the current path
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '/';
        $path = rtrim(dirname($path), '/') . '/';
        
        return $parsed_url['scheme'] . '://' . $parsed_url['host'] . $path . $rel_url;
    }

    /**
     * Check if extracted content is valid and meets minimum requirements.
     *
     * @param array $content The extracted content.
     * @return bool Whether the content is valid.
     */
    private function is_content_valid($content) {
        // Check if content is an array with required keys
        if (!is_array($content) || empty($content['html']) || empty($content['title'])) {
            return false;
        }
        
        // Check minimum content length
        $min_length = $this->settings->get('min_content_length', 200);
        if (strlen($content['text']) < $min_length) {
            return false;
        }
        
        // Check minimum image count if required
        $min_images = $this->settings->get('min_image_count', 0);
        if ($min_images > 0 && count($content['images']) < $min_images) {
            return false;
        }
        
        return true;
    }

    /**
     * Save content to a debug file for inspection.
     *
     * @param string $type Type of content being saved (raw_html, extracted_article, etc.).
     * @param string $url The URL being processed.
     * @param string $content The content to save.
     * @return bool True if content was saved, false otherwise.
     */
    private function save_debug_content($type, $url, $content) {
        try {
            // Get WordPress uploads directory which is more reliable than WP_CONTENT_DIR
            $uploads_dir = \wp_upload_dir();
            if (isset($uploads_dir['error']) && $uploads_dir['error'] !== false) {
                error_log('UTG: Failed to get uploads directory: ' . $uploads_dir['error']);
                return false;
            }
            
            // Create debug directory in uploads
            $debug_dir = $uploads_dir['basedir'] . '/utg-debug';
            if (!file_exists($debug_dir)) {
                if (!mkdir($debug_dir, 0755, true)) {
                    error_log('UTG: Failed to create debug directory: ' . $debug_dir);
                    return false;
                }
                
                // Create an index.php file to prevent directory listing
                file_put_contents($debug_dir . '/index.php', '<?php // Silence is golden');
                
                // Create .htaccess to prevent direct access
                file_put_contents($debug_dir . '/.htaccess', 'Deny from all');
            }
            
            // Generate standardized filename
            $filename = $this->get_debug_filename($url, $type);
            $full_path = $debug_dir . '/' . $filename;
            
            // Save the content
            if (file_put_contents($full_path, $content)) {
                error_log('UTG: Debug content saved: ' . $filename);
                return true;
            }
            
            error_log('UTG: Failed to save debug content: ' . $filename);
            return false;
            
        } catch (\Exception $e) {
            error_log('UTG: Exception while saving debug content: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generates a standardized debug filename for a given URL and content type.
     * 
     * @param string $url The URL being processed
     * @param string $type The type of content (raw_html, extracted_article, etc.)
     * @return string The generated filename
     */
    public function get_debug_filename($url, $type) {
        // Create a safe filename based on URL
        $url_hash = md5($url);
        $safe_url = preg_replace('/[^a-z0-9]+/i', '-', parse_url($url, PHP_URL_HOST));
        $timestamp = date('Ymd-His');
        
        $filename = $safe_url . '-' . $url_hash . '-' . $type . '-' . $timestamp;
        
        // Use appropriate extension based on content type
        if ($type === 'raw_html' || $type === 'extracted_article' || $type === 'body_fallback' || 
            $type === 'medium_cleaned_article' || $type === 'aggressive_cleaned_article' || 
            $type === 'standard_cleaned_article') {
            $filename .= '.html';
        } elseif ($type === 'extracted_title') {
            $filename .= '.txt';
        } elseif ($type === 'final_content' || $type === 'fallback_content') {
            $filename .= '.json';
        } elseif ($type === 'extraction_error') {
            $filename .= '.log';
        } else {
            // Default extension for any other type
            $filename .= '.html';
        }
        
        return $filename;
    }

    /**
     * Aggressive HTML cleaning for complex content.
     * 
     * @param string $html The HTML content to clean.
     * @return string The cleaned HTML content.
     */
    private function aggressive_html_cleaning($html) {
        // Create a DOM document
        $dom = new \DOMDocument();
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);
        
        // Use UTF-8 encoding
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Elements to completely remove
        $remove_tags = ['script', 'style', 'meta', 'link', 'iframe', 'noscript', 'svg', 'canvas'];
        foreach ($remove_tags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            $remove_elements = [];
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $remove_elements[] = $elements->item($i);
            }
            foreach ($remove_elements as $element) {
                if ($element->parentNode) {
                    $element->parentNode->removeChild($element);
                }
            }
        }
        
        // Process all elements to remove unnecessary attributes
        $this->clean_element_attributes($dom->documentElement);
        
        // Special handling for table structures - preserve semantic structure but clean aggressively
        $tables = $dom->getElementsByTagName('table');
        $table_elements = [];
        for ($i = 0; $i < $tables->length; $i++) {
            $table_elements[] = $tables->item($i);
        }
        
        foreach ($table_elements as $table) {
            // Replace table with div but keep a class to indicate it was a table
            $div = $dom->createElement('div');
            $div->setAttribute('class', 'utg-table');
            
            // Replace tbody, thead, tfoot with divs
            $sections = ['tbody', 'thead', 'tfoot'];
            foreach ($sections as $section) {
                $elements = $table->getElementsByTagName($section);
                $section_elements = [];
                for ($i = 0; $i < $elements->length; $i++) {
                    $section_elements[] = $elements->item($i);
                }
                
                foreach ($section_elements as $element) {
                    $section_div = $dom->createElement('div');
                    $section_div->setAttribute('class', 'utg-' . $section);
                    
                    // Process rows within this section
                    $rows = $element->getElementsByTagName('tr');
                    $row_elements = [];
                    for ($i = 0; $i < $rows->length; $i++) {
                        $row_elements[] = $rows->item($i);
                    }
                    
                    foreach ($row_elements as $row) {
                        $row_div = $dom->createElement('div');
                        $row_div->setAttribute('class', 'utg-tr');
                        
                        // Process cells
                        $cells = $row->getElementsByTagName('td');
                        $cell_elements = [];
                        for ($i = 0; $i < $cells->length; $i++) {
                            $cell_elements[] = $cells->item($i);
                        }
                        
                        // Also process th cells
                        $header_cells = $row->getElementsByTagName('th');
                        for ($i = 0; $i < $header_cells->length; $i++) {
                            $cell_elements[] = $header_cells->item($i);
                        }
                        
                        foreach ($cell_elements as $cell) {
                            $cell_div = $dom->createElement('div');
                            $cell_div->setAttribute('class', 'utg-td');
                            
                            // Move all children to the new div
                            while ($cell->childNodes->length > 0) {
                                $cell_div->appendChild($cell->childNodes->item(0));
                            }
                            
                            $row_div->appendChild($cell_div);
                        }
                        
                        $section_div->appendChild($row_div);
                    }
                    
                    $div->appendChild($section_div);
                }
            }
            
            // Replace the table with our cleaned div structure
            if ($table->parentNode) {
                $table->parentNode->replaceChild($div, $table);
            }
        }
        
        // Extract the body content
        $body = $dom->getElementsByTagName('body')->item(0);
        $result = '';
        if ($body) {
            $children = $body->childNodes;
            foreach ($children as $child) {
                $result .= $dom->saveHTML($child);
            }
        } else {
            $result = $dom->saveHTML();
        }
        
        // Remove control characters that might break JSON
        $result = preg_replace('/[\x00-\x1F\x7F]/u', '', $result);
        
        // Remove Unicode line and paragraph separators
        $result = str_replace(["\xE2\x80\xA8", "\xE2\x80\xA9"], '', $result);
        
        // Clean up multiple spaces and line breaks
        $result = preg_replace('/\s{2,}/', ' ', $result);
        
        return $result;
    }
    
    /**
     * Clean attributes from an element and its children recursively.
     * 
     * @param \DOMNode $element The element to clean.
     */
    private function clean_element_attributes($element) {
        if (!$element || $element->nodeType !== XML_ELEMENT_NODE) {
            return;
        }
        
        // List of attributes to keep
        $keep_attributes = [
            'href', 'src', 'alt', 'title', 'colspan', 'rowspan'
        ];
        
        // Remove all attributes except those in the keep list
        if ($element->hasAttributes()) {
            $attributes = [];
            foreach ($element->attributes as $attr) {
                $attributes[] = $attr->name;
            }
            
            foreach ($attributes as $attr) {
                if (!in_array($attr, $keep_attributes)) {
                    $element->removeAttribute($attr);
                }
            }
        }
        
        // Process children recursively
        if ($element->hasChildNodes()) {
            $children = [];
            foreach ($element->childNodes as $child) {
                $children[] = $child;
            }
            
            foreach ($children as $child) {
                $this->clean_element_attributes($child);
            }
        }
    }

    /**
     * Medium HTML cleaning for content.
     * Preserves more structure than aggressive cleaning but removes most styling.
     * 
     * @param string $html The HTML content to clean.
     * @return string The cleaned HTML content.
     */
    private function medium_html_cleaning($html) {
        // Create a DOM document
        $dom = new \DOMDocument();
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors(true);
        
        // Use UTF-8 encoding
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Elements to completely remove
        $remove_tags = ['script', 'style', 'meta', 'link', 'iframe', 'noscript'];
        foreach ($remove_tags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            $remove_elements = [];
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $remove_elements[] = $elements->item($i);
            }
            foreach ($remove_elements as $element) {
                if ($element->parentNode) {
                    $element->parentNode->removeChild($element);
                }
            }
        }
        
        // Process all elements to selectively remove attributes - medium level cleans less aggressively
        $this->clean_element_attributes_medium($dom->documentElement);
        
        // For tables - preserve the structure but clean some attributes
        $tables = $dom->getElementsByTagName('table');
        for ($i = 0; $i < $tables->length; $i++) {
            $table = $tables->item($i);
            
            // Keep the table tag but remove some attributes
            $allowed_attrs = ['width', 'height', 'cellspacing', 'cellpadding', 'border'];
            if ($table->hasAttributes()) {
                $attributes = [];
                foreach ($table->attributes as $attr) {
                    $attributes[] = $attr->name;
                }
                
                foreach ($attributes as $attr) {
                    if (!in_array($attr, $allowed_attrs) && $attr !== 'class' && $attr !== 'id') {
                        $table->removeAttribute($attr);
                    }
                }
            }
            
            // Process rows and cells similarly
            $rows = $table->getElementsByTagName('tr');
            for ($j = 0; $j < $rows->length; $j++) {
                $row = $rows->item($j);
                
                // Keep alignment attributes but remove style
                if ($row->hasAttribute('style')) {
                    $row->removeAttribute('style');
                }
                
                // Process cells
                $cells = $row->getElementsByTagName('td');
                for ($k = 0; $k < $cells->length; $k++) {
                    $cell = $cells->item($k);
                    
                    // Keep some useful attributes
                    $cell_allowed = ['width', 'height', 'rowspan', 'colspan', 'align', 'valign'];
                    if ($cell->hasAttributes()) {
                        $attributes = [];
                        foreach ($cell->attributes as $attr) {
                            $attributes[] = $attr->name;
                        }
                        
                        foreach ($attributes as $attr) {
                            if (!in_array($attr, $cell_allowed) && $attr !== 'class' && $attr !== 'id') {
                                $cell->removeAttribute($attr);
                            }
                        }
                    }
                }
                
                // Also process th cells
                $headers = $row->getElementsByTagName('th');
                for ($k = 0; $k < $headers->length; $k++) {
                    $header = $headers->item($k);
                    if ($header->hasAttribute('style')) {
                        $header->removeAttribute('style');
                    }
                }
            }
        }
        
        // Extract the body content
        $body = $dom->getElementsByTagName('body')->item(0);
        $result = '';
        if ($body) {
            $children = $body->childNodes;
            foreach ($children as $child) {
                $result .= $dom->saveHTML($child);
            }
        } else {
            $result = $dom->saveHTML();
        }
        
        // Remove control characters that might break JSON
        $result = preg_replace('/[\x00-\x1F\x7F]/u', '', $result);
        
        // Remove Unicode line and paragraph separators
        $result = str_replace(["\xE2\x80\xA8", "\xE2\x80\xA9"], '', $result);
        
        // Clean up multiple spaces and line breaks but preserve more formatting than aggressive mode
        $result = preg_replace('/\s{3,}/', ' ', $result);
        
        return $result;
    }
    
    /**
     * Clean attributes from an element and its children for medium level cleaning.
     * Less aggressive than the full cleaning, preserves more formatting attributes.
     * 
     * @param \DOMNode $element The element to clean.
     */
    private function clean_element_attributes_medium($element) {
        if (!$element || $element->nodeType !== XML_ELEMENT_NODE) {
            return;
        }
        
        // List of attributes to keep for medium cleaning
        $keep_attributes = [
            'href', 'src', 'alt', 'title', 'colspan', 'rowspan', 'width', 'height',
            'align', 'valign', 'target', 'name', 'id', 'class'
        ];
        
        // List of elements that should keep their class/style for better rendering
        $style_elements = ['table', 'tr', 'td', 'th', 'img', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        
        // Add 'style' to keep attributes if this is a style element
        if (in_array($element->nodeName, $style_elements)) {
            $keep_attributes[] = 'style';
        }
        
        // Remove unwanted inline style properties but keep the attribute
        if ($element->hasAttribute('style')) {
            $style = $element->getAttribute('style');
            
            // Remove potentially harmful or layout-breaking styles
            $style = preg_replace('/position\s*:\s*[^;]+;?/i', '', $style);
            $style = preg_replace('/z-index\s*:\s*[^;]+;?/i', '', $style);
            $style = preg_replace('/float\s*:\s*[^;]+;?/i', '', $style);
            
            $element->setAttribute('style', $style);
        }
        
        // Remove all attributes except those in the keep list
        if ($element->hasAttributes()) {
            $attributes = [];
            foreach ($element->attributes as $attr) {
                $attributes[] = $attr->name;
            }
            
            foreach ($attributes as $attr) {
                if (!in_array($attr, $keep_attributes)) {
                    $element->removeAttribute($attr);
                }
            }
        }
        
        // Process children recursively
        if ($element->hasChildNodes()) {
            $children = [];
            foreach ($element->childNodes as $child) {
                $children[] = $child;
            }
            
            foreach ($children as $child) {
                $this->clean_element_attributes_medium($child);
            }
        }
    }
} 