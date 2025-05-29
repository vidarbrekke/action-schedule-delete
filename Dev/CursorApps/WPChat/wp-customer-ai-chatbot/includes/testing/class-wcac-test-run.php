<?php

/**
 * Represents a single test run session.
 */
class Wcac_Test_Run
{
    private $run_id;
    private $start_time;

    /**
     * Create a new test run.
     */
    public function __construct()
    {
        error_log('WCAC TEST RUN: Creating new test run');
        $this->run_id = uniqid('', true);
        $this->start_time = microtime(true);
        error_log('WCAC TEST RUN: Test run created with ID: ' . $this->run_id);
    }

    /**
     * Get the run ID.
     *
     * @return string The run ID
     */
    public function get_run_id(): string
    {
        return $this->run_id;
    }

    /**
     * Get the start time.
     *
     * @return float The start time
     */
    public function get_start_time(): float
    {
        return $this->start_time;
    }

    // Add other methods as needed (e.g., to store summary results)
}
