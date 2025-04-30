# Git Commit and Push Guide

This guide outlines the recommended workflow for committing and pushing code changes to the GitHub repository, taking into account the specific setup where the repository root is `/Users/vidarbrekke`.

## Core Principles

*   **Commit Often:** Make small, logical commits frequently.
*   **Write Clear Messages:** Use Conventional Commit format (see below).
*   **Stage Intentionally:** Only add the files you intend to commit.
*   **Know Your Location:** Be aware of your current working directory relative to the repository root.

## Standard Workflow

1.  **Check Status:** See what changes Git is aware of. Run this from within the `Dev/CursorApps/WPChat` directory or one of its subdirectories.
    ```bash
    git status
    ```
    This shows modified, staged, and untracked files.

2.  **Stage Changes:** Add the specific files you want to include in the next commit to the staging area.
    *   **Specific Files (Recommended):** Add only the files you've intentionally changed for this logical unit of work. You can list multiple files. Run this from the repository root (`/Users/vidarbrekke`) or use paths relative to the root.
        ```bash
        # Example from the repo root
        git add Dev/CursorApps/WPChat/wp-customer-ai-chatbot/public/class-wcac-public.php Dev/CursorApps/WPChat/wp-customer-ai-chatbot/admin/class-wcac-admin-settings.php

        # Example from within WPChat (less common with root at ~)
        # cd Dev/CursorApps/WPChat
        # git add wp-customer-ai-chatbot/public/class-wcac-public.php wp-customer-ai-chatbot/admin/class-wcac-admin-settings.php
        ```
    *   **All Changes in a Directory (Use with Caution):** Add all modified/new files within a specific directory *relative to your current location*. **Be very careful with `git add .` when your repository root is your home directory (`/Users/vidarbrekke`), as it will try to add *everything* in that directory.** It's generally safer to add specific files or specific subdirectories relative to the root.
        ```bash
        # From the repo root (/Users/vidarbrekke) - Adds ONLY the WPChat project
        git add Dev/CursorApps/WPChat/

        # If you are inside Dev/CursorApps/WPChat, this adds everything inside WPChat
        # git add .
        ```

3.  **Commit Changes:** Save the staged changes to the local repository history with a descriptive message.
    *   **Use Conventional Commit Format:** `<type>: <subject>`
        *   `<type>`: `feat` (new feature), `fix` (bug fix), `docs` (documentation), `style` (formatting), `refactor`, `test`, `chore` (build process, miscellaneous).
        *   `<subject>`: Concise description of the change (e.g., "Add restore default button for system prompt").
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

*   **Repository Root:** Your repository is initialized at `/Users/vidarbrekke`. This means Git tracks *everything* under this path unless excluded by `.gitignore`.
*   **Working Directory:** Commands like `git add .` operate relative to your *current* directory. Running `git add .` inside `Dev/CursorApps/WPChat` when the repo root is `/Users/vidarbrekke` is **dangerous** and will stage unrelated files from your home directory.
*   **Adding Files:** It's safest to run `git add` commands from the repository root (`/Users/vidarbrekke`) and specify the full path relative to the root (e.g., `git add Dev/CursorApps/WPChat/path/to/file.php`).
*   **Submodules (Former Issue):** The `wp-customer-ai-chatbot` directory *used* to be treated as a submodule (a nested repository). We have converted it to be a regular directory tracked by the main repository. You should not need to perform separate commits inside that directory anymore; treat it as part of the main `WPChat` project.

## Example Scenario

Let's say you modified `wp-customer-ai-chatbot/public/class-wcac-public.php` and added a new file `wp-customer-ai-chatbot/includes/helper-functions.php`.

```bash
# Navigate to the repository root (optional but often clearer)
cd /Users/vidarbrekke

# Check status (optional)
git status

# Stage the specific files
git add Dev/CursorApps/WPChat/wp-customer-ai-chatbot/public/class-wcac-public.php Dev/CursorApps/WPChat/wp-customer-ai-chatbot/includes/helper-functions.php

# Commit the changes with a conventional message
git commit -m "feat: Add helper functions and update public class"

# Push the commit to the 'new' branch on GitHub
git push origin new
```

## Conclusion

Following this workflow, especially regarding staging specific files relative to the root directory, will help maintain a clean and manageable Git history for your project. 