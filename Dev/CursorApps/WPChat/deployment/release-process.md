# Release Checklist

_Note: This checklist is current as of version 1.1.0._

This checklist is intended to be updated over time. Use it to ensure every release is consistent, high quality, and production-ready.

---

## 1. Code Quality & Cleanup
- [ ] Run manual audit for dead code, TODOs, and deprecated logic
- [ ] Run PHPCBF auto-fix on all updated PHP files
- [ ] Run PHPCS static analysis (optional but recommended)
- [ ] Remove or comment out debug code and logs
- [ ] Ensure all code follows project and WordPress coding standards

## 2. Documentation
- [ ] Update README with new features, changes, or instructions
- [ ] Update CHANGELOG with all notable changes since last release
- [ ] Update inline docblocks and comments as needed
- [ ] Ensure all public APIs and hooks are documented

## 3. Versioning
- [ ] Bump version number in main plugin file header
- [ ] Update version in README and any constants in code
- [ ] Use semantic versioning (MAJOR.MINOR.PATCH)

## 4. Testing
- [ ] Run all automated and manual tests
- [ ] Test plugin activation, deactivation, and uninstall
- [ ] Test upgrade path from previous version
- [ ] Verify compatibility with minimum supported WordPress and PHP versions

## 5. Dependencies
- [ ] Ensure all required dependencies are listed in composer.json
- [ ] Run `composer install --no-dev --optimize-autoloader` to verify production dependencies
- [ ] Verify that vendor directory is properly included in the distribution package
- [ ] Test plugin installation on a server without Composer to ensure all dependencies are included

## 6. Final Review
- [ ] Check for unwanted files (logs, backups, temp files) in the commit
- [ ] Ensure .gitignore is up to date
- [ ] Review all staged changes with `git status` and `git diff --cached`
- [ ] Verify dev-tools directory is properly excluded from distribution

## 7. Tagging & Deployment
- [ ] Tag the release in Git with the new version number
- [ ] Push all commits and tags to the remote repository
- [ ] Run the packaging script from the plugin root directory:
  - `./deployment/package-plugin.sh`
- [ ] Verify the ZIP file in the `dist/` directory
- [ ] Upload the ZIP file to WordPress.org or your server/distribution channel as needed

---

## Notes / To-Do for Future Releases
- [ ] (Add any project-specific or evolving requirements here) 