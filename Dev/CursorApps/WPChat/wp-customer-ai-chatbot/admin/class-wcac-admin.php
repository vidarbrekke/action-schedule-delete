<?php

declare(strict_types=1);

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://customer-ai-chatbot.wp
 * @since      1.0.0
 *
 * @package    WP_Customer_AI_Chatbot
 * @subpackage WP_Customer_AI_Chatbot/admin
 */

/**
 * This file is intended to be run within the WordPress environment.
 * Functions such as add_action, add_filter, add_menu_page, register_setting, esc_html__, and admin_url
 * are provided by WordPress core and are available when this plugin is loaded by WordPress.
 *
 * If you see linter errors for undefined functions, ensure you are running this code within WordPress.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Ensure WordPress functions are available
if (!function_exists('wp_enqueue_style')) {
    require_once ABSPATH . 'wp-includes/functions.php';
    require_once ABSPATH . 'wp-includes/script-loader.php';
}
if (!function_exists('get_post_types')) {
    require_once ABSPATH . 'wp-includes/post.php';
    if (!function_exists('get_post_types')) {
        function get_post_types($args = [], $output = 'names') {
            error_log('WCAC WARNING: get_post_types called but not available. Returning empty array.');
            return [];
        }
    }
}
if (!function_exists('get_taxonomies')) {
    require_once ABSPATH . 'wp-includes/taxonomy.php';
    if (!function_exists('get_taxonomies')) {
        function get_taxonomies($args = [], $output = 'names') {
            error_log('WCAC WARNING: get_taxonomies called but not available. Returning empty array.');
            return [];
        }
    }
}
if (!function_exists('wp_die')) {
    require_once ABSPATH . 'wp-includes/functions.php';
    if (!function_exists('wp_die')) {
        function wp_die($message) {
            error_log('WCAC WARNING: wp_die called but not available. Echoing message.');
            echo $message;
            exit;
        }
    }
}
if (!function_exists('wp_verify_nonce')) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
    if (!function_exists('wp_verify_nonce')) {
        function wp_verify_nonce($nonce, $action = -1) {
            error_log('WCAC WARNING: wp_verify_nonce called but not available. Returning false.');
            return false;
        }
    }
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for
 * the admin area functionality of the plugin.
 *
 * @package    WP_Customer_AI_Chatbot
 * @subpackage WP_Customer_AI_Chatbot/admin
 * @author     WP Customer AI Chatbot Team
 */
class Wcac_Admin
{
    private string $plugin_name;
    private string $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version     The version of this plugin.
     */
    public function __construct(string $plugin_name, string $version)
    {
        error_log('WCAC DEBUG: Wcac_Admin constructor called at ' . date('Y-m-d H:i:s'));
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        // Register the Manage Brands submenu
        add_action('admin_menu', [ $this, 'register_brand_admin_menu' ]);
        // Register the Index Status submenu
        add_action('admin_menu', [ $this, 'register_index_status_admin_menu' ]);
        // Register the Feedback submenu
        add_action('admin_menu', [ $this, 'register_feedback_admin_menu' ]);
    }

