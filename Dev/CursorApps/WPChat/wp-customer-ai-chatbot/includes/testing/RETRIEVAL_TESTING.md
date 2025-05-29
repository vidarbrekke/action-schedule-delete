# Custom Test Runner for WP Customer AI Chatbot

## Purpose
This directory contains the custom test runner system used to evaluate and tune the plugin's retrieval and RAG (Retrieval-Augmented Generation) performance against real data. These tests run within the WordPress environment and are accessible through the admin UI.

## What This Test System Does
- Runs retrieval tests against your actual indexed content and products
- Evaluates RAG pipeline accuracy and relevance for real-world queries
- Logs test results in the database for analysis and tuning
- Allows comparison of changes to retrieval algorithms and scoring parameters
- Helps identify and fix issues in content retrieval

## Key Components
- **`class-wcac-test-runner.php`**: Core test execution engine that processes test cases
- **`class-wcac-test-case.php`**: Defines test cases (queries, expected results)
- **`class-wcac-test-logger.php`**: Stores test results in the WordPress database
- **`class-wcac-test-result-formatter.php`**: Formats test results for admin UI display
- **`class-wcac-csv-parser.php`**: Imports test cases from CSV files
- **`class-wcac-test-results-exporter.php`**: Exports test results for external analysis

## Relationship to Other Test Systems
- **Custom Test Runner (This Directory)**: Real-world retrieval quality testing
- **Unit Tests (`/tests/`)**: Developer-focused code correctness tests
- **Admin Testing UI**: Frontend for this custom test runner

## Deployment Considerations
Unlike unit tests, these files are required for the plugin's admin testing functionality and should be included in the distribution zip. However, consider moving the entire testing infrastructure to a separate plugin that can be activated only in development/staging environments. 