#!/bin/sh

# Server configuration
SERVER_IP="69.164.209.52"
ADMIN_USERNAME="admin"
ADMIN_PASSWORD="@Flatbygdi73?"
SSH_KEY_PATH="$HOME/.ssh/id_rsa.pub"

# Check for existing SSH key or create new one
if [ ! -f "$SSH_KEY_PATH" ]; then
  echo "No SSH key found, creating a new one..."
  ssh-keygen -t rsa -b 4096 -f "${SSH_KEY_PATH%.*}" -N ""
fi

# Install sshpass if not already installed
which sshpass > /dev/null || { 
  echo "Installing sshpass..."
  brew install sshpass
}

# Copy SSH key to the server
echo "Setting up SSH key authentication..."
sshpass -p "$ADMIN_PASSWORD" ssh-copy-id -o StrictHostKeyChecking=accept-new $ADMIN_USERNAME@$SERVER_IP

# Create a temporary script to run on the server
cat > temp_ssh_config.sh << EOF
#!/bin/sh
# Create backup of SSH config
echo "Creating backup of SSH config..."
sudo -S cp /etc/ssh/sshd_config /etc/ssh/sshd_config.bak

# Modify SSH config to enhance security
echo "Configuring SSH security settings..."

# Disable root login and password authentication
echo "Updating SSH configuration..."
sudo -S sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
sudo -S sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin no/' /etc/ssh/sshd_config

# Add security enhancements at the end of the file
echo "Adding security enhancements..."
echo "
# Security enhancements
PermitRootLogin no
PasswordAuthentication no
ChallengeResponseAuthentication no
# UsePAM yes # Commented out for Alpine Linux compatibility
X11Forwarding no
AllowAgentForwarding yes
AllowTcpForwarding yes
PrintMotd no
" | sudo -S tee -a /etc/ssh/sshd_config > /dev/null

# Ensure UsePAM yes is removed if present (for Alpine compatibility and consistency)
if sudo -S grep -q "UsePAM yes" /etc/ssh/sshd_config; then
    echo "INFO: Removing 'UsePAM yes' from sshd_config for Alpine compatibility..."
    sudo -S sed -i "/UsePAM yes/d" /etc/ssh/sshd_config
fi

# Restart SSH service
echo "Restarting SSH service..."
sudo -S /etc/init.d/sshd restart
EOF

# Copy the script to the server and run it
echo "Uploading configuration script to server..."
scp temp_ssh_config.sh $ADMIN_USERNAME@$SERVER_IP:~/temp_ssh_config.sh

echo "Executing configuration script on server..."
ssh $ADMIN_USERNAME@$SERVER_IP "chmod +x ~/temp_ssh_config.sh && echo '$ADMIN_PASSWORD' | ~/temp_ssh_config.sh && rm ~/temp_ssh_config.sh"

# Clean up local temporary file
rm temp_ssh_config.sh

echo "✅ SSH security setup completed successfully!"
echo ""
echo "🔒 Security measures implemented:"
echo "  1. SSH key-based authentication enabled"
echo "  2. Password authentication disabled"
echo "  3. Root login disabled"
echo ""
echo "⚠️ IMPORTANT: From now on, you can only connect using your SSH key."
echo "Connect with: ssh $ADMIN_USERNAME@$SERVER_IP" 