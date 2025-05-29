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
        wp_add_inline_script('wcqm-admin', '
            console.log("WCQM Debug: Document ready state:", document.readyState);
            console.log("WCQM Debug: jQuery loaded:", typeof jQuery !== "undefined");
            console.log("WCQM Debug: Select2 loaded:", typeof jQuery.fn.select2 !== "undefined");
            jQuery(document).ready(function($) {
                console.log("WCQM Debug: Document ready event fired");
                console.log("WCQM Debug: Product search element exists:", $("#wcqm-product-search").length > 0);
                console.log("WCQM Debug: Category search element exists:", $("#wcqm-category-search").length > 0);
            });
        ');
        
        error_log('WCQM Settings: Admin scripts loaded successfully');
    }

    /**
     * Settings page content.
     */
    public function settings_page() {
        // Get current rules
        $rules = $this->rules_manager->get_all_rules_data();
        $default_multiple = $this->rules_manager->get_default_multiple();
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
                        <?php if (!empty($rules['products']) || !empty($rules['categories'])) : ?>
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
                                    // Display product rules
                                    foreach ($rules['products'] as $product_id => $multiple) {
                                        $product = wc_get_product($product_id);
                                        if ($product) {
                                            echo '<tr>';
                                            echo '<td>' . esc_html__('Product', 'wc-quantity-multiples') . '</td>';
                                            echo '<td>' . esc_html($product->get_name()) . '</td>';
                                            echo '<td>' . esc_html($multiple) . '</td>';
                                            echo '<td><button class="button delete-rule" data-type="product" data-id="' . esc_attr($product_id) . '">' . esc_html__('Delete', 'wc-quantity-multiples') . '</button></td>';
                                            echo '</tr>';
                                        }
                                    }

                                    // Display category rules
                                    foreach ($rules['categories'] as $category_id => $multiple) {
                                        $term = get_term($category_id, 'product_cat');
                                        if ($term && !is_wp_error($term)) {
                                            echo '<tr>';
                                            echo '<td>' . esc_html__('Category', 'wc-quantity-multiples') . '</td>';
                                            echo '<td>' . esc_html($term->name) . '</td>';
                                            echo '<td>' . esc_html($multiple) . '</td>';
                                            echo '<td><button class="button delete-rule" data-type="category" data-id="' . esc_attr($category_id) . '">' . esc_html__('Delete', 'wc-quantity-multiples') . '</button></td>';
                                            echo '</tr>';
                                        }
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
                                <input type="number" id="wcqm-multiple" name="multiple" min="1" value="10" required>
                            </p>
                            
                            <p>
                                <button type="submit" class="button button-primary"><?php echo esc_html__('Add Rule', 'wc-quantity-multiples'); ?></button>
                                <span id="wcqm-submit-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
                            </p>
                            
                            <!-- Rule ID for editing (hidden) -->
                            <input type="hidden" id="wcqm-rule-id" name="rule_id" value="">
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
     * AJAX handler for saving rules
     */
    public function ajax_save_rule() {
        // Verify nonce
        if (!check_ajax_referer('wcqm_admin_nonce', 'nonce', false)) {
            error_log('WCQM Debug: Nonce verification failed');
            wp_send_json_error(array(
                'message' => 'Security check failed'
            ));
            return;
        }

        // Get and validate parameters
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        $target_id = isset($_POST['target_id']) ? absint($_POST['target_id']) : 0;
        $multiple = isset($_POST['multiple']) ? absint($_POST['multiple']) : 0;

        error_log('WCQM Debug: Attempting to save rule - Type: ' . $type . ', Target ID: ' . $target_id . ', Multiple: ' . $multiple);

        // Validate type
        if (!in_array($type, array('product', 'category'))) {
            error_log('WCQM Debug: Invalid rule type: ' . $type);
            wp_send_json_error(array(
                'message' => 'Invalid rule type'
            ));
            return;
        }

        // Validate target_id
        if ($target_id <= 0) {
            error_log('WCQM Debug: Invalid target ID: ' . $target_id);
            wp_send_json_error(array(
                'message' => 'Invalid target ID'
            ));
            return;
        }

        // Validate multiple
        if ($multiple <= 0) {
            error_log('WCQM Debug: Invalid multiple value: ' . $multiple);
            wp_send_json_error(array(
                'message' => 'Multiple must be greater than 0'
            ));
            return;
        }

        // Verify target exists
        $exists = false;
        if ($type === 'product') {
            $exists = wc_get_product($target_id) !== false;
        } else {
            $exists = get_term($target_id, 'product_cat') !== null;
        }

        if (!$exists) {
            error_log('WCQM Debug: Target ' . $type . ' with ID ' . $target_id . ' does not exist');
            wp_send_json_error(array(
                'message' => ucfirst($type) . ' does not exist'
            ));
            return;
        }

        // Get current rules
        $current_data = get_option('wcqm_settings', array(
            'rules' => array(),
            'default_multiple' => 10
        ));

        // Create new rule
        $new_rule = array(
            'type' => $type,
            'target_id' => $target_id,
            'multiple' => $multiple
        );

        // Add to rules array
        if (!isset($current_data['rules'])) {
            $current_data['rules'] = array();
        }
        $current_data['rules'][] = $new_rule;

        error_log('WCQM Debug: Saving updated rules data: ' . print_r($current_data, true));

        // Save updated rules
        $save_result = update_option('wcqm_settings', $current_data);

        if ($save_result) {
            error_log('WCQM Debug: Rule saved successfully');
            
            // Clear any cached data in the rules manager
            $this->rules_manager->clear_cache();
            
            wp_send_json_success(array(
                'message' => 'Rule saved successfully'
            ));
        } else {
            error_log('WCQM Debug: Failed to save rule to database');
            wp_send_json_error(array(
                'message' => 'Failed to save rule to database'
            ));
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

        // Try to parse rule_id into type and target_id
        $parts = explode('_', $rule_id, 2);
        if (count($parts) !== 2) {
            wp_send_json_error(__('Invalid rule ID format.', 'wc-quantity-multiples'));
        }

        $type = $parts[0];
        $target_id = absint($parts[1]);

        if (!in_array($type, array('product', 'category')) || $target_id <= 0) {
            wp_send_json_error(__('Invalid rule type or target ID.', 'wc-quantity-multiples'));
        }

        $result = false;
        
        // Get rules
        $rules_manager = WCQM_Rules_Manager::instance();
        $rules = $rules_manager->get_rules();
        $updated_rules = array();
        
        // Filter out the rule to delete
        foreach ($rules as $rule) {
            if ($rule['type'] !== $type || $rule['target_id'] != $target_id) {
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
     * AJAX handler for saving the default multiple value.
     */
    public function ajax_save_default_multiple() {
        // Check nonce for security
        if (!check_ajax_referer('wcqm_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-quantity-multiples')));
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have permission to do this.', 'wc-quantity-multiples')));
            return;
        }
        
        // Get and validate the multiple value
        $multiple = isset($_POST['multiple']) ? absint($_POST['multiple']) : 1;
        
        // Ensure multiple is at least 1
        if ($multiple < 1) {
            $multiple = 1;
        }
        
        // Save the default multiple
        update_option('wcqm_default_multiple', $multiple);
        
        // Send success response
        wp_send_json_success(array(
            'message' => __('Default multiple saved successfully.', 'wc-quantity-multiples'),
            'multiple' => $multiple
        ));
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
} 