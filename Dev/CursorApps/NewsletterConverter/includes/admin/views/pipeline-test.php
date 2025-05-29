<?php
/**
 * Pipeline Test View
 *
 * @package UTG
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('URL to Gutenberg - Pipeline Test', 'url-to-gutenberg'); ?></h1>
    
    <div class="utg-container">
        <div class="utg-form-container">
            <div class="utg-box">
                <h2><?php esc_html_e('Test Content Pipeline', 'url-to-gutenberg'); ?></h2>
                <p><?php esc_html_e('Enter a URL to test the complete content pipeline workflow.', 'url-to-gutenberg'); ?></p>
                
                <form id="utg-pipeline-test-form" method="post">
                    <?php wp_nonce_field('utg_pipeline_test', 'utg_pipeline_test_nonce'); ?>
                    
                    <div class="utg-form-field">
                        <label for="utg-url"><?php esc_html_e('URL', 'url-to-gutenberg'); ?></label>
                        <input type="url" id="utg-url" name="utg_url" class="regular-text" placeholder="https://example.com" required>
                    </div>
                    
                    <div class="utg-form-field">
                        <label for="utg-post-status"><?php esc_html_e('Post Status', 'url-to-gutenberg'); ?></label>
                        <select id="utg-post-status" name="utg_post_status">
                            <option value="draft"><?php esc_html_e('Draft', 'url-to-gutenberg'); ?></option>
                            <option value="publish"><?php esc_html_e('Publish', 'url-to-gutenberg'); ?></option>
                            <option value="pending"><?php esc_html_e('Pending', 'url-to-gutenberg'); ?></option>
                            <option value="private"><?php esc_html_e('Private', 'url-to-gutenberg'); ?></option>
                        </select>
                    </div>
                    
                    <div class="utg-form-field">
                        <label for="utg-post-type"><?php esc_html_e('Post Type', 'url-to-gutenberg'); ?></label>
                        <select id="utg-post-type" name="utg_post_type">
                            <?php 
                            $post_types = get_post_types(['public' => true], 'objects');
                            foreach ($post_types as $post_type) {
                                echo '<option value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->label) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="utg-form-submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Test Pipeline', 'url-to-gutenberg'); ?></button>
                    </div>
                </form>
                
                <div id="utg-pipeline-loading" class="utg-loading" style="display: none;">
                    <span class="spinner is-active"></span>
                    <span class="utg-loading-text"><?php esc_html_e('Processing URL...', 'url-to-gutenberg'); ?></span>
                </div>
            </div>
        </div>
        
        <div class="utg-sidebar">
            <div class="utg-box">
                <h3><?php esc_html_e('Pipeline Workflow', 'url-to-gutenberg'); ?></h3>
                <ol>
                    <li><?php esc_html_e('URL Submission', 'url-to-gutenberg'); ?></li>
                    <li><?php esc_html_e('HTML Content Extraction', 'url-to-gutenberg'); ?></li>
                    <li><?php esc_html_e('LLM Processing (temp=0.1)', 'url-to-gutenberg'); ?></li>
                    <li><?php esc_html_e('Content Parsing', 'url-to-gutenberg'); ?></li>
                    <li><?php esc_html_e('Image Processing', 'url-to-gutenberg'); ?></li>
                    <li><?php esc_html_e('WordPress Post Creation', 'url-to-gutenberg'); ?></li>
                </ol>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=url-to-gutenberg-settings')); ?>"><?php esc_html_e('Configure Settings', 'url-to-gutenberg'); ?></a></p>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=url-to-gutenberg')); ?>"><?php esc_html_e('URL Converter', 'url-to-gutenberg'); ?></a></p>
            </div>
            
            <div class="utg-box">
                <h3><?php esc_html_e('Documentation', 'url-to-gutenberg'); ?></h3>
                <p><?php esc_html_e('For detailed information about the pipeline workflow, check the documentation:', 'url-to-gutenberg'); ?></p>
                <p><a href="<?php echo esc_url(plugins_url('docs/pipeline-workflow.md', UTG_PLUGIN_FILE)); ?>" target="_blank"><?php esc_html_e('Pipeline Documentation', 'url-to-gutenberg'); ?></a></p>
            </div>
        </div>
    </div>
    
    <div id="utg-pipeline-result" class="utg-result" style="display: none;">
        <h3><?php esc_html_e('Test Results', 'url-to-gutenberg'); ?></h3>
        <div id="utg-pipeline-result-content"></div>
        <div id="utg-pipeline-result-actions" class="utg-result-actions" style="display: none;">
            <a id="utg-edit-post-link" href="#" class="button" target="_blank"><?php esc_html_e('Edit Post', 'url-to-gutenberg'); ?></a>
            <a id="utg-view-post-link" href="#" class="button" target="_blank"><?php esc_html_e('View Post', 'url-to-gutenberg'); ?></a>
        </div>
    </div>
    
    <div id="utg-pipeline-error" class="utg-error notice notice-error" style="display: none;">
        <p id="utg-pipeline-error-message"></p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#utg-pipeline-test-form').on('submit', function(e) {
        e.preventDefault();
        
        // Reset UI
        $('#utg-pipeline-result').hide();
        $('#utg-pipeline-result-actions').hide();
        $('#utg-pipeline-error').hide();
        $('#utg-pipeline-loading').show();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'utg_test_pipeline',
                security: $('#utg_pipeline_test_nonce').val(),
                url: $('#utg-url').val(),
                post_status: $('#utg-post-status').val(),
                post_type: $('#utg-post-type').val()
            },
            success: function(response) {
                $('#utg-pipeline-loading').hide();
                
                if (response.success) {
                    $('#utg-pipeline-result-content').html(
                        '<p><strong>Success!</strong> Post created with ID: ' + response.data.post_id + '</p>' +
                        '<p>Title: ' + response.data.title + '</p>' +
                        '<p>Processing time: ' + response.data.execution_time + ' seconds</p>'
                    );
                    
                    $('#utg-edit-post-link').attr('href', response.data.edit_url);
                    $('#utg-view-post-link').attr('href', response.data.view_url);
                    $('#utg-pipeline-result-actions').show();
                    $('#utg-pipeline-result').show();
                } else {
                    // Fix for [object Object] error - properly handle error message
                    var errorMessage = '';
                    if (typeof response.data === 'object' && response.data !== null) {
                        // If response.data is an object, try to extract error message
                        if (response.data.message) {
                            errorMessage = response.data.message;
                        } else {
                            // Convert object to a readable format
                            errorMessage = JSON.stringify(response.data);
                        }
                    } else {
                        // Use response.data directly if it's a string or other primitive
                        errorMessage = response.data;
                    }
                    $('#utg-pipeline-error-message').text(errorMessage);
                    $('#utg-pipeline-error').show();
                }
            },
            error: function(xhr, status, error) {
                $('#utg-pipeline-loading').hide();
                
                // Improved error handling
                var errorMessage = 'AJAX error: ' + error;
                
                // Try to parse response JSON if available
                if (xhr.responseText) {
                    try {
                        var responseObj = JSON.parse(xhr.responseText);
                        if (responseObj.message) {
                            errorMessage = responseObj.message;
                        } else if (responseObj.data) {
                            if (typeof responseObj.data === 'object') {
                                errorMessage = JSON.stringify(responseObj.data);
                            } else {
                                errorMessage = responseObj.data;
                            }
                        }
                    } catch (e) {
                        // If parsing fails, use the error as is
                        console.log('Error parsing response:', e);
                    }
                }
                
                $('#utg-pipeline-error-message').text(errorMessage);
                $('#utg-pipeline-error').show();
            }
        });
    });
});
</script> 