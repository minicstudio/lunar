<?php

namespace Lunar\Klaviyo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class DeleteAllProductsFromKlaviyo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $pageSize = 100,
    ) {}

    public function handle(KlaviyoCatalogService $catalogService): void
    {
        if (! KlaviyoAvailability::enabled()) {
            KlaviyoLogger::warning('Delete all products job skipped — Klaviyo not enabled');

            return;
        }

        $pageSize = max(1, min(100, $this->pageSize));

        KlaviyoLogger::info('Delete all products job started', [
            'page_size' => $pageSize,
        ]);

        try {
            $result = $catalogService->deleteAllCatalogItems($pageSize);
        } catch (FailedKlaviyoSyncException $e) {
            KlaviyoLogger::error('Delete all products job failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        KlaviyoLogger::info('Delete all products job finished', [
            'deleted' => $result['deleted'],
            'jobs' => $result['jobs'],
        ]);
    }
}
