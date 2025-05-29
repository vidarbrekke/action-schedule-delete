# WP Customer AI Chatbot — Unit Testing Guide

## Overview
This directory contains all PHPUnit tests for the plugin. All tests must be run on a server with a full WordPress installation and the plugin's dependencies. Do not attempt to run these tests locally in an IDE without WordPress and the plugin codebase.

## Test Infrastructure Overview

The WP Customer AI Chatbot plugin employs two complementary testing systems:

1. **PHPUnit Tests (This Directory)**
   - Developer-focused unit tests for code correctness
   - Validates individual components in isolation
   - Runs outside of WordPress (with stubs in bootstrap.php)
   - Should NOT be included in distribution packages

2. **Custom Test Runner (`/includes/testing/`)**
   - Production-focused retrieval quality tests
   - Runs against real indexed content in WordPress
   - Accessible through admin UI
   - Required for ongoing quality monitoring
   - Part of the plugin's core functionality

Both systems serve important but different purposes:
- PHPUnit tests verify that code logic works correctly
- Custom tests verify that retrieval quality meets expectations

## Test Files
- **SimpleTest.php**: Minimal sanity check to verify PHPUnit runs.
- **RequireTest.php**: Verifies plugin files can be required without fatal errors.
- **TestWcacScoringParameters.php**: Data-driven test verifying that all tunable scoring parameters affect result scoring as expected.
- **TestWcacSettingsApplication.php**: Verifies that admin settings are correctly applied to the plugin's rules and affect scoring logic.
- **TestWcacQueryAnalyzer.php**: Tests keyword and intent extraction logic.
- **TestWcacKeywordSearch.php**: Tests the keyword search logic and result structure.
- **TestWcacChatbotRules.php**: Tests keyword extraction and stopword filtering in the main rules class.
- **bootstrap.php**: Bootstraps the test environment, stubbing required WordPress functions and globals.

## Running PHPUnit Tests (Server Only)

### Prerequisites
- PHP 8.2+
- Composer (for dependencies)
- PHPUnit (installed via Composer as a dev dependency)
- All test files present in `tests/` directory
- WordPress and the plugin installed on the server

### 1. Install/Update Dependencies
```sh
cd /home/staging/public_html/wp-content/plugins/wp-customer-ai-chatbot
composer install
```
If PHPUnit is missing:
```sh
composer require --dev phpunit/phpunit
```

### 2. Run All Tests
```sh
./vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/
```

### 3. Run a Specific Test File
```sh
./vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/TestWcacSettingsApplication.php
```
Or for a minimal test:
```sh
./vendor/bin/phpunit tests/SimpleTest.php
```

### 4. Troubleshooting
- If you see `No tests executed!`, ensure test classes extend `PHPUnit\Framework\TestCase` and methods start with `test`.
- If you see `Cannot open bootstrap script`, check the path to `bootstrap.php`.
- If you see missing file errors, ensure all plugin source files and the `tests/` directory are present on the server.
- If you see no output at all, try running `SimpleTest.php`.
- Check `/home/staging/public_html/wp-content/debug.log` for PHP errors.
- If you see WordPress function errors, ensure `bootstrap.php` stubs or loads all required functions.

### 5. Extending the Test Suite
- Add new test files to `tests/`.
- Require plugin files as needed at the top of each test.
- Use the existing stubs in `bootstrap.php` for isolated logic tests.
- For integration or end-to-end tests, use shell scripts or WP-CLI commands as described in the main plugin documentation.

## Deployment Considerations
These unit tests should NOT be included in the final plugin distribution zip. Before packaging the plugin for distribution, you should:

1. Move this tests directory outside the main plugin structure
2. Create a build script that excludes test files from the distribution package
3. Consider using a `.distignore` file if using wp-cli to build the package

The Custom Test Runner (`/includes/testing/`), on the other hand, is part of the plugin's functionality and should be included in the distribution.

---
_Last updated: 2024-06-20_ 