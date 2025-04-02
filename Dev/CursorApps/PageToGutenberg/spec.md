# URL-to-Gutenberg Converter WordPress Plugin PRD & Development Guide

## 1. Overview

**Purpose:**  
Develop a WordPress plugin that accepts a URL, leverages a multi-modal LLM (compatible with the OpenAI protocol) to generate both a screenshot of the page and a structured layout for replicating the page using Gutenberg blocks. The plugin will then create a new WordPress post using the generated layout. Additionally, all images from the source document (including screenshots) will be saved in the WordPress Media Library for easy reuse and reference.

**Scope:**  
- **Phase 1:**  
  - URL input functionality.
  - Content extraction using EasyPHPArticleExtractor for standard HTML pages.
  - Integration with a multi-modal LLM to:
    - Retrieve a screenshot of the page.
    - Return a structured layout for generating Gutenberg blocks.
  - Save all images (screenshots and others from the source) in the WordPress Media Library.
  - Generation of a new WordPress post that replicates the source page using Gutenberg blocks.
  - A settings screen allowing users to securely enter and manage their LLM API credentials (with guidance provided for using OpenRouter as the LLM provider).
  - Optional advanced extraction capability for JavaScript-heavy sites (requires server configuration).

- **Phase 2 (Future Enhancement):**  
  - Remove the ability for users to input their own LLM credentials.
  - Implement a billing system where users are allotted a fixed number of conversions (credits) with the ability to purchase extra credits when needed.
  - Transition to a developer-controlled LLM API key rather than using user-provided keys.

> **Note:** The focus is on perfecting Phase 1 before planning and transitioning to Phase 2.

---

## 2. Objectives & Goals

- **Automate Content Replication:**  
  Replicate the visual layout of a given webpage within WordPress by converting it into Gutenberg blocks.

- **Reliable Content Extraction:**
  Extract content from standard HTML pages using EasyPHPArticleExtractor, with an optional advanced mode for JavaScript-rendered content.

- **Leverage Multi-Modal LLM:**  
  Use a coordinated set of API calls to the multi-modal LLM to obtain both a screenshot (for visual reference) and a structured layout for generating the post.

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
   - **Primary Method - EasyPHPArticleExtractor:**
     - Extract content from standard HTML pages.
     - Parse and identify main content areas, headings, paragraphs, and images.
     - This method requires no special server configuration and works with standard PHP.
   
   - **Advanced Method (Optional) - Symfony Panther:**
     - Extract content from JavaScript-heavy sites that require browser rendering.
     - This method requires ChromeDriver/GeckoDriver to be installed on the server.
     - Will be presented as an optional feature for advanced users with appropriate server access.
     - Clear documentation on driver installation will be provided.

3. **LLM API Integration:**
   - **Request:**  
     - Submit the extracted content along with the user's LLM API credentials (entered in the settings screen) to the multi-modal LLM endpoint.
   - **Response:**  
     - Retrieve a screenshot of the page (returned as a base64 image or URL).
     - Receive a structured JSON response detailing the page layout in terms of Gutenberg blocks (e.g., headings, paragraphs, images, columns).

4. **Image Handling:**
   - Save all images received (e.g., screenshots, media assets from the source) into the WordPress Media Library.
   - Ensure that each saved image is properly registered in WordPress, so it can be referenced or reused in the generated post.

5. **Post Generation:**
   - Create a new WordPress post using the Gutenberg editor.
   - Dynamically construct Gutenberg blocks based on the structured layout returned from the LLM.
   - Optionally embed the saved screenshot (or other images) as visual references within the post.

