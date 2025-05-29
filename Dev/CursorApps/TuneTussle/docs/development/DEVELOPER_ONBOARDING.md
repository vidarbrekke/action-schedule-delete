# TuneTussle Developer Onboarding Guide

**Essential setup guide for new developers to prevent common issues and maintain code quality**

## 🚀 **Quick Start (5 Minutes)**

### **1. Repository Setup**
```bash
# Clone and install dependencies
git clone https://github.com/vidarbrekke/tunetussle.git
cd TuneTussle

# Install all dependencies
cd backend && npm install
cd ../frontend && npm install
cd ..
```

### **2. Environment Configuration**
```bash
# Configure backend environment
echo "OPENROUTER_API_KEY=your_key_here" > backend/.env
echo "MUSIC_PROVIDER=deezer" >> backend/.env
```

### **3. Verify Installation**
```bash
# 🚨 CRITICAL: Verify everything works
npm run ci-check

# If this passes, you're ready to develop!
```

### **4. Development Tools Setup**
```bash
# Install pre-commit hooks (prevents CI failures)
./scripts/setup-pre-commit.sh

# Add development aliases to your shell
source ~/.tunetussle_aliases
```

---

## 🛠️ **Complete Development Environment**

### **IDE Setup (VS Code Recommended)**

When you open the project in VS Code, you'll be prompted to install recommended extensions. **Click "Install All"** for:

- **ESLint** - Code quality enforcement
- **Prettier** - Code formatting
- **TypeScript** - Enhanced TypeScript support
- **Jest** - Test integration
- **Tailwind CSS** - CSS class completion

### **Git Configuration**

Add these to your git config for better commit messages:
```bash
git config --global alias.co checkout
git config --global alias.br branch
git config --global alias.ci commit
git config --global alias.st status
```

### **Shell Aliases**

After running the setup script, you'll have these aliases:
```bash
tt-check      # npm run ci-check (MOST IMPORTANT)
tt-test       # npm test
tt-lint       # npm run lint -- --fix
tt-types      # npm run type-check
pre-push      # Full validation + confirmation
```

---

## 🧪 **Testing Strategy**

### **The Golden Rule**
**ALWAYS run `npm run ci-check` before pushing to GitHub!**

This prevents:
- ❌ 6-hour CI runs that consume budget
- ❌ TypeScript compilation failures
- ❌ ESLint violations that break builds
- ❌ Test failures in production pipeline

### **Development Workflow**

#### **Starting Work**
```bash
# 1. Ensure clean baseline
git pull origin main
npm run ci-check        # Should pass

# 2. Create feature branch
git checkout -b feature/your-feature

# 3. Start development with watch mode
cd backend && npm test -- --watch    # Terminal 1
cd frontend && npm test -- --watch   # Terminal 2
```

#### **During Development**
```bash
# Quick feedback loop
npm test -- --watch         # Tests update automatically
npm run lint -- --fix      # Auto-fix style issues as you go
```

#### **Before Every Commit**
```bash
# 🚨 REQUIRED
npm run ci-check

# Fix any issues before committing
npm run lint -- --fix      # Fix auto-fixable issues
npm run type-check         # Check TypeScript
npm test                   # Verify tests pass
```

### **Testing Commands Reference**

```bash
# Root level (run these from project root)
npm run ci-check            # Complete validation (MOST IMPORTANT)
npm run test:all           # All tests (backend + frontend)
npm run lint:all           # All linting
npm run type-check:all     # All TypeScript compilation

# Backend specific
cd backend
npm test                   # All backend tests
npm test -- gameSession   # Specific test file
npm test -- --watch      # Watch mode
npm run test:coverage     # Coverage report

# Frontend specific
cd frontend
npm test                   # All frontend tests
npm test -- Button       # Specific component
npm test -- --watch     # Watch mode
npm run test:coverage    # Coverage report
```

---

## 🔍 **Common Issues & Solutions**

### **❌ "npm run ci-check fails locally"**

**TypeScript Errors:**
```bash
# Error: "Unexpected any. Specify a different type"
# Fix: Replace any with proper interface

# Before
function handleData(data: any) { ... }

# After
interface DataProps {
  id: string;
  value: number;
}
function handleData(data: DataProps) { ... }
```

**ESLint Errors:**
```bash
# Error: "React Hook useEffect has missing dependencies"
# Fix: Add dependency or disable rule

# Before
useEffect(() => {
  doSomething(variable);
}, []); // Missing variable dependency

# After - Option 1: Add dependency
useEffect(() => {
  doSomething(variable);
}, [variable]);

# After - Option 2: Disable if intentional
useEffect(() => {
  doSomething(variable);
  // eslint-disable-next-line react-hooks/exhaustive-deps
}, []);
```

### **❌ "Tests hang forever"**

```bash
# Problem: Missing timer cleanup
useEffect(() => {
  const interval = setInterval(() => {
    // Some logic
  }, 1000);
  // Missing cleanup!
}, []);

# Solution: Always clean up
useEffect(() => {
  const interval = setInterval(() => {
    // Some logic
  }, 1000);

  return () => clearInterval(interval); // CRITICAL
}, []);
```

### **❌ "Out of memory during tests"**

```bash
# Run with more memory
NODE_OPTIONS="--max-old-space-size=4096" npm test

# Or run in smaller batches
npm test -- --maxWorkers=2
```

