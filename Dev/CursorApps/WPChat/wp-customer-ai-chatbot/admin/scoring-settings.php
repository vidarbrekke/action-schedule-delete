<?php

declare(strict_types=1);

// Register scoring section
add_settings_section(
    'wcac_scoring_section',
    esc_html__('Scoring Rules', 'wp-customer-ai-chatbot'),
    function() {
        echo '<p>' . esc_html__('Configure how products and content are scored in search results. These settings affect how matches are ranked and which results appear first.', 'wp-customer-ai-chatbot') . '</p>';
    },
    'wcac-settings-scoring'
);

// Add all numeric weight fields
$numeric_weight_fields = [
    [
        'key' => 'wcac_parent_product_weight',
        'label' => 'Parent Product Weight',
        'desc' => 'Base score for parent products. Higher values (150+) ensure parent products appear first in broad searches.',
        'default' => 150,
        'min' => 0, 'max' => 500, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_variation_product_weight',
        'label' => 'Variation Product Weight',
        'desc' => 'Base score for product variations. Keep low (1-5) to prevent variations from dominating broad searches.',
        'default' => 2,
        'min' => 0, 'max' => 500, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_title_match_weight',
        'label' => 'Title Match Weight',
        'desc' => 'Score added for each keyword match in titles. Lower values (15.0) prevent generic title matches from dominating specific content matches.',
        'default' => 15,
        'min' => 0, 'max' => 100, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_content_match_weight',
        'label' => 'Content Match Weight',
        'desc' => 'Score added for each keyword match in indexed content (search_blob). Higher values (15.0) give more weight to detailed content matches.',
        'default' => 15,
        'min' => 0, 'max' => 100, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_category_match_weight',
        'label' => 'Category Match Weight',
        'desc' => 'Score added for each keyword match in product categories. Higher values (35.0) ensure products in relevant categories rank well.',
        'default' => 35,
        'min' => 0, 'max' => 100, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_tag_match_weight',
        'label' => 'Tag Match Weight',
        'desc' => 'Score added for each keyword match in product tags. Moderate values (15.0) help with additional context without overwhelming other factors.',
        'default' => 15,
        'min' => 0, 'max' => 100, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_on_sale_weight',
        'label' => 'On Sale Weight',
        'desc' => 'Score added if the product is currently marked as \'on sale\'.',
        'default' => 10,
        'min' => 0, 'max' => 50, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_menu_match_weight',
        'label' => 'Menu Match Weight',
        'desc' => 'Score added for each keyword match in menu item titles. Higher values prioritize results that are present in the site navigation.',
        'default' => 10,
        'min' => 0, 'max' => 100, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_attribute_match_weight',
        'label' => 'Attribute Match Weight',
        'desc' => 'Score added for each keyword match in product attributes.',
        'default' => 10,
        'min' => 0, 'max' => 100, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_parent_category_match_weight',
        'label' => 'Parent Category Match Weight',
        'desc' => 'Score added for each keyword match in parent categories.',
        'default' => 10,
        'min' => 0, 'max' => 100, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_taxonomy_match_weight',
        'label' => 'Taxonomy Match Weight',
        'desc' => 'Score added for each keyword match in custom taxonomies.',
        'default' => 10,
        'min' => 0, 'max' => 100, 'step' => 0.1,
    ],
    [
        'key' => 'wcac_rating_weight',
        'label' => 'Rating Weight',
        'desc' => 'Score added based on product average rating (0-5 stars, normalized). Higher values prioritize highly rated products.',
        'default' => 20,
        'min' => 0, 'max' => 100, 'step' => 0.1,
    ],
];

foreach ($numeric_weight_fields as $field) {
    add_settings_field(
        $field['key'],
        esc_html__($field['label'], 'wp-customer-ai-chatbot'),
        function() use ($field) {
            $options = get_option('wcac_settings', []);
            $value = $options[$field['key']] ?? $field['default'];
            ?>
            <input type="number"
                   step="<?php echo esc_attr($field['step']); ?>"
                   min="<?php echo esc_attr($field['min']); ?>"
                   max="<?php echo esc_attr($field['max']); ?>"
                   id="<?php echo esc_attr($field['key']); ?>"
                   name="<?php echo esc_attr('wcac_settings[' . $field['key'] . ']'); ?>"
                   value="<?php echo esc_attr((string)$value); ?>"
                   class="regular-text">
            <p class="description">
                <?php echo esc_html__($field['desc'], 'wp-customer-ai-chatbot');
                echo ' ' . sprintf(esc_html__('Default: %s', 'wp-customer-ai-chatbot'), esc_html((string)$field['default'])); ?>
            </p>
            <?php
        },
        'wcac-settings-scoring',
        'wcac_scoring_section',
        [
            'label_for' => $field['key'],
            'field' => $field
        ]
    );
}

error_log('WCAC DEBUG: Registered scoring settings');