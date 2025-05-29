# WC Quantity Multiples Plugin Specification

## Overview
WC Quantity Multiples is a WordPress/WooCommerce plugin that enforces product quantities to be purchased in specific multiples (e.g., 3, 5, 10). It allows store owners to set quantity multiples on a per-product or per-category basis, with product-specific rules taking precedence over category rules.

## Core Requirements

### 1. Rule Management
- Set a default quantity multiple that applies to all products without specific rules
- Create product-specific quantity multiple rules
- Create category-specific quantity multiple rules
- Product rules override category rules when both exist for a product
- Provide an intuitive admin interface for managing these rules
- Persist rules in WordPress options using a simple, maintainable data structure

### 2. Product Page Integration
- Replace the default quantity selector with a custom one that enforces the applicable multiple
- Increment/decrement buttons should adjust by the defined multiple
- Prevent users from entering quantities that aren't multiples of the defined value
- Display clear messaging about the quantity requirement
- Ensure correct initial quantity is set (default value should be the applicable multiple)

### 3. Cart Validation
- Validate quantities when products are added to cart
- Auto-adjust quantities to the nearest valid multiple if necessary
- Display informative messages about any adjustments made
- Prevent checkout if any cart item violates its quantity multiple rule

### 4. Data Management
- Store rules in a single WordPress option using a clear, simple structure
- Avoid complex nested data structures that are difficult to debug
- Include version information in the saved data format for future migration needs

## Technical Architecture

### Data Structure
```php
// Simple, flat data structure for rules
$rules = [
    'default_multiple' => 1, // Default multiple for all products
    'product_rules' => [
        // product_id => multiple
        123 => 5,
        456 => 10
    ],
    'category_rules' => [
        // category_id => multiple
        22 => 3,
        33 => 6
    ]
];
```

### Core Classes

1. **Main Plugin Class (`WC_Quantity_Multiples`)**
   - Plugin initialization
   - Dependency checks
   - Hook registration
   - Template overrides

2. **Rules Manager (`WCQM_Rules_Manager`)**
   - Rule loading, retrieval, and storage
   - Simple public API for rule access
   - Methods to determine the applicable rule for a product

3. **Product Integration (`WCQM_Product`)**
   - Product page quantity field modifications
   - Add-to-cart validation
   - Display of quantity requirements

4. **Cart Integration (`WCQM_Cart`)**
   - Cart quantity validation
   - Quantity adjustment on cart updates
   - Cart error messaging

5. **Settings (`WCQM_Settings`)**
   - Admin settings page
   - Rule creation and management UI
   - Settings storage

## Implementation Guidelines

### Server-Side Logic (PHP)
- Keep all business logic server-side
- Single source of truth for rule resolution
- Clear validation methods
- Comprehensive error logging
- Use WordPress hooks and filters where appropriate
- Follow WordPress coding standards

### Client-Side Logic (JavaScript)
- JavaScript should only handle UI interactions and form validation
- No rule resolution logic in JavaScript
- Rely on data attributes populated by server for any rule-specific behavior
- Keep JavaScript simple and focused on usability enhancements

### Templating
- Use template overrides for quantity input fields
- Minimal changes to WooCommerce templates
- Support theme customizations

### Error Handling
- Graceful degradation if JavaScript is disabled
- Clear user messaging for quantity adjustments
- Detailed error logging for debugging
- Validation at all entry points (product page, cart, checkout)

## Common Pitfalls to Avoid

Based on our development experience, these are critical issues to avoid:

1. **Data Structure Complexity**
   - Avoid deeply nested arrays that are difficult to debug
   - Keep the data structure flat and simple
   - Use clear naming conventions for data keys

2. **Rule Resolution Timing**
   - Ensure rules are loaded before they're needed
   - Cache rule resolutions where appropriate
   - Be mindful of when hooks fire in the WooCommerce lifecycle

3. **JavaScript/PHP Data Exchange**
   - Don't duplicate business logic in JavaScript
   - Pass only necessary data to JavaScript via data attributes or localized variables
   - Keep frontend logic minimal

4. **Caching Issues**
   - Implement proper cache invalidation when rules change
   - Be aware of object caching in WooCommerce
   - Reset internal state when needed

5. **Template Conflicts**
   - Use template overrides carefully
   - Support themes that may customize WooCommerce templates
   - Test with popular themes

6. **AJAX Handling**
   - Ensure proper nonce verification
   - Validate all inputs
   - Return clear error messages

7. **Rule Precedence Confusion**
   - Maintain clear precedence rules (product rules > category rules > default)
   - Document the precedence logic clearly
   - Consistent application of precedence rules throughout

8. **Missing Validation Points**
   - Validate at all possible entry points (direct URL, add to cart, update cart, checkout)
   - Don't assume previous validation steps have executed

## Development Workflow

1. **Setup Phase**
   - Create plugin structure
   - Implement dependency checks
   - Set up basic hooks

2. **Rules Management**
   - Implement settings page
   - Create rule storage and retrieval
   - Build admin UI

3. **Frontend Integration**
   - Product page customization
   - Quantity selector behavior
   - Add to cart validation

4. **Cart Integration**
   - Cart validation
   - Quantity adjustments
   - Error messaging

5. **Testing**
   - Test with simple products
   - Test with variable products
   - Test with different themes
   - Test edge cases (min/max quantities, stock limitations)

## Code Organization

```
wc-quantity-multiples/
├── wc-quantity-multiples.php  (Main plugin file)
├── readme.txt                 (WordPress readme)
├── includes/
│   ├── class-wcqm-rules-manager.php  (Rule management)
│   ├── class-wcqm-product.php        (Product integration)
│   ├── class-wcqm-cart.php           (Cart integration)
│   └── class-wcqm-settings.php       (Admin settings)
├── templates/
│   ├── global/
│   │   └── quantity-input.php        (Product page template)
│   └── cart/
│       └── quantity-input.php        (Cart page template)
├── assets/
│   ├── css/
│   │   ├── wcqm-style.css            (Frontend styles)
│   │   └── wcqm-admin.css            (Admin styles)
│   └── js/
│       ├── wcqm-quantity.js          (Frontend quantity script)
│       └── wcqm-admin.js             (Admin UI script)
└── languages/                        (Translation files)
```

## Testing Checklist

- [ ] Default rules apply when no specific rules exist
- [ ] Product rules override category rules
- [ ] Quantity selector on product page respects the rule
- [ ] Add to cart validation works correctly
- [ ] Cart quantity updates are validated
- [ ] Checkout prevents invalid quantities
- [ ] Rules can be added successfully in admin
- [ ] Rules can be edited and deleted
- [ ] Works with simple products
- [ ] Works with variable products
- [ ] Works with different themes
- [ ] Properly handles edge cases (min/max quantities, stock limitations)

## Lessons Learned

1. **Keep It Simple**
   - The simpler the code, the fewer bugs it will have
   - Avoid premature optimization
   - Choose readability over cleverness

2. **Single Source of Truth**
   - Keep all business logic server-side
   - Avoid duplicating rule resolution logic
   - Pass only necessary data to the frontend

3. **Robust Data Validation**
   - Validate all inputs, especially user inputs
   - Sanitize data before storage
   - Escape output appropriately

4. **Thorough Testing**
   - Test all user workflows
   - Test edge cases
   - Test with different WooCommerce configurations

5. **Clear Separation of Concerns**
   - Each class should have a single responsibility
   - Avoid tight coupling between components
   - Use dependency injection where appropriate 