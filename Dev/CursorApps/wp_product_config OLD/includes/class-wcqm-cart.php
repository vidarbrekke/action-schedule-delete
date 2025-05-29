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
if (!defined('ABSPATH')) {
    exit;
}

// Required WordPress files
require_once(ABSPATH . 'wp-includes/functions.php');
require_once(ABSPATH . 'wp-includes/plugin.php');
require_once(ABSPATH . 'wp-includes/l10n.php');

// Required WooCommerce files
if (!class_exists('WooCommerce')) {
    return;
}

// Ensure WooCommerce functions are available
if (!function_exists('WC')) {
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
     * Flag to prevent infinite loops during validation.
     *
     * @var bool
     */
    private $is_validating = false;

    /**
     * Flag to prevent infinite loops during cart validation
     *
     * @var bool
     */
    private $is_updating = false;

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
        // Get rules manager instance
        $this->rules_manager = WCQM_Rules_Manager::instance();

        // Add to cart validation - highest priority to run first
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart_quantity'), 5, 5);
        
        // Cart update validation - high priority
        add_filter('woocommerce_update_cart_validation', array($this, 'validate_cart_item_quantity'), 5, 4);
        
        // Ensure quantities are correct during cart calculations
        add_action('woocommerce_before_calculate_totals', array($this, 'adjust_cart_quantities'), 10, 1);
        
        // Store multiple in cart item data
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_quantity_multiple_to_cart_item'), 10, 3);
        
        // Set quantity input attributes
        add_filter('woocommerce_quantity_input_args', array($this, 'set_quantity_input_args'), 10, 2);
        
        // Display notices about quantity requirements
        add_action('woocommerce_before_add_to_cart_button', array($this, 'display_quantity_notice'));
        add_action('woocommerce_before_cart', array($this, 'display_cart_quantity_notices'));
    }

    /**
     * Get the correct multiple for a product
     *
     * @param int $product_id Product ID
     * @return int Multiple value
     */
    private function get_product_multiple($product_id) {
        // Get from rules manager
        $multiple = $this->rules_manager->get_product_multiple($product_id);
        error_log(sprintf('WCQM Cart: Product %d using rules manager multiple: %d', $product_id, $multiple));

        // Ensure valid multiple
        return $multiple > 0 ? $multiple : 1;
    }

    /**
     * Set quantity input arguments
     *
     * @param array $args Arguments
     * @param WC_Product|null $product Product object
     * @return array Modified arguments
     */
    public function set_quantity_input_args($args, $product) {
        if (!$product) {
            return $args;
        }

        $product_id = $product->get_id();
        $multiple = $this->get_product_multiple($product_id);

        // Set step and min values
        $args['step'] = $multiple;
        $args['min_value'] = $multiple;
        
        // Ensure input value is valid
        if (isset($args['input_value'])) {
            $args['input_value'] = $this->adjust_to_valid_multiple($args['input_value'], $multiple);
        }

        return $args;
    }

    /**
     * Display quantity notice on product page
     */
    public function display_quantity_notice() {
        global $product;
        if (!$product) {
            return;
        }

        $multiple = $this->get_product_multiple($product->get_id());
        if ($multiple > 1) {
            echo '<div class="wcqm-quantity-notice">';
            echo sprintf(
                __('This product must be ordered in multiples of %d.', 'wc-quantity-multiples'),
                $multiple
            );
            echo '</div>';
        }
    }

    /**
     * Display quantity notices in cart
     */
    public function display_cart_quantity_notices() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $notices = array();
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
            $multiple = $this->get_product_multiple($product_id);
            
            if ($multiple > 1) {
                $product = wc_get_product($product_id);
                $product_name = $product ? $product->get_name() : sprintf(__('Product #%d', 'wc-quantity-multiples'), $product_id);
                
                $notices[] = sprintf(
                    __('"%s" must be ordered in multiples of %d.', 'wc-quantity-multiples'),
                    $product_name,
                    $multiple
                );
            }
        }

        if (!empty($notices)) {
            echo '<div class="woocommerce-info wcqm-cart-notices">';
            echo implode('<br>', array_unique($notices));
            echo '</div>';
        }
    }

    /**
     * Add quantity multiple to cart item data
     *
     * @param array $cart_item_data Cart item data
     * @param int   $product_id     Product ID
     * @param int   $variation_id   Variation ID
     * @return array Modified cart item data
     */
    public function add_quantity_multiple_to_cart_item($cart_item_data, $product_id, $variation_id) {
        $check_product_id = $variation_id > 0 ? $variation_id : $product_id;
        $multiple = $this->get_product_multiple($check_product_id);
        
        if ($multiple > 1) {
            $cart_item_data['quantity_multiple'] = $multiple;
        }
        
        return $cart_item_data;
    }

    /**
     * Validate quantity when adding to cart
     *
     * @param bool   $valid       Whether the item can be added to cart
     * @param int    $product_id  Product ID
     * @param int    $quantity    Quantity
     * @param int    $variation_id Variation ID
     * @param array  $variations  Variation data
     * @return bool Whether the quantity is valid
     */
    public function validate_add_to_cart_quantity($valid, $product_id, $quantity, $variation_id = 0, $variations = array()) {
        if (!$valid || $quantity <= 0) {
            return $valid;
        }

        $check_product_id = $variation_id > 0 ? $variation_id : $product_id;
        
        // Get product from WooCommerce
        $product = wc_get_product($check_product_id);
        if (!$product) {
            return $valid;
        }
        
        // Important: Use the rules manager's get_product_multiple directly 
        // WITHOUT going through the category rule resolution again
        // to ensure consistency with the product page
        $multiple = $this->rules_manager->get_product_multiple($check_product_id);
        
        error_log("WCQM Debug: validate_add_to_cart_quantity - Using multiple {$multiple} for product {$check_product_id}");
        
        // Check if quantity is a valid multiple
        if ($multiple > 1 && $quantity % $multiple !== 0) {
            $adjusted_quantity = $this->adjust_to_valid_multiple($quantity, $multiple);
            
            $product_name = $product->get_name();
            
            wc_add_notice(
                sprintf(
                    __('Quantity for "%s" must be in multiples of %d. We\'ve adjusted it to %d.', 'wc-quantity-multiples'),
                    $product_name,
                    $multiple,
                    $adjusted_quantity
                ),
                'notice'
            );

            // Add to cart with adjusted quantity
            if (!$this->is_updating) {
                $this->is_updating = true;
                WC()->cart->add_to_cart($product_id, $adjusted_quantity, $variation_id, $variations);
                $this->is_updating = false;
            }
            
            return false;
        }

        return true;
    }

    /**
     * Validate cart item quantity against rules
     *
     * @param bool   $valid          Current validation state
     * @param string $cart_item_key  Cart item key
     * @param array  $cart_item      Cart item data
     * @param int    $quantity       New quantity
     * @return bool
     */
    public function validate_cart_item_quantity($valid, $cart_item_key, $cart_item, $quantity) {
        // Prevent infinite loops
        if ($this->is_validating) {
            return $valid;
        }
        $this->is_validating = true;

        try {
            $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
            
            // Get the required multiple from rules manager
            $multiple = $this->rules_manager->get_product_multiple($product_id);
            
            error_log("WCQM Debug: Validating cart item - Product: {$product_id}, Multiple: {$multiple}, Quantity: {$quantity}");
            
            // Skip validation if no multiple is set
            if (!$multiple || $multiple <= 0) {
                $this->is_validating = false;
                return $valid;
            }

            // Check if quantity is a valid multiple
            if ($quantity % $multiple !== 0) {
                // Calculate the nearest valid multiple
                $adjusted_quantity = $this->adjust_to_valid_multiple($quantity, $multiple);
                
                // Add notice about quantity adjustment
                $product_name = $cart_item['data']->get_name();
                
                // Only update if we're not already updating
                if (!$this->is_updating) {
                    $this->is_updating = true;
                    WC()->cart->set_quantity($cart_item_key, $adjusted_quantity, false);
                    $this->is_updating = false;
                }
                
                wc_add_notice(sprintf(
                    __('%s: Quantity %d must be a multiple of %d', 'wc-quantity-multiples'),
                    $product_name,
                    $quantity,
                    $multiple
                ), 'error');
                
                $valid = false;
            }
        } catch (Exception $e) {
            error_log('WCQM Error in validate_cart_item_quantity: ' . $e->getMessage());
        }

        $this->is_validating = false;
        return $valid;
    }

    /**
     * Adjust quantity to nearest valid multiple
     *
     * @param int $quantity The current quantity
     * @param int $multiple The required multiple
     * @return int
     */
    private function adjust_to_valid_multiple($quantity, $multiple) {
        if ($multiple <= 0) {
            return $quantity;
        }
        
        error_log("WCQM Debug: Adjusting quantity {$quantity} to multiple of {$multiple}");
        
        // Calculate the nearest multiple (round up)
        $remainder = $quantity % $multiple;
        if ($remainder === 0) {
            return $quantity;
        }
        
        $adjusted = $quantity + ($multiple - $remainder);
        error_log("WCQM Debug: Adjusted quantity from {$quantity} to {$adjusted}");
        return $adjusted;
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

    /**
     * Filter the cart item quantity field to enforce multiples.
     *
     * @param string $product_quantity HTML of quantity input.
     * @param string $cart_item_key    Cart item key.
     * @param array  $cart_item        Cart item data.
     * @return string Modified quantity input HTML.
     */
    public function cart_item_quantity_field($product_quantity, $cart_item_key, $cart_item) {
        // Bail if we're not in the cart or checkout
        if (!is_cart() && !is_checkout()) {
            return $product_quantity;
        }
        
        // Get product ID from cart item
        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        
        // Check if we have a stored multiple value in the cart item data
        if (isset($cart_item['quantity_multiple'])) {
            $multiple = intval($cart_item['quantity_multiple']);
        } else {
            // Get the appropriate multiple
            $multiple = $this->rules_manager->get_product_multiple($product_id);
        }
        
        error_log(sprintf('WCQM Cart: Customizing quantity field for product %d with multiple %d', $product_id, $multiple));
        
        // If WooCommerce is using our template already, we just need to ensure data attributes are present
        if (strpos($product_quantity, 'data-multiple') !== false) {
            // Already using our custom template with data attributes
            $product_quantity = str_replace(
                array('step="1"', 'data-multiple="1"'),
                array('step="' . esc_attr($multiple) . '"', 'data-multiple="' . esc_attr($multiple) . '"'),
                $product_quantity
            );
            
            return $product_quantity;
        }
        
        // Extract the input value from the original quantity field
        $value = 0;
        if (preg_match('/value="([0-9]+)"/', $product_quantity, $matches)) {
            $value = intval($matches[1]);
        }
        
        // Ensure value is a valid multiple
        if ($value % $multiple !== 0) {
            $value = $this->adjust_to_valid_multiple($value, $multiple);
        }
        
        // Custom template path
        $template_path = 'templates/cart/quantity-input.php';
        $plugin_template = WCQM_PLUGIN_DIR . $template_path;
        
        // Check if template exists
        if (file_exists($plugin_template)) {
            ob_start();
            
            // Include template with necessary variables
            include $plugin_template;
            
            $product_quantity = ob_get_clean();
        }
        
        return $product_quantity;
    }

    /**
     * Add quantity multiple notice in cart after item name.
     *
     * @param array $cart_item Cart item data.
     * @param string $cart_item_key Cart item key.
     */
    public function cart_item_quantity_notice($cart_item, $cart_item_key) {
        // Get product ID from cart item
        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        
        // Check if we have a stored multiple value in the cart item data
        if (isset($cart_item['quantity_multiple'])) {
            $multiple = intval($cart_item['quantity_multiple']);
        } else {
            // Get the appropriate multiple
            $multiple = $this->rules_manager->get_product_multiple($product_id);
        }
        
        // If multiple is 1, no need to show notice
        if ($multiple <= 1) {
            return;
        }
        
        // Add discreet notice about quantity multiple
        echo '<div class="wcqm-cart-notice" style="font-size: 0.85em; opacity: 0.8;">';
        echo sprintf(
            __('This product must be ordered in multiples of %d.', 'wc-quantity-multiples'),
            $multiple
        );
        echo '</div>';
    }

    /**
     * Handle cart item restored from session.
     *
     * @param string $cart_item_key Cart item key.
     * @param WC_Cart $cart Cart object.
     */
    public function cart_item_restored($cart_item_key, $cart) {
        if (!isset($cart->cart_contents[$cart_item_key])) {
            return;
        }

        $cart_item = $cart->cart_contents[$cart_item_key];
        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        $quantity = $cart_item['quantity'];
        
        // Check if we have a stored multiple value in the cart item data
        if (isset($cart_item['quantity_multiple'])) {
            $multiple = intval($cart_item['quantity_multiple']);
        } else {
            // Get the appropriate multiple
            $multiple = $this->rules_manager->get_product_multiple($product_id);
            
            // Store in cart item data
            $cart->cart_contents[$cart_item_key]['quantity_multiple'] = $multiple;
        }
        
        // Check if the quantity is a valid multiple
        if ($quantity % $multiple !== 0) {
            $adjusted_quantity = $this->adjust_to_valid_multiple($quantity, $multiple);
            
            // Update cart item quantity
            $cart->set_quantity($cart_item_key, $adjusted_quantity, false);
        }
    }

    /**
     * Adjust cart quantities to match multiples during cart calculation.
     *
     * @param WC_Cart $cart Cart object.
     */
    public function adjust_cart_quantities($cart) {
        if ($this->is_updating || is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $this->is_updating = true;
        
        try {
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
                $quantity = $cart_item['quantity'];
                $multiple = $this->rules_manager->get_product_multiple($product_id);
                
                error_log("WCQM Debug: Checking cart item - Product: {$product_id}, Multiple: {$multiple}, Current: {$quantity}");
                
                if ($quantity % $multiple !== 0) {
                    $adjusted_quantity = $this->adjust_to_valid_multiple($quantity, $multiple);
                    error_log("WCQM Debug: Adjusting quantity from {$quantity} to {$adjusted_quantity}");
                    $cart->set_quantity($cart_item_key, $adjusted_quantity, false);
                }
            }
        } catch (Exception $e) {
            error_log('WCQM Error in adjust_cart_quantities: ' . $e->getMessage());
        }
        
        $this->is_updating = false;
    }

    /**
     * Display quantity multiple in cart item meta.
     *
     * @param array $item_data Item data.
     * @param array $cart_item Cart item.
     * @return array Modified item data.
     */
    public function display_quantity_multiple_in_cart($item_data, $cart_item) {
        // Don't add this meta on the cart page, only on checkout
        if (is_cart()) {
            return $item_data;
        }
        
        // Check if multiple is stored and worth showing (greater than 1)
        if (isset($cart_item['quantity_multiple']) && $cart_item['quantity_multiple'] > 1) {
            $item_data[] = array(
                'key' => __('Quantity Multiple', 'wc-quantity-multiples'),
                'value' => $cart_item['quantity_multiple'],
                'display' => $cart_item['quantity_multiple'],
            );
        }
        
        return $item_data;
    }
}

// Initialize the cart class
WCQM_Cart::instance(); 