# WCAC Chatbot: Retrieval-Augmented Generation (RAG) Process Guide

This document is the authoritative reference for the Retrieval-Augmented Generation (RAG) process implemented in the WordPress Customer AI Chatbot plugin. It describes the end-to-end flow, key components, and best practices for maintaining, tuning, and extending the system.

## Core Purpose

The RAG process grounds AI chatbot responses in real, indexed website content (products, pages, posts, FAQs), minimizing hallucination and maximizing relevance. The system retrieves, ranks, and presents contextually appropriate content to the LLM, which then generates a user-facing response. All retrieval, ranking, and business logic is handled server-side in PHP for transparency and control.

## RAG Flow: End-to-End Process (Current Implementation)

1. **User Query Submission**
   - User enters a message in the chat widget and submits it.
   - AJAX request is sent to the server (`wcac_send_message` action).

2. **Keyword Extraction & Intent Detection**
   - The plugin extracts keywords from the query, preserving compound product names and removing stopwords (stopwords are removed at index time for efficiency).
   - Intent is detected (product, category, FAQ, etc.) using both data-driven logic and a minimal phrase dictionary.
   - **Category intent** is only flagged for very short queries (≤3 words) that exactly match a category name, or for longer queries with ≥95% similarity.

3. **Content Retrieval**
   - The system queries the custom index (`wp_wcac_index`) for candidates matching the keywords in the denormalized `search_blob` and other fields.
   - Robust category and tag matching is performed in PHP for accuracy, including parent categories and fuzzy/normalized matching.
   - **Index now includes:**
     - Full content (not just snippet)
     - Parent categories (as array)
     - Stock status, attributes, price, recency, and popularity
     - Negative keyword filtering (content with negative keywords is excluded at index time)

4. **Scoring & Ranking**
   - Each candidate is scored using configurable weights, boosts, and penalties:
     - Title, content, category, tag, product type, sale status, compound name, attribute matches, parent product preference, recency, stock status, and popularity.
   - **Boosts:**
     - In-stock products, recent content, parent products, attribute matches, and exact/compound name matches.
   - **Penalties:**
     - Out-of-stock, old, or duplicate results.
   - **Deduplication:**
     - Duplicate entries in actual results are removed to avoid wasting LLM tokens.
   - **Low-confidence fallback:**
     - If no result meets a relative_score_threshold, a canned response is returned.
   - **Relative score filtering:**
     - Only the most relevant results are returned, with parent product preference and score-based filtering.

5. **Context Formatting**
   - The top N results are formatted as context strings for the LLM prompt.

6. **LLM Prompt Construction & API Call**
   - The system prompt is assembled (base instructions, site-specific instructions, context).
   - The prompt, conversation history, and user message are sent to the LLM API.
   - The LLM generates an introductory sentence or answer.

7. **Response Assembly**
   - The final response combines the LLM intro with a PHP-generated product/content list.
   - The response is returned to the frontend and displayed to the user.

8. **Logging & Test Automation (Optional)**
   - If enabled, all queries and results are logged for debugging and relevance tuning.
   - Admins can upload a CSV of test queries, run automated tests, and review results in the Test Results admin page.
   - **Test runner deduplicates actual results and logs per-test-case breakdowns.**

## Core Goal

To answer user queries accurately based on indexed website content (products, pages, posts), minimizing hallucination by grounding Large Language Model (LLM) responses in retrieved context.

## Key Components (Current State)

### 1. Indexer (`Wcac_Indexer`)
- **Purpose:** Periodically scans and indexes selected post types (products, pages, posts, FAQs) into a custom database table (`wp_wcac_index`).
- **Data Extraction:**
  - Extracts and normalizes all relevant fields: title, full content, URL, type, categories, parent categories, parent_category_names, tags, price, sale price, stock status, attributes, parent ID, recency, popularity, and taxonomies.
  - Builds a denormalized, lowercased `search_blob` field containing all non-stopword text for robust keyword matching.
  - Removes stopwords at index time (not at query time) for more accurate search.
  - **Negative keyword filtering:** Content containing negative keywords (from admin settings) is excluded at index time.
