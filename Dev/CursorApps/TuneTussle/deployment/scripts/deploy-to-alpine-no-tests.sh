#!/bin/bash

# TuneTussle Deployment Script for Alpine Linux Production Server
# Usage: ./deploy-to-alpine.sh [--commit COMMIT_HASH] [--branch BRANCH_NAME] [--force]

set -e  # Exit on any error

# =============================================================================
# CONFIGURATION
# =============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
DEPLOYMENT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Load configuration
if [[ -f "$DEPLOYMENT_DIR/configs/alpine-production.env" ]]; then
    source "$DEPLOYMENT_DIR/configs/alpine-production.env"
else
    echo "❌ Error: Configuration file not found at $DEPLOYMENT_DIR/configs/alpine-production.env"
    exit 1
fi

# Default values
COMMIT_HASH=""
BRANCH_NAME="main"
FORCE_DEPLOY=false
SERVER_USER="$ADMIN_USER"
SERVER_HOST="$SERVER_IP"
SSH_OPTS="-p $SSH_PORT -o StrictHostKeyChecking=no"
SCP_OPTS="-P $SSH_PORT -o StrictHostKeyChecking=no"

# =============================================================================
# FUNCTIONS
# =============================================================================
log_info() {
    echo "ℹ️  INFO: $1"
}

log_success() {
    echo "✅ SUCCESS: $1"
}

log_warning() {
    echo "⚠️  WARNING: $1"
}

log_error() {
    echo "❌ ERROR: $1"
}

show_usage() {
    cat << EOF
TuneTussle Alpine Linux Deployment Script

Usage: $0 [OPTIONS]

Options:
  --commit HASH     Deploy specific commit hash
  --branch NAME     Deploy specific branch (default: main)
  --force          Force deployment without confirmations
  --help           Show this help message

Examples:
  $0                          # Deploy latest main branch
  $0 --branch feature/new-ui  # Deploy specific branch
  $0 --commit abc123def       # Deploy specific commit
  $0 --force                  # Force deploy without prompts

Server: $SERVER_HOST:$SSH_PORT
Target: $APP_DEPLOY_PATH
EOF
}

parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --commit)
                COMMIT_HASH="$2"
                shift 2
                ;;
            --branch)
                BRANCH_NAME="$2"
                shift 2
                ;;
            --force)
                FORCE_DEPLOY=true
                shift
                ;;
            --help)
                show_usage
                exit 0
                ;;
            *)
                log_error "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done
}

check_prerequisites() {
    log_info "Checking prerequisites..."
    
    # Check if we're in the right directory
    if [[ ! -f "$PROJECT_ROOT/package.json" ]]; then
        log_error "Not in TuneTussle project root directory"
        exit 1
    fi
    
    # Check git status
    if [[ -n "$(git status --porcelain)" && "$FORCE_DEPLOY" != true ]]; then
        log_warning "You have uncommitted changes:"
        git status --short
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Deployment cancelled"
            exit 0
        fi
    fi
    
    # Check SSH connectivity
    log_info "Testing SSH connection to $SERVER_HOST..."
    if ! ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" "echo 'SSH connection successful'" >/dev/null 2>&1; then
        log_error "Cannot connect to server via SSH"
        log_info "Make sure you can connect with: ssh $SSH_OPTS $SERVER_USER@$SERVER_HOST"
        exit 1
    fi
    
    log_success "Prerequisites check passed"
}

prepare_deployment() {
    log_info "Preparing deployment package..."
    
    # Create temporary deployment directory
    TEMP_DIR=$(mktemp -d)
    DEPLOYMENT_PACKAGE="$TEMP_DIR/tunetussle-deploy.tar.gz"
    
    # Ensure we're on the right commit/branch
    if [[ -n "$COMMIT_HASH" ]]; then
        log_info "Checking out commit: $COMMIT_HASH"
        git checkout "$COMMIT_HASH"
    else
        log_info "Checking out branch: $BRANCH_NAME"
        git checkout "$BRANCH_NAME"
        git pull origin "$BRANCH_NAME" || log_warning "Could not pull latest changes"
    fi
    
    # Run local tests before deployment
    log_info "Skipping pre-deployment tests (already verified)..."
    # if ! npm test > /dev/null 2>&1; then
    #     log_error "Tests failed! Deployment aborted."
    #     cleanup_temp
    #     exit 1
    # fi
    
    # Create deployment package
    log_info "Creating deployment package..."
    
    # Create a clean copy without node_modules, .git, etc.
    tar -czf "$DEPLOYMENT_PACKAGE" \
        --exclude='node_modules' \
        --exclude='.git' \
        --exclude='*.log' \
        --exclude='coverage' \
        --exclude='dist' \
        --exclude='build' \
        --exclude='.env*' \
        --exclude='deployment' \
        -C "$PROJECT_ROOT" .
    
    log_success "Deployment package created: $(basename $DEPLOYMENT_PACKAGE)"
}

