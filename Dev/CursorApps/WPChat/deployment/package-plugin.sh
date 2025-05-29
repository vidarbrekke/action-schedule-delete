#!/bin/bash

# --- Configuration ---
PLUGIN_SLUG="wp-customer-ai-chatbot"
SOURCE_DIR="../"
BUILD_DIR="../build/${PLUGIN_SLUG}/"
OUTPUT_DIR="../dist/"

# --- Extract Version from Main Plugin File ---
PLUGIN_FILE="${SOURCE_DIR}${PLUGIN_SLUG}.php"
VERSION=$(grep -E '^\s*\*\s*Version:\s*' "$PLUGIN_FILE" | head -1 | sed -E 's/.*Version:\s*([0-9.]+).*/\1/')
if [ -z "$VERSION" ]; then
  echo "❌ Could not extract version from $PLUGIN_FILE. Aborting packaging."
  exit 1
fi
ZIP_FILE="${OUTPUT_DIR}${PLUGIN_SLUG}-v${VERSION}.zip"

# --- Changelog and README Automation ---
CHANGELOG="${SOURCE_DIR}CHANGELOG.md"
README="${SOURCE_DIR}readme.txt"
TODAY=$(date +%Y-%m-%d)

# 1. Parse last released version from CHANGELOG.md
LAST_VERSION=$(grep -E '^## \[[0-9]+\.[0-9]+\.[0-9]+\]' "$CHANGELOG" | head -1 | sed -E 's/.*\[([0-9.]+)\].*/\1/')

# 2. Only update if this version is not already in the changelog
if ! grep -q "^## \[${VERSION}\]" "$CHANGELOG"; then
  echo "📝 Generating changelog for version $VERSION (since $LAST_VERSION)..."
  # 3. Get all commit messages since the last version
  if git rev-parse "$LAST_VERSION" >/dev/null 2>&1; then
    GIT_RANGE="$LAST_VERSION..HEAD"
  else
    GIT_RANGE="HEAD"
  fi
  COMMITS=$(git log --pretty=format:'%s' --no-merges $GIT_RANGE)

  # 4. Group commits by type
  FEAT=""
  FIX=""
  DOCS=""
  STYLE=""
  REFACTOR=""
  TEST=""
  CHORE=""
  OTHER=""
  while IFS= read -r line; do
    case "$line" in
      feat:*) FEAT+="  - ${line#feat: }\n" ;;
      fix:*) FIX+="  - ${line#fix: }\n" ;;
      docs:*) DOCS+="  - ${line#docs: }\n" ;;
      style:*) STYLE+="  - ${line#style: }\n" ;;
      refactor:*) REFACTOR+="  - ${line#refactor: }\n" ;;
      test:*) TEST+="  - ${line#test: }\n" ;;
      chore:*) CHORE+="  - ${line#chore: }\n" ;;
      *) OTHER+="  - $line\n" ;;
    esac
  done <<< "$COMMITS"

  # 5. Build new changelog section
  NEW_CHANGELOG="## [${VERSION}] - ${TODAY}\n"
  [ -n "$FEAT" ] && NEW_CHANGELOG+="### Added\n$FEAT"
  [ -n "$FIX" ] && NEW_CHANGELOG+="### Fixed\n$FIX"
  [ -n "$DOCS" ] && NEW_CHANGELOG+="### Documentation\n$DOCS"
  [ -n "$STYLE" ] && NEW_CHANGELOG+="### Style\n$STYLE"
  [ -n "$REFACTOR" ] && NEW_CHANGELOG+="### Refactored\n$REFACTOR"
  [ -n "$TEST" ] && NEW_CHANGELOG+="### Tests\n$TEST"
  [ -n "$CHORE" ] && NEW_CHANGELOG+="### Chore\n$CHORE"
  [ -n "$OTHER" ] && NEW_CHANGELOG+="### Other\n$OTHER"
  NEW_CHANGELOG+="\n"

  # 6. Prepend to CHANGELOG.md
  TMP_CHANGELOG=$(mktemp)
  { echo -e "$NEW_CHANGELOG"; cat "$CHANGELOG"; } > "$TMP_CHANGELOG"
  mv "$TMP_CHANGELOG" "$CHANGELOG"
  echo "✅ CHANGELOG.md updated with version $VERSION."
else
  echo "ℹ️  Version $VERSION already present in CHANGELOG.md, skipping changelog update."
fi

# 7. Update Stable tag in readme.txt
if grep -q '^Stable tag:' "$README"; then
  sed -i '' -E "s/^(Stable tag:).*/\\1 $VERSION/" "$README"
  echo "✅ Updated Stable tag in readme.txt to $VERSION."
fi

# 8. Tag the release in git if not already tagged
if git rev-parse "v$VERSION" >/dev/null 2>&1; then
  echo "ℹ️  Git tag v$VERSION already exists, skipping tagging."
else
  git add "$CHANGELOG" "$README"
  git commit -m "chore(release): prepare v$VERSION release"
  git tag -a "v$VERSION" -m "Release v$VERSION"
  echo "🏷️  Created git tag v$VERSION."
  git push
  git push origin "v$VERSION"
  echo "🚀 Pushed release commit and tag v$VERSION to remote."
fi

# Exit immediately if a command exits with a non-zero status.
set -e

echo "📦 Starting packaging process for ${PLUGIN_SLUG} (version ${VERSION})..."

# --- Cleanup Previous Build ---
echo "🧹 Cleaning up previous build and distribution files..."
rm -rf ../build
rm -rf $OUTPUT_DIR
mkdir -p $BUILD_DIR
mkdir -p $OUTPUT_DIR

# --- Copy Source Files ---
echo "📄 Copying plugin source files to build directory..."
# Use rsync to easily exclude files/dirs if needed later, for now copy all
# Add --exclude='.git' just in case it's copied from root
rsync -a --exclude='.git' "$SOURCE_DIR" "$BUILD_DIR"

# --- Install Composer Dependencies (Production) ---
echo "📦 Installing composer dependencies (production only)..."
# Check if composer.json exists before trying to install
if [ -f "${BUILD_DIR}composer.json" ]; then
    # Navigate to the build directory to run composer
    (cd "$BUILD_DIR" && composer install --no-dev --optimize-autoloader)
    echo "✅ Composer dependencies installed and optimized for production."
else
    echo "  -> composer.json not found, skipping composer install."
fi

# --- Remove Development Files ---
echo "🗑️ Removing development files from build directory..."
rm -f ${BUILD_DIR}.gitignore
rm -f ${BUILD_DIR}.gitattributes
rm -f ${BUILD_DIR}composer.json
rm -f ${BUILD_DIR}composer.lock
rm -f ${BUILD_DIR}phpunit.xml
rm -f ${BUILD_DIR}phpcs.xml
rm -f ${BUILD_DIR}*.md # Remove markdown files
rm -rf ${BUILD_DIR}.git
rm -rf ${BUILD_DIR}tests/
rm -rf ${BUILD_DIR}bin/
rm -rf ${BUILD_DIR}dev-tools/ # Ensure any dev-tools directory is removed
# Add any other dev-specific files/dirs here
# Dev tools are now outside the plugin directory

# --- Create ZIP Archive ---
echo "🤐 Creating ZIP archive..."
(cd ../build && zip -r "../${ZIP_FILE}" ./*)

# --- Cleanup Build Directory ---
echo "🧹 Cleaning up build directory..."
rm -rf ../build

echo "✅ Packaging complete! Distribution file created at: ${ZIP_FILE}" 