<?php

namespace Lunar\ProductImport\Persisting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\Models\ProductAssociation;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\TargetField;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Structuring\ParentResolver;

/**
 * Adds the up-sells and cross-sells of the imported products after every
 * product group is written. A reference is resolved like a parent
 * reference: `id:N`, a source ID, a SKU in the import, or an existing
 * variant's SKU. Associations are added without removing any, and never to
 * the product itself. A reference that names no written product is a
 * warning on its row.
 */
class AssociationWriter
{
    /**
     * Rows read per chunk.
     */
    protected const CHUNK = 500;

    /**
     * The association type of each reference target.
     *
     * @var array<string, string>
     */
    protected const TYPES = [
        'product.upsells' => ProductAssociation::UP_SELL,
        'product.cross_sells' => ProductAssociation::CROSS_SELL,
    ];

    /**
     * Add the associations of the import's imported rows, or only of the
     * given groups' rows. Returns how many associations were added.
     *
     * @param  ?list<string>  $groupKeys
     */
    public function write(ProductImport $import, ?array $groupKeys = null): int
    {
        $resolver = new ParentResolver;
        $productIds = [];
        $references = [];

        ProductImportRow::includedIn($import)
            ->select(['id', 'source_id', 'normalized', 'overrides', 'product_id', 'status', 'group_key'])
            ->chunkById(self::CHUNK, function ($rows) use ($resolver, &$productIds, &$references, $groupKeys): void {
                foreach ($rows as $row) {
                    /** @var ProductImportRow $row */
                    $values = $row->effectiveRow();
                    $sku = $values->get(TargetField::VariantSku->value);

                    $resolver->addRow($row->id, $row->source_id, filled($sku) ? (string) $sku : null);

                    if ($row->status === RowStatus::Imported && $row->product_id !== null) {
                        $productIds[$row->id] = (int) $row->product_id;
                    }

                    if ($row->status !== RowStatus::Imported || ($groupKeys !== null && ! in_array($row->group_key, $groupKeys, true))) {
                        continue;
                    }

                    foreach (self::TYPES as $field => $type) {
                        foreach ((array) $values->get($field) as $reference) {
                            $references[] = ['row' => $row->id, 'field' => $field, 'type' => $type, 'reference' => (string) $reference];
                        }
                    }
                }
            });

        if ($references === []) {
            return 0;
        }

        $resolver->preloadExisting(array_column($references, 'reference'));

        $wanted = [];
        $unresolved = [];

        foreach ($references as $entry) {
            $parentId = $productIds[$entry['row']];
            $resolved = $resolver->resolve($entry['reference']);
            $targetId = match (true) {
                isset($resolved['row']) => $productIds[$resolved['row']] ?? null,
                isset($resolved['product']) => $resolved['product'],
                default => null,
            };

            if ($targetId === null) {
                $unresolved[$entry['row']][] = $entry;

                continue;
            }

            if ($targetId !== $parentId) {
                $wanted[$parentId.'|'.$targetId.'|'.$entry['type']] = [$parentId, $targetId, $entry['type']];
            }
        }

        $added = $this->add(array_values($wanted));

        $this->warn($unresolved);

        return $added;
    }

    /**
     * Create the associations that do not exist yet.
     *
     * @param  list<array{0: int, 1: int, 2: string}>  $wanted
     */
    protected function add(array $wanted): int
    {
        $added = 0;

        foreach (array_chunk($wanted, self::CHUNK) as $chunk) {
            $existing = ProductAssociation::query()
                ->whereIn('product_parent_id', array_unique(array_column($chunk, 0)))
                ->get(['product_parent_id', 'product_target_id', 'type'])
                ->mapWithKeys(fn (ProductAssociation $association): array => [$association->product_parent_id.'|'.$association->product_target_id.'|'.$association->type => true]);

            DB::transaction(function () use ($chunk, $existing, &$added): void {
                foreach ($chunk as [$parentId, $targetId, $type]) {
                    if ($existing->has($parentId.'|'.$targetId.'|'.$type)) {
                        continue;
                    }

                    ProductAssociation::query()->create([
                        'product_parent_id' => $parentId,
                        'product_target_id' => $targetId,
                        'type' => $type,
                    ]);

                    $added++;
                }
            });
        }

        return $added;
    }

    /**
     * Warn on each row about the references that name no written product,
     * unless the row already says so.
     *
     * @param  array<int, list<array{row: int, field: string, type: string, reference: string}>>  $unresolved
     */
    protected function warn(array $unresolved): void
    {
        foreach (array_chunk($unresolved, self::CHUNK, true) as $chunk) {
            foreach (ProductImportRow::query()->whereIn('id', array_keys($chunk))->get(['id', 'messages']) as $row) {
                $messages = $row->messages ?? [];

                foreach ($chunk[$row->id] as $entry) {
                    $code = $entry['field'] === TargetField::ProductUpsells->value ? 'upsell_not_found' : 'cross_sell_not_found';
                    $message = Str::ucfirst(__('lunarpanel.product-import::validation.messages.'.$code, ['reference' => $entry['reference']]));

                    if (collect($messages)->contains(fn (array $existing): bool => ($existing['code'] ?? null) === $code && ($existing['message'] ?? null) === $message)) {
                        continue;
                    }

                    $messages[] = ['level' => 'warning', 'code' => $code, 'field' => $entry['field'], 'message' => $message];
                }

                $row->forceFill(['messages' => $messages])->save();
            }
        }
    }
}
