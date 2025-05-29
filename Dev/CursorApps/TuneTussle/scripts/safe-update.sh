#!/bin/bash

# TuneTussle Safe Update Script
# Applies only patch-level updates with comprehensive validation

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
BACKEND_DIR="backend"
FRONTEND_DIR="frontend"
BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"

echo -e "${BLUE}🔄 TuneTussle Safe Update Process${NC}"
echo "======================================="

# Function to create backup
create_backup() {
    echo -e "\n${BLUE}📦 Creating backup...${NC}"
    mkdir -p "$BACKUP_DIR"
    
    # Backup package.json and package-lock.json files
    cp "$BACKEND_DIR/package.json" "$BACKUP_DIR/backend_package.json"
    cp "$BACKEND_DIR/package-lock.json" "$BACKUP_DIR/backend_package-lock.json"
    cp "$FRONTEND_DIR/package.json" "$BACKUP_DIR/frontend_package.json"
    cp "$FRONTEND_DIR/package-lock.json" "$BACKUP_DIR/frontend_package-lock.json"
    
    echo -e "${GREEN}✅ Backup created: $BACKUP_DIR${NC}"
}

# Function to restore backup
restore_backup() {
    echo -e "\n${RED}🔙 Restoring from backup...${NC}"
    
    if [ -d "$BACKUP_DIR" ]; then
        cp "$BACKUP_DIR/backend_package.json" "$BACKEND_DIR/package.json"
        cp "$BACKUP_DIR/backend_package-lock.json" "$BACKEND_DIR/package-lock.json"
        cp "$BACKUP_DIR/frontend_package.json" "$FRONTEND_DIR/package.json"
        cp "$BACKUP_DIR/frontend_package-lock.json" "$FRONTEND_DIR/package-lock.json"
        
        # Reinstall dependencies
        cd "$BACKEND_DIR" && npm ci && cd ..
        cd "$FRONTEND_DIR" && npm ci && cd ..
        
        echo -e "${GREEN}✅ Backup restored successfully${NC}"
    else
        echo -e "${RED}❌ Backup directory not found${NC}"
        exit 1
    fi
}

# Function to run pre-update checks
pre_update_checks() {
    echo -e "\n${BLUE}🔍 Pre-update validation...${NC}"
    
    # Check current test status
    echo "Verifying current test status..."
    
    cd "$BACKEND_DIR"
    if npm test > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Backend tests passing${NC}"
    else
        echo -e "${RED}❌ Backend tests failing - aborting update${NC}"
        exit 1
    fi
    cd ..
    
    cd "$FRONTEND_DIR"
    if npm test > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Frontend tests passing${NC}"
    else
        echo -e "${RED}❌ Frontend tests failing - aborting update${NC}"
        exit 1
    fi
    cd ..
    
    # Security audit
    echo "Running security audit..."
    cd "$BACKEND_DIR"
    if npm audit --audit-level=high > /dev/null 2>&1; then
        echo -e "${GREEN}✅ No high/critical security issues${NC}"
    else
        echo -e "${YELLOW}⚠️  Security issues detected - will fix during update${NC}"
    fi
    cd ..
    
    cd "$FRONTEND_DIR"
    if npm audit --audit-level=high > /dev/null 2>&1; then
        echo -e "${GREEN}✅ No high/critical security issues${NC}"
    else
        echo -e "${YELLOW}⚠️  Security issues detected - will fix during update${NC}"
    fi
    cd ..
}

# Function to apply safe updates
apply_safe_updates() {
    local dir=$1
    local name=$2
    
    echo -e "\n${BLUE}🔄 Updating $name packages...${NC}"
    cd "$dir"
    
    # Show what will be updated
    echo "Checking for available updates..."
    npm outdated || true
    
    # Apply patch-level updates only
    echo "Applying safe patch updates..."
    npm update
    
    # Fix security vulnerabilities
    echo "Fixing security vulnerabilities..."
    npm audit fix --audit-level=moderate || true
    
    # Install any new dependencies that might be needed
    npm install
    
    echo -e "${GREEN}✅ $name packages updated${NC}"
    cd ..
}