    /**
     * Enqueue styles for the admin area.
     */
    public function enqueue_styles($hook): void
    {
        error_log('WCAC DEBUG: Wcac_Admin enqueue_styles called with hook: ' . $hook);

        // This gets called via Wcac_Admin_Settings
        if ('toplevel_page_wcac-settings' !== $hook) {
            return;
        }

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                $this->plugin_name . '-admin',
                WCAC_PLUGIN_URL . 'admin/css/wcac-admin.css',
                [],
                $this->version
            );
        } else {
            error_log('WCAC ERROR: wp_enqueue_style function not available');
        }
    }

    /**
     * Get all available post types for indexing.
     */
    private function get_post_types(): array
    {
        if (!function_exists('get_post_types')) {
            error_log('WCAC ERROR: get_post_types function not available');
            return [];
        }

        $post_types = get_post_types(['public' => true], 'objects');
        $options = [];

        foreach ($post_types as $post_type) {
            if ($post_type->name !== 'attachment') {
                $options[$post_type->name] = $post_type->labels->name;
            }
        }

        return $options;
    }

    /**
     * Get all available taxonomies for indexing.
     */
    private function get_taxonomies(): array
    {
        if (!function_exists('get_taxonomies')) {
            error_log('WCAC ERROR: get_taxonomies function not available');
            return [];
        }

        $taxonomies = get_taxonomies(['public' => true], 'objects');
        $options = [];

        foreach ($taxonomies as $taxonomy) {
            $options[$taxonomy->name] = $taxonomy->labels->name;
        }

        return $options;
    }

    /**
     * Register the Manage Brands submenu page.
     */
    public function register_brand_admin_menu(): void
    {
        add_submenu_page(
            'wcac-settings',
            esc_html__('Manage Brands', 'wp-customer-ai-chatbot'),
            esc_html__('Manage Brands', 'wp-customer-ai-chatbot'),
            'manage_options',
            'wcac-manage-brands',
            [ $this, 'render_brand_admin_page' ]
        );
    }

    /**
     * Register the Index Status submenu page.
     */
    public function register_index_status_admin_menu(): void
    {
        add_submenu_page(
            'wcac-settings',
            esc_html__('Index Status', 'wp-customer-ai-chatbot'),
            esc_html__('Index Status', 'wp-customer-ai-chatbot'),
            'manage_options',
            'wcac-index-status',
            [ $this, 'render_index_status_admin_page' ]
        );
    }

    /**
     * Register the Feedback admin menu.
     */
    public function register_feedback_admin_menu() {
        add_submenu_page(
            'wp-customer-ai-chatbot',
            __('Chat Feedback', 'wp-customer-ai-chatbot'),
            __('Chat Feedback', 'wp-customer-ai-chatbot'),
            'manage_options',
            'wcac-feedback',
            [ $this, 'render_feedback_admin_page' ]
        );
    }

    /**
     * Render the Manage Brands admin page.
     */
    public function render_brand_admin_page(): void
    {
        require_once WCAC_PLUGIN_DIR . 'admin/partials/wcac-admin-brands.php';
    }

    /**
     * Render the Index Status admin page.
     */
    public function render_index_status_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-customer-ai-chatbot'));
        }
        // Fallback log pruning: prune if last prune >24h ago
        $pruned = class_exists('Wcac_Log') && method_exists('Wcac_Log', 'maybe_fallback_prune') ? Wcac_Log::maybe_fallback_prune() : false;
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcac_index';
        $notice = '';
        // Handle retry POST
        if (
            isset($_POST['wcac_retry_failed'], $_POST['wcac_retry_ids'], $_POST['wcac_retry_nonce']) &&
            wp_verify_nonce(sanitize_text_field($_POST['wcac_retry_nonce']), 'wcac_retry_failed_summaries')
        ) {
            $ids = array_map('intval', (array)$_POST['wcac_retry_ids']);
            $retried = 0;
            if (class_exists('Wcac_Indexer') && method_exists('Wcac_Indexer', 'retry_failed_summaries')) {
                $retried = Wcac_Indexer::retry_failed_summaries($ids);
            }
            $notice = sprintf(esc_html__('%d failed summaries re-queued for retry.', 'wp-customer-ai-chatbot'), $retried);
        }
        // Handle manual prune POST
        if (
            isset($_POST['wcac_prune_log'], $_POST['wcac_prune_log_nonce']) &&
            wp_verify_nonce(sanitize_text_field($_POST['wcac_prune_log_nonce']), 'wcac_prune_log')
        ) {
            if (class_exists('Wcac_Log') && method_exists('Wcac_Log', 'scheduled_prune')) {
                Wcac_Log::scheduled_prune();
                update_option('wcac_log_last_prune', time());
                $notice = esc_html__('Log pruned successfully.', 'wp-customer-ai-chatbot');
            }
        }
        $results = $wpdb->get_results("SELECT post_id, post_type, title, llm_summary_status, llm_summary_error FROM {$table_name} ORDER BY post_id DESC LIMIT 200", ARRAY_A);
        if ($notice) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }
        if ($pruned) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Log pruned for maintenance (fallback).', 'wp-customer-ai-chatbot') . '</p></div>';
        }
        require WCAC_PLUGIN_DIR . 'admin/partials/wcac-admin-index-status.php';
    }

    /**
     * Render the Feedback admin page.
     */
    public function render_feedback_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-customer-ai-chatbot'));
        }
        global $wpdb;
        $table = $wpdb->prefix . 'wcac_feedback';
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $up = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE feedback = 'up'");
        $down = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE feedback = 'down'");
        $recent = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 50", ARRAY_A);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Chat Feedback', 'wp-customer-ai-chatbot'); ?></h1>
            <p><?php printf(esc_html__('Total feedback: %d | 👍: %d | 👎: %d', 'wp-customer-ai-chatbot'), $total, $up, $down); ?></p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'wp-customer-ai-chatbot'); ?></th>
                        <th><?php esc_html_e('Chat ID', 'wp-customer-ai-chatbot'); ?></th>
                        <th><?php esc_html_e('Message ID', 'wp-customer-ai-chatbot'); ?></th>
                        <th><?php esc_html_e('Feedback', 'wp-customer-ai-chatbot'); ?></th>
                        <th><?php esc_html_e('Date', 'wp-customer-ai-chatbot'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td><?php echo (int) $row['id']; ?></td>
                        <td><?php echo esc_html($row['chat_id']); ?></td>
                        <td><?php echo esc_html($row['message_id']); ?></td>
                        <td><?php echo $row['feedback'] === 'up' ? '👍' : '👎'; ?></td>
                        <td><?php echo esc_html($row['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
