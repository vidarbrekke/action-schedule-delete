<?php
/**
 * URL to Gutenberg Converter Admin Page
 *
 * @package UrlToGutenberg
 */

defined('ABSPATH') || exit;

// Ensure required WordPress functions are available
if (!function_exists('wp_create_nonce')) {
    return;
}

// Generate nonce once at page load
$ajax_nonce = wp_create_nonce('utg_ajax_nonce');
?>

<div class="wrap utg-admin-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="utg-tool-description">
        <p><?php _e('This tool allows you to convert content from any URL into Gutenberg blocks, making it easy to import content into WordPress.', 'url-to-gutenberg'); ?></p>
    </div>
    
    <?php
    // Display admin notices if any
    if (isset($_GET['message']) && $_GET['message'] === 'settings-updated') {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings updated successfully.', 'url-to-gutenberg') . '</p></div>';
    }
    ?>
    
    <div class="utg-container">
        <div class="utg-form-container">
            <div class="utg-panel">
                <h2 class="utg-panel-title"><?php _e('URL Converter', 'url-to-gutenberg'); ?></h2>
                
                <form id="utg-form" class="utg-form">
                    <?php // Add nonce field directly in the form ?>
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($ajax_nonce); ?>">
                    
                    <div class="utg-form-field">
                        <label for="utg-url"><?php _e('URL to Convert', 'url-to-gutenberg'); ?></label>
                        <input type="url" id="utg-url" name="url" placeholder="https://example.com/page-to-convert" required>
                        <p class="description"><?php _e('Enter the full URL of the page you want to convert.', 'url-to-gutenberg'); ?></p>
                    </div>
                    
                    <div class="utg-form-field utg-checkbox-field">
                        <input type="checkbox" id="utg-parse-only" name="parse_only" value="1">
                        <label for="utg-parse-only"><?php _e('HTML extraction only (no AI conversion)', 'url-to-gutenberg'); ?></label>
                        <p class="description"><?php _e('Check this to extract the HTML content only without converting to Gutenberg blocks.', 'url-to-gutenberg'); ?></p>
                    </div>
                    
                    <div class="utg-form-field">
                        <label for="utg-cleaning-level"><?php _e('HTML Cleaning Level', 'url-to-gutenberg'); ?></label>
                        <select id="utg-cleaning-level" name="cleaning_level">
                            <option value="standard"><?php _e('Standard - Keep most formatting', 'url-to-gutenberg'); ?></option>
                            <option value="medium"><?php _e('Medium - Clean some styling while preserving structure', 'url-to-gutenberg'); ?></option>
                            <option value="aggressive"><?php _e('Aggressive - Remove most styling but maintain basic structure', 'url-to-gutenberg'); ?></option>
                        </select>
                        <p class="description"><?php _e('Select how aggressively to clean the HTML structure. More aggressive cleaning removes more styling but maintains content structure.', 'url-to-gutenberg'); ?></p>
                    </div>
                    
                    <div class="utg-form-actions">
                        <input type="submit" id="utg-submit" class="button button-primary" value="<?php _e('Convert URL', 'url-to-gutenberg'); ?>">
                    </div>
                </form>
            </div>
        </div>
        
        <div class="utg-results-container" id="utg-results">
            <div id="utg-result-message" class="utg-result-message"></div>
            
            <div id="utg-preview" class="utg-preview" style="display:none;"></div>
            
            <div class="utg-textarea-container" style="display:none;">
                <h3><?php _e('Conversion Result', 'url-to-gutenberg'); ?></h3>
                <p class="description"><?php _e('You can copy this content and paste it into the WordPress editor.', 'url-to-gutenberg'); ?></p>
                <div class="utg-copy-button-container">
                    <button type="button" id="utg-copy-button" class="button utg-copy-button">
                        <?php _e('Copy to Clipboard', 'url-to-gutenberg'); ?>
                    </button>
                </div>
                <textarea id="utg-result" class="utg-result-textarea" rows="15" readonly></textarea>
            </div>
        </div>
    </div>
</div>

<script>
// Copy button functionality
document.addEventListener('DOMContentLoaded', function() {
    const copyButton = document.getElementById('utg-copy-button');
    const resultTextarea = document.getElementById('utg-result');
    
    if (copyButton && resultTextarea) {
        copyButton.addEventListener('click', function() {
            resultTextarea.select();
            document.execCommand('copy');
            
            const originalText = copyButton.textContent;
            copyButton.textContent = '<?php _e('Copied!', 'url-to-gutenberg'); ?>';
            
            setTimeout(function() {
                copyButton.textContent = originalText;
            }, 2000);
        });
    }
    
    // Show textarea container when there's content
    const textarea = document.getElementById('utg-result');
    if (textarea) {
        textarea.addEventListener('change', function() {
            if (textarea.value) {
                document.querySelector('.utg-textarea-container').style.display = 'block';
            }
        });
    }
});
</script>

<script>
// Inline script to provide essential variables
window.utgVars = {
    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    nonce: '<?php echo esc_js($ajax_nonce); ?>',
    debugMode: <?php echo $this->settings->get('debug_mode', false) ? 'true' : 'false'; ?>
};
</script> 