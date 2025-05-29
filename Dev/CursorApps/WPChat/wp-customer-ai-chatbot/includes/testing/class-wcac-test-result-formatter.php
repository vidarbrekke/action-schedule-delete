<?php

// --- WordPress Core Function Handling ---
if (!defined('ABSPATH')) {
    // Mock WordPress core functions if not in WordPress context
    if (!function_exists('get_the_title')) {
        function get_the_title($post_id)
        {
            return '';
        }
    }
    if (!function_exists('get_post_field')) {
        function get_post_field($field, $post_id)
        {
            return '';
        }
    }
    if (!function_exists('get_edit_post_link')) {
        function get_edit_post_link($post_id)
        {
            return '';
        }
    }
    if (!function_exists('esc_html')) {
        function esc_html($text)
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }
    if (!function_exists('esc_url')) {
        function esc_url($url)
        {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }
    }
    if (!function_exists('trailingslashit')) {
        function trailingslashit($string)
        {
            return rtrim($string, '/\\') . '/';
        }
    }
} else {
    require_once ABSPATH . 'wp-includes/post.php';
    require_once ABSPATH . 'wp-includes/formatting.php';
    require_once ABSPATH . 'wp-includes/link-template.php';
}

require_once WCAC_PLUGIN_DIR . 'includes/common/class-wcac-utils.php';

class Wcac_Test_Result_Formatter
{
    /**
     * Format pass/fail status as colored badge based on actual vs expected results.
     * @param array|null $actual_results_array
     * @param array|null $expected_results_array
     * @return string
     */
    public static function format_pass_fail($actual_results_array, $expected_results_array)
    {
        // Normalize to arrays of strings for comparison
        $expected = is_array($expected_results_array) ? array_map('strval', $expected_results_array) : [];
        $actual = [];
        if (is_array($actual_results_array)) {
            foreach ($actual_results_array as $item) {
                if (is_array($item) && isset($item['id'])) {
                    $actual[] = strval($item['id']);
                } elseif (is_scalar($item)) {
                    $actual[] = strval($item);
                }
            }
        }
        $expected = array_filter($expected, fn($id) => $id !== '' && $id !== null);
        $actual = array_filter($actual, fn($id) => $id !== '' && $id !== null);
        $matches = array_intersect($actual, $expected);
        $num_matches = count($matches);
        $num_actual = count($actual);
        $num_expected = count($expected);
        // All actual results are in expected
        if ($num_actual > 0 && $num_matches === $num_actual && $num_actual <= $num_expected) {
            return '<span style="font-weight:bold; color:#2ecc40;">PASS</span>';
        }
        // Some matches
        if ($num_matches > 0) {
            $color = ($num_matches === 1) ? '#a259c4' : '#ff9800'; // purple for 1, orange for >1
            $label = $num_matches . ' of ' . $num_actual . ' matches';
            return '<span style="font-weight:bold; color:' . esc_html($color) . ';">' . esc_html($label) . '</span>';
        }
        // No matches
        return '<span style="font-weight:bold; color:#ff4136;">FAIL</span>';
    }

