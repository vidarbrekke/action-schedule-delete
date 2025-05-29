# TuneTussle Developer Guide

**Complete setup guide for local development and production deployment**

## 🚀 Quick Start (5 minutes)

### Prerequisites
- Node.js 18+ 
- Git
- OpenRouter API key ([get one here](https://openrouter.ai/))

### Local Development Setup
```bash
# 1. Clone repository
git clone https://github.com/vidarbrekke/tunetussle.git
cd TuneTussle

# 2. Install dependencies
cd backend && npm install
cd ../frontend && npm install
cd ..

# 3. Configure environment
echo "OPENROUTER_API_KEY=your_key_here" > backend/.env
echo "MUSIC_PROVIDER=deezer" >> backend/.env

# 4. Start development servers
./run.sh --dev    # Development mode with auto-reload
# OR
./run.sh          # Production mode

# 5. Access application
# Judge: http://localhost:3000
# Players: Join with 4-character code
```

## 🌳 Git Workflow

### Branch Strategy
```
main           ← Production branch (automated updates)
├── feature/*  ← New features
├── fix/*      ← Bug fixes
└── dev        ← Development integration (optional)
```

### **Main Branch**: `main`
- **Production-ready code**
- **Automated dependency updates** (Mondays 9 AM UTC)
- **All PRs target this branch**
- **Direct pushes allowed** for hotfixes

### Development Workflow

#### Creating Features
```bash
# 1. Start from main
git checkout main
git pull origin main

# 2. Create feature branch
git checkout -b feature/your-feature-name

# 3. Develop and commit
git add .
git commit -m "feat: description of your feature"

# 4. Push and create PR
git push origin feature/your-feature-name
gh pr create --title "Feature: Your Feature" --base main
```

#### Hotfixes
```bash
# Critical fixes can go directly to main
git checkout main
git add .
git commit -m "fix: critical issue description"
git push origin main
```

### Commit Message Format
```bash
feat: new feature description
fix: bug fix description  
docs: documentation changes
style: formatting changes
refactor: code restructuring
test: adding or updating tests
chore: maintenance tasks
```

## 🛠️ Local Development Environment

### Environment Configuration

#### Required (backend/.env)
```bash
OPENROUTER_API_KEY=sk-or-v1-xxx    # LLM song generation
MUSIC_PROVIDER=deezer              # Primary music provider
```

#### Optional Providers (backend/.env)
```bash
SPOTIFY_CLIENT_ID=your_id          # Spotify fallback
SPOTIFY_CLIENT_SECRET=your_secret
YOUTUBE_API_KEY=your_key           # YouTube fallback
```

#### Optional Performance (backend/.env)
```bash
MEMORY_WARNING_MB=600              # Memory monitoring
GAME_TTL_MINUTES=30               # Game cleanup
MAX_GAMES=500                     # Concurrent limit
```

### Development Commands

#### Starting Services
```bash
./run.sh --dev                    # Development with auto-reload
./run.sh                          # Production mode
./run-dev.sh                      # Alternative dev script
```

#### Testing
```bash
# Run all tests (521 total)
cd backend && npm test             # Backend tests (198/201 passing)
cd frontend && npm test            # Frontend tests (323/324 passing)

# Specific test files
npm test gameSessionManager        # Specific test suite
npm test -- --watch              # Watch mode
```

#### Dependency Management
```bash
# Test updates safely (recommended)
./scripts/test-updates.sh         # Test all updates in isolation

# Check outdated packages
cd backend && npm outdated
cd frontend && npm outdated

# Security audit
cd backend && npm audit
cd frontend && npm audit

# Comprehensive dependency analysis
./scripts/dependency-check.sh
```

### Development Tools

#### Auto-reload
- **Backend**: Nodemon watches TypeScript files
- **Frontend**: Vite development server
- **Both start together**: `./run.sh --dev`

#### Network Configuration
```bash
./setup-network.sh               # Configure multi-device access
./test-network.sh                # Test network connectivity
```

#### Performance Monitoring
- **Health endpoint**: `GET /api/performance/health`
- **Stats endpoint**: `GET /api/stats`  
- **Memory monitoring**: Automatic warnings

## 🏔️ Production Environment (Alpine Linux)

### Server Requirements
- **OS**: Alpine Linux (latest)
- **RAM**: 1GB minimum
- **Storage**: 2GB minimum
- **Network**: Internet access for music APIs

### Production Deployment

#### Initial Server Setup
```bash
# 1. Install required packages (run as root)
apk add --no-cache nodejs npm git bash redis

# 2. Configure Redis
rc-update add redis default
service redis start

# 3. Create application directory
mkdir -p /opt/tunetussle
cd /opt/tunetussle

# 4. Clone repository
git clone https://github.com/vidarbrekke/tunetussle.git .

# 5. Install dependencies (production mode)
cd backend && npm ci --production --prefer-offline
cd ../frontend && npm ci --production --prefer-offline

# 6. Build frontend
cd frontend && npm run build

# 7. Setup environment
cp backend/.env.example backend/.env
# Edit backend/.env with production values

# 8. Test the deployment
./scripts/alpine-staging-test.sh  # Memory-optimized testing

# 9. Start application
./run.sh
```

#### Production Environment Variables
```bash
# backend/.env (production)
OPENROUTER_API_KEY=your_production_key
MUSIC_PROVIDER=deezer
NODE_ENV=production

# Optional Redis configuration
REDIS_URL=redis://localhost:6379
REDIS_PASSWORD=your_redis_password

# Performance tuning for 1GB server
MEMORY_WARNING_MB=400
MAX_GAMES=200
GAME_TTL_MINUTES=30
```

#### Alpine Linux Memory Optimization
```bash
# Redis configuration (/etc/redis.conf)
maxmemory 100mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
bind 127.0.0.1
requirepass your_redis_password
```

### Production Maintenance

#### Deployment Updates
```bash
# 1. Navigate to application directory
cd /opt/tunetussle

# 2. Test updates safely first
./scripts/alpine-staging-test.sh

# 3. If tests pass, pull latest changes
git pull origin main

# 4. Restart application
pkill -f "node.*backend"         # Stop backend
./run.sh                         # Start application
```

#### Monitoring
```bash
# Check application health
curl http://localhost:4000/api/performance/health

# Monitor memory usage
watch free -h

# Monitor Redis
redis-cli info memory

# Check logs
tail -f /var/log/tunetussle.log   # If logging configured
```

#### Backup Strategy
```bash
# Backup game data (if persistent storage added)
tar -czf backup-$(date +%Y%m%d).tar.gz /opt/tunetussle/data/

# Backup Redis data
redis-cli SAVE
cp /var/lib/redis/dump.rdb backup-redis-$(date +%Y%m%d).rdb
```

## 🔧 Automated Package Management

### How It Works
The project includes automated dependency management that runs every Monday:

1. **GitHub Actions triggers** at 9 AM UTC
2. **Staging environment created** (completely isolated)  
3. **Updates applied** only in staging
4. **All 521 tests run** to validate changes
5. **PR created** only if all tests pass
6. **Manual review** before merging

### Manual Package Testing
```bash
# Test updates locally before automation
./scripts/test-updates.sh         # General testing
./scripts/alpine-staging-test.sh  # Alpine-optimized testing

# Apply safe updates manually
./scripts/safe-update.sh          # With comprehensive validation
```

### Package Update Types
- **Patch updates**: Applied automatically (bug fixes only)
- **Minor updates**: Require manual PR review
- **Major updates**: Require manual PR review
- **Security fixes**: Applied immediately

## 🧪 Testing Strategy

### Test Structure
```
Total Tests: 521 (99.4% pass rate)
├── Backend: 198/201 passing (99.0%)
│   ├── Unit tests: Game logic, services
│   ├── Integration tests: Socket.IO, API endpoints
│   └── Performance tests: Memory, concurrency
└── Frontend: 323/324 passing (99.7%)
    ├── Component tests: React components
    ├── Hook tests: Custom React hooks
    └── Service tests: API services, utilities
```

### Running Tests
```bash
# All tests
npm test                          # Root level (runs both)
cd backend && npm test            # Backend only
cd frontend && npm test           # Frontend only

# Specific areas
npm test gameSessionManager       # Core game logic
npm test socketBatchingIntegration # Real-time features
npm test artworkPreloadService    # Artwork system

# Watch mode for development
npm test -- --watch              # Auto-run on changes
```

### Test Coverage Areas
- ✅ **Game logic**: Buzzing, scoring, round progression
- ✅ **Real-time sync**: Socket.IO events, state management
- ✅ **Music providers**: Deezer, Spotify, YouTube integration
- ✅ **Answer parsing**: Natural language input handling
- ✅ **Artwork system**: Preloading, caching, display
- ✅ **Performance**: Memory usage, concurrent games
- ✅ **UI components**: React components, user interactions

## 🚨 Troubleshooting

### Common Development Issues

#### Port Conflicts
```bash
# Kill processes on ports 3000/4000
lsof -ti:3000 | xargs kill -9
lsof -ti:4000 | xargs kill -9

# Or use different ports
PORT=3001 npm start              # Frontend
PORT=4001 npm start              # Backend
```

#### Memory Issues (Development)
```bash
# Increase Node.js memory limit
export NODE_OPTIONS="--max-old-space-size=4096"

# Monitor memory usage
node --max-old-space-size=4096 src/index.js
```

#### API Key Issues
```bash
# Verify OpenRouter API key
curl -H "Authorization: Bearer $OPENROUTER_API_KEY" \
     https://openrouter.ai/api/v1/models

# Test music providers
cd backend && npm run test:deezer
```

### Common Production Issues

#### Low Memory (Alpine 1GB)
```bash
# Check memory usage
free -h
ps aux --sort=-%mem | head -10

# Optimize Redis
redis-cli CONFIG SET maxmemory 100mb
redis-cli CONFIG SET maxmemory-policy allkeys-lru

# Use lightweight staging
./scripts/alpine-staging-test.sh
```

#### Service Management
```bash
# Check if services are running
ps aux | grep node               # Node.js processes
redis-cli ping                   # Redis status

# Restart services
pkill -f "node.*backend"
service redis restart
./run.sh
```

## 📚 Architecture Overview

### Backend Structure
```
backend/src/
├── gameSessionManager.ts          # Main game orchestrator
├── services/
│   ├── deezerService.ts          # Primary music provider
│   ├── musicProviderService.ts   # Multi-provider system
│   ├── answerSubmissionService.ts # Answer processing
│   └── llmService.ts             # Song generation
├── realtimeEmitter.ts            # Socket.IO events
└── routes/                       # API endpoints
```

### Frontend Structure  
```
frontend/src/
├── hooks/
│   ├── useGameLogic.ts           # Game state management
│   └── gameStateReducer.ts      # State transitions
├── components/
│   ├── JudgeGameControls.tsx     # Music player + controls
│   ├── RoundResultsScreen.tsx    # Post-round results
│   └── BuzzButton.tsx            # Player interactions
└── services/
    └── artworkPreloadService.ts  # Artwork caching
```

### Key Systems
- **Game State**: Centralized in gameSessionManager.ts
- **Real-time**: Socket.IO with event batching
- **Music**: Multi-provider fallback system
- **Artwork**: Just-in-time preloading with smart caching
- **Testing**: Comprehensive 521-test validation suite

## 🎯 Development Best Practices

### Code Style
- **TypeScript**: Strict mode enabled
- **ESLint**: Configured for consistency
- **Prettier**: Automatic code formatting
- **YAGNI**: Build only what's needed now
- **DRY**: Don't repeat yourself

### Performance Guidelines
- **Memory efficiency**: Especially important for Alpine production
- **Test everything**: Maintain 99%+ test coverage
- **Cache intelligently**: 5-minute TTL for external APIs
- **Batch operations**: Use event batching for real-time features

### Security Considerations
- **API keys**: Never commit to git
- **Input validation**: All user inputs sanitized
- **Rate limiting**: Built into music providers
- **Dependency monitoring**: Automated security updates

## 📄 Documentation

### Available Guides
- **[README.md](../README.md)**: Project overview and quick start
- **[DEVELOPMENT.md](../DEVELOPMENT.md)**: Technical implementation details
- **[HANDOVER.md](../HANDOVER.md)**: Project handover notes
- **[package-management.md](../package-management.md)**: User-friendly package guide
- **[Alpine Production Guide](PRODUCTION_ALPINE_CONSIDERATIONS.md)**: Alpine deployment
- **[Simple Staging Guide](../README_SIMPLE_STAGING.md)**: Staging system overview

### Quick References
- **[Package Management Quick Start](QUICK_START_PACKAGE_MANAGEMENT.md)**: 5-minute setup
- **[Complete Package Management](PACKAGE_MANAGEMENT.md)**: Technical deep dive
- **[Simple Staging Strategy](SIMPLE_STAGING_STRATEGY.md)**: Docker-free staging

---

**Questions?** Check the documentation above or create an issue on GitHub.

**Ready to code!** 🚀 

## 📋 Environment Requirements

### Local Development (Required)
- **Node.js**: 18+ (Latest LTS recommended)
- **npm**: 8+ (comes with Node.js)
- **Git**: Any recent version
- **OpenRouter API Key**: [Get one here](https://openrouter.ai/)

### Optional Tools
- **Code Editor**: VS Code recommended with TypeScript extensions
- **Browser**: Chrome/Firefox with developer tools

### Not Required for Development
- ❌ **Redis**: Application uses in-memory storage for development
- ❌ **Docker**: Simple local development without containers
- ❌ **Database**: Pure in-memory game state management 