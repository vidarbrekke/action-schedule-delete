<?php

declare(strict_types=1);

/**
 * Renders the Debug Logs admin page for WP Customer AI Chatbot.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/admin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure necessary WordPress functions are available for static analysis and CLI/test contexts
if (!function_exists('esc_html__')) {
    require_once ABSPATH . 'wp-includes/l10n.php';
}
if (!function_exists('settings_errors')) {
    require_once ABSPATH . 'wp-admin/includes/template.php';
}
if (!function_exists('wp_nonce_field')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('esc_attr_e')) {
    require_once ABSPATH . 'wp-includes/l10n.php';
}
if (!function_exists('paginate_links')) {
    require_once ABSPATH . 'wp-includes/general-template.php';
}
if (!function_exists('add_query_arg')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}

// Include necessary class
require_once WCAC_PLUGIN_DIR . 'includes/common/class-wcac-debug-logger.php';
require_once WCAC_PLUGIN_DIR . 'includes/common/context-utils.php';

class Wcac_Admin_Debug_Logs
{
    /**
     * Render the Debug Logs page.
     */
    public static function render(): void
    {
        if (class_exists('Wcac_Debug_Logger')) {
            Wcac_Debug_Logger::init();
        }
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $logs = Wcac_Debug_Logger::get_logs($page, $per_page);
        $total_logs = Wcac_Debug_Logger::get_total_logs();
        $total_pages = ceil($total_logs / $per_page);
        ?>
        <div class="wrap wcac-debug-logs-page">
            <?php if (!function_exists('esc_html__')) {
                require_once ABSPATH . 'wp-includes/l10n.php';
            } ?>
            <h1><?php if (!function_exists('esc_html__')) {
                require_once ABSPATH . 'wp-includes/l10n.php';
                } echo esc_html__('Debug Logs', 'wp-customer-ai-chatbot'); ?></h1>
            <p style="font-size:14px; color:#666; max-width:700px;">This page shows live debug logs for chatbot queries, keyword extraction, and scoring. Use for troubleshooting and development. <a href="admin.php?page=wcac-test-results">Switch to Test Results</a></p>
            <?php if (!function_exists('settings_errors')) {
                require_once ABSPATH . 'wp-admin/includes/template.php';
            } settings_errors('wcac_messages'); ?>
            <div class="wcac-log-actions">
                <?php if (!empty($logs)) : ?>
                    <button type="button" id="wcac-delete-all-debug-logs" class="button button-secondary">Delete All Logs</button>
                <?php endif; ?>
                <form method="post" action="" class="wcac-log-action-form">
                    <?php if (!function_exists('wp_nonce_field')) {
                        require_once ABSPATH . 'wp-includes/functions.php';
                    } wp_nonce_field('wcac_test_logging', 'wcac_test_logging_nonce'); ?>
                    <input type="hidden" name="action" value="test_logging">
                    <?php if (!function_exists('esc_attr_e')) {
                        require_once ABSPATH . 'wp-includes/l10n.php';
                    } ?>
                    <input type="submit" class="button button-secondary" value="<?php if (!function_exists('esc_attr_e')) {
                        require_once ABSPATH . 'wp-includes/l10n.php';
                                                                                } esc_attr_e('Test Logging', 'wp-customer-ai-chatbot'); ?>">
                </form>
            </div>
            <?php if (empty($logs)) : ?>
                <?php if (!function_exists('esc_html__')) {
                    require_once ABSPATH . 'wp-includes/l10n.php';
                } ?>
                <p><?php if (!function_exists('esc_html__')) {
                    require_once ABSPATH . 'wp-includes/l10n.php';
                   } echo esc_html__('No debug logs found.', 'wp-customer-ai-chatbot'); ?></p>
            <?php else : ?>
                <form id="wcac-debug-logs-form" method="post" action="">
                    <?php if (!function_exists('wp_nonce_field')) {
                        require_once ABSPATH . 'wp-includes/functions.php';
                    } wp_nonce_field('wcac_delete_logs', 'wcac_nonce'); ?>
                    <input type="hidden" name="action" value="delete_logs">
                    <div class="tablenav top">
                        <div class="alignleft actions">
                            <button type="button" id="wcac-delete-selected-debug-logs" class="button action">Delete Selected Logs</button>
                        </div>
                    </div>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all-1">
                                </td>
                                <?php if (!function_exists('esc_html__')) {
                                    require_once ABSPATH . 'wp-includes/l10n.php';
                                } ?>
                                <th><?php if (!function_exists('esc_html__')) {
                                    require_once ABSPATH . 'wp-includes/l10n.php';
                                    } echo esc_html__('Created At', 'wp-customer-ai-chatbot'); ?></th>
                                <th><?php if (!function_exists('esc_html__')) {
                                    require_once ABSPATH . 'wp-includes/l10n.php';
                                    } echo esc_html__('Query', 'wp-customer-ai-chatbot'); ?></th>
                                <th><?php if (!function_exists('esc_html__')) {
                                    require_once ABSPATH . 'wp-includes/l10n.php';
                                    } echo esc_html__('Keywords', 'wp-customer-ai-chatbot'); ?></th>
                                <th class="log-row-indicator" style="text-align:center;width:24px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                            <tr class="log-row" data-log-id="<?php echo esc_attr($log['id']); ?>">
                                <td class="check-column">
                                    <input type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log['id']); ?>">
                                </td>
                                <td class="column-created_at"><?php echo esc_html($log['created_at']); ?></td>
                                <td class="column-query"><?php echo esc_html($log['query']); ?></td>
                                <td class="column-keywords"><?php
                                    $keywords = json_decode($log['keywords'], true);
                                    echo esc_html(is_array($keywords) ? implode(', ', $keywords) : $keywords);
                                ?></td>
                                <td class="log-row-indicator" style="text-align:center;width:24px;">
                                    <span class="dashicons dashicons-arrow-down"></span>
                                </td>
                            </tr>
                            <tr id="log-details-<?php echo esc_attr($log['id']); ?>" class="log-details-row">
                                <td colspan="5" class="log-details-cell">
                                    <div class="log-details-content">
                                        <?php
                                        try {
                                            // Display Raw Candidates
                                            $raw_candidates = !empty($log['raw_candidates_data']) ? json_decode($log['raw_candidates_data'], true) : null;
                                            if (json_last_error() !== JSON_ERROR_NONE) {
                                                echo '<div class="notice notice-warning">Error decoding raw candidates data: ' . esc_html(json_last_error_msg()) . '</div>';
                                                $raw_candidates = null;
                                            }
                                            if (is_array($raw_candidates)) {
                                                echo '<h5>Raw Candidates Retrieved (' . count($raw_candidates) . ')</h5>';
                                                if (empty($raw_candidates)) {
                                                    echo '<p><i>None</i></p>';
                                                } else {
                                                    echo '<ul class="raw-candidates-list">';
                                                    foreach (array_slice($raw_candidates, 0, 20) as $candidate) { // Limit display
                                                        echo '<li>ID: ' . esc_html($candidate['id'] ?? 'N/A') .
                                                             ' - Title: ' . esc_html($candidate['title'] ?? 'N/A') .
                                                             ' (Score: ' . esc_html(isset($candidate['score']) ? number_format((float)$candidate['score'], 2) : 'Pre-score') . ')</li>';
                                                    }
                                                    if (count($raw_candidates) > 20) {
                                                        echo '<li><i>...and ' . (count($raw_candidates) - 20) . ' more</i></li>';
                                                    }
                                                    echo '</ul>';
                                                }
                                            }

                                            // Display Products/Scoring Data (Existing Logic)
                                            echo '<h5>Scoring & Breakdown Data</h5>';
                                            $products_data = json_decode($log['products_data'], true);
                                            if (json_last_error() !== JSON_ERROR_NONE) {
                                                throw new \Exception('Malformed products_data JSON: ' . json_last_error_msg());
                                            }
                                            if (is_array($products_data)) {
                                                foreach ($products_data as $result) {
                                                    echo '<div class="result-item">';
                                                    echo '<h4>' . esc_html($result['title'] ?? '') . '</h4>';
                                                    echo '<div class="matched-fields-summary">';
                                                    if (isset($result['score'])) {
                                                        $scoreVal = $result['score'];
                                                        $scoreDisplay = (is_float($scoreVal) || is_int($scoreVal)) ? number_format($scoreVal, 2) : 'N/A';
                                                        echo '<span class="match-badge total-score">Total Score: ' . esc_html($scoreDisplay) . '</span>';
                                                    }
                                                    if (isset($result['matched_fields']) && is_array($result['matched_fields'])) {
                                                        foreach ($result['matched_fields'] as $field => $score) {
                                                            if ($score > 0) {
                                                                $class = str_replace('_match', '', $field);
                                                                $label = ucfirst(str_replace('_match', '', $field));
                                                                $scoreDisplay = (is_float($score) || is_int($score)) ? number_format($score, 2) : 'N/A';
                                                                echo '<span class="match-badge ' . esc_attr($class) . '">' .
                                                                     esc_html($label) . ': ' .
                                                                     esc_html($scoreDisplay) . '</span>';
                                                            }
                                                        }
                                                    }
                                                    echo '</div>';
                                                    $scoring_breakdown = $result['scoring_breakdown'] ?? null;
                                                    if ($scoring_breakdown && is_array($scoring_breakdown)) {
                                                        echo '<div class="scoring-breakdown">';
                                                        echo '<h5>Detailed Scoring Breakdown</h5>';
                                                        echo '<table class="scoring-table">';
                                                        echo '<tr><th>Scoring Factor</th><th>Points</th><th>Details</th></tr>';
                                                        $scoring_factors = $scoring_breakdown;
                                                        uasort($scoring_factors, function ($a, $b) {
                                                            return $b <=> $a;
                                                        });
                                                        $total_score = 0;
                                                        foreach ($scoring_factors as $field => $score) {
                                                            // Ensure score is numeric
                                                            if (is_string($score)) {
                                                                $score = floatval($score);
                                                            } elseif (!is_numeric($score)) {
                                                                $score = 0;
                                                            }
                                                            
                                                            $class = str_replace('_match', '', $field);
                                                            $label = ucfirst(str_replace('_', ' ', str_replace('_match', '', $field)));
                                                            $scoreDisplay = (is_float($score) || is_int($score)) ? number_format($score, 2) : 'N/A';
                                                            echo '<tr class="score-row score-' . esc_attr($class) . '">';
                                                            echo '<td>' . esc_html($label) . '</td>';
                                                            echo '<td>' . esc_html($scoreDisplay) . '</td>';
                                                            echo '<td></td>';
                                                            echo '</tr>';
                                                            
                                                            // Only add to total if numeric
                                                            if (is_numeric($score)) {
                                                                $total_score += (float)$score;
                                                            }
                                                        }
                                                        
                                                        // Ensure total score is numeric
                                                        $total_score = is_numeric($total_score) ? (float)$total_score : 0;
                                                        
                                                        echo '<tr class="score-row score-total">';
                                                        echo '<td><strong>Total Score</strong></td>';
                                                        echo '<td><strong>' . number_format($total_score, 2) . '</strong></td>';
                                                        echo '<td></td>';
                                                        echo '</tr>';
                                                        echo '</table>';
                                                        echo '</div>';
                                                    } elseif (isset($result['matched_fields']) && is_array($result['matched_fields'])) {
                                                        echo '<div class="scoring-breakdown">';
                                                        echo '<h5>Detailed Scoring Breakdown</h5>';
                                                        echo '<table class="scoring-table">';
                                                        echo '<tr><th>Scoring Factor</th><th>Points</th><th>Details</th></tr>';
                                                        $scoring_factors = [];
                                                        foreach ($result['matched_fields'] as $field => $score) {
                                                            if (is_array($score)) {
                                                                $actual_score = 0;
                                                                $details = json_encode($score);
                                                                $scoring_factors[$field] = [
                                                                    'score' => $actual_score,
                                                                    'details' => $details
                                                                ];
                                                            } else {
                                                                $scoring_factors[$field] = [
                                                                    'score' => $score,
                                                                    'details' => ''
                                                                ];
                                                            }
                                                        }
                                                        uasort($scoring_factors, function ($a, $b) {
                                                            return $b['score'] <=> $a['score'];
                                                        });
                                                        $total_score = 0;
                                                        foreach ($scoring_factors as $field => $data) {
                                                            // Ensure we have valid data structure
                                                            if (!is_array($data)) {
                                                                continue;
                                                            }
                                                            
                                                            // Get score with proper type handling
                                                            $score = isset($data['score']) ? $data['score'] : 0;
                                                            // Convert string scores to float
                                                            if (is_string($score)) {
                                                                $score = floatval($score);
                                                            } elseif (!is_numeric($score)) {
                                                                $score = 0;
                                                            }
                                                            
                                                            $details = isset($data['details']) ? $data['details'] : '';
                                                            $class = str_replace('_match', '', $field);
                                                            $label = ucfirst(str_replace('_', ' ', str_replace('_match', '', $field)));
                                                            $scoreDisplay = (is_float($score) || is_int($score)) ? number_format($score, 2) : 'N/A';
                                                            
                                                            echo '<tr class="score-row score-' . esc_attr($class) . '">';
                                                            echo '<td>' . esc_html($label) . '</td>';
                                                            echo '<td>' . esc_html($scoreDisplay) . '</td>';
                                                            echo '<td>' . esc_html($details) . '</td>';
                                                            echo '</tr>';
                                                            
                                                            // Only add to total if numeric
                                                            if (is_numeric($score)) {
                                                                $total_score += (float)$score;
                                                            }
                                                        }
                                                        
                                                        // Ensure total score is numeric
                                                        $total_score = is_numeric($total_score) ? (float)$total_score : 0;
                                                        
                                                        echo '<tr class="score-row score-total">';
                                                        echo '<td><strong>Total Score</strong></td>';
                                                        echo '<td><strong>' . number_format($total_score, 2) . '</strong></td>';
                                                        echo '<td></td>';
                                                        echo '</tr>';
                                                        echo '</table>';
                                                        echo '</div>';
                                                    }
                                                    echo '</div>';
                                                }
                                            } else {
                                                echo '<div class="notice notice-warning">No product data available for this log.</div>';
                                            }

                                            // Display Final Filtered Results
                                            $final_results = !empty($log['final_results_data']) ? json_decode($log['final_results_data'], true) : null;
                                            if (json_last_error() !== JSON_ERROR_NONE) {
                                                echo '<div class="notice notice-warning">Error decoding final results data: ' . esc_html(json_last_error_msg()) . '</div>';
                                                $final_results = null;
                                            }
                                            if (is_array($final_results)) {
                                                echo '<h5>Final Filtered Results (' . count($final_results) . ')</h5>';
                                                if (empty($final_results)) {
                                                    echo '<p><i>None (Filtered out or low confidence)</i></p>';
                                                } else {
                                                    echo '<ul class="final-results-list">';
                                                    foreach ($final_results as $result) { // Typically limited already
                                                        echo '<li>ID: ' . esc_html($result['id'] ?? 'N/A') .
                                                             ' - Title: ' . esc_html($result['title'] ?? 'N/A') .
                                                             ' (Score: ' . esc_html(isset($result['score']) ? number_format((float)$result['score'], 2) : 'N/A') . ')</li>';
                                                    }
                                                    echo '</ul>';
                                                }
                                            }
                                        } catch (\Throwable $e) {
                                            echo '<div class="notice notice-error">Error rendering log details: ' . esc_html($e->getMessage()) . '</div>';
                                        }
                                        ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
                <?php if ($total_pages > 1) : ?>
                    <?php if (!function_exists('paginate_links')) {
                        require_once ABSPATH . 'wp-includes/general-template.php';
                    } ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $page
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}
