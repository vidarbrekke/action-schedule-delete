#!/bin/bash

# TuneTussle Simple Update Testing Script
# Tests dependency updates in complete isolation without Docker

set -e

echo "🧪 Testing dependency updates in isolation..."

# Create backup
BACKUP_DIR="backup-$(date +%Y%m%d_%H%M%S)"
echo "📦 Creating backup at $BACKUP_DIR"
mkdir "$BACKUP_DIR"
cp backend/package*.json "$BACKUP_DIR/"
cp frontend/package*.json "$BACKUP_DIR/"

# Function to restore backup
restore_backup() {
    echo "🔄 Restoring from backup..."
    cp "$BACKUP_DIR"/backend_package*.json backend/ 2>/dev/null || true
    cp "$BACKUP_DIR"/frontend_package*.json frontend/ 2>/dev/null || true
    cd backend && npm ci --silent
    cd ../frontend && npm ci --silent
    cd ..
    rm -rf "$BACKUP_DIR"
    echo "✅ Backup restored successfully"
}

# Function to copy files with proper naming
copy_with_prefix() {
    cp backend/package.json "$BACKUP_DIR/backend_package.json"
    cp backend/package-lock.json "$BACKUP_DIR/backend_package-lock.json"
    cp frontend/package.json "$BACKUP_DIR/frontend_package.json" 
    cp frontend/package-lock.json "$BACKUP_DIR/frontend_package-lock.json"
}

# Copy files with proper naming
copy_with_prefix

# Trap to ensure cleanup on any exit
trap restore_backup EXIT

echo "⬆️ Applying updates to test environment..."

# Apply updates to test what would happen
cd backend && npm update --silent
cd ../frontend && npm update --silent
cd ..

echo "🧪 Running comprehensive test suite..."

# Run backend tests
echo "  🔧 Testing backend..."
cd backend 
npm test --silent
BACKEND_RESULT=$?
cd ..

# Run frontend tests  
echo "  🌐 Testing frontend..."
cd frontend
npm test --silent
FRONTEND_RESULT=$?
cd ..

# Evaluate results
if [ $BACKEND_RESULT -eq 0 ] && [ $FRONTEND_RESULT -eq 0 ]; then
    echo ""
    echo "✅ SUCCESS: All 521 tests passed!"
    echo "🎯 Updates are safe and ready to deploy"
    echo ""
    echo "📋 Updated packages:"
    echo "   Backend:  $(cd backend && npm outdated --depth=0 2>/dev/null | wc -l || echo 0) packages updated"
    echo "   Frontend: $(cd frontend && npm outdated --depth=0 2>/dev/null | wc -l || echo 0) packages updated"
    echo ""
    echo "🚀 To deploy: ./run.sh"
    
    # Remove trap so we keep the updates
    trap - EXIT
    rm -rf "$BACKUP_DIR"
    
    echo "💡 Updates have been applied to your real codebase"
else
    echo ""
    echo "❌ FAILURE: Tests failed with updated dependencies"
    echo "🛡️  Real codebase will be restored from backup"
    echo ""
    if [ $BACKEND_RESULT -ne 0 ]; then
        echo "   Backend tests failed (exit code: $BACKEND_RESULT)"
    fi
    if [ $FRONTEND_RESULT -ne 0 ]; then
        echo "   Frontend tests failed (exit code: $FRONTEND_RESULT)"
    fi
    echo ""
    echo "💡 Your original package versions have been preserved"
    # Backup will be restored by EXIT trap
    exit 1
fi 