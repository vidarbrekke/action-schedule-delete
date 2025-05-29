#!/bin/bash
# ─────────────────────────────────────────────────────────────
# start-production.sh — Production Startup Script for 1GB Servers
# Builds and runs TuneTussle with memory optimization
# ─────────────────────────────────────────────────────────────

set -e

echo "🚀 Starting TuneTussle in Production Mode (1GB Optimized)"
echo "========================================================="

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the project root
if [ ! -f "package.json" ] || [ ! -d "backend" ] || [ ! -d "frontend" ]; then
    log_error "Please run this script from the TuneTussle project root directory"
    exit 1
fi

# Stop any existing servers
log_info "Stopping existing servers..."
./run.sh --stop 2>/dev/null || true

# Build backend for production
log_info "Building backend for production..."
cd backend

# Install production dependencies only
log_info "Installing production dependencies..."
npm ci --only=production --max-old-space-size=512

# Build TypeScript to JavaScript
log_info "Compiling TypeScript..."
npx tsc --project tsconfig.prod.json

# Check if build was successful
if [ ! -f "dist/index.js" ]; then
    log_error "Backend build failed - dist/index.js not found"
    exit 1
fi

# Build frontend for production
log_info "Building frontend for production..."
cd ../frontend

# Install and build frontend
npm ci --only=production --max-old-space-size=512
npm run build

# Check if frontend build was successful
if [ ! -d "dist" ]; then
    log_error "Frontend build failed - dist directory not found"
    exit 1
fi

# Start backend with memory optimization
log_info "Starting backend with memory optimization..."
cd ../backend

# Set production environment variables
export NODE_ENV=production
export NODE_OPTIONS="--max-old-space-size=768 --gc-interval=100"
export UV_THREADPOOL_SIZE=2

# Start the backend server
log_info "Backend starting on port 4000 with memory limit: 768MB"
npm run start:prod &
BACKEND_PID=$!

# Wait for backend to start
log_info "Waiting for backend to initialize..."
sleep 5

# Check if backend is running
if ! kill -0 $BACKEND_PID 2>/dev/null; then
    log_error "Backend failed to start"
    exit 1
fi

# Test backend health
log_info "Testing backend health..."
for i in {1..10}; do
    if curl -s http://localhost:4000/api/performance/health > /dev/null; then
        log_info "✅ Backend is healthy and responding"
        break
    fi
    if [ $i -eq 10 ]; then
        log_error "Backend health check failed after 10 attempts"
        kill $BACKEND_PID 2>/dev/null || true
        exit 1
    fi
    sleep 2
done

# Serve frontend (simple static server for production)
log_info "Starting frontend static server..."
cd ../frontend

# Use a simple static server for production
npx serve -s dist -l 5173 &
FRONTEND_PID=$!

# Wait for frontend to start
sleep 3

# Check if frontend is accessible
if curl -s -I http://localhost:5173 > /dev/null; then
    log_info "✅ Frontend is accessible"
else
    log_warn "Frontend may not be accessible yet"
fi

# Display status
echo ""
echo "🎉 TuneTussle Production Deployment Complete!"
echo "============================================="
echo ""
echo "📊 System Status:"
echo "  Backend:  http://localhost:4000 (PID: $BACKEND_PID)"
echo "  Frontend: http://localhost:5173 (PID: $FRONTEND_PID)"
echo ""
echo "📈 Performance Monitoring:"
echo "  Health:   curl http://localhost:4000/api/performance/health"
echo "  Stats:    curl http://localhost:4000/api/performance/stats"
echo "  Memory:   ps -o pid,ppid,cmd,%mem --sort=-%mem -C node"
echo ""
echo "🛑 To stop servers:"
echo "  kill $BACKEND_PID $FRONTEND_PID"
echo ""

# Monitor memory usage
log_info "Monitoring initial memory usage..."
sleep 5

MEMORY_USAGE=$(ps -o %mem -p $BACKEND_PID --no-headers 2>/dev/null | tr -d ' ' || echo "N/A")
if [ "$MEMORY_USAGE" != "N/A" ]; then
    log_info "Backend memory usage: ${MEMORY_USAGE}%"
    
    # Convert percentage to MB (rough estimate for 1GB system)
    MEMORY_MB=$(echo "$MEMORY_USAGE * 10" | bc 2>/dev/null || echo "N/A")
    if [ "$MEMORY_MB" != "N/A" ]; then
        log_info "Estimated memory usage: ~${MEMORY_MB}MB"
        
        if (( $(echo "$MEMORY_USAGE > 60" | bc -l) )); then
            log_warn "High memory usage detected. Monitor closely."
        else
            log_info "Memory usage is within acceptable limits."
        fi
    fi
else
    log_warn "Could not determine memory usage"
fi

# Keep script running and show logs
log_info "Production servers are running. Press Ctrl+C to stop."
log_info "Monitoring logs..."

# Function to cleanup on exit
cleanup() {
    log_info "Shutting down servers..."
    kill $BACKEND_PID $FRONTEND_PID 2>/dev/null || true
    log_info "Servers stopped."
    exit 0
}

# Set trap for cleanup
trap cleanup SIGINT SIGTERM

# Wait for processes
wait 