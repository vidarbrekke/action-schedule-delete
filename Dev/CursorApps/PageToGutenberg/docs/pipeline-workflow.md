# URL to Gutenberg - Pipeline Workflow

This document outlines the complete workflow of the URL to Gutenberg plugin, from URL submission to WordPress post creation.

## Overview

The URL to Gutenberg plugin processes external web content into WordPress Gutenberg blocks through a series of steps:

1. **URL Submission**: The user provides a URL to process
2. **HTML Extraction**: The plugin retrieves and extracts content from the URL
3. **LLM Processing**: The extracted content is processed by an LLM (Language Learning Model) to generate Gutenberg blocks
4. **JSON Parsing**: The LLM output is parsed into structured WordPress blocks
5. **Image Processing**: Images from the source content are downloaded and added to the media library
6. **Post Creation**: A WordPress post is created with the Gutenberg blocks

## Detailed Workflow

### 1. URL Submission

Users can submit URLs through:
- The admin interface at "URL to Gutenberg" in the WordPress admin menu
- The pipeline test interface at `/admin-test.php?test=pipeline&url=https://example.com`
- Programmatically via the Content_Pipeline class

### 2. HTML Extraction

The plugin uses a Content Extractor to:
- Fetch the HTML content from the URL
- Remove unnecessary elements (ads, navigation, etc.)
- Extract the main content, title, and images
- Clean the HTML structure for optimal processing

### 3. LLM Processing

The HTML content is sent to an LLM service (via OpenRouter.ai) with:
- Temperature setting of 0.1 (reduced from 0.2) for more deterministic outputs
- Enhanced anti-truncation instructions to ensure complete content processing
- Specific formatting rules for Gutenberg block generation
- Domain-specific instructions for certain types of websites

Recent optimizations include:
- Lowering temperature from 0.2 to 0.1 for more consistent outputs
- Enhanced prompting to prevent truncation issues
- Improved handling of complex content with multiple nested blocks

### 4. JSON Parsing

The LLM output is parsed by:
- Cleaning the content to fix common issues (incomplete blocks, HTML entities)
- Ensuring all blocks have proper opening and closing tags
- Converting HTML comments to proper Gutenberg block format
- Validating JSON attributes in block definitions

### 5. Image Processing

The plugin processes images by:
- Extracting image URLs from the content
- Downloading remote images to the WordPress media library
- Updating block attributes to reference local media IDs
- Maintaining image alignment and size attributes

### 6. Post Creation

Finally, a WordPress post is created with:
- The extracted title as the post title
- The processed Gutenberg blocks as the post content
- A default status of "draft" (configurable in settings)
- Optional metadata for tracking the source URL

## Testing & Debugging

### Test Options

1. **Admin Test Interface**:
   - Access `/admin-test.php?test=pipeline&url=https://example.com`
   - Provides real-time feedback on each processing step
   - Displays execution time and results

2. **Debug Mode**:
   - Enable debug mode in plugin settings
   - Debug logs are written to `/wp-content/uploads/utg-debug/`
   - Files include raw HTML, LLM output, and processed blocks

### Common Issues

1. **JavaScript-heavy Sites**:
   - Sites with dynamic content may not extract properly
   - Future implementation will use Symfony Panther for JavaScript rendering

2. **Block Truncation**:
   - Very large content may get truncated by LLM
   - Enhanced anti-truncation instructions help mitigate this issue
   - Debug logs will show if truncation occurred

3. **Image Processing Failures**:
   - Remote images may be inaccessible or have unusual formats
   - The plugin will continue processing even if image downloads fail
   - Check debug logs for specific image processing errors

## Development Reference

### Key Classes

- `Content_Pipeline`: Orchestrates the entire workflow
- `LLM_API`: Handles communication with AI service
- `Content_Extractor`: Extracts content from URLs
- `Media_Handler`: Processes and downloads images
- `Post_Generator`: Creates WordPress posts

### Code Example

```php
// Process a URL through the complete pipeline
$pipeline = new \UTG\Content_Pipeline();
$result = $pipeline->process_url('https://example.com');

if (is_wp_error($result)) {
    echo 'Error: ' . $result->get_error_message();
} else {
    echo 'Post created with ID: ' . $result['post_id'];
    echo 'Edit URL: ' . $result['edit_url'];
}
```

## Next Steps

The development roadmap includes:
1. Integrating Symfony Panther for JavaScript-heavy sites
2. Enhancing image processing for complex layouts
3. Adding support for more Gutenberg block types
4. Implementing a caching system for faster processing
5. Adding bulk URL processing capabilities 