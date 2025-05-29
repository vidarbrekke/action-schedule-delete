<?php
/**
 * Manual script to rebuild the WooCommerce product index for the AI Chatbot.
 * 
 * Usage: Add this file to the plugin directory and run it via WP-CLI:
 * `wp eval-file wp-content/plugins/wp-customer-ai-chatbot/rebuild-index.php`
 * or visit it directly via browser once (not recommended for production).
 */

// Prevent direct access unless WP-CLI or explicitly allowed
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
    // Check if we're in a browser with a secret key
    $secret_key = isset($_GET['secret']) ? $_GET['secret'] : '';
    $expected_key = 'wcac_rebuild_' . date('Ymd');
    
    if ($secret_key !== $expected_key) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied.';
        exit;
    }
    
    // For browser access, define a minimal WordPress environment
    define('WP_USE_THEMES', false);
    require_once('../../../wp-load.php');
}

// Output function that works in both CLI and browser
function wcac_output($message) {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::log($message);
    } else {
        echo $message . "<br>\n";
        flush();
    }
}

wcac_output('Starting index rebuild...');

// Load the indexer class if not already loaded
if (!class_exists('Wcac_Indexer')) {
    require_once dirname(__FILE__) . '/../wp-customer-ai-chatbot/includes/class-wcac-indexer.php';
}

// Create indexer and build index
try {
    $indexer = new Wcac_Indexer();
    $result = $indexer->build_index(); // This now handles all selected types
    
    if ($result['error']) {
        wcac_output('Error: ' . $result['error']);
    } else {
		$counts = $result['counts'];
		$counts_string = sprintf(
			'Total: %d (Products: %d, Pages: %d, Posts: %d)',
			absint($counts['total'] ?? 0),
			absint($counts['product'] ?? 0),
			absint($counts['page'] ?? 0),
			absint($counts['post'] ?? 0)
		);
        wcac_output('Success! Indexed content. ' . $counts_string );
        
        // Check index structure for debugging (using the new key)
        $index = get_option('wcac_content_index', []);
		if (!empty($index)) {
			$first_item_id = array_key_first($index);
			$first_item = $index[$first_item_id];
			
			wcac_output('Index structure check (First Item ID: ' . $first_item_id . '):');
			if (is_array($first_item)) {
				wcac_output('✅ First item is correctly stored as an array.');
				if (isset($first_item['text'])) {
					wcac_output('✅ First item has the required "text" field.');
				} else {
					wcac_output('❌ First item is missing the "text" field! Keys: ' . implode(', ', array_keys($first_item)));
				}
				if (isset($first_item['type'])) {
					wcac_output('✅ First item has the required "type" field: ' . $first_item['type']);
				} else {
					wcac_output('❌ First item is missing the "type" field!');
				}
			} else {
				wcac_output('❌ First item is NOT stored as an array. Type: ' . gettype($first_item));
			}
		} else {
			wcac_output('Index is empty after rebuild.');
		}
    }
} catch (Exception $e) {
    wcac_output('Fatal error: ' . $e->getMessage());
}

wcac_output('Index rebuild process complete.');

// For browser access, provide a link back to admin
if (!defined('WP_CLI') || !WP_CLI) {
    echo '<p><a href="' . admin_url('admin.php?page=wcac-settings') . '">Return to settings</a></p>';
} 