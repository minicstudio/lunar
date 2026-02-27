<?php

namespace Lunar\ERP\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Lunar\Base\BaseModel;

class ErpSyncLog extends BaseModel implements Contracts\ErpSyncLog
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'sync_type', // 'products', 'orders', 'stock'
        'status', // 'pending', 'running', 'completed', 'failed'
        'started_at',
        'completed_at',
        'items_processed',
        'items_total',
        'error_message',
        'sync_data', // JSON data about the sync
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'sync_data' => 'array',
    ];

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('sync_type', $type);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
