# Design

## Context

See proposal.md for why staff need to change the method. The shipping strip is `DisplaysShippingInfo`: the shipping line description on the left, and `sub_total` on the right. **Modify shipping price** is `afterContent` on the price, and `.lunar-shipping-nowrap` keeps that action on the same line as the amount.

`UpdateOrderShippingPrice` locks the order, checks `GetOrderProductLineEditGates` and `userCanManageOrders()`, stamps `meta.shipping_price_override`, and recalculates through `RecalculateEditedOrder`. While that flag is set, `applyShippingPriceOverride()` assigns `shippingOptionOverride` and the carrier is not asked again. `writeShippingLine()` finds the shipping line by identifier and copies the option, including its meta. A different identifier therefore inserts a second shipping line and leaves the old one.

`alignShippingAddress()` copies the order address onto the cart and sets `shipping_option` only when the cart address has none. `ShippingManifest::getOptions()` does not clear options first. `getShippingOption()` prices whatever identifier is already on the cart address.

## Goals / Non-Goals

**Goals:**

- One save, in one transaction, that stores the chosen method and its calculated price, or stores nothing.
- The option list and the save use the same alignment the product-line save already uses.
- The two actions stack under the method name and under the price.

**Non-Goals:**

- Changing locker selection or the postal address. Checkout already stored the billing address as the shipping address on a personal-pickup order.
- Offering a method the cart would not be offered, or adding a shipping line.
- Resetting the price by saving the method already on the order.
- A new loyalty event. The existing `OrderProductLinesRecalculated` listener adjusts a spend.

## Decisions

### Stack both actions under their labels

Move **Modify shipping price** from `afterContent` to `belowContent` on the price entry, and end-align that action so it sits under the price. Add **Modify shipping method** with `belowContent` on the description entry, start-aligned under the name. Both stay pencil links. The link view already applies to below content.

The nowrap rule targets `.fi-in-entry-content-ctn`, so a below-content action should leave that row. If it stays inline, point a strip class at the below schema and let the name and the price ellipsize as they do now. The right column stays `auto` width.

English label: "Modify shipping method". Hungarian: "Szállítási mód módosítása". The same meaning in the other 14 admin locales, including the field label and the success notification.

Alternative considered: one modal that edits method and price together. The price action already exists, and staff asked for a second action under the name.

### List only the options the aligned cart would be offered

Add `UpdateOrderShippingMethod`. The modal's options come from the linked cart after `RecalculateEditedOrder::syncCart()`, then `ShippingManifest::clearOptions()` and `getOptions()`. That is zone, weight, exclusions, channel, currency, and the current lines. The value is the option identifier. The label is the option name. When two options share a name, append the zone from `ShippingOption::getOption()`.

Opening the modal must not leave the cart aligned. Run that read in a database transaction and roll it back, including the line rebuild `syncCart()` performs. The save does not use that rolled-back read. It aligns again inside its own transaction and resolves the options there. Clear the manifest first in both places so options added earlier in the request are not offered.

The choice starts on the current identifier when that identifier is in the list. The select is required. Drop options whose identifier ends in `-locker`. A locker label needs a chosen machine, and this save does not collect one. Reject a submitted locker identifier the same way as an identifier that is not offered.

On a personal-pickup order, store collection or `meta.shippingType` `pickup`, that read uses the stored shipping address. Checkout already copied the billing address there, so the select lists the couriers that address would be offered, including Sameday when it is in a Sameday zone. The rollback discards the alignment.

Alternative considered: every enabled shipping method, ignoring the cart. Staff chose only the options checkout would offer.

### Reprice through the pipelines and drop the override

Inside the save transaction, after the gates and the single-line check, reject the identifier already on the line before anything else is written. Same permission, missing cart, and failed gate as the price save.

After the chosen option is confirmed, set `meta.shippingType` from that option: `pickup` when it is store collection, otherwise `courier`. Store the same value on the linked cart. Leave the postal fields alone. A missing billing address does not block the save.

`syncCart()` runs first. `alignShippingAddress()` carries the order shipping address onto the cart. Set the cart address `shipping_option` to the chosen identifier, because alignment will not replace one that is already filled. When the order shipping address already stores a shipping option, set that column too. Clear the manifest, resolve the options, and reject an identifier that is not in that list before the shipping type changes. The price uses the stored shipping address.

On the existing shipping line, set `identifier` to the chosen option and remove `shipping_price_override` before `recalculate()`. Do not set `shippingOptionOverride`. `assertShippingPriced()` then requires the manifest option for that identifier, and `writeShippingLine()` updates the same line instead of inserting another. The option meta replaces the line meta, so a pending preorder marker remains only when the new option carries it. A qualifying free-shipping discount waives the new price because the override is gone.

Call `recalculate()` with `keepStoredLoyaltyRedemption: true` and dispatch `OrderProductLinesRecalculated` with the previous `loyalty_points_to_redeem`, as the price save does. No stock movement and no product change.

A chosen option that the manifest cannot price throws `UnpricedOrderShippingException`. An unchanged identifier throws `InvalidOrderShippingMethodException` with reason `shipping_method_unchanged`. An unoffered identifier throws the same exception with reason `shipping_method`. Map each reason in `OrderProductLineEditFailure` to its own notification, and halt the modal the way the price action does.

The option list for the modal is read once per order during the request and reused, including the rolled-back alignment. The action reports whether personal pickup became an address delivery. After a successful save that did, and only when the stored shipping address matches the stored billing address, the order screen shows the success notice and a warning. English: "The shipping address corresponds to the billing address. Update the shipping address if the delivery address is different." Hungarian: "A szállítási cím megegyezik a számlázási címmel. Ha a kézbesítési cím ettől eltér, módosítsa a szállítási címet." The same meaning in the other 14 locales. Staff change the address with the existing shipping-address edit. A differing address, a missing billing address, and any other successful save show only the success notice.

Alternative considered: keep the typed price and only change the name. Staff chose to replace the price and clear the override so the next product edit follows the new method.

### Keep the price save's behavior

`UpdateOrderShippingPrice` is unchanged. A price saved after the method change sticks, and a later product edit keeps it. A method change is the path that removes the flag.

## Risks / Trade-offs

- [The manifest keeps options for the request] → Clear it immediately before `getOptions()` on both the modal read and the save. The recalculation's later `getOptions()` runs the modifiers again.
- [A rolled-back modal read still locks the cart] → Keep that transaction to the alignment and the option lookup, and do not hold it across the modal.
- [The billing address is not the delivery address] → Checkout already stored it as the shipping address. The warning after a personal-pickup to address-delivery save is shown when those stored addresses still match, and tells staff to edit the shipping address. The existing address edit is the place they do that.
- [Exclusive tax makes the strip and the totals differ] → The strip shows the shipping line subtotal. The totals show the charged shipping total. The price action already does this.
- [An extension that replaces the shipping infolist drops both actions] → They live in the default strip, which is what `extendShippingInfolist` receives. A replacement has to keep them.

## Migration Plan

No schema change. Deploy the code. Orders already placed gain a new method only when staff save one. Rollback is a code revert. A method already saved stays on the line; the next product edit reprices it because the override was cleared.

Add a short description of the method action, the repriced amount, and the new button placement to `docs/design/order_processing.md`, in the staff order-editing paragraph. Append a changelog bullet for the implementation day under `docs/changelog/`.