- **Batch Indexing:**
  - Uses AJAX-driven batching to handle large content sets without timeouts.
  - Progress and status are visible in the admin UI, with robust handling of partial/final batches.
- **Admin Controls:**
  - Manual re-index button, content type selection, and negative keyword filtering are available in plugin settings.
  - Indexing is required after changing content types, stopword/negative keyword lists, or scoring parameters.
- **Schema:**
  - Table includes: `post_id`, `post_type`, `post_modified`, `title`, `content_snippet` (full text), `url`, `categories` (JSON), `parent_categories` (JSON), `parent_category_names` (JSON), `tags` (JSON), `regular_price`, `sale_price`, `on_sale`, `parent_id`, `stock_status`, `search_blob`, `attributes_text`, `menu_titles`, `taxonomies`.
  - **Note:** The plugin activator ensures all required columns are present on fresh install. For existing installs, a database migration (ALTER TABLE) may be required to add new columns. See troubleshooting below.

### 2. Retrieval & Ranking (`Wcac_ChatbotRules`)
- **Purpose:** Extracts keywords, detects intent, retrieves and scores relevant content from the index, and applies configurable ranking rules.
- **Keyword Extraction:**
  - Processes user queries to extract keywords, removing punctuation and stopwords.
  - Detects and preserves compound product names (from admin settings) as single search units.
- **Intent Detection:**
  - Determines if the query is seeking a specific product, a category, or general store info/FAQ.
  - Uses both data-driven logic and a minimal phrase dictionary for store info/FAQ detection.
  - **Category intent** is only flagged for very short queries (≤3 words) that exactly match a category name, or for longer queries with ≥95% similarity.
- **Content Retrieval:**
  - Builds SQL queries to find candidates by matching keywords in the denormalized `search_blob` and other fields.
  - Robustly matches categories and tags, including fuzzy and normalized matching, and parent categories.
- **Scoring:**
  - Calculates a relevance score for each candidate using weighted factors: title, content, category, tag, product type, sale status, exact/compound name matches, attribute matches, parent product preference, recency, stock status, and popularity.
  - Applies admin-configurable weights, boosts, and penalties (all loaded from settings at runtime).
  - Applies parent product preference and relative score filtering to ensure only the most relevant results are returned.
  - **Deduplication:** Duplicate results are removed before sending to the LLM.
  - **Low-confidence fallback:** If no result meets a relative_score_threshold, a canned response is returned.
- **Extensibility:**
  - All scoring logic is modular and can be tuned via the admin UI.

### 3. LLM API Handler (`WCAC_API_Handler`)
- **Purpose:** Handles all communication with the LLM API (e.g., OpenRouter), including prompt construction, context formatting, and response parsing.
- **Prompt Construction:**
  - Assembles the system prompt using base instructions, site-specific instructions (from admin settings), and formatted context strings for the top N retrieved items.
  - Context is generated by `Wcac_Public::format_context_item` for each result.
- **API Call:**
  - Sends the prompt, conversation history, and user message to the LLM API using `wp_remote_post`.
  - Uses model and parameters from admin settings.
- **Error Handling:**
  - Handles API/network errors, HTTP status codes, and JSON decoding issues.
  - Extracts the LLM's content from the response, or returns a fallback message on error.
- **Response Strategy:**
  - The LLM is currently used to generate only an introductory sentence or answer, while the product/content list is generated in PHP for reliability and transparency.
  - Future versions may allow the LLM to generate the full response, with careful prompt engineering and post-processing.

### 4. Test Logging & Automation (`Wcac_Test_Logger`, `Wcac_Test_Runner`, `Wcac_Csv_Parser`)
- **Purpose:** Enables automated testing of the RAG process and records test results in a dedicated database table (`wp_wcac_test_results`).
- **CSV Upload:**
  - Admins can upload a CSV file containing test queries (`query` column) and expected results (`expected_results` column with comma-separated IDs) via the Test Results admin page. Other columns (e.g., `comment`) are ignored but allowed.
  - Old test results and optimizer logs are deleted when a new CSV is uploaded.
