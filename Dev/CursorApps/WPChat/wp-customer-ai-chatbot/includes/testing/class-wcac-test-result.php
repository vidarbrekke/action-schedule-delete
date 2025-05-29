<?php

class Wcac_Test_Result
{
    public $query;
    public $expected_results;
    public $actual_results;
    public $pass_fail;
    public $comment;
    public $scoring_breakdown;

    public function __construct($query, $expected_results, $actual_results, $pass_fail, $comment = '', $scoring_breakdown = [])
    {
        $this->query = $query;
        $this->expected_results = $expected_results;
        $this->actual_results = $actual_results;
        $this->pass_fail = $pass_fail;
        $this->comment = $comment;
        $this->scoring_breakdown = $scoring_breakdown;
    }

    /**
     * Determine pass/fail: pass if any expected result is in the top N actual results
     * @param array $expected
     * @param array $actual_ids
     * @param int $top_n
     * @return string 'PASS' or 'FAIL'
     */
    public static function evaluate($expected, $actual_ids, $top_n = 10)
    {
        if (empty($expected)) {
            return 'N/A';
        }
        $actual_top = array_slice($actual_ids, 0, $top_n);
        $found = [];
        $not_found = [];
        foreach ($expected as $exp) {
            if (in_array((string)$exp, $actual_top, true)) {
                $found[] = $exp;
            } else {
                $not_found[] = $exp;
            }
        }
        if (!empty($found)) {
            return 'PASS ID ' . implode(',', $found);
        } else {
            return 'FAIL ID ' . implode(',', $not_found);
        }
    }
}
