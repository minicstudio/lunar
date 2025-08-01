<?php

namespace Lunar\Opayo\Facades;

use Illuminate\Support\Facades\Facade;
use Lunar\Opayo\OpayoInterface;

/**
 * @method static string|null getMerchantKey()
 * @method static \Illuminate\Http\Client\PendingRequest api()
 * @method static object|null getTransaction(string $id, void $attempt = 1)
 * @method static array getAuthPayload(\Lunar\Opayo\DataTransferObjects\AuthPayloadParameters $parameters)
 *
 * @see \Lunar\Opayo\Opayo
 */
class Opayo extends Facade
{
    /**
     * Status for successful authorization.
     */
    const AUTH_SUCCESSFUL = 1;

    /**
     * Status if an order has already been placed.
     */
    const ALREADY_PLACED = 10;

    /**
     * Status when the payment requires Three D Secure authentication.
     */
    const THREED_AUTH = 20;

    /**
     * Status for when Three D Secure fails.
     */
    const THREED_SECURE_FAILED = 30;

    /**
     * Status for when authorization has failed.
     */
    const AUTH_FAILED = 40;

    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor()
    {
        return OpayoInterface::class;
    }
}
