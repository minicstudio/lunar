<?php

namespace Lunar\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\Base\BaseModel;
use Lunar\Base\Casts\AsAttributeData;
use Lunar\Base\Traits\HasChannels;
use Lunar\Base\Traits\HasTranslations;
use Lunar\Base\Traits\HasUrls;
use Lunar\Blog\Database\Factories\BlogCategoryFactory;
use Lunar\Models\Language;
use Lunar\Blog\Traits\ResolvesSlug;

class BlogCategory extends BaseModel implements Contracts\BlogCategory
{
    use HasChannels,
        HasFactory,
        HasTranslations,
        HasUrls,
        ResolvesSlug;

    /**
     * Fillable fields
     *
     * @var array
     */
    protected $fillable = [
        'attribute_data',
        'status',
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
        return BlogCategoryFactory::new();
    }

    /**
     * Define a many-to-many relationship with BlogPost.
     */
    public function blogPosts(): BelongsToMany
    {
        $prefix = config('lunar.database.table_prefix');

        return $this->belongsToMany(
            BlogPost::class,
            "{$prefix}blog_category_blog_post",
        );
    }

    /**
     * Get the slug attribute for the blog category.
     *
     * @param  Language  $language  The language for which to generate the slug.
     * @return Collection The blog category slugs.
     */
    public function getBlogCategorySlug(Language $language): Collection
    {
        $slug = $this->getSlugs($language->id);

        return $slug->isNotEmpty()
            ? $slug
            : collect([Str::slug($this->translateAttribute('name'))]);
    }
}
