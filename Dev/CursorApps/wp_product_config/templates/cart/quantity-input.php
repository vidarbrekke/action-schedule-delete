<?php
/**
 * Product quantity inputs
 *
 * Custom version for WC Quantity Multiples that relies on server-side validation
 *
 * @package WC_Quantity_Multiples
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Special products that should always use multiple of 6
$special_products = array(4522, 4523, 4524, 24320, 53051, 53054, 53151, 64638, 75360);

// Try to get the rules manager instance safely
$rules_manager = null;
if (function_exists('WCQM_Rules_Manager::instance')) {
    $rules_manager = WCQM_Rules_Manager::instance();
}

// Set up variables
$multiple = isset($multiple) ? absint($multiple) : 1;
$product_id = isset($product_id) ? absint($product_id) : 0;
$cart_item_key = isset($cart_item_key) ? $cart_item_key : '';
$input_name = isset($input_name) ? $input_name : 'quantity';
$value = isset($value) ? $value : 1;

// Special products check MUST happen first
if (in_array($product_id, $special_products)) {
    $multiple = 6;
    error_log(sprintf('WCQM Template: Product %d is special, using multiple of 6', $product_id));
} 
// Then check cart item data
else if (!empty($cart_item_key) && function_exists('WC') && isset(WC()->cart)) {
    $cart_item = WC()->cart->get_cart_item($cart_item_key);
    if ($cart_item && isset($cart_item['quantity_multiple'])) {
        $multiple = intval($cart_item['quantity_multiple']);
        error_log(sprintf('WCQM Template: Product %d using cart item multiple: %d', $product_id, $multiple));
    }
}
// Finally check rules manager
else if ($rules_manager && $product_id && $multiple <= 1) {
    $multiple = $rules_manager->get_product_multiple($product_id);
    error_log(sprintf('WCQM Template: Product %d using rules manager multiple: %d', $product_id, $multiple));
}

// Ensure we have a valid multiple
if ($multiple <= 0) {
    $multiple = 1; // Fallback to prevent division by zero
    error_log(sprintf('WCQM Template: Invalid multiple for product %d, defaulting to 1', $product_id));
}

// Ensure value is a valid multiple
if ($value % $multiple !== 0) {
    // Round to nearest valid multiple
    $remainder = $value % $multiple;
    if ($remainder >= $multiple / 2) {
        $value = $value + ($multiple - $remainder);
    } else {
        $value = $value - $remainder;
    }
    
    // Ensure at least one multiple
    if ($value < $multiple) {
        $value = $multiple;
    }
}

// Set the minimum value to be the multiple
$min_value = $multiple;

// Generate a unique HTML ID
$input_id = uniqid('quantity_');

// Debug output
if (is_cart()) {
    echo '<!-- Cart item detected for product ID: ' . esc_html($product_id) . ' - Using step value: ' . esc_html($multiple) . ' -->';
}
?>
<div class="quantity">
    <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php esc_html_e('Quantity', 'woocommerce'); ?></label>
    <input
        type="number"
        id="<?php echo esc_attr($input_id); ?>"
        name="<?php echo esc_attr($input_name); ?>"
        class="input-text qty text"
        value="<?php echo esc_attr($value); ?>"
        title="<?php echo esc_attr_x('Qty', 'Product quantity input tooltip', 'woocommerce'); ?>"
        min="<?php echo esc_attr($multiple); ?>"
        max="<?php echo esc_attr(isset($max_value) ? $max_value : ''); ?>"
        step="<?php echo esc_attr($multiple); ?>"
        data-product-id="<?php echo esc_attr($product_id); ?>"
        data-multiple="<?php echo esc_attr($multiple); ?>"
        data-title="<?php echo esc_attr__('Please enter a multiple of', 'wc-quantity-multiples') . ' ' . esc_attr($multiple); ?>"
        data-wcqm-id="<?php echo esc_attr($input_id); ?>"
        data-wcqm-input="true"
        <?php if (!empty($cart_item_key)) : ?>
        data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>"
        <?php endif; ?>
        data-enforce-multiple="yes"
        data-has-product-rule="<?php echo esc_attr(($rules_manager && $product_id && $rules_manager->get_product_rule($product_id)) ? 'yes' : 'no'); ?>"
    />
    <?php if (is_cart()) : ?>
    <!-- Full input element: <?php echo htmlspecialchars('<input type="number" id="' . esc_attr($input_id) . '" name="' . esc_attr($input_name) . '" class="input-text qty text" value="' . esc_attr($value) . '" title="' . esc_attr_x('Qty', 'Product quantity input tooltip', 'woocommerce') . '" min="' . esc_attr($min_value) . '" max="' . esc_attr(isset($max_value) ? $max_value : '') . '" step="' . esc_attr($multiple) . '" data-product-id="' . esc_attr($product_id) . '" data-multiple="' . esc_attr($multiple) . '" data-title="' . esc_attr__('Please enter a multiple of', 'wc-quantity-multiples') . ' ' . esc_attr($multiple) . '" data-wcqm-id="' . esc_attr($input_id) . '" data-wcqm-input="true" data-wcqm-cart="true" data-enforce-multiple="yes" data-has-product-rule="' . esc_attr(($rules_manager && $product_id && $rules_manager->get_product_rule($product_id)) ? 'yes' : 'no') . '">'); ?> -->
    <?php endif; ?>
    
    <button type="button" class="minus" aria-label="<?php esc_attr_e('Decrease quantity', 'woocommerce'); ?>">
        <span class="dashicons dashicons-minus"></span>
    </button>
    <button type="button" class="plus" aria-label="<?php esc_attr_e('Increase quantity', 'woocommerce'); ?>">
        <span class="dashicons dashicons-plus"></span>
    </button>
</div> 