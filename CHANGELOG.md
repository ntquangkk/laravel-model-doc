# Changelog

## [1.1.0] - 2025-08-21
### Added
- Description column at the end of `@property` lines.
- Enum values in description column for `ENUM` columns.

### Changed
- Moved `DBType` column to third position in `@property` lines.

## [1.0.0] - 2025-07-01
### Added
- Initial release of `GenerateModelDocCommand`.
- Support for generating PHPDoc for Laravel Eloquent models.
- Automatic detection of model properties from database schema.
- Support for detecting relationships (`belongsTo`, `hasMany`, etc.) in PHPDoc.
- Sorting options for PHPDoc properties (`--sort=type`, `--sort=name`, `--sort=db`).
- Support for scanning models in default `app/Models` and custom namespaces (`--ns`).
- Dry-run mode (`--dry-run`) to preview PHPDoc output without modifying files.
- Support for module-based projects (e.g., `Modules/*/app/Models`).