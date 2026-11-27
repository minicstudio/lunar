<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Support\Facades\Cache;
use Lunar\ProductImport\Actions\Concerns\GuardsImportStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Persisting\CommitChain;
use Lunar\ProductImport\Queries\PreviewSummary;

/**
 * Starts writing an approved import to the catalog. Rows with errors are
 * skipped after staff confirm it. Only one import writes at a time.
 */
class StartImport
{
    use GuardsImportStatus;

    /**
     * The lock held while an import switches to importing.
     */
    public const LOCK = 'lunar-product-import:commit';

    public function __construct(
        protected PreviewSummary $summary,
        protected SkipRows $skipRows,
        protected CommitChain $chain,
    ) {}

    /**
     * @param  bool  $confirmed  Whether staff confirmed skipping the rows with errors.
     */
    public function handle(ProductImport $import, bool $confirmed = false): ProductImport
    {
        $this->ensureStatus($import, [ImportStatus::PreviewReady]);

        $totals = $this->summary->totals($import);

        if ($totals['importable'] === 0) {
            throw ImportActionRefused::because('nothing_to_import');
        }

        if ($totals['errors'] > 0 && ! $confirmed) {
            throw new ImportActionRefused($this->label($import));
        }

        $lock = Cache::lock(self::LOCK, 30);

        if (! $lock->get()) {
            throw ImportActionRefused::because('another_import_running');
        }

        try {
            $running = ProductImport::query()
                ->whereKeyNot($import->getKey())
                ->where('status', ImportStatus::Importing)
                ->exists();

            if ($running) {
                throw ImportActionRefused::because('another_import_running');
            }

            if ($totals['errors'] > 0) {
                $this->skipRows->skip($import, ProductImportRow::includedIn($import)->where('status', RowStatus::Error)->get());
            }

            $groups = $this->chain->importableGroups($import);

            if ($groups === []) {
                throw ImportActionRefused::because('nothing_to_import');
            }

            $started = $import->transitionTo(ImportStatus::Importing, ImportStatus::PreviewReady, [
                'started_at' => now(),
                'finished_at' => null,
                'failure_message' => null,
                'stats' => [...($import->stats ?? []), 'total_groups' => count($groups), 'processed_groups' => 0],
            ]);

            if (! $started) {
                throw ImportActionRefused::because('action_not_completed');
            }
        } finally {
            $lock->release();
        }

        $this->chain->dispatch($import);

        return $import;
    }

    /**
     * The start action's text: how many variants will be imported, and that
     * rows with errors are skipped when there are any.
     */
    public function label(ProductImport $import): string
    {
        $totals = $this->summary->totals($import);

        return trans_choice(
            $totals['errors'] > 0
                ? 'lunarpanel.product-import::preview.start.skip_errors'
                : 'lunarpanel.product-import::preview.start.import',
            $totals['importable'],
            ['count' => $totals['importable']],
        );
    }
}
