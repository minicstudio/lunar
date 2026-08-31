<?php

namespace Lunar\Events\Marketing;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Customer;

class CustomerMarketingProfileUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $properties  Business properties (e.g. language).
     */
    public function __construct(
        public Customer $customer,
        public array $properties = [],
    ) {}
}
