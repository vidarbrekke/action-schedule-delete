# TuneTussle Testing Guide

**Comprehensive testing documentation to prevent CI failures and maintain code quality**

## 📋 **Table of Contents**

- [Critical Pre-Push Checklist](#critical-pre-push-checklist)
- [Why Testing Failed Before](#why-testing-failed-before)
- [Development Workflow](#development-workflow)
- [Testing Commands Reference](#testing-commands-reference)
- [CI/CD Pipeline](#cicd-pipeline)
- [Troubleshooting Common Issues](#troubleshooting-common-issues)
- [IDE Configuration](#ide-configuration)
- [Test Writing Guidelines](#test-writing-guidelines)

---

## 🚨 **Critical Pre-Push Checklist**

**ALWAYS run this before `git push` to prevent CI failures and budget consumption:**

```bash
# 1. Run complete CI validation locally
npm run ci-check

# 2. If errors, fix them:
npm run lint -- --fix        # Auto-fix ESLint issues
npm run type-check           # Check TypeScript issues
npm test                     # Run all tests

# 3. Only push when ALL checks pass
git push
```

### **What `npm run ci-check` Does**
- **TypeScript compilation** - Catches `any` types, interface issues
- **ESLint validation** - Catches unused variables, missing dependencies
- **Test execution** - Validates functionality
- **Coverage checks** - Ensures adequate test coverage

---

## 🔍 **Why Testing Failed Before**

### **The Problem**
Our CI workflow consumed the entire GitHub Actions budget ($10) in a single 6-hour run because:

1. **Local testing vs CI mismatch**:
   - `npm test` (local) - Only runs Jest/Vitest
   - CI pipeline - Runs TypeScript + ESLint + Tests

2. **Hanging tests**:
   - `setInterval` timers weren't cleaned up in tests
   - Jest waited forever for timers to clear
   - No timeout protection

3. **Type safety ignored**:
   - Tests pass with `any` types
   - TypeScript compilation fails in CI
   - ESLint catches style issues tests miss

### **The Solution**
- **Timeout protection** in CI (10-15 minutes max)
- **Local CI simulation** with `npm run ci-check`
- **Proper test cleanup** for timers and resources
- **Comprehensive documentation** (this guide)

---

## ⚙️ **Development Workflow**

### **Starting New Work**

```bash
# 1. Verify clean state
git status                   # Check working directory
npm run ci-check            # Ensure baseline passes

# 2. Create feature branch
git checkout -b feature/new-feature

# 3. Start development with watch mode
cd backend && npm test -- --watch    # Backend watch
cd frontend && npm test -- --watch   # Frontend watch
```

### **During Development**

```bash
# Quick feedback loop
npm test -- --watch         # Tests in watch mode
npm run lint -- --fix      # Auto-fix style issues
npm run type-check          # Check types incrementally
```

### **Before Committing**

```bash
# 1. Complete validation
npm run ci-check

# 2. If any issues, fix them
npm run lint -- --fix      # Fix auto-fixable issues
npm run type-check         # Check remaining TypeScript issues
npm test                   # Verify all tests pass

# 3. Commit only when clean
git add .
git commit -m "feat: description"
git push
```

---

## 📊 **Testing Commands Reference**

### **Root Level Commands**

```bash
# Complete CI validation (MOST IMPORTANT)
npm run ci-check              # TypeScript + ESLint + Tests

# Individual checks
npm run type-check           # TypeScript compilation only
npm run lint                 # ESLint only
npm test                    # Tests only
npm run test:coverage       # Tests with coverage
```

### **Backend Testing**

```bash
cd backend

# Test execution
npm test                                    # All tests
npm test -- gameSessionManager             # Specific file
npm test -- --testPathPattern="game"       # Pattern matching
npm test -- --watch                        # Watch mode
npm test -- --verbose                      # Detailed output

# Quality checks
npm run type-check                          # TypeScript
npm run lint                               # ESLint
npm run lint -- --fix                     # Auto-fix issues
npm run test:coverage                      # Coverage report

# Specific test categories
npm test -- --testPathPattern="(music|answer)" # Music and answer tests
npm test -- artworkPreloadService              # Artwork tests
npm test -- answerParser                       # Answer parsing tests
```

### **Frontend Testing**

```bash
cd frontend

# Test execution
npm test                                    # All tests
npm test -- TrackLinkButton               # Specific component
npm test -- --testPathPattern="hooks"      # Hook tests only
npm test -- --watch                       # Watch mode
npm test -- --silent                      # Quiet output

# Quality checks
npm run type-check                         # TypeScript
npm run lint                              # ESLint
npm run lint -- --fix                    # Auto-fix issues
npm run test:coverage                     # Coverage report

# Specific test categories
npm test -- --testPathPattern="components" # Component tests
npm test -- --testPathPattern="hooks"     # Hook tests
npm test -- SocketContext                 # Context tests
```

### **Advanced Testing**

```bash
# Coverage analysis
npm run test:coverage                      # Generate coverage report
open coverage/lcov-report/index.html      # View coverage (macOS)

# Performance testing
npm test -- --detectOpenHandles           # Find hanging resources
npm test -- --forceExit                  # Force exit after tests
npm test -- --testTimeout=10000          # Set timeout (10 seconds)

# Debugging
npm test -- --verbose --detectOpenHandles # Detailed debugging
npm test -- --runInBand                  # Run tests serially
```

---

## 🔄 **CI/CD Pipeline**

### **GitHub Actions Workflow**

Our CI pipeline (`ci.yml`) runs:

1. **Dependency Security Check** (5 minutes)
   ```bash
   npm audit --audit-level moderate
   ```

2. **Backend Validation** (10 minutes max)
   ```bash
   npm ci
   npm run type-check
   npm test -- --forceExit --testTimeout=10000
   npm run test:coverage
   ```

3. **Frontend Validation** (10 minutes max)
   ```bash
   npm ci
   npm run type-check
   npm test
   npm run test:coverage
   ```

4. **Integration Testing** (15 minutes max)
   - Cross-component validation
   - End-to-end workflow testing

### **Timeout Protection**

All CI jobs have timeout protection:
- **Job level**: 10-15 minutes maximum
- **Step level**: 2-5 minutes per step
- **Test level**: 10 seconds per test
- **Force exit**: Tests exit cleanly

### **Budget Protection**

- **Total budget**: $10/month GitHub Actions
- **Current usage**: Protected by timeouts
- **Monitoring**: Automatic budget alerts
- **Fail-safe**: Manual workflow triggers only

---

## 🛠️ **Troubleshooting Common Issues**

### **TypeScript Errors**

```bash
# Error: "Unexpected any. Specify a different type"
# Solution: Replace any with proper types

# Before
function process(data: any) { ... }

# After
interface ProcessData {
  id: string;
  value: number;
}
function process(data: ProcessData) { ... }
```

### **ESLint Errors**

```bash
# Error: "React Hook useEffect has missing dependencies"
# Solution: Add dependencies or disable rule

# Before
useEffect(() => {
  handleData(someVariable);
}, []); // Missing someVariable

# After
useEffect(() => {
  handleData(someVariable);
}, [someVariable]); // Include dependency

# Or disable if intentional
useEffect(() => {
  handleData(someVariable);
  // eslint-disable-next-line react-hooks/exhaustive-deps
}, []); // Intentionally empty
```

### **Test Hanging Issues**

```bash
# Error: Tests hang indefinitely
# Solution: Clean up timers and resources

# Problem code
useEffect(() => {
  const interval = setInterval(() => {
    // Some logic
  }, 1000);
  // Missing cleanup!
}, []);

# Fixed code
useEffect(() => {
  const interval = setInterval(() => {
    // Some logic
  }, 1000);

  return () => clearInterval(interval); // Cleanup
}, []);
```

### **Memory Issues**

```bash
# Error: JavaScript heap out of memory
# Solution: Run tests with more memory

# Local fix
NODE_OPTIONS="--max-old-space-size=4096" npm test

# Or run tests in smaller batches
npm test -- --maxWorkers=2
```

---

## 🎯 **IDE Configuration**

### **VS Code Settings**

Create/update `.vscode/settings.json`:

```json
{
  "typescript.preferences.strictMode": true,
  "eslint.validate": ["typescript", "typescriptreact"],
  "typescript.reportStyleChecksAsWarnings": false,
  "editor.codeActionsOnSave": {
    "source.fixAll.eslint": true
  },
  "typescript.updateImportsOnFileMove.enabled": "always",
  "editor.formatOnSave": true,
  "jest.autoRun": "watch",
  "jest.showCoverageOnLoad": true
}
```

### **VS Code Extensions**

Required extensions for optimal development:

```json
{
  "recommendations": [
    "esbenp.prettier-vscode",
    "dbaeumer.vscode-eslint",
    "ms-vscode.vscode-typescript-next",
    "orta.vscode-jest",
    "bradlc.vscode-tailwindcss"
  ]
}
```

### **Shell Aliases**

Add to `.bashrc`, `.zshrc`, or equivalent:

```bash
# TuneTussle development aliases
alias tt-check="npm run ci-check"
alias tt-test="npm test"
alias tt-lint="npm run lint -- --fix"
alias tt-types="npm run type-check"

# Pre-push safety
alias pre-push="npm run ci-check && echo '✅ Safe to push!'"
```

---

## 📝 **Test Writing Guidelines**

### **Test Structure**

```typescript
describe('ComponentName', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Reset state
  });

  afterEach(() => {
    // Cleanup resources
    vi.restoreAllMocks();
  });

  it('should have descriptive test name', () => {
    // Arrange
    const props = { id: '1', name: 'test' };

    // Act
    const result = render(<Component {...props} />);

    // Assert
    expect(result.getByText('test')).toBeInTheDocument();
  });
});
```

### **Mocking Best Practices**

```typescript
// ✅ Good: Proper interface mocking
interface MockSocket {
  on: vi.Mock;
  emit: vi.Mock;
  disconnect: vi.Mock;
}

const mockSocket: MockSocket = {
  on: vi.fn(),
  emit: vi.fn(),
  disconnect: vi.fn()
};

// ❌ Bad: Using any
const mockSocket: any = {
  on: vi.fn(),
  emit: vi.fn(),
  disconnect: vi.fn()
};
```

### **Async Test Patterns**

```typescript
// ✅ Good: Proper async testing
it('should handle async operations', async () => {
  const mockFetch = vi.fn().mockResolvedValue({
    ok: true,
    json: async () => ({ data: 'test' })
  });

  global.fetch = mockFetch;

  const result = await fetchData();
  expect(result).toEqual({ data: 'test' });
});

// ✅ Good: Waiting for DOM updates
it('should update UI after state change', async () => {
  render(<Component />);

  fireEvent.click(screen.getByRole('button'));

  await waitFor(() => {
    expect(screen.getByText('Updated')).toBeInTheDocument();
  });
});
```

### **Resource Cleanup**

```typescript
// ✅ Good: Proper cleanup
it('should clean up timers', () => {
  const { unmount } = renderHook(() => useTimer());

  // Verify timer is set
  expect(setInterval).toHaveBeenCalled();

  // Cleanup
  unmount();

  // Verify cleanup
  expect(clearInterval).toHaveBeenCalled();
});
```

---

## 📊 **Test Coverage Guidelines**

### **Coverage Targets**

- **Statements**: > 80%
- **Branches**: > 75%
- **Functions**: > 85%
- **Lines**: > 80%

### **Critical Areas (100% Coverage Required)**

- Authentication logic
- Payment processing (if added)
- Security functions
- Data validation
- Error handling

### **Coverage Commands**

```bash
# Generate coverage report
npm run test:coverage

# View coverage report
open coverage/lcov-report/index.html

# Coverage with specific threshold
npm test -- --coverage --coverageThreshold='{"global":{"statements":80}}'
```

---

## 🔗 **Additional Resources**

### **Testing Documentation**
- [Jest Documentation](https://jestjs.io/docs/getting-started)
- [Vitest Documentation](https://vitest.dev/guide/)
- [React Testing Library](https://testing-library.com/docs/react-testing-library/intro/)
- [TypeScript Testing](https://typescript-eslint.io/docs/)

### **Internal Documentation**
- [Development Guide](DEVELOPMENT.md) - Technical implementation details
- [Git Workflow](GIT_WORKFLOW.md) - Branching and commit strategies
- [Deployment Guide](../deployment/DEPLOYMENT_GUIDE.md) - Production deployment

### **Project-Specific**
- [Backend Test Utilities](../../backend/src/testUtils/) - Shared testing utilities
- [Frontend Test Utilities](../../frontend/src/testUtils/) - Component testing patterns
- [CI Configuration](../../.github/workflows/ci.yml) - GitHub Actions setup

---

**Remember**: The few minutes spent running `npm run ci-check` locally can save hours of CI debugging and prevent budget exhaustion. Make it a habit!

## 🚨 CRITICAL: CI TypeScript Compilation Issues

**⚠️ MUST READ**: [CI TypeScript Troubleshooting Guide](./CI_TYPESCRIPT_TROUBLESHOOTING.md)

**If GitHub CI fails with TypeScript errors but local checks pass:**
1. **STOP** - Don't push more changes (consumes CI budget)
2. Read the troubleshooting guide above
3. Run strict TypeScript check: `cd frontend && npx tsc --noEmit --strict --verbatimModuleSyntax true`
4. Fix type-only imports, mock implementations, and casting issues
5. Verify with `npm run ci-check` before pushing

**Common Causes**:
- `MockInstance` imports without `type` keyword
- Incomplete mock implementations using `as any`
- Unsafe Event to SyntheticEvent casting
- Socket.io mock type conversion errors

---

## Golden Rule
