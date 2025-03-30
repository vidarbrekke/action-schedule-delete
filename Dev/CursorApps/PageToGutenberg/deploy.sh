#!/bin/bash

# Exit on error
set -e

# Configuration
PLUGIN_SLUG="url-to-gutenberg"
PLUGIN_DIR="."
MAIN_FILE="url-to-gutenberg.php"
REMOTE_USER="staging"
REMOTE_HOST="45.33.31.79"
REMOTE_PATH="/home/staging/public_html"
REMOTE_WP_PATH="/home/staging/public_html"

# Check if a command was provided
COMMAND=${1:-"package"}

# Files and directories to include in the package
INCLUDE_FILES=(
    "url-to-gutenberg.php"
    "README.md"
    "CHANGELOG.md"
    "includes"
    "assets"
    "languages"
    "uninstall.php"
)

# Files and directories to exclude
EXCLUDE_FILES=(
    ".git"
    ".vscode"
    ".gitignore"
    ".cursorignore"
    ".cursorrules"
    "deploy.sh"
    "deploy-new.sh"
    "*.DS_Store"
    "*.zip"
    "node_modules"
    "*.log"
    "spec.md"
    "*.tmp"
    "tests"
    "progress.md"
)

# Function to get current version
get_current_version() {
    # Try to extract version from the main plugin file
    if [ -f "$MAIN_FILE" ]; then
        VERSION=$(grep -i "Version:" "$MAIN_FILE" | head -n 1 | awk -F': ' '{print $2}' | tr -d ' \t\r\n')
        
        # Verify that we got a valid version format (x.y.z)
        if [[ $VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            echo "$VERSION"
            return
        fi
        
        # Try to extract from the define statement as a fallback
        VERSION=$(grep -i "define.*UTG_VERSION" "$MAIN_FILE" | head -n 1 | grep -o "'[0-9]\+\.[0-9]\+\.[0-9]\+'" | tr -d "'")
        if [[ $VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            echo "$VERSION"
            return
        fi
    fi
    
    # Default fallback version if we couldn't extract one
    echo "1.0.0"
}

# Function to increment version
increment_version() {
    local version=$1
    IFS='.' read -r -a version_parts <<< "$version"
    
    local major="${version_parts[0]}"
    local minor="${version_parts[1]}"
    local patch="${version_parts[2]}"
    
    # Increment patch version
    patch=$((patch + 1))
    
    echo "${major}.${minor}.${patch}"
}

# Function to update version in files
update_version() {
    local new_version=$1
    local current_date=$(date +"%Y-%m-%d")
    
    # Update main plugin file
    sed -i '' "s/Version: .*/Version: ${new_version}/" "$MAIN_FILE" || exit 1
    sed -i '' "s/define('UTG_VERSION', '.*');/define('UTG_VERSION', '${new_version}');/" "$MAIN_FILE" || exit 1
    
    # Update readme.txt if it exists
    if [ -f "readme.txt" ]; then
        sed -i '' "s/Stable tag: .*/Stable tag: ${new_version}/" "readme.txt" || exit 1
    fi
    
    echo "Updated version to $new_version"
}

# Function to create changelog if it doesn't exist
ensure_changelog_exists() {
    if [ ! -f "CHANGELOG.md" ]; then
        touch "CHANGELOG.md"
        echo "# Changelog" > "CHANGELOG.md"
        echo "" >> "CHANGELOG.md"
        echo "Created CHANGELOG.md file"
    fi
}

# Function to update changelog
update_changelog() {
    local new_version=$1
    local current_date=$(date +"%Y-%m-%d")
    local temp_file="CHANGELOG.tmp"
    
    ensure_changelog_exists
    
    # Create new version entry
    {
        echo "## ${new_version} - ${current_date}"
        echo "### Added"
        echo "- Improved architecture with dependency injection"
        echo "- Added caching mechanism for API responses"
        echo "- Enhanced error handling and logging"
        echo "- Added extensibility hooks throughout the codebase"
        echo "- Added new settings for better control"
        echo ""
        cat "CHANGELOG.md"
    } > "$temp_file"
    
    mv "$temp_file" "CHANGELOG.md"
    echo "Updated CHANGELOG.md with new version $new_version"
}

# Function to validate menu slug consistency
validate_menu_slug() {
    echo "Validating menu slug consistency..."
    
    # Check for inconsistent menu slug usage (e.g., "utg" vs "url-to-gutenberg")
    if grep -r "add_menu_page.*\"utg\"" --include="*.php" . > /dev/null; then
        echo "WARNING: Found potentially inconsistent menu slug usage in add_menu_page calls."
        echo "Menu slug should be \"url-to-gutenberg\" but found \"utg\"."
        
        # Fix the issue automatically
        find . -name "*.php" -exec sed -i '' 's/add_menu_page([^,]*,[^,]*,[^,]*,"utg"/add_menu_page(\1,\2,\3,"url-to-gutenberg"/g' {} \;
        echo "Fixed menu slug inconsistencies in add_menu_page calls."
    fi
    
    # Check admin file specifically (this is where we had the issue before)
    ADMIN_FILE="includes/admin/class-admin.php"
    if [ -f "$ADMIN_FILE" ]; then
        if grep -q "menu_slug.*=>.*\"utg\"" "$ADMIN_FILE"; then
            echo "WARNING: Found 'utg' menu slug in $ADMIN_FILE"
            sed -i '' 's/menu_slug.*=>.*"utg"/menu_slug => "url-to-gutenberg"/g' "$ADMIN_FILE"
            echo "Fixed menu slug in $ADMIN_FILE"
        fi
    fi
    
    echo "Menu slug validation complete."
}

# Function to create zip package
create_package() {
    local version=$1
    local package_name="${PLUGIN_SLUG}-${version}.zip"
    
    echo "Creating package ${package_name}..."
    
    # Create a clean copy of the plugin
    rm -rf "/tmp/${PLUGIN_SLUG}"
    mkdir -p "/tmp/${PLUGIN_SLUG}"
    
    # Copy included files
    for file in "${INCLUDE_FILES[@]}"; do
        if [ -e "${file}" ]; then
            cp -r "${file}" "/tmp/${PLUGIN_SLUG}/" || exit 1
        else
            echo "Warning: Included file/directory not found: ${file}"
        fi
    done
    
    # Create zip package
    (cd "/tmp" && zip -r "$package_name" "${PLUGIN_SLUG}" -x "${EXCLUDE_FILES[@]/#/*/}") || exit 1
    mv "/tmp/$package_name" "./$package_name" || exit 1
    
    echo "Package created: $package_name"
    echo "$package_name"
}

# Function to deploy using rsync
deploy_to_staging() {
    echo "Deploying to staging server..."
    
    # First, validate menu slug consistency
    validate_menu_slug
    
    # Create exclude patterns for rsync
    excludes=""
    for pattern in "${EXCLUDE_FILES[@]}"; do
        excludes+=" --exclude='$pattern'"
    done
    
    # Use rsync to transfer files directly to the server
    rsync -avzi --delete $excludes ./ "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/wp-content/plugins/${PLUGIN_SLUG}/" || {
        echo "Error: Failed to rsync files to staging server"
        exit 1
    }
    
    # Activate the plugin remotely
    ssh "${REMOTE_USER}@${REMOTE_HOST}" "cd ${REMOTE_WP_PATH} && wp plugin activate ${PLUGIN_SLUG}" || {
        echo "Warning: Failed to activate plugin remotely. It may need to be activated manually."
    }
    
    echo "Deployment to staging server complete!"
}

# Main execution based on command
case "$COMMAND" in
    "package")
        current_version=$(get_current_version)
        new_version=$(increment_version "$current_version")
        
        echo "Preparing package version $new_version..."
        
        # Update version numbers and changelog
        update_version "$new_version"
        update_changelog "$new_version"
        
        # Validate menu slug consistency
        validate_menu_slug
        
        # Create package
        create_package "$new_version"
        
        echo "Package creation complete! Version: $new_version"
        ;;
    
    "deploy")
        echo "Deploying to staging server..."
        deploy_to_staging
        echo "Deployment complete!"
        ;;
    
    "full")
        current_version=$(get_current_version)
        new_version=$(increment_version "$current_version")
        
        echo "Preparing full package and deployment for version $new_version..."
        
        # Update version numbers and changelog
        update_version "$new_version"
        update_changelog "$new_version"
        
        # Validate menu slug consistency
        validate_menu_slug
        
        # Create package
        package_name=$(create_package "$new_version")
        
        # Deploy
        deploy_to_staging
        
        echo "Package creation and deployment complete! Version: $new_version"
        ;;
    
    *)
        echo "Unknown command: $COMMAND"
        echo "Available commands: package, deploy, full"
        exit 1
        ;;
esac