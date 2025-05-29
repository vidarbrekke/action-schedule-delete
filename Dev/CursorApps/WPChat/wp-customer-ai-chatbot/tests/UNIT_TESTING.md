# PHPUnit Tests for WP Customer AI Chatbot

## Purpose
This directory contains PHPUnit-based unit tests for the plugin's core PHP classes and functionality. These tests verify that individual components work correctly in isolation and serve as regression tests during development.

## What These Tests Do
- Verify core plugin logic and algorithms work as expected
- Test individual classes and methods in isolation
- Validate that settings are correctly applied
- Ensure consistent behavior of the retrieval pipeline
- Catch regressions during refactoring or feature additions

## What These Tests Don't Do
- Test integration with WordPress or WooCommerce
- Verify retrieval quality against real data
- Evaluate LLM responses
- Test frontend functionality or AJAX endpoints

## Relationship to Other Test Systems
- **Unit Tests (This Directory)**: Developer-focused code correctness tests
- **Custom Test Runner (`/includes/testing/`)**: Real-world retrieval quality tests in production
- **Admin Testing UI**: Frontend for custom test runner to assess retrieval performance

## Deployment Considerations
These tests are development tools and should not be included in the final plugin distribution zip. Consider moving this directory outside the main plugin directory structure before packaging for distribution. 