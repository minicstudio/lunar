<?php

namespace Lunar\Tests\Loyalty;

use Lunar\Loyalty\LoyaltyServiceProvider;
use Lunar\Tests\Core\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            LoyaltyServiceProvider::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'lunar.loyalty.enabled' => true,
            'lunar.loyalty.currency.earn_ratio' => 100,
            'lunar.loyalty.currency.redeem_ratio' => 1,
            'lunar.loyalty.currency.min_redeem' => 100,
            'lunar.loyalty.currency.max_redeem_percent' => 50,
            'lunar.loyalty.expiration.months' => 12,
            'lunar.loyalty.events.registration' => null,
        ]);
    }
}
