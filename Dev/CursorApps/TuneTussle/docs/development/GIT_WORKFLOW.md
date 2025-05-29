# Git Workflow Guide

**Simple, clear git workflow for TuneTussle development**

## 🌳 Branch Strategy

### **Main Branch**: `main`
- **Production-ready code** 
- **Automated dependency updates** (Mondays 9 AM UTC)
- **All feature PRs target this branch**
- **Direct pushes allowed** for hotfixes

### **Feature Branches**: `feature/*`
- All new development
- Named descriptively: `feature/post-round-results`
- Always branch from `main`
- Always merge back to `main`

### **Bug Fix Branches**: `fix/*`  
- Bug fixes and patches
- Named descriptively: `fix/scoring-calculation-bug`
- Always branch from `main`
- Always merge back to `main`

## 🚀 Developer Workflows

### Starting New Feature
```bash
# 1. Switch to main and get latest
git checkout main
git pull origin main

# 2. Create feature branch
git checkout -b feature/your-feature-name

# 3. Work on your feature
# ... make changes ...

# 4. Commit your work
git add .
git commit -m "feat: description of your feature"

# 5. Push branch
git push origin feature/your-feature-name

# 6. Create Pull Request (via GitHub UI or CLI)
gh pr create \
  --title "Feature: Your Feature Name" \
  --body "Description of what this feature does" \
  --base main \
  --head feature/your-feature-name
```

### Quick Bug Fix
```bash
# 1. Start from main
git checkout main
git pull origin main

# 2. Create fix branch
git checkout -b fix/bug-description

# 3. Fix the bug
# ... make changes ...

# 4. Commit and push
git add .
git commit -m "fix: description of bug fix"
git push origin fix/bug-description

# 5. Create PR
gh pr create --title "Fix: Bug Description" --base main
```

### Hotfix (Critical/Urgent)
```bash
# For critical issues that need immediate deployment
git checkout main
git pull origin main

# Make your fix
git add .
git commit -m "fix: critical issue description"
git push origin main

# Deploy immediately if needed
```

## 📝 Commit Message Format

### Standard Format
```
<type>: <description>

[optional body]
```

### Types
- **feat**: New feature
- **fix**: Bug fix
- **docs**: Documentation changes
- **style**: Code formatting (no functional changes)
- **refactor**: Code restructuring (no functional changes)
- **test**: Adding or updating tests
- **chore**: Maintenance tasks (dependencies, build, etc.)

### Examples
```bash
feat: add post-round results screen with artwork display
fix: resolve scoring calculation for partial matches
docs: update README with Alpine Linux deployment guide
style: format components with prettier
refactor: extract audio service for better separation
test: add comprehensive tests for answer parsing
chore: update dependencies to latest patch versions
```

## 🔄 Day-to-Day Workflow

### Starting Your Day
```bash
# Get latest changes from main
git checkout main
git pull origin main

# If working on existing feature
git checkout feature/your-feature
git merge main  # or git rebase main
```

### During Development
```bash
# Commit frequently with good messages
git add .
git commit -m "feat: implement basic artwork display"

# Push regularly to backup your work
git push origin feature/your-feature
```

### Before Creating PR
```bash
# Make sure you're up to date with main
git checkout main
git pull origin main
git checkout feature/your-feature
git merge main

# Run tests to ensure everything works
cd backend && npm test
cd ../frontend && npm test

# Run staging test to verify compatibility
./scripts/test-updates.sh
```

## 🤖 Automated Dependency Updates

### How It Works
Every Monday at 9 AM UTC, GitHub Actions:

1. **Creates staging environment**
2. **Applies dependency updates** (patch versions only)
3. **Runs all 521 tests** to validate changes
4. **Creates PR** if all tests pass
5. **Targets main branch** for manual review

### Update PR Branches
GitHub Actions creates branches like:
```
dependency-updates-20250113
dependency-updates-20250120
dependency-updates-20250127
```

### Your Role
1. **Review the automated PR**
2. **Check what was updated**
3. **Merge if changes look good**
4. **Test manually if needed**

### Manual Dependency Testing
```bash
# Test updates before automation runs
./scripts/test-updates.sh

# Alpine-specific testing
./scripts/alpine-staging-test.sh

# Check what would be updated
cd backend && npm outdated
cd frontend && npm outdated
```

