# ⭐ Review Plugin

The Lunar Review plugin provides a comprehensive review management system for your e-commerce platform, fully integrated with the Lunar admin panel. It allows customers to submit reviews with ratings and images for products and channels, with approval workflows and multi-language support.

## Overview

The Review plugin extends your application with a complete review module that seamlessly integrates with Lunar's core features:

- **Product and Channel Reviews** - Customers can review product variants and overall sales channels
- **Rating System** - Star ratings with average rating calculations
- **Image Uploads** - Customers can attach multiple images to reviews
- **Approval Workflow** - Staff can approve or reject reviews before publication
- **Multi-language Support** - Review content supports all enabled languages
- **Order Integration** - Link reviews to verified purchases
- **User Association** - Track which customers wrote which reviews
- **Customizable Attributes** - Add custom fields to reviews
- **Admin Panel Integration** - Full Filament UI for managing reviews
- **Media Management** - Image upload support with configurable storage

## Installation

### 1. Enable the plugin

The Review plugin is included in the Lunar core package. Add the plugin to your Filament panel configuration:

```php
// In your admin panel configuration (e.g., app/Filament/AdminPanelProvider.php)

use Lunar\Review\ReviewPlugin;

public function register(Panel $panel): Panel
{
    return $panel
        ->plugins([
            ReviewPlugin::make(),
        ]);
}
```

### 2. Publish and run migrations

```bash
# Publish config files
php artisan vendor:publish --tag="lunar.review.config"
php artisan vendor:publish --tag="lunar.review.migrations" # not required, migrations are auto-discovered

# Run migrations
php artisan migrate
```

### 3. Seed initial data (optional)

Seed the database with default review attribute groups and attributes:

```bash
php artisan lunar:seed-review
```

### 4. Configure environment variables

Set the following environment variables:

```env
# Media upload settings
REVIEW_UPLOAD_DISK=s3
REVIEW_MAX_FILES=15

# Review reminder email settings
ORDER_STATUS_FOR_REVIEW_REMINDER=completed
FIRST_REMINDER_DELAY_MINUTES=21600  # 15 days in minutes
SECOND_REMINDER_DELAY_MINUTES=43200 # 30 days in minutes
```

Or configure them directly in `config/lunar/review.php`.

### 5. Set up review reminder emails (optional)

To automatically send review request emails to customers:

1. Create a custom mailer class:

```php
namespace App\Mail;

use Illuminate\Mail\Mailable;
use Lunar\Models\Order;

class ReviewReminderMail extends Mailable
{
    public function __construct(public Order $order)
    {
    }

    public function build()
    {
        return $this->view('emails.review-reminder')
            ->subject('How was your order?');
    }
}
```

2. Configure the mailer in `config/lunar/review.php`:

```php
'review_reminder_mailer' => \App\Mail\ReviewReminderMail::class,
```

3. Schedule the command in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('review:request-email')->everyMinute();
}
```

## Features

### Reviews

Reviews are the main content items in your review system. Each review includes:

#### Core Properties

- **User** - The customer who submitted the review
- **Order** - Optional link to the order where the product was purchased
- **Reviewable** - The entity being reviewed (Product Variant or Channel)
- **Rating** - Star rating (stored in attributes)
- **Title** - Review title (stored in attributes)
- **Anonym** - Support for guest reviews without user association
- **Full Name** - Full name of the reviewer (stored in attributes)
- **Comment** - Review text content (stored in attributes)
- **Images** - Multiple image uploads (up to 15 by default)
- **Approved At** - Timestamp when review was approved (null = pending)
- **Soft Deletes** - Reviews are soft deleted for data retention

#### Reviewable Types

The Review plugin supports two types of reviewables out of the box:

**Product Variant Reviews:**
- Customers review specific product variants
- Includes average rating calculation
- Total review count tracking
- Filtered by approval status

**Channel Reviews:**
- Customers review the overall shopping experience on a channel
- Store/platform-wide feedback
- Rating aggregation per channel

You can extend the system to support additional reviewable types by adding them to the configuration.

#### Attributes

Reviews support custom attributes through the flexible attribute system:

**Default Attributes:**

- `rating` - Star rating (number, 1-5)
- `title` - Review headline (text)
- `comment` - Full review text (textarea)
- `full_name` - Reviewer name (text, for guest reviews)
- `anonym` - Support for guest reviews without user association

You can extend the attribute system by creating custom attributes in the admin panel.

#### Managing Reviews

In the Filament admin panel, navigate to **Sales > Reviews** to:

- View all reviews in a centralized list
- Filter by approval status (approved/pending)
- Filter by reviewable type (Product Variant / Channel)
- Filter by rating (1-5 stars)
- Navigate to the associated order for detailed review management

**To manage individual reviews** (approve, edit, delete), click on a review to navigate to the **Order** page where the review was submitted. On the Order page, you can:

- Approve or reject pending reviews
- Edit review content and attributes
- View and manage attached images
- Soft delete inappropriate reviews

This design ensures that reviews are always managed in the context of their associated orders, providing full order and customer context when moderating review content.

### Multi-Language Support

All review content (titles, review text) supports full multi-language management through the attribute system:

```php
// Access review in different languages
$review = Review::find(1);

