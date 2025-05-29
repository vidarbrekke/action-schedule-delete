# Git Commit and Push Guide

This guide outlines the recommended workflow for committing and pushing code changes to the GitHub repository, taking into account the specific setup where the repository root is `/Users/vidarbrekke`.

## Core Principles

*   **Commit Often:** Make small, logical commits frequently.
*   **Write Clear Messages:** Use Conventional Commit format (see below).
*   **Know Your Location:** Be aware of your current working directory relative to the repository root.
*   **Track Plugin Files:** Ensure all plugin files (including the main PHP file) are properly tracked.
*   **Exclude Unwanted Directories:** Avoid committing backup directories, logs, and other temporary files.

## Standard Workflow

1.  **Check Status:** See what changes Git is aware of. Run this from within the `Dev/CursorApps/WPChat` directory or one of its subdirectories.
    ```bash
    git status
    ```
    This shows modified, staged, and untracked files.

2.  **Stage Changes:**
    **Always add all new or updated files before committing to ensure nothing is missed.**
    *   **Default (Recommended):**
        ```bash
        git add .
        ```
        This will stage all new and modified files in your current directory and subdirectories. **Always review what will be committed using `git status` and `git diff --cached` before proceeding.**
    *   **Specific Files (Optional):** You may still add specific files for very targeted commits, but the default is to use `git add .` to avoid missing anything.

3.  **Commit Changes:** Save the staged changes to the local repository history with a descriptive message.
    *   **Use Conventional Commit Format:** `<type>: <subject>`
        *   `<type>`: `feat` (new feature), `fix` (bug fix), `docs` (documentation), `style` (formatting), `refactor`, `test`, `chore` (build process, miscellaneous).
        *   `<subject>`: Concise description of the change (e.g., "Add restore default button for system prompt").
    *   **Important:** Always use a single-line message format to ensure compatibility with all Git tools and avoid potential errors. Multi-line commit messages can cause issues with certain Git operations.
    ```bash
    git commit -m "feat: Add restore default prompt button"
    ```
    Or for a `fix`:
    ```bash
    git commit -m "fix: Correct calculation in scoring logic"
    ```

4.  **Push Changes:** Upload your local commits to the remote repository (GitHub).
    *   Replace `<branch-name>` with the actual name of your branch (e.g., `new`, `main`).
    ```bash
    git push origin <branch-name>
    ```

## Important Considerations for Your Setup

*   **Repository Root:** Your repository is initialized at `/Users/vidarbrekke/Dev/CursorApps/WPChat`. This means Git tracks *everything* under this path unless excluded by `.gitignore`.
*   **Working Directory:** Commands like `git add .` operate relative to your *current* directory. Running `git add .` inside `Dev/CursorApps/WPChat` when the repo root is `/Users/vidarbrekke` is **dangerous** and will stage unrelated files from your home directory. **Always run `git add .` from the project directory you want to commit.**
*   **Adding Files:** It's safest to run `git add .` from the `Dev/CursorApps/WPChat` directory to ensure only project files are staged.

### Tracking Plugin Files

*   **Main Plugin File:** The `.gitignore` file has been updated to explicitly include `wp-customer-ai-chatbot.php` to ensure it's tracked properly with the pattern `!wp-customer-ai-chatbot.php`.
*   **Plugin Directory:** All plugin files within the `wp-customer-ai-chatbot` directory are included with the pattern `!wp-content/plugins/wp-customer-ai-chatbot/**` and with `!wp-customer-ai-chatbot/wp-customer-ai-chatbot.php` for the main plugin file within the directory structure.
*   **Backup Exclusions:** The `.gitignore` now uses more comprehensive patterns to exclude backup directories:
    - `backup/` - Excludes any directory named backup
    - `**/backup/` - Excludes backup directories at any level
    - `*backup*/` - Excludes any directory with "backup" in its name
*   **Verification:** To verify all PHP files in your plugin are being tracked properly, run:
    ```bash
    git ls-files Dev/CursorApps/WPChat/wp-customer-ai-chatbot | grep -i "\.php$"
    ```
    This should list all PHP files in your plugin directory that Git is tracking.

### Excluded Directories and Files

The following should not be committed:
*   **Backup Directories:**
    - Any directory named `backup`
    - Any directory path containing `/backup/`
*   **Log Files:** 
    - All files with `.log` extension (includes `debug.log`)
    - Use `grep -v` to verify no log files are being tracked
*   **WordPress Core:**
    - WordPress core files and standard WordPress directories
*   **IDE/Editor Files:**
    - `.idea/`, `.vscode/` directories
    - `.DS_Store` and other OS-generated files

*   **Submodules (Former Issue):** The `wp-customer-ai-chatbot` directory *used* to be treated as a submodule (a nested repository). We have converted it to be a regular directory tracked by the main repository. You should not need to perform separate commits inside that directory anymore; treat it as part of the main `WPChat` project.

## Example Scenario

Let's say you modified `wp-customer-ai-chatbot/public/class-wcac-public.php` and added a new file `wp-customer-ai-chatbot/includes/helper-functions.php`.

```bash
# Navigate to the WPChat project directory
cd /Users/vidarbrekke/Dev/CursorApps/WPChat

# Check status (optional)
git status

# Stage all changes (recommended)
git add .

# Commit the changes with a conventional message
git commit -m "feat: Add helper functions and update public class"

# Push the commit to the 'new' branch on GitHub
git push origin new
```

## Git Commands for Common Tasks

Here are some helpful Git commands for specific situations:

1. **To verify what files will be included in your commit:**
   ```bash
   git diff --cached --name-only
   ```

2. **To undo staging of a file:**
   ```bash
   git restore --staged <file>
   ```

3. **To check if the plugin files are being properly tracked:**
   ```bash
   git ls-files Dev/CursorApps/WPChat/wp-customer-ai-chatbot | grep -i "\.php$"
   ```

4. **To ensure your .gitignore changes take effect:**
   ```bash
   git rm -r --cached .
   git add .
   git commit -m "chore: Update .gitignore and reset cache"
   ```

5. **To check for unwanted files that might be staged:**
   ```bash
   # Look for log files
   git diff --cached --name-only | grep -i "\.log$"
   
   # Look for backup directories
   git diff --cached --name-only | grep -i "backup"
   
   # Check for WordPress core files
   git diff --cached --name-only | grep -i "wp-includes\|wp-admin"
   ```

## Conclusion

Following this workflow, especially regarding staging all changes with `git add .` and reviewing with `git status`, will help maintain a clean and manageable Git history for your project. Always verify what you're committing using `git status` and `git diff --cached` before finalizing your commits. 