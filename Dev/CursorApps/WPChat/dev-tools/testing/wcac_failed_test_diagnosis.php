<?php
// dev-tools/testing/wcac_failed_test_diagnosis.php
// Usage: wp eval-file dev-tools/testing/wcac_failed_test_diagnosis.php

global $wpdb;

// Always load and apply latest settings from DB before running analysis.
if (class_exists('Wcac_ChatbotRules')) {
    $settings = get_option('wcac_settings', []);
    Wcac_ChatbotRules::apply_admin_settings($settings);
}

$report_md = [];
$report_md[] = "# WCAC Failed Test Diagnosis Report\n";
$report_md[] = "_Generated: " . date('Y-m-d H:i:s') . "_\n";

// 1. Get latest run_id
$run_id = $wpdb->get_var("SELECT run_id FROM {$wpdb->prefix}wcac_test_results ORDER BY created_at DESC LIMIT 1");
if (!$run_id) {
    echo "No test runs found in wcac_test_results table." . PHP_EOL;
    $report_md[] = "No test runs found in wcac_test_results table.";
    file_put_contents(__DIR__ . '/wcac_failed_test_diagnosis_report.md', implode("\n", $report_md));
    return;
}
echo "Latest run_id: $run_id" . PHP_EOL;
$report_md[] = "**Latest run_id:** `$run_id`\n";

// 2. Get all failed test results
$failed = $wpdb->get_results($wpdb->prepare(
    "SELECT id, query, expected_results, actual_results, created_at FROM {$wpdb->prefix}wcac_test_results WHERE run_id = %s AND is_pass = 0 ORDER BY id ASC",
    $run_id
), ARRAY_A);
if (!$failed) {
    echo "No failed test results found for this run." . PHP_EOL;
    $report_md[] = "No failed test results found for this run.";
    file_put_contents(__DIR__ . '/wcac_failed_test_diagnosis_report.md', implode("\n", $report_md));
    return;
}
echo "Found " . count($failed) . " failed test cases." . PHP_EOL;
$report_md[] = "**Failed test cases:** " . count($failed) . "\n";

$missing_count = 0;
$empty_field_count = 0;
$checked_ids = [];

foreach ($failed as $row) {
    echo str_repeat("-", 60) . PHP_EOL;
    $report_md[] = "---\n";
    $report_md[] = "## Query: `{$row['query']}`";
    $expected = json_decode($row['expected_results'], true);
    $actual = json_decode($row['actual_results'], true);
    $expected_ids = is_array($expected) ? $expected : [];
    $actual_ids = [];
    if (is_array($actual)) {
        foreach ($actual as $item) {
            if (is_array($item) && isset($item['id'])) {
                $actual_ids[] = $item['id'];
            } elseif (is_scalar($item)) {
                $actual_ids[] = $item;
            }
        }
    }
    echo "Query: {$row['query']}" . PHP_EOL;
    echo "Expected IDs: " . implode(", ", $expected_ids) . PHP_EOL;
    echo "Actual IDs:   " . implode(", ", $actual_ids) . PHP_EOL;
    $report_md[] = "- **Expected IDs:** `" . implode(", ", $expected_ids) . "`";
    $report_md[] = "- **Actual IDs:** `" . implode(", ", $actual_ids) . "`";
    foreach ($expected_ids as $eid) {
        $eid = intval($eid);
        if (!$eid) continue;
        if (isset($checked_ids[$eid])) continue; // Only check each ID once
        $checked_ids[$eid] = true;
        $row_idx = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcac_index WHERE post_id = %d",
            $eid
        ), ARRAY_A);
        $hypothesis = '';
        $raw_post_content = null; // Initialize variable for raw content

        if (!$row_idx) {
            echo "  [!] Expected ID $eid is NOT indexed!" . PHP_EOL;
            $report_md[] = "### Expected ID `$eid`";
            $report_md[] = "- [!] **NOT indexed**";
            $report_md[] = "- **Hypothesis:** Indexing bug, exclusion, or missing data.";
            $missing_count++;
            continue;
        } else {
            // Fetch raw post_content if index row exists
            $raw_post_content = $wpdb->get_var($wpdb->prepare(
                "SELECT post_content FROM {$wpdb->prefix}posts WHERE ID = %d",
                $eid
            ));
        }

        // Print all indexed fields (summarize in markdown)
        $report_md[] = "### Expected ID `$eid`";
        $report_md[] = "<details><summary>Indexed Data (Full Row)</summary>";
        $report_md[] = "```php"; // Use PHP block for better formatting
        
        $field_content_for_analysis = []; // Store content for keyword check
        $major_fields = ['title', 'content_snippet', 'categories', 'tags', 'menu_titles', 'search_blob'];
        $empty_major = 0;
        $non_empty_major_fields_count = 0;

        foreach ($row_idx as $k => $v) {
            // Output all key-value pairs from index table
            $report_md[] = sprintf("%-20s => %s", "'$k'", var_export($v, true));
            
            // Track empty/non-empty major fields for hypothesis
            if (in_array($k, $major_fields)) {
                $field_content_for_analysis[$k] = $v ?? ''; // Store raw value for check
                if (is_null($v) || $v === '' || $v === '[]') {
                    $empty_major++;
                } else {
                    $non_empty_major_fields_count++;
                }
            }
        }
        // Add the raw post_content separately
        $report_md[] = sprintf("%-20s => %s", "'raw_post_content'", var_export($raw_post_content, true));
        $report_md[] = "```"; // End PHP block
        $report_md[] = "</details>\n";

        // Hypothesis logic (slightly refined)
        if ($non_empty_major_fields_count === 0) { // Check if ALL major fields are effectively empty
            $hypothesis = 'Indexed but all major fields (title, content, terms, blob) are empty! **Likely data extraction/formatting issue.**';
            $empty_field_count++;
        } else {
            // Check if any query keyword appears in the combined content of major fields + search_blob
            $query_terms = preg_split('/\s+/', strtolower($row['query']));
            $found = false;
            // Include raw_post_content in the combined content check
            $combined_field_content = strtolower(implode(' ', $field_content_for_analysis)) . ' ' . strtolower($raw_post_content ?? '');

            foreach ($query_terms as $term) {
                if (!empty($term) && strlen($term) > 1 && strpos($combined_field_content, $term) !== false) { // Basic check, ignore very short terms
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // Updated message
                $hypothesis = 'Indexed, major fields have content, but **no query keywords found** in them. Possible **synonym/normalization issue**, query/field mismatch, or stopwords filtering too aggressively.';
            } else {
                // If here, query terms are present in indexed data, but still not matched
                $hypothesis = 'Indexed, fields present, query keywords found in indexed data, but item not returned or ranked high enough. Possible **scoring/weighting issue**, **subtle normalization difference**, **retrieval logic problem**, or filtering (e.g., parent preference).';
            }
        }
        $report_md[] = "- **Hypothesis:** $hypothesis\n";
    }
}
$report_md[] = "---\n";
$report_md[] = "## Summary";
$report_md[] = "- Missing expected IDs in index: $missing_count";
$report_md[] = "- Indexed IDs with empty/blank major fields: $empty_field_count";
$report_md[] = "\n_End of diagnosis._";

file_put_contents(__DIR__ . '/wcac_failed_test_diagnosis_report.md', implode("\n", $report_md));

echo str_repeat("=", 60) . PHP_EOL;
echo "Summary:" . PHP_EOL;
echo "  Missing expected IDs in index: $missing_count" . PHP_EOL;
echo "  Indexed IDs with empty/blank fields: $empty_field_count" . PHP_EOL;
echo "==== End of Diagnosis ====" . PHP_EOL; 