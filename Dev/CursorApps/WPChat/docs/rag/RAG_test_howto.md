# RAG Test Suite & Root-Cause Analysis: How-To Guide

This guide explains how to run the Retrieval-Augmented Generation (RAG) test suite and perform root-cause analysis for the WP Customer AI Chatbot plugin. It is designed for both humans and AI agents.

---

## Prerequisites

- **WordPress** and the plugin must be installed and activated on your server.
- **WP-CLI** must be available on the server (`wp --info` to check).
- **SSH access** to the server (for running CLI commands and downloading reports).
- **Test suite CSV**: Prepare a CSV file with test queries and expected results (see below for format).
- **Index up to date**: Ensure the content index is current (re-index if plugin or content has changed).
- **Product Types**: All WooCommerce product types, including grouped and custom types, are supported and indexed. See [optimizer_parameters.md](../../dev-tools/optimizer_parameters.md) for extension details.
- **Rating Weight**: The plugin now supports a tunable "Rating Weight" parameter, boosting products based on their average rating. See [optimizer_parameters.md](../../dev-tools/optimizer_parameters.md) for details.

---

## 1. Prepare the Test Suite CSV

- The CSV should have at least these columns:
  - `query`: The test query string.
  - `expected_results`: Comma-separated list of expected post/product/page IDs (or JSON array).
  - (Optional) `comment`: Any notes for the test case.
- Example:

  | query                      | expected_results | comment                |
  |----------------------------|------------------|------------------------|
  | Do you have Peer Gynt yarn?| 6197,63784,81675 | Peer Gynt test         |

---

## 2. Upload the Test Suite

- Go to the WordPress Admin > **WP Customer AI Chatbot > Test Results** page.
- Upload your CSV file using the provided form.
- Wait for the upload and parsing to complete. The UI will show the number of test cases loaded.

---

## 3. Run the Test Suite (Admin UI or WP-CLI)

### **Via Admin UI**
- After uploading, click the **Run Tests** button (if available) or wait for the tests to run automatically.
- Results will appear in the Test Results table, showing queries, expected/actual results, and pass/fail status.

### **Via WP-CLI**
- SSH into your server and navigate to the WordPress root directory:
  ```sh
  cd /path/to/wordpress
  ```
- Run the test suite:
  ```sh
  wp wcac run_tests
  ```
  - To use a specific CSV:
    ```sh
    wp wcac run_tests --csv=path/to/your_test_cases.csv
    ```
- The CLI will output a run ID and summary.

---

## 4. Score and Review Test Results

- To score a specific test run (by run ID):
  ```sh
  wp wcac score_results --run_id=YOUR_RUN_ID --top_n=10
  ```
- The CLI will print the pass rate and summary.
- In the admin UI, review the Test Results table for detailed breakdowns.

---

## 5. Export, Analyze, and Report on Failed Cases

- **After every test run, always generate a comprehensive `RAG_analysis.md` report with general findings and root-cause analysis at the top.**
- **Before generating the report, review the latest admin settings and codebase documentation—including context compression and out-of-stock penalty settings—to ensure your analysis and recommendations reflect the current system and settings.**
- Use the provided analysis script to extract and analyze failed test cases:
  1. **Run the analysis script on the server:**
     ```sh
     php wp-content/plugins/wp-customer-ai-chatbot/dev-tools/testing/extract_failed_cases.php
     ```
     - This generates `missing_indexed_metadata.csv` in the `dev-tools/testing/` directory with all indexed fields for missing/failed cases.
  2. **Download the CSV to your local machine for further analysis:**
     ```sh
     scp -i /path/to/your/key.pem user@host:/path/to/wordpress/wp-content/plugins/wp-customer-ai-chatbot/dev-tools/testing/missing_indexed_metadata.csv ./dev-tools/testing/
     ```
  3. **Generate the Markdown report:**
     - Always run the local analysis script or use the AI to create `RAG_analysis.md` from the latest CSV, including general findings and root-cause analysis at the top, and referencing the current admin settings and codebase documentation for context.

---

## 6. Root-Cause Analysis (Manual or Automated)

