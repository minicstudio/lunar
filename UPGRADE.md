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
- **Language Context**: Added `setLanguage()` and `getLanguage()` methods for language-specific URL handling
- **Attribute-based Generation**: New `generateUrlsForAttribute()` method generates URLs for all configured languages
- **Simple Property Support**: When a model has a simple `name` property, URL is generated only for the default language
- **Attribute Support**: When a model has translatable attributes (in `attribute_data`), URLs are generated for all languages using `translateAttribute()`
---

**Note**: These changes are backward compatible with existing implementations. No breaking changes introduced.
