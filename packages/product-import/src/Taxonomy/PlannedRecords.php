<?php

namespace Lunar\ProductImport\Taxonomy;

use Illuminate\Support\Str;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\ProductImport\Support\KeyNormalizer;

/**
 * The records an import's "create" decisions resolve to: one per normalized
 * name, type, and parent (categories) or option (option values). A category
 * value may create several levels; shared levels are planned once.
 */
class PlannedRecords
{
    /**
     * The planned records, parents before their children.
     *
     * @return list<PlannedRecord>
     */
    public function for(ProductImport $import): array
    {
        /** @var array<string, PlannedRecord> $planned */
        $planned = [];

        $values = $import->values()
            ->where('action', ValueAction::Create)
            ->whereIn('type', [ValueType::Brand, ValueType::Category, ValueType::OptionValue])
            ->orderBy('id')
            ->get();

        $namedOptions = $this->namedOptions($import);

        foreach ($values as $value) {
            /** @var ProductImportValue $value */
            $name = Str::trim((string) ($value->create_name ?: $value->source_value));

            if ($name === '') {
                continue;
            }

            if ($value->type !== ValueType::Category) {
                $optionKey = $value->type === ValueType::OptionValue ? $this->optionIdentity($value->option_key, $namedOptions) : '';

                if ($optionKey === null) {
                    continue;
                }

                $key = $value->type->value.'|'.$optionKey.'|'.KeyNormalizer::key($name);
                $planned[$key] ??= new PlannedRecord($key, $value->type, Str::squish($name), $optionKey);
                $planned[$key]->valueIds[] = $value->id;

                continue;
            }

            $parentKey = null;
            $levelKey = $value->parent_id !== null ? 'collection:'.$value->parent_id : 'root';
            $levels = array_values(array_filter(array_map(
                fn (string $level): string => Str::squish($level),
                Str::of($name)->explode('>')->all(),
            ), fn (string $level): bool => $level !== ''));

            foreach ($levels as $depth => $level) {
                $levelKey .= '/'.KeyNormalizer::key($level);
                $key = 'category|'.$levelKey;

                $planned[$key] ??= new PlannedRecord(
                    $key,
                    ValueType::Category,
                    $level,
                    parentKey: $parentKey,
                    parentId: $depth === 0 ? $value->parent_id : null,
                );

                if ($depth === count($levels) - 1) {
                    $planned[$key]->valueIds[] = $value->id;
                }

                $parentKey = $key;
            }
        }

        return array_values($planned);
    }

    /**
     * The option every option name from cells resolves to, by its normalized
     * name: `option:<id>` for an existing option, `new:<normalized name>` for
     * a new one, so a name from cells and a mapped new option of the same
     * name share one option. Skipped and undecided names resolve to nothing.
     *
     * @return array<string, ?string>
     */
    public function namedOptions(ProductImport $import): array
    {
        $resolved = [];

        foreach ($import->values()->where('type', ValueType::Option)->get() as $option) {
            $resolved[$option->normalized_key] = match ($option->action) {
                ValueAction::UseExisting => $option->target_id !== null ? 'option:'.$option->target_id : null,
                ValueAction::Create => 'new:'.KeyNormalizer::key((string) ($option->create_name ?: $option->source_value)),
                default => null,
            };
        }

        return $resolved;
    }

    /**
     * The option an option value belongs to: its mapped option, or the option
     * its name from cells resolves to. Null when the name is skipped.
     *
     * @param  array<string, ?string>  $namedOptions
     */
    public function optionIdentity(string $optionKey, array $namedOptions): ?string
    {
        if (! Str::startsWith($optionKey, 'name:')) {
            return $optionKey;
        }

        return $namedOptions[Str::after($optionKey, 'name:')] ?? null;
    }
}
