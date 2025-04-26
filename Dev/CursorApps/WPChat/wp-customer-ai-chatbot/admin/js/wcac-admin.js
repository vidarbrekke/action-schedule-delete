/**
 * WCAC Admin JS
 */
jQuery(document).ready(function($) {
    'use strict';

    const $reindexButton = $('#wcac-reindex-button');
    const $statusSpan = $('#wcac-reindex-status');

    if ($reindexButton.length) {
        $reindexButton.on('click', function(e) {
            e.preventDefault();

            if (!confirm(wcac_admin_data.reindex_confirm)) {
                return;
            }

            $reindexButton.prop('disabled', true);
            $statusSpan.text(wcac_admin_data.reindexing_text).css('color', 'orange');

            $.ajax({
                url: wcac_admin_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcac_build_index',
                    nonce: wcac_admin_data.nonce // Use the nonce passed from PHP
                },
                success: function(response) {
                    if (response.success && response.data && response.data.counts) {
                        const counts = response.data.counts;
                        const successMsg = wcac_admin_data.reindex_success_template
                            .replace('%d', counts.total || 0)        // Total
                            .replace('%d', counts.product || 0)    // Products
                            .replace('%d', counts.page || 0)       // Pages
                            .replace('%d', counts.post || 0);      // Posts
                        
                        $statusSpan.text(successMsg).css('color', 'green');
                        // Optionally refresh part of the page to show updated counts in the header, or fully reload.
                        // For simplicity, we just show the message here.
                    } else {
                        console.error('Re-index Error:', response.data ? response.data.message : 'Unknown error structure');
                        $statusSpan.text(wcac_admin_data.reindex_error_text).css('color', 'red');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Re-index Error:', textStatus, errorThrown);
                    $statusSpan.text('AJAX Error: Could not contact server.').css('color', 'red');
                },
                complete: function() {
                    $reindexButton.prop('disabled', false);
                }
            });
        });
    }
}); 