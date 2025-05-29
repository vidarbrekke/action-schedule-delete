<?php
/**
 * WC Quantity Multiples - Rules Manager
 * 
 * Handles the management, storage, and retrieval of quantity rules.
 *
 * @package WC_Quantity_Multiples
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

// Define ABSPATH if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(__FILE__))) . '/');
}

// Load WordPress core files if functions don't exist
if (!function_exists('get_option')) {
    require_once(ABSPATH . 'wp-includes/functions.php');
    require_once(ABSPATH . 'wp-includes/plugin.php');
    require_once(ABSPATH . 'wp-includes/post.php');
    require_once(ABSPATH . 'wp-includes/taxonomy.php');
}

// Ensure WooCommerce is active and available
if (!class_exists('WooCommerce')) {
    return;
}

// Check for WordPress functions
if (!function_exists('add_action') || 
    !function_exists('add_filter') || 
    !function_exists('get_option') || 
    !function_exists('update_option')) {
    return;
}

/**
 * WCQM_Rules_Manager Class
 * 
 * Manages the quantity multiple rules for products and categories.
 */
class WCQM_Rules_Manager {
    /**
     * The single instance of the class.
     *
     * @var WCQM_Rules_Manager
     */
    private static $instance = null;

    /**
     * Rules array.
     *
     * @var array
     */
    private $rules = null;

    /**
     * Rules by type array.
     *
     * @var array
     */
    private $rules_by_type = null;

    /**
     * Option name for storing rules
     */
    const RULES_OPTION = 'wcqm_rules';
    const RULES_BACKUP_OPTION = 'wcqm_rules_backup';
    const RULES_VERSION_OPTION = 'wcqm_rules_version';
    const CURRENT_RULES_VERSION = '1.0.0';

    /**
     * Default multiple value.
     *
     * @var int
     */
    private $default_multiple = 10;

    /**
     * Flag to indicate if rules have been loaded.
     *
     * @var bool
     */
    private $rules_loaded = false;

    /**
     * Cache of resolved rules for products.
     *
     * @var array
     */
    protected $product_rule_cache = array();

    /**
     * Cache of resolved rules for products.
     *
     * @var array
     */
    protected $product_rules_cache = array();

    /**
     * Cache of resolved rules for categories.
     *
     * @var array
     */
    protected $category_rules_cache = array();

    /**
     * Product category map
     *
     * @var array
     */
    private $product_category_map = null;

