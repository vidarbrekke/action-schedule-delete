<?php
declare(strict_types=1);

/**
 * Handles registration of settings sections and fields for WP Customer AI Chatbot.
 *
 * @package    Wcac_Customer_AI_Chatbot
 * @subpackage Wcac_Customer_AI_Chatbot/admin
 */
class Wcac_Admin_Settings_Registrar
{
    private string $option_group;
    private string $option_key;
    private ?object $field_renderer; // Expects an object with rendering methods
    private ?object $section_header_renderer; // Expects an object with section header rendering methods (could be $this from Wcac_Admin_Settings)

    // Store all scoring field definitions statically so they can be accessed by the sanitizer
    private static array $all_scoring_fields_definitions = [
        ['key' => 'wcac_parent_product_weight', 'label' => 'Parent Product Weight', 'desc' => 'Base score for parent products.', 'default' => 150, 'min' => 0, 'max' => 500, 'step' => 0.1],
        ['key' => 'wcac_variation_product_weight', 'label' => 'Variation Product Weight', 'desc' => 'Base score for product variations.', 'default' => 2, 'min' => 0, 'max' => 500, 'step' => 0.1],
        ['key' => 'wcac_title_match_weight', 'label' => 'Title Match Weight', 'desc' => 'Score for keyword match in titles.', 'default' => 15, 'min' => 0, 'max' => 100, 'step' => 0.1],
        ['key' => 'wcac_content_match_weight', 'label' => 'Content Match Weight', 'desc' => 'Score for keyword match in content.', 'default' => 15, 'min' => 0, 'max' => 100, 'step' => 0.1],
        ['key' => 'wcac_category_match_weight', 'label' => 'Category Match Weight', 'desc' => 'Score for keyword match in categories.', 'default' => 35, 'min' => 0, 'max' => 100, 'step' => 0.1],
        ['key' => 'wcac_tag_match_weight', 'label' => 'Tag Match Weight', 'desc' => 'Score for keyword match in tags.', 'default' => 15, 'min' => 0, 'max' => 100, 'step' => 0.1],
        ['key' => 'wcac_on_sale_weight', 'label' => 'On Sale Weight', 'desc' => 'Score if product is on sale.', 'default' => 10, 'min' => 0, 'max' => 50, 'step' => 0.1],
        ['key' => 'wcac_menu_match_weight', 'label' => 'Menu Match Weight', 'desc' => 'Score for keyword match in menu titles.', 'default' => 10, 'min' => 0, 'max' => 100, 'step' => 0.1],
        ['key' => 'wcac_attribute_match_weight', 'label' => 'Attribute Match Weight', 'desc' => 'Score for keyword match in attributes.', 'default' => 10, 'min' => 0, 'max' => 100, 'step' => 0.1],
        ['key' => 'wcac_parent_category_match_weight', 'label' => 'Parent Category Match Weight', 'desc' => 'Score for match in parent categories.', 'default' => 10, 'min' => 0, 'max' => 100, 'step' => 0.1],
        ['key' => 'wcac_taxonomy_match_weight', 'label' => 'Taxonomy Match Weight', 'desc' => 'Score for match in custom taxonomies.', 'default' => 10, 'min' => 0, 'max' => 100, 'step' => 0.1],
        ['key' => 'wcac_rating_weight', 'label' => 'Rating Weight', 'desc' => 'Score based on product rating.', 'default' => 20, 'min' => 0, 'max' => 100, 'step' => 0.1],
        ['key' => 'wcac_title_category_match_bonus', 'label' => 'Title-Category Match Bonus', 'desc' => 'Bonus if query terms match both title and category.', 'default' => 0, 'min' => 0, 'max' => 100, 'step' => 1],
        ['key' => 'wcac_exact_product_name_boost', 'label' => 'Exact Product Name Boost', 'desc' => 'Large boost if query is exact product name.', 'default' => 50, 'min' => 0, 'max' => 200, 'step' => 1],
        ['key' => 'wcac_multi_field_match_bonus', 'label' => 'Multi-Field Match Bonus', 'desc' => 'Bonus if query terms match in multiple fields (e.g., title and content).', 'default' => 0, 'min' => 0, 'max' => 100, 'step' => 1],
        ['key' => 'wcac_all_keywords_title_boost', 'label' => 'All Keywords in Title Boost', 'desc' => 'Bonus if all query keywords are found in the title.', 'default' => 0, 'min' => 0, 'max' => 100, 'step' => 1],
        ['key' => 'wcac_parent_preference_margin', 'label' => 'Parent Preference Margin', 'desc' => 'Score margin to prefer parent products over variations in ambiguous searches.', 'default' => 0, 'min' => 0, 'max' => 100, 'step' => 0.1],
        ['key' => 'wcac_relative_score_threshold', 'label' => 'Relative Score Threshold', 'desc' => 'Min score relative to best match (0.0-1.0) to be included in results.', 'default' => 0.7, 'min' => 0, 'max' => 1, 'step' => 0.01],
        ['key' => 'wcac_negative_keyword_penalty', 'label' => 'Negative Keyword Penalty', 'desc' => 'Penalty applied if a negative keyword is found.', 'default' => -100, 'min' => -500, 'max' => 0, 'step' => 1],
        ['key' => 'wcac_boost_term_score_value', 'label' => 'Boost Term Score Value', 'desc' => 'Score added for each boosted term match.', 'default' => 50, 'min' => 0, 'max' => 200, 'step' => 1],
        ['key' => 'wcac_devalue_term_score_value', 'label' => 'Devalue Term Score Value', 'desc' => 'Penalty applied for each devalued term match.', 'default' => -50, 'min' => -200, 'max' => 0, 'step' => 1],
        ['key' => 'wcac_max_results_returned', 'label' => 'Max Results Returned', 'desc' => 'Maximum number of search results to return to the LLM.', 'default' => 10, 'min' => 1, 'max' => 50, 'step' => 1],
        ['key' => 'wcac_out_of_stock_penalty', 'label' => 'Out-of-Stock Penalty', 'desc' => 'Penalty for out-of-stock products.', 'default' => -200, 'min' => -500, 'max' => 0, 'step' => 1],
        ['key' => 'wcac_recency_boost_multiplier', 'label' => 'Recency Boost Multiplier', 'desc' => 'Multiplier for recently published content (0 to disable).', 'default' => 0, 'min' => 0, 'max' => 10, 'step' => 0.1],
    ];

