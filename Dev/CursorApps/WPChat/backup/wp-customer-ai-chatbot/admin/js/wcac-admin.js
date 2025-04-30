/**
 * WCAC Admin JS (Refactored for Batch Processing)
 */
jQuery(document).ready(function($) {
    'use strict';

    const $reindexButton = $('#wcac-reindex-button');
    const $statusDiv = $('#wcac-reindex-status-container'); // Target a container div
    const $statusSpan = $('#wcac-reindex-status'); // The text part
    const $progressBar = $('#wcac-reindex-progress'); // Progress bar element

    if ($reindexButton.length && $statusDiv.length) {
        $reindexButton.on('click', function(e) {
            e.preventDefault();

            if (!confirm(wcac_admin_data.reindex_confirm)) {
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
}); 