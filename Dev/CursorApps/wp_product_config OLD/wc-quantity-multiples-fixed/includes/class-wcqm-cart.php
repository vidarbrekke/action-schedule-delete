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

// Ensure WordPress core functions are available
require_once(ABSPATH . 'wp-includes/plugin.php');
require_once(ABSPATH . 'wp-includes/functions.php');

// Ensure WooCommerce is active and available
if ( ! class_exists( 'WooCommerce' ) ) {
    return;
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
        
        // Initialize early to ensure our data is available during cart rendering
        add_action('woocommerce_before_cart', array($this, 'initialize_cart_rules'));
        
        // Modify cart item data when first added to cart
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        
        // Ensure our data persists when cart is loaded from session
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        
        // Modify quantity input args before rendering
        add_filter('woocommerce_quantity_input_args', array($this, 'inject_quantity_args'), 5, 2);
        
        // Final modification of the quantity HTML
        add_filter('woocommerce_cart_item_quantity', array($this, 'modify_cart_item_quantity'), 5, 3);
        
        error_log('WCQM Debug: Cart class initialized with enhanced hooks');
    }

    /**
     * Initialize cart rules early
     */
    public function initialize_cart_rules() {
        // Force rules to load/reload
        $this->rules_manager->load_rules();
        error_log('WCQM Debug: Cart rules initialized early');
    }

    /**
     * Add our quantity multiple data when item is added to cart
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        $actual_id = $variation_id ? $variation_id : $product_id;
        
        // Get the multiple directly from rules manager
        $multiple = $this->rules_manager->get_product_multiple($actual_id);
        
        // Store the multiple in cart item data
        if (!is_array($cart_item_data)) {
            $cart_item_data = array();
        }
        $cart_item_data['wcqm_multiple'] = $multiple;
        
        error_log("WCQM Debug: Adding cart item data for product {$actual_id} - Multiple: {$multiple}");
        
        return $cart_item_data;
    }

    /**
     * Ensure our data persists when cart is loaded from session
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['wcqm_multiple'])) {
            $cart_item['wcqm_multiple'] = $values['wcqm_multiple'];
            error_log("WCQM Debug: Restored multiple {$values['wcqm_multiple']} from session for product {$cart_item['product_id']}");
        } else {
            // If not in session, recalculate
            $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
            $multiple = $this->rules_manager->get_product_multiple($product_id);
            $cart_item['wcqm_multiple'] = $multiple;
            error_log("WCQM Debug: Recalculated multiple {$multiple} for product {$product_id}");
        }
        return $cart_item;
    }

    /**
     * Inject quantity arguments for cart items
     */
    public function inject_quantity_args($args, $product) {
        if (!$product) {
            return $args;
        }
        
        $product_id = $product->get_id();
        
        // Check if we're in cart context
        if (is_cart() && isset($GLOBALS['woocommerce']->cart)) {
            foreach ($GLOBALS['woocommerce']->cart->get_cart() as $cart_item) {
                if ($cart_item['product_id'] == $product_id || 
                    (!empty($cart_item['variation_id']) && $cart_item['variation_id'] == $product_id)) {
                    
                    // Get multiple from cart item data or recalculate if needed
                    $multiple = isset($cart_item['wcqm_multiple']) ? $cart_item['wcqm_multiple'] : $this->rules_manager->get_product_multiple($product_id);
                    
                    error_log("WCQM Debug: Using multiple {$multiple} for cart product {$product_id}");
                    
                    // Update quantity input arguments
                    $args['step'] = $multiple;
                    $args['min_value'] = $multiple;
                    $args['product_id'] = $product_id;
                    
                    // Ensure input value is valid
                    $args['input_value'] = max($multiple, isset($args['input_value']) ? $args['input_value'] : $multiple);
                    if ($args['input_value'] % $multiple !== 0) {
                        $args['input_value'] = ceil($args['input_value'] / $multiple) * $multiple;
                    }
                    
                    // Add data attributes for JavaScript
                    if (!isset($args['custom_attributes'])) {
                        $args['custom_attributes'] = array();
                    }
                    $args['custom_attributes']['data-multiple'] = $multiple;
                    $args['custom_attributes']['data-enforce-multiple'] = 'yes';
                    
                    break;
                }
            }
        }
        
        return $args;
    }

    /**
     * Final modification of cart item quantity HTML
     */
    public function modify_cart_item_quantity($product_quantity, $cart_item_key, $cart_item) {
        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        
        // Get multiple from cart item data or recalculate
        $multiple = isset($cart_item['wcqm_multiple']) ? $cart_item['wcqm_multiple'] : $this->rules_manager->get_product_multiple($product_id);
        
        error_log("WCQM Debug: Modifying cart quantity HTML for product {$product_id} - Multiple: {$multiple}");
        
        // Parse the quantity input HTML
        if (preg_match('/<input[^>]*>/', $product_quantity, $matches)) {
            $input = $matches[0];
            
            // Add our data attributes
            $input = str_replace('class="', 'data-multiple="' . esc_attr($multiple) . '" data-enforce-multiple="yes" class="', $input);
            
            // Replace the original input
            $product_quantity = str_replace($matches[0], $input, $product_quantity);
        }
        
        return $product_quantity;
    }
}

// Initialize the cart class
WCQM_Cart::instance(); 