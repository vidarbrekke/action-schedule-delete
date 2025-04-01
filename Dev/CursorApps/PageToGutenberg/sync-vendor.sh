#!/bin/bash

# Configuration (should match deploy.sh)
PLUGIN_SLUG="url-to-gutenberg"
REMOTE_USER="staging"
REMOTE_HOST="45.33.31.79"
REMOTE_PATH="/home/staging/public_html"

# Create local backup of current vendor directory
echo "Creating backup of current vendor directory..."
if [ -d "vendor" ]; then
    timestamp=$(date +"%Y%m%d_%H%M%S")
    mkdir -p backups
    cp -r vendor "backups/vendor_backup_$timestamp"
    echo "Backup created at backups/vendor_backup_$timestamp"
fi

# Sync vendor directory from remote server
echo "Syncing vendor directory from remote server..."
rsync -avz --delete "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/wp-content/plugins/${PLUGIN_SLUG}/vendor/" ./vendor/

echo "Vendor directory sync complete!" 