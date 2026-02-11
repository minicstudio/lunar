# 🛠️ Upgrade Guide

## 📋 What to do at upgrade

1. Update config files if needed
2. Add new env variables (if any)
3. Run migrations
4. Seed blog data (optional)
5. Configure blog attributes and categories

✏️ Features marked with '❗' mean breaking changes so carefully make the needed updates.

## ⬆️ From 0.1.0 → 1.0.0

Initial release of the Lunar Blog plugin.

#### ✨ Features

- Blog post management with multi-language support
- Blog categories with hierarchical organization
- Channel integration (publish blog posts to specific channels)
- Staff author association
- Blog post thumbnails with automatic cleanup
- Customizable attributes for blog posts and categories
- URL slug generation with language support
- Draft and published status management
- Filament admin panel integration

#### ⚙️ Configuration

The following configuration files are available:

```
config/lunar/blog.php               # Blog feature toggle
config/lunar/generators/url.php     # URL generation settings
```

#### 🌍 Environment Variables

```
BLOG_ENABLED=true
```

#### 💻 Commands

```
php artisan migrate                 # Run all migrations including blog tables
php artisan lunar:seed-blog         # Seed blog attributes and categories
```

#### 📦 Published Assets

You can publish the configuration files:

```bash
php artisan vendor:publish --tag="lunar.blog.config"
php artisan vendor:publish --tag="lunar.blog.migrations"
```

#### 📋 Database Tables

The following tables are created:

- `blog_categories` - Blog categories
- `blog_posts` - Blog posts
- `blog_category_blog_post` - Relationship between blog posts and categories
- `channels` (extended) - Blog post channel assignments
- `channelables` (extended) - Polymorphic channel relationships for blog content

#### 🔑 Key Features

**Multi-language Support:**

- Blog posts and categories support multiple languages
- Automatic URL slug generation per language
- Language-specific attribute translations

**Channel Publishing:**

- Control which channels each blog post is visible on
- Categories can be assigned to specific channels
- Flexible content distribution across your storefront

**Staff Authors:**

- Blog posts are authored by staff members
- Staff-to-blog post relationship tracking
- Author information in blog listings and detail views

**Attributes:**

- Blog posts and categories support custom attributes
- Out-of-the-box attributes: thumbnail, title, content, SEO fields
- Extensible attribute system for custom metadata

**URL Management:**

- Automatic slug generation from titles
- Custom URL support per language
- SEO-friendly URL structures

## ⬆️ From 1.0.0 → 1.1.0 (Current)

#### 🐞 Bug Fixes & Improvements

- ServiceProvider refactored for better code organization
- Console command structure improved
- Seeder autoload configuration in composer.json fixed
- Better separation of concerns with dedicated private methods

#### 🔧 Changes

**ServiceProvider Structure:**

```php
// Old structure: Everything in boot()
// New structure: Organized private methods
$this->loadPackageAssets();
$this->publishAssets();
$this->registerObservers();
$this->registerRelations();
$this->registerMorphMap();
$this->registerModelManifest();
$this->registerConsoleCommands();
```

**Autoload Configuration:**
The `composer.json` now includes the seeder autoload path:

```json
"Lunar\\Blog\\Database\\Seeders\\": "database/seeders"
```

**Console Command:**
The `RunLunarBlogSeederCommand` now correctly instantiates the seeder class directly.

#### 💻 Required Actions

After upgrading, run:

```bash
php artisan lunar:seed-blog
```

---

## 📚 Further Documentation

For detailed information about the Blog Plugin, features, and usage, see [BLOG_PLUGIN.md](BLOG_PLUGIN.md).

## ⚠️ Important Notes

- Blog functionality requires at least Lunar Core and Admin packages
- Ensure all migrations are run before using blog features
- Staff members must exist before assigning them as blog post authors
