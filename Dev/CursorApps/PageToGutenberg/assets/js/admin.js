/**
 * URL to Gutenberg Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Prevent multiple initializations
    if (window.utgInitialized) {
        console.warn('UTG: Admin script already initialized, skipping duplicate execution');
        return;
    }
    
    // Set the initialization flag
    window.utgInitialized = true;
    
    // Log script initialization
    console.log('UTG: Admin script initialized');
    
    // Global variables
    var $form, $url, $parseOnly, $cleaningLevel, $submitButton, $preview, $result, $resultMessage;
    var isSubmitting = false;
    
    /**
     * Initialize the script
     */
    function init() {
        console.log('UTG: Starting initialization');
        // Initialize both settings and converter page functionality
        initSettingsPage();
        initConverterPage();
        console.log('UTG: Initialization complete');
    }
    
    /**
     * Initialize settings page functionality
     */
    function initSettingsPage() {
        // Check if we're on the settings page
        var $testApiBtn = $('#utg-test-api');
        console.log('UTG: Test API button found?', $testApiBtn.length > 0);
        
        if (!$testApiBtn.length) {
            console.log('UTG: Test API button not found, possibly not on settings page');
            return;
        }
        
        console.log('UTG: Initializing settings page functionality');
        
        // Log button properties for debugging
        console.log('UTG: Test button ID:', $testApiBtn.attr('id'));
        console.log('UTG: Test button text:', $testApiBtn.text());
        
        // Add event listener for test API button
        $testApiBtn.on('click', function(e) {
            console.log('UTG: Test API button clicked');
            e.preventDefault();
            testApiConnection();
        });
        
        console.log('UTG: Test API button event listener attached');
    }
    
    /**
     * Initialize converter page functionality
     */
    function initConverterPage() {
        // Check if we're on the converter page by looking for our form
        $form = $('#utg-form');
        if (!$form.length) {
            console.log('UTG: Form not found, possibly not on URL converter page');
            return;
        }
        
        console.log('UTG: Initializing converter page functionality');
        
        // Cache DOM elements
        $url = $('#utg-url');
        $parseOnly = $('#utg-parse-only');
        $cleaningLevel = $('#utg-cleaning-level');
        $submitButton = $('#utg-submit');
        $preview = $('#utg-preview');
        $result = $('#utg-result');
        $resultMessage = $('#utg-result-message');
        
        // Add event listeners
        console.log('UTG: Form found, adding event listeners');
        $form.on('submit', handleFormSubmit);
        $parseOnly.on('change', handleParseOnlyChange);
        
        // Initialize UI state
        handleParseOnlyChange();
        
        // Show the textarea container if it already has content
        if ($result && $result.val && $result.val()) {
            $('.utg-textarea-container').show();
        }
    }
    
    /**
     * Test the API connection
     */
    function testApiConnection() {
        var $testBtn = $('#utg-test-api');
        var $spinner = $testBtn.next('.spinner');
        var $result = $('#utg-test-result');
        
        console.log('UTG: testApiConnection called');
        console.log('UTG: utgVars available?', typeof utgVars !== 'undefined');
        
        if (typeof utgVars === 'undefined') {
            console.error('UTG: utgVars is not defined, AJAX request cannot proceed');
            alert('Error: WordPress AJAX variables not found. Please refresh the page and try again.');
            return;
        }
        
        // Log AJAX parameters for debugging
        console.log('UTG: AJAX URL:', utgVars.ajaxUrl);
        console.log('UTG: Security nonce:', utgVars.nonce ? 'Available' : 'Missing');
        
        // Prevent multiple test requests
        if ($testBtn.prop('disabled')) {
            console.log('UTG: Button disabled, ignoring click');
            return;
        }
        
        // Update UI to show we're testing
        $testBtn.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.removeClass('notice-success notice-error').addClass('hidden').empty();
        
        console.log('UTG: Testing API connection');
        
        // AJAX request to test the API connection
        $.ajax({
            url: utgVars.ajaxUrl,
            type: 'POST',
            data: {
                action: 'utg_test_api_connection',
                security: utgVars.nonce
            },
            dataType: 'json',
            success: function(response) {
                console.log('UTG: API test response received', response);
                
                if (response.success) {
                    $result.addClass('notice notice-success').html('<p>' + response.data.message + '</p>');
                } else {
                    $result.addClass('notice notice-error').html('<p>' + response.data.message + '</p>');
                }
                
                $result.removeClass('hidden');
            },
            error: function(xhr, status, error) {
                console.error('UTG: API test AJAX error', {xhr: xhr, status: status, error: error});
                console.error('UTG: Response text:', xhr.responseText);
                
                // Format error message
                var errorMessage = 'Connection error: ';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage += xhr.responseJSON.data.message;
                } else {
                    errorMessage += status + ' - ' + error;
                }
                
                $result.addClass('notice notice-error').html('<p>' + errorMessage + '</p>').removeClass('hidden');
            },
            complete: function() {
                console.log('UTG: API test request complete');
                // Restore UI
                $testBtn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    }
    
    /**
     * Handle form submission
     * @param {Event} e - The submit event
     */
    function handleFormSubmit(e) {
        e.preventDefault();
        
        if (isSubmitting) {
            console.log('UTG: Already processing, ignoring duplicate submission');
            return;
        }
        
        // Get the URL value
        var url = $url.val().trim();
        
        // Validate URL
        if (!url) {
            showError('Please enter a URL');
            return;
        }
        
        // Set form state to loading
        setFormSubmitting(true);
        
        // Log the request details
        console.log('UTG: Starting URL conversion request', {
            url: url,
            parseOnly: $parseOnly.is(':checked'),
            cleaningLevel: $cleaningLevel.val(),
            model: utgVars.defaultModel || 'default'
        });
        
        // Prepare AJAX data
        var data = {
            action: 'utg_convert_url',
            url: url,
            parse_only: $parseOnly.is(':checked'),
            cleaning_level: $cleaningLevel.val(),
            security: utgVars.nonce
        };
        
        // Send the AJAX request
        $.ajax({
            url: utgVars.ajaxUrl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                console.log('UTG: Received successful AJAX response', response);
                
                if (response.success) {
                    handleSuccess(response.data);
                } else {
                    handleError(response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('UTG: AJAX error', {xhr: xhr, status: status, error: error});
                
                // Extract error details
                var errorMessage = 'AJAX error: ' + status;
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                } else if (xhr.responseText) {
                    try {
                        var jsonResponse = JSON.parse(xhr.responseText);
                        if (jsonResponse.data) {
                            errorMessage = jsonResponse.data;
                        }
                    } catch (e) {
                        errorMessage = 'Server error: ' + error;
                    }
                }
                
                handleError(errorMessage);
            },
            complete: function() {
                setFormSubmitting(false);
            }
        });
    }
    
    /**
     * Handle successful response
     * @param {Object} data - The response data
     */
    function handleSuccess(data) {
        console.log('UTG: Processing successful response');
        
        // Show success message
        showSuccess(data.message || 'URL successfully converted');
        
        // Display preview if available
        if (data.preview) {
            $preview.html('<div class="utg-preview-content">' + data.preview + '</div>');
            $preview.show();
        }
        
        // Display result content if available
        if (data.content) {
            const preformattedContent = $parseOnly.is(':checked') ? 
                data.content : 
                '<!-- wp:html -->\n' + data.content + '\n<!-- /wp:html -->';
            
            $result.val(preformattedContent);
            $result.show();
            
            // Add debug file info if available
            if (data.debug_file) {
                $resultMessage.append(' <span class="utg-debug-info">(Debug file: ' + data.debug_file + ')</span>');
            }
        }
        
        // Scroll to results
        scrollToResults();
    }
    
    /**
     * Handle error response
     * @param {string} message - The error message
     */
    function handleError(message) {
        console.error('UTG: Error processing URL', message);
        showError(message || 'An unknown error occurred');
        $preview.hide();
        $result.hide();
    }
    
    /**
     * Show success message
     * @param {string} message - The success message
     */
    function showSuccess(message) {
        $resultMessage.removeClass('utg-error').addClass('utg-success').html(message).show();
    }
    
    /**
     * Show error message
     * @param {string} message - The error message
     */
    function showError(message) {
        $resultMessage.removeClass('utg-success').addClass('utg-error').html(message).show();
    }
    
    /**
     * Set form to submitting or not submitting state
     * @param {boolean} submitting - Whether the form is submitting
     */
    function setFormSubmitting(submitting) {
        isSubmitting = submitting;
        
        if (submitting) {
            $submitButton.prop('disabled', true).addClass('utg-loading').val('Processing...');
            $resultMessage.hide();
        } else {
            $submitButton.prop('disabled', false).removeClass('utg-loading').val('Convert URL');
        }
    }
    
    /**
     * Handle parse only checkbox change
     */
    function handleParseOnlyChange() {
        var isParseOnly = $parseOnly.is(':checked');
        console.log('UTG: Parse only changed to', isParseOnly);
        // No UI changes needed anymore since we removed the model dropdown
    }
    
    /**
     * Scroll to results section
     */
    function scrollToResults() {
        $('html, body').animate({
            scrollTop: $resultMessage.offset().top - 100
        }, 500);
    }
    
    // Initialize on document ready
    $(document).ready(init);
    
})(jQuery); 