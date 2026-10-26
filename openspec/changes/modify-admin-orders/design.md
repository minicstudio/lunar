# Design

## Context

See proposal.md for why staff need this. The order screen is `ManageOrder`. `OrderItemsTable` lists physical and digital lines and can refund them. It cannot change quantity, add a variant, or remove a line.

Totals are produced by the cart pipelines (`CalculateLines`, `ApplyDiscounts`, `ApplyShipping`, `ApplyLoyaltyRedemption`, `CalculateTax`, `Calculate`). `Cart::createOrder()` copies that result through `lunar.orders.pipelines.creation`. `FillOrderFromCart` overwrites status with the draft status, regenerates the reference, and replaces order meta with cart meta. `FinalizeLoyaltySpend` spends points again on that pipeline. Re-running creation against a placed order would wipe the order and double-spend points. `ValidateCartForOrderCreation` also rejects a cart that already has a completed order.

Payment state already exists on `ManageOrder`: a successful capture, a successful intent, and refunds. Invoice state is `meta.billing_series` plus `meta.billing_number`. AWB state is `meta.awb`. A successful ERP send is not stored. `ErpService::sendOrder()` returns the exporter result and leaves the order unchanged. Failure is visible only as status `failed-erp-sync` or `invalid-address`.

Placing an order does not move stock. The storefront moves it on status change: `prepare-shipment` decreases every product line, and `canceled`, `payment-failed`, or `test-order` increases them again, but only when the latest of those statuses was a decrease. That check is `getActivitiesByStatuses()` over `lunar-frontend.orders.stock.decrease` and `.increase`. A later cancel restores whatever product lines exist at that moment, so an edit has to keep the taken quantity equal to the lines.

Loyalty spend, when it has already happened, is one ledger row keyed by `order:{id}:spend`. `LoyaltyLedger::adjust()` can return those points or spend more, and a positive adjust can restore the original spend's allocations.

## Goals / Non-Goals

**Goals:**

- One domain operation that applies a line change, recalculates, and commits, or commits nothing.
- The admin screen and that operation use the same gate.
- Recalculation stays on the cart pipelines without the order-creation pipeline.
- A successful ERP send becomes visible to the gate from then on.

**Non-Goals:**

- Editing addresses, customer, status, notes, shipping method, or payment.
- Pushing the edited order to the ERP, regenerating an invoice, or regenerating an AWB.
- Mailchimp or Klaviyo updates.
- Changing stock for an order whose history says stock is not currently taken.
- A backfill of `meta.erp_sent_at` for orders already accepted by Magister.

## Decisions

### Gate lives in core, and the screen only displays it

Add one core query, used by the admin screen and by the save, that returns the failed gates: `paid`, `erp`, `invoice`, `awb`. The save rejects when the list is not empty. The screen hides quantity change, add, and remove when it is not empty, and names each failed gate as the reason the order cannot be modified.

Paid: status `payment-received`, or any successful `capture`. A full refund does not clear it. An intent with no capture, and cash on delivery with no capture, are unpaid.

Invoice: both `meta.billing_series` and `meta.billing_number` filled. AWB: `meta.awb` filled.

ERP: `meta.erp_sent_at` filled, or sending is enabled and the timestamp is empty and the status is outside `awaiting-payment`, `failed-erp-sync`, and `invalid-address`. If no enabled provider is allowed to send orders, an empty timestamp is not sent.

`ErpService::sendOrder()` writes `meta.erp_sent_at` only when the exporter returns true, through `Order::withoutEvents`, so the billing and AWB observers do not run. A false or thrown send leaves the timestamp empty. Resend uses the same method, so it stamps the order too.

Alternative considered: infer send only from status. That stays wrong after the first successful send, because Magister does not change status when it accepts an order. The timestamp fixes new sends. The status list is only the fallback for rows that predate it.

### Change the linked cart, then copy the calculated result onto the order

The save runs in one database transaction. It locks the order row, checks the gates again, and requires `cart_id`. A missing cart rejects the save.

Inside that transaction:

1. Make the cart's customer, channel, currency, coupon, and loyalty meta match the order. The coupon comes from the order's discount breakdown when the cart coupon is empty. The shipping option identifier on the cart address is restored from the order's shipping line when the address has lost it.
2. Rebuild the cart's product lines from the order's product lines, including line meta, then apply the single requested change: set quantity, add a variant, or remove a line.
3. Reject a quantity below 1, a quantity the variant's quantity rules reject, a variant that is not purchasable, or a quantity `CartLineStock` cannot fulfill. Reject removal of the last product line. Adding a variant that matches an existing line's purchasable and meta increases that quantity instead of inserting a duplicate. Do not run `ValidateCartForOrderCreation`; it fails because the completed order already exists.
4. Recalculate the cart. Do not call `createOrder()` or the creation pipeline.
5. Require the priced shipping option to keep the order's shipping identifier. If it cannot be priced, throw and roll back.
6. Write product lines and the shipping line from the calculated cart. Match existing order lines by purchasable and meta so personalization stays. Update quantity and the calculated money fields. Delete product lines that the change removed. Update order subtotal, discount total, discount breakdown, shipping total, shipping breakdown, tax total, tax breakdown, total, and fingerprint.
7. Leave status, reference, `placed_at`, customer, addresses, notes, and payment meta unchanged. Copy only the recalculated loyalty keys back onto order meta. Do not replace the whole meta object. Then dispatch the synchronous loyalty event described below.

