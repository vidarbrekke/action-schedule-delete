<?php
/**
 * Utility class for extracting candidate brands from WooCommerce products.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes/common
 */
class Wcac_BrandExtractor {
    /**
     * Extracts a candidate brand from a WooCommerce product object.
     * Looks for a 'brand' attribute, or tries to parse from title/description.
     * Returns null if no brand found.
     *
     * @param WC_Product $product WooCommerce product object.
     * @return string|null Brand name or null if not found.
     */
    public static function extract_from_product($product) {
        // Try WooCommerce 'brand' attribute (common plugin pattern)
        if (method_exists($product, 'get_attribute')) {
            $brand = $product->get_attribute('brand');
            if (!empty($brand)) {
                return trim($brand);
            }
        }
        // Fallback: Try to parse from title (e.g., "Nike Air Max" => "Nike")
        $title = $product->get_name();
        if (preg_match('/^([A-Z][a-zA-Z0-9\- ]{2,15})\b/', $title, $m)) {
            return trim($m[1]);
        }
        // Fallback: Try to parse from description (very basic)
        $desc = $product->get_description();
        if (preg_match('/Brand[:\s]+([A-Z][a-zA-Z0-9\- ]{2,15})/i', $desc, $m)) {
            return trim($m[1]);
        }
        return null;
    }
} 