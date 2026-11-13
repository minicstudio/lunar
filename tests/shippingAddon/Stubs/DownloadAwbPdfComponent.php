<?php

namespace Lunar\Tests\shippingAddon\Stubs;

use Livewire\Component;
use Lunar\Addons\Shipping\Filament\Actions\DownloadAwbPdfAction;
use Lunar\Models\Order;

class DownloadAwbPdfComponent extends Component
{
    public int $orderId;

    public function mount(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function download(): void
    {
        DownloadAwbPdfAction::download(Order::query()->findOrFail($this->orderId), $this);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
