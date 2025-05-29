<?php
/**
 * Product quantity inputs
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/global/quantity-input.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.8.0
 */

defined('ABSPATH') || exit;

/* translators: %s: Quantity. */
$label = ! empty( $args['product_name'] ) ? sprintf( esc_html__( '%s quantity', 'woocommerce' ), wp_strip_all_tags( $args['product_name'] ) ) : esc_html__( 'Quantity', 'woocommerce' );

// Get the multiple from the args if it's set
$multiple = isset($args['step']) ? $args['step'] : 1;
$min_value = isset($args['min_value']) ? $args['min_value'] : 0;
$max_value = isset($args['max_value']) ? $args['max_value'] : '';
$input_value = isset($args['input_value']) ? $args['input_value'] : $min_value;

// Ensure input_value is a multiple
if ($multiple > 1 && $input_value > 0) {
    $mod = $input_value % $multiple;
    if ($mod > 0) {
        $input_value = $input_value + ($multiple - $mod);
    }
}

// Log final values for debugging
error_log("WCQM Template: Product {$input_id}, Multiple: {$multiple}, Min: {$min_value}, Value: {$input_value}");
?>
<div class="quantity">
	<?php do_action( 'woocommerce_before_quantity_input_field' ); ?>
	<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $label ); ?></label>
	<input
		type="text"
		<?php echo $readonly ? 'readonly="readonly"' : ''; ?>
		id="<?php echo esc_attr( $input_id ); ?>"
		class="<?php echo esc_attr( join( ' ', (array) $classes ) ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>"
		value="<?php echo esc_attr( $input_value ); ?>"
		title="<?php echo esc_attr_x( 'Qty', 'Product quantity input tooltip', 'woocommerce' ); ?>"
		size="4"
		placeholder="<?php echo esc_attr( $placeholder ); ?>"
		inputmode="numeric"
		autocomplete="<?php echo esc_attr( isset( $autocomplete ) ? $autocomplete : 'on' ); ?>"
		<?php if ( ! empty( $step ) ) : ?>
			data-step="<?php echo esc_attr( $step ); ?>"
		<?php endif; ?>
		<?php if ( ! empty( $min_value ) ) : ?>
			data-min="<?php echo esc_attr( $min_value ); ?>"
		<?php endif; ?>
		<?php if ( ! empty( $max_value ) ) : ?>
			data-max="<?php echo esc_attr( $max_value ); ?>"
		<?php endif; ?>
		<?php if ( isset( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) : ?>
			<?php foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) : ?>
				<?php echo esc_attr( $attribute ); ?>="<?php echo esc_attr( $attribute_value ); ?>"
			<?php endforeach; ?>
		<?php endif; ?>
	/>
	<?php do_action( 'woocommerce_after_quantity_input_field' ); ?>
</div> 