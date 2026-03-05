<?php

namespace Lunar\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lunar\Base\BaseModel;
use Lunar\Base\Casts\AsAttributeData;
use Lunar\Base\Enums\Concerns\ProvidesProductAssociationType;
use Lunar\Base\HasCustomerGroupAvailability;
use Lunar\Base\HasThumbnailImage;
use Lunar\Base\Traits\HasChannels;
use Lunar\Base\Traits\HasCustomerGroups;
use Lunar\Base\Traits\HasDiscount;
use Lunar\Base\Traits\HasMacros;
use Lunar\Base\Traits\HasMedia;
use Lunar\Base\Traits\HasTags;
use Lunar\Base\Traits\HasTranslations;
use Lunar\Base\Traits\HasUrls;
use Lunar\Base\Traits\LogsActivity;
use Lunar\Base\Traits\Searchable;
use Lunar\Database\Factories\ProductFactory;
use Lunar\Events\ProductCreatedEvent;
use Lunar\Events\ProductDeletedEvent;
use Lunar\Events\ProductUpdatedEvent;
use Lunar\Facades\StorefrontSession;
use Lunar\Jobs\Products\Associations\Associate;
use Lunar\Jobs\Products\Associations\Dissociate;
use Lunar\Models\Collection as CollectionModel;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;

/**
 * @property int $id
 * @property ?int $brand_id
 * @property int $product_type_id
 * @property string $status
 * @property ?\Illuminate\Support\Collection $attribute_data
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property ?\Illuminate\Support\Carbon $deleted_at
 */
