<?php

namespace Lunar\Addons\Shipping\Services;

use Illuminate\Support\Collection;
use Lunar\Addons\Shipping\Connectors\NominatimConnector;
use Lunar\Addons\Shipping\Contracts\ShippingProviderInterface;
use Lunar\Addons\Shipping\Enums\ShippingProviderEnum;
use Lunar\Addons\Shipping\Exceptions\OrderMissingShippingProviderException;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\GeocodeCountyRequest;
use Lunar\Models\Order;
use Saloon\Http\Response;

class ShippingService
{
    /**
     * Get the shipping provider for the given order.
     *
     * @throws OrderMissingShippingProviderException
     */
    private function getShippingProviderOfOrder(Order $order): ShippingProviderInterface
    {
        $identifier = $order->shipping_breakdown?->items?->first()?->identifier ?? null;

        if (! $identifier) {
            throw new OrderMissingShippingProviderException('Order does not have a shipping provider selected.');
        }

        $provider = ShippingProviderEnum::fromIdentifier($identifier);
        $shippingManager = new ShippingManager([$provider]);

        return $shippingManager->getProvider($provider);
    }

    /**
     * Generate an AWB for the given order.
     *
     * @throws OrderMissingShippingProviderException
     */
    public function generateAWB(Order $order): void
    {
        $shippingProvider = $this->getShippingProviderOfOrder($order);

        $response = $shippingProvider->generateAWB($order);

        // Save AWB to order without triggering events again
        Order::withoutEvents(function () use ($order, $response) {
            $order->meta['awb'] = $response['awbNumber'];
            $order->save();
        });
    }

    /**
     * Download the AWB PDF for the given order.
     *
     * @throws OrderMissingShippingProviderException
     */
    public function downloadAWBPDF(Order $order): Response
    {
        $shippingProvider = $this->getShippingProviderOfOrder($order);

        $awbNumber = $this->getAwb($order);

        return $shippingProvider->downloadAWBPDF($awbNumber);
    }

    /**
     * Get the AWB (tracking number) from order meta.
     */
    public function getAwb(Order $order): ?string
    {
        return $order->meta['awb'] ?? null;
    }

    /**
     * Get the tracking URL for the order.
     */
    public function getTrackingUrl(Order $order): ?string
    {
        $shippingItem = $order->shipping_breakdown?->items?->first();
        $identifier = $shippingItem?->identifier;
        $awb = $this->getAwb($order);

        if ($identifier && $awb) {
            $trackingBaseUrl = config("lunar.shipping.{$identifier}.provider_page_url");

            return $trackingBaseUrl ? $trackingBaseUrl.urlencode($awb) : null;
        }

        return null;
    }

    /**
     * Get the shipping provider name.
     */
    public function getShippingProviderName(Order $order): ?string
    {
        return $order->shipping_breakdown?->items?->first()?->name ?? null;
    }

    /**
     * Get the shipping provider instance for the given provider enum.
     */
    private function getShippingProvider(ShippingProviderEnum $provider): ShippingProviderInterface
    {
        $shippingManager = new ShippingManager([$provider->value]);

        return $shippingManager->getProvider($provider);
    }

    /**
     * Get name for a specific shipping provider
     */
    public function getName(ShippingProviderEnum $provider): string
    {
        return $this->getShippingProvider($provider)->getName();
    }

    /**
     * Get description for a specific shipping provider
     */
    public function getDescription(ShippingProviderEnum $provider): string
    {
        return $this->getShippingProvider($provider)->getDescription();
    }

    /**
     * Get counties for a specific shipping provider
     */
    public function getCounties(ShippingProviderEnum $provider): Collection
    {
        return $this->getShippingProvider($provider)->getCounties();
    }

    /**
     * Get cities for a specific shipping provider
     */
    public function getCities(ShippingProviderEnum $provider, int $countyId): Collection
    {
        return $this->getShippingProvider($provider)->getCities($countyId);
    }

    /**
     * Get lockers for a specific shipping provider, county, and city.
     */
    public function getLockers(ShippingProviderEnum $provider, int $countyId, int $cityId): Collection
    {
        return $this->getShippingProvider($provider)->getLockers($countyId, $cityId);
    }

    /**
     * Get the latitude and longitude of a specific location using Nominatim API.
     */
    public function getLatLngOfLocation(string $location): array
    {
        $connector = new NominatimConnector;
        $request = new GeocodeCountyRequest($location);
        $response = $connector->send($request);
        $data = $response->json();

        return [
            'lat' => ! empty($data[0]) ? $data[0]['lat'] : null,
            'lng' => ! empty($data[0]) ? $data[0]['lon'] : null,
        ];
    }
}
