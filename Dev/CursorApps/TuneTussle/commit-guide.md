# Git Commit and Push Guide for TuneTussle

This guide outlines the recommended workflow for committing and pushing code changes to the TuneTussle repository, located at `/Users/vidarbrekke/Dev/CursorApps/TuneTussle` and tracked on GitHub at https://github.com/vidarbrekke/tunetussle.

## Core Principles

* **Commit Often:** Make small, logical commits frequently.
* **Write Clear Messages:** Use Conventional Commit format (see below).
* **Know Your Location:** Always run git commands from the project root (`TuneTussle`) unless targeting a specific subfolder.
* **Track All Project Files:** Ensure all relevant files in `backend/` and `frontend/` are tracked.
* **Exclude Unwanted Files:** Use `.gitignore` to avoid committing build artifacts, logs, and OS/editor files.

## Initial Repository Setup

If you haven't already cloned or initialized the repo:

1. **Clone the GitHub repository:**
   ```bash
   git clone https://github.com/vidarbrekke/tunetussle.git
   cd tunetussle
   ```
   *Or, if starting from scratch:*
   ```bash
   cd /Users/vidarbrekke/Dev/CursorApps/TuneTussle
   git init
   git remote add origin https://github.com/vidarbrekke/tunetussle.git
   ```
2. **Check remote:**
   ```bash
   git remote -v
   ```
3. **Add all files and make the first commit:**
   ```bash
   git add .
   git commit -m "feat: Initial project structure with backend setup"
   git branch -M main
   git push -u origin main
   ```

## Standard Workflow

1. **Check Status:**
   ```bash
   git status
   ```
   This shows modified, staged, and untracked files.

2. **Stage Changes:**
   *Default (Recommended):*
   ```bash
   git add .
   ```
   This will stage all new and modified files in your current directory and subdirectories. **Always review what will be committed using `git status` and `git diff --cached` before proceeding.**

3. **Commit Changes:**
   *Use Conventional Commit Format:* `<type>: <subject>`
   - `<type>`: `feat` (new feature), `fix` (bug fix), `docs` (documentation), `style` (formatting), `refactor`, `test`, `chore` (build process, miscellaneous).
   - `<subject>`: Concise description of the change (e.g., "Add backend server setup").
   ```bash
   git commit -m "feat: Add backend server setup with Express and Socket.IO"
   ```

4. **Push Changes:**
   ```bash
   git push origin main
   ```
   *Or replace `main` with your branch name if working on a feature branch.*

## Important Considerations

* **Project Root:** Your repository is initialized at `/Users/vidarbrekke/Dev/CursorApps/TuneTussle`. Git tracks everything under this path unless excluded by `.gitignore`.
* **Working Directory:** Run `git add .` from the `TuneTussle` directory to ensure only project files are staged.
* **.gitignore:** Make sure your `.gitignore` excludes:
  - `node_modules/`
  - `dist/`, `build/`, `.next/`, etc.
  - `.env`, `.DS_Store`, `.idea/`, `.vscode/`, and other editor/OS files
  - `*.log`

## Example Scenario

Let's say you modified `backend/src/index.ts` and added a new file `backend/src/gameLogic.ts`.

```bash
# Navigate to the TuneTussle project directory
cd /Users/vidarbrekke/Dev/CursorApps/TuneTussle

# Check status (optional)
git status

# Stage all changes (recommended)
git add .

# Commit the changes with a conventional message
git commit -m "feat: Add game logic module and update server entry point"

# Push the commit to the 'main' branch on GitHub
git push origin main
```

## Git Commands for Common Tasks

1. **To verify what files will be included in your commit:**
   ```bash
   git diff --cached --name-only
   ```

2. **To undo staging of a file:**
   ```bash
   git restore --staged <file>
   ```

3. **To ensure your .gitignore changes take effect:**
   ```bash
   git rm -r --cached .
   git add .
   git commit -m "chore: Update .gitignore and reset cache"
   ```

4. **To check for unwanted files that might be staged:**
   ```bash
   # Look for log files
   git diff --cached --name-only | grep -i "\.log$"
   
   # Look for node_modules
   git diff --cached --name-only | grep -i "node_modules"
   ```

## Conclusion

Following this workflow, especially regarding staging all changes with `git add .` and reviewing with `git status`, will help maintain a clean and manageable Git history for your project. Always verify what you're committing using `git status` and `git diff --cached` before finalizing your commits. 