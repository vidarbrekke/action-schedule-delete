<?php
class Wcac_Test_Runner2 {
    private $test_cases;
    private $results = [];
    private $logger;
    private $run_id;

    public function __construct(array $test_cases) {
        $this->test_cases = $test_cases;
        $this->logger = new Wcac_Test_Logger();
        $this->run_id = uniqid('run_', true);
    }

    public function run() {
        foreach ($this->test_cases as $case) {
            $query = $case['query'];
            $expected = array_map('intval', $case['expected_results']);
            $retriever = new Wcac_Content_Retriever();
            $actual_objs = $retriever->retrieve($query, []);
            $actual = array_map(fn($r) => intval($r['id'] ?? 0), $actual_objs['final_results'] ?? []);
            $pass = $this->compare($expected, $actual);
            $this->results[] = [
                'run_id' => $this->run_id,
                'query' => $query,
                'expected' => $expected,
                'actual' => $actual,
                'is_pass' => $pass,
            ];
            $this->logger->log_test_result_simple($query, $expected, $actual, $pass, $this->run_id);
        }
        return $this->results;
    }

    private function compare(array $expected, array $actual): bool {
        $slice = array_slice($actual, 0, count($expected));
        foreach ($expected as $id) {
            if (!in_array($id, $slice, true)) return false;
        }
        return true;
    }
} 