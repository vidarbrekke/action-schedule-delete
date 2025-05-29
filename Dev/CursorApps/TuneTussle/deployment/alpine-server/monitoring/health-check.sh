#!/bin/bash

# TuneTussle Alpine Server Health Check Script
# Monitors system resources, services, and application health

set -e

# =============================================================================
# CONFIGURATION
# =============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/../../configs/alpine-production.env"

# Load configuration if available
if [[ -f "$CONFIG_FILE" ]]; then
    source "$CONFIG_FILE"
fi

# Default values if not in config
SERVER_IP="${SERVER_IP:-localhost}"
APP_DEPLOY_PATH="${APP_DEPLOY_PATH:-/opt/tunetussle}"
REDIS_PORT="${REDIS_PORT:-6379}"
APP_PORT="${PORT:-4000}"
ALERT_EMAIL="${ALERT_EMAIL:-root@localhost}"

# Thresholds
CPU_THRESHOLD=80
MEMORY_THRESHOLD=80
DISK_THRESHOLD=85
LOAD_THRESHOLD=2.0

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# =============================================================================
# FUNCTIONS
# =============================================================================
log_info() {
    echo -e "${BLUE}ℹ️  INFO:${NC} $1"
}

log_success() {
    echo -e "${GREEN}✅ OK:${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}⚠️  WARNING:${NC} $1"
}

log_error() {
    echo -e "${RED}❌ ERROR:${NC} $1"
}

get_timestamp() {
    date "+%Y-%m-%d %H:%M:%S"
}

check_system_resources() {
    echo "════════════════════════════════════════"
    echo "🖥️  SYSTEM RESOURCES"
    echo "════════════════════════════════════════"
    
    # CPU Usage
    cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1 | cut -d'u' -f1)
    if (( $(echo "$cpu_usage > $CPU_THRESHOLD" | bc -l) )); then
        log_warning "High CPU usage: ${cpu_usage}%"
    else
        log_success "CPU usage: ${cpu_usage}%"
    fi
    
    # Memory Usage
    memory_info=$(free | grep Mem)
    total_mem=$(echo $memory_info | awk '{print $2}')
    used_mem=$(echo $memory_info | awk '{print $3}')
    memory_usage=$(echo "scale=1; $used_mem * 100 / $total_mem" | bc)
    
    if (( $(echo "$memory_usage > $MEMORY_THRESHOLD" | bc -l) )); then
        log_warning "High memory usage: ${memory_usage}%"
    else
        log_success "Memory usage: ${memory_usage}%"
    fi
    
    # Disk Usage
    disk_usage=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
    if [[ $disk_usage -gt $DISK_THRESHOLD ]]; then
        log_warning "High disk usage: ${disk_usage}%"
    else
        log_success "Disk usage: ${disk_usage}%"
    fi
    
    # Load Average
    load_avg=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    if (( $(echo "$load_avg > $LOAD_THRESHOLD" | bc -l) )); then
        log_warning "High load average: $load_avg"
    else
        log_success "Load average: $load_avg"
    fi
    
    # Network connections
    connections=$(netstat -an | grep ESTABLISHED | wc -l)
    log_info "Active connections: $connections"
    
    echo
}

check_services() {
    echo "════════════════════════════════════════"
    echo "🔧 SYSTEM SERVICES"
    echo "════════════════════════════════════════"
    
    # Check critical services
    services=("sshd" "redis" "nftables" "sshguard")
    
    for service in "${services[@]}"; do
        if rc-service "$service" status >/dev/null 2>&1; then
            log_success "$service is running"
        else
            log_error "$service is not running"
        fi
    done
    
    # Check TuneTussle application service
    if command -v systemctl >/dev/null 2>&1; then
        if systemctl is-active --quiet tunetussle; then
            log_success "TuneTussle application is running"
        else
            log_error "TuneTussle application is not running"
        fi
    fi
    
    echo
}

check_network() {
    echo "════════════════════════════════════════"
    echo "🌐 NETWORK CONNECTIVITY"
    echo "════════════════════════════════════════"
    
    # Check external connectivity
    if ping -c 1 8.8.8.8 >/dev/null 2>&1; then
        log_success "External network connectivity"
    else
        log_error "No external network connectivity"
    fi
    
    # Check DNS resolution
    if nslookup google.com >/dev/null 2>&1; then
        log_success "DNS resolution working"
    else
        log_error "DNS resolution failed"
    fi
    
    # Check listening ports
    listening_ports=$(netstat -tln | grep LISTEN | awk '{print $4}' | cut -d: -f2 | sort -n | uniq)
    log_info "Listening ports: $(echo $listening_ports | tr '\n' ' ')"
    
    echo
}

check_redis() {
    echo "════════════════════════════════════════"
    echo "🗄️  REDIS DATABASE"
    echo "════════════════════════════════════════"
    
    if command -v redis-cli >/dev/null 2>&1; then
        # Check Redis connectivity
        if redis-cli -p "$REDIS_PORT" ping >/dev/null 2>&1; then
            log_success "Redis is responding"
            
            # Get Redis info
            redis_info=$(redis-cli -p "$REDIS_PORT" info server | grep redis_version)
            log_info "$redis_info"
            
            # Check memory usage
            redis_memory=$(redis-cli -p "$REDIS_PORT" info memory | grep used_memory_human | cut -d: -f2 | tr -d '\r')
            log_info "Redis memory usage: $redis_memory"
            
            # Check number of keys
            redis_keys=$(redis-cli -p "$REDIS_PORT" info keyspace | grep -c "db" || echo "0")
            log_info "Redis databases with keys: $redis_keys"
            
        else
            log_error "Redis is not responding on port $REDIS_PORT"
        fi
    else
        log_warning "redis-cli not available for Redis checks"
    fi
    
    echo
}

