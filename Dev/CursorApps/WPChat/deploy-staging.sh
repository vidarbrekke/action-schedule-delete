#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
STAGING_USER="staging"
STAGING_HOST="45.33.31.79"
STAGING_PLUGIN_DIR="/home/staging/public_html/wp-content/plugins/" # Must end with a slash!
SSH_KEY="/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem"

# --- Variables ---
SOURCE_DIR="./wp-customer-ai-chatbot/"
TARGET_DIR="${STAGING_USER}@${STAGING_HOST}:${STAGING_PLUGIN_DIR}wp-customer-ai-chatbot/"
EXCLUDE_FILE=".rsync-exclude"

# --- Create rsync exclude file (optional, but good practice) ---
# Prevents uploading unnecessary files like .git, .gitignore, development plans etc.
cat > $EXCLUDE_FILE <<EOL
/.git
/.gitignore
/tests
/testing-plan.md
/development-plan.md
/deploy-staging.sh
*.log
.DS_Store
EOL

# --- Deployment ---
echo "🚀 Starting deployment to staging server (${STAGING_HOST})..."

# Use rsync to sync files. Options:
# -a: archive mode (preserves permissions, ownership, timestamps, etc.)
# -v: verbose output
# -z: compress file data during transfer
# --delete: delete extraneous files from destination dirs
# --exclude-from: read exclude patterns from file
# -e: specify the remote shell to use (here, ssh with the specific key)
rsync -avz --delete --exclude-from=$EXCLUDE_FILE -e "ssh -i $SSH_KEY" "$SOURCE_DIR" "$TARGET_DIR"

# --- Cleanup ---
rm $EXCLUDE_FILE

echo "✅ Deployment complete." 