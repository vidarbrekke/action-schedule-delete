#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
STAGING_USER="staging"
STAGING_HOST="45.33.31.79"
STAGING_PLUGIN_DIR="/home/staging/public_html/wp-content/plugins/" # Must end with a slash!
SSH_KEY="/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem"

# Use absolute paths
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
SOURCE_DIR="${SCRIPT_DIR}/../wp-customer-ai-chatbot"  # Only the plugin directory
TARGET_DIR="${STAGING_USER}@${STAGING_HOST}:${STAGING_PLUGIN_DIR}wp-customer-ai-chatbot/"
EXCLUDE_FILE="${SCRIPT_DIR}/.rsync-exclude"

# --- Create rsync exclude file ---
cat > $EXCLUDE_FILE <<EOL
.git/
.gitignore
.DS_Store
*.log
node_modules/
build/
dist/
coverage/
*.zip
*.tar.gz
dev-tools/
deployment/
tests/
EOL

echo "🚀 Starting deployment to staging server (${STAGING_HOST})..."
echo "Source directory: ${SOURCE_DIR}"
echo "Target directory: ${TARGET_DIR}"
echo "Using exclude file: ${EXCLUDE_FILE}"

# Sync files using exclude file
rsync -avz --delete --exclude-from="${EXCLUDE_FILE}" -e "ssh -i $SSH_KEY" "${SOURCE_DIR}/" "${TARGET_DIR}"

# Check if rsync was successful
if [ $? -ne 0 ]; then
    echo "❌ Error: rsync failed. Aborting deployment."
    exit 1
fi

echo "✅ Main plugin sync complete."

# Run composer install on server
echo "📦 Running composer install on staging server..."
ssh -i $SSH_KEY $STAGING_USER@$STAGING_HOST "cd ${STAGING_PLUGIN_DIR}wp-customer-ai-chatbot && composer install --no-dev --optimize-autoloader"

if [ $? -ne 0 ]; then
    echo "❌ Error: composer install failed on staging server."
    exit 1 
fi

# Set correct permissions
echo "🔒 Setting file permissions..."
ssh -i $SSH_KEY $STAGING_USER@$STAGING_HOST "chmod -R 755 ${STAGING_PLUGIN_DIR}wp-customer-ai-chatbot && find ${STAGING_PLUGIN_DIR}wp-customer-ai-chatbot -type f -exec chmod 644 {} \\;"

# --- Restart Services ---
SUDO_PASS='@Flatbygdi73?' # TODO: Consider using environment variables or secure password management

echo "🔄 Restarting PHP-FPM..."
ssh -i $SSH_KEY ${STAGING_USER}@${STAGING_HOST} "echo '$SUDO_PASS' | sudo -S systemctl restart php8.2-fpm"

echo "🔄 Restarting Apache..."
ssh -i $SSH_KEY ${STAGING_USER}@${STAGING_HOST} "echo '$SUDO_PASS' | sudo -S systemctl restart apache2"

# --- Cleanup ---
rm $EXCLUDE_FILE

echo "✅ Deployment complete!"
echo "🌐 Please verify the plugin is working at your staging site." 