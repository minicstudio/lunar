<?php

namespace Lunar\ProductImport\Taxonomy;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Lunar\ProductImport\Enums\MatchType;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Normalizing\RowDiscount;
use Lunar\ProductImport\Support\KeyNormalizer;

/**
 * Matches a value to the catalog in memory: exact, partial (categories whose
 * first levels exist), similar at or above the configured share, or none.
 * Exact values default to the existing record, partial paths to creating the
 * missing levels under the deepest match, similar values need a decision,
 * and new values default to being created. Option names from cells match
 * product options by name in any language, and their values the values of
 * the option the name uses. Properties match the product type's text
 * attributes and default to skip, because the import creates no attributes.
 * Discounts match an eligible discount with the same kind, value, start, and
 * end, and are otherwise created; they are never similar.
 */
class ValueMatcher
{
    /**
     * Match one value.
     */
    public function match(ExtractedValue $value, CatalogSnapshot $catalog): MatchResult
    {
        return match ($value->type) {
            ValueType::Brand => $this->matchName($value->normalizedKey, $catalog->brands, $value->sourceValue),
            ValueType::Option => $this->matchName($value->normalizedKey, $catalog->options, $value->sourceValue),
            ValueType::OptionValue => $this->matchOptionValue($value, $catalog),
            ValueType::Category => $this->matchCategory($value, $catalog),
            ValueType::Property => $this->matchProperty($value, $catalog),
            ValueType::Discount => $this->matchDiscount($value, $catalog),
        };
    }

    /**
     * The similarity of two normalized keys: 1 − edit distance / longer length.
     */
    public function similarity(string $a, string $b): float
    {
        $longer = max(strlen($a), strlen($b));

        return $longer === 0 ? 1.0 : round(1 - levenshtein($a, $b) / $longer, 4);
    }

    /**
     * Match a name against records keyed by id.
     *
     * @param  array<int, string|list<string>>  $records  Id => normalized name(s).
     */
    protected function matchName(string $key, array $records, string $source): MatchResult
    {
        [$closest, $score] = $this->closest($key, $records);

        if ($closest !== null && $score >= 1.0) {
            return new MatchResult(MatchType::Exact, $closest, 1.0, ValueAction::UseExisting, $closest);
        }

        if ($closest !== null && $score >= $this->threshold()) {
            return new MatchResult(MatchType::Similar, $closest, $score);
        }

        return new MatchResult(MatchType::None, action: ValueAction::Create, createName: $source);
    }

    /**
     * Option values match within their existing option; values of a new
     * option are always new.
     */
    protected function matchOptionValue(ExtractedValue $value, CatalogSnapshot $catalog): MatchResult
    {
        $optionId = match (true) {
            Str::startsWith($value->optionKey, 'option:') => (int) Str::after($value->optionKey, 'option:'),
            Str::startsWith($value->optionKey, 'name:') => $catalog->namedOptions[Str::after($value->optionKey, 'name:')] ?? null,
            default => null,
        };

        if ($optionId === null) {
            return new MatchResult(MatchType::None, action: ValueAction::Create, createName: $value->sourceValue);
        }

        return $this->matchName($value->normalizedKey, $catalog->optionValues[$optionId] ?? [], $value->sourceValue);
    }

    /**
     * Properties go into an existing text attribute of the product type with
     * the same name in any language. The import does not create attributes,
     * so any other property is skipped until staff choose an attribute.
     */
    protected function matchProperty(ExtractedValue $value, CatalogSnapshot $catalog): MatchResult
    {
        $productTypeId = (int) Str::after($value->optionKey, 'type:');
        [$closest, $score] = $this->closest($value->normalizedKey, $catalog->attributes[$productTypeId] ?? []);

        return match (true) {
            $closest !== null && $score >= 1.0 => new MatchResult(MatchType::Exact, $closest, 1.0, ValueAction::UseExisting, $closest),
            $closest !== null && $score >= $this->threshold() => new MatchResult(MatchType::Similar, $closest, $score, ValueAction::Skip),
            default => new MatchResult(MatchType::None, action: ValueAction::Skip),
        };
    }

