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
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if (!function_exists('is_admin')) {
        function is_admin() { return false; }
    }
}
// Ensure all required WordPress admin functions are available for linter/static analysis and CLI tools
if (defined('WP_ADMIN') || (defined('ABSPATH') && is_admin())) {
    if (!function_exists('add_action')) {
        require_once ABSPATH . 'wp-includes/plugin.php';
        if (!function_exists('add_action')) {
            function add_action() { /* dummy */ }
        }
    }
    if (!function_exists('add_filter')) {
        require_once ABSPATH . 'wp-includes/plugin.php';
        if (!function_exists('add_filter')) {
            function add_filter() { /* dummy */ }
        }
    }
    if (!function_exists('add_menu_page')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        if (!function_exists('add_menu_page')) {
            function add_menu_page() { return null; }
        }
    }
    if (!function_exists('add_submenu_page')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        if (!function_exists('add_submenu_page')) {
            function add_submenu_page() { return null; }
        }
    }
    if (!function_exists('admin_url')) {
        require_once ABSPATH . 'wp-includes/link-template.php';
        if (!function_exists('admin_url')) {
            function admin_url($path = '') { return $path; }
        }
    }
    if (!function_exists('esc_html__')) {
        require_once ABSPATH . 'wp-includes/l10n.php';
        if (!function_exists('esc_html__')) {
            function esc_html__($text, $domain = null) { return $text; }
        }
    }
    if (!function_exists('esc_html_e')) {
        require_once ABSPATH . 'wp-includes/l10n.php';
        if (!function_exists('esc_html_e')) {
            function esc_html_e($text, $domain = null) { echo $text; }
        }
    }
    if (!function_exists('__')) {
        require_once ABSPATH . 'wp-includes/l10n.php';
        if (!function_exists('__')) {
            function __($text, $domain = null) { return $text; }
        }
    }
    if (!function_exists('esc_attr')) {
        require_once ABSPATH . 'wp-includes/formatting.php';
    }
    if (!function_exists('esc_textarea')) {
        require_once ABSPATH . 'wp-includes/formatting.php';
        if (!function_exists('esc_textarea')) {
            function esc_textarea($text) { return $text; }
        }
    }
    if (!function_exists('checked')) {
        require_once ABSPATH . 'wp-includes/general-template.php';
        if (!function_exists('checked')) {
            function checked($checked, $current = true, $echo = true) { if ($checked == $current) { if ($echo) { echo 'checked="checked"'; } else { return 'checked="checked"'; } } return ''; }
        }
    }
    if (!function_exists('selected')) {
        require_once ABSPATH . 'wp-includes/general-template.php';
        if (!function_exists('selected')) {
            function selected($selected, $current = true, $echo = true) { if ($selected == $current) { if ($echo) { echo 'selected="selected"'; } else { return 'selected="selected"'; } } return ''; }
        }
    }
    if (!function_exists('register_setting')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        if (!function_exists('register_setting')) {
            function register_setting() { /* dummy */ }
        }
    }
    if (!function_exists('add_settings_section')) {
        require_once ABSPATH . 'wp-admin/includes/template.php';
        if (!function_exists('add_settings_section')) {
            function add_settings_section() { /* dummy */ }
        }
    }
    if (!function_exists('add_settings_field')) {
        require_once ABSPATH . 'wp-admin/includes/template.php';
        if (!function_exists('add_settings_field')) {
            function add_settings_field() { /* dummy */ }
        }
    }
    if (!function_exists('settings_fields')) {
        require_once ABSPATH . 'wp-admin/includes/template.php';
        if (!function_exists('settings_fields')) {
            function settings_fields() { /* dummy */ }
        }
    }
    if (!function_exists('do_settings_sections')) {
        require_once ABSPATH . 'wp-admin/includes/template.php';
        if (!function_exists('do_settings_sections')) {
            function do_settings_sections() { /* dummy */ }
        }
    }
    if (!function_exists('submit_button')) {
        require_once ABSPATH . 'wp-admin/includes/template.php';
        if (!function_exists('submit_button')) {
            function submit_button() { /* dummy */ }
        }
    }
    if (!function_exists('wp_enqueue_style')) {
        require_once ABSPATH . 'wp-includes/functions.wp-styles.php';
        if (!function_exists('wp_enqueue_style')) {
            function wp_enqueue_style() { /* dummy */ }
        }
    }
    if (!function_exists('wp_enqueue_script')) {
        require_once ABSPATH . 'wp-includes/functions.wp-scripts.php';
        if (!function_exists('wp_enqueue_script')) {
            function wp_enqueue_script() { /* dummy */ }
        }
    }
    if (!function_exists('wp_localize_script')) {
        require_once ABSPATH . 'wp-includes/functions.wp-scripts.php';
        if (!function_exists('wp_localize_script')) {
            function wp_localize_script() { /* dummy */ }
        }
    }
    if (!function_exists('sanitize_text_field')) {
        require_once ABSPATH . 'wp-includes/formatting.php';
        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) { return $str; }
        }
    }
    if (!function_exists('sanitize_textarea_field')) {
        require_once ABSPATH . 'wp-includes/formatting.php';
        if (!function_exists('sanitize_textarea_field')) {
            function sanitize_textarea_field($str) { return $str; }
        }
    }
    if (!function_exists('wp_strip_all_tags')) {
        require_once ABSPATH . 'wp-includes/formatting.php';
        if (!function_exists('wp_strip_all_tags')) {
            function wp_strip_all_tags($str) { return $str; }
        }
    }
    if (!function_exists('current_user_can')) {
        require_once ABSPATH . 'wp-includes/capabilities.php';
        if (!function_exists('current_user_can')) {
            function current_user_can($cap) { return false; }
        }
    }
    if (!function_exists('wp_send_json_error')) {
        require_once ABSPATH . 'wp-includes/functions.php';
        if (!function_exists('wp_send_json_error')) {
            function wp_send_json_error($data = null, $status_code = null) { echo json_encode(['success' => false, 'data' => $data]); exit; }
        }
    }
    if (!function_exists('wp_send_json_success')) {
        require_once ABSPATH . 'wp-includes/functions.php';
        if (!function_exists('wp_send_json_success')) {
            function wp_send_json_success($data = null, $status_code = null) { echo json_encode(['success' => true, 'data' => $data]); exit; }
        }
    }
}

// Add at the top if not present
require_once WCAC_PLUGIN_DIR . 'includes/common/class-wcac-utils.php';

