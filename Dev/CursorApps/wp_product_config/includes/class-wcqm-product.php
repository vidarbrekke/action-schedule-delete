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
if (!defined('ABSPATH')) {
    exit;
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
     * @param WCQM_Rules_Manager $rules_manager Optional. Rules manager instance.
     * @return WCQM_Product - Main instance.
     */
    public static function instance($rules_manager = null) {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($rules_manager);
        } elseif (!is_null($rules_manager)) {
            self::$_instance->rules_manager = $rules_manager;
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     *
     * @param WCQM_Rules_Manager $rules_manager Rules manager instance.
     */
    public function __construct($rules_manager = null) {
        // Store rules manager instance
        $this->rules_manager = $rules_manager;

        // Initialize after WordPress and WooCommerce are fully loaded
        if (!is_null($rules_manager)) {
            add_action('init', array($this, 'init'));
        }
    }

    /**
     * Initialize the class
     */
    public function init() {
        // Only proceed if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Modify quantity input args - use high priority (99) to run after theme modifications
        add_filter('woocommerce_quantity_input_args', array($this, 'modify_quantity_input_args'), 99, 2);
        
        // Add quantity message - only once at the top of the form
        if (function_exists('is_product') && is_product()) {
            // Use high priority (1) to ensure it's first and only once
            add_action('woocommerce_before_add_to_cart_form', array($this, 'display_quantity_message'), 1);
            
            // Remove any competing hooks that might cause duplicate messages
            remove_action('woocommerce_before_add_to_cart_quantity', array($this, 'display_quantity_message'));
            remove_action('woocommerce_before_quantity_input_field', array($this, 'display_quantity_message'));
        }
        
        // Add our JavaScript with priority 99 to run after WooCommerce scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_quantity_scripts'), 99);
        
        // Validate add to cart
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 99, 3);
        
        // Validate cart update
        add_filter('woocommerce_update_cart_validation', array($this, 'validate_cart_update'), 99, 4);
        
        // Adjust quantity before adding to cart
        add_filter('woocommerce_add_cart_item_data', array($this, 'adjust_cart_item_quantity'), 99, 3);
        
        // Additional hook to modify the HTML output of the quantity input
        add_filter('woocommerce_cart_item_quantity', array($this, 'modify_cart_item_quantity_html'), 99, 3);
        
        error_log('WCQM Debug: Product class initialized');
    }

    /**
     * Modify cart item quantity HTML output
     */
    public function modify_cart_item_quantity_html($product_quantity, $cart_item_key, $cart_item) {
        // Get the product ID
        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        
        // Get the multiple from rules manager
        $multiple = $this->rules_manager->get_product_multiple($product_id);
        
        if ($multiple > 1) {
            // Extract the input attributes using regex
            preg_match('/<input[^>]*?value="([^"]*)"[^>]*?>/', $product_quantity, $matches);
            
            if (!empty($matches)) {
                // Current value
                $current_value = isset($matches[1]) ? intval($matches[1]) : $multiple;
                
                // Ensure it's a valid multiple
                if ($current_value % $multiple !== 0) {
                    $current_value = ceil($current_value / $multiple) * $multiple;
                }
                
                // Replace the input with our custom one
                $product_quantity = str_replace(
                    'type="number"',
                    'type="text" data-quantity-multiple="' . $multiple . '"',
                    $product_quantity
                );
                
                // Replace min, max, step attributes with data attributes
                $product_quantity = preg_replace(
                    '/(min|max|step)="[^"]*"/',
                    'data-$1="$2"',
                    $product_quantity
                );
                
                error_log('WCQM Debug: Modified cart item quantity HTML for product ' . $product_id);
            }
        }
        
        return $product_quantity;
    }

    /**
     * Modify quantity input arguments for product pages
     */
    public function modify_quantity_input_args($args, $product) {
        if (!$product) {
            return $args;
        }

        try {
            // Get product ID (handle variations)
            $product_id = $product->get_id();
            if ($product->is_type('variation')) {
                $product_id = $product->get_variation_id();
            }

            // Get product categories for logging
            $categories = $product->get_category_ids();
            error_log("WCQM Debug: modify_quantity_input_args - Product {$product_id} categories: " . implode(',', $categories));
            
            // Get multiple directly from rules manager - force reload rules
            $this->rules_manager->load_rules(true);
            $multiple = $this->rules_manager->get_product_multiple($product_id);
            error_log("WCQM Debug: modify_quantity_input_args - Got multiple {$multiple} for product {$product_id}");
            
            if ($multiple > 1) {
                // Force type to be text instead of number to avoid browser validation
                $args['type'] = 'text';
                
                // Store multiple as a data attribute 
                $args['custom_attributes'] = isset($args['custom_attributes']) ? $args['custom_attributes'] : array();
                $args['custom_attributes']['data-quantity-multiple'] = $multiple;
                $args['custom_attributes']['data-product-id'] = $product_id;
                $args['custom_attributes']['inputmode'] = 'numeric';
                
                // Remove HTML5 validation attributes completely and use only data attributes
                // This prevents browser validation errors completely
                $args['custom_attributes']['data-min'] = $multiple;
                $args['custom_attributes']['data-step'] = $multiple;
                
                // Don't set actual min/max/step attributes that trigger validation
                unset($args['min_value']);
                unset($args['max_value']);
                unset($args['step']);
                
                // Set input value to ensure it's a valid multiple
                $input_value = isset($args['input_value']) ? intval($args['input_value']) : $multiple;
                if ($input_value < $multiple) {
                    $input_value = $multiple;
                } else {
                    $remainder = $input_value % $multiple;
                    if ($remainder !== 0) {
                        $input_value = $input_value + ($multiple - $remainder);
                    }
                }
                $args['input_value'] = $input_value;
                
                error_log("WCQM Debug: Set input value to {$input_value} for product {$product_id}");
            }
        } catch (Exception $e) {
            error_log("WCQM Error: " . $e->getMessage());
        }

        return $args;
    }

    /**
     * Display quantity message
     */
    public function display_quantity_message() {
        global $product;
        
        if (!$product) {
            return;
        }

        // Use global variable for cross-request tracking
        global $wcqm_message_displayed;
        
        // Initialize tracking variable if not set
        if (!isset($wcqm_message_displayed)) {
            $wcqm_message_displayed = false;
            error_log("WCQM Debug: Message tracking initialized as false");
        }
        
        // If already displayed, don't show again
        if ($wcqm_message_displayed === true) {
            error_log("WCQM Debug: Message already displayed, skipping");
            return;
        }

        $product_id = $product->get_id();
        
        // Force rules manager to load fresh rules
        $this->rules_manager->load_rules(true);
        
        // Get the multiple from the rules manager
        $multiple = $this->rules_manager->get_product_multiple($product_id);
        error_log("WCQM Debug: display_quantity_message - Got multiple {$multiple} for product {$product_id}");

        if ($multiple > 1) {
            echo '<div class="wcqm-quantity-message" style="margin-bottom: 15px; font-weight: bold; color: #333; background-color: #f8f8f8; padding: 10px; border-left: 4px solid #2271b1;">' . 
                 sprintf(esc_html__('This product must be ordered in multiples of %d', 'wc-quantity-multiples'), $multiple) . 
                 '</div>';
            
            // Mark message as displayed globally
            $wcqm_message_displayed = true;
            error_log("WCQM Debug: Displayed quantity message for product {$product_id} with multiple {$multiple} and set tracking to true");
        }
    }

    /**
     * Validate add to cart
     */
    public function validate_add_to_cart($valid, $product_id, $quantity) {
        if (!$valid) {
            return false;
        }

        error_log("WCQM Debug: Validating add to cart from product class - Product ID: {$product_id}, Quantity: {$quantity}");
        
        // Get the product from WooCommerce
        $product = wc_get_product($product_id);
        if (!$product) {
            return $valid;
        }
        
        // Force rules manager to load fresh rules
        $this->rules_manager->load_rules(true);
        
        // Get the multiple directly from rules manager for consistency
        $multiple = $this->rules_manager->get_product_multiple($product_id);
        error_log("WCQM Debug: validate_add_to_cart - Got multiple {$multiple} for product {$product_id}");
        
        // Check if quantity is valid
        if ($multiple > 1 && $quantity % $multiple !== 0) {
            // Calculate adjusted quantity
            $remainder = $quantity % $multiple;
            $adjusted_quantity = $quantity + ($multiple - $remainder);
            
            error_log("WCQM Debug: Invalid quantity {$quantity} for multiple {$multiple}, adjusted to {$adjusted_quantity}");
            
            // Add notice
            wc_add_notice(
                sprintf(
                    __('Quantity for "%s" must be in multiples of %d. We\'ve adjusted it to %d.', 'wc-quantity-multiples'),
                    $product->get_name(),
                    $multiple,
                    $adjusted_quantity
                ),
                'notice'
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

        $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
        error_log("WCQM Debug: Validating cart update from product class - Product ID: {$product_id}, Quantity: {$quantity}");
        
        // Get the product from WooCommerce
        $product = wc_get_product($product_id);
        if (!$product) {
            return $valid;
        }
        
        // Force rules manager to load fresh rules
        $this->rules_manager->load_rules(true);
        
        // Get the multiple directly from rules manager for consistency
        $multiple = $this->rules_manager->get_product_multiple($product_id);
        error_log("WCQM Debug: validate_cart_update - Got multiple {$multiple} for product {$product_id}");
        
        // Check if quantity is valid
        if ($multiple > 1 && $quantity % $multiple !== 0) {
            // Calculate adjusted quantity
            $remainder = $quantity % $multiple;
            $adjusted_quantity = $quantity + ($multiple - $remainder);
            
            error_log("WCQM Debug: validate_cart_update - Invalid quantity {$quantity} for multiple {$multiple}, adjusted to {$adjusted_quantity}");
            
            // Add notice
            wc_add_notice(
                sprintf(
                    __('Quantity for "%s" must be in multiples of %d. We\'ve adjusted it to %d.', 'wc-quantity-multiples'),
                    $product->get_name(),
                    $multiple,
                    $adjusted_quantity
                ),
                'notice'
            );
            
            return false;
        }

        return true;
    }

    /**
     * Adjust cart item quantity
     */
    public function adjust_cart_item_quantity($cart_item_data, $product_id, $variation_id) {
        // Check which product ID to use (variation or parent)
        $check_product_id = $variation_id > 0 ? $variation_id : $product_id;
        
        error_log("WCQM Debug: Adjusting cart item quantity for product {$check_product_id}");
        
        // Get the product from WooCommerce
        $product = wc_get_product($check_product_id);
        if (!$product) {
            return $cart_item_data;
        }
        
        // Force rules manager to load fresh rules
        $this->rules_manager->load_rules(true);
        
        // Get the multiple directly from rules manager for consistency
        $multiple = $this->rules_manager->get_product_multiple($check_product_id);
        error_log("WCQM Debug: adjust_cart_item_quantity - Got multiple {$multiple} for product {$check_product_id}");
        
        // Store the multiple in cart item data for later use
        $cart_item_data['quantity_multiple'] = $multiple;
        
        return $cart_item_data;
    }

    /**
     * Enqueue quantity scripts
     */
    public function enqueue_quantity_scripts() {
        // Only load on relevant pages
        if (!function_exists('is_product') || 
            (!is_product() && !is_cart() && !is_checkout())) {
            return;
        }

        // Register our script
        wp_register_script(
            'wcqm-quantity',
            WCQM_PLUGIN_URL . 'assets/js/wcqm-quantity.js',
            array('jquery', 'wc-add-to-cart-variation'),
            WCQM_VERSION,
            true
        );

        // Add CSS for better styling
        wp_add_inline_style('woocommerce-inline', "
            .wcqm-quantity-message {
                background-color: #f8f8f8;
                padding: 10px;
                border-left: 4px solid #2271b1;
                margin-bottom: 15px;
                font-weight: bold;
            }
            .quantity .qty {
                text-align: center !important;
            }
            .quantity .wcqm-btn {
                background-color: #f0f0f0;
                border: 1px solid #ddd;
                padding: 5px 10px;
                cursor: pointer;
                display: inline-block;
                vertical-align: middle;
            }
        ");

        // Enhanced quantity handling script
        // Using HEREDOC syntax to avoid issues with JavaScript variables being interpreted as PHP variables
        $script = <<<'JAVASCRIPT'
            jQuery(document).ready(function($) {
                // Remove default quantity event handlers that might conflict
                $('body').off('click', '.quantity .plus, .quantity .minus');
                
                // Intercept quantity fields on page load
                function initializeQuantityFields() {
                    $('.quantity').each(function() {
                        var $container = $(this);
                        var $input = $container.find('input.qty');
                        if ($input.length === 0 || $container.hasClass('wcqm-initialized')) {
                            return;
                        }
                        
                        // Get multiple from data attribute
                        var multiple = parseInt($input.attr('data-quantity-multiple') || '1');
                        
                        // Only proceed if it has a multiple attribute and value > 1
                        if (multiple > 1) {
                            console.log('WCQM: Initializing quantity field with multiple:', multiple);
                            
                            // Force type=text to avoid browser validation
                            $input.attr('type', 'text');
                            
                            // Add our own buttons if they don't exist
                            if ($container.find('.wcqm-btn').length === 0) {
                                $input.before('<button type="button" class="wcqm-btn wcqm-minus">-</button>');
                                $input.after('<button type="button" class="wcqm-btn wcqm-plus">+</button>');
                            }
                            
                            // Remove HTML5 validation attributes
                            $input.removeAttr('min');
                            $input.removeAttr('max');
                            $input.removeAttr('step');
                            
                            // Ensure initial value is valid
                            var currentVal = parseInt($input.val()) || multiple;
                            if (currentVal % multiple !== 0) {
                                currentVal = Math.ceil(currentVal / multiple) * multiple;
                                $input.val(currentVal);
                            }
                            
                            // Mark as initialized
                            $container.addClass('wcqm-initialized');
                            
                            console.log('WCQM: Quantity field initialized with multiple ' + multiple + ', current value: ' + currentVal);
                        }
                    });
                }
                
                // Run on page load
                initializeQuantityFields();
                
                // Also when variations change
                $(document).on('woocommerce_variation_has_changed', function() {
                    setTimeout(initializeQuantityFields, 100);
                });
                
                // Run after AJAX cart updates
                $(document.body).on('updated_cart_totals', function() {
                    initializeQuantityFields();
                });
                
                // Run after any quantity field is added dynamically
                $(document.body).on('wc_quantity_field_added', function() {
                    initializeQuantityFields();
                });
                
                // Handle our custom plus/minus buttons
                $(document).on('click', '.wcqm-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var $btn = $(this);
                    var $container = $btn.closest('.quantity');
                    var $input = $container.find('input.qty');
                    var multiple = parseInt($input.attr('data-quantity-multiple') || '1');
                    var currentVal = parseInt($input.val()) || multiple;
                    
                    console.log('WCQM: Button clicked, current value: ' + currentVal + ', multiple: ' + multiple);
                    
                    // Adjust value by multiple
                    if ($btn.hasClass('wcqm-plus')) {
                        $input.val(currentVal + multiple);
                        console.log('WCQM: Increasing to: ' + (currentVal + multiple));
                    } else if (currentVal > multiple) {
                        $input.val(currentVal - multiple);
                        console.log('WCQM: Decreasing to: ' + (currentVal - multiple));
                    }
                    
                    // Trigger change event for WooCommerce
                    $input.trigger('change');
                });
                
                // Disable default WooCommerce quantity buttons
                $(document).on('click', '.quantity .plus, .quantity .minus', function(e) {
                    var $container = $(this).closest('.quantity');
                    var $input = $container.find('input.qty');
                    var multiple = parseInt($input.attr('data-quantity-multiple') || '1');
                    
                    // Only prevent default if we're managing this input
                    if (multiple > 1 && $container.hasClass('wcqm-initialized')) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('WCQM: Prevented default quantity button action');
                    }
                });
                
                // Handle direct input changes
                $(document).on('change', 'input.qty', function() {
                    var $input = $(this);
                    var multiple = parseInt($input.attr('data-quantity-multiple') || '1');
                    
                    if (multiple > 1) {
                        var currentVal = parseInt($input.val()) || 0;
                        var newVal = currentVal;
                        
                        if (currentVal < multiple) {
                            newVal = multiple;
                        } else if (currentVal % multiple !== 0) {
                            newVal = Math.ceil(currentVal / multiple) * multiple;
                        }
                        
                        if (newVal !== currentVal) {
                            console.log('WCQM: Adjusting input value from ' + currentVal + ' to ' + newVal);
                            $input.val(newVal);
                        }
                    }
                });
                
                // Final validation before form submission
                $(document).on('submit', 'form.cart, form.woocommerce-cart-form', function(e) {
                    var $form = $(this);
                    var hasInvalidValues = false;
                    
                    $form.find('input.qty').each(function() {
                        var $input = $(this);
                        var multiple = parseInt($input.attr('data-quantity-multiple') || '1');
                        
                        if (multiple > 1) {
                            var currentVal = parseInt($input.val()) || 0;
                            var newVal = currentVal;
                            
                            if (currentVal < multiple) {
                                newVal = multiple;
                                hasInvalidValues = true;
                            } else if (currentVal % multiple !== 0) {
                                newVal = Math.ceil(currentVal / multiple) * multiple;
                                hasInvalidValues = true;
                            }
                            
                            if (newVal !== currentVal) {
                                console.log('WCQM: Form submission - adjusting value from ' + currentVal + ' to ' + newVal);
                                $input.val(newVal);
                            }
                        }
                    });
                    
                    if (hasInvalidValues) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Show message but allow form to continue after adjustment
                        alert('Some quantities have been adjusted to valid multiples.');
                        
                        // Submit form after notification
                        setTimeout(function() {
                            $form.submit();
                        }, 100);
                    }
                });
            });
JAVASCRIPT;

        // Enqueue script and add inline script
        wp_enqueue_script('wcqm-quantity');
        wp_add_inline_script('wcqm-quantity', $script);
    }
} 