// Get title in current language
$title = $review->attr('title');

// Set translatable fields
$review->fill([
    'attribute_data' => [
        'title' => [
            'en' => 'Great Product!',
            'hu' => 'Nagyszerű Termék!',
            'ro' => 'Produs Excelent!',
        ],
        'comment' => [
            'en' => 'I love this product...',
            'hu' => 'Imádom ezt a terméket...',
            'ro' => 'Îmi place acest produs...',
        ],
    ],
])->save();
```

### Image Management

Reviews support multiple image uploads with configurable storage:

```php
// Upload images to a review
$review->addMedia($file)->toMediaCollection('reviews');

// Get all review images
$images = $review->getMedia('reviews');

// Configure max files in config/lunar/review.php
'max_files' => env('REVIEW_MAX_FILES', 15),

// Configure storage disk
'upload_disk' => env('REVIEW_UPLOAD_DISK', 's3'),
```

The plugin uses Spatie Media Library for handling image uploads, with a custom path generator for organizing files.

### Approval Workflow

Reviews require approval before being publicly visible:

```php
// Query only approved reviews
$approved = Review::approved()->get();

// Approve a review
$review->update(['approved_at' => now()]);

// Check if review is approved
if ($review->approved_at) {
    // Show to public
}

// Get pending reviews
$pending = Review::whereNull('approved_at')->get();
```

### Rating System

The plugin includes built-in rating calculations for reviewable models:

```php
// Get average rating for a product variant
$productVariant = ProductVariant::find(1);
$averageRating = $productVariant->getRatingAverage(); // Returns float (0.0 - 5.0)

// Get total review count
$totalReviews = $productVariant->getTotalReviews(); // Returns int

// Get all reviews for a product
$reviews = $productVariant->reviews()->approved()->get();
```

### Order Integration

Reviews can be linked to verified purchases:

```php
// Create a review linked to an order
$review = Review::create([
    'user_id' => $user->id,
    'order_id' => $order->id,
    'reviewable_type' => ProductVariant::morphName(),
    'reviewable_id' => $variantId,
    'attribute_data' => [
        'rating' => 5,
        'title' => 'Excellent!',
        'content' => 'Great product, fast delivery',
    ],
]);

// Get all reviews for an order
$orderReviews = $order->reviews;

// Verify purchase before allowing review
if ($user->orders()->where('id', $orderId)->exists()) {
    // Allow review submission
}
```

## Database Schema

### Tables

#### `reviews`

```
- id (primary key)
- order_id (foreign key, nullable) - References orders.id
- user_id (foreign key, nullable) - References users.id
- reviewable_id (unsigned big integer, nullable) - Polymorphic relation
- reviewable_type (string, nullable) - Polymorphic type
- attribute_data (JSON) - Stores all review attributes
- approved_at (timestamp, nullable) - Approval timestamp
- created_at
- updated_at
- deleted_at (soft deletes)
```

## Models

### Review Model

**Available Methods:**

- `user()` - BelongsTo relationship with User
- `order()` - BelongsTo relationship with Order
- `reviewable()` - MorphTo polymorphic relationship
- `scopeApproved($query)` - Query scope for approved reviews
- `scopeForProductVariant($query, ?int $id)` - Query scope for product variant reviews
- `scopeForChannel($query, ?int $id)` - Query scope for channel reviews

### HasReviews Trait

Use this trait on any model that should be reviewable:

```php
use Lunar\Review\Traits\HasReviews;

