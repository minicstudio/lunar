<?php

namespace Lunar\Loyalty\Filament\Resources;

use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Loyalty\Filament\Resources\CustomerResource\RelationManagers\LoyaltyAccountRelationManager;

class CustomerResource extends ResourceExtension
{
    /**
     * Get the relation managers for the customer resource.
     *
     * @param  array<int, class-string>  $managers
     * @return array<int, class-string>
     */
    public function getRelations(array $managers): array
    {
        $managers[] = LoyaltyAccountRelationManager::class;

        return $managers;
    }
}
