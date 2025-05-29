<?php
/**
 * Settings page template.
 *
 * @package UTG
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get current settings
$settings = $this->get_settings();
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <?php settings_errors( 'utg_settings' ); ?>
    
    <form method="post" action="">
        <?php wp_nonce_field( 'utg_settings_nonce', 'utg_settings_nonce' ); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="utg_api_key"><?php esc_html_e( 'API Key', 'url-to-gutenberg' ); ?></label>
                </th>
                <td>
                    <input type="text" name="utg_api_key" id="utg_api_key" class="regular-text" 
                           value="<?php echo esc_attr( $settings->get( 'api_key', '' ) ); ?>">
                    <p class="description">
                        <?php esc_html_e( 'Your OpenRouter API key. You can get one at openrouter.ai', 'url-to-gutenberg' ); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="utg_api_base_url"><?php esc_html_e( 'API Base URL', 'url-to-gutenberg' ); ?></label>
                </th>
                <td>
                    <input type="text" name="utg_api_base_url" id="utg_api_base_url" class="regular-text" 
                           value="<?php echo esc_attr( $settings->get( 'api_base_url', 'https://openrouter.ai/api/v1' ) ); ?>">
                    <p class="description">
                        <?php esc_html_e( 'The base URL for the API. Default is https://openrouter.ai/api/v1', 'url-to-gutenberg' ); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="utg_default_model"><?php esc_html_e( 'Default Model', 'url-to-gutenberg' ); ?></label>
                </th>
                <td>
                    <select name="utg_default_model" id="utg_default_model">
                        <?php
                        $current_model = $settings->get( 'default_model', 'anthropic/claude-3-haiku' );
                        $models = [
                            'anthropic/claude-3-haiku' => 'Claude 3 Haiku',
                            'anthropic/claude-3-sonnet' => 'Claude 3 Sonnet',
                            'anthropic/claude-3-opus' => 'Claude 3 Opus',
                            'openai/gpt-4' => 'GPT-4',
                            'openai/gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                        ];
                        
                        foreach ( $models as $model_id => $model_name ) {
                            printf(
                                '<option value="%s" %s>%s</option>',
                                esc_attr( $model_id ),
                                selected( $current_model, $model_id, false ),
                                esc_html( $model_name )
                            );
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'The default model to use for content processing.', 'url-to-gutenberg' ); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php esc_html_e( 'Debug Mode', 'url-to-gutenberg' ); ?>
                </th>
                <td>
                    <label for="utg_debug_mode">
                        <input type="checkbox" name="utg_debug_mode" id="utg_debug_mode" 
                               <?php checked( $settings->get( 'debug_mode', false ) ); ?>>
                        <?php esc_html_e( 'Enable debug mode', 'url-to-gutenberg' ); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e( 'When enabled, debug information will be logged to the error log.', 'url-to-gutenberg' ); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="utg_min_content_length"><?php esc_html_e( 'Minimum Content Length', 'url-to-gutenberg' ); ?></label>
                </th>
                <td>
                    <input type="number" name="utg_min_content_length" id="utg_min_content_length" class="small-text" 
                           value="<?php echo esc_attr( $settings->get( 'min_content_length', 200 ) ); ?>" min="0">
                    <p class="description">
                        <?php esc_html_e( 'Minimum text content length (in characters) required for valid extraction.', 'url-to-gutenberg' ); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="utg_min_image_count"><?php esc_html_e( 'Minimum Image Count', 'url-to-gutenberg' ); ?></label>
                </th>
                <td>
                    <input type="number" name="utg_min_image_count" id="utg_min_image_count" class="small-text" 
                           value="<?php echo esc_attr( $settings->get( 'min_image_count', 0 ) ); ?>" min="0">
                    <p class="description">
                        <?php esc_html_e( 'Minimum number of images required for valid extraction. Set to 0 to disable this check.', 'url-to-gutenberg' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e( 'Hybrid Extraction System', 'url-to-gutenberg' ); ?></h2>
        
        <p>
            <?php esc_html_e( 'URL to Gutenberg uses a hybrid extraction system that combines EasyPHPArticleExtractor for standard HTML pages and Symfony Panther for JavaScript-heavy sites. The system first attempts to extract content using the standard method, and if it fails or the content is insufficient, it falls back to the headless browser approach.', 'url-to-gutenberg' ); ?>
        </p>
        
        <p>
            <?php esc_html_e( 'This approach ensures maximum compatibility with different types of websites while maintaining performance.', 'url-to-gutenberg' ); ?>
        </p>
        
        <?php submit_button( __( 'Save Settings', 'url-to-gutenberg' ), 'primary', 'utg_save_settings' ); ?>
    </form>
</div> 