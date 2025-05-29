<?php

use PHPUnit\Framework\TestCase;

class TestWcacScoringParameters extends TestCase
{
    /**
     * Data provider for scoring parameters.
     * Each entry: [setter, getter, value1, value2, expected_direction, description]
     */
    public static function parameterProvider()
    {
        return [
            // [setter, getter, value1, value2, expected_direction, description]
            ['set_title_match_weight', 'get_title_match_weight', 10, 100, 'increase', 'Title match weight increases score'],
            ['set_content_match_weight', 'get_content_match_weight', 2, 20, 'increase', 'Content match weight increases score'],
            ['set_category_match_weight', 'get_category_match_weight', 5, 50, 'increase', 'Category match weight increases score'],
            ['set_tag_match_weight', 'get_tag_match_weight', 1, 10, 'increase', 'Tag match weight increases score'],
            ['set_fuzzy_match_boost_medium', 'get_fuzzy_match_boost_medium', 0, 20, 'increase', 'Fuzzy match boost increases score for typos'],
            ['set_parent_product_weight', 'get_parent_product_weight', 10, 100, 'increase', 'Parent product weight increases score'],
            ['set_variation_product_weight', 'get_variation_product_weight', 1, 20, 'increase', 'Variation product weight increases score'],
            ['set_direct_title_match_bonus', 'get_direct_title_match_bonus', 0, 50, 'increase', 'Direct title match bonus increases score'],
            ['set_multi_field_match_bonus', 'get_multi_field_match_bonus', 0, 30, 'increase', 'Multi-field match bonus increases score'],
            ['set_all_keywords_in_title_boost', 'get_all_keywords_in_title_boost', 0, 40, 'increase', 'All keywords in title boost increases score'],
            ['set_title_category_match_boost', 'get_title_category_match_boost', 0, 60, 'increase', 'Title-category match boost increases score'],
            ['set_exact_product_name_boost', 'get_exact_product_name_boost', 0, 80, 'increase', 'Exact product name boost increases score'],
            ['set_menu_match_weight', 'get_menu_match_weight', 0, 25, 'increase', 'Menu match weight increases score'],
            // Add more as needed
        ];
    }

    /**
     * @dataProvider parameterProvider
     */
    public function testParameterAffectsScore($setter, $getter, $value1, $value2, $expected_direction, $description)
    {
        // Controlled test item
        $item = [
            'title' => 'Test Product',
            'content_snippet' => 'This is a test product for scoring.',
            'categories' => ['Test Category'],
            'tags' => ['Test Tag'],
            'on_sale' => true,
            'post_type' => 'product',
            'menu_titles' => ['Test Menu'],
            'category_ids' => [],
            'post_id' => 1,
        ];
        $keywords = ['test', 'product', 'category', 'tag', 'menu'];
        $full_query = 'Test Product';

        // --- Parameter-specific adjustments ---
        if ($setter === 'set_fuzzy_match_boost_medium') {
            // Use a near-miss keyword to trigger fuzzy match (Levenshtein distance 1)
            $item['title'] = 'Product';
            $keywords = ['Prodict']; // 'Prodict' is a typo of 'Product' (distance 1)
        }
        if ($setter === 'set_variation_product_weight') {
            // Set post_type to 'product_variation' to trigger this weight
            $item['post_type'] = 'product_variation';
        }
        if ($setter === 'set_all_keywords_in_title_boost') {
            // Ensure all keywords are present in the title
            $item['title'] = 'Test Product Category Tag Menu';
        }
        if ($setter === 'set_exact_product_name_boost') {
            // Set compound_product_names to include the test title
            Wcac_ChatbotRules::$compound_product_names = ['test product'];
        }
        // ... existing code ...

        // Set parameter to value1
        call_user_func(['Wcac_ChatbotRules', $setter], $value1);
        $score1 = Wcac_ChatbotRules::score_item($item, $keywords, $full_query);

        // Set parameter to value2
        call_user_func(['Wcac_ChatbotRules', $setter], $value2);
        $score2 = Wcac_ChatbotRules::score_item($item, $keywords, $full_query);

        if ($expected_direction === 'increase') {
            $this->assertGreaterThan($score1, $score2, $description);
        } else {
            $this->assertLessThan($score1, $score2, $description);
        }
    }
}