    /**
     * Get instance of the rules manager
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        // Load rules immediately on construction
        $this->load_rules(true);
        
        // Only add hooks if we have the required functions
        if (function_exists('add_action')) {
            // Save rules when updated through the settings page
            add_action('wcqm_save_rules', array($this, 'save_rules'));
            
            // Clear rule cache when products or categories are updated
            add_action('woocommerce_update_product', array($this, 'clear_rule_cache'));
            add_action('edited_product_cat', array($this, 'clear_rule_cache'));
            add_action('woocommerce_new_product', array($this, 'clear_rule_cache'));
            add_action('woocommerce_delete_product', array($this, 'clear_rule_cache'));

            // Add an action to maybe restore rules from backup
            add_action('init', array($this, 'maybe_restore_rules'), 5);
            
            // Add an action to backup rules periodically
            add_action('shutdown', array($this, 'maybe_backup_rules'));

            // Add an action to inject rules data into the page for JavaScript use
            add_action('wp_footer', array($this, 'inject_rules_data'));
            add_action('admin_footer', array($this, 'inject_rules_data'));
        }
        
        error_log('WCQM Debug: Rules manager initialized');
    }

    /**
     * Load all rules from database
     */
    public function load_rules() {
        if ($this->rules_loaded) {
            error_log('WCQM Debug: Rules already loaded, skipping load');
            return;
        }

        // Load stored settings with validation
        $stored_data = get_option('wcqm_settings', array(
            'rules' => array(),
            'default_multiple' => 10
        ));

        error_log('WCQM Debug: Loading stored settings: ' . print_r($stored_data, true));

        // Validate and set default multiple
        $this->default_multiple = isset($stored_data['default_multiple']) ? max(1, absint($stored_data['default_multiple'])) : 10;

        // Initialize rules arrays with proper structure
        $this->rules = array();
        $this->rules_by_type = array(
            'products' => array(),
            'categories' => array()
        );

        // Process and index rules by type with validation
        if (isset($stored_data['rules']) && is_array($stored_data['rules'])) {
            foreach ($stored_data['rules'] as $rule) {
                if (!$this->validate_single_rule($rule)) {
                    error_log('WCQM Debug: Invalid rule found: ' . print_r($rule, true));
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

        // Log loaded rules for debugging
        error_log('WCQM Debug: Rules loaded successfully:');
        error_log('- Default multiple: ' . $this->default_multiple);
        error_log('- Product rules count: ' . count($this->rules_by_type['products']));
        error_log('- Category rules count: ' . count($this->rules_by_type['categories']));
        error_log('- Product-category map size: ' . count($this->product_category_map));
    }

    /**
     * Build the product-category map for faster lookups
     */
    private function build_product_category_map() {
        $this->product_category_map = array();

        // Get all published products
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        );

        $product_ids = get_posts($args);

        foreach ($product_ids as $product_id) {
            $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
            if (!is_wp_error($terms) && !empty($terms)) {
                $this->product_category_map[$product_id] = $terms;
            }
        }

        error_log('WCQM Debug: Built product-category map with ' . count($this->product_category_map) . ' products');
    }

    /**
     * Get multiple for a specific product with enhanced validation
     */
    public function get_product_multiple($product_id) {
        if (!$this->rules_loaded) {
            $this->load_rules();
        }

        $product_id = absint($product_id);
        error_log("WCQM Debug: Getting multiple for product {$product_id}");

        // Check for specific product rule first
        if (isset($this->rules_by_type['products'][$product_id])) {
            $multiple = $this->rules_by_type['products'][$product_id];
            error_log("WCQM Debug: Found product rule with multiple {$multiple}");
            return $multiple;
        }

        // Check category rules if product has categories
        if (isset($this->product_category_map[$product_id])) {
            foreach ($this->product_category_map[$product_id] as $category_id) {
                if (isset($this->rules_by_type['categories'][$category_id])) {
                    $multiple = $this->rules_by_type['categories'][$category_id];
                    error_log("WCQM Debug: Found category rule (ID: {$category_id}) with multiple {$multiple}");
                    return $multiple;
                }
            }
        }

        error_log("WCQM Debug: No rules found, using default multiple: {$this->default_multiple}");
        return $this->default_multiple;
    }

    /**
     * Save rules to database with enhanced validation and error reporting
     */
    public function save_rules($rules_data) {
        error_log('WCQM Debug: Saving rules: ' . print_r($rules_data, true));

        // Initialize with current values as fallback
        $current_data = get_option('wcqm_settings', array(
            'rules' => array(),
            'default_multiple' => 10
        ));

        // Validate default multiple
        if (isset($rules_data['default_multiple'])) {
            $default_multiple = absint($rules_data['default_multiple']);
            if ($default_multiple < 1) {
                error_log('WCQM Debug: Invalid default multiple, using 1');
                $default_multiple = 1;
            }
            $current_data['default_multiple'] = $default_multiple;
        }

        // Validate and process rules
        if (isset($rules_data['rules']) && is_array($rules_data['rules'])) {
            $validated_rules = array();
            
            foreach ($rules_data['rules'] as $rule) {
                // Log the rule being processed
                error_log('WCQM Debug: Processing rule: ' . print_r($rule, true));
                
                // Basic structure validation
                if (!isset($rule['type'], $rule['target_id'], $rule['multiple'])) {
                    error_log('WCQM Debug: Rule missing required fields');
                    continue;
                }

                // Validate type
                if (!in_array($rule['type'], array('product', 'category'))) {
                    error_log('WCQM Debug: Invalid rule type: ' . $rule['type']);
                    continue;
                }

                // Validate target_id
                $target_id = absint($rule['target_id']);
                if ($target_id <= 0) {
                    error_log('WCQM Debug: Invalid target ID: ' . $target_id);
                    continue;
                }

                // Validate multiple
                $multiple = absint($rule['multiple']);
                if ($multiple < 1) {
                    error_log('WCQM Debug: Invalid multiple, using 1');
                    $multiple = 1;
                }

                // Verify target exists
                $exists = false;
                if ($rule['type'] === 'product') {
                    $exists = wc_get_product($target_id) !== false;
                } else {
                    $exists = get_term($target_id, 'product_cat') !== null;
                }

                if (!$exists) {
                    error_log('WCQM Debug: Target ' . $rule['type'] . ' with ID ' . $target_id . ' does not exist');
                    continue;
                }

                // Add validated rule
                $validated_rules[] = array(
                    'type' => $rule['type'],
                    'target_id' => $target_id,
                    'multiple' => $multiple
                );
                
                error_log('WCQM Debug: Rule validated successfully');
            }

            $current_data['rules'] = $validated_rules;
        }

        // Attempt to save with error handling
        $save_result = update_option('wcqm_settings', $current_data);
        
        if ($save_result) {
            error_log('WCQM Debug: Rules saved successfully');
            
            // Clear any cached data
            $this->rules = null;
            $this->rules_by_type = null;
            $this->product_category_map = null;
            $this->rules_loaded = false;
            
            // Reload rules
            $this->load_rules();
            
            return true;
        } else {
            error_log('WCQM Debug: Failed to save rules to database');
            return false;
        }
    }

    /**
     * Validate a single rule
     */
    protected function validate_single_rule($rule) {
        error_log('WCQM Debug: Validating rule: ' . print_r($rule, true));

        if (!is_array($rule)) {
            error_log('WCQM Debug: Rule is not an array');
            return false;
        }

        $required_fields = array('id', 'type', 'target_id', 'multiple');
        foreach ($required_fields as $field) {
            if (!isset($rule[$field])) {
                error_log('WCQM Debug: Missing required field: ' . $field);
                return false;
            }
        }

        if (!in_array($rule['type'], array('product', 'category'))) {
            error_log('WCQM Debug: Invalid rule type: ' . $rule['type']);
            return false;
        }

        if (!is_numeric($rule['multiple']) || $rule['multiple'] < 1) {
            error_log('WCQM Debug: Invalid multiple value: ' . $rule['multiple']);
            return false;
        }

        // Additional validation for target_id
        $target_id = absint($rule['target_id']);
        if ($target_id === 0) {
            error_log('WCQM Debug: Invalid target_id: ' . $rule['target_id']);
            return false;
        }

        // Validate target exists
        if ($rule['type'] === 'product' && !wc_get_product($target_id)) {
            error_log('WCQM Debug: Product does not exist: ' . $target_id);
            return false;
        } elseif ($rule['type'] === 'category' && !term_exists($target_id, 'product_cat')) {
            error_log('WCQM Debug: Category does not exist: ' . $target_id);
            return false;
        }

        return true;
    }

    /**
     * Get product categories
     */
    protected function get_product_categories($product_id) {
        $terms = get_the_terms($product_id, 'product_cat');
        if (!$terms || is_wp_error($terms)) {
            return array();
        }
        
        return wp_list_pluck($terms, 'term_id');
    }

    /**
     * Get parent categories for a given category ID
     * 
     * @param int $category_id The category ID
     * @return array Array of parent category IDs
     */
    protected function get_parent_categories($category_id) {
        $parent_categories = array();
        $category = get_term($category_id, 'product_cat');
        
        if ($category && !is_wp_error($category) && $category->parent) {
            $parent_categories[] = $category->parent;
            $parent_categories = array_merge($parent_categories, $this->get_parent_categories($category->parent));
        }
        
        return $parent_categories;
    }

    /**
     * Validate a quantity against applicable rules
     */
    public function validate_quantity($product_id, $quantity) {
        $multiple = $this->get_product_multiple($product_id);
        $quantity = absint($quantity);
        
        if ($quantity < 1) {
            return false;
        }
        
        return ($quantity % $multiple === 0);
    }

    /**
     * Adjust a quantity to the nearest valid multiple
     */
    public function adjust_quantity($product_id, $quantity) {
        $multiple = $this->get_product_multiple($product_id);
        $quantity = absint($quantity);
        
        if ($quantity < 1) {
            return $multiple;
        }
        
        $remainder = $quantity % $multiple;
        if ($remainder === 0) {
            return $quantity;
        }
        
        // Round up to nearest multiple
        return $quantity + ($multiple - $remainder);
    }

    /**
     * Clear rule cache
     */
    public function clear_rule_cache() {
        $this->rules = null;
        $this->product_category_map = null;
    }

    /**
     * Get all rules.
     *
     * @return array Array of all rules.
     */
    public function get_rules() {
        $this->load_rules();
        return $this->rules;
    }

    /**
     * Get rule by ID.
     *
     * @param string $rule_id Rule ID.
     * @return array|false Rule data or false if not found.
     */
    public function get_rule($rule_id) {
        $this->load_rules();
        
        foreach ($this->rules as $rule) {
            if ($rule['id'] === $rule_id) {
                return $rule;
            }
        }
        
        return false;
    }

    /**
     * Add a new rule.
     */
    public function add_rule($rule_data) {
        error_log('WCQM Debug: Adding new rule: ' . print_r($rule_data, true));

        if (!isset($rule_data['type'], $rule_data['target_id'], $rule_data['multiple'])) {
            error_log('WCQM Debug: Missing required rule data');
            return false;
        }

        $this->load_rules();

        // Check for duplicate rule
        foreach ($this->rules as $rule) {
            if ($rule['type'] === $rule_data['type'] && $rule['target_id'] === $rule_data['target_id']) {
                error_log('WCQM Debug: Duplicate rule found');
                return false;
            }
        }

        // Generate rule ID
        $rule_id = 'rule_' . uniqid();

        // Add rule
        $new_rule = array(
            'id' => $rule_id,
            'type' => sanitize_text_field($rule_data['type']),
            'target_id' => absint($rule_data['target_id']),
            'target_name' => isset($rule_data['target_name']) ? sanitize_text_field($rule_data['target_name']) : '',
            'multiple' => absint($rule_data['multiple'])
        );

        if (!$this->validate_single_rule($new_rule)) {
            error_log('WCQM Debug: New rule validation failed');
            return false;
        }

        $this->rules[] = $new_rule;

        // Save rules
        if (!$this->save_rules()) {
            error_log('WCQM Debug: Failed to save rules after adding new rule');
            return false;
        }
        
        error_log('WCQM Debug: Successfully added new rule: ' . $rule_id);
        return $rule_id;
    }

    /**
     * Update an existing rule.
     *
     * @param string $rule_id Rule ID.
     * @param array $rule_data Rule data.
     * @return bool Whether the rule was updated successfully.
     */
    public function update_rule($rule_id, $rule_data) {
        $this->load_rules();
        
        foreach ($this->rules as $key => $rule) {
            if ($rule['id'] === $rule_id) {
                // Check for duplicate
                if (isset($rule_data['type'], $rule_data['target_id'])) {
                    foreach ($this->rules as $existing_rule) {
                        if ($existing_rule['id'] !== $rule_id &&
                            $existing_rule['type'] === $rule_data['type'] &&
                            $existing_rule['target_id'] === $rule_data['target_id']) {
                            return false; // Would create duplicate
                        }
                    }
                }
                
                // Update rule
                if (isset($rule_data['type'])) {
                    $this->rules[$key]['type'] = sanitize_text_field($rule_data['type']);
                }
                if (isset($rule_data['target_id'])) {
                    $this->rules[$key]['target_id'] = absint($rule_data['target_id']);
                }
                if (isset($rule_data['target_name'])) {
                    $this->rules[$key]['target_name'] = sanitize_text_field($rule_data['target_name']);
                }
                if (isset($rule_data['multiple'])) {
                    $multiple = absint($rule_data['multiple']);
                    if ($multiple < 1) {
                        $multiple = 1;
                    }
                    $this->rules[$key]['multiple'] = $multiple;
                }
                
                // Save rules
                $data = array(
                    'rules' => $this->rules,
                    'default_multiple' => $this->default_multiple
                );
                
                update_option('wcqm_settings', $data, 'yes');
                $this->product_rule_cache = array(); // Clear cache
                
                return true;
            }
        }
        
        return false;
    }

    /**
     * Delete a rule.
     *
     * @param string $rule_id Rule ID.
     * @return bool Whether the rule was deleted successfully.
     */
    public function delete_rule($rule_id) {
        $this->load_rules();
        
        foreach ($this->rules as $key => $rule) {
            if ($rule['id'] === $rule_id) {
                unset($this->rules[$key]);
                
                // Re-index array
                $this->rules = array_values($this->rules);
                
                // Save rules
                $data = array(
                    'rules' => $this->rules,
                    'default_multiple' => $this->default_multiple
                );
                
                update_option('wcqm_settings', $data, 'yes');
                $this->product_rule_cache = array(); // Clear cache
                
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get default multiple with validation
     */
    public function get_default_multiple() {
        // Use the instance variable that was loaded from settings
        $default = $this->default_multiple;
        
        // Ensure default is at least 1
        if ($default < 1) {
            $default = 1;
        }
        
        error_log('WCQM Debug: Using default multiple: ' . $default);
        return $default;
    }

    /**
     * Set default multiple.
     *
     * @param int $multiple Default multiple.
     * @return bool Whether the default multiple was updated successfully.
     */
    public function set_default_multiple($multiple) {
        $multiple = absint($multiple);
        
        // Ensure multiple is at least 1
        if ($multiple < 1) {
            $multiple = 1;
        }
        
        // Update the instance variable
        $this->default_multiple = $multiple;
        
        // Save to database
        $stored_data = get_option('wcqm_settings', array());
        $stored_data['default_multiple'] = $multiple;
        
        $result = update_option('wcqm_settings', $stored_data, 'yes');
        
        error_log('WCQM Debug: Default multiple updated to: ' . $multiple);
        return $result;
    }

    /**
     * Get product rule
     *
     * @param int $product_id Product ID
     * @return array|false Rule data or false if not found
     */
    public function get_product_rule($product_id) {
        // Convert to integer for comparison
        $product_id = intval($product_id);

        error_log('WCQM Debug: Checking product rule for product ' . $product_id);

        // Force rules to load if not already loaded
        if ($this->rules === null) {
            error_log('WCQM Debug: Rules not loaded, loading now');
            $this->load_rules(true);
        }

        // Clear cache for this product to ensure fresh data
        unset($this->product_rules_cache[$product_id]);

        // Search for product rule
        foreach ($this->rules as $rule) {
            if ($rule['type'] === 'product' && intval($rule['target_id']) === $product_id) {
                error_log('WCQM Debug: Found product-specific rule for product ' . $product_id . ': ' . print_r($rule, true));
                $this->product_rules_cache[$product_id] = $rule;
                return $rule;
            }
        }

        error_log('WCQM Debug: No product-specific rule found for product ' . $product_id);
        return false;
    }

    /**
     * Get a category rule
     *
     * @param int $category_id Category ID
     * @return array|false Rule data or false if not found
     */
    public function get_category_rule($category_id) {
        // Convert to integer for comparison
        $category_id = intval($category_id);

        // Check cache first
        if (isset($this->category_rules_cache[$category_id])) {
            error_log('WCQM Debug: Returning cached category rule for category ' . $category_id);
            return $this->category_rules_cache[$category_id];
        }

        // Load rules if needed
        if ($this->rules === null) {
            $this->load_rules();
        }

        // Search for category rule
        foreach ($this->rules as $rule) {
            if ($rule['type'] === 'category' && intval($rule['target_id']) === $category_id) {
                error_log('WCQM Debug: Found category rule for category ' . $category_id . ': ' . print_r($rule, true));
                $this->category_rules_cache[$category_id] = $rule;
                return $rule;
            }
        }

        error_log('WCQM Debug: No category rule found for category ' . $category_id);
        $this->category_rules_cache[$category_id] = false;
        return false;
    }

    /**
     * Get all rules at once for efficient bulk loading
     *
     * @return array Array of all rules
     */
    public function get_all_rules() {
        $stored_data = get_option('wcqm_settings', array(
            'rules' => array(),
            'default_multiple' => 10
        ));

        error_log('WCQM Debug: Bulk loading all rules from database');
        
        if (!isset($stored_data['rules']) || !is_array($stored_data['rules'])) {
            error_log('WCQM Debug: No rules found in database');
            return array();
        }

        // Ensure all rules have required fields
        $valid_rules = array_filter($stored_data['rules'], function($rule) {
            return isset($rule['type']) && 
                   isset($rule['target_id']) && 
                   isset($rule['multiple']) &&
                   in_array($rule['type'], array('product', 'category'));
        });

        error_log('WCQM Debug: Loaded ' . count($valid_rules) . ' valid rules');
        return $valid_rules;
    }

    /**
     * Get all rules data for frontend use
     * 
     * @return array All rules data formatted for frontend
     */
    public function get_all_rules_data() {
        // Load rules if not already loaded
        if (!$this->rules_loaded) {
            $this->load_rules(true);
        }
        
        // Initialize arrays
        $product_rules = array();
        $resolved_products = array();
        
        // Extract product-specific rules
        foreach ($this->rules as $rule) {
            if ($rule['type'] === 'product') {
                $product_id = absint($rule['target_id']);
                $product_rules[$product_id] = absint($rule['multiple']);
                $resolved_products[$product_id] = absint($rule['multiple']);
            }
        }
        
        // Make sure the product-category map is built
        $this->ensure_product_category_map();
        
        // Process all products to resolve their final multiples
        // This ensures products with category rules are included
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => 500,
            'post_status'    => 'publish'
        );
        
        $product_query = new WP_Query($args);
        if ($product_query->have_posts()) {
            foreach ($product_query->posts as $product_post) {
                $product_id = $product_post->ID;
                
                // Skip products we already have a direct rule for
                if (isset($resolved_products[$product_id])) {
                    continue;
                }
                
                // Get product categories
                $product = wc_get_product($product_id);
                if (!$product) continue;
                
                $categories = $product->get_category_ids();
                
                // Check for category rules
                foreach ($categories as $category_id) {
                    foreach ($this->rules as $rule) {
                        if ($rule['type'] === 'category' && absint($rule['target_id']) === absint($category_id)) {
                            $resolved_products[$product_id] = absint($rule['multiple']);
                            break 2; // Found a rule, break both loops
                        }
                    }
                }
            }
        }
        
        // Categories to include in the frontend data
        $category_rules = array();
        foreach ($this->rules as $rule) {
            if ($rule['type'] === 'category') {
                $category_id = absint($rule['target_id']);
                $category_rules[$category_id] = absint($rule['multiple']);
            }
        }

        // Build the data structure
        $rules_data = array(
            'default' => $this->default_multiple,
            'products' => $product_rules,
            'categories' => $category_rules,
            'resolved_products' => $resolved_products
        );

        return $rules_data;
    }

    /**
     * Inject rules data into the page for JavaScript use
     */
    public function inject_rules_data() {
        // Don't inject rules in admin except in product editing
        if (is_admin() && !is_callable('get_current_screen')) {
            return;
        }

        // In admin, only inject on product editing screens
        if (is_admin()) {
            $screen = get_current_screen();
            if (!$screen || $screen->id !== 'product') {
                return;
            }
        }

        // Get all rules data
        $rules_data = $this->get_all_rules_data();

        // Pre-resolve all product multiples for cart and checkout
        $resolved_products = array();
        if (is_cart() || is_checkout()) {
            // Ensure the product-category map is loaded
            $this->ensure_product_category_map();
            
            // For each product in the cart, pre-resolve its multiple
            $cart_items = WC()->cart ? WC()->cart->get_cart() : array();
            
            foreach ($cart_items as $cart_item) {
                $product_id = $cart_item['product_id'];
                $variation_id = isset($cart_item['variation_id']) && $cart_item['variation_id'] ? $cart_item['variation_id'] : 0;
                
                // Check for variation first, then product
                $multiple = $this->get_product_multiple($variation_id > 0 ? $variation_id : $product_id);
                
                // Add to resolved products array
                $resolved_products[$product_id] = $multiple;
                
                if ($variation_id > 0) {
                    $resolved_products[$variation_id] = $multiple;
                }
                
                error_log("WCQM Debug: Pre-resolved multiple for cart product {$product_id}: {$multiple}");
            }
        }
        
        // Add resolved products to the rules data
        $rules_data['resolved_products'] = $resolved_products;

        // Add product-category mapping for JavaScript
        $rules_data['product_categories'] = array();
        
        // Ensure the product-category map is loaded
        $this->ensure_product_category_map();
        
        // Add the product category mapping for all products in cart or on the current page
        if (!empty($this->product_category_map)) {
            $rules_data['product_categories'] = $this->product_category_map;
            error_log('WCQM Debug: Added category mapping for ' . count($this->product_category_map) . ' products to JS data');
        }

        // Current product info when on a product page
        if (is_product()) {
            global $product;
            if ($product) {
                $rules_data['current_product_id'] = $product->get_id();
                
                // Add category mapping for the current product if not already there
                if (!isset($rules_data['product_categories'][$product->get_id()])) {
                    $categories = $this->get_product_categories($product->get_id());
                    if (!empty($categories)) {
                        $rules_data['product_categories'][$product->get_id()] = $categories;
                    }
                }
            }
        }

        // Output the data as JavaScript
        echo '<script type="text/javascript">
            /* <![CDATA[ */
            var wcqm_rules = ' . json_encode($rules_data) . ';
            /* ]]> */
        </script>';
        
        error_log('WCQM Debug: Rules data injected with ' . count($resolved_products) . ' pre-resolved products');
    }

    /**
     * Ensure we have a mapping of products to their categories
     */
    private function ensure_product_category_map() {
        if ($this->product_category_map !== null) {
            return;
        }
        
        $this->product_category_map = array();
        
        error_log('WCQM Debug: Building product-category map');
        
        // Get all product IDs with categories
        global $wpdb;
        $product_term_query = "
            SELECT p.ID as product_id, tt.term_id as category_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'product_cat'
        ";
        
        $results = $wpdb->get_results($product_term_query);
        
        if (!empty($results)) {
            foreach ($results as $row) {
                $product_id = intval($row->product_id);
                $category_id = intval($row->category_id);
                
                if (!isset($this->product_category_map[$product_id])) {
                    $this->product_category_map[$product_id] = array();
                }
                
                $this->product_category_map[$product_id][] = $category_id;
                
                // Also add parent categories
                $parent_categories = $this->get_parent_categories($category_id);
                if (!empty($parent_categories)) {
                    foreach ($parent_categories as $parent_id) {
                        if (!in_array($parent_id, $this->product_category_map[$product_id])) {
                            $this->product_category_map[$product_id][] = $parent_id;
                        }
                    }
                }
            }
        }
        
        error_log('WCQM Debug: Built product-category map for ' . count($this->product_category_map) . ' products');
    }

    /**
     * Maybe restore rules from backup on init
     */
    public function maybe_restore_rules() {
        $current_version = get_option(self::RULES_VERSION_OPTION);
        
        if ($current_version !== self::CURRENT_RULES_VERSION) {
            error_log('WCQM Debug: Rules version mismatch, checking data integrity');
            $this->load_rules(true);
        }
    }

    /**
     * Maybe backup rules on shutdown
     */
    public function maybe_backup_rules() {
        if (!is_null($this->rules)) {
            $this->save_rules(array(
                'rules' => $this->rules,
                'default_multiple' => $this->get_default_multiple()
            ));
        }
    }

    /**
     * Render rules tab content
     */
    public function render_rules_tab() {
        if (!function_exists('_e') || !function_exists('esc_attr')) {
            return;
        }
        ?>
        <div class="wcqm-settings-section">
            <h2><?php _e('Default Multiple', 'wc-quantity-multiples'); ?></h2>
            <div class="wcqm-settings-content">
                <div class="wcqm-setting-row">
                    <label for="wcqm-default-multiple"><?php _e('Default Multiple:', 'wc-quantity-multiples'); ?></label>
                    <input type="number" id="wcqm-default-multiple" name="wcqm-default-multiple" min="1" value="<?php echo esc_attr($this->get_default_multiple()); ?>" />
                    <p class="description"><?php _e('This value will be used when no specific rule applies to a product.', 'wc-quantity-multiples'); ?></p>
                    
                    <button type="button" class="button button-primary wcqm-save-default"><?php _e('Save Default', 'wc-quantity-multiples'); ?></button>
                    <span class="spinner wcqm-spinner"></span>
                </div>
            </div>
            
            <hr />
            
            <h2><?php _e('Quantity Rules', 'wc-quantity-multiples'); ?></h2>
            
            <!-- Rest of the existing rules tab content -->
        </div>
        <?php
    }

    /**
     * Clear cache and reset internal state
     */
    public function clear_cache() {
        error_log('WCQM Debug: Clearing rules cache');
        
        // Reset internal state
        $this->rules = null;
        $this->rules_by_type = null;
        $this->product_category_map = null;
        $this->rules_loaded = false;
        
        // Clear any transients if we add them in the future
        delete_transient('wcqm_rules_cache');
        
        error_log('WCQM Debug: Rules cache cleared');
    }
}

// Initialize the rules manager class
WCQM_Rules_Manager::instance(); 