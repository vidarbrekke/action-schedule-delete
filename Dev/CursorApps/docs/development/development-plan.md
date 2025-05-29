# WordPress Customer AI Chatbot Plugin: Development Plan

## Overview
This document outlines the structure, tasks, and development flow for building a robust, extensible WordPress plugin that provides an AI-powered customer chatbot with up-to-date knowledge, WooCommerce product understanding, and customizable admin settings. **(Status: Actively Developed)**

---

## 1. Project Structure

- `wp-customer-ai-chatbot.php` (Main plugin file) - **Done**
- `includes/` (Core PHP classes/functions) - **Done**
- `admin/` (Admin-specific logic, settings pages) - **Done**
- `public/` or `frontend/` (Frontend logic, chat widget) - **Done**
- `assets/` (CSS, JS, images) - **Done**
- `templates/` (Overridable template files) - **Not Implemented**
- `languages/` (Translation files) - **Partially Implemented (some strings)**
- `readme.txt` (WordPress standard readme) - **Updated**
- `DOCUMENTATION_INDEX.md` (Optional, for repository usage) - **Exists**
- `uninstall.php` (Cleanup on plugin deletion) - **Not Implemented**

---

## 2. Milestone-Based Development Flow (Revised based on Actual Progress)

### **Done: Initial Setup & Core**
- Scaffolded plugin files and directories.
- Registered plugin activation/deactivation hooks.
- Created admin menu and settings page.
- Added fields for OpenRouter API key.
- Saved/retrieved core settings using the WordPress Options API.
- Added a shortcode for the chat widget.
- Implemented basic HTML/CSS/JS for the chat UI.
- Connected chat widget to backend via AJAX.
- Implemented OpenRouter LLM API call.
- Displayed LLM responses in the chat UI.
- Added nonce verification and basic error handling.

### **Done: Content Indexing & RAG**
- Added admin settings for content types (Products, Pages, Posts).
- Implemented `Wcac_Indexer` to query selected post types.
- Defined and implemented a unified index structure using a custom table (`wp_wcac_index`).
- Implemented batch processing for indexing.
- Refined the admin UI for index status and manual re-indexing.
- Hooked into `save_post` for incremental updates.
- Implemented retrieval (`retrieve_relevant_content`) using the index.
- Implemented keyword-based scoring (`Wcac_ChatbotRules::score_item`).
- Implemented direct name matching and other boosts/weights.
- Injected context into the LLM prompt.
- Added basic server-side logging.

### **Done: Customization & LLM Tuning**
- Added admin fields for:
    - System Prompt & Site-Specific Prompt.
    - Negative Keywords (with indexer modification).
    - Custom CSS.
    - Synonym Map.
    - Numerous Scoring Weights/Boosts/Penalties/Thresholds.
    - LLM API parameters (`temperature`, `top_p`, `max_tokens`, frequency/presence penalties, model).
- Implemented saving and sanitization for all settings.
- Applied custom CSS and prompts in relevant locations.
- Refined admin UI for scoring rules (grouping, descriptions, input types - Option 1).

### **Done: Testing & Documentation**
- Implemented comprehensive testing (manual and automated)
- Created and updated `CHANGELOG.md`
- Updated `readme.txt` with latest features and instructions

### **In Progress / Next Steps:**
- **Refine Scoring & Retrieval:** Fine-tune default weights/boosts, improve keyword extraction/matching, potentially explore fuzzy matching.
- **Improve Frontend Widget:** Enhance UI/UX (e.g., loading indicators, better error display, markdown rendering).
- **Reliable Product Linking:** Improve server-side post-processing of LLM responses to reliably generate product links.
- **Localization:** Complete i18n/l10n for all user-facing strings.
- **Uninstall Routine:** Implement `uninstall.php` for complete cleanup.

### **Future (Post-Core Functionality):**
- **Site Profile Generation & Taxonomy-Aware Indexing:** (Original MVP 8)
- **Other items from "Future Enhancements" section below.**

---

## 3. Best Practices & Guidelines

- **Follow WordPress and WooCommerce coding standards.** - **Ongoing**
- **Use a unique prefix** for all functions, classes, and options. - **Done (wcac_)**
- **Sanitize and validate** all input and output. - **Implemented**
- **Use nonces** for all form submissions and AJAX requests. - **Implemented**
- **Leverage built-in APIs** (Options API, Settings API, AJAX, WP_Query, etc.). - **Implemented**
- **Minimize custom tables**; use only if necessary for performance. - **Done (1 table for index)**
- **Enqueue assets conditionally** (only where needed). - **Implemented**
- **Document all custom hooks and filters.** - **Needs Improvement**
- **Write unit/integration tests** for core logic. - **Implemented**
- **Maintain a detailed changelog.** - **Implemented**

---

## 4. Testing & Verification

- After each milestone, verify functionality manually and with automated tests. - **Manual and automated verification performed**
- Provide clear test instructions for each feature. - **Implemented**
- Use WP_UnitTestCase for automated testing where applicable. - **Implemented**

---

## 5. Documentation

- Keep `readme.txt` up to date with features, installation, and usage instructions. - **Updated**
- Document all settings, hooks, and extensibility points. - **Needs Improvement**
- Maintain a `CHANGELOG.md` for release notes. - **Implemented**

---

## 6. Future Enhancements (Optional / Post-Core)

- Add support for embeddings/vector search for advanced retrieval.
- Implement RAG prioritization based on recent/frequently accessed products.
- Implement advanced user query processing (intent/entity extraction, filtering).
- Optimize context formatting (e.g., remove labels, compress format, context-aware attribute selection).
- Enhance chat UI with avatars, typing indicators, better markdown rendering, etc.
- Add multi-site compatibility.
- Integrate with other WooCommerce data (orders, customers, etc.).
- Explore structured LLM output (e.g., JSON response format) for specific tasks.
- **Index Custom Fields/Taxonomies:** Add configuration or hooks to allow indexing of product data added by third-party plugins (e.g., brand plugins, custom field plugins).
- **Prioritize Menu/Category Matches:** Enhance retrieval logic to optionally give higher relevance to content items corresponding to navigation menu items or primary site categories.
- **Index Category/Tag Archive Pages:** Add category and tag archive pages themselves to the index, allowing the chatbot to directly suggest browsing a category if it's a relevant match.
- **Abstracted Scoring UI (Option 2):** Create a simplified UI layer (e.g., sliders for "Title Importance," "Result Strictness") that maps to the underlying detailed scoring parameters. This would hide complexity for basic users but requires careful design of the mapping logic.
- **LLM-based Debug Log Comparison and Dialogue:** Allow the admin to ask an LLM to compare debug log results across different settings and have a dialogue about optimization.
- **Refactor Optimizer Robustness:** Improve robustness by replacing the optimizer's reliance on `exec()` calls to `wp wcac run_tests` and `wp wcac score_results`. Instead, have those commands return structured data (e.g., JSON) or call the underlying PHP test runner and scorer functions directly. This is a larger refactoring task.

---

## 7. Getting Started

1. Clone the repository or copy plugin files into your WordPress `wp-content/plugins/` directory.
2. Activate the plugin from the WordPress admin.
3. Follow the milestone tasks above, committing and testing after each stage.
4. Refer to this plan and the codebase documentation for guidance. 