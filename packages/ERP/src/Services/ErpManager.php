<?php

namespace Lunar\ERP\Services;

use Lunar\ERP\Contracts\ErpProviderInterface;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Exceptions\ErpInitializationException;

class ErpManager
{
    /**
     * The ERP providers.
     */
    protected array $providers;

    /**
     * Create a new ERP manager instance.
     */
    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    /**
     * Get the ERP provider instance.
     *
     * @throws ErpInitializationException
     */
    public function getProvider(ErpProviderEnum $provider): ErpProviderInterface
    {
        if (! config('lunar.erp.enabled')) {
            throw new ErpInitializationException('ERP is globally disabled.');
        }

        $key = $provider->value;

        if (! config("lunar.erp.{$key}.enabled", false)) {
            throw new ErpInitializationException("ERP provider [{$key}] is not enabled.");
        }

        return app(
            config("lunar.erp.{$key}.provider_class"),
            [
                'config' => config("lunar.erp.{$key}.client_class"),
            ]
        );
    }
}
