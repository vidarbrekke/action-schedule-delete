<?php
/**
 * Template for the WCAC Chatbot Shortcode.
 *
 * This template can be overridden by copying it to yourtheme/wp-customer-ai-chatbot/wcac-chat-widget-template.php.
 *
 * @package Wcac_Customer_AI_Chatbot
 * @version 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define fallback functions if they don't exist
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $default;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Get options for the chatbot
$options = get_option('wcac_settings', array());
$initial_greeting = isset($options['wcac_initial_greeting']) ? $options['wcac_initial_greeting'] : 'Hello! How can I help you today?';

?>
<div id="wcac-chatbot-container" class="wcac-chatbot-container" data-greeting="<?php echo esc_attr($initial_greeting); ?>">
    <h2><?php echo isset($options['wcac_chat_title']) ? esc_html($options['wcac_chat_title']) : 'AI Shopping Assistant'; ?></h2>
    <div id="wcac-chat-messages" class="wcac-chat-messages">
        <!-- Messages will appear here dynamically via JavaScript -->
    </div>
    <form id="wcac-chat-form" class="wcac-chat-form">
        <div class="wcac-chat-input">
            <textarea id="wcac-message" class="wcac-message" placeholder="<?php echo isset($options['wcac_input_placeholder']) ? esc_attr($options['wcac_input_placeholder']) : 'Type your message...'; ?>" rows="1"></textarea>
            <button id="wcac-send-button" class="wcac-send-button" type="submit"><?php echo isset($options['wcac_send_button_text']) ? esc_html($options['wcac_send_button_text']) : 'Send'; ?></button>
        </div>
    </form>
    <div class="wcac-branding">
        <?php if (!isset($options['wcac_hide_branding']) || !$options['wcac_hide_branding']): ?>
            <small>Powered by WP Customer AI Chatbot</small>
        <?php endif; ?>
    </div>
</div> 