    /**
     * Format expected results as an ordered list with IDs, titles, and links if available.
     * Handles strings (CSV, semicolon, or comma-separated), arrays of IDs, arrays of objects, or null/empty input.
     * @param mixed $expected_results_array PHP array or null
     * @return string HTML
     */
    public static function format_expected_results($expected_results_array)
    {
        error_log('WCAC FORMATTER DEBUG: format_expected_results received: ' . print_r($expected_results_array, true));

        // Assume input is already a PHP array (or null)
        if (!is_array($expected_results_array) || empty($expected_results_array)) {
            return '<em>No expected results</em>';
        }

        // Ensure values are integers and filter out <= 0
        $expected_ids = array_unique(array_map('intval', $expected_results_array));
        $expected_ids = array_filter($expected_ids, function ($id) {
            return $id > 0;
        });

        if (empty($expected_ids)) {
            return '<em>No valid expected IDs</em>';
        }

        $out = '<ol style="margin:0; padding-left:18px;">';
        foreach ($expected_ids as $id) {
            $title = function_exists('get_the_title') ? get_the_title($id) : '';
            $slug = '';
            if (!$title && function_exists('get_post_field')) {
                $slug = get_post_field('post_name', $id);
            }
            $edit_url = function_exists('get_edit_post_link') ? get_edit_post_link($id) : '';

            $label = "ID: $id"; // Default label
            if ($title) {
                $label = esc_html($title) . " (ID: $id)";
            } elseif ($slug) {
                $label = esc_html($slug) . " (slug, ID: $id)";
            }

            if ($edit_url) {
                $label = '<a href="' . esc_url($edit_url) . '" target="_blank">' . $label . '</a>';
            }
            $out .= '<li>' . $label . '</li>';
        }
        $out .= '</ol>';
        return $out;
    }

    /**
     * Format actual results as an ordered list, highlighting matches to expected IDs.
     * Assumes $actual_results_array is an array of result items (or null)
     * Assumes $expected_ids_array is an array of expected IDs (or null)
     * @param array|null $actual_results_array
     * @param array|null $expected_ids_array
     * @return string HTML
     */
    public static function format_actual_results($actual_results_array, $expected_ids_array = null)
    {
        error_log('WCAC FORMATTER DEBUG: format_actual_results received actual: ' . print_r($actual_results_array, true));
        error_log('WCAC FORMATTER DEBUG: format_actual_results received expected: ' . print_r($expected_ids_array, true));

        // Normalize expected IDs to an array of strings for comparison
        $expected_ids_str = [];
        if (is_array($expected_ids_array)) {
            $expected_ids_str = array_map('strval', $expected_ids_array);
        }
        $expected_ids_str = array_filter($expected_ids_str, function ($id) {
            return $id !== '' && $id !== null;
        });

        if (!is_array($actual_results_array) || empty($actual_results_array)) {
            return '<em>No results</em>';
        }

        // Check if it's an array of result objects (like from ContentRetriever)
        if (isset($actual_results_array[0]) && is_array($actual_results_array[0]) && isset($actual_results_array[0]['id'])) {
            $out = '<ol style="margin:0; padding-left:18px;">';
            foreach ($actual_results_array as $r) {
                $id_str = isset($r['id']) ? strval($r['id']) : '0';
                $id_int = intval($id_str);
                $is_expected = in_array($id_str, $expected_ids_str, true);

                $title = isset($r['title']) ? $r['title'] : (function_exists('get_the_title') ? get_the_title($id_int) : '');
                $slug = '';
                if (!$title && function_exists('get_post_field')) {
                    $slug = get_post_field('post_name', $id_int);
                }
                $edit_url = function_exists('get_edit_post_link') ? get_edit_post_link($id_int) : '';

                $label = "ID: $id_str"; // Default label
                if ($title) {
                    $label = esc_html($title) . " (ID: $id_str)";
                } elseif ($slug) {
                    $label = esc_html($slug) . " (slug, ID: $id_str)";
                }

                if ($edit_url) {
                    $label = '<a href="' . esc_url($edit_url) . '" target="_blank">' . $label . '</a>';
                }
                if ($is_expected) {
                    $label = '<span class="match-badge" style="background:#e6ffe8;border:1px solid #b3deba;">EXPECTED</span> ' . $label;
                }
                $out .= '<li>' . $label . '</li>';
            }
            $out .= '</ol>';
            return $out;
        } else {
            // If it's not an array of result objects, maybe it's just an array of IDs (handle legacy or error cases)
            // This part might need adjustment based on actual data structure if the above block fails.
            $out = '<ol style="margin:0; padding-left:18px;">';
            foreach ($actual_results_array as $item) {
                 $id_str = is_scalar($item) ? strval($item) : (isset($item['id']) ? strval($item['id']) : '0');
                 $id_int = intval($id_str);
                 $is_expected = in_array($id_str, $expected_ids_str, true);

                if ($id_int > 0) {
                     $title = function_exists('get_the_title') ? get_the_title($id_int) : '';
                     $slug = '';
                    if (!$title && function_exists('get_post_field')) {
                        $slug = get_post_field('post_name', $id_int);
                    }
                     $edit_url = function_exists('get_edit_post_link') ? get_edit_post_link($id_int) : '';

                     $label = "ID: $id_str"; // Default
                    if ($title) {
                        $label = esc_html($title) . " (ID: $id_str)";
                    } elseif ($slug) {
                        $label = esc_html($slug) . " (slug, ID: $id_str)";
                    }

                    if ($edit_url) {
                        $label = '<a href="' . esc_url($edit_url) . '" target="_blank">' . $label . '</a>';
                    }
                    if ($is_expected) {
                        $label = '<span class="match-badge" style="background:#e6ffe8;border:1px solid #b3deba;">EXPECTED</span> ' . $label;
                    }
                     $out .= '<li>' . $label . '</li>';
                } else {
                    $out .= '<li><em>Invalid item in results</em></li>';
                }
            }
             $out .= '</ol>';
            return $out;
        }
    }

