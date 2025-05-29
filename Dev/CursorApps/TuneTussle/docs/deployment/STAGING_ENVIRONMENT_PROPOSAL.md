# Advanced Staging Environment Implementation

**Docker-Based Isolated Testing for Enhanced Package Validation**

## 🎯 Purpose

While TuneTussle's [Simple Staging Strategy](../development/package-management/SIMPLE_STAGING_STRATEGY.md) works well for basic testing, this guide provides **Docker-based isolated environments** for advanced validation scenarios.

## 🏗️ When to Use Docker Staging

**Use Simple Staging Strategy (Current) For:**
- Weekly dependency updates
- Basic functionality testing
- Memory-constrained environments (1GB RAM)

**Use Docker Staging (This Guide) For:**
- Complex integration testing
- Multi-service validation
- Load testing scenarios
- CI/CD pipeline enhancement

## 🛠️ Docker Implementation

### docker-compose.staging.yml
```yaml
version: '3.8'
services:
  staging-backend:
    build: 
      context: ./backend
      dockerfile: Dockerfile.staging
    environment:
      - NODE_ENV=staging
      - OPENROUTER_API_KEY=${OPENROUTER_API_KEY}
    ports:
      - "4001:4000"
    volumes:
      - ./backend:/app
      
  staging-frontend:
    build: 
      context: ./frontend
      dockerfile: Dockerfile.staging
    environment:
      - REACT_APP_BACKEND_URL=http://localhost:4001
    ports:
      - "3001:3000"
    volumes:
      - ./frontend:/app
      
  staging-redis:
    image: redis:alpine
    ports:
      - "6380:6379"
```

### GitHub Actions Integration
```yaml
# .github/workflows/docker-staging-test.yml
name: Docker Staging Environment

on:
  pull_request:
    paths:
      - 'backend/package*.json'
      - 'frontend/package*.json'

jobs:
  docker-staging:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Create Docker Staging Environment
        run: docker-compose -f docker-compose.staging.yml up -d
        
      - name: Run Integration Tests
        run: |
          # Wait for services to be ready
          sleep 30
          # Run comprehensive test suite
          docker exec staging-backend npm test
          docker exec staging-frontend npm test
          
      - name: Cleanup
        if: always()
        run: |
          docker-compose -f docker-compose.staging.yml down -v
          docker system prune -f
```

### Usage Commands
```bash
# Start Docker staging environment
docker-compose -f docker-compose.staging.yml up -d

# Run tests in staging
docker exec staging-backend npm test
docker exec staging-frontend npm test

# Apply updates in staging
docker exec staging-backend npm update
docker exec staging-frontend npm update

# Cleanup staging environment
docker-compose -f docker-compose.staging.yml down -v
```

## 🎯 Integration with Existing System

This Docker staging complements the existing simple staging strategy:

1. **Daily development**: Use simple staging (`./scripts/test-updates.sh`)
2. **Complex validation**: Use Docker staging for multi-service testing
3. **Production deployment**: Both validate before Alpine deployment

## 📋 Implementation Steps

1. **Create staging configs** (above files)
2. **Test Docker environment** locally
3. **Add to CI/CD pipeline** (optional)
4. **Document team usage** patterns

**Note**: The [Simple Staging Strategy](../development/package-management/SIMPLE_STAGING_STRATEGY.md) remains the primary approach for routine dependency management. 