<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wcac-chatbot-rules.php';

class Test_Wcac_Settings_Application extends TestCase {
    public function setUp(): void {
        parent::setUp();
        // Optionally reset static properties to defaults here if needed
    }

    public function test_settings_are_applied_to_rules() {
        // Simulate admin settings
        $settings = [
            'wcac_parent_product_weight'      => 11,
            'wcac_variation_product_weight'   => 12,
            'wcac_title_match_weight'         => 13,
            'wcac_content_match_weight'       => 14,
            'wcac_category_match_weight'      => 15,
            'wcac_tag_match_weight'           => 16,
            'wcac_on_sale_weight'             => 17,
            'wcac_direct_title_match_bonus'   => 18,
            'wcac_min_score_threshold'        => 19,
            'wcac_max_results_returned'       => 20,
            'wcac_negative_keyword_penalty'   => 21,
        ];

        // Save to options table (simulate admin save)
        if (function_exists('update_option')) {
            update_option('wcac_settings', $settings);
        }

        // Apply settings to rules
        Wcac_ChatbotRules::apply_admin_settings($settings);

        // Assert each static property matches the setting
        $this->assertEquals(11, Wcac_ChatbotRules::get_parent_product_weight());
        $this->assertEquals(12, Wcac_ChatbotRules::get_variation_product_weight());
        $this->assertEquals(13, Wcac_ChatbotRules::get_title_match_weight());
        $this->assertEquals(14, Wcac_ChatbotRules::get_content_match_weight());
        $this->assertEquals(15, Wcac_ChatbotRules::get_category_match_weight());
        $this->assertEquals(16, Wcac_ChatbotRules::get_tag_match_weight());
        $this->assertEquals(17, Wcac_ChatbotRules::get_on_sale_weight());
        $this->assertEquals(18, Wcac_ChatbotRules::get_direct_title_match_bonus());
        $this->assertEquals(19, Wcac_ChatbotRules::get_min_score_threshold());
        $this->assertEquals(20, Wcac_ChatbotRules::get_max_results_returned());
        $this->assertEquals(21, Wcac_ChatbotRules::get_negative_keyword_penalty());
    }
} 