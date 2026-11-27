<?php

namespace Lunar\Analytics\Database\Factories;

use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Database\Factories\BaseFactory;

class TrafficVisitFactory extends BaseFactory
{
    protected $model = TrafficVisit::class;

    /**
     * Define the model's default state: a confirmed direct visit on the home page.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'direct',
            'medium' => 'none',
            'campaign' => null,
            'referrer_host' => null,
            'landing_path' => '/',
            'confirmed_at' => fn (array $attributes) => $attributes['created_at'] ?? now(),
        ];
    }

    /**
     * A visit no browser has confirmed yet.
     */
    public function unconfirmed(): static
    {
        return $this->state(['confirmed_at' => null]);
    }
}
