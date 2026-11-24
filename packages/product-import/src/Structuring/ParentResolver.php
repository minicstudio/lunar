<?php

namespace Lunar\ProductImport\Structuring;

use Illuminate\Support\Str;
use Lunar\Models\ProductVariant;

/**
 * Resolves a reference to a product, in this order: `id:N` names the row
 * whose source ID is N; another reference names the row with that source ID,
 * else the row with that SKU, both on any included sheet, else the product of
 * the existing variant with that SKU. Parent references, up-sells, and
 * cross-sells are resolved this way.
 */
class ParentResolver
{
    /**
     * Row ids by source ID.
     *
     * @var array<string, int>
     */
    protected array $bySourceId = [];

    /**
     * Row ids by SKU.
     *
     * @var array<string, int>
     */
    protected array $bySku = [];

    /**
     * Product ids of existing variants by SKU; null when none exists.
     *
     * @var array<string, ?int>
     */
    protected array $existing = [];

    /**
     * Index a row of the import. The first row with a source ID or a SKU wins.
     */
    public function addRow(int $rowId, ?string $sourceId, ?string $sku): void
    {
        if ($sourceId !== null && $sourceId !== '') {
            $this->bySourceId[$sourceId] ??= $rowId;
        }

        if ($sku !== null && $sku !== '') {
            $this->bySku[$sku] ??= $rowId;
        }
    }

    /**
     * Look up, in one query per chunk, the existing variants that references
     * not found in the import may name.
     *
     * @param  iterable<string>  $references
     */
    public function preloadExisting(iterable $references): void
    {
        $skus = [];

        foreach ($references as $reference) {
            [$isId, $value] = static::parse($reference);

            if (! $isId && $value !== '' && ! isset($this->bySourceId[$value]) && ! isset($this->bySku[$value]) && ! array_key_exists($value, $this->existing)) {
                $skus[$value] = true;
            }
        }

        foreach (array_chunk(array_keys($skus), 500) as $chunk) {
            $chunk = array_map('strval', $chunk);
            $found = ProductVariant::withTrashed()->whereIn('sku', $chunk)->orderBy('id')->get(['sku', 'product_id']);

            foreach ($chunk as $sku) {
                $this->existing[$sku] = null;
            }

            foreach ($found as $variant) {
                $this->existing[(string) $variant->sku] ??= (int) $variant->product_id;
            }
        }
    }

    /**
     * The row or the existing product a reference names, or null.
     *
     * @return ?array{row?: int, product?: int}
     */
    public function resolve(string $reference): ?array
    {
        [$isId, $value] = static::parse($reference);

        if ($value === '') {
            return null;
        }

        if ($isId) {
            return isset($this->bySourceId[$value]) ? ['row' => $this->bySourceId[$value]] : null;
        }

        if (isset($this->bySourceId[$value])) {
            return ['row' => $this->bySourceId[$value]];
        }

        if (isset($this->bySku[$value])) {
            return ['row' => $this->bySku[$value]];
        }

        if (! array_key_exists($value, $this->existing)) {
            $this->preloadExisting([$value]);
        }

        return $this->existing[$value] !== null ? ['product' => $this->existing[$value]] : null;
    }

    /**
     * Whether a reference is `id:N`, and its value.
     *
     * @return array{0: bool, 1: string}
     */
    public static function parse(string $reference): array
    {
        $reference = Str::trim($reference);

        if (Str::isMatch('/^id:\s*\S/i', $reference)) {
            return [true, Str::trim(Str::after($reference, ':'))];
        }

        return [false, $reference];
    }
}
