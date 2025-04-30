/**
 * WCAC Admin JS (Refactored for Batch Processing)
 */
jQuery(document).ready(function($) {
    'use strict';
    console.log('WCAC Admin JS: Document Ready - Executing Restore/Index Logic');

    // --- Restore Default Prompt --- //
    $('#wcac-restore-default-prompt').on('click', function(e) {
        e.preventDefault();
        if (confirm(wcac_admin_data.restore_prompt_confirm)) {
            $('#wcac_system_prompt_field').val(wcac_admin_data.default_system_prompt);
            alert('Default prompt restored. Remember to Save Settings.');
        }
    });

    // --- Indexing Logic --- //
    const $reindexButton = $('#wcac-reindex-button');
    const $statusDiv = $('#wcac-reindex-status-container'); // Target a container div
    const $statusSpan = $('#wcac-reindex-status'); // The text part
    const $progressBar = $('#wcac-reindex-progress'); // Progress bar element
    var isIndexing = false;

    if ($reindexButton.length && $statusDiv.length) {
        $reindexButton.on('click', function(e) {
            e.preventDefault();
            if (isIndexing) return;
            isIndexing = true;
            if (!confirm(wcac_admin_data.reindex_confirm)) {
                isIndexing = false;
                return;
            }
            $reindexButton.prop('disabled', true);
            $statusDiv.show(); // Show the status container
            $progressBar.val(0).show(); // Reset and show progress bar
            $statusSpan.text(wcac_admin_data.reindexing_text).css('color', 'orange');

            // Start the batch process
            processBatch(0);
        });
    }

    function processBatch(offset) {
        console.log(`WCAC Batch: Requesting offset ${offset}`);
        $.ajax({
            url: wcac_admin_data.ajax_url,
            type: 'POST',
            data: {
                action: 'wcac_build_index',
                nonce: wcac_admin_data.nonce,
                offset: offset
            },
            success: function(response) {
                handleIndexingResponse(response);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Batch Error:', textStatus, errorThrown);
                $statusSpan.text('AJAX Error: Could not contact server during batch processing. Please try again.').css('color', 'red');
                $progressBar.hide();
                $reindexButton.prop('disabled', false);
            }
            // No 'complete' here, handled within handleIndexingResponse
        });
    }

    function handleIndexingResponse(response) {
        if (!response || typeof response !== 'object') {
            console.error('Invalid response received:', response);
            $statusSpan.text('Error: Invalid response from server.').css('color', 'red');
            $progressBar.hide();
            $reindexButton.prop('disabled', false);
            return;
        }

        if (response.success === false) {
            const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error from server.';
            console.error('Server Error:', errorMsg);
            $statusSpan.text(`Error: ${errorMsg}`).css('color', 'red');
            $progressBar.hide();
            $reindexButton.prop('disabled', false);
            return;
        }

        // Successful response, check status
        if (response.data && response.data.status) {
            const status = response.data.status;
            const processed = response.data.processed || 0;
            const total = response.data.total || 0;
            const errors = response.data.errors || 0;

            if (status === 'in_progress') {
                const percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
                $progressBar.val(percentage);
                let statusText = `Processing: ${processed} / ${total} items (${percentage}%).`;
                if (errors > 0) {
                    statusText += ` (${errors} errors)`;
                }
                if (response.data.last_batch_error) {
                     console.warn('Batch Error:', response.data.last_batch_error);
                     // Optionally display last_batch_error, but might be too noisy
                }
                $statusSpan.text(statusText).css('color', 'orange');

                // Schedule the next batch
                if (response.data.next_offset !== undefined) {
                    // Use a small timeout to prevent locking up the browser UI thread
                    setTimeout(() => processBatch(response.data.next_offset), 100); 
                } else {
                     console.error('Error: In progress, but no next_offset provided.');
                     $statusSpan.text('Error: Invalid response state.').css('color', 'red');
                     $progressBar.hide();
                     $reindexButton.prop('disabled', false);
                }

            } else if (status === 'complete') {
                $progressBar.val(100);
                const finalCounts = response.data.counts || {};
                let successMsg = wcac_admin_data.reindex_success_template
                    .replace('%d', finalCounts.total || 0)
                    .replace('%d', finalCounts.product || 0)
                    .replace('%d', finalCounts.page || 0)
                    .replace('%d', finalCounts.post || 0)
                    .replace('%d', finalCounts.product_variation || 0); // Added variation count placeholder
                
                if (errors > 0) {
                    successMsg += ` Completed with ${errors} errors (check debug log for details).`;
                    $statusSpan.css('color', 'darkorange');
                } else {
                    $statusSpan.css('color', 'green');
                }
                $statusSpan.text(successMsg);
                $reindexButton.prop('disabled', false);
                // Optionally hide progress bar after a delay
                 setTimeout(() => $progressBar.hide(), 2000);

            } else {
                console.error('Unknown status received:', status);
                $statusSpan.text('Error: Unknown status from server.').css('color', 'red');
                $progressBar.hide();
                $reindexButton.prop('disabled', false);
            }
        } else {
            console.error('Invalid data structure in response:', response);
            $statusSpan.text('Error: Invalid data from server.').css('color', 'red');
            $progressBar.hide();
            $reindexButton.prop('disabled', false);
        }
    }

    // Initialize tabs if we're on the settings page
    if ($('.wcac-settings-wrapper').length) {
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
    
    // Handle index rebuild button
    $('#wcac-rebuild-index').on('click', function(e) {
        e.preventDefault();
        
        // Get the progress bar and status elements
        var $progress = $('#wcac-index-progress');
        var $status = $('#wcac-index-status');
        var $button = $(this);
        
        // Disable button during operation
        $button.attr('disabled', 'disabled').text('Building Index...');
        
        // Show progress bar
        $progress.show();
        $status.text('Initializing...').show();
        
        // Function to build the index in batches
        function buildIndex(offset = 0) {
            $.ajax({
                url: wcac_admin_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcac_build_index',
                    offset: offset,
                    security: wcac_admin_params.build_index_nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Update progress
                        var progress = Math.min(100, Math.round((response.data.processed / response.data.total) * 100));
                        $progress.find('.wcac-progress-bar').css('width', progress + '%');
                        $status.text('Processed ' + response.data.processed + ' of ' + response.data.total + ' items');
                        
                        // Continue if more batches need processing
                        if (response.data.processed < response.data.total) {
                            buildIndex(response.data.next_offset);
                        } else {
                            // Finished
                            $status.text('Index successfully built! ' + response.data.processed + ' items indexed.');
                            $button.removeAttr('disabled').text('Rebuild Index');
                        }
                    } else {
                        $status.text('Error: ' + response.data.message);
                        $button.removeAttr('disabled').text('Retry Build Index');
                    }
                },
                error: function() {
                    $status.text('Error: Failed to connect to server');
                    $button.removeAttr('disabled').text('Retry Build Index');
                }
            });
        }
        
        // Start building the index
        buildIndex();
    });
    
    // Debug logs page: Toggle log details on row click
    $(document).on('click', '.log-row', function(e) {
        // Don't toggle if clicking checkbox or its container
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).closest('.check-column').length) {
            return;
        }
        
        const $row = $(this);
        const logId = $row.data('log-id');
        const $detailsRow = $('#log-details-' + logId);
        
        // Close any other open rows first
        $('.log-row').not($row).removeClass('expanded');
        $('.log-details-row').not($detailsRow).slideUp(200);
        
        // Toggle current row
        $row.toggleClass('expanded');
        $detailsRow.slideToggle(200, function() {
            // After animation completes, scroll the details into view if expanding
            if ($row.hasClass('expanded')) {
                $('html, body').animate({
                    scrollTop: $detailsRow.offset().top - 100 // 100px padding from top
                }, 200);
            }
        });
    });
    
    // Select all checkbox functionality for debug logs
    $(document).on('change', '.wp-list-table #cb-select-all-1', function() {
        const isChecked = $(this).prop('checked');
        $('.wp-list-table input[name="log_ids[]"]').prop('checked', isChecked);
    });
    
    // Update "select all" checkbox when individual checkboxes change
    $(document).on('change', '.wp-list-table input[name="log_ids[]"]', function(e) {
        e.stopPropagation(); // Prevent row click when clicking checkbox
        const totalCheckboxes = $('.wp-list-table input[name="log_ids[]"]').length;
        const checkedCheckboxes = $('.wp-list-table input[name="log_ids[]"]:checked').length;
        $('#cb-select-all-1').prop('checked', totalCheckboxes === checkedCheckboxes);
    });
}); 