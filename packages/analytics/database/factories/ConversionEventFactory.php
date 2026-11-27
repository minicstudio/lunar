<?php

namespace Lunar\Analytics\Database\Factories;

use Lunar\Analytics\Conversions\Enums\ConversionEventType;
use Lunar\Analytics\Conversions\Models\ConversionEvent;
use Lunar\Database\Factories\BaseFactory;
use Lunar\Models\Cart;

class ConversionEventFactory extends BaseFactory
{
    protected $model = ConversionEvent::class;

    /**
     * Define the model's default state: an add to cart.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ConversionEventType::AddToCart,
            'cart_id' => Cart::factory(),
        ];
    }

    /**
     * A cart start.
     */
    public function cartStarted(): static
    {
        return $this->state(['type' => ConversionEventType::CartStarted]);
    }

    /**
     * A checkout start.
     */
    public function beginCheckout(): static
    {
        return $this->state(['type' => ConversionEventType::BeginCheckout]);
    }
}
