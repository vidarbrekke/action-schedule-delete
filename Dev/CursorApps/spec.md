# URL-to-Gutenberg Converter WordPress Plugin PRD & Development Guide

## 1. Overview

**Purpose:**  
Develop a WordPress plugin that accepts a URL, extracts its content using a hybrid extraction approach, and leverages a multi-modal LLM (compatible with the OpenAI protocol) to generate a structured layout for replicating the page using Gutenberg blocks. The plugin will then create a new WordPress post using the generated layout, saving all images from the source document in the WordPress Media Library for easy reuse and reference.

**Scope:**  
- **Phase 1:**  
  - URL input functionality.
  - Hybrid content extraction system using EasyPHPArticleExtractor and Symfony Panther.
  - Integration with a multi-modal LLM to:
    - Process the extracted content.
    - Return a structured layout for generating Gutenberg blocks.
  - Save all images from the source in the WordPress Media Library.
  - Generation of a new WordPress post that replicates the source page using Gutenberg blocks.
  - A settings screen allowing users to securely enter and manage their LLM API credentials (with guidance provided for using OpenRouter as the LLM provider).

- **Phase 2 (Future Enhancement):**  
  - Remove the ability for users to input their own LLM credentials.
  - Implement a billing system where users are allotted a fixed number of conversions (credits) with the ability to purchase extra credits when needed.
  - Transition to a developer-controlled LLM API key rather than using user-provided keys.

> **Note:** The focus is on perfecting Phase 1 before planning and transitioning to Phase 2.

---

## 2. Objectives & Goals

- **Optimize Content Extraction:**  
  Implement a hybrid approach using EasyPHPArticleExtractor for static content and Symfony Panther for JavaScript-heavy sites to ensure high-quality content extraction.

- **Automate Content Replication:**  
  Replicate the visual layout and content of a given webpage within WordPress by converting it into Gutenberg blocks.

- **Leverage Multi-Modal LLM:**  
  Use optimized API calls to the multi-modal LLM by sending only essential extracted content rather than full HTML, reducing token usage while maintaining quality.

- **User-Friendly Integration:**  
  Provide an intuitive settings screen for API credentials and a straightforward URL input process to minimize user friction.

- **Future Monetization:**  
  Lay the groundwork for a billing system that transitions to a developer-controlled LLM API key with credit-based usage in Phase 2.

---

## 3. Functional Requirements

### Phase 1

1. **URL Input Interface:**
   - Provide a simple input field (as part of a dedicated admin page or metabox) where users enter the target URL.

2. **Content Extraction System:**
   - **Hybrid Approach:**  
     - Use EasyPHPArticleExtractor for standard HTML pages (faster, more efficient).
     - Fall back to Symfony Panther for JavaScript-heavy sites that require browser rendering.
     - Implement intelligent detection to determine which extractor to use based on content characteristics.
   - **Caching System:**
     - Cache extraction results to improve performance for repeat conversions.
     - Store domain-specific extraction patterns for optimized future processing.

3. **LLM API Integration:**
   - **Request:**  
     - Submit the extracted content (not raw HTML) along with the user's LLM API credentials to the multi-modal LLM endpoint.
   - **Response:**  
     - Receive a structured JSON response detailing the page layout in terms of Gutenberg blocks (e.g., headings, paragraphs, images, columns).
   - **Format Handling:**
     - Support both `blockName` and `type` formats for compatibility.
     - Properly map attributes between different naming conventions.

4. **Image Handling:**
   - Save all images from the extraction process into the WordPress Media Library.
   - Ensure that each saved image is properly registered in WordPress, so it can be referenced or reused in the generated post.
   - Implement deduplication to avoid storing the same image multiple times.

5. **Post Generation:**
   - Create a new WordPress post using the Gutenberg editor.
   - Dynamically construct Gutenberg blocks based on the structured layout returned from the LLM.
   - Support complex layouts including nested blocks and columns.