- **Test Execution:**
  - The `Wcac_Test_Runner` orchestrates the process: parsing the CSV via `Wcac_Csv_Parser`, creating `Wcac_Test_Case` objects, running each query through the retrieval/scoring pipeline (`Wcac_ChatbotRules`), comparing actual vs. expected results, and logging via `Wcac_Test_Logger`.
  - The test runner deduplicates actual results and logs per-test-case breakdowns. Always outputs the best parameter set and a per-test-case breakdown, even if no global improvement is found.
  - After optimization, the test suite is automatically re-run to generate a post-optimization report.
- **Test Result Logging:**
  - Each test run is logged with a unique `run_id`, the `query`, `expected_results` (as JSON), `actual_results` (as JSON including scoring details), `score` (overall match score), `is_pass` (boolean), and `created_at` timestamp.
  - Results are stored separately from debug logs for clear separation of test automation and live user queries.
- **Admin UI:**
  - The Test Results page (`admin.php?page=wcac-test-results`) displays all test runs, including query, expected vs. actual results (with highlighting for matches/mismatches), pass/fail status, and a detailed scoring breakdown accordion for each actual result. UI display issues related to JSON decoding and formatting have been fixed.
  - Supports pagination and CSV re-upload for batch testing.
  - Deletion of selected results uses a custom confirmation modal implemented in HTML/JS (`#wcac-delete-confirm-modal`) triggered via AJAX, resolving persistent issues with duplicate confirmations from standard browser `confirm()` dialogs.

### 5. Optimizer (`Wcac_Settings_Optimizer`)
- **Purpose:** Automated tuning of RAG scoring parameters using a hybrid grid-random search.
- **How It Works:**
  - Automatically determines the number of test cases in the most recent test run.
  - Sets the number of optimization iterations to 2x the number of test cases (minimum 10, can be overridden via `--iterations=N`).
  - **Parameter Ranges:** The optimizer uses predefined, hardcoded ranges for each tunable scoring parameter (e.g., `wcac_title_match_weight` from 5 to 30). These ranges are defined within the WP-CLI command class (`WCAC_CLI_Command`).
  - **Grid-Random Search:** Each parameter range is divided into 10 discrete steps. The optimizer then randomly selects a value from these steps for each parameter, creating random combinations to test.
  - For each sampled parameter set, the plugin temporarily updates the settings (`update_option`), runs the full test suite by executing the `wp wcac run_tests` command, scores the results by executing `wp wcac score_results --run_id=...`, and records the outcome.
  - **Dependency:** The optimizer relies on the `wp wcac run_tests` and `wp wcac score_results` commands functioning correctly and outputting expected strings ("Run ID: ...", "Pass rate: ...%") for parsing.
  - **Logging:** All runs (iteration, run_id, pass rate %, parameters tested) are logged to a CSV file (`optimizer_log.csv`) located in the plugin's root directory (`wp-content/plugins/wp-customer-ai-chatbot/`).
  - At the end, the best parameter set (highest pass rate) found during the run is applied permanently to the plugin settings (`update_option`). A summary is output to the CLI.
  - The optimizer uses the most recently uploaded/admin test suite by default (no need to specify a CSV unless desired).
  - All optimization and test logic runs server-side within the WordPress environment (never locally).
- **Admin Workflow:**
  - Old test results and optimizer logs are deleted when a new CSV is uploaded.
  - After optimization, the test suite is automatically re-run to generate a post-optimization report.
- **Extensibility:**
  - Placeholder for future LLM-powered synonym suggestion based on failed test cases.

### 6. Debug Logging (`Wcac_Debug_Logger`)
- **Purpose:** Records live user queries, extracted keywords, and search results for debugging and relevance tuning.
- **Live Query Logging:**
  - All user queries and their associated retrieval/scoring data can be logged to a dedicated debug log table (`wp_wcac_debug_logs`).
  - Each log entry includes the query, keywords, scoring breakdown, and the full set of candidate results.
