#!/bin/bash

# Remote Commands Script for TuneTussle Alpine Server
# Usage: ./remote-commands.sh [command] or interactive mode

set -e

# =============================================================================
# CONFIGURATION
# =============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/../configs/alpine-production.env"

# Load configuration
if [[ -f "$CONFIG_FILE" ]]; then
    source "$CONFIG_FILE"
else
    echo "❌ Error: Configuration file not found at $CONFIG_FILE"
    exit 1
fi

# SSH settings
SERVER_USER="$ADMIN_USER"
SERVER_HOST="$SERVER_IP"
SSH_OPTS="-p $SSH_PORT -o StrictHostKeyChecking=no"

# =============================================================================
# FUNCTIONS
# =============================================================================
log_info() {
    echo "ℹ️  INFO: $1"
}

log_success() {
    echo "✅ SUCCESS: $1"
}

log_error() {
    echo "❌ ERROR: $1"
}

show_usage() {
    cat << EOF
TuneTussle Alpine Server Remote Commands

Usage: $0 [COMMAND]

Common Commands:
  status          Show application and system status
  logs            Show TuneTussle application logs
  restart         Restart TuneTussle application
  health          Run health check
  update          Update system packages
  backup          Run backup manually
  disk            Show disk usage
  memory          Show memory usage
  processes       Show running processes
  netstat         Show network connections
  firewall        Show firewall rules

Custom Commands:
  $0 "your custom command"    Execute any command
  $0                          Interactive mode

Server: $SERVER_HOST:$SSH_PORT
EOF
}

execute_remote_command() {
    local command="$1"
    local description="$2"
    
    if [[ -n "$description" ]]; then
        log_info "$description"
    fi
    
    echo "🔗 Executing on $SERVER_HOST: $command"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" "$command"
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo
}

check_connection() {
    log_info "Testing SSH connection to $SERVER_HOST..."
    if ! ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" "echo 'Connection successful'" >/dev/null 2>&1; then
        log_error "Cannot connect to server via SSH"
        log_info "Make sure you can connect with: ssh $SSH_OPTS $SERVER_USER@$SERVER_HOST"
        exit 1
    fi
    log_success "SSH connection established"
}

show_status() {
    execute_remote_command "
        echo '🖥️  System Status:'
        uptime
        echo
        echo '📊 Memory Usage:'
        free -h
        echo
        echo '💽 Disk Usage:'
        df -h /
        echo
        echo '🔧 TuneTussle Service:'
        sudo systemctl status tunetussle --no-pager || echo 'Service not found'
        echo
        echo '🗄️  Redis Status:'
        sudo rc-service redis status || echo 'Redis service check failed'
        echo
        echo '🔥 Firewall Status:'
        sudo nft list ruleset | head -10 || echo 'Firewall check failed'
    " "Getting comprehensive system status"
}

show_logs() {
    execute_remote_command "
        echo '📋 TuneTussle Application Logs (last 50 lines):'
        sudo journalctl -u tunetussle -n 50 --no-pager || echo 'No systemd logs found'
        echo
        echo '📋 System Logs (last 20 lines):'
        sudo tail -20 /var/log/messages || echo 'No system log file found'
    " "Fetching application and system logs"
}

restart_application() {
    execute_remote_command "
        echo '🔄 Restarting TuneTussle application...'
        sudo systemctl restart tunetussle
        sleep 3
        echo '✅ Restart completed. Checking status:'
        sudo systemctl status tunetussle --no-pager
    " "Restarting TuneTussle application"
}

run_health_check() {
    # Check if health check script exists on server
    execute_remote_command "
        if [[ -f '/opt/scripts/health-check.sh' ]]; then
            echo '🔍 Running comprehensive health check:'
            sudo /opt/scripts/health-check.sh
        else
            echo '⚠️  Comprehensive health check script not found. Running basic checks:'
            echo
            echo 'System uptime:'
            uptime
            echo
            echo 'Service status:'
            sudo systemctl is-active tunetussle || echo 'TuneTussle: inactive'
            sudo rc-service redis status >/dev/null && echo 'Redis: active' || echo 'Redis: inactive'
            echo
            echo 'Application endpoint test:'
            curl -f http://localhost:4000/health 2>/dev/null && echo 'Application: responding' || echo 'Application: not responding'
        fi
    " "Running health check"
}

