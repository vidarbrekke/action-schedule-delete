=== WP Customer AI Chatbot ===
Contributors: your-wordpress-org-username
Donate link: https://example.com/donate/
Tags: chatbot, ai, customer service, woocommerce
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An AI-powered chatbot for WordPress and WooCommerce sites, leveraging OpenRouter.

== Description ==

Provides a customer-facing chatbot that can answer questions based on site content and WooCommerce products using a tunable Retrieval-Augmented Generation (RAG) pipeline and advanced context clustering.

== Features ==

* Tunable Retrieval-Augmented Generation (RAG) pipeline for accurate, context-aware answers
* Hybrid search: combines WordPress search and custom vector/keyword search
* Context compression (clustering) to deduplicate and unify results
* Multiple clustering algorithms: by title, category, fuzzy
* Automatic hyperlinking of product/page titles in chatbot responses
* Admin settings for tuning scoring, clustering, and debug logging
* Debug logs with detailed scoring breakdowns for each query
* WooCommerce product and variation support
* AJAX-based re-indexing and search for fast, dynamic responses
* Modular, extensible codebase with clear separation of concerns

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-customer-ai-chatbot` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings > Customer AI Chatbot to configure the plugin.

== Frequently Asked Questions ==

* How do I re-index my products and pages?
  - Use the "Re-index Content" button in the plugin settings (Admin > AI Chatbot > Settings). This prepares your content for the chatbot.
* How does the chatbot decide which products/pages to show?
  - It uses a scoring system based on keyword matches in titles, content, categories, tags, etc. These scoring parameters can be adjusted manually in Settings > Scoring Rules or automatically tuned using the Optimizer tool on the Test Results page.
* How do I enable debug logging?
  - Enable "Generate debug logs" in the plugin settings (Admin > AI Chatbot > Settings > Customization & Filtering). View logs on the "Debug Logs" page.
* Why are some products or pages missing from search?
  - Ensure they are published, not password-protected, and selected for indexing under Settings > Content Indexing. Also check visibility settings and negative keywords.

== Troubleshooting ==

* **AJAX errors during indexing:**
  - Check PHP memory limits, max execution time, and browser console for network errors.
  - Ensure your server allows AJAX requests and is not blocking admin-ajax.php.
* **No results or incomplete answers:**
  - Rebuild the index and check debug logs for scoring details.
* **Debug logs not appearing:**
  - Ensure debug logging is enabled in settings and your database user has permission to create tables.

== Contributing ==

We welcome contributions! Please:

* Follow PSR-12 and WordPress coding standards (see .editorconfig and phpcs.xml)
* Write clear, modular code with docblocks and inline comments
* Submit pull requests with descriptive commit messages (feature, fix, docs, style, refactor, test, chore)
* Update the changelog and README as needed
* Run tests and static analysis before submitting

== Screenshots ==

1.  TBD

== Changelog ==

= 1.1.0 =
* Added extra boost for product name matches in search queries
* Expanded README with features, troubleshooting, and contributing sections
* Improved documentation and code comments throughout the codebase
* Improved search relevance by adjusting scoring weights
* Fixed category handling to prevent duplicate category scoring
* Modified indexer to exclude parent categories from products by default
* Enhanced debug logging for product categories
* Ran PHPCBF to auto-fix code style issues (PSR-12 compliance)
* Manual audit for dead/unused code and TODOs
* Fixed issue with irrelevant products ranking higher due to duplicate category entries
* Fixed scoring system to properly handle compound product names
* Fixed many code style and formatting issues for better maintainability

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
* Major improvements to search relevance, logging, and documentation. See changelog for details.

=== New in Version 0.1.1 ===

* Added Debug Logs feature: Track and analyze search queries and scoring details through a new Debug Logs page in the admin dashboard
* Debug logs show complete breakdown of how product scores are calculated for each search query
* Visual badges indicating what factors contributed to each product's final score
* Ability to delete individual or multiple log entries 