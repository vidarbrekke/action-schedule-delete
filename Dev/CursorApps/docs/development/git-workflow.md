# Git Commit and Push Guide

_Note: This workflow is current as of version 1.1.0._

This guide outlines the recommended workflow for committing and pushing code changes to the GitHub repository, taking into account the specific setup where the repository root is `/Users/vidarbrekke`.

## Core Principles

*   **Commit Often:** Make small, logical commits frequently.
*   **Write Clear Messages:** Use Conventional Commit format (see below).
*   **Stage Intentionally:** Only add the files you intend to commit.
*   **Know Your Location:** Be aware of your current working directory relative to the repository root.
*   **Track Plugin Files:** Ensure all plugin files (including the main PHP file) are properly tracked.
*   **Exclude Unwanted Directories:** Avoid committing backup directories, logs, and other temporary files.
*   **Run Code Quality Checks:** Always run a manual audit and PHPCBF auto-fixes on updated files before committing (see below).

## Standard Workflow

1.  **Check Status:** See what changes Git is aware of. Run this from the **root of your project** (e.g., `Dev/CursorApps/WPChat`).
    ```bash
    git status
    ```
    This shows modified, staged, and untracked files.

2.  **Run Code Quality Checks (Required):**
    Before staging or committing, always:
    * **Manual Audit:** Review your changes for dead code, TODOs, and clarity. You can use grep to help:
      ```bash
      grep -i 'TODO\|FIXME\|UNUSED\|Placeholder\|remove\|deprecated\|obsolete' <your-changed-files>
      ```
    * **PHPCBF Auto-fix:** Run PHPCBF to automatically fix code style issues on all updated PHP files:
      ```bash
      phpcbf --standard=PSR12 <your-changed-files>
      ```
    * **Static Analysis (Optional but recommended):**
      ```bash
      phpcs --standard=PSR12 <your-changed-files>
      ```
    * **Review:** Ensure your code is clean, readable, and follows project standards before proceeding.

3.  **Stage Changes:**
    **Always add all new or updated files before committing to ensure nothing is missed.**
    *   **Default (Recommended):**
        ```bash
        git add .
        ```
        This will stage all new and modified files in your current directory and subdirectories, **excluding any files or directories listed in your `.gitignore`**. 
        
        **Best Practice:** Run `git add .` from the root of your project (e.g., `Dev/CursorApps/WPChat`) to ensure all relevant files are included. As long as your `.gitignore` is correct, this is safe and will not stage unwanted files from outside your project.
        
        **Always review what will be committed using `git status` and `git diff --cached` before proceeding.**
    *   **Specific Files (Optional):** You may still add specific files for very targeted commits, but the default is to use `git add .` from the project root to avoid missing anything.

4.  **Commit Changes:** Save the staged changes to the local repository history with a descriptive message.
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

5.  **Push Changes:** Upload your local commits to the remote repository (GitHub).
    *   Replace `<branch-name>` with the actual name of your branch (e.g., `new`, `main`).
    ```bash
    git push origin <branch-name>
    ```

**Always push after every commit.** This ensures your remote repository stays up to date and reduces the risk of merge conflicts or lost work. Even for small or frequent commits, pushing immediately is best practice.

## Important Considerations for Your Setup

*   **Repository Root:** Your repository is initialized at `/Users/vidarbrekke`. This means Git tracks *everything* under this path unless excluded by `.gitignore`.
*   **Working Directory:**
    - **Recommended:** Always run `git add .` from the root of your project (e.g., `Dev/CursorApps/WPChat`).
    - As long as your `.gitignore` is set up correctly, this will only stage files relevant to your project and will not include unrelated files from your home directory or other locations.
    - **Always review staged files with `git status` and `git diff --cached` before committing.**

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
    - Any directory with "backup" in its name
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

# Run code quality checks (manual audit and PHPCBF)
grep -i 'TODO\|FIXME\|UNUSED\|Placeholder\|remove\|deprecated\|obsolete' wp-customer-ai-chatbot/public/class-wcac-public.php wp-customer-ai-chatbot/includes/helper-functions.php
phpcbf --standard=PSR12 wp-customer-ai-chatbot/public/class-wcac-public.php wp-customer-ai-chatbot/includes/helper-functions.php

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

Following this workflow, especially regarding staging all changes with `git add .`, reviewing with `git status`, and **always pushing after every commit**, will help maintain a clean and manageable Git history for your project. Always verify what you're committing using `git status` and `git diff --cached` before finalizing your commits.

# WordPress Plugin Commit Guide

## Tracking Plugin Files

When committing changes to the repository, ensure that:

1. The main plugin file `wp-customer-ai-chatbot.php` is properly tracked
   - The plugin directory is included in `.gitignore` with `!wp-content/plugins/wp-customer-ai-chatbot/`
   - All plugin files are included with `!wp-content/plugins/wp-customer-ai-chatbot/**`
   - The main plugin file is explicitly included with `!wp-customer-ai-chatbot.php`
   - You should verify the main plugin file is tracked by running: `git ls-files | grep wp-customer-ai-chatbot.php`

2. Backup directories are excluded to prevent cluttering the repository
   - `backup/` (excludes any directory named backup)
   - `**/backup/` (excludes backup directories at any level)
   - `*backup*/` (excludes any directory with "backup" in its name)

3. To verify that all PHP files in the plugin are tracked properly, run:
   ```bash
   git ls-files Dev/CursorApps/WPChat/wp-customer-ai-chatbot | grep -i "\.php$"
   ```

## Commit Best Practices

When making commits:

1. Write clear, descriptive commit messages
   - Begin with a short (50 chars or less) summary
   - Use single-line messages only to ensure compatibility and avoid errors
   - Keep messages concise but informative
   - Follow with a blank line and more detailed explanation if necessary

2. Categorize your commits with prefixes:
   - `feat:` - New feature
   - `fix:` - Bug fix
   - `docs:` - Documentation changes
   - `style:` - Formatting, missing semicolons, etc; no code change
   - `refactor:` - Code refactoring
   - `test:` - Adding tests
   - `chore:` - Maintenance tasks

3. Keep commits focused on a single issue or feature

4. Commit frequently with smaller, logical changes rather than large, sweeping changes

## Release Process

When preparing a release:

1. Update version numbers in:
   - The main plugin file header
   - DOCUMENTATION_INDEX.md
   - Any constant definitions in the code

2. Tag releases using semantic versioning:
   - `MAJOR.MINOR.PATCH`
   - Increment MAJOR for incompatible API changes
   - Increment MINOR for added functionality (backwards compatible)
   - Increment PATCH for backwards compatible bug fixes

3. Update the changelog with all notable changes since the last release

4. Test thoroughly before pushing a tag that represents a release

## Deployment Checklist

Before deploying to production:

1. Run all tests
2. Check for any debug code or comments that should be removed
3. Ensure all docblocks and inline documentation are up to date
4. Verify compatibility with the minimum supported WordPress version
5. Confirm proper handling of plugin activation/deactivation
6. Test uninstallation process 