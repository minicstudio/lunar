<?php

namespace Lunar\Loyalty\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Models\Customer;

class LoyaltyAccountFactory extends BaseFactory
{
    protected $model = LoyaltyAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::withoutEvents(fn () => Customer::factory()->create())->id,
            'balance' => 0,
            'lifetime_earned' => 0,
            'lifetime_spent' => 0,
        ];
    }
}
