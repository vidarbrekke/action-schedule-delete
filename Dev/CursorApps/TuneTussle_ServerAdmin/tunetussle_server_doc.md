# TuneTussle Server Documentation

## 1. Server Information
- **IP Address:** 69.164.209.52  
- **Domain:** tunetussle.com  
- **Server Specs:** 1 CPU Core, 25 GB Storage, 1 GB RAM  
- **OS:** Alpine Linux 3.2  

## 2. User Accounts & SSH Access
- **admin user:** `admin`  
  - Full sudo privileges (configured for passwordless operation via `/etc/sudoers.d/01_admin_nopasswd`)  
  - SSH key authentication required (password auth disabled)  

### Connecting to the Server
```bash
# Default SSH (after setup, port is changed)
ssh -p 2222 admin@69.164.209.52

# With a specific key (after setup, port is changed)
ssh -p 2222 -i /Users/vidarbrekke/Dev/socialintent/tunetussle.pem admin@69.164.209.52
```

### Emergency Access Procedure
If SSH key access is lost:
1. Log in to Linode's console or enter rescue mode.  
2. Reset root password or mount filesystem.  
3. Edit `/etc/ssh/sshd_config`, set `PasswordAuthentication yes`.  
4. Restart SSH: `sudo rc-service sshd restart`.  
5. Recreate SSH keys, disable password auth, and restart SSH again.

---

## 3. Core Package Installs

```sh
apk update && apk add --no-cache \
  nftables            # Firewall (nftables) \
  sshguard            # SSH brute-force protection \
  aide                # File-integrity monitoring \
  restic              # Backup tooling \
  certbot             # Let's Encrypt TLS \
  trivy               # Vulnerability scanner \
  redis               # In-memory DB w/ persistence
```

---

## 4. SSH & User Hardening

1. **SSH Config**: edit `/etc/ssh/sshd_config`
   ```conf
   Port 2222
   PermitRootLogin no
   PasswordAuthentication no
   ChallengeResponseAuthentication no
   X11Forwarding no
   # UsePAM no # Or ensure this line is removed/commented, as it's not standard on Alpine
   ```
2. **Service User**:
   ```sh
   addgroup -S appgroup
   adduser -S -G appgroup appuser
   ```
3. Ensure all services (Node, Redis, etc.) run under non-root users.

---

## 5. Firewall Setup (nftables)

**/etc/nftables.nft**
```nft
table inet filter {
  chain input {
    type filter hook input priority 0; policy drop;
    ct state established,related accept
    iif "lo" accept
    tcp dport { 2222, 80, 443 } accept
    ip saddr 127.0.0.1 tcp dport 6379 accept
    counter drop
  }
}
```
```sh
rc-update add nftables default
rc-service nftables start
```
> **Note:** On Alpine Linux, the nftables service loads `/etc/nftables.nft` by default. If you want to use a different file, update `/etc/init.d/nftables` accordingly.

---

## 6. Kernel & Network Hardening (sysctl)

Add to `/etc/sysctl.conf`:
```conf
net.ipv4.ip_forward = 0
net.ipv4.conf.all.accept_source_route = 0
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.all.secure_redirects = 1
net.ipv4.tcp_syncookies = 1
```
```sh
sysctl -p
```

---

## 7. Intrusion Detection (AIDE)

```sh
aide --init
mv /var/lib/aide/aide.db.new /var/lib/aide/aide.db
```
Add to `/etc/crontabs/root`:
```
0 3 * * * /usr/bin/aide --check | mail -s "AIDE Report" root
```

---

## 8. Redis Persistence

Edit `/etc/redis.conf`:
```conf
dir /data
save 900 1
appendonly yes
```
- Mount `/data` on a Linode Volume or host directory (owned by `redis`).
```sh
rc-update add redis default
rc-service redis start
```

---

## 9. Backups (Restic + Snapshots)

**Backup script** `/usr/local/bin/backup.sh`:
```sh
#!/bin/sh
export RESTIC_REPOSITORY=s3:https://s3.example.com/backup
export RESTIC_PASSWORD=supersecret
restic backup /etc /home/appuser /data
```
Cron in `/etc/crontabs/root`:
```
30 2 * * * /usr/local/bin/backup.sh
```
Also schedule daily Linode snapshots via Cloud Manager.

---

## 10. TLS Automation (Certbot)

```sh
certbot certonly --standalone -d tunetussle.com
```
Cron:
```
0 4 * * * certbot renew --quiet
```

---

## 11. Vulnerability Scanning (Trivy)

Cron in `/etc/crontabs/root`:
```
0 1 * * 0 trivy rootfs --exit-code 1 --no-progress \
  || echo "Vulnerabilities found" | mail -s "Trivy Report" root
```

---

## 12. Service Management (OpenRC)

```sh
# Check status
rc-service <service> status

# Start/stop service
rc-service <service> start|stop

# Enable at boot
rc-update add <service> default
```

---

## 13. Application Management

```bash
# Navigate to app
cd /path/to/application

# View logs
tail -f logs/app.log

# Restart your Node service
rc-service application restart
```

---

## 14. Health Checks & Monitoring

- **Implement** `/healthz` in your Node app (returns HTTP 200).  
- **Monitor** via external uptime service (e.g. Uptime Robot).

---

## 15. .env File Template

```env
# Server
SERVER_IP=69.164.209.52
DOMAIN=tunetussle.com
OS="Alpine Linux 3.2"

# Admin
ADMIN_USER=admin

# SSH
SSH_PORT=2222

# Redis
REDIS_DIR=/data

# Backup (Restic)
RESTIC_REPOSITORY=s3:https://s3.example.com/backup
RESTIC_PASSWORD=supersecret

# TLS
CERTBOT_DOMAINS="tunetussle.com,www.tunetussle.com"
```

---

## 16. Bootstrap Commands

```sh
# 1. Install all core packages
apk update && apk add --no-cache   nftables sshguard aide restic certbot trivy redis

# 2. SSH hardening & user setup
# (Generate/upload SSH key, edit sshd_config as above)
addgroup -S appgroup
adduser -S -G appgroup appuser

# 3. Enable firewall & services
rc-update add nftables default && rc-service nftables start
rc-update add sshd default
rc-update add sshguard default
rc-update add redis default
```

---

## 17. Caddy Reverse Proxy Setup

Caddy v2.10.0 is installed as the reverse proxy web server. It is managed as an OpenRC service:

- **Service script:** `/etc/init.d/caddy`
- **Config file:** `/etc/caddy/Caddyfile`
- **Log file:** `/var/log/caddy.log`

### Service Management
```sh
# Enable Caddy to start at boot
sudo rc-update add caddy default

# Start/stop/restart/status
sudo rc-service caddy start
sudo rc-service caddy stop
sudo rc-service caddy restart
sudo rc-service caddy status
```

### Default Behavior
- By default, Caddy serves a plain text response on port 80: `Caddy is running! (TuneTussle server)`
- After deploying the application, update `/etc/caddy/Caddyfile` to reverse proxy to your Node.js app or serve your static site as needed.
