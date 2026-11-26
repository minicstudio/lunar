<?php

namespace Lunar\ProductImport\Jobs\Concerns;

/**
 * Puts a job on the configured import queue and connection.
 */
trait UsesImportQueue
{
    /**
     * Apply `lunar.product-import.queue`.
     */
    protected function useImportQueue(): void
    {
        if ($queue = config('lunar.product-import.queue.name')) {
            $this->onQueue($queue);
        }

        if ($connection = config('lunar.product-import.queue.connection')) {
            $this->onConnection($connection);
        }
    }
}
