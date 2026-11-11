<?php

namespace Lunar\Analytics\Database\Factories;

use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Database\Factories\BaseFactory;

class TrafficVisitFactory extends BaseFactory
{
    protected $model = TrafficVisit::class;

    /**
     * Define the model's default state: a direct visit on the home page.
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
        ];
    }
}