/**
 * Handles the admin settings page for the plugin.
 *
 * This class now only contains settings UI, registration, and field rendering logic.
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
     * Initialize the class and set its properties.
     *
     * @since 0.1.0
     * @param string $plugin_basename The plugin basename.
     */
    public function __construct(string $plugin_basename)
    {
        error_log('WCAC DEBUG: Admin_Settings constructor called - ' . date('Y-m-d H:i:s'));
        $this->plugin_basename = $plugin_basename;
        // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.add_action_add_action
        add_action('admin_menu', [ $this, 'add_plugin_admin_menu' ]);
        // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.add_action_add_action
        add_action('admin_init', [ $this, 'register_settings' ]);
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.add_filter_add_filter
        add_filter('plugin_action_links_' . $this->plugin_basename, [ $this, 'add_action_links' ]);
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.add_action_add_action
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ]);
        // Register all AJAX handlers in a separate class
        if (class_exists('Wcac_Admin_Ajax')) {
            Wcac_Admin_Ajax::register();
        } else {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.plugin_dir_path_plugin_dir_path
            if (!function_exists('plugin_dir_path')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.plugin_dir_path_plugin_dir_path
            require_once plugin_dir_path(__FILE__) . 'class-wcac-admin-ajax.php';
            Wcac_Admin_Ajax::register();
        }

        // Apply rules from settings at admin load
        $options = get_option('wcac_settings', []);

        // Make sure the class exists first
        if (!class_exists('Wcac_ChatbotRules')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/retrieval/class-wcac-chatbot-rules.php';
        }

        if (class_exists('Wcac_ChatbotRules')) {
            Wcac_ChatbotRules::apply_admin_settings($options);
        }

        // Always ensure debug log table schema is up to date
        if (!class_exists('Wcac_Debug_Logger')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/common/class-wcac-debug-logger.php';
        }
        if (class_exists('Wcac_Debug_Logger')) {
            Wcac_Debug_Logger::ensure_table_schema();
        }

        // TEMP: Add admin action to delete all logs via URL param for admin users
        // (REMOVED after use)

        add_action('wp_ajax_wcac_test_llm_connection', [ $this, 'ajax_test_llm_connection' ]);
        add_action('admin_enqueue_scripts', function($hook) {
            if ($hook === $this->plugin_screen_hook_suffix) {
                wp_localize_script('jquery-core', 'wcacAdmin', [
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'test_nonce' => wp_create_nonce('wcac_test_llm_connection'),
                ]);
            }
        });
    }

    /**
     * AJAX handler for LLM Test Connection button.
     */
    public function ajax_test_llm_connection() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([ 'message' => 'Permission denied.' ]);
        }
        if (empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wcac_test_llm_connection')) {
            wp_send_json_error([ 'message' => 'Invalid nonce.' ]);
        }
        require_once WCAC_PLUGIN_DIR . 'includes/api/class-wcac-api-handler.php';
        $api = new WCAC_API_Handler();
        $result = $api->test_api_connectivity();
        if ($result === true) {
            wp_send_json_success();
        } elseif (is_wp_error($result)) {
            wp_send_json_error([ 'message' => $result->get_error_message() ]);
        } else {
            wp_send_json_error([ 'message' => is_string($result) ? $result : 'Unknown error' ]);
        }
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
            'wcac-settings'
        );

        // Add Debug Logs submenu
        require_once plugin_dir_path(__FILE__) . 'class-wcac-admin-debug-logs.php';
        $this->debug_logs_hook_suffix = add_submenu_page(
            'wcac-settings',
            esc_html__('Debug Logs', 'wp-customer-ai-chatbot'),
            esc_html__('Debug Logs', 'wp-customer-ai-chatbot'),
            'manage_options',
            'wcac-debug-logs',
            [ 'Wcac_Admin_Debug_Logs', 'render' ]
        );

        // Add Test Results submenu
		// phpcs:ignore WordPress.WP.AlternativeFunctions.plugin_dir_path_plugin_dir_path
        if (!function_exists('plugin_dir_path')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
		// phpcs:ignore WordPress.WP.AlternativeFunctions.plugin_dir_path_plugin_dir_path
        require_once plugin_dir_path(__FILE__) . 'class-wcac-admin-test-results.php';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.add_submenu_page_add_submenu_page
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
        register_setting(
            $this->option_group, // Option group
            $this->option_key,   // Option name
            [$this, 'sanitize_settings'] // Sanitize callback
        );

        // Main Settings Section
        add_settings_section(
            'wcac_main_settings_section',      // ID
            esc_html__('OpenRouter API Settings', 'wp-customer-ai-chatbot'), // Title
            [$this, 'render_main_section_header'], // Callback
            $this->option_key                  // Page
        );

        add_settings_field(
            'wcac_api_key',                                 // ID
            esc_html__('OpenRouter API Key', 'wp-customer-ai-chatbot'), // Title
            [$this, 'render_api_key_field'],              // Callback
            $this->option_key,                              // Page
            'wcac_main_settings_section',                   // Section
            ['label_for' => 'wcac_api_key']
        );

        // Indexing Settings Section
        add_settings_section(
            'wcac_indexing_settings_section',
            esc_html__('Content Indexing Settings', 'wp-customer-ai-chatbot'),
            [$this, 'render_indexing_section_header'],
            $this->option_key
        );

        add_settings_field(
            'wcac_index_content_types',
            esc_html__('Content Types to Index', 'wp-customer-ai-chatbot'),
            [$this, 'render_index_content_types_field'],
            $this->option_key,
            'wcac_indexing_settings_section'
        );

        add_settings_field(
            'wcac_reindex_button',
            esc_html__('Manage Index', 'wp-customer-ai-chatbot'),
            [$this, 'render_reindex_button_field'],
            $this->option_key,
            'wcac_indexing_settings_section'
        );

        // Chatbot Customization Section
        add_settings_section(
            'wcac_customization_settings_section',
            esc_html__('Chatbot Behavior & Appearance', 'wp-customer-ai-chatbot'),
            [$this, 'render_customization_section_header'],
            $this->option_key
        );

        add_settings_field(
            'wcac_negative_keywords',
            esc_html__('Global Negative Keywords', 'wp-customer-ai-chatbot'),
            [$this, 'render_negative_keywords_field'],
            $this->option_key,
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_negative_keywords']
        );

        add_settings_field(
            'wcac_system_prompt',
            esc_html__('System Prompt', 'wp-customer-ai-chatbot'),
            [$this, 'render_system_prompt_field'],
            $this->option_key,
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_system_prompt']
        );
        add_settings_field(
            'wcac_site_prompt',
            esc_html__('Site Prompt (Instructions)', 'wp-customer-ai-chatbot'),
            [$this, 'render_site_prompt_field'],
            $this->option_key,
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_site_prompt']
        );

        add_settings_field(
            'wcac_custom_css',
            esc_html__('Custom CSS for Chatbot', 'wp-customer-ai-chatbot'),
            [$this, 'render_custom_css_field'],
            $this->option_key,
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_custom_css']
        );

        add_settings_field(
            'wcac_synonym_map',
            esc_html__('Synonym Map (JSON)', 'wp-customer-ai-chatbot'),
            [$this, 'render_synonym_map_field'],
            $this->option_key,
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_synonym_map']
        );

        add_settings_field(
            'wcac_boost_terms',
            esc_html__('Boost Terms (JSON)', 'wp-customer-ai-chatbot'),
            [$this, 'render_boost_terms_field'],
            $this->option_key,
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_boost_terms']
        );

        add_settings_field(
            'wcac_devalue_terms',
            esc_html__('Devalue Terms (JSON)', 'wp-customer-ai-chatbot'),
            [$this, 'render_devalue_terms_field'],
            $this->option_key,
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_devalue_terms']
        );


        // LLM Parameters Section
        add_settings_section(
            'wcac_llm_params_section',
            esc_html__('LLM Parameters', 'wp-customer-ai-chatbot'),
            [$this, 'render_llm_params_section_header'],
            $this->option_key
        );

        add_settings_field(
            'wcac_model',
            esc_html__('Chat Model', 'wp-customer-ai-chatbot'),
            [$this, 'render_model_field'],
            $this->option_key,
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_temperature',
            esc_html__('Temperature', 'wp-customer-ai-chatbot'),
            [$this, 'render_temperature_field'],
            $this->option_key,
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_top_p',
            esc_html__('Top P', 'wp-customer-ai-chatbot'),
            [$this, 'render_top_p_field'],
            $this->option_key,
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_max_tokens',
            esc_html__('Max Tokens', 'wp-customer-ai-chatbot'),
            [$this, 'render_max_tokens_field'],
            $this->option_key,
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_frequency_penalty',
            esc_html__('Frequency Penalty', 'wp-customer-ai-chatbot'),
            [$this, 'render_frequency_penalty_field'],
            $this->option_key,
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_presence_penalty',
            esc_html__('Presence Penalty', 'wp-customer-ai-chatbot'),
            [$this, 'render_presence_penalty_field'],
            $this->option_key,
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_context_compression_algorithm',
            esc_html__('Context Compression Algorithm', 'wp-customer-ai-chatbot'),
            [$this, 'render_context_compression_algorithm_field'],
            $this->option_key,
            'wcac_llm_params_section'
        );

        // Scoring Parameters Section and its fields are removed from here.
        // They are now managed in scoring-settings.php

        // Debug Logging Section
        add_settings_section(
            'wcac_debug_logging_section',
            esc_html__('Debug Logging', 'wp-customer-ai-chatbot'),
            null, // No header callback needed
            $this->option_key
        );

        add_settings_field(
            'wcac_enable_debug_logging',
            esc_html__('Enable Debug Logging', 'wp-customer-ai-chatbot'),
            [$this, 'render_enable_debug_logging_field'],
            $this->option_key,
            'wcac_debug_logging_section'
        );
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
     * Render the API Key input field.
     *
     * @since 0.1.0
     * @param array $args Field arguments.
     */
    public function render_api_key_field(array $args): void
    {
        $options = get_option($this->option_key);
        $api_key = $options['wcac_api_key'] ?? '';
        echo '<input type="password" id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($this->option_key . '[wcac_api_key]') . '" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Enter your API key from OpenRouter.ai.', 'wp-customer-ai-chatbot') . '</p>';
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
     * Render the checkboxes for content types to index.
     *
     * @since 0.1.1
     */
    public function render_index_content_types_field(): void
    {
        $options = get_option($this->option_key);
        $index_products = $options['wcac_index_products'] ?? true;
        $index_pages = $options['wcac_index_pages'] ?? false;
        $index_posts = $options['wcac_index_posts'] ?? false;
        ?>
        <fieldset>
            <legend class="screen-reader-text"><span><?php esc_html_e('Content Types to Index', 'wp-customer-ai-chatbot'); ?></span></legend>
            <label for="wcac_index_products">
                <input type="checkbox" id="wcac_index_products" name="<?php echo esc_attr($this->option_key . '[wcac_index_products]'); ?>" value="1" <?php checked($index_products, 1); ?> />
                <?php esc_html_e('Index WooCommerce Products', 'wp-customer-ai-chatbot'); ?>
            </label>
            <br/>
            <label for="wcac_index_pages">
                <input type="checkbox" id="wcac_index_pages" name="<?php echo esc_attr($this->option_key . '[wcac_index_pages]'); ?>" value="1" <?php checked($index_pages, 1); ?> />
                <?php esc_html_e('Index Pages', 'wp-customer-ai-chatbot'); ?>
            </label>
            <br/>
            <label for="wcac_index_posts">
                <input type="checkbox" id="wcac_index_posts" name="<?php echo esc_attr($this->option_key . '[wcac_index_posts]'); ?>" value="1" <?php checked($index_posts, 1); ?> />
                <?php esc_html_e('Index Posts (e.g., Blog Posts)', 'wp-customer-ai-chatbot'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('Select which types of content should be included in the chatbot\'s knowledge index. Rebuilding the index is required after changing these settings.', 'wp-customer-ai-chatbot'); ?>
            </p>
        </fieldset>
        <?php
    }

    /**
     * Render the manual re-index button and status area.
     *
     * @since 0.1.1 (Modified 0.1.5 for batching UI)
     */
    public function render_reindex_button_field(): void
    {
        ?>
        <button type="button" id="wcac-reindex-button" class="button button-secondary">
            <?php esc_html_e('Re-index Content', 'wp-customer-ai-chatbot'); ?>
        </button>
        <p class="description">
            <?php esc_html_e('Click this button to manually rebuild the content index. This may take some time depending on the amount of content.', 'wp-customer-ai-chatbot'); ?>
        </p>
        <div id="wcac-reindex-status-container" style="margin-top: 10px; padding: 10px; border: 1px solid #ccd0d4; background-color: #f6f7f7; display: none;">
             <progress id="wcac-reindex-progress" value="0" max="100" style="width: 100%; margin-bottom: 5px; display: none;"></progress>
             <span id="wcac-reindex-status" style="font-style: italic;"></span>
        </div>
        <?php
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
     * Render the Negative Keywords textarea field.
     *
     * @since 0.1.1
     * @param array $args Field arguments.
     */
    public function render_negative_keywords_field(array $args): void
    {
        $options = get_option($this->option_key);
        $negative_keywords = $options['wcac_negative_keywords'] ?? '';
        ?>
        <textarea id="<?php echo esc_attr($args['label_for']); ?>" 
                  name="<?php echo esc_attr($this->option_key . '[wcac_negative_keywords]'); ?>" 
                  rows="6" 
                  class="large-text code"><?php echo esc_textarea($negative_keywords); ?></textarea>
        <p class="description">
            <?php esc_html_e('Enter words or phrases (one per line) that should be completely removed from content before it is indexed. Useful for removing boilerplate text, disclaimers, or irrelevant sections. Changes require re-indexing.', 'wp-customer-ai-chatbot'); ?>
        </p>
        <?php
    }

    /**
     * Render the System Prompt textarea field.
     *
     * @since 0.1.1
     * @param array $args Field arguments.
     */
    public function render_system_prompt_field(array $args): void
    {
        $options = get_option($this->option_key);
        // Retrieve value, but DO NOT apply default here. Default is handled on the public side.
        $system_prompt = $options['wcac_system_prompt'] ?? ''; // Get saved value or empty string

        echo '<textarea id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($this->option_key . '[wcac_system_prompt]') . '" rows="15" class="large-text code">' . esc_textarea($system_prompt) . '</textarea>';
        echo '<p class="description">' . esc_html__('The main instruction prompt for the AI. Controls its personality, core rules, and task. Use {{store_name}} as a placeholder for the site name.', 'wp-customer-ai-chatbot') . '</p>';
        // Add the Restore Default button
        echo '<p><button type="button" id="wcac-restore-default-prompt" class="button button-secondary">' . esc_html__('Restore Default Prompt', 'wp-customer-ai-chatbot') . '</button></p>';
    }

    /**
     * Render the Site-Specific Prompt textarea field.
     *
     * @since 0.1.1
     * @param array $args Field arguments.
     */
    public function render_site_prompt_field(array $args): void
    {
        $options = get_option($this->option_key);
        $site_prompt = $options['wcac_site_prompt'] ?? '';
        ?>
        <textarea id="<?php echo esc_attr($args['label_for']); ?>" 
                  name="<?php echo esc_attr($this->option_key . '[wcac_site_prompt]'); ?>" 
                  rows="4" 
                  class="large-text code"><?php echo esc_textarea($site_prompt); ?></textarea>
        <p class="description">
            <?php esc_html_e('Add specific instructions, rules, or context about *this* particular website (e.g., return policy summary, special promotions, brand voice notes). This is appended to the system prompt.', 'wp-customer-ai-chatbot'); ?>
        </p>
        <?php
    }

    /**
     * Render the Custom CSS textarea field.
     *
     * @since 0.1.1
     * @param array $args Field arguments.
     */
    public function render_custom_css_field(array $args): void
    {
        $options = get_option($this->option_key);
        $custom_css = $options['wcac_custom_css'] ?? '';
        ?>
        <textarea id="<?php echo esc_attr($args['label_for']); ?>" 
                  name="<?php echo esc_attr($this->option_key . '[wcac_custom_css]'); ?>" 
                  rows="8" 
                  class="large-text code"><?php echo esc_textarea($custom_css); ?></textarea>
        <p class="description">
            <?php esc_html_e('Add custom CSS rules to style the chat widget appearance. These rules will be added inline on the frontend.', 'wp-customer-ai-chatbot'); ?>
        </p>
        <?php
    }

    /**
     * Render the Synonym Map textarea field with help and restore button.
     *
     * @since 0.1.1
     * @param array $args Field arguments.
     */
    public function render_synonym_map_field(array $args): void
    {
        $options = get_option($this->option_key, []);
        $value = $options['wcac_synonym_map'] ?? '';
        echo '<textarea id="wcac_synonym_map_field" name="' . esc_attr($this->option_key) . '[wcac_synonym_map]" rows="6" cols="60">' . esc_textarea($value) . '</textarea>';
        echo '<button type="button" class="button-secondary" id="wcac-restore-default-synonyms" style="margin-top:8px;">Restore Defaults</button>';
        echo '<div style="margin-top:8px;">
			<a href="#" id="wcac-synonym-help-toggle" onclick="event.preventDefault();var h=document.getElementById(\'wcac-synonym-help\');h.style.display=h.style.display===\'block\'?\'none\':\'block\';">Show Synonym Help</a>
			<div id="wcac-synonym-help" style="display:none; margin-top:8px; background:#f8f9fa; border:1px solid #eee; padding:10px; border-radius:4px;">
				<strong>How to use synonyms:</strong><br>
				<ul style="margin-left:18px;">
				  <li>Enter <b>comma-separated</b> synonyms, one group per line.</li>
				  <li>All terms in a group are considered equivalent for search and matching.</li>
				  <li>Use for spelling variants, abbreviations, or common alternatives.</li>
				  <li>Keep groups universal and non-domain-specific for best results.</li>
				</ul>
				<b>Examples:</b><br>
				<pre style="background:#f4f4f4; padding:6px; border-radius:3px;">color,colour
catalog,collection
fiber,fibre
yarn,wool
pattern,design
store,shop
size,dimension
sale,discount,offer
</pre>
				<b>Tip:</b> You can add as many groups as needed. Avoid domain-specific jargon unless necessary.
			</div>
		</div>';
        echo '<p class="description">Enter comma-separated synonyms, one group per line. All terms in a group are considered equivalent. E.g.: catalog,collection</p>';
        // Inline JS for restore button
        echo '<script>
		document.addEventListener("DOMContentLoaded", function() {
			var btn = document.getElementById("wcac-restore-default-synonyms");
			if (btn) {
				btn.addEventListener("click", function() {
					var field = document.getElementById("wcac_synonym_map_field");
					if (field) {
						field.value = "color,colour\ncatalog,collection\nfiber,fibre\nyarn,wool\npattern,design\nstore,shop\nsize,dimension\nsale,discount,offer\n";
					}
				});
			}
		});
		</script>';
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

    /**
     * Centralized helper to render an LLM parameter field with label, value, range, and help text.
     *
     * @param string $id Field ID and name suffix
     * @param string $label Field label
     * @param string $value Current value
     * @param string $type Input type (e.g., 'range', 'number', 'text')
     * @param array $attrs Additional attributes (min, max, step, etc.)
     * @param string $description Help text/description
     */
    private function render_llm_param_field(string $id, string $label, $value, string $type, array $attrs, string $description): void
    {
        $input_attrs = '';
        foreach ($attrs as $attr => $attr_val) {
            $input_attrs .= esc_attr($attr) . '="' . esc_attr($attr_val) . '" ';
        }
        ?>
        <label for="<?php echo esc_attr($id); ?>"><strong><?php echo esc_html($label); ?></strong></label><br>
        <input type="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($this->option_key . "[$id]"); ?>" value="<?php echo esc_attr($value); ?>" <?php echo $input_attrs; ?>oninput="this.nextElementSibling.value = this.value">
        <output><?php echo esc_attr($value); ?></output>
        <p class="description"> <?php echo esc_html($description); ?> </p>
        <?php
    }

    public function render_temperature_field(array $args): void
    {
        $options = get_option($this->option_key);
        $temperature = $options['wcac_temperature'] ?? '0.7';
        $this->render_llm_param_field(
            'wcac_temperature',
            __('Temperature', 'wp-customer-ai-chatbot'),
            $temperature,
            'range',
            ['min' => '0', 'max' => '2', 'step' => '0.01'],
            __('Controls randomness: 0.0 is deterministic, 2.0 is very creative. (Min: 0.0, Max: 2.0)', 'wp-customer-ai-chatbot')
        );
    }

    public function render_top_p_field(array $args): void
    {
        $options = get_option($this->option_key);
        $top_p = $options['wcac_top_p'] ?? '1.0';
        $this->render_llm_param_field(
            'wcac_top_p',
            __('Top P (Nucleus Sampling)', 'wp-customer-ai-chatbot'),
            $top_p,
            'range',
            ['min' => '0', 'max' => '1', 'step' => '0.01'],
            __('Controls diversity of output. 1.0 = most diverse. (Min: 0.0, Max: 1.0)', 'wp-customer-ai-chatbot')
        );
    }

    public function render_max_tokens_field(array $args): void
    {
        $options = get_option($this->option_key);
        $max_tokens = $options['wcac_max_tokens'] ?? '1024';
        $this->render_llm_param_field(
            'wcac_max_tokens',
            __('Max Tokens', 'wp-customer-ai-chatbot'),
            $max_tokens,
            'range',
            ['min' => '128', 'max' => '4096', 'step' => '64'],
            __('Maximum number of tokens to generate in the output. (Min: 128, Max: 4096)', 'wp-customer-ai-chatbot')
        );
    }

    public function render_frequency_penalty_field(array $args): void
    {
        $options = get_option($this->option_key);
        $frequency_penalty = $options['wcac_frequency_penalty'] ?? '0';
        $this->render_llm_param_field(
            'wcac_frequency_penalty',
            __('Frequency Penalty', 'wp-customer-ai-chatbot'),
            $frequency_penalty,
            'range',
            ['min' => '-2', 'max' => '2', 'step' => '0.01'],
            __('Reduces repetition of the same words. (Min: -2.0, Max: 2.0)', 'wp-customer-ai-chatbot')
        );
    }

    public function render_presence_penalty_field(array $args): void
    {
        $options = get_option($this->option_key, Wcac_Utils::get_default_settings());
        $this->render_llm_param_field(
            'wcac_presence_penalty',
            esc_html__('Presence Penalty', 'wp-customer-ai-chatbot'),
            $options['wcac_presence_penalty'] ?? Wcac_Utils::get_default_settings()['wcac_presence_penalty'],
            'number',
            ['step' => '0.01', 'min' => '0', 'max' => '2'],
            esc_html__('Value between 0 and 2. Higher values penalize new tokens based on whether they appear in the text so far, increasing the model\'s likelihood to talk about new topics.', 'wp-customer-ai-chatbot')
        );
    }

    /**
     * Render the LLM Model input field.
     *
     * @since 0.1.1
     * @param array $args Field arguments.
     */
    public function render_model_field(array $args): void
    {
        $options = get_option($this->option_key);
        $model = $options['wcac_model'] ?? '';
        ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($this->option_key . '[wcac_model]'); ?>" value="<?php echo esc_attr($model); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e('The model parameter specifies the LLM model to use.', 'wp-customer-ai-chatbot'); ?>
        </p>
        <button type="button" id="wcac-test-llm-connection" class="button button-secondary" style="margin-top:8px;">
            <?php esc_html_e('Test Connection', 'wp-customer-ai-chatbot'); ?>
        </button>
        <div id="wcac-llm-test-toast" style="display:none;position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;background:#23282d;color:#fff;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.15);"></div>
        <script>
        function showWcacToast(message, type) {
            var toast = document.getElementById('wcac-llm-test-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'wcac-llm-test-toast';
                document.body.appendChild(toast);
            }
            toast.textContent = message;
            toast.style.display = 'block';
            if (type === 'success') {
                toast.style.background = '#28a745';
            } else if (type === 'error') {
                toast.style.background = '#dc3545';
            } else {
                toast.style.background = '#23282d';
            }
            setTimeout(function() { toast.style.display = 'none'; }, 3500);
        }
        document.addEventListener('DOMContentLoaded', function() {
            var btn = document.getElementById('wcac-test-llm-connection');
            if (btn) {
                btn.addEventListener('click', function() {
                    btn.disabled = true;
                    showWcacToast('Testing connection...', 'info');
                    fetch(ajaxurl + '?action=wcac_test_llm_connection', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        credentials: 'same-origin',
                        body: 'nonce=' + encodeURIComponent(wcacAdmin.test_nonce)
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            showWcacToast('LLM API connection successful!', 'success');
                        } else {
                            showWcacToast('LLM API test failed: ' + (data.data && data.data.message ? data.data.message : 'Unknown error'), 'error');
                        }
                        btn.disabled = false;
                    })
                    .catch(function(err) {
                        showWcacToast('AJAX error: ' + err, 'error');
                        btn.disabled = false;
                    });
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Render the Generate Debug Logs field.
     *
     * @since NEXT_VERSION
     * @param array $args Field arguments.
     */
    public function render_enable_debug_logging_field(array $args): void
    {
        $options = get_option($this->option_key, []);
        $checked = !empty($options['wcac_enable_debug_logging']) ? 'checked' : '';
        ?>
        <input type="checkbox" id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($this->option_key); ?>[wcac_enable_debug_logging]" value="1" <?php echo $checked; ?>>
        <label for="<?php echo esc_attr($args['label_for']); ?>">
            <?php esc_html_e('Enable debug logging for plugin actions and errors. Recommended only for troubleshooting.', 'wp-customer-ai-chatbot'); ?>
        </label>
        <?php
    }

    // New: Render the context compression algorithm dropdown
    public function render_context_compression_algorithm_field(array $args = []): void
    {
        $options = get_option($this->option_key, []);
        $value = $options['wcac_context_compression_algorithm'] ?? 'title';
        ?>
        <select id="<?php echo esc_attr($args['label_for'] ?? 'wcac_context_compression_algorithm_field'); ?>" name="<?php echo esc_attr($this->option_key); ?>[wcac_context_compression_algorithm]">
            <option value="none" <?php selected($value, 'none'); ?>><?php esc_html_e('None', 'wp-customer-ai-chatbot'); ?></option>
            <option value="title" <?php selected($value, 'title'); ?>><?php esc_html_e('By Title', 'wp-customer-ai-chatbot'); ?></option>
            <option value="category" <?php selected($value, 'category'); ?>><?php esc_html_e('By Category', 'wp-customer-ai-chatbot'); ?></option>
            <option value="fuzzy" <?php selected($value, 'fuzzy'); ?>><?php esc_html_e('Fuzzy', 'wp-customer-ai-chatbot'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Choose how to deduplicate and cluster similar results for LLM context. "None" disables compression.', 'wp-customer-ai-chatbot'); ?></p>
        <?php
    }

    /**
     * Sanitize settings array.
     *
     * @since 0.1.0
     * @param array $input The settings array.
     * @return array Sanitized settings array.
     */
    public function sanitize_settings(array $input): array
    {
        error_log('WCAC DEBUG: sanitize_settings input: ' . print_r($input, true));
        $new_input = [];

        // Use centralized optimizer parameter keys
        if (!class_exists('Wcac_Settings_Optimizer')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/optimizer/class-wcac-settings-optimizer.php';
        }
        $optimizer_keys = \Wcac_Settings_Optimizer::get_parameter_keys();

        // Log unknown/dropped parameters
        foreach ($input as $key => $value) {
            if (!in_array($key, $optimizer_keys, true)
                && strpos($key, 'wcac_') === 0 // Only warn for plugin-related keys
                && $key !== 'wcac_api_key' // Allow API key
                && $key !== 'wcac_enable_debug_logging' // Allow debug logging
                && $key !== 'wcac_negative_keywords' // Allow negative keywords
                && $key !== 'wcac_system_prompt' // Allow system prompt
                && $key !== 'wcac_site_prompt' // Allow site prompt
                && $key !== 'wcac_custom_css' // Allow custom CSS
                && $key !== 'wcac_synonym_map' // Allow synonym map
                && $key !== 'wcac_temperature' // Allow LLM params
                && $key !== 'wcac_top_p'
                && $key !== 'wcac_max_tokens'
                && $key !== 'wcac_frequency_penalty'
                && $key !== 'wcac_presence_penalty'
                && $key !== 'wcac_model'
                && $key !== 'wcac_index_products'
                && $key !== 'wcac_index_pages'
                && $key !== 'wcac_index_posts') {
                error_log('WCAC WARNING: Unknown or dropped parameter in settings input: ' . $key);
            }
        }

        // Preserve existing non-optimizer settings if any are stored in the same option group
        // This example assumes all keys in 'wcac_settings' are optimizer-related or handled below.
        // If other settings share this option key, they need to be explicitly preserved.
        // For now, we only focus on keys known to the optimizer and existing UI fields.

        if (isset($input['wcac_api_key'])) {
            $new_input['wcac_api_key'] = sanitize_text_field($input['wcac_api_key']);
        }

        // Sanitize checkbox values
        $checkboxes = ['wcac_index_products', 'wcac_index_pages', 'wcac_index_posts'];
        foreach ($checkboxes as $cb_key) {
            $new_input[$cb_key] = isset($input[$cb_key]) && ($input[$cb_key] == '1' || $input[$cb_key] === true || $input[$cb_key] === 1);
        }
        
        if (isset($input['wcac_negative_keywords'])) {
            $lines = wcac_safe_split("\\n", $input['wcac_negative_keywords']);
            $sanitized_lines = array_map('sanitize_text_field', $lines);
            $new_input['wcac_negative_keywords'] = implode("\\n", $sanitized_lines);
        } else {
            $new_input['wcac_negative_keywords'] = '';
        }

        if (isset($input['wcac_system_prompt'])) {
            $new_input['wcac_system_prompt'] = sanitize_textarea_field($input['wcac_system_prompt']);
        }
        if (isset($input['wcac_site_prompt'])) {
            $new_input['wcac_site_prompt'] = sanitize_textarea_field($input['wcac_site_prompt']);
        }
        if (isset($input['wcac_custom_css'])) {
            $new_input['wcac_custom_css'] = wp_strip_all_tags($input['wcac_custom_css']);
        }

        // LLM API parameters
        if (isset($input['wcac_temperature'])) {
            $temp = (float) sanitize_text_field($input['wcac_temperature']);
            $new_input['wcac_temperature'] = max(0.0, min(2.0, $temp)); // OpenAI allows up to 2.0
        }
        if (isset($input['wcac_top_p'])) {
            $top_p = (float) sanitize_text_field($input['wcac_top_p']);
            $new_input['wcac_top_p'] = max(0.0, min(1.0, $top_p));
        }
        if (isset($input['wcac_max_tokens'])) {
            $new_input['wcac_max_tokens'] = max(0, (int) sanitize_text_field($input['wcac_max_tokens']));
        }
        if (isset($input['wcac_frequency_penalty'])) {
            $freq_p = (float) sanitize_text_field($input['wcac_frequency_penalty']);
            $new_input['wcac_frequency_penalty'] = max(-2.0, min(2.0, $freq_p));
        }
        if (isset($input['wcac_presence_penalty'])) {
            $pres_p = (float) sanitize_text_field($input['wcac_presence_penalty']);
            $new_input['wcac_presence_penalty'] = max(-2.0, min(2.0, $pres_p));
        }
        if (isset($input['wcac_model'])) {
            $new_input['wcac_model'] = sanitize_text_field($input['wcac_model']);
        }
        
        // Scoring Weights & Parameters (from $optimizer_keys)
        if (isset($input['wcac_parent_product_weight'])) {
            $new_input['wcac_parent_product_weight'] = max(0, min(500, floatval($input['wcac_parent_product_weight'])));
        }
        if (isset($input['wcac_variation_product_weight'])) {
            $new_input['wcac_variation_product_weight'] = max(0, min(500, floatval($input['wcac_variation_product_weight'])));
        }
        if (isset($input['wcac_title_match_weight'])) {
            $new_input['wcac_title_match_weight'] = max(0, min(100, floatval($input['wcac_title_match_weight'])));
        }
        if (isset($input['wcac_content_match_weight'])) {
            $new_input['wcac_content_match_weight'] = max(0, min(100, floatval($input['wcac_content_match_weight'])));
        }
        if (isset($input['wcac_category_match_weight'])) {
            $new_input['wcac_category_match_weight'] = max(0, min(100, floatval($input['wcac_category_match_weight'])));
        }
        if (isset($input['wcac_tag_match_weight'])) {
            $new_input['wcac_tag_match_weight'] = max(0, min(100, floatval($input['wcac_tag_match_weight'])));
        }
        if (isset($input['wcac_on_sale_weight'])) {
            $new_input['wcac_on_sale_weight'] = max(0, min(50, floatval($input['wcac_on_sale_weight'])));
        }
        if (isset($input['wcac_direct_title_match_bonus'])) {
            $new_input['wcac_direct_title_match_bonus'] = max(0, min(200, floatval($input['wcac_direct_title_match_bonus'])));
        }
        if (isset($input['wcac_title_category_match_boost'])) {
            $new_input['wcac_title_category_match_boost'] = max(0, floatval($input['wcac_title_category_match_boost']));
        }
        if (isset($input['wcac_exact_product_name_boost'])) {
            $new_input['wcac_exact_product_name_boost'] = max(0, min(300, floatval($input['wcac_exact_product_name_boost'])));
        }
        if (isset($input['wcac_max_results_returned'])) {
            $new_input['wcac_max_results_returned'] = max(1, min(100, (int) $input['wcac_max_results_returned']));
        }
        if (isset($input['wcac_negative_keyword_penalty'])) {
            $new_input['wcac_negative_keyword_penalty'] = min(0, max(-200, floatval($input['wcac_negative_keyword_penalty'])));
        }
        if (isset($input['wcac_multi_field_match_bonus'])) {
            $new_input['wcac_multi_field_match_bonus'] = max(0, floatval($input['wcac_multi_field_match_bonus']));
        }
        if (isset($input['wcac_all_keywords_in_title_boost'])) {
            $new_input['wcac_all_keywords_in_title_boost'] = max(0, floatval($input['wcac_all_keywords_in_title_boost']));
        }
        if (isset($input['wcac_parent_preference_margin'])) {
            $new_input['wcac_parent_preference_margin'] = max(0.0, floatval($input['wcac_parent_preference_margin']));
        }
        if (isset($input['wcac_relative_score_threshold'])) {
            $new_input['wcac_relative_score_threshold'] = max(0.0, min(1.0, floatval($input['wcac_relative_score_threshold'])));
        }
        if (isset($input['wcac_menu_match_weight'])) {
            $new_input['wcac_menu_match_weight'] = max(0, min(100, floatval($input['wcac_menu_match_weight'])));
        }
        // Newly added explicit sanitization for previously unhandled optimizer keys
        if (isset($input['wcac_attribute_match_weight'])) {
            $new_input['wcac_attribute_match_weight'] = max(0, floatval($input['wcac_attribute_match_weight']));
        } else { // Ensure these keys exist if optimizer sends them, even if not from form
             if (array_key_exists('wcac_attribute_match_weight', $input)) { // Check if optimizer sent it (could be null)
                $new_input['wcac_attribute_match_weight'] = floatval($input['wcac_attribute_match_weight']);
             }
        }
        if (isset($input['wcac_parent_category_match_weight'])) {
            $new_input['wcac_parent_category_match_weight'] = max(0, floatval($input['wcac_parent_category_match_weight']));
        } else {
             if (array_key_exists('wcac_parent_category_match_weight', $input)) {
                $new_input['wcac_parent_category_match_weight'] = floatval($input['wcac_parent_category_match_weight']);
             }
        }
        if (isset($input['wcac_taxonomy_match_weight'])) {
            $new_input['wcac_taxonomy_match_weight'] = max(0, floatval($input['wcac_taxonomy_match_weight']));
        } else {
            if (array_key_exists('wcac_taxonomy_match_weight', $input)) {
                $new_input['wcac_taxonomy_match_weight'] = floatval($input['wcac_taxonomy_match_weight']);
            }
        }


        // Sanitize Synonym Map (textarea, lines of comma-separated terms)
        if (isset($input['wcac_synonym_map'])) {
            $new_input['wcac_synonym_map'] = sanitize_textarea_field($input['wcac_synonym_map']);
        } else {
            $new_input['wcac_synonym_map'] = '';
        }

        // Sanitize Compound Product Names (textarea, one per line)
        if (isset($input['wcac_compound_product_names'])) {
            $new_input['wcac_compound_product_names'] = sanitize_textarea_field($input['wcac_compound_product_names']);
        } else {
            $new_input['wcac_compound_product_names'] = '';
        }
        
        // Boost/Devalue Terms (expect arrays from optimizer, or string from form)
        $term_keys = ['wcac_boost_terms', 'wcac_devalue_terms'];
        foreach ($term_keys as $term_key) {
            if (isset($input[$term_key])) {
                if (is_string($input[$term_key])) { // From form textarea
                    $new_input[$term_key] = array_values(array_filter(array_map('trim', preg_split('/[\\s,]+/', $input[$term_key]))));
                } elseif (is_array($input[$term_key])) { // From optimizer or already processed
                    $new_input[$term_key] = array_values(array_filter(array_map('trim', $input[$term_key])));
                } else {
                    $new_input[$term_key] = []; // Default to empty array if unexpected type
                }
            } else {
                // If the key is not in input at all (e.g. initial save from optimizer with no previous value)
                // and it's an optimizer key, it should default to an empty array.
                if (in_array($term_key, $optimizer_keys, true)) {
                     $new_input[$term_key] = [];
                }
            }
        }
        
        // Sanitize new boost/devalue score values
        if (isset($input['wcac_boost_term_value'])) {
            $new_input['wcac_boost_term_value'] = floatval($input['wcac_boost_term_value']);
        }
        if (isset($input['wcac_devalue_term_value'])) {
             $new_input['wcac_devalue_term_value'] = min(0.0, floatval($input['wcac_devalue_term_value'])); // Ensure <= 0
        }

        $new_input['wcac_enable_debug_logging'] = isset($input['wcac_enable_debug_logging']) && ($input['wcac_enable_debug_logging'] == '1' || $input['wcac_enable_debug_logging'] === true || $input['wcac_enable_debug_logging'] === 1) ? 1 : 0;

        if (isset($input['wcac_context_compression_algorithm'])) {
            $valid_algos = ['none', 'title', 'category', 'fuzzy']; // Define valid algorithms
            $algo = sanitize_text_field($input['wcac_context_compression_algorithm']);
            $new_input['wcac_context_compression_algorithm'] = in_array($algo, $valid_algos, true) ? $algo : 'none';
        } else {
             $new_input['wcac_context_compression_algorithm'] = 'none'; // Default if not set
        }
        
        if (isset($input['wcac_outofstock_penalty'])) {
            $new_input['wcac_outofstock_penalty'] = min(0, max(-200, floatval($input['wcac_outofstock_penalty'])));
        }

        if (isset($input['wcac_recency_boost_multiplier'])) {
            $new_input['wcac_recency_boost_multiplier'] = max(0, floatval($input['wcac_recency_boost_multiplier']));
        }

        // Ensure all optimizer keys are present in $new_input, defaulting to null or appropriate type if not set by UI/Optimizer
        // This is important because update_option will save the entire $new_input array.
        // If a key from $optimizer_keys was not in $input, it won't be in $new_input yet.
        foreach ($optimizer_keys as $opt_key) {
            if (!array_key_exists($opt_key, $new_input)) {
                // Heuristic for default type based on key name, or fetch actual defaults
                if (str_contains($opt_key, 'terms') || str_contains($opt_key, 'names')) { // e.g. boost_terms, compound_product_names
                    $new_input[$opt_key] = []; // Default array types to empty array
                } else if (str_contains($opt_key, 'penalty') || str_contains($opt_key, 'weight') || str_contains($opt_key, 'bonus') || str_contains($opt_key, 'boost') || str_contains($opt_key, 'margin') || str_contains($opt_key, 'threshold')) {
                     // Attempt to get current value from DB to preserve it if optimizer didn't send it
                     // This part is tricky because $input to sanitize_settings is already merged.
                     // If $input[$opt_key] is not set, it means neither UI nor optimizer provided it.
                     // We might want to load original settings for keys not in $input.
                     // For now, default to 0.0 for numeric-like keys if not explicitly set.
                    $current_db_settings = get_option($this->option_key, []);
                    $new_input[$opt_key] = $current_db_settings[$opt_key] ?? 0.0;
                } else {
                    // For other keys not specifically typed, default to null or empty string
                    // This needs careful consideration based on expected types.
                    // $new_input[$opt_key] = null; 
                }
            }
        }

        if (isset($input['wcac_rating_weight'])) {
            $new_input['wcac_rating_weight'] = max(0, min(100, floatval($input['wcac_rating_weight'])));
        }

        error_log('WCAC DEBUG: sanitize_settings output: ' . print_r($new_input, true));
        return $new_input;
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    0.1.0
     */
    public function display_plugin_setup_page(): void
    {
        try {
            error_log('WCAC DEBUG: Starting display_plugin_setup_page');

            if (!current_user_can('manage_options')) {
                error_log('WCAC DEBUG: User does not have manage_options capability');
                return;
            }

            // Check for the template file existence
            $template_path = WCAC_PLUGIN_DIR . 'admin/partials/wcac-admin-settings.php';
            if (!file_exists($template_path)) {
                error_log('WCAC ERROR: Admin settings template file not found at: ' . $template_path);
                // Fall back to inline display if template is missing
                echo '<div class="wrap"><h1>WP Customer AI Chatbot Settings</h1>';
                echo '<div class="error notice"><p>Settings template file not found. Please reinstall the plugin.</p></div>';

                // Minimal settings form to prevent total failure
                echo '<form action="options.php" method="post">';
                settings_fields($this->option_group);
                do_settings_sections('wcac-settings-page');
                submit_button();
                echo '</form></div>';
                return;
            }

            error_log('WCAC DEBUG: Preparing to include admin display template');
            include_once $template_path;
            error_log('WCAC DEBUG: Admin display template included successfully');
        } catch (Throwable $e) {
            error_log('WCAC ERROR: Exception in display_plugin_setup_page: ' . $e->getMessage());
            error_log('WCAC ERROR: Exception trace: ' . $e->getTraceAsString());

            // Output a basic error message to the admin
            echo '<div class="wrap"><h1>WP Customer AI Chatbot Settings</h1>';
            echo '<div class="error notice"><p>An error occurred while loading the settings page: ' . esc_html($e->getMessage()) . '</p>';
            echo '<p>Please check server logs for more details or contact support.</p></div></div>';
        }
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook_suffix The current admin page
     */
    public function enqueue_admin_assets(string $hook_suffix): void
    {
        try {
            error_log('WCAC DEBUG: Enqueue admin assets for hook: ' . $hook_suffix);

            // Only load on our settings page(s)
            $wcac_pages = [
                $this->plugin_screen_hook_suffix,
                $this->debug_logs_hook_suffix,
                $this->test_results_hook_suffix
            ];

            if (!in_array($hook_suffix, $wcac_pages, true)) {
                error_log('WCAC DEBUG: Skipping asset loading for non-WCAC page');
                return;
            }

            error_log('WCAC DEBUG: Loading admin assets for WCAC page');

            // Try/catch each enqueue operation to isolate problems
            try {
                error_log('WCAC DEBUG: Enqueuing admin CSS');
                wp_enqueue_style(
                    'wcac-admin-css',
                    WCAC_PLUGIN_URL . 'admin/css/wcac-admin.css',
                    [],
                    WCAC_VERSION
                );
                error_log('WCAC DEBUG: Admin CSS enqueued successfully');
            } catch (Throwable $e) {
                error_log('WCAC ERROR: Failed to enqueue admin CSS: ' . $e->getMessage());
            }

            try {
                error_log('WCAC DEBUG: Enqueuing jQuery UI CSS');
                // Removed: wp_enqueue_style for jquery-ui.min.css (file missing, caused MIME error)
                // wp_enqueue_style(
                //     'wcac-jquery-ui-css',
                //     WCAC_PLUGIN_URL . 'admin/css/jquery-ui.min.css',
                //     [],
                //     WCAC_VERSION
                // );
                error_log('WCAC DEBUG: jQuery UI CSS enqueue skipped (file missing)');
            } catch (Throwable $e) {
                error_log('WCAC ERROR: Failed to enqueue jQuery UI CSS: ' . $e->getMessage());
            }

            try {
                error_log('WCAC DEBUG: Enqueuing jQuery UI');
                wp_enqueue_script('jquery-ui-core');
                wp_enqueue_script('jquery-ui-tabs');
                wp_enqueue_script('jquery-ui-dialog');
                error_log('WCAC DEBUG: jQuery UI scripts enqueued successfully');
            } catch (Throwable $e) {
                error_log('WCAC ERROR: Failed to enqueue jQuery UI scripts: ' . $e->getMessage());
            }

            try {
                error_log('WCAC DEBUG: Enqueuing admin JS');
                wp_enqueue_script(
                    'wcac-admin-js',
                    WCAC_PLUGIN_URL . 'admin/js/wcac-admin.js',
                    ['jquery', 'jquery-ui-core', 'jquery-ui-tabs', 'jquery-ui-dialog'],
                    WCAC_VERSION,
                    true
                );
                // Enqueue indexing.js only on the settings page
                if ($hook_suffix === $this->plugin_screen_hook_suffix) {
                    wp_enqueue_script(
                        'wcac-indexing-js',
                        WCAC_PLUGIN_URL . 'admin/js/indexing.js',
                        ['wcac-admin-js'],
                        WCAC_VERSION,
                        true
                    );
                }
                // Enqueue logs.js only on Debug Logs and Test Results pages
                if ($hook_suffix === $this->debug_logs_hook_suffix || $hook_suffix === $this->test_results_hook_suffix) {
                    wp_enqueue_script(
                        'wcac-logs-js',
                        WCAC_PLUGIN_URL . 'admin/js/logs.js',
                        ['wcac-admin-js'],
                        WCAC_VERSION,
                        true
                    );
                }
                error_log('WCAC DEBUG: Admin JS enqueued successfully');
            } catch (Throwable $e) {
                error_log('WCAC ERROR: Failed to enqueue admin JS: ' . $e->getMessage());
            }

            try {
                error_log('WCAC DEBUG: Localizing admin script');
                // Ensure the defaults class is loaded
                if (!class_exists('Wcac_ChatbotDefaults')) {
                    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/common/class-wcac-chatbot-defaults.php';
                }
                error_log('WCAC DEBUG: Wcac_ChatbotDefaults loaded: ' . (class_exists('Wcac_ChatbotDefaults') ? 'YES' : 'NO'));
                $default_prompt = '';
                if (class_exists('Wcac_ChatbotDefaults') && method_exists('Wcac_ChatbotDefaults', 'get_default_system_prompt')) {
                    $default_prompt = Wcac_ChatbotDefaults::get_default_system_prompt();
                    error_log('WCAC DEBUG: Default prompt value: ' . $default_prompt);
                }
                $options = get_option('wcac_settings', []);
                wp_localize_script(
                    'wcac-admin-js',
                    'wcacAdmin',
                    [
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('wcac_admin'),
                        'reindex_nonce' => wp_create_nonce('wcac_reindex_nonce'),
                        'test_nonce' => wp_create_nonce('wcac_test_nonce'),
                        'delete_selected_debug_logs_nonce' => wp_create_nonce('wcac_delete_selected_debug_logs'),
                        'delete_all_debug_logs_nonce' => wp_create_nonce('wcac_delete_all_debug_logs'),
                        'delete_test_results_nonce' => wp_create_nonce('wcac_delete_test_results'),
                        'optimizer_nonce' => wp_create_nonce('wcac_optimizer'),
                        'default_system_prompt' => $default_prompt,
                        'restore_prompt_confirm' => __('Are you sure you want to restore the default system prompt? This will overwrite your current custom prompt.', 'wp-customer-ai-chatbot'),
                        'reindex_confirm' => __('Are you sure you want to re-index the selected content types? This may take a while.', 'wp-customer-ai-chatbot'),
                        'enable_debug_logging' => !empty($options['wcac_enable_debug_logging']) ? 1 : 0,
                        'clear_all_confirm' => __('Are you sure you want to delete ALL test result history? This cannot be undone.', 'wp-customer-ai-chatbot'),
                    ]
                );
                error_log('WCAC DEBUG: Admin script localized successfully');
            } catch (Throwable $e) {
                error_log('WCAC ERROR: Failed to localize admin script: ' . $e->getMessage());
            }

            error_log('WCAC DEBUG: Finished loading admin assets');
        } catch (Throwable $e) {
            error_log('WCAC ERROR: Exception in enqueue_admin_assets: ' . $e->getMessage());
            error_log('WCAC ERROR: Exception trace: ' . $e->getTraceAsString());
        }
    }
} // End class Wcac_Admin_Settings