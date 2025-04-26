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

// Note: $atts variable is available in this scope from the shortcode callback.

?>
<div class="wcac-chatbot-container">
    <h2>AI Chatbot (Static Placeholder)</h2>
    <div class="wcac-chat-messages" style="height: 200px; border: 1px solid #ccc; margin-bottom: 10px; overflow-y: scroll; padding: 5px;">
        <!-- Messages will appear here -->
        <p><strong>Bot:</strong> Hello! How can I help you today? (This is a static message)</p>
    </div>
    <div class="wcac-chat-input">
        <input type="text" placeholder="Type your message..." style="width: 80%; padding: 8px;">
        <button type="button" style="padding: 8px;">Send</button>
    </div>
</div> 