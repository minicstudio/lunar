<?php

namespace Lunar\Tests\Mailchimp;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Tests\Core\Stubs\User;
use Lunar\Tests\Core\TestCase as BaseTestCase;
use Mockery;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('auth.providers.users.model', User::class);
    }

    protected function mockCustomerWithUser(User $user): Customer
    {
        $users = Mockery::mock(BelongsToMany::class);
        $users->shouldReceive('first')->andReturn($user);

        $customer = Mockery::mock(Customer::class)->makePartial();
        $customer->shouldReceive('users')->andReturn($users);

        return $customer;
    }

    protected function createLanguages(): void
    {
        if (! Language::where('code', 'en')->exists()) {
            Language::factory()->create([
                'code' => 'en',
                'default' => true,
            ]);
        }

        if (! Language::where('default', false)->exists()) {
            Language::factory()->create([
                'code' => 'hu',
                'name' => 'Magyar',
                'default' => false,
            ]);
        }
    }

    protected function createCurrencies(): void
    {
        if (! Currency::where('code', 'EUR')->exists()) {
            Currency::factory()->create([
                'code' => 'EUR',
                'default' => true,
            ]);
        }

        if (! Currency::where('default', false)->exists()) {
            Currency::factory()->create([
                'code' => 'RON',
                'default' => false,
            ]);
        }
    }

    protected function createCustomerGroup(): CustomerGroup
    {
        $customerGroup = CustomerGroup::where('default', true)->first();

        if (! $customerGroup) {
            $customerGroup = CustomerGroup::factory()->create(['default' => true]);
        }

        return $customerGroup;
    }

    protected function createChannel(): Channel
    {
        $channel = Channel::where('default', true)->first();

        if (! $channel) {
            $channel = Channel::factory()->create(['default' => true]);
        }

        return $channel;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createCart(array $attributes = []): Cart
    {
        return Cart::factory()->create(array_merge([
            'currency_id' => Currency::where('default', true)->firstOrFail()->id,
            'channel_id' => $this->createChannel()->id,
        ], $attributes));
    }
}
