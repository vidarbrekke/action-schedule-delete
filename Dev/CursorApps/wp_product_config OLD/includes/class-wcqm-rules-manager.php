<?php
/**
 * WC Quantity Multiples - Rules Manager
 * 
 * Handles the management, storage, and retrieval of quantity rules.
 *
 * @package WC_Quantity_Multiples
 * @since 1.1.0
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

// Check for WordPress functions
if ( ! function_exists( 'add_action' ) || 
    ! function_exists( 'add_filter' ) || 
    ! function_exists( 'get_option' ) || 
    ! function_exists( 'update_option' ) ) {
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
    protected static $_instance = null;

    /**
     * Rules array.
     *
     * @var array
     */
    protected $rules = null;

    /**
     * Option name for storing rules
     */
    const RULES_OPTION = 'wcqm_rules';
    const RULES_BACKUP_OPTION = 'wcqm_rules_backup';
    const RULES_VERSION_OPTION = 'wcqm_rules_version';
    const PRODUCT_OVERRIDES_OPTION = 'wcqm_product_overrides';
    const CURRENT_RULES_VERSION = '1.1.0';

    /**
     * Default multiple value.
     *
     * @var int
     */
    protected $default_multiple = 10;

    /**
     * Flag to indicate if rules have been loaded.
     *
     * @var bool
     */
    protected $rules_loaded = false;

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
     * Product-specific overrides
     * 
     * @var array
     */
    protected $product_overrides = array();

    /**
     * Organized rules by type
     *
     * @var array
     */
    protected $rules_by_type = array();

    /**
     * Main Rules Manager Instance.
     *
     * @return WCQM_Rules_Manager - Main instance.
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
        // Load rules immediately on construction
        $this->load_rules(true);
        
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
        
        // Load product overrides
        $this->load_product_overrides();
        
        error_log('WCQM Debug: Rules manager initialized');
    }

    /**
     * Load rules from database with validation and backup handling
     */
    public function load_rules($force = false) {
        if (!is_null($this->rules) && !$force) {
            error_log('WCQM Debug: Rules already loaded, skipping load');
            return;
        }

        // Get rules from database with validation
        $stored_data = get_option('wcqm_settings', array());
        
        // Set default values if data is invalid
        if (!$this->validate_rules_data($stored_data)) {
            error_log('WCQM Debug: Invalid rules data found, initializing defaults');
            $stored_data = array(
                'rules' => array(),
                'default_multiple' => 10
            );
        }

        // Set default multiple
        $this->default_multiple = isset($stored_data['default_multiple']) ? absint($stored_data['default_multiple']) : 10;
        
        // Organize rules by type for easier lookup
        $rules_by_type = array(
            'products' => array(),
            'categories' => array()
        );
        
        if (isset($stored_data['rules']) && is_array($stored_data['rules'])) {
            foreach ($stored_data['rules'] as $rule) {
                if (!$this->validate_single_rule($rule)) {
                    continue;
                }
                
                if ($rule['type'] === 'product') {
                    $rules_by_type['products'][$rule['target_id']] = absint($rule['multiple']);
                } elseif ($rule['type'] === 'category') {
                    $rules_by_type['categories'][$rule['target_id']] = absint($rule['multiple']);
                }
            }
        }
        
        // Store the original rules array for backward compatibility
        $this->rules = isset($stored_data['rules']) ? (array)$stored_data['rules'] : array();
        
        // Also store the organized rules for faster lookups
        $this->rules_by_type = $rules_by_type;
        
        // Validate each rule in the original array (for backward compatibility)
        $this->rules = array_filter($this->rules, array($this, 'validate_single_rule'));
        $this->rules = array_values($this->rules); // Re-index array
        
        error_log('WCQM Debug: Rules loaded successfully - ' . count($this->rules) . ' valid rules');
        
        $this->rules_loaded = true;
    }

    /**
     * Save rules to database
     */
    public function save_rules($rules = null) {
        if (is_null($rules)) {
            $rules = $this->rules;
        }

        // Ensure rules is an array
        if (!is_array($rules)) {
            error_log('WCQM Debug: Rules data is not an array, initializing empty array');
            $rules = array();
        }

        // If rules is passed as an array with 'rules' key, extract it
        if (isset($rules['rules'])) {
            $default_multiple = isset($rules['default_multiple']) ? absint($rules['default_multiple']) : $this->default_multiple;
            $rules = $rules['rules'];
            error_log('WCQM Debug: Extracted rules from data array, default multiple: ' . $default_multiple);
        } else {
            $default_multiple = $this->default_multiple;
        }

        // Validate rules before saving
        $rules = array_filter((array)$rules, array($this, 'validate_single_rule'));
        $rules = array_values($rules); // Re-index array
        error_log('WCQM Debug: Validated rules array - ' . count($rules) . ' valid rules');

        $data_to_save = array(
            'rules' => $rules,
            'default_multiple' => $default_multiple
        );

        // Save to database with autoload disabled for better performance
        delete_option('wcqm_settings'); // Clean up first
        $result = add_option('wcqm_settings', $data_to_save, '', 'no');
        
        if (!$result) {
            // If add_option failed, try update_option
            $result = update_option('wcqm_settings', $data_to_save, 'no');
        }
        
        // Force rules to reload on next access
        $this->rules = null;
        $this->product_rule_cache = array();
        $this->category_rules_cache = array();
        
        error_log('WCQM Debug: Rules save attempt completed. Result: ' . ($result ? 'success' : 'failed'));
        error_log('WCQM Debug: Saved data structure: ' . print_r($data_to_save, true));
        
        return $result;
    }

    /**
     * Validate rules data structure
     */
    protected function validate_rules_data($data) {
        if (!is_array($data)) {
            return false;
        }

        if (!isset($data['rules']) || !is_array($data['rules'])) {
            return false;
        }

        if (!isset($data['default_multiple']) || !is_numeric($data['default_multiple'])) {
            return false;
        }

        return true;
    }

    /**
     * Validate a single rule
     */
    protected function validate_single_rule($rule) {
        if (!is_array($rule)) {
            return false;
        }

        $required_fields = array('id', 'type', 'target_id', 'multiple');
        foreach ($required_fields as $field) {
            if (!isset($rule[$field])) {
                return false;
            }
        }

        if (!in_array($rule['type'], array('product', 'category'))) {
            return false;
        }

        if (!is_numeric($rule['multiple']) || $rule['multiple'] < 1) {
            return false;
        }

        return true;
    }

    /**
     * Get multiple for a specific product
     *
     * @param int $product_id Product ID
     * @return int Multiple value
     */
    public function get_product_multiple($product_id) {
        // Load rules if not already loaded
        if (!$this->rules_loaded) {
            $this->load_rules();
        }
        
        // Check cache first
        if (isset($this->product_rule_cache[$product_id])) {
            error_log("WCQM Debug: get_product_multiple - Using cached value for product {$product_id}: {$this->product_rule_cache[$product_id]}");
            return $this->product_rule_cache[$product_id];
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            error_log("WCQM Debug: get_product_multiple - Product {$product_id} not found, using default multiple: {$this->default_multiple}");
            $this->product_rule_cache[$product_id] = $this->default_multiple;
            return $this->default_multiple;
        }

        // Get product categories
        $categories = $product->get_category_ids();
        error_log("WCQM Debug: get_product_multiple - Product {$product_id} categories: " . implode(',', $categories));

        // Check product-specific rules first (highest priority)
        if (isset($this->rules_by_type['products'][$product_id])) {
            $multiple = $this->rules_by_type['products'][$product_id];
            error_log("WCQM Debug: get_product_multiple - Found product-specific rule with multiple {$multiple} for product {$product_id}");
            $this->product_rule_cache[$product_id] = $multiple;
            return $multiple;
        }
        
        // Check for category rules
        foreach ($categories as $category_id) {
            if (isset($this->rules_by_type['categories'][$category_id])) {
                $multiple = $this->rules_by_type['categories'][$category_id];
                error_log("WCQM Debug: get_product_multiple - Found category rule {$category_id} with multiple {$multiple} for product {$product_id}");
                $this->product_rule_cache[$product_id] = $multiple;
                return $multiple;
            }
        }
        
        // If no rules found, use default
        error_log("WCQM Debug: get_product_multiple - No rules found, using default multiple {$this->default_multiple} for product {$product_id}");
        $this->product_rule_cache[$product_id] = $this->default_multiple;
        return $this->default_multiple;
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
     *
     * @param array $rule_data Rule data.
     * @return string|false Rule ID if added successfully, false otherwise.
     */
    public function add_rule($rule_data) {
        if (!isset($rule_data['type'], $rule_data['target_id'], $rule_data['multiple'])) {
            error_log('WCQM Debug: Missing required fields in rule data');
            return false;
        }

        $this->load_rules();

        // Check for duplicate rule
        foreach ($this->rules as $rule) {
            if ($rule['type'] === $rule_data['type'] && $rule['target_id'] === $rule_data['target_id']) {
                error_log('WCQM Debug: Duplicate rule found for ' . $rule_data['type'] . ' ' . $rule_data['target_id']);
                return false; // Duplicate rule
            }
        }

        // Generate rule ID if not provided
        $rule_id = isset($rule_data['id']) ? $rule_data['id'] : 'rule_' . uniqid();

        // Add rule
        $new_rule = array(
            'id' => $rule_id,
            'type' => sanitize_text_field($rule_data['type']),
            'target_id' => absint($rule_data['target_id']),
            'target_name' => isset($rule_data['target_name']) ? sanitize_text_field($rule_data['target_name']) : '',
            'multiple' => absint($rule_data['multiple'])
        );

        $this->rules[] = $new_rule;

        // Save rules to database
        $save_result = $this->save_rules();
        
        if ($save_result) {
            error_log('WCQM Debug: Rule added and saved successfully. ID: ' . $rule_id);
            
            // Clear all caches
            $this->clear_rule_cache();
            
            // Refresh rules data
            $this->refresh_rules();
            
            return $rule_id;
        }

        error_log('WCQM Debug: Failed to save rule. ID: ' . $rule_id);
        return false;
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
     * @return int|false Multiple value or false if no rule exists
     */
    public function get_category_rule($category_id) {
        // Check cache first
        if (isset($this->category_rules_cache[$category_id])) {
            return $this->category_rules_cache[$category_id];
        }

        // Load rules if needed
        if ($this->rules === null) {
            $this->load_rules();
        }

        // Search for category rule
        foreach ($this->rules as $rule) {
            if ($rule['type'] === 'category' && intval($rule['target_id']) === intval($category_id)) {
                $multiple = absint($rule['multiple']);
                $this->category_rules_cache[$category_id] = $multiple;
                error_log("WCQM Debug: Found category rule for {$category_id}: {$multiple}");
                return $multiple;
            }
        }

        // Also check parent categories
        $parent_categories = $this->get_parent_categories($category_id);
        foreach ($parent_categories as $parent_id) {
            foreach ($this->rules as $rule) {
                if ($rule['type'] === 'category' && intval($rule['target_id']) === intval($parent_id)) {
                    $multiple = absint($rule['multiple']);
                    $this->category_rules_cache[$category_id] = $multiple;
                    error_log("WCQM Debug: Found parent category rule for {$category_id} (parent {$parent_id}): {$multiple}");
                    return $multiple;
                }
            }
        }

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
     */
    public function get_all_rules_data() {
        // Load rules if not already loaded
        if (is_null($this->rules)) {
            $this->load_rules();
        }

        // Get all product IDs that have specific rules
        $product_ids = array();
        foreach ($this->rules as $rule) {
            if ($rule['type'] === 'product' && !empty($rule['product_id'])) {
                $product_ids[] = (int)$rule['product_id'];
            }
        }

        // Count total products
        $total_products = count($product_ids);
        if ($total_products > 100) {
            error_log("WCQM Debug: Large product list ({$total_products}), limiting pre-resolving to 100 products");
        }

        // Prioritize products with specific rules first, then fill remaining slots
        $products_to_resolve = array_slice($product_ids, 0, 100);

        // Pre-resolve multiples for the limited set of products
        $resolved_products = array();
        foreach ($products_to_resolve as $product_id) {
            $multiple = $this->get_product_multiple($product_id);
            if ($multiple !== $this->default_multiple) {
                $resolved_products[$product_id] = $multiple;
            }
        }

        // Build the data structure
        $rules_data = array(
            'default' => $this->default_multiple,
            'categories' => array(),
            'resolved_products' => $resolved_products,
            'product_categories' => array()
        );

        // Add category rules
        foreach ($this->rules as $rule) {
            if ($rule['type'] === 'category' && !empty($rule['category_id'])) {
                $rules_data['categories'][(int)$rule['category_id']] = (int)$rule['multiple'];
            }
        }

        return $rules_data;
    }

    /**
     * Inject rules data into the page for JavaScript use
     */
    public function inject_rules_data() {
        static $already_injected = false;
        
        // Prevent multiple injections in the same request
        if ($already_injected) {
            return;
        }
        
        error_log('WCQM Debug: Starting rules data injection');
        
        // Get all rules data for the frontend
        $rules_data = array(
            'rules' => $this->rules,
            'default_multiple' => $this->default_multiple,
            'product_categories' => is_array($this->product_category_map) ? $this->product_category_map : array()
        );
        
        // Ensure product categories exist as an object
        if (!isset($rules_data['product_categories']) || !is_array($rules_data['product_categories'])) {
            $rules_data['product_categories'] = array();
        }
        
        // Encode the data for JavaScript
        $json_data = json_encode($rules_data);
        
        if ($json_data === false) {
            error_log('WCQM Error: Failed to encode rules data for JavaScript');
            return;
        }
        
        // Output the data
        echo '<script type="text/javascript">';
        echo 'var wcqm_rules_data = ' . $json_data . ';';
        echo '</script>';
        
        error_log('WCQM Debug: Rules data injection completed');
        
        $already_injected = true;
    }

    /**
     * Ensure the product-category map is built
     */
    protected function ensure_product_category_map() {
        if (!is_null($this->product_category_map)) {
            return;
        }

        global $wpdb;
        
        // Initialize empty map
        $this->product_category_map = array();
        
        // Log query once for debugging
        $sql = "SELECT tr.object_id as product_id, tt.term_id as category_id 
                FROM {$wpdb->term_relationships} tr 
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
                WHERE tt.taxonomy = 'product_cat' 
                LIMIT 5000";
        error_log('WCQM Debug: Executing product-category query: ' . $sql);
        
        // Get product-category relationships
        $results = $wpdb->get_results($sql);
        
        if (empty($results)) {
            error_log('WCQM Debug: No product-category relationships found');
            return;
        }
        
        // Log total number found
        $total_relationships = count($results);
        error_log("WCQM Debug: Found {$total_relationships} product-category relationships");
        
        // Build the map with limited logging
        $logged_count = 0;
        foreach ($results as $row) {
            $product_id = (int)$row->product_id;
            $category_id = (int)$row->category_id;
            
            if (!isset($this->product_category_map[$product_id])) {
                $this->product_category_map[$product_id] = array();
            }
            
            $this->product_category_map[$product_id][] = $category_id;
            
            // Log only first 5 products for debugging
            if ($logged_count < 5) {
                error_log("WCQM Debug: Product {$product_id} has category {$category_id}");
                $logged_count++;
                if ($logged_count === 5) {
                    error_log('WCQM Debug: Limiting category detail logging to first 5 products');
                }
            }
        }
    }

    /**
     * Maybe restore rules from backup on init
     */
    public function maybe_restore_rules() {
        $current_version = get_option(self::RULES_VERSION_OPTION);
        
        if ($current_version !== self::CURRENT_RULES_VERSION) {
            error_log('WCQM Debug: Rules version mismatch, checking data integrity');
            
            // Load rules to ensure we have the latest data
            $this->load_rules(true);
            
            // Update the version number
            $result = update_option(self::RULES_VERSION_OPTION, self::CURRENT_RULES_VERSION);
            error_log('WCQM Debug: Updated rules version to ' . self::CURRENT_RULES_VERSION . ' - Result: ' . ($result ? 'success' : 'failed'));
            
            // Force a refresh of all rules
            $refresh_result = $this->refresh_rules();
            error_log('WCQM Debug: Rules refresh after version update - Result: ' . ($refresh_result ? 'success' : 'failed'));
            
            // Ensure rules are saved with the new version
            $save_result = $this->save_rules();
            error_log('WCQM Debug: Rules save after version update - Result: ' . ($save_result ? 'success' : 'failed'));
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
     * Load product override multiples from the database
     */
    public function load_product_overrides() {
        $this->product_overrides = get_option(self::PRODUCT_OVERRIDES_OPTION, array());
        
        if (!is_array($this->product_overrides)) {
            $this->product_overrides = array();
        }
        
        error_log('WCQM Debug: Loaded ' . count($this->product_overrides) . ' product overrides');
    }

    /**
     * Save product override multiples to the database
     * 
     * @return bool True on success, false on failure
     */
    public function save_product_overrides() {
        return update_option(self::PRODUCT_OVERRIDES_OPTION, $this->product_overrides);
    }

    /**
     * Set a product-specific multiple override
     * 
     * @param int $product_id The product ID
     * @param int $multiple The multiple value to set
     * @return bool True on success, false on failure
     */
    public function set_product_override($product_id, $multiple) {
        $product_id = absint($product_id);
        $multiple = absint($multiple);
        
        if ($product_id < 1 || $multiple < 1) {
            return false;
        }
        
        // Store the override
        $this->product_overrides[$product_id] = $multiple;
        
        // Clear any cached rules for this product
        unset($this->product_rule_cache[$product_id]);
        
        // Save to database
        return $this->save_product_overrides();
    }

    /**
     * Remove a product-specific multiple override
     * 
     * @param int $product_id The product ID
     * @return bool True on success, false on failure
     */
    public function remove_product_override($product_id) {
        $product_id = absint($product_id);
        
        if (!isset($this->product_overrides[$product_id])) {
            return false;
        }
        
        // Remove the override
        unset($this->product_overrides[$product_id]);
        
        // Clear any cached rules for this product
        unset($this->product_rule_cache[$product_id]);
        
        // Save to database
        return $this->save_product_overrides();
    }

    /**
     * Get a product-specific multiple override
     * 
     * @param int $product_id The product ID
     * @return int|false The multiple value or false if no override exists
     */
    public function get_product_override($product_id) {
        $product_id = absint($product_id);
        
        if (isset($this->product_overrides[$product_id])) {
            return absint($this->product_overrides[$product_id]);
        }
        
        return false;
    }

    /**
     * Get all product-specific multiple overrides
     * 
     * @return array Array of product ID => multiple pairs
     */
    public function get_all_product_overrides() {
        return $this->product_overrides;
    }

    /**
     * Clear the rule cache for a specific product
     *
     * @param int $product_id The product ID
     */
    public function clear_product_rule_cache($product_id = null) {
        if ($product_id === null) {
            $this->product_rule_cache = array();
            $this->category_rules_cache = array();
        } else {
            unset($this->product_rule_cache[$product_id]);
        }
    }

    /**
     * Force a refresh of all rules and clear caches
     */
    public function refresh_rules() {
        // Clear all caches
        $this->rules = null;
        $this->product_rule_cache = array();
        $this->product_rules_cache = array();
        $this->category_rules_cache = array();
        $this->product_category_map = null;

        // Force reload of rules
        $this->load_rules(true);

        // Log the refresh
        error_log('WCQM Debug: Rules refreshed. Total rules: ' . count($this->rules));

        // Trigger rules data refresh for JavaScript
        do_action('wcqm_rules_updated');
        
        // Update JavaScript rules data if script is loaded
        if (wp_script_is('wcqm-quantity', 'enqueued')) {
            wp_localize_script(
                'wcqm-quantity',
                'wcqm_rules',
                $this->get_all_rules_data()
            );
        }

        return true;
    }
}

// Initialize the rules manager class
WCQM_Rules_Manager::instance(); 