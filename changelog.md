# ℗ LiquidDesign/Pages - CHANGELOG

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0-beta.1]

### Added

- **BREAKING:** `Page` now uses `ShopEntity` and all unique constraints are now unique per Shop

### Changed

- **BREAKING:** PHP version 8.2 or higher is required
- **BREAKING:** `Router` now match routes by Shop
  - If no shop available, it will match routes as before
  - If shop is available, router will match routes for that shop or for all shops (null).
- `PageRepository::getPageByUrl` now accepts Shop

### Removed

### Deprecated

### Fixed