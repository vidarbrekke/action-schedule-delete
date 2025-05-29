<?php
/**
 * Custom quantity input template for WC Quantity Multiples
 * 
 * Overrides the default WooCommerce quantity input template with our own version
 * that has built-in increment/decrement buttons and enforces multiples.
 *
 * @package WC_Quantity_Multiples
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get the product ID from the global product if not provided in args
global $product;
$product_id = isset($args['product_id']) ? absint($args['product_id']) : ($product ? $product->get_id() : 0);

// Check if we're on cart page
$is_cart = is_cart() || (function_exists('is_checkout') && is_checkout()) || isset($args['is_cart']) && $args['is_cart'];

// Get other template variables
$input_id = isset($args['input_id']) ? $args['input_id'] : '';
$input_name = isset($args['input_name']) ? $args['input_name'] : 'quantity';
$input_value = isset($args['input_value']) ? $args['input_value'] : '1';
$min_value = isset($args['min_value']) ? $args['min_value'] : '1';
$max_value = isset($args['max_value']) ? $args['max_value'] : '';
$step = isset($args['step']) ? $args['step'] : '1';
$classes = isset($args['classes']) ? $args['classes'] : array('input-text', 'qty', 'text');
$readonly = isset($args['readonly']) ? $args['readonly'] : false;

// Get potential rule-specific data
$rules_manager = null;
if (class_exists('WCQM_Rules_Manager')) {
    $rules_manager = WCQM_Rules_Manager::instance();
}

// Apply rule-specific values if available
if ($rules_manager && $product_id) {
    $multiple = $rules_manager->get_product_multiple($product_id);
    $min_value = $multiple;
    $step = $multiple;
    
    // Make sure input value is at least the minimum and a multiple
    if ($input_value < $min_value) {
        $input_value = $min_value;
    } else {
        // Adjust to nearest multiple
        $remainder = $input_value % $multiple;
        if ($remainder !== 0) {
            $input_value = $input_value - $remainder + ($remainder >= ($multiple / 2) ? $multiple : 0);
        }
    }
}

// Ensure input_value is at least the min_value
if ($input_value < $min_value) {
    $input_value = $min_value;
}

// Custom attributes for the input
$custom_attributes = array(
    'data-product-id' => $product_id,
    'data-multiple' => $step,
    'data-title' => sprintf(__('Please enter a multiple of %d', 'wc-quantity-multiples'), $step)
);

if (isset($args['custom_attributes']) && is_array($args['custom_attributes'])) {
    $custom_attributes = array_merge($custom_attributes, $args['custom_attributes']);
}

// Generate a unique ID for this instance if none provided
if (empty($input_id)) {
    $input_id = 'wcqm_qty_' . uniqid();
}

?>
<div class="wcqm-quantity quantity" data-wcqm-container="true">
    <style>
        /* Reset any theme styles that might interfere */
        .wcqm-quantity {
            position: relative !important;
            display: flex !important;
            align-items: center !important;
            width: auto !important;
            margin: 0 0 1em !important;
            clear: both !important;
            overflow: visible !important;
            height: 40px !important;
            min-height: 40px !important;
        }
        
        .wcqm-quantity input.qty {
            -moz-appearance: textfield !important;
            width: 3.631em !important;
            height: 40px !important;
            padding: 0 !important;
            margin: 0 !important;
            text-align: center !important;
            background-color: #fff !important;
            border: 1px solid #ddd !important;
            border-left: none !important;
            border-right: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            font-size: 1em !important;
            line-height: 1.5 !important;
        }
        
        /* Hide browser spinners */
        .wcqm-quantity input.qty::-webkit-outer-spin-button,
        .wcqm-quantity input.qty::-webkit-inner-spin-button {
            -webkit-appearance: none !important;
            margin: 0 !important;
            display: none !important;
        }
        
        /* Style buttons */
        .wcqm-quantity .wcqm-btn {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 2em !important;
            height: 40px !important;
            padding: 0 !important;
            margin: 0 !important;
            background-color: #f7f7f7 !important;
            border: 1px solid #ddd !important;
            cursor: pointer !important;
            font-size: 1em !important;
            font-weight: bold !important;
            text-decoration: none !important;
            color: #666 !important;
            line-height: 1 !important;
            position: relative !important;
            outline: none !important;
            box-shadow: none !important;
            z-index: 5 !important;
        }
        
        .wcqm-quantity .wcqm-btn.minus {
            border-top-left-radius: 4px !important; 
            border-bottom-left-radius: 4px !important;
            border-right: none !important;
        }
        
        .wcqm-quantity .wcqm-btn.plus {
            border-top-right-radius: 4px !important;
            border-bottom-right-radius: 4px !important;
            border-left: none !important;
        }
        
        .wcqm-quantity .wcqm-btn:hover {
            background-color: #e6e6e6 !important;
        }
        
        /* Make sure button text is visible */
        .wcqm-quantity .wcqm-btn:before,
        .wcqm-quantity .wcqm-btn:after {
            display: none !important;
        }
        
        /* Cart-specific styles */
        .woocommerce-cart-form .wcqm-quantity {
            margin: 0 auto !important;
        }
        
        /* Force visibility and interactivity for cart page */
        .woocommerce-cart-form .wcqm-quantity input.qty {
            pointer-events: auto !important;
            opacity: 1 !important;
            background-color: #fff !important;
        }
        
        /* Fix for readonly cart pages in some themes */
        .woocommerce-cart .wcqm-quantity input.qty[readonly],
        .woocommerce-checkout .wcqm-quantity input.qty[readonly] {
            background-color: #fff !important;
            pointer-events: auto !important;
            opacity: 1 !important;
            cursor: text !important;
        }
        
        /* Add additional styles for cart page */
        .woocommerce-cart-form .wcqm-quantity {
            display: flex !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }
        
        .woocommerce-cart-form .wcqm-quantity input.qty,
        .woocommerce-checkout .wcqm-quantity input.qty {
            opacity: 1 !important;
            pointer-events: auto !important;
            background-color: #fff !important;
        }
        
        /* Fix button visibility in cart */
        .woocommerce-cart-form .wcqm-quantity .wcqm-btn,
        .woocommerce-checkout .wcqm-quantity .wcqm-btn {
            display: flex !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }
    </style>
    
    <button 
        type="button" 
        class="wcqm-btn minus" 
        aria-label="<?php esc_attr_e('Decrease quantity', 'wc-quantity-multiples'); ?>" 
        data-wcqm-btn="minus"
        data-input-id="<?php echo esc_attr($input_id); ?>"
    >-</button>
    
    <input
        type="number"
        id="<?php echo esc_attr($input_id); ?>"
        name="<?php echo esc_attr($input_name); ?>"
        class="<?php echo esc_attr(implode(' ', (array) $classes)); ?>"
        value="<?php echo esc_attr($input_value); ?>"
        title="<?php echo esc_attr_x('Qty', 'Product quantity input tooltip', 'woocommerce'); ?>"
        min="<?php echo esc_attr($min_value); ?>"
        <?php if ($max_value) : ?>max="<?php echo esc_attr($max_value); ?>"<?php endif; ?>
        step="<?php echo esc_attr($step); ?>"
        <?php echo $readonly ? 'readonly="readonly"' : ''; ?>
        <?php 
        foreach ($custom_attributes as $attribute => $attribute_value) {
            echo esc_attr($attribute) . '="' . esc_attr($attribute_value) . '" ';
        }
        ?>
        data-wcqm-id="<?php echo esc_attr($input_id); ?>"
        data-wcqm-input="true"
        <?php if ($is_cart) : ?>data-wcqm-cart="true"<?php endif; ?>
    />
    
    <button 
        type="button" 
        class="wcqm-btn plus" 
        aria-label="<?php esc_attr_e('Increase quantity', 'wc-quantity-multiples'); ?>" 
        data-wcqm-btn="plus"
        data-input-id="<?php echo esc_attr($input_id); ?>"
    >+</button>
