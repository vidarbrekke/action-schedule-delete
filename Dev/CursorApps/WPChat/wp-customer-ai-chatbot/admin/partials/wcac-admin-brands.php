<?php
// phpcs:ignoreFile -- This file is loaded in admin context only.
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$option_key = 'wcac_candidate_brands';
$brands = get_option($option_key, []);
if (!is_array($brands)) {
    $brands = [];
}

// Handle add/edit/delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('wcac_manage_brands')) {
    // Add brand
    if (!empty($_POST['new_brand'])) {
        $new_brand = trim(sanitize_text_field($_POST['new_brand']));
        if ($new_brand !== '') {
            $brands[strtolower($new_brand)] = $new_brand;
            update_option($option_key, $brands, false);
            echo '<div class="updated"><p>Brand added.</p></div>';
        }
    }
    // Edit brand
    if (!empty($_POST['edit_key']) && isset($_POST['edit_brand'])) {
        $edit_key = sanitize_text_field($_POST['edit_key']);
        $edit_brand = trim(sanitize_text_field($_POST['edit_brand']));
        if ($edit_brand !== '') {
            unset($brands[$edit_key]);
            $brands[strtolower($edit_brand)] = $edit_brand;
            update_option($option_key, $brands, false);
            echo '<div class="updated"><p>Brand updated.</p></div>';
        }
    }
    // Delete brand
    if (!empty($_POST['delete_key'])) {
        $delete_key = sanitize_text_field($_POST['delete_key']);
        unset($brands[$delete_key]);
        update_option($option_key, $brands, false);
        echo '<div class="updated"><p>Brand deleted.</p></div>';
    }
    // Refresh after POST
    $brands = get_option($option_key, []);
    if (!is_array($brands)) {
        $brands = [];
    }
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Manage Brands', 'wp-customer-ai-chatbot'); ?></h1>
    <form method="post">
        <?php wp_nonce_field('wcac_manage_brands'); ?>
        <table class="widefat fixed" style="max-width:600px;">
            <thead>
                <tr><th><?php esc_html_e('Brand Name', 'wp-customer-ai-chatbot'); ?></th><th><?php esc_html_e('Actions', 'wp-customer-ai-chatbot'); ?></th></tr>
            </thead>
            <tbody>
                <?php foreach ($brands as $key => $brand): ?>
                    <tr>
                        <td>
                            <?php if (isset($_POST['edit_row']) && $_POST['edit_row'] === $key): ?>
                                <input type="text" name="edit_brand" value="<?php echo esc_attr($brand); ?>" />
                                <input type="hidden" name="edit_key" value="<?php echo esc_attr($key); ?>" />
                            <?php else: ?>
                                <?php echo esc_html($brand); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (isset($_POST['edit_row']) && $_POST['edit_row'] === $key): ?>
                                <button type="submit" class="button button-primary">Update</button>
                            <?php else: ?>
                                <button type="submit" name="edit_row" value="<?php echo esc_attr($key); ?>" class="button">Edit</button>
                                <button type="submit" name="delete_key" value="<?php echo esc_attr($key); ?>" class="button" onclick="return confirm('Delete this brand?');">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><input type="text" name="new_brand" placeholder="Add new brand..." /></td>
                    <td><button type="submit" class="button button-primary">Add</button></td>
                </tr>
            </tbody>
        </table>
    </form>
</div> 