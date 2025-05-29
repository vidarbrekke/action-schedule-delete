# TuneTussle - New Developer Guide 🎵

**Complete onboarding guide for developers taking over TuneTussle**

## 🎯 What is TuneTussle?

Real-time multiplayer music trivia game where players buzz in to identify songs and artists. Judge creates games, players join with 4-character codes, everyone competes to name songs from 30-second audio previews.

**🌐 Live Production**: **[tunetussle.com](https://tunetussle.com)** (Alpine Linux server)

## 🚀 Quick Start (5 Minutes)

### 1. Local Development Setup
```bash
# Clone repository
git clone https://github.com/vidarbrekke/tunetussle.git
cd TuneTussle

# Install dependencies
cd backend && npm install
cd ../frontend && npm install
cd ..

# Create environment file
echo "OPENROUTER_API_KEY=your_key_here" > backend/.env
echo "MUSIC_PROVIDER=deezer" >> backend/.env

# Start development servers
./run.sh --dev

# Access application
open http://localhost:5173  # Frontend (Vite)
# Backend runs on http://localhost:4000
```

### 2. Test the Application
- **Create Game**: Click "Create Game" → Set prompt & rounds → Start
- **Join Game**: Use 4-character code on another device/browser
- **Play Game**: Players buzz in → Submit answers → Judge advances rounds

## 🏗️ Tech Stack & Architecture

### Frontend (React + TypeScript)
```
frontend/src/
├── components/          # UI components (BuzzButton, AnswerInput, etc.)
├── hooks/              # Custom React hooks (useGameLogic, useSocket)
├── pages/              # Route components (GamePage, LobbyPage)
├── services/           # API calls, audio, toasts
└── styles/             # Design system, Tailwind config
```

**Key Technologies:**
- **React 18** + TypeScript + Vite
- **Tailwind CSS** + Framer Motion
- **Socket.IO Client** for real-time updates
- **Vitest** + React Testing Library

### Backend (Node.js + Express)
```
backend/src/
├── services/           # Business logic (GameSessionManager, AnswerSubmission)
├── models/             # TypeScript types and interfaces  
├── utils/              # Helpers, configuration
└── __tests__/          # Integration and unit tests
```

**Key Technologies:**
- **Node.js 20** + Express + TypeScript
- **Socket.IO** for real-time game state
- **Music APIs**: Deezer (primary), Spotify (fallback)
- **OpenRouter AI** for song generation
- **Jest** for testing

### Real-Time Architecture
- **Socket.IO** handles all game state synchronization
- **Game sessions** stored in memory (no database required)
- **Automatic cleanup** after 30 minutes of inactivity
- **Auto-pause audio** when players buzz in

## 🔧 Environment Configuration

### Required Environment Variables
```bash
# backend/.env
OPENROUTER_API_KEY=sk-or-v1-xxx    # Get from https://openrouter.ai
MUSIC_PROVIDER=deezer              # Primary music provider
```

### Optional Environment Variables
```bash
# Music provider fallbacks
SPOTIFY_CLIENT_ID=your_spotify_id
SPOTIFY_CLIENT_SECRET=your_spotify_secret

# Performance tuning
MEMORY_WARNING_MB=600              # Memory usage alerts
GAME_TTL_MINUTES=30               # Auto-cleanup games
MAX_GAMES=500                     # Concurrent game limit

# Development
NODE_ENV=development
DEBUG=tunetussle:*                # Enable debug logging
```

## 🧪 Testing & Quality

### Running Tests
```bash
# All tests (340+ tests, 99.7% pass rate)
cd frontend && npm test           # Frontend: 340 tests
cd backend && npm test            # Backend: Jest integration tests

# Specific test suites
npm test -- BuzzButton           # Component tests
npm test -- useGameLogic         # Hook tests
npm test -- socketGameFlow       # Integration tests
```

### Test Coverage
- **Frontend**: 340 tests covering components, hooks, pages
- **Backend**: Integration tests for socket events, game logic
- **End-to-End**: Socket.IO real-time game flow testing

### Development Scripts
```bash
./run.sh --dev                   # Development with hot reload
./run.sh --stop                  # Stop all servers
./run.sh                         # Production mode locally
npm run lint                     # ESLint + Prettier
```

## 📱 Key Features & Recent Updates

### Core Game Features
- **Real-time multiplayer** with Socket.IO synchronization
- **Audio playback** with auto-pause on buzz-in
- **Smart answer matching** with fuzzy string similarity
- **Role-based UI** (Judge controls vs Player controls)
- **Mobile-optimized** with touch-friendly design

### Recent Major Features (Last Month)
✅ **Players Page**: Navigation to view all participants and scores  
✅ **Game URL Sharing**: Copy-to-clipboard invite system  
✅ **Auto-Redirect**: Seamless rejoin for shared game URLs  
✅ **Answer Feedback**: Immediate visual confirmation for submissions  
✅ **Performance Optimization**: Mobile audio improvements, reduced re-renders  

### Mobile Optimizations
- **Conditional audio loading** for better performance
- **Debounced time updates** to prevent excessive re-renders
- **Touch-optimized controls** for buzz button and inputs
- **Memory management** for 1GB production servers

## 🚀 Production Deployment

### Production Environment (ACTIVE)
- **🌐 Live URL**: **[tunetussle.com](https://tunetussle.com)** (HTTPS enabled)
- **Server**: Alpine Linux 3.20.6 at tunetussle.com (69.164.209.52)
- **Specs**: 1GB RAM (729MB available), 22.5GB storage
- **Stack**: Node.js 20 + Caddy (auto-HTTPS) + Redis (infrastructure-ready)
- **Status**: ✅ Production-ready and optimized

### Deploy to Production (5 Minutes)
```bash
# Deploy current branch
./deployment/scripts/deploy-to-alpine.sh

# Deploy specific branch
./deployment/scripts/deploy-to-alpine.sh --branch feature/new-ui

# Monitor deployment
./deployment/scripts/remote-commands.sh status
./deployment/scripts/remote-commands.sh logs
```

### Test Production Deployment
After deployment, verify the app is working:
- **🌐 Visit**: [tunetussle.com](https://tunetussle.com)
- **Create Game**: Test judge functionality
- **Join Game**: Test on mobile device with game code
- **Verify HTTPS**: Check browser shows secure connection

### Production Management
```bash
# Server management interface
./deployment/scripts/remote-commands.sh

# Common commands
./deployment/scripts/remote-commands.sh status    # System status
./deployment/scripts/remote-commands.sh health    # Health check
./deployment/scripts/remote-commands.sh restart   # Restart app
./deployment/scripts/remote-commands.sh logs      # View logs

# Direct SSH access
ssh -p 2222 admin@69.164.209.52
```

## 🎯 Development Workflow

### 1. Feature Development
```bash
# Create feature branch
git checkout -b feature/new-feature

# Run tests during development
npm test -- --watch              # Frontend tests with watch
npm run test:backend:watch        # Backend tests with watch

# Test on multiple devices
./setup-network.sh               # Configure network access
# Test on phone/tablet via local network IP
```

### 2. Code Quality
```bash
# Linting and formatting
npm run lint                      # ESLint check
npm run format                    # Prettier format

# Type checking
npm run type-check               # TypeScript validation
```

### 3. Deployment Process
```bash
# Test locally
./run.sh --dev                   # Verify development works
./run.sh                         # Test production build

# Deploy to staging (optional)
./scripts/test-updates.sh        # Comprehensive testing

# Deploy to production
./deployment/scripts/deploy-to-alpine.sh
```

## 🔍 Debugging & Troubleshooting

### Common Issues

**Socket Connection Problems:**
```bash
# Check if backend is running
curl http://localhost:4000/health

# Verify Socket.IO connection in browser console
# Should see: "Connected to socket server with ID: xxx"
```

**Audio Playback Issues:**
```bash
# Check browser console for audio errors
# Verify HTTPS in production (required for audio)
# Test with different music providers (Deezer/Spotify)
```

**Memory Issues (Production):**
```bash
# Monitor memory usage
./deployment/scripts/remote-commands.sh status

# Check configured limits
NODE_OPTIONS="--max-old-space-size=512"
```

### Debug Tools
```bash
# Enable debug logging
DEBUG=tunetussle:* npm start

# Monitor Socket.IO events in browser DevTools
# Network tab → WS filter → See real-time events
```

## 📚 Key Documentation Files

### Essential Reading
1. **This Guide** (`docs/deployment/NEW_DEVELOPER_GUIDE.md`) - Start here
2. **API Documentation** (`backend/README.md`) - Backend API reference
3. **Component Guide** (`frontend/.storybook/`) - UI component docs
4. **Game Flow** (`docs/game-flow.md`) - Understanding game mechanics

### Architecture Deep-Dives
- **Socket Events** (`backend/src/registerClientEventHandlers.ts`) - Real-time communication
- **Game Logic** (`frontend/src/hooks/useGameLogic.ts`) - Core game state management
- **Performance** (`backend/src/config/environmentConfig.ts`) - Memory optimization
- **Deployment** (`docs/deployment/alpine-server/DEPLOYMENT_GUIDE.md`) - Production setup

### Test Examples
- **Component Tests** (`frontend/src/components/**/*.test.tsx`) - UI testing patterns
- **Hook Tests** (`frontend/src/hooks/**/*.test.ts`) - Custom hook testing
- **Integration Tests** (`backend/src/__tests__/`) - Socket.IO flow testing

## 🎉 Getting Started Checklist

**Day 1: Setup & Explore**
- [ ] Clone repository and run locally
- [ ] Create a game and test basic functionality
- [ ] Run test suites to verify setup
- [ ] Explore codebase through test files

**Week 1: Understanding**
- [ ] Read through game logic in `useGameLogic.ts`
- [ ] Understand Socket.IO events in backend handlers
- [ ] Test on multiple devices (mobile/desktop)
- [ ] Review recent commits for feature examples

**Ready to Develop:**
- [ ] Make small UI change and test hot reload
- [ ] Write a simple test and verify it passes
- [ ] Deploy to production to see full flow
- [ ] Read through key architectural decisions in docs

**Need Help?**
- Check existing tests for usage examples
- Review Git history for feature implementation patterns
- Use browser DevTools to debug Socket.IO in real-time
- Test memory usage with `./deployment/scripts/remote-commands.sh status`

---

**🎵 Welcome to TuneTussle! You're ready to build amazing music trivia experiences.** 