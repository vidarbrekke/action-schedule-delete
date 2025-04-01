<?php
/**
 * Helper functions for EasyPHPArticleExtractor
 * 
 * This file provides essential helper functions needed by the EasyPHPArticleExtractor library.
 */

if (!function_exists('extract_article_content')) {
    /**
     * Extract main content from an HTML page
     * 
     * @param string $html The HTML content to analyze
     * @return array Extracted content with title, text, images, etc.
     */
    function extract_article_content($html) {
        // Simple fallback implementation if the real library fails
        $article = [
            'title' => '',
            'content' => '',
            'images' => [],
            'description' => '',
            'author' => '',
            'published_date' => '',
        ];
        
        // Extract title from meta tags or h1
        preg_match('/<title>(.*?)<\/title>/is', $html, $title_match);
        if (!empty($title_match[1])) {
            $article['title'] = trim($title_match[1]);
        } else {
            preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1_match);
            if (!empty($h1_match[1])) {
                $article['title'] = trim(strip_tags($h1_match[1]));
            }
        }
        
        // Extract meta description
        preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/is', $html, $meta_desc);
        if (!empty($meta_desc[1])) {
            $article['description'] = trim($meta_desc[1]);
        }
        
        // Extract author
        preg_match('/<meta[^>]*name=["\']author["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/is', $html, $meta_author);
        if (!empty($meta_author[1])) {
            $article['author'] = trim($meta_author[1]);
        }
        
        // Extract main content (simplified)
        $content = '';
        // Look for article, main, or content divs
        preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $article_match);
        if (!empty($article_match[1])) {
            $content = $article_match[1];
        } else {
            preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $main_match);
            if (!empty($main_match[1])) {
                $content = $main_match[1];
            } else {
                preg_match('/<div[^>]*id=["\']content["\'][^>]*>(.*?)<\/div>/is', $html, $content_match);
                if (!empty($content_match[1])) {
                    $content = $content_match[1];
                }
            }
        }
        
        // Clean up content
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
        $content = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $content);
        $content = preg_replace('/<header\b[^>]*>(.*?)<\/header>/is', '', $content);
        $content = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $content);
        
        $article['content'] = trim($content);
        
        // Extract images
        preg_match_all('/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/is', $content, $image_matches);
        if (!empty($image_matches[1])) {
            $article['images'] = array_unique($image_matches[1]);
        }
        
        return $article;
    }
}

if (!function_exists('clean_html_content')) {
    /**
     * Cleans HTML content by removing unwanted elements
     * 
     * @param string $html The HTML content to clean
     * @return string Cleaned HTML
     */
    function clean_html_content($html) {
        // Remove scripts, styles, and comments
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        $html = preg_replace('/<!--(.*)-->/Uis', '', $html);
        
        // Remove navigation, header, footer elements
        $html = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $html);
        $html = preg_replace('/<header\b[^>]*>(.*?)<\/header>/is', '', $html);
        $html = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $html);
        
        return $html;
    }
}

if (!function_exists('get_most_important_image')) {
    /**
     * Gets the most important image from article content
     * 
     * @param array $article The article data containing images
     * @return string URL of the most important image or empty string
     */
    function get_most_important_image($article) {
        if (empty($article['images'])) {
            return '';
        }
        
        // Simply return the first image for this fallback implementation
        return $article['images'][0];
    }
} 