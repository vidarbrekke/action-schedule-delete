# WP Customer AI Chatbot Optimizer Guide

This guide explains how to run the RAG parameter optimizer for the WP Customer AI Chatbot plugin using WP-CLI on your WordPress server.

---

## Prerequisites

- **WordPress** and the plugin must be installed and activated on your server.
- **WP-CLI** must be available on the server (run `wp --info` to check).
- You must have SSH access to the server and permission to run WP-CLI commands.
- The test suite (CSV) should be uploaded via the plugin's Test Results page.

---

## How to Run the Optimizer

1. **Deploy the latest plugin code to your server.**
   - Ensure all recent changes are uploaded.

2. **(Optional) Re-upload your test CSV** via the Test Results page to ensure the test suite is up to date.

3. **SSH into your server.**

4. **Navigate to your WordPress root directory.**
   ```sh
   cd /home/staging/public_html
   ```

5. **Run the optimizer using WP-CLI:**
   ```sh
   wp wcac optimize_random --iterations=20
   ```
   - You can adjust the number of iterations (default is 20).
   - Each iteration tests a random set of RAG parameters—including context compression and out-of-stock penalty settings—and scores the results.

---

## What the Optimizer Does

- Randomly samples parameter sets within defined ranges.
- Updates plugin settings for each sample.
- Runs the test suite and scores the results.
- Logs all runs and scores.
- At the end, updates the plugin with the best parameter set found.

---

## Interpreting Results

- The CLI output will show the score for each parameter set.
- At the end, it prints the best score and the best parameters (now active in the plugin), including context compression and out-of-stock penalty settings.
- If the score is always zero, check your test data, index, and retriever logic.

---

## Troubleshooting & Debugging

- **Check the PHP error log** for lines containing `WCAC OPTIMIZER:` and `WCAC TEST RUNNER:` for parameter propagation and test run details.
- If you see `Error: Test run failed or no run ID returned`, ensure your test suite is uploaded and the index is up to date.
- If all scores are zero, verify that your test suite's expected IDs match indexed content and that the retriever returns valid results.
- For deeper debugging, increase the number of optimizer iterations or review the optimizer and test runner logs.

---

## Tips

- You can re-run the optimizer at any time; it will always update the plugin to the best parameter set found.
- After running the optimizer, the Test Results page will reflect the new parameters.
- For best results, keep your test suite and index up to date.

---

For further help, review the plugin documentation or contact the developer.

## Note on New Parameters

The optimizer now includes the context compression mode and out-of-stock penalty as tunable parameters. These can significantly affect retrieval quality and test results. See `optimizer_parameters.md` for details. 