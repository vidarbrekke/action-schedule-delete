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
	}

	public static function activate() {
		self::instance()->ensure_scheduled_event();
		// Run once on activation
		self::instance()->cleanup_action_scheduler();
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
			$this->log_cleanup($results);
			return $results;
		}

		// Set lock for 10 minutes (should be plenty for cleanup)
		set_transient($lock_key, true, 10 * MINUTE_IN_SECONDS);

		try {
			// Check if tables exist
			if (!$this->tables_exist($prefix)) {
				$results['success'] = false;
				$results['error'] = 'Action Scheduler tables not found';
				$this->log_cleanup($results);
				return $results;
			}

			// Start transaction for atomicity
			$wpdb->query('START TRANSACTION');

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

			// Commit transaction
			$wpdb->query('COMMIT');

		} catch (Exception $e) {
			// Rollback on any error
			$wpdb->query('ROLLBACK');
			$results['success'] = false;
			$results['error'] = $e->getMessage();
		} finally {
			// Always clean up the lock
			delete_transient($lock_key);
		}

		$this->log_cleanup($results);
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

		// Use LEFT JOIN for better performance than subquery
		$query = $wpdb->prepare(
			"DELETE al FROM {$prefix}actionscheduler_logs al
			 LEFT JOIN {$prefix}actionscheduler_actions aa ON al.action_id = aa.action_id
			 WHERE aa.action_id IS NULL"
		);

		$result = $wpdb->query($query);
		if ($result === false) {
			throw new Exception('Failed to delete orphaned logs: ' . $wpdb->last_error);
		}

		return (int) $result;
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
					$('#mk-asc-result').show().text(JSON.stringify(resp, null, 2));
				}).fail(function(){
					$('#mk-asc-result').show().text('Request failed');
				}).always(function(){
					$btn.prop('disabled', false).text('Run Cleanup Now');
				});
			});
		});
		</script>
		<?php
	}

	public function manual_cleanup() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized'], 403);
		}
		check_ajax_referer('action_scheduler_cleanup_nonce', 'nonce');

		$results = $this->cleanup_action_scheduler();

		if ($results['success']) {
			wp_send_json_success($results);
		} else {
			wp_send_json_error([
				'message' => 'Cleanup failed: ' . ($results['error'] ?? 'Unknown error'),
				'results' => $results
			], 500);
		}
	}
}

register_activation_hook(__FILE__, ['MK_Action_Scheduler_Cleanup', 'activate']);
register_deactivation_hook(__FILE__, ['MK_Action_Scheduler_Cleanup', 'deactivate']);
MK_Action_Scheduler_Cleanup::instance();