<?php

namespace Lunar\Tests\ERP;

use Lunar\Loyalty\LoyaltyServiceProvider;

class LoyaltyInteractionTestCase extends TestCase
{
    /**
     * Register loyalty after ERP so LunarInvoiceObserver runs before loyalty earn,
     * matching production boot order.
     */
    protected function getPackageProviders($app)
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
            'lunar.loyalty.earn.order_status' => 'completed',
        ]);
    }
}
