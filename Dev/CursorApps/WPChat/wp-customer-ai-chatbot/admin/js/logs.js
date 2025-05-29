/**
 * WCAC Debug Log & Test Result Management Logic
 * Handles debug log row toggling, selection, deletion, and test result management (selection, deletion, CSV upload, clear all).
 * Loaded only on Debug Logs and Test Results admin pages.
 */
jQuery(document).ready(function($) {
    'use strict';
    // --- Debug Logs Page Logic --- //
    // Row toggle for log details
    $(document).on('click', '.log-row', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).closest('.check-column').length) {
            return;
        }
        const $row = $(this);
        const logId = $row.data('log-id');
        const $detailsRow = $('#log-details-' + logId);
        $('.log-row').not($row).removeClass('expanded');
        $('.log-details-row').not($detailsRow).slideUp(200);
        $row.toggleClass('expanded');
        $detailsRow.slideToggle(200, function() {
            if ($row.hasClass('expanded')) {
                $('html, body').animate({
                    scrollTop: $detailsRow.offset().top - 100
                }, 200);
            }
        });
    });
    // Select all for debug logs
    $(document).on('change', '.wp-list-table #cb-select-all-1', function() {
        const isChecked = $(this).prop('checked');
        $('.wp-list-table input[name="log_ids[]"]').prop('checked', isChecked);
    });
    $(document).on('change', '.wp-list-table input[name="log_ids[]"]', function(e) {
        e.stopPropagation();
        const total = $('.wp-list-table input[name="log_ids[]"]').length;
        const checked = $('.wp-list-table input[name="log_ids[]"]:checked').length;
        $('#cb-select-all-1').prop('checked', total === checked);
    });
    // Delete selected debug logs
    const $deleteSelectedDebugBtn = $('#wcac-delete-selected-debug-logs');
    const $deleteAllDebugBtn = $('#wcac-delete-all-debug-logs');
    const $debugLogCheckboxes = $('.wcac-debug-logs-page input[name="log_ids[]"]');
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
            if (!confirm(wcacAdmin.delete_debug_logs_confirm || 'Are you sure you want to delete the selected debug logs?')) {
                return;
            }
            const deleteNonce = wcacAdmin.delete_selected_debug_logs_nonce;
            $.ajax({
                url: wcacAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcac_delete_selected_debug_logs',
                    ids: JSON.stringify(checkedIds),
                    _wpnonce: deleteNonce
                },
                success: function(response) {
                    if (response && response.success) {
                        alert('Selected debug logs deleted successfully.');
                        window.location.reload();
                    } else {
                        const errorMsg = response && response.data && response.data.message ? response.data.message : 'Unknown error deleting selected logs.';
                        alert('Error: ' + errorMsg);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('Failed to delete selected debug logs. See console for details.');
                }
            });
        });
    }
    // Delete all debug logs
    if ($deleteAllDebugBtn.length) {
        $deleteAllDebugBtn.on('click', function(e) {
            e.preventDefault();
            if (!confirm(wcacAdmin.delete_all_debug_logs_confirm || 'Are you sure you want to delete ALL debug logs?')) {
                return;
            }
            const deleteNonce = wcacAdmin.delete_all_debug_logs_nonce;
            $.ajax({
                url: wcacAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcac_delete_all_debug_logs',
                    _wpnonce: deleteNonce
                },
                success: function(response) {
                    if (response && response.success) {
                        alert('All debug logs deleted successfully.');
                        window.location.reload();
                    } else {
                        const errorMsg = response && response.data && response.data.message ? response.data.message : 'Unknown error deleting all logs.';
                        alert('Error: ' + errorMsg);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('Failed to delete all debug logs. See console for details.');
                }
            });
        });
    }
    // --- Test Results Page Logic --- //
    // CSV upload
    const $csvUploadForm = $('#wcac-csv-upload-form');
    const $csvStatusSpan = $('#wcac-csv-upload-status');
    const $csvFileInput = $('#wcac_test_csv');
    const $csvSubmitButton = $csvUploadForm.find('button[type="submit"]');
    if ($csvUploadForm.length && $csvStatusSpan.length && $csvFileInput.length && $csvSubmitButton.length) {
        $csvUploadForm.on('submit', function(e) {
            e.preventDefault();
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
    // Test results delete logic (modal)
    const $deleteBtn = $('#wcac-delete-selected');
    const $selectAllCheckbox = $('#wcac-select-all');
    const $rowCheckboxes = $('.wcac-select-row');
    const $modal = $('#wcac-delete-confirm-modal');
    const $modalConfirmBtn = $('#wcac-confirm-delete-test-results-btn');
    const $modalCancelBtn = $('#wcac-cancel-delete-test-results-btn');
    window.wcacIsDeletingTestResults = false;
    let wcacTestResultIdsToDelete = [];
    const hideModalAndReset = () => {
        $modal.hide();
        setTimeout(() => { window.wcacIsDeletingTestResults = false; }, 50);
    };
    if ($deleteBtn.length) {
        $deleteBtn.off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (window.wcacIsDeletingTestResults) return;
            wcacTestResultIdsToDelete = $rowCheckboxes.filter(':checked').map(function() {
                return $(this).val();
            }).get();
            if (wcacTestResultIdsToDelete.length === 0) {
                alert('No test results selected.');
                return;
            }
            window.wcacIsDeletingTestResults = true;
            $modal.show();
        });
    }
    if ($modalConfirmBtn.length) {
        $modalConfirmBtn.off('click').on('click', function() {
            $modal.hide();
            if (!wcacTestResultIdsToDelete || wcacTestResultIdsToDelete.length === 0) {
                alert('Error: No results were selected for deletion.');
                hideModalAndReset();
                return;
            }
            const deleteNonce = wcacAdmin.delete_test_results_nonce;
            $.ajax({
                url: wcacAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcac_delete_test_results',
                    ids: JSON.stringify(wcacTestResultIdsToDelete),
                    _wpnonce: deleteNonce
                },
                success: function(response) {
                    if (response && response.success) {
                        alert('Selected results deleted successfully.');
                        window.location.reload();
                    } else {
                        const errorMsg = response && response.data && response.data.message ? response.data.message : 'Unknown error deleting results.';
                        alert('Error: ' + errorMsg);
                        hideModalAndReset();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('Failed to delete results. See console for details.');
                    hideModalAndReset();
                },
                complete: function() {
                    wcacTestResultIdsToDelete = [];
                }
            });
        });
    }
    if ($modalCancelBtn.length) {
        $modalCancelBtn.off('click').on('click', function() {
            hideModalAndReset();
            wcacTestResultIdsToDelete = [];
        });
    }
    if ($modal.length) {
        $modal.on('click', function(e) {
            if ($(e.target).is($modal)) {
                hideModalAndReset();
                wcacTestResultIdsToDelete = [];
            }
        });
    }
    if ($selectAllCheckbox.length && $rowCheckboxes.length) {
        $selectAllCheckbox.on('change', function() {
            $rowCheckboxes.prop('checked', $(this).prop('checked'));
        });
        $rowCheckboxes.on('change', function() {
            if (!$(this).prop('checked')) {
                $selectAllCheckbox.prop('checked', false);
            }
        });
    }
    // Clear all test history
    $(document).on('click', '#wcac-clear-all-history', function(e) {
        e.preventDefault();
        if (confirm(wcacAdmin.clear_all_confirm || 'Are you sure you want to delete ALL test result history? This cannot be undone.')) {
            const nonce = $('#wcac_clear_all_test_history_nonce').val();
            const statusSpan = $('#wcac-csv-upload-status');
            statusSpan.text('Clearing history...').css('color', 'orange');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcac_clear_all_test_history',
                    nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        statusSpan.text(response.data.message || 'History cleared successfully.').css('color', 'green');
                        alert(response.data.message || 'All test result history cleared successfully!');
                        window.location.reload();
                    } else {
                        const errorMsg = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
                        statusSpan.text('Error: ' + errorMsg).css('color', 'red');
                        alert('Error clearing history: ' + errorMsg);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    const errorMsg = 'AJAX error: Could not contact server.';
                    statusSpan.text(errorMsg).css('color', 'red');
                    alert('Error: ' + errorMsg);
                }
            });
        }
    });
}); 