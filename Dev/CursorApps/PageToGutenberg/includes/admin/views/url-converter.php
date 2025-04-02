<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="utg-converter-form">
        <h2><?php _e('Convert URL to WordPress Post', 'url-to-gutenberg'); ?></h2>
        
        <form id="utg-url-form" method="post">
            <div class="utg-form-field">
                <label for="utg-url"><?php _e('Enter URL', 'url-to-gutenberg'); ?></label>
                <input type="url" id="utg-url" name="utg-url" class="regular-text" placeholder="https://example.com/page-to-convert">
                <p class="description"><?php _e('Enter the full URL of the page you want to convert to a WordPress post.', 'url-to-gutenberg'); ?></p>
            </div>
            
            <div class="utg-form-actions">
                <button type="submit" class="button button-primary">
                    <?php _e('Convert to Post', 'url-to-gutenberg'); ?>
                </button>
                <span class="spinner"></span>
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