Changing status is what starts invoicing and AWB generation. This save must not change it.

Unit prices come from the current catalog because the cart line pipeline is the pricing engine for customer, channel, currency, discounts, and coupons. Freezing the old unit price would ignore "applicable prices" and would disagree with the discount pipeline.

Alternative considered: edit `order_lines` and recompute totals in PHP. That would fork discount, coupon, shipping, tax, and loyalty behavior that already exists on the cart.

### Loyalty delta uses the ledger adjust, not a second spend

`FinalizeLoyaltySpend` must not run. Core must not reference loyalty classes. Loyalty is a separate package and already inserts `ApplyLoyaltyRedemption` into the cart pipeline when it is booted, so a normal admin request recalculates the redemption as part of cart calculate.

After the order meta has the recalculated `loyalty_points_to_redeem`, and still inside the order transaction, core dispatches a synchronous event with the order and the previous point count. The loyalty listener adjusts the account when a spend row exists for `order:{id}:spend` and the new count differs. Returning points uses a positive `LoyaltyLedger::adjust()` tied to that spend, which restores its allocations. Spending more uses a negative adjust, which allocates from current lots. The event key must be unique per save. If there is no spend row, the listener leaves the ledger alone. If loyalty is disabled, the listener returns.

`LoyaltyLedger` opens its own transaction. On the default connection that is a savepoint, so an outer rollback undoes it. The listener has to run on that same connection, before the order transaction commits. A failed save must not dispatch the event after the rollback.

### Each admin action is one save

`OrderItemsTable` gets three actions, wired through the existing `extendTable` hook so addons can still extend the table:

- header action to add a product and a quantity. The form asks for the product first. When that product has more than one variant, it then asks for one of those variants. When it has exactly one variant, that variant is used and the variant field is not shown.
- row action to set the quantity
- row action to remove the line, with confirmation

Each action calls the domain operation and then refreshes the order screen. There is no multi-line draft. One failed action cannot leave the other lines half-saved, because each action is its own transaction.

The failed-gate message is a shout on `ManageOrder`, fed by the same gate query. Labels and gate text go in all 16 `order` locale files.

Authorization stays `sales:manage-orders`. The domain operation checks that permission as well as the gates, so a crafted request cannot skip the screen.

### Stock moves by the line difference, and only while it is taken

Use the same history check as the storefront. Load the latest `status-update` whose new status is in `lunar-frontend.orders.stock.decrease` or `.increase`. Stock is taken only when that activity's new status is a decrease status. An empty config, no matching activity, or a latest increase status means stock is not taken.

When stock is not taken, do not call `decreaseStock()` or `increaseStock()`, and do not reject the save with `CartLineStock`. The next decrease status will move the lines as they are then. Moving them now would move them twice.

When stock is taken, apply the difference inside the order transaction, before commit:

- Removed line: `increaseStock(removed quantity)`.
- Lower quantity: `increaseStock(old - new)`.
- Higher quantity or a new line: `decreaseStock(added quantity)`, after `CartLineStock` accepts that added quantity. Units already on the order are already out of stock, so the check covers only the extra.

`preorder` still no-ops inside those stock methods. A rejected save rolls the stock change back with the order.

Alternative considered: restore stock only when a whole line is removed. A quantity cut from 5 to 1 would leave 4 units missing, and a later cancel would restore 1. The difference has to move with every line change.

## Risks / Trade-offs

- [Current prices replace the unit price of every recalculated line, including lines the admin did not touch.] → This is the cart pipeline's result, which is what keeps discounts and tax consistent. The spec requires that result.
- [An unpaid `payment-offline` order that Magister already accepted, before `erp_sent_at` existed, is treated as sent and cannot be edited.] → Safer than editing an order the ERP already has. New accepts store the timestamp. No backfill is attempted.
- [An `awaiting-payment` order that was accepted before the timestamp existed can still be edited.] → Failed sends are moved to `failed-erp-sync` or `invalid-address`. A successful historical send left in `awaiting-payment` is the remaining hole. New sends close it.
- [The order's cart is rewritten to match the order before the change.] → The transaction rolls the cart back on failure. Completed carts are not the shopper's active cart.
- [An ERP send and an edit can overlap.] → The edit locks the order and checks the gates again after the lock. The timestamp write must update that same row, so it waits for the lock. The edit either commits first and the send stamps the edited order, or the send stamps first and the edit then fails the gate.
- [A nested loyalty transaction commits on its own if it ever runs on another connection.] → Call it on the default connection, inside the order transaction.
- [Marketing orders stay stale.] → Accepted. Placement sync is not repeated, and a sent order cannot be edited.
- [Returning stock only for a deleted line, or moving stock before it was taken, leaves the variant wrong.] → Move the signed quantity difference only while the latest stock operation is a decrease. A later cancel restores the lines that remain.

## Migration Plan

Deploy the application code. No schema migration. Existing orders gain no timestamp. The status fallback applies until a later successful send writes `meta.erp_sent_at`.

Rollback is a code rollback. Timestamps already written only make those orders non-editable, which matches an order the ERP has accepted.

## Open Questions

None. The historical ERP fallback is specified so implementation does not choose a different set of orders.
