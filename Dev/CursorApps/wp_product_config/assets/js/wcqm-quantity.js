/**
 * WC Quantity Multiples - Frontend JavaScript
 * Handles only UI interactions for quantity inputs
 */
(function($) {
    'use strict';

    // Simple increment/decrement functionality
    function initQuantityButtons() {
        $(document.body).on('click', '.quantity .plus, .quantity .minus', function(e) {
            e.preventDefault();
            
            const $input = $(this).closest('.quantity').find('.qty');
            const step = parseInt($input.attr('step'), 10) || 1;
            const min = parseInt($input.attr('min'), 10) || 1;
            let currentVal = parseInt($input.val(), 10) || 0;

            if ($(this).hasClass('plus')) {
                currentVal += step;
            } else {
                currentVal = Math.max(min, currentVal - step);
            }

            $input.val(currentVal).trigger('change');
        });
    }

    // Initialize on document ready
    $(function() {
        initQuantityButtons();
    });

    // Re-initialize on AJAX complete (for dynamic content)
    $(document.body).on('updated_cart_totals', function() {
        initQuantityButtons();
    });

})(jQuery); 