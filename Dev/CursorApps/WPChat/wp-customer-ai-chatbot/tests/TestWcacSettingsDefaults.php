<?php

use PHPUnit\Framework\TestCase;

// Ensure the class we are testing is loaded
// Note: bootstrap.php should handle loading dependencies and WP stubs
require_once __DIR__ . '/../includes/retrieval/class-wcac-chatbot-rules.php';

// LinterWarning: PHPUnit context unavailable locally
class TestWcacSettingsDefaults extends TestCase
{
    /**
     * Test that the default static property values are used when settings are empty.
     */
    public function testDefaultsAppliedWhenOptionsEmpty(): void
    {
        // Arrange: Simulate empty options being returned by the bootstrap stub
        $empty_options = [];

        // Act: Apply the empty settings
        // Note: This might reset the properties if tests run in the same process without isolation.
        // Consider using @backupStaticAttributes annotation if needed, though it might not work perfectly without full PHPUnit setup.
        Wcac_ChatbotRules::apply_admin_settings($empty_options);

        // Assert: Check that all static properties match their declared defaults
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(150.0, Wcac_ChatbotRules::get_parent_product_weight(), 'Default parent_product_weight');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(1.0, Wcac_ChatbotRules::get_variation_product_weight(), 'Default variation_product_weight');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(25.0, Wcac_ChatbotRules::get_title_match_weight(), 'Default title_match_weight');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(5.0, Wcac_ChatbotRules::get_content_match_weight(), 'Default content_match_weight');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(25.0, Wcac_ChatbotRules::get_category_match_weight(), 'Default category_match_weight');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(10.0, Wcac_ChatbotRules::get_tag_match_weight(), 'Default tag_match_weight');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(5.0, Wcac_ChatbotRules::get_on_sale_weight(), 'Default on_sale_weight');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(100.0, Wcac_ChatbotRules::get_direct_title_match_bonus(), 'Default direct_title_match_bonus');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(5, Wcac_ChatbotRules::get_max_results_returned(), 'Default max_results_returned');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(-3.0, Wcac_ChatbotRules::get_negative_keyword_penalty(), 'Default negative_keyword_penalty');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(50.0, Wcac_ChatbotRules::get_multi_field_match_bonus(), 'Default multi_field_match_bonus');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(50.0, Wcac_ChatbotRules::get_all_keywords_in_title_boost(), 'Default all_keywords_in_title_boost');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(10.0, Wcac_ChatbotRules::get_fuzzy_match_boost_high(), 'Default fuzzy_match_boost_high');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(5.0, Wcac_ChatbotRules::get_fuzzy_match_boost_medium(), 'Default fuzzy_match_boost_medium');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(1.0, Wcac_ChatbotRules::get_fuzzy_match_boost_low(), 'Default fuzzy_match_boost_low');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(100.0, Wcac_ChatbotRules::get_title_category_match_boost(), 'Default title_category_match_boost');

        // Use direct static property access for those without getters/setters shown in the previous read
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(200.0, Wcac_ChatbotRules::$exact_product_name_boost, 'Default exact_product_name_boost');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(25.0, Wcac_ChatbotRules::$parent_preference_margin, 'Default parent_preference_margin');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(0.4, Wcac_ChatbotRules::$relative_score_threshold, 'Default relative_score_threshold');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(30.0, Wcac_ChatbotRules::get_menu_match_weight(), 'Default menu_match_weight');
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals([], Wcac_ChatbotRules::$compound_product_names, 'Default compound_product_names');
    }
}
