<?php

namespace Lunar\ProductImport\Persisting;

use Illuminate\Support\Str;
use Lunar\Models\Collection;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Support\TargetFieldRegistry;

/**
 * What every product group of an import is written with, loaded once per job.
 */
final class CommitContext
{
    /**
     * @param  array<int, SheetMapping>  $mappings  Sheet id => mapping.
     * @param  array<string, int>  $options  Option key => product option id.
     * @param  array<string, array<string, array<string, array{action: ?ValueAction, target: ?int}>>>  $decisions
     * @param  list<int>  $additionalCollectionIds  The chosen additional collections that still exist.
     */
    public function __construct(
        public ImportOptions $importOptions,
        public string $locale,
        public Currency $currency,
        public int $taxClassId,
        public array $mappings,
        public array $options,
        public array $decisions,
        public array $additionalCollectionIds = [],
    ) {}

    public static function for(ProductImport $import): self
    {
        $decisions = [];

        foreach ($import->values()->get(['type', 'option_key', 'normalized_key', 'action', 'target_id']) as $value) {
            $decisions[$value->type->value][$value->option_key][$value->normalized_key] = [
                'action' => $value->action,
                'target' => $value->target_id,
            ];
        }

        $options = $import->resolved_options ?? [];

        foreach ($import->sheets()->get() as $sheet) {
            foreach ($sheet->sheetMapping()->optionKeys() as $key) {
                if (! isset($options[$key]) && Str::startsWith($key, 'option:')) {
                    $options[$key] = (int) Str::after($key, 'option:');
                }
            }
        }

        $currency = Currency::getDefault();
        $taxClass = TaxClass::getDefault();

        throw_if($currency === null, \RuntimeException::class, 'No default currency is set.');
        throw_if($taxClass === null, \RuntimeException::class, 'No default tax class is set.');

        $importOptions = $import->importOptions();

        $additionalCollectionIds = $importOptions->additionalCollectionIds === []
            ? []
            : Collection::query()->whereIn('id', $importOptions->additionalCollectionIds)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return new self(
            importOptions: $importOptions,
            locale: app(TargetFieldRegistry::class)->defaultLanguage(),
            currency: $currency,
            taxClassId: (int) $taxClass->id,
            mappings: $import->sheets()->get()->mapWithKeys(fn ($sheet): array => [$sheet->id => $sheet->sheetMapping()])->all(),
            options: $options,
            decisions: $decisions,
            additionalCollectionIds: $additionalCollectionIds,
        );
    }

    /**
     * The record a value resolved to, or null when it is skipped or unknown.
     */
    public function target(string $type, string $optionKey, string $normalizedKey): ?int
    {
        $decision = $this->decisions[$type][$optionKey][$normalizedKey] ?? null;

        if ($decision === null || $decision['action'] === ValueAction::Skip) {
            return null;
        }

        return $decision['target'];
    }
}
