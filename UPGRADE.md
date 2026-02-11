# Upgrade Guide

This document outlines the key changes and new features in the Admin and Core packages.

## Admin Package

### Field Types Enhancements

#### File Field Type

- **Storage Disk Configuration**: Added ability to configure which storage disk to use for file uploads (`disk` option)
- **Directory Configuration**: Added ability to specify upload directory (`directory` option)
- Translations added for new configuration options in all supported languages

#### TranslatedText Field Type

- **Disable Richtext Toolbar**: New option to disable the richtext editor toolbar while keeping richtext functionality (`disable_richtext_toolbar`)
- Useful for fields that need simple multi-line text with limited formatting

## Core Package

### Field Types Configuration

#### File Field Type

- Configuration schema updated to support `disk` and `directory` options
- Schema validation added for new string-type configuration fields

#### TranslatedText Field Type

- Configuration schema updated to support `disable_richtext_toolbar` option
- Allows fine-grained control over editor behavior

### URL Generator

Major improvements to the URL generator system:

- **Multi-language Support**: URL generation now supports multiple languages with per-language slug generation
- **Configurable Attributes**: New configuration file `config/generators/url.php` allows customization of which model attributes are used for URL generation
- **Language Context**: Added `setLanguage()` and `getLanguage()` methods for language-specific URL handling
- **Attribute-based Generation**: New `generateUrlsForAttribute()` method generates URLs for all configured languages
- **Model Type Configuration**: URL generation can be customized per model type via configuration

### Configuration

- **New Config File**: `config/generators/url.php` for URL generator settings
    - Define priority attributes for URL generation per model type
    - Fallback to default configuration if model type not specified

---

**Note**: These changes are backward compatible with existing implementations. No breaking changes introduced.
