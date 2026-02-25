<?php

namespace Lunar\Addons\Shipping\Services;

use Lunar\Addons\Shipping\Contracts\ShippingProviderInterface;
use Lunar\Addons\Shipping\Enums\ShippingProviderEnum;
use Lunar\Addons\Shipping\Exceptions\ShippingInitializationException;

class ShippingManager
{
    /**
     * The shipping providers.
     */
    protected array $providers;

    /**
     * Create a new shipping manager instance.
     *
     * @return void
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    /**
     * Get the shipping provider instance.
     *
     * @throws ShippingInitializationException
     */
    public function getProvider(ShippingProviderEnum $provider): ShippingProviderInterface
    {
        if (! config('lunar.shipping.enabled')) {
            throw new ShippingInitializationException('Shipping is globally disabled.');
        }

        $key = $provider->value;

        if (! config("lunar.shipping.{$key}.enabled", false)) {
            throw new ShippingInitializationException("Shipping provider [{$key}] is not enabled.");
        }

        return app(
            config("lunar.shipping.{$key}.provider_class"),
            [
                'config' => config("lunar.shipping.{$key}.client_class"),
            ]
        );
    }
}
