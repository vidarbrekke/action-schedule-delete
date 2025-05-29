<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/retrieval/class-wcac-chatbot-rules.php';

// LinterWarning: PHPUnit context unavailable locally
class TestWcacChatbotRules extends TestCase
{
    /**
     * @dataProvider provideKeywordExtractionCases
     */
    public function testExtractKeywordsFiltersStopwords(string $input, array $expectedKeywords): void
    {
        // Assuming Wcac_ChatbotRules::extract_keywords handles loading stopwords/settings
        $keywords = Wcac_ChatbotRules::extract_keywords($input);
        sort($keywords); // Sort for consistent comparison
        sort($expectedKeywords);
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals($expectedKeywords, $keywords);
    }

    public function provideKeywordExtractionCases(): array
    {
        return [
            'simple sentence' => ["Buy 2 skeins of soft yarn!", ['buy', 'skeins', 'soft', 'yarn']],
            'with punctuation' => ["Needles, hooks, and patterns?", ['needles', 'hooks', 'patterns']],
            'numbers only' => ["123 456", []],
            'stopwords only' => ["is the a of it", []],
            'empty string' => ["", []],
            'case variation' => ["Soft YARN and NEEDLES", ['soft', 'yarn', 'needles']],
            // TODO: Add case for compound product names if handled by extract_keywords
        ];
    }

    // --- New Tests for RAG Pipeline ---

    public function testHandleEmptyQuery(): void
    {
        // TODO: Implement test: Mock Wcac_ChatbotRules instance or necessary static methods
        // TODO: Call the main retrieval/ranking method with an empty query/keywords
        // TODO: Assert the expected outcome (e.g., empty result array, specific fallback message structure)
        // LinterWarning: PHPUnit context unavailable locally
        $this->markTestIncomplete('Placeholder for empty query test.');
    }

    public function testHandleStopwordOnlyQueryPipeline(): void
    {
        // TODO: Implement test: Mock Wcac_ChatbotRules instance or necessary static methods
        // TODO: Call the main retrieval/ranking method simulating a query that results in only stopwords
        // TODO: Assert the expected outcome (likely low-confidence fallback or empty results)
        // LinterWarning: PHPUnit context unavailable locally
        $this->markTestIncomplete('Placeholder for stopword-only query pipeline test.');
    }

    public function testCompoundProductNameBoost(): void
    {
        // Configure compound names and the boost value
        $test_compound_name = 'tynn silk mohair';
        $test_boost_value = 200.0;
        Wcac_ChatbotRules::$compound_product_names = [$test_compound_name];
        Wcac_ChatbotRules::$exact_product_name_boost = $test_boost_value;
        // Reset other weights/boosts to isolate this one, or set to known values
        Wcac_ChatbotRules::$parent_product_weight = 10.0;
        Wcac_ChatbotRules::$variation_product_weight = 1.0;
        Wcac_ChatbotRules::$title_match_weight = 5.0;
        Wcac_ChatbotRules::$content_match_weight = 1.0;
        // ... reset others if necessary ...

        // Mock items
        $item_match = [
            'post_id' => 101,
            'title' => 'Tynn Silk Mohair', // Exact match (case-insensitive)
            'content_snippet' => 'Soft yarn.',
            'post_type' => 'product'
            // Add other fields score_item expects if needed
        ];
        $item_start_match = [
            'post_id' => 102,
            'title' => 'Tynn Silk Mohair Extra Fine', // Starts with match
            'content_snippet' => 'Extra soft yarn.',
            'post_type' => 'product'
        ];
        $item_bracket_match = [
            'post_id' => 103,
            'title' => 'Color Pack [Tynn Silk Mohair]', // Bracket match
            'content_snippet' => 'Various colors.',
            'post_type' => 'product'
        ];
        $item_no_match = [
            'post_id' => 104,
            'title' => 'Regular Silk Mohair', // No match
            'content_snippet' => 'Also soft yarn.',
            'post_type' => 'product'
        ];

        $keywords = ['tynn', 'silk', 'mohair', 'yarn']; // Keywords to trigger some base score

        // Score items
        $score_match = Wcac_ChatbotRules::score_item($item_match, $keywords);
        $score_start_match = Wcac_ChatbotRules::score_item($item_start_match, $keywords);
        $score_bracket_match = Wcac_ChatbotRules::score_item($item_bracket_match, $keywords);
        $score_no_match = Wcac_ChatbotRules::score_item($item_no_match, $keywords);

        // Assertions
        // Calculate expected difference (the boost value)
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals($score_no_match + $test_boost_value, $score_match, "Exact compound name match should receive the boost.", 1.0); // Delta for potential float issues
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals($score_no_match + $test_boost_value, $score_start_match, "Start-of-title compound name match should receive the boost.", 1.0);
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals($score_no_match + $test_boost_value, $score_bracket_match, "Bracketed compound name match should receive the boost.", 1.0);

        // Check the non-matching item didn't get boosted (sanity check)
        // This is implicitly checked by the assertions above, but explicit check can be useful
        // We need the breakdown for this.
        Wcac_ChatbotRules::score_item($item_no_match, $keywords); // Re-run to get breakdown
        $breakdown_no_match = Wcac_ChatbotRules::$last_debug_breakdown;
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertArrayHasKey('exact_product_name_boost', $breakdown_no_match, "Breakdown should contain boost key.");
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(0.0, $breakdown_no_match['exact_product_name_boost'], "Non-matching item should have zero exact product name boost.");
    }

