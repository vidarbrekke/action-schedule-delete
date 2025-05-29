<?php
/**
 * Content Optimizer class.
 *
 * @package UTG
 */

namespace UTG\Generator;

use \DOMDocument;
use \DOMXPath;
use \DOMElement;
use UTG\Settings;

/**
 * Content Optimizer Class
 * 
 * This class is responsible for optimizing HTML content before sending it to the LLM,
 * reducing token consumption while preserving essential content structure.
 */
class UTG_Content_Optimizer {
    
    /**
     * Settings instance
     *
     * @var UTG_Settings
     */
    private $settings;
    
    /**
     * Constructor
     *
     * @param Settings $settings Settings instance.
     */
    public function __construct( Settings $settings ) {
        $this->settings = $settings;
    }
    
    /**
     * Optimize HTML content for LLM processing to reduce token usage.
     * 
     * @param string $html The HTML content to optimize
     * @param string $source_url The source URL for context
     * @return string The optimized HTML
     */
    public function optimize_for_llm( $html, $source_url ) {
        if ( empty( $html ) ) {
            return $html;
        }
        
        // Log original content size if in debug mode
        if ( $this->settings->get( 'debug_mode' ) ) {
            error_log( sprintf( '[URL to Gutenberg] Content Optimizer: Original HTML size: %d bytes', strlen( $html ) ) );
        }
        
        // Parse the HTML as DOM
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        if ( ! $dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
            // If DOM parsing fails, fall back to regex-based optimization
            libxml_clear_errors();
            $optimized_html = $this->optimize_using_regex( $html );
            
            if ( $this->settings->get( 'debug_mode' ) ) {
                error_log( sprintf( '[URL to Gutenberg] Content Optimizer: DOM parsing failed, using regex. New size: %d bytes', strlen( $optimized_html ) ) );
            }
            
            return $optimized_html;
        }
        libxml_clear_errors();
        
        $xpath = new DOMXPath( $dom );
        
        // 1. Remove all div wrapper elements that don't add semantic value
        $this->remove_redundant_wrappers( $dom, $xpath );
        
        // 2. Simplify class and ID attributes (removing most)
        $this->simplify_attributes( $dom, $xpath );
        
        // 3. Remove empty elements
        $this->remove_empty_elements( $dom, $xpath );
        
        // 4. Convert complex structures to simpler ones
        $this->simplify_content_structure( $dom, $xpath );
        
        // 5. Compress whitespace and newlines
        $this->compress_whitespace( $dom );
        
        // Get the optimized HTML
        $optimized_html = $dom->saveHTML();
        
        // Final regex-based cleaning
        $optimized_html = $this->final_regex_cleanup( $optimized_html );
        
        // Log the optimization results if in debug mode
        if ( $this->settings->get( 'debug_mode' ) ) {
            $original_size = strlen( $html );
            $optimized_size = strlen( $optimized_html );
            $reduction = $original_size > 0 ? ( ( $original_size - $optimized_size ) / $original_size ) * 100 : 0;
            
            error_log( sprintf( 
                '[URL to Gutenberg] Content Optimizer: Optimized HTML size: %d bytes (reduced by %.1f%%)',
                $optimized_size,
                $reduction
            ) );
        }
        
        return $optimized_html;
    }
    
    /**
     * Remove redundant wrapper elements that don't add semantic value
     * 
     * @param DOMDocument $dom The DOM document
     * @param DOMXPath $xpath The XPath object
     */
    private function remove_redundant_wrappers( $dom, $xpath ) {
        // Find div elements without semantic classes
        $non_semantic_divs = $xpath->query(
            '//div[not(contains(@class, "content")) and not(contains(@class, "article")) and not(contains(@class, "post")) and not(contains(@id, "content")) and not(contains(@id, "article")) and not(contains(@id, "post"))]'
        );
        
        if ( $non_semantic_divs ) {
            foreach ( $non_semantic_divs as $div ) {
                // Skip if the div contains complex nested content
                if ( $xpath->query( './/div | .//section | .//article', $div )->length > 0 ) {
                    continue;
                }
                
                // Unwrap the div (replace with its contents)
                $parent = $div->parentNode;
                if ( $parent ) {
                    while ( $div->firstChild ) {
                        $parent->insertBefore( $div->firstChild, $div );
                    }
                    $parent->removeChild( $div );
                }
            }
        }
    }
    