update_system() {
    execute_remote_command "
        echo '📦 Updating system packages...'
        sudo apk update
        sudo apk upgrade
        echo '✅ System update completed'
    " "Updating system packages"
}

run_backup() {
    execute_remote_command "
        echo '💾 Running manual backup...'
        if [[ -f '/usr/local/bin/backup_tunetussle.sh' ]]; then
            sudo /usr/local/bin/backup_tunetussle.sh
        else
            echo '❌ Backup script not found at /usr/local/bin/backup_tunetussle.sh'
        fi
    " "Running manual backup"
}

show_disk_usage() {
    execute_remote_command "
        echo '💽 Detailed Disk Usage:'
        df -h
        echo
        echo '📁 Directory sizes in /opt:'
        sudo du -sh /opt/* 2>/dev/null || echo 'No /opt directory or access denied'
        echo
        echo '📁 Directory sizes in /data:'
        sudo du -sh /data/* 2>/dev/null || echo 'No /data directory or access denied'
        echo
        echo '🗑️  Log file sizes:'
        sudo find /var/log -name '*.log' -exec ls -lh {} \; 2>/dev/null | head -10
    " "Checking detailed disk usage"
}

show_memory_usage() {
    execute_remote_command "
        echo '📊 Memory Usage Details:'
        free -h
        echo
        echo '🔝 Top Memory Consumers:'
        ps aux --sort=-%mem | head -10
        echo
        echo '🗄️  Redis Memory Usage:'
        redis-cli info memory | grep used_memory_human || echo 'Redis not accessible'
    " "Checking detailed memory usage"
}

show_processes() {
    execute_remote_command "
        echo '🔄 Running Processes:'
        ps aux --sort=-%cpu | head -15
        echo
        echo '🎮 TuneTussle Processes:'
        ps aux | grep -E '(node|tunetussle)' | grep -v grep || echo 'No TuneTussle processes found'
    " "Showing running processes"
}

show_network() {
    execute_remote_command "
        echo '🌐 Network Connections:'
        netstat -tulnp | grep LISTEN
        echo
        echo '📈 Connection Statistics:'
        netstat -s | grep -E '(active|passive|failed|reset)'
        echo
        echo '🔗 Active Connections:'
        netstat -an | grep ESTABLISHED | wc -l | awk '{print \"Active connections: \" \$1}'
    " "Showing network information"
}

show_firewall() {
    execute_remote_command "
        echo '🔥 Firewall Rules (nftables):'
        sudo nft list ruleset || echo 'nftables not available or not configured'
        echo
        echo '🚪 Open Ports:'
        sudo netstat -tulnp | grep LISTEN
    " "Showing firewall configuration"
}

# Staging environment management functions
staging_status() {
    execute_remote_command "
        if [[ -f '/usr/local/bin/staging-manager.sh' ]]; then
            echo '🏗️ Staging Environment Status:'
            sudo staging-manager.sh status
        else
            echo '❌ Staging environment not set up'
            echo '💡 Run ./deployment/scripts/setup-server-staging.sh to set up staging'
        fi
    " "Checking staging environment status"
}

staging_setup() {
    execute_remote_command "
        if [[ -f '/usr/local/bin/staging-manager.sh' ]]; then
            echo '🔧 Setting up staging environment from production...'
            sudo staging-manager.sh setup
        else
            echo '❌ Staging environment not installed'
            echo '💡 Run ./deployment/scripts/setup-server-staging.sh first'
        fi
    " "Setting up staging environment"
}

staging_test_updates() {
    execute_remote_command "
        if [[ -f '/usr/local/bin/staging-manager.sh' ]]; then
            echo '🧪 Testing package updates in staging...'
            sudo staging-manager.sh test-updates
        else
            echo '❌ Staging environment not installed'
            echo '💡 Run ./deployment/scripts/setup-server-staging.sh first'
        fi
    " "Testing package updates in staging"
}

staging_apply_to_prod() {
    execute_remote_command "
        if [[ -f '/usr/local/bin/staging-manager.sh' ]]; then
            echo '🚀 Applying staging changes to production...'
            sudo staging-manager.sh apply-to-prod
        else
            echo '❌ Staging environment not installed'
            echo '💡 Run ./deployment/scripts/setup-server-staging.sh first'
        fi
    " "Applying staging changes to production"
}

staging_logs() {
    execute_remote_command "
        if [[ -f '/usr/local/bin/staging-manager.sh' ]]; then
            echo '📋 Staging Environment Logs:'
            sudo staging-manager.sh logs
        else
            echo '❌ Staging environment not installed'
            echo '💡 Run ./deployment/scripts/setup-server-staging.sh first'
        fi
    " "Showing staging environment logs"
}

staging_clean() {
    execute_remote_command "
        if [[ -f '/usr/local/bin/staging-manager.sh' ]]; then
            echo '🧹 Cleaning staging environment...'
            sudo staging-manager.sh clean
        else
            echo '❌ Staging environment not installed'
            echo '💡 Run ./deployment/scripts/setup-server-staging.sh first'
        fi
    " "Cleaning staging environment"
}

interactive_mode() {
    echo "🎛️  TuneTussle Remote Command Interface"
    echo "Server: $SERVER_HOST:$SSH_PORT"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo
    
    while true; do
        echo "Available commands:"
        echo "  🏭 Production: status, logs, restart, health, update, backup"
        echo "  📊 System: disk, memory, processes, netstat, firewall"
        echo "  🏗️ Staging: staging-status, staging-setup, staging-test, staging-apply, staging-logs, staging-clean"
        echo "  🔧 Other: shell (interactive shell), quit"
        echo
        read -p "Enter command (or 'quit' to exit): " command
        
        case "$command" in
            "quit"|"exit"|"q")
                echo "👋 Goodbye!"
                break
                ;;
            "shell")
                echo "🐚 Opening interactive shell on $SERVER_HOST..."
                ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST"
                ;;
            "")
                continue
                ;;
            *)
                if [[ "$command" =~ ^(status|logs|restart|health|update|backup|disk|memory|processes|netstat|firewall|staging-status|staging-setup|staging-test|staging-apply|staging-logs|staging-clean)$ ]]; then
                    handle_command "$command"
                else
                    # Execute custom command
                    execute_remote_command "$command" "Executing custom command"
                fi
                ;;
        esac
        
        echo
        read -p "Press Enter to continue..."
        echo
    done
}

handle_command() {
    case "$1" in
        "status")
            show_status
            ;;
        "logs")
            show_logs
            ;;
        "restart")
            restart_application
            ;;
        "health")
            run_health_check
            ;;
        "update")
            update_system
            ;;
        "backup")
            run_backup
            ;;
        "disk")
            show_disk_usage
            ;;
        "memory")
            show_memory_usage
            ;;
        "processes")
            show_processes
            ;;
        "netstat")
            show_network
            ;;
        "firewall")
            show_firewall
            ;;
        "staging-status")
            staging_status
            ;;
        "staging-setup")
            staging_setup
            ;;
        "staging-test")
            staging_test_updates
            ;;
        "staging-apply")
            staging_apply_to_prod
            ;;
        "staging-logs")
            staging_logs
            ;;
        "staging-clean")
            staging_clean
            ;;
        *)
            log_error "Unknown command: $1"
            show_usage
            exit 1
            ;;
    esac
}

# =============================================================================
# MAIN EXECUTION
# =============================================================================
main() {
    # Check SSH connection first
    check_connection
    
    if [[ $# -eq 0 ]]; then
        # No arguments - interactive mode
        interactive_mode
    elif [[ "$1" == "--help" || "$1" == "-h" ]]; then
        show_usage
    else
        # Command provided
        if [[ "$1" =~ ^(status|logs|restart|health|update|backup|disk|memory|processes|netstat|firewall|staging-status|staging-setup|staging-test|staging-apply|staging-logs|staging-clean)$ ]]; then
            handle_command "$1"
        else
            # Execute as custom command
            execute_remote_command "$*" "Executing custom command"
        fi
    fi
}

# Run main function with all arguments
main "$@" 