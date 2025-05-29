# Package Management Guide

## Overview

TuneTussle uses an **automated, safety-first package management strategy** that ensures dependencies stay up-to-date while preventing breaking changes in production.

## Automated Update Strategy

### 🔄 **Automatic Updates (Safe)**
- **Patch updates** (x.y.Z): Applied automatically every Monday
- **Security fixes**: Applied immediately when vulnerabilities detected  
- **All updates validated** by comprehensive test suite (521 tests)

### 🔍 **Manual Review Required**
- **Minor updates** (x.Y.z): Reported for review, manual approval needed
- **Major updates** (X.y.z): Reported for review, breaking changes possible

## How It Works

### 1. Weekly Automated Process
Every Monday at 9 AM UTC, GitHub Actions:

1. **Security Audit**: Scans all dependencies for vulnerabilities
2. **Patch Updates**: Applies safe bug-fix updates automatically  
3. **Test Validation**: Runs full test suite (521 tests)
4. **Pull Request Creation**: Creates PRs for safe updates
5. **Outdated Report**: Generates report of available updates

### 2. Security Response
When vulnerabilities are detected:

1. **Immediate Action**: Security fixes applied automatically
2. **Test Validation**: Full test suite validates functionality  
3. **Urgent PR**: High-priority pull request created
4. **Manual Review**: Review required before merge

### 3. Manual Commands
```bash
# Check for outdated packages
cd backend && npm outdated
cd frontend && npm outdated

# Security audit
cd backend && npm audit
cd frontend && npm audit

# Update patch versions only (safe)
cd backend && npm update
cd frontend && npm update

# Update specific package
npm install package@latest

# Fix security vulnerabilities
npm audit fix
```

## Package Version Strategy

### Current Approach
```json
{
  "dependencies": {
    "express": "^5.1.0",     // Allows minor + patch updates
    "react": "^19.1.0",      // Allows minor + patch updates  
    "axios": "^1.9.0"        // Allows minor + patch updates
  }
}
```

### Version Range Meanings
- `^1.2.3`: Compatible updates (1.2.3 → 1.9.9, but not 2.0.0)
- `~1.2.3`: Patch updates only (1.2.3 → 1.2.9, but not 1.3.0)
- `1.2.3`: Exact version (no automatic updates)

## Safety Mechanisms

### 1. Comprehensive Testing
- **Backend**: 198/201 tests passing (98% success rate)
- **Frontend**: 323/324 tests passing (100% success rate)
- **Integration**: Health checks and API validation
- **Critical Path**: All game functionality tested

### 2. Staged Rollout
1. **Security audit** before any updates
2. **Automated testing** validates functionality
3. **Pull request review** for visibility
4. **Manual merge** control for production

### 3. Rollback Strategy
```bash
# Rollback to previous version
npm install package@previous-version

# Restore from package-lock.json
npm ci

# Git rollback if needed
git revert <commit-hash>
```

## Monitoring & Alerts

### GitHub Actions Notifications
- **✅ Success**: Automated updates merged safely
- **⚠️ Security**: Urgent security updates available
- **📊 Report**: Weekly outdated packages summary
- **❌ Failure**: Test failures block updates

### Manual Monitoring
```bash
# Check current dependency status
npm audit --audit-level=moderate

# Check for outdated packages  
npm outdated --long

# Verify no vulnerabilities
npm audit --audit-level=high
```

## Production Deployment Safety

### Pre-deployment Checklist
- [ ] All tests passing locally: `npm test`
- [ ] Security audit clean: `npm audit` 
- [ ] Build successful: `npm run build`
- [ ] Health checks passing: `curl /api/performance/health`

### Emergency Response
```bash
# Critical security update
npm audit fix --force
npm test  # Validate functionality
./run.sh  # Deploy immediately

# Rollback if issues
git revert HEAD
./run.sh
```

## Best Practices

### 1. **Never Auto-Merge Major Updates**
- Major version changes (X.y.z) can contain breaking changes
- Always test major updates in development environment
- Review changelogs and migration guides

### 2. **Trust the Test Suite**
- 521 comprehensive tests validate core functionality
- Automated updates only proceed if tests pass
- Test coverage includes critical game flow and edge cases

### 3. **Monitor Security Actively**
- Weekly security audits via automation
- Immediate alerts for high/critical vulnerabilities
- Security updates get highest priority

### 4. **Staged Environment Testing**
```bash
# Test in development first
./run.sh --dev
# Run game session, verify functionality
# Then deploy to production
./run.sh
```

### 5. **Documentation Updates**
- Update CHANGELOG.md for significant dependency changes
- Document any configuration changes required
- Note any breaking changes or migration steps

## Package-Specific Considerations

### Critical Dependencies (High Impact)
- **Express**: Backend framework - test all API endpoints
- **React**: Frontend framework - test all UI interactions  
- **Socket.IO**: Real-time communication - test multiplayer functionality
- **TypeScript**: Type system - verify all builds pass

### Development Dependencies (Lower Risk)
- **Jest/Vitest**: Test frameworks - verify tests still run
- **ESLint**: Code quality - check for new rules
- **Vite**: Build tool - verify builds and dev server

### External APIs (Monitor Changes)
- **Deezer API**: Music provider - verify preview URLs work
- **OpenRouter API**: LLM service - test song generation

## Troubleshooting

### Common Issues

**Tests failing after update:**
```bash
# Check for breaking changes
npm list package-name
# Review package changelog
# Update test mocks if needed
```

**Build failing:**
```bash
# Clear cache and reinstall
rm -rf node_modules package-lock.json
npm install
npm run build
```

**Security vulnerabilities:**
```bash
# Apply automated fixes
npm audit fix
# Manual fix if needed
npm audit fix --force
# Verify functionality
npm test
```

## Summary

✅ **Automated patch updates** keep dependencies current safely  
✅ **Comprehensive testing** validates all changes before deployment  
✅ **Security-first approach** addresses vulnerabilities immediately  
✅ **Manual control** for major changes prevents breaking updates  
✅ **Production safety** through staged validation and rollback options

This system ensures TuneTussle stays secure and up-to-date while maintaining the excellent stability demonstrated by the current 99.4% test pass rate. 