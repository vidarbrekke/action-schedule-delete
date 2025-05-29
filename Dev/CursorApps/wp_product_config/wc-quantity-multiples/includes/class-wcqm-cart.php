<?php
/**
 * WC Quantity Multiples - Cart
 * 
 * Handles the cart quantity validations and modifications.
 *
 * @package WC_Quantity_Multiples
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check for WordPress functions
if ( ! function_exists( 'add_action' ) || 
    ! function_exists( 'add_filter' ) || 
    ! function_exists( 'get_option' ) || 
    ! function_exists( 'update_option' ) || 
    ! function_exists( 'wp_enqueue_script' ) || 
    ! function_exists( 'wp_enqueue_style' ) ||
    ! function_exists( 'wc_add_notice' ) ||
    ! function_exists( 'esc_attr' ) ||
    ! function_exists( 'esc_html' ) ||
    ! function_exists( 'is_admin' ) ) {
    return;
}

/**
 * WCQM_Cart Class
 * 
 * Handles cart-specific quantity multiple functionality.
 */
class WCQM_Cart {
    /**
     * The single instance of the class.
     *
     * @var WCQM_Cart
     */
    protected static $_instance = null;

    /**
     * Rules manager instance
     *
     * @var WCQM_Rules_Manager
     */
    protected $rules_manager;

    /**
     * Main Cart Instance.
     *
     * @return WCQM_Cart - Main instance.
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

        // Initialize cart integration
        $this->init();

        error_log('WCQM Debug: Cart class initialized');
    }

    /**
     * Initialize cart integration
     */
    public function init() {
        // Hook into cart item quantity field
        add_filter( 'woocommerce_quantity_input_args', array( $this, 'modify_quantity_args' ), 10, 2 );
        
        // Validate cart item quantities
        add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_cart_item_quantity' ), 10, 4 );
        
        // Ensure quantities are multiples when added to cart
        add_filter( 'woocommerce_add_to_cart_quantity', array( $this, 'ensure_add_to_cart_multiple' ), 10, 2 );
        
        // Ensure our quantity template is used in cart
        add_filter( 'woocommerce_locate_template', array( $this, 'override_cart_quantity_template' ), 10, 3 );
        
        // Add is_cart flag to quantity input args - use high priority to override other plugins
        add_filter( 'woocommerce_quantity_input_args', array( $this, 'add_cart_flag' ), 99, 2 );
        
        // Log initialization
        error_log( 'WCQM_Cart::init() - Cart integration initialized' );
    }

    /**
     * Modify quantity input args for cart items
     *
     * @param array $args     Arguments
     * @param mixed $product  Product
     * @return array Modified arguments
     */
    public function modify_quantity_args($args, $product) {
        if (!$product) {
            return $args;
        }
        
        $product_id = $product->get_id();
        $multiple = $this->rules_manager->get_product_multiple($product_id);
        
        if ($multiple > 0) {
            $args['step'] = $multiple;
            $args['min_value'] = $multiple;
            
            // Ensure current value is a multiple
            if (isset($args['input_value']) && $args['input_value'] > 0) {
                $current = $args['input_value'];
                $remainder = $current % $multiple;
                
                if ($remainder !== 0) {
                    // Round to the nearest multiple
                    if ($remainder >= ($multiple / 2)) {
                        $args['input_value'] = $current + ($multiple - $remainder);
                    } else {
                        $args['input_value'] = $current - $remainder;
                    }
                    
                    // Ensure minimum value
                    if ($args['input_value'] < $multiple) {
                        $args['input_value'] = $multiple;
                    }
                }
            } else {
                // Default to minimum value
                $args['input_value'] = $multiple;
            }
        }
        
        return $args;
    }
    
    /**
     * Validate cart item quantity to ensure it's a multiple
     *
     * @param bool   $passed            Whether the validation passed
     * @param string $cart_item_key     Cart item key
     * @param array  $cart_item         Cart item data
     * @param int    $quantity          Quantity
     * @return bool Whether validation passed
     */
    public function validate_cart_item_quantity($passed, $cart_item_key, $cart_item, $quantity) {
        if (!isset($cart_item['product_id'])) {
            return $passed;
        }
        
        $product_id = $cart_item['product_id'];
        $multiple = $this->rules_manager->get_product_multiple($product_id);
        
        if ($multiple > 0 && $quantity % $multiple !== 0) {
            // Get product information
            $product = wc_get_product($product_id);
            
            // Add an error message
            wc_add_notice(
                sprintf(
                    __('The quantity for "%s" must be a multiple of %d. Please adjust your cart.', 'wc-quantity-multiples'),
                    $product ? $product->get_name() : 'Product',
                    $multiple
                ),
                'error'
            );
            
            return false;
        }
        
        return $passed;
    }
    
