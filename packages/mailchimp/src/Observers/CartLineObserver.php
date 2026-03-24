<?php

namespace Lunar\Mailchimp\Observers;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Lunar\Mailchimp\Jobs\SyncCartToMailchimp;
use Lunar\Models\CartLine;

class CartLineObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the CartLine "created" event.
     * Triggered when an item is added to the cart.
     */
    public function created(CartLine $cartLine): void
    {
        $this->syncCart($cartLine);
    }

    /**
     * Handle the CartLine "updated" event.
     * Triggered when cart line quantity or meta is updated.
     */
    public function updated(CartLine $cartLine): void
    {
        $this->syncCart($cartLine);
    }

    /**
     * Handle the CartLine "deleted" event.
     * Triggered when an item is removed from the cart.
     */
    public function deleted(CartLine $cartLine): void
    {
        $this->syncCart($cartLine);
    }

    /**
     * Sync the cart to Mailchimp.
     */
    protected function syncCart(CartLine $cartLine): void
    {
        if (! config('lunar.mailchimp.enabled', false) ||
            ! config('lunar.mailchimp.sync_carts', true)) {
            return;
        }

        $cart = $cartLine->cart;

        // Only sync carts for logged-in users (abandoned cart tracking)
        if (! $cart || ! $cart->user_id) {
            return;
        }

        // Dispatch async job to sync cart to Mailchimp
        SyncCartToMailchimp::dispatch($cart);
    }
}
