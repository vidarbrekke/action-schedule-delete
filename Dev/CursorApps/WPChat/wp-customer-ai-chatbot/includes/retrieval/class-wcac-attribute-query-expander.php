<?php

declare(strict_types=1);

/**
 * WCAC Attribute Query Expander: Detects attribute/variant intent in queries.
 * Scans queries for known attribute values (color, size, etc.) to expand search context.
 */
class Wcac_Attribute_Query_Expander
{
    /**
     * Expand a query by detecting attribute/variant values.
     * @param string $query
     * @param array $catalog_attributes [ 'color' => [ 'chalk', 'red', ... ], 'size' => [ 'XL', ... ], ... ]
     * @return array [ 'matched_attributes' => [ 'color' => 'chalk', ... ] ]
     */
    public static function expand_query(string $query, array $catalog_attributes): array
    {
        $query_lc = strtolower($query);
        $matched = [];
        foreach ($catalog_attributes as $attr_type => $values) {
            foreach ($values as $val) {
                $val_lc = strtolower($val);
                // Simple substring match; can be improved with tokenization or fuzzy
                if ($val_lc && strpos($query_lc, $val_lc) !== false) {
                    $matched[$attr_type] = $val;
                }
            }
        }
        return [ 'matched_attributes' => $matched ];
    }
}