---

## 📚 **Project Architecture Overview**

### **Technology Stack**
- **Backend**: Node.js + Express + TypeScript + Socket.IO
- **Frontend**: React + TypeScript + Vite + Socket.IO Client
- **Testing**: Jest (backend) + Vitest (frontend)
- **Music APIs**: Deezer (primary) + Spotify/YouTube (fallback)
- **Production**: Alpine Linux + Caddy + Cloudflare

### **Key Files to Understand**

#### **Backend Core**
- `src/gameSessionManager.ts` - Game state management
- `src/utils/answerParser.ts` - Answer validation logic
- `src/services/` - Music API integrations
- `src/realtimeEmitter.ts` - Socket.IO event handling

#### **Frontend Core**
- `src/hooks/useGameLogic.ts` - Main game logic hook
- `src/contexts/SocketContext.tsx` - Socket.IO integration
- `src/components/game/` - Game UI components
- `src/services/artworkPreloadService.ts` - Artwork caching

#### **Testing Utilities**
- `backend/src/testUtils/` - Shared backend test utilities
- `frontend/src/testUtils/` - Shared frontend test utilities
- `jest.config.js` - Jest configuration
- `frontend/vitest.config.ts` - Vitest configuration

### **Critical Systems**

1. **Answer Parsing System**: Handles flexible user input for song/artist answers
2. **Artwork Preloading**: Just-in-time image loading with smart caching
3. **Real-time Synchronization**: Socket.IO for multiplayer state management
4. **Music Provider Fallbacks**: Robust API fallback system

---

## 🚨 **Critical Don'ts**

### **❌ Never Do These**

1. **Push without running `npm run ci-check`**
   - Wastes GitHub Actions budget
   - Causes deployment failures
   - Blocks other developers

2. **Use `any` types in TypeScript**
   - Tests pass locally but CI fails
   - Breaks type safety
   - Creates maintenance issues

3. **Ignore ESLint warnings**
   - React hook dependency issues cause bugs
   - Unused variables indicate dead code
   - Missing types reduce code quality

4. **Skip test cleanup**
   - Tests hang indefinitely
   - Consumes CI budget
   - Blocks deployments

5. **Modify core game logic without tests**
   - `gameSessionManager.ts` changes need comprehensive tests
   - Answer parsing changes need validation
   - Socket events need integration tests

---

## 📊 **Quality Standards**

### **Test Coverage Requirements**
- **Statements**: >80%
- **Branches**: >75%
- **Functions**: >85%
- **Lines**: >80%

### **Code Quality Standards**
- **No `any` types** (except when absolutely necessary with `// @ts-ignore` comment)
- **All React hooks** must declare dependencies correctly
- **All timers/intervals** must be cleaned up in tests
- **All async operations** must be properly awaited
- **All error cases** must be handled

### **Git Workflow**
- **Feature branches** for all changes
- **Descriptive commit messages** with type prefixes (`feat:`, `fix:`, `docs:`)
- **Small, focused commits** (one logical change per commit)
- **PR reviews** required for main branch

---

## 🎯 **Success Metrics**

### **You'll Know You're Ready When:**

1. ✅ `npm run ci-check` passes consistently
2. ✅ Pre-commit hooks are working
3. ✅ You can run the full game locally
4. ✅ Your VS Code shows no TypeScript/ESLint errors
5. ✅ Tests run in watch mode without hanging

### **Daily Workflow Should Be:**

1. 🌅 `git pull origin main` (start with latest)
2. 🧪 `npm run ci-check` (verify baseline)
3. 🚀 Start development with watch modes
4. 🔧 `npm run lint -- --fix` (fix issues as you code)
5. ✅ `npm run ci-check` (before every commit)
6. 🚢 `git push` (only after validation passes)

---

## 🆘 **Getting Help**

### **If You're Stuck:**

1. **Check the error message carefully** - Often contains the solution
2. **Run individual commands** to isolate the issue:
   ```bash
   npm run type-check     # TypeScript issues
   npm run lint          # ESLint issues
   npm test             # Test failures
   ```
3. **Check existing tests** for similar patterns
4. **Read the specific error documentation** (links in error messages)

### **Documentation Resources**
- [Complete Testing Guide](TESTING.md) - Comprehensive testing documentation
- [Development Guide](DEVELOPMENT.md) - Technical implementation details
- [Git Workflow](GIT_WORKFLOW.md) - Branching and commit strategies
- [Deployment Guide](../deployment/DEPLOYMENT_GUIDE.md) - Production deployment

### **Common Commands Cheat Sheet**
```bash
# Daily essentials
npm run ci-check          # Before every push
npm test -- --watch      # Development testing
npm run lint -- --fix    # Fix style issues

# Debugging
npm test -- --verbose --detectOpenHandles  # Find hanging tests
npm run type-check                         # TypeScript issues
npm test -- --coverage                    # Coverage analysis

# Navigation (with aliases)
tt-be                     # cd backend
tt-fe                     # cd frontend
tt-check                  # npm run ci-check
```

---

**Remember**: These tools and processes exist to help you ship high-quality code quickly. The 5 minutes spent on `npm run ci-check` can save hours of debugging and prevent expensive CI failures!

**Welcome to the TuneTussle team! 🎵**
