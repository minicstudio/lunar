<?php

namespace Lunar\Actions\Orders;

use Lunar\Models\ProductVariant;

final class OrderProductLineChange
{
    /**
     * @param  'set_quantity'|'add'|'remove'  $type
     */
    private function __construct(
        public string $type,
        public ?int $orderLineId = null,
        public ?int $quantity = null,
        public ?string $purchasableType = null,
        public ?int $purchasableId = null,
    ) {}

    public static function setQuantity(int $orderLineId, int $quantity): self
    {
        return new self('set_quantity', orderLineId: $orderLineId, quantity: $quantity);
    }

    public static function add(ProductVariant $purchasable, int $quantity): self
    {
        return new self(
            'add',
            quantity: $quantity,
            purchasableType: $purchasable->getMorphClass(),
            purchasableId: $purchasable->id,
        );
    }

    public static function remove(int $orderLineId): self
    {
        return new self('remove', orderLineId: $orderLineId);
    }
}
