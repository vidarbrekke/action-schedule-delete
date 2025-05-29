/**
 * WCAC Indexing Logic (Extracted from wcac-admin.js)
 * Handles reindex button, progress bar, batch AJAX, and status UI.
 * Loaded only on relevant admin pages.
 */
jQuery(document).ready(function($) {
    'use strict';
    console.log('[WCAC] indexing.js loaded and running');
    // --- Indexing Logic --- //
    const $reindexButton = $('#wcac-reindex-button');
    const $statusDiv = $('#wcac-reindex-status-container');
    const $statusSpan = $('#wcac-reindex-status');
    const $progressBar = $('#wcac-reindex-progress');
    var isIndexing = false;

    // Use delegated event binding for robustness
    $(document).on('click', '#wcac-reindex-button', function(e) {
        console.log('[WCAC] Re-index button clicked');
            e.preventDefault();
            if (isIndexing) return;
            isIndexing = true;
            if (!confirm(wcacAdmin.reindex_confirm)) {
                isIndexing = false;
                return;
            }
        const $reindexButton = $(this);
            $reindexButton.prop('disabled', true);
            $statusDiv.show();
            $progressBar.val(0).show();
            $statusSpan.text(wcacAdmin.reindexing_text).css('color', 'orange');
            processBatch(0);
        });

    function processBatch(offset) {
        $.ajax({
            url: wcacAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'wcac_build_index',
                nonce: wcacAdmin.reindex_nonce,
                offset: offset
            },
            success: function(response) {
                handleIndexingResponse(response);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $statusSpan.text('AJAX Error: Could not contact server during batch processing. Please try again.').css('color', 'red');
                $progressBar.hide();
                $reindexButton.prop('disabled', false);
            }
        });
    }

    function handleIndexingResponse(response) {
        if (!response || typeof response !== 'object') {
            $statusSpan.text('Error: Invalid response from server.').css('color', 'red');
            $progressBar.hide();
            $reindexButton.prop('disabled', false);
            return;
        }
        if (response.success === false) {
            const errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error from server.';
            $statusSpan.text(`Error: ${errorMsg}`).css('color', 'red');
            $progressBar.hide();
            $reindexButton.prop('disabled', false);
            return;
        }
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
                $statusSpan.text(statusText).css('color', 'orange');
                if (response.data.next_offset !== undefined) {
                    setTimeout(() => processBatch(response.data.next_offset), 100);
                } else {
                    $statusSpan.text('Error: Invalid response state.').css('color', 'red');
                    $progressBar.hide();
                    $reindexButton.prop('disabled', false);
                }
            } else if (status === 'complete') {
                $progressBar.val(100);
                const finalCounts = response.data.counts || {};
                let template = (typeof wcacAdmin.reindex_success_template === 'string' && wcacAdmin.reindex_success_template.length > 0)
                    ? wcacAdmin.reindex_success_template
                    : 'Successfully indexed %d items (%d products, %d pages, %d posts, %d variations).';
                let successMsg = template
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
                setTimeout(() => $progressBar.hide(), 2000);
            } else {
                if ((typeof processed !== 'undefined' && typeof total !== 'undefined') && processed >= total) {
                    $progressBar.val(100);
                    $statusSpan.text('Indexing complete.').css('color', 'green');
                    $reindexButton.prop('disabled', false);
                    setTimeout(() => $progressBar.hide(), 2000);
                } else {
                    $statusSpan.text('Error: Unknown status from server.').css('color', 'red');
                    $progressBar.hide();
                    $reindexButton.prop('disabled', false);
                }
            }
        } else {
            $statusSpan.text('Error: Invalid data from server.').css('color', 'red');
            $progressBar.hide();
            $reindexButton.prop('disabled', false);
        }
    }
}); 