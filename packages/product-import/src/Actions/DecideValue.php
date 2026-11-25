<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Support\Str;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\ProductImport\Normalizing\RowDiscount;
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Taxonomy\CatalogSnapshot;
use Lunar\ProductImport\Taxonomy\ValueCollector;
use Lunar\ProductImport\Validation\ImportValidator;

/**
 * Records what a category, brand, option, option value, property, or
 * discount becomes: an existing record of its type, a new record, or
 * nothing. A discount may use any eligible discount, or create one with
 * edited values. The rows that use the value are validated again.
 */
class DecideValue
{
    use GuardsImportStatus;

    public function __construct(
        protected ImportValidator $validator,
        protected ValueCollector $collector,
    ) {}

    /**
     * Use an existing record of the value's type.
     */
    public function useExisting(ProductImportValue $value, int $recordId): ProductImportValue
    {
        $this->ensureReviewStep($value);

        if (! $this->recordExists($value, $recordId)) {
            throw ImportActionRefused::because('unknown_record');
        }

        return $this->decide($value, [
            'action' => ValueAction::UseExisting,
            'target_id' => $recordId,
            'create_name' => null,
            'parent_id' => null,
        ]);
    }

    /**
     * Create a new record with the given name and, for a category, parent,
     * or for a discount, kind, value, start, and end (by default those from
     * the file).
     *
     * @param  ?array<string, mixed>  $details  For a discount: `kind`, `value` (the percent, or the amount in minor units), `starts_at`, and `ends_at`.
     */
    public function create(ProductImportValue $value, string $name, ?int $parentId = null, ?array $details = null): ProductImportValue
    {
        $this->ensureReviewStep($value);

        $name = Str::trim($name);

        if ($name === '') {
            throw ImportActionRefused::because('name_required');
        }

        // The import does not create attributes.
        if ($value->type === ValueType::Property) {
            throw ImportActionRefused::because('unknown_record');
        }

        if ($value->type === ValueType::Category && $parentId !== null && ! $this->collectionInGroup($value, $parentId)) {
            throw ImportActionRefused::because('parent_outside_group');
        }

        $discount = $value->type === ValueType::Discount ? RowDiscount::fromArray($details ?? $value->create_details ?? $value->details) : null;

        if ($value->type === ValueType::Discount && ($discount === null || ! $discount->isValid())) {
            throw ImportActionRefused::because('invalid_discount');
        }

        return $this->decide($value, [
            'action' => ValueAction::Create,
            'target_id' => null,
            'create_name' => Str::limit($name, 500, ''),
            'create_details' => $discount?->toArray(),
            'parent_id' => $value->type === ValueType::Category ? $parentId : null,
        ]);
    }

    /**
     * Import the value's products without it (categories, brands), or skip
     * the variants that use it (option values).
     */
    public function skip(ProductImportValue $value): ProductImportValue
    {
        $this->ensureReviewStep($value);

        return $this->decide($value, [
            'action' => ValueAction::Skip,
            'target_id' => null,
        ]);
    }

    /**
     * Store the decision and validate the rows that use the value again.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function decide(ProductImportValue $value, array $attributes): ProductImportValue
    {
        $value->forceFill($attributes)->save();

        $groups = $value->group_keys ?? [];

        // The values of an option named in cells are matched within the option it uses.
        if ($value->type === ValueType::Option) {
            $this->collector->rematchNamedOptionValues($value);

            foreach ($value->import->values()->where('type', ValueType::OptionValue)->where('option_key', 'name:'.$value->normalized_key)->get() as $optionValue) {
                array_push($groups, ...($optionValue->group_keys ?? []));
            }
        }

        $this->validator->validateGroups($value->import, array_values(array_unique($groups)));

        return $value;
    }

    /**
     * Refuse unless the import is at the value review.
     */
    protected function ensureReviewStep(ProductImportValue $value): void
    {
        $this->ensureStatus($value->import, [ImportStatus::ReviewingValues], 'not_review_step');
    }

    /**
     * Whether the record exists among the records the value may use.
     */
    protected function recordExists(ProductImportValue $value, int $recordId): bool
    {
        return match ($value->type) {
            ValueType::Brand => Brand::query()->whereKey($recordId)->exists(),
            ValueType::Category => $this->collectionInGroup($value, $recordId),
            ValueType::Option => ProductOption::query()->whereKey($recordId)->exists(),
            ValueType::OptionValue => ($optionId = $value->optionOf()) !== null
                && ProductOptionValue::query()
                    ->whereKey($recordId)
                    ->where('product_option_id', $optionId)
                    ->exists(),
            ValueType::Property => app(TargetFieldRegistry::class)
                ->textAttributes((int) Str::after($value->option_key, 'type:'))
                ->contains(fn ($attribute): bool => (int) $attribute->id === $recordId),
            ValueType::Discount => CatalogSnapshot::eligibleDiscounts()->whereKey($recordId)->exists(),
        };
    }

    /**
     * Whether the collection belongs to the import's collection group.
     */
    protected function collectionInGroup(ProductImportValue $value, int $collectionId): bool
    {
        return Collection::query()
            ->whereKey($collectionId)
            ->where('collection_group_id', $value->import->importOptions()->collectionGroupId)
            ->exists();
    }
}
