# Action Scheduler Cleanup Plugin

A WordPress plugin that automatically cleans up Action Scheduler data to prevent database bloat.

## Features

- **Automatic Cleanup**: Runs every 30 days via WordPress cron
- **Failed Actions**: Removes all failed actions immediately
- **Old Completed Actions**: Removes completed actions older than 30 days
- **Orphaned Logs**: Cleans up log entries without corresponding actions
- **Admin Interface**: Tools page with manual run button and status display
- **Safe Operations**: Proper WordPress hooks and nonce verification

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Access via Tools → Action Scheduler Cleanup

## What It Cleans

- `wp_actionscheduler_actions` - Failed and old completed actions
- `wp_actionscheduler_logs` - Orphaned log entries
- Compatible with custom table prefixes

## Security

- Requires `manage_options` capability
- AJAX nonce verification
- Proper WordPress sanitization and escaping

## Database Impact

- Significantly reduces Action Scheduler table sizes
- Improves database performance
- Prevents future accumulation of old data

## Compatibility

- WordPress 5.0+
- WooCommerce (uses Action Scheduler)
- Custom table prefixes supported
