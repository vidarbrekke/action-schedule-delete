# Changelog

## [Unreleased]
### Added
- Added extra boost for product name matches in search queries
- Expanded README with features, troubleshooting, and contributing sections
- Improved documentation and code comments throughout the codebase

### Changed
- Improved search relevance by adjusting scoring weights:
  - Increased Direct Title Match Bonus from 20.0 to 100.0
  - Reduced Category Match Weight from 50.0 to 25.0
  - Increased Exact Product Name Boost from 100.0 to 200.0
- Fixed category handling to prevent duplicate category scoring
- Modified indexer to exclude parent categories from products by default to prevent category duplication
- Enhanced debug logging for product categories
- Ran PHPCBF to auto-fix thousands of code style issues (PSR-12 compliance)
- Manual audit for dead/unused code and TODOs

### Fixed
- Fixed issue with irrelevant products ranking higher due to duplicate category entries
- Fixed scoring system to properly handle compound product names
- Fixed many code style and formatting issues for better maintainability

## [1.0.0] - 2023-12-01
### Added
- Initial release 