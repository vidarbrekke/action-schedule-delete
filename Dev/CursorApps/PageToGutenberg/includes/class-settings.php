<?php
/**
 * Settings class for URL to Gutenberg
 *
 * @package UTG
 */

namespace UTG;

/**
 * Class Settings
 * Handles plugin settings and options
 */
class Settings {

    /**
     * Option name in the WordPress database
     *
     * @var string
     */
    private $option_name = 'utg_settings';

    /**
     * Default settings values
     *
     * @var array
     */
    private $defaults = array(
        'api_key' => '',
        'api_endpoint' => 'https://api.openai.com/v1/chat/completions',
        'api_model' => 'gpt-3.5-turbo',
        'max_tokens' => 2000,
        'temperature' => 0.7,
        'extract_images' => true,
        'create_featured_image' => true,
        'default_category' => 1,
        'default_status' => 'draft',
        'cleanup_html' => true,
        'debug_mode' => false,
        'cache_enabled' => true,
        'cache_lifetime' => 86400, // 24 hours in seconds
    );

    /**
     * Current settings values
     *
     * @var array
     */
    private $options;

    /**
     * Settings constructor.
     */
    public function __construct() {
        $this->options = get_option($this->option_name, $this->defaults);
        $this->options = wp_parse_args($this->options, $this->defaults);
    }

    /**
     * Get a specific setting value
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value if setting is not found.
     * @return mixed Setting value
     */
    public function get($key, $default = null) {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }

        if (null !== $default) {
            return $default;
        }

        if (isset($this->defaults[$key])) {
            return $this->defaults[$key];
        }

        return null;
    }

    /**
     * Set a specific setting value
     *
     * @param string $key   Setting key.
     * @param mixed  $value Setting value.
     * @return bool Success or failure
     */
    public function set($key, $value) {
        $this->options[$key] = $value;
        return $this->save();
    }

    /**
     * Save all settings to database
     *
     * @return bool Success or failure
     */
    public function save() {
        return update_option($this->option_name, $this->options);
    }

    /**
     * Update multiple settings at once
     *
     * @param array $new_options Array of new option values.
     * @return bool Success or failure
     */
    public function update($new_options) {
        $this->options = array_merge($this->options, $new_options);
        return $this->save();
    }

    /**
     * Reset settings to defaults
     *
     * @return bool Success or failure
     */
    public function reset() {
        $this->options = $this->defaults;
        return $this->save();
    }

    /**
     * Get all settings
     *
     * @return array All setting values
     */
    public function get_all() {
        return $this->options;
    }
} 