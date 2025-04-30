console.log('WCAC Admin JS: File Loaded'); // Basic check

// --- Tabbed Settings Interface Logic --- //
jQuery(document).ready(function($) {
    'use strict';
    console.log('WCAC Admin JS: Document Ready - Executing Tab Logic');
    var $sectionsWrapper = $('#wcac-settings-sections-wrapper');
    if ($sectionsWrapper.length) {
         var sectionTabMap = {
             '#wcac_main_section_content': '#tab-core',
             '#wcac_indexing_section_content': '#tab-indexing',
             '#wcac_customization_section_content': '#tab-customization',
             '#wcac_llm_params_section_content': '#tab-llm_params',
             '#wcac_scoring_section_content': '#tab-scoring'
         };

         $.each(sectionTabMap, function(sectionContentId, tabContainerId) {
            var $sectionContentDiv = $sectionsWrapper.find(sectionContentId);
            var $tabContainer = $(tabContainerId);
            
            if ($sectionContentDiv.length && $tabContainer.length) {
                var $h2 = $sectionContentDiv.prev('h2'); 
                var $table = $h2.nextAll('table.form-table').first(); 
                
                if ($h2.length) $h2.appendTo($tabContainer);
                 $sectionContentDiv.appendTo($tabContainer);
                if ($table.length) $table.appendTo($tabContainer);

            } else {
                console.warn('WCAC Admin: Could not find elements for ', sectionContentId, ' or ', tabContainerId);
            }
         });
         console.log('WCAC Admin: Finished moving settings sections into tabs.');
         // $sectionsWrapper.remove(); // Optionally remove wrapper after moving
    } else {
        console.warn('WCAC Admin: Could not find #wcac-settings-sections-wrapper');
    }
});
// --- End Tabbed Settings Interface Logic ---


// --- Restore Default Prompt & Indexing Logic --- //
jQuery(document).ready(function($) {
    'use strict';
    console.log('WCAC Admin JS: Document Ready - Executing Restore/Index Logic');

    // --- Restore Default Prompt --- //
    $('#wcac-restore-default-prompt').on('click', function(e) {
        e.preventDefault();
        if (confirm(wcac_admin_data.restore_prompt_confirm || 'Are you sure you want to restore the default system prompt? This will overwrite your current custom prompt.')) {
             var defaultPrompt = wcac_admin_data.default_system_prompt || '';
            if (defaultPrompt) {
                 $('#wcac_system_prompt_field').val(defaultPrompt);
                 alert('Default prompt restored. Remember to Save Settings.');
            } else {
                 alert('Error: Could not retrieve default prompt.');
            }
        }
    });

    // --- Indexing Logic --- //
    const $reindexButton = $('#wcac-reindex-button');
    const $statusDiv = $('#wcac-reindex-status-container');
    const $statusSpan = $('#wcac-reindex-status');
    const $progressBar = $('#wcac-reindex-progress');
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
            $statusDiv.show();
            $progressBar.val(0).show();
            $statusSpan.text(wcac_admin_data.reindexing_text).css('color', 'orange');
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
                isIndexing = false; // Reset flag on error
            }
        });
    }

    function handleIndexingResponse(response) {
        if (!response || typeof response !== 'object') {
            console.error('Invalid response received:', response);
            $statusSpan.text('Error: Invalid response from server.').css('color', 'red');
            $progressBar.hide();
            $reindexButton.prop('disabled', false);
            isIndexing = false; // Reset flag
            return;
        }

        if (response.success === false) {
            const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error from server.';
            console.error('Server Error:', errorMsg);
            $statusSpan.text(`Error: ${errorMsg}`).css('color', 'red');
            $progressBar.hide();
            $reindexButton.prop('disabled', false);
            isIndexing = false; // Reset flag
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
                }
                $statusSpan.text(statusText).css('color', 'orange');

                if (response.data.next_offset !== undefined) {
                    setTimeout(() => processBatch(response.data.next_offset), 100); 
                } else {
                     console.error('Error: In progress, but no next_offset provided.');
                     $statusSpan.text('Error: Invalid response state.').css('color', 'red');
                     $progressBar.hide();
                     $reindexButton.prop('disabled', false);
                     isIndexing = false; // Reset flag
                }

            } else if (status === 'complete') {
                $progressBar.val(100);
                const finalCounts = response.data.counts || {};
                let successMsg = wcac_admin_data.reindex_success_template
                    .replace('%d', finalCounts.total || 0)
                    .replace('%d', finalCounts.product || 0)
                    .replace('%d', finalCounts.page || 0)
                    .replace('%d', finalCounts.post || 0)
                    .replace('%d', finalCounts.product_variation || 0);
                
                if (errors > 0) {
                    successMsg += ` Completed with ${errors} errors (check debug log for details).`;
                    $statusSpan.css('color', 'darkorange');
                } else {
                    $statusSpan.css('color', 'green');
                }
                $statusSpan.text(successMsg);
                $reindexButton.prop('disabled', false);
                isIndexing = false; // Reset flag
                 setTimeout(() => $progressBar.hide(), 2000);

            } else {
                console.error('Unknown status received:', status);
                $statusSpan.text('Error: Unknown status from server.').css('color', 'red');
                $progressBar.hide();
                $reindexButton.prop('disabled', false);
                isIndexing = false; // Reset flag
            }
        } else {
            console.error('Invalid data structure in response:', response);
            $statusSpan.text('Error: Invalid data from server.').css('color', 'red');
            $progressBar.hide();
            $reindexButton.prop('disabled', false);
            isIndexing = false; // Reset flag
        }
    }
});
// --- End Restore Default Prompt & Indexing Logic --- // 