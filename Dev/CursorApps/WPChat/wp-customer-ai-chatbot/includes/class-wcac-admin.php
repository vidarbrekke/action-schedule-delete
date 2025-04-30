<?php

class WCAC_Admin {
    public function __construct() {
        // Add after other AJAX handlers in the constructor
        add_action('wp_ajax_wcac_cleanup_categories', [$this, 'handle_category_cleanup_ajax']);
    }

    /**
     * Handle AJAX request to clean up duplicate categories
     */
    public function handle_category_cleanup_ajax(): void {
        // Check nonce and capabilities
        check_ajax_referer('wcac_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'You do not have permission to perform this action.']);
            return;
        }
        
        // Create indexer instance and run the cleanup
        $indexer = new Wcac_Indexer();
        $results = $indexer->fix_duplicate_categories();
        
        // Format message
        $message = sprintf(
            'Category cleanup completed. Checked %d products, updated %d products with duplicate categories, failed to update %d products.',
            $results['products_checked'],
            $results['products_updated'],
            $results['products_failed']
        );
        
        // Include details for verbose logging
        $details = '';
        if (!empty($results['details'])) {
            $details = '<br><strong>Details:</strong><br>' . implode('<br>', array_slice($results['details'], 0, 10));
            if (count($results['details']) > 10) {
                $details .= '<br>...(and ' . (count($results['details']) - 10) . ' more)';
            }
        }
        
        wp_send_json_success([
            'message' => $message . $details,
            'results' => $results
        ]);
    }
} 