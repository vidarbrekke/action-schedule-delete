#!/bin/bash

# TuneTussle Network Setup Script
# This script configures the application for network access from iPhones and other devices

echo "🌐 TuneTussle Network Setup"
echo "=========================="

# Get the current network IP
NETWORK_IP=$(ifconfig | grep "inet " | grep -v 127.0.0.1 | awk '{print $2}' | head -n1)

if [ -z "$NETWORK_IP" ]; then
    echo "❌ Could not detect network IP address"
    echo "Please run 'ifconfig' manually and find your WiFi adapter's IP address"
    exit 1
fi

echo "📍 Detected network IP: $NETWORK_IP"
echo ""

# Backup existing frontend .env if it exists
if [ -f "frontend/.env" ]; then
    echo "💾 Backing up existing frontend/.env to frontend/.env.backup"
    cp frontend/.env frontend/.env.backup
fi

# Create/update frontend .env for network access
echo "🔧 Configuring frontend for network access..."
cat > frontend/.env << EOF
# TuneTussle Frontend Network Configuration
# Updated for network access from iPhones and other devices

VITE_API_BASE_URL=http://$NETWORK_IP:4000/api
VITE_SOCKET_IO_URL=http://$NETWORK_IP:4000
EOF

echo "✅ Frontend configured for network access"

# Check backend configuration
echo ""
echo "🔍 Checking backend configuration..."

if [ -f "backend/.env" ]; then
    if grep -q "HOST=0.0.0.0" backend/.env; then
        echo "✅ Backend is already configured for network access (HOST=0.0.0.0)"
    else
        echo "⚠️  Backend HOST setting may need adjustment"
        echo "   Current HOST setting:"
        grep "HOST=" backend/.env || echo "   HOST not set (will default to 0.0.0.0)"
    fi
else
    echo "⚠️  Backend .env file not found"
fi

echo ""
echo "📱 Network Access Instructions"
echo "=============================="
echo ""
echo "1. Start the servers:"
echo "   ./run.sh"
echo ""
echo "2. On your iPhone/device, connect to the same WiFi network"
echo ""
echo "3. Open Safari (or any browser) and go to:"
echo "   http://$NETWORK_IP:5173"
echo ""
echo "4. You should see the TuneTussle game interface"
echo ""
echo "5. Create a game on your computer (judge interface)"
echo "   Then join from your iPhone using the game code"
echo ""
echo "🔧 Troubleshooting:"
echo "- Make sure both devices are on the same WiFi network"
echo "- Check that your firewall allows connections on ports 4000 and 5173"
echo "- If it doesn't work, try restarting the servers: ./run.sh --stop && ./run.sh"
echo ""
echo "🔄 To revert to localhost-only:"
echo "   cp frontend/.env.backup frontend/.env"
echo ""
echo "✨ Setup complete! Your TuneTussle is now accessible from network devices." 