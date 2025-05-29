<?php

/**
 * WCAC Optimizer Constraints: Enforces valid ranges for optimizer parameters.
 * Keeps optimizer from testing known-bad parameter sets.
 */
class Wcac_Optimizer_Constraints
{
    /**
     * Enforce constraints on a parameter set.
     *
     * @param array $params Associative array of parameter keys and values.
     * @return array Adjusted parameter set.
     */
    public static function enforce(array $params): array
    {
        // Enforce maximum for variation product weight
        if (isset($params['wcac_variation_product_weight'])) {
            $params['wcac_variation_product_weight'] = min(10, $params['wcac_variation_product_weight']);
        }
        // Enforce min/max for parent product weight
        if (isset($params['wcac_parent_product_weight'])) {
            $params['wcac_parent_product_weight'] = max(100, min(200, $params['wcac_parent_product_weight']));
        }
        // Enforce minimum for multi-field match bonus
        if (isset($params['wcac_multi_field_match_bonus'])) {
            $params['wcac_multi_field_match_bonus'] = max(50, $params['wcac_multi_field_match_bonus']);
        }
        // Add more constraints here as needed
        return $params;
    }
}