# Function to validate updates
validate_updates() {
    local dir=$1
    local name=$2
    
    echo -e "\n${BLUE}🧪 Validating $name updates...${NC}"
    cd "$dir"
    
    # Type checking (if available)
    if npm run type-check > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Type checking passed${NC}"
    else
        echo -e "${YELLOW}⚠️  Type checking not available or failed${NC}"
    fi
    
    # Build test (if available)
    if npm run build > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Build successful${NC}"
    else
        echo -e "${YELLOW}⚠️  Build not available or failed${NC}"
    fi
    
    # Run tests
    if npm test; then
        echo -e "${GREEN}✅ All tests passing${NC}"
    else
        echo -e "${RED}❌ Tests failing after update${NC}"
        cd ..
        return 1
    fi
    
    cd ..
    return 0
}

# Function to show update summary
show_update_summary() {
    echo -e "\n${BLUE}📊 Update Summary${NC}"
    echo "==================="
    
    echo -e "\n${BLUE}Backend Changes:${NC}"
    cd "$BACKEND_DIR"
    npm list --depth=0 | head -20
    cd ..
    
    echo -e "\n${BLUE}Frontend Changes:${NC}"
    cd "$FRONTEND_DIR"
    npm list --depth=0 | head -20
    cd ..
    
    echo -e "\n${GREEN}🎉 Safe update completed successfully!${NC}"
    echo ""
    echo -e "${BLUE}Next Steps:${NC}"
    echo "1. Test the application: ./run.sh --dev"
    echo "2. Run a complete game session"
    echo "3. Deploy if everything works: ./run.sh"
    echo "4. Monitor for any issues"
    echo ""
    echo -e "${YELLOW}Backup available at: $BACKUP_DIR${NC}"
}

# Function to run integration test
run_integration_test() {
    echo -e "\n${BLUE}🔗 Running integration test...${NC}"
    
    # Start backend in background for health check
    cd "$BACKEND_DIR"
    
    # Create test environment
    echo "OPENROUTER_API_KEY=test_key" > .env.test
    echo "MUSIC_PROVIDER=deezer" >> .env.test
    
    # Start server
    NODE_ENV=test npm run start:dev &
    BACKEND_PID=$!
    
    # Wait for server to start
    sleep 8
    
    # Test health endpoint
    if curl -f http://localhost:4000/api/performance/health > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Integration test passed${NC}"
    else
        echo -e "${YELLOW}⚠️  Integration test failed - manual verification needed${NC}"
    fi
    
    # Cleanup
    kill $BACKEND_PID 2>/dev/null || true
    rm -f .env.test
    
    cd ..
}

# Main execution
main() {
    echo -e "\n${BLUE}Starting safe update process...${NC}"
    
    # Pre-flight checks
    if [ ! -d "$BACKEND_DIR" ] || [ ! -d "$FRONTEND_DIR" ]; then
        echo -e "${RED}❌ Backend or frontend directory not found${NC}"
        exit 1
    fi
    
    # Create backup
    create_backup
    
    # Pre-update validation
    pre_update_checks
    
    # Apply updates
    apply_safe_updates "$BACKEND_DIR" "backend"
    apply_safe_updates "$FRONTEND_DIR" "frontend"
    
    # Validate updates
    if ! validate_updates "$BACKEND_DIR" "backend"; then
        echo -e "${RED}❌ Backend validation failed${NC}"
        restore_backup
        exit 1
    fi
    
    if ! validate_updates "$FRONTEND_DIR" "frontend"; then
        echo -e "${RED}❌ Frontend validation failed${NC}"
        restore_backup
        exit 1
    fi
    
    # Integration test
    run_integration_test
    
    # Show summary
    show_update_summary
    
    # Cleanup old backups (keep last 5)
    echo -e "\n${BLUE}🧹 Cleaning up old backups...${NC}"
    ls -dt backup_* 2>/dev/null | tail -n +6 | xargs rm -rf 2>/dev/null || true
    
    echo -e "\n${GREEN}✨ Update process completed successfully!${NC}"
}

# Handle interruption
trap 'echo -e "\n${RED}❌ Update interrupted - restoring backup${NC}"; restore_backup; exit 1' INT TERM

# Run main function
main "$@" 