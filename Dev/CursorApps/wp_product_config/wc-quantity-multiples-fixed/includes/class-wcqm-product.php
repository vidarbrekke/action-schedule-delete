<?php
/**
 * WC Quantity Multiples - Product
 * 
 * Handles the product quantity modifications on product pages.
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
    ! function_exists( 'update_option' ) || 
    ! function_exists( 'is_woocommerce' ) ||
    ! function_exists( 'is_cart' ) ||
    ! function_exists( 'is_checkout' ) ||
    ! function_exists( 'is_product' ) ||
    ! function_exists( 'is_shop' ) ) {
    return;
}

/**
 * WCQM_Product Class
 * 
 * Handles product-specific quantity multiple functionality.
 */
class WCQM_Product {
    /**
     * The single instance of the class.
     *
     * @var WCQM_Product
     */
    protected static $_instance = null;

    /**
     * Settings options array.
     *
     * @var array
     */
    protected $options;

    /**
     * Default multiple value.
     *
     * @var int
     */
    protected $default_multiple = 10;
    
    /**
     * Flag to track if we're currently processing a cart update
     *
     * @var bool
     */
    protected $processing_cart_update = false;
    
    /**
     * Store original cart quantities to detect which item was changed
     *
     * @var array
     */
    protected $original_cart_quantities = array();

    /**
     * Rules manager instance.
     *
     * @var WCQM_Rules_Manager
     */
    protected $rules_manager;

    /**
     * Cached rules storage
     *
     * @var array
     */
    protected $cached_rules = null;

    /**
     * Static cache for enforcement decisions
     *
     * @var array
     */
    protected static $enforcement_cache = [];

    /**
     * Flag to track if we should apply to all products
     *
     * @var bool
     */
    protected $apply_to_all_products = false;

    /**
     * Main Product Instance.
     *
     * @return WCQM_Product - Main instance.
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

        // Modify quantity input args
        add_filter('woocommerce_quantity_input_args', array($this, 'modify_quantity_input_args'), 10, 2);
        
        // Validate add to cart
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        
        // Validate cart update
        add_filter('woocommerce_update_cart_validation', array($this, 'validate_cart_update'), 10, 4);
        
        // Adjust quantity before adding to cart
        add_filter('woocommerce_add_cart_item_data', array($this, 'adjust_cart_item_quantity'), 10, 3);
        
        // Add quantity validation message
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_quantity_validation_message'));
        
        // Load our custom quantity template
        add_filter('woocommerce_locate_template', array($this, 'get_quantity_template'), 10, 3);
        
        error_log('WCQM Debug: Product class initialized');
    }

    /**
     * Modify quantity input arguments
     */
    public function modify_quantity_input_args($args, $product) {
        if (!$product) {
            return $args;
        }

        $product_id = $product->get_id();
        $multiple = $this->rules_manager->get_product_multiple($product_id);

        // Set minimum quantity to multiple
        $args['min_value'] = $multiple;
        
        // Set step to multiple
        $args['step'] = $multiple;
        
        // Set default value to multiple if not set
        if (!isset($args['input_value']) || $args['input_value'] < $multiple) {
            $args['input_value'] = $multiple;
        }

        // Add data attributes for JavaScript
        $args['custom_attributes'] = array_merge(
            isset($args['custom_attributes']) ? $args['custom_attributes'] : array(),
            array(
                'data-multiple' => $multiple,
                'data-product-id' => $product_id
            )
        );

        return $args;
    }

    /**
     * Validate add to cart
     */
    public function validate_add_to_cart($valid, $product_id, $quantity) {
        if (!$valid) {
            return false;
        }

        if (!$this->rules_manager->validate_quantity($product_id, $quantity)) {
            $product = wc_get_product($product_id);
            $multiple = $this->rules_manager->get_product_multiple($product_id);
            
            wc_add_notice(
                sprintf(
                    __('Quantity must be a multiple of %d for %s.', 'wc-quantity-multiples'),
                    $multiple,
                    $product->get_name()
                ),
                'error'
            );
            
            return false;
        }

        return true;
    }

    /**
     * Validate cart update
     */
    public function validate_cart_update($valid, $cart_item_key, $values, $quantity) {
        if (!$valid) {
            return false;
        }

        $cart = WC()->cart;
        $cart_item = $cart->get_cart_item($cart_item_key);
        
        if (!$cart_item) {
            return false;
        }

        if (!$this->rules_manager->validate_quantity($cart_item['product_id'], $quantity)) {
            $product = wc_get_product($cart_item['product_id']);
            $multiple = $this->rules_manager->get_product_multiple($cart_item['product_id']);
            
            wc_add_notice(
                sprintf(
                    __('Quantity must be a multiple of %d for %s.', 'wc-quantity-multiples'),
                    $multiple,
                    $product->get_name()
                ),
                'error'
            );
            
            return false;
        }

        return true;
    }

    /**
     * Adjust quantity before adding to cart
     */
    public function adjust_cart_item_quantity($cart_item_data, $product_id, $variation_id) {
        if (isset($cart_item_data['quantity'])) {
            $cart_item_data['quantity'] = $this->rules_manager->adjust_quantity(
                $product_id,
                $cart_item_data['quantity']
            );
        }
        return $cart_item_data;
    }

    /**
     * Add quantity validation message
     */
    public function add_quantity_validation_message() {
        // This method has been intentionally disabled to prevent showing validation messages
        // The validation still happens, but we don't display a message to the user
        return;
    }

    /**
     * Get the template for the quantity field
     */
    public function get_quantity_template($template, $template_name, $template_path) {
        // Only override quantity-input.php template
        if ('global/quantity-input.php' !== $template_name) {
            return $template;
        }
        
        // Path to our custom template
        $plugin_template = WCQM_PLUGIN_DIR . 'templates/global/quantity-input.php';
        
        // Use our template if it exists
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return $template;
    }
}

// Initialize the product class - only once!
WCQM_Product::instance(); 