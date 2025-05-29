<?php
// Ensure get_post is available
if (!function_exists('get_post')) {
    require_once ABSPATH . 'wp-includes/post.php';
}
// Ensure WordPress meta functions are available
if (!function_exists('get_post_meta')) {
    if (file_exists(ABSPATH . 'wp-includes/meta.php')) {
        require_once ABSPATH . 'wp-includes/meta.php';
    } else {
        function get_post_meta($post_id, $key = '', $single = false) {
            error_log('WCAC WARNING: get_post_meta called but meta.php not found. Returning null.');
            return null;
        }
    }
}
if (!function_exists('update_post_meta')) {
    if (file_exists(ABSPATH . 'wp-includes/meta.php')) {
        require_once ABSPATH . 'wp-includes/meta.php';
    } else {
        function update_post_meta($post_id, $key, $value, $prev_value = '') {
            error_log('WCAC WARNING: update_post_meta called but meta.php not found. Returning false.');
            return false;
        }
    }
}

require_once WCAC_PLUGIN_DIR . 'includes/indexing/class-wcac-llm-client.php';

class Wcac_SummaryHelper {
    private static $max_retries = 5;
    private static $base_delay = 60; // seconds

    public static function get_last_summary_hash(int $post_id): ?string {
        return get_post_meta($post_id, '_wcac_summary_hash', true) ?: null;
    }
    public static function set_last_summary_hash(int $post_id, string $hash): void {
        update_post_meta($post_id, '_wcac_summary_hash', $hash);
    }
    public static function compute_content_hash(string $content): string {
        return md5($content);
    }
    public static function compute_content_diff(string $old, string $new): string {
        $old_lines = explode("\n", $old);
        $new_lines = explode("\n", $new);
        $diff = [];
        foreach ($new_lines as $i => $line) {
            if (!isset($old_lines[$i]) || $old_lines[$i] !== $line) {
                $diff[] = '+ ' . $line;
            }
        }
        foreach ($old_lines as $i => $line) {
            if (!isset($new_lines[$i]) || $new_lines[$i] !== $line) {
                $diff[] = '- ' . $line;
            }
        }
        return implode("\n", $diff);
    }

    /**
     * Summarize content incrementally using the LLM client. Handles DB update and error status.
     * Only requires post_id; fetches all other data internally.
     *
     * @param int $post_id
     * @return bool True on success, false on error.
     */
    public static function summarize_incremental($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';
        $post = self::fetch_post($post_id);
        if (!$post) return false;
        $content = $post->post_content;
        $current_hash = self::compute_content_hash($content);
        $last_hash = self::get_last_summary_hash($post_id);
        if ($last_hash === $current_hash) {
            self::update_status($table_name, $post_id, 'success');
            $wpdb->update($table_name, [ 'llm_retry_count' => 0, 'llm_next_attempt' => null ], [ 'post_id' => $post_id ]);
            return true;
        }
        $old_content = $last_hash ? get_post_meta($post_id, '_wcac_last_summarized_content', true) : '';
        $diff = self::compute_content_diff($old_content, $content);
        $current_summary = $wpdb->get_var($wpdb->prepare("SELECT llm_summary FROM {$table_name} WHERE post_id = %d", $post_id));
        $prompt = self::build_prompt($current_summary, $diff);
        // Fetch current retry count
        $row = $wpdb->get_row($wpdb->prepare("SELECT llm_retry_count FROM {$table_name} WHERE post_id = %d", $post_id), ARRAY_A);
        $retry_count = isset($row['llm_retry_count']) ? (int)$row['llm_retry_count'] : 0;
        try {
            $new_summary = self::call_llm($prompt);
            self::update_summary($table_name, $post_id, $new_summary, $current_hash, $content);
            $wpdb->update($table_name, [ 'llm_retry_count' => 0, 'llm_next_attempt' => null ], [ 'post_id' => $post_id ]);
            return true;
        } catch (Exception $e) {
            $retry_count++;
            if ($retry_count <= self::$max_retries) {
                $delay = self::$base_delay * pow(2, $retry_count - 1);
                $next_time = date('Y-m-d H:i:s', time() + $delay);
                $wpdb->update($table_name, [
                    'llm_summary_status' => 'retry',
                    'llm_summary_error' => $e->getMessage(),
                    'llm_retry_count' => $retry_count,
                    'llm_next_attempt' => $next_time
                ], [ 'post_id' => $post_id ]);
            } else {
                $wpdb->update($table_name, [
                    'llm_summary_status' => 'error',
                    'llm_summary_error' => $e->getMessage(),
                    'llm_retry_count' => $retry_count,
                    'llm_next_attempt' => null
                ], [ 'post_id' => $post_id ]);
            }
            return false;
        }
    }

    private static function fetch_post($post_id) {
        if (!function_exists('get_post')) {
            if (file_exists(ABSPATH . 'wp-includes/post.php')) {
                require_once ABSPATH . 'wp-includes/post.php';
            } else {
                return null;
            }
        }
        // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
        $post = get_post($post_id);
        if (!$post) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wcac_index';
            $wpdb->update($table_name, [ 'llm_summary_status' => 'error', 'llm_summary_error' => 'Post not found' ], [ 'post_id' => $post_id ], [ '%s', '%s' ], [ '%d' ]);
        }
        return $post;
    }

    private static function build_prompt($current_summary, $diff) {
        return "Update the following summary based on the content diff.\nCurrent summary:\n" . ($current_summary ?: '[none]') . "\nDiff:\n" . $diff . "\nNew summary:";
    }

    private static function call_llm($prompt) {
        return Wcac_LLMClient::summarize($prompt);
    }

    private static function update_summary($table_name, $post_id, $new_summary, $current_hash, $content) {
        global $wpdb;
        $wpdb->update(
            $table_name,
            [ 'llm_summary' => $new_summary, 'llm_summary_status' => 'success', 'llm_summary_error' => null ],
            [ 'post_id' => $post_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
        self::set_last_summary_hash($post_id, $current_hash);
        update_post_meta($post_id, '_wcac_last_summarized_content', $content);
    }

    private static function update_status($table_name, $post_id, $status, $error = null) {
        global $wpdb;
        $wpdb->update(
            $table_name,
            [ 'llm_summary_status' => $status, 'llm_summary_error' => $error ],
            [ 'post_id' => $post_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
    }
} 