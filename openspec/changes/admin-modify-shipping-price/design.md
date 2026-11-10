# Design

## Context

See proposal.md for why staff need this. The order screen is `ManageOrder`. Its main column is the shipping strip (`DisplaysShippingInfo`), then `OrderItemsTable`, then the totals. The strip is a borderless section: the shipping line description on the left, and `sub_total` on the right. That right-hand amount is the 20.00 in the screenshot. The totals block repeats the charged shipping total, and it hides that row when the value is 0. The right-hand column shows the shipping method name, and the only Edit nearby changes the address.

Product edits already go through `UpdateOrderProductLines`. It locks the order, checks `GetOrderProductLineEditGates` and `userCanManageOrders()`, recalculates the linked cart, and copies the result back. `writeShippingLine()` then replaces the shipping line from `cart->getShippingOption()`, including its meta. A price typed only onto the line would be lost on the next product edit.

`Cart::$shippingOptionOverride` is already honored by `ApplyShipping`, `CalculateTax`, and `ApplyDiscountFreeShipping`. Split uses it so the amount staff type is the shipping subtotal, tax is calculated on it, and a free-shipping discount does not waive it. `CalculateTax` adds shipping tax only when prices exclude tax. With prices including tax, the shipping total equals that subtotal.

Consultation done writes a shipping amount with its own arithmetic and does not set an override. A later product edit still reprices that order from the carrier.

## Goals / Non-Goals

**Goals:**

- One save, in one transaction, that stores the typed price or stores nothing.
- The same price is what a later product-line recalculation uses.
- Tax, the order total, and an existing loyalty spend follow the cart pipelines already used for product edits.

**Non-Goals:**

- A reset back to the carrier price.
- Making a consultation price sticky. Only a price saved by this action is kept.
- Changing the shipping method, adding a shipping line, or editing an order with several shipping lines.
- Completing a phone consultation, or creating an AWB, an invoice, or an ERP send.

## Decisions

### Put the action on the shipping strip, beside the price

Add **Modify shipping price** to the shipping strip, in the price column, end-aligned, with the same pencil link treatment as **Update quantity**. It opens one numeric field in the order currency, required, minimum 0, filled from that line's current subtotal. The field is the amount on the strip, not the charged shipping total in the totals block.

Show it only when `UpdateOrderProductLines::userCanManageOrders()` is true, `GetOrderProductLineEditGates` returns no gates, and the order has exactly one shipping line. A locked order keeps the existing gate notice and does not get a second one.

English label: "Modify shipping price". Hungarian: "Szállítási díj módosítása". The same meaning in the other 14 admin locales.

Alternative considered: an action on the Shipping Total row. That row is hidden at 0, so a free shipment would have nowhere to edit, and the row is a receipt total rather than the amount staff type.

Alternative considered: the sidebar, next to Shipping method. The Edit already there changes the address. The method name is not the price.

Alternative considered: a header action beside Update Status and Download PDF. Those stay available when the order is locked. This action has to disappear with the product actions.

### Store the price as an override the cart already understands

On the single shipping line, set `meta.shipping_price_override` to true and set `unit_price` to the entered minor amount. Remove `preorder_shipping_pending` when it is present. Leave every other meta key, the identifier, and the description.

Before `recalculate()`, both saves look at that flag. When it is set, build a `ShippingOption` with the line's identifier, description, unit price, tax class, and meta, and assign it to `shippingOptionOverride`. Do not call `setShippingOption()`. Its validators can reject a price the manifest did not return.

Resolve the tax class the way `SplitOrder` does: match the line's tax breakdown, otherwise the default tax class. Share that lookup between this save and the product-line save. Leave the private copies in `SplitOrder` and `CompletePreorderConsultation` as they are.

`writeShippingLine()` copies `option->meta`, so the option meta has to include `shipping_price_override`. Otherwise the next product edit drops the flag and the carrier price comes back.

The new save does not add shipping tax by hand. The cart pipelines do, which is what keeps a later product edit from changing the price or the tax basis.

### Reuse the product-line transaction, without a product change

Add `UpdateOrderShippingPrice`. It locks the order and the linked cart, checks the same permission and gates, rejects a missing cart, a negative amount, and any shipping-line count other than one, stamps the line, then runs the shared recalculation with no product change and no stock movement.

`UpdateOrderProductLines` calls the same recalculation after its own product change. The flag is the only check. When `shipping_price_override` is absent, shipping is priced again from the carrier, and a free-shipping coupon or automatic discount may set it to 0. That includes a higher quantity or an added product that is what makes the order eligible. When the flag is present, the saved price stays, the free-shipping waiver does not apply, and an option the manifest cannot price does not reject the product edit.

Dispatch the existing synchronous `OrderProductLinesRecalculated` event with the previous `loyalty_points_to_redeem`. The loyalty listener already adjusts an existing spend by the difference and rolls back with the transaction. Do not add a second event.

### Reject the save on the server as well as in the form

The form minimum is 0. The action also rejects a negative amount, a failed gate, a missing permission, a missing cart, and a shipping-line count other than one. A failed save leaves lines, totals, the cart, stock, and loyalty as they were. Success notifies and refreshes the order screen, the same way a product save does.

## Risks / Trade-offs

- [Exclusive tax makes the strip and the totals differ] → The strip and the field show the subtotal staff typed. The totals show the charged shipping total, subtotal plus tax. The split flow already does this. With prices including tax the two amounts match, which is what the current order screen shows.
- [A consultation price is still replaced by the next product edit] → That is the current behavior, and this change does not take it over. Staff set this action when the price has to stick.
- [Cash on delivery and bank transfer stay locked when ERP sending is enabled and `erp_sent_at` is empty] → Those statuses are already outside the product-line pre-send list. This action uses that gate on purpose.
- [An extension that replaces the shipping infolist drops the action] → The action lives in the default strip, which is what `extendShippingInfolist` receives. A replacement has to keep it.

## Migration Plan

No schema change. Deploy the code. Orders already placed gain the flag only when staff save a price. Rollback is a code revert. A leftover `shipping_price_override` key is unused if the code is removed, and the next product edit reprices from the carrier again.

Add a short description of the action and the sticky price to `docs/design/order_processing.md`, and a changelog bullet for the implementation day under `docs/changelog/`.
