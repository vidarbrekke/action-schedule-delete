/**
 * WCAC Admin JS (Refactored for Batch Processing)
 */
jQuery(document).ready(function($) {
    'use strict';
    // console.log('[WCAC DEBUG] wcac-admin.js: Document ready started.'); // Keep this for basic JS load confirmation

    // --- BEGIN: Global debug logging toggle ---
    // const debugEnabled = typeof wcacAdmin !== 'undefined' && typeof wcacAdmin.enable_debug_logging !== 'undefined' && wcacAdmin.enable_debug_logging;
    // --- END: Global debug logging toggle ---
    // if (debugEnabled) {
    //     console.log('WCAC Admin JS: Document Ready - debugEnabled is true.');
    // }

    // --- Re-Index All Content (delegated) --- //
    // ... existing code ...
    // --- Restore Default Prompt (delegated) --- //
    $(document).on('click', '#wcac-restore-default-prompt', function(e) {
        e.preventDefault();
        console.log('[WCAC DEBUG] Restore Default Prompt button clicked (DELEGATED HANDLER).'); // Clarified log

        if (typeof wcacAdmin === 'undefined') {
            console.error('[WCAC DEBUG] wcacAdmin object is undefined (DELEGATED HANDLER).');
            alert('Error: Plugin scripts may not be loaded correctly. Please refresh and try again.');
            return;
        }
        if (typeof wcacAdmin.default_system_prompt === 'undefined') {
            console.error('[WCAC DEBUG] wcacAdmin.default_system_prompt is undefined (DELEGATED HANDLER).');
            alert('Error: Default prompt data is missing. Please contact support.');
            return;
        }
        
        const $systemPromptField = $('#wcac_system_prompt');
        if (!$systemPromptField.length) {
            console.error('[WCAC DEBUG] Textarea #wcac_system_prompt not found (DELEGATED HANDLER).');
            alert('Error: System prompt field not found. Please contact support.');
            return;
        }

        // Use the localized confirmation message
        if (wcacAdmin.restore_prompt_confirm && confirm(wcacAdmin.restore_prompt_confirm)) {
            $systemPromptField.val(wcacAdmin.default_system_prompt);
            // Use a generic success message, or localize a specific one if preferred
            alert('Default prompt restored. Remember to Save Settings.'); 
        } else if (!wcacAdmin.restore_prompt_confirm) {
            // Fallback if the localized confirm message is somehow missing
            console.warn('[WCAC DEBUG] wcacAdmin.restore_prompt_confirm is undefined. Using generic confirm.');
            if (confirm('Are you sure you want to restore the default system prompt? Any changes will be lost.')) {
                $systemPromptField.val(wcacAdmin.default_system_prompt);
                alert('Default prompt restored. Remember to Save Settings.');
            }
        }
    });

    // --- Glossary Modal Logic --- //
    const $glossaryModal = $('#wcac-glossary-modal');
    const $glossaryLink = $('#wcac-scoring-glossary-link');
    const $glossaryContent = $('#wcac-glossary-content');
    const $modalClose = $('.wcac-modal-close');
    var glossaryData = null; // Cache glossary data

    if ($glossaryLink.length && $glossaryModal.length) {
        $glossaryLink.on('click', function(e) {
            e.preventDefault();
            if (glossaryData) {
                // Use cached data
                populateAndShowGlossary(glossaryData);
            } else {
                // Fetch data via AJAX
                $glossaryContent.html('<p>Loading...</p>');
                $glossaryModal.show(); // Show modal with loading text
                
                $.ajax({
                    url: wcacAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wcac_get_scoring_glossary',
                        nonce: wcacAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            glossaryData = response.data; // Cache the data
                            populateAndShowGlossary(glossaryData);
                        } else {
                            const errorMsg = response.data && response.data.message ? response.data.message : 'Failed to load glossary data.';
                            $glossaryContent.html('<p style="color:red;">Error: ' + errorMsg + '</p>');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Glossary AJAX Error:', textStatus, errorThrown);
                        $glossaryContent.html('<p style="color:red;">Error: Could not contact server.</p>');
                    }
                });
            }
        });

        // Close modal handlers
        $modalClose.on('click', function() {
            $glossaryModal.hide();
        });
        $glossaryModal.on('click', function(e) {
            if (e.target === this) { // Click outside the modal content
                $glossaryModal.hide();
            }
        });
        $(document).on('keydown', function(e) {
            if (e.key === "Escape" && $glossaryModal.is(':visible')) {
                $glossaryModal.hide();
            }
        });
    }
    
    function populateAndShowGlossary(data) {
        let htmlContent = '<dl>';
        for (const term in data) {
            if (data.hasOwnProperty(term)) {
                htmlContent += `<dt>${term}</dt><dd>${data[term]}</dd>`;
            }
        }
        htmlContent += '</dl>';
        $glossaryContent.html(htmlContent);
        $glossaryModal.show(); // Ensure it's visible if already loaded
    }
    
    // --- Test LLM Connection --- //
    $(document).on('click', '#wcac-test-llm-connection', function() {
        const $button = $(this);
        const $toast = $('#wcac-llm-test-toast');
        const apiKey = $('input[name="' + wcacAdmin.option_key + '[wcac_api_key]"]').val(); // Assumes wcacAdmin.option_key is localized
        const model = $('input[name="' + wcacAdmin.option_key + '[wcac_model]"]').val(); // Assumes wcacAdmin.option_key is localized

        if (!wcacAdmin.option_key) {
            console.error('WCAC Admin JS: wcacAdmin.option_key is not defined. Cannot get API key or model for test.');
            $toast.text('Error: Plugin configuration missing (option_key). Cannot test.').css({'background-color': '#dc3232', 'color': 'white'}).fadeIn();
            setTimeout(function() { $toast.fadeOut(); }, 5000);
            return;
        }

        $button.prop('disabled', true).text(wcacAdmin.testing_connection_text || 'Testing...'); // Assumes wcacAdmin.testing_connection_text is localized
        $toast.hide();

        $.ajax({
            url: wcacAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'wcac_test_llm_connection',
                nonce: wcacAdmin.nonce,
                // The AJAX handler ajax_test_llm_connection fetches options directly using Wcac_Utils::get_option.
                // So, we don't strictly need to send api_key and model from JS if they are already saved.
                // However, to test unsaved values, it would be better if the AJAX handler could accept them as parameters.
                // For now, relying on saved values as per the current PHP AJAX handler.
                // If the PHP handler is updated to accept these, uncomment the following:
                // wcac_api_key: apiKey, 
                // wcac_model: model
            },
            success: function(response) {
                if (response.success) {
                    $toast.text(response.data.message || 'Connection successful!').css({'background-color': '#4CAF50', 'color': 'white'}).fadeIn();
                } else {
                    $toast.text(response.data.message || 'Connection failed.').css({'background-color': '#dc3232', 'color': 'white'}).fadeIn();
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('LLM Connection Test AJAX Error:', textStatus, errorThrown);
                $toast.text('AJAX error: Could not contact server.').css({'background-color': '#dc3232', 'color': 'white'}).fadeIn();
            },
            complete: function() {
                $button.prop('disabled', false).text(wcacAdmin.test_connection_text || 'Test Connection'); // Assumes wcacAdmin.test_connection_text is localized
                setTimeout(function() { $toast.fadeOut(); }, 5000);
            }
        });
    });

    // Initialize tabs if we're on the settings page
    // and PHP-rendered tabs (.nav-tab-wrapper) are NOT already present.
    if ($('.wcac-settings-wrapper').length && !$('.nav-tab-wrapper').length) {
        const $form = $('.wcac-settings-wrapper form');
        
        // Define tab sections with their h2 text as identifiers
        const sections = {
            'main': {
                label: 'Core Settings',
                title: 'Core Settings'
            },
            'indexing': {
                label: 'Content Indexing',
                title: 'Content Indexing'
            },
            'customization': {
                label: 'Customization',
                title: 'Customization & Filtering'
            },
            'llm_params': {
                label: 'LLM Parameters',
                title: 'LLM API Parameters'
            },
            'scoring': {
                label: 'Scoring Rules',
                title: 'Scoring Rules'
            }
        };

        // Create tab structure
        const $tabContainer = $('<div class="wcac-tabs"></div>');
        const $tabNav = $('<ul class="wcac-tab-nav"></ul>');
        
        // Create tab navigation
        Object.entries(sections).forEach(([key, {label}], index) => {
            $tabNav.append(`<li data-tab="${key}" class="${index === 0 ? 'active' : ''}">${label}</li>`);
        });
        
        // Insert tab navigation at the start of the form
        $form.prepend($tabContainer.append($tabNav));
        
        // Create tab content containers and move submit button outside
        const $submitButton = $form.find('p.submit');
        Object.entries(sections).forEach(([key, {title}], index) => {
            const $tabContent = $('<div class="wcac-tab-content"></div>')
                .attr('id', `wcac-${key}-tab`)
                .toggleClass('active', index === 0);
            
            // Find the section by its title and move it to the tab
            $form.find('h2').each(function() {
                if ($(this).text().trim() === title) {
                    const $section = $(this).nextUntil('h2, p.submit');
                    $tabContent.append($(this)).append($section);
                }
            });
            
            $tabContainer.append($tabContent);
        });

        // Move submit button outside tabs and style it
        if ($submitButton.length) {
            $submitButton.addClass('wcac-submit-button');
            $form.append($submitButton);
        }
        
        // Handle tab clicks
        $('.wcac-tab-nav li').on('click', function() {
            const tab = $(this).data('tab');
            
            // Update active states
            $('.wcac-tab-nav li').removeClass('active');
            $(this).addClass('active');
            
            // Show selected tab content
            $('.wcac-tab-content').removeClass('active');
            $(`#wcac-${tab}-tab`).addClass('active');
        });
    }

    // Initialize tabbed interface if it exists
    if ($('#wcac-tabs').length) {
        // Set up tabbed interface
        $('#wcac-tabs').tabs();
    }
    
    // --- TEST CSV UPLOAD LOGIC --- //
    const $csvUploadForm = $('#wcac-csv-upload-form');
    const $csvStatusSpan = $('#wcac-csv-upload-status');
    const $csvFileInput = $('#wcac_test_csv');
    const $csvSubmitButton = $csvUploadForm.find('button[type="submit"]');

    if ($csvUploadForm.length && $csvStatusSpan.length && $csvFileInput.length && $csvSubmitButton.length) {
        $csvUploadForm.on('submit', function(e) {
            e.preventDefault();
            console.log('[WCAC CSV Upload] event.preventDefault() CALLED.');
            $csvStatusSpan.text('');
            if ($csvFileInput[0].files.length === 0) {
                $csvStatusSpan.text('Error: Please select a CSV file.').css('color', 'red');
                return;
            }
            $csvStatusSpan.text('Uploading and processing... Please wait.').css('color', '');
            $csvSubmitButton.prop('disabled', true);
            $csvFileInput.prop('disabled', true);
            const formData = new FormData();
            formData.append('action', 'wcac_handle_upload_test_csv');
            const nonceInput = $csvUploadForm.find('input[name="wcac_upload_test_csv_nonce"]');
            if (nonceInput.length) {
                formData.append('wcac_upload_test_csv_nonce', nonceInput.val());
            }
            formData.append('wcac_test_csv', $csvFileInput[0].files[0]);
            $.ajax({
                url: wcacAdmin.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $csvStatusSpan.text('Test run complete! Reloading results...');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $csvStatusSpan.text('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error')).css('color', 'red');
                    }
                    $csvSubmitButton.prop('disabled', false);
                    $csvFileInput.prop('disabled', false);
                },
                error: function(xhr) {
                    $csvStatusSpan.text('AJAX error: ' + xhr.statusText).css('color', 'red');
                    $csvSubmitButton.prop('disabled', false);
                    $csvFileInput.prop('disabled', false);
                }
            });
        });
    }
    
    // --- TEST RESULTS DELETE LOGIC --- //
    const $deleteBtn = $('#wcac-delete-selected');
    const $selectAllCheckbox = $('#wcac-select-all');
    const $rowCheckboxes = $('.wcac-select-row');
    const $modal = $('#wcac-delete-confirm-modal');
    const $modalConfirmBtn = $('#wcac-confirm-delete-test-results-btn');
    const $modalCancelBtn = $('#wcac-cancel-delete-test-results-btn');

    // Function to hide modal and reset flag
    const hideModalAndReset = () => {
        $modal.hide();
        // Use timeout to ensure flag stays locked briefly after closing
        setTimeout(() => { window.wcacIsDeletingTestResults = false; }, 50);
    };

    // Open Modal Handler (Original Delete Button)
    if ($deleteBtn.length) {
        $deleteBtn.off('click').on('click', function(e) { 
            e.preventDefault();
            e.stopPropagation();

            if (window.wcacIsDeletingTestResults) {
                console.log('WCAC Delete Modal: Already processing, ignoring duplicate trigger.');
                return; 
            }

            wcacTestResultIdsToDelete = $rowCheckboxes.filter(':checked').map(function() {
                return $(this).val();
            }).get();

            if (wcacTestResultIdsToDelete.length === 0) {
                alert('No test results selected.');
                return; 
            }

            // Set flag and show the modal
            window.wcacIsDeletingTestResults = true;
            console.log('WCAC Delete Modal: Showing confirmation modal for IDs:', wcacTestResultIdsToDelete);
            $modal.show(); 
            // --- REMOVED native confirm() and AJAX call here --- 
        });
    }

    // Modal Confirm Button Handler
    if ($modalConfirmBtn.length) {
        $modalConfirmBtn.off('click').on('click', function() {
            console.log('WCAC Delete Modal: Confirm button clicked.');
            // Hide modal immediately
            $modal.hide(); 

            // Check if we have IDs stored
            if (!wcacTestResultIdsToDelete || wcacTestResultIdsToDelete.length === 0) {
                console.error('WCAC Delete Modal: No IDs found to delete on confirm.');
                alert('Error: No results were selected for deletion.');
                hideModalAndReset(); // Reset flag
                return;
            }

            const deleteNonce = wcacAdmin.delete_test_results_nonce;
            console.log('WCAC Delete Modal: Sending AJAX request for IDs:', wcacTestResultIdsToDelete);

            $.ajax({
                url: wcacAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcac_delete_test_results',
                    ids: JSON.stringify(wcacTestResultIdsToDelete),
                    _wpnonce: deleteNonce
                },
                success: function(response) {
                    console.log('WCAC Delete Modal: Success Response', response);
                    if (response && response.success) {
                        alert('Selected results deleted successfully.');
                        window.location.reload(); 
                    } else {
                        const errorMsg = response && response.data && response.data.message ? response.data.message : 'Unknown error deleting results.';
                        alert('Error: ' + errorMsg);
                         // Reset flag even on error
                        hideModalAndReset(); 
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('WCAC Delete Modal: AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                    alert('Failed to delete results. See console for details.');
                     // Reset flag on error
                    hideModalAndReset();
                },
                complete: function() {
                     // Optional: Clear the stored IDs after completion
                     wcacTestResultIdsToDelete = [];
                     // Flag is reset via hideModalAndReset called in success/error 
                     // or can be reset here as a fallback if needed, but hideModalAndReset is better.
                     // hideModalAndReset(); 
                }
            });
        });
    }

    // Modal Cancel Button Handler
    if ($modalCancelBtn.length) {
        $modalCancelBtn.off('click').on('click', function() {
            console.log('WCAC Delete Modal: Cancel button clicked.');
            hideModalAndReset();
            wcacTestResultIdsToDelete = []; // Clear IDs if cancelled
        });
    }

    // Optional: Clicking outside the modal content closes it
    if ($modal.length) {
        $modal.on('click', function(e) {
            // Check if the click target is the modal background itself
            if ($(e.target).is($modal)) { 
                console.log('WCAC Delete Modal: Click outside detected.');
                hideModalAndReset();
                wcacTestResultIdsToDelete = []; // Clear IDs if cancelled
            }
        });
    }

    // Select all checkbox logic
    if ($selectAllCheckbox.length && $rowCheckboxes.length) {
        $selectAllCheckbox.on('change', function() {
            $rowCheckboxes.prop('checked', $(this).prop('checked'));
        });

        // Optional: Uncheck select-all if any row is unchecked
        $rowCheckboxes.on('change', function() {
            if (!$(this).prop('checked')) {
                $selectAllCheckbox.prop('checked', false);
            }
            // Optional: Check select-all if all rows are checked
            // else if ($rowCheckboxes.filter(':not(:checked)').length === 0) {
            //     $selectAllCheckbox.prop('checked', true);
            // }
        });
    }

    // --- Debug Logs Delete Logic --- //
    const $deleteSelectedDebugBtn = $('#wcac-delete-selected-debug-logs');
    const $deleteAllDebugBtn = $('#wcac-delete-all-debug-logs');
    const $debugLogCheckboxes = $('.wcac-debug-logs-page input[name="log_ids[]"]');

    // Handler for deleting selected debug logs
    if ($deleteSelectedDebugBtn.length) {
        $deleteSelectedDebugBtn.on('click', function(e) {
            e.preventDefault();
            const checkedIds = $debugLogCheckboxes.filter(':checked').map(function() {
                return $(this).val();
            }).get();

            if (checkedIds.length === 0) {
                alert('No debug logs selected for deletion.');
                return;
            }

            // Use the specific confirmation message
            if (!confirm(wcacAdmin.delete_debug_logs_confirm || 'Are you sure you want to delete the selected debug logs?')) {
                return;
            }

            // Use the specific nonce
            const deleteNonce = wcacAdmin.delete_selected_debug_logs_nonce;
            
            console.log('WCAC Debug Delete Selected: Sending request for IDs:', checkedIds); 

            $.ajax({
                url: wcacAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcac_delete_selected_debug_logs',
                    ids: JSON.stringify(checkedIds), 
                    _wpnonce: deleteNonce // Use default _wpnonce parameter name
                },
                success: function(response) {
                    console.log('WCAC Debug Delete Selected: Success Response', response); 
                    if (response && response.success) {
                        alert('Selected debug logs deleted successfully.');
                        window.location.reload(); // Reload page
                    } else {
                        const errorMsg = response && response.data && response.data.message ? response.data.message : 'Unknown error deleting selected logs.';
                        alert('Error: ' + errorMsg);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('WCAC Debug Delete Selected: AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                    alert('Failed to delete selected debug logs. See console for details.');
                }
            });
        });
    }

    // Handler for deleting all debug logs
    if ($deleteAllDebugBtn.length) {
        $deleteAllDebugBtn.on('click', function(e) {
            e.preventDefault();

            // Use the specific confirmation message
            if (!confirm(wcacAdmin.delete_all_debug_logs_confirm || 'Are you sure you want to delete ALL debug logs?')) {
                return;
            }

            // Use the specific nonce
            const deleteNonce = wcacAdmin.delete_all_debug_logs_nonce;
            
            console.log('WCAC Debug Delete All: Sending request...'); 

            $.ajax({
                url: wcacAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcac_delete_all_debug_logs',
                    _wpnonce: deleteNonce // Use default _wpnonce parameter name
                },
                success: function(response) {
                    console.log('WCAC Debug Delete All: Success Response', response); 
                    if (response && response.success) {
                        alert('All debug logs deleted successfully.');
                        window.location.reload(); // Reload page
                    } else {
                        const errorMsg = response && response.data && response.data.message ? response.data.message : 'Unknown error deleting all logs.';
                        alert('Error: ' + errorMsg);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('WCAC Debug Delete All: AJAX Error:', textStatus, errorThrown, jqXHR.responseText);
                    alert('Failed to delete all debug logs. See console for details.');
                }
            });
        });
    }

    // --- OPTIMIZER MODAL WORKFLOW --- //
    // Initialize optimizer if the button exists
    const $optimizeBtn = $('#wcac-optimize');
    const $optimizerModal = $('#wcac-optimizer-modal');

    // Define the logger function in a scope accessible by other helpers
    window.wcacLogOptimizer = function(msg) {
        const $currentLog = $('#wcac-optimizer-log');
        if ($currentLog.length && $currentLog[0]) {
            $currentLog.append($('<div>').text(msg));
            setTimeout(() => {
                if ($currentLog[0] && $currentLog[0].scrollHeight > $currentLog.innerHeight()) {
                    $currentLog.scrollTop($currentLog[0].scrollHeight);
                }
            }, 0);
        }
    };

    if ($optimizeBtn.length && $optimizerModal.length) {
        $optimizeBtn.on('click', function(e) {
            e.preventDefault();

            if (!wcacAdmin || !wcacAdmin.ajaxurl) { // Only check for ajaxurl initially
                alert('Error: Critical wcacAdmin.ajaxurl missing. Please ensure the plugin is properly localized.');
                return;
            }

            // Step 1: Fetch all necessary nonces first
            $.ajax({
                url: wcacAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcac_get_optimizer_nonce' // No nonce needed to fetch nonces
                },
                success: function(nonceResponse) {
                    if (!nonceResponse.success || !nonceResponse.data) {
                        alert('Error: Could not fetch necessary security tokens (nonces) to start the optimizer. Please try reloading the page.');
                        console.error('Failed to fetch nonces:', nonceResponse);
                        return;
                    }
                    
                    const optimizerNonces = nonceResponse.data; // Store the fetched nonces

                    // Now proceed with the rest of the optimizer logic, using optimizerNonces
                    let defaultIterations = 200;
                    let userInput = prompt('How many optimizer iterations? (Recommended: 200)', defaultIterations);
                    if (userInput === null) return;
                    let iterations = parseInt(userInput, 10);
                    if (isNaN(iterations) || iterations < 10 || iterations > 2000) {
                        alert('Please enter a valid number of iterations (10-2000).');
                        return;
                    }

                    let batchSize = 10;
                    showOptimizerModal('<div style="padding:32px 0;text-align:center;"><span class="spinner is-active" style="float:none;display:inline-block;"></span><br>Running optimization...<br><b>Total Iterations:</b> ' + iterations + ' &nbsp; <b>Batch size:</b> ' + batchSize + '<br>This may take a minute.</div>', true);
                    
                    window.wcacLogOptimizer('Starting optimizer with ' + iterations + ' iterations and batch size ' + batchSize + '...');
                    let currentIdx = 0;
                    let optimizerInProgress = false;
                    let optimizerCompleted = false;
                    let currentOptimizerBatchNonce = optimizerNonces.optimizer_batch_nonce;

                    function runBatch() {
                        if (optimizerInProgress || optimizerCompleted) return;
                        optimizerInProgress = true;

                        $.ajax({
                            url: wcacAdmin.ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'wcac_run_optimizer',
                                nonce: currentOptimizerBatchNonce,
                                iterations: iterations,
                                batch_size: batchSize,
                                current_idx: currentIdx
                            },
                            success: function(response) {
                                optimizerInProgress = false;
                                console.log('[WCAC Optimizer] Batch response received:', response);

                                if (!response || typeof response !== 'object') {
                                    window.wcacLogOptimizer('Error: Invalid response format from run_optimizer');
                                    console.error('[WCAC Optimizer] Invalid response format:', response);
                                    return;
                                }
                                if (!response.success) {
                                    const errorMsg = response.data && response.data.message ? response.data.message : 'Operation failed in run_optimizer';
                                    window.wcacLogOptimizer('Error: ' + errorMsg);
                                    console.error('[WCAC Optimizer] Operation failed:', response);
                                    return;
                                }
                                if (!response.data) {
                                    window.wcacLogOptimizer('Error: Response missing data field from run_optimizer');
                                    console.error('[WCAC Optimizer] Response missing data field:', response);
                                    return;
                                }

                                if (typeof response.data.progress !== 'undefined') {
                                    window.wcacLogOptimizer('Progress: ' + response.data.progress + '% (' + (response.data.current_idx || 0) + ' / ' + (response.data.total || iterations) + ')');
                                }

                                if (response.data.done) {
                                    optimizerCompleted = true;
                                    window.wcacLogOptimizer('Optimizer batch processing completed.');
                                    const settingsSuggestions = response.data.settings_suggestions || {};
                                    applySettingsAndShowSummary(settingsSuggestions, optimizerNonces); // Pass all fetched nonces
                                } else {
                                    currentIdx = response.data.current_idx || 0;
                                    if (response.data.new_nonce) { 
                                        currentOptimizerBatchNonce = response.data.new_nonce;
                                    }
                                    setTimeout(runBatch, 10);
                                }
                            },
                            error: function(xhr) {
                                optimizerInProgress = false;
                                if (xhr.status === 403) {
                                    window.wcacLogOptimizer('Batch Optimizer error: Session expired or page is out of date. Please reload the page to continue.');
                                    alert('Your session has expired or the page is out of date. Please reload the page and try again.');
                                } else if (xhr.status === 500) {
                                    window.wcacLogOptimizer('Batch Optimizer error: A server error occurred. Please check the debug log.');
                                    alert('A server error occurred during optimization. Please check the debug log for details.');
                                } else {
                                    window.wcacLogOptimizer('Batch Optimizer AJAX error: ' + xhr.statusText);
                                }
                            }
                        });
                    }
                    runBatch();
                },
                error: function(xhr) {
                    alert('Error: Failed to contact server to prepare the optimizer. Please check your connection and try again.');
                    console.error('AJAX error fetching nonces:', xhr);
                }
            });
        });
    }

    // Helper: Show/Update modal with persistent log area
    window.showOptimizerModal = function(contentHtml, includeLogArea = false) {
        const $mainContentArea = $optimizerModal.find('.wcac-modal-content-main');
        
        if ($mainContentArea.length === 0) {
            let frameHtml = `
                <div class="wcac-modal-overlay">
                    <div class="wcac-modal-content">
                         <button class="wcac-modal-close-x button-link" style="position:absolute; top:10px; right:10px; font-size:20px; line-height: 1; padding: 5px; border: none; background: none; cursor: pointer;">&times;</button>
                         <div class="wcac-modal-content-main">${contentHtml}</div> 
                         ${includeLogArea ? '<div id="wcac-optimizer-log" style="margin-top:18px;text-align:left;font-size:13px;background:#f9f9f9;border:1px solid #eee;padding:12px;max-height:150px;overflow:auto; border-radius: 3px;"></div>' : ''}
                         <div class="wcac-modal-actions-footer" style="margin-top:16px; text-align: right; border-top: 1px solid #eee; padding-top: 10px;">
                              <button class="button wcac-modal-close-footer">Close</button>
                         </div>
                    </div>
                 </div>`;
            $optimizerModal.html(frameHtml);

            $optimizerModal.find('.wcac-modal-close-x, .wcac-modal-close-footer').on('click', function(e) { 
                e.preventDefault();
                $optimizerModal.hide(); 
            });
            $optimizerModal.on('click', function(e) { 
                if ($(e.target).hasClass('wcac-modal-overlay')) { 
                    $optimizerModal.hide(); 
                }
            });
        } else {
            $mainContentArea.html(contentHtml);
            const $logArea = $optimizerModal.find('#wcac-optimizer-log');
            if (includeLogArea && $logArea.length === 0) {
                $mainContentArea.after('<div id="wcac-optimizer-log" style="margin-top:18px;text-align:left;font-size:13px;background:#f9f9f9;border:1px solid #eee;padding:12px;max-height:150px;overflow:auto; border-radius: 3px;"></div>');
            } else if (!includeLogArea && $logArea.length > 0) {
                $logArea.remove();
            }
        }
        $optimizerModal.show();
    };

    // Helper: Apply settings and show summary
    window.applySettingsAndShowSummary = function(settings, optimizerNonces) { // Expects the object of nonces
        const settingsToApply = settings && typeof settings === 'object' && Object.keys(settings).length > 0 ? settings : null;

        if (settingsToApply) {
            window.wcacLogOptimizer('Automatically applying ' + Object.keys(settingsToApply).length + ' suggested settings...');
            $.ajax({
                url: wcacAdmin.ajaxurl,
                method: 'POST',
                data: {
                    action: 'wcac_apply_settings',
                    nonce: optimizerNonces.optimizer_apply_settings_nonce,
                    settings: JSON.stringify(settingsToApply)
                },
                success: function(response) {
                    if (response.success) {
                        window.wcacLogOptimizer('Settings automatically applied successfully.');
                    } else {
                        window.wcacLogOptimizer('Error automatically applying settings: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                    }
                    showSummaryModal(settingsToApply, optimizerNonces); // Pass all fetched nonces
                },
                error: function(xhr) {
                    window.wcacLogOptimizer('AJAX Error automatically applying settings: ' + xhr.statusText);
                    showSummaryModal(settingsToApply, optimizerNonces); // Pass all fetched nonces
                }
            });
        } else {
            window.wcacLogOptimizer('No settings suggestions to apply.');
            showSummaryModal({}, optimizerNonces); // Pass all fetched nonces
        }
    };

    // Show summary modal
    window.showSummaryModal = function(appliedSettings, optimizerNonces) { // Expects the object of nonces
        let html = '<h2>Optimization Complete</h2>';
        let appliedSettingsCount = typeof appliedSettings === 'object' && appliedSettings !== null ? Object.keys(appliedSettings).length : 0;
        
        html += '<p>Optimization process finished.</p>';
        if (appliedSettingsCount > 0) {
            html += `<p>${appliedSettingsCount} settings tweak(s) were automatically applied based on the results.</p>`;
        } else {
            html += '<p>No settings tweaks were applied based on the results.</p>';
        }

        // No log file actions/buttons in the modal
        showOptimizerModal(html, true);
        window.wcacLogOptimizer('Optimization workflow complete.');
    };

    // --- CLEAR DEBUG LOG BUTTON --- //
    const $clearDebugLogBtn = $('#wcac-clear-debug-log');
    const $clearDebugLogResult = $('#wcac-clear-debug-log-result');
    if ($clearDebugLogBtn.length) {
        $clearDebugLogBtn.on('click', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to clear the debug.log file? This cannot be undone.')) return;
            $clearDebugLogResult.text('Clearing...');
            $.post(wcacAdmin.ajaxurl, {
                action: 'wcac_clear_debug_log'
            }, function(response) {
                if (response.success) {
                    $clearDebugLogResult.text(response.data.message);
                } else {
                    $clearDebugLogResult.text('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                }
            });
        });
    }

    // --- Clear All Test History Button --- //
    $(document).on('click', '#wcac-clear-all-history', function(e) {
        e.preventDefault();
        
        if (confirm(wcacAdmin.clear_all_confirm || 'Are you sure you want to delete ALL test result history? This cannot be undone.')) {
            const nonce = $('#wcac_clear_all_test_history_nonce').val();
            const statusSpan = $('#wcac-csv-upload-status'); // Use existing status area

            statusSpan.text('Clearing history...').css('color', 'orange');

            $.ajax({
                url: ajaxurl, // Use global ajaxurl
                type: 'POST', 
                data: {
                    action: 'wcac_clear_all_test_history',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        statusSpan.text(response.data.message || 'History cleared successfully.').css('color', 'green');
                        alert(response.data.message || 'All test result history cleared successfully!');
                        // Reload the page to show the empty table
                        window.location.reload(); 
                    } else {
                        const errorMsg = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
                        statusSpan.text('Error: ' + errorMsg).css('color', 'red');
                        alert('Error clearing history: ' + errorMsg);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Clear All History AJAX Error:', textStatus, errorThrown);
                    const errorMsg = 'AJAX error: Could not contact server.';
                    statusSpan.text(errorMsg).css('color', 'red');
                    alert('Error: ' + errorMsg);
                }
            });
        }
    });

    // --- BEGIN: Per-Tab AJAX Save Logic ---
    $(document).on('click', '.wcac-tab-save', function(e) {
        e.preventDefault();
        var $form = $(this).closest('form');
        var tabId = $form.data('tab-id');
        var data = $form.serializeArray();
        
        // Log each form field for debugging
        console.log('WCAC DEBUG: Saving tab:', tabId);
        console.log('WCAC DEBUG: Form fields:');
        data.forEach(function(item) {
            console.log(' -', item.name + ':', item.value);
        });
        
        // Get the tab-specific nonce from the hidden field
        var nonceFieldName = '_wpnonce_' + tabId;
        var nonce = $form.find('input[name="' + nonceFieldName + '"]').val();
        if (!nonce) {
            console.error('WCAC DEBUG: Nonce field not found in form for tab', tabId);
            alert('Error: Security token not found. Please refresh the page.');
            return;
        }
        
        data.push({name: 'action', value: 'wcac_save_tab_settings'});
        data.push({name: 'tab_id', value: tabId});
        data.push({name: nonceFieldName, value: nonce}); // Use tab-specific nonce field
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: wcacAdmin.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                console.log('WCAC DEBUG: Save response:', response);
                if (response.success) {
                    alert(response.data.message || wcacAdmin.settings_update_success || 'Settings saved.');
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error saving settings.';
                    console.error('WCAC DEBUG: Save error:', errorMsg);
                    alert(errorMsg);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('WCAC DEBUG: AJAX error:', {
                    status: jqXHR.status,
                    statusText: textStatus,
                    error: errorThrown,
                    response: jqXHR.responseText
                });
                alert('Error saving settings: ' + textStatus);
            }
        }).always(function() {
            $btn.prop('disabled', false).text('Save');
        });
    });
    // --- END: Per-Tab AJAX Save Logic ---

}); 