    /**
     * Ensure add to cart quantity is a multiple
     *
     * @param int $quantity    Quantity
     * @param int $product_id  Product ID
     * @return int Adjusted quantity
     */
    public function ensure_add_to_cart_multiple($quantity, $product_id) {
        $multiple = $this->rules_manager->get_product_multiple($product_id);
        
        if ($multiple > 0 && $quantity % $multiple !== 0) {
            // Round to the nearest multiple
            $remainder = $quantity % $multiple;
            
            if ($remainder >= ($multiple / 2)) {
                $quantity = $quantity + ($multiple - $remainder);
            } else {
                $quantity = $quantity - $remainder;
            }
            
            // Ensure minimum value
            if ($quantity < $multiple) {
                $quantity = $multiple;
            }
        }
        
        return $quantity;
    }

    /**
     * Adjust cart totals
     */
    public function adjust_cart_totals($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            
            if (!$this->rules_manager->validate_quantity($product_id, $quantity)) {
                $adjusted_quantity = $this->rules_manager->adjust_quantity($product_id, $quantity);
                if ($adjusted_quantity !== $quantity) {
                    $cart->set_quantity($cart_item_key, $adjusted_quantity);
                }
            }
        }
    }

    /**
     * Add cart validation message
     */
    public function add_cart_validation_message() {
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
            echo '<div class="wcqm-cart-notice">';
            echo sprintf(
                __('Please ensure all quantities are multiples of: %s', 'wc-quantity-multiples'),
                implode(', ', $multiples)
            );
            echo '</div>';
        }
    }

    /**
     * Override the cart quantity template
     * 
     * @param string $template      Template path
     * @param string $template_name Template name
     * @param string $template_path Template path
     * @return string Modified template path
     */
    public function override_cart_quantity_template( $template, $template_name, $template_path ) {
        // Only override quantity-input.php template
        if ( $template_name !== 'global/quantity-input.php' ) {
            return $template;
        }
        
        // Get our template path - use the global template for all pages
        $plugin_template = WCQM_PLUGIN_DIR . 'templates/global/quantity-input.php';
        
        // Use our template if it exists
        if ( file_exists( $plugin_template ) ) {
            error_log( 'WCQM_Cart::override_cart_quantity_template() - Using global template for all quantity inputs' );
            return $plugin_template;
        }
        
        return $template;
    }
    
    /**
     * Add is_cart flag to quantity input args
     *
     * @param array $args     Arguments
     * @param mixed $product  Product
     * @return array Modified arguments
     */
    public function add_cart_flag($args, $product) {
        if (is_cart() || is_checkout()) {
            $args['is_cart'] = true;
            
            // Get the product ID
            $product_id = $product ? $product->get_id() : 0;
            
            // If we have a product ID, get the correct multiple from rules manager
            if ($product_id && $this->rules_manager) {
                // Debug log the current product
                error_log("WCQM Debug: Cart - Getting multiple for product {$product_id}");
                
                // Force rules to be loaded
                $this->rules_manager->load_rules(true);
                
                // Get the correct multiple for this product - this is the key method call
                $multiple = $this->rules_manager->get_product_multiple($product_id);
                
                // Debug log the multiple retrieved
                error_log("WCQM Debug: Cart - Retrieved multiple for product {$product_id}: {$multiple}");
                
                // Set the step value to the correct multiple for this product
                $args['step'] = $multiple;
                $args['min_value'] = $multiple;
                
                // Add the multiple directly as a data attribute
                if (!isset($args['custom_attributes'])) {
                    $args['custom_attributes'] = array();
                }
                $args['custom_attributes']['data-multiple'] = $multiple;
                
                // Get any category rules that might apply to this product
                $categories = $product ? $product->get_category_ids() : array();
                
                // Log detailed info about the rules and categories
                error_log('WCQM_Cart: Product ' . $product_id . ' - Multiple: ' . $multiple . 
                          ' - Categories: ' . implode(',', $categories));
            }
        }
        
        return $args;
    }
}

// Initialize the cart class
WCQM_Cart::instance(); 