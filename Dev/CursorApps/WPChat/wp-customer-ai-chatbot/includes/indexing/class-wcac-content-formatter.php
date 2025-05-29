<?php
// Ensure required WordPress and WooCommerce functions are available
if (!function_exists('get_term_by')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (!function_exists('wc_attribute_label')) {
    if (defined('WC_ABSPATH')) {
        if (file_exists(WC_ABSPATH . 'includes/wc-attribute-functions.php')) {
            require_once WC_ABSPATH . 'includes/wc-attribute-functions.php';
        }
    }
}
if (!function_exists('wp_get_post_terms')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
}
if (!function_exists('get_the_terms')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
if (!function_exists('wp_list_pluck')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('get_permalink')) {
    require_once ABSPATH . 'wp-includes/link-template.php';
}
if (!function_exists('get_the_category')) {
    require_once ABSPATH . 'wp-includes/category-template.php';
}
if (!function_exists('get_the_tags')) {
    require_once ABSPATH . 'wp-includes/category-template.php';
}
if (!class_exists('WP_Post')) {
    require_once ABSPATH . 'wp-includes/class-wp-post.php';
}

class Wcac_ContentFormatter {
    public static function format(WP_Post $post): ?array {
        $type = isset($post->post_type) ? $post->post_type : (property_exists($post, 'post_type') ? $post->post_type : '');
        if ($type !== 'product' && $type !== 'product_variation') {
            return self::format_page_or_post($post);
        }

        $post_id = isset($post->ID) ? $post->ID : (property_exists($post, 'ID') ? $post->ID : 0);
        $parent_id = isset($post->post_parent) ? $post->post_parent : (property_exists($post, 'post_parent') ? $post->post_parent : 0);

        $product = wc_get_product($post_id);
        if (!$product) {
            error_log("WCAC ContentFormatter: Could not get product object for ID {$post_id}.");
            return null;
        }

        $product_type = $product->get_type();
        $categories = [];
        if (function_exists('wp_get_post_terms')) {
            $cat_ids = wp_get_post_terms($post_id, 'product_cat', ['fields' => 'ids']);
            $categories = self::get_all_category_names($cat_ids, $post_id, 'product_cat', true);
        }
        $tags = [];
        if (function_exists('wp_list_pluck') && function_exists('get_the_terms')) {
            $tags = wp_list_pluck(get_the_terms($post_id, 'product_tag') ?: [], 'name');
        }
        $fields = [
            'title' => $product->get_name(),
            'type' => $type,
            'product_type' => $product_type,
            'url' => $product->get_permalink(),
            'categories' => $categories,
            'tags' => $tags,
            'average_rating' => method_exists($product, 'get_average_rating') ? $product->get_average_rating() : null,
            'comment_count' => method_exists($product, 'get_review_count') ? $product->get_review_count() : null,
            'stock_status' => method_exists($product, 'get_stock_status') ? $product->get_stock_status() : null,
            'parent_id' => $parent_id ?: null,
            'attributes_text' => '',
        ];

        // Inline type-specific hooks
        $type_hook = 'wcac_format_product_type_' . $product_type;
        if (function_exists($type_hook)) {
            $fields = $type_hook($fields, $product, $post);
        } else {
            switch ($product_type) {
                case 'simple':
                case 'variable':
                    $fields['regular_price'] = $product->get_regular_price();
                    $fields['sale_price'] = $product->get_sale_price();
                    $fields['on_sale'] = $product->is_on_sale();
                    $fields['content_text'] = $product->get_short_description() ?: $product->get_description();
                    break;
                case 'variation':
                    $fields['regular_price'] = $product->get_regular_price();
                    $fields['sale_price'] = $product->get_sale_price();
                    $fields['on_sale'] = $product->is_on_sale();
                    $fields['content_text'] = $product->get_description();
                    $fields['attributes_text'] = self::attributes_to_text($product->get_attributes());
                    break;
                case 'grouped':
                    $fields['child_ids'] = $product->get_children();
                    $fields['content_text'] = $product->get_description();
                    break;
                default:
                    $fields['content_text'] = $product->get_description();
                    break;
            }
        }

        return self::assemble_output($fields);
    }

    private static function format_variation(WP_Post $post): ?array {
        $post_id = isset($post->ID) ? $post->ID : 0;
        $parent_id = isset($post->post_parent) ? $post->post_parent : 0;
        $variation = wc_get_product($post_id);
        $parent_product = wc_get_product($parent_id);
        if (!$variation || !$parent_product) {
            error_log("WCAC ContentFormatter: Could not get variation or parent product object for variation ID {$post_id}.");
            return null;
        }
        $title = $parent_product->get_name();
        $regular_price = $variation->get_regular_price();
        $sale_price = $variation->get_sale_price();
        $on_sale = $variation->is_on_sale();
        $variation_attributes_raw = $variation->get_variation_attributes(false);
        $attribute_strings = [];
        $attributes = [];
        if (is_array($variation_attributes_raw)) {
            foreach ($variation_attributes_raw as $taxonomy => $term_slug) {
                if (empty($term_slug)) continue;
                $term = function_exists('get_term_by') ? get_term_by('slug', $term_slug, $taxonomy) : null;
                $term_name = $term ? $term->name : $term_slug;
                $attribute_label = function_exists('wc_attribute_label') ? wc_attribute_label($taxonomy, $parent_product) : $taxonomy;
                $attribute_strings[] = $attribute_label . ': ' . $term_name;
                $title .= ' - ' . $term_name;
                $attributes[$attribute_label] = $term_slug;
            }
        }
        $raw_content = $variation->get_description();
        if (empty(trim($raw_content))) {
            $raw_content = $parent_product->get_short_description();
            if (empty(trim($raw_content))) {
                $raw_content = $parent_product->get_description();
            }
        }
        $parent_term_ids = function_exists('wp_get_post_terms') ? wp_get_post_terms($parent_id, 'product_cat', ['fields' => 'ids']) : [];
        $categories = self::get_all_category_names($parent_term_ids, $parent_id, 'product_cat', true);
        $tag_terms = function_exists('get_the_terms') ? get_the_terms($parent_id, 'product_tag') : [];
        $tags = (!empty($tag_terms) && !is_wp_error($tag_terms) && function_exists('wp_list_pluck')) ? wp_list_pluck($tag_terms, 'name') : [];
        $url = $variation->get_permalink();
        if (empty($url)) {
            error_log("WCAC ContentFormatter: Failed to get permalink for variation ID {$post_id}, falling back to parent URL.");
            $url = $parent_product->get_permalink();
        }
        $average_rating = method_exists($variation, 'get_average_rating') ? $variation->get_average_rating() : null;
        $comment_count = method_exists($variation, 'get_review_count') ? $variation->get_review_count() : null;
        $main_content_limited = self::extract_main_content($raw_content);
        $urls_string = $main_content_limited['urls_string'];
        $main_content = $main_content_limited['main_content'];
        $recommended_for = self::get_recommendations($main_content);
        $attributes_text = self::attributes_to_text($attributes);
        $stock_status = method_exists($variation, 'get_stock_status') ? $variation->get_stock_status() : null;
        $post_modified = isset($post->post_modified) ? $post->post_modified : (property_exists($post, 'post_modified') ? $post->post_modified : null);
        return self::assemble_output([
            'title' => $title,
            'type' => 'product_variation',
            'url' => $url,
            'content_text' => $main_content,
            'categories' => $categories,
            'tags' => $tags,
            'recommended_for' => $recommended_for,
            'attributes_text' => $attributes_text,
            'regular_price' => $regular_price,
            'sale_price' => $sale_price,
            'on_sale' => $on_sale,
            'parent_id' => $parent_id > 0 ? $parent_id : null,
            'stock_status' => $stock_status,
            'post_modified' => $post_modified,
            'average_rating' => $average_rating,
            'comment_count' => $comment_count,
            'urls_string' => $urls_string,
        ]);
    }

    private static function format_product(WP_Post $post): ?array {
        $post_id = isset($post->ID) ? $post->ID : 0;
        $product = wc_get_product($post_id);
        if (!$product) {
            error_log("WCAC ContentFormatter: Could not get product object for product ID {$post_id}.");
            return null;
        }
        $title = $product->get_name();
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $on_sale = $product->is_on_sale();
        $raw_content = $product->get_short_description();
        if (empty(trim($raw_content))) {
            $raw_content = $product->get_description();
        }
        $term_ids = function_exists('wp_get_post_terms') ? wp_get_post_terms($post_id, 'product_cat', ['fields' => 'ids']) : [];
        $categories = self::get_all_category_names($term_ids, $post_id, 'product_cat', true);
        $tag_terms = function_exists('get_the_terms') ? get_the_terms($post_id, 'product_tag') : [];
        $tags = (!empty($tag_terms) && !is_wp_error($tag_terms) && function_exists('wp_list_pluck')) ? wp_list_pluck($tag_terms, 'name') : [];
        $url = $product->get_permalink();
        $average_rating = method_exists($product, 'get_average_rating') ? $product->get_average_rating() : null;
        $comment_count = method_exists($product, 'get_review_count') ? $product->get_review_count() : null;
        $main_content_limited = self::extract_main_content($raw_content);
        $urls_string = $main_content_limited['urls_string'];
        $main_content = $main_content_limited['main_content'];
        $recommended_for = self::get_recommendations($main_content);
        $attributes_text = '';
        $stock_status = method_exists($product, 'get_stock_status') ? $product->get_stock_status() : null;
        $post_modified = isset($post->post_modified) ? $post->post_modified : (property_exists($post, 'post_modified') ? $post->post_modified : null);
        $parent_id = isset($post->post_parent) ? $post->post_parent : (property_exists($post, 'post_parent') ? $post->post_parent : null);
        return self::assemble_output([
            'title' => $title,
            'type' => 'product',
            'url' => $url,
            'content_text' => $main_content,
            'categories' => $categories,
            'tags' => $tags,
            'recommended_for' => $recommended_for,
            'attributes_text' => $attributes_text,
            'regular_price' => $regular_price,
            'sale_price' => $sale_price,
            'on_sale' => $on_sale,
            'parent_id' => $parent_id > 0 ? $parent_id : null,
            'stock_status' => $stock_status,
            'post_modified' => $post_modified,
            'average_rating' => $average_rating,
            'comment_count' => $comment_count,
            'urls_string' => $urls_string,
        ]);
    }

    private static function format_page_or_post(WP_Post $post): ?array {
        $post_id = isset($post->ID) ? $post->ID : 0;
        $title = isset($post->post_title) ? $post->post_title : '';
        $content_type = isset($post->post_type) ? $post->post_type : '';
        $raw_content = isset($post->post_excerpt) ? $post->post_excerpt : '';
        if (empty(trim($raw_content)) && isset($post->post_content)) {
            $raw_content = $post->post_content;
        }
        $url = function_exists('get_permalink') ? get_permalink($post_id) : null;
        $categories = [];
        $tags = [];
        if ($content_type === 'post') {
            $post_categories = function_exists('get_the_category') ? get_the_category($post_id) : [];
            $cat_ids = (!empty($post_categories) && !is_wp_error($post_categories) && function_exists('wp_list_pluck')) ? wp_list_pluck($post_categories, 'term_id') : [];
            $categories = self::get_all_category_names($cat_ids, $post_id, 'category', true);
            $post_tags = function_exists('get_the_tags') ? get_the_tags($post_id) : [];
            $tags = (!empty($post_tags) && !is_wp_error($post_tags) && function_exists('wp_list_pluck')) ? wp_list_pluck($post_tags, 'name') : [];
        }
        $main_content_limited = self::extract_main_content($raw_content);
        $urls_string = $main_content_limited['urls_string'];
        $main_content = $main_content_limited['main_content'];
        $recommended_for = self::get_recommendations($main_content);
        $post_modified = isset($post->post_modified) ? $post->post_modified : (property_exists($post, 'post_modified') ? $post->post_modified : null);
        $parent_id = isset($post->post_parent) ? $post->post_parent : (property_exists($post, 'post_parent') ? $post->post_parent : null);
        return self::assemble_output([
            'title' => $title,
            'type' => $content_type,
            'url' => $url,
            'content_text' => $main_content,
            'categories' => $categories,
            'tags' => $tags,
            'recommended_for' => $recommended_for,
            'attributes_text' => '',
            'regular_price' => null,
            'sale_price' => null,
            'on_sale' => false,
            'parent_id' => $parent_id > 0 ? $parent_id : null,
            'stock_status' => null,
            'post_modified' => $post_modified,
            'average_rating' => null,
            'comment_count' => null,
            'urls_string' => $urls_string,
        ]);
    }

    // Shared helpers
    private static function get_all_category_names(array $term_ids, int $post_id, string $taxonomy = 'product_cat', bool $include_parents = true): array {
        $all_category_names = [];
        foreach ($term_ids as $term_id) {
            $term = function_exists('get_term') ? get_term($term_id, $taxonomy) : null;
            if ($term && !is_wp_error($term)) {
                $direct_cat_name = $term->name;
                $all_category_names[] = $direct_cat_name;
                if ($include_parents) {
                    $parent_id_term = $term->parent;
                    while ($parent_id_term != 0) {
                        $parent_term = function_exists('get_term') ? get_term($parent_id_term, $taxonomy) : null;
                        if ($parent_term && !is_wp_error($parent_term)) {
                            $parent_name = $parent_term->name;
                            $all_category_names[] = $parent_name;
                            $parent_id_term = $parent_term->parent;
                        } else {
                            break;
                        }
                    }
                }
            }
        }
        return array_unique($all_category_names);
    }

    private static function extract_main_content($raw_content): array {
        if (!empty($raw_content)) {
            list($cleaned_text, $url_list) = self::extract_text_and_urls_from_html($raw_content);
            $main_content = $cleaned_text;
            $urls_string = !empty($url_list) ? 'URLs: ' . implode(' ', $url_list) : '';
        } else {
            $main_content = '';
            $urls_string = '';
        }
        return [
            'main_content' => $main_content,
            'urls_string' => $urls_string,
        ];
    }

    private static function get_recommendations($main_content) {
        require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-recommendation-miner.php';
        $phrase_map = [
            'socks' => ['recommended for socks', 'sock yarn', 'good for socks', 'suitable for socks'],
            'summer' => ['summer', 'hot weather', 'warm weather'],
            'beginner' => ['beginner', 'easy', 'simple pattern'],
        ];
        return class_exists('Wcac_Recommendation_Miner') ? Wcac_Recommendation_Miner::mine_recommendations($main_content, $phrase_map) : null;
    }

    private static function attributes_to_text($attributes): string {
        if (empty($attributes)) return '';
        $attr_pairs = [];
        foreach ($attributes as $label => $slug) {
            $attr_pairs[] = $label . ': ' . $slug;
        }
        return implode(', ', $attr_pairs);
    }

    private static function assemble_output(array $data): array {
        // Compose the output array, encoding categories/tags as JSON if needed
        return [
            'title' => $data['title'],
            'type' => $data['type'],
            'url' => $data['url'],
            'content_text' => $data['content_text'],
            'categories' => !empty($data['categories']) ? json_encode($data['categories']) : null,
            'tags' => !empty($data['tags']) ? json_encode($data['tags']) : null,
            'recommended_for' => $data['recommended_for'],
            'attributes_text' => $data['attributes_text'],
            'regular_price' => $data['regular_price'],
            'sale_price' => $data['sale_price'],
            'on_sale' => $data['on_sale'],
            'parent_id' => $data['parent_id'],
            'stock_status' => $data['stock_status'],
            'post_modified' => $data['post_modified'],
            'average_rating' => $data['average_rating'],
            'comment_count' => $data['comment_count'],
            // Optionally include URLs string for debugging or future use
            // 'urls_string' => $data['urls_string'],
        ];
    }

    private static function extract_text_and_urls_from_html($html) {
        if (empty($html) || !class_exists('DOMDocument')) {
            return ['', []];
        }
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($doc);
        foreach (['script', 'style'] as $tag) {
            foreach ($doc->getElementsByTagName($tag) as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        $nodes = $xpath->query('//body//*[not(self::script or self::style)]/text()');
        $text = '';
        foreach ($nodes as $node) {
            $text .= ' ' . $node->nodeValue;
        }
        $urls = [];
        foreach (['a' => 'href', 'img' => 'src'] as $tag => $attr) {
            foreach ($doc->getElementsByTagName($tag) as $el) {
                $url = $el->getAttribute($attr);
                if (!empty($url)) {
                    $urls[] = $url;
                }
            }
        }
        libxml_clear_errors();
        return [trim(preg_replace('/\s+/s', ' ', $text)), array_unique($urls)];
    }
}

// Example: Add support for a custom product type via a callback
if (!function_exists('wcac_format_product_type_customtype')) {
    function wcac_format_product_type_customtype($fields, $product, $post) {
        $fields['custom_field'] = $product->get_meta('custom_field');
        return $fields;
    }
} 