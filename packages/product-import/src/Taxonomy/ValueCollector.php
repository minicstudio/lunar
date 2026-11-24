<?php

namespace Lunar\ProductImport\Taxonomy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\ProductImport\Support\TargetFieldRegistry;

/**
 * Collects the import's categories, brands, option names, option values, and
 * properties, matches them to the catalog, and stores them by their unique
 * key. Decisions of values
 * that still occur survive staging again; values that vanished are removed.
 */
class ValueCollector
{
    public function __construct(
        protected ValueExtractor $extractor,
        protected ValueMatcher $matcher,
        protected TargetFieldRegistry $registry,
    ) {}

    /**
     * Collect and match the values. Returns how many values there are.
     */
    public function collect(ProductImport $import): int
    {
        $extracted = $this->extractor->extract($import);
        $options = $import->importOptions();

        $optionIds = collect($extracted['values'])
            ->filter(fn (ExtractedValue $value): bool => $value->type === ValueType::OptionValue && Str::startsWith($value->optionKey, 'option:'))
            ->map(fn (ExtractedValue $value): int => (int) Str::after($value->optionKey, 'option:'))
            ->unique()
            ->values()
            ->all();

        $productTypeIds = collect($extracted['values'])
            ->filter(fn (ExtractedValue $value): bool => $value->type === ValueType::Property)
            ->map(fn (ExtractedValue $value): int => (int) Str::after($value->optionKey, 'type:'))
            ->unique()
            ->values()
            ->all();

        $catalog = CatalogSnapshot::load($options->collectionGroupId, $optionIds, $this->registry->defaultLanguage(), $productTypeIds);

        $existing = $import->values()->get()->keyBy(fn (ProductImportValue $value): string => $value->type->value."\0".$value->option_key."\0".$value->normalized_key);

        $this->resolveNamedOptions($extracted['values'], $existing, $catalog);
        $kept = [];

        DB::transaction(function () use ($import, $extracted, $catalog, $existing, &$kept): void {
            foreach ($extracted['values'] as $value) {
                $key = $value->key();
                $kept[] = $key;

                $attributes = [
                    'source_value' => Str::limit($value->sourceValue, 500, ''),
                    'path' => $value->path,
                    'sheets' => array_keys($value->sheets),
                    'group_keys' => array_keys($value->groups),
                    'product_count' => count($value->groups),
                    'variant_count' => $value->variantCount,
                ];

                $match = $this->matcher->match($value, $catalog);

                $attributes += [
                    'match_type' => $match->matchType,
                    'matched_id' => $match->matchedId,
                    'score' => $match->score,
                ];

                /** @var ?ProductImportValue $stored */
                $stored = $existing->get($key);

                if ($stored !== null) {
                    $stored->forceFill($attributes)->save();

                    continue;
                }

                $import->values()->create([
                    'type' => $value->type,
                    'option_key' => $value->optionKey,
                    'normalized_key' => Str::limit($value->normalizedKey, 500, ''),
                    ...$attributes,
                    'action' => $match->action,
                    'target_id' => $match->targetId,
                    'create_name' => $match->createName !== null ? Str::limit($match->createName, 500, '') : null,
                    'parent_id' => $match->parentId,
                ]);
            }

            $vanished = $existing->keys()->diff($kept)->map(fn (string $key): int => $existing[$key]->id)->values()->all();

            if ($vanished !== []) {
                ProductImportValue::query()->whereIn('id', $vanished)->delete();
            }
        });

        $stats = $import->stats ?? [];
        $stats['review'] = ['products_without_category' => $extracted['products_without_category']];
        $import->forceFill(['stats' => $stats])->save();

        return count($kept);
    }

    /**
     * Decide which existing option each option name from cells uses: the
     * stored decision, or the default of its match, and load those options'
     * values, so the names' values are matched within them.
     *
     * @param  list<ExtractedValue>  $values
     * @param  Collection<string, ProductImportValue>  $existing
     */
    public function resolveNamedOptions(array $values, Collection $existing, CatalogSnapshot $catalog): void
    {
        foreach ($values as $value) {
            if ($value->type !== ValueType::Option) {
                continue;
            }

            /** @var ?ProductImportValue $stored */
            $stored = $existing->get($value->key());

            if ($stored !== null && $stored->action !== null) {
                [$action, $target] = [$stored->action, $stored->target_id];
            } else {
                $match = $this->matcher->match($value, $catalog);
                [$action, $target] = [$match->action, $match->targetId];
            }

            if ($action === ValueAction::UseExisting && $target !== null) {
                $catalog->namedOptions[$value->normalizedKey] = (int) $target;
            }
        }

        $catalog->loadOptionValues(array_values($catalog->namedOptions));
    }

    /**
     * Match the values of an option named in cells again, after the option's
     * decision changed, and reset their decisions to the new defaults.
     */
    public function rematchNamedOptionValues(ProductImportValue $option): void
    {
        $catalog = CatalogSnapshot::load(null, [], $this->registry->defaultLanguage());

        if ($option->action === ValueAction::UseExisting && $option->target_id !== null) {
            $catalog->namedOptions[$option->normalized_key] = (int) $option->target_id;
            $catalog->loadOptionValues([(int) $option->target_id]);
        }

        $optionKey = 'name:'.$option->normalized_key;

        foreach ($option->import->values()->where('type', ValueType::OptionValue)->where('option_key', $optionKey)->get() as $stored) {
            $match = $this->matcher->match(new ExtractedValue(ValueType::OptionValue, $optionKey, $stored->normalized_key, $stored->source_value, (array) $stored->path), $catalog);

            $stored->forceFill([
                'match_type' => $match->matchType,
                'matched_id' => $match->matchedId,
                'score' => $match->score,
                'action' => $match->action,
                'target_id' => $match->targetId,
                'create_name' => $match->createName !== null ? Str::limit($match->createName, 500, '') : null,
                'parent_id' => null,
            ])->save();
        }
    }
}
