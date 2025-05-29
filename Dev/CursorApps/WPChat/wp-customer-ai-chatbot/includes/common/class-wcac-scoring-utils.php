<?php
class ScoringUtils {
    public static function normalize($value) {
        if (is_array($value)) {
            return array_map([self::class, 'normalize'], $value);
        }
        return strtolower(trim((string)$value));
    }

    public static function split_terms($value) {
        if (is_array($value)) return $value;
        $decoded = json_decode($value, true);
        if (is_array($decoded)) return $decoded;
        return preg_split('/[|,]/', $value);
    }

    public static function match_keyword($field, $keyword) {
        return strpos($field, $keyword) !== false;
    }

    public static function fuzzy_match($needle, $haystack, $max_distance = 1) {
        if (abs(strlen($needle) - strlen($haystack)) > $max_distance) return false;
        return levenshtein($needle, $haystack) <= $max_distance;
    }
} 