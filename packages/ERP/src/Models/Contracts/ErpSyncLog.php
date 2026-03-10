<?php

namespace Lunar\ERP\Models\Contracts;

interface ErpSyncLog
{
    public function scopeByProvider($query, string $provider);

    public function scopeByType($query, string $type);

    public function scopeCompleted($query);

    public function scopeFailed($query);
}
