<?php

declare(strict_types=1);

/**
 * Handles rendering of settings fields for WP Customer AI Chatbot.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/admin
 */
class Wcac_Admin_Settings_Renderer
{
    private string $option_key;
    private array $options;

    /**
     * Constructor.
     *
     * @param string $option_key The key for plugin options.
     */
    public function __construct(string $option_key)
    {
        $this->option_key = $option_key;
        $options = get_option($this->option_key);
        // TEMPORARY DEBUG LINE:
        if (WP_DEBUG) {
            error_log('[WCAC RENDERER CONSTRUCT] Raw $options fetched: ' . print_r($options, true));
        }
        $this->options = is_array($options) ? $options : []; // Ensure it's always an array
        if (WP_DEBUG) {
            error_log('[WCAC RENDERER CONSTRUCT] Final $this->options: ' . print_r($this->options, true));
        }
    }

    /**
     * Render the API Key input field.
     * @param array $args Field arguments.
     */
    public function render_api_key_field(array $args): void
    {
        $api_key = $this->options['wcac_api_key'] ?? '';
        echo '<input type="password" id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($this->option_key . '[wcac_api_key]') . '" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('Enter your API key from OpenRouter.ai.', 'wp-customer-ai-chatbot') . '</p>';
    }

    /**
     * Render the checkboxes for content types to index.
     */
    public function render_index_content_types_field(): void
    {
        $index_products = $this->options['wcac_index_products'] ?? true;
        $index_pages = $this->options['wcac_index_pages'] ?? false;
        $index_posts = $this->options['wcac_index_posts'] ?? false;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCAC RENDERER] render_index_content_types_field: wcac_index_products=' . var_export($index_products, true) . ', wcac_index_pages=' . var_export($index_pages, true) . ', wcac_index_posts=' . var_export($index_posts, true));
        }
        ?>
        <fieldset>
            <legend class="screen-reader-text"><?php esc_html_e('Content Types to Index', 'wp-customer-ai-chatbot'); ?></legend>
            
            <label for="wcac_index_products">
                <input type="hidden" name="wcac_settings[wcac_index_products]" value="0" />
                <input type="checkbox" id="wcac_index_products" name="wcac_settings[wcac_index_products]" value="1" <?php checked((string)$index_products, '1'); ?> />
                <?php esc_html_e('Index Products', 'wp-customer-ai-chatbot'); ?>
            </label><br />
            
            <label for="wcac_index_pages">
                <input type="hidden" name="wcac_settings[wcac_index_pages]" value="0" />
                <input type="checkbox" id="wcac_index_pages" name="wcac_settings[wcac_index_pages]" value="1" <?php checked((string)$index_pages, '1'); ?> />
                <?php esc_html_e('Index Pages', 'wp-customer-ai-chatbot'); ?>
            </label><br />
            
            <label for="wcac_index_posts">
                <input type="hidden" name="wcac_settings[wcac_index_posts]" value="0" />
                <input type="checkbox" id="wcac_index_posts" name="wcac_settings[wcac_index_posts]" value="1" <?php checked((string)$index_posts, '1'); ?> />
                <?php esc_html_e('Index Posts', 'wp-customer-ai-chatbot'); ?>
            </label>
        </fieldset>
        <?php
    }

    /**
     * Render the manual re-index button and status area.
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
     * Render the Negative Keywords textarea field.
     * @param array $args Field arguments.
     */
    public function render_negative_keywords_field(array $args): void
    {
        $negative_keywords = $this->options['wcac_negative_keywords'] ?? '';
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
     * @param array $args Field arguments.
     */
    public function render_system_prompt_field(array $args): void
    {
        $system_prompt = $this->options['wcac_system_prompt'] ?? ''; 

        echo '<textarea id="wcac_system_prompt" name="' . esc_attr($this->option_key . '[wcac_system_prompt]') . '" rows="15" class="large-text code">' . esc_textarea($system_prompt) . '</textarea>';
        echo '<p class="description">' . esc_html__('The main instruction prompt for the AI. Controls its personality, core rules, and task. Use {{store_name}} as a placeholder for the site name.', 'wp-customer-ai-chatbot') . '</p>';
        echo '<p><button type="button" id="wcac-restore-default-prompt" class="button button-secondary">' . esc_html__('Restore Default Prompt', 'wp-customer-ai-chatbot') . '</button></p>';
    }

    /**
     * Render the Site-Specific Prompt textarea field.
     * @param array $args Field arguments.
     */
    public function render_site_prompt_field(array $args): void
    {
        $site_prompt = $this->options['wcac_site_prompt'] ?? '';
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
     * @param array $args Field arguments.
     */
    public function render_custom_css_field(array $args): void
    {
        $custom_css = $this->options['wcac_custom_css'] ?? '';
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
     * @param array $args Field arguments.
     */
    public function render_synonym_map_field(array $args): void
    {
        $value = $this->options['wcac_synonym_map'] ?? '';
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
				<pre style="background:#f4f4f4; padding:6px; border-radius:3px;">color,colour\ncatalog,collection\nfiber,fibre\nyarn,wool\npattern,design\nstore,shop\nsize,dimension\nsale,discount,offer\n</pre>
				<b>Tip:</b> You can add as many groups as needed. Avoid domain-specific jargon unless necessary.
			</div>
		</div>';
        echo '<p class="description">Enter comma-separated synonyms, one group per line. All terms in a group are considered equivalent. E.g.: catalog,collection</p>';
        // Inline JS for restore button (ensure wcacAdmin.default_synonym_map is available or use a hardcoded string)
        echo '<script>
		document.addEventListener("DOMContentLoaded", function() {
			var btn = document.getElementById("wcac-restore-default-synonyms");
			if (btn) {
				btn.addEventListener("click", function() {
					var field = document.getElementById("wcac_synonym_map_field");
					if (field) {
                        var defaultSynonyms = "color,colour\ncatalog,collection\nfiber,fibre\nyarn,wool\npattern,design\nstore,shop\nsize,dimension\nsale,discount,offer\n";
                        if (typeof wcacAdmin !== "undefined" && typeof wcacAdmin.default_synonym_map !== "undefined" && wcacAdmin.default_synonym_map) {
                           defaultSynonyms = wcacAdmin.default_synonym_map.replace(/\\n/g, "\n");
                        }
						field.value = defaultSynonyms;
					}
				});
			}
		});
		</script>';
    }

    /**
     * Render the Boost Terms (JSON) field.
     * @param array $args
     */
    public function render_boost_terms_field(array $args): void 
    {
        $value = $this->options['wcac_boost_terms'] ?? '{}';
        ?>
        <textarea id="<?php echo esc_attr($args['label_for']); ?>" 
                  name="<?php echo esc_attr($this->option_key . '[wcac_boost_terms]'); ?>\" 
                  rows=\"5\" 
                  class=\"large-text code\"><?php echo esc_textarea($value); ?></textarea>
        <p class=\"description\">
            <?php esc_html_e('Define terms that should receive a scoring boost. Enter as a JSON object where keys are terms and values are boost scores (e.g., {\"new arrival\": 20, \"exclusive\": 25}). Boost values from Scoring Rules tab are separate.', 'wp-customer-ai-chatbot'); ?>
        </p>
        <?php
    }

    /**
     * Render the Devalue Terms textarea field.
     * @param array $args Field arguments.
     */
    public function render_devalue_terms_field(array $args): void 
    {
        $value = $this->options['wcac_devalue_terms'] ?? '{}';
        ?>
        <textarea id="<?php echo esc_attr($args['label_for']); ?>" 
                  name="<?php echo esc_attr($this->option_key . '[wcac_devalue_terms]'); ?>\" 
                  rows=\"5\" 
                  class=\"large-text code\"><?php echo esc_textarea($value); ?></textarea>
        <p class=\"description\">
            <?php esc_html_e('Define terms that should receive a scoring penalty. Enter as a JSON object (e.g., {\"old model\": -15, \"clearance\": -10}). Devalue values from Scoring Rules tab are separate.', 'wp-customer-ai-chatbot'); ?>
        </p>
        <?php
    }
    
    /**
     * Centralized helper to render an LLM parameter field.
     */
    private function render_llm_param_field(string $id, string $label, $value, string $type, array $attrs, string $description): void
    {
        $input_attrs = '';
        foreach ($attrs as $attr => $attr_val) {
            $input_attrs .= esc_attr((string)$attr) . '="' . esc_attr((string)$attr_val) . '" ';
        }
        ?>
        <label for="<?php echo esc_attr($id); ?>"><strong><?php echo esc_html($label); ?></strong></label><br>
        <input type="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($this->option_key . "[$id]"); ?>" value="<?php echo esc_attr((string)$value); ?>" <?php echo $input_attrs; ?>oninput="this.nextElementSibling.value = this.value">
        <output><?php echo esc_attr((string)$value); ?></output>
        <p class="description"> <?php echo esc_html($description); ?> </p>
        <?php
    }

    public function render_temperature_field(array $args): void
    {
        $key = 'wcac_temperature';
        // Structural default for this LLM parameter
        $default_temp = 0.7; 
        // The Wcac_Utils check was non-functional and has been removed.
        $current_value = $this->options[$key] ?? $default_temp;
        
        $this->render_llm_param_field(
            $key,
            esc_html__('Temperature', 'wp-customer-ai-chatbot'),
            $current_value,
            'range', 
            ['step' => '0.01', 'min' => '0', 'max' => '2', 'default' => $default_temp],
            esc_html__('Controls randomness. Lower is more deterministic. Default: %s', 'wp-customer-ai-chatbot')
        );
    }

    public function render_top_p_field(array $args): void
    {
        $key = 'wcac_top_p';
        // Structural default
        $default_top_p = 1.0; 
        // The Wcac_Utils check was non-functional and has been removed.
        $current_value = $this->options[$key] ?? $default_top_p;
        
        $this->render_llm_param_field(
            $key,
            esc_html__('Top P', 'wp-customer-ai-chatbot'),
            $current_value,
            'range',
            ['min' => '0', 'max' => '1', 'step' => '0.01', 'default' => $default_top_p],
            esc_html__('Alternative to temperature. Considers tokens with top P probability mass. Default: %s', 'wp-customer-ai-chatbot')
        );
    }

    public function render_max_tokens_field(array $args): void
    {
        $key = 'wcac_max_tokens';
        // Structural default
        $default_max_tokens = 1024; 
        // The Wcac_Utils check was non-functional and has been removed.
        $current_value = $this->options[$key] ?? $default_max_tokens;
        
        $this->render_llm_param_field(
            $key,
            esc_html__('Max Tokens', 'wp-customer-ai-chatbot'),
            $current_value,
            'range', 
            ['min' => '128', 'max' => '4096', 'step' => '64', 'default' => $default_max_tokens], 
            esc_html__('Maximum number of tokens to generate in the response. Default: %s', 'wp-customer-ai-chatbot')
        );
    }

    public function render_frequency_penalty_field(array $args): void
    {
        $key = 'wcac_frequency_penalty';
        // Structural default
        $default_freq_penalty = 0.0;
        // The Wcac_Utils check was non-functional and has been removed.
        $current_value = $this->options[$key] ?? $default_freq_penalty;

        $this->render_llm_param_field(
            $key,
            esc_html__('Frequency Penalty', 'wp-customer-ai-chatbot'),
            $current_value,
            'range',
            ['min' => '-2', 'max' => '2', 'step' => '0.01', 'default' => $default_freq_penalty],
            esc_html__('Penalizes new tokens based on their existing frequency. Default: %s', 'wp-customer-ai-chatbot')
        );
    }

    public function render_presence_penalty_field(array $args): void
    {
        $key = 'wcac_presence_penalty';
        // Structural default
        $default_pres_penalty = 0.0;
        // The Wcac_Utils check was non-functional and has been removed.
        $current_value = $this->options[$key] ?? $default_pres_penalty;
        
        $this->render_llm_param_field(
            $key,
            esc_html__('Presence Penalty', 'wp-customer-ai-chatbot'),
            $current_value,
            'number', 
            ['step' => '0.01', 'min' => '-2.0', 'max' => '2.0', 'default' => $default_pres_penalty],
            esc_html__('Penalizes new tokens based on whether they appear in the text so far. Default: %s', 'wp-customer-ai-chatbot')
        );
    }

    /**
     * Render the LLM Model input field.
     * @param array $args Field arguments.
     */
    public function render_model_field(array $args): void
    {
        // Structural default for model can be an empty string or a specific common model.
        // For now, let's use a common one as a placeholder if not set.
        $default_model = 'nousresearch/nous-hermes-2-mixtral-8x7b-dpo'; // Example common model
        // The Wcac_Utils check was non-functional and has been removed.
        $current_model = $this->options['wcac_model'] ?? $default_model;
        
        $label_for_id = $args['label_for'] ?? 'wcac_model_field'; 
        ?>
        <input type="text" id="<?php echo esc_attr($label_for_id); ?>" name="<?php echo esc_attr($this->option_key . '[wcac_model]'); ?>" value="<?php echo esc_attr($current_model); ?>" class="regular-text">
        <p class="description">
            <?php esc_html_e('The model parameter specifies the LLM model to use.', 'wp-customer-ai-chatbot'); ?>
        </p>
        <button type="button" id="wcac-test-llm-connection" class="button button-secondary" style="margin-top:8px;">
            <?php esc_html_e('Test Connection', 'wp-customer-ai-chatbot'); ?>
        </button>
        <div id="wcac-llm-test-toast" style="display:none;position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;background:#23282d;color:#fff;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.15);"></div>
        <?php
        // JS for test connection button is usually enqueued via Wcac_Admin_Settings::enqueue_admin_assets
        // and localized with ajaxurl and nonces.
        // If this class needs to be fully self-contained for rendering, the script would need to be here
        // or ensured to be loaded on pages where these fields are rendered.
        // For now, assume the main admin class handles JS enqueueing.
    }

    /**
     * Render the Enable Debug Logging field.
     * @param array $args Field arguments.
     */
    public function render_enable_debug_logging_field(array $args): void
    {
        $enable_debug_logging = $this->options['wcac_enable_debug_logging'] ?? false;
        $field_id = $args['label_for'] ?? 'wcac_enable_debug_logging_field';
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WCAC RENDERER] render_enable_debug_logging_field: wcac_enable_debug_logging=' . var_export($enable_debug_logging, true));
        }
        ?>
        <input type="hidden" name="<?php echo esc_attr($this->option_key); ?>[wcac_enable_debug_logging]" value="0" />
        <input type="checkbox" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($this->option_key); ?>[wcac_enable_debug_logging]" value="1" <?php checked((string)$enable_debug_logging, '1'); ?>>
        <label for="<?php echo esc_attr($field_id); ?>">
            <?php esc_html_e('Enable debug logging for plugin actions and errors. Recommended only for troubleshooting.', 'wp-customer-ai-chatbot'); ?>
        </label>
        <?php
    }

    /**
     * Render the context compression algorithm dropdown.
     * @param array $args
     */
    public function render_context_compression_algorithm_field(array $args = []): void
    {
        $current_value = $this->options['wcac_context_compression_algorithm'] ?? 'none';
        $field_id = $args['label_for'] ?? 'wcac_context_compression_algorithm_field';

        $algorithms = [
            'none' => esc_html__('None', 'wp-customer-ai-chatbot'),
            'title' => esc_html__('By Title', 'wp-customer-ai-chatbot'),
            'category' => esc_html__('By Category', 'wp-customer-ai-chatbot'),
            'fuzzy' => esc_html__('Fuzzy', 'wp-customer-ai-chatbot'),
        ];
        ?>
        <select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($this->option_key); ?>[wcac_context_compression_algorithm]">
            <?php foreach ($algorithms as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_value, $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Choose how to deduplicate and cluster similar results for LLM context. "None" disables compression.', 'wp-customer-ai-chatbot'); ?></p>
        <?php
    }
    
    /**
     * Generic renderer for scoring fields.
     * @param array $args Field arguments, expects 'field_data' with key, label, desc, default, etc.
     */
    public function render_generic_scoring_field(array $args): void
    {
        // Arguments are passed nested under 'field_data' key from the registrar
        $field_data = $args['field_data'] ?? null;

        if (empty($field_data) || !isset($field_data['key'])) {
            echo '<p>Error: Field data missing or key not set in field_data for generic scoring field. Args received: ' . esc_html(print_r($args, true)) . '</p>';
            return;
        }

        $key = $field_data['key'];
        
        // Default value comes directly from registrar (passed in $field_data['default'])
        $default_value = $field_data['default'] ?? 0;
        
        // Current value from saved options, falling back to the determined default
        $current_value = $this->options[$key] ?? $default_value;

        $label = $field_data['label'] ?? 'Scoring Field';
        // The registrar passes 'desc' for description, not 'description'
        $desc = $field_data['desc'] ?? ''; 

        // Display the actual default that will be used if the option is not set or is null
        // This shows the default from registrar/utils, not necessarily what is currently in $this->options[$key] if it exists
        $description_text = $desc . ' ' . sprintf(esc_html__('Default: %s', 'wp-customer-ai-chatbot'), $default_value);

        $step = $field_data['step'] ?? 0.1;
        $min = $field_data['min'] ?? 0;
        $max = $field_data['max'] ?? 100;

        // Specific overrides for known keys - these should ideally come from registrar via $field_data too
        // If $field_data correctly supplies min/max/step, these might not be needed here.
        if ($key === 'wcac_relative_score_threshold') {
            // These are standard, but ensure $field_data provides them or they are set here.
            $min = $field_data['min'] ?? 0;
            $max = $field_data['max'] ?? 1;
            $step = $field_data['step'] ?? 0.01;
        }

        echo '<input type="number" step="'.esc_attr((string)$step).'" min="'.esc_attr((string)$min).'" max="'.esc_attr((string)$max).'" id="'.esc_attr($key).'" name="'.esc_attr($this->option_key . '[' . $key . ']').'" value="'.esc_attr((string)$current_value).'" class="regular-text">';
        echo '<p class="description">' . esc_html($description_text) . '</p>';
    }
} 