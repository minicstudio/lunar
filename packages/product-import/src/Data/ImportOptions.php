<?php

namespace Lunar\ProductImport\Data;

use Lunar\Models\CollectionGroup;
use Lunar\ProductImport\Enums\ExistingVariants;
use Lunar\ProductImport\Enums\MatchKey;
use Lunar\ProductImport\Enums\NewProductStatus;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The options that apply to every sheet of an import. Separators are not
 * import options: they belong to columns (`ColumnFormat`).
 */
#[MapName(SnakeCaseMapper::class)]
class ImportOptions extends Data
{
    /**
     * @param  list<int>  $additionalCollectionIds  Existing collections every imported product is also added to.
     */
    public function __construct(
        public MatchKey $matchKey = MatchKey::Sku,
        public ExistingVariants $existingVariants = ExistingVariants::Update,
        public NewProductStatus $newProductStatus = NewProductStatus::Draft,
        public ?int $collectionGroupId = null,
        public array $additionalCollectionIds = [],
    ) {}

    /**
     * The defaults: match by SKU, update existing variants, create drafts,
     * use the first collection group, and add no additional collections.
     */
    public static function defaults(): self
    {
        return new self(collectionGroupId: static::defaultCollectionGroupId());
    }

    /**
     * Read stored options, filling what is missing with the defaults.
     *
     * @param  ?array<string, mixed>  $stored
     */
    public static function restore(?array $stored): self
    {
        $options = $stored ? self::from($stored) : self::defaults();

        if ($options->collectionGroupId === null) {
            $options->collectionGroupId = static::defaultCollectionGroupId();
        }

        $options->additionalCollectionIds = array_values(array_unique(array_map('intval', $options->additionalCollectionIds)));

        return $options;
    }

    /**
     * The first collection group.
     */
    protected static function defaultCollectionGroupId(): ?int
    {
        return CollectionGroup::query()->orderBy('id')->value('id');
    }
}