- **Admin Review:**
  - The Debug Logs admin page (`admin.php?page=wcac-debug-logs`) allows review, filtering, and deletion of log entries. Deletion ("Delete Selected", "Delete All") now uses reliable AJAX handlers, replacing previous form submission logic.
  - Detailed scoring breakdowns are shown for each result, supporting transparency and troubleshooting.
- **Troubleshooting Support:**
  - Admins can test the logging system, force table creation, and view error messages directly from the admin UI.

## Error Handling, Batching, and UI Improvements

- **AJAX Batching:**
  - Indexer and optimizer use robust server-side batching with WordPress transients for resumable processing.
  - The frontend repeatedly calls the endpoint until all batches are processed, with a progress bar and status updates.
  - Final batch and completion status are now reliably handled, with fallback logic to prevent the UI from getting stuck at 99%.

- **Nonce and Security:**
  - All AJAX handlers use WordPress nonces for security. Nonce mismatches and verification errors are logged and surfaced in the UI.

- **Debug Logging:**
  - All critical steps (batch start, batch end, errors, completion) are logged to the server debug log for troubleshooting.

- **Error Diagnosis:**
  - Common issues (nonce errors, AJAX 500s, unserialization bugs, missing class includes) are handled with clear error messages and robust error handling.

## Tuning, Configuration, and Extensibility

- **Relevance Tuning:**
  - All scoring weights, boosts, and penalties are configurable in the plugin admin settings.
  - Experiment with different values to optimize search relevance for your content and user base.

- **Compound Product Names:**
  - Add frequently searched multi-word product names in the admin settings to ensure they are treated as single search units and receive appropriate search boosts.

- **Test Automation:**
  - Use the Test Results admin page to upload CSVs of test queries and review automated test outcomes.
  - Use comments in the CSV to document expected behavior or edge cases.

- **Extensibility:**
  - The retrieval, scoring, and logging logic is modular and can be extended for new content types, custom fields, or advanced ranking strategies.
  - The LLM prompt and response handling can be further customized for more advanced conversational flows.

- **Performance:**
  - The current system uses SQL `LIKE` queries for retrieval. For very large sites, consider exploring `FULLTEXT` indexes or pre-tokenized search fields.

## Automated RAG Parameter Optimization

The plugin includes a WP-CLI command for automated tuning of RAG scoring parameters:

### How It Works
- The optimizer (`wp wcac optimize_random`) automatically determines the number of test cases in the most recent test run (from the Test Results admin page upload).
- It sets the number of optimization iterations to 2x the number of test cases (minimum 10, can be overridden via `--iterations=N`).
- **Parameter Ranges:** The optimizer uses predefined, hardcoded ranges for each tunable scoring parameter (e.g., `wcac_title_match_weight` from 5 to 30). These ranges are defined within the WP-CLI command class (`WCAC_CLI_Command`).
- **Grid-Random Search:** Each parameter range is divided into 10 discrete steps. The optimizer then randomly selects a value from these steps for each parameter, creating random combinations to test.
- For each sampled parameter set, the plugin temporarily updates the settings (`update_option`), runs the full test suite by executing the `wp wcac run_tests` command, scores the results by executing `wp wcac score_results --run_id=...`, and records the outcome.
- **Dependency:** The optimizer relies on the `wp wcac run_tests` and `wp wcac score_results` commands functioning correctly and outputting expected strings ("Run ID: ...", "Pass rate: ...%") for parsing.
- **Logging:** All runs (iteration, run_id, pass rate %, parameters tested) are logged to a CSV file (`optimizer_log.csv`) located in the plugin's root directory (`wp-content/plugins/wp-customer-ai-chatbot/`).
- At the end, the best parameter set (highest pass rate) found during the run is applied permanently to the plugin settings (`update_option`). A summary is output to the CLI.
- The optimizer uses the most recently uploaded/admin test suite by default (no need to specify a CSV unless desired).
- All optimization and test logic runs server-side within the WordPress environment (never locally).

