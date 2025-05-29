#!/bin/bash

# TuneTussle Dependency Check Script
# Comprehensive dependency validation for safe updates

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
REPORT_DIR="dependency-reports"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

echo -e "${BLUE}🔍 TuneTussle Dependency Check - $(date)${NC}"
echo "=================================================="

# Create reports directory
mkdir -p "$REPORT_DIR"

# Function to check if directory exists
check_directory() {
    local dir=$1
    if [ ! -d "$dir" ]; then
        echo -e "${RED}❌ Directory $dir not found${NC}"
        exit 1
    fi
}

# Function to run security audit
run_security_audit() {
    local dir=$1
    local name=$2
    
    echo -e "\n${BLUE}🔒 Security Audit: $name${NC}"
    cd "$dir"
    
    # Check for vulnerabilities
    if npm audit --audit-level=moderate > "../$REPORT_DIR/${name}_security_${TIMESTAMP}.txt" 2>&1; then
        echo -e "${GREEN}✅ No moderate+ security vulnerabilities found${NC}"
    else
        echo -e "${YELLOW}⚠️  Security vulnerabilities detected - check report${NC}"
        echo "Report: $REPORT_DIR/${name}_security_${TIMESTAMP}.txt"
        
        # Show critical/high vulnerabilities
        echo -e "\n${RED}High/Critical Vulnerabilities:${NC}"
        npm audit --audit-level=high || true
    fi
    
    cd ..
}

# Function to check outdated packages
check_outdated() {
    local dir=$1
    local name=$2
    
    echo -e "\n${BLUE}📦 Outdated Packages: $name${NC}"
    cd "$dir"
    
    # Generate outdated report
    if npm outdated --long > "../$REPORT_DIR/${name}_outdated_${TIMESTAMP}.txt" 2>&1; then
        echo -e "${GREEN}✅ All packages up to date${NC}"
    else
        echo -e "${YELLOW}📊 Outdated packages found - generating report${NC}"
        echo "Report: $REPORT_DIR/${name}_outdated_${TIMESTAMP}.txt"
        
        # Show summary
        echo -e "\n${YELLOW}Summary of outdated packages:${NC}"
        npm outdated || true
    fi
    
    cd ..
}

# Function to validate package lock files
validate_lockfiles() {
    local dir=$1
    local name=$2
    
    echo -e "\n${BLUE}🔒 Package Lock Validation: $name${NC}"
    cd "$dir"
    
    if [ -f "package-lock.json" ]; then
        # Check if package-lock.json is in sync
        if npm ci --dry-run > /dev/null 2>&1; then
            echo -e "${GREEN}✅ package-lock.json is valid and in sync${NC}"
        else
            echo -e "${RED}❌ package-lock.json issues detected${NC}"
            echo "Run 'npm install' to fix package-lock.json"
        fi
    else
        echo -e "${YELLOW}⚠️  No package-lock.json found${NC}"
    fi
    
    cd ..
}

# Function to check dependency licenses
check_licenses() {
    local dir=$1
    local name=$2
    
    echo -e "\n${BLUE}📄 License Check: $name${NC}"
    cd "$dir"
    
    # Generate license report
    npx license-checker --summary > "../$REPORT_DIR/${name}_licenses_${TIMESTAMP}.txt" 2>/dev/null || {
        echo -e "${YELLOW}⚠️  license-checker not available (run 'npm install -g license-checker')${NC}"
    }
    
    cd ..
}

# Function to run tests
run_tests() {
    local dir=$1
    local name=$2
    
    echo -e "\n${BLUE}🧪 Test Validation: $name${NC}"
    cd "$dir"
    
    if npm test > "../$REPORT_DIR/${name}_tests_${TIMESTAMP}.txt" 2>&1; then
        echo -e "${GREEN}✅ All tests passing${NC}"
    else
        echo -e "${RED}❌ Test failures detected${NC}"
        echo "Report: $REPORT_DIR/${name}_tests_${TIMESTAMP}.txt"
        
        # Show test summary
        echo -e "\n${RED}Test Results:${NC}"
        tail -20 "../$REPORT_DIR/${name}_tests_${TIMESTAMP}.txt"
    fi
    
    cd ..
}

