# How to Use the Failed Test Analysis Script

This guide explains how to use the `export_and_analyze_failed_tests.sh` script to diagnose failed RAG (Retrieval-Augmented Generation) test runs for the WP Customer AI Chatbot plugin.

## Purpose

The script automates the process of:
1. Running a detailed PHP analysis script (`wcac_failed_test_diagnosis.php`) directly on the staging server against the latest test results stored in the database (`wp_wcac_test_results`).
2. Generating a comprehensive Markdown report (`wcac_failed_test_diagnosis_report.md`) on the server containing insights into why tests failed.
3. Downloading this report to your local machine for review.

This provides much richer diagnostic information than standard test runner output alone.

## Workflow Steps

When you run the script, it performs the following actions:
1. **Connects via SSH:** Uses the credentials (user, host, key path) defined at the top of the script to connect to the staging server.
2. **Executes Analysis Script:** Navigates to the WordPress root directory on the server and uses `wp eval-file` to execute the `wcac_failed_test_diagnosis.php` script. This script queries the database, analyzes failures, and writes the report file *on the server*.
3. **Downloads Report:** Uses SCP to download the generated `wcac_failed_test_diagnosis_report.md` from the server's plugin `dev-tools/testing` directory to your local `dev-tools/testing/` directory, overwriting any previous version.

## Prerequisites

Before running the script, ensure:
- **SSH Access:** You have SSH access to the staging server (`staging@45.33.31.79`).
- **SSH Key:** The path to your private SSH key (`/Users/vidarbrekke/Dev/socialintent/staging.motherknitter.pem`) is correct in the script, and the file exists and has the correct read permissions (`chmod 400 path/to/key`).
- **Remote Analysis Script:** The PHP analysis script `wcac_failed_test_diagnosis.php` **must exist** on the staging server at this exact path: `/home/staging/public_html/wp-content/plugins/wp-customer-ai-chatbot/dev-tools/testing/wcac_failed_test_diagnosis.php`. If it's missing, the script will fail (see Troubleshooting).
- **WP-CLI:** WP-CLI is installed and functional on the staging server.
- **Test Results:** The `wp_wcac_test_results` table on the staging server contains data from previous test runs (e.g., via the optimizer or `wp wcac run_tests`).

## How to Run

1. Open your terminal.
2. Navigate to the project root directory (`/Users/vidarbrekke/Dev/CursorApps/WPChat`).
3. Execute the script:
   ```bash
   bash dev-tools/testing/export_and_analyze_failed_tests.sh
   ```

## Output

The script will print progress messages to the console. Upon successful completion, it will download the analysis report to:
`dev-tools/testing/wcac_failed_test_diagnosis_report.md`

## Analyzing Results

Open the `wcac_failed_test_diagnosis_report.md` file in a Markdown viewer or text editor. The report typically includes:
- A summary of the latest test run (pass rate, total tests).
- Detailed analysis of each failed test case, showing:
    - The query.
    - Expected vs. Actual results.
    - Scoring breakdowns for actual results.
    - Potential hypotheses for the failure (e.g., "Keyword not found", "Low score", "Incorrect parameters").
    - Actionable recommendations (e.g., "Adjust scoring weights", "Add synonyms", "Check indexed content").

Use this report to identify patterns in failures and guide adjustments to scoring parameters, context compression mode, out-of-stock penalty, Rating Weight, indexed content, or the test suite itself. All WooCommerce product types, including grouped and custom types, are supported and indexed. See optimizer_parameters.md for parameter and extension details.

## Troubleshooting

- **SSH Errors:** Check the SSH key path, permissions (`chmod 400`), and ensure the user/host are correct. Verify you can SSH manually.
- **`wp eval-file` Errors:** This usually indicates a PHP syntax error within the `wcac_failed_test_diagnosis.php` script itself. Check the script for errors and ensure it's compatible with the server's PHP version.
- **`File does not exist` Error:** This is the most common issue if the script worked previously. It means `wcac_failed_test_diagnosis.php` is missing from the server at `/home/staging/public_html/wp-content/plugins/wp-customer-ai-chatbot/dev-tools/testing/`.
    - **Fix:** Upload the local `dev-tools/testing/wcac_failed_test_diagnosis.php` script to that specific directory on the server using SCP or your preferred deployment method.
- **SCP Errors:** Usually caused by incorrect paths or permissions issues on the server or locally. Verify the `REMOTE_REPORT_PATH` and `LOCAL_REPORT_PATH` variables in the script.

Always ensure the remote analysis script is present and up-to-date on the server before running the export script. When troubleshooting, always check the current context compression mode, out-of-stock penalty, Rating Weight, and scoring parameters in the admin UI. All WooCommerce product types, including grouped and custom types, are supported and indexed. See optimizer_parameters.md for extension details. 