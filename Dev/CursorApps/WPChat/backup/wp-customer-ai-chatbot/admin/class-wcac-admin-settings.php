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
	 * @since    0.1.0
	 * @param string $plugin_basename The basename of the plugin file.
	 */
	public function __construct( string $plugin_basename ) {
		$this->plugin_basename = $plugin_basename;
        add_action( 'admin_menu', [ $this, 'add_plugin_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'plugin_action_links_' . $this->plugin_basename, [ $this, 'add_action_links' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        // Apply rules from settings at admin load
        $options = get_option('wcac_settings', []);
        Wcac_ChatbotRules::apply_admin_settings($options);
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
		$this->plugin_screen_hook_suffix = add_menu_page(
			esc_html__( 'Customer AI Chatbot Settings', 'wp-customer-ai-chatbot' ), // Page title
			esc_html__( 'AI Chatbot', 'wp-customer-ai-chatbot' ), // Menu title
			'manage_options', // Capability required
			'wcac-settings', // Menu slug
			[ $this, 'display_plugin_setup_page' ], // Callback function
			'dashicons-format-chat', // Icon URL (Dashicon class)
			null // Position (default)
		);
	}

	/**
	 * Register the settings for this plugin.
	 *
	 * @since    0.1.0
	 */
	public function register_settings(): void {
        register_setting(
            $this->option_group, // Option group
            $this->option_key, // Option name
            [ $this, 'sanitize_settings' ] // Sanitize callback
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

		// Customization & Filtering Section (New)
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
            esc_html__( 'LLM API Parameters', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_llm_params_section_header' ],
            'wcac-settings-page'
        );
        add_settings_field(
            'wcac_temperature',
            esc_html__( 'Temperature', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_temperature_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_temperature_field' ]
        );
        add_settings_field(
            'wcac_top_p',
            esc_html__( 'Top P (Nucleus Sampling)', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_top_p_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_top_p_field' ]
        );
        add_settings_field(
            'wcac_max_tokens',
            esc_html__( 'Max Tokens', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_max_tokens_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_max_tokens_field' ]
        );
        add_settings_field(
            'wcac_frequency_penalty',
            esc_html__( 'Frequency Penalty', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_frequency_penalty_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_frequency_penalty_field' ]
        );
        add_settings_field(
            'wcac_presence_penalty',
            esc_html__( 'Presence Penalty', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_presence_penalty_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_presence_penalty_field' ]
        );
        add_settings_field(
            'wcac_model',
            esc_html__( 'LLM Model', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_model_field' ],
            'wcac-settings-page',
            'wcac_llm_params_section',
            [ 'label_for' => 'wcac_model_field' ]
        );

        // Rules Section
        add_settings_section(
            'wcac_rules_section',
            esc_html__('Chatbot Scoring & Rules', 'wp-customer-ai-chatbot'),
            [ $this, 'render_rules_section_header' ],
            'wcac-settings-page'
        );
        add_settings_field(
            'wcac_parent_product_weight',
            esc_html__('Parent Product Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_parent_product_weight_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_parent_product_weight_field' ]
        );
        add_settings_field(
            'wcac_variation_product_weight',
            esc_html__('Variation Product Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_variation_product_weight_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_variation_product_weight_field' ]
        );
        add_settings_field(
            'wcac_title_match_weight',
            esc_html__('Title Match Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_title_match_weight_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_title_match_weight_field' ]
        );
        add_settings_field(
            'wcac_content_match_weight',
            esc_html__('Content Match Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_content_match_weight_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_content_match_weight_field' ]
        );
        add_settings_field(
            'wcac_category_match_weight',
            esc_html__('Category Match Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_category_match_weight_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_category_match_weight_field' ]
        );
        add_settings_field(
            'wcac_tag_match_weight',
            esc_html__('Tag Match Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_tag_match_weight_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_tag_match_weight_field' ]
        );
        add_settings_field(
            'wcac_on_sale_weight',
            esc_html__('On Sale Weight', 'wp-customer-ai-chatbot'),
            [ $this, 'render_on_sale_weight_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_on_sale_weight_field' ]
        );
        add_settings_field(
            'wcac_direct_title_match_bonus',
            esc_html__('Direct Title Match Bonus', 'wp-customer-ai-chatbot'),
            [ $this, 'render_direct_title_match_bonus_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_direct_title_match_bonus_field' ]
        );
        add_settings_field(
            'wcac_min_score_threshold',
            esc_html__('Minimum Score Threshold', 'wp-customer-ai-chatbot'),
            [ $this, 'render_min_score_threshold_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_min_score_threshold_field' ]
        );
        add_settings_field(
            'wcac_max_results_returned',
            esc_html__('Max Results Returned', 'wp-customer-ai-chatbot'),
            [ $this, 'render_max_results_returned_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_max_results_returned_field' ]
        );
        add_settings_field(
            'wcac_negative_keyword_penalty',
            esc_html__( 'Negative Keyword Penalty', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_negative_keyword_penalty_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_negative_keyword_penalty_field' ]
        );
        add_settings_field(
            'wcac_multi_field_match_bonus',
            esc_html__( 'Multi-Field Match Bonus', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_multi_field_match_bonus_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_multi_field_match_bonus_field' ]
        );
        add_settings_field(
            'wcac_all_keywords_in_title_boost',
            esc_html__( 'All Keywords in Title Boost', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_all_keywords_in_title_boost_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_all_keywords_in_title_boost_field' ]
        );
        add_settings_field(
            'wcac_fuzzy_match_boost_high',
            esc_html__( 'Fuzzy Match Boost (High >60%)', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_fuzzy_match_boost_high_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_fuzzy_match_boost_high_field' ]
        );
        add_settings_field(
            'wcac_fuzzy_match_boost_medium',
            esc_html__( 'Fuzzy Match Boost (Medium >40%)', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_fuzzy_match_boost_medium_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_fuzzy_match_boost_medium_field' ]
        );
        add_settings_field(
            'wcac_fuzzy_match_boost_low',
            esc_html__( 'Fuzzy Match Boost (Low >30%)', 'wp-customer-ai-chatbot' ),
            [ $this, 'render_fuzzy_match_boost_low_field' ],
            'wcac-settings-page',
            'wcac_rules_section',
            [ 'label_for' => 'wcac_fuzzy_match_boost_low_field' ]
        );
	}

    /**
     * Render the header for the main settings section.
     *
     * @since 0.1.0
     */
    public function render_main_section_header(): void {
        echo '<p>' . esc_html__( 'Configure the core settings for the AI Chatbot.', 'wp-customer-ai-chatbot' ) . '</p>';
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
		$meta = get_option( Wcac_Indexer::META_KEY, [] );
		$last_updated = $meta['last_updated'] ?? 'Never';
		// Get counts from the nested array
		$counts = $meta['counts'] ?? ['product' => 0, 'page' => 0, 'post' => 0, 'total' => 0];
		$counts_string = sprintf(
			'Total: %d (Products: %d, Pages: %d, Posts: %d)',
			absint($counts['total'] ?? 0),
			absint($counts['product'] ?? 0),
			absint($counts['page'] ?? 0),
			absint($counts['post'] ?? 0)
		);
        echo '<p>' . esc_html__( 'Manage the content data index used by the chatbot.', 'wp-customer-ai-chatbot' ) . '</p>';
		echo '<p>' . sprintf( esc_html__( 'Index Status: %s. Last updated: %s', 'wp-customer-ai-chatbot' ), esc_html( $counts_string ), esc_html( $last_updated ) ) . '</p>'; // Updated label

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
	 * @since 0.1.1
	 */
	public function render_customization_section_header(): void {
		echo '<p>' . esc_html__( 'Customize chatbot behavior and filter indexed content.', 'wp-customer-ai-chatbot' ) . '</p>';
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
	 * @since 0.1.1
	 */
	public function render_llm_params_section_header(): void {
		echo '<p>' . esc_html__( 'Configure the LLM API parameters for the chatbot.', 'wp-customer-ai-chatbot' ) . '</p>';
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
	 * @since 0.1.1
	 */
	public function render_rules_section_header(): void {
		echo '<p>' . esc_html__( 'Configure the chatbot scoring and rules.', 'wp-customer-ai-chatbot' ) . '</p>';
	}

	/**
	 * Render the Parent Product Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_parent_product_weight_field( array $args ): void {
		$options = get_option( $this->option_key );
		$parent_product_weight = $options['wcac_parent_product_weight'] ?? '1';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_parent_product_weight]' ); ?>" value="<?php echo esc_attr( $parent_product_weight ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Weight assigned to parent products. (Min: 0, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Variation Product Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_variation_product_weight_field( array $args ): void {
		$options = get_option( $this->option_key );
		$variation_product_weight = $options['wcac_variation_product_weight'] ?? '1';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_variation_product_weight]' ); ?>" value="<?php echo esc_attr( $variation_product_weight ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Weight assigned to variation products. (Min: 0, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Title Match Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_title_match_weight_field( array $args ): void {
		$options = get_option( $this->option_key );
		$title_match_weight = $options['wcac_title_match_weight'] ?? '1';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_title_match_weight]' ); ?>" value="<?php echo esc_attr( $title_match_weight ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Weight assigned to title match. (Min: 0, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Content Match Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_content_match_weight_field( array $args ): void {
		$options = get_option( $this->option_key );
		$content_match_weight = $options['wcac_content_match_weight'] ?? '1';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_content_match_weight]' ); ?>" value="<?php echo esc_attr( $content_match_weight ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Weight assigned to content match. (Min: 0, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Category Match Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_category_match_weight_field( array $args ): void {
		$options = get_option( $this->option_key );
		$category_match_weight = $options['wcac_category_match_weight'] ?? '1';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_category_match_weight]' ); ?>" value="<?php echo esc_attr( $category_match_weight ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Weight assigned to category match. (Min: 0, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Tag Match Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_tag_match_weight_field( array $args ): void {
		$options = get_option( $this->option_key );
		$tag_match_weight = $options['wcac_tag_match_weight'] ?? '1';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_tag_match_weight]' ); ?>" value="<?php echo esc_attr( $tag_match_weight ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Weight assigned to tag match. (Min: 0, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the On Sale Weight input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_on_sale_weight_field( array $args ): void {
		$options = get_option( $this->option_key );
		$on_sale_weight = $options['wcac_on_sale_weight'] ?? '1';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_on_sale_weight]' ); ?>" value="<?php echo esc_attr( $on_sale_weight ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Weight assigned to on sale products. (Min: 0, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Direct Title Match Bonus input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_direct_title_match_bonus_field( array $args ): void {
		$options = get_option( $this->option_key );
		$direct_title_match_bonus = $options['wcac_direct_title_match_bonus'] ?? '1';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_direct_title_match_bonus]' ); ?>" value="<?php echo esc_attr( $direct_title_match_bonus ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Bonus assigned to direct title match. (Min: 0, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Minimum Score Threshold input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_min_score_threshold_field( array $args ): void {
		$options = get_option( $this->option_key );
		$min_score_threshold = $options['wcac_min_score_threshold'] ?? '0';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_min_score_threshold]' ); ?>" value="<?php echo esc_attr( $min_score_threshold ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Minimum score threshold for a match to be considered valid. (Min: 0, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Maximum Results Returned input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_max_results_returned_field( array $args ): void {
		$options = get_option( $this->option_key );
		$max_results_returned = $options['wcac_max_results_returned'] ?? '10';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_max_results_returned]' ); ?>" value="<?php echo esc_attr( $max_results_returned ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Maximum number of results to return. (Min: 1, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Negative Keyword Penalty input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_negative_keyword_penalty_field( array $args ): void {
		$options = get_option( $this->option_key );
		$negative_keyword_penalty = $options['wcac_negative_keyword_penalty'] ?? '0';
		?>
		<input type="text" id="<?php echo esc_attr( $args['label_for'] ); ?>" name="<?php echo esc_attr( $this->option_key . '[wcac_negative_keyword_penalty]' ); ?>" value="<?php echo esc_attr( $negative_keyword_penalty ); ?>" class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Penalty assigned to negative keyword matches. (Min: 0, Max: 100)', 'wp-customer-ai-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Multi-Field Match Bonus input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_multi_field_match_bonus_field( array $args ): void {
		$options = get_option( $this->option_key );
		$value = $options['wcac_multi_field_match_bonus'] ?? Wcac_ChatbotRules::get_multi_field_match_bonus();
		printf(
			'<input type="number" step="0.1" min="0" name="%1$s[wcac_multi_field_match_bonus]" id="%2$s" value="%3$s" class="small-text"> <p class="description">%4$s</p>',
			esc_attr( $this->option_key ),
			esc_attr( $args['label_for'] ),
			esc_attr( (string)$value ),
			esc_html__( 'Bonus score added when keywords match across multiple fields (title, content, category, tag).', 'wp-customer-ai-chatbot' )
		);
	}

	/**
	 * Render the All Keywords in Title Boost input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_all_keywords_in_title_boost_field( array $args ): void {
		$options = get_option( $this->option_key );
		$value = $options['wcac_all_keywords_in_title_boost'] ?? Wcac_ChatbotRules::get_all_keywords_in_title_boost();
		printf(
			'<input type="number" step="0.1" min="0" name="%1$s[wcac_all_keywords_in_title_boost]" id="%2$s" value="%3$s" class="small-text"> <p class="description">%4$s</p>',
			esc_attr( $this->option_key ),
			esc_attr( $args['label_for'] ),
			esc_attr( (string)$value ),
			esc_html__( 'Bonus score added when all extracted keywords are found within the product title.', 'wp-customer-ai-chatbot' )
		);
	}

	/**
	 * Render the Fuzzy Match Boost (High >60%) input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_fuzzy_match_boost_high_field( array $args ): void {
		$options = get_option( $this->option_key );
		$value = $options['wcac_fuzzy_match_boost_high'] ?? Wcac_ChatbotRules::get_fuzzy_match_boost_high();
		printf(
			'<input type="number" step="0.1" min="0" name="%1$s[wcac_fuzzy_match_boost_high]" id="%2$s" value="%3$s" class="small-text"> <p class="description">%4$s</p>',
			esc_attr( $this->option_key ),
			esc_attr( $args['label_for'] ),
			esc_attr( (string)$value ),
			esc_html__( 'Bonus score added for high fuzzy title match similarity (>60%).', 'wp-customer-ai-chatbot' )
		);
	}

	/**
	 * Render the Fuzzy Match Boost (Medium >40%) input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_fuzzy_match_boost_medium_field( array $args ): void {
		$options = get_option( $this->option_key );
		$value = $options['wcac_fuzzy_match_boost_medium'] ?? Wcac_ChatbotRules::get_fuzzy_match_boost_medium();
		printf(
			'<input type="number" step="0.1" min="0" name="%1$s[wcac_fuzzy_match_boost_medium]" id="%2$s" value="%3$s" class="small-text"> <p class="description">%4$s</p>',
			esc_attr( $this->option_key ),
			esc_attr( $args['label_for'] ),
			esc_attr( (string)$value ),
			esc_html__( 'Bonus score added for medium fuzzy title match similarity (>40%).', 'wp-customer-ai-chatbot' )
		);
	}

	/**
	 * Render the Fuzzy Match Boost (Low >30%) input field.
	 *
	 * @since 0.1.1
	 * @param array $args Field arguments.
	 */
	public function render_fuzzy_match_boost_low_field( array $args ): void {
		$options = get_option( $this->option_key );
		$value = $options['wcac_fuzzy_match_boost_low'] ?? Wcac_ChatbotRules::get_fuzzy_match_boost_low();
		printf(
			'<input type="number" step="0.1" min="0" name="%1$s[wcac_fuzzy_match_boost_low]" id="%2$s" value="%3$s" class="small-text"> <p class="description">%4$s</p>',
			esc_attr( $this->option_key ),
			esc_attr( $args['label_for'] ),
			esc_attr( (string)$value ),
			esc_html__( 'Bonus score added for low fuzzy title match similarity (>30%).', 'wp-customer-ai-chatbot' )
		);
	}

	/**
	 * Sanitize settings array.
	 *
	 * @since 0.1.0
	 * @param array $input The settings array.
	 * @return array Sanitized settings array.
	 */
	public function sanitize_settings( array $input ): array {
		$new_input = [];
		if ( isset( $input['wcac_api_key'] ) ) {
			$new_input['wcac_api_key'] = sanitize_text_field( $input['wcac_api_key'] );
		}

        // Sanitize checkbox values (ensure they are 1 or not set)
        $new_input['wcac_index_products'] = isset( $input['wcac_index_products'] ) && $input['wcac_index_products'] == '1';
        $new_input['wcac_index_pages'] = isset( $input['wcac_index_pages'] ) && $input['wcac_index_pages'] == '1';
        $new_input['wcac_index_posts'] = isset( $input['wcac_index_posts'] ) && $input['wcac_index_posts'] == '1';

		// Sanitize Negative Keywords textarea
		if ( isset( $input['wcac_negative_keywords'] ) ) {
            // Sanitize each line individually (optional, but safer against injected HTML/JS)
            $lines = explode( "\n", $input['wcac_negative_keywords'] );
            $sanitized_lines = array_map( 'sanitize_text_field', $lines );
            $new_input['wcac_negative_keywords'] = implode( "\n", $sanitized_lines );

			// Alternatively, simpler sanitization:
            // $new_input['wcac_negative_keywords'] = sanitize_textarea_field( $input['wcac_negative_keywords'] );
        }

		// Sanitize Prompts (allow some basic HTML maybe? Or just textarea)
		if ( isset( $input['wcac_system_prompt'] ) ) {
            $new_input['wcac_system_prompt'] = sanitize_textarea_field( $input['wcac_system_prompt'] );
        }
		if ( isset( $input['wcac_site_prompt'] ) ) {
            $new_input['wcac_site_prompt'] = sanitize_textarea_field( $input['wcac_site_prompt'] );
        }

		// Sanitize Custom CSS (most important!)
        if ( isset( $input['wcac_custom_css'] ) ) {
            // Use WordPress built-in CSS sanitization
            $new_input['wcac_custom_css'] = wp_strip_all_tags( $input['wcac_custom_css'] );
			// Note: For more complex CSS allowing more properties, consider using KSES with allowed CSS properties
			// or a dedicated CSS validation library if needed, but wp_strip_all_tags is safe baseline.
        }

		// Sanitize LLM API parameters
		if ( isset( $input['wcac_temperature'] ) ) {
			// Ensure temperature is within valid range 0.0-1.0
			$temp = (float) sanitize_text_field( $input['wcac_temperature'] );
			$new_input['wcac_temperature'] = max(0.0, min(1.0, $temp));
		}
		if ( isset( $input['wcac_top_p'] ) ) {
			// Ensure top_p is within valid range 0.0-1.0
			$top_p = (float) sanitize_text_field( $input['wcac_top_p'] );
			$new_input['wcac_top_p'] = max(0.0, min(1.0, $top_p));
		}
		if ( isset( $input['wcac_max_tokens'] ) ) {
			// Ensure max_tokens is a positive integer
			$tokens = (int) sanitize_text_field( $input['wcac_max_tokens'] );
			$new_input['wcac_max_tokens'] = max(1, $tokens);
		}
		if ( isset( $input['wcac_frequency_penalty'] ) ) {
			// Ensure frequency_penalty is within valid range -2.0 to 2.0
			$freq = (float) sanitize_text_field( $input['wcac_frequency_penalty'] );
			$new_input['wcac_frequency_penalty'] = max(-2.0, min(2.0, $freq));
		}
		if ( isset( $input['wcac_presence_penalty'] ) ) {
			// Ensure presence_penalty is within valid range -2.0 to 2.0
			$pres = (float) sanitize_text_field( $input['wcac_presence_penalty'] );
			$new_input['wcac_presence_penalty'] = max(-2.0, min(2.0, $pres));
		}
		if ( isset( $input['wcac_model'] ) ) {
			$new_input['wcac_model'] = sanitize_text_field( $input['wcac_model'] );
		}

		// Sanitize Rules section
		if ( isset( $input['wcac_parent_product_weight'] ) ) {
			$new_input['wcac_parent_product_weight'] = max(0, min(100, (int) sanitize_text_field( $input['wcac_parent_product_weight'] ) ));
		}
		if ( isset( $input['wcac_variation_product_weight'] ) ) {
			$new_input['wcac_variation_product_weight'] = max(0, min(100, (int) sanitize_text_field( $input['wcac_variation_product_weight'] ) ));
		}
		if ( isset( $input['wcac_title_match_weight'] ) ) {
			$new_input['wcac_title_match_weight'] = max(0, min(100, (int) sanitize_text_field( $input['wcac_title_match_weight'] ) ));
		}
		if ( isset( $input['wcac_content_match_weight'] ) ) {
			$new_input['wcac_content_match_weight'] = max(0, min(100, (int) sanitize_text_field( $input['wcac_content_match_weight'] ) ));
		}
		if ( isset( $input['wcac_category_match_weight'] ) ) {
			$new_input['wcac_category_match_weight'] = max(0, min(100, (int) sanitize_text_field( $input['wcac_category_match_weight'] ) ));
		}
		if ( isset( $input['wcac_tag_match_weight'] ) ) {
			$new_input['wcac_tag_match_weight'] = max(0, min(100, (int) sanitize_text_field( $input['wcac_tag_match_weight'] ) ));
		}
		if ( isset( $input['wcac_on_sale_weight'] ) ) {
			$new_input['wcac_on_sale_weight'] = max(0, min(100, (int) sanitize_text_field( $input['wcac_on_sale_weight'] ) ));
		}
		if ( isset( $input['wcac_direct_title_match_bonus'] ) ) {
			$new_input['wcac_direct_title_match_bonus'] = max(0, min(100, (int) sanitize_text_field( $input['wcac_direct_title_match_bonus'] ) ));
		}
		if ( isset( $input['wcac_min_score_threshold'] ) ) {
			$new_input['wcac_min_score_threshold'] = max(0, min(100, (int) sanitize_text_field( $input['wcac_min_score_threshold'] ) ));
		}
		if ( isset( $input['wcac_max_results_returned'] ) ) {
			$new_input['wcac_max_results_returned'] = max(1, min(100, (int) sanitize_text_field( $input['wcac_max_results_returned'] ) ));
		}
		if ( isset( $input['wcac_negative_keyword_penalty'] ) ) {
			$new_input['wcac_negative_keyword_penalty'] = max(0, min(100, (int) sanitize_text_field( $input['wcac_negative_keyword_penalty'] ) ));
		}
		if ( isset( $input['wcac_multi_field_match_bonus'] ) ) {
			$new_input['wcac_multi_field_match_bonus'] = max(0, floatval( $input['wcac_multi_field_match_bonus'] ));
		}
		if ( isset( $input['wcac_all_keywords_in_title_boost'] ) ) {
			$new_input['wcac_all_keywords_in_title_boost'] = max(0, floatval( $input['wcac_all_keywords_in_title_boost'] ));
		}
		if ( isset( $input['wcac_fuzzy_match_boost_high'] ) ) {
			$new_input['wcac_fuzzy_match_boost_high'] = max(0, floatval( $input['wcac_fuzzy_match_boost_high'] ));
		}
		if ( isset( $input['wcac_fuzzy_match_boost_medium'] ) ) {
			$new_input['wcac_fuzzy_match_boost_medium'] = max(0, floatval( $input['wcac_fuzzy_match_boost_medium'] ));
		}
		if ( isset( $input['wcac_fuzzy_match_boost_low'] ) ) {
			$new_input['wcac_fuzzy_match_boost_low'] = max(0, floatval( $input['wcac_fuzzy_match_boost_low'] ));
		}

		if (isset($input['wcac_synonym_map'])) {
			$new_input['wcac_synonym_map'] = trim(str_replace(["\r\n", "\r"], "\n", $input['wcac_synonym_map']));
		}

		return $new_input;
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    0.1.0
	 */
	public function display_plugin_setup_page(): void {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				// Output security fields for the registered setting section
				settings_fields( $this->option_group );
				// Output setting sections and fields
				do_settings_sections( 'wcac-settings-page' ); // Must match page slug in add_settings_section/field
				// Output save settings button
				submit_button( esc_html__( 'Save Settings', 'wp-customer-ai-chatbot' ) );
				?>
			</form>
		</div>
		<?php
	}

    /**
     * Enqueue admin scripts and styles.
     *
     * @since 0.1.0
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_admin_assets( string $hook_suffix ): void {
        // Only load on our settings page
        if ( $this->plugin_screen_hook_suffix !== $hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'wcac-admin-script',
            WCAC_PLUGIN_URL . 'admin/js/wcac-admin.js',
            [ 'jquery' ], // Dependency
            WCAC_VERSION,
            true // In footer
        );

        // Localize script data for AJAX
		$meta = get_option( Wcac_Indexer::META_KEY, [] );
		$counts = $meta['counts'] ?? ['product' => 0, 'page' => 0, 'post' => 0, 'total' => 0];
        $script_data = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wcac_reindex_nonce' ), // Use the nonce created for the button field
			'reindex_confirm' => esc_html__( 'Are you sure you want to re-index the selected content types? This might take a while.', 'wp-customer-ai-chatbot' ), // Updated confirm text
			'reindexing_text' => esc_html__( 'Indexing...', 'wp-customer-ai-chatbot' ),
			'reindex_success_template' => esc_html__( 'Success! Indexed Content. Total: %d (Products: %d, Pages: %d, Posts: %d)', 'wp-customer-ai-chatbot' ), // Template for success message
			'reindex_error_text' => esc_html__( 'Error during re-indexing. Check server logs.', 'wp-customer-ai-chatbot' )
        ];
        wp_localize_script( 'wcac-admin-script', 'wcac_admin_data', $script_data );

        // Add inline script for the restore button functionality
        $default_prompt_template_js = <<<'EOT'
You are a helpful and knowledgeable assistant for the online store {{store_name}}. Your goal is to answer customer questions accurately based *only* on the context provided below regarding products, pages, and posts.

**Response Guidelines:**
- Speak directly as a representative of the store (use 'we', 'our' when referring to {{store_name}}).
- Be friendly, helpful, and confident in your answers based on the provided information.
- Do NOT identify yourself as an AI, bot, or language model.
- Do NOT use phrases like 'Based on the information provided', 'As an AI', 'As a language model', 'According to the context...', or similar hedging language. State facts directly as known by the store.
- Mention the most relevant item (the one with the highest score) from the context first in your response, if applicable.
- Format your answers clearly using markdown, especially for lists and links ([Link Text](URL)). Ensure lists use double newlines between items for proper paragraph spacing.
- Provide concise answers, focusing on the user's query and the relevant information found in the context.
- If the context includes product prices, mention them when relevant to the user's query.
- Answer *only* based on information explicitly provided. Do not reference general knowledge about brands or products unless that information was given to you in the context.
EOT;

        $inline_script = sprintf(
            'jQuery(document).ready(function($) {
                const defaultPrompt = %s;
                $("#wcac-restore-default-prompt").on("click", function(e) {
                    e.preventDefault();
                    if (confirm(%s)) {
                        $("#%s").val(defaultPrompt);
                    }
                });
            });',
            wp_json_encode($default_prompt_template_js), // Safely encode the multi-line string for JS
            wp_json_encode(esc_html__( 'Are you sure you want to restore the default system prompt? Any changes you made will be lost.', 'wp-customer-ai-chatbot' )),
            'wcac_system_prompt_field' // The ID of the textarea
        );

        wp_add_inline_script('wcac-admin-script', $inline_script);
    }
} 