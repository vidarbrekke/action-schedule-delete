<?php
declare(strict_types=1);

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

		// TODO: Add fields for System Prompt, Site Prompt, CSS here later (MVP 6)
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
     * Render the Re-index button field.
     *
     * @since 0.1.0
     */
	public function render_reindex_button_field(): void {
		?>
		<button type="button" id="wcac-reindex-button" class="button"> 
			<?php esc_html_e( 'Re-index Selected Content', 'wp-customer-ai-chatbot' ); // Updated button text ?>
		</button>
		<span id="wcac-reindex-status" style="margin-left: 10px;"></span>
		<p class="description">
			<?php esc_html_e( 'Click this button to manually rebuild the index for the selected content types. This may take time for large sites.', 'wp-customer-ai-chatbot' ); // Updated description ?>
		</p>
		<?php
		// Add nonce field for AJAX
		wp_nonce_field( 'wcac_reindex_nonce', 'wcac_reindex_nonce_field' );
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