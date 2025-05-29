# URL-to-Gutenberg LLM Integration

This document describes how this plugin integrates with Language Learning Models (LLMs) to convert web page content to WordPress Gutenberg blocks.

## Overview

The integration process follows these steps:

1. **Content Extraction**: The plugin extracts HTML content from URLs using the `Content_Extractor` class
2. **Content Processing**: When "HTML extraction only" is unchecked, the extracted content is sent to the LLM API
3. **Block Generation**: The LLM processes the HTML and returns structured Gutenberg blocks
4. **Post Creation**: The blocks are used to create a WordPress post

## Content Extraction Process

The `Content_Extractor` class handles extracting content from URLs:

- Uses EasyPHPArticleExtractor for initial content parsing
- Applies different cleaning levels (standard, medium, aggressive) to the HTML
- Extracts images and their metadata
- Creates a structured content object with title, content, and images

## LLM Integration

The `LLM_API` class manages communication with the LLM:

### Key Methods

- `process_page_content()`: Sends the extracted content to the LLM with specific instructions
- `set_model()`: Configures which LLM model to use
- `get_domain_specific_instructions()`: Provides domain-specific processing guidance

### Processing Flow

1. The extracted content object (containing HTML, title, and images) is passed to `process_page_content()`
2. The content is sanitized for LLM processing
3. Domain-specific instructions are generated based on the URL
4. A comprehensive prompt is created with:
   - General instructions for block conversion
   - Domain-specific instructions
   - List of extracted images with URLs and metadata
   - Content metadata (title, length, image count)
   - Specific output format requirements
   - The actual HTML content
5. The prompt is sent to the LLM API via `send_request()`
6. The JSON response is parsed and validated
7. A structured array of Gutenberg blocks is returned

## JSON Format

The LLM returns a JSON object with this structure:

```json
{
  "title": "Page Title",
  "blocks": [
    {
      "type": "core/paragraph",
      "attributes": {
        "content": "Paragraph content",
        "dropCap": false
      },
      "innerHTML": "<p>Paragraph content</p>"
    },
    {
      "type": "core/heading",
      "attributes": {
        "content": "Heading Text",
        "level": 2
      },
      "innerHTML": "<h2>Heading Text</h2>"
    },
    // Additional blocks as needed
  ]
}
```

## Domain-Specific Processing

The plugin provides specialized instructions for different types of websites:

- Email newsletters (Campaign Monitor)
- Medium articles
- Blog posts
- News articles
- Documentation pages

These instructions help the LLM better understand the content structure and produce more accurate Gutenberg blocks.

## Performance Optimizations

- Low temperature setting (0.2) for more consistent results
- JSON response extraction from code blocks if present
- Fallback processing if JSON parsing fails
- Proper sanitization of HTML content before sending to the LLM

## Debug Mode

When debug mode is enabled, the plugin logs:
- Prompt content and length
- API responses and parsing results
- Any errors encountered during processing

Debug files are saved in the WordPress uploads directory for troubleshooting. 