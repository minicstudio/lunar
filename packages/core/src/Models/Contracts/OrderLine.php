<?php

namespace Lunar\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lunar\DataTypes\Price;

interface OrderLine
{
    /**
     * Return the order relationship.
     */
    public function order(): BelongsTo;

    /**
     * Return the polymorphic relation.
     */
    public function purchasable(): MorphTo;

    /**
     * Return the currency relationship.
     */
    public function currency(): HasOneThrough;

    /**
     * Whether the line is a complimentary / BXGY gift line.
     */
    public function isComplimentary(): bool;

    /**
     * Unit price for invoicing (zero when complimentary).
     */
    public function invoiceUnitPrice(): Price;

    /**
     * Number of pieces in one priced pack.
     */
    public function packSize(): int;

    /**
     * Whether the line can be invoiced as whole packs instead of pieces.
     */
    public function isInvoicedInPacks(): bool;

    /**
     * Number of whole packs on the line.
     */
    public function invoicePackQuantity(): int;

    /**
     * Pack price for invoicing (zero when complimentary).
     */
    public function invoicePackPrice(): Price;
}