    /**
     * Simplify attributes by removing non-essential classes and IDs
     * 
     * @param DOMDocument $dom The DOM document
     * @param DOMXPath $xpath The XPath object
     */
    private function simplify_attributes( $dom, $xpath ) {
        // Remove most class and ID attributes that aren't semantically meaningful
        $elements_with_attrs = $xpath->query( '//*[@class or @id or @style]' );
        
        if ( $elements_with_attrs ) {
            foreach ( $elements_with_attrs as $element ) {
                // Skip if not a DOM element
                if (!($element instanceof DOMElement)) {
                    continue;
                }
                
                // Preserve certain semantic attributes
                $node_name = $element->nodeName;
                $class = $element->hasAttribute('class') ? $element->getAttribute( 'class' ) : '';
                $id = $element->hasAttribute('id') ? $element->getAttribute( 'id' ) : '';
                
                // Keep classes for semantic elements and those with content/article/post
                $keep_class = false;
                if ( in_array( $node_name, ['article', 'main', 'section', 'header', 'footer'] ) ) {
                    $keep_class = true;
                } elseif ( preg_match( '/(content|article|post|main)/i', $class ) ) {
                    $keep_class = true;
                }
                
                // Keep IDs for semantic content
                $keep_id = preg_match( '/(content|article|post|main)/i', $id );
                
                // Remove attributes based on conditions
                if ( !$keep_class && $element->hasAttribute('class') ) {
                    $element->removeAttribute( 'class' );
                }
                if ( !$keep_id && $element->hasAttribute('id') ) {
                    $element->removeAttribute( 'id' );
                }
                
                // Always remove style
                if ( $element->hasAttribute('style') ) {
                    $element->removeAttribute( 'style' );
                }
            }
        }
        
        // Remove other non-essential attributes
        $non_essential_attrs = ['data-', 'aria-', 'role', 'tabindex', 'onclick', 'onload', 'onmouseover'];
        foreach ( $non_essential_attrs as $attr_prefix ) {
            $attr_query = $attr_prefix;
            if ( substr( $attr_prefix, -1 ) === '-' ) {
                $attr_query = 'starts-with(name(), "' . substr( $attr_prefix, 0, -1 ) . '")';
            } else {
                $attr_query = 'name()="' . $attr_prefix . '"';
            }
            
            $elements = $xpath->query( '//*[@*[' . $attr_query . ']]' );
            if ( $elements ) {
                foreach ( $elements as $element ) {
                    $attrs_to_remove = [];
                    foreach ( $element->attributes as $attr ) {
                        if ( substr( $attr_prefix, -1 ) === '-' && strpos( $attr->name, substr( $attr_prefix, 0, -1 ) ) === 0 ) {
                            $attrs_to_remove[] = $attr->name;
                        } elseif ( $attr->name === $attr_prefix ) {
                            $attrs_to_remove[] = $attr->name;
                        }
                    }
                    foreach ( $attrs_to_remove as $attr_name ) {
                        if ($element instanceof DOMElement && $element->hasAttribute($attr_name)) {
                            $element->removeAttribute( $attr_name );
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Remove empty elements
     * 
     * @param DOMDocument $dom The DOM document
     * @param DOMXPath $xpath The XPath object
     */
    private function remove_empty_elements( $dom, $xpath ) {
        // Remove elements with no text content (excluding img, br, hr)
        $empty_elements = $xpath->query( '//*[not(self::img) and not(self::br) and not(self::hr) and not(normalize-space(.))]' );
        
        if ( $empty_elements ) {
            $nodes_to_remove = [];
            foreach ( $empty_elements as $element ) {
                // Check if it has no child elements
                if ( $element->childNodes->length === 0 ) {
                    $nodes_to_remove[] = $element;
                }
            }
            
            // Remove the empty nodes
            foreach ( $nodes_to_remove as $node ) {
                if ( $node->parentNode ) {
                    $node->parentNode->removeChild( $node );
                }
            }
        }
    }
    
    /**
     * Simplify content structure by converting complex HTML to simpler forms
     * 
     * @param DOMDocument $dom The DOM document
     * @param DOMXPath $xpath The XPath object
     */
    private function simplify_content_structure( $dom, $xpath ) {
        // Simplify table structures that aren't data tables
        $tables = $xpath->query( '//table[not(.//th)]' );
        if ( $tables ) {
            foreach ( $tables as $table ) {
                // Check if this looks like a layout table rather than a data table
                if ( $xpath->query( './/td[.//div or .//p or .//h1 or .//h2 or .//h3]', $table )->length > 0 ) {
                    // Replace table with a div containing its content
                    $replacement = $dom->createElement( 'div' );
                    $replacement->setAttribute( 'class', 'table-content' );
                    
                    // Move content from table cells
                    $cells = $xpath->query( './/td', $table );
                    foreach ( $cells as $cell ) {
                        // Only add cells with actual content
                        if ( trim( $cell->textContent ) ) {
                            while ( $cell->firstChild ) {
                                $replacement->appendChild( $cell->firstChild );
                            }
                            // Add a spacing element between cells
                            $replacement->appendChild( $dom->createElement( 'br' ) );
                        }
                    }
                    
                    // Replace the table with our simplified div
                    if ( $table->parentNode ) {
                        $table->parentNode->replaceChild( $replacement, $table );
                    }
                }
            }
        }
        
        // Convert complex lists to simpler ones
        $lists = $xpath->query( '//ul | //ol' );
        if ( $lists ) {
            foreach ( $lists as $list ) {
                // Check if it's a complex list with nested elements
                $complex_items = $xpath->query( './/li[.//div or .//p or .//ul or .//ol]', $list );
                if ( $complex_items && $complex_items->length > 0 ) {
                    // Simplify each complex list item
                    foreach ( $complex_items as $item ) {
                        // Extract the text content
                        $text = trim( $item->textContent );
                        
                        // Clear the item
                        while ( $item->firstChild ) {
                            $item->removeChild( $item->firstChild );
                        }
                        
                        // Set text content directly
                        $item->textContent = $text;
                    }
                }
            }
        }
    }
    
    /**
     * Compress whitespace in the DOM
     * 
     * @param DOMDocument $dom The DOM document
     */
    private function compress_whitespace( $dom ) {
        // This is done on the final output using regex
    }
    
    /**
     * Optimize HTML using regex when DOM parsing fails
     * 
     * @param string $html The HTML to optimize
     * @return string The optimized HTML
     */
    private function optimize_using_regex( $html ) {
        // Remove scripts, styles, and comments
        $html = preg_replace( '/<script\b[^>]*>(.*?)<\/script>/is', '', $html );
        $html = preg_replace( '/<style\b[^>]*>(.*?)<\/style>/is', '', $html );
        $html = preg_replace( '/<!--(.*?)-->/s', '', $html );
        
        // Remove most attributes except for essential ones
        $html = preg_replace( '/ (class|id|style|data-[^=]+|aria-[^=]+|role|tabindex|onclick|onload)="[^"]*"/i', '', $html );
        
        // Simplify complex divs that likely act as wrappers
        $html = preg_replace( '/<div(?![^>]*?(?:id|class)\s*=\s*"[^"]*(?:content|article|post|main)[^"]*")[^>]*>(.*?)<\/div>/is', '$1', $html );
        
        // Compress whitespace
        $html = preg_replace( '/\s+/', ' ', $html );
        $html = preg_replace( '/>\s+</', '><', $html );
        $html = trim( $html );
        
        return $html;
    }
    
    /**
     * Perform final regex-based cleanup on the output HTML
     * 
     * @param string $html The HTML to clean
     * @return string The cleaned HTML
     */
    private function final_regex_cleanup( $html ) {
        // Remove excessive whitespace and newlines
        $html = preg_replace( '/\s+/', ' ', $html );
        $html = preg_replace( '/>\s+</', '><', $html );
        
        // Remove empty paragraphs
        $html = preg_replace( '/<p>\s*<\/p>/i', '', $html );
        
        // Remove redundant line breaks
        $html = preg_replace( '/<br\s*\/?>\s*<br\s*\/?>/i', '<br/>', $html );
        
        // Simplify inline elements that are often overused
        $html = preg_replace( '/<span[^>]*>(.*?)<\/span>/i', '$1', $html );
        
        // Simplify common wordpress utility classes
        $html = preg_replace( '/ class="wp-[^"]*"/i', '', $html );
        
        // Trim the final output
        $html = trim( $html );
        
        return $html;
    }
    
    /**
     * Extract text-only version of the content for ultra-low token usage
     * 
     * @param string $html The HTML content
     * @return string Text-only content with minimal formatting
     */
    public function extract_text_only( $html ) {
        // Parse the HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        if ( ! $dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
            // If DOM parsing fails, fall back to regex
            libxml_clear_errors();
            return $this->extract_text_with_regex( $html );
        }
        libxml_clear_errors();
        
        $xpath = new DOMXPath( $dom );
        
        // Extract content with simplified structure
        $text_content = '';
        
        // Process headings
        foreach ( ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $heading_tag ) {
            $headings = $xpath->query( "//{$heading_tag}" );
            if ( $headings ) {
                foreach ( $headings as $heading ) {
                    $text = trim( $heading->textContent );
                    if ( !empty( $text ) ) {
                        $level = (int) substr( $heading_tag, 1, 1 );
                        $prefix = str_repeat( '#', $level ) . ' ';
                        $text_content .= "{$prefix}{$text}\n\n";
                    }
                }
            }
        }
        
        // Process paragraphs
        $paragraphs = $xpath->query( '//p' );
        if ( $paragraphs ) {
            foreach ( $paragraphs as $p ) {
                $text = trim( $p->textContent );
                if ( !empty( $text ) ) {
                    $text_content .= "{$text}\n\n";
                }
            }
        }
        
        // Process lists
        $lists = $xpath->query( '//ul | //ol' );
        if ( $lists ) {
            foreach ( $lists as $list ) {
                $text_content .= "\n";
                $items = $xpath->query( './/li', $list );
                $is_ordered = $list->nodeName === 'ol';
                $item_number = 1;
                
                if ( $items ) {
                    foreach ( $items as $item ) {
                        $text = trim( $item->textContent );
                        if ( !empty( $text ) ) {
                            if ( $is_ordered ) {
                                $text_content .= "{$item_number}. {$text}\n";
                                $item_number++;
                            } else {
                                $text_content .= "- {$text}\n";
                            }
                        }
                    }
                }
                $text_content .= "\n";
            }
        }
        
        // Process images with alt text
        $images = $xpath->query( '//img[@alt]' );
        if ( $images ) {
            foreach ( $images as $img ) {
                // Check if it's a DOM element before using getAttribute
                if ($img instanceof DOMElement && $img->hasAttribute('alt')) {
                    $alt = $img->getAttribute( 'alt' );
                    if ( !empty( $alt ) ) {
                        $text_content .= "[Image: {$alt}]\n\n";
                    }
                }
            }
        }
        
        // Process tables
        $tables = $xpath->query( '//table' );
        if ( $tables ) {
            foreach ( $tables as $table ) {
                $text_content .= "[Table]\n";
                $rows = $xpath->query( './/tr', $table );
                if ( $rows ) {
                    foreach ( $rows as $row ) {
                        $cells = $xpath->query( './/td | .//th', $row );
                        $row_text = '';
                        if ( $cells ) {
                            foreach ( $cells as $cell ) {
                                $cell_text = trim( $cell->textContent );
                                $row_text .= "{$cell_text} | ";
                            }
                        }
                        if ( !empty( $row_text ) ) {
                            $text_content .= rtrim( $row_text, " | " ) . "\n";
                        }
                    }
                }
                $text_content .= "\n";
            }
        }
        
        // Final cleanup
        $text_content = preg_replace( '/\n{3,}/', "\n\n", $text_content );
        $text_content = trim( $text_content );
        
        if ( $this->settings->get( 'debug_mode' ) ) {
            error_log( sprintf( 
                '[URL to Gutenberg] Content Optimizer: Text-only content size: %d bytes',
                strlen( $text_content )
            ) );
        }
        
        return $text_content;
    }
    
    /**
     * Extract text using regex when DOM parsing fails
     * 
     * @param string $html The HTML content
     * @return string Text-only content
     */
    private function extract_text_with_regex( $html ) {
        // Remove all HTML tags but preserve content
        $text = strip_tags( $html );
        
        // Clean up whitespace
        $text = preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );
        
        // Add some basic structure
        $paragraphs = preg_split( '/(?:\. |\? |\! )(?=[A-Z])/', $text );
        $text = implode( ".\n\n", $paragraphs );
        
        return $text;
    }
    
    /**
     * Decide the optimal content format based on size constraints
     * 
     * @param string $html The original extracted HTML
     * @param string $source_url The source URL for context
     * @param int $max_token_target The target maximum token length (approximate)
     * @return string The optimized content in the appropriate format
     */
    public function get_optimized_content( $html, $source_url, $max_token_target = 2000 ) {
        // Approximate token count (rough estimate: 4 chars = 1 token)
        $estimated_tokens = strlen( $html ) / 4;
        
        if ( $this->settings->get( 'debug_mode' ) ) {
            error_log( sprintf( 
                '[URL to Gutenberg] Content Optimizer: Estimated tokens for original content: %d (target: %d)',
                $estimated_tokens,
                $max_token_target
            ) );
        }
        
        // If the content is already under our target, use standard optimization
        if ( $estimated_tokens <= $max_token_target * 1.5 ) {
            return $this->optimize_for_llm( $html, $source_url );
        }
        
        // For larger content, first try aggressive HTML optimization
        $optimized_html = $this->optimize_for_llm( $html, $source_url );
        $estimated_optimized_tokens = strlen( $optimized_html ) / 4;
        
        if ( $estimated_optimized_tokens <= $max_token_target ) {
            if ( $this->settings->get( 'debug_mode' ) ) {
                error_log( sprintf( 
                    '[URL to Gutenberg] Content Optimizer: Using optimized HTML format (%d estimated tokens)',
                    $estimated_optimized_tokens
                ) );
            }
            return $optimized_html;
        }
        
        // If still too large, use text-only format
        $text_only = $this->extract_text_only( $html );
        $estimated_text_tokens = strlen( $text_only ) / 4;
        
        if ( $this->settings->get( 'debug_mode' ) ) {
            error_log( sprintf( 
                '[URL to Gutenberg] Content Optimizer: Using text-only format (%d estimated tokens)',
                $estimated_text_tokens
            ) );
        }
        
        // If even text-only is too large, truncate it with a note
        if ( $estimated_text_tokens > $max_token_target ) {
            $chars_to_keep = $max_token_target * 3.5; // Allow some buffer
            $truncated_text = substr( $text_only, 0, $chars_to_keep );
            $truncated_text .= "\n\n[Content truncated due to length constraints. This is a partial extraction of the full content.]";
            
            if ( $this->settings->get( 'debug_mode' ) ) {
                error_log( sprintf( 
                    '[URL to Gutenberg] Content Optimizer: Content truncated to %d characters',
                    strlen( $truncated_text )
                ) );
            }
            
            return $truncated_text;
        }
        
        return $text_only;
    }
} 