#!/bin/bash
# This script starts the frontend and backend servers for TuneTussle.
# By default, uses production builds for better memory efficiency.
# Use --dev flag for development mode with ts-node.

# Always run from the TuneTussle project root, regardless of where the script is called from
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Verify we're in the correct directory by checking for expected subdirectories
if [ ! -d "./backend" ] || [ ! -d "./frontend" ]; then
    echo "❌ ERROR: This script must be run from the TuneTussle project root directory."
    echo "   Expected to find ./backend and ./frontend directories."
    echo "   Current directory: $(pwd)"
    echo "   Script location: $SCRIPT_DIR"
    exit 1
fi

echo "📁 Running from TuneTussle directory: $(pwd)"

# Check for development mode flag
DEV_MODE=false
if [ "$1" == "--dev" ]; then
  DEV_MODE=true
  shift # Remove --dev from arguments
fi

# Helper to extract a variable from a .env file
get_env_var() {
  local file=$1
  local var=$2
  if [ -f "$file" ]; then
    # Read the last matching line, remove leading/trailing quotes if any
    local val=$(grep -E "^$var=" "$file" | tail -n1 | cut -d'=' -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")
    echo "$val"
  fi
}

# === Configuration ===
# Intended backend port. Ensure your backend's .env or config uses this.
DESIRED_BACKEND_PORT=4000 
FRONTEND_ENV_FILE=./frontend/.env # Vite typically uses .env in its own root

# Read frontend port from its .env or default
FRONTEND_PORT=$(get_env_var $FRONTEND_ENV_FILE VITE_PORT)
if [ -z "$FRONTEND_PORT" ]; then FRONTEND_PORT=5173; fi

# Check backend's .env for awareness, but script will prioritize DESIRED_BACKEND_PORT for killing/startup
BACKEND_ENV_FILE=./backend/.env
BACKEND_PORT_FROM_ENV=$(get_env_var $BACKEND_ENV_FILE PORT)

if [ -n "$BACKEND_PORT_FROM_ENV" ] && [ "$BACKEND_PORT_FROM_ENV" -ne "$DESIRED_BACKEND_PORT" ]; then
  echo "⚠️ WARNING: ./backend/.env specifies PORT=$BACKEND_PORT_FROM_ENV, but this script is configured for $DESIRED_BACKEND_PORT."
  echo "The script will attempt to kill processes on $DESIRED_BACKEND_PORT and start the backend on $DESIRED_BACKEND_PORT."
  echo "Please ensure your backend application (src/index.ts or utils/envConfig.ts) is also set to use $DESIRED_BACKEND_PORT."
fi
# Use the desired port for script operations
BACKEND_PORT=$DESIRED_BACKEND_PORT
# === End Configuration ===

# Function to kill processes on a specific port (all interfaces)
kill_process_on_port() {
  local port=$1
  local name=$2
  echo "🔍 Checking for $name processes on port $port..."
  
  local pids_found=0
  local attempts=0
  local max_attempts=3 # Try to kill for a few attempts

  while [ $attempts -lt $max_attempts ]; do
    # lsof to find PIDs listening on the TCP port. -Pn avoids name resolution, -sTCP:LISTEN filters for listening sockets.
    local pids=$(lsof -tiTCP:"$port" -sTCP:LISTEN -Pn)
    
    if [ -z "$pids" ]; then
      if [ $pids_found -eq 0 ]; then # Never found any PID
          echo "ℹ️ No existing $name process found listening on port $port."
      else # Found PIDs previously, but now they are gone
          echo "✅ Successfully stopped $name process(es) on port $port."
      fi
      return 0 # Success or nothing to do
    fi

    pids_found=1 # Mark that we've found a PID at least once
    echo "🔄 Found existing $name process(es) on port $port (PID(s): $pids). Attempt #$((attempts + 1)) to stop..."
    
    # Show process tree for these PIDs to identify potential supervisors like nodemon
    echo "  Process details (PID, PPID, Command):"
    for pid in $pids; do
      ps -o pid,ppid,command -p $pid | tail -n +2 # tail to skip header
    done

    # Attempt to kill the found PIDs
    # Send SIGTERM first (graceful shutdown)
    kill $pids > /dev/null 2>&1
    sleep 0.5 # Shorter sleep, iterate faster
    
    # Check if still alive
    local still_alive_pids_term=$(lsof -tiTCP:"$port" -sTCP:LISTEN -Pn)
    if [ -n "$still_alive_pids_term" ]; then
        echo "  ⏳ Process(es) $still_alive_pids_term still active after SIGTERM. Forcing stop with SIGKILL (kill -9)..."
        kill -9 $still_alive_pids_term > /dev/null 2>&1
        sleep 0.5 # Give a moment for SIGKILL
    fi
    
    # Check again
    local final_check_pids=$(lsof -tiTCP:"$port" -sTCP:LISTEN -Pn)
    if [ -z "$final_check_pids" ]; then
        echo "✅ Successfully stopped $name process(es) on port $port (was PID(s): $pids)."
        return 0 # Success
    fi
    
    echo "  ⚠️ Processes $final_check_pids still active on port $port."
    attempts=$((attempts + 1))
    if [ $attempts -lt $max_attempts ]; then
      echo "  Retrying in a moment..."
      sleep 1
    fi
  done

  echo "❌ ERROR: Failed to stop $name process(es) on port $port (last seen as PID(s): $final_check_pids) after $max_attempts attempts."
  echo "  The process might be managed by a supervisor (like nodemon) that's restarting it."
  echo "  If 'nodemon' is in the command list above, you might need to stop the 'npm run dev' command or its parent shell."
  echo "  You may need to stop them manually (e.g., using 'kill -9 $final_check_pids' and potentially its parent PID)."
  return 1 # Failure
}

# Check for --stop flag
if [ "$1" == "--stop" ]; then
  echo "🛑 Stopping all relevant servers..."
  kill_process_on_port $BACKEND_PORT "Backend"
  kill_process_on_port $FRONTEND_PORT "Frontend"
  # Check exit status of kill_process_on_port if needed
  echo "✅ Stop script finished." # This doesn't mean all were stopped, check messages above
  exit 0
fi

# Kill any existing servers on the target ports
echo "🧹 Pre-flight check: Ensuring target ports are free..."
if ! kill_process_on_port $BACKEND_PORT "Backend"; then
    echo "⚠️ Could not ensure backend port $BACKEND_PORT is free. Startup might fail."
fi
if ! kill_process_on_port $FRONTEND_PORT "Frontend"; then
    echo "⚠️ Could not ensure frontend port $FRONTEND_PORT is free. Startup might fail."
fi

# Start backend server
if [ "$DEV_MODE" = true ]; then
  echo "🚀 Starting backend server in DEVELOPMENT mode on port $BACKEND_PORT (using ts-node)..."
  (cd ./backend && PORT=$BACKEND_PORT npm run start:dev &)
else
  echo "🚀 Starting backend server in PRODUCTION mode on port $BACKEND_PORT (building first)..."
  echo "   Building TypeScript to optimized JavaScript..."
  (cd ./backend && npm run build:prod)
  if [ $? -ne 0 ]; then
    echo "❌ Backend build failed. Exiting."
    exit 1
  fi
  echo "   Starting optimized server with memory limits..."
  (cd ./backend && PORT=$BACKEND_PORT npm run start:prod &)
fi

# Start frontend server
# Vite uses --port or VITE_PORT from .env in frontend/.env
echo "🚀 Starting frontend (Vite) development server on port $FRONTEND_PORT..."
(cd ./frontend && npm run dev -- --port $FRONTEND_PORT &)

# Output the frontend server URL
# Find the LAN IP address (IPv4, non-loopback, non-docker)
LAN_IP=$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || hostname -I | awk '{print $1}')
if [ -z "$LAN_IP" ]; then
  LAN_IP=$(hostname -I | awk '{print $1}')
fi

echo "---------------------------------------------------------------------"
echo "Frontend should be available at: http://localhost:$FRONTEND_PORT"
if [ -n "$LAN_IP" ]; then
  echo "Or, on your local network:   http://$LAN_IP:$FRONTEND_PORT"
  echo "Open this address on your phone/tablet (must be on the same WiFi)."
else
  echo "Could not automatically determine LAN IP. Use 'ifconfig' or 'ipconfig' to find your IP address."
fi
echo "If servers did not start, check for errors above or in their respective logs."
echo "Press Ctrl+C in this terminal to stop viewing this script's messages (servers will keep running in background)."
echo ""
echo "Usage options:"
echo "  ./run.sh           # Start in production mode (default, memory optimized)"
echo "  ./run.sh --dev     # Start in development mode (ts-node, higher memory)"
echo "  ./run.sh --stop    # Stop all servers"
echo "---------------------------------------------------------------------" 