<?php

namespace Lunar\Content\Filament\Resources\HeroResource\Pages;

use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Cache;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\Content\Filament\Resources\HeroResource;

class ListHeroes extends BaseListRecords
{
    protected static string $resource = HeroResource::class;

    /**
     * Get the default header actions for the page.
     */
    protected function getDefaultHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Persist the new order, then bust the storefront hero cache.
     *
     * Filament's bulk reorder uses a query builder update, which skips model
     * observers — so we clear the cache explicitly here.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order): void
    {
        parent::reorderTable($order);

        Cache::forget('content.heroes');
    }
}
