#!/bin/bash

# TuneTussle Alpine Server Staging Environment Setup
# Creates isolated staging environment for package testing

set -e

SERVER_HOST="69.164.209.52"
SERVER_PORT="2222"
SERVER_USER="admin"
SSH_OPTS="-o StrictHostKeyChecking=no -o ConnectTimeout=30"

STAGING_DIR="/opt/staging"
PROD_DIR="/opt/tunetussle"
STAGING_PORT="4001"
STAGING_FRONTEND_PORT="3001"

echo "🏗️ Setting up staging environment on Alpine server..."

# Create staging environment setup script
cat > staging-setup.sh << 'EOF'
#!/bin/bash
set -e

STAGING_DIR="/opt/staging"
PROD_DIR="/opt/tunetussle"
APP_USER="appuser"
APP_GROUP="appgroup"

echo "📁 Creating staging directories..."

# Create staging directory structure
sudo mkdir -p "$STAGING_DIR"
sudo mkdir -p /var/log/staging
sudo mkdir -p /tmp/staging-backups

# Set proper ownership
sudo chown -R "$APP_USER:$APP_GROUP" "$STAGING_DIR"
sudo chown -R "$APP_USER:$APP_GROUP" /var/log/staging
sudo chown -R "$APP_USER:$APP_GROUP" /tmp/staging-backups

echo "🔧 Creating staging service configuration..."

# Create staging systemd service
sudo tee /etc/systemd/system/tunetussle-staging.service > /dev/null << 'SERVICE_EOF'
[Unit]
Description=TuneTussle Staging Server
After=network.target
After=redis.service

[Service]
Type=simple
User=appuser
Group=appgroup
WorkingDirectory=/opt/staging
Environment=NODE_ENV=staging
Environment=PORT=4001
Environment=FRONTEND_PORT=3001
ExecStart=/usr/bin/node backend/src/index.js
ExecReload=/bin/kill -HUP $MAINPID
KillMode=mixed
KillSignal=SIGINT
TimeoutStopSec=5
Restart=on-failure
RestartSec=5

# Security
NoNewPrivileges=yes
PrivateTmp=yes

# Logging
StandardOutput=journal
StandardError=journal
SyslogIdentifier=tunetussle-staging

[Install]
WantedBy=multi-user.target
SERVICE_EOF

# Reload systemd
sudo systemctl daemon-reload

echo "🛡️ Configuring staging firewall rules..."

# Add firewall rules for staging ports (localhost only)
sudo iptables -A INPUT -p tcp --dport 4001 -s 127.0.0.1 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 3001 -s 127.0.0.1 -j ACCEPT

# Save iptables rules
sudo /etc/init.d/iptables save

echo "📋 Creating staging management scripts..."

# Create staging management script
sudo tee /usr/local/bin/staging-manager.sh > /dev/null << 'MANAGER_EOF'
#!/bin/bash

STAGING_DIR="/opt/staging"
PROD_DIR="/opt/tunetussle"
LOG_FILE="/var/log/staging/operations.log"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

