# Design

## Context

See proposal.md for why. Today `ShipBy`, `FlatRate`, and `FreeShipping` sum every cart line. `Collection` is already price 0. `CreateOrderLines` copies cart-line meta onto the order line, and `CleanUpOrderLines` deletes an order line whose meta does not match the cart line. `SplitOrder` copies that line meta onto each new order and does not copy arbitrary order meta. AWB generation, ERP invoicing, and ERP export already key off status or placement; none of them look at preorder.

## Goals / Non-Goals

**Goals:**

- One rule for which lines count toward an automatic shipping price, shared by checkout, product-line recalculation, and split preview.
- One rule for "waiting for phone consultation", shared by the admin order page, the AWB observer, the ERP invoice observer, and both ERP send listeners.
- A preorder-only option stays priced at 0 so an order save is not rejected as unpriced.

**Non-Goals:**

- New order statuses, payment drivers, mailables, or a proforma document.
- Changing stock movement, the product-line edit gates, or the split gates.
- Checkout copy and when the storefront authorizes payment or dispatches placement.

## Decisions

### 1. Filter the shipping basis inside the table-rate drivers

A cart line is left out of the shipping basis when `meta.preorder` is true or its purchasable `isPreorder()`. `ShipBy` keeps its current amount for the lines that remain: discounted subtotal without coupon for `cart_total`, converted weight for `weight`. `FlatRate` keeps `subTotal` for those lines. `FreeShipping` compares the minimum with those lines' subtotal, and when it subtracts discounts it subtracts only those lines' discounts. Product exclusion lists use only the product ids of lines that are still in the basis.

When no product line remains in the basis, `ShipBy`, `FlatRate`, and `FreeShipping` skip tier matching and return the same option identity at price 0 with `meta.preorder_shipping_pending = true`. Customer-type limits in `ShipBy` still apply before that shortcut. `Collection` never sets the flag.

`CreateShippingLine` and the split shipping writer already store option meta on the shipping line, so the marker reaches the order without a new column. A mixed cart does not set the flag. Staff can still type a different shipping amount on a split; that override stays the stored amount.

Alternative considered: drop preorder lines from the cart before pricing. That would also drop them from the order. A discount applied after pricing cannot fix the tier that was chosen from the full total.

### 2. Record a preorder line on line meta, and stamp it before the order line is matched

`CreateOrderLines` stamps `meta.preorder = true` on the cart line when the variant `isPreorder()`, then saves that meta and copies it to the order line. It never removes the key. The cleanup signature then matches. A variant that later leaves `preorder` leaves the key in place.

`SplitOrder` already copies source line meta onto the child cart line, so the child keeps the record. Do not add the consultation timestamp to the copied order-meta keys.

`UpdateOrderProductLines` rebuilds cart lines from order-line meta, so the key survives a quantity change. Adding a variant that `isPreorder()` must put the same key in the new cart line's meta before the "same meta" lookup, so it merges with an existing plain preorder line of that variant. Increasing the quantity of a line that is already recorded does not clear `preorder_consultation_completed_at`. When that timestamp is already stored, a newly added preorder line keeps it too, so the order does not wait again and its status stays unchanged.

Alternative considered: a list of preorder line ids on the order. Split creates new line ids, so the list would not follow the goods.

### 3. Waiting is derived, and the modal moves the order on

`Order::awaitsPreorderConsultation()` is true when any product line has `meta.preorder` true and `meta.preorder_consultation_completed_at` is empty. The notice is a shout on the order page. The phone is `shippingAddress.contact_phone`, then `billingAddress.contact_phone`. Copy lives in all 16 `order.php` locale files. English and Hungarian are the source wording; the other locales carry the same meaning.

Consultation done stays a header action for someone who can manage orders. The page already requires `sales:manage-orders`. It opens a modal instead of only storing a timestamp. The order status stays `awaiting-phone-consultation` until that modal is submitted. This change does not add that status; lunar-frontend already places the order there.

Update Status stays available while the order is waiting. That click opens a confirmation first: proceed opens the existing status form, and cancel leaves the order unchanged. An order that is not waiting opens the status form directly.

The modal asks for the payment method: `bt-ipay`, `stripe-card`, `bank-transfer`, or `cash-on-delivery`. Unless `meta.shippingType` is `pickup` or the selected shipping method driver is `collection`, it also asks for the full shipping price. That field starts from the price the order's shipping method would charge if every product line were a simple product. A method that charges by weight uses the weight of every product line, including preorder lines. The quote is a temporary cart with `shipping_include_preorder` set, so checkout pricing is unchanged. When that price cannot be resolved, the field starts from the shipping total already stored. Store collection has no shipping field and stays at 0.

Submitting saves in this order:

1. The shipping line, `shipping_total`, tax, and the order total. `preorder_shipping_pending` is removed from that shipping line. The payment email and the invoice read the total stored at send time.
2. `preorder_consultation_completed_at` on every product line whose `meta.preorder` is true.
3. `meta.payment_option` and the new status, on the same save.

