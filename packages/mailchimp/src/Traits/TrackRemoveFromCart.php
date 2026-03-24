<?php

namespace Lunar\Mailchimp\Traits;

use Lunar\Exceptions\SilentException;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;

trait TrackRemoveFromCart
{
    /**
     * Track remove_from_cart event to Mailchimp.
     *
     * @param  int  $lineId  The ID of the cart line being removed.
     */
    protected function trackMailchimpRemoveFromCartEvent(int $lineId): void
    {
        if (! config('lunar.mailchimp.enabled', false)
            || ! config('lunar.mailchimp.track_events', true)) {
            return;
        }

        $user = auth()->user();

        if (! $user) {
            return;
        }

        $line = $this->cart->lines?->find($lineId);
        $productVariant = $line?->purchasable;

        if (! $productVariant) {
            return;
        }

        try {
            $prices = $productVariant->getPricesForDatalayerAndGTM();

            app(MailchimpSubscriberService::class)->trackEvent($user->email, 'remove_from_cart', [
                'product_id' => (string) $productVariant->product_id,
                'product_name' => $productVariant->product->translateAttribute('name'),
                'variant_id' => (string) $productVariant->id,
                'sku' => $productVariant->sku,
                'price' => (string) ($prices['sale'] ?? $prices['original']),
                'currency' => $prices['currency'],
                'quantity' => (string) ($line->quantity ?? 1),
            ]);
        } catch (\Exception $e) {
            report(new SilentException('Failed to track remove_from_cart event to Mailchimp for product '.$productVariant->product_id.'. Error: '.$e->getMessage()));
        }
    }
}
