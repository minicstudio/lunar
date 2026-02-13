# 📝 Blog Plugin

The Lunar Blog plugin provides a comprehensive blog management system for your e-commerce platform, fully integrated with the Lunar admin panel. It allows you to create and manage blog posts and categories with multi-language support, channel targeting, and customizable attributes.

## Overview

The Blog plugin extends your application with a complete blog module that seamlessly integrates with Lunar's core features:

- **Multi-language blog posts and categories**
- **Channel-aware publishing** - Control visibility across different sales channels
- **Staff author management** - Track who created and maintains content
- **Flexible taxonomies** - Organize content with hierarchical categories
- **SEO optimization** - Built-in URL slug management and SEO fields
- **Customizable attributes** - Add custom metadata to posts and categories
- **Media management** - Thumbnail support with automatic cleanup
- **Admin panel integration** - Full Filament UI for managing blog content

## Installation

### 1. Enable the plugin

The Blog plugin is included in the Lunar core package. Add the plugin to your Filament panel configuration:

```php
// In your admin panel configuration (e.g., app/Filament/AdminPanelProvider.php)

use Lunar\Blog\BlogPlugin;

public function register(Panel $panel): Panel
{
    return $panel
        ->plugins([
            BlogPlugin::make(),
        ]);
}
```

### 2. Publish and run migrations

```bash
# Publish config files
php artisan vendor:publish --tag="lunar.blog.config"

# Run migrations
php artisan migrate
```

### 3. Seed initial data (optional)

Seed the database with default blog attribute groups and attributes:

```bash
php artisan lunar:seed-blog
```

### 4. Enable in configuration

Set the environment variable:

```env
BLOG_ENABLED=true
```

Or configure it directly in `config/lunar/blog.php`:

```php
return [
    'enabled' => true,
];
```

## Features

### Blog Posts

Blog posts are the main content items in your blog. Each blog post includes:

#### Core Properties

- **Title** - Post title (translatable across all enabled languages)
- **Content** - Rich text blog post content (translatable)
- **Thumbnail** - Featured image for the blog post
- **Author** - Staff member who created the post
- **Status** - Published or draft status
- **Categories** - One or more categories to organize posts
- **Channels** - Which sales channels the post is visible on
- **Slug** - SEO-friendly URL identifier (auto-generated or custom per language)

#### Attributes

Blog posts support custom attributes through the flexible attribute system:

**Default Attributes:**

- `title` - Post title (text)
- `short_description` - Brief excerpt (text)
- `content` - Full post content (textarea)
- `thumbnail` - Featured image (file)

**SEO Attributes:**

- `meta_title` - SEO meta title
- `meta_description` - SEO meta description
- `meta_keywords` - Searchable keywords

**Author Information:**

- `author_name` - Author display name

You can extend the attribute system by creating custom attributes in the admin panel.

#### Managing Blog Posts

In the Filament admin panel, navigate to **Blog > Posts** to:

- Create new blog posts
- Edit existing posts
- Upload thumbnails and media
- Manage post status (Draft/Published)
- Assign authors and categories
- Configure channel visibility
- Set custom URLs per language
- Add custom attributes

### Blog Categories

Organize your blog posts with categories. Categories help users navigate your blog and improve SEO.

#### Core Properties

- **Name** - Category name (translatable)
- **Status** - Active or inactive
- **Slug** - SEO-friendly URL identifier (auto-generated or custom)
- **Channels** - Which sales channels the category is visible on
- **Posts** - Blog posts in this category

#### Managing Categories

In the Filament admin panel, navigate to **Blog > Categories** to:

- Create and organize categories
- Manage category status
- Configure channel visibility
- Set custom URLs per language
- Add custom attributes

### Multi-Language Support

All blog content (titles, descriptions, content) supports full multi-language management:

```php
// Access blog post in different languages
$post = BlogPost::find(1);

// Get title in Hungarian
$title = $post->translateAttribute('title'); // Returns localized array

// Set translatable fields
$post->fill([
    'attribute_data' => [
        'title' => [
            'en' => 'Welcome to Our Blog',
            'hu' => 'Üdvözöljük Blogunkban',
            'ro' => 'Bun venit în Blogul Nostru',
        ],
        'content' => [
            'en' => 'Welcome content...',
            'hu' => 'Üdvözlő tartalom...',
            'ro' => 'Conținut de bun venit...',
        ],
    ],
])->save();
```

### Channel Management

Control which sales channels each blog post and category is visible on:

```php
// Assign blog post to channels
$post->channels()->attach([1, 2, 3]); // Attach to channel IDs 1, 2, 3

// Query posts for a specific channel
$posts = BlogPost::whereHas('channels', function ($query) {
    $query->where('channel_id', 1);
})->get();
```

