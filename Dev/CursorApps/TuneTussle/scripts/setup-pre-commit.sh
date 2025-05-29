#!/bin/bash

# TuneTussle Pre-commit Hook Setup
# Helps prevent CI failures by running checks locally before pushing

set -e

echo "🔧 Setting up TuneTussle pre-commit hooks..."

# Create pre-commit hook
PRE_COMMIT_HOOK=".git/hooks/pre-commit"
cat > "$PRE_COMMIT_HOOK" << 'EOF'
#!/bin/bash

echo "🔍 TuneTussle: Running pre-commit validation..."

# Check if we're in the right directory
if [ ! -f "package.json" ]; then
    echo "❌ Error: Must be run from project root directory"
    exit 1
fi

# Run linter with auto-fix first
echo "🔧 Running ESLint with auto-fix..."
npm run lint:frontend -- --fix 2>/dev/null || {
    echo "⚠️  Some ESLint issues couldn't be auto-fixed"
}

# CRITICAL: Run strict TypeScript check that matches CI environment
echo "🔍 Running strict TypeScript check (matches CI)..."
cd frontend && npx tsc --noEmit --strict --verbatimModuleSyntax true
FRONTEND_TS_EXIT_CODE=$?
cd ..

if [ $FRONTEND_TS_EXIT_CODE -ne 0 ]; then
    echo "❌ CRITICAL: TypeScript strict check failed!"
    echo "   This would fail in CI and consume GitHub Actions budget."
    echo "   See docs/development/CI_TYPESCRIPT_TROUBLESHOOTING.md"
    echo "   Common fixes:"
    echo "   - Use 'import { type MockInstance }' for type-only imports"
    echo "   - Implement 'Partial<Interface>' for mocks instead of 'as any'"
    echo "   - Fix Event to SyntheticEvent casting"
    exit 1
fi

echo "✅ TypeScript strict check passed"

# Stage any auto-fixed files
git add -A

echo "✅ Pre-commit validation complete"
EOF

# Create pre-push hook (more comprehensive)
PRE_PUSH_HOOK=".git/hooks/pre-push"
cat > "$PRE_PUSH_HOOK" << 'EOF'
#!/bin/bash

echo "🚀 TuneTussle: Running pre-push validation..."

# Run full CI validation suite
echo "🔍 Running complete CI validation (this prevents expensive CI failures)..."
npm run ci-check

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ CRITICAL: Full CI validation failed!"
    echo "   This push would fail CI and consume GitHub Actions budget."
    echo ""
    echo "📚 Troubleshooting guides:"
    echo "   - General: docs/development/TESTING.md"
    echo "   - TypeScript CI issues: docs/development/CI_TYPESCRIPT_TROUBLESHOOTING.md"
    echo ""
    echo "🔧 Quick fixes to try:"
    echo "   npm run lint:frontend -- --fix  # Auto-fix ESLint issues"
    echo "   cd frontend && npx tsc --noEmit --strict --verbatimModuleSyntax true  # Check TS"
    echo ""
    exit 1
fi

echo "✅ All validations passed - ready for CI"
EOF

# Make hooks executable
chmod +x "$PRE_COMMIT_HOOK"
chmod +x "$PRE_PUSH_HOOK"

# Add useful aliases to shell profile
ALIAS_FILE="$HOME/.tunetussle_aliases"
cat > "$ALIAS_FILE" << 'EOF'
# TuneTussle Development Aliases
# Add to your .bashrc, .zshrc, or equivalent:
# source ~/.tunetussle_aliases

alias tt-check="npm run ci-check"
alias tt-test="npm test"
alias tt-lint="npm run lint -- --fix"
alias tt-types="npm run type-check"
alias pre-push="npm run ci-check && echo '✅ Safe to push!'"

# Quick navigation
alias tt-be="cd backend"
alias tt-fe="cd frontend"
alias tt-root="cd .."

echo "🎵 TuneTussle aliases loaded!"
EOF

echo ""
echo "✅ Pre-commit hooks setup complete!"
echo ""
echo "📋 What was installed:"
echo "   • Pre-commit hook: Runs lint --fix before commits"
echo "   • Pre-push hook: Runs full CI validation before pushes"
echo "   • Shell aliases: Available in ~/.tunetussle_aliases"
echo ""
echo "🔧 To use the aliases, add this to your shell profile:"
echo "   echo 'source ~/.tunetussle_aliases' >> ~/.bashrc"
echo "   echo 'source ~/.tunetussle_aliases' >> ~/.zshrc"
echo ""
echo "🧪 Test the setup:"
echo "   npm run ci-check    # Should pass if everything is working"
echo ""
echo "💡 Remember: Always run 'npm run ci-check' before pushing!"
echo "   This prevents expensive CI failures and GitHub Actions budget consumption."
echo ""