# Function to check for critical dependency issues
check_critical_issues() {
    echo -e "\n${BLUE}🚨 Critical Dependency Analysis${NC}"
    
    # Check for known problematic packages
    echo "Checking for known problematic packages..."
    
    problematic_packages=(
        "event-stream"
        "eslint-scope"
        "flatmap-stream"
        "getcookies"
    )
    
    for pkg in "${problematic_packages[@]}"; do
        if grep -r "\"$pkg\"" */package*.json > /dev/null 2>&1; then
            echo -e "${RED}⚠️  Found potentially problematic package: $pkg${NC}"
        fi
    done
    
    # Check for packages with many dependencies (potential bloat)
    echo -e "\nChecking for dependency bloat..."
    
    cd "$BACKEND_DIR"
    heavy_deps=$(npm list --depth=0 2>/dev/null | grep -E "├──|└──" | wc -l)
    echo "Backend dependencies: $heavy_deps"
    cd ..
    
    cd "$FRONTEND_DIR"
    heavy_deps=$(npm list --depth=0 2>/dev/null | grep -E "├──|└──" | wc -l)
    echo "Frontend dependencies: $heavy_deps"
    cd ..
}

# Function to generate update recommendations
generate_recommendations() {
    echo -e "\n${BLUE}💡 Update Recommendations${NC}"
    
    cat > "$REPORT_DIR/recommendations_${TIMESTAMP}.md" << EOF
# Dependency Update Recommendations - $(date)

## Summary
- Security audit completed
- Outdated packages identified
- Test validation performed

## Safe to Update (Automated)
- Patch versions (x.y.Z)
- Security fixes
- Development dependencies

## Requires Review
- Minor versions (x.Y.z)
- Major versions (X.y.z)
- Critical dependencies (Express, React, Socket.IO)

## Action Items
1. Review security report
2. Apply patch updates: \`npm update\`
3. Test thoroughly: \`npm test\`
4. Review major version changes manually

## Commands
\`\`\`bash
# Apply safe updates
cd backend && npm update
cd frontend && npm update

# Fix security issues
cd backend && npm audit fix
cd frontend && npm audit fix

# Validate changes
cd backend && npm test
cd frontend && npm test
\`\`\`
EOF

    echo "Recommendations saved to: $REPORT_DIR/recommendations_${TIMESTAMP}.md"
}

# Main execution
main() {
    echo -e "\n${BLUE}Starting comprehensive dependency check...${NC}"
    
    # Verify directories exist
    check_directory "$BACKEND_DIR"
    check_directory "$FRONTEND_DIR"
    
    # Backend checks
    echo -e "\n${BLUE}=== BACKEND ANALYSIS ===${NC}"
    run_security_audit "$BACKEND_DIR" "backend"
    check_outdated "$BACKEND_DIR" "backend"
    validate_lockfiles "$BACKEND_DIR" "backend"
    check_licenses "$BACKEND_DIR" "backend"
    run_tests "$BACKEND_DIR" "backend"
    
    # Frontend checks
    echo -e "\n${BLUE}=== FRONTEND ANALYSIS ===${NC}"
    run_security_audit "$FRONTEND_DIR" "frontend"
    check_outdated "$FRONTEND_DIR" "frontend"
    validate_lockfiles "$FRONTEND_DIR" "frontend"
    check_licenses "$FRONTEND_DIR" "frontend"
    run_tests "$FRONTEND_DIR" "frontend"
    
    # Critical analysis
    check_critical_issues
    
    # Generate recommendations
    generate_recommendations
    
    echo -e "\n${GREEN}🎉 Dependency check complete!${NC}"
    echo "Reports saved in: $REPORT_DIR/"
    echo ""
    echo -e "${BLUE}Quick Actions:${NC}"
    echo "  Safe updates: ./scripts/safe-update.sh"
    echo "  Security fixes: npm audit fix (in each directory)"
    echo "  Full report: cat $REPORT_DIR/recommendations_${TIMESTAMP}.md"
}

# Run main function
main "$@" 