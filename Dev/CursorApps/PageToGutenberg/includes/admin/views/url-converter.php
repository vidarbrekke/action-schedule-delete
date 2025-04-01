<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="utg-converter-form">
        <h2><?php _e('Convert URL to WordPress Post', 'url-to-gutenberg'); ?></h2>
        
        <div class="utg-form-field">
            <label for="utg-url"><?php _e('Enter URL', 'url-to-gutenberg'); ?></label>
            <input type="url" id="utg-url" name="utg-url" class="regular-text" placeholder="https://example.com/page-to-convert">
            <p class="description"><?php _e('Enter the full URL of the page you want to convert to a WordPress post.', 'url-to-gutenberg'); ?></p>
        </div>
        
        <div class="utg-form-actions">
            <button type="button" id="utg-convert-url" class="button button-primary">
                <?php _e('Convert to Post', 'url-to-gutenberg'); ?>
            </button>
            <span class="spinner"></span>
        </div>
        
        <div id="utg-conversion-result" class="hidden">
            <div class="utg-result-message">
                <div class="utg-message-content"></div>
                <div class="utg-debug-info hidden"></div>
            </div>
            <div class="utg-result-actions hidden">
                <a href="#" class="button utg-edit-post"><?php _e('Edit Post', 'url-to-gutenberg'); ?></a>
                <a href="#" class="button utg-view-post"><?php _e('View Post', 'url-to-gutenberg'); ?></a>
            </div>
        </div>
    </div>
</div> 