### How to Use
1. **Prerequisite:** Ensure you have uploaded a representative test suite (CSV format) via the Test Results admin page. The optimizer uses this to evaluate parameter sets.
2. Run the optimizer from the command line (SSH into the server):
   ```sh
   # Navigate to your WordPress root directory (e.g., /home/staging/public_html)
   cd /path/to/wordpress
   
   # Run the optimizer (uses default iterations based on test suite size)
   wp wcac optimize_random 
   
   # Optionally, specify the number of iterations
   wp wcac optimize_random --iterations=50 
   ```
3. Monitor the CLI output for progress on each iteration.
4. Review the final CLI output showing the best score and parameters found.
5. Optionally, examine the generated `optimizer_log.csv` (in the plugin directory `wp-content/plugins/wp-customer-ai-chatbot/`) for detailed run history.

### Interpreting Results
- The CLI will show the score (pass rate %) for each parameter set tested and the best overall result at the end.
- The `optimizer_log.csv` file contains columns: `iteration`, `run_id`, `score` (pass rate %), and `params` (JSON string of the parameter set used for that run).
- Use the CSV to plot score vs. iteration, or perform more detailed analysis (e.g., in Excel, Python with pandas) to understand which parameters or value ranges correlate with better performance on your test suite.

### Next Steps: LLM-Powered Synonym Suggestion
- The optimizer includes a placeholder for future integration with an LLM to analyze failed test cases and suggest synonyms or alternative phrasings.
- This will further automate the process of improving test coverage and relevance.

## Out-of-Stock Penalty (Scoring)

- **Parameter:** `outofstock_penalty`
- **Where:** Admin > WP Customer AI Chatbot > Settings > Scoring Rules
- **Default:** `0` (no penalty)
- **Range:** `-200` (strong penalty) to `0` (none)
- **Effect:** Applies a negative score to out-of-stock products during RAG scoring. This makes them less likely to appear in results, but does not filter them out entirely.
- **Tip:** Tune this value to balance recall (showing all relevant products) vs. user experience (hiding unavailable items). Set to `0` to ignore stock status, or a strong negative value to heavily penalize out-of-stock items.

## Glossary & Troubleshooting

### Key Terms
- **RAG (Retrieval-Augmented Generation):** AI technique that grounds LLM responses in retrieved, contextually relevant content.
- **Index:** The custom database table (`wp_wcac_index`) storing all searchable content fields for fast retrieval.
- **Scoring:** The process of assigning a relevance score to each candidate result based on keyword matches, field weights, and boosts.
- **Compound Product Name:** Multi-word product names (e.g., "Tynn Silk Mohair") treated as a single search unit for improved matching.
- **Test Results:** Automated test runs logged in a dedicated table (`wp_wcac_test_results`) for regression testing and tuning.
- **Debug Logs:** Live user queries and search results logged for troubleshooting and transparency.

### Troubleshooting
- **No Results or Poor Relevance:**
  - Check admin settings for scoring weights, stopwords, and compound product names.
  - Review debug logs for the actual keywords extracted and scoring breakdowns.
  - Rebuild the index after changing stopwords, negative keywords, or content types.
- **Test Results Not Logging:**
  - Ensure the test logger table exists (use the admin UI to force table creation if needed).
  - Check for PHP errors in the server log if uploads fail.
- **AJAX/JS Issues:**
  - Use browser dev tools to inspect AJAX requests and responses.
  - Ensure the correct admin page is loading the required JS and nonce fields.
- **Database Schema Mismatch:**
  - If you see errors like `Unknown column 'parent_categories' in 'field list'`, your database schema is out of date. Run the provided ALTER TABLE statement (see plugin documentation) or re-activate the plugin to trigger a schema update.
- **Performance Issues:**
  - For large sites, consider optimizing the index or exploring alternative search strategies.
- **Deployment/Caching:**
  - If code changes don't appear live on the server, verify file contents directly using `ssh` and `cat`. Clear server-side caches (especially OPcache) and restart relevant services (PHP-FPM, Apache/Nginx) as standard deployment steps.