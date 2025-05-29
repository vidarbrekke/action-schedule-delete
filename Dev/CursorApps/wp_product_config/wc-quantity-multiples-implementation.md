# WC Quantity Multiples: Implementation Guide

This document provides a comprehensive explanation of how the WC Quantity Multiples plugin enforces product quantity rules on product pages, cart, and checkout in WooCommerce.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Data Structure](#data-structure)
3. [Backend Implementation](#backend-implementation)
   - [Rule Storage and Management](#rule-storage-and-management)
   - [Rule Resolution Process](#rule-resolution-process)
   - [Product Page Integration](#product-page-integration)
4. [Template System](#template-system)
   - [Template Overrides](#template-overrides)
   - [Quantity Input Templates](#quantity-input-templates)
5. [Frontend Implementation](#frontend-implementation)
   - [JavaScript Rule Handling](#javascript-rule-handling)
   - [Quantity Input Enhancement](#quantity-input-enhancement)
   - [Validation and Enforcement](#validation-and-enforcement)
6. [Integration Points](#integration-points)
   - [WooCommerce Hooks](#woocommerce-hooks)
   - [Templates](#templates)
   - [JavaScript Integration](#javascript-integration)
7. [Performance Optimizations](#performance-optimizations)
8. [Implementation Challenges](#implementation-challenges)
9. [Testing and Validation](#testing-and-validation)

## Architecture Overview

The WC Quantity Multiples plugin follows a layered architecture:

1. **Data Layer**: Stores rules in WordPress options table
2. **Business Logic Layer**: Handles rule resolution and enforcement
3. **Integration Layer**: Connects with WooCommerce hooks and templates
4. **Presentation Layer**: Enhances the UI with custom quantity inputs

The plugin ensures that business logic resides server-side, with JavaScript used only for UI enhancement.

## Data Structure

Rules are stored in a flat, efficient data structure:

```php
// Stored in wp_options table under 'wcqm_settings'
$rules = [
    'default_multiple' => 10,  // Default multiple for all products
    'rules' => [
        [
            'id' => 'unique_id_1',
            'type' => 'product',
            'target_id' => 123,
            'target_name' => 'Product Name',
            'multiple' => 5
        ],
        [
            'id' => 'unique_id_2',
            'type' => 'category',
            'target_id' => 45,
            'target_name' => 'Category Name',
            'multiple' => 6
        ]
    ]
]
```

For runtime efficiency, we transform this into indexed structures:

```php
$rules_by_type = [
    'products' => [
        123 => 5,
        456 => 10
    ],
    'categories' => [
        22 => 3,
        33 => 6
    ]
]

$product_category_map = [
    123 => [22, 45],  // Product 123 belongs to categories 22 and 45
    456 => [33]       // Product 456 belongs to category 33
]
```

## Backend Implementation

### Rule Storage and Management

The core of the plugin is the `WCQM_Rules_Manager` class, implemented as a singleton:

```php
class WCQM_Rules_Manager {
    // Singleton instance
    private static $instance = null;
    
    // Rule storage
    private $rules = null;
    private $rules_by_type = null;
    private $product_category_map = null;
    private $default_multiple = 10;
    private $rules_loaded = false;
    
    // Get singleton instance
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Constructor and initialization methods
    // ...
}
```

Rule loading happens lazily, ensuring we only load data when needed:

```php
public function load_rules() {
    if ($this->rules_loaded) {
        return;
    }

    // Load stored settings
    $stored_data = get_option('wcqm_settings', [
        'rules' => [],
        'default_multiple' => 10
    ]);

    // Set default multiple
    $this->default_multiple = isset($stored_data['default_multiple']) 
        ? max(1, absint($stored_data['default_multiple'])) 
        : 10;

    // Initialize rule arrays
    $this->rules = [];
    $this->rules_by_type = [
        'products' => [],
        'categories' => []
    ];

    // Process and validate rules
    if (isset($stored_data['rules']) && is_array($stored_data['rules'])) {
        foreach ($stored_data['rules'] as $rule) {
            if (!$this->validate_single_rule($rule)) {
                continue;
            }

            // Store in main rules array
            $this->rules[] = $rule;

            // Index by type for faster lookups
            $target_id = absint($rule['target_id']);
            $multiple = max(1, absint($rule['multiple']));

            if ($rule['type'] === 'product') {
                $this->rules_by_type['products'][$target_id] = $multiple;
            } elseif ($rule['type'] === 'category') {
                $this->rules_by_type['categories'][$target_id] = $multiple;
            }
        }
    }

    // Build product-category map for faster lookups
    $this->build_product_category_map();

    $this->rules_loaded = true;
}
```

### Rule Resolution Process

The critical method for enforcing multiples is `get_product_multiple()`:

```php
public function get_product_multiple($product_id) {
    if (!$this->rules_loaded) {
        $this->load_rules();
    }

    $product_id = absint($product_id);

    // Check for product-specific rule first (highest priority)
    if (isset($this->rules_by_type['products'][$product_id])) {
        return $this->rules_by_type['products'][$product_id];
    }

    // Check category rules if product has categories
    if (isset($this->product_category_map[$product_id])) {
        foreach ($this->product_category_map[$product_id] as $category_id) {
            if (isset($this->rules_by_type['categories'][$category_id])) {
                return $this->rules_by_type['categories'][$category_id];
            }
        }
    }

    // Fall back to default multiple
    return $this->default_multiple;
}
```

Category mapping is built efficiently using a SQL query:

```php
private function build_product_category_map() {
    $this->product_category_map = [];
    
    // Query to get all product-category relationships
    global $wpdb;
    $results = $wpdb->get_results("
        SELECT p.ID as product_id, tt.term_id as category_id
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        AND tt.taxonomy = 'product_cat'
    ");
    
    if (!empty($results)) {
        foreach ($results as $row) {
            $product_id = intval($row->product_id);
            $category_id = intval($row->category_id);
            
            if (!isset($this->product_category_map[$product_id])) {
                $this->product_category_map[$product_id] = [];
            }
            
            $this->product_category_map[$product_id][] = $category_id;
        }
    }
}
```

### Product Page Integration

Integration with product pages happens through the `WCQM_Product` class, which hooks into WooCommerce:

```php
class WCQM_Product {
    // Class properties and initialization
    // ...
    
    public function __construct() {
        // Get rules manager instance
        $this->rules_manager = WCQM_Rules_Manager::instance();
        
        // Register hooks
        add_filter('woocommerce_quantity_input_args', [$this, 'modify_quantity_input_args'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 3);
        // More hooks...
    }
    
    public function modify_quantity_input_args($args, $product) {
        if (!$product) {
            return $args;
        }

        $product_id = $product->get_id();
        $multiple = $this->rules_manager->get_product_multiple($product_id);

        // Modify quantity input arguments
        $args['min_value'] = $multiple;
        $args['step'] = $multiple;
        
        // Set default value to multiple
        if (!isset($args['input_value']) || $args['input_value'] < $multiple) {
            $args['input_value'] = $multiple;
        }

        // Add data attributes for JavaScript
        $args['custom_attributes'] = array_merge(
            isset($args['custom_attributes']) ? $args['custom_attributes'] : [],
            [
                'data-multiple' => $multiple,
                'data-product-id' => $product_id
            ]
        );

        return $args;
    }
    
    // Additional methods for validation, etc.
}
```

## Template System

### Template Overrides

The plugin overrides two key WooCommerce templates:

1. `global/quantity-input.php` - For product pages
2. `cart/quantity-input.php` - For cart pages

These are loaded using WooCommerce's template override system:

```php
function wcqm_override_quantity_template($template, $template_name, $template_path) {
    // Check if this is a quantity template
    if ($template_name !== 'global/quantity-input.php' && $template_name !== 'cart/quantity-input.php') {
        return $template;
    }
    
    // Get our template path
    $plugin_template = WCQM_PLUGIN_DIR . 'templates/' . $template_name;
    
    // Return our template if it exists
    if (file_exists($plugin_template)) {
        return $plugin_template;
    }
    
    // Fall back to WooCommerce template
    return $template;
}
add_filter('woocommerce_locate_template', 'wcqm_override_quantity_template', 10, 3);
```

### Quantity Input Templates

The custom product page template enforces quantity multiples:

```php
// Excerpt from templates/global/quantity-input.php
<?php
// Get the product ID
global $product;
$product_id = isset($args['product_id']) ? absint($args['product_id']) : ($product ? $product->get_id() : 0);

// Apply rule-specific values if available
if (class_exists('WCQM_Rules_Manager')) {
    $rules_manager = WCQM_Rules_Manager::instance();
    $multiple = $rules_manager->get_product_multiple($product_id);
    $min_value = $multiple;
    $step = $multiple;
    
    // Make sure input value is at least the minimum and a multiple
    if ($input_value < $min_value) {
        $input_value = $min_value;
    } else {
        // Adjust to nearest multiple
        $remainder = $input_value % $multiple;
        if ($remainder !== 0) {
            $input_value = $input_value - $remainder + ($remainder >= ($multiple / 2) ? $multiple : 0);
        }
    }
}
?>

<div class="wcqm-quantity">
    <button 
        type="button" 
        class="wcqm-btn minus" 
        aria-label="<?php esc_attr_e('Decrease quantity', 'wc-quantity-multiples'); ?>" 
        data-wcqm-btn="minus"
        data-input-id="<?php echo esc_attr($input_id); ?>"
    >-</button>
    
    <input
        type="number"
        id="<?php echo esc_attr($input_id); ?>"
        name="<?php echo esc_attr($input_name); ?>"
        class="<?php echo esc_attr(implode(' ', (array) $classes)); ?>"
        value="<?php echo esc_attr($input_value); ?>"
        min="<?php echo esc_attr($min_value); ?>"
        <?php if ($max_value) : ?>max="<?php echo esc_attr($max_value); ?>"<?php endif; ?>
        step="<?php echo esc_attr($step); ?>"
        <?php echo $readonly ? 'readonly="readonly"' : ''; ?>
        <?php 
        foreach ($custom_attributes as $attribute => $attribute_value) {
            echo esc_attr($attribute) . '="' . esc_attr($attribute_value) . '" ';
        }
        ?>
        data-wcqm-id="<?php echo esc_attr($input_id); ?>"
        data-wcqm-input="true"
    />
    
    <button 
        type="button" 
        class="wcqm-btn plus" 
        aria-label="<?php esc_attr_e('Increase quantity', 'wc-quantity-multiples'); ?>" 
        data-wcqm-btn="plus"
        data-input-id="<?php echo esc_attr($input_id); ?>"
    >+</button>
</div>
```

## Frontend Implementation

### JavaScript Rule Handling

The plugin injects all rules data into the page for JavaScript access:

```php
// In main plugin file
function wcqm_enqueue_scripts() {
    if (!is_woocommerce() && !is_cart() && !is_checkout()) {
        return;
    }

    wp_enqueue_style(
        'wcqm-style',
        WCQM_PLUGIN_URL . 'assets/css/wcqm-style.css',
        [],
        WCQM_VERSION
    );

    wp_enqueue_script(
        'wcqm-quantity',
        WCQM_PLUGIN_URL . 'assets/js/wcqm-quantity.js',
        ['jquery'],
        WCQM_VERSION,
        true
    );

    // Inject rules data
    $rules_manager = WCQM_Rules_Manager::instance();
    wp_localize_script(
        'wcqm-quantity',
        'wcqm_rules',
        $rules_manager->get_all_rules_data()
    );
}
```

The `get_all_rules_data()` method returns a complete rule set:

```php
public function get_all_rules_data() {
    // Load rules if not already loaded
    if (!$this->rules_loaded) {
        $this->load_rules();
    }
    
    // Extract product-specific rules
    $product_rules = [];
    $resolved_products = [];
    
    foreach ($this->rules as $rule) {
        if ($rule['type'] === 'product') {
            $product_id = absint($rule['target_id']);
            $product_rules[$product_id] = absint($rule['multiple']);
            $resolved_products[$product_id] = absint($rule['multiple']);
        }
    }
    
    // Extract category rules
    $category_rules = [];
    foreach ($this->rules as $rule) {
        if ($rule['type'] === 'category') {
            $category_id = absint($rule['target_id']);
            $category_rules[$category_id] = absint($rule['multiple']);
        }
    }
    
    // Pre-resolve all product multiples
    $this->ensure_product_category_map();
    
    // For each product, pre-resolve its multiple if not already in resolved_products
    $args = [
        'post_type' => 'product',
        'posts_per_page' => 500,
        'post_status' => 'publish',
    ];
    
    $product_query = new WP_Query($args);
    if ($product_query->have_posts()) {
        foreach ($product_query->posts as $product_post) {
            $product_id = $product_post->ID;
            
            // Skip products we already have a direct rule for
            if (isset($resolved_products[$product_id])) {
                continue;
            }
            
            // Check for category rules
            if (isset($this->product_category_map[$product_id])) {
                foreach ($this->product_category_map[$product_id] as $category_id) {
                    if (isset($category_rules[$category_id])) {
                        $resolved_products[$product_id] = $category_rules[$category_id];
                        break; // Found a rule
                    }
                }
            }
            
            // If no rule found, use default
            if (!isset($resolved_products[$product_id])) {
                $resolved_products[$product_id] = $this->default_multiple;
            }
        }
    }

    // Return the complete data structure
    return [
        'default' => $this->default_multiple,
        'products' => $product_rules,
        'categories' => $category_rules,
        'resolved_products' => $resolved_products,
        'product_categories' => $this->product_category_map
    ];
}
```

### Quantity Input Enhancement

The JavaScript enhances the quantity input controls:

```javascript
// Excerpt from wcqm-quantity.js
(function($) {
    // Initialize on document ready
    $(document).ready(function() {
        // Initialize all quantity inputs
        initAllQuantityInputs();
        
        // Handle button clicks
        $(document).on('click', '.wcqm-btn', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('wcqm-btn');
            var inputId = $button.data('input-id');
            var $input = $('#' + inputId);
            
            if (!$input.length) {
                $input = $button.siblings('input.qty');
            }
            
            if (!$input.length) {
                return;
            }
            
            // Get values and constraints
            var currentVal = parseFloat($input.val()) || 0;
            var min = parseFloat($input.attr('min'));
            var max = parseFloat($input.attr('max'));
            
            // Get the step (multiple) to use
            var productId = $input.data('product-id');
            var multiple = parseFloat($input.data('multiple')) || 1;
            
            // Check for pre-resolved multiple from rules data
            if (productId && window.wcqm_rules && window.wcqm_rules.resolved_products && 
                typeof window.wcqm_rules.resolved_products[productId] !== 'undefined') {
                multiple = parseInt(window.wcqm_rules.resolved_products[productId]);
            }
            
            // Ensure min is at least one multiple
            if (isNaN(min) || min < multiple) {
                min = multiple;
            }
            
            // Calculate new value
            var newVal = currentVal;
            
            if (action === 'plus') {
                newVal = currentVal + multiple;
                
                // Check max value
                if (!isNaN(max) && (newVal > max)) {
                    newVal = max;
                }
            } else if (action === 'minus') {
                newVal = currentVal - multiple;
                
                // Check min value
                if (newVal < min) {
                    newVal = min;
                }
            }
            
            // Update value and trigger change
            $input.val(newVal).trigger('change');
        });
        
        // Handle manual input
        $(document).on('input change', '.wcqm-quantity input[type="number"]', function() {
            var $input = $(this);
            var productId = $input.data('product-id');
            var multiple = parseFloat($input.data('multiple')) || 1;
            var currentVal = parseFloat($input.val()) || 0;
            
            // Get the applicable multiple
            if (productId && window.wcqm_rules && window.wcqm_rules.resolved_products && 
                window.wcqm_rules.resolved_products[productId]) {
                multiple = parseInt(window.wcqm_rules.resolved_products[productId]);
            }
            
            // Ensure minimum is respected
            var min = Math.max(multiple, parseFloat($input.attr('min')) || 0);
            
            // Round to nearest multiple
            var remainder = currentVal % multiple;
            if (remainder !== 0) {
                var newVal = currentVal - remainder;
                if (remainder >= multiple / 2) {
                    newVal += multiple;
                }
                if (newVal < min) {
                    newVal = min;
                }
                $input.val(newVal);
            }
        });
    });
    
    // Initialize all quantity inputs
    function initAllQuantityInputs() {
        $('.wcqm-quantity').each(function() {
            var $container = $(this);
            var $input = $container.find('input[type="number"]');
            
            if (!$input.length) return;
            
            // Get product ID and multiple
            var productId = parseInt($input.data('product-id')) || 0;
            var multiple = parseInt($input.data('multiple')) || 1;
            
            // Verify multiple is coming from data attribute
            if (productId && window.wcqm_rules && window.wcqm_rules.resolved_products) {
                if (window.wcqm_rules.resolved_products[productId]) {
                    multiple = parseInt(window.wcqm_rules.resolved_products[productId]);
                    console.log('Using pre-resolved multiple for product ' + productId + ': ' + multiple);
                    $input.data('multiple', multiple);
                }
            }
        });
    }
})(jQuery);
```

### Validation and Enforcement

Server-side validation happens in multiple places to ensure quantity rules are enforced:

1. **Add to cart validation:**

```php
public function validate_add_to_cart($valid, $product_id, $quantity) {
    if (!$valid) {
        return false;
    }

    // Get the applicable multiple
    $multiple = $this->rules_manager->get_product_multiple($product_id);
    
    // Check if the quantity is a multiple
    if ($quantity % $multiple !== 0) {
        wc_add_notice(
            sprintf(
                __('Quantity must be a multiple of %d for %s.', 'wc-quantity-multiples'),
                $multiple,
                wc_get_product($product_id)->get_name()
            ),
            'error'
        );
        return false;
    }

    return true;
}
```

2. **Cart update validation:**

```php
public function validate_cart_update($valid, $cart_item_key, $values, $quantity) {
    if (!$valid) {
        return false;
    }

    $product_id = $values['product_id'];
    $variation_id = isset($values['variation_id']) ? $values['variation_id'] : 0;
    
    // Check the appropriate product ID
    $check_id = $variation_id > 0 ? $variation_id : $product_id;
    
    // Get the applicable multiple
    $multiple = $this->rules_manager->get_product_multiple($check_id);
    
    // Check if the quantity is a multiple
    if ($quantity % $multiple !== 0) {
        // Round to the nearest multiple
        $adjusted_quantity = round($quantity / $multiple) * $multiple;
        
        // Ensure quantity is at least one multiple
        $adjusted_quantity = max($multiple, $adjusted_quantity);
        
        // Set adjusted quantity in cart
        WC()->cart->set_quantity($cart_item_key, $adjusted_quantity, false);
        
        // Add notice
        wc_add_notice(
            sprintf(
                __('Quantity for %s has been adjusted to %d to match the required multiple of %d.', 'wc-quantity-multiples'),
                wc_get_product($check_id)->get_name(),
                $adjusted_quantity,
                $multiple
            ),
            'notice'
        );
        
        // Return false to prevent the original update
        return false;
    }

    return true;
}
```

## Integration Points

### WooCommerce Hooks

The plugin uses the following key WooCommerce hooks:

```php
// Product page hooks
add_filter('woocommerce_quantity_input_args', [$this, 'modify_quantity_input_args'], 10, 2);
add_filter('woocommerce_available_variation', [$this, 'modify_variation_data'], 10, 3);

// Cart and validation hooks
add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 3);
add_filter('woocommerce_update_cart_validation', [$this, 'validate_cart_update'], 10, 4);
add_filter('woocommerce_cart_item_quantity', [$this, 'modify_cart_item_quantity'], 10, 3);
add_filter('woocommerce_stock_amount', [$this, 'adjust_stock_amount'], 10, 2);

// Checkout hooks
add_action('woocommerce_checkout_process', [$this, 'validate_checkout_quantities']);

// Template hooks
add_filter('woocommerce_locate_template', 'wcqm_override_quantity_template', 10, 3);
```

### Templates

Overridden WooCommerce templates:

- `templates/global/quantity-input.php` (product pages)
- `templates/cart/quantity-input.php` (cart page)

### JavaScript Integration

The JavaScript is carefully integrated to:

1. Work with all quantity inputs across the site
2. Support AJAX cart updates
3. Use pre-resolved rule data for performance
4. Avoid duplicate code and functionality
5. Maintain consistency with server-side rules

## Performance Optimizations

Several performance optimizations are implemented:

1. **Rule Indexing:**
   - Rules are indexed by type for O(1) lookups
   - Product-category mapping for efficient rule resolution

2. **Lazy Loading:**
   - Rules are loaded only when needed
   - Heavy operations like building the product-category map happen once

3. **Pre-Resolution:**
   - Product multiples are pre-resolved before sending to JavaScript
   - Avoids redundant rule resolution on the frontend

4. **Caching:**
   - WordPress transients store expensive computations
   - Rules are cached within the request lifecycle

5. **Efficient Queries:**
   - Direct SQL query for product-category mapping
   - Limited batch sizes for large catalogs

## Implementation Challenges

Several challenges were addressed in this implementation:

1. **Rule Resolution Order:**
   - Clear precedence: product rules > category rules > default
   - Consistent application across all touch points

2. **Stock Level Awareness:**
   - Enforces rules only when stock levels are sufficient
   - Disables enforcement for products with low stock

3. **Template Compatibility:**
   - Works with standard WooCommerce themes
   - Handles customized themes through careful template design

4. **AJAX Compatibility:**
   - Supports AJAX cart updates
   - Maintains rule enforcement during asynchronous operations

5. **Multiple Category Handling:**
   - Products in multiple categories use the smallest applicable multiple
   - Documented behavior for predictable results

## Testing and Validation

To ensure the implementation works correctly, test the following scenarios:

1. **Product Page Tests:**
   - Verify quantity input has correct min, step, and initial value
   - Test increment/decrement buttons
   - Validate manual quantity input
   - Check variation switching for variable products

2. **Cart Tests:**
   - Add product to cart with valid quantity
   - Update cart with valid and invalid quantities
   - Test bulk cart updates
   - Verify mini-cart behavior

3. **Checkout Tests:**
   - Complete checkout with valid quantities
   - Attempt checkout with manipulated quantities (should validate)

4. **Rule Management Tests:**
   - Create, update, and delete rules
   - Set default multiple
   - Verify rule precedence

5. **Edge Cases:**
   - Products with no stock management
   - Products with stock below threshold
   - Very high quantity values
   - Zero or negative quantities (should prevent) 