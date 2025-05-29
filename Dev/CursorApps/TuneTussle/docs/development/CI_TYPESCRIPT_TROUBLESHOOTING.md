# CI TypeScript Compilation Troubleshooting

**⚠️ CRITICAL READING**: This document covers CI failure scenarios that can consume GitHub Actions budget if not caught locally.

## Overview

This guide documents real CI TypeScript compilation failures that occurred in the TuneTussle project and how to diagnose/fix them. These errors passed local TypeScript checks but failed in the stricter CI environment.

## The Problem

**Date**: May 29, 2025
**Cost**: Nearly consumed entire GitHub Actions budget
**Root Cause**: Environment differences between local TypeScript and CI TypeScript settings

### What Happened
1. ✅ Local `npm run type-check:frontend` passed
2. ✅ Local `npm run ci-check` passed TypeScript compilation
3. ❌ GitHub CI failed with TypeScript compilation errors
4. 💰 CI kept retrying, consuming budget

## Specific CI TypeScript Errors Encountered

### 1. MockInstance Import Error
```
'MockInstance' is a type and must be imported using a type-only import when 'verbatimModuleSyntax' is enabled
```

**File**: `frontend/src/contexts/SocketContext.test.tsx`

**Problem**:
```typescript
// ❌ Wrong - runtime import when only used as type
import { vi, describe, beforeEach, afterEach, it, expect, MockInstance } from 'vitest';
```

**Solution**:
```typescript
// ✅ Correct - type-only import
import { vi, describe, beforeEach, afterEach, it, expect, type MockInstance } from 'vitest';
```

### 2. MockAudioElement Type Errors
```
Type 'MockAudioElement' is missing the following properties from type 'HTMLAudioElement': addEventListener, removeEventListener, autoplay, buffered, and 350 more.
```

**File**: `frontend/src/services/audioService.test.ts`

**Problem**:
```typescript
// ❌ Wrong - incomplete mock with any casting
class MockAudio {
  public volume = 1;
  // ... minimal properties
}
global.Audio = MockAudio as any;
```

**Solution**:
```typescript
// ✅ Correct - implements Partial<HTMLAudioElement>
class MockAudio implements Partial<HTMLAudioElement> {
  public volume = 1;
  public currentTime = 0;
  // ... all needed properties

  async play(): Promise<void> {
    return Promise.resolve();
  }
}
global.Audio = MockAudio as unknown as typeof Audio;
```

### 3. Event Type Casting Error
```
Conversion of type 'Event' to type 'SyntheticEvent<HTMLAudioElement, Event>' may be a mistake because neither type sufficiently overlaps with the other.
```

**File**: `frontend/src/components/MobileAudioPlayer.tsx`

**Problem**:
```typescript
// ❌ Wrong - unsafe type casting
const handleError = (e: Event) => onError?.(e as React.SyntheticEvent<HTMLAudioElement, Event>);
```

**Solution**:
```typescript
// ✅ Correct - proper SyntheticEvent creation
const handleError = (e: Event) => {
  if (onError) {
    const syntheticEvent = {
      ...e,
      currentTarget: audio,
      target: audio,
      nativeEvent: e,
      // ... all required SyntheticEvent properties
    } as React.SyntheticEvent<HTMLAudioElement, Event>;
    onError(syntheticEvent);
  }
};
```

### 4. Socket.io Mock Type Conversion
```
Conversion of type 'SocketIOClientStatic' to type '{ io: MockInstance }' may be a mistake
```

**File**: `frontend/src/contexts/SocketContext.test.tsx`

**Problem**:
```typescript
// ❌ Wrong - unsafe casting of imported module
const SIOClient = await import('socket.io-client');
mockIo = (SIOClient as { io: MockInstance }).io || vi.fn();
```

**Solution**:
```typescript
// ✅ Correct - direct property access with type assertion
const SIOClient = await import('socket.io-client');
mockIo = SIOClient.io as MockInstance;
```

## Why These Passed Locally But Failed in CI

### Environment Differences