### URLs and Slug Management

The Blog plugin automatically generates SEO-friendly slugs and manages URLs per language using a Blog-specific URL generator.

#### Blog-Specific URL Generation

The Blog package includes its own URL generator that differs from the Core URL generator:

**Priority Order:**

1. `title` attribute (if available in translatable attributes)
2. `name` attribute (if available in translatable attributes)

**Multi-language Support:**

When using translatable attributes (`title` or `name`), URLs are automatically generated for all configured languages:

#### Auto-generated Slugs

Slugs are automatically generated from the post/category title in all supported languages:

```
Title: "Welcome to Our Blog"
Auto-generated slug: "welcome-to-our-blog"

Translated titles automatically generate translated slugs:
- "Üdvözöljük Blogunkban" → "udvozeljunk-blogunkban"
- "Bun venit în Blogul Nostru" → "bun-venit-in-blogul-nostru"
```

#### Custom URLs

Configure custom URLs for specific languages in the admin panel's "URLs" tab.

#### Configuration

The URL generator is configured in `config/lunar/blog.php`:

```php
'urlGenerator' => \Lunar\Blog\Generators\UrlGenerator::class,
```

To use a custom URL generator, replace this with your own class that implements the URL generation logic.

### Author Management

Track who creates and maintains blog content:

```php
// Get blog post author
$post = BlogPost::find(1);
$author = $post->author; // Returns Staff instance

// Get all posts by an author
$posts = Staff::find($staffId)->blogPosts()->get();
```

### Thumbnail Management

Blog posts support featured image thumbnails with automatic cleanup:

```php
// Get the first thumbnail for a post
$thumbnail = $post->getThumbnail(); // Returns path on configured disk

// Configure the storage disk
// In config/lunar/blog.php, set the thumbnail disk in attributes
```

The BlogPostObserver automatically deletes old thumbnails when they are replaced or when a post is deleted.

### Status Management

Blog posts and categories have a status field to control publication:

- **Published** - Visible to users
- **Draft** - Only visible to authenticated staff members

```php
// Query only published posts
$published = BlogPost::where('status', 'published')->get();

// Check post status
if ($post->status === 'published') {
    // Show to users
}
```

## Database Schema

### Tables

#### `blog_posts`

```
- id (primary key)
- attribute_data (JSON) - Stores all translatable content and attributes
- status (string) - "published" or "draft"
- author_id (foreign key) - References staff.id
- created_at
- updated_at
```

#### `blog_categories`

```
- id (primary key)
- attribute_data (JSON) - Stores category names, descriptions, attributes
- status (string) - "published" or "draft"
- created_at
- updated_at
```

#### `blog_category_blog_post` (pivot table)

```
- id (primary key)
- blog_category_id (foreign key)
- blog_post_id (foreign key)
```

#### `channelables` (polymorphic)

```
- id (primary key)
- channelable_type (string) - "blog_post" or "blog_category"
- channelable_id (foreign key)
- channel_id (foreign key)
```

## Models & APIs

### BlogPost Model

The main model for blog posts with full Lunar trait integration:

```php
use Lunar\Blog\Models\BlogPost;

// Create a blog post
$post = BlogPost::create([
    'status' => 'published',
    'author_id' => 1,
    'attribute_data' => [
        'title' => ['en' => 'Post Title', 'hu' => 'Poszt Címe'],
        'content' => ['en' => 'Post content...', 'hu' => 'Poszt tartalma...'],
        'thumbnail' => ['en' => 'path/to/thumbnail.jpg'],
    ],
]);

// Access relationships
$post->author; // Get author
$post->blogCategories; // Get categories
$post->channels; // Get channels

// Access attributes
$post->translateAttribute('title'); // Get translatable title
$post->getThumbnail(); // Get thumbnail path
```

**Available Methods:**

- `author()` - BelongsTo relationship with Staff
- `blogCategories()` - BelongsToMany relationship with BlogCategory
- `getThumbnail()` - Get the first thumbnail path
- `thumbnailDisk()` - Get the configured disk for thumbnails
- `translateAttribute($attribute)` - Get translatable attributes

### BlogCategory Model

The category model for organizing blog posts:

```php
use Lunar\Blog\Models\BlogCategory;

// Create a category
$category = BlogCategory::create([
    'status' => 'published',
    'attribute_data' => [
        'name' => ['en' => 'Technology', 'hu' => 'Technológia'],
    ],
]);

// Access relationships
$category->blogPosts; // Get posts in this category
$category->channels; // Get channels

// Generate slug
$slug = $category->getBlogCategorySlug($language);
```

