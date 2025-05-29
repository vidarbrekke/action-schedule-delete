#!/bin/bash

# TuneTussle Network Test Script
# This script tests that the network configuration is working correctly

echo "🧪 TuneTussle Network Test"
echo "========================="

# Get the current network IP
NETWORK_IP=$(ifconfig | grep "inet " | grep -v 127.0.0.1 | awk '{print $2}' | head -n1)

if [ -z "$NETWORK_IP" ]; then
    echo "❌ Could not detect network IP address"
    exit 1
fi

echo "📍 Testing network IP: $NETWORK_IP"
echo ""

# Test 1: Check if servers are running
echo "🔍 Test 1: Checking if servers are running..."
BACKEND_PID=$(lsof -ti :4000)
FRONTEND_PID=$(lsof -ti :5173)

if [ -z "$BACKEND_PID" ]; then
    echo "❌ Backend server not running on port 4000"
    echo "   Run: ./run.sh"
    exit 1
else
    echo "✅ Backend server running (PID: $BACKEND_PID)"
fi

if [ -z "$FRONTEND_PID" ]; then
    echo "❌ Frontend server not running on port 5173"
    echo "   Run: ./run.sh"
    exit 1
else
    echo "✅ Frontend server running (PID: $FRONTEND_PID)"
fi

echo ""

# Test 2: Test backend API connectivity
echo "🔍 Test 2: Testing backend API connectivity..."
BACKEND_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null "http://$NETWORK_IP:4000/api/admin/llm-config")

if [ "$BACKEND_RESPONSE" = "200" ]; then
    echo "✅ Backend API accessible via network IP"
else
    echo "❌ Backend API not accessible (HTTP $BACKEND_RESPONSE)"
    echo "   Check firewall settings or CORS configuration"
fi

echo ""

# Test 3: Test frontend connectivity
echo "🔍 Test 3: Testing frontend connectivity..."
FRONTEND_RESPONSE=$(curl -s -w "%{http_code}" -o /dev/null "http://$NETWORK_IP:5173")

if [ "$FRONTEND_RESPONSE" = "200" ]; then
    echo "✅ Frontend accessible via network IP"
else
    echo "❌ Frontend not accessible (HTTP $FRONTEND_RESPONSE)"
    echo "   Check if Vite server is configured with host: '0.0.0.0'"
fi

echo ""

# Test 4: Check frontend environment configuration
echo "🔍 Test 4: Checking frontend environment configuration..."
if [ -f "frontend/.env" ]; then
    if grep -q "VITE_SOCKET_IO_URL=http://$NETWORK_IP:4000" frontend/.env; then
        echo "✅ Frontend environment correctly configured"
    else
        echo "⚠️  Frontend environment may need updating"
        echo "   Expected: VITE_SOCKET_IO_URL=http://$NETWORK_IP:4000"
        echo "   Current:"
        cat frontend/.env | grep VITE_SOCKET_IO_URL || echo "   VITE_SOCKET_IO_URL not found"
    fi
else
    echo "⚠️  Frontend .env file not found"
fi

echo ""

# Summary
echo "📱 Network Access Summary"
echo "========================"
echo ""
echo "If all tests passed, your iPhone and other devices can access TuneTussle at:"
echo "🌐 http://$NETWORK_IP:5173"
echo ""
echo "📋 Next Steps:"
echo "1. Connect your iPhone to the same WiFi network"
echo "2. Open Safari and go to: http://$NETWORK_IP:5173"
echo "3. Create a game on your computer, join from your iPhone!"
echo ""
echo "🔧 If tests failed:"
echo "- Run: ./setup-network.sh"
echo "- Check firewall settings (System Preferences → Security & Privacy → Firewall)"
echo "- Restart servers: ./run.sh --stop && ./run.sh"
echo ""
echo "✨ Happy gaming! 🎵" 