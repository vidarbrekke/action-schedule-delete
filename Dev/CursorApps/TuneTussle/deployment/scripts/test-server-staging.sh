#!/bin/bash

# TuneTussle Server Staging Environment Testing
# Tests package upgrades in the server-side staging environment

set -e

SERVER_HOST="69.164.209.52"
SERVER_PORT="2222"
SERVER_USER="admin"
SSH_OPTS="-o StrictHostKeyChecking=no -o ConnectTimeout=30"

echo "🧪 Testing server-side staging environment for package upgrades..."

# Function to run commands on server and capture output
run_on_server() {
    local cmd="$1"
    ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" "$cmd"
}

# Function to check if staging environment exists
check_staging_exists() {
    echo "🔍 Checking if staging environment exists..."
    
    if run_on_server "test -f /usr/local/bin/staging-manager.sh"; then
        echo "✅ Staging environment is set up"
        return 0
    else
        echo "❌ Staging environment not found"
        echo "🔧 Run './deployment/scripts/setup-server-staging.sh' first"
        return 1
    fi
}

# Function to test staging setup
test_staging_setup() {
    echo "🏗️ Testing staging environment setup..."
    
    # Set up staging from production
    echo "  📋 Setting up staging from production..."
    run_on_server "sudo staging-manager.sh setup"
    
    # Check staging status
    echo "  📊 Checking staging status..."
    run_on_server "sudo staging-manager.sh status"
}

# Function to test package update workflow
test_package_updates() {
    echo "🔄 Testing package update workflow in staging..."
    
    # Show current package status
    echo "  📦 Current package status:"
    run_on_server "cd /opt/staging/backend && npm outdated || echo 'No outdated packages'"
    run_on_server "cd /opt/staging/frontend && npm outdated || echo 'No outdated packages'"
    
    # Test updates in staging
    echo "  ⬆️ Testing package updates..."
    if run_on_server "sudo staging-manager.sh test-updates"; then
        echo "  ✅ Package updates tested successfully in staging"
        
        # Ask if user wants to apply to production
        echo ""
        read -p "🚀 Apply tested updates to production? (y/N): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            echo "  🔄 Applying updates to production..."
            run_on_server "sudo staging-manager.sh apply-to-prod"
            echo "  ✅ Production updated successfully"
        else
            echo "  ⏸️ Updates not applied to production"
        fi
    else
        echo "  ❌ Package update tests failed in staging"
        echo "  🛡️ Production remains unchanged"
    fi
}

# Function to test staging service
test_staging_service() {
    echo "🖥️ Testing staging service functionality..."
    
    # Start staging service
    echo "  ▶️ Starting staging service..."
    run_on_server "sudo staging-manager.sh start"
    
    # Wait for service to start
    sleep 5
    
    # Check if service is running
    echo "  🔍 Checking if staging service is running..."
    if run_on_server "sudo systemctl is-active tunetussle-staging"; then
        echo "  ✅ Staging service is running"
        
        # Test staging endpoints (from server)
        echo "  🌐 Testing staging endpoints..."
        if run_on_server "curl -s http://localhost:4001/api/performance/health"; then
            echo "  ✅ Staging backend is responding"
        else
            echo "  ⚠️ Staging backend may not be fully ready yet"
        fi
    else
        echo "  ❌ Staging service failed to start"
        echo "  📋 Checking staging logs..."
        run_on_server "sudo staging-manager.sh logs"
    fi
    
    # Stop staging service
    echo "  ⏹️ Stopping staging service..."
    run_on_server "sudo staging-manager.sh stop"
}

# Function to show staging management commands
show_staging_commands() {
    echo "📋 Available staging management commands:"
    echo ""
    echo "Local commands:"
    echo "  ./deployment/scripts/setup-server-staging.sh    # Set up staging environment"
    echo "  ./deployment/scripts/test-server-staging.sh     # Test staging environment"
    echo ""
    echo "Server commands (via SSH):"
    echo "  ssh admin@69.164.209.52 'sudo staging-manager.sh setup'        # Copy prod to staging"
    echo "  ssh admin@69.164.209.52 'sudo staging-manager.sh test-updates' # Test package updates"
    echo "  ssh admin@69.164.209.52 'sudo staging-manager.sh apply-to-prod' # Apply to production"
    echo "  ssh admin@69.164.209.52 'sudo staging-manager.sh status'       # Check status"
    echo "  ssh admin@69.164.209.52 'sudo staging-manager.sh logs'         # View logs"
    echo "  ssh admin@69.164.209.52 'sudo staging-manager.sh clean'        # Clean staging"
    echo ""
    echo "Resource monitoring:"
    echo "  ssh admin@69.164.209.52 'free -h'                              # Memory usage"
    echo "  ssh admin@69.164.209.52 'df -h /opt/staging'                   # Disk usage"
}

# Main execution
main() {
    echo "🏔️ TuneTussle Alpine Server Staging Test"
    echo "=========================================="
    
    # Check if staging environment exists
    if ! check_staging_exists; then
        exit 1
    fi
    
    echo ""
    echo "🎯 Test Options:"
    echo "1. Full staging test (setup + package updates + service test)"
    echo "2. Package update test only"
    echo "3. Service test only"
    echo "4. Show staging commands"
    echo "5. Exit"
    echo ""
    
    read -p "Choose option (1-5): " -n 1 -r
    echo
    echo ""
    
    case $REPLY in
        1)
            echo "🧪 Running full staging test..."
            test_staging_setup
            echo ""
            test_package_updates
            echo ""
            test_staging_service
            ;;
        2)
            echo "🔄 Testing package updates only..."
            test_staging_setup
            echo ""
            test_package_updates
            ;;
        3)
            echo "🖥️ Testing staging service only..."
            test_staging_service
            ;;
        4)
            show_staging_commands
            ;;
        5)
            echo "👋 Exiting..."
            exit 0
            ;;
        *)
            echo "❌ Invalid option"
            exit 1
            ;;
    esac
    
    echo ""
    echo "✅ Staging test complete!"
    echo ""
    show_staging_commands
}

# Run main function
main 