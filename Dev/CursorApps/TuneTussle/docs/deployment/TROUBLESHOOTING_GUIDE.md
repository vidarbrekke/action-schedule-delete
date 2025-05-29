# TuneTussle Deployment Troubleshooting Guide

**🔧 Common Issues and Solutions from Real Deployments**

This guide documents real issues encountered during TuneTussle deployments and their proven solutions. Based on actual deployment experiences and fixes.

## 🚨 Critical Issues & Solutions

### 1. TypeScript Compilation Errors (Most Common)

**Issue**: Frontend build fails with 90+ TypeScript errors
```bash
error TS2307: Cannot find module './HomePage' or its type declarations
error TS2345: Argument of type 'string | undefined' is not assignable to parameter of type 'string'
```

**Root Causes**:
- Duplicate JavaScript/TypeScript test files
- Missing icon imports (LogIn, Plus, Gamepad2)
- Type mismatches in gameStateReducer (audioUrl vs trackUrl)
- Unused imports causing strict TypeScript failures

**Solution**:
```bash
# 1. Remove duplicate .js test files when .ts versions exist
find . -name "*.test.js" -exec rm {} \; 2>/dev/null || true
find . -name "*TestUtils.js" -exec rm {} \; 2>/dev/null || true

# 2. Fix missing icon imports in HomePage.tsx
# Add: import { LogIn, Plus, Gamepad2 } from 'lucide-react';

# 3. Fix type issues in gameStateReducer.ts
# Change: audioUrl = round.audioUrl || round.trackUrl || null;

# 4. Remove unused imports
# Example: Remove unused useState, useCallback, waitFor imports

# 5. Verify fix
cd frontend && npm run build
```

**Prevention**: Run `npm run build` regularly during development

### 2. Cloudflare HTTPS Integration Issues

**Issue**: "Cannot GET /" when visiting domain, but API works
```bash
curl https://tunetussle.com/          # Returns "Cannot GET /"
curl https://tunetussle.com/api/health # Works perfectly
```

**Root Cause**: Cloudflare terminates SSL and sends HTTP to server, but Caddy configuration issues

#### **Subissue 2a: Caddy Automatic TLS Management Conflicts**

**Symptoms**: 
- Configuration looks correct but frontend still returns 404
- Caddy logs show automatic TLS management:
```
{"level":"info","logger":"http.auto_https","msg":"enabling automatic HTTP->HTTPS redirects"}
{"level":"info","logger":"http","msg":"enabling automatic TLS certificate management","domains":["tunetussle.com"]}
```

**Root Cause**: Caddy automatically enables TLS management when it detects domain names, conflicting with Cloudflare's SSL termination.

**Solution**: Ensure Caddyfile doesn't trigger automatic TLS
```bash
# Check if Caddy is trying to manage TLS
ssh -p 2222 admin@69.164.209.52 "sudo tail -20 /var/log/caddy.log | grep -i tls"

# If you see TLS management messages, restart Caddy with correct config
ssh -p 2222 admin@69.164.209.52 "sudo rc-service caddy restart"

# Verify fix
curl -s https://tunetussle.com/ | grep "TuneTussle"
```

**Solution - Final Working Caddyfile**:
```caddyfile
tunetussle.com {
    # Accept HTTP from Cloudflare (which terminates SSL)
    root * /opt/tunetussle/current/frontend/dist
    
    # Handle API requests first (order matters!)
    handle /api/* {
        reverse_proxy localhost:4000
    }
    
    # Handle Socket.IO requests  
    handle /socket.io/* {
        reverse_proxy localhost:4000
    }
    
    # Handle frontend with SPA routing
    handle {
        try_files {path} /index.html
        file_server
    }
    
    # Security headers
    header {
        -Server
        X-Content-Type-Options nosniff
        X-Frame-Options DENY
        X-XSS-Protection "1; mode=block"
    }
    
    encode gzip
}

www.tunetussle.com {
    redir https://tunetussle.com{uri}
}
```

**Key Points**:
- **Order matters**: Handle API routes before file_server
- **Don't use `tls off`**: Cloudflare handles SSL termination
- **Use `handle` blocks**: Prevents routing conflicts

### 3. Alpine Linux OpenRC Service Issues

