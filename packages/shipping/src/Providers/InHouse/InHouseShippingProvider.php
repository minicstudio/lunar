<?php

namespace Lunar\Addons\Shipping\Providers\InHouse;

use Illuminate\Support\Collection;
use Lunar\Addons\Shipping\Contracts\ShippingApiClient;
use Lunar\Addons\Shipping\Contracts\ShippingProviderInterface;
use Lunar\Models\Order;
use Lunar\Shipping\Models\ShippingMethod;
use Saloon\Http\Response;

class InHouseShippingProvider implements ShippingProviderInterface
{
    /**
     * The shipping API client instance.
     */
    protected ShippingApiClient $client;

    /**
     * Create a new In-house shipping provider instance.
     *
     * @return void
     */
    public function __construct(ShippingApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritDoc}
     */
    public function isEnabled(): bool
    {
        return config('lunar.shipping.inhouse.enabled', false);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return __(ShippingMethod::where('code', 'inhouse')->first()?->name) ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return __(ShippingMethod::where('code', 'inhouse')->first()?->description) ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function generateAWB(Order $order): array
    {
        return $this->client->generateAWB(null);
    }

    /**
     * {@inheritDoc}
     */
    public function downloadAWBPDF(string $awbNumber): ?Response
    {
        return $this->client->downloadAWBPDF($awbNumber);
    }

    /**
     * {@inheritDoc}
     */
    public function getLockers(int $countyId, int $cityId): Collection
    {
        return collect([]);
    }

    /**
     * {@inheritDoc}
     */
    public function getCounties(): Collection
    {
        return collect([]);
    }

    /**
     * {@inheritDoc}
     */
    public function getCities(int $countyId): Collection
    {
        return collect([]);
    }
}
