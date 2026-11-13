# Design

## Context

See proposal.md for why staff need this. The order screen is `ManageOrder`. Header actions already include capture, status update, and PDF download, and addons extend them through `headerActions`. Product-line edits go through `UpdateOrderProductLines`, which recalculates the linked cart and copies the result back. That path is the pattern for money. It is not the operation that creates the new orders.

`Cart::createOrder()` runs `lunar.orders.pipelines.creation`. `FillOrderFromCart` clears the reference and replaces meta. `FinalizeLoyaltySpend` spends points again. A split must not use that pipeline.

Discounts are applied in `DiscountManager::apply()` in three different ways:

- One automatic non-coupon discount per line, chosen by priority tier and then by amount (`AdvancedAmountOff`).
- Cart-level Buy X Get Y, including a coupon-backed Buy X Get Y, across the lines that qualify together.
- One coupon, from `cart.coupon_code`, applied to the whole cart by `applyCouponForCart()`. A fixed coupon is the configured amount for that cart, not a share of a larger cart. Percentage coupons use each eligible line. `checkDiscountConditions()` clears the coupon code when the minimum spend fails. `markAsUsed()` is not called by the cart pipeline. The storefront increments `uses` after payment.

Loyalty is also cart-level. `ApplyLoyaltyRedemption` reads `loyalty_points_to_redeem` from cart meta, caps it, and spreads it across lines. A spend that already happened is one ledger row, `order:{id}:spend`. Core does not reference the loyalty package. The product-line edit dispatches a synchronous event inside its transaction, and loyalty adjusts the ledger.

Shipping price comes from `ShippingManifest` unless `Cart::$shippingOptionOverride` is set. `ApplyShipping` and `CalculateTax` both honor that override. The order stores one shipping line. Its `identifier` is what carrier AWB generation resolves later.

lunar-frontend moves stock when an order's status changes into a configured decrease or increase status. In the webshop that status is `awaiting-payment` for the decrease, so stock is taken once when the order is received. `canceled`, `payment-failed`, and `test-order` increase stock, and only when the latest stock operation was a decrease. That check is `Order::getActivitiesByStatuses()` over `lunar-frontend.orders.stock.decrease` and `.increase`. `ProductVariant::decreaseStock()` and `increaseStock()` leave a `preorder` variant unchanged. A mixed order can therefore have its in-stock line decreased while the preorder line is unchanged, and staff still need to split those into separate deliveries.

Status changes notify several observers: activity log, Smartbill or Magister invoice, AWB generation, loyalty earn or reversal, and the table-rate shipping zone. Invoice and AWB run on `updated` when the new status is one they are configured for. Creating a row does not run those two. `OrderPlacedEvent` is dispatched by the host after payment authorization. It is what sends the order to the ERP and what Klaviyo and Mailchimp listen for. This repository does not dispatch it during order creation.

Payment method is `meta.payment_option`. Captures and intents are `transactions` rows. There is no parent-order column. Report revenue uses `Order::scopeReportable()`, which skips statuses flagged `exclude_from_reports`.

## Goals / Non-Goals

**Goals:**

- One domain operation whose preview and confirm use the same cart calculation.
- Confirm either stores every new order and the `split` status, or stores nothing.
- The shipping amount the administrator types is the stored shipping total, with tax calculated on that amount, while the shipping method identifier stays the original one.
- A coupon and a loyalty redemption each have one destination. Automatic line discounts and Buy X Get Y follow the products.

**Non-Goals:**

- Dividing a captured payment, an invoice, an AWB, or an ERP document across the new orders.
- A customer email that says the order was split.
- Klaviyo or Mailchimp placement sync for the new orders.
- Moving stock during the split itself.
- Address editing inside the split modal. Copied addresses use the order screen's existing address edit.
- Changing when the original order takes stock. That still happens once, on receipt.

## Decisions

### Refuse the split when another system already owns the order

The save locks the order and rejects it when any of these is true:

- status is `split` or `dispatched`
- status is `payment-received`, the status history includes `payment-received`, or any successful capture exists
- `meta.billing_series` and `meta.billing_number` are both filled
- `meta.awb` is filled
- `meta.erp_sent_at` is filled

