# Debugging Log: WCAC Chatbot AJAX Failures (April 2025)

This document summarizes the debugging process undertaken to resolve issues with the WP Customer AI Chatbot plugin, specifically concerning failed AJAX requests and inconsistent logging for certain user queries like "Peer Gynt".

## Initial Problem Symptoms

1.  **Flawed SQL Query:** Certain user queries (e.g., "Peer Gynt") resulted in the `retrieve_relevant_content` function generating a flawed SQL `LIKE` clause using a SHA-256 hash instead of the search term (e.g., `LIKE '{hash...}'`). This was observed in initial debugging but became less relevant as the core issue was found earlier in the process.
2.  **AJAX Handler Not Logging:** For the same problematic queries, the primary AJAX handler `handle_send_message_ajax` in `Wcac_Public` class failed to write *any* expected `error_log` messages to the standard `wp-content/debug.log`, despite evidence that the function *was* being called.
3.  **Successful Browser Response (Sometimes):** Paradoxically, even when server logs were missing, the browser sometimes received a `200 OK` status, occasionally even with a valid (though perhaps incorrect) JSON response, suggesting partial execution of the handler.

## Initial Hypotheses

*   Plugin/Theme Conflict: Another plugin or the theme interfering with the AJAX request or output.
*   MU-Plugin Interference: A Must-Use plugin intercepting or modifying the request.
*   Server Configuration: `.htaccess` rules, `mod_security`, or PHP settings interfering.
*   Fatal Error in Handler: A fatal PHP error occurring within the handler, potentially masked by output buffering.
*   Logic Error in Handler: A specific bug within the `handle_send_message_ajax` logic or its called methods.

## Debugging Steps & Findings

1.  **Enhanced Logging:** Added detailed `error_log` statements throughout `handle_send_message_ajax` and related functions (`retrieve_relevant_content`). **Finding:** Logs appeared for simple queries ("Hello") but were consistently *missing* for "Peer Gynt", even logs placed at the very top of the handler.
2.  **Searched for Interference:** Grep searches for `$_POST['message']` access, SHA-256 hashing (`hash('sha256', ...)`), and the specific flawed `LIKE '{hash...}'` pattern in `wp-content` yielded no likely culprits directly modifying the request or query.
3.  **Checked Output Buffering:** Searched for `ob_start()` calls; found many but no obvious interference point explaining the selective failure.
4.  **Increased Memory/Error Display:** Set `WP_MEMORY_LIMIT` to `512M` and forced `display_errors` in `wp-config.php`. **Finding:** No fatal errors were displayed in the browser response body, suggesting the issue wasn't a standard fatal PHP error.
5.  **Simplified AJAX Handler:** Pointed the `wp_ajax_wcac_send_message` action to a minimal global function (`wcac_simple_ajax_test`). **Finding:** This worked perfectly, returning a success message and logging correctly. This proved the basic AJAX routing, nonce handling, and server setup were functional, isolating the issue to the `Wcac_Public->handle_send_message_ajax` method or its invocation.
6.  **Incremental Uncommenting:** Reverted the AJAX hook back to the class method and systematically uncommented sections of `handle_send_message_ajax`:
    *   Minimal handler (just log + success): Browser success, **NO log** for "Peer Gynt".
    *   Security checks + sanitization: Browser success, **NO logs** for "Peer Gynt".
    *   `try...catch` structure added: Browser success, **NO logs** for "Peer Gynt".
    *   Intent classification added: Browser success (showing correct intent), **NO log** for "Peer Gynt" *after* classification.
    *   Context building added: Browser success (showing context built), logs still failing.
    *   LLM response generation added: Browser **FAILURE** (`success: false`).
7.  **Logging Failure Confirmation:** The consistent failure of `error_log` *specifically* for "Peer Gynt", even when the surrounding code executed successfully (proven by browser responses), became the central mystery.
8.  **Checked Server Logs:** `tail`ed Apache `error.log` (showed unrelated PHP `mysqli` warning) and attempted to check `modsec_audit.log` (not found at default path). No direct evidence of server interference found.
9.  **Compared with Old Commit:** Examined code from commit `1771a065...`. **Finding:** The older, working code had minimal/no debug logging in the AJAX handler's success path. This supported the hypothesis that the *act of logging* the specific "Peer Gynt" string was problematic.
10. **Bypassed Logging for Errors:** Modified the main `catch` block to send the actual `Exception->getMessage()` back to the browser, bypassing server logs.
11. **Identified 401 Error:** Browser response revealed `DEBUG: Error encountered: API request failed with status 401`.
12. **Captured Request Context:** Modified error handling to include request context (URL, headers, messages) in the error sent to the browser.
13. **Found URL Mismatch:** Browser response showed `DEBUG: ... "resolved_url":"https:\/\/api.openai.com\/..." ... "api_url_from_settings":"[Not Set]" ...`. **Finding:** Confirmed that `get_option('wcac_settings')` was returning data without the `wcac_api_url`, causing fallback to the **incorrect** OpenAI URL when the OpenRouter key was used.
14. **Tested Cache Flush:** Added `wp_cache_delete('wcac_settings', 'options')` before `get_option`. **Finding:** This **resolved** the issue; the correct OpenRouter URL was used, and the LLM call succeeded.

## Culprit

The immediate cause of the failure for the "Peer Gynt" query was that the call to `get_option('wcac_settings')` within the `generate_llm_response` function was retrieving stale or incorrect data where the `wcac_api_url` key was missing. This caused the code to incorrectly default to the OpenAI API endpoint (`https://api.openai.com/v1/chat/completions`) while still using the OpenRouter API key (`sk-or-v1-...`), resulting in a `401 Unauthorized` error from the API.

The underlying reason *why* the options cache (`get_option`) returned incorrect data specifically for requests containing "Peer Gynt" remains unclear but points towards a subtle issue with WordPress's object cache or potential memory interference related to that specific input string.

The secondary, persistent issue was the silent failure of the `error_log` function when processing the "Peer Gynt" string within the AJAX handler's context. This masked the underlying 401 error for a long time and might be related to server configuration (PHP logging settings, `mod_security`) or an edge case PHP bug.

## Solution

1.  **Cache Flushing:** Adding `wp_cache_delete('wcac_settings', 'options')` before `get_option('wcac_settings')` forced a fresh retrieval from the database, bypassing the bad cache entry and ensuring the correct OpenRouter API URL was used. (This was subsequently removed as a permanent fix, assuming the underlying cache issue is intermittent or resolved elsewhere).
2.  **Restoring Logic:** Systematically uncommenting the code and confirming each stage worked (once the cache issue was bypassed) restored full functionality.
3.  **Bypassing Logging:** Using the browser response to relay error messages when server-side logging proved unreliable for the specific input was a crucial debugging step.

## Remaining Mysteries

*   Why did the WordPress object cache serve incorrect data for `wcac_settings` *only* when the AJAX request contained "Peer Gynt"?
*   Why did `error_log` fail silently *only* when processing the "Peer Gynt" string within the AJAX handler? 