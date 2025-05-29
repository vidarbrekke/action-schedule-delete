#!/usr/bin/env php
<?php
// Usage: php wcac_random_optimizer.php [optional:/path/to/test_cases.csv]

if (php_sapi_name() !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$csv_file = $argv[1] ?? null;
if ($csv_file && !file_exists($csv_file)) {
    echo "Warning: Provided CSV file does not exist. Will use default/admin-uploaded test suite.\n";
    $csv_file = null;
}

// --- Parameter Ranges (customize as needed) ---
$ranges = [
    'wcac_parent_product_weight' => [5, 30],
    'wcac_variation_product_weight' => [5, 30],
    'wcac_title_match_weight' => [5, 30],
    'wcac_content_match_weight' => [5, 30],
    'wcac_category_match_weight' => [5, 30],
    'wcac_tag_match_weight' => [5, 30],
    'wcac_on_sale_weight' => [0, 20],
    'wcac_direct_title_match_bonus' => [0, 50],
    'wcac_title_category_match_boost' => [0, 50],
    'wcac_exact_product_name_boost' => [0, 50],
    'wcac_multi_field_match_bonus' => [0, 50],
    'wcac_all_keywords_in_title_boost' => [0, 50],
    'wcac_fuzzy_match_boost_high' => [0, 50],
    'wcac_fuzzy_match_boost_medium' => [0, 30],
    'wcac_fuzzy_match_boost_low' => [0, 20],
    'wcac_parent_preference_margin' => [0, 100],
    'wcac_relative_score_threshold' => [0, 100],
    'wcac_max_results_returned' => [5, 20],
    'wcac_negative_keyword_penalty' => [-50, 0],
    'wcac_menu_match_weight' => [0, 50],
    'wcac_attribute_match_weight' => [0, 50],
    'wcac_parent_category_match_weight' => [0, 50],
    'wcac_taxonomy_match_weight' => [0, 50],
];

$iterations = 20; // Number of random samples to try
$log_file = __DIR__ . '/optimizer_log.csv';

require_once __DIR__ . '/../includes/class-wcac-settings-optimizer.php';
require_once __DIR__ . '/../includes/optimizer/class-wcac-optimizer-constraints.php'; // Enforce parameter constraints

$best_score = -1;
$best_params = [];

for ($i = 0; $i < $iterations; $i++) {
    // --- Generate random parameter set ---
    $params = [];
    foreach ($ranges as $key => [$min, $max]) {
        $params[$key] = is_int($min) && is_int($max)
            ? rand($min, $max)
            : mt_rand($min * 100, $max * 100) / 100;
    }
    // Enforce constraints before applying parameters
    $params = Wcac_Optimizer_Constraints::enforce($params);
    Wcac_Settings_Optimizer::set_parameters($params);
    echo "[$i] Updated parameters.\n";

    // --- Run test suite via WP-CLI ---
    $run_cmd = $csv_file ? "wp wcac run_tests --csv=$csv_file" : "wp wcac run_tests";
    $output = [];
    $return_var = 0;
    exec($run_cmd, $output, $return_var);
    $run_id = null;
    foreach ($output as $line) {
        if (preg_match('/Run ID: (\d+)/', $line, $m)) {
            $run_id = $m[1];
            break;
        }
    }
    if (!$run_id) {
        echo "Test run failed. Output:\n" . implode("\n", $output) . "\n";
        continue;
    }
    echo "Run ID: $run_id\n";

    // --- Score results via WP-CLI ---
    $score_cmd = "wp wcac score_results --run_id=$run_id --top_n=10";
    $score_output = [];
    exec($score_cmd, $score_output);
    $score_line = end($score_output);
    preg_match('/Pass rate: ([\d.]+)%/', $score_line, $m);
    $score = isset($m[1]) ? floatval($m[1]) : 0;
    echo "Score: $score\n";

    // --- Log ---
    $log_row = [$i, $run_id, $score, json_encode($params)];
    file_put_contents($log_file, implode(",", $log_row) . "\n", FILE_APPEND);

    if ($score > $best_score) {
        $best_score = $score;
        $best_params = $params;
    }
}

// --- Update plugin settings with best parameters ---
Wcac_Settings_Optimizer::set_parameters($best_params);
echo "Best score: $best_score\n";
echo "Best parameters: " . json_encode($best_params) . "\n"; 