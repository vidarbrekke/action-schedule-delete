<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://customer-ai-chatbot.wp
 * @since      1.0.0
 *
 * @package    WP_Customer_AI_Chatbot
 * @subpackage WP_Customer_AI_Chatbot/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Ensure WordPress functions are available
if (!function_exists('current_user_can')) {
    require_once ABSPATH . 'wp-includes/capabilities.php';
}
if (!function_exists('wp_die')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}
if (!function_exists('esc_html__')) {
    require_once ABSPATH . 'wp-includes/l10n.php';
}
if (!function_exists('get_admin_page_title')) {
    require_once ABSPATH . 'wp-includes/general-template.php';
    if (!function_exists('get_admin_page_title')) {
        function get_admin_page_title() { return 'WP Customer AI Chatbot Settings'; }
    }
}
if (!function_exists('settings_fields') || !function_exists('do_settings_sections') || !function_exists('submit_button')) {
    require_once ABSPATH . 'wp-admin/includes/template.php';
}
if (!function_exists('sanitize_key')) {
    /**
     * Polyfill for sanitize_key for static analysis/CLI tools.
     * In WordPress, this strips out anything except lowercase letters, numbers, underscores, and dashes.
     */
    function sanitize_key($key) {
        $key = strtolower($key);
        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

// Error handling for admin view
try {
    // Ensure user has permission
    if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
        if (function_exists('wp_die') && function_exists('esc_html__')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-customer-ai-chatbot'));
        } else {
            echo 'You do not have sufficient permissions to access this page.';
            exit;
        }
    }

    // Ensure expected variables exist
    if (!isset($this) || !property_exists($this, 'option_group') || !property_exists($this, 'option_key')) {
        error_log('WCAC ERROR: Admin template called without proper context. Required properties missing.');
        ?>
        <div class="wrap">
            <h1>WP Customer AI Chatbot Settings</h1>
            <div class="error notice">
                <p>Error: Settings page could not be loaded correctly. Please check error logs.</p>
            </div>
        </div>
        <?php
        return;
    }

    // Define page slugs for each tab (must match those in Wcac_Admin_Settings::register_settings)
    $api_page_slug = 'wcac_settings_api';
    $indexing_page_slug = 'wcac_settings_indexing';
    $customization_page_slug = 'wcac_settings_customization';
    $llm_page_slug = 'wcac_settings_llm';
    $debug_page_slug = 'wcac_settings_debug';

    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'api'; // Default to 'api' tab

    // Get the base URL for the settings page
    $base_url = admin_url('admin.php?page=wcac-settings');

    ?>

<div class="wrap">
    <h1><?php echo function_exists('get_admin_page_title') ? esc_html(get_admin_page_title()) : esc_html__('WP Customer AI Chatbot Settings', 'wp-customer-ai-chatbot'); ?></h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="<?php echo esc_url($base_url . '&tab=api'); ?>" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('API Settings', 'wp-customer-ai-chatbot'); ?>
        </a>
        <a href="<?php echo esc_url($base_url . '&tab=indexing'); ?>" class="nav-tab <?php echo $active_tab === 'indexing' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Indexing', 'wp-customer-ai-chatbot'); ?>
        </a>
        <a href="<?php echo esc_url($base_url . '&tab=customization'); ?>" class="nav-tab <?php echo $active_tab === 'customization' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Customization', 'wp-customer-ai-chatbot'); ?>
        </a>
        <a href="<?php echo esc_url($base_url . '&tab=llm'); ?>" class="nav-tab <?php echo $active_tab === 'llm' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('LLM Parameters', 'wp-customer-ai-chatbot'); ?>
        </a>
        <a href="<?php echo esc_url($base_url . '&tab=debug'); ?>" class="nav-tab <?php echo $active_tab === 'debug' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Debug Logging', 'wp-customer-ai-chatbot'); ?>
        </a>
        <a href="<?php echo esc_url($base_url . '&tab=scoring_rules'); ?>" class="nav-tab <?php echo $active_tab === 'scoring_rules' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Scoring Rules', 'wp-customer-ai-chatbot'); ?>
        </a>
    </h2>

    <div class="wcac-settings-wrapper">
        <div id="wcac-settings-sections-wrapper">
            <?php if ($active_tab === 'api'): ?>
                <form class="wcac-tab-form" data-tab-id="api">
                    <?php 
                    settings_fields($this->option_group);
                    wp_nonce_field('wcac_save_tab_settings_api', '_wpnonce_api');
                    do_settings_sections($api_page_slug);
                    ?>
                    <button type="button" class="button button-primary wcac-tab-save">Save API Settings</button>
                </form>
            <?php elseif ($active_tab === 'indexing'): ?>
                <form class="wcac-tab-form" data-tab-id="indexing">
                    <?php 
                    settings_fields($this->option_group);
                    wp_nonce_field('wcac_save_tab_settings_indexing', '_wpnonce_indexing');
                    do_settings_sections($indexing_page_slug);
                    ?>
                    <button type="button" class="button button-primary wcac-tab-save">Save Indexing Settings</button>
                </form>
            <?php elseif ($active_tab === 'customization'): ?>
                <form class="wcac-tab-form" data-tab-id="customization">
                    <?php 
                    settings_fields($this->option_group);
                    wp_nonce_field('wcac_save_tab_settings_customization', '_wpnonce_customization');
                    do_settings_sections($customization_page_slug);
                    ?>
                    <button type="button" class="button button-primary wcac-tab-save">Save Customization Settings</button>
                </form>
            <?php elseif ($active_tab === 'llm'): ?>
                <form class="wcac-tab-form" data-tab-id="llm">
                    <?php 
                    settings_fields($this->option_group);
                    wp_nonce_field('wcac_save_tab_settings_llm', '_wpnonce_llm');
                    do_settings_sections($llm_page_slug);
                    ?>
                    <button type="button" class="button button-primary wcac-tab-save">Save LLM Settings</button>
                </form>
            <?php elseif ($active_tab === 'debug'): ?>
                <form class="wcac-tab-form" data-tab-id="debug">
                    <?php 
                    settings_fields($this->option_group);
                    wp_nonce_field('wcac_save_tab_settings_debug', '_wpnonce_debug');
                    do_settings_sections($debug_page_slug);
                    ?>
                    <button type="button" class="button button-primary wcac-tab-save">Save Debug Settings</button>
                </form>
            <?php elseif ($active_tab === 'scoring_rules'): ?>
                <form class="wcac-tab-form" data-tab-id="scoring_rules">
                    <?php 
                    settings_fields($this->option_group);
                    wp_nonce_field('wcac_save_tab_settings_scoring_rules', '_wpnonce_scoring_rules');
                    do_settings_sections('wcac_settings_scoring_rules');
                    ?>
                    <button type="button" class="button button-primary wcac-tab-save">Save Scoring Rules</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
    <?php
} catch (Throwable $e) {
    error_log('WCAC ERROR: Exception in admin settings template: ' . $e->getMessage());
    error_log('WCAC ERROR: ' . $e->getTraceAsString());
    ?>
    <div class="wrap">
        <h1>WP Customer AI Chatbot Settings</h1>
        <div class="error notice">
            <p>Error: Settings page encountered an error. Please check error logs.</p>
        </div>
    </div>
    <?php
}