    public function testNegativeKeywordFilteringSimulation(): void
    {
        // Note: Actual filtering happens at index time in Wcac_Indexer.
        // This test is skipped because Wcac_ChatbotRules does not perform negative keyword filtering;
        // it processes data assumed to be already filtered.
        // LinterWarning: PHPUnit context unavailable locally
        $this->markTestSkipped('Negative keyword filtering occurs in Wcac_Indexer, not testable here.');
    }

    public function testLowConfidenceFallback(): void
    {
        // Mock data: All items have scores below a potential threshold
        $mock_scoring_data = [
            ['id' => 101, 'title' => 'Product A', 'score' => 50.0, 'breakdown' => []],
            ['id' => 102, 'title' => 'Product B', 'score' => 40.0, 'breakdown' => []],
            ['id' => 103, 'title' => 'Product C', 'score' => 30.0, 'breakdown' => []],
        ];

        $mock_items_by_id = [
            101 => ['post_type' => 'product', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 10:00:00'],
            102 => ['post_type' => 'product', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 09:00:00'],
            103 => ['post_type' => 'product', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 08:00:00'],
        ];

        // Set a threshold that no item can meet
        // Top score is 50.0. If threshold is 0.8, min acceptable is 40.0 (relative to top)
        // If we set it higher, e.g., 0.9, min acceptable is 45.0 - filters out item 102.
        // Let's test the case where *no* items meet the threshold
        $top_score = 50.0;
        $test_threshold = 0.9; // Min acceptable = 50 * 0.9 = 45.0
        Wcac_ChatbotRules::$relative_score_threshold = $test_threshold;
        // Ensure other settings don't interfere
        Wcac_ChatbotRules::$parent_preference_margin = 0;
        Wcac_ChatbotRules::set_max_results_returned(10);

        // Call the static method under test
        $results = Wcac_ChatbotRules::process_search_results($mock_scoring_data, $mock_items_by_id);

        // Assertions
        // When no items meet the threshold, process_search_results should return an empty array.
        // The calling function would then typically handle the fallback message.
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertIsArray($results, "Results should be an array even if empty");
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEmpty($results, "Expected empty results array when no items meet the low confidence threshold");
    }

    public function testDeduplication(): void
    {
        // Mock data: item 101 appears twice, item 103 appears twice
        // Items 101 have different scores to check which one is kept after sorting
        // Item 201 is a variation of 101, to test interaction with parent preference
        $mock_scoring_data = [
            ['id' => 101, 'title' => 'Parent Product A', 'score' => 150.0, 'breakdown' => []],
            ['id' => 102, 'title' => 'Other Product B', 'score' => 120.0, 'breakdown' => []],
            ['id' => 101, 'title' => 'Parent Product A (duplicate)', 'score' => 155.0, 'breakdown' => []], // Higher score duplicate
            ['id' => 201, 'title' => 'Variation A1', 'score' => 160.0, 'breakdown' => []], // Variation of 101
            ['id' => 103, 'title' => 'Parent Product C', 'score' => 100.0, 'breakdown' => []],
            ['id' => 103, 'title' => 'Parent Product C (duplicate)', 'score' => 90.0, 'breakdown' => []], // Lower score duplicate
        ];

        $mock_items_by_id = [
            101 => ['post_type' => 'product', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 10:00:00'],
            102 => ['post_type' => 'page', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 09:00:00'],
            201 => ['post_type' => 'product_variation', 'parent_id' => 101, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 11:00:00'],
            103 => ['post_type' => 'product', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 08:00:00'],
        ];

        // Set necessary static properties (defaults might be fine, but explicit is safer)
        Wcac_ChatbotRules::$parent_preference_margin = 25.0;
        Wcac_ChatbotRules::$relative_score_threshold = 0.0; // Ensure threshold doesn't interfere
        Wcac_ChatbotRules::set_max_results_returned(10); // Ensure limit doesn't interfere

        // Call the static method under test
        $results = Wcac_ChatbotRules::process_search_results($mock_scoring_data, $mock_items_by_id);

        // Assertions
        $result_ids = array_map(fn($item) => $item['id'], $results);

        // Check for uniqueness
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertSame(array_unique($result_ids), $result_ids, "Result IDs should be unique");

        // Check expected count (102, 103, and either 101 or 201 depending on parent pref)
        // Since variation 201 score (160) > parent 101 highest score (155) + margin (25) is FALSE,
        // the parent (101 with score 155) should be kept, variation 201 discarded.
        // The duplicate parent 101 (score 150) should be discarded by sorting/deduplication.
        // The duplicate parent 103 (score 90) should be discarded by sorting/deduplication.
        // Expected IDs: 101 (score 155), 102 (score 120), 103 (score 100)
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertCount(3, $results, "Expected 3 unique items after processing");

        // Check that the correct IDs are present
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertContains(101, $result_ids, "Parent Product A (ID 101) should be present");
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertNotContains(201, $result_ids, "Variation A1 (ID 201) should have been filtered by parent preference");
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertContains(102, $result_ids, "Other Product B (ID 102) should be present");
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertContains(103, $result_ids, "Parent Product C (ID 103) should be present");

        // Check that the higher score duplicate of 101 was kept
        $found_101 = null;
        foreach ($results as $item) {
            if ($item['id'] === 101) {
                $found_101 = $item;
                break;
            }
        }
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertNotNull($found_101, "Item 101 not found in results");
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertEquals(155.0, $found_101['score'], "Expected higher score duplicate of item 101 to be kept");
    }

    public function testRelativeScoreThresholdFiltering(): void
    {
        // Mock data: scores ranging from 200 down to 80
        $mock_scoring_data = [
            ['id' => 101, 'title' => 'Product A', 'score' => 200.0, 'breakdown' => []], // Top score
            ['id' => 102, 'title' => 'Product B', 'score' => 150.0, 'breakdown' => []],
            ['id' => 103, 'title' => 'Product C', 'score' => 100.0, 'breakdown' => []], // Exactly at threshold (if threshold=0.5)
            ['id' => 104, 'title' => 'Product D', 'score' => 99.0, 'breakdown' => []],  // Just below threshold (if threshold=0.5)
            ['id' => 105, 'title' => 'Product E', 'score' => 80.0, 'breakdown' => []],   // Well below threshold
        ];

        // Minimal mock item details needed for processing
        $mock_items_by_id = [
            101 => ['post_type' => 'product', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 10:00:00'],
            102 => ['post_type' => 'product', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 09:00:00'],
            103 => ['post_type' => 'product', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 08:00:00'],
            104 => ['post_type' => 'product', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 07:00:00'],
            105 => ['post_type' => 'product', 'parent_id' => 0, 'stock_status' => 'instock', 'post_modified' => '2023-01-01 06:00:00'],
        ];

        // Set the threshold for this test
        $test_threshold = 0.5;
        Wcac_ChatbotRules::$relative_score_threshold = $test_threshold;
        // Ensure other settings don't interfere
        Wcac_ChatbotRules::$parent_preference_margin = 0; // Disable parent preference effect
        Wcac_ChatbotRules::set_max_results_returned(10);

        // Call the static method under test
        $results = Wcac_ChatbotRules::process_search_results($mock_scoring_data, $mock_items_by_id);

        // Assertions
        $result_ids = array_map(fn($item) => $item['id'], $results);
        $top_score = 200.0;
        $min_acceptable_score = $top_score * $test_threshold; // 100.0

        // Check expected count (items with score >= 100.0)
        // Expected IDs: 101 (200), 102 (150), 103 (100)
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertCount(3, $results, "Expected 3 items to meet the relative score threshold");

        // Check that only items meeting the threshold are present
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertContains(101, $result_ids, "Item 101 should be present");
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertContains(102, $result_ids, "Item 102 should be present");
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertContains(103, $result_ids, "Item 103 (at threshold) should be present");
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertNotContains(104, $result_ids, "Item 104 (below threshold) should be filtered out");
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertNotContains(105, $result_ids, "Item 105 (below threshold) should be filtered out");

        // Verify scores are as expected (optional sanity check)
        foreach ($results as $item) {
            // LinterWarning: PHPUnit context unavailable locally
            $this->assertGreaterThanOrEqual($min_acceptable_score, $item['score'], "Item score should be >= min acceptable score");
        }
    }
}
