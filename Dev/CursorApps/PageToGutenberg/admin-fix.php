<?php
/**
 * Simple admin menu fix for URL to Gutenberg
 * This file adds direct menu registration to ensure the plugin menu appears
 */

// Direct admin menu registration with high priority
add_action('admin_menu', 'utg_direct_admin_menu', 999);

/**
 * Register the admin menu directly
 */
function utg_direct_admin_menu() {
    // Main menu
    add_menu_page(
        'URL to Gutenberg',
        'URL to Gutenberg',
        'manage_options',
        'url-to-gutenberg',
        'utg_render_converter_page',
        'dashicons-admin-page',
        30
    );
    
    // Settings submenu
    add_submenu_page(
        'url-to-gutenberg',
        'Settings',
        'Settings',
        'manage_options',
        'utg-settings',
        'utg_render_settings_page'
    );
}

/**
 * Render the converter page
 */
function utg_render_converter_page() {
    include_once plugin_dir_path(__FILE__) . 'includes/admin/views/url-converter.php';
}

/**
 * Render the settings page
 */
function utg_render_settings_page() {
    include_once plugin_dir_path(__FILE__) . 'includes/admin/views/settings.php';
} 