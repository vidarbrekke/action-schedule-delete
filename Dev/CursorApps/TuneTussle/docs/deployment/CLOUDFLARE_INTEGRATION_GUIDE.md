# Cloudflare Integration Guide for TuneTussle

**🌐 Complete guide for deploying TuneTussle with Cloudflare proxy**

This guide covers the specific configuration needed when your domain is proxied through Cloudflare, based on real deployment experience.

## 🔍 Understanding Cloudflare Proxy

### How Cloudflare Works with Your Server
```
User Browser → Cloudflare Edge → Your Alpine Server
     HTTPS    →      HTTP      →    HTTP (port 80)
```

**Key Points**:
- **Cloudflare terminates SSL** and sends HTTP to your server
- **Your server receives HTTP requests** with special headers
- **Domain requests go to port 80**, not 443 on your server
- **Cloudflare handles SSL certificates** automatically

### Headers Cloudflare Sends
```
Host: tunetussle.com
X-Forwarded-Proto: https
X-Forwarded-For: [user-ip]
Cf-Connecting-Ip: [user-ip]
Cf-Ray: [request-id]
Cf-Visitor: {"scheme":"https"}
```

## ⚙️ Correct Caddy Configuration

### ✅ Working Configuration (Final)
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
    
    # Compression
    encode gzip
}

