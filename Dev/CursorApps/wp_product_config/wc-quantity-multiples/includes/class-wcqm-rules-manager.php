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

// Ensure WordPress core functions are available
require_once(ABSPATH . 'wp-includes/functions.php');
require_once(ABSPATH . 'wp-includes/plugin.php');
require_once(ABSPATH . 'wp-includes/post.php');
require_once(ABSPATH . 'wp-includes/taxonomy.php');

// Ensure WooCommerce is active and available
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
    const CURRENT_RULES_VERSION = '1.0.0';

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

        // Set instance variables
        $this->rules = isset($stored_data['rules']) ? (array)$stored_data['rules'] : array();
        $this->default_multiple = isset($stored_data['default_multiple']) ? absint($stored_data['default_multiple']) : 10;
        
        // Validate each rule
        $this->rules = array_filter($this->rules, array($this, 'validate_single_rule'));
        $this->rules = array_values($this->rules); // Re-index array
        
        error_log('WCQM Debug: Rules loaded successfully - ' . count($this->rules) . ' valid rules');
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
            $rules = array();
        }

        // If rules is passed as an array with 'rules' key, extract it
        if (isset($rules['rules'])) {
            $default_multiple = isset($rules['default_multiple']) ? absint($rules['default_multiple']) : $this->default_multiple;
            $rules = $rules['rules'];
        } else {
            $default_multiple = $this->default_multiple;
        }

        // Validate rules before saving
        $rules = array_filter((array)$rules, array($this, 'validate_single_rule'));
        $rules = array_values($rules); // Re-index array

        $data_to_save = array(
            'rules' => $rules,
            'default_multiple' => $default_multiple
        );

        // Save to database
        $result = update_option('wcqm_settings', $data_to_save, 'yes');
        
        // Force rules to reload on next access
        $this->rules = null;
        $this->product_rule_cache = array();
        $this->category_rules_cache = array();
        
        error_log('WCQM Debug: Rules saved successfully. Result: ' . ($result ? 'true' : 'false'));
        
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
     * Get the multiple value for a product, considering all applicable rules
     */
    public function get_product_multiple($product_id) {
        // Make sure rules are loaded
        if (is_null($this->rules)) {
            $this->load_rules();
        }
        
        // Debug what rules we have
        error_log("WCQM Debug: get_product_multiple for product {$product_id}");
        
        // Check if we have a product-specific rule in our flat array structure
        foreach ($this->rules as $rule) {
            if ($rule['type'] === 'product' && absint($rule['target_id']) === absint($product_id)) {
                error_log("WCQM: Found product rule for product {$product_id}: " . $rule['multiple']);
                return absint($rule['multiple']);
            }
        }
        
        // Check for product category rules
        $product_cats = $this->get_product_categories($product_id);
        if (!empty($product_cats)) {
            error_log("WCQM Debug: Product categories for product {$product_id}: " . implode(',', $product_cats));
            
            // Search for category rules
            foreach ($this->rules as $rule) {
                if ($rule['type'] === 'category') {
                    $category_id = absint($rule['target_id']);
                    if (in_array($category_id, $product_cats)) {
                        error_log("WCQM: Found category rule for product {$product_id} (category {$category_id}): " . $rule['multiple']);
                        return absint($rule['multiple']);
                    }
                }
            }
            
            // Check for parent category rules
            foreach ($product_cats as $cat_id) {
                $parent_categories = $this->get_parent_categories($cat_id);
                foreach ($parent_categories as $parent_cat_id) {
                    foreach ($this->rules as $rule) {
                        if ($rule['type'] === 'category' && absint($rule['target_id']) === absint($parent_cat_id)) {
                            error_log("WCQM: Found parent category rule for product {$product_id} (parent category {$parent_cat_id}): " . $rule['multiple']);
                            return absint($rule['multiple']);
                        }
                    }
                }
            }
        } else {
            error_log("WCQM Debug: No categories found for product {$product_id}");
        }
        
        // Default multiple
        $default = $this->get_default_multiple();
        error_log("WCQM Debug: Using default multiple for product {$product_id}: {$default}");
        return $default;
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
            return false;
        }

        $this->load_rules();

        // Check for duplicate rule
        foreach ($this->rules as $rule) {
            if ($rule['type'] === $rule_data['type'] && $rule['target_id'] === $rule_data['target_id']) {
                return false; // Duplicate rule
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

        $this->rules[] = $new_rule;

        // Save rules
        $data = array(
            'rules' => $this->rules,
            'default_multiple' => $this->default_multiple
        );

        update_option('wcqm_settings', $data, 'yes');
        $this->product_rule_cache = array(); // Clear cache
        
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
     * Get all rules data formatted for frontend use
     */
    public function get_all_rules_data() {
        // Force a fresh load of rules
        $this->load_rules(true);
        
        error_log('WCQM Debug: Loading rules data for frontend');
        
        // Get the correct default multiple
        $default_multiple = $this->get_default_multiple();
        error_log('WCQM Debug: Using default multiple: ' . $default_multiple);
        
        // Initialize data structure
        $data = array(
            'default' => $default_multiple,
            'products' => array(),
            'categories' => array(),
            'resolved_products' => array() // New field for pre-resolved product multiples
        );
        
        // Process rules - create lookup arrays for faster frontend access
        if (!empty($this->rules)) {
            foreach ($this->rules as $rule) {
                // Ensure we have the required fields
                if (!isset($rule['type']) || !isset($rule['target_id']) || !isset($rule['multiple'])) {
                    continue;
                }
                
                // Create lookup arrays based on type
                if ($rule['type'] === 'product' && !empty($rule['target_id'])) {
                    $data['products'][$rule['target_id']] = absint($rule['multiple']);
                    error_log("WCQM Debug: Added product rule - ID: {$rule['target_id']}, Multiple: {$rule['multiple']}");
                } elseif ($rule['type'] === 'category' && !empty($rule['target_id'])) {
                    $data['categories'][$rule['target_id']] = absint($rule['multiple']);
                    error_log("WCQM Debug: Added category rule - ID: {$rule['target_id']}, Multiple: {$rule['multiple']}");
                }
            }
        }
        
        // Build product to category mapping - needed for category resolution
        $this->ensure_product_category_map();
        
        // Pre-resolve all product multiples to simplify frontend processing
        $product_ids = array();
        
        // Get IDs of all products and products with categories
        if (!empty($this->product_category_map)) {
            $product_ids = array_unique(array_merge($product_ids, array_keys($this->product_category_map)));
        }
        
        // Add products with direct rules
        if (!empty($data['products'])) {
            $product_ids = array_unique(array_merge($product_ids, array_keys($data['products'])));
        }
        
        // Get all published products if our list is small
        if (count($product_ids) < 100) {
            global $wpdb;
            $all_products = $wpdb->get_col("
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'product'
                AND post_status = 'publish'
                LIMIT 1000
            ");
            if (!empty($all_products)) {
                $product_ids = array_unique(array_merge($product_ids, $all_products));
            }
        }
        
        error_log('WCQM Debug: Pre-resolving multiples for ' . count($product_ids) . ' products');
        
        // Resolve the multiple for each product
        foreach ($product_ids as $product_id) {
            $multiple = $this->get_product_multiple($product_id);
            if ($multiple === $default_multiple) {
                error_log("WCQM Debug: Product {$product_id} using default multiple: {$default_multiple}");
            }
            $data['resolved_products'][$product_id] = $multiple;
        }
        
        error_log('WCQM Debug: Pre-resolved ' . count($data['resolved_products']) . ' products total');
        error_log('WCQM Debug: Final frontend rules data structure ready');
        
        return $data;
    }

    /**
     * Inject rules data into the page
     */
    public function inject_rules_data() {
        // Only inject on relevant pages
        if (!is_admin() && !is_product() && !is_cart() && !is_checkout()) {
            return;
        }
        
        $rules_data = $this->get_all_rules_data();
        
        if (empty($rules_data)) {
            error_log('WCQM Debug: No rules data to inject');
            return;
        }
        
        error_log('WCQM Debug: Injecting rules data into page');
        
        ?>
        <script type="text/javascript">
            /* <![CDATA[ */
            var wcqm_rules = <?php echo wp_json_encode($rules_data); ?>;
            /* ]]> */
        </script>
        <?php
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
}

// Initialize the rules manager class
WCQM_Rules_Manager::instance(); 