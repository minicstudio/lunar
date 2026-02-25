<?php

namespace Minic\LunarFrontend\Domains\Order\Filament\Extensions;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Lunar\Admin\Support\Extending\ViewPageExtension;
use Lunar\Addons\Shipping\Enums\ShippingProviderEnum;
use Lunar\Addons\Shipping\Exceptions\InvalidShippingProviderException;
use Lunar\Addons\Shipping\Filament\Actions\DownloadAwbPdfAction;
use Lunar\Addons\Shipping\Services\ShippingService;
use Lunar\Models\Order;

class ShippingExtension extends ViewPageExtension
{
    /**
     * Extend the order summary section with AWB field
     */
    public function extendOrderSummaryInfolist(Section $section): Section
    {
        return $section->schema([
            ...$section->getChildComponents(),
            TextEntry::make('awb_number')
                ->label('AWB')
                ->default(fn(Order $record) => $record->meta['awb'] ?? null)
                ->alignEnd(),
            TextEntry::make('shipping_method')
                ->label(__('Shipping method'))
                ->default(function (Order $record) {
                    $identifier = $record->shipping_breakdown?->items?->first()?->identifier ?? null;

                    if (! $identifier) {
                        return null;
                    }

                    try {
                        $provider = ShippingProviderEnum::fromIdentifier($identifier);
                    } catch (\ValueError $e) {
                        throw new InvalidShippingProviderException('Invalid shipping provider: ' . $identifier);
                    }

                    return app(ShippingService::class)->getName($provider);
                })
                ->alignEnd(),
        ]);
    }

    /**
     * Add actions to the header of the order view page.
     */
    public function headerActions(array $actions): array
    {
        $order = $this->caller->record;

        if ($this->hasAwb($order)) {
            $actions[] = DownloadAwbPdfAction::make();
        }

        return $actions;
    }

    /**
     * Check if AWB exists for the order.
     */
    private function hasAwb(Order $order): bool
    {
        return isset($order->meta['awb']) && ! empty($order->meta['awb']);
    }
}
