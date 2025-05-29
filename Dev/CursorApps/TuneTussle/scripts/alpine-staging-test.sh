#!/bin/bash

# TuneTussle Alpine Linux Staging Script
# Memory-optimized staging for Alpine Linux (1GB RAM)

set -e

echo "🏔️ Alpine Linux staging environment (memory optimized)"

# Check available memory
AVAILABLE_MEM=$(free -m | awk 'NR==2{printf "%d", $7}')
echo "💾 Available memory: ${AVAILABLE_MEM}MB"

if [ "$AVAILABLE_MEM" -lt 300 ]; then
    echo "⚠️  Low memory detected. Using ultra-lightweight mode."
    LIGHTWEIGHT_MODE=true
else
    LIGHTWEIGHT_MODE=false
fi

# Create minimal backup (memory efficient)
BACKUP_DIR="backup-$(date +%Y%m%d_%H%M%S)"
echo "📦 Creating lightweight backup..."
mkdir "$BACKUP_DIR"

# Only backup essential files (not node_modules)
cp backend/package.json "$BACKUP_DIR/backend_package.json"
cp backend/package-lock.json "$BACKUP_DIR/backend_package-lock.json"
cp frontend/package.json "$BACKUP_DIR/frontend_package.json"
cp frontend/package-lock.json "$BACKUP_DIR/frontend_package-lock.json"

# Memory-optimized restore function
restore_backup() {
    echo "🔄 Restoring from lightweight backup..."
    cp "$BACKUP_DIR/backend_package.json" backend/package.json 2>/dev/null || true
    cp "$BACKUP_DIR/backend_package-lock.json" backend/package-lock.json 2>/dev/null || true
    cp "$BACKUP_DIR/frontend_package.json" frontend/package.json 2>/dev/null || true
    cp "$BACKUP_DIR/frontend_package-lock.json" frontend/package-lock.json 2>/dev/null || true
    
    # Use npm ci with cache for faster, memory-efficient install
    cd backend && npm ci --prefer-offline --no-audit --silent
    cd ../frontend && npm ci --prefer-offline --no-audit --silent
    cd ..
    rm -rf "$BACKUP_DIR"
}

trap restore_backup EXIT

# Apply updates with memory optimization
echo "⬆️ Applying updates (memory optimized)..."
cd backend && npm update --no-audit --prefer-offline --silent
cd ../frontend && npm update --no-audit --prefer-offline --silent
cd ..

# Run tests with memory limits if needed
if [ "$LIGHTWEIGHT_MODE" = true ]; then
    echo "🧪 Running tests (lightweight mode)..."
    # Limit Node.js memory usage for low-memory systems
    export NODE_OPTIONS="--max-old-space-size=256"
else
    echo "🧪 Running tests (normal mode)..."
fi

# Run tests sequentially to save memory
echo "  🔧 Testing backend..."
cd backend && npm test --silent
BACKEND_RESULT=$?

echo "  🌐 Testing frontend..."
cd ../frontend && npm test --silent
FRONTEND_RESULT=$?
cd ..

# Show memory usage during tests
echo "💾 Current memory usage:"
free -h | head -2

# Evaluate results
if [ $BACKEND_RESULT -eq 0 ] && [ $FRONTEND_RESULT -eq 0 ]; then
    echo ""
    echo "✅ SUCCESS: All tests passed on Alpine!"
    echo "🚀 Safe to deploy on production Alpine server"
    echo "💾 Memory efficiency maintained throughout testing"
    
    # Show final memory state
    echo ""
    echo "📊 Final memory usage:"
    free -h | head -2
    
    trap - EXIT
    rm -rf "$BACKUP_DIR"
    
    echo "💡 Updates applied and ready for Alpine production"
else
    echo ""
    echo "❌ FAILURE: Tests failed - backup will be restored"
    echo "🛡️ Alpine system memory preserved"
    exit 1
fi 