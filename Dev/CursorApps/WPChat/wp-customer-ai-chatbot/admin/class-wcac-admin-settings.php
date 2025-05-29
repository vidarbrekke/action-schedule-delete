<?php

declare(strict_types=1);

/**
 * Admin settings for WP Customer AI Chatbot plugin.
 *
 * This file is intended to be run within the WordPress environment.
 * Functions such as add_action, add_filter, add_menu_page, register_setting, esc_html__, and admin_url
 * are provided by WordPress core and are available when this plugin is loaded by WordPress.
 *
 * If you see linter errors for undefined functions, ensure you are running this code within WordPress.
 */

// Ensure is_admin is defined for linter/static analysis
if (!function_exists('is_admin')) {
    if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!function_exists('is_admin')) {
        function is_admin() { return false; }
    }
}

// Ensure all required WordPress admin functions are available for linter/static analysis and CLI tools
if (defined('WP_ADMIN') || (defined('ABSPATH') && is_admin())) {
    // Guard for ABSPATH definition
    if (!defined('ABSPATH')) {
        // This might happen in a pure CLI context without WordPress loaded.
        // Define it minimally if possible, or log an error.
        // define('ABSPATH', dirname(__FILE__, 4) . '/'); // Example: Assumes plugin is in wp-content/plugins/your-plugin
        // For safety in a generic context, we might just skip these if ABSPATH isn't there.
    }

    if (defined('ABSPATH')) {
        if (!function_exists('add_action') && file_exists(ABSPATH . 'wp-includes/plugin.php')) {
            require_once ABSPATH . 'wp-includes/plugin.php';
            if (!function_exists('add_action')) { function add_action(...$args) { /* dummy */ } }
        }
        if (!function_exists('add_filter') && file_exists(ABSPATH . 'wp-includes/plugin.php')) {
            require_once ABSPATH . 'wp-includes/plugin.php';
            if (!function_exists('add_filter')) { function add_filter(...$args) { /* dummy */ } }
        }
        if (!function_exists('add_menu_page') && file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            if (!function_exists('add_menu_page')) { function add_menu_page(...$args) { return null; } }
        }
        if (!function_exists('add_submenu_page') && file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            if (!function_exists('add_submenu_page')) { function add_submenu_page(...$args) { return null; } }
        }
        if (!function_exists('admin_url') && file_exists(ABSPATH . 'wp-includes/link-template.php')) {
            require_once ABSPATH . 'wp-includes/link-template.php';
            if (!function_exists('admin_url')) { function admin_url($path = '') { return $path; } }
        }
        if (!function_exists('esc_html__') && file_exists(ABSPATH . 'wp-includes/l10n.php')) {
            require_once ABSPATH . 'wp-includes/l10n.php';
            if (!function_exists('esc_html__')) { function esc_html__($text, $domain = null) { return $text; } }
        }
        if (!function_exists('esc_html_e') && file_exists(ABSPATH . 'wp-includes/l10n.php')) {
            require_once ABSPATH . 'wp-includes/l10n.php';
            if (!function_exists('esc_html_e')) { function esc_html_e($text, $domain = null) { echo $text; } }
        }
        if (!function_exists('__') && file_exists(ABSPATH . 'wp-includes/l10n.php')) {
            require_once ABSPATH . 'wp-includes/l10n.php';
            if (!function_exists('__')) { function __($text, $domain = null) { return $text; } }
        }
        if (!function_exists('esc_attr') && file_exists(ABSPATH . 'wp-includes/formatting.php')) {
            require_once ABSPATH . 'wp-includes/formatting.php';
        }
        if (!function_exists('esc_textarea') && file_exists(ABSPATH . 'wp-includes/formatting.php')) {
            require_once ABSPATH . 'wp-includes/formatting.php';
            if (!function_exists('esc_textarea')) { function esc_textarea($text) { return $text; } }
        }
        if (!function_exists('checked') && file_exists(ABSPATH . 'wp-includes/general-template.php')) {
            require_once ABSPATH . 'wp-includes/general-template.php';
            if (!function_exists('checked')) { function checked($checked, $current = true, $echo = true) { if ($checked == $current) { if ($echo) { echo 'checked="checked"'; } else { return 'checked="checked"'; } } return ''; } }
        }
        if (!function_exists('selected') && file_exists(ABSPATH . 'wp-includes/general-template.php')) {
            require_once ABSPATH . 'wp-includes/general-template.php';
            if (!function_exists('selected')) { function selected($selected, $current = true, $echo = true) { if ($selected == $current) { if ($echo) { echo 'selected="selected"'; } else { return 'selected="selected"'; } } return ''; } }
        }
        if (!function_exists('register_setting') && file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            if (!function_exists('register_setting')) { function register_setting(...$args) { /* dummy */ } }
        }
        if (!function_exists('add_settings_section') && file_exists(ABSPATH . 'wp-admin/includes/template.php')) {
            require_once ABSPATH . 'wp-admin/includes/template.php';
            if (!function_exists('add_settings_section')) { function add_settings_section(...$args) { /* dummy */ } }
        }
        if (!function_exists('add_settings_field') && file_exists(ABSPATH . 'wp-admin/includes/template.php')) {
            require_once ABSPATH . 'wp-admin/includes/template.php';
            if (!function_exists('add_settings_field')) { function add_settings_field(...$args) { /* dummy */ } }
        }
        if (!function_exists('settings_fields') && file_exists(ABSPATH . 'wp-admin/includes/template.php')) {
            require_once ABSPATH . 'wp-admin/includes/template.php';
            if (!function_exists('settings_fields')) { function settings_fields(...$args) { /* dummy */ } }
        }
        if (!function_exists('do_settings_sections') && file_exists(ABSPATH . 'wp-admin/includes/template.php')) {
            require_once ABSPATH . 'wp-admin/includes/template.php';
            if (!function_exists('do_settings_sections')) { function do_settings_sections(...$args) { /* dummy */ } }
        }
        if (!function_exists('submit_button') && file_exists(ABSPATH . 'wp-admin/includes/template.php')) {
            require_once ABSPATH . 'wp-admin/includes/template.php';
            if (!function_exists('submit_button')) { function submit_button(...$args) { /* dummy */ } }
        }
        if (!function_exists('wp_enqueue_style') && file_exists(ABSPATH . 'wp-includes/functions.wp-styles.php')) {
            require_once ABSPATH . 'wp-includes/functions.wp-styles.php';
            if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style(...$args) { /* dummy */ } }
        }
        if (!function_exists('wp_enqueue_script') && file_exists(ABSPATH . 'wp-includes/functions.wp-scripts.php')) {
            require_once ABSPATH . 'wp-includes/functions.wp-scripts.php';
            if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script(...$args) { /* dummy */ } }
        }
        if (!function_exists('wp_localize_script') && file_exists(ABSPATH . 'wp-includes/functions.wp-scripts.php')) {
            require_once ABSPATH . 'wp-includes/functions.wp-scripts.php';
            if (!function_exists('wp_localize_script')) { function wp_localize_script(...$args) { /* dummy */ } }
        }
        if (!function_exists('sanitize_text_field') && file_exists(ABSPATH . 'wp-includes/formatting.php')) {
            require_once ABSPATH . 'wp-includes/formatting.php';
            if (!function_exists('sanitize_text_field')) { function sanitize_text_field($str) { return $str; } }
        }
        if (!function_exists('sanitize_textarea_field') && file_exists(ABSPATH . 'wp-includes/formatting.php')) {
            require_once ABSPATH . 'wp-includes/formatting.php';
            if (!function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($str) { return $str; } }
        }
        if (!function_exists('wp_strip_all_tags') && file_exists(ABSPATH . 'wp-includes/formatting.php')) {
            require_once ABSPATH . 'wp-includes/formatting.php';
            if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($str) { return $str; } }
        }
        if (!function_exists('current_user_can') && file_exists(ABSPATH . 'wp-includes/capabilities.php')) {
            require_once ABSPATH . 'wp-includes/capabilities.php';
            if (!function_exists('current_user_can')) { function current_user_can($cap) { return false; } }
        }
        if (!function_exists('wp_send_json_error') && file_exists(ABSPATH . 'wp-includes/functions.php')) {
            require_once ABSPATH . 'wp-includes/functions.php';
            if (!function_exists('wp_send_json_error')) { function wp_send_json_error($data = null, $status_code = null) { echo json_encode(['success' => false, 'data' => $data]); exit; } }
        }
        if (!function_exists('wp_send_json_success') && file_exists(ABSPATH . 'wp-includes/functions.php')) {
            require_once ABSPATH . 'wp-includes/functions.php';
            if (!function_exists('wp_send_json_success')) { function wp_send_json_success($data = null, $status_code = null) { echo json_encode(['success' => true, 'data' => $data]); exit; } }
        }
         if (!function_exists('check_ajax_referer') && file_exists(ABSPATH . 'wp-includes/pluggable.php')) {
            require_once ABSPATH . 'wp-includes/pluggable.php';
            if (!function_exists('check_ajax_referer')) { function check_ajax_referer(...$args) { return true; } } 
        }
        if (!function_exists('wp_create_nonce') && file_exists(ABSPATH . 'wp-includes/pluggable.php')) {
            require_once ABSPATH . 'wp-includes/pluggable.php';
            if (!function_exists('wp_create_nonce')) { function wp_create_nonce($action = -1) { return 'nonce'; } }
        }
        // Add dummy for class_exists, method_exists, plugins_url
        if (!function_exists('class_exists')) { function class_exists($class_name, $autoload = true) { return true; } } // Assume class exists for linter
        if (!function_exists('method_exists')) { function method_exists($object_or_class, $method_name) { return true; } } // Assume method exists
        if (!function_exists('plugins_url')) { function plugins_url($path = '', $plugin = '') { return 'dummy_plugin_url/' . ltrim($path, '/'); } }
    }
}