6. **Settings Screen:**
   - A dedicated admin page where users can enter, update, and securely store their LLM API credentials.
   - **Guidance for Using OpenRouter:**  
     - Provide instructions or tooltips on how to sign up for OpenRouter, obtain the API key, and configure endpoint details.
     - Recommend users refer to [OpenRouter's official documentation](https://openrouter.ai) for specifics on API usage, authentication, and endpoint details.
   - Validate and store the credentials using WordPress secure options (e.g., the `wp_options` table with proper sanitization).
   - Configuration options for content extraction preferences.

### Phase 2 (Future)

1. **Credential Handling:**
   - Remove the user-facing API credential input.
   - Transition to using a developer-managed LLM API key (e.g., via OpenRouter).

2. **Billing Integration:**
   - Implement a credit-based system where:
     - Each conversion deducts a predefined number of credits.
     - Users can view their remaining credits within the plugin interface.
     - Integrate with a payment gateway (e.g., Stripe or PayPal) for purchasing additional credits.

3. **Usage Limits & Alerts:**
   - Enforce conversion limits based on available credits.
   - Notify users when credits are low and direct them to the purchase process.

---

## 4. Technical Requirements & Architecture

### Plugin Structure

- **Core Plugin Files:**
  - Main plugin file (e.g., `url-to-gutenberg.php`) that registers hooks and includes necessary components.
  - Separate files/folders for:
    - Admin UI (settings page)
    - Content extraction system (EasyPHPArticleExtractor and Panther integration)
    - API integration and communication
    - Gutenberg block generation and post creation
    - Media handling for saving images

- **WordPress Hooks & Actions:**
  - Utilize `admin_menu` to create the settings page.
  - Implement REST API endpoints or AJAX for asynchronous URL processing.
  - Hook into `wp_insert_post` for creating the new post.

### Content Extraction System

- **Extraction Strategy:**
  ```php
  // Example workflow
  if ($isJsHeavySite) {
      // Use Panther for JS rendering
      $client = Client::createChromeClient();
      $crawler = $client->request('GET', $url);
      $html = $crawler->html();
  } else {
      // Use EasyPHPArticleExtractor for efficiency
      $article = new ArticleExtractor($url);
      $html = $article->article();
  }

  // Shared processing
  $dom = new DOMDocument();
  $dom->loadHTML($html);
  // Additional parsing logic...
  ```

- **Scenario-Based Approach:**

  | Scenario | EasyPHPArticleExtractor | Panther |
  |----------|--------------------------|---------|
  | Non-JS pages | Faster, optimized for article/content extraction | Slower due to full browser emulation |
  | JS-heavy pages | Limited (cannot execute JS) | Essential (renders dynamic content) |
  | Image extraction | Built-in methods for images/embedded media | Requires manual DOM traversal |
  | Ad/boilerplate removal | Automatically strips non-content elements | Requires custom filtering logic |

- **Detection Mechanism:**
  - Analyze content-to-code ratio to identify JavaScript-heavy pages.
  - Maintain a domain-based cache to remember which sites required Panther previously.
  - Implement a fallback system that escalates to Panther if EasyPHPArticleExtractor yields insufficient content.

### API Integration

- **LLM API Call:**
  - Use `wp_remote_post` for secure communication with the LLM endpoint.
  - Configure the API call to include the extracted content and API credentials.
  - **OpenRouter Integration Guidance:**  
    - Follow OpenRouter's API documentation for setting up the correct headers, authentication tokens, and endpoint URLs.
    - Ensure error handling for issues such as authentication failures, rate limits, and timeouts.
  
- **Data Parsing & Conversion:**
  - Parse the JSON response from the LLM.
  - Map the response data to Gutenberg block syntax, creating the corresponding blocks.
  - Support both `blockName` and `type` formats for compatibility.

### Image Handling

- Use WordPress functions (such as `media_handle_upload` or `wp_insert_attachment`) to save images into the Media Library.
- Implement caching and deduplication to avoid redundant image storage.
- Ensure that images are properly registered and available for embedding within the generated post.

### Security & Data Handling

- **Credentials:**
  - Sanitize and validate API credentials upon entry.
  - Securely store credentials using WordPress options, ensuring they are not exposed.
  
- **Error Handling:**
  - Provide clear, user-friendly error messages for failed API calls, invalid URLs, or other issues.
  - Log errors for troubleshooting and debugging purposes.

---

## 5. User Interface & UX

### Admin Settings Screen

- **Layout:**
  - Input fields for API key/credentials.
  - Configuration options for content extraction preferences.
  - Guidance and tooltips on how to use OpenRouter for obtaining an API key.
  - A "Save & Test Connection" button to verify the LLM API integration.

### URL Submission Page

- **Interface:**
  - Clean and simple form for entering the target URL.
  - Feedback messages indicating processing status (e.g., "Processing…", "Post created successfully!", error notifications).
  - Display of extraction method used (EasyPHPArticleExtractor or Panther) for transparency.

### Gutenberg Post Generation

- **Visual Feedback:**
  - Include saved images from the source in the post.
  - Render content using native Gutenberg blocks to facilitate easy editing.
  - Support complex layouts including nested blocks and columns.

---

## 6. Development Milestones & Timeline

### Milestone 1: Setup & Basic Plugin Structure ✓
- Create the initial plugin scaffold (main file and folder structure).
- Implement the settings page for API credentials and OpenRouter guidance.

### Milestone 2: Content Extraction System
- Integrate EasyPHPArticleExtractor for efficient HTML parsing.
- Integrate Symfony Panther for JavaScript-heavy sites.
- Implement intelligent detection between the two extraction methods.
- Develop caching system for extraction results.
- **Time Estimate:** 2–3 weeks

### Milestone 3: LLM Integration & URL Submission
- Develop functionality for URL input and validation.
- Integrate with the LLM API to process extracted content and generate Gutenberg blocks.
- Optimize token usage by sending only essential content.
- **Time Estimate:** 2–3 weeks

### Milestone 4: Image Handling & Post Generation
- Implement functionality to save all images in the WordPress Media Library.
- Map the LLM response to Gutenberg block syntax and automatically generate a post.
- Support complex layouts including nested blocks and columns.
- **Time Estimate:** 2–3 weeks

### Milestone 5: Testing & Refinement
- Conduct unit tests for content extraction, API calls, image saving, and block generation.
- User testing for UI/UX within the WordPress admin area.
- **Time Estimate:** 1–2 weeks

### Milestone 6: Documentation & Code Review
- Finalize code documentation and usage instructions.
- Prepare for Phase 2 by outlining necessary changes.
- **Time Estimate:** 1 week

---

## 7. Future Considerations (Phase 2)

- **Credential Management:**  
  Transition to a developer-controlled API key and remove user inputs for LLM credentials.

- **Billing System:**  
  Develop a secure billing system that:
  - Tracks and deducts credits per conversion.
  - Integrates with popular payment gateways for credit purchases.

- **Usage Monitoring:**  
  Build an admin dashboard to display conversion statistics and alert users when credits are low.

- **Scalability & Security Enhancements:**  
  Optimize API call handling and implement rate limiting to prevent abuse.

---

## 8. Risks & Mitigation Strategies

- **Content Extraction Challenges:**
  - *Risk:* Failure to extract meaningful content from complex or heavily JavaScript-dependent sites.
  - *Mitigation:* Implement a robust fallback system between EasyPHPArticleExtractor and Panther, with domain-specific optimizations for common sites.

- **API Reliability:**  
  - *Risk:* LLM API downtime or latency.
  - *Mitigation:* Implement robust error handling and fallback messages.

- **Security Concerns:**  
  - *Risk:* Exposure of API credentials.
  - *Mitigation:* Use WordPress nonces, sanitize all inputs, and store credentials securely.

- **Post Layout Accuracy:**  
  - *Risk:* Generated Gutenberg layout does not accurately match the source design.
  - *Mitigation:* Allow manual adjustments post-generation and log user feedback for continuous improvement of the LLM prompt.

---

## 9. Conclusion

This PRD outlines the blueprint for creating a WordPress plugin that converts a webpage (via URL) into a WordPress post using a hybrid content extraction approach and multi-modal LLM capabilities. The updated approach addresses key challenges in content extraction by leveraging EasyPHPArticleExtractor for static sites and Symfony Panther for JavaScript-heavy sites, optimizing both performance and extraction quality.

Progress has been made in establishing the plugin foundation, implementing the core LLM integration, and handling basic block format conversion. The next phase focuses on implementing the hybrid extraction system, optimizing token usage, and enhancing the overall robustness of the solution.

This document serves as an evolving guide and will continue to be refined as development progresses and feedback is gathered from ongoing testing. 