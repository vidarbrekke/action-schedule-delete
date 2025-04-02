# URL to Gutenberg

A WordPress plugin that converts web content from any URL into Gutenberg blocks using a hybrid extraction system.

## Description

URL to Gutenberg provides a powerful solution for importing content from external websites into WordPress as Gutenberg blocks. It utilizes a hybrid extraction system that combines:

1. **EasyPHPArticleExtractor** for standard HTML pages
2. **Symfony Panther** for JavaScript-heavy sites (but this will only be implemented at a later stage when we have a working solution with EasyPHPArticleExtractor for standard HTML pages)

This dual approach ensures maximum compatibility with different types of websites while maintaining performance.

## Features

- Extract content from any URL with a simple interface
- Works with both standard HTML and JavaScript-rendered content
- Converts extracted content into WordPress Gutenberg blocks
- Creates draft posts with the converted content
- Configurable settings for API keys, models, and extraction parameters
- Comprehensive error handling and debugging functionality

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Composer for dependency management
- Chrome/Chromium browser (for headless browsing with Symfony Panther)

## Installation

1. Download the plugin files and place them in the `/wp-content/plugins/url-to-gutenberg/` directory.
2. Navigate to the plugin directory in your terminal:
   ```
   cd /wp-content/plugins/url-to-gutenberg/
   ```
3. Install the required dependencies using Composer:
   ```
   composer install
   ```
4. Activate the plugin through the 'Plugins' screen in WordPress.
5. Configure the plugin settings by going to 'URL to Gutenberg' > 'Settings' in the WordPress admin menu.

## Configuration

### API Settings

1. Obtain an API key from [OpenRouter](https://openrouter.ai/)
2. Enter your API key in the plugin settings page
3. Choose your preferred LLM model for content processing

### Extraction Settings

- Adjust the minimum content length for valid extraction
- Set minimum image count requirements if needed
- Enable debug mode for troubleshooting

## Usage

1. Go to 'URL to Gutenberg' in the WordPress admin menu
2. Enter the URL you want to convert
3. Click 'Convert to post'
4. The plugin will extract the content, process it with the LLM, and create a draft post
5. Edit the draft post as needed and publish when ready

## Troubleshooting

If you encounter issues with content extraction:

1. Enable debug mode in settings to get more detailed error information
2. Check if the URL is accessible from your server
3. Verify that your API key is valid and has sufficient credits

## Dependencies

The plugin relies on the following libraries (some are only requered once we are ready to implement Panther):

- [EasyPHPArticleExtractor](https://github.com/HStanleyCrow/EasyPHPArticleExtractor)
- [Symfony Panther](https://github.com/symfony/panther)
- [PHP WebDriver](https://github.com/php-webdriver/php-webdriver)
- [Symfony CSS Selector](https://github.com/symfony/css-selector)
- [Symfony Process](https://github.com/symfony/process)

## License

GPL v2 or later 