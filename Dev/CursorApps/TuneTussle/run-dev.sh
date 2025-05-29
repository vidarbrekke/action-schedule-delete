#!/bin/bash
# Quick development script with auto-reload
# Uses nodemon for backend and standard Vite dev server for frontend
# Higher memory usage but faster iteration

echo "🚀 Starting TuneTussle in DEVELOPMENT mode with auto-reload"
echo "=========================================================="

# Configuration
BACKEND_PORT=4000
FRONTEND_PORT=5173

# Function to kill processes on a specific port
kill_process_on_port() {
  local port=$1
  local name=$2
  echo "🔍 Checking for $name processes on port $port..."
  
  local pids=$(lsof -tiTCP:"$port" -sTCP:LISTEN -Pn)
  
  if [ -z "$pids" ]; then
    echo "ℹ️ No existing $name process found listening on port $port."
    return 0
  fi

  echo "🔄 Found existing $name process(es) on port $port (PID(s): $pids). Stopping..."
  kill $pids > /dev/null 2>&1
  sleep 1
  
  # Force kill if still alive
  local still_alive=$(lsof -tiTCP:"$port" -sTCP:LISTEN -Pn)
  if [ -n "$still_alive" ]; then
    echo "  Forcing stop with SIGKILL..."
    kill -9 $still_alive > /dev/null 2>&1
  fi
  
  echo "✅ Stopped $name process(es) on port $port."
}

# Check for --stop flag
if [ "$1" == "--stop" ]; then
  echo "🛑 Stopping development servers..."
  kill_process_on_port $BACKEND_PORT "Backend"
  kill_process_on_port $FRONTEND_PORT "Frontend"
  echo "✅ Development servers stopped."
  exit 0
fi

# Clean up existing processes
kill_process_on_port $BACKEND_PORT "Backend"
kill_process_on_port $FRONTEND_PORT "Frontend"

# Start backend with nodemon (auto-reload)
echo "🚀 Starting backend with nodemon (auto-reload) on port $BACKEND_PORT..."
(cd ./backend && PORT=$BACKEND_PORT npm run dev &)

# Start frontend with Vite dev server
echo "🚀 Starting frontend with Vite dev server on port $FRONTEND_PORT..."
(cd ./frontend && npm run dev -- --port $FRONTEND_PORT &)

# Wait and show status
echo "⏳ Waiting for servers to initialize..."
sleep 5

echo "---------------------------------------------------------------------"
echo "🔥 DEVELOPMENT MODE with AUTO-RELOAD"
echo "Backend (nodemon): http://localhost:$BACKEND_PORT"
echo "Frontend (Vite):   http://localhost:$FRONTEND_PORT"
echo ""
echo "⚠️  High memory usage expected with ts-node and nodemon"
echo "✅ Files will auto-reload on changes"
echo ""
echo "To stop: ./run-dev.sh --stop"
echo "---------------------------------------------------------------------" 