## 🔍 Code Review Process

### Creating Good PRs
```bash
# Good PR title examples:
"Feature: Add post-round results with artwork display"
"Fix: Resolve answer parsing for comma-separated input"
"Chore: Update dependencies (automated staging validated)"

# Include description:
- What does this change?
- Why is it needed?
- How was it tested?
- Any breaking changes?
```

### Reviewing PRs
1. **Check the description** - Does it explain the change?
2. **Review the code** - Is it clean and well-structured?
3. **Test locally** if significant changes
4. **Check tests pass** - All 521 tests should pass
5. **Approve and merge** or request changes

## 🚨 Emergency Procedures

### Rollback Bad Deployment
```bash
# If something breaks in production
git checkout main
git log --oneline -10  # Find last good commit

# Revert to last good state
git revert <bad-commit-hash>
git push origin main

# Or reset to specific commit (use carefully!)
git reset --hard <good-commit-hash>
git push origin main --force-with-lease
```

### Fix Broken Main Branch
```bash
# If main branch is broken
git checkout main
git pull origin main

# Create fix branch
git checkout -b fix/urgent-main-branch-fix

# Fix the issue
git add .
git commit -m "fix: restore main branch functionality"
git push origin fix/urgent-main-branch-fix

# Fast-track merge to main
gh pr create --title "URGENT: Fix main branch" --base main
# Get immediate review and merge
```

## 📋 Branch Cleanup

### After Merging PR
```bash
# Delete local feature branch
git branch -d feature/your-feature

# Delete remote feature branch (if not auto-deleted)
git push origin --delete feature/your-feature
```

### Periodic Cleanup
```bash
# See all branches
git branch -a

# Delete old local branches that are merged
git branch --merged main | grep -v main | xargs git branch -d

# Prune remote tracking branches
git remote prune origin
```

## 🎯 Best Practices

### Branching
- ✅ **Always branch from main**
- ✅ **Use descriptive branch names**
- ✅ **Keep branches focused** (one feature/fix per branch)
- ✅ **Delete branches after merging**

### Committing  
- ✅ **Commit frequently** with good messages
- ✅ **Test before committing** (at least run the app)
- ✅ **Use consistent commit format**
- ✅ **Don't commit secrets** (API keys, passwords)

### Pull Requests
- ✅ **Small, focused PRs** are easier to review
- ✅ **Good descriptions** help reviewers
- ✅ **All tests must pass** before merging
- ✅ **Get code review** for significant changes

### Merging
- ✅ **Squash commits** for clean history (if multiple small commits)
- ✅ **Use merge commits** for feature branches (preserves context)
- ✅ **Delete feature branches** after merging

## 🔧 Git Configuration

### One-time Setup
```bash
# Configure your identity
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

# Useful aliases
git config --global alias.st status
git config --global alias.co checkout
git config --global alias.br branch
git config --global alias.last 'log -1 HEAD'

# Better log format
git config --global alias.lg "log --oneline --graph --decorate --all"
```

### Project-specific Setup
```bash
# Clone the repository
git clone https://github.com/vidarbrekke/tunetussle.git
cd TuneTussle

# Verify remote
git remote -v

# Set up upstream if needed
git remote add upstream https://github.com/vidarbrekke/tunetussle.git
```

## 📚 Helpful Commands

### Status and Information
```bash
git status                    # See current state
git log --oneline -10        # Recent commits
git branch -a                # All branches
git remote -v                # Remote repositories
```

### Working with Changes
```bash
git diff                     # See unstaged changes
git diff --staged            # See staged changes
git add .                    # Stage all changes
git add <file>               # Stage specific file
git reset <file>             # Unstage specific file
git checkout -- <file>      # Discard changes to file
```

### Branch Operations
```bash
git checkout -b <branch>     # Create and switch to branch
git checkout <branch>        # Switch to existing branch
git merge <branch>           # Merge branch into current
git branch -d <branch>       # Delete local branch
```

### Remote Operations
```bash
git fetch origin             # Get latest from remote
git pull origin main         # Fetch and merge main
git push origin <branch>     # Push branch to remote
git push origin --delete <branch>  # Delete remote branch
```

---

**Need help?** Check the [Developer Guide](DEVELOPER_GUIDE.md) or create an issue on GitHub.

**Happy coding!** 🚀 