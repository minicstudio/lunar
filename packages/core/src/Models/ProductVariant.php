<?php

namespace Lunar\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Lunar\Base\BaseModel;
use Lunar\Base\Casts\AsAttributeData;
use Lunar\Base\HasThumbnailImage;
use Lunar\Base\Purchasable;
use Lunar\Base\Traits\HasAttributes;
use Lunar\Base\Traits\HasDimensions;
use Lunar\Base\Traits\HasDiscount;
use Lunar\Base\Traits\HasMacros;
use Lunar\Base\Traits\HasPrices;
use Lunar\Base\Traits\HasTranslations;
use Lunar\Base\Traits\LogsActivity;
use Lunar\Database\Factories\ProductVariantFactory;
use Lunar\Events\ProductVariantCreatedEvent;
use Lunar\Events\ProductVariantDeletedEvent;
use Lunar\Events\ProductVariantUpdatedEvent;
use Lunar\Models\Collection as CollectionModel;
use Spatie\LaravelBlink\BlinkFacade as Blink;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $product_id
 * @property int $tax_class_id
 * @property ?Collection $attribute_data
 * @property ?string $tax_ref
 * @property int $unit_quantity
 * @property int $min_quantity
 * @property int $quantity_increment
 * @property ?string $sku
 * @property ?string $gtin
 * @property ?string $mpn
 * @property ?string $ean
 * @property ?float $length_value
 * @property ?string $length_unit
 * @property ?float $width_value
 * @property ?string $width_unit
 * @property ?float $height_value
 * @property ?string $height_unit
 * @property ?float $weight_value
 * @property ?string $weight_unit
 * @property ?float $volume_value
 * @property ?string $volume_unit
 * @property bool $shippable
 * @property int $stock
 * @property int $backorder
 * @property string $purchasable
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
class ProductVariant extends BaseModel implements Contracts\ProductVariant, HasThumbnailImage, Purchasable
{
    use HasAttributes;
    use HasDimensions;
    use HasDiscount;
    use HasFactory;
    use HasMacros;
    use HasPrices;
    use HasTranslations;
    use LogsActivity;
    use Searchable;
    use SoftDeletes;

