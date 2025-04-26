# WordPress Customer AI Chatbot Plugin: Testing Plan

## Overview
This document outlines the Test-Driven Development (TDD) approach for the WordPress Customer AI Chatbot plugin. We will use the standard WordPress testing framework (WP_UnitTestCase based on PHPUnit) for unit and integration tests.

---

## Testing Setup

1.  **Environment:** Set up the WordPress testing environment using the `wp scaffold plugin-tests` command.
2.  **Test Files:** Organize tests within a `tests/` directory at the plugin root, mirroring the `includes/` and `admin/` structure where applicable (e.g., `tests/test-settings.php`, `tests/test-indexing.php`).
3.  **Running Tests:** Use PHPUnit to run tests from the command line.

---

## Testing Strategy per Milestone

### **MVP 1: Plugin Skeleton & Settings**
- **Unit Tests (`WP_UnitTestCase`):**
    - Test settings registration (e.g., `test_settings_fields_are_registered`).
    - Test sanitization callbacks for each setting (e.g., `test_sanitize_api_key`).
    - Test saving and retrieving the OpenRouter API key (e.g., `test_api_key_option_saves_retrieves`).
    - Test activation hook logic (e.g., default options set).
    - Test deactivation hook logic (if any).
- **Integration Tests:**
    - Verify the admin menu and settings page are correctly added.

### **MVP 2: Frontend Chat Widget (Static)**
- **Manual Testing:**
    - Verify the shortcode/block renders the basic chat UI structure correctly on a page/post.
    - Check basic CSS rendering.
    - Test responsiveness (if applicable).
- **Integration Tests (Potentially):**
    - Test shortcode/block registration and output generation.

### **MVP 3: OpenRouter LLM Integration**
- **Unit Tests (`WP_UnitTestCase`):**
    - Test AJAX handler registration.
    - Test nonce verification logic within the AJAX handler.
    - Test error handling for missing API key or invalid input.
    - Mock `wp_remote_post` to test the structure of the API request sent to OpenRouter (e.g., `test_openrouter_request_structure`).
    - Test processing of successful and error responses from the mocked API call.
- **Integration Tests:**
    - Simulate an AJAX request and verify the handler returns the expected JSON structure (success/error).
- **Manual/E2E Testing:**
    - Use the frontend chat widget to send a message and verify a response (or error) appears.

### **MVP 4: WooCommerce Product Indexing**
- **Unit Tests (`WP_UnitTestCase`):**
    - Test functions responsible for fetching product data (`test_get_product_data`).
    - Test the structure and content of the indexed data for a sample product.
    - Test the logic for saving/updating the index (e.g., in `wp_options`).
    - Test the reindexing trigger logic (e.g., `test_reindex_on_product_update_hook`).
- **Integration Tests:**
    - Create/update a test WooCommerce product and verify the index is updated accordingly.
    - Test the admin UI functions for viewing/rebuilding the index.

### **MVP 5: Retrieval-Augmented Generation (RAG)**
- **Unit Tests (`WP_UnitTestCase`):**
    - Test the keyword retrieval function against a sample index (e.g., `test_retrieve_products_by_keyword`).
    - Test the prompt construction logic, ensuring retrieved context is correctly formatted and inserted.
    - Mock the retrieval and LLM call steps to test the overall flow within the AJAX handler.
- **Integration Tests:**
    - Simulate an AJAX request with specific keywords and verify the correct context is retrieved and used in the (mocked) LLM call.
- **Manual/E2E Testing:**
    - Ask the chatbot questions related to specific products and verify relevant information appears in the response.

### **MVP 6: Custom Prompts & CSS**
- **Unit Tests (`WP_UnitTestCase`):**
    - Test saving/retrieving/sanitizing the system prompt, site prompt, and custom CSS options.
    - Test the prompt construction logic to ensure custom prompts are correctly included.
- **Integration Tests:**
    - Verify custom CSS is correctly enqueued/outputted on the frontend.
- **Manual Testing:**
    - Update prompts/CSS in settings and verify changes on the frontend chat widget and in LLM responses.

### **MVP 7: Polish & Extensibility**
- **Unit Tests (`WP_UnitTestCase`):**
    - Test any new helper functions or classes added.
    - Test localization functions (ensure text domain is loaded).
    - Test uninstall script logic (mocking option deletion etc.).
- **Integration Tests:**
    - Test custom action/filter hooks by adding test callbacks.
- **Manual Testing:**
    - Verify translations load correctly (if translations are provided).
    - Test the uninstall process to ensure data cleanup.

---

## General Testing Principles

- **Write tests before or alongside code.**
- **Keep tests small and focused** on a single piece of functionality.
- **Use descriptive test names.**
- **Refactor tests** as code evolves.
- **Run tests frequently** during development. 