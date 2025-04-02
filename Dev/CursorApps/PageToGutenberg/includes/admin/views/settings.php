<?php
/**
 * Settings view for URL to Gutenberg
 * 
 * @package UTG
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Create the settings object if it's not available
if (!isset($settings) || !is_object($settings) || !method_exists($settings, 'get')) {
    echo '<div class="notice notice-error"><p>';
    _e('Error: Settings object not available. Creating a fallback for basic functionality.', 'url-to-gutenberg');
    echo '</p></div>';
    
    // Create a fallback settings object
    class UTG_Fallback_Settings {
        /**
         * Get a setting value
         *
         * @param string $key Setting key
         * @param mixed $default Default value
         * @return mixed Setting value or default
         */
        public function get($key, $default = '') {
            return $default;
        }
    }
    
    $settings = new UTG_Fallback_Settings();
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('utg_settings');
        do_settings_sections('utg_settings');
        ?>
        
        <div class="utg-settings-container">
            <h2 class="title"><?php _e('API Settings', 'url-to-gutenberg'); ?></h2>
            <p><?php _e('Configure your LLM API credentials. We recommend using OpenRouter as your LLM provider.', 'url-to-gutenberg'); ?></p>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="utg_api_key"><?php _e('API Key', 'url-to-gutenberg'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                id="utg_api_key" 
                                name="utg_settings[api_key]" 
                                value="<?php echo esc_attr($settings->get('api_key')); ?>" 
                                class="regular-text">
                            <p class="description"><?php _e('Enter your OpenRouter API key. This is required for the plugin to function.', 'url-to-gutenberg'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="utg_api_endpoint"><?php _e('API Endpoint', 'url-to-gutenberg'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                id="utg_api_endpoint" 
                                name="utg_settings[api_endpoint]" 
                                value="<?php echo esc_attr($settings->get('api_endpoint')); ?>" 
                                class="regular-text">
                            <p class="description"><?php _e('The LLM API endpoint. Default is OpenRouter\'s endpoint.', 'url-to-gutenberg'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="utg_default_model"><?php _e('Default Model', 'url-to-gutenberg'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                id="utg_default_model" 
                                name="utg_settings[default_model]" 
                                value="<?php echo esc_attr($settings->get('default_model')); ?>" 
                                class="regular-text">
                            <p class="description"><?php _e('The default model to use for processing URLs. Must support multi-modal capabilities.', 'url-to-gutenberg'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h2 class="title"><?php _e('Post Settings', 'url-to-gutenberg'); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="utg_default_post_status"><?php _e('Default Post Status', 'url-to-gutenberg'); ?></label>
                        </th>
                        <td>
                            <select id="utg_default_post_status" name="utg_settings[default_post_status]">
                                <option value="draft" <?php selected($settings->get('default_post_status'), 'draft'); ?>><?php _e('Draft', 'url-to-gutenberg'); ?></option>
                                <option value="publish" <?php selected($settings->get('default_post_status'), 'publish'); ?>><?php _e('Published', 'url-to-gutenberg'); ?></option>
                                <option value="pending" <?php selected($settings->get('default_post_status'), 'pending'); ?>><?php _e('Pending Review', 'url-to-gutenberg'); ?></option>
                                <option value="private" <?php selected($settings->get('default_post_status'), 'private'); ?>><?php _e('Private', 'url-to-gutenberg'); ?></option>
                            </select>
                            <p class="description"><?php _e('The default status for new posts.', 'url-to-gutenberg'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <h2 class="title"><?php _e('Advanced Settings', 'url-to-gutenberg'); ?></h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <?php _e('Cache Settings', 'url-to-gutenberg'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php _e('Cache Settings', 'url-to-gutenberg'); ?></span>
                                </legend>
                                <label for="utg_cache_enabled">
                                    <input type="checkbox" 
                                        id="utg_cache_enabled" 
                                        name="utg_settings[cache_enabled]" 
                                        value="1" 
                                        <?php checked($settings->get('cache_enabled')); ?>>
                                    <?php _e('Enable caching of API responses', 'url-to-gutenberg'); ?>
                                </label>
                                <p class="description"><?php _e('Cache API responses to improve performance and reduce API calls.', 'url-to-gutenberg'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="utg_cache_expiration"><?php _e('Cache Expiration', 'url-to-gutenberg'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                id="utg_cache_expiration" 
                                name="utg_settings[cache_expiration]" 
                                value="<?php echo esc_attr($settings->get('cache_expiration')); ?>" 
                                class="small-text">
                            <?php _e('seconds', 'url-to-gutenberg'); ?>
                            <p class="description"><?php _e('How long to cache API responses. Default is 3600 seconds (1 hour).', 'url-to-gutenberg'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Debug Mode', 'url-to-gutenberg'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php _e('Debug Mode', 'url-to-gutenberg'); ?></span>
                                </legend>
                                <label for="utg_debug_mode">
                                    <input type="checkbox" 
                                        id="utg_debug_mode" 
                                        name="utg_settings[debug_mode]" 
                                        value="1" 
                                        <?php checked($settings->get('debug_mode')); ?>>
                                    <?php _e('Enable debug mode', 'url-to-gutenberg'); ?>
                                </label>
                                <p class="description"><?php _e('Log detailed information to the server error log. Only enable for troubleshooting.', 'url-to-gutenberg'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php
            // Allow other plugins to add their own settings sections
            do_action('utg_settings_sections', $settings);
            
            submit_button();
            ?>
        </div>
    </form>
    
    <div class="utg-api-test">
        <h2><?php _e('Test API Connection', 'url-to-gutenberg'); ?></h2>
        <p><?php _e('Click the button below to test your API connection.', 'url-to-gutenberg'); ?></p>
        <button type="button" id="utg-test-api" class="button button-secondary">
            <?php _e('Test Connection', 'url-to-gutenberg'); ?>
        </button>
        <span class="spinner"></span>
        <div id="utg-test-result" class="hidden"></div>
    </div>
    
    <div class="utg-openrouter-guide">
        <h2><?php _e('How to Use OpenRouter', 'url-to-gutenberg'); ?></h2>
        <ol>
            <li><?php _e('Sign up for an account at <a href="https://openrouter.ai" target="_blank">OpenRouter.ai</a>', 'url-to-gutenberg'); ?></li>
            <li><?php _e('Navigate to your API Keys in the dashboard', 'url-to-gutenberg'); ?></li>
            <li><?php _e('Create a new API key and copy it', 'url-to-gutenberg'); ?></li>
            <li><?php _e('Paste the API key in the field above', 'url-to-gutenberg'); ?></li>
            <li><?php _e('Make sure to use a multi-modal model that supports both text and image processing', 'url-to-gutenberg'); ?></li>
        </ol>
        <p><?php _e('For more detailed information, please refer to <a href="https://openrouter.ai/docs" target="_blank">OpenRouter\'s documentation</a>.', 'url-to-gutenberg'); ?></p>
    </div>
</div> 