class CustomModel extends Model
{
    use HasReviews;
}

// The trait provides these methods:
$model->reviews(); // Get all reviews
$model->getRatingAverage(); // Get average rating
$model->getTotalReviews(); // Get total review count
$model->getName(); // Get translated name for display
```

**Built-in Reviewable Models:**

The plugin includes mixins for:
- `ProductVariant` - Product reviews
- `Channel` - Channel/store reviews

These mixins are automatically applied via the service provider.

## Console Commands

### Seed Review Attributes

```bash
php artisan lunar:seed-review
```

Seeds the database with default review attribute groups and attributes:

- Review details attributes (rating, title, comment)
- Guest reviewer information (full_name)
- Any custom attributes defined in seed data

Run this command after installation to set up the default review structure.

### Send Review Request Emails

```bash
php artisan review:request-email
```

Sends automated review reminder emails to customers based on configured delays and order status.

**How it works:**

- Finds orders in the configured status (default: 'completed')
- Sends first reminder after configured delay (default: 15 days)
- Sends second reminder after configured delay (default: 30 days) only if no review was submitted
- Staggers email delivery with 3-second intervals to prevent spam
- Sends to user email or billing address email

**Configuration:**

Before using this command, you must:

1. Create a custom mailer class that implements `Mailable` and accepts an `Order` parameter
2. Configure the mailer in `config/lunar/review.php`:

```php
'review_reminder_mailer' => \App\Mail\ReviewReminderMail::class,
```

3. Set environment variables:

```env
ORDER_STATUS_FOR_REVIEW_REMINDER=completed
FIRST_REMINDER_DELAY_MINUTES=21600  # 15 days
SECOND_REMINDER_DELAY_MINUTES=43200 # 30 days
```

**Scheduling:**

Add this command to your task scheduler in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('review:request-email')->everyMinute();
}
```

If no mailer is configured, the command will skip execution with an informational message.

## Configuration

**Settings:**

- `media_definitions` - Class handling media conversions and definitions
- `path_generator` - Class organizing how review images are stored
- `upload_disk` - Filesystem disk for storing images (local, s3, etc.)
- `max_files` - Maximum number of images per review
- `available_types` - Which model types can be reviewed
- `order_status_for_review_reminder` - Order status that triggers review reminder emails (default: 'completed')
- `review_reminder_mailer` - Custom mailer class for review reminders (must accept Order parameter)
- `first_reminder_delay_minutes` - Delay in minutes before sending first reminder (default: 21600 = 15 days)
- `second_reminder_delay_minutes` - Delay in minutes before sending second reminder (default: 43200 = 30 days)

## Events

The Review plugin dispatches events for key actions:

**ReviewCreatedEvent:**
- Fired when a new review is created
- Contains the Review model

**ReviewUpdatedEvent:**
- Fired when a review is updated
- Contains the Review model

**ReviewDeletedEvent:**
- Fired when a review is deleted (soft or hard delete)
- Contains the Review model

## Policies

The plugin includes a comprehensive policy for review management:

**ReviewPolicy:**

- `viewAny()` - Control who can view review listings
- `view()` - Control who can view individual reviews
- `create()` - Control who can create reviews
- `update()` - Control who can update reviews
- `delete()` - Control who can delete reviews
- `restore()` - Control who can restore soft-deleted reviews
- `forceDelete()` - Control who can permanently delete reviews

Required permission: `sales:reviews:manage`

## Troubleshooting

### Reviews not visible in admin

- Verify ReviewPlugin is registered in your Filament panel configuration
- Run migrations: `php artisan migrate`
- Check staff permissions for `sales:reviews:manage`

### Images not uploading

- Check that the configured storage disk is accessible
- Verify file permissions on the storage directory
- Ensure `max_files` configuration is correct
- Check Spatie Media Library configuration

### Approval workflow not working

- Ensure you're filtering by `approved_at` on frontend queries
- Use the `approved()` scope: `Review::approved()->get()`
- Check that staff has permission to approve reviews

### Rating calculations incorrect

- Verify that the `rating` attribute exists and contains numeric values
- Check that only approved reviews are included in calculations
- Clear any cached rating values

### Multi-language content not showing

- Verify all languages are configured in Lunar
- Ensure content is filled in for all languages
- Check that the Review's `attr()` method returns data for current locale
