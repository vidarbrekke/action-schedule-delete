<?php
/**
 * Plugin Name: Action Scheduler Cleanup
 * Description: Automatically cleans failed actions and completed actions older than 30 days. Adds Tools page for manual run.
 * Version: 1.0.0
 * Author: MotherKnitter Ops
 * Text Domain: action-scheduler-cleanup
 */

if (!defined('ABSPATH')) {
	exit;
}

final class MK_Action_Scheduler_Cleanup {
	public function __construct() {
		add_filter('cron_schedules', [$this, 'add_every_30_days_schedule']);
		add_action('init', [$this, 'ensure_scheduled_event']);
		add_action('action_scheduler_cleanup_cron', [$this, 'cleanup_action_scheduler']);

		// Admin UI
		add_action('admin_menu', [$this, 'add_admin_menu']);
		add_action('wp_ajax_action_scheduler_cleanup_run', [$this, 'manual_cleanup']);
		add_action('wp_ajax_action_scheduler_cleanup_clear_history', [$this, 'clear_history']);
	}

	public static function activate() {
		self::instance()->ensure_scheduled_event();
		// Initialize history option without autoload to avoid front-end memory usage
		if (get_option('mk_asc_history', null) === null) {
			add_option('mk_asc_history', [], '', 'no');
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook('action_scheduler_cleanup_cron');
	}

	public static function instance() {
		static $inst = null;
		if ($inst === null) {
			$inst = new self();
		}
		return $inst;
	}

	public function add_every_30_days_schedule($schedules) {
		$schedules['every_30_days'] = [
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __('Every 30 Days', 'action-scheduler-cleanup'),
		];
		return $schedules;
	}

	public function ensure_scheduled_event() {
		if (!wp_next_scheduled('action_scheduler_cleanup_cron')) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'every_30_days', 'action_scheduler_cleanup_cron');
		}
	}

	public function cleanup_action_scheduler() {
		global $wpdb;
		$prefix = $wpdb->prefix;
		$results = [
			'failed_actions'    => 0,
			'completed_actions' => 0,
			'orphaned_logs'     => 0,
			'success'          => true,
			'error'            => null,
		];

		// Prevent concurrent cleanup operations
		$lock_key = 'mk_action_scheduler_cleanup_lock';
		if (get_transient($lock_key)) {
			$results['success'] = false;
			$results['error'] = 'Cleanup already running';
			$entry = $this->log_cleanup($results);
			$results['history_entry'] = $entry;
			return $results;
		}

		// Set lock for 60 minutes to cover large cleanups
		set_transient($lock_key, true, 60 * MINUTE_IN_SECONDS);

		try {
			// Check if tables exist
			if (!$this->tables_exist($prefix)) {
				$results['success'] = false;
				$results['error'] = 'Action Scheduler tables not found';
				$entry = $this->log_cleanup($results);
				$results['history_entry'] = $entry;
				return $results;
			}

			// Delete failed actions with batching
			$results['failed_actions'] = $this->delete_with_limit(
				"DELETE FROM {$prefix}actionscheduler_actions WHERE status = %s LIMIT %d",
				['failed', 1000]
			);

			// Delete completed actions older than 30 days with batching
			$results['completed_actions'] = $this->delete_with_limit(
				"DELETE FROM {$prefix}actionscheduler_actions WHERE status = %s AND scheduled_date_gmt < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) LIMIT %d",
				['complete', 30, 1000]
			);

			// Delete orphaned logs with optimized query
			$results['orphaned_logs'] = $this->delete_orphaned_logs($prefix);
		} catch (Exception $e) {
			$results['success'] = false;
			$results['error'] = $e->getMessage();
		} finally {
			// Always clean up the lock
			delete_transient($lock_key);
		}

		$entry = $this->log_cleanup($results);
		$results['history_entry'] = $entry;
		return $results;
	}

	/**
	 * Check if Action Scheduler tables exist
	 */
	private function tables_exist($prefix) {
		global $wpdb;
		$tables = [
			$prefix . 'actionscheduler_actions',
			$prefix . 'actionscheduler_logs'
		];

		foreach ($tables as $table) {
			$result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
			if (!$result) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Delete records with batching support
	 */
	private function delete_with_limit($query, $args) {
		global $wpdb;
		$total_deleted = 0;

		do {
			$rows_affected = $wpdb->query($wpdb->prepare($query, ...$args));
			if ($rows_affected === false) {
				throw new Exception('Database query failed: ' . $wpdb->last_error);
			}
			$total_deleted += $rows_affected;
		} while ($rows_affected > 0);

		return $total_deleted;
	}

	/**
	 * Delete orphaned logs with optimized query
	 */
	private function delete_orphaned_logs($prefix) {
		global $wpdb;
		$total_deleted = 0;

		do {
			// Select a batch of orphaned log IDs
			$ids = $wpdb->get_col(
				"SELECT al.log_id
				 FROM {$prefix}actionscheduler_logs al
				 LEFT JOIN {$prefix}actionscheduler_actions aa ON al.action_id = aa.action_id
				 WHERE aa.action_id IS NULL
				 LIMIT 1000"
			);

			if (empty($ids)) {
				break;
			}

			$placeholders = implode(',', array_fill(0, count($ids), '%d'));
			$sql = "DELETE FROM {$prefix}actionscheduler_logs WHERE log_id IN ($placeholders)";
			$deleted = $wpdb->query($wpdb->prepare($sql, $ids));
			if ($deleted === false) {
				throw new Exception('Failed to delete orphaned logs: ' . $wpdb->last_error);
			}
			$total_deleted += (int) $deleted;
		} while (!empty($ids));

		return $total_deleted;
	}

	/**
	 * Enhanced logging with proper error handling
	 */
	private function log_cleanup($results) {
		$log_message = sprintf(
			'[Action Scheduler Cleanup] Success: %s, Failed: %d, Completed: %d, Orphaned: %d%s',
			$results['success'] ? 'Yes' : 'No',
			$results['failed_actions'],
			$results['completed_actions'],
			$results['orphaned_logs'],
			$results['error'] ? ', Error: ' . $results['error'] : ''
		);

		// Always log errors, only log success in debug mode
		if (!$results['success'] || (defined('WP_DEBUG') && WP_DEBUG)) {
			error_log($log_message);
		}

		$entry = [
			'time_gmt'          => gmdate('Y-m-d H:i:s'),
			'trigger'           => (defined('DOING_CRON') && DOING_CRON) ? 'cron' : ((defined('DOING_AJAX') && DOING_AJAX) ? 'manual' : 'manual'),
			'failed_actions'    => (int) ($results['failed_actions'] ?? 0),
			'completed_actions' => (int) ($results['completed_actions'] ?? 0),
			'orphaned_logs'     => (int) ($results['orphaned_logs'] ?? 0),
			'success'           => (bool) ($results['success'] ?? false),
			'error'             => $results['error'] ?? null,
		];

		$this->append_history($entry);
		return $entry;
	}

	/**
	 * Persist recent history (capped)
	 */
	private function append_history($entry) {
		$history = get_option('mk_asc_history', []);
		if (!is_array($history)) {
			$history = [];
		}
		array_unshift($history, $entry);
		// Cap to last 50 entries
		$history = array_slice($history, 0, 50);
		update_option('mk_asc_history', $history, false);
	}

	private function get_history($limit = 20) {
		$history = get_option('mk_asc_history', []);
		if (!is_array($history)) {
			return [];
		}
		return array_slice($history, 0, max(0, (int) $limit));
	}

	public function clear_history() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized']);
		}
		check_ajax_referer('action_scheduler_cleanup_nonce', 'nonce');
		update_option('mk_asc_history', [], false);
		wp_send_json_success(['message' => __('History cleared.', 'action-scheduler-cleanup')]);
	}

	public function add_admin_menu() {
		add_management_page(
			__('Action Scheduler Cleanup', 'action-scheduler-cleanup'),
			__('Action Scheduler Cleanup', 'action-scheduler-cleanup'),
			'manage_options',
			'action-scheduler-cleanup',
			[$this, 'render_tools_page']
		);
	}

	public function render_tools_page() {
		if (!current_user_can('manage_options')) {
			return;
		}
		$next = wp_next_scheduled('action_scheduler_cleanup_cron');
		?>
		<div class="wrap">
			<h1><?php esc_html_e('Action Scheduler Cleanup', 'action-scheduler-cleanup'); ?></h1>
			<p><?php esc_html_e('Automatically cleans failed actions and completed actions older than 30 days. You can also run it manually.', 'action-scheduler-cleanup'); ?></p>
			<p><strong><?php esc_html_e('Next scheduled run:', 'action-scheduler-cleanup'); ?></strong> <?php echo $next ? esc_html(gmdate('Y-m-d H:i:s', $next)) . ' UTC' : esc_html__('Not scheduled', 'action-scheduler-cleanup'); ?></p>

			<h2 class="title"><?php esc_html_e('Manual Run', 'action-scheduler-cleanup'); ?></h2>
			<button id="mk-asc-run" class="button button-primary"><?php esc_html_e('Run Cleanup Now', 'action-scheduler-cleanup'); ?></button>
			<pre id="mk-asc-result" style="margin-top:12px;background:#fff;border:1px solid #ccd0d4;padding:12px;display:none;"></pre>

			<h2 class="title" style="margin-top:20px;"><?php esc_html_e('History (last 20)', 'action-scheduler-cleanup'); ?></h2>
			<p>
				<button id="mk-asc-clear-history" class="button"><?php esc_html_e('Clear History', 'action-scheduler-cleanup'); ?></button>
			</p>
			<table class="widefat fixed striped" id="mk-asc-history">
				<thead>
					<tr>
						<th><?php esc_html_e('Time (UTC)', 'action-scheduler-cleanup'); ?></th>
						<th><?php esc_html_e('Trigger', 'action-scheduler-cleanup'); ?></th>
						<th><?php esc_html_e('Failed', 'action-scheduler-cleanup'); ?></th>
						<th><?php esc_html_e('Completed', 'action-scheduler-cleanup'); ?></th>
						<th><?php esc_html_e('Logs', 'action-scheduler-cleanup'); ?></th>
						<th><?php esc_html_e('Status', 'action-scheduler-cleanup'); ?></th>
						<th><?php esc_html_e('Error', 'action-scheduler-cleanup'); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($this->get_history(20) as $row): ?>
					<tr>
						<td><?php echo esc_html($row['time_gmt']); ?></td>
						<td><?php echo esc_html($row['trigger']); ?></td>
						<td><?php echo esc_html((string) $row['failed_actions']); ?></td>
						<td><?php echo esc_html((string) $row['completed_actions']); ?></td>
						<td><?php echo esc_html((string) $row['orphaned_logs']); ?></td>
						<td><?php echo $row['success'] ? '<span style="color:green;">' . esc_html__('Success', 'action-scheduler-cleanup') . '</span>' : '<span style="color:#b32d2e;">' . esc_html__('Failed', 'action-scheduler-cleanup') . '</span>'; ?></td>
						<td><?php echo $row['error'] ? esc_html($row['error']) : ''; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<script>
		jQuery(function($){
			$('#mk-asc-run').on('click', function(){
				var $btn = $(this);
				$btn.prop('disabled', true).text('Running...');
				$.post(ajaxurl, {
					action: 'action_scheduler_cleanup_run',
					nonce: '<?php echo esc_js(wp_create_nonce('action_scheduler_cleanup_nonce')); ?>'
				}).done(function(resp){
					var message = 'Unexpected response';
					if (resp && typeof resp.success !== 'undefined') {
						if (resp.success && resp.data) {
							message = resp.data.message || 'Cleanup completed.';
							var counts = [];
							if (typeof resp.data.failed_actions !== 'undefined') counts.push('Failed actions removed: ' + resp.data.failed_actions);
							if (typeof resp.data.completed_actions !== 'undefined') counts.push('Old completed actions removed: ' + resp.data.completed_actions);
							if (typeof resp.data.orphaned_logs !== 'undefined') counts.push('Orphaned logs removed: ' + resp.data.orphaned_logs);
							if (resp.data.next_run_utc) counts.push('Next scheduled run: ' + resp.data.next_run_utc + ' UTC');
							if (counts.length) message += '\n\n' + counts.join('\n');

							// inject latest history row if provided
							if (resp.data.history_entry) {
								var r = resp.data.history_entry;
								var $row = $('<tr>');
								$row.append($('<td>').text(r.time_gmt));
								$row.append($('<td>').text(r.trigger));
								$row.append($('<td>').text(String(r.failed_actions)));
								$row.append($('<td>').text(String(r.completed_actions)));
								$row.append($('<td>').text(String(r.orphaned_logs)));
								$row.append($('<td>').html(r.success ? '<span style="color:green;">Success</span>' : '<span style="color:#b32d2e;">Failed</span>'));
								$row.append($('<td>').text(r.error || ''));
								$('#mk-asc-history tbody').prepend($row);
								// keep max 20 rows in UI
								var $rows = $('#mk-asc-history tbody tr');
								if ($rows.length > 20) { $rows.last().remove(); }
							}
						} else if (!resp.success && resp.data) {
							message = resp.data.message || 'Cleanup failed.';
						}
					}
					$('#mk-asc-result').show().text(message);
				}).fail(function(){
					$('#mk-asc-result').show().text('Request failed. Please check your connection and try again.');
				}).always(function(){
					$btn.prop('disabled', false).text('Run Cleanup Now');
				});
			});

			$('#mk-asc-clear-history').on('click', function(){
				var $btn = $(this);
				$btn.prop('disabled', true).text('Clearing...');
				$.post(ajaxurl, { action: 'action_scheduler_cleanup_clear_history', nonce: '<?php echo esc_js(wp_create_nonce('action_scheduler_cleanup_nonce')); ?>' })
				.done(function(resp){
					if (resp && resp.success) {
						$('#mk-asc-history tbody').empty();
						$('#mk-asc-result').show().text(resp.data && resp.data.message ? resp.data.message : 'History cleared.');
					} else {
						$('#mk-asc-result').show().text('Failed to clear history.');
					}
				}).fail(function(){
					$('#mk-asc-result').show().text('Request failed.');
				}).always(function(){
					$btn.prop('disabled', false).text('Clear History');
				});
			});
		});
		</script>
		<?php
	}

	public function manual_cleanup() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized']);
		}
		check_ajax_referer('action_scheduler_cleanup_nonce', 'nonce');

		$results = $this->cleanup_action_scheduler();

		$payload = $results;
		$payload['next_run_utc'] = $this->get_next_run_utc();
		$payload['message'] = $this->format_results_message($results, $payload['next_run_utc']);

		if ($results['success']) {
			wp_send_json_success($payload);
		} else {
			// Return 200 with success=false so the UI can display the error message
			wp_send_json_error(['message' => $payload['message'], 'results' => $results]);
		}
	}

	/**
	 * Build a friendly message for the admin UI based on cleanup results
	 */
	private function format_results_message($results, $next_run_utc) {
		if (!$results['success']) {
			if (!empty($results['error'])) {
				if ($results['error'] === 'Cleanup already running') {
					return __('A cleanup is already in progress. Please try again in a few minutes.', 'action-scheduler-cleanup');
				}
				if ($results['error'] === 'Action Scheduler tables not found') {
					return __('Action Scheduler tables were not found for this site. Ensure WooCommerce (or Action Scheduler) is installed and active.', 'action-scheduler-cleanup');
				}
				return sprintf(
					/* translators: %s is an error message */
					__('Cleanup encountered an error: %s. Some items may have been cleaned before the error occurred.', 'action-scheduler-cleanup'),
					esc_html($results['error'])
				);
			}
			return __('Cleanup failed due to an unknown error.', 'action-scheduler-cleanup');
		}

		$failed = (int) ($results['failed_actions'] ?? 0);
		$completed = (int) ($results['completed_actions'] ?? 0);
		$logs = (int) ($results['orphaned_logs'] ?? 0);
		$total = $failed + $completed + $logs;

		if ($total === 0) {
			return __('No cleanup needed. Your Action Scheduler tables are already clean.', 'action-scheduler-cleanup');
		}

		$message = __('Cleanup completed successfully.', 'action-scheduler-cleanup');
		if ($next_run_utc) {
			$message .= ' ' . sprintf(__('Next scheduled run: %s UTC.', 'action-scheduler-cleanup'), esc_html($next_run_utc));
		}
		return $message;
	}

	private function get_next_run_utc() {
		$next = wp_next_scheduled('action_scheduler_cleanup_cron');
		return $next ? gmdate('Y-m-d H:i:s', $next) : null;
	}
}

register_activation_hook(__FILE__, ['MK_Action_Scheduler_Cleanup', 'activate']);
register_deactivation_hook(__FILE__, ['MK_Action_Scheduler_Cleanup', 'deactivate']);
MK_Action_Scheduler_Cleanup::instance();