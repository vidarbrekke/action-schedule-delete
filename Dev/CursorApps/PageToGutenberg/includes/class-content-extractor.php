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
     * @return array|\WP_Error The extracted content or an error.
     */
    public function extract( $url ) {
        if (empty($url)) {
            error_log('UTG: Empty URL provided to Content_Extractor');
            return new \WP_Error('invalid_url', 'URL cannot be empty');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            error_log('UTG: Invalid URL format: ' . $url);
            return new \WP_Error('invalid_url', 'Invalid URL format');
        }

        error_log('UTG: Starting content extraction for URL: ' . $url);

        try {
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
            $context = stream_context_create([
                'http' => [
                    'user_agent' => $user_agent
                ]
            ]);

            error_log('UTG: Attempting to fetch content with user agent: ' . $user_agent);
            $html = @file_get_contents($url, false, $context);

            if ($html === false) {
                $error = error_get_last();
                error_log('UTG: Failed to fetch content: ' . ($error ? $error['message'] : 'Unknown error'));
                return new \WP_Error('fetch_failed', 'Failed to fetch content from URL');
            }

            error_log('UTG: Content fetched successfully, length: ' . strlen($html));

            $extractor = new ArticleExtractor($url);
            $article = $extractor->article();

            error_log('UTG: Attempting to extract article content');
            $content = [
                'title' => $extractor->title(),
                'content' => $article,
                'images' => $this->extract_images($html, $url),
                'author' => '',  // Not supported by ArticleExtractor
                'published_date' => ''  // Not supported by ArticleExtractor
            ];

            error_log('UTG: Content extraction completed. Title length: ' . strlen($content['title']) . ', Content length: ' . strlen($content['content']) . ', Image count: ' . count($content['images']));

            if (empty($content['content']) || strlen($content['content']) < 100) {
                error_log('UTG: Insufficient content extracted. Content length: ' . strlen($content['content']));
                return new \WP_Error('insufficient_content', 'Could not extract sufficient content from the URL');
            }

            return $content;

        } catch (\Exception $e) {
            error_log('UTG: Exception during content extraction: ' . $e->getMessage());
            return new \WP_Error('extraction_failed', 'Content extraction failed: ' . $e->getMessage());
        }
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
} 