6. **Settings Screen:**
   - A dedicated admin page where users can enter, update, and securely store their LLM API credentials.
   - **Extraction Method Configuration:**
     - Allow users to enable/disable the advanced extraction method (Symfony Panther).
     - Provide clear information about server requirements for the advanced method.
     - Include troubleshooting tips for common driver installation issues.
   - **Guidance for Using OpenRouter:**  
     - Provide instructions or tooltips on how to sign up for OpenRouter, obtain the API key, and configure endpoint details.
     - Recommend users refer to [OpenRouter's official documentation](https://openrouter.ai) for specifics on API usage, authentication, and endpoint details.
   - Validate and store the credentials using WordPress secure options (e.g., the `wp_options` table with proper sanitization).

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
    - Content extraction (EasyPHPArticleExtractor and optional Symfony Panther)
    - API integration and communication
    - Gutenberg block generation and post creation
    - Media handling for saving images

- **WordPress Hooks & Actions:**
  - Utilize `admin_menu` to create the settings page.
  - Implement REST API endpoints or AJAX for asynchronous URL processing.
  - Hook into `wp_insert_post` for creating the new post.

### Content Extraction

- **Primary Extraction (EasyPHPArticleExtractor):**
  - Used by default for all URLs
  - Requires no special server configuration
  - Optimized for standard HTML pages
  - Includes content quality assessment to identify main content areas

- **Advanced Extraction (Symfony Panther - Optional):**
  - Optional feature that can be enabled in settings
  - Requires ChromeDriver/GeckoDriver installation on the server
  - Used for JavaScript-heavy sites that require browser rendering
  - Clear error messages and fallbacks if drivers aren't available

### API Integration

- **LLM API Call:**
  - Use `wp_remote_post` for secure communication with the multi-modal LLM endpoint.
  - Configure the API call to include the extracted content and API credentials.
  - **OpenRouter Integration Guidance:**  
    - Follow OpenRouter's API documentation for setting up the correct headers, authentication tokens, and endpoint URLs.
    - Ensure error handling for issues such as authentication failures, rate limits, and timeouts.
  
- **Data Parsing & Conversion:**
  - Parse the JSON response from the LLM.
  - Map the response data to Gutenberg block syntax, creating the corresponding blocks (e.g., using WordPress block registration functions or JSON block definitions).

### Image Handling

- Use WordPress functions (such as `media_handle_upload` or `wp_insert_attachment`) to save images into the Media Library.
- Ensure that images are properly registered and available for embedding within the generated post.

### Security & Data Handling

- **Credentials:**
  - Sanitize and validate API credentials upon entry.
  - Securely store credentials using WordPress options, ensuring they are not exposed.
  
- **Error Handling:**
  - Provide clear, user-friendly error messages for failed API calls, invalid URLs, or other issues.
  - Specific error handling for advanced extraction when drivers are missing.
  - Log errors for troubleshooting and debugging purposes.

---

## 5. User Interface & UX

### Admin Settings Screen

- **Layout:**
  - Input fields for API key/credentials.
  - Extraction method configuration with clear server requirements.
  - Guidance and tooltips on how to use OpenRouter for obtaining an API key.
  - A "Save & Test Connection" button to verify the LLM API integration.

### URL Submission Page

- **Interface:**
  - Clean and simple form for entering the target URL.
  - Option to select extraction method (Standard or Advanced, if enabled).
  - Feedback messages indicating processing status (e.g., "Processing…", "Post created successfully!", error notifications).
  - Detailed information if advanced extraction fails due to missing drivers.

### Gutenberg Post Generation

- **Visual Feedback:**
  - Include saved images (e.g., the generated screenshot) in the post as either a primary visual element or embedded within the content.
  - Render content using native Gutenberg blocks to facilitate easy editing.

---

## 6. Development Milestones & Timeline

### Milestone 1: Setup & Basic Plugin Structure
- Create the initial plugin scaffold (main file and folder structure).
- Implement the settings page for API credentials and OpenRouter guidance.
- **Time Estimate:** 1–2 weeks

### Milestone 2: Content Extraction Implementation
- Implement EasyPHPArticleExtractor for standard HTML pages.
- Create the optional advanced extraction system using Symfony Panther.
- Develop configuration options and detection logic.
- **Time Estimate:** 2–3 weeks

### Milestone 3: LLM Integration & URL Submission
- Develop functionality for URL input and validation.
- Integrate with the multi-modal LLM API (with OpenRouter guidance) to process extracted content.
- **Time Estimate:** 2–3 weeks

### Milestone 4: Image Handling & Post Generation
- Implement functionality to save all images in the WordPress Media Library.
- Map the LLM response to Gutenberg block syntax and automatically generate a post.
- **Time Estimate:** 2–3 weeks

### Milestone 5: Testing & Refinement
- Conduct unit tests for extraction methods, API calls, image saving, and block generation.
- User testing for UI/UX within the WordPress admin area.
- **Time Estimate:** 1–2 weeks

### Milestone 6: Documentation & Advanced Features
- Create comprehensive documentation for both standard and advanced extraction.
- Provide detailed server configuration guides for the advanced extraction method.
- Finalize code documentation and usage instructions.
- **Time Estimate:** 1-2 weeks

---

## 7. Future Considerations (Phase 2)

- **Credential Management:**  
  Transition to a developer-controlled API key and remove user inputs for LLM credentials.

- **Extraction Improvements:**
  - Develop specialized extraction rules for common website platforms.
  - Create a system for users to contribute site-specific extraction patterns.

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

- **API Reliability:**  
  - *Risk:* LLM API downtime or latency.
  - *Mitigation:* Implement robust error handling and fallback messages.

- **Security Concerns:**  
  - *Risk:* Exposure of API credentials.
  - *Mitigation:* Use WordPress nonces, sanitize all inputs, and store credentials securely.

- **Advanced Extraction Requirements:**
  - *Risk:* Users enable advanced extraction without proper server configuration.
  - *Mitigation:* Clear warnings, detailed documentation, and graceful fallbacks to standard extraction.

- **Post Layout Accuracy:**  
  - *Risk:* Generated Gutenberg layout does not accurately match the source design.
  - *Mitigation:* Allow manual adjustments post-generation and log user feedback for continuous improvement of the LLM prompt.

---

## 9. Conclusion

This PRD outlines the blueprint for creating a WordPress plugin that converts a webpage (via URL) into a WordPress post using multi-modal LLM capabilities. The approach focuses on providing reliable content extraction through EasyPHPArticleExtractor, with an optional advanced mode using Symfony Panther for JavaScript-heavy sites.

In Phase 1, the focus is on achieving a seamless conversion, saving source images in the Media Library, and guiding users on how to use OpenRouter for their LLM needs. The advanced extraction capability will be clearly presented as optional, requiring specific server configuration. Future enhancements in Phase 2 will transition to a billing model with a developer-controlled API key and credit-based usage.

This document serves as a starting point and can be refined as development progresses and feedback is gathered from initial testing.