<?php

namespace Lunar\ProductImport\Taxonomy;

use Lunar\ProductImport\Enums\ValueType;

/**
 * One distinct value found in the staged rows, with where it occurs.
 */
final class ExtractedValue
{
    /**
     * @param  list<string>  $path  The category levels, or the value itself.
     * @param  array<string, true>  $sheets  Sheet names.
     * @param  array<string, true>  $groups  Product group keys.
     */
    public function __construct(
        public ValueType $type,
        public string $optionKey,
        public string $normalizedKey,
        public string $sourceValue,
        public array $path,
        public array $sheets = [],
        public array $groups = [],
        public int $variantCount = 0,
    ) {}

    /**
     * The unique key of the value within the import.
     */
    public function key(): string
    {
        return $this->type->value."\0".$this->optionKey."\0".$this->normalizedKey;
    }
}
