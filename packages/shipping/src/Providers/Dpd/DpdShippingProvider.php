<?php

namespace Lunar\Addons\Shipping\Providers\Dpd;

use Illuminate\Support\Collection;
use Lunar\Addons\Shipping\Contracts\AWBRequestBodyInterface;
use Lunar\Addons\Shipping\Contracts\ShippingApiClient;
use Lunar\Addons\Shipping\Contracts\ShippingProviderInterface;
use Lunar\Addons\Shipping\Exceptions\FailedAWBGenerationException;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdAdditionalServices;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdAddress;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdAWBRequestBody;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdCODAdditionalService;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdContent;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdPayment;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdPhoneNumber;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdRecipient;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdService;
use Lunar\Models\Order;
use Lunar\Shipping\Models\ShippingMethod;
use Saloon\Http\Response;

class DpdShippingProvider implements ShippingProviderInterface
{
    /**
     * The shipping API client instance.
     */
    protected ShippingApiClient $client;

    /**
     * Create a new DPD shipping provider instance.
     *
     * @return void
     */
    public function __construct(ShippingApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Whether the shipping provider is enabled.
     */
    public function isEnabled(): bool
    {
        return config('lunar.shipping.dpd.enabled', false);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return __(ShippingMethod::where('code', 'dpd')->first()?->name) ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return __(ShippingMethod::where('code', 'dpd')->first()?->description) ?? '';
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

        if (! isset($response['id'])) {
            $message = $response['message'] ?? 'Unknown error';
            throw new FailedAWBGenerationException("No AWB number returned. {$message}");
        }

        $response['awbNumber'] = $response['id'];

        return $response;
    }

    /**
     * Build the request body for AWB generation.
     */
    private function buildAWBGenerationRequestBody(Order $order): AWBRequestBodyInterface
    {
        $userName = config('lunar.shipping.dpd.username');
        $password = config('lunar.shipping.dpd.password');
        $service = config('lunar.shipping.dpd.service_id');
        $contents = config('lunar.shipping.dpd.contents');
        $package = config('lunar.shipping.dpd.package');

        $companyName = $order->shippingAddress->company_name;
        $fullName = $order->shippingAddress->first_name.' '.$order->shippingAddress->last_name;

        $clientName = ! empty($companyName) ? $companyName : $fullName;
        $contactName = ! empty($companyName) ? $fullName : null;
        $privatePerson = empty($companyName);

        return new DpdAWBRequestBody(
            userName: $userName,
            password: $password,
            language: 'EN',
            recipient: new DpdRecipient(
                phone: new DpdPhoneNumber(
                    number: $order->shippingAddress->contact_phone,
                ),
                clientName: $clientName,
                contactName: $contactName,
                email: $order->shippingAddress->contact_email,
                privatePerson: $privatePerson,
                address: new DpdAddress(
                    siteName: $order->shippingAddress->city,
                    postCode: $order->shippingAddress->postcode,
                    addressNote: str($order->shippingAddress->line_one)->limit(196),
                ),
            ),
            service: new DpdService(
                autoAdjustPickupDate: true,
                additionalServices: new DpdAdditionalServices(
                    cod: new DpdCODAdditionalService(
                        amount: $order->meta['payment_type'] === 'offline' ? $order->total->decimal() : 0
                    )
                ),
                serviceId: $service,
            ),
            content: new DpdContent(
                parcelsCount: 1,
                totalWeight: $order->packageWeight > 0 ? $order->packageWeight : 1, // kg
                contents: $contents,
                package: $package,
            ),
            payment: new DpdPayment(
                courierServicePayer: 'SENDER',
                packagePayer: 'RECIPIENT',
            ),
            shipmentNote: str($order->shippingAddress->shipping_instructions)->limit(196),
        );
    }

    /**
     * Download the AWB PDF.
     */
    public function downloadAWBPDF(string $awbNumber): ?Response
    {
        return $this->client->downloadAWBPDF($awbNumber);
    }

    /**
     * Get lockers for the given county and city IDs.
     */
    public function getLockers(int $countyId, int $cityId): Collection
    {
        return collect([]);
    }

    /**
     * Get the list of counties
     */
    public function getCounties(): Collection
    {
        return collect([]);
    }

    /**
     * Get the list of cities for the given county
     */
    public function getCities(int $countyId): Collection
    {
        return collect([]);
    }
}
