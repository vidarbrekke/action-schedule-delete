<?php
/**
 * Cart-specific quantity input template for WC Quantity Multiples
 * 
 * This is a specialized template for the cart page that ensures better
 * compatibility with the cart update process.
 *
 * @package WC_Quantity_Multiples
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Set defaults for template variables
$readonly = isset($readonly) ? $readonly : '';
$classes = isset($classes) ? $classes : array('input-text', 'qty', 'text');
$input_name = isset($input_name) ? $input_name : 'quantity';
$input_id = isset($input_id) ? $input_id : '';
$cart_item_key = isset($cart_item_key) ? $cart_item_key : '';
$input_value = isset($input_value) ? $input_value : 1;

// Get product ID from the cart item
$product_id = 0;
if (isset($args['cart_item']) && isset($args['cart_item']['product_id'])) {
    $product_id = $args['cart_item']['product_id'];
} elseif (isset($product_id)) {
    // Use product_id if directly provided
} elseif (isset($args['product_id'])) {
    $product_id = $args['product_id'];
}

// Try to get the rules manager instance safely
$multiple = 1; // Default fallback if no rules found
if (class_exists('WCQM_Rules_Manager')) {
    $rules_manager = WCQM_Rules_Manager::instance();
    if (method_exists($rules_manager, 'get_product_multiple')) {
        $product_multiple = $rules_manager->get_product_multiple($product_id);
        if ($product_multiple > 0) {
            $multiple = $product_multiple;
            error_log("WCQM Debug: Cart template using multiple {$multiple} for product {$product_id}");
        }
    }
}

// Ensure min value is a multiple
if (isset($min_value)) {
    $min_value = max($multiple, ceil($min_value / $multiple) * $multiple);
} else {
    $min_value = $multiple;
}

// Ensure max value is a multiple if it's set
if (isset($max_value) && $max_value > 0) {
    $max_value = floor($max_value / $multiple) * $multiple;
    if ($max_value < $min_value) {
        $max_value = $min_value;
    }
} else {
    $max_value = '';
}

// Ensure input value is a multiple
if ($input_value % $multiple !== 0) {
    $input_value = ceil($input_value / $multiple) * $multiple;
}

// Generate a unique ID for this input using cart item key for uniqueness
$unique_id = 'wcqm-cart-qty-' . (isset($cart_item_key) ? $cart_item_key : uniqid());
?>

<div class="wcqm-cart-quantity" data-multiple="<?php echo esc_attr($multiple); ?>" data-product-id="<?php echo esc_attr($product_id); ?>">
    <!-- Hidden input that WooCommerce will use for form submission -->
    <input
        type="hidden"
        id="<?php echo esc_attr($input_id); ?>"
        class="<?php echo esc_attr(implode(' ', (array) $classes)); ?>"
        name="<?php echo esc_attr($input_name); ?>"
        value="<?php echo esc_attr($input_value); ?>"
        data-multiple="<?php echo esc_attr($multiple); ?>"
        data-product-id="<?php echo esc_attr($product_id); ?>"
        data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>"
    />
    
    <!-- Visible quantity control with buttons -->
    <div class="wcqm-cart-quantity-control" id="<?php echo esc_attr($unique_id); ?>" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>">
        <button type="button" class="wcqm-cart-minus" data-action="minus">-</button>
        <span class="wcqm-cart-quantity-display"><?php echo esc_html($input_value); ?></span>
        <button type="button" class="wcqm-cart-plus" data-action="plus">+</button>
    </div>
    
    <style type="text/css">
        /* Cart-specific quantity styling */
        .wcqm-cart-quantity {
            display: flex;
            position: relative;
            width: 110px;
            margin: 0 auto;
        }
        
        .wcqm-cart-quantity-control {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            height: 40px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            position: relative;
            background: #fff;
            overflow: visible;
        }
        
        .wcqm-cart-quantity button {
            flex: 0 0 30px;
            width: 30px;
            height: 38px;
            border: none;
            background: #f5f5f5;
            color: #333;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 0;
            position: relative;
        }
        
        .wcqm-cart-quantity button:hover {
            background: #e0e0e0;
        }
        
        .wcqm-cart-quantity .wcqm-cart-minus {
            border-right: 1px solid #ddd;
            border-top-left-radius: 3px;
            border-bottom-left-radius: 3px;
        }
        
        .wcqm-cart-quantity .wcqm-cart-plus {
            border-left: 1px solid #ddd;
            border-top-right-radius: 3px;
            border-bottom-right-radius: 3px;
        }
        
        .wcqm-cart-quantity-display {
            flex: 1;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            line-height: 38px;
            color: #333;
            background: #fff;
        }
        
        /* Hide the original update cart button for simplicity */
        .woocommerce .quantity .qty {
            display: none !important;
        }
        
        @media (max-width: 768px) {
            .wcqm-cart-quantity {
                width: 100px;
            }
            
            .wcqm-cart-quantity button {
                flex: 0 0 25px;
                width: 25px;
                font-size: 16px;
            }
        }
    </style>