    /**
     * Format pass/fail status as colored badges for each expected ID.
     * @param mixed $pass_fail
     * @param mixed $expected_results
     * @return string
     */
    public static function format_pass_fail_detailed($pass_fail, $expected_results = null)
    {
        // Parse expected IDs
        $expected_ids = [];
        if (is_string($expected_results)) {
            $expected_ids = array_filter(array_map('trim', preg_split('/[;,]/', $expected_results)));
        } elseif (is_array($expected_results)) {
            $expected_ids = array_map('strval', $expected_results);
        }
        $expected_ids = array_filter($expected_ids, function ($id) {
            return (string)$id !== '' && $id !== null;
        });
        $pass_ids = [];
        $fail_ids = [];
        if (is_string($pass_fail) && strpos($pass_fail, 'PASS ID ') === 0) {
            $pass_ids = array_filter(array_map('trim', wcac_safe_split(',', substr($pass_fail, 8))));
        } elseif (is_string($pass_fail) && strpos($pass_fail, 'FAIL ID ') === 0) {
            $fail_ids = array_filter(array_map('trim', wcac_safe_split(',', substr($pass_fail, 8))));
        }
        // If both, parse both
        if (is_string($pass_fail) && strpos($pass_fail, 'PASS ID ') === 0 && strpos($pass_fail, 'FAIL ID ') !== false) {
            $parts = wcac_safe_split('FAIL ID ', $pass_fail);
            $pass_ids = array_filter(array_map('trim', wcac_safe_split(',', substr($parts[0], 8))));
            $fail_ids = array_filter(array_map('trim', wcac_safe_split(',', $parts[1])));
        }
        $out = '';
        foreach ($expected_ids as $id) {
            if (in_array($id, $pass_ids, true)) {
                $out .= '<span class="match-badge" style="background:#e6ffe8;border:1px solid #b3deba;margin-right:4px;">PASS ID ' . esc_html($id) . '</span>';
            } elseif (in_array($id, $fail_ids, true)) {
                $out .= '<span class="match-badge" style="background:#ffecec;border:1px solid #ffb3b3;margin-right:4px;">FAIL ID ' . esc_html($id) . '</span>';
            } else {
                $out .= '<span class="match-badge" style="background:#f0f0f0;border:1px solid #ccc;margin-right:4px;">N/A ID ' . esc_html($id) . '</span>';
            }
        }
        if ($out === '') {
            $out = esc_html($pass_fail);
        }
        return $out;
    }

