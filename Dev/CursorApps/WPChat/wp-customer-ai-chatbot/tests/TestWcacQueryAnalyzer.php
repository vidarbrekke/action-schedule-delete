<?php

use PHPUnit\Framework\TestCase;

// Assuming the path is correct based on the original require in TestChatbotRules.php
require_once __DIR__ . '/../includes/retrieval/class-wcac-query-analyzer.php';

// LinterWarning: PHPUnit context unavailable locally
class TestWcacQueryAnalyzer extends TestCase
{
    public function testIsProductQueryDetectsKeywords(): void
    {
        $analyzer = new Wcac_Query_Analyzer();
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertTrue($analyzer->isProductQuery('I want to buy yarn.'));
        // LinterWarning: PHPUnit context unavailable locally
        $this->assertFalse($analyzer->isProductQuery('What are your store hours?'));
    }
}
