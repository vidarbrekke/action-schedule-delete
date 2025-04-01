<?php
/**
 * Content Extractor class.
 *
 * @package UTG
 */

namespace UTG;

use \WP_Error;
use \HStanleyCrow\EasyPHPArticleExtractor\ArticleExtractor;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

/**
 * Class Content_Extractor
 * 
 * Handles content extraction from URLs using a hybrid approach with 
 * EasyPHPArticleExtractor for standard HTML and Symfony Panther for JavaScript-heavy sites.
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
        // Validate URL
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return new \WP_Error( 'invalid_url', \__( 'Invalid URL provided.', 'url-to-gutenberg' ) );
        }

        // Debug logging
        if ( $this->settings->get( 'debug_mode' ) ) {
            \error_log( "[URL to Gutenberg] Extracting content from URL: $url" );
        }

        try {
            // Try standard extraction first
            $content = $this->standard_extraction( $url );
            
            // Check if content meets minimum requirements
            if ( $this->is_content_valid( $content ) ) {
                return $content;
            }
            
            // If standard extraction fails or content is insufficient, try Panther
            if ( $this->settings->get( 'debug_mode' ) ) {
                \error_log( "[URL to Gutenberg] Standard extraction insufficient, trying Panther extraction." );
            }
            
            $content = $this->panther_extraction( $url );
            
            // Check again if content meets minimum requirements
            if ( ! $this->is_content_valid( $content ) ) {
                return new \WP_Error( 
                    'extraction_failed', 
                    \__( 'Could not extract sufficient content from the URL.', 'url-to-gutenberg' ) 
                );
            }
            
            return $content;
            
        } catch ( \Exception $e ) {
            if ( $this->settings->get( 'debug_mode' ) ) {
                \error_log( "[URL to Gutenberg] Extraction error: " . $e->getMessage() );
            }
            
            return new \WP_Error( 'extraction_error', $e->getMessage() );
        }
    }

    /**
     * Standard extraction using EasyPHPArticleExtractor.
     *
     * @param string $url The URL to extract from.
     * @return array The extracted content.
     */
    private function standard_extraction( $url ) {
        // Use EasyPHPArticleExtractor
        $extractor = new ArticleExtractor();
        $article = $extractor->extractFrom($url);
        
        if (!$article || empty($article->getContent())) {
            throw new \Exception( \__( 'Failed to extract content with EasyPHPArticleExtractor.', 'url-to-gutenberg' ) );
        }
        
        // Get the HTML content
        $html = $article->getContent();
        $title = $article->getTitle();
        $text = $article->getText();
        
        // Extract images
        $images = $this->extract_images( $html, $url );
        
        return [
            'title' => $title,
            'html' => $html,
            'text' => $text,
            'images' => $images,
            'url' => $url,
            'extraction_method' => 'standard',
        ];
    }

    /**
     * Extract content using Symfony Panther (headless browser).
     *
     * @param string $url The URL to extract from.
     * @return array The extracted content.
     */
    private function panther_extraction( $url ) {
        // Initialize Chrome client
        $client = Client::createChromeClient();
        
        // Navigate to the URL
        $crawler = $client->request('GET', $url);
        
        // Wait for JavaScript to load content
        $client->waitFor('.article, article, .content, .post, main, #content', 10);
        
        // Get page title
        $title = $crawler->filter('title')->text();
        
        // Try to find the main content
        $content_selectors = [
            'article', '.article', '.post-content', '.entry-content',
            'main', '#content', '.content', '[role="main"]'
        ];
        
        $html = '';
        foreach ( $content_selectors as $selector ) {
            try {
                $content = $crawler->filter( $selector )->first();
                if ( $content->count() > 0 ) {
                    $html = $client->getHtml( $content );
                    break;
                }
            } catch ( \Exception $e ) {
                continue;
            }
        }
        
        // If no content found, use the body
        if ( empty( $html ) ) {
            $html = $client->getHtml( $crawler->filter( 'body' ) );
        }
        
        // Extract text from HTML
        $text = strip_tags( $html );
        
        // Extract images
        $images = $this->extract_images( $html, $url );
        
        // Close the client
        $client->quit();
        
        return [
            'title' => $title,
            'html' => $html,
            'text' => $text,
            'images' => $images,
            'url' => $url,
            'extraction_method' => 'panther',
        ];
    }

    /**
     * Extract images from HTML content.
     *
     * @param string $html The HTML content.
     * @param string $url The base URL.
     * @return array Array of image information.
     */
    private function extract_images( $html, $url ) {
        $images = [];
        
        // Create a DOM document
        $dom = new \DOMDocument();
        
        // Suppress errors for malformed HTML
        libxml_use_internal_errors( true );
        $dom->loadHTML( $html );
        libxml_clear_errors();
        
        // Find all img tags
        $img_tags = $dom->getElementsByTagName( 'img' );
        
        foreach ( $img_tags as $img ) {
            $src = $img->getAttribute( 'src' );
            
            // Skip if no src attribute
            if ( empty( $src ) ) {
                continue;
            }
            
            // Make src absolute if it's relative
            if ( strpos( $src, 'http' ) !== 0 ) {
                $src = $this->make_absolute_url( $src, $url );
            }
            
            // Get alt text
            $alt = $img->getAttribute( 'alt' );
            
            // Get image dimensions
            $width = $img->getAttribute( 'width' );
            $height = $img->getAttribute( 'height' );
            
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
    private function make_absolute_url( $rel_url, $base_url ) {
        // Parse the base URL
        $parsed_url = parse_url( $base_url );
        
        // If the relative URL starts with //, it's a protocol-relative URL
        if ( strpos( $rel_url, '//' ) === 0 ) {
            return $parsed_url['scheme'] . ':' . $rel_url;
        }
        
        // If the relative URL starts with /, it's relative to the root
        if ( strpos( $rel_url, '/' ) === 0 ) {
            return $parsed_url['scheme'] . '://' . $parsed_url['host'] . $rel_url;
        }
        
        // Otherwise, it's relative to the current path
        $path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';
        $path = rtrim( dirname( $path ), '/' ) . '/';
        
        return $parsed_url['scheme'] . '://' . $parsed_url['host'] . $path . $rel_url;
    }

    /**
     * Check if extracted content is valid and meets minimum requirements.
     *
     * @param array $content The extracted content.
     * @return bool Whether the content is valid.
     */
    private function is_content_valid( $content ) {
        // Check if content is an array with required keys
        if ( ! is_array( $content ) || empty( $content['html'] ) || empty( $content['title'] ) ) {
            return false;
        }
        
        // Check minimum content length
        $min_length = $this->settings->get( 'min_content_length', 200 );
        if ( strlen( $content['text'] ) < $min_length ) {
            return false;
        }
        
        // Check minimum image count if required
        $min_images = $this->settings->get( 'min_image_count', 0 );
        if ( $min_images > 0 && count( $content['images'] ) < $min_images ) {
            return false;
        }
        
        return true;
    }
} 