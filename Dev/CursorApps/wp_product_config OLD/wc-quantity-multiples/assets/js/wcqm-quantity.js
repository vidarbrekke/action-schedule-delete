/**
 * WC Quantity Multiples - Quantity JavaScript
 * 
 * Handles the quantity input enforcement on the frontend.
 *
 * @package WC_Quantity_Multiples
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    // Debug mode
    var DEBUG = true;
    
    // Store product-specific rules locally for caching
    var productRules = {};
    var defaultMultiple = 10;
    var globalRules = null;
    
    // Log helper function
    function log(message, data) {
        if (!DEBUG) return;
        if (data) {
            console.log('WCQM: ' + message, data);
        } else {
            console.log('WCQM: ' + message);
        }
    }
    
    // CRITICAL FIX: Load rules immediately when script loads
    if (typeof window.wcqm_rules !== 'undefined') {
        globalRules = window.wcqm_rules;
        log('Loaded global rules on script load', globalRules);
        
        // Set default multiple immediately
        if (globalRules.default) {
            defaultMultiple = parseInt(globalRules.default);
            log('Set default multiple to: ' + defaultMultiple);
        }
    }
    
    $(document).ready(function() {
        log('Initializing quantity multiples');
        
        // Force rules to load again in case they weren't loaded earlier
        loadGlobalRules();
        
        // Initialize controls with a slight delay to ensure DOM is ready
        setTimeout(function() {
            initializeQuantityControls();
            enforceAllQuantityRules();
        }, 100);
        
        // Handle variation changes
        $(document).on('show_variation', '.variations_form', handleVariationChange);
        $(document).on('hide_variation', '.variations_form', handleVariationReset);
        
        // Re-initialize on AJAX events with delay
        $(document.body).on('updated_cart_totals updated_checkout', function() {
            log('Cart/checkout updated - reinitializing controls');
            setTimeout(function() {
                initializeQuantityControls();
                enforceAllQuantityRules();
            }, 100);
        });
        
        // Add mutation observer
        setupMutationObserver();
    });
    
    /**
     * Load global rules data from the page
     */
    function loadGlobalRules() {
        // Check if the wcqm_rules global variable exists
        if (typeof window.wcqm_rules !== 'undefined') {
            globalRules = window.wcqm_rules;
            log('Loaded/reloaded global rules data', globalRules);
            
            // CRITICAL FIX: Always update default multiple when rules are loaded
            if (globalRules.default) {
                defaultMultiple = parseInt(globalRules.default);
                log('Updated default multiple to: ' + defaultMultiple);
            }
            
            // Pre-cache product rules
            if (globalRules.products) {
                productRules = {}; // Clear existing cache
                for (var productId in globalRules.products) {
                    if (globalRules.products.hasOwnProperty(productId)) {
                        productRules[productId] = parseInt(globalRules.products[productId]);
                    }
                }
                log('Cached product rules', productRules);
            }
        } else {
            log('WARNING: Global rules data not found!');
        }
    }
    
    /**
     * Setup mutation observer to catch dynamic DOM changes
     */
    function setupMutationObserver() {
        if (!window.MutationObserver) {
            log('MutationObserver not supported');
            return;
        }
        
        var observer = new MutationObserver(function(mutations) {
            var shouldReinitialize = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    // Check if any quantity inputs were added
                    for (var i = 0; i < mutation.addedNodes.length; i++) {
                        var node = mutation.addedNodes[i];
                        if (node.nodeType === 1) { // Element node
                            if ($(node).find('.quantity input.qty').length > 0 || 
                                ($(node).is('.quantity') && $(node).find('input.qty').length > 0)) {
                                shouldReinitialize = true;
                                break;
                            }
                        }
                    }
                }
            });
            
            if (shouldReinitialize) {
                log('DOM mutations detected, reinitializing controls');
                initializeQuantityControls();
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        log('Mutation observer set up');
    }
    
    /**
     * Initialize all quantity controls
     */
    function initializeQuantityControls() {
        log('Initializing all quantity controls');
        
        // Query all quantity inputs
        $('.quantity input.qty').each(function() {
            var $input = $(this);
            
            // Get product ID with multiple fallbacks
            var productId = getProductIdForInput($input);
            
            if (productId) {
                log('Found quantity input for product ' + productId);
                
                // Enforce rules for this input using server-provided data
                enforceRulesOnInput($input, productId);
            } else {
                log('Could not determine product ID for input', $input);
                
                // Still apply default rules
                enforceRulesOnInput($input);
            }
        });
        
        // Handle plus/minus buttons
        $('.quantity .plus, .quantity .minus').off('click.wcqm').on('click.wcqm', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $input = $button.closest('.quantity').find('input.qty');
            var productId = getProductIdForInput($input);
            
            // Get the correct multiple
            var multiple = getMultipleForProduct(productId);
            log('Button click using multiple: ' + multiple);
            
            var min = multiple; // Minimum is always at least one multiple
            var max = parseInt($input.attr('max')) || 0;
            var currentVal = parseFloat($input.val()) || min;
            
            // Calculate new value
            var newVal;
            if ($button.hasClass('plus')) {
                newVal = currentVal + multiple;
                log('Plus button: ' + currentVal + ' + ' + multiple + ' = ' + newVal);
            } else {
                newVal = currentVal - multiple;
                log('Minus button: ' + currentVal + ' - ' + multiple + ' = ' + newVal);
            }
            
            // Enforce constraints
            if (newVal < min) {
                newVal = min;
            }
            if (max > 0 && newVal > max) {
                newVal = max;
            }
            
            // Update value and trigger change
            $input.val(newVal).trigger('change');
        });
    }
    
    /**
     * Get product ID for an input with multiple fallbacks
     */
    function getProductIdForInput($input) {
        // Try data attributes first
        var productId = $input.data('product-id') || 
                        $input.attr('data-product-id');
        
        // If not found, try various DOM traversal techniques
        if (!productId) {
            // Look for product_id in a parent form
            productId = $input.closest('form.cart, form.woocommerce-cart-form').find('input[name="product_id"], button[data-product_id]').val();
            
            // Look for add-to-cart links nearby
            if (!productId) {
                productId = $input.closest('.product').find('.add_to_cart_button').data('product_id');
            }
            
            // If in cart, try to get from cart item
            if (!productId) {
                productId = $input.closest('.cart_item, .woocommerce-cart-form__cart-item').find('a.remove').data('product_id');
            }
            
            // Try to get from URL if we're on a product page (last resort)
            if (!productId && globalRules && globalRules.current_product_id) {
                productId = globalRules.current_product_id;
            }
        }
        
        return productId ? parseInt(productId) : null;
    }
    
    /**
     * Enforce all product rules by directly manipulating the DOM
     */
    function enforceAllQuantityRules() {
        log('Enforcing all quantity rules by direct DOM manipulation');
        
        // Force rule enforcement for all quantity inputs
        $('.quantity input.qty').each(function() {
            var $input = $(this);
            var productId = getProductIdForInput($input);
            enforceRulesOnInput($input, productId, true); // force=true
        });
    }
    
    /**
     * Enforce rules on a specific input
     */
    function enforceRulesOnInput($input, productId, force) {
        if (!productId) {
            productId = getProductIdForInput($input);
        }
        
        var multiple = getMultipleForProduct(productId);
        
        log('Enforcing rules on input' + (productId ? ' for product ' + productId : '') + ' with multiple ' + multiple);
        
        // Force input attributes - this is the key to making it work!
        $input.attr('step', multiple);
        $input.attr('min', multiple);
        $input.attr('data-multiple', multiple);
        $input.attr('data-enforce-multiple', 'yes');
        $input.attr('data-product-id', productId || '');
        
        // Mark if this has a specific rule from our server-side data
        var hasSpecificRule = productId && (
            (globalRules && globalRules.products && globalRules.products[productId]) || 
            productRules[productId]
        );
        
        $input.attr('data-has-product-rule', hasSpecificRule ? 'yes' : 'no');
        
        // Handle value correction
        var currentVal = parseFloat($input.val()) || multiple;
        if (force || currentVal % multiple !== 0) {
            var correctedVal = enforceMultiple(currentVal, multiple);
            if (correctedVal !== currentVal) {
                log('Correcting value from ' + currentVal + ' to ' + correctedVal);
                $input.val(correctedVal);
            }
        }
        
        // Add event handlers
        $input.off('change.wcqm input.wcqm').on('change.wcqm input.wcqm', function() {
            var val = parseFloat($(this).val()) || multiple;
            var correctedVal = enforceMultiple(val, multiple);
            
            if (val !== correctedVal) {
                log('Input changed - correcting from ' + val + ' to ' + correctedVal);
                $(this).val(correctedVal);
            }
        });
    }
    
    /**
     * Get the appropriate multiple for a product
     */
    function getMultipleForProduct(productId) {
        // CRITICAL FIX: Always check global rules first
        if (globalRules) {
            // First check pre-resolved multiples (most accurate, includes category resolution)
            if (productId && globalRules.resolved_products && globalRules.resolved_products[productId]) {
                var multiple = parseInt(globalRules.resolved_products[productId]);
                log('Found pre-resolved multiple for ' + productId + ': ' + multiple);
                return multiple;
            }
            
            // Fallback to product-specific rules if resolved data is missing
            if (productId && globalRules.products && globalRules.products[productId]) {
                var multiple = parseInt(globalRules.products[productId]);
                log('Found product rule for ' + productId + ': ' + multiple);
                return multiple;
            }
            
            // Legacy fallback to category rules (shouldn't be needed with pre-resolved data)
            if (productId && globalRules.product_categories && globalRules.product_categories[productId]) {
                var categories = globalRules.product_categories[productId];
                for (var i = 0; i < categories.length; i++) {
                    var categoryId = categories[i];
                    if (globalRules.categories && globalRules.categories[categoryId]) {
                        var catMultiple = parseInt(globalRules.categories[categoryId]);
                        log('Found category rule for product ' + productId + ' (category ' + categoryId + '): ' + catMultiple);
                        return catMultiple;
                    }
                }
            }
            
            // Use global default if available
            if (globalRules.default) {
                log('Using global default multiple: ' + globalRules.default);
                return parseInt(globalRules.default);
            }
        }
        
        // Fallback to hardcoded default
        log('Using fallback default multiple: ' + defaultMultiple);
        return defaultMultiple;
    }
    
    /**
     * Enforce a value is a multiple
     */
    function enforceMultiple(value, multiple) {
        if (value % multiple === 0) {
            return value;
        }
        
        // Round to nearest multiple
        var remainder = value % multiple;
        var correctedVal;
        
        if (remainder >= multiple / 2) {
            correctedVal = value + (multiple - remainder);
        } else {
            correctedVal = value - remainder;
        }
        
        // Ensure at least one multiple
        if (correctedVal < multiple) {
            correctedVal = multiple;
        }
        
        return correctedVal;
    }
    
    /**
     * Handle variation change
     */
    function handleVariationChange(event, variation) {
        if (!variation) return;
        
        log('Variation changed', variation);
        
        var $form = $(this);
        var $quantityInput = $form.find('input.qty');
        
        if (!$quantityInput.length) return;
        
        // Check for variation-specific data
        if (variation.wcqm_data) {
            var multiple = parseInt(variation.wcqm_data.multiple);
            var variationId = variation.variation_id;
            
            if (!isNaN(multiple) && multiple > 0 && variationId) {
                log('Variation ' + variationId + ' has specific multiple: ' + multiple);
                
                // Store rule if it's product-specific
                if (variation.wcqm_data.has_product_rule === 'yes') {
                    productRules[variationId] = multiple;
                    log('Stored rule for variation ' + variationId + ': ' + multiple);
                }
                
                // Force the rules now
                enforceRulesOnInput($quantityInput, variationId, true);
            }
        }
    }
    
    /**
     * Handle variation reset
     */
    function handleVariationReset() {
        var $form = $(this);
        var $quantityInput = $form.find('input.qty');
        
        if (!$quantityInput.length) return;
        
        // Reset to parent product multiple
        var parentId = $form.find('input[name="product_id"]').val();
        if (parentId) {
            log('Variation reset - using parent product ' + parentId);
            enforceRulesOnInput($quantityInput, parentId, true);
        } else {
            log('Variation reset - using default multiple');
            enforceRulesOnInput($quantityInput, null, true);
        }
    }
})(jQuery); 