</div>

<script type="text/javascript">
    (function($) {
        // Initialization
        $(document).ready(function() {
            // Make sure we disable form validation
            $('form').attr('novalidate', 'novalidate');
            
            // Initialize all quantity inputs
            initAllQuantityInputs();
            
            // Set up cart-specific handlers if needed
            setupCartHandlers();
            
            // Handle WCQM button clicks
            $(document).on('click', '.wcqm-btn', function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var action = $button.data('wcqm-btn');
                var inputId = $button.data('input-id');
                var $input = $('#' + inputId);
                
                if (!$input.length) {
                    // Try to find the input as a sibling
                    $input = $button.siblings('input.qty');
                }
                
                if (!$input.length) {
                    console.error('WCQM: Could not find input for button', $button);
                    return;
                }
                
                // Get values and constraints
                var currentVal = parseFloat($input.val()) || 0;
                var min = parseFloat($input.attr('min'));
                var max = parseFloat($input.attr('max'));
                
                // Get the step (multiple) to use
                var productId = $input.data('product-id');
                var multiple = parseFloat($input.data('multiple')) || 1;
                
                // Check for pre-resolved multiple from rules data
                if (productId && window.wcqm_rules && window.wcqm_rules.resolved_products && 
                    typeof window.wcqm_rules.resolved_products[productId] !== 'undefined') {
                    multiple = parseInt(window.wcqm_rules.resolved_products[productId]);
                    console.log('Button using pre-resolved multiple for product ' + productId + ': ' + multiple);
                }
                
                // Ensure min is at least one multiple
                if (isNaN(min) || min < multiple) {
                    min = multiple;
                }
                
                // Calculate new value
                var newVal = currentVal;
                
                if (action === 'plus') {
                    newVal = currentVal + multiple;
                    console.log('Plus: ' + currentVal + ' + ' + multiple + ' = ' + newVal);
                    
                    // Check max value
                    if (!isNaN(max) && (newVal > max)) {
                        newVal = max;
                    }
                } else if (action === 'minus') {
                    newVal = currentVal - multiple;
                    console.log('Minus: ' + currentVal + ' - ' + multiple + ' = ' + newVal);
                    
                    // Check min value
                    if (newVal < min) {
                        newVal = min;
                    }
                }
                
                // Update value and trigger change
                $input.val(newVal).trigger('change');
            });
        });
        
        // Initialize all quantity inputs
        function initAllQuantityInputs() {
            $('.wcqm-quantity').each(function() {
                initQuantityInput($(this));
            });
        }
        
        // Initialize a single quantity input
        function initQuantityInput($container) {
            if (!$container.length) return;
            
            var $input = $container.find('input[data-wcqm-input]');
            var $minus = $container.find('[data-wcqm-btn="minus"]');
            var $plus = $container.find('[data-wcqm-btn="plus"]');
            
            if (!$input.length) return;
            
            // IMPORTANT: Get product ID and multiple from the input attributes
            var productId = parseInt($input.data('product-id')) || 0;
            var multiple = parseInt($input.attr('step')) || 1;
            
            // Double check if we're in cart and ensure we use the correct multiple value
            if ($input.data('wcqm-cart')) {
                // Force the multiple to match what's set in the step attribute - this is coming from the WooCommerce quantity_input_args filter
                multiple = parseInt($input.attr('step')) || 1;
                console.log('Cart item detected for product ID: ' + productId + ' - Using step value: ' + multiple);
                console.log('Full input element:', $input[0].outerHTML);
            }
            
            // Store these values for debugging
            $input.data('wcqm-multiple', multiple);
            
            // Set up plus-minus buttons
            $minus.off('click.wcqm').on('click.wcqm', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var val = parseInt($input.val()) || 0;
                // Get the most up-to-date multiple value from the input
                var currentMultiple = parseInt($input.attr('step')) || 1;
                
                // Double-check multiple from pre-resolved values when possible
                if (productId && window.wcqm_rules && window.wcqm_rules.resolved_products && window.wcqm_rules.resolved_products[productId]) {
                    currentMultiple = parseInt(window.wcqm_rules.resolved_products[productId]);
                    console.log('Using pre-resolved multiple from rules: ' + currentMultiple);
                }
                
                console.log('Decrement using multiple: ' + currentMultiple + ' for product: ' + productId);
                var newVal = val - currentMultiple;
                if (newVal < currentMultiple) newVal = currentMultiple;
                $input.val(newVal).trigger('change');
                console.log('Manual decrement: ' + val + ' - ' + currentMultiple + ' = ' + newVal);
                
                updateButtonStates($container);
            });
            
            $plus.off('click.wcqm').on('click.wcqm', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var val = parseInt($input.val()) || 0;
                // Get the most up-to-date multiple value from the input
                var currentMultiple = parseInt($input.attr('step')) || 1;
                
                // Double-check multiple from pre-resolved values when possible
                if (productId && window.wcqm_rules && window.wcqm_rules.resolved_products && window.wcqm_rules.resolved_products[productId]) {
                    currentMultiple = parseInt(window.wcqm_rules.resolved_products[productId]);
                    console.log('Using pre-resolved multiple from rules: ' + currentMultiple);
                }
                
                console.log('Increment using multiple: ' + currentMultiple + ' for product: ' + productId);
                var newVal = val + currentMultiple;
                var max = parseInt($input.attr('max')) || 0;
                if (max && newVal > max) newVal = Math.floor(max / currentMultiple) * currentMultiple;
                $input.val(newVal).trigger('change');
                console.log('Manual increment: ' + val + ' + ' + currentMultiple + ' = ' + newVal);
                
                updateButtonStates($container);
            });
            
            // Handle direct input changes
            $input.off('change.wcqm input.wcqm blur.wcqm').on('change.wcqm input.wcqm blur.wcqm', function() {
                updateButtonStates($container);
                
                // Get the most up-to-date multiple value from the input
                var currentMultiple = parseInt($input.attr('step')) || 1;
                
                // Ensure value is a multiple
                var val = parseInt($input.val()) || 0;
                if (val % currentMultiple !== 0 || val < currentMultiple) {
                    // Fix the value
                    var remainder = val % currentMultiple;
                    var newVal = remainder >= currentMultiple/2 ? val + (currentMultiple - remainder) : val - remainder;
                    if (newVal < currentMultiple) newVal = currentMultiple;
                    $input.val(newVal);
                    console.log('Adjusted value to nearest multiple: ' + val + ' -> ' + newVal);
                }
            });
            
            // Find the form and disable validation
            var $form = $input.closest('form');
            if ($form.length) {
                $form.attr('novalidate', 'novalidate');
                
                // Prevent form submission if value is invalid
                $form.off('submit.wcqm').on('submit.wcqm', function(e) {
                    var val = parseInt($input.val()) || 0;
                    if (val % currentMultiple !== 0 || val < currentMultiple) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // No error message displayed - silently fix the value
                        var remainder = val % currentMultiple;
                        var newVal = remainder >= currentMultiple/2 ? val + (currentMultiple - remainder) : val - remainder;
                        if (newVal < currentMultiple) newVal = currentMultiple;
                        $input.val(newVal);
                        
                        return false;
                    }
                });
            }
            
            // Update button states initially
            updateButtonStates($container);
        }
        
        // Update button states
        function updateButtonStates($container) {
            if (!$container.length) return;
            
            var $input = $container.find('input[data-wcqm-input]');
            var $minus = $container.find('[data-wcqm-btn="minus"]');
            var $plus = $container.find('[data-wcqm-btn="plus"]');
            
            if (!$input.length) return;
            
            var val = parseInt($input.val()) || 0;
            var min = parseInt($input.attr('min')) || 0;
            var max = parseInt($input.attr('max')) || 0;
            
            $minus.toggleClass('disabled', val <= min);
            $plus.toggleClass('disabled', max > 0 && val >= max);
        }
        
        // Set up cart-specific handlers
        function setupCartHandlers() {
            // Make sure cart inputs aren't readonly
            $('.woocommerce-cart-form .quantity input.qty, .woocommerce-checkout .quantity input.qty').prop('readonly', false);
            
            // Handle cart quantity changes
            $(document).on('change', '[data-wcqm-cart="true"]', function() {
                var $updateButton = $('.actions .button[name="update_cart"]');
                if ($updateButton.length) {
                    $updateButton.prop('disabled', false);
                }
            });
        }
        
        // Re-initialize after AJAX operations
        $(document).on('updated_cart_totals updated_checkout added_to_cart wc_fragments_loaded wc_fragments_refreshed', function() {
            setTimeout(function() {
                initAllQuantityInputs();
                setupCartHandlers();
            }, 100);
        });
    })(jQuery);
</script> 