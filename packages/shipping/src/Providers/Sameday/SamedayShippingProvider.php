<?php

namespace Lunar\Addons\Shipping\Providers\Sameday;

use Illuminate\Support\Collection;
use Lunar\Addons\Shipping\Contracts\AWBRequestBodyInterface;
use Lunar\Addons\Shipping\Contracts\ShippingProviderInterface;
use Lunar\Addons\Shipping\Contracts\TokenAwareShippingApiClient;
use Lunar\Addons\Shipping\DTOs\LockerDTO;
use Lunar\Addons\Shipping\Enums\ShippingType;
use Lunar\Addons\Shipping\Exceptions\FailedAWBGenerationException;
use Lunar\Addons\Shipping\Models\ShippingCity;
use Lunar\Addons\Shipping\Models\ShippingCounty;
use Lunar\Addons\Shipping\Models\ShippingLocker;
use Lunar\Addons\Shipping\Providers\Sameday\DTOs\SamedayAWBRecipient;
use Lunar\Addons\Shipping\Providers\Sameday\DTOs\SamedayAWBRequestBody;
use Lunar\Addons\Shipping\Providers\Sameday\DTOs\SamedayParcel;
use Lunar\Models\Country;
use Lunar\Models\Order;
use Lunar\Shipping\Models\ShippingMethod;
use Saloon\Http\Response;

class SamedayShippingProvider implements ShippingProviderInterface
{
    /**
     * The shipping API client instance.
     */
    protected TokenAwareShippingApiClient $client;

    /**
     * Create a new Sameday shipping provider instance.
     *
     * @return void
     */
    public function __construct(TokenAwareShippingApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Whether the shipping provider is enabled.
     */
    public function isEnabled(): bool
    {
        return config('lunar.shipping.sameday.enabled', false);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return __(ShippingMethod::where('code', 'sameday')->first()?->name) ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return __(ShippingMethod::where('code', 'sameday')->first()?->description) ?? '';
    }

    /**
     * Generate AWB
     *
     * @throws FailedAWBGenerationException
     */
    public function generateAWB(Order $order): array
    {
        $requestBody = $this->buildAWBGenerationRequestBody($order);

        try {
            $response = $this->client->generateAWB($requestBody);
        } catch (\Throwable $e) {
            throw new FailedAWBGenerationException('AWB generation failed: '.$e->getMessage());
        }

        if (! isset($response['awbNumber'])) {
            $message = $response['message'] ?? 'Unknown error';
            throw new FailedAWBGenerationException("No AWB number returned. {$message}");
        }

        return $response;
    }

    /**
     * Build the request body for AWB generation.
     */
    private function buildAWBGenerationRequestBody(Order $order): AWBRequestBodyInterface
    {
        $service = config('lunar.shipping.sameday.home_shipping_id');
        $oohLastMile = null; // no need for home shipping

        if ($order->meta['shippingType'] === ShippingType::LOCKER->value) {
            $service = config('lunar.shipping.sameday.locker_shipping_id');
            $oohLastMile = $order->shippingAddress->meta['locker_id'];
        }

        return new SamedayAWBRequestBody(
            pickupPoint: config('lunar.shipping.sameday.pickup_point_id'),
            packageType: 0, // 0: between 1 and 38 kg
            packageWeight: $order->packageWeight > 0 ? $order->packageWeight : 1, // kg
            service: $service,
            serviceTaxes: config('lunar.shipping.sameday.personal_drop_off') ? ['PDO'] : [],
            awbPayment: 1,
            cashOnDelivery: $order->meta['payment_type'] === 'offline' ? $order->total->decimal() : 0, // if payment type is offline, set cash on shipping to order total
            insuredValue: 0,
            thirdPartyPickup: 0,
            contactPerson: config('lunar.shipping.sameday.contact_person_id'),
            packageNumber: 1,
            clientInternalReference: $order->reference,
            awbRecipient: new SamedayAWBRecipient(
                name: $order->shippingAddress->first_name.' '.$order->shippingAddress->last_name,
                phoneNumber: $order->shippingAddress->contact_phone,
                personType: $order->shippingAddress->company_name ? 1 : 0, // 0 for individual, 1 for company
                companyName: $order->shippingAddress->company_name,
                postalCode: $order->shippingAddress->postcode,
                countyString: $order->shippingAddress->state,
                cityString: $order->shippingAddress->city,
                address: $order->shippingAddress->line_one.' '.$order->shippingAddress->line_two.' '.$order->shippingAddress->line_three,
                email: $order->shippingAddress->contact_email,
            ),
            parcels: [new SamedayParcel(
                weight: $order->packageWeight > 0 ? $order->packageWeight : 1, // kg
            )],
            observation: str($order->shippingAddress->shipping_instructions)->limit(196),
            oohLastMile: $oohLastMile, // locker id in case of easybox
        );
    }

    /**
     * Get lockers for the given county and city IDs.
     */
    public function getLockers(int $countyId, int $cityId): Collection
    {
        return ShippingLocker::where('provider', 'sameday')
            ->where('county_id', $countyId)
            ->where('city_id', $cityId)
            ->whereNull('deleted_at')
            ->get()
            ->map(fn ($locker) => new LockerDTO(
                id: $locker->provider_locker_id,
                name: $locker->name,
                address: $locker->address,
                lat: (float) $locker->lat,
                lng: (float) $locker->lng,
                county: $locker->county,
                city: $locker->city,
                countryId: Country::where('iso2', 'RO')->first()->id,
                postalCode: $locker->postal_code,
            ));
    }

    /**
     * Download the AWB PDF.
     */
    public function downloadAWBPDF(string $awbNumber): ?Response
    {
        return $this->client->downloadAWBPDF($awbNumber);
    }

    /**
     * Get the list of counties
     */
    public function getCounties(): Collection
    {
        return ShippingCounty::where('provider', 'sameday')->get();
    }

    /**
     * Get the list of cities for the given county
     */
    public function getCities(int $countyId): Collection
    {
        return ShippingCity::where('provider', 'sameday')
            ->where('county_id', $countyId)
            ->get();
    }
}