**Issue**: systemd service scripts don't work on Alpine Linux
```bash
systemctl start tunetussle  # Command not found
```

**Root Cause**: Alpine uses OpenRC, not systemd

**Solution - OpenRC Service Script** (`/etc/init.d/tunetussle`):
```bash
#!/sbin/openrc-run

name=tunetussle
description="TuneTussle - Real-time Music Quiz Game"

user="appuser"
group="appgroup"
pidfile="/var/run/${name}.pid"
command="/usr/bin/node"
command_args="dist/index.js"
command_background="yes"
directory="/opt/tunetussle/current/backend"

depend() {
    need net
    use redis
}

start_pre() {
    checkpath --directory --owner $user:$group --mode 0755 /var/run
    export NODE_ENV=production
    export PORT=4000
}
```

**Commands**:
```bash
# Install and enable service
sudo cp tunetussle-service /etc/init.d/tunetussle
sudo chmod +x /etc/init.d/tunetussle
sudo rc-update add tunetussle default

# Control service
sudo rc-service tunetussle start
sudo rc-service tunetussle status
sudo rc-service tunetussle restart
```

### 4. Memory Issues During Compilation

**Issue**: TypeScript compilation fails with out-of-memory errors
```bash
FATAL ERROR: Ineffective mark-compacts near heap limit Allocation failed - JavaScript heap out of memory
```

**Root Cause**: ts-node is memory-intensive on small servers

**Solution**:
```bash
# Option 1: Build locally and upload (recommended)
cd backend
npm run build:prod
tar -czf dist.tar.gz dist/
scp -P 2222 dist.tar.gz admin@server:/tmp/

# Option 2: Increase memory limit (if building on server)
NODE_OPTIONS='--max-old-space-size=1024' npx tsc

# Option 3: Use compiled JavaScript in service (not ts-node)
# command_args="dist/index.js"  # Not "node_modules/.bin/ts-node src/index.ts"
```

### 5. File Permissions Issues

**Issue**: Caddy can't serve frontend files
```bash
curl http://localhost/  # Returns empty response
```

**Root Cause**: Incorrect file permissions for web server

**Solution**:
```bash
# Fix frontend file permissions
sudo chmod -R 755 /opt/tunetussle/current/frontend/dist/
sudo chmod 644 /opt/tunetussle/current/frontend/dist/*.html
sudo chown -R appuser:appgroup /opt/tunetussle/current/
```

### 6. Backend API 404 Errors

**Issue**: API endpoints return 404 when accessed through Caddy
```bash
curl http://localhost/api/health      # 404 Not Found
curl http://localhost:4000/api/health # Works directly
```

**Root Cause**: Caddy routing order - file_server catches all requests before reverse_proxy

**Solution**: Use `handle` blocks with proper order:
```caddyfile
# WRONG - file_server catches everything first
root * /frontend/dist
reverse_proxy /api/* localhost:4000
file_server

# CORRECT - API routes handled first
handle /api/* {
    reverse_proxy localhost:4000
}
handle {
    root * /frontend/dist
    try_files {path} /index.html
    file_server
}
```

### 7. Socket.IO Connection Timeout

**Issue**: API works but Socket.IO connection fails with timeout
```bash
# Frontend console error:
[SocketProvider] Socket connection timeout. Backend may not be running or is unreachable. Error: timeout
```

**Root Cause**: Frontend trying to connect to `https://tunetussle.com:4000` instead of `https://tunetussle.com`

**Symptoms**:
- Game creation works (API is functional)
- Socket.IO connection times out
- Real-time features don't work

**Solution**: Configure correct Socket.IO URL in frontend environment
```bash
# Create/update frontend production environment
echo 'VITE_SOCKET_IO_URL=https://tunetussle.com' > /opt/tunetussle/current/frontend/.env.production

# Rebuild frontend with correct environment
cd /opt/tunetussle/current/frontend
npm run build

# Restart Caddy to serve updated files
sudo rc-service caddy restart
```

**Related Issue**: Frontend may also call wrong API URLs
```bash
# If you see API calls to localhost:4000 in production, also add:
echo -e 'VITE_API_BASE_URL=/api\nVITE_SOCKET_IO_URL=https://tunetussle.com' > /opt/tunetussle/current/frontend/.env.production

# CRITICAL: Rebuild frontend after changing environment files
cd /opt/tunetussle/current/frontend
npm run build

# Restart Caddy to serve updated files
sudo rc-service caddy restart
```