class Product extends BaseModel implements Contracts\Product, HasThumbnailImage, SpatieHasMedia, HasCustomerGroupAvailability
{
    use HasChannels;
    use HasCustomerGroups;
    use HasFactory;
    use HasMacros;
    use HasMedia;
    use HasTags;
    use HasTranslations;
    use HasUrls;
    use LogsActivity;
    use Searchable;
    use SoftDeletes;
    use HasDiscount;

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProductFactory::new();
    }

    /**
     * Define which attributes should be
     * fillable during mass assignment.
     *
     * @var array
     */
    protected $fillable = [
        'attribute_data',
        'product_type_id',
        'status',
        'brand_id',
    ];

    /**
     * Define which attributes should be cast.
     *
     * @var array
     */
    protected $casts = [
        'attribute_data' => AsAttributeData::class,
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'created' => ProductCreatedEvent::class,
        'updated' => ProductUpdatedEvent::class,
        'deleted' => ProductDeletedEvent::class,
    ];

    /**
     * Record's title
     */
    protected function recordTitle(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => $this->translateAttribute('name'),
        );
    }

    public function mappedAttributes(): Collection
    {
        return $this->productType->mappedAttributes;
    }

    public function productType(): BelongsTo
    {
        return $this->belongsTo(ProductType::modelClass());
    }

    public function images(): MorphMany
    {
        return $this->media()->where('collection_name', config('lunar.media.collection'));
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::modelClass());
    }

    public function variant(): HasOne
    {
        return $this->hasOne(ProductVariant::modelClass());
    }

    protected function hasVariants(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->variants()->count() > 1,
        );
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(
            \Lunar\Models\Collection::modelClass(),
            config('lunar.database.table_prefix').'collection_product'
        )->withPivot(['position'])->orderByPivot('position')->withTimestamps();
    }

    public function associations(): HasMany
    {
        return $this->hasMany(ProductAssociation::modelClass(), 'product_parent_id');
    }

    public function inverseAssociations(): HasMany
    {
        return $this->hasMany(ProductAssociation::modelClass(), 'product_target_id');
    }

    public function associate(mixed $product, ProvidesProductAssociationType|string $type): void
    {
        Associate::dispatch($this, $product, $type);
    }

    /**
     * Dissociate a product to another with a type.
     */
    public function dissociate(mixed $product, ProvidesProductAssociationType|string|null $type = null): void
    {
        Dissociate::dispatch($this, $product, $type);
    }

    public function customerGroups(): BelongsToMany
    {
        $prefix = config('lunar.database.table_prefix');

        return $this->belongsToMany(
            CustomerGroup::modelClass(),
            "{$prefix}customer_group_product"
        )->withPivot([
            'purchasable',
            'visible',
            'enabled',
            'starts_at',
            'ends_at',
        ])->withTimestamps();
    }

    public static function getExtraCustomerGroupPivotValues(CustomerGroup $customerGroup): array
    {
        return [
            'purchasable' => $customerGroup->default,
        ];
    }

    /**
     * Return the brand relationship.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::modelClass());
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->whereStatus($status);
    }

    public function prices(): HasManyThrough
    {
        return $this->hasManyThrough(
            Price::modelClass(),
            ProductVariant::modelClass(),
            'product_id',
            'priceable_id'
        )->wherePriceableType('product_variant');
    }

    public function productOptions(): BelongsToMany
    {
        $prefix = config('lunar.database.table_prefix');

        return $this->belongsToMany(
            ProductOption::modelClass(),
            "{$prefix}product_product_option"
        )->withPivot(['position'])->orderByPivot('position');
    }

    public function getThumbnailImage(): string
    {
        return $this->thumbnail?->getUrl('small') ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function scopeAvailableCustomerGroups($query): Builder
    {
        // TODO: Add as a subquery
        $customerGroupIds = StorefrontSession::getCustomerGroups()->pluck('id');

        return $query->whereHas('customerGroups', function ($query) use ($customerGroupIds) {
            $query->whereIn('lunar_customer_groups.id', $customerGroupIds)
                ->where('visible', true)
                ->where('enabled', true)
                ->where(function ($query) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', now());
                })->where(function ($query) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', now());
                });
        });
    }

    /**
     * Scope to only include customer groups that are allowed to purchase the product.
     *
     * This scope filters customer groups based on their purchasability by checking that the 'purchasable'
     * flag is true and that the group is currently active. It applies time constraints using `starts_at`
     * and `ends_at` to ensure only customer groups valid at the current moment are included.
     */
    public function scopePurchasableCustomerGroups(Builder $query): Builder
    {
        $customerGroupIds = StorefrontSession::getCustomerGroups()->pluck('id');

        return $query->whereHas('customerGroups', function ($query) use ($customerGroupIds) {
            $query->whereIn('lunar_customer_groups.id', $customerGroupIds)
                ->where('purchasable', true)
                ->where(function ($query) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', now());
                });
        });
    }

    /**
     * Determines if the specified product can be purchased by the customer's groups.
     *
     * @return bool True if the product is purchasable by the customer's groups, false otherwise.
     */
    public function canPurchaseProduct(): bool
    {
        return self::query()
            ->where('id', $this->id)
            ->purchasableCustomerGroups()
            ->exists();
    }

    /**
     * Scope a query to only include available products.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->availableCustomerGroups()
            ->status('published')
            ->channel(StorefrontSession::getChannel());
    }

    /**
     * Scope a query to only include unavailable products.
     */
    public function scopeUnavailable(Builder $query): Builder
    {
        return $query->where(function ($query) {
            $query->where('status', '!=', 'published')
                ->orWhereDoesntHave('customerGroups', function ($q) {
                    $customerGroupIds = StorefrontSession::getCustomerGroups()->pluck('id');

                    $q->whereIn('lunar_customer_groups.id', $customerGroupIds)
                        ->where('visible', true)
                        ->where('enabled', true)
                        ->where(function ($q) {
                            $q->whereNull('starts_at')
                                ->orWhere('starts_at', '<=', now());
                        })->where(function ($q) {
                            $q->whereNull('ends_at')
                                ->orWhere('ends_at', '>=', now());
                        });
                });
        });
    }

    /**
     * Scope a query to only include products from the specified collection.
     *
     * @param  int  $collectionId  The ID of the collection to filter by.
     * @param  Currency  $currency  The currency to filter by.
     */
    public function scopeAvailableByCollection(Builder $query, $collectionId, $currency)
    {
        $collectionIds = CollectionModel::where('parent_id', $collectionId)
            ->orWhere('id', $collectionId) // get the current collection as well
            ->pluck('id');

        return $query->with([
            'prices' => function ($query) use ($currency) {
                $query->where('currency_id', $currency->id)
                    ->with('currency');
            },
            'urls',
            'media',
        ])
            ->available()
            ->whereHas('collections', function ($query) use ($collectionIds) {
                $query->whereIn('collection_id', $collectionIds);
            });
    }

    /**
     * Check if a product is available for purchase.
     *
     * @param  int  $productId  The ID of the product to check.
     * @return bool True if the product is available, false otherwise.
     */
    public function isAvailable(): bool
    {
        return self::available()
            ->where('id', $this->id)
            ->exists();
    }
}
