# Quick Deployment Reference - TuneTussle

**⚡ Essential commands and checks for fast deployment**

## 🚨 Pre-Deployment Checklist (CRITICAL)

```bash
# 1. TypeScript compilation MUST work
cd frontend && npm run build  # Must succeed with 0 errors
cd ../backend && npm run build:prod  # Must succeed

# 2. Tests MUST pass  
cd backend && npm test  # All tests passing
cd ../frontend && npm test  # All tests passing

# 3. Environment MUST be configured
grep OPENROUTER_API_KEY backend/.env  # API key present

# 4. Clean workspace (commit all changes)
git status  # Should be clean for deployment
```

## ⚡ 5-Minute Deployment (If Pre-checks Pass)

```bash
# Deploy to production
./deployment/scripts/deploy-to-alpine.sh

# Verify deployment
curl -s https://tunetussle.com/ | grep "TuneTussle"
curl -s https://tunetussle.com/api/performance/health
```

## 🚨 If Deployment Fails - Troubleshooting Order

### 1. TypeScript Errors (Most Common - 60% of issues)
```bash
# Check for errors
cd frontend && npm run build 2>&1 | grep "error TS"

# Common fixes:
find . -name "*.test.js" -delete  # Remove JS duplicates
# Fix missing imports in HomePage.tsx
# Fix type issues in gameStateReducer.ts
# Remove unused imports

# Verify fix
npm run build  # Must succeed
```

### 2. Cloudflare "Cannot GET /" (25% of issues)
```bash
# Test API first
curl -s https://tunetussle.com/api/performance/health

# If API works but frontend doesn't, check for TLS conflicts
ssh -p 2222 admin@69.164.209.52 "sudo tail -10 /var/log/caddy.log | grep -i tls"

# Fix: Restart Caddy (resolves automatic TLS management conflicts)
ssh -p 2222 admin@69.164.209.52 "sudo rc-service caddy restart"

# Verify fix
curl -s https://tunetussle.com/ | grep "TuneTussle"

# If still broken, fix Caddyfile:
# Ensure: handle /api/* BEFORE handle { file_server }
# Use domain name, not :80
# Don't use 'tls off'
```

### 3. Alpine Linux Service Issues (10% of issues)
```bash
# Check services
ssh -p 2222 admin@69.164.209.52 "sudo rc-service tunetussle status"
ssh -p 2222 admin@69.164.209.52 "sudo rc-service caddy status"

# Restart if needed
ssh -p 2222 admin@69.164.209.52 "sudo rc-service tunetussle restart"
```

### 4. Memory/Build Issues (5% of issues)
```bash
# Build locally and upload if server runs out of memory
cd backend && npm run build:prod
tar -czf dist.tar.gz dist/
scp -P 2222 dist.tar.gz admin@69.164.209.52:/tmp/
```

## 📋 Working Configurations (Copy-Paste Ready)

### Caddyfile for Cloudflare
```caddyfile
tunetussle.com {
    root * /opt/tunetussle/current/frontend/dist
    
    handle /api/* {
        reverse_proxy localhost:4000
    }
    
    handle /socket.io/* {
        reverse_proxy localhost:4000
    }
    
    handle {
        try_files {path} /index.html
        file_server
    }
    
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

### OpenRC Service for Alpine
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

## ✅ Deployment Verification Commands

```bash
# All these MUST work after deployment:

# 1. Frontend loads
curl -s https://tunetussle.com/ | grep "TuneTussle"

# 2. API responds  
curl -s https://tunetussle.com/api/performance/health | grep "status"

# 3. SPA routing works
curl -s https://tunetussle.com/nonexistent | grep "TuneTussle"

# 4. Services running
ssh -p 2222 admin@69.164.209.52 "sudo rc-service tunetussle status && sudo rc-service caddy status"
```

## 🆘 Emergency Recovery

```bash
# Complete service reset
ssh -p 2222 admin@69.164.209.52 "
sudo rc-service tunetussle stop
sudo rc-service caddy stop
sudo rc-service caddy start  
sudo rc-service tunetussle start
"

# Test recovery
curl -s https://tunetussle.com/api/performance/health
```

## 📚 Full Documentation Links

- **🚨 [TROUBLESHOOTING_GUIDE.md](TROUBLESHOOTING_GUIDE.md)** - Complete problem solutions
- **🌐 [CLOUDFLARE_INTEGRATION_GUIDE.md](CLOUDFLARE_INTEGRATION_GUIDE.md)** - Cloudflare setup
- **🔧 [TYPESCRIPT_COMPILATION_GUIDE.md](TYPESCRIPT_COMPILATION_GUIDE.md)** - TypeScript fixes
- **📚 [DEPLOYMENT_LESSONS_LEARNED.md](DEPLOYMENT_LESSONS_LEARNED.md)** - Deployment insights
- **📖 [NEW_DEVELOPER_GUIDE.md](NEW_DEVELOPER_GUIDE.md)** - Complete onboarding

---

**💡 Remember**: 90% of deployment time is spent on TypeScript compilation issues. Always run `npm run build` before deploying! 