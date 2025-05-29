/**
 * WC Quantity Multiples - Admin JavaScript
 * 
 * Handles all admin functionality for rule management.
 *
 * @package WC_Quantity_Multiples
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    console.log('WC Quantity Multiples admin JS loaded');
    
    // Check if Select2 is available
    if (typeof $.fn.select2 === 'undefined') {
        console.error('Select2 library not loaded! Product and category searches will not work.');
        return;
    }
    
    try {
        // Initialize Select2 for product search
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
                    console.log('Product search results:', data);
                    return data;
                },
                cache: true,
                error: function(xhr, status, error) {
                    console.error('Product search AJAX error:', status, error);
                }
            },
            minimumInputLength: 2,
            placeholder: 'Search for a product...',
            width: '100%',
            dropdownAutoWidth: true
        });

        // Initialize Select2 for category search
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
                    console.log('Category search results:', data);
                    return data;
                },
                cache: true,
                error: function(xhr, status, error) {
                    console.error('Category search AJAX error:', status, error);
                }
            },
            minimumInputLength: 2,
            placeholder: 'Search for a category...',
            width: '100%',
            dropdownAutoWidth: true
        });

        console.log('Select2 fields initialized successfully');
    } catch (e) {
        console.error('Error initializing Select2:', e);
    }

    // Store the target name when a product is selected
    $('#wcqm-product-search').on('select2:select', function(e) {
        $('#wcqm-product-name').val(e.params.data.text);
        console.log('Product selected:', e.params.data);
    });

    // Store the target name when a category is selected
    $('#wcqm-category-search').on('select2:select', function(e) {
        $('#wcqm-category-name').val(e.params.data.text);
        console.log('Category selected:', e.params.data);
    });

    // Handle rule type toggle
    $('#wcqm-rule-type').on('change', function() {
        var type = $(this).val();
        console.log('Rule type changed to:', type);
        
        if (type === 'product') {
            $('#wcqm-product-search-container').show();
            $('#wcqm-category-search-container').hide();
        } else if (type === 'category') {
            $('#wcqm-product-search-container').hide();
            $('#wcqm-category-search-container').show();
        }
    });

    // Handle rule deletion
    $('.wcqm-rules-list').on('click', '.delete-rule', function(e) {
        e.preventDefault();
        
        if (!confirm(wcqm_admin_vars.delete_confirm)) {
            return;
        }

        var $button = $(this);
        var type = $button.data('type');
        var id = $button.data('id');
        var rule_id = type + '_' + id;
        
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
                        
                        // If no more rules, add "No rules" message
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
            error: function(xhr, status, error) {
                console.error('Delete rule AJAX error:', status, error);
                alert(wcqm_admin_vars.ajax_error);
                $button.prop('disabled', false).text('Delete');
            }
        });
    });

    // Handle rule form submission
    $('#wcqm-add-rule-form').on('submit', function(e) {
        e.preventDefault();
        console.log('WCQM Admin: Form submission started');
        
        var type = $('#wcqm-rule-type').val();
        var target_id = '';
        
        console.log('WCQM Admin: Rule type:', type);
        
        if (type === 'product') {
            target_id = $('#wcqm-product-search').val();
            if (!target_id) {
                alert('Please select a product');
                return;
            }
        } else if (type === 'category') {
            target_id = $('#wcqm-category-search').val();
            if (!target_id) {
                alert('Please select a category');
                return;
            }
        }
        
        console.log('WCQM Admin: Target ID:', target_id);
        
        var multiple = $('#wcqm-multiple').val();
        if (!multiple || multiple < 1) {
            alert('Multiple must be at least 1');
            return;
        }
        
        console.log('WCQM Admin: Multiple:', multiple);
        
        var $spinner = $('#wcqm-submit-spinner');
        var $submitButton = $('#wcqm-submit-rule');
        
        $submitButton.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // Prepare the data
        var ruleData = {
            action: 'wcqm_save_rule',
            type: type,
            target_id: target_id,
            multiple: multiple,
            nonce: wcqm_admin_vars.nonce
        };
        
        console.log('WCQM Admin: Sending rule data:', ruleData);
        
        // Send AJAX request
        $.ajax({
            url: wcqm_admin_vars.ajax_url,
            type: 'POST',
            data: ruleData,
            success: function(response) {
                console.log('WCQM Admin: Server response:', response);
                
                if (response.success) {
                    // Clear form
                    $('#wcqm-rule-type').val('product').trigger('change');
                    $('#wcqm-product-search').val('').trigger('change');
                    $('#wcqm-category-search').val('').trigger('change');
                    $('#wcqm-multiple').val('');
                    
                    // Reload rules list
                    location.reload();
                } else {
                    console.error('WCQM Admin: Save failed:', response.data);
                    alert(response.data.message || wcqm_admin_vars.save_error);
                }
            },
            error: function(xhr, status, error) {
                console.error('WCQM Admin: AJAX error:', {
                    status: status,
                    error: error,
                    response: xhr.responseText
                });
                alert(wcqm_admin_vars.ajax_error);
            },
            complete: function() {
                $submitButton.prop('disabled', false);
                $spinner.removeClass('is-active');
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
                    console.error('Save default error:', response);
                    alert(wcqm_admin_vars.save_error);
                }
                
                $button.prop('disabled', false);
            },
            error: function(xhr, status, error) {
                console.error('Save default AJAX error:', status, error);
                $spinner.removeClass('is-active');
                $button.prop('disabled', false);
                alert(wcqm_admin_vars.ajax_error);
            }
        });
    });

    // Handle rule type changes
    $(document).on('change', '.wcqm-rule-type', function() {
        var $row = $(this).closest('.wcqm-rule-row');
        var type = $(this).val();
        
        // Toggle visibility of product/category selects
        if (type === 'product') {
            $row.find('.wcqm-product-select').show();
            $row.find('.wcqm-category-select').hide();
            $row.attr('data-rule-type', 'product');
        } else {
            $row.find('.wcqm-product-select').hide();
            $row.find('.wcqm-category-select').show();
            $row.attr('data-rule-type', 'category');
        }
    });

    // Handle adding new rules
    $('.wcqm-add-rule').on('click', function() {
        var template = $('#wcqm-rule-row-template').html();
        $('#wcqm-rules-container').append(template);
        
        // Initialize select2 for new selects if available
        if ($.fn.select2) {
            $('.wcqm-product-select, .wcqm-category-select').select2();
        }
    });

    // Handle removing rules
    $(document).on('click', '.wcqm-remove-rule', function() {
        $(this).closest('.wcqm-rule-row').remove();
    });

    // Initialize select2 for existing selects if available
    if ($.fn.select2) {
        $('.wcqm-product-select, .wcqm-category-select').select2();
    }

    // Form submission handling
    $('#wcqm-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitButton = $form.find('input[type="submit"]');
        
        // Disable submit button to prevent double submission
        $submitButton.prop('disabled', true);
        
        // Collect form data
        var formData = new FormData($form[0]);
        formData.append('action', 'wcqm_save_settings');
        formData.append('security', wcqm_admin_data.nonce);
        
        // Send AJAX request
        $.ajax({
            url: wcqm_admin_data.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Show success message
                    var $message = $('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    $('.wcqm-settings-wrap h1').after($message);
                    
                    // Remove message after 3 seconds
                    setTimeout(function() {
                        $message.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    // Show error message
                    var $message = $('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    $('.wcqm-settings-wrap h1').after($message);
                }
            },
            error: function() {
                // Show generic error message
                var $message = $('<div class="notice notice-error"><p>An error occurred while saving settings.</p></div>');
                $('.wcqm-settings-wrap h1').after($message);
            },
            complete: function() {
                // Re-enable submit button
                $submitButton.prop('disabled', false);
            }
        });
    });
}); 