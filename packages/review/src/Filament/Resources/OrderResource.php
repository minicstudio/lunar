<?php

namespace Lunar\Review\Filament\Resources;

use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Review\Filament\Resources\OrderResource\RelationManagers\ChannelReviewRelationManager;
use Lunar\Review\Filament\Resources\OrderResource\RelationManagers\ProductVariantReviewRelationManager;

class OrderResource extends ResourceExtension
{
    /**
     * Get the relation managers for the order resource.
     *
     * We can't use the Pennant Feature class here because at this point in the lifecycle its services (like Auth/Hash) are not yet bootstrapped.
     */
    public function getRelations(array $managers): array
    {
        $managers[] = ProductVariantReviewRelationManager::class;
        $managers[] = ChannelReviewRelationManager::class;

        return $managers;
    }
}
