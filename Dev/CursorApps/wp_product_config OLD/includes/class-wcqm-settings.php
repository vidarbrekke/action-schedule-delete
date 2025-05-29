<?php
/**
 * WC Quantity Multiples - Admin Settings
 * 
 * Handles the admin settings page for managing quantity rules.
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
require_once(ABSPATH . 'wp-admin/includes/plugin.php');
require_once(ABSPATH . 'wp-includes/post.php');
require_once(ABSPATH . 'wp-includes/class-wp-query.php');

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
 * WCQM_Settings Class
 * 
 * Handles the admin settings page for managing quantity rules.
 */
class WCQM_Settings {
    /**
     * The single instance of the class.
     *
     * @var WCQM_Settings
     */
    protected static $_instance = null;

    /**
     * Rules manager instance.
     *
     * @var WCQM_Rules_Manager
     */
    protected $rules_manager;

    /**
     * Main Settings Instance.
     *
     * @return WCQM_Settings - Main instance.
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
        // Get rules manager
        $this->rules_manager = WCQM_Rules_Manager::instance();

        // Add menu items and initialize admin functionality
        $this->init_admin();
    }

    /**
     * Initialize admin functionality
     */
    private function init_admin() {
        // Register admin menu
        add_action('admin_menu', array($this, 'add_menu_items'));
        
        // Add AJAX handlers
        add_action('wp_ajax_wcqm_save_rule', array($this, 'ajax_save_rule'));
        add_action('wp_ajax_wcqm_delete_rule', array($this, 'ajax_delete_rule'));
        add_action('wp_ajax_wcqm_search_products', array($this, 'ajax_search_products'));
        add_action('wp_ajax_wcqm_search_categories', array($this, 'ajax_search_categories'));
        add_action('wp_ajax_wcqm_get_rules', array($this, 'ajax_get_rules'));
        add_action('wp_ajax_wcqm_save_default_multiple', array($this, 'ajax_save_default_multiple'));
        
        // Add AJAX handlers for product overrides
        add_action('wp_ajax_wcqm_add_product_override', array($this, 'ajax_add_product_override'));
        add_action('wp_ajax_wcqm_delete_product_override', array($this, 'ajax_delete_product_override'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Current page hook.
     */
    public function enqueue_admin_scripts($hook) {
        // The hook for our settings page will be toplevel_page_wc-quantity-multiples
        if ($hook !== 'toplevel_page_wc-quantity-multiples') {
            return;
        }
        
        // Log the current hook for debugging
        error_log('WCQM Settings: Loading admin scripts for hook: ' . $hook);
        
        // First, load jQuery and jQuery UI
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        
        // Explicitly load Select2 directly from CDN to ensure it works
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0-rc.0', true);

        // Enqueue admin CSS
        wp_enqueue_style(
            'wcqm-admin-style',
            WCQM_PLUGIN_URL . 'assets/css/wcqm-admin.css',
            array('select2-css'),
            WCQM_VERSION
        );
        
        // Enqueue admin JS with debug wrapper
        wp_enqueue_script(
            'wcqm-admin',
            WCQM_PLUGIN_URL . 'assets/js/wcqm-admin.js',
            array('jquery', 'select2-js'),
            WCQM_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script(
            'wcqm-admin',
            'wcqm_admin_vars',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcqm_admin_nonce'),
                'delete_confirm' => __('Are you sure you want to delete this rule?', 'wc-quantity-multiples'),
                'delete_error' => __('Failed to delete rule.', 'wc-quantity-multiples'),
                'save_error' => __('Failed to save settings.', 'wc-quantity-multiples'),
                'ajax_error' => __('An error occurred while processing your request.', 'wc-quantity-multiples'),
                'no_rules' => __('No rules configured yet.', 'wc-quantity-multiples')
            )
        );
        
        // Add inline script to debug Select2 initialization
        $debug_script = <<<'JAVASCRIPT'
            console.log("WCQM Debug: Document ready state:", document.readyState);
            console.log("WCQM Debug: jQuery loaded:", typeof jQuery !== "undefined");
            console.log("WCQM Debug: Select2 loaded:", typeof jQuery.fn.select2 !== "undefined");
            jQuery(document).ready(function($) {
                console.log("WCQM Debug: Document ready event fired");
                console.log("WCQM Debug: Product search element exists:", $("#wcqm-product-search").length > 0);
                console.log("WCQM Debug: Category search element exists:", $("#wcqm-category-search").length > 0);
            });
JAVASCRIPT;
        wp_add_inline_script('wcqm-admin', $debug_script);
        
        error_log('WCQM Settings: Admin scripts loaded successfully');
    }

    /**
     * Settings page content.
     */
    public function settings_page() {
        // Get current rules and default multiple
        $rules = $this->rules_manager->get_rules();
        $default_multiple = $this->rules_manager->get_default_multiple();
        
        // Debug log the rules data
        error_log('WCQM Debug: Rules data in settings page: ' . print_r($rules, true));
        ?>
        <div class="wrap wcqm-settings-page">
            <h1><?php echo esc_html__('WooCommerce Quantity Rules', 'wc-quantity-multiples'); ?></h1>
            
            <div class="wcqm-settings-content">
                <!-- Default Multiple Setting -->
                <div class="wcqm-default-section">
                    <h2><?php echo esc_html__('Default Multiple', 'wc-quantity-multiples'); ?></h2>
                    <p><?php echo esc_html__('This value will be used when no specific rule applies to a product.', 'wc-quantity-multiples'); ?></p>
                    
                    <div class="wcqm-setting-row">
                        <label for="wcqm-default-multiple"><?php echo esc_html__('Default Multiple:', 'wc-quantity-multiples'); ?></label>
                        <input type="number" id="wcqm-default-multiple" name="wcqm-default-multiple" min="1" value="<?php echo esc_attr($default_multiple); ?>" />
                        <button type="button" class="button button-primary wcqm-save-default"><?php echo esc_html__('Save Default', 'wc-quantity-multiples'); ?></button>
                        <span class="spinner wcqm-spinner"></span>
                    </div>
                </div>
                
                <hr />
                
                <div class="wcqm-rules-section">
                    <h2><?php echo esc_html__('Quantity Rules', 'wc-quantity-multiples'); ?></h2>
                    
                    <div class="wcqm-rules-list">
                        <?php if (!empty($rules)) : ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Type', 'wc-quantity-multiples'); ?></th>
                                        <th><?php echo esc_html__('Target', 'wc-quantity-multiples'); ?></th>
                                        <th><?php echo esc_html__('Multiple', 'wc-quantity-multiples'); ?></th>
                                        <th><?php echo esc_html__('Actions', 'wc-quantity-multiples'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($rules as $rule) {
                                        echo '<tr>';
                                        echo '<td>' . esc_html(ucfirst($rule['type'])) . '</td>';
                                        echo '<td>' . esc_html($rule['target_name']) . '</td>';
                                        echo '<td>' . esc_html($rule['multiple']) . '</td>';
                                        echo '<td><button class="button delete-rule" data-rule-id="' . esc_attr($rule['id']) . '">' . esc_html__('Delete', 'wc-quantity-multiples') . '</button></td>';
                                        echo '</tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p><?php echo esc_html__('No rules configured yet.', 'wc-quantity-multiples'); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="wcqm-add-rule">
                        <h3><?php echo esc_html__('Add New Rule', 'wc-quantity-multiples'); ?></h3>
                        <form id="wcqm-add-rule-form">
                            <p>
                                <label for="wcqm-rule-type"><?php echo esc_html__('Rule Type:', 'wc-quantity-multiples'); ?></label>
                                <select id="wcqm-rule-type" name="rule_type" required>
                                    <option value="product"><?php echo esc_html__('Product', 'wc-quantity-multiples'); ?></option>
                                    <option value="category"><?php echo esc_html__('Category', 'wc-quantity-multiples'); ?></option>
                                </select>
                            </p>
                            
                            <!-- Product Search Field (initially visible) -->
                            <div id="wcqm-product-search-container">
                                <p>
                                    <label for="wcqm-product-search"><?php echo esc_html__('Product:', 'wc-quantity-multiples'); ?></label>
                                    <select id="wcqm-product-search" name="product_id" class="wcqm-search" style="width: 100%;" data-placeholder="<?php esc_attr_e('Search for a product...', 'wc-quantity-multiples'); ?>"></select>
                                    <input type="hidden" id="wcqm-product-name" name="product_name">
                                </p>
                            </div>
                            
                            <!-- Category Search Field (initially hidden) -->
                            <div id="wcqm-category-search-container" style="display: none;">
                                <p>
                                    <label for="wcqm-category-search"><?php echo esc_html__('Category:', 'wc-quantity-multiples'); ?></label>
                                    <select id="wcqm-category-search" name="category_id" class="wcqm-search" style="width: 100%;" data-placeholder="<?php esc_attr_e('Search for a category...', 'wc-quantity-multiples'); ?>"></select>
                                    <input type="hidden" id="wcqm-category-name" name="category_name">
                                </p>
                            </div>
                            
                            <p>
                                <label for="wcqm-multiple"><?php echo esc_html__('Multiple:', 'wc-quantity-multiples'); ?></label>
                                <input type="number" id="wcqm-multiple" name="multiple" min="1" required>
                            </p>
                            
                            <p>
                                <button type="submit" class="button button-primary"><?php echo esc_html__('Add Rule', 'wc-quantity-multiples'); ?></button>
                                <span class="spinner" id="wcqm-submit-spinner"></span>
                            </p>
                        </form>
                    </div>
                </div>
                
                <hr />
                
                <div class="wcqm-product-overrides-section">
                    <h2><?php echo esc_html__('Product Overrides', 'wc-quantity-multiples'); ?></h2>
                    <p><?php echo esc_html__('Create direct product-to-multiple mappings that take priority over all other rules. Use this for special products that need specific multiples.', 'wc-quantity-multiples'); ?></p>
                    
                    <!-- Display existing product overrides -->
                    <div class="wcqm-overrides-list">
                        <?php 
                        // Get product overrides from the rules manager
                        $overrides = $this->rules_manager->get_all_product_overrides();
                        
                        if (!empty($overrides)) : 
                        ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php echo esc_html__('Product', 'wc-quantity-multiples'); ?></th>
                                        <th><?php echo esc_html__('Multiple', 'wc-quantity-multiples'); ?></th>
                                        <th><?php echo esc_html__('Actions', 'wc-quantity-multiples'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($overrides as $product_id => $multiple) {
                                        $product = wc_get_product($product_id);
                                        if ($product) {
                                            echo '<tr>';
                                            echo '<td>' . esc_html($product->get_name()) . ' (#' . esc_html($product_id) . ')</td>';
                                            echo '<td>' . esc_html($multiple) . '</td>';
                                            echo '<td><button class="button delete-override" data-product-id="' . esc_attr($product_id) . '">' . esc_html__('Delete', 'wc-quantity-multiples') . '</button></td>';
                                            echo '</tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p><?php echo esc_html__('No product overrides configured yet.', 'wc-quantity-multiples'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add new product override form -->
                    <div class="wcqm-add-override">
                        <h3><?php echo esc_html__('Add New Product Override', 'wc-quantity-multiples'); ?></h3>
                        <form id="wcqm-add-override-form">
                            <p>
                                <label for="wcqm-override-product-search"><?php echo esc_html__('Product:', 'wc-quantity-multiples'); ?></label>
                                <select id="wcqm-override-product-search" name="product_id" class="wcqm-search" style="width: 100%;" data-placeholder="<?php esc_attr_e('Search for a product...', 'wc-quantity-multiples'); ?>" required></select>
                            </p>
                            
                            <p>
                                <label for="wcqm-override-multiple"><?php echo esc_html__('Multiple:', 'wc-quantity-multiples'); ?></label>
                                <input type="number" id="wcqm-override-multiple" name="multiple" min="1" value="6" required>
                            </p>
                            
                            <p>
                                <button type="submit" class="button button-primary"><?php echo esc_html__('Add Override', 'wc-quantity-multiples'); ?></button>
                                <span id="wcqm-override-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle AJAX product search.
     */
    public function ajax_search_products() {
        check_ajax_referer('wcqm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wc-quantity-multiples'));
        }

        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        
        if (empty($term)) {
            wp_die();
        }

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            's'              => $term,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        $products_query = new WP_Query($args);
        $products = array();

        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                if ($product) {
                    $products[] = array(
                        'id' => $product_id,
                        'text' => $product->get_name() . ' (#' . $product_id . ')'
                    );
                }
            }
        }

        wp_reset_postdata();

        wp_send_json(array('results' => $products));
    }

    /**
     * Handle AJAX category search.
     */
    public function ajax_search_categories() {
        check_ajax_referer('wcqm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wc-quantity-multiples'));
        }

        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        
        if (empty($term)) {
            wp_die();
        }

        $args = array(
            'taxonomy'     => 'product_cat',
            'orderby'      => 'name',
            'order'        => 'ASC',
            'hide_empty'   => false,
            'name__like'   => $term
        );

        $categories = get_terms($args);
        $results = array();

        if (!is_wp_error($categories)) {
            foreach ($categories as $category) {
                $results[] = array(
                    'id' => $category->term_id,
                    'text' => $category->name
                );
            }
        }

        wp_send_json(array('results' => $results));
    }

    /**
     * Handle AJAX save rule request.
     */
    public function ajax_save_rule() {
        check_ajax_referer('wcqm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wc-quantity-multiples'));
        }

        $type = isset($_POST['rule_type']) ? sanitize_text_field($_POST['rule_type']) : '';
        $multiple = isset($_POST['multiple']) ? absint($_POST['multiple']) : 10;
        
        // Get target ID based on rule type
        $target_id = 0;
        if ($type === 'product' && isset($_POST['product_id'])) {
            $target_id = absint($_POST['product_id']);
        } elseif ($type === 'category' && isset($_POST['category_id'])) {
            $target_id = absint($_POST['category_id']);
        }

        if (!$type || !$target_id || !$multiple) {
            wp_send_json_error(array('message' => __('Invalid rule data. Please make sure all fields are filled correctly.', 'wc-quantity-multiples')));
        }

        // Generate a unique ID for the rule
        $rule_id = uniqid($type . '_', true);

        // Prepare rule data in the format expected by the rules manager
        $rule_data = array(
            'id' => $rule_id,
            'type' => $type,
            'target_id' => $target_id,
            'multiple' => $multiple
        );
        
        // Get target name for display
        $target_name = '';
        if ($type === 'product') {
            $product = wc_get_product($target_id);
            if ($product) {
                $target_name = $product->get_name();
                $rule_data['target_name'] = $target_name;
            }
        } elseif ($type === 'category') {
            $term = get_term($target_id, 'product_cat');
            if ($term && !is_wp_error($term)) {
                $target_name = $term->name;
                $rule_data['target_name'] = $target_name;
            }
        }

        // Add the rule
        $result = $this->rules_manager->add_rule($rule_data);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Rule saved successfully.', 'wc-quantity-multiples'),
                'rule_id' => $rule_id,
                'rule_data' => array(
                    'id' => $rule_id,
                    'type' => $type,
                    'target_id' => $target_id,
                    'target_name' => $target_name,
                    'multiple' => $multiple
                ),
                'is_new' => true
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save rule. The rule may already exist.', 'wc-quantity-multiples')));
        }
    }

