# 🛠️ Upgrade Guide

## 📋 What to do at upgrade

1. Update config files if needed
2. Add new env variables (if any)
3. Run migrations
4. Seed review data (optional)
5. Configure review attributes

✏️ Features marked with '❗' mean breaking changes so carefully make the needed updates.

## ⬆️ From 0.1.0 → 1.0.0

Initial release of the Lunar Review plugin.

#### ✨ Features

- Customer review submission with ratings and images
- Product variant and channel review support
- Multi-language support for review content
- Approval workflow for review moderation
- Order integration for verified purchase reviews
- User association tracking
- Customizable attributes for reviews
- Image upload support with Spatie Media Library
- Rating calculation and aggregation
- Soft delete support for reviews
- Filament admin panel integration

#### ⚙️ Configuration

The following configuration file is available:

```
config/lunar/review.php               # Review feature configuration
```

#### 🌍 Environment Variables

```
REVIEW_UPLOAD_DISK=s3
REVIEW_MAX_FILES=15
```

#### 💻 Commands

```
php artisan migrate                 # Run all migrations including reviews table
php artisan lunar:seed-review       # Seed review attributes
```

#### 📦 Published Assets

You can publish the configuration files:

```bash
php artisan vendor:publish --tag="lunar.review.config"
php artisan vendor:publish --tag="lunar.review.migrations"
```

#### 📋 Database Tables

The following table is created:

- `reviews` - Customer reviews with polymorphic reviewable support

#### 🔑 Key Features

**Multi-language Support:**

- Review content supports multiple languages
- Language-specific attribute translations
- Translatable title and content fields

**Approval Workflow:**

- Reviews start as pending (approved_at = null)
- Staff can approve reviews via admin panel
- Soft delete support for inappropriate content

**Reviewable Types:**

- Product Variant reviews (customer product feedback)
- Channel reviews (overall store/platform feedback)
- Extensible to support custom reviewable types

**Image Uploads:**

- Multiple image support (up to 15 by default)
- Spatie Media Library integration
- Custom path generator for file organization
- Configurable media conversions

**Rating System:**

- Star ratings (1-5) stored in attributes
- Average rating calculation per reviewable
- Total review count tracking
- Only approved reviews included in calculations

**Order Integration:**

- Link reviews to verified purchases
- Order relationship tracking
- Supports verified purchase badges

**Attributes:**

- Reviews support custom attributes
- Out-of-the-box attributes: rating, title, content, name, email
- Extensible attribute system for custom metadata

## ⬆️ From 1.0.0 → 1.1.0 (Current)

#### 🐞 Bug Fixes & Improvements

- ServiceProvider refactored for better code organization
- Console command structure improved
- Seeder autoload configuration in composer.json fixed
- Better separation of concerns with dedicated private methods
- Policy implementation for review management
- Event system for review lifecycle actions
- Mixin system for adding review capabilities to models

#### 🔧 Changes

**ServiceProvider Structure:**

```php
// Old structure: Everything in boot()
// New structure: Organized private methods
$this->registerConsoleCommands();
$this->registerModelManifest();
$this->registerAttributeManifest();
$this->loadPackageAssets();
$this->publishAssets();
$this->registerRelations();
$this->registerModelMixins();
$this->registerMediaDefinitions();
$this->registerPathGenerators();
$this->registerStateListeners();
$this->registerMorphMap();
```

**Autoload Configuration:**
The `composer.json` now includes proper autoload paths:

```json
"autoload": {
    "psr-4": {
        "Lunar\\Review\\": "src",
        "Lunar\\Review\\Database\\Factories\\": "database/factories",
        "Lunar\\Review\\Database\\Seeders\\": "database/seeders",
        "Lunar\\Review\\Database\\State\\": "database/state"
    }
}
```

**Model Mixins:**

ProductVariant and Channel now have review capabilities via mixins:

```php
// ProductVariant
$variant->reviews(); // Get all reviews
$variant->getRatingAverage(); // Get average rating
$variant->getTotalReviews(); // Get total count
$variant->getName(); // Get display name

// Channel
$channel->reviews(); // Get all reviews
$channel->getRatingAverage(); // Get average rating
$channel->getTotalReviews(); // Get total count
$channel->getName(); // Get display name
```

**Event System:**

Three events are now dispatched:

```php
use Lunar\Review\Events\ReviewCreatedEvent;
use Lunar\Review\Events\ReviewUpdatedEvent;
use Lunar\Review\Events\ReviewDeletedEvent;

// Listen for review events
Event::listen(ReviewCreatedEvent::class, function ($event) {
    // Handle new review
});
```

**Policy Implementation:**

Reviews now have a comprehensive policy:

```php
// Required permission: sales:reviews:manage
- viewAny() - View review listings
- view() - View individual reviews
- create() - Create new reviews
- update() - Update existing reviews
- delete() - Soft delete reviews
- restore() - Restore deleted reviews
- forceDelete() - Permanently delete reviews
```

**Console Command:**
The `RunReviewSeederCommand` now correctly instantiates the seeder class:

```php
$this->call('db:seed', ['--class' => ReviewAttributeSeeder::class]);
```

**Scopes Added:**

New query scopes for filtering reviews:

```php
Review::approved()->get(); // Only approved reviews
Review::forProductVariant($id)->get(); // Product variant reviews
Review::forChannel($id)->get(); // Channel reviews
```

#### 💻 Required Actions

After upgrading, run:

```bash
# Publish config
php artisan vendor:publish --tag="lunar.review.config"

# Run migrations
php artisan migrate

# Seed review attributes
php artisan lunar:seed-review
```

#### ⚠️ Breaking Changes

None. This is a maintenance release with improvements and additions.

---

## 📚 Further Documentation

For detailed information about the Review Plugin, features, and usage, see [REVIEW_PLUGIN.md](REVIEW_PLUGIN.md).

## ⚠️ Important Notes

- Review functionality requires at least Lunar Core and Admin packages
- Ensure all migrations are run before using review features
- Configure your storage disk before enabling image uploads
- Set up the review permission (`sales:reviews:manage`) for staff members
- The approval workflow is enabled by default - reviews must be approved before being publicly visible
- Images are stored using Spatie Media Library - ensure it's properly configured
