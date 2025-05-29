# TuneTussle 🎵

**🎉 PRODUCTION READY** - Real-time multiplayer music quiz game with Alpine Linux production server fully configured and operational!

## 🏆 Production Status: LIVE

**✅ Alpine Linux server tunetussle.com (69.164.209.52) is production-ready and fully configured!**

**Current Production Configuration:**
- **Server**: Alpine Linux 3.20.6, 973MB RAM (729MB available)
- **Runtime**: Node.js v20.15.1, npm 10.9.1, Redis 7.2.8 (infrastructure-ready)
- **Web Server**: Caddy with automatic HTTPS for tunetussle.com
- **Security**: SSH keys, firewall, hardening complete
- **Deployment**: Zero-downtime system active

## 🚀 **IMPROVED DEPLOYMENT SCRIPTS** ⭐ **USE THESE** ⭐

**🎉 Based on lessons learned from 4-hour deployment experience - now deploys in 30 minutes!**

### **Quick 5-Minute Deployment** (Recommended)

```bash
# 1. Validate everything is ready (prevents 90% of failures)
./deployment/scripts/pre-deployment-check.sh

# 2. Deploy with all improvements (30 minutes vs 4 hours)
./deployment/scripts/deploy-to-alpine-improved.sh
```

### **Key Improvements**
- ✅ **Pre-compile TypeScript locally** (prevents server memory issues)
- ✅ **Comprehensive pre-deployment checks** (catches 90% of issues early)
- ✅ **Alpine OpenRC service management** (not systemd - critical for Alpine)
- ✅ **Cloudflare-compatible configuration** (proper routing and SSL)
- ✅ **Real production verification testing** (ensures everything works)

**📚 Complete script documentation**: [deployment/scripts/README.md](deployment/scripts/README.md)

**Ready for Immediate Deployment:**
```bash
# Original deployment (kept for reference)
./deployment/scripts/deploy-to-alpine.sh

# Monitor production server
./deployment/scripts/remote-commands.sh status

# Production health check
./deployment/scripts/remote-commands.sh health

# 🆕 NEW: Test package upgrades in server staging
./deployment/scripts/remote-commands.sh staging-setup    # Set up staging
./deployment/scripts/remote-commands.sh staging-test     # Test upgrades
./deployment/scripts/remote-commands.sh staging-apply    # Apply to production
```

## 🏗️ Architecture

**Stack**: React/TypeScript + Node.js/Express + Socket.IO + Deezer API + Alpine Linux Production

### 🆕 **Production + Staging Architecture**
```
Alpine Production Server (69.164.209.52)
├── /opt/tunetussle/           # Production environment (Port 4000)
├── /opt/staging/              # 🆕 Isolated staging for package testing (Port 4001)
├── Package Testing Workflow:  # 🆕 Safe upgrade validation
│   ├── Copy prod → staging    # Complete isolation
│   ├── Test package updates   # Full 521 test suite
│   └── Apply to prod (if pass) # Zero-risk deployment
└── Memory Optimized (729MB available) # Both environments share resources efficiently
```

### Core Game Flow
1. **Judge creates game** → Generates 4-character code
2. **Players join** → Enter code, set name
3. **LLM generates songs** → Based on judge's prompt
4. **Real-time gameplay** → Buzz in, answer, score
5. **Post-round results** → Show correct answer, points, artwork
6. **Judge advances** → Controls game progression

### Key Features
- **Real-time multiplayer** with multi-device synchronization
- **Post-round results** with artwork display
- **Just-in-time artwork preloading** for smooth UX
- **Intelligent answer parsing** with natural language support
- **Judge-controlled gameplay** with complete round management

## 🎨 Recent Enhancements

### v1.5.3 - Intelligent Answer Parsing (December 2025)
- **Smart answer matching** handles any input order and case variations
- **Natural language support** for flexible user input
- **Enhanced scoring accuracy** with pattern matching

### v1.5.0 - Post-Round Results & Artwork System (December 2025)
- **Post-round results screen** with comprehensive scoring display
- **Just-in-time artwork preloading** with smart caching
- **Memory-optimized** for 1GB RAM production servers

