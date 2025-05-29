<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/retrieval/class-wcac-chatbot-rules.php';

// LinterWarning: PHPUnit context unavailable locally
class TestWcacSettingsApplication extends TestCase
{
    public function setUp(): void
    {
        // LinterWarning: PHPUnit context unavailable locally (parent::setUp)
        parent::setUp();
        // Optionally reset static properties to defaults here if needed
    }

    public function test_settings_are_applied_to_rules()
    {
        // Simulate admin settings - these values are arbitrary for this test
        $settings = [
            'wcac_parent_product_weight'      => 11,
            'wcac_variation_product_weight'   => 12,
            'wcac_title_match_weight'         => 13,
            'wcac_content_match_weight'       => 14,
            'wcac_category_match_weight'      => 15,
            'wcac_tag_match_weight'           => 16,
            'wcac_on_sale_weight'             => 17,
            'wcac_direct_title_match_bonus'   => 18,
            'wcac_max_results_returned'       => 20,
            'wcac_negative_keyword_penalty'   => 21,
        ];

        // This test directly applies the settings array.
        // It does not test loading settings from wp_options.
        // if (function_exists('update_option')) {
        //     update_option('wcac_settings', $settings);
        // }

        // Apply settings directly to rules
        Wcac_ChatbotRules::apply_admin_settings($settings);

        // Assert each static property matches the setting
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(11, Wcac_ChatbotRules::get_parent_product_weight());
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(12, Wcac_ChatbotRules::get_variation_product_weight());
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(13, Wcac_ChatbotRules::get_title_match_weight());
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(14, Wcac_ChatbotRules::get_content_match_weight());
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(15, Wcac_ChatbotRules::get_category_match_weight());
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(16, Wcac_ChatbotRules::get_tag_match_weight());
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(17, Wcac_ChatbotRules::get_on_sale_weight());
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(18, Wcac_ChatbotRules::get_direct_title_match_bonus());
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(20, Wcac_ChatbotRules::get_max_results_returned());
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(21, Wcac_ChatbotRules::get_negative_keyword_penalty());
    }

    public function test_fuzzy_boosts_affect_scoring()
    {
        // Set fuzzy boosts to known values
        $settings = [
            'wcac_fuzzy_match_boost_high' => 0,
            'wcac_fuzzy_match_boost_medium' => 10,
            'wcac_fuzzy_match_boost_low' => 5,
        ];
        Wcac_ChatbotRules::apply_admin_settings($settings);

        // Create a mock item with a field that will only fuzzy-match the keyword
        $item = [
            'id' => 1,
            'title' => 'Yarn',
            'content_snippet' => 'Soft blue yarn',
            'categories' => [],
            'tags' => [],
            'menu_titles' => [],
            'post_type' => 'product',
            'parent_id' => 0,
            'on_sale' => false,
        ];
        $keywords = ['yarnn']; // Intentional typo to trigger fuzzy match

        // Score with medium boost (distance 1)
        $score_medium = Wcac_ChatbotRules::score_item($item, $keywords);

        // Change medium boost to a different value and re-score
        $settings['wcac_fuzzy_match_boost_medium'] = 20;
        Wcac_ChatbotRules::apply_admin_settings($settings);
        $score_medium2 = Wcac_ChatbotRules::score_item($item, $keywords);

        // Assert that the score increases when the boost increases
        $this->assertTrue($score_medium2 > $score_medium, 'Score should increase when fuzzy boost increases');

        // Now test low boost (distance 2)
        $keywords = ['yarnnn']; // Distance 2 typo
        $settings['wcac_fuzzy_match_boost_medium'] = 0;
        $settings['wcac_fuzzy_match_boost_low'] = 30;
        Wcac_ChatbotRules::apply_admin_settings($settings);
        $score_low = Wcac_ChatbotRules::score_item($item, $keywords);
        $settings['wcac_fuzzy_match_boost_low'] = 50;
        Wcac_ChatbotRules::apply_admin_settings($settings);
        $score_low2 = Wcac_ChatbotRules::score_item($item, $keywords);
        $this->assertTrue($score_low2 > $score_low, 'Score should increase when low fuzzy boost increases');
    }

    public function testPhpunitSanity()
    {
        $this->assertTrue(true, 'PHPUnit is running and can execute a basic test.');
    }
}

// NOTE: This test is for legacy settings. Update or remove if the test suite is modernized.
