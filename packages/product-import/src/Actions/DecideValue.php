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
use Lunar\ProductImport\Support\TargetFieldRegistry;
use Lunar\ProductImport\Taxonomy\ValueCollector;
use Lunar\ProductImport\Validation\ImportValidator;

/**
 * Records what a category, brand, or option value becomes: an existing
 * record of its type, a new record, or nothing. The rows that use the value
 * are validated again.
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
     * Create a new record with the given name and, for a category, parent.
     */
    public function create(ProductImportValue $value, string $name, ?int $parentId = null): ProductImportValue
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

        return $this->decide($value, [
            'action' => ValueAction::Create,
            'target_id' => null,
            'create_name' => Str::limit($name, 500, ''),
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