Stock that was already taken does not reject the split. An in-stock product and a preorder product on the same order are a normal reason to split after receipt.

`payment-offline` with none of those is allowed. The product-line edit treats that status as already sent to the ERP when a send provider is enabled and the timestamp is empty. The split does not. Cash on delivery is a main reason to split, and a missing timestamp is not proof Magister has the order. The modal shows a warning in that case: sending is enabled, the timestamp is empty, and the status is outside `awaiting-payment`, `failed-erp-sync`, and `invalid-address`.

The same permission check as product-line edit applies: staff `admin`, or `sales:manage-orders`.

Alternative considered: reuse `GetOrderProductLineEditGates` unchanged. That would block cash on delivery whenever ERP sending is enabled, including orders that were never accepted.

Alternative considered: allow a captured or invoiced order and leave the money on the original. The new orders would look unpaid while the customer had already paid once. Stripe, Smartbill, the carrier, and Magister would still describe one order. The split is refused instead.

### Build one cart per target and calculate it there

Preview and confirm both build a cart per non-empty target. The cart copies the original customer, channel, currency, and billing and shipping addresses. Product lines are the assigned quantities, with the original line meta. The original order's cart is not rewritten.

The coupon code is set only on the cart the administrator chose. Loyalty meta is copied only onto the cart they chose. Other carts omit both.

`shippingOptionOverride` is a `ShippingOption` with the original shipping line's identifier, name, and tax class, and with the price the administrator entered. When the manifest can price that identifier for the subset, the field starts at that price. When it cannot, the field starts at zero and the preview says automatic pricing was unavailable. Recalculate after the override is set, so tax uses the entered price. Do not call `setShippingOption()`. Its validators can reject a price the manifest did not return, and the entered price is intentionally allowed to differ.

Preview runs this inside a database transaction and rolls it back. Pipelines persist cart rows and loyalty meta. The rollback is what keeps the preview from creating orders or carts. Confirm runs the same calculation and commits.

The order-creation pipeline is not used. After calculate, write the order, its lines, and its addresses from the cart result, using the same money fields `UpdateOrderProductLines` copies: line prices, discount breakdown, shipping breakdown, tax breakdown, subtotal, discount total, shipping total, tax total, total, and fingerprint. Generate the reference from the new id with `OrderReferenceGenerator`. Set `placed_at` to now and `new_customer` to false. Do not dispatch `MarkAsNewCustomer` or `OrderPlacedEvent`.

Each cart line keeps the order line's stored unit price. `Cart::$unitPriceOverrides` is read by `GetUnitPrice` before the catalog lookup. A line is omitted from that map only when staff choose the catalog price, which is the price for the new quantity. The stored price is copied as-is, so a bulk break the new quantity would miss still applies. Discounts, the coupon, loyalty, and shipping run after that price is set. The form shows the choice on a product only when the catalog price for an assigned quantity differs from the stored unit price, and the choice defaults to the original price.

Alternative considered: always reprice from the catalog. A split would then change what the customer owes whenever a quantity break no longer applies.

Copied meta is the customer context: `language_locale`, `shippingType`, `is_guest`, `isBillingSameAsShipping`, and the selected `payment_option` and `payment_type`. Loyalty keys are copied only for the chosen order. `awb`, `billing_series`, `billing_number`, invoice timestamps, and `erp_sent_at` are not copied.

Initial status is `payment-offline` when the payment method is `cash-on-delivery`, `cash-in-hand`, or `offline`. Otherwise it is `awaiting-payment`. The new order is created in that status, so creation is not a status change and does not run the stock listener. The split does not call `decreaseStock()` or `increaseStock()`.

Alternative considered: clone the original order row and delete lines. Observers, transactions, and meta would be cloned with it, including invoice and ERP identity.

### Coupon, Buy X Get Y, and loyalty each keep one outcome

Investigated against the current discount pipeline:

- A coupon is one code and one cart-level amount. Copying it onto every new order would discount the same code many times. The administrator picks one target, or explicitly applies it to none. Minimum spend is judged on the original order. When that order qualified, the chosen target receives the coupon even though its own total is below the minimum. The split calculation skips that minimum check for this coupon, because `checkDiscountConditions()` would otherwise clear the code. The same cart also keeps that coupon when the discount has since expired, the original order already consumed its use limit, or it no longer lists the assigned products: `DiscountManager` force-loads the code without the active/product filters, `checkDiscountConditions()` skips the use limits for that code, and `getEligibleLines()` prices every line on that cart. A percentage coupon is calculated from the chosen target's products. A fixed coupon uses the configured amount, and when that amount is larger than the target's product total it is applied up to that total. `applyFixedValueForCart()` currently applies nothing in that case, so the split must cap it instead of returning early. The preview shows the amount that will be stored. There is no silent `coupon_code = null` on confirm. Explicitly applying it to none is the way to drop it.
- Automatic line discounts are not assigned. Each target's cart runs `filterDiscountsByPriority` for its own lines. A product that moves takes its line discount with it. A fixed line discount that fails the minimum-remaining percentage on a smaller quantity is omitted, which is the current line rule, and the preview shows the total without it.
- Buy X Get Y qualifies on the set of lines in one cart. Splitting the qualifying product from the reward product drops the offer on both sides. The preview shows that. It is not given its own assignment control. A Buy X Get Y that is the order's coupon uses the coupon control instead.
- Loyalty uses the same single-target control. If `order:{id}:spend` does not exist, the administrator may apply it to none. The pipeline may cap unspent points, and the preview shows the cap. If the spend exists, the chosen target must accept every point. Otherwise confirm is rejected. On success, and still inside the order transaction, core dispatches a synchronous event. The loyalty listener reverses the original spend and spends the same points on the chosen order, with a unique event key. It does not call the placement spend a second time on top of the reversal. Core does not reference loyalty classes. A rolled-back split does not dispatch the event.

Alternative considered: split a coupon amount in proportion to the new subtotals. The pipeline has no partial-coupon mode, and the request asks for the discount to land on one order.

### The original order stays as the record and drops out of normal processing

On success the original order's lines, transactions, and addresses stay. Its status becomes `split`. Meta gains `split_into_order_ids`. Each new order's meta gains `split_from_order_id`. The order screen links these in both directions. No new column. Order links already live in meta (`erp_sent_at`, `awb`, invoice identifiers).

`split` is added to `lunar.orders.statuses` with an empty mailers list, an empty notifications list, and `exclude_from_reports`. `scopeReportable()` then omits it, so the original total is not added to the new orders' totals. The status is set with a normal save, not `UpdatesOrderStatus`, so no customer mailer runs. The activity log still records the status change.

The screen hides and the domain rejects status changes, product-line edits, and capture while the status is `split`. Product-line edit gets `split` as a rejected status in its own gate. Split is not added to invoice statuses, AWB statuses, loyalty earn, or loyalty cancel.

A published host `config/lunar/orders.php` that replaces `statuses` will not receive the new key on deploy. The stored status is still `split`, and the label falls back to that slug until the host adds the entry. The changelog tells them to add it.

### A new order does not take stock that the original order already took

`DecreaseStockForOrder` already skips when this order's own latest stock operation is a decrease. A new order has no stock activity of its own, so a later change into a decrease status would take the units again. The listener also skips when `meta.split_from_order_id` points at an order whose latest stock operation is a decrease. `IncreaseStockForOrder` restores the new order's quantities in that same case, so canceling the new order returns the units that were taken on receipt. If the original order's stock was later restored, the new order takes and returns stock on its own status changes.

### Offline new orders are sent after commit

After the transaction commits, core dispatches one event with the new orders. The ERP listener queues `SendOrderToERP` for each order whose `payment_option` is `cash-on-delivery`, `cash-in-hand`, or `offline`. That is the placement listener: it runs on the ERP queue, retries, and moves the order to `failed-erp-sync` after the last failure. Card, wallet, bank transfer, and hosted payment are not sent. Those wait for the host's payment authorization, which is when `OrderPlacedEvent` is normally fired. A failed send does not undo the split. The split confirmation still means the new orders were saved.

`OrderPlacedEvent` is not dispatched for the split. It would also notify Klaviyo, Mailchimp, and the host's customer mailer, and it would send card orders to the ERP before payment.