// Ensure WCAC_PLUGIN_DIR is defined for utility class loading
if (!defined('WCAC_PLUGIN_DIR')) {
    // Attempt to define it based on common WordPress structure
    // This might need adjustment based on the actual plugin structure if run outside WP context
    define('WCAC_PLUGIN_DIR', dirname(__FILE__, 2) . '/'); // Assumes this file is in /plugins/your-plugin/admin/
}

if (defined('WCAC_PLUGIN_DIR') && file_exists(WCAC_PLUGIN_DIR . 'includes/common/class-wcac-utils.php')) {
    require_once WCAC_PLUGIN_DIR . 'includes/common/class-wcac-utils.php';
}

// Ensure Wcac_Query_Analyzer is available as it might be a dependency for other loaded classes.
if (defined('WCAC_PLUGIN_DIR') && file_exists(WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-query-analyzer.php')) {
    require_once WCAC_PLUGIN_DIR . 'includes/retrieval/class-wcac-query-analyzer.php';
}

// Include the new Settings Renderer class
if (defined('WCAC_PLUGIN_DIR') && file_exists(WCAC_PLUGIN_DIR . 'admin/class-wcac-admin-settings-renderer.php')) {
    require_once WCAC_PLUGIN_DIR . 'admin/class-wcac-admin-settings-renderer.php';
}

// Include the new Settings Sanitizer class
if (defined('WCAC_PLUGIN_DIR') && file_exists(WCAC_PLUGIN_DIR . 'admin/class-wcac-admin-settings-sanitizer.php')) {
    require_once WCAC_PLUGIN_DIR . 'admin/class-wcac-admin-settings-sanitizer.php';
}

// Include the new Settings Registrar class
if (defined('WCAC_PLUGIN_DIR') && file_exists(WCAC_PLUGIN_DIR . 'admin/class-wcac-admin-settings-registrar.php')) {
    require_once WCAC_PLUGIN_DIR . 'admin/class-wcac-admin-settings-registrar.php';
}

// Add at the top of the file, after the opening PHP tag and any namespace declaration:
if (!function_exists('update_option')) {
    require_once ABSPATH . 'wp-includes/option.php';
}
if (!function_exists('get_option')) {
    require_once ABSPATH . 'wp-includes/option.php';
}

/**
 * Handles the admin settings page for the plugin.
 *
 * This class now only contains settings UI, registration, and field rendering logic
 * for API keys, indexing, chatbot customization (prompts, CSS, synonyms, boost/devalue terms),
 * LLM parameters, and debug logging.
 * Obsolete scoring-related properties and methods have been removed.
 * Test Results and Debug Logs page rendering have been moved to Wcac_Admin_Test_Results and Wcac_Admin_Debug_Logs, respectively.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/admin
 */
class Wcac_Admin_Settings
{
    /**
     * The unique identifier of this plugin's settings page.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $plugin_screen_hook_suffix    Stores the hook suffix of the plugin screen.
     */
    private ?string $plugin_screen_hook_suffix = null;
    private ?string $debug_logs_hook_suffix = null;
    private ?string $test_results_hook_suffix = null;

    /**
     * Instance of the settings field renderer.
     *
     * @since NEXT_VERSION
     * @access private
     * @var Wcac_Admin_Settings_Renderer|null
     */
    private ?Wcac_Admin_Settings_Renderer $field_renderer = null;

    /**
     * Instance of the settings sanitizer.
     *
     * @since NEXT_VERSION
     * @access private
     * @var Wcac_Admin_Settings_Sanitizer|null
     */
    private ?Wcac_Admin_Settings_Sanitizer $sanitizer = null;

    /**
     * Instance of the settings registrar.
     *
     * @since NEXT_VERSION
     * @access private
     * @var Wcac_Admin_Settings_Registrar|null
     */
    private ?Wcac_Admin_Settings_Registrar $registrar = null;

    /**
     * Option key for settings, stored in wp_options table.
     *
     * @since 0.1.0
     * @var string
     */
    private string $option_key = 'wcac_settings';

    /**
     * Option group name for settings page.
     *
     * @since 0.1.0
     * @var string
     */
    private string $option_group = 'wcac_option_group';

    /**
     * Plugin basename.
     *
     * @since 0.1.0
     * @var string
     */
    private string $plugin_basename;

    /**
     * Plugin slug. Used for enqueueing assets.
     * @since NEXT_VERSION
     * @var string
     */
    private string $plugin_slug;

    /**
     * Plugin version. Used for enqueueing assets.
     * @since NEXT_VERSION
     * @var string
     */
    private string $version;

    /**
     * Constructor.
     *
     * @since 0.1.0
     * @param string $plugin_basename Plugin basename.
     */
    public function __construct(string $plugin_basename)
    {
        $this->plugin_basename = $plugin_basename;
        $this->plugin_slug = 'wp-customer-ai-chatbot'; // Define a slug
        $this->version = defined('WCAC_VERSION') ? WCAC_VERSION : '1.0.0'; // Define version

        // Instantiate the field renderer
        if (class_exists('Wcac_Admin_Settings_Renderer')) {
            $this->field_renderer = new Wcac_Admin_Settings_Renderer($this->option_key);
        } else {
            if (WP_DEBUG) error_log('WCAC ERROR: Wcac_Admin_Settings_Renderer class not found.');
        }

        // Instantiate the sanitizer
        if (class_exists('Wcac_Admin_Settings_Sanitizer')) {
            $this->sanitizer = new Wcac_Admin_Settings_Sanitizer($this->option_key);
        } else {
            if (WP_DEBUG) error_log('WCAC ERROR: Wcac_Admin_Settings_Sanitizer class not found.');
        }

        // Instantiate the registrar
        // Pass $this as the section header renderer, and the field renderer instance
        if (class_exists('Wcac_Admin_Settings_Registrar') && $this->field_renderer) {
            $this->registrar = new Wcac_Admin_Settings_Registrar($this->option_group, $this->option_key, $this->field_renderer, $this);
        } else {
            if (WP_DEBUG) error_log('WCAC ERROR: Wcac_Admin_Settings_Registrar class not found or field_renderer not available.');
        }

        // Actions and filters for WordPress admin area
        add_action('admin_menu', [$this, 'add_plugin_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Add action links to plugins page
        add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'add_action_links']);

        // AJAX handler for testing LLM connection
        add_action('wp_ajax_wcac_test_llm_connection', [$this, 'ajax_test_llm_connection']);

        // AJAX handler for per-tab settings save
        add_action('wp_ajax_wcac_save_tab_settings', function() {
            if (WP_DEBUG) {
                error_log('WCAC DEBUG: Starting AJAX save for tab settings');
                error_log('WCAC DEBUG: POST data: ' . print_r($_POST, true));
            }

            if (!current_user_can('manage_options')) {
                if (WP_DEBUG) error_log('WCAC DEBUG: Permission denied for tab settings save');
                wp_send_json_error(['message' => 'Permission denied']);
                return;
            }

            // Tab-specific nonce verification
            $tab_id = $_POST['tab_id'] ?? '';
            if (empty($tab_id)) {
                if (WP_DEBUG) error_log('WCAC DEBUG: No tab_id provided in request');
                wp_send_json_error(['message' => 'No tab ID provided']);
                return;
            }

            // Tab-specific nonce verification
            $nonce_field = '_wpnonce_' . $tab_id;
            $nonce_action = 'wcac_save_tab_settings_' . $tab_id;
            if (!check_ajax_referer($nonce_action, $nonce_field, false)) {
                if (WP_DEBUG) error_log('WCAC DEBUG: Nonce verification failed for tab ' . $tab_id . ' (action: ' . $nonce_action . ', field: ' . $nonce_field . ')');
                wp_send_json_error(['message' => 'Security check failed']);
                return;
            }

            // Get the wcac_settings array from POST
            $settings_input = $_POST['wcac_settings'] ?? [];
            if (WP_DEBUG) {
                error_log('WCAC DEBUG: Raw settings input: ' . print_r($settings_input, true));
            }

            // Define fields for each tab
            switch ($tab_id) {
                case 'api':
                    $fields = ['wcac_api_key'];
                    break;
                case 'indexing':
                    $fields = ['wcac_index_products', 'wcac_index_pages', 'wcac_index_posts'];
                    break;
                case 'customization':
                    $fields = [
                        'wcac_system_prompt',
                        'wcac_site_prompt',
                        'wcac_custom_css',
                        'wcac_synonym_map',
                        'wcac_boost_terms',
                        'wcac_devalue_terms'
                    ];
                    break;
                case 'llm':
                    $fields = [
                        'wcac_temperature',
                        'wcac_top_p',
                        'wcac_max_tokens',
                        'wcac_frequency_penalty',
                        'wcac_presence_penalty',
                        'wcac_model',
                        'wcac_context_compression_algorithm'
                    ];
                    break;
                case 'debug':
                    $fields = ['wcac_enable_debug_logging'];
                    break;
                case 'scoring_rules':
                    $fields = [
                        'wcac_parent_product_weight',
                        'wcac_variation_product_weight',
                        'wcac_title_match_weight',
                        'wcac_content_match_weight',
                        'wcac_category_match_weight',
                        'wcac_tag_match_weight',
                        'wcac_on_sale_weight',
                        'wcac_menu_match_weight',
                        'wcac_attribute_match_weight',
                        'wcac_parent_category_match_weight',
                        'wcac_taxonomy_match_weight',
                        'wcac_rating_weight',
                        'wcac_title_category_match_bonus',
                        'wcac_exact_product_name_boost',
                        'wcac_multi_field_match_bonus',
                        'wcac_all_keywords_title_boost',
                        'wcac_parent_preference_margin',
                        'wcac_relative_score_threshold',
                        'wcac_negative_keyword_penalty',
                        'wcac_boost_term_score_value',
                        'wcac_devalue_term_score_value',
                        'wcac_max_results_returned',
                        'wcac_out_of_stock_penalty',
                        'wcac_recency_boost_multiplier'
                    ];
                    break;
                default:
                    wp_send_json_error('Invalid tab ID');
                    return;
            }

            // Get existing options
            $options = get_option('wcac_settings', []);
            if (WP_DEBUG) {
                error_log('WCAC DEBUG: Current options from DB: ' . print_r($options, true));
                error_log('WCAC DEBUG: Raw input to sanitize: ' . print_r($settings_input, true));
            }

            $sanitizer = new Wcac_Admin_Settings_Sanitizer('wcac_settings');
            $sanitized = $sanitizer->sanitize_settings($settings_input);
            if (WP_DEBUG) {
                error_log('WCAC DEBUG: Sanitized input: ' . print_r($sanitized, true));
                error_log('WCAC DEBUG: Sanitizer class: ' . get_class($sanitizer));
            }

            // Only update the fields for the current tab, leave all others untouched
            $merged = $options;
            foreach ($fields as $field) {
                if (array_key_exists($field, $sanitized)) {
                    $merged[$field] = $sanitized[$field];
                }
            }
            if (WP_DEBUG) {
                error_log('WCAC DEBUG: Merged result before save: ' . print_r($merged, true));
                error_log('WCAC DEBUG: Differences from current options:');
                $diff = array_diff_assoc($merged, $options);
                error_log('WCAC DEBUG: Changed keys: ' . print_r($diff, true));
            }

            // Save merged options
            $updated = update_option('wcac_settings', $merged);
            if (WP_DEBUG) {
                error_log('WCAC DEBUG: update_option result: ' . ($updated ? 'true' : 'false'));
                if (!$updated) {
                    error_log('WCAC DEBUG: No update occurred - checking if values are identical');
                    $current = get_option('wcac_settings', []);
                    $identical = $current == $merged;
                    error_log('WCAC DEBUG: Current and merged values are ' . ($identical ? 'identical' : 'different'));
                }
            }

            if ($updated) {
                wp_send_json_success(['message' => 'Settings saved successfully']);
            } else {
                if (WP_DEBUG) {
                    error_log('WCAC DEBUG: Failed to save settings - checking conditions:');
                    error_log('WCAC DEBUG: - Input empty? ' . (empty($settings_input) ? 'yes' : 'no'));
                    error_log('WCAC DEBUG: - Sanitized empty? ' . (empty($sanitized) ? 'yes' : 'no'));
                    error_log('WCAC DEBUG: - Any changes? ' . (!empty($diff) ? 'yes' : 'no'));
                }
                wp_send_json_error(['message' => 'Failed to save settings - no changes detected or error occurred']);
            }
        });
    }
    
    /**
     * AJAX handler for testing LLM connection.
     *
     * @since 0.8.0
     */
    public function ajax_test_llm_connection(): void 
    {
        check_ajax_referer('wcac_test_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
            return;
        }

        // Example: Logic to test LLM connection using Wcac_OpenRouter_Api class if available
        // This is a placeholder; actual implementation depends on your API class.
        /*
        if (class_exists('Wcac_OpenRouter_Api')) {
            $api_key = Wcac_Utils::get_option('wcac_api_key');
            $model = Wcac_Utils::get_option('wcac_model', 'mythomax/mythomax-l2-13b'); // default model
            if (empty($api_key)) {
                wp_send_json_error(['message' => 'API key is not set.'], 400);
                return;
            }
            $api = new Wcac_OpenRouter_Api($api_key);
            $test_result = $api->test_connection($model);
            if ($test_result['success']) {
                wp_send_json_success(['message' => 'LLM API connection successful with model: ' . $model]);
            } else {
                wp_send_json_error(['message' => 'LLM API test failed: ' . $test_result['error']], 500);
            }
        } else {
            wp_send_json_error(['message' => 'API handler class not found.'], 500);
        }
        */
        // For now, just simulate success as before if the above is not yet ready
        wp_send_json_success(['message' => 'Connection test placeholder successful!']);
    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    0.1.0
     * @param array $links An array of plugin action links.
     * @return array An array of plugin action links.
     */
    public function add_action_links(array $links): array
    {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.admin_url_admin_url
		// phpcs:ignore WordPress.WP.I18n.esc_html__
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            function_exists('admin_url') ? admin_url('admin.php?page=wcac-settings') : '#',
            function_exists('esc_html__') ? esc_html__('Settings', 'wp-customer-ai-chatbot') : 'Settings'
        );
        array_unshift($links, $settings_link); // Add to beginning of links
        return $links;
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     *
     * @since    0.1.0
     */
    public function add_plugin_admin_menu(): void
    {
       

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.add_menu_page_add_menu_page
        $this->plugin_screen_hook_suffix = add_menu_page(
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.esc_html__
            esc_html__('Customer AI Chatbot Settings', 'wp-customer-ai-chatbot'),
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.esc_html__
            esc_html__('AI Chatbot', 'wp-customer-ai-chatbot'),
            'manage_options',
            'wcac-settings',
            [ $this, 'display_plugin_setup_page'],
            'dashicons-format-chat'
        );

        // Add Settings submenu (to match the parent)
        add_submenu_page(
            'wcac-settings',
            esc_html__('Settings', 'wp-customer-ai-chatbot'),
            esc_html__('Settings', 'wp-customer-ai-chatbot'),
            'manage_options',
            'wcac-settings' // Intentionally same as parent to make parent clickable
        );

        // Add Debug Logs submenu
        if (defined('WCAC_PLUGIN_DIR') && file_exists(plugin_dir_path(__FILE__) . 'class-wcac-admin-debug-logs.php')) {
             require_once plugin_dir_path(__FILE__) . 'class-wcac-admin-debug-logs.php';
             if (class_exists('Wcac_Admin_Debug_Logs')) {
                $this->debug_logs_hook_suffix = add_submenu_page(
                    'wcac-settings',
                    esc_html__('Debug Logs', 'wp-customer-ai-chatbot'),
                    esc_html__('Debug Logs', 'wp-customer-ai-chatbot'),
                    'manage_options',
                    'wcac-debug-logs',
                    [ 'Wcac_Admin_Debug_Logs', 'render' ]
                );
            }
        }

        // Add Test Results submenu
		// phpcs:ignore WordPress.WP.AlternativeFunctions.plugin_dir_path_plugin_dir_path
        if (!function_exists('plugin_dir_path')) {
            if (defined('ABSPATH') && file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
                 require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        }
        if (function_exists('plugin_dir_path') && file_exists(plugin_dir_path(__FILE__) . 'class-wcac-admin-test-results.php')) {
            require_once plugin_dir_path(__FILE__) . 'class-wcac-admin-test-results.php';
            if (class_exists('Wcac_Admin_Test_Results')) {
                $this->test_results_hook_suffix = add_submenu_page(
                    'wcac-settings',
					// phpcs:ignore WordPress.WP.I18n.esc_html__
                    esc_html__('Test Results', 'wp-customer-ai-chatbot'),
					// phpcs:ignore WordPress.WP.I18n.esc_html__
                    esc_html__('Test Results', 'wp-customer-ai-chatbot'),
                    'manage_options',
                    'wcac-test-results',
                    [ 'Wcac_Admin_Test_Results', 'render' ]
                );
            }
        }
    }

    /**
     * Alias for add_plugin_admin_menu for backwards compatibility
     *
     * @since    0.1.5
     */
    public function add_options_page(): void
    {
        $this->add_plugin_admin_menu();
    }

    /**
     * Register the settings for this plugin.
     *
     * @since    0.1.0
     */
    public function register_settings(): void
    {
        if ($this->registrar && $this->sanitizer) {
            $this->registrar->register_settings([$this->sanitizer, 'sanitize_settings']);
        } elseif (WP_DEBUG) {
            error_log('WCAC ERROR: Settings registrar or sanitizer not available. Settings will not be registered.');
            // Fallback to direct registration if registrar is missing, but this indicates a problem.
            // register_setting($this->option_group, $this->option_key, [$this, 'sanitize_settings_fallback_if_needed']);
        }
    }

    /**
     * Render the header for the main settings section.
     *
     * @since 0.1.0
     */
    public function render_main_section_header(): void
    {
        echo '<p>' . esc_html__('Configure core plugin settings including API credentials and basic functionality.', 'wp-customer-ai-chatbot') . '</p>';
    }

    /**
     * Render the header for the indexing settings section.
     *
     * @since 0.1.0
     */
    public function render_indexing_section_header(): void
    {
        echo '<p>' . esc_html__('Control which content types are indexed and manage the content index.', 'wp-customer-ai-chatbot') . '</p>';
    }

    /**
     * Render the header for the Customization section.
     *
     * @since NEXT_VERSION
     */
    public function render_customization_section_header(): void
    {
        echo '<p>' . esc_html__('Customize the chatbot behavior, appearance, and response generation.', 'wp-customer-ai-chatbot') . '</p>';
    }

    /**
     * Render the header for the LLM API Parameters section.
     *
     * @since NEXT_VERSION
     */
    public function render_llm_params_section_header(): void
    {
        echo '<p>' . esc_html__('Configure the Large Language Model parameters to control response generation.', 'wp-customer-ai-chatbot') . '</p>';
    }

    public function render_scoring_rules_section_header(): void
    {
        echo '<p>' . esc_html__('Configure how products and content are scored in search results. These settings affect how matches are ranked and which results appear first.', 'wp-customer-ai-chatbot') . '</p>';
    }

    public function render_scoring_optimizable_header(): void
    {
        echo '<p>' . esc_html__('These parameters are tuned by the automated optimizer. Manual changes may be overwritten.', 'wp-customer-ai-chatbot') . '</p>';
    }

    public function render_scoring_static_header(): void
    {
        echo '<p>' . esc_html__('These parameters are fixed and not affected by the optimizer.', 'wp-customer-ai-chatbot') . '</p>';
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    0.1.0
     */
    public function display_plugin_setup_page(): void
    {
        try {
            if (WP_DEBUG) error_log('WCAC DEBUG: Starting display_plugin_setup_page for main settings');

            if (!current_user_can('manage_options')) {
                if (WP_DEBUG) error_log('WCAC DEBUG: User does not have manage_options capability');
                return;
            }

            $template_path = '';
            if (defined('WCAC_PLUGIN_DIR')) {
                $template_path = WCAC_PLUGIN_DIR . 'admin/partials/wcac-admin-settings.php';
            }
            
            if (empty($template_path) || !file_exists($template_path)) {
                if (WP_DEBUG) error_log('WCAC ERROR: Admin settings template file not found at: ' . $template_path);
                echo '<div class="wrap"><h1>WP Customer AI Chatbot Settings</h1>';
                echo '<div class="error notice"><p>Settings template file not found. Please reinstall the plugin or check WCAC_PLUGIN_DIR definition.</p></div>';
                echo '<form action="options.php" method="post">';
                settings_fields($this->option_group);
                do_settings_sections($this->option_key);
                submit_button();
                echo '</form></div>';
                return;
            }
            if (WP_DEBUG) error_log('WCAC DEBUG: Preparing to include admin display template: ' . $template_path);
            include_once $template_path;
            if (WP_DEBUG) error_log('WCAC DEBUG: Admin display template included successfully for main settings');
        } catch (Throwable $e) {
            if (WP_DEBUG) {
                 error_log('WCAC ERROR: Exception in display_plugin_setup_page: ' . $e->getMessage());
                 error_log('WCAC ERROR: Exception trace: ' . $e->getTraceAsString());
            }
            echo '<div class="wrap"><h1>WP Customer AI Chatbot Settings</h1>';
            echo '<div class="error notice"><p>An error occurred while loading the settings page: ' . esc_html($e->getMessage()) . '</p>';
            echo '<p>Please check server logs for more details or contact support.</p></div></div>';
        }
    }

    /**
     * Enqueue admin styles and scripts.
     *
     * @param string $hook_suffix The current admin page hook.
     */
    public function enqueue_admin_assets(string $hook_suffix): void
    {
        // Only load on our plugin's settings pages
        $allowed_hooks = [
            'toplevel_page_wcac-settings', // Main settings page (slug from add_menu_page)
            'ai-chatbot_page_wcac-settings-debug', // Debug Logs (slug from add_submenu_page)
            'ai-chatbot_page_wcac-settings-test-results', // Test Results (slug from add_submenu_page)
        ];

        $current_screen = get_current_screen();
        if (!$current_screen || !in_array($current_screen->id, $allowed_hooks, true)) {
            return;
        }
        
        if (WP_DEBUG) {
            error_log("WCAC DEBUG: Enqueue admin assets for hook: " . $current_screen->id);
        }

        // Get the URL to the current directory (wp-customer-ai-chatbot/admin/)
        $admin_assets_url = plugin_dir_url(__FILE__);

        // Enqueue admin styles
        wp_enqueue_style(
            $this->plugin_slug . '-admin-styles',
            $admin_assets_url . 'css/wcac-admin.css', // Corrected path
            [],
            $this->version
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            $this->plugin_slug . '-admin-script', // Handle used for localization
            $admin_assets_url . 'js/wcac-admin.js',   // Corrected path
            ['jquery', 'jquery-ui-tabs', 'jquery-ui-dialog'],
            $this->version,
            true // Load in footer
        );

        // Prepare data for JavaScript
        $default_system_prompt = '';
        $default_synonym_map = '';
        if (class_exists('Wcac_Chatbot_Defaults')) {
            $default_system_prompt = Wcac_Chatbot_Defaults::get_default_system_prompt();
            $default_synonym_map = Wcac_Chatbot_Defaults::get_default_synonym_map();
        } else {
            // Provide basic fallbacks if Wcac_Chatbot_Defaults isn't loaded
            $default_system_prompt = "You are a helpful and friendly shopping assistant for our store. 
Your goal is to help customers find products they are looking for and answer questions about the store's products and policies based *only* on the provided context snippets.

CORE RULES:
1.  **Context Analysis:** Carefully read all provided context snippets (product details, page content) before answering.
2.  **Information Source:** ONLY use information explicitly present in the context snippets. Do NOT make assumptions or use external knowledge.
3.  **Content Type Priority:**
    - For broad product queries: Show main/parent products first, not variations
    - For non-product queries (policies, info): Prioritize relevant pages and posts
    - For specific product queries: Only show variations if the query explicitly matches variation attributes (color, size) or names
4.  **Product Recommendations:**
    - Recommend items found in context that best match the user's intent
    - For broad queries, focus on main product lines rather than specific variants
    - Only suggest variations when specifically asked about them
    - Limit recommendations to 5 items unless asked for more
5.  **Complex Query Handling:**
    - For ambiguous or complex queries, analyze the user's intent carefully
    - Use your understanding to recommend the most relevant content type (products, pages, or posts)
    - Briefly explain your reasoning when it helps clarify the response
6.  **Response Style:**
    - Be concise and friendly
    - Keep product descriptions brief but informative
    - Mention available variations only when relevant
    - Use exact product names from the context
7.  **Missing Information:**
    - If context doesn't contain the answer, clearly state that
    - Suggest related categories or topics if available
    - Never invent or assume information
8.  **Links and URLs:**
    - Only provide URLs found in the context snippets
    - Use proper markdown format for links
9.  **Categories:**
    - When showing multiple products, mention their category if provided
    - For broad queries, suggest exploring relevant categories
10.  **No Hallucination:**
    - Never report or imply information that is not present in the provided context snippets.
    - If the answer is not in the context, say so clearly.";
            $default_synonym_map = "color,colour\ncatalog,collection\nfiber,fibre\nyarn,wool\npattern,design\nstore,shop\nsize,dimension\nsale,discount,offer\n"; // Basic fallback, ensure newlines are handled correctly if needed by JS
        }
        
        $enable_debug_logging = false; // Default to false
        if (class_exists('Wcac_Utils')) {
            $enable_debug_logging = Wcac_Utils::get_option('wcac_enable_debug_logging', false);
        }

        wp_localize_script(
            $this->plugin_slug . '-admin-script', // Must match the script handle
            'wcacAdmin', // Object name in JavaScript
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcac_admin_nonce'), // General admin nonce
                
                // Re-indexing related
                'reindex_confirm' => esc_html__('Are you sure you want to re-index all content? This may take some time.', 'wp-customer-ai-chatbot'),
                'reindex_success' => esc_html__('Content re-indexing started. Check Debug Logs for progress.', 'wp-customer-ai-chatbot'),
                'reindex_error' => esc_html__('Failed to start re-indexing. See console for details.', 'wp-customer-ai-chatbot'),
                'reindex_nonce' => wp_create_nonce('wcac_reindex_nonce'), // Specific for re-indexing action

                // Debug log related
                'delete_log_confirm' => esc_html__('Are you sure you want to delete all debug logs?', 'wp-customer-ai-chatbot'),
                'delete_log_success' => esc_html__('Debug logs cleared.', 'wp-customer-ai-chatbot'),
                'delete_log_error' => esc_html__('Failed to clear debug logs.', 'wp-customer-ai-chatbot'),
                'delete_log_nonce' => wp_create_nonce('wcac_delete_log_nonce'), // Specific for delete log action

                // System prompt related
                'restore_prompt_confirm' => esc_html__('Are you sure you want to restore the default system prompt? Any changes you made will be lost.', 'wp-customer-ai-chatbot'),
                'default_system_prompt' => $default_system_prompt,

                // Synonym map related
                'default_synonym_map' => $default_synonym_map, // JS might need to process \n if present

                // Test Query related (Test Results page)
                'query_test_empty' => esc_html__('Please enter a query to test.', 'wp-customer-ai-chatbot'),
                'test_query_nonce' => wp_create_nonce('wcac_test_query_nonce'),

                // Batch delete Test Results related
                'batch_delete_confirm' => __('Are you sure you want to delete the selected test results?', 'wp-customer-ai-chatbot'),
                'no_results_selected' => __('No test results selected for deletion.', 'wp-customer-ai-chatbot'),
                'delete_error' => __('Error deleting test results. Please try again.', 'wp-customer-ai-chatbot'),
                'delete_success' => __('Selected test results deleted successfully.', 'wp-customer-ai-chatbot'),
                'batch_delete_nonce' => wp_create_nonce('wcac_batch_delete_test_results_nonce'),
                'clear_all_confirm' => __('Are you sure you want to delete ALL test result history? This cannot be undone.', 'wp-customer-ai-chatbot'),
                'clear_all_nonce' => wp_create_nonce('wcac_clear_all_test_results_nonce'),

                // LLM Connection Test related
                'test_connection_text' => esc_html__('Test Connection', 'wp-customer-ai-chatbot'),
                'testing_connection_text' => esc_html__('Testing...', 'wp-customer-ai-chatbot'),
                // The main 'nonce' (wcac_admin_nonce) is used for test_llm_connection AJAX call

                // General settings & UI
                'enable_debug_logging' => (bool) $enable_debug_logging,
                'settings_update_success' => __('Settings saved.', 'wp-customer-ai-chatbot'), // Example, if you want JS to show this
                'settings_update_error' => __('Error saving settings.', 'wp-customer-ai-chatbot'), // Example
                
                // Glossary related
                'glossary_nonce' => wp_create_nonce('wcac_get_scoring_glossary_nonce')
            ]
        );
    }
} // End class Wcac_Admin_Settings