    /**
     * Use an eligible discount with the same percent, or the same amount for
     * the default currency, and the same start and end; otherwise create one
     * with the file's values. An empty start matches a discount that has
     * started, and an empty end only a discount without an end.
     */
    protected function matchDiscount(ExtractedValue $value, CatalogSnapshot $catalog): MatchResult
    {
        $discount = RowDiscount::fromArray($value->details);

        foreach ($discount !== null ? $catalog->discounts : [] as $id => $existing) {
            if ($this->sameDiscount($discount, $existing)) {
                return new MatchResult(MatchType::Exact, $id, 1.0, ValueAction::UseExisting, $id);
            }
        }

        return new MatchResult(MatchType::None, action: ValueAction::Create, createName: $value->sourceValue, createDetails: $value->details);
    }

    /**
     * Whether an existing discount takes the same off between the same dates,
     * to the minute.
     *
     * @param  array{kind: string, value: int|float, starts_at: ?string, ends_at: ?string}  $existing
     */
    public function sameDiscount(RowDiscount $discount, array $existing): bool
    {
        $minute = fn (?string $date): ?string => $date !== null ? CarbonImmutable::parse($date)->format('Y-m-d H:i') : null;

        $sameValue = $discount->kind === RowDiscount::AMOUNT
            ? (int) $existing['value'] === (int) $discount->value
            : abs((float) $existing['value'] - (float) $discount->value) < 0.0001;

        $sameStart = $discount->startsAt === null
            ? $existing['starts_at'] !== null && CarbonImmutable::parse($existing['starts_at'])->lessThanOrEqualTo(CarbonImmutable::now())
            : $minute($existing['starts_at']) === $minute($discount->startsAt);

        $sameEnd = $discount->endsAt === null
            ? $existing['ends_at'] === null
            : $minute($existing['ends_at']) === $minute($discount->endsAt);

        return $existing['kind'] === $discount->kind && $sameValue && $sameStart && $sameEnd;
    }

    /**
     * Match a category path level by level under the matched parent,
     * starting at the root of the collection group.
     */
    protected function matchCategory(ExtractedValue $value, CatalogSnapshot $catalog): MatchResult
    {
        $levels = array_map(fn (string $level): string => KeyNormalizer::key($level), $value->path);
        $parent = null;

        foreach ($levels as $depth => $level) {
            $siblings = [];

            foreach ($catalog->children[(string) ($parent ?? '')] ?? [] as $childId) {
                $siblings[$childId] = $catalog->collections[$childId]['key'];
            }

            [$closest, $score] = $this->closest($level, $siblings);

            if ($closest !== null && $score >= 1.0) {
                $parent = $closest;

                continue;
            }

            if ($closest !== null && $score >= $this->threshold()) {
                return new MatchResult(MatchType::Similar, $closest, $score);
            }

            $missing = implode(' > ', array_slice($value->path, $depth));

            return $depth === 0
                ? new MatchResult(MatchType::None, action: ValueAction::Create, createName: $missing)
                : new MatchResult(MatchType::Partial, $parent, null, ValueAction::Create, null, $missing, $parent);
        }

        return new MatchResult(MatchType::Exact, $parent, 1.0, ValueAction::UseExisting, $parent);
    }

    /**
     * The record closest to the key, and its similarity.
     *
     * @param  array<int, string|list<string>>  $records
     * @return array{0: ?int, 1: float}
     */
    protected function closest(string $key, array $records): array
    {
        $best = null;
        $bestScore = 0.0;

        foreach ($records as $id => $names) {
            foreach ((array) $names as $name) {
                $score = $name === $key ? 1.0 : $this->similarity($key, $name);

                if ($score > $bestScore) {
                    $best = $id;
                    $bestScore = $score;
                }

                if ($score >= 1.0) {
                    return [$id, 1.0];
                }
            }
        }

        return [$best, $bestScore];
    }

    /**
     * The share from which a value counts as similar.
     */
    protected function threshold(): float
    {
        return (float) config('lunar.product-import.matching.similar_threshold', 0.70);
    }
}
