<?php

namespace Lunar\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lunar\Admin\Models\Staff;
use Lunar\Base\BaseModel;
use Lunar\Base\Casts\AsAttributeData;
use Lunar\Base\Traits\HasChannels;
use Lunar\Base\Traits\HasTranslations;
use Lunar\Base\Traits\HasUrls;
use Lunar\Models\Attribute;
use Lunar\Blog\Database\Factories\BlogPostFactory;

class BlogPost extends BaseModel implements Contracts\BlogPost
{
    use HasChannels,
        HasFactory,
        HasTranslations,
        HasUrls;

    /**
     * Fillable fields
     *
     * @var array
     */
    protected $fillable = [
        'attribute_data',
        'status',
        'author_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'attribute_data' => AsAttributeData::class,
    ];

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return BlogPostFactory::new();
    }

    /**
     * Get the author of the blog post.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'author_id');
    }

    /**
     * Get the disk configuration for blog post thumbnails.
     *
     * This method retrieves the disk configuration for the blog post thumbnails
     * based on the 'thumbnail' attribute settings. If no specific configuration is found,
     * it defaults to the application's default filesystem disk.
     *
     * @return string The disk name for storing blog post thumbnails.
     */
    public function thumbnailDisk(): string
    {
        $attribute = Attribute::where('attribute_type', BlogPost::morphName())
            ->where('handle', 'thumbnail')
            ->first();

        if ($attribute?->configuration) {
            $config = json_decode($attribute->configuration, true);

            return $config['disk'] ?? config('filesystems.default');
        }

        return config('filesystems.default');
    }

    /**
     * Returns the first thumbnail path for the blog post.
     */
    public function getThumbnail(): ?string
    {
        return collect($this->translateAttribute('thumbnail'))->first();
    }

    /**
     * Define a many-to-many relationship with BlogCategory.
     */
    public function blogCategories(): BelongsToMany
    {
        $prefix = config('lunar.database.table_prefix');

        return $this->belongsToMany(
            BlogCategory::class,
            "{$prefix}blog_category_blog_post",
        );
    }

    /**
     * Accessor to get the author full name.
     *
     * @return string The full name of the blog post author.
     */
    public function getAuthorFullNameAttribute(): string
    {
        $firstName = $this->attr('author_first_name');
        $lastName = $this->attr('author_last_name');

        return $firstName.' '.$lastName;
    }
}