An order that is not split keeps the current ERP sync. The host still dispatches `OrderPlacedEvent` once when that order is received, and `SendOrderToERP` still forwards that one order. This change does not add a second send and does not change that listener.

### The modal stays a two-column assignment

The header action opens one wide modal with two columns at every width. The left column is the products. Each product is one row: its name and quantity, then the order it belongs to. The row fills that column. "New order" adds another target. A line of quantity one is assigned whole. "Divide quantity" on a larger line replaces that row with quantity inputs that must sum to the original quantity. Personalization meta is copied onto every part.

The right column is titled Preview. Each new order is a block headed with its name, such as New order 1. That block lists the products assigned to it, the order total, the shipping input, and the payment method, so the shipping cost and payment belong to that named order. When a coupon exists, one select above the blocks chooses its target, including "do not apply". Loyalty, when present, gets the same kind of select. The confirm button stays disabled until every unit is assigned, at least two targets have products, shipping amounts are non-negative, and the coupon and loyalty choices are valid.

The preview is recalculated on the server from the form. The client does not send totals. Confirm sends the assignment, the shipping amounts, the payment methods, and the coupon and loyalty choices. Under the lock, the operation checks that the original lines and quantities still match the assignment. A mismatch rejects the save.

Labels go in all 16 `order` locale files. English and Hungarian carry the full sentences. The other locales get the same keys.

## Risks / Trade-offs

- [A cash-on-delivery order without `erp_sent_at` may already be in Magister from before timestamps existed.] → The modal warns when sending is enabled and the status is outside the pre-send list. The split still does not update that external order. New sends write the timestamp, so this shrinks.
- [Splitting Buy X Get Y across the qualifying product and the reward drops the offer.] → The preview shows the loss before confirm. Forcing the offer onto one side would discount products that are not on that order.
- [A chosen order is below the coupon minimum, or a fixed coupon is larger than that order's products.] → Minimum spend stays on the original order, so the coupon still applies. A fixed amount is capped at the chosen order's product total, and the preview shows that capped amount.
- [The original coupon has expired, or no longer lists the assigned products.] → The coupon still applies on the chosen target because it already appeared on the original order. Expiry and product limitation are waived only for that re-application.
- [An automatic fixed line discount can vanish when the divided quantity is too small for the minimum-remaining percentage.] → That is the current line rule. The preview total is the amount that will be stored.
- [A published status config omits `split`, so the admin label is the slug and the status filter does not list it.] → The changelog says to add the status. Behavior does not depend on the label.
- [A host invoice or AWB list that includes `split` would invoice or label the original order when its status changes.] → The package lists do not include it. The status is not `awaiting-payment` or `prepare-shipment`.
- [ERP send for an offline new order fails after commit.] → The split remains. The child is queued on `SendOrderToERP` and becomes `failed-erp-sync` after the last retry. Card orders are not sent early.
- [Klaviyo and Mailchimp do not learn about the new orders.] → Accepted. Placement sync is tied to `OrderPlacedEvent`, which would also send unpaid card orders to the ERP.
- [Preview rollback holds a transaction while the pipelines run.] → One transaction per preview refresh, then rollback. No carts remain. Confirm is a separate transaction.
- [The original lines remain, so a report that ignores `exclude_from_reports` would count them again.] → `scopeReportable()` is the revenue scope. The status flag is set. Dashboard counts use `payment-received`, which `split` is not.
- [A later decrease status on a new order would take stock that the original order already took.] → `DecreaseStockForOrder` skips that order when the source order's latest stock operation is a decrease. Canceling the new order restores its quantities.

## Migration Plan

Deploy the application code. No schema migration. Orders that are already dispatched, paid, invoiced, labeled, or sent are unchanged and cannot be split.

Hosts that publish `lunar.orders.statuses` add a `split` entry with `exclude_from_reports` set and with empty `mailers` and `notifications`. Until they do, the status still saves and the label falls back to `split`.

Rollback is a code rollback. Orders already marked `split` stay in that status. The new orders stay independent orders. There is no automatic merge.

## Open Questions

None. The gates, the single destination for a coupon and for loyalty, and the refusal to split a paid order are specified so implementation does not pick a different set. An order whose stock was already taken can be split.
