#!/bin/bash

# Shared configuration for TuneTussle deployment scripts
# Source this file in other scripts: source "$(dirname "$0")/shared-config.sh"

# =============================================================================
# SERVER CONFIGURATION
# =============================================================================
export SERVER_HOST="69.164.209.52"
export SERVER_PORT="2222"
export SERVER_USER="admin"
export SSH_OPTS="-p $SERVER_PORT -o StrictHostKeyChecking=no -o ConnectTimeout=30"

# =============================================================================
# DIRECTORY CONFIGURATION  
# =============================================================================
export STAGING_DIR="/opt/staging"
export PROD_DIR="/opt/tunetussle"
export STAGING_PORT="4001"
export STAGING_FRONTEND_PORT="3001"

# =============================================================================
# SHARED FUNCTIONS
# =============================================================================

# Check if staging environment exists on server
check_staging_installed() {
    ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" "test -f /usr/local/bin/staging-manager.sh"
}

# Execute command on server with error handling
execute_server_command() {
    local cmd="$1"
    local description="${2:-Executing server command}"
    
    echo "🔗 $description..."
    if ! ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" "$cmd"; then
        echo "❌ Command failed: $cmd"
        return 1
    fi
    return 0
}

# Show staging not installed message
show_staging_setup_message() {
    echo "❌ Staging environment not installed"
    echo "💡 Run './deployment/scripts/setup-server-staging.sh' first"
}

# =============================================================================
# VALIDATION
# =============================================================================

# Validate SSH connection
validate_ssh_connection() {
    if ! ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" "echo 'Connection test'" >/dev/null 2>&1; then
        echo "❌ Cannot connect to server: $SERVER_HOST:$SERVER_PORT"
        echo "💡 Check SSH configuration and server availability"
        return 1
    fi
    return 0
} 