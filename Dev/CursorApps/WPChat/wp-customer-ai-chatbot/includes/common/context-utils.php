<?php
/**
 * Context formatting utility for RAG/LLM context construction.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/includes/common
 */
if (!function_exists('wcac_format_context_item')) {
    /**
     * Format a context item (product, page, etc.) for LLM context, using smart truncation and keyword inclusion.
     *
     * @param array $item The item to format (expects keys: title/name, content/content_snippet, type, id, etc.)
     * @param string $compression_algorithm Compression algorithm to use: 'title', 'category', or 'none'
     * @return string Formatted context string.
     */
    function wcac_format_context_item(array $item, string $compression_algorithm = 'none'): string
    {
        $settings = get_option('wcac_settings', []);
        $char_limit = isset($settings['wcac_context_char_limit']) && is_numeric($settings['wcac_context_char_limit'])
            ? (int)$settings['wcac_context_char_limit']
            : 500;
        $fields = [];
        $title = $item['title'] ?? $item['name'] ?? 'Unknown';
        $url = $item['url'] ?? '';
        if ($compression_algorithm === 'title') {
            $fields[] = 'Title: ' . $title;
            if ($url) $fields[] = 'URL: ' . $url;
            return implode("\n", $fields);
        }
        if ($compression_algorithm === 'category') {
            $fields[] = 'Title: ' . $title;
            if (!empty($item['categories'])) {
                $categories = is_array($item['categories']) ? $item['categories'] : json_decode($item['categories'], true) ?? [];
                if (!empty($categories)) {
                    $fields[] = 'Category: ' . $categories[0];
                }
            }
            if ($url) $fields[] = 'URL: ' . $url;
            return implode("\n", $fields);
        }
        // Default: all fields (legacy behavior)
        $keywords = ['yardage', 'meter', 'gauge', 'weight', 'dimension', 'length'];
        $fields[] = 'Title: ' . $title;
        $is_product = isset($item['type']) && $item['type'] === 'product';
        if ($is_product && function_exists('wc_get_product')) {
            $product = wc_get_product($item['id']);
            if ($product) {
                $price = wp_strip_all_tags($product->get_price_html());
                if ($price) {
                    $fields[] = 'Price: ' . $price;
                }
                $stock_status = $product->is_in_stock() ? 'In Stock' : 'Out of Stock';
                $fields[] = 'Stock Status: ' . $stock_status;
                if ($product->is_on_sale()) {
                    $fields[] = 'On Sale: Yes';
                }
            }
        }
        $content_value = $item['content'] ?? ($item['content_snippet'] ?? '');
        if (!empty($content_value)) {
            if (!class_exists('Wcac_Context_Formatter')) {
                require_once __DIR__ . '/class-wcac-context-formatter.php';
            }
            $desc = Wcac_Context_Formatter::truncate_with_keywords($content_value, $char_limit, $keywords);
            $fields[] = 'Description: ' . $desc;
        }
        $categories_value = $item['categories'] ?? [];
        if (is_string($categories_value)) {
            $categories_value = json_decode($categories_value, true) ?? [];
        }
        if (!empty($categories_value) && is_array($categories_value)) {
            $fields[] = 'Categories: ' . implode(', ', $categories_value);
        }
        $tags_value = $item['tags'] ?? [];
        if (is_string($tags_value)) {
            $tags_value = json_decode($tags_value, true) ?? [];
        }
        if (!empty($tags_value) && is_array($tags_value)) {
            $fields[] = 'Tags: ' . implode(', ', $tags_value);
        }
        if ($url) $fields[] = 'URL: ' . $url;
        return implode("\n", $fields);
    }
} 