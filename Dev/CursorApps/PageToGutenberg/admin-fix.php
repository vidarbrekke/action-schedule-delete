<?php
/**
 * DISABLED: This file is completely disabled and should not be loaded.
 * 
 * This file previously contained admin menu fixes for the URL to Gutenberg plugin.
 * These fixes have been replaced with standard WordPress menu registration.
 * 
 * This file is kept only for reference and is not used by the plugin.
 */

// Exit immediately
return;

// None of the code below will ever execute
// =====================================================================

/**
 * Simple admin menu fix for URL to Gutenberg
 * This file adds direct menu registration to ensure the plugin menu appears
 * 
 * DISABLED: This file is now completely disabled as it was causing duplicate menu items
 * and conflicts with the core plugin's menu registration. The core plugin now handles
 * the settings page rendering properly.
 */

// Completely disabled to prevent duplicate menu items and conflicts
// add_action('admin_menu', 'utg_direct_admin_menu', 999);

/**
 * Register the admin menu directly
 * DISABLED: Function left for reference only
 */
/*
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
        'url-to-gutenberg-settings',
        'utg_render_settings_page'
    );
}
*/

/**
 * Render the converter page
 * DISABLED: Function left for reference only
 */
/*
function utg_render_converter_page() {
    include_once plugin_dir_path(__FILE__) . 'includes/admin/views/url-converter.php';
}
*/

/**
 * Render the settings page with proper variable setup
 * DISABLED: Function left for reference only
 * This function ensures that the settings object is available to the view
 */
/*
function utg_render_settings_page_fixed() {
    // Create a clean scope for the settings variable
    $settings = null;
    
    // Try to get the settings from the global UTG_Admin instance if available
    global $utg_admin;
    if (isset($utg_admin) && is_object($utg_admin) && property_exists($utg_admin, 'settings')) {
        $settings = $utg_admin->settings;
    }
    
    // If we can't get it from the admin instance, try to find the Settings class
    if (!$settings && class_exists('UTG\\Settings')) {
        try {
            $settings = new UTG\Settings();
        } catch (Exception $e) {
            // If we can't create the object, show an error
            echo '<div class="wrap"><h1>URL to Gutenberg Settings</h1>';
            echo '<div class="notice notice-error"><p>Error: Could not initialize settings. ' . esc_html($e->getMessage()) . '</p></div></div>';
            return;
        }
    }
    
    // If we still don't have a settings object, create a fallback
    if (!$settings) {
        $settings = new stdClass();
        $settings->get = function($key, $default = '') {
            return $default;
        };
        
        // Show an admin notice about the issue
        echo '<div class="notice notice-warning"><p>Warning: Using fallback settings. Some features may not work correctly.</p></div>';
    }
    
    // Include the settings view file with settings variable in scope
    include_once plugin_dir_path(__FILE__) . 'includes/admin/views/settings.php';
}
*/

// Fix settings page issues by creating a direct handler
// Uncomment this if you continue to have settings page issues
add_action('admin_menu', 'utg_fix_settings_page', 999);

/**
 * Fix settings page by providing direct access
 * Function was left for reference and is now being reactivated to fix menu issues
 */
function utg_fix_settings_page() {
    // Remove the original settings page
    remove_submenu_page('url-to-gutenberg', 'url-to-gutenberg-settings');
    
    // Add our fixed version
    add_submenu_page(
        'url-to-gutenberg',
        'Settings',
        'Settings',
        'manage_options',
        'url-to-gutenberg-settings',
        'utg_render_settings_page'
    );
}

/**
 * Render the settings page with proper variable setup
 * This function ensures that the settings object is available to the view
 */
function utg_render_settings_page() {
    // Create a clean scope for the settings variable
    $settings = null;
    
    // Try to get the settings from the global UTG instance
    $utg_instance = \UTG\URL_To_Gutenberg::get_instance();
    if (method_exists($utg_instance, 'get_settings')) {
        $settings = $utg_instance->get_settings();
    }
    
    // If we still don't have a settings object, create a fallback
    if (!$settings) {
        $settings = new \stdClass();
        $settings->get = function($key, $default = '') {
            return $default;
        };
        
        // Show an admin notice about the issue
        echo '<div class="notice notice-warning"><p>Warning: Using fallback settings. Some features may not work correctly.</p></div>';
    }
    
    // Include the settings view file with settings variable in scope
    include_once dirname(__FILE__) . '/includes/admin/views/settings.php';
}

// Add submenu page
add_submenu_page(
    'url-to-gutenberg',
    'Settings',
    'Settings',
    'manage_options',
    'url-to-gutenberg-settings',
    'utg_render_settings_page'
);

// Remove duplicate menu items
remove_submenu_page('url-to-gutenberg', 'url-to-gutenberg-settings');

// Add submenu page again
add_submenu_page(
    'url-to-gutenberg',
    'Settings',
    'Settings',
    'manage_options',
    'url-to-gutenberg-settings',
    'utg_render_settings_page'
); 