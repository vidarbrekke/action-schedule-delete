<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php \_e('URL to Gutenberg Converter', 'url-to-gutenberg'); ?></h1>
    
    <div class="utg-converter-form">
        <h2><?php _e('Convert URL to WordPress Post', 'url-to-gutenberg'); ?></h2>
        
        <form id="utg-url-form" class="utg-form">
            <?php wp_nonce_field('utg_ajax_nonce'); ?>
            <div class="utg-form-field">
                <label for="utg-url"><?php \_e('Enter URL', 'url-to-gutenberg'); ?></label>
                <input type="url" id="utg-url" name="url" class="regular-text" placeholder="https://example.com/page-to-convert">
                <p class="description">
                    <?php \_e('Enter the full URL of the page you want to convert to Gutenberg blocks.', 'url-to-gutenberg'); ?>
                </p>
            </div>
            
            <div class="utg-form-field">
                <label>
                    <input type="checkbox" id="parse-only" name="parse_only" value="true">
                    <?php \_e('Only parse HTML (faster, no AI enhancement)', 'url-to-gutenberg'); ?>
                </label>
            </div>
            
            <div class="utg-form-actions">
                <button type="submit" id="utg-submit" class="button button-primary">
                    <?php \_e('Convert URL', 'url-to-gutenberg'); ?>
                </button>
                <span class="spinner utg-spinner"></span>
            </div>
        </form>
        
        <div class="utg-loading hidden">
            <span class="spinner is-active"></span>
            <span class="utg-loading-text"><?php _e('Processing URL...', 'url-to-gutenberg'); ?></span>
        </div>
        
        <div class="utg-result hidden">
            <div class="utg-result-message"></div>
            <div class="utg-result-actions"></div>
        </div>
    </div>
</div> 