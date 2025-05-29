<?php
if (!defined('ABSPATH')) { define('ABSPATH', '/home/staging/public_html/'); }
if (!defined('WPINC')) { define('WPINC', 'wp-includes'); }
define('PHPUNIT_RUNNING', true);
// tests/bootstrap.php

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/\\') . '/';
    }
}

// Load Composer autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    echo "Composer autoload not found. Please run 'composer install' in the plugin directory.";
    exit(1);
}

// Load plugin classes
require_once __DIR__ . '/../includes/retrieval/class-wcac-chatbot-rules.php';
require_once __DIR__ . '/../includes/retrieval/class-wcac-query-analyzer.php';
require_once __DIR__ . '/../includes/retrieval/class-wcac-result-ranker.php';
require_once __DIR__ . '/../includes/retrieval/class-wcac-content-retriever.php';
require_once __DIR__ . '/../includes/retrieval/class-wcac-keyword-search.php';
require_once __DIR__ . '/../includes/retrieval/class-wcac-vector-search.php';
require_once __DIR__ . '/../includes/retrieval/class-wcac-search-strategy.php';

// Stub WordPress functions used in tests
if (! function_exists('wp_json_encode')) {
    function wp_json_encode($data) { return json_encode($data); }
}

// Provide a basic $wpdb stub for tests
global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new class {
        public $prefix = 'wp_';
        public function esc_like($text) { return $text; }
        public function prepare($query, ...$params) { return ''; }
        public function get_results($query, $output = null) { return []; }
    };
} 