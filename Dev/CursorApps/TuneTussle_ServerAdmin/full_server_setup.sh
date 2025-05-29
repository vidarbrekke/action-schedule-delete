#!/bin/sh

# Exit on error
set -e

echo "=== TuneTussle Server Setup: Starting ==="

# --- 0. System Update & Prerequisite Tools ---
echo "INFO: Updating package repositories and installing prerequisite tools..."
sudo apk update
sudo apk add --no-cache curl wget git

# --- 1. Core Package Installs (from tunetussle_server_doc.md) ---
echo "INFO: Installing core packages: nftables, sshguard, aide, restic, certbot, trivy, redis..."
sudo apk add --no-cache nftables sshguard aide restic certbot trivy redis

# --- 2. Application Stack Dependencies (from stack.txt) ---
echo "INFO: Installing Node.js (LTS) and npm..."
sudo apk add --no-cache nodejs-lts npm # Alpine 3.20 should have nodejs-lts

echo "INFO: Verifying Node.js, npm, and Git installation..."
node -v
npm -v
git --version

# --- 3. SSH & User Hardening (from tunetussle_server_doc.md) ---
NEW_SSH_PORT="2222" # As per docs
APP_USER="appuser"
APP_GROUP="appgroup"

echo "INFO: Creating application user '$APP_USER' and group '$APP_GROUP'..."
if ! getent group "$APP_GROUP" > /dev/null; then
  sudo addgroup -S "$APP_GROUP"
else
  echo "INFO: Group $APP_GROUP already exists."
fi
if ! id "$APP_USER" > /dev/null 2>&1; then
  sudo adduser -S -G "$APP_GROUP" "$APP_USER"
else
  echo "INFO: User $APP_USER already exists."
fi

echo "INFO: Configuring SSH..."
SSHD_CONFIG="/etc/ssh/sshd_config"
echo "INFO: Backing up $SSHD_CONFIG to $SSHD_CONFIG.orig.$(date +%Y%m%d%H%M%S)..."
sudo cp "$SSHD_CONFIG" "$SSHD_CONFIG.orig.$(date +%Y%m%d%H%M%S)"

echo "INFO: Updating SSH configuration ($SSHD_CONFIG)..."
sudo sed -i "s/^#*Port .*/Port $NEW_SSH_PORT/" "$SSHD_CONFIG"
sudo sed -i "s/^#*PermitRootLogin .*/PermitRootLogin no/" "$SSHD_CONFIG"
sudo sed -i "s/^#*PasswordAuthentication .*/PasswordAuthentication no/" "$SSHD_CONFIG"
sudo sed -i "s/^#*ChallengeResponseAuthentication .*/ChallengeResponseAuthentication no/" "$SSHD_CONFIG"
sudo sed -i "s/^#*X11Forwarding .*/X11Forwarding no/" "$SSHD_CONFIG"

# Ensure settings are present if they were commented out or missing
grep -qxF "Port $NEW_SSH_PORT" "$SSHD_CONFIG" || echo "Port $NEW_SSH_PORT" | sudo tee -a "$SSHD_CONFIG" > /dev/null
grep -qxF "PermitRootLogin no" "$SSHD_CONFIG" || echo "PermitRootLogin no" | sudo tee -a "$SSHD_CONFIG" > /dev/null
grep -qxF "PasswordAuthentication no" "$SSHD_CONFIG" || echo "PasswordAuthentication no" | sudo tee -a "$SSHD_CONFIG" > /dev/null
grep -qxF "ChallengeResponseAuthentication no" "$SSHD_CONFIG" || echo "ChallengeResponseAuthentication no" | sudo tee -a "$SSHD_CONFIG" > /dev/null
grep -qxF "X11Forwarding no" "$SSHD_CONFIG" || echo "X11Forwarding no" | sudo tee -a "$SSHD_CONFIG" > /dev/null
# Remove UsePAM if it was added by a previous version or attempt
if grep -q "UsePAM yes" "$SSHD_CONFIG"; then
    sudo sed -i "/UsePAM yes/d" "$SSHD_CONFIG"
fi

echo "INFO: Validating SSH configuration..."
sudo sshd -t

# --- 3.1. Configure Passwordless Sudo for admin user ---
echo "INFO: Configuring passwordless sudo for the 'admin' user..."
SUDOERS_ADMIN_FILE="/etc/sudoers.d/01_admin_nopasswd"
if [ ! -f "$SUDOERS_ADMIN_FILE" ] || ! sudo grep -qxF "admin ALL=(ALL) NOPASSWD: ALL" "$SUDOERS_ADMIN_FILE"; then
  echo "admin ALL=(ALL) NOPASSWD: ALL" | sudo tee "$SUDOERS_ADMIN_FILE" > /dev/null
  sudo chmod 0440 "$SUDOERS_ADMIN_FILE"
  echo "INFO: Created/Updated $SUDOERS_ADMIN_FILE for passwordless sudo for admin."
  echo "INFO: Validating sudoers configuration..."
  if sudo visudo -c; then
    echo "INFO: Sudoers configuration is valid."
  else
    echo "ERROR: Sudoers configuration is invalid after adding $SUDOERS_ADMIN_FILE. Please check manually!" >&2
    # Optionally, remove the problematic file to prevent lockout, though visudo -c should catch syntax errors before they apply.
    # sudo rm "$SUDOERS_ADMIN_FILE"
    exit 1 # Exit if sudoers is broken
  fi
