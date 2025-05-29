<?php
/**
 * WC Quantity Multiples - Checkout
 * 
 * Handles the checkout validations for product quantities.
 *
 * @package WC_Quantity_Multiples
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WCQM_Checkout Class
 * 
 * Handles checkout-specific quantity multiple functionality.
 */
class WCQM_Checkout {
    /**
     * The single instance of the class.
     *
     * @var WCQM_Checkout
     */
    protected static $_instance = null;

    /**
     * Rules manager instance
     *
     * @var WCQM_Rules_Manager
     */
    protected $rules_manager;

    /**
     * Main Checkout Instance.
     *
     * @return WCQM_Checkout - Main instance.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        // Get rules manager instance
        $this->rules_manager = WCQM_Rules_Manager::instance();

        // Validate cart items before checkout
        add_action('woocommerce_check_cart_items', array($this, 'validate_checkout_quantities'));
        
        // Add checkout validation message
        add_action('woocommerce_before_checkout_form', array($this, 'add_checkout_validation_message'));
        
        error_log('WCQM Debug: Checkout class initialized');
    }

    /**
     * Validate cart quantities before checkout
     */
    public function validate_checkout_quantities() {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }

        $invalid_items = array();
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            
            if (!$this->rules_manager->validate_quantity($product_id, $quantity)) {
                $product = wc_get_product($product_id);
                $multiple = $this->rules_manager->get_product_multiple($product_id);
                
                $invalid_items[] = array(
                    'product' => $product->get_name(),
                    'quantity' => $quantity,
                    'multiple' => $multiple
                );
            }
        }

        if (!empty($invalid_items)) {
            $message = __('The following items have invalid quantities:', 'wc-quantity-multiples') . '<br>';
            foreach ($invalid_items as $item) {
                $message .= sprintf(
                    __('%s: Quantity %d must be a multiple of %d', 'wc-quantity-multiples'),
                    $item['product'],
                    $item['quantity'],
                    $item['multiple']
                ) . '<br>';
            }
            
            wc_add_notice($message, 'error');
        }
    }

    /**
     * Add checkout validation message
     */
    public function add_checkout_validation_message() {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }

        $multiples = array();
        foreach ($cart->get_cart() as $cart_item) {
            $multiple = $this->rules_manager->get_product_multiple($cart_item['product_id']);
            if (!in_array($multiple, $multiples)) {
                $multiples[] = $multiple;
            }
        }

        if (!empty($multiples)) {
            echo '<div class="wcqm-checkout-notice">';
            echo sprintf(
                __('Please ensure all quantities are multiples of: %s', 'wc-quantity-multiples'),
                implode(', ', $multiples)
            );
            echo '</div>';
        }
    }
}

// Initialize the checkout class
WCQM_Checkout::instance(); 