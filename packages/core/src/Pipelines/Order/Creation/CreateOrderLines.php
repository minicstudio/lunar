<?php

namespace Lunar\Pipelines\Order\Creation;

use Closure;
use Illuminate\Support\Facades\App;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Contracts\OrderLine as OrderLineContract;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Utils\Arr;

class CreateOrderLines
{
    /**
     * @param  Closure(OrderContract): mixed  $next
     */
    public function handle(OrderContract $order, Closure $next): mixed
    {
        /** @var Order $order */
        if (! $order->id) {
            $order->save();
        }

        $cart = $order->cart;

        $cart->recalculate();

        foreach ($cart->lines as $cartLine) {
            $this->recordPreorder($cartLine);

            /** @var OrderLine $orderLine */
            $orderLine = $order->lines->first(function ($line) use ($cartLine) {
                $diff = Arr::diff($line->meta, $cartLine->meta);

                return empty($diff->new) &&
                    empty($diff->edited) &&
                    empty($diff->removed) &&
                    $line->purchasable_type == $cartLine->purchasable_type &&
                    $line->purchasable_id == $cartLine->purchasable_id;
            }) ?: App::make(OrderLineContract::class);

            $orderLine->fill([
                'order_id' => $order->id,
                'purchasable_type' => $cartLine->purchasable_type,
                'purchasable_id' => $cartLine->purchasable_id,
                'type' => $cartLine->purchasable->getType(),
                'description' => $cartLine->purchasable->getDescription(),
                'option' => $cartLine->purchasable->getOption(),
                'identifier' => $cartLine->purchasable->getIdentifier(),
                'unit_price' => $cartLine->unitPrice->value,
                'unit_quantity' => $cartLine->purchasable->getUnitQuantity(),
                'quantity' => $cartLine->quantity,
                'sub_total' => $cartLine->subTotal->value,
                'discount_total' => $cartLine->discountTotal?->value,
                'tax_breakdown' => $cartLine->taxBreakdown,
                'tax_total' => $cartLine->taxAmount->value,
                'total' => $cartLine->total->value,
                'notes' => null,
                'meta' => $cartLine->meta,
            ])->save();
        }

        return $next($order->refresh());
    }

    /**
     * Record a preorder variant on the cart line before the order line is matched.
     *
     * The key is never removed, so a variant that later leaves preorder stays recorded.
     */
    private function recordPreorder(object $cartLine): void
    {
        $purchasable = $cartLine->purchasable ?? null;

        if (! is_object($purchasable) || ! method_exists($purchasable, 'isPreorder') || ! $purchasable->isPreorder()) {
            return;
        }

        $meta = $this->lineMeta($cartLine->meta ?? null);

        if (($meta['preorder'] ?? null) === true) {
            return;
        }

        $meta['preorder'] = true;
        $cartLine->meta = $meta;
        $cartLine->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function lineMeta(mixed $meta): array
    {
        if ($meta instanceof \ArrayObject) {
            return $meta->getArrayCopy();
        }

        if (is_array($meta)) {
            return $meta;
        }

        return [];
    }
}
