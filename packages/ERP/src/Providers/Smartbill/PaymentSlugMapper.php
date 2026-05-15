<?php

namespace Lunar\ERP\Providers\Smartbill;

class PaymentSlugMapper
{
    public function __invoke(?string $paymentOption): string
    {
        return match ($paymentOption) {
            'offline' => 'ramburs',
            'cash-on-delivery' => 'ramburs',
            'hosted-payment' => 'card',
            'stripe-card' => 'card',
            default => '',
        };
    }
}
