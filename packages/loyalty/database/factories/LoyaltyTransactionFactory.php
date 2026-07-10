<?php

namespace Lunar\Loyalty\Database\Factories;

use Lunar\Database\Factories\BaseFactory;
use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Models\LoyaltyTransaction;
use Lunar\Loyalty\Support\LoyaltyEventKey;

class LoyaltyTransactionFactory extends BaseFactory
{
    protected $model = LoyaltyTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $points = $this->faker->numberBetween(100, 5000);

        return [
            'loyalty_account_id' => LoyaltyAccount::factory(),
            'type' => LoyaltyTransactionType::Earn,
            'points' => $points,
            'remaining_points' => $points,
            'event_key' => LoyaltyEventKey::adjust(),
            'meta' => ['notifications' => []],
            'expires_at' => now()->addYear(),
        ];
    }

    /**
     * Configure the factory as a spend transaction.
     */
    public function spend(int $points = 100): static
    {
        return $this->state(fn () => [
            'type' => LoyaltyTransactionType::Spend,
            'points' => $points,
            'remaining_points' => null,
            'meta' => ['allocations' => []],
            'expires_at' => null,
        ]);
    }

    /**
     * Configure the factory as an adjust transaction.
     */
    public function adjust(int $points): static
    {
        return $this->state(fn () => [
            'type' => LoyaltyTransactionType::Adjust,
            'points' => $points,
            'remaining_points' => null,
            'meta' => [],
            'expires_at' => null,
        ]);
    }
}
