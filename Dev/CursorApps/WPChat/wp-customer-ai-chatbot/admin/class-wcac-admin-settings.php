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

/**
 * Handles the admin settings page for the plugin.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/admin
 */
class Wcac_Admin_Settings {

    /**
     * The unique identifier of this plugin's settings page.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $plugin_screen_hook_suffix    Stores the hook suffix of the plugin screen.
     */
    private ?string $plugin_screen_hook_suffix = null;
	private ?string $debug_logs_hook_suffix = null;

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
	public function __construct( string $plugin_basename ) {
		$this->plugin_basename = $plugin_basename;
        add_action( 'admin_menu', [ $this, 'add_plugin_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'plugin_action_links_' . $this->plugin_basename, [ $this, 'add_action_links' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        
        // AJAX Actions
        add_action( 'wp_ajax_wcac_get_scoring_glossary', [ $this, 'ajax_get_scoring_glossary' ] );

        // Apply rules from settings at admin load
        $options = get_option('wcac_settings', []);
        
        // Make sure the class exists first
        if (!class_exists('Wcac_ChatbotRules')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wcac-chatbot-rules.php';
        }
        
        if (class_exists('Wcac_ChatbotRules')) {
            Wcac_ChatbotRules::apply_admin_settings($options);
        }

        // Always ensure debug log table schema is up to date
        if (!class_exists('Wcac_Debug_Logger')) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wcac-debug-logger.php';
        }
        if (class_exists('Wcac_Debug_Logger')) {
            Wcac_Debug_Logger::ensure_table_schema();
        }

        // TEMP: Add admin action to delete all logs via URL param for admin users
        // (REMOVED after use)
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    0.1.0
	 * @param array $links An array of plugin action links.
	 * @return array An array of plugin action links.
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=wcac-settings' ),
			esc_html__( 'Settings', 'wp-customer-ai-chatbot' )
		);
		array_unshift( $links, $settings_link ); // Add to beginning of links
		return $links;
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    0.1.0
	 */
	public function add_plugin_admin_menu(): void {
		// Add main menu item
		$this->plugin_screen_hook_suffix = add_menu_page(
			esc_html__('Customer AI Chatbot Settings', 'wp-customer-ai-chatbot'),
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
		$this->debug_logs_hook_suffix = add_submenu_page(
			'wcac-settings',
			esc_html__('Debug Logs', 'wp-customer-ai-chatbot'),
			esc_html__('Debug Logs', 'wp-customer-ai-chatbot'),
			'manage_options',
			'wcac-debug-logs',
			[ $this, 'display_debug_logs_page']
		);
	}

	/**
	 * Alias for add_plugin_admin_menu for backwards compatibility
	 *
	 * @since    0.1.5
	 */
	public function add_options_page(): void {
		$this->add_plugin_admin_menu();
	}

	/**
	 * Register the settings for this plugin.
	 *
	 * @since    0.1.0
	 */
	public function register_settings(): void {
        register_setting(
            $this->option_group,
            $this->option_key,
            [ $this, 'sanitize_settings' ]
        );

        // Core Settings Section
        add_settings_section(
            'wcac_main_section',
            esc_html__( 'Core Settings', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_main_section_header' ],
            'wcac-settings-page'
        );
        add_settings_field(
            'wcac_api_key',
            esc_html__( 'OpenRouter API Key', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_api_key_field' ],
            'wcac-settings-page',
            'wcac_main_section',
            [ 'label_for' => 'wcac_api_key_field' ]
        );

        // Indexing Section
        add_settings_section(
            'wcac_indexing_section',
            esc_html__( 'Content Indexing', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_indexing_section_header' ],
            'wcac-settings-page'
        );
        add_settings_field(
            'wcac_index_content_types',
            esc_html__( 'Content Types to Index', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_index_content_types_field' ],
            'wcac-settings-page',
            'wcac_indexing_section'
        );
        add_settings_field(
            'wcac_reindex_button',
            esc_html__( 'Manual Re-index', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_reindex_button_field' ],
            'wcac-settings-page',
            'wcac_indexing_section'
        );

        // Customization & Filtering Section
        add_settings_section(
            'wcac_customization_section',
            esc_html__( 'Customization & Filtering', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_customization_section_header' ],
            'wcac-settings-page'
        );
        
        add_settings_field(
            'wcac_negative_keywords',
            esc_html__( 'Negative Keywords/Phrases', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_negative_keywords_field' ],
            'wcac-settings-page',
            'wcac_customization_section',
            [ 'label_for' => 'wcac_negative_keywords_field' ]
        );
        
        add_settings_field(
            'wcac_system_prompt',
            esc_html__( 'System Prompt', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_system_prompt_field' ],
            'wcac-settings-page',
            'wcac_customization_section',
            [ 'label_for' => 'wcac_system_prompt_field' ]
        );
        
        add_settings_field(
            'wcac_site_prompt',
            esc_html__( 'Site-Specific Prompt', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_site_prompt_field' ],
            'wcac-settings-page',
            'wcac_customization_section',
            [ 'label_for' => 'wcac_site_prompt_field' ]
        );
        
        add_settings_field(
            'wcac_custom_css',
            esc_html__( 'Custom CSS', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_custom_css_field' ],
            'wcac-settings-page',
            'wcac_customization_section',
            [ 'label_for' => 'wcac_custom_css_field' ]
        );
        
        add_settings_field(
            'wcac_synonym_map',
            esc_html__( 'Synonym Map', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_synonym_map_field' ],
            'wcac-settings-page',
            'wcac_customization_section',
            [ 'label_for' => 'wcac_synonym_map_field' ]
        );

        // LLM API Parameters Section
        add_settings_section(
            'wcac_llm_params_section',
            esc_html__('LLM API Parameters', 'wp-customer-ai-chatbot'),
            [ $this, 'render_llm_params_section_header' ],
            'wcac-settings-page'
        );
        add_settings_field(
            'wcac_temperature',
            esc_html__('Temperature', 'wp-customer-ai-chatbot'),
            [ $this, 'render_temperature_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_temperature_field' ]
        );
        add_settings_field(
            'wcac_top_p',
            esc_html__('Top P (Nucleus Sampling)', 'wp-customer-ai-chatbot'),
            [ $this, 'render_top_p_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_top_p_field' ]
        );
        add_settings_field(
            'wcac_max_tokens',
            esc_html__('Max Tokens', 'wp-customer-ai-chatbot'),
            [ $this, 'render_max_tokens_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_max_tokens_field' ]
        );
        add_settings_field(
            'wcac_frequency_penalty',
            esc_html__('Frequency Penalty', 'wp-customer-ai-chatbot'),
            [ $this, 'render_frequency_penalty_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_frequency_penalty_field' ]
        );
        add_settings_field(
            'wcac_presence_penalty',
            esc_html__('Presence Penalty', 'wp-customer-ai-chatbot'),
            [ $this, 'render_presence_penalty_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_presence_penalty_field' ]
        );
        add_settings_field(
            'wcac_model',
            esc_html__('LLM Model', 'wp-customer-ai-chatbot'),
            [ $this, 'render_model_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_model_field' ]
        );

        // Scoring Rules Section
        add_settings_section(
            'wcac_scoring_section',
            esc_html__('Scoring Rules', 'wp-customer-ai-chatbot'),
            [ $this, 'render_scoring_section_header' ],
            'wcac-settings-page'
        );
        
        // Product Type Weights
        add_settings_field(
            'wcac_parent_product_weight',
            esc_html__('Parent Product Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_parent_product_weight_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_parent_product_weight_field' ]
        );
        add_settings_field(
            'wcac_variation_product_weight',
            esc_html__('Variation Product Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_variation_product_weight_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_variation_product_weight_field' ]
        );
        
        // Field Match Weights
        add_settings_field(
            'wcac_title_match_weight',
            esc_html__('Title Match Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_title_match_weight_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_title_match_weight_field' ]
        );
        add_settings_field(
            'wcac_content_match_weight',
            esc_html__('Content Match Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_content_match_weight_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_content_match_weight_field' ]
        );
        add_settings_field(
            'wcac_category_match_weight',
            esc_html__('Category Match Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_category_match_weight_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_category_match_weight_field' ]
        );
        add_settings_field(
            'wcac_tag_match_weight',
            esc_html__('Tag Match Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_tag_match_weight_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_tag_match_weight_field' ]
        );
        
        // Boost Settings
        add_settings_field(
            'wcac_direct_title_match_bonus',
            esc_html__('Direct Title Match Bonus', 'wp-customer-ai-chatbot'),
            [ $this, 'render_direct_title_match_bonus_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_direct_title_match_bonus_field' ]
        );
        add_settings_field(
            'wcac_title_category_match_boost',
            esc_html__('Title-Category Match Boost', 'wp-customer-ai-chatbot'),
            [ $this, 'render_title_category_match_boost_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_title_category_match_boost_field' ]
        );
        add_settings_field(
            'wcac_exact_product_name_boost',
            esc_html__('Exact Product Name Boost', 'wp-customer-ai-chatbot'),
            [ $this, 'render_exact_product_name_boost_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_exact_product_name_boost_field' ]
        );
        add_settings_field(
            'wcac_multi_field_match_bonus',
            esc_html__('Multi-Field Match Bonus', 'wp-customer-ai-chatbot'),
            [ $this, 'render_multi_field_match_bonus_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_multi_field_match_bonus_field' ]
        );
        add_settings_field(
            'wcac_all_keywords_in_title_boost',
            esc_html__('All Keywords in Title Boost', 'wp-customer-ai-chatbot'),
            [ $this, 'render_all_keywords_in_title_boost_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_all_keywords_in_title_boost_field' ]
        );
        
        // Thresholds and Margins
        add_settings_field(
            'wcac_min_score_threshold',
            esc_html__('Minimum Score Threshold', 'wp-customer-ai-chatbot'),
            [ $this, 'render_min_score_threshold_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_min_score_threshold_field' ]
        );
        add_settings_field(
            'wcac_parent_preference_margin',
            esc_html__('Parent Preference Margin', 'wp-customer-ai-chatbot'),
            [ $this, 'render_parent_preference_margin_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_parent_preference_margin_field' ]
        );
        add_settings_field(
            'wcac_max_results_returned',
            esc_html__('Max Results Returned', 'wp-customer-ai-chatbot'),
            [ $this, 'render_max_results_returned_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_max_results_returned_field' ]
        );
        add_settings_field(
            'wcac_negative_keyword_penalty',
            esc_html__('Negative Keyword Penalty', 'wp-customer-ai-chatbot'),
            [ $this, 'render_negative_keyword_penalty_field' ],
            'wcac-settings-page',
            'wcac_scoring_section',
            [ 'label_for' => 'wcac_negative_keyword_penalty_field' ]
        );
    }

    /**
     * Render the header for the main settings section.
     *
     * @since 0.1.0
     */
    public function render_main_section_header(): void {
        echo '<p>' . esc_html__('Configure core plugin settings including API credentials and basic functionality.', 'wp-customer-ai-chatbot') . '</p>';
    }

    /**
     * Render the API Key input field.
     *
     * @since 0.1.0
	 * @param array $args Field arguments.
     */
    public function render_api_key_field( array $args ): void {
        $options = get_option( $this->option_key );
        $api_key = $options['wcac_api_key'] ?? '';
		echo '<input type="password" id="' . esc_attr( $args['label_for'] ) . '" name="' . esc_attr( $this->option_key . '[wcac_api_key]' ) . '" value="' . esc_attr( $api_key ) . '" class="regular-text">';
		echo '<p class="description">' . esc_html__( 'Enter your API key from OpenRouter.ai.', 'wp-customer-ai-chatbot' ) . '</p>';
    }

	/**
     * Render the header for the indexing settings section.
     *
     * @since 0.1.0
     */
    public function render_indexing_section_header(): void {
		echo '<p>' . esc_html__('Control which content types are indexed and manage the content index.', 'wp-customer-ai-chatbot') . '</p>';
    }

	/**
	 * Render the checkboxes for content types to index.
	 *
	 * @since 0.1.1
	 */
	public function render_index_content_types_field(): void {
		$options = get_option( $this->option_key );
		$index_products = $options['wcac_index_products'] ?? true;
		$index_pages = $options['wcac_index_pages'] ?? false;
		$index_posts = $options['wcac_index_posts'] ?? false;
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Content Types to Index', 'wp-customer-ai-chatbot' ); ?></span></legend>
			<label for="wcac_index_products">
				<input type="checkbox" id="wcac_index_products" name="<?php echo esc_attr( $this->option_key . '[wcac_index_products]' ); ?>" value="1" <?php checked( $index_products, 1 ); ?> />
				<?php esc_html_e( 'Index WooCommerce Products', 'wp-customer-ai-chatbot' ); ?>
			</label>
			<br/>
			<label for="wcac_index_pages">
				<input type="checkbox" id="wcac_index_pages" name="<?php echo esc_attr( $this->option_key . '[wcac_index_pages]' ); ?>" value="1" <?php checked( $index_pages, 1 ); ?> />
				<?php esc_html_e( 'Index Pages', 'wp-customer-ai-chatbot' ); ?>
			</label>
			<br/>
			<label for="wcac_index_posts">
				<input type="checkbox" id="wcac_index_posts" name="<?php echo esc_attr( $this->option_key . '[wcac_index_posts]' ); ?>" value="1" <?php checked( $index_posts, 1 ); ?> />
				<?php esc_html_e( 'Index Posts (e.g., Blog Posts)', 'wp-customer-ai-chatbot' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Select which types of content should be included in the chatbot\'s knowledge index. Rebuilding the index is required after changing these settings.', 'wp-customer-ai-chatbot' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render the manual re-index button and status area.
	 *
	 * @since 0.1.1 (Modified 0.1.5 for batching UI)
	 */
	public function render_reindex_button_field(): void {
		?>
        <button type="button" id="wcac-reindex-button" class="button button-secondary">
            <?php esc_html_e( 'Re-index Content', 'wp-customer-ai-chatbot' ); ?>
        </button>
        <p class="description">
			<?php esc_html_e( 'Click this button to manually rebuild the content index. This may take some time depending on the amount of content.', 'wp-customer-ai-chatbot' ); ?>
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
	public function render_customization_section_header(): void {
		echo '<p>' . esc_html__('Customize the chatbot behavior, appearance, and response generation.', 'wp-customer-ai-chatbot') . '</p>';
	}

	/**
	 * Render the Negative Keywords textarea field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_negative_keywords_field( array $args ): void {
		$options = get_option( $this->option_key );
		$negative_keywords = $options['wcac_negative_keywords'] ?? '';
		?>
		<textarea id="<?php echo esc_attr( $args['label_for'] ); ?>" 
				  name="<?php echo esc_attr( $this->option_key . '[wcac_negative_keywords]' ); ?>" 
				  rows="6" 
				  class="large-text code"><?php echo esc_textarea( $negative_keywords ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Enter words or phrases (one per line) that should be completely removed from content before it is indexed. Useful for removing boilerplate text, disclaimers, or irrelevant sections. Changes require re-indexing.', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the System Prompt textarea field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_system_prompt_field( array $args ): void {
		$options = get_option( $this->option_key );
		// Retrieve value, but DO NOT apply default here. Default is handled on the public side.
		$system_prompt = $options['wcac_system_prompt'] ?? ''; // Get saved value or empty string

		echo '<textarea id="' . esc_attr( $args['label_for'] ) . '" name="' . esc_attr( $this->option_key . '[wcac_system_prompt]' ) . '" rows="15" class="large-text code">' . esc_textarea( $system_prompt ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'The main instruction prompt for the AI. Controls its personality, core rules, and task. Use {{store_name}} as a placeholder for the site name.', 'wp-customer-ai-chatbot' ) . '</p>';
		// Add the Restore Default button
		echo '<p><button type="button" id="wcac-restore-default-prompt" class="button button-secondary">' . esc_html__( 'Restore Default Prompt', 'wp-customer-ai-chatbot' ) . '</button></p>';
	}

	/**
	 * Render the Site-Specific Prompt textarea field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_site_prompt_field( array $args ): void {
		$options = get_option( $this->option_key );
		$site_prompt = $options['wcac_site_prompt'] ?? '';
		?>
		<textarea id="<?php echo esc_attr( $args['label_for'] ); ?>" 
				  name="<?php echo esc_attr( $this->option_key . '[wcac_site_prompt]' ); ?>" 
				  rows="4" 
				  class="large-text code"><?php echo esc_textarea( $site_prompt ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Add specific instructions, rules, or context about *this* particular website (e.g., return policy summary, special promotions, brand voice notes). This is appended to the system prompt.', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Custom CSS textarea field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_custom_css_field( array $args ): void {
		$options = get_option( $this->option_key );
		$custom_css = $options['wcac_custom_css'] ?? '';
		?>
		<textarea id="<?php echo esc_attr( $args['label_for'] ); ?>" 
				  name="<?php echo esc_attr( $this->option_key . '[wcac_custom_css]' ); ?>" 
				  rows="8" 
				  class="large-text code"><?php echo esc_textarea( $custom_css ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Add custom CSS rules to style the chat widget appearance. These rules will be added inline on the frontend.', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Synonym Map textarea field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_synonym_map_field( array $args ): void {
		$options = get_option( $this->option_key, [] );
		$value = $options['wcac_synonym_map'] ?? '';
		echo '<textarea id="wcac_synonym_map_field" name="' . esc_attr( $this->option_key ) . '[wcac_synonym_map]" rows="6" cols="60">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Enter comma-separated synonyms, one group per line. All terms in a group are considered equivalent. E.g.: catalog,collection', 'wp-customer-ai-chatbot' ) . '</p>';
	}

	/**
	 * Render the header for the LLM API Parameters section.
	 *
	 * @since NEXT_VERSION
	 */
	public function render_llm_params_section_header(): void {
		echo '<p>' . esc_html__('Configure the Large Language Model parameters to control response generation.', 'wp-customer-ai-chatbot') . '</p>';
	}

	/**
	 * Render the Temperature input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_temperature_field( array $args ): void {
		$options = get_option( $this->option_key );
		$temperature = $options['wcac_temperature'] ?? '0.7';
		?>
		<input type="range" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_temperature]' ); ?>" value="<?php echo esc_attr( $temperature ); ?>" min="0" max="1" step="0.01" oninput="this.nextElementSibling.value = this.value">
		<output><?php echo esc_attr( $temperature ); ?></output>
		<p class="description">
			<?php esc_html_e( 'Controls randomness: 0.0 is deterministic, 1.0 is very creative. (Min: 0.0, Max: 1.0)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Top P (Nucleus Sampling) input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_top_p_field( array $args ): void {
		$options = get_option( $this->option_key );
		$top_p = $options['wcac_top_p'] ?? '1.0';
		?>
		<input type="range" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_top_p]' ); ?>" value="<?php echo esc_attr( $top_p ); ?>" min="0" max="1" step="0.01" oninput="this.nextElementSibling.value = this.value">
		<output><?php echo esc_attr( $top_p ); ?></output>
		<p class="description">
			<?php esc_html_e( 'The top_p parameter controls the nucleus sampling, which affects the diversity of the output. (Min: 0.0, Max: 1.0)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Max Tokens input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_max_tokens_field( array $args ): void {
		$options = get_option( $this->option_key );
		$max_tokens = $options['wcac_max_tokens'] ?? '1024';
		?>
		<input type="range" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_max_tokens]' ); ?>" value="<?php echo esc_attr( $max_tokens ); ?>" min="128" max="2048" step="64" oninput="this.nextElementSibling.value = this.value">
		<output><?php echo esc_attr( $max_tokens ); ?></output>
		<p class="description">
			<?php esc_html_e( 'The max_tokens parameter controls the maximum number of tokens to generate in the output. (Min: 128, Max: 2048)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Frequency Penalty input field.
	 *
	 * @since 0.1.8
	 * @param array $args Field arguments.
	 */
	public function render_frequency_penalty_field( array $args ): void {
		$options = get_option( $this->option_key );
		$frequency_penalty = $options['wcac_frequency_penalty'] ?? '0';
		?>
		<input type="range" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_frequency_penalty]' ); ?>" value="<?php echo esc_attr( $frequency_penalty ); ?>" min="-2" max="2" step="0.01" oninput="this.nextElementSibling.value = this.value">
		<output><?php echo esc_attr( $frequency_penalty ); ?></output>
		<p class="description">
			<?php esc_html_e( 'Reduces repetition of the same words. (Min: -2.0, Max: 2.0)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Presence Penalty input field.
	 *
	 * @since 0.1.8
	 * @param array $args Field arguments.
	 */
	public function render_presence_penalty_field( array $args ): void {
		$options = get_option( $this->option_key );
		$presence_penalty = $options['wcac_presence_penalty'] ?? '0';
		?>
		<input type="range" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_presence_penalty]' ); ?>" value="<?php echo esc_attr( $presence_penalty ); ?>" min="-2" max="2" step="0.01" oninput="this.nextElementSibling.value = this.value">
		<output><?php echo esc_attr( $presence_penalty ); ?></output>
		<p class="description">
			<?php esc_html_e( 'Encourages the model to talk about new topics. (Min: -2.0, Max: 2.0)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the LLM Model input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_model_field( array $args ): void {
		$options = get_option( $this->option_key );
		$model = $options['wcac_model'] ?? '';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_model]' ); ?>" value="<?php echo esc_attr( $model ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'The model parameter specifies the LLM model to use.', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the header for the Rules section.
	 *
	 * @since NEXT_VERSION
	 */
	public function render_scoring_section_header(): void {
		echo '<p>' . esc_html__('Configure how different factors affect search result scoring and ranking. Higher weights give more importance to those matches.', 'wp-customer-ai-chatbot') . '</p>';
	}

	/**
	 * Render the Parent Product Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_parent_product_weight_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_parent_product_weight'] ?? Wcac_ChatbotRules::$parent_product_weight;
		?>
		<input type="number" step="1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_parent_product_weight]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Base score for parent products. Higher values (150+) ensure parent products appear first in broad searches. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$parent_product_weight); ?>
		</p>
		<?php
	}

	/**
	 * Render the Variation Product Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_variation_product_weight_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_variation_product_weight'] ?? Wcac_ChatbotRules::$variation_product_weight;
		?>
		<input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_variation_product_weight]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Base score for product variations. Keep low (1-5) to prevent variations from dominating broad searches. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$variation_product_weight); ?>
		</p>
		<?php
	}

	/**
	 * Render the Title Match Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_title_match_weight_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_title_match_weight'] ?? Wcac_ChatbotRules::$title_match_weight;
		?>
		<input type="number" step="1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_title_match_weight]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Score added for each keyword match in titles. Higher values (25+) prioritize exact title matches. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$title_match_weight); ?>
		</p>
		<?php
	}

	/**
	 * Render the Content Match Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_content_match_weight_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_content_match_weight'] ?? Wcac_ChatbotRules::$content_match_weight;
		?>
		<input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_content_match_weight]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Score added per keyword found in the item content/description snippet. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$content_match_weight); ?>
		</p>
		<?php
	}

	/**
	 * Render the Category Match Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_category_match_weight_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_category_match_weight'] ?? Wcac_ChatbotRules::$category_match_weight;
		?>
		<input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_category_match_weight]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Score added per keyword found in the item\'s assigned categories. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$category_match_weight); ?>
		</p>
		<?php
	}

	/**
	 * Render the Tag Match Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_tag_match_weight_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_tag_match_weight'] ?? Wcac_ChatbotRules::$tag_match_weight;
		?>
		<input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_tag_match_weight]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Score added per keyword found in the item\'s assigned tags. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$tag_match_weight); ?>
		</p>
		<?php
	}

	/**
	 * Render the On Sale Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_on_sale_weight_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_on_sale_weight'] ?? Wcac_ChatbotRules::$on_sale_weight;
		echo '<hr><h4>' . esc_html__( 'Attribute-Based Scoring', 'wp-customer-ai-chatbot' ) . '</h4>';
		echo '<p>' . esc_html__( 'Score adjustments based on specific product attributes.', 'wp-customer-ai-chatbot' ) . '</p>';
		?>
		<input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_on_sale_weight]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Score added if the product is currently marked as \'on sale\'. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$on_sale_weight); ?>
		</p>
		<?php
	}

	/**
	 * Render the Direct Title Match Bonus input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_direct_title_match_bonus_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_direct_title_match_bonus'] ?? Wcac_ChatbotRules::$direct_title_match_bonus;
		echo '<hr><h4>' . esc_html__( 'Match & Context Boosts', 'wp-customer-ai-chatbot' ) . '</h4>';
		echo '<p>' . esc_html__( 'Additional score boosts based on how keywords match or the context of the match.', 'wp-customer-ai-chatbot' ) . '</p>';
		?>
		<input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_direct_title_match_bonus]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Large bonus added if the user\'s full query exactly matches the item title. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$direct_title_match_bonus); ?>
		</p>
		<?php
	}

	/**
	 * Render the Title-Category Match Boost input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_title_category_match_boost_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_title_category_match_boost'] ?? Wcac_ChatbotRules::$title_category_match_boost;
		?>
		<input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_title_category_match_boost]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Bonus score added when at least one keyword matches the title AND at least one matches a category. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$title_category_match_boost); ?>
		</p>
		<?php
	}

	/**
	 * Render the Exact Product Name Boost input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_exact_product_name_boost_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_exact_product_name_boost'] ?? Wcac_ChatbotRules::$exact_product_name_boost;
		?>
		<input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_exact_product_name_boost]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Significant bonus score added when the item title *exactly* matches (or starts with) a known compound product name (e.g., \'Peer Gynt\', \'Tynn Silk Mohair\'). Helps prioritize specific product results. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$exact_product_name_boost); ?>
		</p>
		<?php
	}

	/**
	 * Render the Minimum Score Threshold input field.
	 * OBSOLETE - Replaced by Relative Score Threshold
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_min_score_threshold_field(array $args): void {
		// This setting is less useful than the relative threshold. Hide it for now.
		/*
		$options = get_option($this->option_key);
		$value = $options['wcac_min_score_threshold'] ?? Wcac_ChatbotRules::$min_score_threshold;
		?>
		<input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_min_score_threshold]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('OBSOLETE: Minimum absolute score an item must have to be considered relevant. Use Relative Score Threshold instead. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$min_score_threshold); ?>
		</p>
		<?php
		*/
		 echo '<p><i>' . esc_html__('Setting is obsolete, replaced by Relative Score Threshold below.', 'wp-customer-ai-chatbot') . '</i></p>';
	}

	/**
	 * Render the Maximum Results Returned input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_max_results_returned_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_max_results_returned'] ?? Wcac_ChatbotRules::$max_results_returned;
		echo '<hr><h4>' . esc_html__( 'Result Filtering & Limits', 'wp-customer-ai-chatbot' ) . '</h4>';
		echo '<p>' . esc_html__( 'Control how many results are returned and how strict the filtering is.', 'wp-customer-ai-chatbot' ) . '</p>';
		?>
		<input type="number" step="1" min="1" max="20" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_max_results_returned]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Maximum number of product/content results to display in the chat response. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$max_results_returned); ?>
		</p>
		<?php
	}

	/**
	 * Render the Negative Keyword Penalty input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_negative_keyword_penalty_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_negative_keyword_penalty'] ?? Wcac_ChatbotRules::$negative_keyword_penalty;
		echo '<hr><h4>' . esc_html__( 'Penalties', 'wp-customer-ai-chatbot' ) . '</h4>';
		?>
		<input type="number" step="0.1" min="-500" max="0" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_negative_keyword_penalty]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Penalty (negative score) applied if item contains negative keywords (defined under Customization). Must be 0 or negative. Use 0 to disable. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$negative_keyword_penalty); ?>
		</p>
		<?php
	}

	/**
	 * Render the Multi-Field Match Bonus input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_multi_field_match_bonus_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_multi_field_match_bonus'] ?? Wcac_ChatbotRules::$multi_field_match_bonus;
		// Moved under Match & Context Boosts section
		?>
		 <input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_multi_field_match_bonus]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Bonus score added when keywords match across multiple fields (e.g., title AND content). Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$multi_field_match_bonus); ?>
		</p>
		<?php
	}

	/**
	 * Render the All Keywords in Title Boost input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_all_keywords_in_title_boost_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_all_keywords_in_title_boost'] ?? Wcac_ChatbotRules::$all_keywords_in_title_boost;
		// Moved under Match & Context Boosts section
		?>
		 <input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_all_keywords_in_title_boost]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('Bonus score added when ALL extracted keywords from the user query are found within the item title. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$all_keywords_in_title_boost); ?>
		</p>
	   <?php
	}

	/**
	 * Render the Fuzzy Match Boost (High >60%) input field.
	 * TODO: Fuzzy matching is not currently implemented in score_item.
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_fuzzy_match_boost_high_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_fuzzy_match_boost_high'] ?? Wcac_ChatbotRules::$fuzzy_match_boost_high;
		echo '<hr><h4>' . esc_html__( 'Fuzzy Matching (Future Feature)', 'wp-customer-ai-chatbot' ) . '</h4>';
		echo '<p>' . esc_html__( 'These settings are for a planned fuzzy matching feature and currently have no effect.', 'wp-customer-ai-chatbot' ) . '</p>';
		?>
		 <input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_fuzzy_match_boost_high]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>" disabled>
		<p class="description">
			<?php esc_html_e('(Future) Bonus score for high fuzzy title match similarity (>60%). Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$fuzzy_match_boost_high); ?>
		</p>
		<?php
	}

	/**
	 * Render the Fuzzy Match Boost (Medium >40%) input field.
	 * TODO: Fuzzy matching is not currently implemented in score_item.
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_fuzzy_match_boost_medium_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_fuzzy_match_boost_medium'] ?? Wcac_ChatbotRules::$fuzzy_match_boost_medium;
		?>
		 <input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_fuzzy_match_boost_medium]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>" disabled>
		<p class="description">
			<?php esc_html_e('(Future) Bonus score for medium fuzzy title match similarity (>40%). Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$fuzzy_match_boost_medium); ?>
		</p>
		<?php
	}

	/**
	 * Render the Fuzzy Match Boost (Low >30%) input field.
	 * TODO: Fuzzy matching is not currently implemented in score_item.
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_fuzzy_match_boost_low_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_fuzzy_match_boost_low'] ?? Wcac_ChatbotRules::$fuzzy_match_boost_low;
		?>
		 <input type="number" step="0.1" min="0" max="500" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_fuzzy_match_boost_low]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>" disabled>
		<p class="description">
			<?php esc_html_e('(Future) Bonus score for low fuzzy title match similarity (>30%). Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$fuzzy_match_boost_low); ?>
		</p>
	   <?php
	}

	/**
	 * Render the Parent Preference Margin input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_parent_preference_margin_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_parent_preference_margin'] ?? Wcac_ChatbotRules::$parent_preference_margin;
		?>
		<input type="number" step="1" min="0" max="100" class="small-text"
			   id="<?php echo esc_attr($args['label_for']); ?>"
			   name="<?php echo esc_attr($this->option_key . '[wcac_parent_preference_margin]'); ?>"
			   value="<?php echo esc_attr((string)$value); ?>">
		<p class="description">
			<?php esc_html_e('How much to boost parent products above their variations in broad searches. Higher values (25+) ensure parents rank above variations unless the query specifically matches variation attributes. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$parent_preference_margin); ?>
		</p>
		<?php
	}

	/**
	 * Render the Relative Score Threshold input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_relative_score_threshold_field(array $args): void {
		$options = get_option($this->option_key);
		$value = $options['wcac_relative_score_threshold'] ?? Wcac_ChatbotRules::$relative_score_threshold; // Default from class
		// Moved under Result Filtering section
		?>
		<input type="number" id="<?php echo esc_attr($args['label_for']); ?>" name="<?php echo esc_attr($this->option_key); ?>[wcac_relative_score_threshold]" 
			   value="<?php echo esc_attr((string)$value); ?>" 
			   step="0.05" min="0" max="1.0" class="small-text" />
		<p class="description">
			<?php esc_html_e('Items must score at least this fraction of the top score (e.g., 0.4 = 40%) to be included. Range: 0.0 to 1.0. Default: ', 'wp-customer-ai-chatbot'); echo esc_html(Wcac_ChatbotRules::$relative_score_threshold); ?>
		</p>
		<?php
	}

	/**
	 * Render the Compound Product Names textarea field.
	 *
	 * @since NEXT_VERSION
	 * @param array $args Field arguments.
	 */
	public function render_compound_product_names_field(array $args): void {
		$options = get_option($this->option_key);
		// Use the default from the class only if the option isn't set (for initial display)
		// However, the class default will be empty [] now, so we just need the saved option.
		$value = $options['wcac_compound_product_names'] ?? ''; // Default to empty string
		?>
		<textarea id="<?php echo esc_attr($args['label_for']); ?>" 
			name="<?php echo esc_attr($this->option_key . '[wcac_compound_product_names]'); ?>" 
			rows="5" 
			class="large-text code"><?php echo esc_textarea($value); ?></textarea>
		<p class="description">
			<?php echo esc_html__( 'Enter compound product names (one per line). Use this for multi-word product names that should be treated as a single unit during search.', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize settings array.
	 *
	 * @since 0.1.0
	 * @param array $input The settings array.
	 * @return array Sanitized settings array.
	 */
	public function sanitize_settings(array $input): array {

		$new_input = [];
		if (isset($input['wcac_api_key'])) {
			$new_input['wcac_api_key'] = sanitize_text_field($input['wcac_api_key']);
		}

        // Sanitize checkbox values (ensure they are 1 or not set)
        $new_input['wcac_index_products'] = isset($input['wcac_index_products']) && $input['wcac_index_products'] == '1';
        $new_input['wcac_index_pages'] = isset($input['wcac_index_pages']) && $input['wcac_index_pages'] == '1';
        $new_input['wcac_index_posts'] = isset($input['wcac_index_posts']) && $input['wcac_index_posts'] == '1';

		// Sanitize Negative Keywords textarea
		if (isset($input['wcac_negative_keywords'])) {
            // Sanitize each line individually
            $lines = explode("\n", $input['wcac_negative_keywords']);
            $sanitized_lines = array_map('sanitize_text_field', $lines);
            $new_input['wcac_negative_keywords'] = implode("\n", $sanitized_lines);
        }

		// Sanitize Prompts 
		if (isset($input['wcac_system_prompt'])) {
            $new_input['wcac_system_prompt'] = sanitize_textarea_field($input['wcac_system_prompt']);
        }
		if (isset($input['wcac_site_prompt'])) {
            $new_input['wcac_site_prompt'] = sanitize_textarea_field($input['wcac_site_prompt']);
        }

		// Sanitize Custom CSS 
        if (isset($input['wcac_custom_css'])) {
            $new_input['wcac_custom_css'] = wp_strip_all_tags($input['wcac_custom_css']);
        }

		// Sanitize LLM API parameters
		if (isset($input['wcac_temperature'])) {
			$temp = (float) sanitize_text_field($input['wcac_temperature']);
			$new_input['wcac_temperature'] = max(0.0, min(1.0, $temp));
		}
		if (isset($input['wcac_top_p'])) {
			$top_p = (float) sanitize_text_field($input['wcac_top_p']);
			$new_input['wcac_top_p'] = max(0.0, min(1.0, $top_p));
		}
		if (isset($input['wcac_max_tokens'])) {
			$tokens = (int) sanitize_text_field($input['wcac_max_tokens']);
			$new_input['wcac_max_tokens'] = max(1, $tokens);
		}
		if (isset($input['wcac_frequency_penalty'])) {
			$freq = (float) sanitize_text_field($input['wcac_frequency_penalty']);
			$new_input['wcac_frequency_penalty'] = max(-2.0, min(2.0, $freq));
		}
		if (isset($input['wcac_presence_penalty'])) {
			$pres = (float) sanitize_text_field($input['wcac_presence_penalty']);
			$new_input['wcac_presence_penalty'] = max(-2.0, min(2.0, $pres));
		}
		if (isset($input['wcac_model'])) {
			$new_input['wcac_model'] = sanitize_text_field($input['wcac_model']);
		}

		// Sanitize Rules section
		// (Using floatval directly for numeric inputs as sanitize_text_field might be overkill 
        // and potentially cause issues if locale affects decimal separators, though unlikely here)
		if (isset($input['wcac_parent_product_weight'])) {
			$new_input['wcac_parent_product_weight'] = max(0, min(500, floatval($input['wcac_parent_product_weight'])));
		}
		if (isset($input['wcac_variation_product_weight'])) {
			$new_input['wcac_variation_product_weight'] = max(0, min(500, floatval($input['wcac_variation_product_weight'])));
		}
		if (isset($input['wcac_title_match_weight'])) {
			$new_input['wcac_title_match_weight'] = max(0, min(500, floatval($input['wcac_title_match_weight'])));
		}
		if (isset($input['wcac_content_match_weight'])) {
			$new_input['wcac_content_match_weight'] = max(0, min(500, floatval($input['wcac_content_match_weight'])));
		}
		if (isset($input['wcac_category_match_weight'])) {
			$new_input['wcac_category_match_weight'] = max(0, min(500, floatval($input['wcac_category_match_weight'])));
		}
		if (isset($input['wcac_tag_match_weight'])) {
			$new_input['wcac_tag_match_weight'] = max(0, min(500, floatval($input['wcac_tag_match_weight'])));
		}
		if (isset($input['wcac_on_sale_weight'])) {
			$new_input['wcac_on_sale_weight'] = max(0, min(500, floatval($input['wcac_on_sale_weight'])));
		}
		if (isset($input['wcac_direct_title_match_bonus'])) {
			$new_input['wcac_direct_title_match_bonus'] = max(0, min(500, floatval($input['wcac_direct_title_match_bonus'])));
		}
		if (isset($input['wcac_title_category_match_boost'])) {
			$new_input['wcac_title_category_match_boost'] = max(0, floatval($input['wcac_title_category_match_boost']));
		}
		if (isset($input['wcac_exact_product_name_boost'])) {
			$new_input['wcac_exact_product_name_boost'] = max(0, min(500, floatval($input['wcac_exact_product_name_boost'])));
		}
		if (isset($input['wcac_min_score_threshold'])) {
            // This field is obsolete but keep sanitizing if present
			$new_input['wcac_min_score_threshold'] = max(0, floatval($input['wcac_min_score_threshold'])); 
		}
		if (isset($input['wcac_max_results_returned'])) {
			$new_input['wcac_max_results_returned'] = max(1, min(100, (int) $input['wcac_max_results_returned']));
		}
		if (isset($input['wcac_negative_keyword_penalty'])) {
			$new_input['wcac_negative_keyword_penalty'] = min(0, floatval($input['wcac_negative_keyword_penalty'])); 
		}
		if (isset($input['wcac_multi_field_match_bonus'])) {
			$new_input['wcac_multi_field_match_bonus'] = max(0, floatval($input['wcac_multi_field_match_bonus']));
		}
		if (isset($input['wcac_all_keywords_in_title_boost'])) {
			$new_input['wcac_all_keywords_in_title_boost'] = max(0, floatval($input['wcac_all_keywords_in_title_boost']));
		}
		if (isset($input['wcac_fuzzy_match_boost_high'])) {
			$new_input['wcac_fuzzy_match_boost_high'] = max(0, floatval($input['wcac_fuzzy_match_boost_high']));
		}
		if (isset($input['wcac_fuzzy_match_boost_medium'])) {
			$new_input['wcac_fuzzy_match_boost_medium'] = max(0, floatval($input['wcac_fuzzy_match_boost_medium']));
		}
		if (isset($input['wcac_fuzzy_match_boost_low'])) {
			$new_input['wcac_fuzzy_match_boost_low'] = max(0, floatval($input['wcac_fuzzy_match_boost_low']));
		}
		if (isset($input['wcac_parent_preference_margin'])) {
			$new_input['wcac_parent_preference_margin'] = max(0.0, floatval($input['wcac_parent_preference_margin'])); 
		}
		if (isset($input['wcac_relative_score_threshold'])) {
			$new_input['wcac_relative_score_threshold'] = max(0.0, min(1.0, floatval($input['wcac_relative_score_threshold'])));
		}

		if (isset($input['wcac_synonym_map'])) {
			$new_input['wcac_synonym_map'] = sanitize_textarea_field($input['wcac_synonym_map']);
		}

		// Sanitize Compound Product Names - Use sanitize_textarea_field to preserve newlines
		if (isset($input['wcac_compound_product_names'])) {
			$new_input['wcac_compound_product_names'] = sanitize_textarea_field($input['wcac_compound_product_names']);
		} else {
            // Ensure the key exists even if empty
			$new_input['wcac_compound_product_names'] = ''; 
		}

		return $new_input;
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    0.1.0
	 */
	public function display_plugin_setup_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-customer-ai-chatbot'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="wcac-settings-wrapper">
                <div id="wcac-settings-sections-wrapper">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields($this->option_group);
                        do_settings_sections('wcac-settings-page');
                        submit_button();
                        ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook_suffix): void {
        // Enqueue assets for both the main settings page and debug logs page
        if ($hook_suffix === $this->plugin_screen_hook_suffix || $hook_suffix === $this->debug_logs_hook_suffix) {
            // Enqueue our admin styles
            wp_enqueue_style(
                'wcac-admin-style',
                WCAC_PLUGIN_URL . 'admin/css/wcac-admin.css',
                [],
                WCAC_VERSION,
                'all'
            );
            
            // Enqueue our admin script
            wp_enqueue_script(
                'wcac-admin-script',
                WCAC_PLUGIN_URL . 'admin/js/wcac-admin.js',
                ['jquery'],
                WCAC_VERSION,
                true
            );
            
            // Base script data for AJAX
            $script_data = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcac_admin_nonce')
            ];
            
            // Only add the settings-specific scripts/data for the main settings page
            if ($hook_suffix === $this->plugin_screen_hook_suffix) {
                // Enqueue jQuery UI for tabs
                wp_enqueue_script('jquery-ui-core');
                wp_enqueue_script('jquery-ui-tabs');
                
                // Get the default system prompt from the defaults class
                require_once WCAC_PLUGIN_DIR . 'includes/class-wcac-chatbot-defaults.php';
                $default_system_prompt = Wcac_ChatbotDefaults::get_default_system_prompt();
     
                // Add settings-specific data
                $script_data = array_merge($script_data, [
                    'reindex_confirm' => esc_html__('Are you sure you want to re-index the selected content types? This might take a while.', 'wp-customer-ai-chatbot'),
                    'reindexing_text' => esc_html__('Indexing...', 'wp-customer-ai-chatbot'),
                    'reindex_success_template' => esc_html__('Success! Indexed Content. Total: %d (Products: %d, Pages: %d, Posts: %d)', 'wp-customer-ai-chatbot'),
                    'reindex_error_text' => esc_html__('Error during re-indexing. Check server logs.', 'wp-customer-ai-chatbot'),
                    'restore_prompt_confirm' => esc_html__('Are you sure you want to restore the default system prompt? Any changes you made will be lost.', 'wp-customer-ai-chatbot'),
                    'default_system_prompt' => $default_system_prompt
                ]);
            }

            // Localize the script with the appropriate data
            wp_localize_script('wcac-admin-script', 'wcac_admin_data', $script_data);
            
            // Inline CSS specific to the debug logs page
            if ($hook_suffix === $this->debug_logs_hook_suffix) {
                wp_add_inline_style('wcac-admin-style', '
                    .log-details-row { display: none; }
                    .log-details-cell { 
                        padding: 20px !important; 
                        background-color: #f9f9f9; 
                        position: relative;
                    }
                    .log-details-content {
                        min-height: 50px; /* Prevent collapse with minimal content */
                        position: relative; /* For proper event handling */
                    }
                    .log-row { 
                        cursor: pointer; 
                        position: relative; /* For proper z-index handling */
                        z-index: 1;
                    }
                    .log-row:hover { background-color: #f1f1f1; }
                    .match-badge {
                        display: inline-block;
                        padding: 3px 8px;
                        margin: 2px;
                        border-radius: 3px;
                        font-size: 12px;
                        color: #fff;
                        background-color: #666;
                    }
                    .match-badge.total-score { background-color: #1d2327; font-weight: bold; }
                    .match-badge.title { background-color: #007cba; }
                    .match-badge.content { background-color: #46b450; }
                    .match-badge.category { background-color: #826eb4; }
                    .match-badge.tag { background-color: #d63638; }
                    .match-badge.direct { background-color: #fd7e14; }
                    .match-badge.type { background-color: #00a0d2; }
                    .match-badge.sale { background-color: #dc3545; }
                    .result-item {
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        padding: 10px;
                        margin-bottom: 15px;
                        background-color: #fff;
                    }
                    .result-item h4 {
                        margin-top: 0;
                        margin-bottom: 10px;
                        padding-bottom: 5px;
                        border-bottom: 1px solid #eee;
                    }
                    .matched-fields-summary {
                        margin-top: 10px;
                        margin-bottom: 15px;
                    }
                    /* New styles for scoring breakdown */
                    .scoring-breakdown {
                        margin-top: 15px;
                        border-top: 1px solid #eee;
                        padding-top: 10px;
                    }
                    .scoring-breakdown h5 {
                        margin-top: 0;
                        margin-bottom: 10px;
                        font-size: 14px;
                        font-weight: 600;
                    }
                    .scoring-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 10px;
                        background-color: #fff;
                        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                    }
                    .scoring-table th {
                        text-align: left;
                        background-color: #f7f7f7;
                        padding: 8px;
                        border-bottom: 1px solid #ddd;
                        font-weight: 600;
                    }
                    .scoring-table td {
                        padding: 8px;
                        border-bottom: 1px solid #eee;
                    }
                    .score-row:hover {
                        background-color: #f9f9f9;
                    }
                    .score-total td {
                        border-top: 2px solid #ddd;
                        background-color: #f7f7f7;
                    }
                    /* Color-coding for different score types */
                    .score-title td:first-child { border-left: 3px solid #007cba; }
                    .score-content td:first-child { border-left: 3px solid #46b450; }
                    .score-category td:first-child { border-left: 3px solid #826eb4; }
                    .score-tag td:first-child { border-left: 3px solid #d63638; }
                    .score-direct td:first-child { border-left: 3px solid #fd7e14; }
                    .score-type td:first-child { border-left: 3px solid #00a0d2; }
                    .score-sale td:first-child { border-left: 3px solid #dc3545; }
                    .score-total td:first-child { border-left: 3px solid #1d2327; }
                ');
                
                // Add inline script for toggling log details
                wp_add_inline_script('wcac-admin-script', '
                    jQuery(document).ready(function($) {
                        // Click handler for log rows
                        $(".log-row").on("click", function(e) {
                            // Don\'t toggle if checkbox was clicked
                            if ($(e.target).is("input[type=checkbox]")) {
                                return;
                            }
                            
                            // Prevent any other handlers from firing
                            e.preventDefault();
                            e.stopPropagation();
                            
                            // Get the log ID
                            var logId = $(this).data("log-id");
                            
                            // Toggle the details row
                            var detailsRow = $("#log-details-" + logId);
                            detailsRow.toggle();
                            
                            // Toggle the indicator icon
                            $(this).find(".dashicons").toggleClass("dashicons-arrow-down dashicons-arrow-up");
                            
                            return false; // Extra safety to prevent default behavior
                        });
                        
                        // Prevent clicks inside details from propagating upward
                        $(".log-details-cell").on("click", function(e) {
                            e.stopPropagation();
                        });
                        
                        // Click handler for "select all" checkbox
                        $("#cb-select-all-1").on("click", function() {
                            $("input[name^=\'log_ids\']").prop("checked", this.checked);
                        });
                    });
                ');
            }
        }
    }

    /**
     * Display the debug logs page.
     *
     * @since    0.1.1
     */
    public function display_debug_logs_page(): void {
        if (!class_exists('Wcac_Debug_Logger')) {
            require_once WCAC_PLUGIN_DIR . 'includes/class-wcac-debug-logger.php';
        }

        // Initialize the debug logger if not already initialized
        if (class_exists('Wcac_Debug_Logger')) {
            Wcac_Debug_Logger::init();
        }

        // Handle logging toggle
        if (isset($_POST['wcac_toggle_debug_logging'])) {
            check_admin_referer('wcac_toggle_debug_logging', 'wcac_toggle_debug_logging_nonce');
            $enable = isset($_POST['wcac_enable_debug_logging']) ? '1' : '0';
            $options = get_option($this->option_key, []);
            $options['wcac_enable_debug_logging'] = $enable;
            update_option($this->option_key, $options);
            add_settings_error('wcac_messages', 'wcac_message', __('Debug logging setting updated.', 'wp-customer-ai-chatbot'), 'updated');
        }
        $options = get_option($this->option_key, []);
        $debug_logging_enabled = !empty($options['wcac_enable_debug_logging']) || (defined('WP_DEBUG') && WP_DEBUG);

        // Handle delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete_logs' && isset($_POST['log_ids'])) {
            check_admin_referer('wcac_delete_logs', 'wcac_nonce');
            $log_ids = array_map('intval', $_POST['log_ids']);
            Wcac_Debug_Logger::delete_logs($log_ids);
            add_settings_error('wcac_messages', 'wcac_message', __('Selected logs deleted successfully.', 'wp-customer-ai-chatbot'), 'updated');
        }

        // Handle delete all logs action
        if (isset($_POST['action']) && $_POST['action'] === 'delete_all_logs') {
            check_admin_referer('wcac_delete_all_logs', 'wcac_delete_all_logs_nonce');
            $result = Wcac_Debug_Logger::delete_all_logs();
            if ($result !== false) {
                add_settings_error('wcac_messages', 'wcac_message', __('All logs deleted successfully.', 'wp-customer-ai-chatbot'), 'updated');
            } else {
                error_log('WCAC ERROR: Failed to delete all logs.');
                add_settings_error('wcac_messages', 'wcac_message', __('Failed to delete all logs.', 'wp-customer-ai-chatbot'), 'error');
            }
        }

        // Handle test logging action
        if (isset($_POST['action']) && $_POST['action'] === 'test_logging') {
            check_admin_referer('wcac_test_logging', 'wcac_test_logging_nonce');
            if (!class_exists('Wcac_Debug_Logger')) {
                require_once WCAC_PLUGIN_DIR . 'includes/class-wcac-debug-logger.php';
            }
            if (class_exists('Wcac_Debug_Logger')) {
                $test_results = Wcac_Debug_Logger::test_logging();
                
                if ($test_results['success']) {
                    add_settings_error('wcac_messages', 'wcac_message', __('Debug logging system tested successfully.', 'wp-customer-ai-chatbot'), 'updated');
                } else {
                    $error_message = __('Debug logging test failed: ', 'wp-customer-ai-chatbot') . $test_results['error'];
                    
                    // Add detailed diagnostics
                    $details = '<ul>';
                    foreach ($test_results['details'] as $key => $value) {
                        $details .= '<li>' . esc_html($key) . ': ' . ($value ? 'Yes' : 'No') . '</li>';
                    }
                    $details .= '</ul>';
                    
                    add_settings_error('wcac_messages', 'wcac_message', $error_message . $details, 'error');
                    error_log('WCAC ERROR: Debug logging test failed: ' . $test_results['error'] . ' - Details: ' . json_encode($test_results['details']));
                }
            }
        }

        // Handle force table creation action
        if (isset($_POST['action']) && $_POST['action'] === 'force_table_creation') {
            check_admin_referer('wcac_force_table_creation', 'wcac_force_table_creation_nonce');
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'wcac_debug_logs';
            $charset_collate = $wpdb->get_charset_collate();
            
            // First check if table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if (!$table_exists) {
                // Try direct SQL creation
                $result = $wpdb->query("CREATE TABLE $table_name (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    query text NOT NULL,
                    keywords text NOT NULL,
                    products_data longtext NOT NULL,
                    settings longtext NULL,
                    llm_params longtext NULL,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id)
                ) $charset_collate");
                
                if ($result === false) {
                    add_settings_error('wcac_messages', 'wcac_message', __('Failed to create debug logs table: ', 'wp-customer-ai-chatbot') . $wpdb->last_error, 'error');
                } else {
                    add_settings_error('wcac_messages', 'wcac_message', __('Debug logs table created successfully via direct SQL.', 'wp-customer-ai-chatbot'), 'updated');
                }
            } else {
                // Check if columns exist and add if missing
                $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name", ARRAY_A);
                $column_names = array_map(function($col) { return $col['Field']; }, $columns);
                
                $updates_made = false;
                
                if (!in_array('settings', $column_names)) {
                    $wpdb->query("ALTER TABLE $table_name ADD COLUMN settings longtext NULL AFTER products_data");
                    $updates_made = true;
                }
                
                if (!in_array('llm_params', $column_names)) {
                    $wpdb->query("ALTER TABLE $table_name ADD COLUMN llm_params longtext NULL AFTER settings");
                    $updates_made = true;
                }
                
                if ($updates_made) {
                    add_settings_error('wcac_messages', 'wcac_message', __('Debug logs table structure updated successfully.', 'wp-customer-ai-chatbot'), 'updated');
                } else {
                    add_settings_error('wcac_messages', 'wcac_message', __('Debug logs table exists and has correct structure.', 'wp-customer-ai-chatbot'), 'updated');
                }
            }
            
            // Try a test insert
            $test_data = [
                'query' => 'TEST QUERY (DIRECT SQL)',
                'keywords' => json_encode(['test']),
                'products_data' => json_encode(['name' => 'Test Product', 'score' => 1.0]),
                'settings' => json_encode(['test_setting' => true]),
                'llm_params' => json_encode(['test_param' => true]),
            ];
            
            $insert_result = $wpdb->insert($table_name, $test_data);
            if ($insert_result === false) {
                add_settings_error('wcac_messages', 'wcac_message', __('Failed to insert test log entry: ', 'wp-customer-ai-chatbot') . $wpdb->last_error, 'error');
            } else {
                add_settings_error('wcac_messages', 'wcac_message', __('Test log entry created successfully via direct SQL.', 'wp-customer-ai-chatbot'), 'updated');
            }
        }

        // Get current page number
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;

        // Get logs
        $logs = Wcac_Debug_Logger::get_logs($page, $per_page);
        $total_logs = Wcac_Debug_Logger::get_total_logs();
        $total_pages = ceil($total_logs / $per_page);

        // Display the logs
        ?>
        <div class="wrap wcac-debug-logs-page">
            <h1><?php echo esc_html__('Debug Logs', 'wp-customer-ai-chatbot'); ?></h1>
            
            <?php settings_errors('wcac_messages'); ?>
            
            <div class="wcac-log-actions">
                <?php if (!empty($logs)): ?>
                    <form method="post" action="" class="wcac-log-action-form">
                        <?php wp_nonce_field('wcac_delete_all_logs', 'wcac_delete_all_logs_nonce'); ?>
                        <input type="hidden" name="action" value="delete_all_logs">
                        <input type="submit" class="button button-secondary delete-all-logs-button" value="<?php esc_attr_e('Delete All Logs', 'wp-customer-ai-chatbot'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete ALL logs? This cannot be undone.', 'wp-customer-ai-chatbot'); ?>');">
                    </form>
                <?php endif; ?>
                <form method="post" action="" class="wcac-log-action-form">
                    <?php wp_nonce_field('wcac_test_logging', 'wcac_test_logging_nonce'); ?>
                    <input type="hidden" name="action" value="test_logging">
                    <input type="submit" class="button button-secondary" value="<?php esc_attr_e('Test Logging', 'wp-customer-ai-chatbot'); ?>">
                </form>
                
                <?php if (current_user_can('manage_options')): ?>
                <form method="post" action="" class="wcac-log-action-form">
                    <?php wp_nonce_field('wcac_force_table_creation', 'wcac_force_table_creation_nonce'); ?>
                    <input type="hidden" name="action" value="force_table_creation">
                    <input type="submit" class="button button-secondary" value="<?php esc_attr_e('Advanced Debug Test', 'wp-customer-ai-chatbot'); ?>" title="<?php esc_attr_e('Force table creation using direct SQL', 'wp-customer-ai-chatbot'); ?>">
                </form>
                <?php endif; ?>
                
                <a href="#" id="wcac-scoring-glossary-link" class="button button-secondary" style="margin-left: 10px;"><?php esc_html_e('Scoring Glossary', 'wp-customer-ai-chatbot'); ?></a>

            </div>

            <?php if (empty($logs)): ?>
                <p><?php echo esc_html__('No debug logs found.', 'wp-customer-ai-chatbot'); ?></p>
                
                <div class="notice notice-info">
                    <p><?php _e('Debug logs may not be showing because:', 'wp-customer-ai-chatbot'); ?></p>
                    <ul>
                        <li><?php _e('No chat queries have been made since logging was enabled', 'wp-customer-ai-chatbot'); ?></li>
                        <li><?php _e('The debug logs table may not exist or have the correct structure', 'wp-customer-ai-chatbot'); ?></li>
                        <li><?php _e('There might be database permission issues for creating or writing to the table', 'wp-customer-ai-chatbot'); ?></li>
                    </ul>
                    <p><?php _e('Click "Test Logging" above to troubleshoot database issues.', 'wp-customer-ai-chatbot'); ?></p>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <?php wp_nonce_field('wcac_delete_logs', 'wcac_nonce'); ?>
                    <input type="hidden" name="action" value="delete_logs">
                    
                    <div class="tablenav top">
                        <div class="alignleft actions">
                            <input type="submit" class="button action" value="<?php esc_attr_e('Delete Selected Logs', 'wp-customer-ai-chatbot'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete the selected logs?', 'wp-customer-ai-chatbot'); ?>');">
                        </div>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all-1">
                                </td>
                                <th><?php echo esc_html__('Created At', 'wp-customer-ai-chatbot'); ?></th>
                                <th><?php echo esc_html__('Query', 'wp-customer-ai-chatbot'); ?></th>
                                <th><?php echo esc_html__('Keywords', 'wp-customer-ai-chatbot'); ?></th>
                                <th class="log-row-indicator" style="text-align:center;width:24px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (
    $logs as $log): ?>
    <tr class="log-row" data-log-id="<?php echo esc_attr($log['id']); ?>">
        <td class="check-column">
            <input type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log['id']); ?>">
        </td>
        <td class="column-created_at"><?php echo esc_html($log['created_at']); ?></td>
        <td class="column-query"><?php echo esc_html($log['query']); ?></td>
        <td class="column-keywords"><?php 
            $keywords = json_decode($log['keywords'], true);
            echo esc_html(is_array($keywords) ? implode(', ', $keywords) : $keywords);
        ?></td>
        <td class="log-row-indicator" style="text-align:center;width:24px;">
            <span class="dashicons dashicons-arrow-down"></span>
        </td>
    </tr>
    <tr id="log-details-<?php echo esc_attr($log['id']); ?>" class="log-details-row">
        <td colspan="5" class="log-details-cell">
            <div class="log-details-content">
                <?php
                try {
                    $products_data = json_decode($log['products_data'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \Exception('Malformed products_data JSON: ' . json_last_error_msg());
                    }
                    if (is_array($products_data)) {
                        foreach ($products_data as $result) {
                            echo '<div class="result-item">';
                            echo '<h4>' . esc_html($result['title'] ?? '') . '</h4>';
                            echo '<div class="matched-fields-summary">';
                            if (isset($result['score'])) {
                                $scoreVal = $result['score'];
                                $scoreDisplay = (is_float($scoreVal) || is_int($scoreVal)) ? number_format($scoreVal, 2) : 'N/A';
                                echo '<span class="match-badge total-score">Total Score: ' . esc_html($scoreDisplay) . '</span>';
                            }
                            if (isset($result['matched_fields']) && is_array($result['matched_fields'])) {
                                foreach ($result['matched_fields'] as $field => $score) {
                                    if ($score > 0) {
                                        $class = str_replace('_match', '', $field);
                                        $label = ucfirst(str_replace('_match', '', $field));
                                        $scoreDisplay = (is_float($score) || is_int($score)) ? number_format($score, 2) : 'N/A';
                                        echo '<span class="match-badge ' . esc_attr($class) . '">' . 
                                             esc_html($label) . ': ' . 
                                             esc_html($scoreDisplay) . '</span>';
                                    }
                                }
                            }
                            echo '</div>';
                            
                            // Add a detailed scoring breakdown
                            $scoring_breakdown = $result['scoring_breakdown'] ?? null;
                            if ($scoring_breakdown && is_array($scoring_breakdown)) {
                                echo '<div class="scoring-breakdown">';
                                echo '<h5>Detailed Scoring Breakdown</h5>';
                                echo '<table class="scoring-table">';
                                echo '<tr><th>Scoring Factor</th><th>Points</th><th>Details</th></tr>';
                                // Sort factors by score (highest first)
                                $scoring_factors = $scoring_breakdown;
                                uasort($scoring_factors, function($a, $b) {
                                    return $b <=> $a;
                                });
                                $total_score = 0;
                                foreach ($scoring_factors as $field => $score) {
                                    $class = str_replace('_match', '', $field);
                                    $label = ucfirst(str_replace('_', ' ', str_replace('_match', '', $field)));
                                    $scoreDisplay = (is_float($score) || is_int($score)) ? number_format($score, 2) : 'N/A';
                                    echo '<tr class="score-row score-' . esc_attr($class) . '">';
                                    echo '<td>' . esc_html($label) . '</td>';
                                    echo '<td>' . esc_html($scoreDisplay) . '</td>';
                                    echo '<td></td>';
                                    echo '</tr>';
                                    $total_score += $score;
                                }
                                // Show total
                                echo '<tr class="score-row score-total">';
                                echo '<td><strong>Total Score</strong></td>';
                                echo '<td><strong>' . number_format($total_score, 2) . '</strong></td>';
                                echo '<td></td>';
                                echo '</tr>';
                                echo '</table>';
                                echo '</div>';
                            } elseif (isset($result['matched_fields']) && is_array($result['matched_fields'])) {
                                // Fallback for legacy logs: show matched_fields as before
                                echo '<div class="scoring-breakdown">';
                                echo '<h5>Detailed Scoring Breakdown</h5>';
                                echo '<table class="scoring-table">';
                                echo '<tr><th>Scoring Factor</th><th>Points</th><th>Details</th></tr>';
                                $scoring_factors = [];
                                foreach ($result['matched_fields'] as $field => $score) {
                                    if (is_array($score)) {
                                        $actual_score = 0;
                                        $details = json_encode($score);
                                        $scoring_factors[$field] = [
                                            'score' => $actual_score,
                                            'details' => $details
                                        ];
                                    } else {
                                        $scoring_factors[$field] = [
                                            'score' => $score,
                                            'details' => ''
                                        ];
                                    }
                                }
                                uasort($scoring_factors, function($a, $b) {
                                    return $b['score'] <=> $a['score'];
                                });
                                $total_score = 0;
                                foreach ($scoring_factors as $field => $data) {
                                    $score = $data['score'];
                                    $details = $data['details'];
                                    $class = str_replace('_match', '', $field);
                                    $label = ucfirst(str_replace('_', ' ', str_replace('_match', '', $field)));
                                    $scoreDisplay = (is_float($score) || is_int($score)) ? number_format($score, 2) : 'N/A';
                                    echo '<tr class="score-row score-' . esc_attr($class) . '">';
                                    echo '<td>' . esc_html($label) . '</td>';
                                    echo '<td>' . esc_html($scoreDisplay) . '</td>';
                                    echo '<td>' . esc_html($details) . '</td>';
                                    echo '</tr>';
                                    $total_score += $score;
                                }
                                echo '<tr class="score-row score-total">';
                                echo '<td><strong>Total Score</strong></td>';
                                echo '<td><strong>' . number_format($total_score, 2) . '</strong></td>';
                                echo '<td></td>';
                                echo '</tr>';
                                echo '</table>';
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="notice notice-warning">No product data available for this log.</div>';
                    }
                } catch (\Throwable $e) {
                    echo '<div class="notice notice-error">Error rendering log details: ' . esc_html($e->getMessage()) . '</div>';
                }
                ?>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $page
                            ]);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Glossary Modal Structure -->
        <div id="wcac-glossary-modal" class="wcac-modal">
            <div class="wcac-modal-content">
                <span class="wcac-modal-close">&times;</span>
                <h2><?php esc_html_e('Scoring Factor Glossary', 'wp-customer-ai-chatbot'); ?></h2>
                <div id="wcac-glossary-content">
                    <p><?php esc_html_e('Loading glossary...', 'wp-customer-ai-chatbot'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to fetch the scoring glossary definitions.
     */
    public function ajax_get_scoring_glossary(): void {
        check_ajax_referer('wcac_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized', 'wp-customer-ai-chatbot')]);
            return;
        }

        // Define the descriptions map
        $descriptions = [
            'Type weight' => __('Base score for item type. Higher values (150+) for parent products ensure they appear first in broad searches, while lower values (1-5) for variations prevent them from dominating results.', 'wp-customer-ai-chatbot'),
            'Title score' => __('Score added for each keyword match in titles. Higher values (25+) prioritize exact title matches, making products with matching titles rank higher.', 'wp-customer-ai-chatbot'),
            'Content score' => __('Score added per keyword found in the item\'s content or description. Helps surface products where keywords appear in detailed descriptions rather than just titles.', 'wp-customer-ai-chatbot'),
            'Category score' => __('Score added per keyword found in the item\'s assigned categories. Helps match products when users search by category terms or general product types.', 'wp-customer-ai-chatbot'),
            'Tag score' => __('Score added per keyword found in the item\'s assigned tags. Useful for matching alternative terms or attributes tagged to products.', 'wp-customer-ai-chatbot'),
            'Direct title boost' => __('Large bonus added if the user\'s full query exactly matches the item title. Ensures exact matches rank at the top of results.', 'wp-customer-ai-chatbot'),
            'Multi field boost' => __('Bonus score when keywords match across multiple fields (e.g., title AND content). Rewards items that are more comprehensively relevant to the query.', 'wp-customer-ai-chatbot'),
            'Title count' => __('Number of query keywords found in the title. More matching keywords generally indicate higher relevance.', 'wp-customer-ai-chatbot'),
            'All keywords in title boost' => __('Bonus score when ALL extracted keywords from the user query are found within the item title. Indicates a highly relevant match.', 'wp-customer-ai-chatbot'),
            'Title category boost' => __('Bonus score when keywords match in both the title AND a category. Helps identify products that are well-categorized and match the search intent.', 'wp-customer-ai-chatbot'),
            'Exact product name boost' => __('Significant bonus when the item title exactly matches (or starts with) a known compound product name (e.g., \'Peer Gynt\', \'Tynn Silk Mohair\'). Helps prioritize specific product results.', 'wp-customer-ai-chatbot'),
            'Query product boost' => __('Bonus when the user\'s query contains a compound product name that is also found in the title. Ensures specific product searches return the right items.', 'wp-customer-ai-chatbot'),
            'Parent preference margin' => __('How much to boost parent products above their variations in broad searches. Higher values (25+) ensure parents rank above variations unless the query specifically matches variation attributes.', 'wp-customer-ai-chatbot')
        ];

        wp_send_json_success($descriptions);
    }
} // <-- Ensure this is the final closing brace for the class