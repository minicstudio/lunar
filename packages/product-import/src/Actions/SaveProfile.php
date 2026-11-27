<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Support\Str;
use Lunar\Admin\Models\Staff;
use Lunar\Models\ProductOption;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Mapping\HeaderNormalizer;
use Lunar\ProductImport\Mapping\ProfileMatcher;
use Lunar\ProductImport\Models\ProductImportProfile;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Saves a sheet's current mapping, headers, and the import options as a
 * named profile.
 */
class SaveProfile
{
    public function __construct(
        protected HeaderNormalizer $normalizer,
        protected ProfileMatcher $matcher,
    ) {}

    public function handle(ProductImportSheet $sheet, string $name, ?Staff $staff = null): ProductImportProfile
    {
        $name = Str::trim($name);

        $this->ensureNameIsFree($name);

        if (! $sheet->hasMapping()) {
            throw ImportActionRefused::because('profile_without_mapping');
        }

        $mapping = $sheet->sheetMapping();
        $headers = array_map(fn (ColumnMapping $column): string => $column->header, $mapping->columns);

        // Keep the option names, so a deleted option can be named when the profile is applied.
        foreach ($mapping->columnsFor(TargetField::VariantOption) as $column) {
            if ($column->optionId !== null && $column->optionName === null) {
                $column->optionName = ProductOption::query()->find($column->optionId)?->translate('name');
            }
        }

        return ProductImportProfile::query()->create([
            'name' => $name,
            'headers' => $this->normalizer->normalizeAll($headers),
            'header_fingerprint' => $this->matcher->fingerprint($headers),
            'mapping' => $mapping->toArray(),
            'options' => $sheet->import->importOptions()->toArray(),
            'staff_id' => $staff?->getKey(),
        ]);
    }

    /**
     * Refuse an empty name or one another profile has.
     */
    public function ensureNameIsFree(string $name, ?ProductImportProfile $except = null): void
    {
        if ($name === '') {
            throw ImportActionRefused::because('profile_name_required');
        }

        $taken = ProductImportProfile::query()
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();

        if ($taken) {
            throw ImportActionRefused::because('profile_name_taken');
        }
    }
}