- Open `missing_indexed_metadata.csv` and compare the indexed fields (title, categories, tags, search_blob, etc.) for each failed case.
- Look for:
  - Missing or misclassified categories/tags
  - Out-of-stock status
  - Content not present in `search_blob`
  - Keyword mismatches or synonym gaps
  - Any evidence of content truncation
  - Context compression mode affecting retrieval (e.g., deduplication or clustering removing relevant context)
  - Rating Weight set too high or low (may cause highly rated products to dominate or be ignored)
- **Document findings in a Markdown report (`RAG_analysis.md`) after every test run.**

---

## 7. Troubleshooting & Best Practices

- **If results are unexpectedly poor:**
  - Re-index all content (from plugin settings) to ensure the latest data is used.
  - Check for content truncation in `search_blob` (should contain full, stopword-filtered content).
  - Review plugin settings for negative keywords, scoring weights (including Rating Weight), context compression mode, out-of-stock penalty, and content type selection.
  - All WooCommerce product types, including grouped and custom types, are supported and indexed. See [optimizer_parameters.md](../../dev-tools/optimizer_parameters.md) for extension details.
  - Check PHP error logs for lines containing `WCAC TEST RUNNER` or `WCAC OPTIMIZER` for debugging info.
- **For optimizer runs:**
  - See `../dev-tools/optimizer.md` for details on running and interpreting the parameter optimizer.

---

## 8. Parameter Optimization

The WP Customer AI Chatbot includes a parameter optimizer that can automatically find the best RAG parameter settings for your site—including context compression and out-of-stock penalty. For complete details on running and interpreting the parameter optimizer, see the [optimizer documentation](../dev-tools/optimizer.md) and [optimizer parameters](../dev-tools/optimizer_parameters.md).

Key features of the optimizer:
- Randomly samples parameter sets within defined ranges
- Updates plugin settings for each sample
- Runs the test suite and scores the results
- Automatically applies the best parameter set found

---

## 9. Automation & Extensibility

- All steps can be scripted for CI/CD or automated QA.
- The test runner, scorer, and analysis scripts are modular and can be extended for new test types or reporting needs.

---

## 10. Reference

- **Test runner code:** `includes/testing/class-wcac-test-runner.php`
- **CSV parser:** `includes/testing/class-wcac-csv-parser.php`
- **Admin UI:** Test Results page in WordPress admin
- **WP-CLI commands:** `wp wcac run_tests`, `wp wcac score_results`, `wp wcac optimize_random`
- **Analysis script:** `dev-tools/testing/extract_failed_cases.php`

---

## 11. Required Server Commands (Do NOT use deprecated/absent commands)

- **To run the test suite:** (Admin UI recommended; WP-CLI custom command may not be available)
- **To analyze failed cases:**
  ```sh
  php wp-content/plugins/wp-customer-ai-chatbot/dev-tools/testing/extract_failed_cases.php
  scp -i /path/to/your/key.pem user@host:/path/to/wordpress/wp-content/plugins/wp-customer-ai-chatbot/dev-tools/testing/missing_indexed_metadata.csv ./dev-tools/testing/
  ```
- **Do NOT use:**
  - `wp wcac run_tests` (unless you have confirmed the custom WP-CLI command is registered)
  - Any other unlisted or deprecated commands

---

## 12. Out-of-Stock Penalty (Scoring)

- The out-of-stock penalty (`wcac_outofstock_penalty`) and context compression mode (`wcac_context_compression_mode`) are now tweakable in the admin UI (Settings > Scoring Rules and Context Compression, respectively).
- Default is 0 (no penalty); range is -200 (strong penalty) to 0 (none).
- This parameter controls how much out-of-stock products are penalized in RAG scoring. Adjust as needed to balance recall and user experience.
- **Tip:** If you notice relevant but out-of-stock products are missing from results, try reducing the penalty or setting it to 0 and rerun the analysis. If you notice context is missing or overly compressed, try adjusting the context compression mode and rerun the analysis.

---

**This guide ensures any developer or AI can run, score, and analyze the RAG test suite for the WP Customer AI Chatbot plugin.**

> **Note:** Before generating or updating `RAG_analysis.md`, always review the latest admin settings and codebase documentation. Recent changes may affect both root-cause analysis and recommendations. This ensures your analysis is based on the current implementation, settings, and features. 