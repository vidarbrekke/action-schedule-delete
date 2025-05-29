<?php

declare(strict_types=1);

require_once WCAC_PLUGIN_DIR . 'includes/testing/class-wcac-test-case.php';

class Wcac_Csv_Parser
{
    /**
     * Parse a CSV file and return an array of associative arrays (one per row), with case-insensitive header mapping.
     * @param string $file Path to the uploaded CSV file
     * @return array Parsed rows as associative arrays
     */
    public function parse($file)
    {
        error_log('WCAC CSV PARSER: parse() called with file: ' . $file);
        $handle = fopen($file, 'r');
        if (!$handle) {
            return [];
        }
        $header = fgetcsv($handle);
        error_log('WCAC CSV PARSER: header = ' . json_encode($header));
        if (!$header) {
            fclose($handle);
            return [];
        }
        $header_lc = array_map('strtolower', $header);
        error_log('WCAC CSV PARSER: header_lc = ' . json_encode($header_lc));
        $query_col_index = array_search('query', $header_lc);
        if ($query_col_index === false) {
            fclose($handle);
            return [];
        }
        // Find all expected result columns (e.g., expected_results, expected top result 1, 2, 3, ...)
        $expected_cols = [];
        foreach ($header_lc as $i => $col) {
            // Match 'expected_results' or any 'expected top result X' (case-insensitive, ignore spaces)
            if ($col === 'expected_results' || preg_match('/^expected\s*top\s*result\s*\d+$/i', trim($header[$i]))) {
                $expected_cols[] = $i;
            }
        }
        error_log('WCAC CSV PARSER: expected_cols (regex) = ' . json_encode($expected_cols));
        $rows = [];
        error_log('WCAC CSV PARSER: entering while loop, handle valid: ' . ($handle ? 'yes' : 'no'));
        while (($row = fgetcsv($handle)) !== false) {
            error_log('WCAC CSV PARSER: row = ' . json_encode($row));
            error_log('WCAC CSV PARSER: per-row $row = ' . json_encode($row));
            error_log('WCAC CSV PARSER: per-row $expected_cols = ' . json_encode($expected_cols));
            error_log('WCAC CSV PARSER: count($row) = ' . count($row) . ', count($header_lc) = ' . count($header_lc));
            if (count($row) < count($header_lc)) {
                continue;
            }
            error_log('WCAC CSV PARSER: before array_combine');
            $data = array_combine($header_lc, $row);
            error_log('WCAC CSV PARSER: after array_combine');
            if ($data === false) {
                error_log('WCAC CSV PARSER: array_combine failed! header_lc = ' . json_encode($header_lc) . ', row = ' . json_encode($row));
                continue;
            }
            error_log('WCAC CSV PARSER: $data = ' . json_encode($data));
            error_log('WCAC CSV PARSER: $data keys = ' . json_encode(array_keys($data)));
            $query = trim($data['query'] ?? '');
            error_log('WCAC CSV PARSER: $query = ' . json_encode($query));
            // Collect expected results from all expected columns
            $expected = [];
            foreach ($expected_cols as $col_idx) {
                $raw_val = $row[$col_idx] ?? '';
                $val = trim($raw_val);
                error_log('WCAC CSV PARSER: col_idx=' . $col_idx . ', raw_val=' . json_encode($raw_val) . ', val=' . json_encode($val));
                if ($val !== '') {
                    $expected[] = $val;
                }
            }
            error_log('WCAC CSV PARSER: $expected array = ' . json_encode($expected) . ', count = ' . count($expected));
            $comment = isset($data['comment']) ? trim($data['comment']) : '';
            if ($query === '') {
                continue;
            }
            $test_case = new Wcac_Test_Case($query, $expected, $comment);
            error_log('WCAC CSV PARSER: test_case->expected_results = ' . var_export($test_case->expected_results, true));
            $rows[] = $test_case;
        }
        error_log('WCAC CSV PARSER: while loop completed, rows processed = ' . count($rows));
        if (!empty($rows)) {
            error_log('WCAC CSV PARSER: first row expected_results = ' . json_encode($rows[0]->expected_results));
        } else {
            error_log('WCAC CSV PARSER: rows array is empty');
        }
        fclose($handle);
        error_log('WCAC CSV PARSER: parse() completed for file: ' . $file);
        return $rows;
    }
}
