<?php

namespace Lunar\ProductImport\Staging;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Currency;
use Lunar\ProductImport\Grouping\ProductGrouper;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Normalizing\RowNormalizer;
use Lunar\ProductImport\Support\TargetFieldRegistry;

/**
 * Normalizes every data row of the included sheets from its stored raw cells
 * and assigns its product group. Corrections in `overrides` are kept.
 */
class ImportNormalizer
{
    /**
     * Rows handled per chunk.
     */
    public const CHUNK = 500;

    public function __construct(
        protected RowNormalizer $normalizer,
        protected ProductGrouper $grouper,
        protected TargetFieldRegistry $registry,
    ) {}

    /**
     * Normalize and group the import's rows. `$shouldContinue` is asked
     * between chunks, so a cancelled import stops.
     *
     * @param  ?callable(): bool  $shouldContinue
     */
    public function normalize(ProductImport $import, ?callable $shouldContinue = null): bool
    {
        $factor = (int) (Currency::getDefault()?->factor ?? 100);
        $defaultLocale = $this->registry->defaultLanguage();

        foreach ($import->sheets()->where('skipped', false)->get() as $sheet) {
            $mapping = $sheet->sheetMapping();

            $completed = $sheet->rows()
                ->where('row_number', '>', $sheet->header_row)
                ->select(['id', 'row_number', 'raw', 'typed'])
                ->chunkById(self::CHUNK, function ($rows) use ($sheet, $mapping, $factor, $defaultLocale, $shouldContinue): bool {
                    if ($shouldContinue !== null && ! $shouldContinue()) {
                        return false;
                    }

                    DB::transaction(function () use ($rows, $sheet, $mapping, $factor, $defaultLocale): void {
                        foreach ($rows as $row) {
                            /** @var ProductImportRow $row */
                            $normalized = $this->normalizer->normalize($row->raw, $mapping, $factor, $row->typed ?? []);

                            ProductImportRow::query()->whereKey($row->id)->update([
                                'normalized' => json_encode($normalized->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'group_key' => $this->grouper->groupKey($sheet->id, $row->row_number, $normalized, $mapping, $defaultLocale),
                            ]);
                        }
                    });

                    return true;
                });

            if ($completed === false) {
                return false;
            }
        }

        return true;
    }
}
