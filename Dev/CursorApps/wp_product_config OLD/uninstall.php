<?php
/**
 * WC Quantity Multiples - Uninstall
 * 
 * Fired when the plugin is uninstalled to clean up any plugin data.
 *
 * @package WC_Quantity_Multiples
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'wcqm_settings' );

// Clear any cached or transient data
$transients_to_clear = array(
    'wcqm_corrected_cart_items',
    'wcqm_quantities_changed'
);

foreach ( $transients_to_clear as $transient ) {
    delete_transient( $transient );
} 