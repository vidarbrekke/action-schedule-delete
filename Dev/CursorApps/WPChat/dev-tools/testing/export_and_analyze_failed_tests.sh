#!/bin/bash
# dev-tools/testing/export_and_analyze_failed_tests.sh
# Usage: bash dev-tools/testing/export_and_analyze_failed_tests.sh

set -e

# Config
SSH_USER="staging"
SSH_HOST="45.33.31.79"
SSH_KEY="/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem"
REMOTE_WP_PATH="/home/staging/public_html"
REMOTE_TOOLS_DIR="$REMOTE_WP_PATH/wp-content/plugins/wp-customer-ai-chatbot/dev-tools/testing"
LOCAL_TOOLS_DIR="$(cd "$(dirname "$0")" && pwd)"
REPORT_MD_NAME="wcac_failed_test_diagnosis_report.md"
REMOTE_REPORT_PATH="$REMOTE_TOOLS_DIR/$REPORT_MD_NAME"
LOCAL_REPORT_PATH="$LOCAL_TOOLS_DIR/$REPORT_MD_NAME"

# Check SSH key
if [ -z "$SSH_KEY" ]; then
  echo "ERROR: SSH key variable is empty."
  exit 1
fi
if [ ! -f "$SSH_KEY" ]; then
  echo "ERROR: SSH key not found at $SSH_KEY"
  exit 1
fi
if [ ! -r "$SSH_KEY" ]; then
  echo "ERROR: SSH key at $SSH_KEY is not readable. Check permissions."
  exit 1
fi

echo "Using SSH key: $SSH_KEY"

# 1. Run the DB-based analysis script via WP-CLI on the server
# This will generate the Markdown report in the tools directory
# (Assumes dev-tools/testing/wcac_failed_test_diagnosis.php is present and up to date)
echo "[1/3] Running DB-based analysis script on server via WP-CLI..."
ssh -i "$SSH_KEY" $SSH_USER@$SSH_HOST "cd $REMOTE_WP_PATH && wp eval-file wp-content/plugins/wp-customer-ai-chatbot/dev-tools/testing/wcac_failed_test_diagnosis.php"

# 2. Download the generated Markdown report
if [ -f "$LOCAL_REPORT_PATH" ]; then
    rm -f "$LOCAL_REPORT_PATH"
fi
echo "[2/3] Downloading generated Markdown report..."
scp -i "$SSH_KEY" $SSH_USER@$SSH_HOST:"$REMOTE_REPORT_PATH" "$LOCAL_REPORT_PATH" || { echo 'Failed to download Markdown report.'; exit 1; }
echo "Downloaded report to $LOCAL_REPORT_PATH."

# 3. Print a summary and location
if [ -f "$LOCAL_REPORT_PATH" ]; then
    echo "[3/3] Analysis complete. See $LOCAL_REPORT_PATH for results."
else
    echo "Analysis report not found: $LOCAL_REPORT_PATH"
    exit 1
fi

echo "Workflow finished." 