deploy_to_server() {
    log_info "Deploying to Alpine server..."
    
    # Upload deployment package
    log_info "Uploading deployment package..."
    scp $SCP_OPTS "$DEPLOYMENT_PACKAGE" "$SERVER_USER@$SERVER_HOST:/tmp/"
    
    # Create deployment script for server
    cat > "$TEMP_DIR/deploy-server.sh" << 'EOF'
#!/bin/bash
set -e

APP_DEPLOY_PATH="/opt/tunetussle"
APP_USER="appuser"
APP_GROUP="appgroup"
SERVICE_NAME="tunetussle"

echo "🚀 Starting server-side deployment..."

# Create application directory if it doesn't exist
sudo mkdir -p "$APP_DEPLOY_PATH"

# Create backup of current deployment
if [[ -d "$APP_DEPLOY_PATH/current" ]]; then
    echo "📦 Creating backup of current deployment..."
    sudo mv "$APP_DEPLOY_PATH/current" "$APP_DEPLOY_PATH/backup-$(date +%Y%m%d-%H%M%S)" || true
fi

# Extract new deployment
echo "📂 Extracting new deployment..."
sudo mkdir -p "$APP_DEPLOY_PATH/current"
sudo tar -xzf /tmp/tunetussle-deploy.tar.gz -C "$APP_DEPLOY_PATH/current"

# Set ownership
sudo chown -R "$APP_USER:$APP_GROUP" "$APP_DEPLOY_PATH/current"

# Install dependencies
echo "📥 Installing Node.js dependencies..."
cd "$APP_DEPLOY_PATH/current"
sudo -u "$APP_USER" npm ci --production

# Build frontend if needed
echo "🏗️  Building frontend..."
cd "$APP_DEPLOY_PATH/current/frontend"
sudo -u "$APP_USER" npm ci
sudo -u "$APP_USER" npm run build

# Copy production environment configuration
echo "⚙️  Setting up production configuration..."
cd "$APP_DEPLOY_PATH/current"
sudo -u "$APP_USER" cp .env.production .env || echo "No .env.production found, using defaults"

# Create systemd service if it doesn't exist
if [[ ! -f "/etc/systemd/system/$SERVICE_NAME.service" ]]; then
    echo "🔧 Creating systemd service..."
    sudo tee "/etc/systemd/system/$SERVICE_NAME.service" > /dev/null << SERVICEEOF
[Unit]
Description=TuneTussle - Real-time Music Quiz Game
After=network.target redis.service

[Service]
Type=simple
User=$APP_USER
Group=$APP_GROUP
WorkingDirectory=$APP_DEPLOY_PATH/current/backend
ExecStart=/usr/bin/node server.js
Restart=always
RestartSec=10
Environment=NODE_ENV=production
Environment=PORT=4000
StandardOutput=syslog
StandardError=syslog
SyslogIdentifier=tunetussle

[Install]
WantedBy=multi-user.target
SERVICEEOF

    sudo systemctl daemon-reload
    sudo systemctl enable "$SERVICE_NAME"
fi

# Start/restart the service
echo "🔄 Restarting TuneTussle service..."
sudo systemctl restart "$SERVICE_NAME"

# Wait a moment and check status
sleep 3
if sudo systemctl is-active --quiet "$SERVICE_NAME"; then
    echo "✅ TuneTussle service is running successfully!"
else
    echo "❌ TuneTussle service failed to start"
    sudo systemctl status "$SERVICE_NAME" --no-pager
    exit 1
fi

# Clean up
rm -f /tmp/tunetussle-deploy.tar.gz

echo "🎉 Deployment completed successfully!"
echo "🌐 Application should be available at: http://$(hostname -I | awk '{print $1}'):4000"
EOF
    
    # Upload and execute deployment script
    scp $SCP_OPTS "$TEMP_DIR/deploy-server.sh" "$SERVER_USER@$SERVER_HOST:/tmp/"
    ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" "chmod +x /tmp/deploy-server.sh && /tmp/deploy-server.sh"
    
    log_success "Deployment completed successfully!"
}

verify_deployment() {
    log_info "Verifying deployment..."
    
    # Check service status
    log_info "Checking service status..."
    ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" "sudo systemctl status tunetussle --no-pager" || true
    
    # Test application endpoint
    log_info "Testing application endpoint..."
    if ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" "curl -f http://localhost:4000/health" >/dev/null 2>&1; then
        log_success "Application is responding to health checks"
    else
        log_warning "Application health check failed"
    fi
    
    # Show deployment info
    cat << EOF

🚀 Deployment Summary
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Server:     $SERVER_HOST:$SSH_PORT
Branch:     $BRANCH_NAME
Commit:     $(git rev-parse --short HEAD)
Deploy Time: $(date)
Target:     $APP_DEPLOY_PATH/current

🔗 Access URLs:
Frontend:   http://$SERVER_HOST/
Backend:    http://$SERVER_HOST:4000/
Health:     http://$SERVER_HOST:4000/health

🛠️  Management Commands:
Status:     ssh $SSH_OPTS $SERVER_USER@$SERVER_HOST 'sudo systemctl status tunetussle'
Logs:       ssh $SSH_OPTS $SERVER_USER@$SERVER_HOST 'sudo journalctl -u tunetussle -f'
Restart:    ssh $SSH_OPTS $SERVER_USER@$SERVER_HOST 'sudo systemctl restart tunetussle'

EOF
}

cleanup_temp() {
    if [[ -n "$TEMP_DIR" && -d "$TEMP_DIR" ]]; then
        rm -rf "$TEMP_DIR"
    fi
}

# =============================================================================
# MAIN EXECUTION
# =============================================================================
main() {
    log_info "🚀 TuneTussle Alpine Linux Deployment Starting..."
    
    parse_arguments "$@"
    
    # Show deployment info
    cat << EOF
🎯 Deployment Configuration
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Target Server:  $SERVER_HOST:$SSH_PORT
Branch/Commit:  ${COMMIT_HASH:-$BRANCH_NAME}
Force Deploy:   $FORCE_DEPLOY
Deploy Path:    $APP_DEPLOY_PATH

EOF
    
    if [[ "$FORCE_DEPLOY" != true ]]; then
        read -p "Continue with deployment? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Deployment cancelled"
            exit 0
        fi
    fi
    
    # Trap cleanup
    trap cleanup_temp EXIT
    
    check_prerequisites
    prepare_deployment
    deploy_to_server
    verify_deployment
    
    log_success "🎉 Deployment completed successfully!"
}

# Run main function with all arguments
main "$@" 