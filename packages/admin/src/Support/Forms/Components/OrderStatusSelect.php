<?php

namespace Lunar\Admin\Support\Forms\Components;

use Filament\Forms\Components\Select;
use Lunar\Admin\Support\OrderStatus;

class OrderStatusSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->options(fn (): array => collect(array_keys(config('lunar.orders.statuses', [])))
                ->mapWithKeys(fn (string $status): array => [
                    $status => OrderStatus::getBadgeHtml($status),
                ])
                ->all())
            ->allowHtml()
            ->searchable();
    }
}