</div>

<?php if (!defined('WCQM_CART_SCRIPT_LOADED')): ?>
<?php define('WCQM_CART_SCRIPT_LOADED', true); ?>
<!-- Only load this script once -->
<script type="text/javascript">
(function($) {
    // Global initialization function that will run on page load AND after cart updates
    function initWcqmCartQuantityControls() {
        // Use event delegation to handle clicks on quantity buttons
        $(document.body).off('click', '.wcqm-cart-quantity-control button');
        $(document.body).on('click', '.wcqm-cart-quantity-control button', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $control = $button.closest('.wcqm-cart-quantity-control');
            var $quantityWrapper = $control.closest('.wcqm-cart-quantity');
            var $hiddenInput = $quantityWrapper.find('input[type="hidden"]');
            var $displayElement = $control.find('.wcqm-cart-quantity-display');
            
            // Get data attributes
            var productId = parseInt($quantityWrapper.data('product-id'), 10);
            var multiple = parseInt($quantityWrapper.data('multiple'), 10) || 1;
            var action = $button.data('action');
            var cartItemKey = $control.data('cart-item-key');
            
            // Check for pre-resolved multiple from rules data
            if (productId && window.wcqm_rules && window.wcqm_rules.resolved_products && window.wcqm_rules.resolved_products[productId]) {
                multiple = parseInt(window.wcqm_rules.resolved_products[productId], 10);
                console.log('Cart using pre-resolved multiple from rules for product ' + productId + ': ' + multiple);
            }
            
            // Get current value
            var currentValue = parseInt($hiddenInput.val(), 10);
            
            // No input found or invalid value
            if (isNaN(currentValue)) return;
            
            // Get min and max values
            var min = multiple; // Minimum is always at least one multiple
            var max = 0; // Default no max
            
            // Handle the plus or minus action
            var newValue = currentValue;
            
            if (action === 'minus' && currentValue > min) {
                newValue = currentValue - multiple;
                console.log('Cart minus: ' + currentValue + ' - ' + multiple + ' = ' + newValue);
            } else if (action === 'plus') {
                newValue = currentValue + multiple;
                console.log('Cart plus: ' + currentValue + ' + ' + multiple + ' = ' + newValue);
            } else {
                // No change needed
                return;
            }
            
            // Update display and hidden input values
            $displayElement.text(newValue);
            $hiddenInput.val(newValue);
            
            // Trigger change event
            $hiddenInput.trigger('change');
            
            // Set the active cart item key in a custom hidden input
            if (!$('#wcqm-updated-item').length) {
                $('form.woocommerce-cart-form').append('<input type="hidden" id="wcqm-updated-item" name="wcqm_updated_item" value="">');
            }
            
            $('#wcqm-updated-item').val(cartItemKey);
            
            // Trigger cart update
            $('.woocommerce-cart-form [name="update_cart"]').prop('disabled', false).trigger('click');
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initWcqmCartQuantityControls();
    });

    // Also re-initialize after cart updates
    $(document.body).on('updated_cart_totals', function() {
        initWcqmCartQuantityControls();
    });
    
    $(document.body).on('wc_fragments_loaded wc_fragments_refreshed', function() {
        initWcqmCartQuantityControls();
    });
})(jQuery);
</script>
<?php endif; ?> 