    /**
     * Define the guarded attributes.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * {@inheritDoc}
     */
    protected $casts = [
        'shippable' => 'bool',
        'attribute_data' => AsAttributeData::class,
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'created' => ProductVariantCreatedEvent::class,
        'updated' => ProductVariantUpdatedEvent::class,
        'deleted' => ProductVariantDeletedEvent::class,
    ];

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ProductVariantFactory::new();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::modelClass())->withTrashed();
    }

    /**
     * @return BelongsTo<TaxClass, $this>
     */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::modelClass());
    }

    /**
     * @return BelongsToMany<ProductOptionValue, $this>
     */
    public function values(): BelongsToMany
    {
        $prefix = config('lunar.database.table_prefix');

        return $this->belongsToMany(
            ProductOptionValue::modelClass(),
            "{$prefix}product_option_value_product_variant",
            'variant_id',
            'value_id'
        )->withTimestamps()
            ->orderBy('position')
            ->orderByPivot('id');
    }

    public function getPrices(): Collection
    {
        return $this->prices;
    }

    /**
     * Return the unit quantity for the variant.
     */
    public function getUnitQuantity(): int
    {
        return $this->unit_quantity;
    }

    /**
     * Return the tax class.
     */
    public function getTaxClass(): TaxClass
    {
        return Blink::once("tax_class_{$this->tax_class_id}", function () {
            return $this->taxClass;
        });
    }

    public function getTaxReference(): ?string
    {
        return $this->tax_ref;
    }

    /**
     * {@inheritDoc}
     */
    public function getType(): string
    {
        return $this->shippable ? 'physical' : 'digital';
    }

    /**
     * {@inheritDoc}
     */
    public function isShippable(): bool
    {
        return $this->shippable;
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return $this->product->translateAttribute('name');
    }

    /**
     * {@inheritDoc}
     */
    public function getOption(): string
    {
        return $this->values->map(fn ($value) => $value->translate('name'))->join(', ');
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions(): Collection
    {
        return $this->values->map(fn ($value) => $value->translate('name'));
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier(): string
    {
        return $this->sku;
    }

    /**
     * Get the variant's images through the MediaProductVariant pivot, so changes to them fire model events.
     */
    public function images(): BelongsToMany
    {
        $prefix = config('lunar.database.table_prefix');

        return $this->belongsToMany(Media::class, "{$prefix}media_product_variant")
            ->using(MediaProductVariant::class)
            ->withPivot(['primary', 'position'])
            ->orderBy('position')
            ->withTimestamps();
    }

    /**
     * Get the variant primary image, or its first image by pivot position.
     * Fall back to the general media's primary or first image, then to all
     * product media's primary or first image in product media order.
     */
    public function getThumbnail(): ?Media
    {
        $productMedia = $this->product->media->sortBy('order_column');
        $variantImage = $this->images->firstWhere('pivot.primary', true) ?? $this->images->first();

        if ($variantImage) {
            return $productMedia->firstWhere('id', $variantImage->getKey()) ?? $variantImage;
        }

        $generalMedia = $this->generalMedia();
        $isPrimary = fn (Media $media): bool => $media->getCustomProperty('primary') === true;

        return $generalMedia->first($isPrimary)
            ?? $generalMedia->first()
            ?? $productMedia->first($isPrimary)
            ?? $productMedia->first();
    }

    /**
     * Get the general media: the product's media not assigned to any of its variants.
     *
     * Uses the variant images when loaded for every variant, otherwise a single pivot query,
     * so partially loaded variants never lazy load their images one by one.
     *
     * @return Collection<int, Media>
     */
    public function generalMedia(): Collection
    {
        $product = $this->product;

        $variantImagesLoaded = $product->relationLoaded('variants')
            && $product->variants->every(fn (ProductVariant $variant) => $variant->relationLoaded('images'));

        $variantMediaIds = $variantImagesLoaded
            ? $product->variants->flatMap(fn (ProductVariant $variant) => $variant->images->modelKeys())
            : MediaProductVariant::query()
                ->whereIn('product_variant_id', $product->variants()->select('id'))
                ->pluck('media_id');

        return $product->media->whereNotIn('id', $variantMediaIds->unique())->sortBy('order_column')->values();
    }

    public function canBeFulfilledAtQuantity(int $quantity): bool
    {
        if ($this->purchasable == 'always') {
            return true;
        }

        return $quantity <= $this->getTotalInventory();
    }

    public function isPurchasable(): bool
    {
        return ! $this->trashed()
            && $this->product
            && ! $this->product->trashed()
            && $this->product->status === 'published';
    }

    public function getTotalInventory(): int
    {
        if ($this->purchasable == 'in_stock') {
            return $this->stock;
        }

        return $this->stock + $this->backorder;
    }

    public function getThumbnailImage(): string
    {
        return $this->getThumbnail()?->getUrl('small') ?? '';
    }

    /**
     * Get the collections that the product variant belongs to.
     */
    public function collections(): HasManyThrough
    {
        return $this->hasManyThrough(CollectionModel::class, Product::class);
    }

    /**
     * Ensure stock is never stored below zero.
     */
    protected function stock(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value) => max(0, (int) $value),
        );
    }

    /**
     * Decrease the stock.
     *
     * Stock is floored at 0. When purchasable is not `in_stock`, any
     * shortfall is applied to backorder (which may go negative).
     */
    public function decreaseStock(int $quantity = 1): self
    {
        $remaining = $this->stock - $quantity;

        if ($remaining < 0 && $this->purchasable !== 'in_stock') {
            $this->backorder += $remaining;
        }

        $this->stock = max(0, $remaining);

        return $this;
    }

    /**
     * Increase the stock.
     */
    public function increaseStock(int $quantity = 1): self
    {
        if ($this->backorder < 0 && $this->purchasable !== 'in_stock') {
            $backorderToReduce = min($quantity, abs($this->backorder));
            $this->backorder += $backorderToReduce;
            $quantity -= $backorderToReduce;
        }

        if ($quantity > 0) {
            $this->stock += $quantity;
        }

        return $this;
    }
}
