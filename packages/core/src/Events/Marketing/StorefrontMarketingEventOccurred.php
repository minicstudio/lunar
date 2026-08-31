<?php

namespace Lunar\Events\Marketing;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class StorefrontMarketingEventOccurred
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $email;

    public string $eventName;

    /**
     * @var array<string, mixed>
     */
    public array $properties;

    /**
     * Stable id for the logical occurrence — set once in the constructor
     * and preserved across queue retries via serialization.
     */
    public string $eventId;

    /**
     * @param  array<string, mixed>  $properties
     * @param  string|null  $uniqueKey  Optional deterministic key from the producer.
     */
    public function __construct(
        string $email,
        string $eventName,
        array $properties = [],
        ?string $uniqueKey = null,
    ) {
        $this->email = $email;
        $this->eventName = $eventName;
        $this->properties = $properties;
        $this->eventId = $uniqueKey ?? (string) Str::uuid();
    }
}