**Available Methods:**

- `blogPosts()` - BelongsToMany relationship with BlogPost
- `getBlogCategorySlug($language)` - Generate slug for a language

## Console Commands

### Seed Blog Attributes

```bash
php artisan lunar:seed-blog
```

Seeds the database with default blog attribute groups and attributes:

- Details attributes (title, content, short description)
- Author information attributes
- SEO attributes (meta title, description, keywords)

Run this command after installation to set up the default blog structure.

## Configuration

### config/lunar/blog.php

```php
return [
    'enabled' => env('BLOG_ENABLED', false),
    
    /*
    |--------------------------------------------------------------------------
    | URL Generator
    |--------------------------------------------------------------------------
    |
    | Here you can specify a class to automatically generate URLs for blog
    | models which implement the `HasUrls` trait. If left null no generation
    | will happen. The Blog package provides a custom URL generator that
    | tries the 'title' attribute first, then falls back to 'name'.
    |
    */
    'urlGenerator' => \Lunar\Blog\Generators\UrlGenerator::class,
];
```

**Settings:**

- `enabled` - Enable/disable the blog plugin globally
- `urlGenerator` - Class to generate URLs for blog posts and categories

## Events & Listeners

The Blog plugin includes an observer for automatic thumbnail cleanup:

**BlogPostObserver:**

- `updated()` - Deletes old thumbnails when the thumbnail attribute changes
- `deleted()` - Cleans up thumbnails when a blog post is deleted

## Best Practices

### Content Structure

1. **Organize with Categories** - Use categories to group related content
2. **Assign Authors** - Always assign a staff member as the author
3. **Use Thumbnails** - Add featured images to improve engagement
4. **Multi-language** - Provide content in all supported languages for better reach
5. **Channel Targeting** - Publish specific content to relevant sales channels

### SEO Optimization

1. **Custom Meta Titles** - Use SEO-specific meta title attribute
2. **Meta Descriptions** - Write compelling meta descriptions
3. **Keywords** - Add relevant keywords for search discoverability
4. **Custom URLs** - Set clean, descriptive URLs per language

### Performance

1. **Eager Loading** - Use `with('author', 'channels', 'blogCategories')` when querying multiple posts
2. **Status Filtering** - Filter by status to avoid displaying draft content
3. **Channel Scoping** - Query by channel to reduce result sets

## Example Usage

### Display Blog Posts

```php
use Lunar\Blog\Models\BlogPost;
use Lunar\Models\Language;

// Get published posts for current channel
$language = Language::current();
$channel = Channel::current();

$posts = BlogPost::where('status', 'published')
    ->whereHas('channels', function ($query) use ($channel) {
        $query->where('channel_id', $channel->id);
    })
    ->with('author', 'blogCategories')
    ->latest()
    ->paginate(10);
```

### Display Blog Post Categories

```php
// Get published categories
$categories = BlogCategory::where('status', 'published')
    ->with('blogPosts')
    ->get();
```

### Filter Posts by Category

```php
// Get posts in a specific category
$category = BlogCategory::where('slug', 'technology')->first();
$posts = $category->blogPosts()->where('status', 'published')->get();
```

### Create Blog Post Programmatically

```php
$post = BlogPost::create([
    'status' => 'draft',
    'author_id' => auth()->user()->id, // Current staff member
    'attribute_data' => [
        'title' => [
            'en' => 'Getting Started with Lunar',
            'hu' => 'Kezdő Lépések a Lunar-ral',
        ],
        'short_description' => [
            'en' => 'A quick guide to get started...',
            'hu' => 'Rövid útmutató az első...',
        ],
        'content' => [
            'en' => '# Welcome\n\nContent here...',
            'hu' => '# Üdvözöljük\n\nTartalom itt...',
        ],
    ],
]);

// Assign categories
$post->blogCategories()->attach([1, 2]);

// Assign to channels
$post->channels()->attach([1, 2, 3]);
```

## Troubleshooting

### Blog posts not visible in admin

- Ensure `BLOG_ENABLED=true` in `.env`
- Verify BlogPlugin is registered in your Filament panel configuration
- Run migrations: `php artisan migrate`

### Thumbnails not saving

- Check that the configured storage disk is accessible
- Verify file permissions on the storage directory
- Ensure the thumbnail attribute is configured in admin

### Slug generation issues

- Clear cached routes: `php artisan route:cache --forget`
- Regenerate slugs through admin panel

### Multi-language content not showing

- Verify all languages are configured in Lunar
- Ensure content is filled in for all languages
- Check that the BlogPost's `translateAttribute()` method returns data
