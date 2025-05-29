<?php
function wcac_render_prune_log_button() {
    ?>
    <form method="post" style="display:inline; margin-bottom:16px;">
        <?php wp_nonce_field('wcac_prune_log', 'wcac_prune_log_nonce'); ?>
        <button type="submit" class="button" name="wcac_prune_log" value="1" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to prune the log? This cannot be undone.', 'wp-customer-ai-chatbot')); ?>');">
            <?php esc_html_e('Prune Log Now', 'wp-customer-ai-chatbot'); ?>
        </button>
    </form>
    <?php
} 