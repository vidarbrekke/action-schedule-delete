<?php
/**
 * URL to Gutenberg Diagnostic Tool
 * 
 * This file helps diagnose issues with the plugin's settings page.
 * Place this file in the plugin root directory and access it via browser.
 */

// Ensure WordPress is loaded
if (!defined('ABSPATH')) {
    // Attempt to load WordPress
    $wordpress_loader_paths = array(
        // Standard WordPress paths
        __DIR__ . '/../../../wp-load.php',
        __DIR__ . '/../../wp-load.php',
        __DIR__ . '/../wp-load.php',
    );

    foreach ($wordpress_loader_paths as $loader_path) {
        if (file_exists($loader_path)) {
            require_once $loader_path;
            break;
        }
    }
}

// Safety check - only run for admins
if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
    die('This diagnostic tool requires administrator access.');
}

// Header
echo "<html><head><title>URL to Gutenberg Diagnostic</title>";
echo "<style>
    body { font-family: sans-serif; margin: 20px; line-height: 1.5; }
    h1 { color: #23282d; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
    pre { background: #f0f0f0; padding: 10px; overflow: auto; }
    .section { margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
</style>";
echo "</head><body>";
echo "<h1>URL to Gutenberg Diagnostic Tool</h1>";

// Start output buffering to catch any errors
ob_start();

// Diagnostic functions
function check_file_exists($file_path, $show_contents = false) {
    $full_path = plugin_dir_path(__FILE__) . $file_path;
    
    echo "<p>Checking file: <code>$file_path</code> ... ";
    
    if (file_exists($full_path)) {
        echo "<span class='success'>FOUND</span></p>";
        
        if ($show_contents) {
            echo "<p>File contents:</p>";
            echo "<pre>";
            $contents = file_get_contents($full_path);
            echo htmlspecialchars($contents);
            echo "</pre>";
        }
        
        return true;
    } else {
        echo "<span class='error'>MISSING</span></p>";
        return false;
    }
}

function check_class_exists($class, $namespace = '', $instantiate = false) {
    $full_class = $namespace ? "$namespace\\$class" : $class;
    
    echo "<p>Checking class: <code>$full_class</code> ... ";
    
    if (class_exists($full_class)) {
        echo "<span class='success'>FOUND</span></p>";
        
        if ($instantiate) {
            echo "<p>Attempting to instantiate class... ";
            try {
                $instance = new $full_class();
                echo "<span class='success'>SUCCESS</span></p>";
                return $instance;
            } catch (\Throwable $e) {
                echo "<span class='error'>FAILED</span></p>";
                echo "<p>Error: " . $e->getMessage() . "</p>";
                return false;
            }
        }
        
        return true;
    } else {
        echo "<span class='error'>MISSING</span></p>";
        return false;
    }
}

// Check environment
echo "<div class='section'>";
echo "<h2>Environment Information</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>WordPress Version: " . (defined('get_bloginfo') ? get_bloginfo('version') : 'Not available') . "</p>";
echo "<p>Plugin Directory: " . plugin_dir_path(__FILE__) . "</p>";
echo "</div>";

// File check
echo "<div class='section'>";
echo "<h2>File Check</h2>";

// List of files to check
$files_to_check = array(
    'includes/class-settings.php',
    'includes/admin/class-admin.php',
    'includes/admin/views/settings.php',
    'url-to-gutenberg.php',
    'admin-fix.php',
);

foreach ($files_to_check as $file) {
    check_file_exists($file);
}
echo "</div>";

// Detailed Admin Check
echo "<div class='section'>";
echo "<h2>Admin Class Check</h2>";

// Check admin-fix.php is properly disabled
$admin_fix_path = plugin_dir_path(__FILE__) . 'admin-fix.php';
if (file_exists($admin_fix_path)) {
    echo "<p>Checking if admin-fix.php is properly disabled... ";
    $admin_fix_content = file_get_contents($admin_fix_path);
    if (strpos($admin_fix_content, '// add_action(\'admin_menu\'') !== false) {
        echo "<span class='success'>DISABLED</span></p>";
    } else {
        echo "<span class='error'>STILL ACTIVE</span> - This file may be causing duplicate menus</p>";
    }
}

// Check Admin class structure
$admin_class_path = plugin_dir_path(__FILE__) . 'includes/admin/class-admin.php';
if (file_exists($admin_class_path)) {
    echo "<p>Checking Admin class for render_settings_page method... ";
    $admin_class_content = file_get_contents($admin_class_path);
    
    if (strpos($admin_class_content, 'function render_settings_page') !== false) {
        echo "<span class='success'>FOUND</span></p>";
        
        // Check how it loads the view
        if (preg_match('/render_settings_page.*?\{(.*?)\}/s', $admin_class_content, $matches)) {
            echo "<p>Settings page render method:</p>";
            echo "<pre>" . htmlspecialchars(trim($matches[1])) . "</pre>";
        }
    } else {
        echo "<span class='error'>MISSING</span></p>";
    }
}
echo "</div>";

// Check settings.php view
echo "<div class='section'>";
echo "<h2>Settings View Check</h2>";

$settings_view_path = plugin_dir_path(__FILE__) . 'includes/admin/views/settings.php';
if (file_exists($settings_view_path)) {
    $settings_view_content = file_get_contents($settings_view_path);
    
    // Check if it uses $this->settings
    echo "<p>Checking for <code>\$this->settings</code> usage... ";
    if (strpos($settings_view_content, '$this->settings') !== false) {
        echo "<span class='error'>FOUND</span> - This may be causing the error</p>";
        
        // Check if it has the fix
        echo "<p>Checking for fix (<code>\$settings = \$this->settings;</code>)... ";
        if (strpos($settings_view_content, '$settings = $this->settings;') !== false) {
            echo "<span class='success'>FIXED</span></p>";
        } else {
            echo "<span class='error'>NOT FIXED</span></p>";
        }
    } else {
        echo "<span class='success'>NOT FOUND</span> - The view correctly uses local variables</p>";
    }
}
echo "</div>";

// Check WordPress function availability in view context
echo "<div class='section'>";
echo "<h2>WordPress Function Check</h2>";

$wp_functions = array(
    'get_admin_page_title',
    'settings_fields',
    'do_settings_sections',
    '_e',
    'esc_html',
    'esc_attr',
    'checked',
    'selected',
    'submit_button'
);

foreach ($wp_functions as $func) {
    echo "<p>Checking function <code>$func</code>... ";
    if (function_exists($func)) {
        echo "<span class='success'>AVAILABLE</span></p>";
    } else {
        echo "<span class='error'>NOT AVAILABLE</span> - This may cause errors in the view</p>";
    }
}
echo "</div>";

// Check UTG Settings class
echo "<div class='section'>";
echo "<h2>Settings Class Check</h2>";

if (class_exists('UTG\\Settings')) {
    echo "<p>Settings class exists</p>";
    
    echo "<p>Checking for get() method... ";
    if (method_exists('UTG\\Settings', 'get')) {
        echo "<span class='success'>FOUND</span></p>";
    } else {
        echo "<span class='error'>MISSING</span></p>";
    }
    
    echo "<p>Checking for is_configured() method... ";
    if (method_exists('UTG\\Settings', 'is_configured')) {
        echo "<span class='success'>FOUND</span></p>";
    } else {
        echo "<span class='error'>MISSING</span> - This method is required by the admin class</p>";
    }
} else {
    echo "<p><span class='error'>Settings class not found</span></p>";
}
echo "</div>";

// Admin menu check
echo "<div class='section'>";
echo "<h2>Admin Menu Check</h2>";

global $menu, $submenu;
echo "<p>Checking for plugin menu entries...</p>";
echo "<pre>";
foreach ($menu as $menu_item) {
    if (strpos($menu_item[2], 'url-to-gutenberg') !== false) {
        echo "Found menu: " . htmlspecialchars(print_r($menu_item, true)) . "\n";
    }
}

if (isset($submenu['url-to-gutenberg'])) {
    echo "Submenu items:\n";
    foreach ($submenu['url-to-gutenberg'] as $submenu_item) {
        echo "- " . htmlspecialchars(print_r($submenu_item, true)) . "\n";
    }
}
echo "</pre>";
echo "</div>";

// Get any buffered output (including errors)
$output = ob_get_clean();
echo $output;

// Display recommendations
echo "<div class='section'>";
echo "<h2>Recommendations</h2>";
echo "<ol>";
echo "<li>Ensure that <code>admin-fix.php</code> is properly disabled to prevent duplicate menus.</li>";
echo "<li>Make sure the settings view file (<code>includes/admin/views/settings.php</code>) uses a local <code>\$settings</code> variable instead of <code>\$this->settings</code>.</li>";
echo "<li>Verify that the Settings class has the <code>is_configured()</code> method.</li>";
echo "<li>Check if there are any PHP errors in your server logs related to the settings page.</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?> 