    /**
     * Constructor.
     *
     * @param string $option_group The option group name.
     * @param string $option_key The key for plugin options.
     * @param object $field_renderer An instance of the field renderer class.
     * @param object $section_header_renderer An instance of the class that renders section headers.
     */
    public function __construct(string $option_group, string $option_key, object $field_renderer, object $section_header_renderer)
    {
        $this->option_group = $option_group;
        $this->option_key = $option_key;
        $this->field_renderer = $field_renderer;
        $this->section_header_renderer = $section_header_renderer;
    }

    /**
     * Register the settings for this plugin.
     *
     * @param callable $sanitize_callback The callback function for sanitizing settings.
     */
    public function register_settings(callable $sanitize_callback): void
    {
        register_setting(
            $this->option_group, 
            $this->option_key,   
            $sanitize_callback 
        );

        // Define page slugs for each tab
        $api_page_slug = 'wcac_settings_api';
        $indexing_page_slug = 'wcac_settings_indexing';
        $customization_page_slug = 'wcac_settings_customization';
        $llm_page_slug = 'wcac_settings_llm';
        $debug_page_slug = 'wcac_settings_debug';
        $scoring_page_slug = 'wcac_settings_scoring_rules';

        // Main Settings Section (API Tab)
        add_settings_section(
            'wcac_main_settings_section',      
            esc_html__('OpenRouter API Settings', 'wp-customer-ai-chatbot'), 
            [$this->section_header_renderer, 'render_main_section_header'], 
            $api_page_slug                  
        );

        add_settings_field(
            'wcac_api_key',                                 
            esc_html__('OpenRouter API Key', 'wp-customer-ai-chatbot'), 
            [$this->field_renderer, 'render_api_key_field'],              
            $api_page_slug,                              
            'wcac_main_settings_section',                   
            ['label_for' => 'wcac_api_key']
        );

        // Indexing Settings Section (Indexing Tab)
        add_settings_section(
            'wcac_indexing_settings_section',
            esc_html__('Content Indexing Settings', 'wp-customer-ai-chatbot'),
            [$this->section_header_renderer, 'render_indexing_section_header'],
            $indexing_page_slug 
        );

        add_settings_field(
            'wcac_index_content_types',
            esc_html__('Content Types to Index', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_index_content_types_field'], 
            $indexing_page_slug, 
            'wcac_indexing_settings_section'
        );

        add_settings_field(
            'wcac_reindex_button',
            esc_html__('Manage Index', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_reindex_button_field'], 
            $indexing_page_slug, 
            'wcac_indexing_settings_section'
        );

        // Chatbot Customization Section (Customization Tab)
        add_settings_section(
            'wcac_customization_settings_section',
            esc_html__('Chatbot Behavior & Appearance', 'wp-customer-ai-chatbot'),
            [$this->section_header_renderer, 'render_customization_section_header'],
            $customization_page_slug 
        );

        add_settings_field(
            'wcac_negative_keywords',
            esc_html__('Global Negative Keywords', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_negative_keywords_field'], 
            $customization_page_slug, 
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_negative_keywords']
        );

        add_settings_field(
            'wcac_system_prompt',
            esc_html__('System Prompt', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_system_prompt_field'], 
            $customization_page_slug, 
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_system_prompt']
        );
        add_settings_field(
            'wcac_site_prompt',
            esc_html__('Site Prompt (Instructions)', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_site_prompt_field'], 
            $customization_page_slug, 
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_site_prompt']
        );

        add_settings_field(
            'wcac_custom_css',
            esc_html__('Custom CSS for Chatbot', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_custom_css_field'], 
            $customization_page_slug, 
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_custom_css']
        );

        add_settings_field(
            'wcac_synonym_map',
            esc_html__('Synonym Map (Text)', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_synonym_map_field'], 
            $customization_page_slug, 
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_synonym_map']
        );

        add_settings_field(
            'wcac_boost_terms',
            esc_html__('Boost Terms (JSON)', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_boost_terms_field'], 
            $customization_page_slug, 
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_boost_terms']
        );

        add_settings_field(
            'wcac_devalue_terms',
            esc_html__('Devalue Terms (JSON)', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_devalue_terms_field'], 
            $customization_page_slug, 
            'wcac_customization_settings_section',
            ['label_for' => 'wcac_devalue_terms']
        );


        // LLM Parameters Section (LLM Tab)
        add_settings_section(
            'wcac_llm_params_section',
            esc_html__('LLM Parameters', 'wp-customer-ai-chatbot'),
            [$this->section_header_renderer, 'render_llm_params_section_header'],
            $llm_page_slug 
        );

        add_settings_field(
            'wcac_model',
            esc_html__('Chat Model', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_model_field'], 
            $llm_page_slug, 
            'wcac_llm_params_section',
            ['label_for' => 'wcac_model_field'] 
        );

        add_settings_field(
            'wcac_temperature',
            esc_html__('Temperature', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_temperature_field'], 
            $llm_page_slug, 
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_top_p',
            esc_html__('Top P', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_top_p_field'], 
            $llm_page_slug, 
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_max_tokens',
            esc_html__('Max Tokens', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_max_tokens_field'], 
            $llm_page_slug, 
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_frequency_penalty',
            esc_html__('Frequency Penalty', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_frequency_penalty_field'], 
            $llm_page_slug, 
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_presence_penalty',
            esc_html__('Presence Penalty', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_presence_penalty_field'], 
            $llm_page_slug, 
            'wcac_llm_params_section'
        );

        add_settings_field(
            'wcac_context_compression_algorithm',
            esc_html__('Context Compression Algorithm', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_context_compression_algorithm_field'], 
            $llm_page_slug, 
            'wcac_llm_params_section',
            ['label_for' => 'wcac_context_compression_algorithm_field'] 
        );

        // Debug Logging Section (Debug Tab)
        add_settings_section(
            'wcac_debug_logging_section',
            esc_html__('Debug Logging', 'wp-customer-ai-chatbot'),
            null, // No header callback needed for this simple section
            $debug_page_slug 
        );

        add_settings_field(
            'wcac_enable_debug_logging',
            esc_html__('Enable Debug Logging', 'wp-customer-ai-chatbot'),
            [$this->field_renderer, 'render_enable_debug_logging_field'], 
            $debug_page_slug, 
            'wcac_debug_logging_section',
            ['label_for' => 'wcac_enable_debug_logging_field'] 
        );

        // --- Scoring Rules Tab ---
        $explicit_static_keys = [
            'wcac_max_results_returned',
            'wcac_out_of_stock_penalty',
            'wcac_recency_boost_multiplier',
            'wcac_rating_weight',
            'wcac_on_sale_weight',
            'wcac_boost_term_score_value',
            'wcac_devalue_term_score_value'
        ];

        add_settings_section(
            'wcac_scoring_optimizable_section',
            esc_html__('Optimizable Parameters', 'wp-customer-ai-chatbot'),
            [$this->section_header_renderer, 'render_scoring_optimizable_header'],
            $scoring_page_slug
        );

        add_settings_section(
            'wcac_scoring_static_section',
            esc_html__('Static Parameters', 'wp-customer-ai-chatbot'),
            [$this->section_header_renderer, 'render_scoring_static_header'],
            $scoring_page_slug
        );
        
        foreach (self::$all_scoring_fields_definitions as $field_data) {
            $is_static = in_array($field_data['key'], $explicit_static_keys, true);
            $section_id = $is_static ? 'wcac_scoring_static_section' : 'wcac_scoring_optimizable_section';

            add_settings_field(
                $field_data['key'],
                esc_html__($field_data['label'], 'wp-customer-ai-chatbot'),
                [$this->field_renderer, 'render_generic_scoring_field'], 
                $scoring_page_slug,
                $section_id,
                ['label_for' => $field_data['key'], 'field_data' => $field_data]
            );
        }
    }

    /**
     * Get all scoring field definitions.
     *
     * @return array The array of all scoring field definitions.
     */
    public static function get_all_scoring_field_definitions(): array
    {
        return self::$all_scoring_fields_definitions;
    }
} 