1. **TypeScript Version Differences**: CI may use different TypeScript version
2. **Stricter CI Config**: `verbatimModuleSyntax: true` enforced more strictly in CI
3. **Node Modules Differences**: Different dependency versions between local and CI
4. **Compilation Context**: CI runs in isolated environment with stricter checking

### Key Lesson

The `verbatimModuleSyntax: true` setting in `frontend/tsconfig.app.json` requires:
- Type-only imports for types: `import { type MockInstance }`
- Explicit casting for complex type conversions
- Complete interface implementations rather than `any` shortcuts

## Diagnostic Steps

### 1. Reproduce CI Environment Locally

```bash
# Test with exact CI strictness
cd frontend
npx tsc --noEmit --strict --verbatimModuleSyntax true

# Alternative: Clear node_modules and reinstall
rm -rf node_modules package-lock.json
npm install
npm run type-check
```

### 2. Check for Environment Differences

```bash
# Compare TypeScript versions
npx tsc --version

# Check if CI uses different tsconfig
cat frontend/tsconfig.json
cat frontend/tsconfig.app.json
```

### 3. Test Mock Types Specifically

Focus on test files that use complex mocking:
- Socket.io mocks
- Audio/Media element mocks
- Event handling mocks

## Prevention Strategy

### 1. Enhanced Pre-Commit Checks

Update your pre-commit hook to include strict TypeScript checking:

```bash
#!/bin/bash
# .git/hooks/pre-commit

echo "🔍 Running strict TypeScript check..."
cd frontend && npx tsc --noEmit --strict --verbatimModuleSyntax true
if [ $? -ne 0 ]; then
  echo "❌ TypeScript strict check failed"
  exit 1
fi

echo "🔍 Running full CI validation..."
cd .. && npm run ci-check
if [ $? -ne 0 ]; then
  echo "❌ CI check failed - see errors above"
  exit 1
fi
```

### 2. Regular CI Environment Testing

```bash
# Weekly check to ensure local matches CI
npm run ci-check && echo "✅ Ready for CI"
```

### 3. Mock Type Guidelines

**Always use proper TypeScript for mocks:**

```typescript
// ✅ DO: Implement proper interfaces
class MockComponent implements Partial<ActualInterface> {
  requiredMethod(): ReturnType {
    return expectedValue;
  }
}

// ✅ DO: Use type-only imports
import { type MockInstance } from 'vitest';

// ✅ DO: Proper type casting
const mock = createMock() as unknown as typeof RealType;

// ❌ DON'T: Use any shortcuts
const mock = createMock() as any;
```

## Emergency Fix Procedure

If CI fails with TypeScript errors:

### 1. **STOP** - Don't Push More Changes
Pushing more commits will consume more CI budget.

### 2. **Reproduce Locally**
```bash
cd frontend
npx tsc --noEmit --strict --verbatimModuleSyntax true
```

### 3. **Fix Type Issues**
- Check import statements (use `type` imports for types)
- Verify mock implementations are complete
- Ensure proper type casting (avoid `as any`)

### 4. **Verify Fix**
```bash
npm run ci-check
```

### 5. **Commit and Push**
Only after local CI check passes completely.

## Files Most Likely to Have CI Type Issues

1. **Test files with complex mocks**:
   - `frontend/src/**/*.test.tsx`
   - `frontend/src/**/*.test.ts`

2. **Socket.io related files**:
   - `frontend/src/contexts/SocketContext.test.tsx`
   - `frontend/src/utils/socketTestUtils.ts`

3. **Audio/Media component tests**:
   - `frontend/src/services/audioService.test.ts`
   - `frontend/src/components/*AudioPlayer*.tsx`

4. **Event handling components**:
   - Components with DOM event listeners
   - Custom event handlers

## Success Metrics

After implementing these fixes:
- ✅ TypeScript compilation passes in CI
- ✅ 336/341 tests passing (98.5% success rate)
- ✅ No CI budget consumption from TypeScript errors
- ✅ Local testing matches CI behavior

---

**💡 Remember**: TypeScript strictness in CI is a feature, not a bug. It catches issues that could cause runtime errors in production.