check_application() {
    echo "════════════════════════════════════════"
    echo "🎮 TUNETUSSLE APPLICATION"
    echo "════════════════════════════════════════"
    
    # Check if application directory exists
    if [[ -d "$APP_DEPLOY_PATH/current" ]]; then
        log_success "Application directory exists"
        
        # Check application files
        if [[ -f "$APP_DEPLOY_PATH/current/backend/server.js" ]]; then
            log_success "Application files present"
        else
            log_error "Application files missing"
        fi
        
        # Check application dependencies
        if [[ -d "$APP_DEPLOY_PATH/current/node_modules" ]]; then
            log_success "Dependencies installed"
        else
            log_warning "Dependencies may not be installed"
        fi
        
    else
        log_error "Application directory not found"
    fi
    
    # Check application endpoint
    if command -v curl >/dev/null 2>&1; then
        if curl -f -s "http://localhost:$APP_PORT/health" >/dev/null 2>&1; then
            log_success "Application health endpoint responding"
        else
            log_warning "Application health endpoint not responding"
        fi
        
        # Check if frontend is serving
        if curl -f -s "http://localhost" >/dev/null 2>&1; then
            log_success "Frontend is accessible"
        else
            log_warning "Frontend may not be accessible"
        fi
    else
        log_warning "curl not available for endpoint checks"
    fi
    
    echo
}

check_security() {
    echo "════════════════════════════════════════"
    echo "🔒 SECURITY STATUS"
    echo "════════════════════════════════════════"
    
    # Check SSH configuration
    if grep -q "PasswordAuthentication no" /etc/ssh/sshd_config; then
        log_success "SSH password authentication disabled"
    else
        log_warning "SSH password authentication may be enabled"
    fi
    
    if grep -q "PermitRootLogin no" /etc/ssh/sshd_config; then
        log_success "SSH root login disabled"
    else
        log_warning "SSH root login may be enabled"
    fi
    
    # Check firewall status
    if command -v nft >/dev/null 2>&1; then
        if nft list ruleset >/dev/null 2>&1; then
            log_success "Firewall (nftables) is active"
        else
            log_warning "Firewall (nftables) may not be configured"
        fi
    fi
    
    # Check for failed login attempts
    failed_logins=$(grep "Failed password" /var/log/auth.log 2>/dev/null | tail -10 | wc -l || echo "0")
    if [[ $failed_logins -gt 5 ]]; then
        log_warning "Multiple failed login attempts detected ($failed_logins in last 10 entries)"
    else
        log_success "No suspicious login activity"
    fi
    
    echo
}

check_logs() {
    echo "════════════════════════════════════════"
    echo "📋 LOG STATUS"
    echo "════════════════════════════════════════"
    
    # Check system logs
    if [[ -f "/var/log/messages" ]]; then
        recent_errors=$(grep -i error /var/log/messages | tail -5 | wc -l)
        if [[ $recent_errors -gt 0 ]]; then
            log_warning "$recent_errors recent error(s) in system logs"
        else
            log_success "No recent errors in system logs"
        fi
    fi
    
    # Check application logs
    if command -v journalctl >/dev/null 2>&1; then
        app_errors=$(journalctl -u tunetussle --since "1 hour ago" --grep="ERROR" --no-pager -q | wc -l || echo "0")
        if [[ $app_errors -gt 0 ]]; then
            log_warning "$app_errors application error(s) in the last hour"
        else
            log_success "No recent application errors"
        fi
    fi
    
    echo
}

generate_summary() {
    echo "════════════════════════════════════════"
    echo "📊 HEALTH CHECK SUMMARY"
    echo "════════════════════════════════════════"
    echo "Timestamp: $(get_timestamp)"
    echo "Server: $SERVER_IP"
    echo "Uptime: $(uptime -p)"
    echo "Kernel: $(uname -r)"
    echo "Alpine Version: $(cat /etc/alpine-release 2>/dev/null || echo "Unknown")"
    echo
    
    # Quick status indicators
    echo "🔍 Quick Status:"
    echo "  CPU: ${cpu_usage}% | Memory: ${memory_usage}% | Disk: ${disk_usage}%"
    echo "  Load: $load_avg | Connections: $connections"
    echo
}

send_alert() {
    local subject="$1"
    local message="$2"
    
    if command -v mail >/dev/null 2>&1; then
        echo "$message" | mail -s "$subject" "$ALERT_EMAIL"
        log_info "Alert sent to $ALERT_EMAIL"
    else
        log_warning "Mail not configured, cannot send alert"
    fi
}

# =============================================================================
# MAIN EXECUTION
# =============================================================================
main() {
    echo "🔍 TuneTussle Alpine Server Health Check"
    echo "$(get_timestamp)"
    echo "════════════════════════════════════════"
    echo
    
    # Run all checks
    check_system_resources
    check_services
    check_network
    check_redis
    check_application
    check_security
    check_logs
    generate_summary
    
    echo "✅ Health check completed"
    echo "════════════════════════════════════════"
}

# Check if script is being run directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi 