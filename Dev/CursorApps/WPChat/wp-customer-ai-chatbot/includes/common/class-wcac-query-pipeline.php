<?php
class Wcac_Query_Pipeline {
    private array $steps = [];

    public function __construct(array $steps) {
        $this->steps = $steps;
    }

    public function process(string $query): array {
        $context = ['query' => $query];
        foreach ($this->steps as $step) {
            $context = $step($context);
        }
        return $context;
    }
} 