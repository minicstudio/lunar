<?php

namespace Lunar\ProductImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Jobs\Concerns\UsesImportQueue;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Persisting\AssociationWriter;
use Lunar\ProductImport\Persisting\CommitChain;

/**
 * Adds the up-sells and cross-sells once every product group is written,
 * then queues the image downloads before the result is recorded.
 */
class CommitAssociations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, UsesImportQueue;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  ?list<string>  $groupKeys  The groups of a retry; null for every group.
     */
    public function __construct(public int $importId, public ?array $groupKeys = null)
    {
        $this->useImportQueue();
    }

    public function handle(AssociationWriter $writer, CommitChain $chain): void
    {
        $import = ProductImport::query()->find($this->importId);

        if (! $import || $import->status !== ImportStatus::Importing) {
            return;
        }

        $added = $writer->write($import, $this->groupKeys);

        $stats = $import->stats ?? [];
        $stats['associations'] = (int) ($stats['associations'] ?? 0) + $added;
        $import->forceFill(['stats' => $stats])->save();

        $images = $chain->imageBatch($import, $this->groupKeys);

        if ($images !== null) {
            $this->prependToChain($images);
        }
    }
}