**Important**: Vite bundles environment variables at build time, so the frontend must be rebuilt after any `.env.production` changes.

**Verification**:
```bash
# Test Socket.IO endpoint directly
curl -s "https://tunetussle.com/socket.io/?EIO=4&transport=polling" | grep '"sid"'
# Should return: 0{"sid":"xyz123","upgrades":["websocket"],...}

# Check frontend Socket.IO configuration
grep VITE_SOCKET_IO_URL /opt/tunetussle/current/frontend/.env.production
# Should show: VITE_SOCKET_IO_URL=https://tunetussle.com
```

**Prevention**: Updated deployment script automatically creates correct `.env.production` file

## 🔍 Diagnostic Commands

### Quick Health Check
```bash
# Check all services
sudo rc-service tunetussle status
sudo rc-service caddy status

# Test API directly
curl http://localhost:4000/api/performance/health

# Test through web server
curl http://localhost/api/performance/health

# Check frontend serving
curl -I http://localhost/
```

### Log Analysis
```bash
# Application logs (if logging configured)
sudo tail -f /var/log/tunetussle/app.log

# Caddy access logs
sudo tail -f /var/log/caddy/access.log

# System messages
sudo tail -f /var/log/messages | grep tunetussle
```

### Memory Debugging
```bash
# Check memory usage
free -h
ps aux | grep node
ps aux | grep caddy

# Monitor during deployment
top -u appuser
```

### Network Debugging
```bash
# Check listening ports
netstat -tlnp | grep -E '(80|443|4000)'

# Test connectivity
curl -v http://localhost:4000/api/performance/health
curl -v -H 'Host: tunetussle.com' http://localhost/
```

## 🛠 Prevention Strategies

### 1. Pre-Deployment Checklist
```bash
# Frontend checks
cd frontend
npm run build          # Must succeed with no errors
npm run test           # All tests passing

# Backend checks  
cd backend
npm test               # All tests passing
npm run build:prod     # TypeScript compilation successful

# Environment checks
grep -v '^#' backend/.env | grep 'OPENROUTER_API_KEY'  # API key present
```

### 2. Development Best Practices
- **Always test builds locally** before deploying
- **Run TypeScript in strict mode** during development
- **Use consistent file naming** (avoid mixing .js/.ts)
- **Remove unused imports** regularly
- **Test API routes** with both direct and proxied access

### 3. Deployment Validation
```bash
# After deployment, verify:
curl -s https://tunetussle.com/ | grep "TuneTussle"                    # Frontend loads
curl -s https://tunetussle.com/api/performance/health | grep "status"  # API responds
curl -s https://tunetussle.com/nonexistent | grep "TuneTussle"         # SPA routing works
```

## 📚 Related Documentation

- **[Deployment Guide](DEPLOYMENT_GUIDE.md)** - Complete deployment instructions
- **[Alpine Production](PRODUCTION_ALPINE_CONSIDERATIONS.md)** - Alpine-specific details
- **[New Developer Guide](NEW_DEVELOPER_GUIDE.md)** - Getting started guide

## 🆘 Emergency Recovery

### Complete Service Reset
```bash
# Stop all services
sudo rc-service tunetussle stop
sudo rc-service caddy stop

# Check what's running
ps aux | grep -E '(node|caddy)'

# Restart in order
sudo rc-service caddy start
sudo rc-service tunetussle start

# Verify
curl -s https://tunetussle.com/api/performance/health
```

### Configuration Backup/Restore
```bash
# Backup current config
sudo cp /etc/caddy/Caddyfile /etc/caddy/Caddyfile.backup
sudo cp /etc/init.d/tunetussle /etc/init.d/tunetussle.backup

# Restore from backup
sudo cp /etc/caddy/Caddyfile.backup /etc/caddy/Caddyfile
sudo rc-service caddy restart
```

---

**💡 Pro Tip**: Most deployment issues are either TypeScript compilation problems or Caddy routing configuration. Fix TypeScript first, then test routing order. 