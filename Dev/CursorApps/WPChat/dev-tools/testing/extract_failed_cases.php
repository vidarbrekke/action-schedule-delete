<?php
// dev-tools/testing/extract_failed_cases.php
// Parse the test results CSV and extract failed test cases

$csvFile = __DIR__ . '/wcac_test_results_run_1746559103-7809.csv';
if (!file_exists($csvFile)) {
    die("CSV file not found: $csvFile\n");
}

$handle = fopen($csvFile, 'r');
if (!$handle) {
    die("Failed to open CSV file: $csvFile\n");
}

$header = fgetcsv($handle);
$results = [];
while (($row = fgetcsv($handle)) !== false) {
    $rowData = array_combine($header, $row);
    if ($rowData['is_pass'] === '0') {
        $expected = json_decode($rowData['expected_results'], true) ?: [];
        $actual = [];
        if (!empty($rowData['actual_results'])) {
            $actualDecoded = json_decode($rowData['actual_results'], true);
            if (is_array($actualDecoded)) {
                foreach ($actualDecoded as $item) {
                    if (isset($item['id'])) {
                        $actual[] = (string)$item['id'];
                    }
                }
            }
        }
        $missing = array_values(array_diff($expected, $actual));
        $results[] = [
            'query' => $rowData['query'],
            'expected' => $expected,
            'actual' => $actual,
            'missing' => $missing,
        ];
    }
}
fclose($handle);

$missing_ids = array_unique(array_merge(...array_column($results, 'missing')));
if (empty($missing_ids)) {
    die("No missing IDs found.\n");
}

// Connect to the database
$mysqli = new mysqli('localhost', 'staging', 'uNd8afbfFhEU4QDvh7UvW5FCtXWrKna', 'staging');
if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error . "\n");
}

$id_list = implode(',', array_map('intval', $missing_ids));
$query = "SELECT post_id, post_type, post_modified, title, content_snippet, url, categories, tags, recommended_for, regular_price, sale_price, on_sale, parent_id, stock_status, search_blob, menu_titles, parent_categories, parent_category_names, attributes_text, taxonomies FROM wp_wcac_index WHERE post_id IN ($id_list)";
$result = $mysqli->query($query);

if (!$result) {
    die("Query failed: " . $mysqli->error . "\n");
}

$outputFile = __DIR__ . '/missing_indexed_metadata.csv';
$out = fopen($outputFile, 'w');
$header_written = false;
while ($row = $result->fetch_assoc()) {
    if (!$header_written) {
        fputcsv($out, array_keys($row));
        $header_written = true;
    }
    fputcsv($out, $row);
}
fclose($out);
$mysqli->close();

// Print summary
foreach ($results as $case) {
    echo "Query: {$case['query']}\n";
    echo "  Expected: " . implode(', ', $case['expected']) . "\n";
    echo "  Actual:   " . implode(', ', $case['actual']) . "\n";
    echo "  Missing:  " . implode(', ', $case['missing']) . "\n";
    echo str_repeat('-', 40) . "\n";
}
echo "\nIndexed metadata for missing posts written to $outputFile\n"; 