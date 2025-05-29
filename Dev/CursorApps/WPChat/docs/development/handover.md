# WP Customer AI Chatbot - Developer Handover

## Introduction

An AI-powered chatbot for WordPress and WooCommerce sites, leveraging OpenRouter and a custom Retrieval-Augmented Generation (RAG) pipeline.

This plugin provides a customer-facing chatbot that can answer questions based on site content (products, pages, posts) and general knowledge, utilizing its indexed data and advanced context clustering.

For a feature list and user documentation, refer to `wp-customer-ai-chatbot/readme.txt`.

## Getting Started

1.  **Environment Setup:** All details for local and server environment setup, credentials, deployment workflow, and troubleshooting can be found in [`docs/development/environment-setup.md`](docs/development/environment-setup.md). This is the primary reference for server access, database info, log locations, and deployment.
2.  **Development Tools:** Familiarize yourself with the available scripts and guides in [`dev-tools/DEVELOPMENT_TOOLS.md`](../dev-tools/DEVELOPMENT_TOOLS.md). This includes deployment scripts, testing utilities, and links to other essential guides.
3.  **Commit Practices:** Follow the commit conventions outlined in [`utilities/commit-guide.md`](../../utilities/commit-guide.md).

## Core Plugin Architecture

The plugin follows standard WordPress plugin structure.

### Key Directories

-   **`wp-customer-ai-chatbot/`**: The main plugin directory.
    -   **`admin/`**: Handles WordPress admin area functionality, settings pages, AJAX handlers, and test/log UIs.
        -   `class-wcac-admin-settings.php`: Orchestrates the main settings page, delegating UI rendering, settings registration, and data sanitization to helper classes. It defines the settings tabs and basic page structure.
        -   `class-wcac-admin-settings-renderer.php`: Responsible for rendering all HTML fields for the plugin settings pages.
        -   `class-wcac-admin-settings-sanitizer.php`: Handles the sanitization of all settings data before it's saved to the database.
        -   `class-wcac-admin-settings-registrar.php`: Manages the registration of all settings sections and fields with WordPress, including defining default values and structures for scoring parameters.
        -   `class-wcac-admin-test-results.php`: Test Results page UI and optimizer logic.
        -   `class-wcac-admin-debug-logs.php`: Debug Logs page UI.
        -   `class-wcac-admin-ajax.php`: Handles admin-initiated AJAX requests *other than* settings save (e.g., testing, logging). The main settings save AJAX handler (`wcac_save_tab_settings`) is located within `class-wcac-admin-settings.php`. The indexing AJAX handler (`wcac_build_index`) is in the main plugin file (`wp-customer-ai-chatbot.php`).
    -   **`includes/`**: Core logic, including RAG pipeline, indexing, and common utilities.
        -   **`common/`**: Utility classes and functions used across the plugin (e.g., logging, defaults).
        -   **`indexing/`**: Content indexing logic.
            -   `class-wcac-indexer.php`: Responsible for fetching, formatting, and saving content to the index table (`wp_wcac_index`). Triggered manually or via `save_post` hook.
        -   **`retrieval/`**: RAG pipeline components.
            -   `class-wcac-content-retriever.php`: Fetches candidate content from the index based on keywords.
            -   `class-wcac-chatbot-rules.php`: Central class for scoring candidates based on various parameters, handling fuzzy matching, boosts, penalties, and parent/variation logic. Contains default scoring parameters.
            -   `class-wcac-query-analyzer.php`: Analyzes user queries for intent and keyword extraction.
        -   **`optimizer/`**: Settings optimization logic.
            -   `class-wcac-settings-optimizer.php`: Manages reading/writing scoring parameters and running optimization batches based on test results.
        -   **`testing/`**: Classes related to running test cases against the scoring engine.
            -   `class-wcac-test-runner.php`: Executes test cases.
            -   `class-wcac-test-case.php`: Represents a single test case (query + expected results).
    -   **`public/`**: Handles frontend functionality, including the chatbot shortcode and widget rendering.
        -   `class-wcac-public.php`: Enqueues scripts/styles, defines the shortcode.
    -   **`languages/`**: Translation files.
    -   **`vendor/`**: Composer dependencies (currently none).
-   **`docs/`**: Project documentation.
-   **`dev-tools/`**: Development and deployment scripts/utilities.

### Primary Files

-   `wp-customer-ai-chatbot.php`: Main plugin file (bootstrap).

## Core Processes

### 1. Content Indexing

-   **Purpose:** To prepare site content (products, variations, pages, posts) for efficient retrieval by the chatbot.
-   **Process:**
    -   Triggered manually via the "Re-index Content" button (Admin > AI Chatbot > Settings) or automatically when relevant content is saved/updated.
    -   `Wcac_Indexer::build_index()` (for manual full re-index) or `Wcac_Indexer::update_single_content()` (for `save_post`) is called.
    -   The indexer fetches content based on selected types in settings.
    -   It formats the content (`Wcac_Indexer::format_content_for_llm`), extracting relevant fields (title, content, categories, tags, prices, attributes, etc.).
    -   It generates a `search_blob` containing weighted text from various fields.
    -   The formatted data is saved/updated in the custom database table: `{$wpdb->prefix}wcac_index`.
    -   Metadata (counts, timestamp) is stored in the `wcac_index_meta` WP option.
-   **Key Class:** `includes/indexing/class-wcac-indexer.php`

