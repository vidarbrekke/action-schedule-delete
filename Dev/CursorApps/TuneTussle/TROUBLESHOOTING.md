# TuneTussle Troubleshooting Guide

## "Start Game" Button Issues

### Issue: Start Game Button Does Nothing / Socket.IO 502 Errors

**Symptoms:**
- Clicking "Start Game" as judge doesn't redirect to game management screen
- Frontend console shows Socket.IO connection errors: `502 Bad Gateway`
- Backend logs show: `Music provider "deezer NODE_ENV=production" not supported`
- Songs in game have `trackUrl: undefined` instead of audio URLs

**Root Cause:**
The `MUSIC_PROVIDER` environment variable gets corrupted during deployment, containing extra text like `"deezer NODE_ENV=production"` instead of just `"deezer"`.

**Diagnosis Steps:**
1. Check Socket.IO connectivity:
   ```bash
   curl "https://tunetussle.com/socket.io/?EIO=4&transport=polling"
   # Should return: 0{"sid":"...","upgrades":["websocket"]...}
   ```

2. Check backend environment variable:
   ```bash
   ssh -p 2222 admin@69.164.209.52 "cat /opt/tunetussle/current/backend/.env | grep MUSIC_PROVIDER"
   # Should show: MUSIC_PROVIDER=deezer
   ```

3. Check backend logs for music provider errors:
   ```bash
   ssh -p 2222 admin@69.164.209.52 "ps aux | grep 'node dist/index.js'"
   ```

**Resolution:**
1. **Fix environment variable:**
   ```bash
   ssh -p 2222 admin@69.164.209.52 "sudo sed -i 's/MUSIC_PROVIDER=deezer.*/MUSIC_PROVIDER=deezer/' /opt/tunetussle/current/backend/.env"
   ```

2. **Kill all Node.js processes:**
   ```bash
   ssh -p 2222 admin@69.164.209.52 "sudo pkill -f 'node dist/index.js'"
   ssh -p 2222 admin@69.164.209.52 "sudo rc-service tunetussle stop"
   ```

3. **Restart service cleanly:**
   ```bash
   ssh -p 2222 admin@69.164.209.52 "sudo rc-service tunetussle start"
   ```

4. **Verify fix:**
   ```bash
   # Test Socket.IO endpoint
   curl -s "https://tunetussle.com/socket.io/?EIO=4&transport=polling" | head -1
   
   # Test game creation
   curl -s -X POST https://tunetussle.com/api/games \
     -H "Content-Type: application/json" \
     -H "Origin: https://tunetussle.com" \
     -d '{"judgeName": "TestJudge", "prompt": "test song", "numberOfRounds": 1}' \
     | jq '.success'
   ```

**Prevention:**
- Always verify `.env` file contents after deployment
- Ensure environment variables don't contain extra whitespace or appended text
- Use proper service restart procedures instead of manual process management

## Environment Variable Corruption

**Common Cause:** Environment variables can get corrupted during deployment if:
- Multiple deployment scripts run concurrently
- Environment files are not properly quoted
- Process substitution or command concatenation affects variable parsing

**Best Practices:**
- Use quoted values in `.env` files: `MUSIC_PROVIDER="deezer"`
- Verify environment after each deployment
- Use atomic deployment methods (blue-green deployment)
- Test critical functionality after deployment

## Service Management (Alpine Linux + OpenRC)

**Key Commands:**
```bash
# Check service status
sudo rc-service tunetussle status

# Start/stop/restart service
sudo rc-service tunetussle start
sudo rc-service tunetussle stop
sudo rc-service tunetussle restart

# Check running processes
ps aux | grep 'node dist/index.js'

# Kill specific processes
sudo pkill -f 'node dist/index.js'
```

**Important Notes:**
- Alpine Linux uses OpenRC, not systemd
- Service logs may not be available via `journalctl`
- Always verify process is running as correct user (`appuser`, not `root`)
- Symbolic link warnings in `/var/run` can be ignored if service starts successfully 

## Automated Package Updates & Local Compilation

### Issue: Automated Updates Don't Deploy to Production

**Symptoms:**
- GitHub Actions successfully merges dependency updates
- Tests pass in CI/CD
- Production server still runs old code with outdated dependencies
- `npm list` on server shows old versions

**Root Cause:**
The automated package update system updates `package.json` and `package-lock.json` in the repository, but production runs from locally-compiled `dist/` directories that aren't automatically updated.

**Current Flow (Broken):**
1. ✅ Monday: Automated updates run, tests pass, PR merged
2. ❌ Production: Still running old compiled code from previous local build
3. ❌ Security/dependency fixes not applied in production

**Solution Options:**

#### **Option A: Auto-Deploy After Updates (Recommended)**
Use the new `.github/workflows/deploy-after-merge.yml` workflow:

```bash
# This workflow automatically:
# 1. Detects when package files are updated
# 2. Compiles TypeScript with new dependencies  
# 3. Deploys to production server
# 4. Restarts services
```

**Required Secrets:**
- `DEPLOY_SSH_KEY` - SSH private key for server access
- `SERVER_HOST` - `69.164.209.52`
- `SERVER_USER` - `admin`  
- `SERVER_PORT` - `2222`

#### **Option B: Server-Side Compilation**
❌ **Not viable for Alpine Linux servers with 1GB RAM**

```bash
# This would fail due to memory constraints:
ssh -p 2222 admin@69.164.209.52 "
  cd /opt/tunetussle/current/backend && 
  npm ci && 
  npm run build:prod  # ← OOM Error: TypeScript compilation requires ~2-4GB
"
```

**Why this doesn't work:**
- TypeScript compilation requires 2-4GB RAM
- Alpine Linux server only has 1GB RAM
- Process would be killed by OOM (Out of Memory) killer
- This is why we moved to local compilation originally

**Use Option A (Auto-Deploy) instead** - compiles in GitHub Actions with plenty of memory.

#### **Option C: Manual Deployment After Updates**
Monitor for automated updates and manually deploy:

```bash
# Check for recent dependency updates
git log --oneline --grep="chore: automated dependency updates"

# If found, redeploy manually
npm run build:prod
./deployment/scripts/deploy-to-alpine.sh
```

**Recommendation:**
Use **Option A** for full automation while maintaining the security benefits of local compilation.

**Prevention:**
- Set up GitHub Actions secrets for automatic deployment
- Monitor deployment logs for automated update deployments
- Test the auto-deploy workflow with manual triggers first 