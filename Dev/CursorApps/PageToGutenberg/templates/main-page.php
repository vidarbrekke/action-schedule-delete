<?php
/**
 * Main plugin admin page template.
 *
 * @package UTG
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <div class="utg-container">
        <div class="utg-form-container">
            <h2><?php esc_html_e( 'Convert URL to Gutenberg Blocks', 'url-to-gutenberg' ); ?></h2>
            
            <p class="description">
                <?php esc_html_e( 'Enter a URL below to extract its content and convert it into Gutenberg blocks. A draft post will be created with the converted content.', 'url-to-gutenberg' ); ?>
            </p>
            
            <form id="utg-url-form" class="utg-form">
                <div class="utg-form-field">
                    <label for="utg-url"><?php esc_html_e( 'URL', 'url-to-gutenberg' ); ?></label>
                    <input type="url" id="utg-url" name="url" class="regular-text" placeholder="https://example.com/article" required>
                </div>
                
                <div class="utg-form-submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Convert', 'url-to-gutenberg' ); ?>
                    </button>
                </div>
            </form>
            
            <div id="utg-result" class="utg-result" style="display: none;">
                <div class="utg-result-content"></div>
                <div class="utg-result-actions">
                    <a href="#" class="button button-secondary" id="utg-edit-post" target="_blank">
                        <?php esc_html_e( 'Edit Draft Post', 'url-to-gutenberg' ); ?>
                    </a>
                </div>
            </div>
            
            <div id="utg-loading" class="utg-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <span class="utg-loading-text"><?php esc_html_e( 'Processing URL...', 'url-to-gutenberg' ); ?></span>
            </div>
            
            <div id="utg-error" class="utg-error notice notice-error" style="display: none;"></div>
        </div>
        
        <div class="utg-sidebar">
            <div class="utg-box">
                <h3><?php esc_html_e( 'How It Works', 'url-to-gutenberg' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'Enter a URL in the form', 'url-to-gutenberg' ); ?></li>
                    <li><?php esc_html_e( 'Our hybrid extraction system will fetch and parse the content', 'url-to-gutenberg' ); ?></li>
                    <li><?php esc_html_e( 'The content is processed with a language model to create Gutenberg blocks', 'url-to-gutenberg' ); ?></li>
                    <li><?php esc_html_e( 'A draft post is created with the converted content', 'url-to-gutenberg' ); ?></li>
                </ol>
            </div>
            
            <div class="utg-box">
                <h3><?php esc_html_e( 'Tips', 'url-to-gutenberg' ); ?></h3>
                <ul>
                    <li><?php esc_html_e( 'Works with most article and blog post URLs', 'url-to-gutenberg' ); ?></li>
                    <li><?php esc_html_e( 'JavaScript-heavy sites are supported via our hybrid extraction system', 'url-to-gutenberg' ); ?></li>
                    <li><?php esc_html_e( 'Configure API settings in the Settings page', 'url-to-gutenberg' ); ?></li>
                    <li><?php esc_html_e( 'Enable debug mode for troubleshooting', 'url-to-gutenberg' ); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#utg-url-form').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading
        $('#utg-result').hide();
        $('#utg-error').hide();
        $('#utg-loading').show();
        
        // Get URL
        var url = $('#utg-url').val();
        
        // Send AJAX request
        $.ajax({
            url: utg_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'utg_process_url',
                url: url,
                nonce: utg_vars.nonce
            },
            success: function(response) {
                $('#utg-loading').hide();
                
                if (response.success) {
                    // Show result
                    $('#utg-result .utg-result-content').html('<p>' + response.data.message + '</p>');
                    $('#utg-edit-post').attr('href', response.data.edit_url);
                    $('#utg-result').show();
                } else {
                    // Show error
                    $('#utg-error').html('<p>' + utg_vars.error_text + ' ' + response.data.message + '</p>');
                    $('#utg-error').show();
                }
            },
            error: function() {
                $('#utg-loading').hide();
                $('#utg-error').html('<p>' + utg_vars.error_text + ' ' + '<?php esc_html_e( 'An unknown error occurred.', 'url-to-gutenberg' ); ?>' + '</p>');
                $('#utg-error').show();
            }
        });
    });
});
</script>

<style>
.utg-container {
    display: flex;
    margin-top: 20px;
}

.utg-form-container {
    flex: 2;
    margin-right: 30px;
}

.utg-sidebar {
    flex: 1;
}

.utg-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-bottom: 20px;
    padding: 15px;
}

.utg-form-field {
    margin-bottom: 15px;
}

.utg-form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.utg-form-submit {
    margin-top: 20px;
}

.utg-loading {
    margin-top: 20px;
    display: flex;
    align-items: center;
}

.utg-loading-text {
    margin-left: 10px;
}

.utg-result {
    margin-top: 20px;
    background: #f0f6fc;
    border-left: 4px solid #2271b1;
    padding: 15px;
}

.utg-result-actions {
    margin-top: 15px;
}

.utg-error {
    margin-top: 20px;
}
</style> 