### 2. Retrieval-Augmented Generation (RAG) Pipeline

-   **Purpose:** To answer user queries by retrieving relevant indexed content and feeding it to the LLM.
-   **Process:**
    -   User query received via frontend chat widget (`Wcac_Public`).
    -   AJAX request handled by `Wcac_Public::handle_send_message_ajax`.
    -   `Wcac_Query_Analyzer::extract_keywords()` extracts keywords from the query.
    -   `Wcac_Content_Retriever::retrieve_candidates()` fetches potential matching items from the `wp_wcac_index` table based on keywords.
    -   `Wcac_ChatbotRules::score_item()` scores each candidate based on numerous factors:
        - Core weights (title, content, category matches)
        - Boost signals (rating, recency, menu presence)
        - Penalty factors (out-of-stock, negative keywords)
        - Product type relationships
        - Fuzzy matching and compound names
    -   `Wcac_ChatbotRules::process_search_results()` filters low-scoring results, applies parent/variation preference logic, and limits the final set.
    -   The top-scoring, processed results are formatted as context.
    -   The context is optionally compressed using the selected algorithm (none/title/category/fuzzy).
    -   The context, conversation history, and system prompts are sent to the LLM via the OpenRouter API client.
    -   The LLM generates a response based on the provided information.
    -   The response is streamed back to the user.
-   **Key Classes:** `includes/retrieval/*`, `includes/api/*`
-   **Parameters:** See [`dev-tools/optimizer_parameters.md`](../dev-tools/optimizer_parameters.md) for a detailed list of scoring parameters. These are defined for UI registration and default values in `Wcac_Admin_Settings_Registrar` and their active values are stored in the `wcac_settings` WordPress option.

### 3. Settings Optimization

-   **Purpose:** To automatically tune the RAG scoring parameters based on test case performance.
-   **Process:**
    -   User uploads a CSV of test cases (Query, Expected Product ID/Title) on the "Test Results" admin page.
    -   `Wcac_Admin_Test_Results` handles the upload and stores tests in `{$wpdb->prefix}wcac_test_results`.
    -   User clicks "Run Optimizer".
    -   `Wcac_Admin_Ajax::run_optimizer_batch()` is called repeatedly.
    -   `Wcac_Settings_Optimizer::run_batch()`:
        -   Uses the initial session settings as a baseline for the entire optimization run
        -   Generates slightly randomized versions of the parameters
        -   For each parameter set, runs all test cases using `Wcac_Test_Runner`
        -   Compares actual results against expected results
        -   Tracks the best-performing parameter set
        -   After each batch, suggests the best set found
    -   User can apply the suggested settings via the UI, which calls `Wcac_Admin_Ajax::apply_suggested_settings()` triggering `Wcac_Settings_Optimizer::apply_settings()`.
-   **Key Classes:** `includes/optimizer/*`, `includes/testing/*`, `admin/class-wcac-admin-test-results.php`
-   **AJAX Handlers:** Settings save in `class-wcac-admin-settings.php`, Optimizer run/apply in `class-wcac-admin-ajax.php`.

## Recent Changes

The admin settings architecture has been significantly refactored for better organization and maintainability:

1. **Settings Management Refactor:**
   - The main settings class `Wcac_Admin_Settings` now acts as an orchestrator
   - Responsibilities are delegated to specialized helper classes:
     - `Wcac_Admin_Settings_Renderer`: HTML field rendering
     - `Wcac_Admin_Settings_Sanitizer`: Data sanitization
     - `Wcac_Admin_Settings_Registrar`: WordPress settings registration

2. **Scoring Parameters:**
   - All scoring parameters are now defined in `Wcac_Admin_Settings_Registrar`
   - Parameters are grouped into "Optimizable" and "Static" sections
   - The dedicated `class-wcac-scoring-settings.php` has been removed
   - New parameters added:
     - Rating Weight (product rating boost)
     - Recency Boost (recent content priority, with fixed 30-day window)
     - Out-of-Stock Penalty
     - Menu Presence Weight
     - Context Compression Algorithm selection

3. **Optimizer Improvements:**
   - Now maintains consistent baseline settings throughout optimization sessions
   - Better parameter randomization logic with bounds checking
   - Improved scoring metrics for test results
   - New parameters included in optimization

4. **Settings Save & Security Enhancements:**
   - Implemented **tab-specific nonce verification** for each settings form to enhance security.
   - Refactored the settings save AJAX handler (`wcac_save_tab_settings` within `Wcac_Admin_Settings`) to **only update settings for the submitted tab**, preserving values (including checkbox states) from other tabs.
   - Improved sanitization for all parameter types within `Wcac_Admin_Settings_Sanitizer`.
   - Added more detailed debug logging during settings save.

## Further Documentation

-   **Environment & Credentials:** [`docs/development/environment-setup.md`](docs/development/environment-setup.md)
-   **Development Tools & Scripts:** [`dev-tools/DEVELOPMENT_TOOLS.md`](../dev-tools/DEVELOPMENT_TOOLS.md)
-   **Scoring Parameters:** [`dev-tools/optimizer_parameters.md`](../dev-tools/optimizer_parameters.md)
-   **Commit Guide:** [`utilities/commit-guide.md`](../../utilities/commit-guide.md)
-   **For full release and packaging steps, see:** [`deployment/release-process.md`](../../deployment/release-process.md)