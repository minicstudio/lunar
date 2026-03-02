<?php

namespace Lunar\Addons\Shipping\Providers\Dpd\DTOs;

use Lunar\Addons\Shipping\Contracts\AWBRequestBodyInterface;

class DpdAWBRequestBody implements AWBRequestBodyInterface
{
    public function __construct(
        public string $userName,
        public string $password,
        public string $language,
        public DpdRecipient $recipient,
        public DpdService $service,
        public DpdContent $content,
        public DpdPayment $payment,
        public ?string $shipmentNote,
    ) {}

    public function toArray(): array
    {
        return [
            'userName' => $this->userName,
            'password' => $this->password,
            'language' => $this->language,
            'recipient' => $this->recipient->toArray(),
            'service' => $this->service->toArray(),
            'content' => $this->content->toArray(),
            'payment' => $this->payment->toArray(),
            'shipmentNote' => $this->shipmentNote,
        ];
    }
}
