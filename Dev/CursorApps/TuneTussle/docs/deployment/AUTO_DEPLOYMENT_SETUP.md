# Automated Deployment After Dependency Updates

## Overview

The `deploy-after-merge.yml` workflow automatically deploys dependency updates to production when they're merged to the `main` branch. This solves the issue where automated package updates were merged but never deployed due to local TypeScript compilation.

## How It Works

```mermaid
graph LR
    A[Monday: Dependency Updates] --> B[Tests Pass]
    B --> C[PR Merged to main]
    C --> D[🔄 Auto-Deploy Triggered]
    D --> E[Compile TypeScript]
    E --> F[Deploy to Server]
    F --> G[✅ Production Updated]
```

## Setup Required

### 1. GitHub Actions Secrets

Add these secrets to your GitHub repository:

```bash
# In GitHub Repository Settings > Secrets and variables > Actions

DEPLOY_SSH_KEY      # SSH private key for server access
SERVER_HOST         # 69.164.209.52  
SERVER_USER         # admin
SERVER_PORT         # 2222
```

### 2. Generate SSH Key for Deployment

```bash
# Generate a dedicated deployment key
ssh-keygen -t rsa -b 4096 -C "github-actions-deployment" -f ~/.ssh/github_deploy

# Add public key to server
ssh-copy-id -i ~/.ssh/github_deploy.pub -p 2222 admin@69.164.209.52

# Add private key to GitHub Secrets as DEPLOY_SSH_KEY
cat ~/.ssh/github_deploy
# Copy the entire output to GitHub Secrets
```

### 3. Test the Workflow

```bash
# Test with manual trigger
# Go to GitHub Actions > Auto-Deploy After Dependency Updates > Run workflow

# Or test by updating a package file
echo "# Test comment" >> backend/package.json
git add backend/package.json
git commit -m "test: trigger auto-deployment"
git push origin main
```

## Workflow Triggers

The workflow runs when these files are modified on `main` branch:

- `backend/package-lock.json`
- `frontend/package-lock.json` 
- `backend/package.json`
- `frontend/package.json`

## What It Does

1. **🔍 Detects Changes**: Triggered by package file modifications
2. **📦 Installs Dependencies**: Uses updated package files
3. **🧪 Runs Tests**: Verifies all tests pass with new dependencies
4. **🔨 Builds Production**: Compiles TypeScript with new dependencies
5. **📦 Creates Packages**: Creates tar files for deployment
6. **🚀 Deploys**: 
   - Uploads compiled code to server
   - Updates backend and frontend
   - Restarts services
7. **🧹 Cleanup**: Removes temporary files and SSH keys

## Monitoring

### Check Deployment Status

```bash
# View GitHub Actions logs
# Repository > Actions > Auto-Deploy After Dependency Updates

# Check production deployment
ssh -p 2222 admin@69.164.209.52 "
  cd /opt/tunetussle/current/backend && 
  npm list --depth=0 | grep -E '(express|socket|typescript)'
"
```

### Verify Services Running

```bash
# Check backend service
ssh -p 2222 admin@69.164.209.52 "ps aux | grep 'node dist/index.js'"

# Check application health
curl -s https://tunetussle.com/api/health
```

## Benefits

✅ **Fully Automated**: No manual intervention needed for dependency updates
✅ **Security**: Patches and security updates deploy immediately  
✅ **Tested**: All updates verified before deployment
✅ **Fast**: Deploys within 5-10 minutes of merge
✅ **Safe**: Local compilation ensures consistent builds

## Troubleshooting

### Deployment Fails

1. **Check SSH Connection**:
   ```bash
   # Test SSH key in GitHub Actions secrets
   ssh -p 2222 -i <DEPLOY_SSH_KEY> admin@69.164.209.52 "echo 'Connection test'"
   ```

2. **Check Server Space**:
   ```bash
   ssh -p 2222 admin@69.164.209.52 "df -h"
   ```

3. **Check Service Status**:
   ```bash
   ssh -p 2222 admin@69.164.209.52 "sudo rc-service tunetussle status"
   ```

### Updates Not Deploying

1. **Verify Workflow Trigger**: Check if package files were actually modified
2. **Check GitHub Actions**: Look for failed workflow runs
3. **Manual Fallback**: Deploy manually if needed:
   ```bash
   npm run build:prod
   ./deployment/scripts/deploy-to-alpine.sh
   ```

## Alternative: Disable Auto-Deployment

If you prefer manual deployment after updates:

```bash
# Rename or delete the workflow file
mv .github/workflows/deploy-after-merge.yml .github/workflows/deploy-after-merge.yml.disabled
```

Then monitor for updates and deploy manually:

```bash
# Check for recent dependency updates
git log --oneline --grep="chore: automated dependency updates" -10
```

## Security Considerations

- SSH key is scoped only for deployment user (`admin`)
- Workflow only triggers on specific package file changes
- Full test suite runs before any deployment
- Server access is logged and auditable
- No secrets are exposed in logs 