## 🚨 Developer Status

### Production Ready ✅
- **All core features** fully implemented and tested
- **Comprehensive testing** with 521 tests (99.4% pass rate)
- **Complete documentation** with deployment guides
- **Single-command deployment** with health monitoring

### Next Developer Guidelines
1. **Follow established patterns** - Use existing test utilities and architecture
2. **Test comprehensively** - Maintain high test coverage standards
3. **Preserve core systems** - Artwork preloading and answer parsing are optimized

---

**Ready to deploy, built to scale, designed for fun.**

## 🚀 Quick Start

### Prerequisites
- Node.js 18+
- OpenRouter API key ([get one here](https://openrouter.ai/))

### Setup & Run
```bash
# 1. Clone and install
git clone https://github.com/vidarbrekke/tunetussle.git
cd TuneTussle
cd backend && npm install
cd ../frontend && npm install

# 2. Configure environment
echo "OPENROUTER_API_KEY=your_key_here" > backend/.env
echo "MUSIC_PROVIDER=deezer" >> backend/.env

# 3. Start (auto-detects network for multi-device)
./run.sh --dev    # Development with auto-reload
./run.sh          # Production mode

# 4. Play: Judge opens browser, players join with 4-char code
```

## 📚 **Developer Documentation**

### 🎨 **Essential Guides**

#### **[📚 Complete Documentation Index](docs/README.md)** - Start Here!
**Your documentation hub!** Organized navigation to all guides and references.

#### **[📖 Developer Guide](docs/getting-started/DEVELOPER_GUIDE.md)** - Complete Setup & Workflow
**Your starting point!** Everything from git setup to production deployment.

#### **[🌳 Git Workflow Guide](docs/development/GIT_WORKFLOW.md)** - Git Best Practices
**Git made simple!** Clear branching strategy and daily workflows.

#### **[🚀 Deployment Guide](docs/deployment/DEPLOYMENT_GUIDE.md)** - Local & Production
**Deploy with confidence!** Local development and Alpine Linux production.

### 🐧 **Production Deployment System - READY**

**✅ Complete production environment with Alpine Linux server fully configured and operational!**

#### **[🚀 Production Deployment Guide](docs/deployment/alpine-server/DEPLOYMENT_GUIDE.md)** - Deploy in 5 Minutes
**🎉 Server is ready - no setup required!** Immediate deployment to production Alpine server.

#### **[🏔️ Alpine Server Management](docs/deployment/alpine-server/README.md)** - Production Operations
**Monitor and manage your live production server.**

#### **Production Deployment Commands (Ready Now!)**
```bash
# Deploy to production Alpine server (5 minutes)
./deployment/scripts/deploy-to-alpine.sh

# Production server management
./deployment/scripts/remote-commands.sh status     # System status
./deployment/scripts/remote-commands.sh health     # Health check
./deployment/scripts/remote-commands.sh logs       # Application logs

# Direct access to production
ssh -p 2222 admin@69.164.209.52
```

#### **Production Features Active**
- **✅ Zero-downtime deployment** with blue-green strategy
- **✅ Memory optimization** for 1GB RAM servers (729MB available)
- **✅ Automatic SSL/TLS** certificates for tunetussle.com
- **✅ Security hardening** complete (SSH keys, firewall, intrusion detection)
- **✅ Health monitoring** with comprehensive system checks
- **✅ Production environment** ready for immediate deployment

### 📦 **Package Management**

#### **[⚡ Quick Start Package Management](docs/development/package-management/QUICK_START_PACKAGE_MANAGEMENT.md)** - 5-minute Setup
**Get automated updates running immediately!**

#### **[🔧 Complete Package Management](docs/development/package-management/PACKAGE_MANAGEMENT.md)** - Technical Deep Dive
**Comprehensive guide to the automated dependency system.**

### 🏗️ **Project Technical Details**

#### **[🔧 Development Setup](docs/development/DEVELOPMENT.md)** - Technical Implementation
**Architecture, testing strategy, and advanced development topics.**

#### **[📋 Project Handover](docs/getting-started/HANDOVER.md)** - Project Overview
**Complete project context, features, and recent changes.**

## 🧪 Testing & Development

### Current Status
**Total**: ✅ **521 tests** (99.4% pass rate)

#### Quick Test Commands
```bash
cd backend && npm test    # Backend tests
cd frontend && npm test   # Frontend tests
./run.sh --dev           # Development with auto-reload
```

**All critical features working perfectly** - Answer parsing, judge persistence, artwork preloading
*See [Development Guide](docs/development/DEVELOPMENT.md) for complete technical details*

## 🔬 **Complete Testing Guide**

### **⚠️ CRITICAL: Run Full CI Suite Locally**

**Before pushing to GitHub**, always run the complete CI validation suite to prevent budget consumption and deployment failures:

```bash
# 🚨 REQUIRED before every push
npm run ci-check         # Runs type-check, lint, and tests

# Or run individually
npm run type-check       # TypeScript compilation
npm run lint            # ESLint code quality 
npm test                # Jest/Vitest tests
```

### **Why This Matters**

**Recent Issue**: Tests passed locally but CI failed due to:
- TypeScript `any` types (tests don't catch these)
- ESLint issues (missing dependencies, unused variables)
- **Result**: 6-hour CI runs, budget exhaustion, production delays

### **Development Workflow**

#### **Before Starting Work**
```bash
# Verify clean state
npm run ci-check
```

#### **During Development** 
```bash
# Quick feedback loop
npm test -- --watch     # Run tests in watch mode
npm run lint -- --fix   # Auto-fix lint issues
```

#### **Before Committing**
```bash
# 🚨 ALWAYS run this before git push
npm run ci-check

# If any errors, fix them first
npm run lint -- --fix   # Fix auto-fixable issues
npm run type-check      # Check TypeScript issues
```

### **Testing Commands**

#### **Backend Testing**
```bash
cd backend
npm test                           # All tests
npm test -- gameSessionManager    # Specific test file
npm test -- --watch              # Watch mode
npm run test:coverage             # Coverage report
npm run type-check                # TypeScript check
npm run lint                      # ESLint check
```

#### **Frontend Testing**
```bash
cd frontend  
npm test                          # All tests
npm test -- TrackLinkButton      # Specific component
npm test -- --watch             # Watch mode
npm run test:coverage            # Coverage report
npm run type-check               # TypeScript check
npm run lint                     # ESLint check
```

#### **Full Project Validation**
```bash
# Root level commands
npm run ci-check                  # Complete CI validation
npm run test:all                  # All tests (both frontend/backend)
npm run lint:all                  # All linting
npm run type-check:all            # All TypeScript checks
```

### **IDE Setup for Quality**

Add to `.vscode/settings.json`:
```json
{
  "typescript.preferences.strictMode": true,
  "eslint.validate": ["typescript", "typescriptreact"],
  "typescript.reportStyleChecksAsWarnings": false,
  "editor.codeActionsOnSave": {
    "source.fixAll.eslint": true
  }
}
```

### **Pre-commit Hook (Recommended)**

Add to your shell profile (`.bashrc`, `.zshrc`):
```bash
# Alias for comprehensive checking
alias pre-push="npm run ci-check"

# Git hook (optional but recommended)
echo '#!/bin/sh\nnpm run ci-check' > .git/hooks/pre-push
chmod +x .git/hooks/pre-push
```

**📚 See [Testing Guide](docs/development/TESTING.md) for comprehensive testing documentation**

## 🔧 Environment Configuration

### Required
```bash
# backend/.env
OPENROUTER_API_KEY=sk-or-v1-xxx    # LLM song generation
MUSIC_PROVIDER=deezer              # Primary provider
```

### Optional (Fallback Providers)
```bash
SPOTIFY_CLIENT_ID=your_id          # Spotify fallback
SPOTIFY_CLIENT_SECRET=your_secret  
YOUTUBE_API_KEY=your_key           # YouTube fallback
```

*See [Developer Guide](docs/getting-started/DEVELOPER_GUIDE.md) for complete setup instructions*

---

**Ready to deploy, built to scale, designed for fun.** 🎵# Trigger workflow re-scan
# Force Actions re-scan