    /**
     * Handle AJAX delete rule request.
     */
    public function ajax_delete_rule() {
        check_ajax_referer('wcqm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wc-quantity-multiples'));
        }

        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field($_POST['rule_id']) : '';

        if (empty($rule_id)) {
            wp_send_json_error(__('Invalid rule data.', 'wc-quantity-multiples'));
        }

        // Get rules
        $rules_manager = WCQM_Rules_Manager::instance();
        $rules = $rules_manager->get_rules();
        $updated_rules = array();
        $result = false;
        
        // Filter out the rule to delete
        foreach ($rules as $rule) {
            if ($rule['id'] !== $rule_id) {
                $updated_rules[] = $rule;
            } else {
                $result = true; // Found and removing the rule
            }
        }
        
        // Save the updated rules if a rule was found and removed
        if ($result) {
            $data = array(
                'rules' => $updated_rules,
                'default_multiple' => $rules_manager->get_default_multiple()
            );
            
            update_option('wcqm_settings', $data, 'yes');
            wp_send_json_success(__('Rule deleted successfully.', 'wc-quantity-multiples'));
        } else {
            wp_send_json_error(__('Rule not found.', 'wc-quantity-multiples'));
        }
    }

    /**
     * AJAX handler for saving default multiple
     */
    public function ajax_save_default_multiple() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wcqm_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-quantity-multiples')));
            return;
        }
        
        // Check for multiple parameter
        if (!isset($_POST['multiple'])) {
            wp_send_json_error(array('message' => __('No multiple value provided.', 'wc-quantity-multiples')));
            return;
        }
        
        // Sanitize and validate multiple
        $multiple = absint($_POST['multiple']);
        
        if ($multiple < 1) {
            $multiple = 1;
        }
        
        // Save the default multiple
        $result = $this->rules_manager->set_default_multiple($multiple);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Default multiple saved successfully.', 'wc-quantity-multiples'),
                'multiple' => $multiple
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save default multiple.', 'wc-quantity-multiples')));
        }
    }

    /**
     * AJAX handler for adding a product override
     */
    public function ajax_add_product_override() {
        // Check nonce for security
        check_ajax_referer('wcqm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wc-quantity-multiples'));
        }

        // Validate required fields
        if (!isset($_POST['product_id']) || !isset($_POST['multiple'])) {
            wp_send_json_error(__('Missing required fields.', 'wc-quantity-multiples'));
        }

        $product_id = absint($_POST['product_id']);
        $multiple = absint($_POST['multiple']);

        if ($product_id < 1 || $multiple < 1) {
            wp_send_json_error(__('Invalid product ID or multiple value.', 'wc-quantity-multiples'));
        }

        // Verify product exists
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Invalid product.', 'wc-quantity-multiples'));
        }

        // Set the override
        $result = $this->rules_manager->set_product_override($product_id, $multiple);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Product override saved successfully.', 'wc-quantity-multiples'),
                'product_id' => $product_id,
                'product_name' => $product->get_name(),
                'multiple' => $multiple
            ));
        } else {
            wp_send_json_error(__('Failed to save product override.', 'wc-quantity-multiples'));
        }
    }

    /**
     * AJAX handler for deleting a product override
     */
    public function ajax_delete_product_override() {
        // Check nonce for security
        check_ajax_referer('wcqm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wc-quantity-multiples'));
        }

        // Validate required fields
        if (!isset($_POST['product_id'])) {
            wp_send_json_error(__('Missing product ID.', 'wc-quantity-multiples'));
        }

        $product_id = absint($_POST['product_id']);

        if ($product_id < 1) {
            wp_send_json_error(__('Invalid product ID.', 'wc-quantity-multiples'));
        }

        // Remove the override
        $result = $this->rules_manager->remove_product_override($product_id);

        if ($result) {
            wp_send_json_success(__('Product override deleted successfully.', 'wc-quantity-multiples'));
        } else {
            wp_send_json_error(__('Failed to delete product override.', 'wc-quantity-multiples'));
        }
    }

    /**
     * Add menu items to the WordPress admin menu
     */
    public function add_menu_items() {
        add_menu_page(
            __('WC Quantity Multiples', 'wc-quantity-multiples'),   // Page title
            __('Quantity Rules', 'wc-quantity-multiples'),          // Menu title
            'manage_woocommerce',                                   // Capability required
            'wc-quantity-multiples',                                // Menu slug
            array($this, 'settings_page'),                          // Callback function to display the page
            'dashicons-cart',                                       // Icon
            58                                                      // Position
        );
        
        // Add a debug log
        error_log('WCQM Debug: Admin menu registration successful');
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Plugin action links
     * @return array Modified plugin action links
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-quantity-multiples') . '">' . 
                        __('Settings', 'wc-quantity-multiples') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Initialize settings
     */
    public function init() {
        // Register settings page
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(WCQM_PLUGIN_FILE), array($this, 'add_settings_link'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }
} 