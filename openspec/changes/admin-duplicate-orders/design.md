# Design

## Context

See proposal.md for why staff need this. The order screen is `ManageOrder`. Split already opens a modal with a shipping price and a payment method, but it changes the source order, keeps the original shipping method, and can queue an offline order to the ERP. Modify shipping method lists the options a cart would be offered and reprices the chosen one. Neither action creates an independent copy.

A normal new order runs `lunar.orders.pipelines.creation`. `FillOrderFromCart` sets the draft status `awaiting-payment` and copies cart meta. `CreateShippingLine` prices the option on the cart address unless `shippingOptionOverride` is set. An override is what split uses for a typed shipping price, and the free-shipping waiver leaves an override alone. `OrderPlacedEvent` is not dispatched by this pipeline. ERP, Klaviyo, and Mailchimp listen to that event. Stock moves on a later status-update activity, not when the row is first inserted. `OrderObserver` writes that activity when a normal save changes `status`.

`CompletePreorderConsultation::paymentOptions()` and `statusFor()` read `lunar.orders.payment_options`. With the published list, cash on delivery is `payment-offline`, card methods are `awaiting-payment`, and bank transfer is `awaiting-bank-transfer`.

## Goals / Non-Goals

**Goals:**

- One modal whose shipping price is the calculated price of the selected method, and whose save stores that price or a typed replacement.
- One database transaction that stores the new order, its cart, its lines, and its tags, or stores nothing.
- The source order is only read. Its link is stored on the new order, the same way `split_from_order_id` is stored.

**Non-Goals:**

- Editing products, quantities, or addresses in the modal. Staff do that afterwards with the existing order actions.
- Reusing `SplitOrder`, the source order's cart, or the storefront session cart.
- Marking discounts used, spending loyalty, or dispatching `OrderPlacedEvent` or `SplitOfflineOrderReadyForErp`.
- A new status, permission, or column.

## Decisions

### Preview the modal without saving

Add `DuplicateOrder::preview()`. It builds the same private cart as the save, inside a transaction that always rolls back. It returns the configured payment options, the shipping methods `ShippingManifest::getOptions()` offers after `clearOptions()`, and the calculated shipping amount for each method.

Price each method by selecting it on the cart, with no `shippingOptionOverride`, and reading the shipping total after `recalculate()`. That total is what the field shows, including 0 when free shipping qualifies or the shipment is preorder-only. Drop identifiers ending in `-locker`. A locker needs a chosen machine, and this modal does not collect one.

The default payment method is the source `meta.payment_option` when it is still configured, otherwise `cash-on-delivery` when that is configured, otherwise the first configured method. The default shipping method is the source shipping identifier when it is still offered, otherwise the first offered method. Its calculated amount fills the price field.

Changing the shipping select in the modal sets the price field to that method's previewed amount and clears the typed-price flag, the same way split's `shipping_custom` flag works. It does not call the save. The modal reads the preview once per open.

When the copied lines are not shippable, the preview has no shipping methods and the modal hides both shipping fields.

Alternative considered: recalculate on every keystroke. The price only depends on the method, so one preview per open is enough. The save calculates the chosen method again.

### Save the confirmed method, price, and mapped status

`DuplicateOrder::execute()` receives the payment method, the shipping identifier or null, and the shipping amount in minor units or null. In one transaction, lock the source order for reading and do not write it.

Build a new `Cart` with the source user, customer, channel, and currency. Copy addresses and product lines. Copy line meta except `preorder` and `preorder_consultation_completed_at`. Keep an in-memory map from each cart line to its source line.

Cart meta is `language_locale`, `is_guest`, `isBillingSameAsShipping`, the chosen `payment_option`, `payment_type` only when that option equals the source option, `shippingType` from the chosen method (`pickup` for store collection, otherwise `courier`), and `duplicated_from_order_id`. Leave out loyalty keys, `traffic_source`, `awb`, `erp_sent_at`, invoice keys, and the source `shipping_zone`.

Set the cart address `shipping_option` to the confirmed identifier. Clear the manifest and reject an identifier that is not offered, including a locker identifier. Reject a missing payment method, a payment method outside `paymentOptions()`, a negative amount, a shippable cart with no offered method, and a non-shippable cart that was given a method.

