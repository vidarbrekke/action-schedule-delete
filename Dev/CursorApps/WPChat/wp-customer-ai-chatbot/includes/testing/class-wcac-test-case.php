<?php

class Wcac_Test_Case
{
    public $query;
    public $expected_results; // array of expected post IDs (as strings or ints)
    public $comment;

    /**
     * Create a new test case.
     *
     * @param string $query The search query
     * @param array $expected_results Array of expected result IDs
     * @param string $comment Optional comment about the test case
     */
    public function __construct(string $query, array $expected_results, string $comment = '')
    {
        error_log('WCAC TEST CASE: Creating test case');
        error_log('WCAC TEST CASE: Query: ' . $query);
        error_log('WCAC TEST CASE: Expected results: ' . json_encode($expected_results));
        error_log('WCAC TEST CASE: Comment: ' . $comment);

        // Validate query
        if (empty($query)) {
            throw new InvalidArgumentException('Query cannot be empty');
        }
        $this->query = $query;

        // Validate expected results
        if (empty($expected_results)) {
            throw new InvalidArgumentException('Expected results cannot be empty');
        }
        $this->expected_results = array_map('intval', $expected_results);

        // Validate comment
        $this->comment = $comment;

        error_log('WCAC TEST CASE: Test case created successfully');
    }

    /**
     * Get the search query.
     *
     * @return string The search query
     */
    public function get_query(): string
    {
        return $this->query;
    }

    /**
     * Get the expected results.
     *
     * @return array Array of expected result IDs
     */
    public function get_expected_results(): array
    {
        return $this->expected_results;
    }

    /**
     * Get the test case comment.
     *
     * @return string The comment
     */
    public function get_comment(): string
    {
        return $this->comment;
    }

    /**
     * Parse expected results from a CSV string (comma or semicolon separated)
     * @param string|null $str
     * @return array
     */
    public static function parse_expected_results($str)
    {
        if (!$str) {
            return [];
        }
        $str = trim($str);
        if ($str === '') {
            return [];
        }
        // Accept comma or semicolon separated
        $parts = preg_split('/[;,]/', $str);
        return array_map('trim', $parts);
    }
}
