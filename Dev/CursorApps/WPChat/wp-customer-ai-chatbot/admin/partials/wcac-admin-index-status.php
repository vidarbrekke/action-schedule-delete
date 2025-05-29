<?php
if (!defined('WPINC')) { die; }
require_once WCAC_PLUGIN_DIR . 'admin/partials/wcac-admin-prune-log-button.php';
// Prepare a nonce for the retry action
$retry_nonce = wp_create_nonce('wcac_retry_failed_summaries');

// --- Summary Statistics ---
global $wpdb;
$table_name = $wpdb->prefix . 'wcac_index';
// Get counts by type
$type_counts = $wpdb->get_results("SELECT post_type, COUNT(*) as count FROM {$table_name} GROUP BY post_type", ARRAY_A);
$counts = [];
$total = 0;
foreach ($type_counts as $row) {
    $type = $row['post_type'];
    $counts[$type] = (int)$row['count'];
    $total += (int)$row['count'];
}
// LLM summary status counts
$status_counts = $wpdb->get_results("SELECT llm_summary_status, COUNT(*) as count FROM {$table_name} GROUP BY llm_summary_status", ARRAY_A);
$llm_stats = [];
foreach ($status_counts as $row) {
    $status = $row['llm_summary_status'];
    $llm_stats[$status] = (int)$row['count'];
}

// --- Table Data Fetch (AJAX support) ---
$per_page = 25;
$offset = isset($_GET['wcac_offset']) ? intval($_GET['wcac_offset']) : 0;
$where = '';
$total_rows = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
$results = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} ORDER BY post_id DESC LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A);

require_once WCAC_PLUGIN_DIR . 'includes/admin/class-wcac-analytics.php';
$analytics = Wcac_Analytics::get_summary_stats();
?>
<div class="wrap">
    <h1><?php esc_html_e('Index Status', 'wp-customer-ai-chatbot'); ?></h1>
    <div class="wcac-analytics-summary" style="margin-bottom:24px; padding:16px; background:#eaf6fb; border:1px solid #b6d6e6; border-radius:6px; max-width:600px;">
        <h2 style="margin-top:0; font-size:1.2em; color:#21759b;">Analytics Summary</h2>
        <ul style="margin:0 0 0 18px; padding:0;">
            <li><?php printf(esc_html__('Total Indexed: %d', 'wp-customer-ai-chatbot'), $analytics['total']); ?></li>
            <li><?php printf(esc_html__('Errors: %d', 'wp-customer-ai-chatbot'), $analytics['error_count']); ?></li>
            <li><?php printf(esc_html__('Successes: %d', 'wp-customer-ai-chatbot'), $analytics['success_count']); ?></li>
            <li><?php printf(esc_html__('Indexed in last 7 days: %d', 'wp-customer-ai-chatbot'), $analytics['recent_indexed']); ?></li>
            <?php foreach ($analytics['by_type'] as $type => $count): ?>
                <li><?php echo esc_html(ucfirst($type)) . ': ' . esc_html($count); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div style="margin-bottom:24px; padding:16px; background:#f8f9fa; border:1px solid #e1e4e8; border-radius:6px; max-width:600px;">
        <h2 style="margin-top:0; font-size:1.2em;">Summary Statistics</h2>
        <table style="width:auto;">
            <tr><th style="text-align:left; padding-right:16px;">Total Indexed Items:</th><td><?php echo esc_html($total); ?></td></tr>
            <?php foreach ($counts as $type => $count): ?>
                <tr><th style="text-align:left; padding-right:16px;"><?php echo esc_html(ucwords(str_replace('_', ' ', $type))); ?>:</th><td><?php echo esc_html($count); ?></td></tr>
            <?php endforeach; ?>
            <?php foreach ($llm_stats as $status => $count): ?>
                <tr><th style="text-align:left; padding-right:16px;">LLM Summaries <?php echo esc_html(ucwords($status)); ?>:</th><td><?php echo esc_html($count); ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php wcac_render_prune_log_button(); ?>
    <form method="post" id="wcac-index-status-form">
        <input type="hidden" name="wcac_retry_nonce" value="<?php echo esc_attr($retry_nonce); ?>">
        <table class="widefat fixed striped" id="wcac-index-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="wcac-select-all"></th>
                    <th><?php esc_html_e('Post ID', 'wp-customer-ai-chatbot'); ?></th>
                    <th><?php esc_html_e('Type', 'wp-customer-ai-chatbot'); ?></th>
                    <th><?php esc_html_e('Title', 'wp-customer-ai-chatbot'); ?></th>
                    <th><?php esc_html_e('LLM Summary Status', 'wp-customer-ai-chatbot'); ?></th>
                    <th><?php esc_html_e('LLM Summary Error', 'wp-customer-ai-chatbot'); ?></th>
                </tr>
            </thead>
            <tbody id="wcac-index-tbody">
                <?php if (!empty($results)) : foreach ($results as $row) :
                    $status = esc_html($row['llm_summary_status'] ?? '');
                    $error = esc_html($row['llm_summary_error'] ?? '');
                    $status_badge = '<span class="match-badge ' . ($status === 'success' ? 'total-score' : ($status === 'error' ? 'wcac-status-error' : 'wcac-status-warning')) . '">' . $status . '</span>';
                    $error_display = $error ? '<span title="' . esc_attr($error) . '">' . esc_html(mb_strimwidth($error, 0, 60, '...')) . '</span>' : '';
                    $can_retry = ($status === 'error');
                ?>
                <tr>
                    <td><input type="checkbox" name="wcac_retry_ids[]" value="<?php echo esc_attr($row['post_id']); ?>" <?php if (!$can_retry) echo 'disabled'; ?>></td>
                    <td><?php echo esc_html($row['post_id']); ?></td>
                    <td><?php echo esc_html($row['post_type']); ?></td>
                    <td><?php echo esc_html($row['title']); ?></td>
                    <td><?php echo $status_badge; ?></td>
                    <td><?php echo $error_display; ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6"><?php esc_html_e('No indexed items found.', 'wp-customer-ai-chatbot'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <p>
            <button type="submit" class="button button-primary" name="wcac_retry_failed" value="1"><?php esc_html_e('Retry Failed Summaries', 'wp-customer-ai-chatbot'); ?></button>
        </p>
        <?php if ($offset + $per_page < $total_rows): ?>
            <button type="button" class="button" id="wcac-load-more" data-offset="<?php echo esc_attr($offset + $per_page); ?>"><?php esc_html_e('Load More', 'wp-customer-ai-chatbot'); ?></button>
        <?php endif; ?>
    </form>
    <script>
    // Simple JS for select all
    document.addEventListener('DOMContentLoaded', function() {
        var selectAll = document.getElementById('wcac-select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('input[name="wcac_retry_ids[]"]:not([disabled])');
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = selectAll.checked;
                }
            });
        }
    });
    // Load More AJAX
    jQuery(document).ready(function($) {
        $('#wcac-load-more').on('click', function() {
            var btn = $(this);
            var offset = btn.data('offset');
            btn.prop('disabled', true).text('Loading...');
            $.get(window.location.pathname, { page: 'wcac-index-status', wcac_offset: offset }, function(data) {
                var newRows = $(data).find('#wcac-index-tbody').html();
                $('#wcac-index-tbody').append(newRows);
                // Update or remove Load More button
                var newBtn = $(data).find('#wcac-load-more');
                if (newBtn.length) {
                    btn.replaceWith(newBtn);
                } else {
                    btn.remove();
                }
            });
        });
    });
    </script>
</div> 