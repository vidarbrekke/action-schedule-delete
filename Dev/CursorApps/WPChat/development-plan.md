# WordPress Customer AI Chatbot Plugin: Development Plan

## Overview
This document outlines the structure, tasks, and development flow for building a robust, extensible WordPress plugin that provides an AI-powered customer chatbot with up-to-date knowledge, WooCommerce product understanding, and customizable admin settings.

---

## 1. Project Structure

- `wp-customer-ai-chatbot.php` (Main plugin file)
- `includes/` (Core PHP classes/functions)
- `admin/` (Admin-specific logic, settings pages)
- `public/` or `frontend/` (Frontend logic, chat widget)
- `assets/` (CSS, JS, images)
- `templates/` (Overridable template files)
- `languages/` (Translation files)
- `readme.txt` (WordPress standard readme)
- `README.md` (Optional, for repository usage)
- `uninstall.php` (Cleanup on plugin deletion)

---

## 2. Milestone-Based Development Flow

### **MVP 1: Plugin Skeleton & Settings**
- Scaffold plugin files and directories.
- Register plugin activation/deactivation hooks.
- Create admin menu and settings page.
- Add fields for OpenRouter API key (with sanitization).
- Save/retrieve settings using the WordPress Options API.

### **MVP 2: Frontend Chat Widget (Static)**
- Add a shortcode or block to display the chat widget.
- Implement basic HTML/CSS/JS for the chat UI.
- No AI integration yet; static or echo responses.

### **MVP 3: OpenRouter LLM Integration**
- Connect chat widget to backend via AJAX.
- Use stored OpenRouter API key to call LLM API.
- Display LLM responses in the chat UI.
- Add nonce verification and error handling.

### **MVP 4: Content Indexing**
- Add admin settings checkboxes: "Index Products", "Index Pages", "Index Posts".
- Implement logic in `Wcac_Indexer` to query selected post types (`product`, `page`, `post`) based on settings.
- Define a unified index structure (e.g., storing title, content snippet, URL, post type, taxonomy terms, price, etc. for each item).
- Create a custom database table (`wp_wcac_index`) to store the index data efficiently.
- Implement batch processing in the indexer (`Wcac_Indexer::index_all_content`) to handle large sites without timeouts.
- Refine the admin UI to show index status (counts per type) and allow rebuilding the index (triggering the batch process).
- Hook into `save_post` action for relevant post types for incremental reindexing of individual items.

### **MVP 5: Retrieval-Augmented Generation (RAG) on Content**
- Update the retrieval function (`retrieve_relevant_content`) to work with the unified index.
- Score and retrieve relevant items (products, pages, posts) based on keyword overlap.
- Implement direct name matching for higher relevance on specific titles.
- Implement token counting/estimation to ensure the retrieved context fits within a specified budget.
- Inject context from the top N relevant items into the LLM prompt, clearly separating context from the user question.
- Add basic server-side logging for retrieved content and potential token limit issues.

### **MVP 6: Customization & Filtering**
- Add admin fields for:
    - System Prompt (Instructions for the LLM's general behavior).
    - Site-Specific Prompt (Additional context/rules about the specific site).
    - **Negative Keywords/Phrases:** A textarea field allowing admins to list words or phrases (one per line) to be excluded from the index.
    - Custom CSS (For styling the chat widget).
- Save these settings using the Options API.
- **Modify Indexer (`Wcac_Indexer::format_content_for_llm`):**
    - Retrieve the list of negative keywords/phrases from settings.
    - Process the list (split by newline, trim whitespace, filter empty lines).
    - After generating the combined `text` for an item but *before* returning the structured array, use case-insensitive replacement (`str_ireplace`) to remove all occurrences of the negative keywords/phrases from the `text` field.
- Apply custom CSS to the chat widget on the frontend.
- Modify the AJAX handler (`handle_send_message_ajax`) to include the System Prompt and Site-Specific Prompt in the messages array sent to the LLM API.

### **MVP 7: Polish & Extensibility**
- Add hooks and filters for extensibility.
- Implement localization (i18n/l10n) for all user-facing strings.
- Add uninstall routine to clean up plugin data.
- Implement server-side post-processing of LLM responses to ensure reliable product hyperlinking.
- Add PHPDoc and inline comments.
- Maintain a changelog and update documentation.

### **MVP 8: Site Profile Generation and Taxonomy-Aware Indexing**
- Implement logic to generate a site profile based on site-specific data.
- Add taxonomy-aware indexing to the retrieval process.
- Refine the admin UI to show site profile and taxonomy-aware indexing status.
- Hook into relevant actions for site profile and taxonomy-aware indexing.

---

## 3. Best Practices & Guidelines

- **Follow WordPress and WooCommerce coding standards.**
- **Use a unique prefix** for all functions, classes, and options.
- **Sanitize and validate** all input and output.
- **Use nonces** for all form submissions and AJAX requests.
- **Leverage built-in APIs** (Options API, Settings API, AJAX, WP_Query, etc.).
- **Minimize custom tables**; use only if necessary for performance.
- **Enqueue assets conditionally** (only where needed).
- **Document all custom hooks and filters.**
- **Write unit/integration tests** for core logic.
- **Maintain a detailed changelog.**

---

## 4. Testing & Verification

- After each milestone, verify functionality manually and with automated tests.
- Provide clear test instructions for each feature.
- Use WP_UnitTestCase for automated testing where applicable.

---

## 5. Documentation

- Keep `readme.txt` up to date with features, installation, and usage instructions.
- Document all settings, hooks, and extensibility points.
- Maintain a `CHANGELOG.md` for release notes.

---

## 6. Future Enhancements (Optional)

- Add support for embeddings/vector search for advanced retrieval.
- Enhance chat UI with avatars, typing indicators, etc.
- Add multi-site compatibility.
- Integrate with other WooCommerce data (orders, customers, etc.).
- **Index Custom Fields/Taxonomies:** Add configuration or hooks to allow indexing of product data added by third-party plugins (e.g., brand plugins, custom field plugins).
- **Prioritize Menu/Category Matches:** Enhance retrieval logic to optionally give higher relevance to content items corresponding to navigation menu items or primary site categories.
- **Index Category/Tag Archive Pages:** Add category and tag archive pages themselves to the index, allowing the chatbot to directly suggest browsing a category if it's a relevant match.

---

## 7. Getting Started

1. Clone the repository or copy plugin files into your WordPress `wp-content/plugins/` directory.
2. Activate the plugin from the WordPress admin.
3. Follow the milestone tasks above, committing and testing after each stage.
4. Refer to this plan and the codebase documentation for guidance. 