/**
 * URL to Gutenberg Admin JavaScript
 */
jQuery(document).ready(function($) {
    if (utgParams.debugMode) {
        console.log('UTG Admin JS loaded');
    }
    
    // Test API Connection
    $(document).on('click', '#utg-test-api', function(e) {
        e.preventDefault();
        if (utgParams.debugMode) {
            console.log('Test API button clicked');
        }
        
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var $result = $('#utg-test-result');
        
        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.css('visibility', 'visible');
        $result.removeClass('hidden').html(utgParams.testingText);
        
        // Make AJAX request
        $.ajax({
            url: utgParams.ajaxUrl,
            type: 'POST',
            data: {
                action: 'utg_test_api_connection',
                nonce: utgParams.nonce
            },
            success: function(response) {
                if (utgParams.debugMode) {
                    console.log('API test response:', response);
                }
                
                if (response.success) {
                    $result.removeClass('notice-error').addClass('notice-success').html('<p>' + response.data.message + '</p>');
                } else {
                    $result.removeClass('notice-success').addClass('notice-error').html('<p>' + utgParams.errorText + response.data.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                if (utgParams.debugMode) {
                    console.error('AJAX error:', status, error);
                }
                $result.removeClass('notice-success').addClass('notice-error').html('<p>' + utgParams.i18n.serverError + '</p>');
            },
            complete: function() {
                // Re-enable button and hide spinner
                $button.prop('disabled', false);
                $spinner.css('visibility', 'hidden');
            }
        });
    });
    
    // URL Converter Form
    $('#utg-url-form').on('submit', function(e) {
        e.preventDefault();
        
        if (utgParams.debugMode) {
            console.log('URL conversion form submitted');
        }
        
        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');
        var $url = $form.find('#utg-url');
        var $loading = $('.utg-loading');
        var $result = $('.utg-result');
        
        // Validate URL
        if (!$url.val()) {
            alert(utgParams.i18n.enterValidUrl);
            $url.focus();
            return;
        }
        
        if (utgParams.debugMode) {
            console.log('Making AJAX request to convert URL:', $url.val());
        }
        
        // Disable button and show loading indicator
        $submitButton.prop('disabled', true);
        $loading.removeClass('hidden');
        $result.addClass('hidden');
        
        // Make AJAX request
        $.ajax({
            url: utgParams.ajaxUrl,
            type: 'POST',
            data: {
                action: 'utg_convert_url',
                url: $url.val(),
                nonce: utgParams.nonce
            },
            success: function(response) {
                if (utgParams.debugMode) {
                    console.log('URL convert response:', response);
                }
                
                if (response.success) {
                    // Show success message and edit link
                    $result.removeClass('utg-error').addClass('utg-success').html(
                        '<div class="utg-result-message">' +
                        '<p>' + response.data.message + '</p>' +
                        '</div>' +
                        '<div class="utg-result-actions">' +
                        '<a href="' + response.data.edit_url + '" class="button button-primary">Edit Post</a> ' +
                        '<a href="' + response.data.view_url + '" class="button" target="_blank">View Post</a>' +
                        '</div>'
                    ).removeClass('hidden');
                    
                    // Clear the URL field
                    $url.val('');
                } else {
                    // Show error message
                    $result.removeClass('utg-success').addClass('utg-error').html(
                        '<div class="utg-result-message">' +
                        '<p>' + utgParams.errorText + response.data.message + '</p>' +
                        '</div>'
                    ).removeClass('hidden');
                }
            },
            error: function(xhr, status, error) {
                if (utgParams.debugMode) {
                    console.error('AJAX error:', status, error);
                }
                // Show generic error message
                $result.removeClass('utg-success').addClass('utg-error').html(
                    '<div class="utg-result-message">' +
                    '<p>' + utgParams.i18n.serverError + '</p>' +
                    '</div>'
                ).removeClass('hidden');
            },
            complete: function() {
                // Re-enable button and hide loading indicator
                $submitButton.prop('disabled', false);
                $loading.addClass('hidden');
            }
        });
    });
}); 