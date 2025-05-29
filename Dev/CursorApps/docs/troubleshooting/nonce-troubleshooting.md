# WordPress Nonce Debugging & Fix Guide

This guide explains how to fix and debug nonce errors in WordPress, with a focus on AJAX and admin workflows. It is based on real issues encountered in the WP Customer AI Chatbot project.

---

## 1. Always Match the Nonce Action String
- The string used in `wp_create_nonce('action')` **must exactly match** the string used in `check_ajax_referer('action', ...)` or `wp_verify_nonce(..., 'action')`.
- Example:
  - Nonce creation: `wp_create_nonce('wcac_reindex_nonce')`
  - Verification: `wp_verify_nonce($_POST['nonce'], 'wcac_reindex_nonce')`
- **Mismatch = always fails.**

## 2. Consistent Nonce Field Names
- In JavaScript, send the nonce with a clear, consistent key (e.g., `nonce` or `security`).
- In PHP, check for the same key in `$_POST` or `$_REQUEST`.
- If you change the key in JS, update the PHP handler accordingly.

## 3. Localize Nonce to JavaScript
- Use `wp_localize_script` to pass the nonce to your JS:
  ```php
  wp_localize_script('my-script', 'myData', [
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce'    => wp_create_nonce('my_action'),
  ]);
  ```
- In JS, use `myData.nonce` in your AJAX request.

## 4. Debugging Nonce Failures
- Log the incoming nonce and POST data in your PHP handler:
  ```php
  error_log('Incoming nonce: ' . ($_POST['nonce'] ?? 'MISSING'));
  error_log('POST data: ' . print_r($_POST, true));
  ```
- This helps confirm if the nonce is missing, mismatched, or malformed.

## 5. AJAX Handler Best Practices
- Always check the nonce at the very start of your handler.
- Return a clear error if verification fails:
  ```php
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'my_action')) {
      wp_send_json_error(['message' => 'Nonce verification failed.']);
  }
  ```

## 6. Cache, Expiry, and Deployment
- Nonces are user/session-specific and expire after 24 hours by default.
- If you deploy new code or clear caches, users may need to reload the admin page to get a fresh nonce.

## 7. Testing
- Test with multiple user roles and in incognito/private windows to ensure nonces are generated and verified correctly for all users.

---

## Common Pitfalls
- **Action string mismatch** between creation and verification.
- **Nonce field name mismatch** between JS and PHP.
- **Stale nonce** after deployment or cache clear (requires page reload).
- **Not localizing the nonce** to JS, or using a hardcoded value.

---

## Quick Checklist for Fixing Nonce Errors
1. Confirm the action string is identical in both `wp_create_nonce` and `wp_verify_nonce`/`check_ajax_referer`.
2. Ensure the nonce field name is the same in JS and PHP.
3. Log the incoming nonce and POST data if verification fails.
4. Instruct users to reload the page after deployment or cache clear.
5. Always localize the nonce to JS using `wp_localize_script`.
6. Test with different users and browsers.

If you follow these steps, you will resolve most nonce errors quickly and avoid them in future development. 