    /**
     * Format scoring breakdown as a collapsible table for each actual result.
     * @param array|null $actual_results_array The array of actual result items, potentially containing scoring_breakdown.
     * @return string HTML
     */
    public static function format_scoring_breakdown($actual_results_array)
    {
         error_log('WCAC FORMATTER DEBUG: format_scoring_breakdown received: ' . print_r($actual_results_array, true));
        try {
            // Iterate over the actual results array to find scoring breakdowns
            if (is_array($actual_results_array) && !empty($actual_results_array)) {
                $out = '';
                $idx = 0;
                foreach ($actual_results_array as $r) {
                    // Ensure $r is an array
                    if (is_object($r)) {
                        $r = (array)$r;
                    }

                    if (is_array($r) && isset($r['scoring_breakdown']) && is_array($r['scoring_breakdown'])) {
                        $idx++;
                        $toggle_id = 'wcac-score-toggle-' . uniqid() . '-' . $idx;
                        $result_id = isset($r['id']) ? esc_html($r['id']) : 'N/A';
                        $result_title = isset($r['title']) ? esc_html($r['title']) : 'Result';

                        $out .= '<div style="margin-bottom:8px; border-top: 1px dashed #eee; padding-top: 5px;">'; // Add separator
                        // Use title in toggle if available
                        $out .= '<a href="#" onclick="var el=document.getElementById(\'' . $toggle_id . '\');el.style.display=el.style.display==\'none\'?\'block\':\'none\';return false;" style="font-size:12px; text-decoration: none;">' . $result_title . ' (ID: ' . $result_id . ') - Show/Hide Scoring</a>';
                        $out .= '<div id="' . $toggle_id . '" style="display:none; margin-top: 5px;">';
                        $out .= '<table class="wcac-scoring-breakdown-table" style="font-size:11px; width: auto;">';
                        $out .= '<thead><tr><th>Factor</th><th>Score</th></tr></thead><tbody>';

                        $total_score = 0;
                        $breakdown = $r['scoring_breakdown'];
                        // Sort breakdown factors for consistency (optional)
                        // ksort($breakdown);

                        foreach ($breakdown as $k => $v) {
                            // Format key nicely (e.g., 'title_match_score' -> 'Title Match')
                            $formatted_key = ucwords(str_replace(['_', 'score', 'boost', 'weight', 'penalty'], ' ', $k));
                            $formatted_key = preg_replace('/\s+/', ' ', trim($formatted_key)); // Clean up extra spaces

                            $value_display = '';
                            if (is_numeric($v)) {
                                $value_display = number_format(floatval($v), 2);
                                $total_score += floatval($v);
                            } elseif (is_array($v)) {
                                // Optionally display simple arrays (like matched keywords)
                                $value_display = esc_html(implode(', ', $v));
                            } else {
                                $value_display = esc_html((string)$v);
                            }

                            $out .= '<tr><td style="padding: 2px 8px 2px 0;">' . esc_html($formatted_key) . '</td><td style="padding: 2px 0; text-align: right;">' . $value_display . '</td></tr>';
                        }
                        // Add Total Row
                         $out .= '<tr style="font-weight: bold; border-top: 1px solid #ccc;"><td style="padding: 2px 8px 2px 0;">Total Calculated</td><td style="padding: 2px 0; text-align: right;">' . number_format($total_score, 2) . '</td></tr>';

                        $out .= '</tbody></table>';
                        $out .= '</div></div>';
                    } else {
                         // Handle cases where a result item doesn't have a breakdown (or it's not an array)
                         // $out .= '<div style="font-size: 11px; color: #888;"><em>No scoring breakdown for this item.</em></div>';
                    }
                }
                if ($out) {
                    return $out; // Return the formatted breakdowns if any were found
                }
            }

            // Fallback if no breakdowns were found or input was invalid
            return '<em>No breakdown available</em>';
        } catch (\Throwable $e) {
            error_log("WCAC FORMATTER ERROR in format_scoring_breakdown: " . $e->getMessage());
            return '<em>Error formatting breakdown</em>';
        }
    }
}
