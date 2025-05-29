/**
 * WC Quantity Multiples - Admin JavaScript
 * 
 * Handles all admin functionality for rule management.
 *
 * @package WC_Quantity_Multiples
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize Select2 fields
    function initializeSelect2() {
        if (typeof $.fn.select2 === 'undefined') {
            console.error('WCQM Admin: Select2 library not loaded');
            return;
        }

        try {
            $('#wcqm-product-search').select2({
                ajax: {
                    url: wcqm_admin_vars.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            term: params.term,
                            action: 'wcqm_search_products',
                            nonce: wcqm_admin_vars.nonce
                        };
                    },
                    processResults: function(data) {
                        return data;
                    },
                    cache: true
                },
                minimumInputLength: 2,
                placeholder: 'Search for a product...',
                width: '100%'
            });

            $('#wcqm-category-search').select2({
                ajax: {
                    url: wcqm_admin_vars.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            term: params.term,
                            action: 'wcqm_search_categories',
                            nonce: wcqm_admin_vars.nonce
                        };
                    },
                    processResults: function(data) {
                        return data;
                    },
                    cache: true
                },
                minimumInputLength: 2,
                placeholder: 'Search for a category...',
                width: '100%'
            });
        } catch (e) {
            console.error('WCQM Admin: Error initializing Select2', e);
        }
    }

    // Handle rule type changes
    function handleRuleTypeChange() {
        $('#wcqm-rule-type').on('change', function() {
            var type = $(this).val();
            if (type === 'product') {
                $('#wcqm-product-search-container').show();
                $('#wcqm-category-search-container').hide();
            } else if (type === 'category') {
                $('#wcqm-product-search-container').hide();
                $('#wcqm-category-search-container').show();
            }
        });
    }

    // Setup event handlers
    function setupEventHandlers() {
        // Store product name on select
        $('#wcqm-product-search').on('select2:select', function(e) {
            $('#wcqm-product-name').val(e.params.data.text);
        });

        // Store category name on select
        $('#wcqm-category-search').on('select2:select', function(e) {
            $('#wcqm-category-name').val(e.params.data.text);
        });

        // Handle rule deletion
        $('.wcqm-rules-list').on('click', '.delete-rule', function(e) {
            e.preventDefault();
            if (!confirm(wcqm_admin_vars.delete_confirm)) {
                return;
            }

            var $button = $(this);
            var rule_id = $button.data('rule-id');

            $.ajax({
                url: wcqm_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcqm_delete_rule',
                    rule_id: rule_id,
                    nonce: wcqm_admin_vars.nonce
                },
                beforeSend: function() {
                    $button.prop('disabled', true).text('Deleting...');
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                            if ($('.wcqm-rules-list tbody tr').length === 0) {
                                $('.wcqm-rules-list table').remove();
                                $('.wcqm-rules-list').append('<p>' + wcqm_admin_vars.no_rules + '</p>');
                            }
                        });
                    } else {
                        alert(wcqm_admin_vars.delete_error);
                        $button.prop('disabled', false).text('Delete');
                    }
                },
                error: function() {
                    alert(wcqm_admin_vars.ajax_error);
                    $button.prop('disabled', false).text('Delete');
                }
            });
        });

        // Handle rule form submission
        $('#wcqm-add-rule-form').on('submit', function(e) {
            e.preventDefault();
            var type = $('#wcqm-rule-type').val();
            var target_id = type === 'product' ? $('#wcqm-product-search').val() : $('#wcqm-category-search').val();
            
            if (!target_id) {
                alert('Please select a ' + type);
                return;
            }

            var multiple = $('#wcqm-multiple').val();
            if (!multiple || multiple < 1) {
                alert('Multiple must be at least 1');
                return;
            }

            var $spinner = $('#wcqm-submit-spinner');
            $spinner.addClass('is-active');

            $.ajax({
                url: wcqm_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcqm_save_rule',
                    rule_type: type,
                    product_id: type === 'product' ? target_id : '',
                    category_id: type === 'category' ? target_id : '',
                    multiple: multiple,
                    nonce: wcqm_admin_vars.nonce
                },
                success: function(response) {
                    $spinner.removeClass('is-active');
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(wcqm_admin_vars.save_error);
                    }
                },
                error: function() {
                    $spinner.removeClass('is-active');
                    alert(wcqm_admin_vars.ajax_error);
                }
            });
        });

        // Handle default multiple save
        $('.wcqm-save-default').on('click', function() {
            var $button = $(this);
            var multiple = $('#wcqm-default-multiple').val();
            var $spinner = $('.wcqm-spinner');

            if (!multiple || multiple < 1) {
                multiple = 1;
                $('#wcqm-default-multiple').val(1);
            }

            $button.prop('disabled', true);
            $spinner.addClass('is-active');

            $.ajax({
                url: wcqm_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcqm_save_default_multiple',
                    multiple: multiple,
                    nonce: wcqm_admin_vars.nonce
                },
                success: function(response) {
                    $spinner.removeClass('is-active');
                    if (response.success) {
                        alert('Default multiple saved successfully.');
                    } else {
                        alert(wcqm_admin_vars.save_error);
                    }
                    $button.prop('disabled', false);
                },
                error: function() {
                    $spinner.removeClass('is-active');
                    alert(wcqm_admin_vars.ajax_error);
                    $button.prop('disabled', false);
                }
            });
        });
    }

    // Initialize everything when document is ready
    $(document).ready(function() {
        initializeSelect2();
        handleRuleTypeChange();
        setupEventHandlers();
    });

})(jQuery); 