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

### 4. Stock Level Awareness
- Apply quantity multiple rules only when stock levels exceed 10 units
- Dynamically disable enforcement when stock falls below 10 units
- Handle transitions between enforced and non-enforced states gracefully
- Provide clear messaging about stock-related rule changes

### 5. Data Management
- Store rules in a single WordPress option using a clear, simple structure
- Avoid complex nested data structures that are difficult to debug
- Include version information in the saved data format for future migration needs

## Technical Architecture

### Data Structure
```php
// Simple, flat data structure for rules
$rules = [
    'default_multiple' => 10, // Default multiple for all products (typically 10)
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

### Performance Requirements
- Optimize database queries for minimal impact on page load times
- Implement efficient caching of rule data with proper invalidation
- Use WordPress transients for temporary data storage where appropriate
- Minimize JavaScript execution time on front-end
- Ensure plugin scales well with large product catalogs (1000+ products)

## WooCommerce Compatibility

### Version Support
- Minimum supported WooCommerce version: 4.0.0
- Recommended WooCommerce version: 5.0.0 and above
- WordPress minimum version: 5.4

### Integration Points
- Hooks into standard WooCommerce cart and checkout processes
- Compatible with standard WooCommerce themes and templates
- Works with AJAX-enabled cart updates
- Compatible with mini-cart implementations

### Testing Requirements
- Test with each new WooCommerce minor version
- Verify template compatibility after WooCommerce updates
- Document any breaking changes in newer WooCommerce versions

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

9. **Category Hierarchy Complexity**
   - Consider how rules cascade through the category hierarchy
   - Define clear behavior for products in multiple categories
   - Document how parent-child category relationships affect rule application

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
- [ ] Correctly disables enforcement when stock is below 10
- [ ] Performs efficiently with large product catalogs

## Deployment and Maintenance

### Pre-release Checklist
- Ensure all code follows WordPress coding standards
- Verify compatibility with latest WordPress and WooCommerce versions
- Complete all items in the testing checklist
- Confirm proper internationalization and localization
- Review security practices (nonce verification, capability checks, etc.)

### Version Control
- Use semantic versioning (MAJOR.MINOR.PATCH)
- Document all changes in a changelog
- Tag releases in the repository
- Maintain a clean commit history with meaningful messages

### Plugin Repository Submission
- Create complete readme.txt following WordPress standards
- Prepare screenshots and banner images
- Write clear installation and usage instructions
- Define appropriate plugin tags

### Ongoing Support
- Establish a system for tracking and prioritizing bug reports
- Define support channels and response expectations
- Document common issues and their resolutions
- Plan for regular security and compatibility updates

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

6. **Implementation Notes**
   - Rule resolution timing: Pay special attention to when rules are loaded and applied during the WooCommerce lifecycle.
   - Category hierarchy: Define how rules cascade through the category hierarchy for stores with complex taxonomies.
   - Cache management: Ensure proper cache invalidation whenever rules are updated.
   - JavaScript dependency: Verify proper data attribute population for frontend JS.
   - Template compatibility: Regularly test quantity templates against new WooCommerce versions.
   - Linter errors: WordPress function-related linter errors can be ignored in this context.
   - Server-side validation: Never rely on JS validation alone for critical business logic.