Compare the confirmed amount with the amount just calculated for that method. When they differ, set `shippingOptionOverride` to that amount and put `shipping_price_override` on the cart meta, so the creation pipeline stores the typed price and a later product edit keeps it. When they match, do not set the override. Free shipping can then store 0. Recalculate after that choice.

Run `lunar.orders.pipelines.creation` with the order born in `CompletePreorderConsultation::statusFor()`, so the first save is the status that sends a card payment link or bank-transfer instructions. Then set `placed_at`, notes, and customer reference without model events. A normal status save would log a status-update, and that activity is what label and ERP listeners watch. Copy line notes from the in-memory map. Copy tags on the morph relation inside the transaction, not through `syncTags()`. Decrease each product line with `ProductVariant::decreaseStock()`. A preorder variant is unchanged. Set `meta.stock_taken` without model events so a later shipment status does not take the same units again, and a later cancel can still restore them.

Do not dispatch `OrderPlacedEvent`, `SplitOfflineOrderReadyForErp`, or `MarkAsNewCustomer` directly. `placed_at` is set without events, so dispatch `MarkAsNewCustomer` once after commit, which is what `OrderObserver` would have done.

Alternative considered: let split's `initialStatus()` collapse every non-offline method to `awaiting-payment`. Staff asked for the status configured for the method, so bank transfer stays `awaiting-bank-transfer`.

### Reject before anything is kept

Throw `DuplicateOrderException` with one reason. The transaction rolls the cart back with the order.

- `unauthorized` when `userCanManageOrders()` is false.
- `no_products` when there is no product line.
- `unavailable` when a purchasable is missing, `isPurchasable()` is false, or `CartLineAvailability` fails for this cart.
- `quantity` when `CartLineQuantity` rejects the quantity.
- `stock` when a variant's `canBeFulfilledAtQuantity()` is false. Do not call `CartLineStock`.
- `address` when a required address fails the order-creation fields: country, first name, line one, city, and postcode.
- `payment` when the method is missing or not configured.
- `shipping` when a shippable order has no offered method, or the confirmed identifier is not offered.
- `negative_shipping` when the amount is below zero.

### Show the modal and the source order

Add `DuplicateOrderAction` beside `SplitOrderAction`. It uses the same payment select and numeric shipping price as split: required, minimum 0, currency suffix. Add a required shipping-method select. Its label is the option name, and when two options share a name, append the zone, as Modify shipping method does. The price field is filled from the preview of the selected method.

On success, notify and redirect to `ManageOrder::getUrl(['record' => $order])`. On failure, notify and stay on the source order. English label: "Duplicate order". Hungarian: "Rendelés duplikálása". The same meaning in the other 14 locales, including field labels, the success notice, and one notice per rejection reason.

Add a summary entry beside the split entries, visible when `meta.duplicated_from_order_id` is filled, using `relatedOrderLinks()`. English: "Duplicated from". Hungarian: "Duplikálva erről".

## Risks / Trade-offs

- [The preview and the save can see different prices if a rate changes while the modal is open] → The save calculates the chosen method again. A price that still equals that new calculation is not an override. A price that differs is stored as typed.
- [The manifest keeps options for the request] → Clear it immediately before `getOptions()` in both the preview and the save.
- [`save` without events skips the new-customer observer] → Dispatch `MarkAsNewCustomer` once after commit.
- [A typed price blocks free shipping] → That is the existing override rule. The field starts at 0 when shipping is free, so leaving it stores 0.
- [A rolled-back preview still locks the source order] → Keep that transaction to the cart build and the option pricing. Do not hold it across the modal.
- [`payment-offline` usually means an offline order can be exported] → Do not dispatch the placement or split ERP event. The status is recorded only.

## Migration Plan

No migration and no published config change. Deploy the action with the core action. Rollback is reverting the code. Orders already duplicated stay valid and keep the status and meta written at creation.

## Open Questions

None. The modal collects payment, shipping method, and shipping price. The initial status is `lunar.orders.payment_options` for the chosen method. Integrations stay idle.
