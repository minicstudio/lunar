<?php

namespace Lunar\Addons\Shipping\Contracts;

use Illuminate\Support\Collection;
use Lunar\Models\Order;
use Saloon\Http\Response;

interface ShippingProviderInterface
{
    /**
     * Whether the shipping provider is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get the translatable name for the shipping provider.
     */
    public function getName(): string;

    /**
     * Get the translatable description for the shipping provider.
     */
    public function getDescription(): string;

    /**
     * Generate AWB
     */
    public function generateAWB(Order $order): array;

    /**
     * Download the AWB PDF.
     *
     * @throws \Lunar\Addons\Shipping\Exceptions\FailedAWBGenerationException
     */
    public function downloadAWBPDF(string $awbNumber): ?Response;

    /**
     * Get lockers for the given county and city IDs.
     */
    public function getLockers(int $countyId, int $cityId): Collection;

    /**
     * Get the list of counties
     */
    public function getCounties(): Collection;

    /**
     * Get the list of cities for the given county
     */
    public function getCities(int $countyId): Collection;
}