A waiting order that has a recorded preorder line and another product line does not open that form immediately. Consultation done asks whether to keep the order together or split it. Keep opens the payment and shipping form. Split opens the existing split screen, which starts with the preorder lines on one new order and the other lines on the other. Staff can still move lines. The header Split action stays hidden while that mixed order is waiting, and stays available once this split screen is open. A preorder-only order still opens the payment form directly and still has the header Split action.

Confirming the split from this choice finishes the consultation. The request carries a flag so a normal split is unchanged. Each new order uses the same payment list and the same status map as the payment form, including `bt-ipay`. Bank transfer becomes `awaiting-bank-transfer`. Preorder lines on the new orders get `preorder_consultation_completed_at`, so neither new order is waiting. The in-stock order's shipping field starts from its calculated price. A new order whose lines are all preorder starts from the price that method would charge if those lines were simple products. Store collection stays at 0 and does not ask for a price. The original order becomes `split`. The notice and Consultation done stay hidden on an order with that status. This confirm does not dispatch placement and does not generate an AWB. A cash-on-delivery child is no longer waiting, so the existing split ERP listener can send it. Card and bank transfer wait for payment.

A split that was not opened from this choice still strips `preorder_consultation_completed_at`. Its status map still sends bank transfer to `awaiting-payment`, which would not send the invoice email.

The status comes from the payment method, not from a normal `SplitOrder`. That split map sends bank transfer to `awaiting-payment`, which would not send the invoice email.

- `bt-ipay` or `stripe-card` → `awaiting-payment`. The existing staff-guard observer in lunar-frontend emails the payment link.
- `bank-transfer` → `awaiting-bank-transfer`. The same bank-transfer email checkout sends goes out, with the invoice attached.
- `cash-on-delivery` → `payment-offline`. No payment email.

The timestamp is already stored when the status save runs, so the AWB, ERP invoice, and ERP send checks no longer hold for that change and for later ones. This submit does not dispatch `OrderPlacedEvent`, does not generate an AWB, and does not send the order to the ERP. It does not add a status, a mailable, or a payment driver.

Adding a new preorder line after the consultation timestamp is stored copies that timestamp onto the new line and leaves the order status unchanged, so the order does not wait again. An order that has not been stamped still waits when a preorder line is added. An order with no recorded preorder line is not waiting even if an old timestamp remains.

Alternative considered: a dedicated order status for the end of the call. Hosts already express payment progression with `awaiting-payment`, `awaiting-bank-transfer`, and `payment-offline`. A new status would also need a new mailer. The modal uses those three statuses instead.

### 4. Guard the existing side effects

Before generating an AWB, generating an invoice, or sending an order to the ERP, return when `awaitsPreorderConsultation()` is true.

- Carrier `OrderObserver` adds the check to its existing status condition.
- ERP `OrderObserver` adds the check next to the invoice status check.
- `SendOrderToERP::handle` returns immediately, for both placement and a split offline event.
- `SendSplitOrdersToERP` does not dispatch for a waiting child, so that child is not queued and cannot be moved to `failed-erp-sync` for a send that was supposed to wait.

An order that is not waiting keeps the current path. The modal stores the timestamp before it changes status, so those checks see a finished consultation. The statuses it sets are not the AWB status, and it does not dispatch placement, so the click itself does not create an AWB or send the order to the ERP.

## Risks / Trade-offs

- [A mixed cart no longer counts preorder value toward a free-shipping minimum or a cheaper tier] → That is the pricing rule. Orders with no preorder line are unchanged. Cover it with the existing `ShipBy` and `FreeShipping` tests plus a flat-rate case.
- [A preorder-only order stores shipping 0 until staff edit it] → Split already lets staff replace the calculated amount. `preorder_shipping_pending` records that the whole amount is still to be agreed. The storefront sentence is shown for every preorder line that is not store collection, including a mixed cart, which has no flag.
- [A waiting order is not in the ERP until staff confirm] → The order-page notice is the prompt. The modal sets a payment status after the timestamp is stored, so a later AWB, ERP invoice, or ERP send is not held. The modal does not dispatch placement and does not use the AWB status, so it does not send the order or create a label itself.
- [Stamping cart-line meta changes the line signature] → Stamp only the boolean key, before matching, and never remove it, so draft reuse and cleanup keep a single line.
- [The carrier observer checks `isDirty('status')` inside `updated`] → Leave that condition as it is. Add the waiting check beside it rather than moving the hook.

## Migration Plan

No schema change. Deploy the engine before the storefront starts relying on `preorder_shipping_pending`. Orders already placed are not backfilled: a line is recorded only when it is written after this change, and an existing order does not start waiting unless a preorder line is written onto it. Rollback is a code revert. Leftover meta keys are unused if the code is removed.

## Open Questions

None. The payment-link email and the invoice email stay in lunar-frontend. This modal only sets the status and payment method those emails already read.
