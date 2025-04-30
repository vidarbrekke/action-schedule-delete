# WCAC Chatbot: RAG Process Overview (Post-Refactor)

This document outlines the flow and key components involved in the Retrieval-Augmented Generation (RAG) process used by the WordPress Customer AI Chatbot plugin after the recent refactoring.

## Core Goal

To answer user queries accurately based on indexed website content (products, pages, posts), minimizing hallucination by grounding Large Language Model (LLM) responses in retrieved context.

## Key Components

1.  **`Wcac_Indexer` (`includes/class-wcac-indexer.php`)**:
    *   Responsible for periodically scanning selected post types.
    *   Extracts key data (title, content snippet, URL, type, categories, tags, price, stock status, etc.) using helper methods (`_extract_basic_data`, `_extract_term_data`, `_extract_product_metadata`).
    *   Stores extracted data, including JSON-encoded term lists, into the custom `wp_wcac_index` database table.
    *   Uses AJAX-driven batching to handle potentially large amounts of content without timeouts.

2.  **`Wcac_Public` (`public/class-wcac-public.php`)**:
    *   Handles the frontend AJAX request (`wcac_send_message` action via `handle_send_message_ajax`).
    *   Receives user message and conversation history.
    *   Orchestrates the RAG flow by calling `Wcac_ChatbotRules` and `WCAC_API_Handler`.
    *   Handles different scenarios: specific product follow-up (`handle_product_specific_query`), no results found (`handle_no_results_found`), or processing search results (`process_search_results`).
    *   Formats the final response for the user, currently combining an LLM-generated introduction with a PHP-generated list of relevant products.

3.  **`Wcac_ChatbotRules` (`includes/class-wcac-chatbot-rules.php`)**:
    *   **Keyword Extraction (`extract_keywords`)**: 
        * Processes the user's raw message to extract relevant search keywords (removes punctuation, stopwords).
        * Detects compound product names from admin settings (e.g., "Peer Gynt", "Tynn Silk Mohair") and preserves them as single search units.
        * Returns an array of keywords weighted for search.
    *   **Content Retrieval (`retrieve_relevant_content`)**:
        *   Builds a database query (`_build_search_where_clause`) to find potentially relevant items in the `wp_wcac_index` table based on keyword `LIKE` matching across multiple fields.
        *   Retrieves initial results from the database.
        *   Iterates through results, calling `score_item` for each.
        *   Applies post-scoring logic: parent product preference boost (using configurable `parent_preference_margin`) and relative score filtering (using configurable `relative_score_threshold`).
        *   Returns a sorted and filtered list of relevant content items.
    *   **Scoring (`score_item`)**:
        *   The core relevance calculation engine.
        *   Safely decodes JSON for categories and tags stored in the index.
        *   Applies a series of weighted scores and conditional boosts based on keyword matches in different fields (title, content, category, tag), product type, sale status, exact name matches, query structure, etc.
        *   Applies special boost to products with titles that exactly match or start with a compound product name from settings.
        *   Uses numerous helper methods (`_score_field_matches`, `_score_term_matches`, `_calculate_*_boost`, `_calculate_*_weight`) for modularity.
        *   Most weights and boosts are configurable via static properties loaded from WordPress options (`apply_admin_settings`).
    *   **Settings (`apply_admin_settings`)**: 
        * Loads weights, boosts, and filtering thresholds from `wp_options` into the class's static properties at runtime.
        * Includes loading compound product names which are stored as a newline-separated list.

4.  **`WCAC_API_Handler` (`includes/class-wcac-api-handler.php`)**:
    *   **Prompt Construction (`Wcac_Public::build_system_prompt`)**: Although located in `Wcac_Public`, this method assembles the final prompt sent to the LLM, including base instructions, site-specific instructions (from settings), and the formatted context strings derived from top-scoring retrieved items (`Wcac_Public::format_context_item`).
    *   **API Communication (`send_simple_llm_message`, `send_to_llm_api`)**:
        *   Takes the constructed prompt, conversation history, and user message.
        *   Formats the request according to the OpenRouter API specs (using model and parameters from settings).
        *   Sends the request via `wp_remote_post`.
        *   Handles basic API errors (WP_Error, HTTP status, JSON decoding).
        *   Extracts the LLM's content from the response.
        *   *Note:* Currently, this is primarily used by `Wcac_Public::process_search_results` to generate only an introductory sentence.

## RAG Flow Summary (Standard Query)

1.  User sends message via frontend widget (`wcac-public.js`).
2.  AJAX request hits `admin-ajax.php` (`wcac_send_message` action).
3.  `Wcac_Public::handle_send_message_ajax` receives the request.
4.  `Wcac_ChatbotRules::extract_keywords` processes the user message, identifying and preserving compound product names.
5.  `Wcac_ChatbotRules::retrieve_relevant_content` queries the `wp_wcac_index` using `_build_search_where_clause`.
6.  Loop through DB results in `retrieve_relevant_content`:
    *   `Wcac_ChatbotRules::score_item` calculates relevance score for each item using various helper methods and configured weights/boosts.
    *   Products with titles that match compound product names receive a significant boost.
7.  `retrieve_relevant_content` applies parent-boost and relative score filtering, then sorts the results.
8.  `Wcac_Public::process_search_results` receives the filtered/sorted relevant items.
9.  `Wcac_Public::format_context_item` formats the top N items into context strings.
10. `Wcac_Public::build_system_prompt` creates the LLM system prompt with context.
11. `WCAC_API_Handler::send_simple_llm_message` sends the prompt to the LLM API to get an introductory text.
12. `Wcac_Public::process_search_results` builds a Markdown list of top products.
13. Final response (LLM intro + product list) is sent back via `wp_send_json_success`.

## Tuning & Future Considerations

*   **Relevance Tuning:** Primarily done by adjusting weights/boosts in `Wcac_ChatbotRules` via admin settings. Requires experimentation.
*   **Compound Product Names:** Add frequently searched multi-word product names to the settings to ensure they're processed as single units and receive appropriate search boosts.
*   **Performance:** The `LIKE` query in `retrieve_relevant_content` is a potential bottleneck. Alternatives like `FULLTEXT` or pre-tokenization could be explored if needed.
*   **LLM Role:** The current strategy separates LLM intro generation from product list generation. Exploring ways to have the LLM generate the full response (potentially referencing products) is a possible future direction, requiring careful prompt engineering and potentially response parsing.
*   **Keyword Extraction:** Stopword list refinement, stemming, or more advanced NLP techniques could be added. 