case "$1" in
    "setup")
        log "Setting up staging environment from production..."
        
        # Stop staging if running
        sudo systemctl stop tunetussle-staging 2>/dev/null || true
        
        # Clean staging directory
        rm -rf "$STAGING_DIR"/*
        
        # Copy production code to staging
        cp -r "$PROD_DIR"/* "$STAGING_DIR/"
        
        # Install dependencies in staging
        cd "$STAGING_DIR/backend" && npm ci --prefer-offline --silent
        cd "$STAGING_DIR/frontend" && npm ci --prefer-offline --silent
        
        # Set proper ownership
        sudo chown -R appuser:appgroup "$STAGING_DIR"
        
        log "Staging environment ready"
        ;;
        
    "test-updates")
        log "Testing package updates in staging..."
        
        cd "$STAGING_DIR"
        
        # Create backup of package files
        mkdir -p /tmp/staging-backups/$(date +%Y%m%d_%H%M%S)
        cp backend/package*.json /tmp/staging-backups/$(date +%Y%m%d_%H%M%S)/
        cp frontend/package*.json /tmp/staging-backups/$(date +%Y%m%d_%H%M%S)/
        
        # Apply updates
        cd backend && npm update --silent
        cd ../frontend && npm update --silent
        cd ..
        
        # Run tests
        log "Running backend tests..."
        cd backend && npm test --silent
        BACKEND_RESULT=$?
        
        log "Running frontend tests..."
        cd ../frontend && npm test --silent
        FRONTEND_RESULT=$?
        cd ..
        
        if [ $BACKEND_RESULT -eq 0 ] && [ $FRONTEND_RESULT -eq 0 ]; then
            log "✅ All tests passed in staging"
            echo "SUCCESS: Updates tested successfully"
            return 0
        else
            log "❌ Tests failed in staging"
            echo "FAILED: Tests failed in staging environment"
            return 1
        fi
        ;;
        
    "apply-to-prod")
        log "Applying staging changes to production..."
        
        # Stop production
        sudo systemctl stop tunetussle
        
        # Create production backup
        BACKUP_DIR="/tmp/prod-backup-$(date +%Y%m%d_%H%M%S)"
        mkdir -p "$BACKUP_DIR"
        cp "$PROD_DIR/backend/package*.json" "$BACKUP_DIR/"
        cp "$PROD_DIR/frontend/package*.json" "$BACKUP_DIR/"
        
        # Copy tested package files from staging to production
        cp "$STAGING_DIR/backend/package*.json" "$PROD_DIR/backend/"
        cp "$STAGING_DIR/frontend/package*.json" "$PROD_DIR/frontend/"
        
        # Install production dependencies
        cd "$PROD_DIR/backend" && npm ci --production --prefer-offline --silent
        cd "$PROD_DIR/frontend" && npm ci --production --prefer-offline --silent
        
        # Start production
        sudo systemctl start tunetussle
        
        log "Production updated successfully"
        ;;
        
    "start")
        log "Starting staging environment..."
        sudo systemctl start tunetussle-staging
        ;;
        
    "stop")
        log "Stopping staging environment..."
        sudo systemctl stop tunetussle-staging
        ;;
        
    "status")
        echo "=== Staging Environment Status ==="
        sudo systemctl status tunetussle-staging --no-pager || true
        echo ""
        echo "=== Memory Usage ==="
        free -h
        echo ""
        echo "=== Disk Usage ==="
        df -h /opt/staging 2>/dev/null || echo "Staging directory not found"
        ;;
        
    "logs")
        echo "=== Staging Service Logs ==="
        sudo journalctl -u tunetussle-staging -n 50 --no-pager
        echo ""
        echo "=== Staging Operations Log ==="
        tail -20 "$LOG_FILE" 2>/dev/null || echo "No operations log found"
        ;;
        
    "clean")
        log "Cleaning staging environment..."
        sudo systemctl stop tunetussle-staging 2>/dev/null || true
        rm -rf "$STAGING_DIR"/*
        rm -rf /tmp/staging-backups/*
        log "Staging environment cleaned"
        ;;
        
    *)
        echo "Usage: $0 {setup|test-updates|apply-to-prod|start|stop|status|logs|clean}"
        echo ""
        echo "Commands:"
        echo "  setup         - Copy production to staging"
        echo "  test-updates  - Test package updates in staging"
        echo "  apply-to-prod - Apply tested changes to production"
        echo "  start         - Start staging service"
        echo "  stop          - Stop staging service"
        echo "  status        - Show staging status"
        echo "  logs          - Show staging logs"
        echo "  clean         - Clean staging environment"
        ;;
esac
MANAGER_EOF

# Make staging manager executable
sudo chmod +x /usr/local/bin/staging-manager.sh

echo "✅ Staging environment setup complete!"
echo ""
echo "📋 Available commands:"
echo "  sudo staging-manager.sh setup        # Copy production to staging"
echo "  sudo staging-manager.sh test-updates # Test package updates"
echo "  sudo staging-manager.sh status       # Check staging status"
echo ""
echo "🏔️ Staging environment configured for Alpine Linux 1GB RAM optimization"

EOF

# Upload and execute staging setup script
echo "📤 Uploading staging setup script to server..."
scp $SSH_OPTS staging-setup.sh "$SERVER_USER@$SERVER_HOST:/tmp/"

echo "🔧 Executing staging setup on server..."
ssh $SSH_OPTS "$SERVER_USER@$SERVER_HOST" 'chmod +x /tmp/staging-setup.sh && sudo /tmp/staging-setup.sh'

# Clean up local staging setup script
rm staging-setup.sh

echo "✅ Server-side staging environment setup complete!"
echo ""
echo "🎯 Next steps:"
echo "1. Test staging setup: ./deployment/scripts/test-server-staging.sh"
echo "2. Use staging: ./deployment/scripts/remote-commands.sh staging"
echo ""
echo "📋 Staging is now available at:"
echo "  - Backend: localhost:4001 (server-only)"
echo "  - Frontend: localhost:3001 (server-only)"
echo "  - Management: ssh admin@69.164.209.52 'sudo staging-manager.sh status'" 