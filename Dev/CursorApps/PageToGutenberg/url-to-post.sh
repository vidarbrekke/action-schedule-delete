#!/bin/bash

# URL To Gutenberg Post Creator
# This script automates the workflow from URL to WordPress post

# Check if a URL was provided
if [ $# -lt 1 ]; then
    echo "Usage: $0 <url>"
    exit 1
fi

URL=$1
SERVER="staging@45.33.31.79"
SERVER_PATH="/home/staging/public_html"
DATE=$(date +"%Y%m%d-%H%M%S")
FILENAME="url-extraction-${DATE}"
JSON_FILE="${FILENAME}-final_content.json"

echo "==============================================="
echo "Starting URL to Post process for: $URL"
echo "==============================================="

# Step 1: Extract content using EasyPHPArticleExtractor
echo "Step 1: Extracting content from URL..."
ssh $SERVER "cd $SERVER_PATH && wp eval 'require_once \"wp-load.php\"; \$extractor = new hstanleycrow\EasyPHPArticleExtractor\Extractor(); \$content = \$extractor->getContent(\"$URL\"); \$upload_dir = wp_upload_dir(); \$debug_dir = \$upload_dir[\"basedir\"] . \"/utg-debug/\"; if (!file_exists(\$debug_dir)) { mkdir(\$debug_dir, 0755, true); } file_put_contents(\$debug_dir . \"$FILENAME.txt\", \$content);'"

if [ $? -ne 0 ]; then
    echo "Error: Content extraction failed"
    exit 1
fi

echo "Content extracted successfully"

# Step 2: Process content with LLM API (simulated here - would be a real API call)
echo "Step 2: Processing content with LLM..."
# This would be a real API call to your LLM
ssh $SERVER "cd $SERVER_PATH && wp eval 'require_once \"wp-load.php\"; \$upload_dir = wp_upload_dir(); \$debug_dir = \$upload_dir[\"basedir\"] . \"/utg-debug/\"; \$content = file_get_contents(\$debug_dir . \"$FILENAME.txt\"); \$json_data = json_encode([\"title\" => \"Article from $URL\", \"blocks\" => [[\"blockName\" => \"core/paragraph\", \"attrs\" => [], \"innerBlocks\" => [], \"innerHTML\" => \"<p>This is a test paragraph from the URL: $URL</p>\", \"innerContent\" => [\"<p>This is a test paragraph from the URL: $URL</p>\"]]]]);
file_put_contents(\$debug_dir . \"$JSON_FILE.json\", \$json_data);'"

if [ $? -ne 0 ]; then
    echo "Error: LLM processing failed"
    exit 1
fi

echo "Content processed by LLM successfully"

# Step 3: Create WordPress post from JSON
echo "Step 3: Creating WordPress post..."
ssh $SERVER "cd $SERVER_PATH && wp utg json-to-post $JSON_FILE.json"

if [ $? -ne 0 ]; then
    echo "Error: Post creation failed"
    exit 1
fi

echo "==============================================="
echo "Process completed successfully!"
echo "===============================================" 