else
  echo "INFO: Passwordless sudo for 'admin' user already configured in $SUDOERS_ADMIN_FILE."
fi

# --- 4. Firewall Setup (nftables) (from tunetussle_server_doc.md) ---
echo "INFO: Setting up firewall (nftables)..."
NFTABLES_CONF_PATH="/etc/nftables.nft"
echo "INFO: Writing nftables configuration to $NFTABLES_CONF_PATH..."
sudo tee "$NFTABLES_CONF_PATH" > /dev/null << EOF
table inet filter {
  chain input {
    type filter hook input priority 0; policy drop;
    ct state established,related accept;
    iif "lo" accept;
    tcp dport { $NEW_SSH_PORT, 80, 443 } accept;
    ip saddr 127.0.0.1 tcp dport 6379 accept; # Redis for localhost only
    counter drop;
  }
}
EOF

sudo nft -c -f /etc/nftables.nft
sudo rc-service nftables restart
sudo nft list ruleset

# --- 5. Kernel & Network Hardening (sysctl) (from tunetussle_server_doc.md) ---
echo "INFO: Applying kernel and network hardening settings (sysctl)..."
SYSCTL_CONF_PATH="/etc/sysctl.d/99-hardening.conf" # Use /etc/sysctl.d for modularity
sudo mkdir -p /etc/sysctl.d
sudo tee "$SYSCTL_CONF_PATH" > /dev/null << EOF
net.ipv4.ip_forward = 0
net.ipv4.conf.all.accept_source_route = 0
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.all.secure_redirects = 1
net.ipv4.tcp_syncookies = 1
EOF

echo "INFO: Applying new sysctl settings..."
sudo sysctl -p "$SYSCTL_CONF_PATH"

# --- 6. Intrusion Detection (AIDE) (from tunetussle_server_doc.md) ---
echo "INFO: Setting up intrusion detection (AIDE)..."
if [ ! -f /var/lib/aide/aide.db ]; then
  echo "INFO: Initializing AIDE database (this may take a while)..."
  sudo aide --init
  sudo mv /var/lib/aide/aide.db.new /var/lib/aide/aide.db
else
  echo "INFO: AIDE database already exists. Skipping initialization."
fi

AIDE_CRON_FILE="/etc/crontabs/root"
AIDE_CRON_JOB="0 3 * * * /usr/bin/aide --check | mail -s \\\"AIDE Report for TuneTussle\\\" root@localhost"
if ! sudo grep -qF -- "/usr/bin/aide --check" "$AIDE_CRON_FILE"; then
  echo "INFO: Adding AIDE check to root's crontab..."
  echo "$AIDE_CRON_JOB" | sudo tee -a "$AIDE_CRON_FILE" > /dev/null
else
  echo "INFO: AIDE cron job already exists."
fi

# --- 7. Redis Persistence (from tunetussle_server_doc.md) ---
echo "INFO: Configuring Redis persistence..."
REDIS_CONF_PATH="/etc/redis.conf"
REDIS_DATA_DIR="/data/redis" # More specific path under /data

echo "INFO: Backing up $REDIS_CONF_PATH to $REDIS_CONF_PATH.orig.$(date +%Y%m%d%H%M%S)..."
sudo cp "$REDIS_CONF_PATH" "$REDIS_CONF_PATH.orig.$(date +%Y%m%d%H%M%S)"

sudo mkdir -p "$REDIS_DATA_DIR"
sudo chown redis:redis "$REDIS_DATA_DIR"

sudo sed -i "s|^#*dir .*|dir $REDIS_DATA_DIR|" "$REDIS_CONF_PATH"
# Ensure 'save' directive for RDB persistence is set as per docs (900 1)
if sudo grep -q "^#*save [0-9]\\+ [0-9]\\+" "$REDIS_CONF_PATH"; then
    sudo sed -i 's|^#*save [0-9].*|save 900 1|' "$REDIS_CONF_PATH"
else
    echo "save 900 1" | sudo tee -a "$REDIS_CONF_PATH" > /dev/null
fi
# Ensure appendonly is set to 'yes'
if sudo grep -q "^#*appendonly" "$REDIS_CONF_PATH"; then
  sudo sed -i "s/^#*appendonly .*/appendonly yes/" "$REDIS_CONF_PATH"
else
  echo "appendonly yes" | sudo tee -a "$REDIS_CONF_PATH" > /dev/null
fi

