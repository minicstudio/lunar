<?php

namespace Lunar\ERP\Models;

use Lunar\Base\BaseModel;

class ErpSyncTemp extends BaseModel implements Contracts\ErpSyncTemp
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'erp_sync_temp';

    protected $fillable = [
        'erp_id',
        'name',
        'sku',
        'price',
        'category_1',
        'category_2',
        'provider_data',
        'attributes',
        'stock',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'provider_data' => 'array',
        'attributes' => 'array',
    ];
}