# Redirect www to non-www
www.tunetussle.com {
    redir https://tunetussle.com{uri}
}
```

### ❌ Common Mistakes to Avoid

**Don't use `tls off`**:
```caddyfile
# WRONG - causes routing issues
tunetussle.com {
    tls off  # ❌ Don't do this
    # ...
}
```

**Don't mix port configurations**:
```caddyfile
# WRONG - conflicts with domain
:80 {
    # ...
}
tunetussle.com {
    # ...
}
```

**Don't put file_server before API routes**:
```caddyfile
# WRONG - file_server catches API requests
tunetussle.com {
    file_server
    reverse_proxy /api/* localhost:4000  # ❌ Never reached
}
```

## 🛠 Cloudflare DNS Configuration

### Required DNS Settings
```
Type: A
Name: tunetussle.com
Value: 69.164.209.52
Proxy: ✅ Proxied (orange cloud)
TTL: Auto

Type: CNAME  
Name: www
Value: tunetussle.com
Proxy: ✅ Proxied (orange cloud)
TTL: Auto
```

### SSL/TLS Settings in Cloudflare
1. **SSL/TLS > Overview**: Set to "Flexible" or "Full"
2. **SSL/TLS > Edge Certificates**: 
   - Universal SSL: ✅ Enabled
   - Always Use HTTPS: ✅ Enabled
   - HSTS: ✅ Enabled (optional)

## 🔧 Testing Your Configuration

### 1. Test API Endpoints
```bash
# Should return JSON health status
curl -s https://tunetussle.com/api/performance/health

# Expected response:
# {"status":"warning","issues":["WARNING: CPU usage at 70%"],...}
```

### 2. Test Frontend Serving
```bash
# Should return HTML with TuneTussle
curl -s https://tunetussle.com/ | grep "TuneTussle"

# Expected: <title>TuneTussle - The Music Quiz Game</title>
```

### 3. Test SPA Routing (React Router)
```bash
# Should return same HTML as root (not 404)
curl -s https://tunetussle.com/nonexistent-page | grep "TuneTussle"

# Expected: Same HTML as root (React will handle routing)
```

### 4. Test www Redirect
```bash
# Should redirect to non-www
curl -I https://www.tunetussle.com/

# Expected: HTTP/2 301 Location: https://tunetussle.com/
```

## 🚨 Troubleshooting Cloudflare Issues

### Issue: "Cannot GET /" Error
**Symptoms**: 
- Domain shows "Cannot GET /"
- API endpoints work fine
- Direct server IP works

**Diagnosis**:
```bash
# Check if Caddy is receiving requests
ssh -p 2222 admin@69.164.209.52 "sudo tail -f /var/log/caddy/access.log"

# Test local server directly
ssh -p 2222 admin@69.164.209.52 "curl -H 'Host: tunetussle.com' http://localhost/"
```

**Solution**: Fix Caddy routing order (API before file_server)

### Issue: Caddy Automatic TLS Management Conflicts ⚠️ **NEW**

**Symptoms**:
- Caddyfile looks correct but still getting 404 errors
- Caddy logs show automatic HTTPS management:
```bash
# Check Caddy logs for TLS management
sudo tail -20 /var/log/caddy.log | grep -E "(auto_https|TLS|certificate)"
```

**Root Cause**: Caddy automatically enables TLS certificate management when it detects domain names, even with Cloudflare proxy.

**Immediate Fix**:
```bash
# Restart Caddy to reset TLS management
sudo rc-service caddy restart

# Verify frontend is working
curl -s https://tunetussle.com/ | grep "TuneTussle"
```

**Long-term Prevention**: Ensure Caddyfile configuration doesn't trigger automatic TLS

### Issue: SSL/TLS Errors
**Symptoms**:
- "SSL handshake failed"
- "Too many redirects"
- "Certificate errors"

**Solution**:
1. Set Cloudflare SSL to "Flexible" 
2. Remove `tls` directives from Caddyfile
3. Let Cloudflare handle all SSL

### Issue: API 404 Through Domain
**Symptoms**:
- `curl https://tunetussle.com/api/health` returns 404
- `curl http://server-ip:4000/api/health` works

**Solution**: Check routing order in Caddyfile - use `handle` blocks

## 📊 Monitoring Cloudflare Integration

### Cloudflare Analytics
Check these metrics in Cloudflare dashboard:
- **Requests**: Should show traffic to your domain
- **Bandwidth**: Should show data transfer
- **Cache Ratio**: Static assets should be cached
- **Response Time**: Should be low (edge caching)

### Server-Side Monitoring
```bash
# Check requests reaching your server
sudo tail -f /var/log/caddy/access.log | grep -E '(tunetussle\.com|api)'

# Monitor backend performance
curl -s https://tunetussle.com/api/performance/health | jq .

# Check service status
sudo rc-service caddy status
sudo rc-service tunetussle status
```

## 🔄 Deployment Process with Cloudflare

### Standard Deployment
```bash
# 1. Deploy to server (same process)
./deployment/scripts/deploy-to-alpine.sh

# 2. Test server directly
ssh -p 2222 admin@69.164.209.52 "curl http://localhost:4000/api/performance/health"

# 3. Test through Cloudflare
curl -s https://tunetussle.com/api/performance/health

# 4. Clear Cloudflare cache if needed
# (Use Cloudflare dashboard > Caching > Purge Cache)
```

### Cache Management
```bash
# Cloudflare caches static assets automatically
# To force refresh after deployment:

# Option 1: Purge cache via API (if you have API key)
curl -X POST "https://api.cloudflare.com/client/v4/zones/ZONE_ID/purge_cache" \
     -H "Authorization: Bearer API_TOKEN" \
     -H "Content-Type: application/json" \
     --data '{"purge_everything":true}'

# Option 2: Use Cloudflare dashboard
# Go to Caching > Purge Cache > Purge Everything
```

## 🛡 Security Considerations

### Cloudflare Security Features
Enable these in Cloudflare dashboard:
- **DDoS Protection**: Automatic
- **Web Application Firewall (WAF)**: Recommended
- **Rate Limiting**: For API endpoints
- **Bot Fight Mode**: Helps prevent abuse

### Server-Side Security
Your server still needs:
- **Firewall**: Only ports 22 (SSH), 80 (HTTP) open
- **SSH Key Authentication**: Password auth disabled
- **Regular Updates**: Alpine packages

### Headers Configuration
Cloudflare + Caddy provides these security headers:
```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY  
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000 (from Cloudflare)
```

## 📋 Cloudflare Integration Checklist

- [ ] DNS A record pointing to server IP (69.164.209.52)
- [ ] DNS CNAME for www pointing to root domain
- [ ] Cloudflare proxy enabled (orange cloud)
- [ ] SSL/TLS set to "Flexible" or "Full"
- [ ] Caddy configured with domain name (not :80)
- [ ] API routes handled before file_server in Caddyfile
- [ ] Frontend files have correct permissions
- [ ] Services running: `sudo rc-service caddy status && sudo rc-service tunetussle status`
- [ ] Frontend test: `curl -s https://tunetussle.com/ | grep TuneTussle`
- [ ] API test: `curl -s https://tunetussle.com/api/performance/health`
- [ ] SPA routing test: `curl -s https://tunetussle.com/fake-page | grep TuneTussle`

---

**💡 Key Insight**: With Cloudflare, your server receives HTTP requests on port 80, even though users access via HTTPS. Configure Caddy for the domain name, not port numbers. 