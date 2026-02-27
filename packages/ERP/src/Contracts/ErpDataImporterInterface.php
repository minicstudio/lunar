<?php

namespace Lunar\ERP\Contracts;

interface ErpDataImporterInterface
{
    /**
     * Sync products from ERP system
     */
    public function syncProducts(?callable $progressCallback = null): array;

    /**
     * Sync order statuses from ERP system
     */
    public function syncOrderStatuses(): array;

    /**
     * Sync stock levels from ERP system
     */
    public function syncStock(?callable $progressCallback = null): array;
}