# --- 8. Backups (Restic) (from tunetussle_server_doc.md) ---
echo "INFO: Setting up Restic backup script (placeholders for S3)..."
RESTIC_SCRIPT_PATH="/usr/local/bin/backup_tunetussle.sh"
echo "WARNING: Restic S3 repository and password in $RESTIC_SCRIPT_PATH are placeholders. PLEASE UPDATE THEM."
sudo tee "$RESTIC_SCRIPT_PATH" > /dev/null << EOF
#!/bin/sh
export RESTIC_REPOSITORY="s3:https://s3.example.com/backup_tunetussle" # FIXME: Update with your S3 bucket
export RESTIC_PASSWORD_FILE="/etc/restic/restic_password" # FIXME: Create this file with your password

# Ensure password file exists and has correct permissions
if [ ! -f \$RESTIC_PASSWORD_FILE ]; then
  echo "Error: Restic password file \$RESTIC_PASSWORD_FILE not found." >&2
  exit 1
fi
chmod 600 \$RESTIC_PASSWORD_FILE

restic backup /etc /home/$APP_USER $REDIS_DATA_DIR --exclude-caches
restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 3 --prune
EOF
sudo chmod +x "$RESTIC_SCRIPT_PATH"
echo "ACTION REQUIRED: Create /etc/restic/restic_password with your Restic password and set permissions (chmod 600)."

RESTIC_CRON_FILE="/etc/crontabs/root"
RESTIC_CRON_JOB="30 2 * * * $RESTIC_SCRIPT_PATH"
if ! sudo grep -qF -- "$RESTIC_SCRIPT_PATH" "$RESTIC_CRON_FILE"; then
  echo "INFO: Adding Restic backup job to root's crontab..."
  echo "$RESTIC_CRON_JOB" | sudo tee -a "$RESTIC_CRON_FILE" > /dev/null
else
  echo "INFO: Restic cron job already exists."
fi

# --- 9. TLS Automation (Certbot) (from tunetussle_server_doc.md) ---
DOMAIN_NAME="tunetussle.com" # from doc
ADMIN_EMAIL="admin@tunetussle.com" # Placeholder, replace with actual admin email

echo "INFO: Setting up Certbot for TLS (Let's Encrypt) for $DOMAIN_NAME..."
echo "ACTION REQUIRED: Run Certbot manually to obtain the initial certificate after DNS is confirmed:"
echo "  'sudo certbot certonly --standalone -d $DOMAIN_NAME -d www.$DOMAIN_NAME -m $ADMIN_EMAIL --agree-tos --no-eff-email'"
echo "  This requires $DOMAIN_NAME (and www.$DOMAIN_NAME) to point to this server's IP and port 80 to be free."

CERTBOT_CRON_FILE="/etc/crontabs/root"
CERTBOT_CRON_JOB="0 4 * * * certbot renew --quiet"
if ! sudo grep -qF -- "certbot renew" "$CERTBOT_CRON_FILE"; then
  echo "INFO: Adding Certbot renew job to root's crontab..."
  echo "$CERTBOT_CRON_JOB" | sudo tee -a "$CERTBOT_CRON_FILE" > /dev/null
else
  echo "INFO: Certbot renew cron job already exists."
fi

# --- 10. Vulnerability Scanning (Trivy) (from tunetussle_server_doc.md) ---
echo "INFO: Setting up Trivy vulnerability scanning..."
TRIVY_CRON_FILE="/etc/crontabs/root"
TRIVY_CRON_JOB='0 1 * * 0 /usr/bin/trivy rootfs --exit-code 1 --no-progress --scanners vuln,secret,config | mail -s "Trivy Report for TuneTussle" root@localhost'
if ! sudo grep -qF -- "/usr/bin/trivy rootfs" "$TRIVY_CRON_FILE"; then
  echo "INFO: Adding Trivy scan to root's crontab..."
  echo "$TRIVY_CRON_JOB" | sudo tee -a "$TRIVY_CRON_FILE" > /dev/null
else
  echo "INFO: Trivy cron job already exists."
fi

# --- 11. Enable and Restart Key Services ---
echo "INFO: Enabling and (re)starting key services..."
sudo rc-update add sshd default
sudo rc-update add nftables default
sudo rc-update add redis default
sudo rc-update add sshguard default

sudo rc-service sshd restart
sudo rc-service nftables restart # Ensure this is safe; it might drop current non-SSH connections
sudo rc-service redis restart
sudo rc-service sshguard restart

echo ""
echo "=== TuneTussle Server Setup: Completed ==="
echo "IMPORTANT: SSH port has been changed to $NEW_SSH_PORT."
echo "IMPORTANT: You will need to reconnect using: ssh -p $NEW_SSH_PORT admin@<SERVER_IP>"
echo ""
echo "PLEASE REVIEW THE OUTPUT FOR ANY WARNINGS OR REQUIRED MANUAL ACTIONS:"
echo "  - Update Restic S3 repository details and password file ($RESTIC_SCRIPT_PATH, /etc/restic/restic_password)."
echo "  - Run Certbot manually to obtain the initial TLS certificate."
echo "  - Ensure the Redis data directory ($REDIS_DATA_DIR) is on a persistent volume if required."
echo "  - Test all services and configurations."

exit 0 