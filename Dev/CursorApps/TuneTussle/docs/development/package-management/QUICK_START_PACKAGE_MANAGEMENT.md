# Package Management Quick Start

**5-Minute Setup for Automated Dependency Management**

## ✅ Current System Status

TuneTussle has **fully automated package management** that safely updates dependencies every Monday with comprehensive testing.

## 🚀 Essential Commands

```bash
# Check all dependency status (run weekly)
./scripts/dependency-check.sh

# Apply safe patch updates manually
./scripts/safe-update.sh

# Quick health check
cd backend && npm outdated && npm audit
cd frontend && npm outdated && npm audit
```

## 📋 What Happens Automatically

- **Every Monday 9 AM UTC**: Patch updates applied with 521-test validation
- **Security issues**: Immediate automated fixes with urgent PR creation
- **All changes**: Backed up with rollback capability

## 🎯 Getting Started

1. **Run your first check**: `./scripts/dependency-check.sh`
2. **Review generated reports** in `dependency-reports/` folder
3. **Monitor GitHub** for Monday's automated PRs

## 📖 Need More Details?

- **[Complete Package Management Guide](PACKAGE_MANAGEMENT.md)** - Full technical documentation
- **[Simple Staging Strategy](SIMPLE_STAGING_STRATEGY.md)** - Testing approach details

**System maintains TuneTussle's 99.4% test pass rate!** ✅ 