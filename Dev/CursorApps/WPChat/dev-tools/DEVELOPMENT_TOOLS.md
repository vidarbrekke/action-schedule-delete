# WP Customer AI Chatbot Development Tools

This directory contains tools and utilities to aid in the development, testing, and deployment of the WP Customer AI Chatbot plugin.

## Directory Structure

- **[deployment/](deployment/)** - Deployment scripts and tools
  - [deploy-staging.sh](deployment/deploy-staging.sh) - Script to deploy the plugin to the staging server
  - [package-plugin.sh](deployment/package-plugin.sh) - Script to create a distribution package of the plugin
  - [restart.sh](deployment/restart.sh) - Script to restart services on the server
  - [release-process.md](deployment/release-process.md) - Checklist and process for releasing a new version

- **[testing/](testing/)** - Test utilities
  - *(Currently contains setup for integration tests - see `testing/README.md`)*
  - Contains the [Optimizer](testing/optimizer.md) tool description and usage guide.

- **[utilities/](utilities/)** - Miscellaneous development utilities
  - [commit-guide.md](utilities/commit-guide.md) - Guide on Git commit practices and conventions.

- **[optimizer_parameters.md](optimizer_parameters.md)** - Detailed documentation of all tunable RAG parameters used by the optimizer and scoring engine, including:
  - Core scoring weights (title, content, category matches)
  - Boost signals (recency, rating, menu presence)
  - Penalty factors (out-of-stock, negative keywords)
  - Context compression settings
  - Product type handling (parent/variation relationships)
  - Advanced features (compound names, boost/devalue terms)

## How to Use These Tools

### Deployment

1. To deploy changes to the staging server, use:
   ```
   ./dev-tools/deployment/deploy-staging.sh
   ```
   Run this from the project root directory.

2. To create a distribution package for production release, use:
   ```
   ./dev-tools/deployment/package-plugin.sh
   ```
   Run this from the project root directory.

3. Before releasing, consult the [deployment/release-process.md](deployment/release-process.md) checklist to ensure all required steps are completed.

### Development Practices

- **Committing Code:** Follow the guidelines outlined in [utilities/commit-guide.md](utilities/commit-guide.md).
- **Understanding Parameters:** For a comprehensive understanding of all tunable RAG parameters, consult [optimizer_parameters.md](optimizer_parameters.md). This is the authoritative source for:
  - All scoring parameter definitions and their impact
  - Context compression features and algorithms
  - Product type support and relationship handling
  - Boost signals (rating, recency, menu presence)
  - Penalty factors (out-of-stock, negative keywords)

### Testing

- Consult the `testing/` directory and its `README.md` for information on running tests and using testing utilities.
- The [Optimizer](testing/optimizer.md) tool helps tune scoring parameters automatically based on test cases.
- For optimizer troubleshooting and best practices, see the "Tips" section in [optimizer.md](testing/optimizer.md).

### Other Notes

- All deployment scripts should be run from the project root directory (not the plugin directory)
- Remember to make deployment scripts executable before running: `chmod +x script.sh`
- Check each script for required parameters or environment setup

### Product Type Support

The plugin supports all WooCommerce product types, including:
- Simple products
- Variable products and variations
- Grouped products
- Custom product types
See the "Product Type Support" section in [optimizer_parameters.md](optimizer_parameters.md) for details and extension instructions.

### Scoring Features

The plugin includes several advanced scoring features, all configurable in the admin UI under Scoring Rules:

- **Rating Weight Boost:** Boosts products based on their average rating (0-5 stars)
- **Recency Boost:** Prioritizes recently modified content with linear decay over a fixed 30-day window
- **Out-of-Stock Penalty:** Optional score reduction for out-of-stock items
- **Menu Presence Boost:** Higher scores for items present in site navigation
- **Context Compression:** Multiple algorithms for optimizing context sent to LLM

See [optimizer_parameters.md](optimizer_parameters.md) for detailed documentation of all parameters and their effects. 