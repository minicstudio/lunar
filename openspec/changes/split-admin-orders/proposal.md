# Proposal

## Why

An order that mixes in-stock products with preorder products has to ship, and sometimes be paid, as more than one delivery. Staff can change product lines on an unpaid order, but they cannot turn that order into several independent orders. Doing it by hand would copy lines, take stock twice, and apply a cart coupon to every copy.

## What Changes

- On the order screen, an administrator with `sales:manage-orders` can split one placed order into two or more independent orders. Each product unit is assigned to exactly one new order. A line with quantity greater than one can be divided.
- Before anything is saved, a preview shows each new order's products, quantities, recalculated totals, payment method, and shipping cost. The shipping cost starts from the calculated price and can be edited. A cart coupon and any loyalty redemption are each assigned to one new order, not copied onto all of them.
- Confirming saves every new order, or none. Each new order gets its own reference, addresses, payment method, and totals. The original order becomes `split`, keeps its lines as the record of what was split, and is no longer processed as a normal order.
- The split does not take or return stock. An order whose stock was already decreased when it was received can still be split, including an order that mixes an in-stock product with a preorder product. The split is refused when the order is already paid, invoiced, labeled, sent to the ERP, dispatched, or already split. A later stock-decrease status on a new order does not take the same units again. Canceling that new order restores its quantities. Preorder variants keep their current stock behavior.
- Automatic product discounts are recalculated on each new order from the products assigned to it. When the original order qualified for a coupon, the administrator assigns that coupon to one new order. That order receives it even when its own total is below the coupon minimum. The other new orders do not.

## Capabilities

### New Capabilities

- `orders/admin-order-split`: When an administrator may split an order, how products, quantities, shipping, coupons, and loyalty are divided, and how the original order and stock stay consistent.

### Modified Capabilities

- None. `inventory/preorder-purchasability` already states that preorder variants do not move stock. This change uses that behavior and does not change its requirements.

## Impact

- Admin order screen (`ManageOrder`) and all 16 `order` locale files. A header action opens the split modal. Split orders lose the normal processing actions (status change, product-line edit, capture).
- Core order creation path used by the split. It must not run the order-creation pipeline: that pipeline resets status, reference, and meta, and the loyalty creation stage would spend points again. Totals still come from the cart pipelines.
- New `split` status in `lunar.orders.statuses`, excluded from report revenue so the original total is not counted again beside the new orders. Hosts that publish a replacement status list need this status added.
- Stock still decreases once, when the order is received, in lunar-frontend. A later split is allowed. The storefront stock actions skip a second decrease on a new order whose source order already took stock, and they restore that new order's quantities if it is canceled.
- ERP, invoicing, AWB, Klaviyo, and Mailchimp are not run for the original order. An order that is not split keeps the current ERP sync and is still forwarded once when it is received. Offline-payment new orders are sent to the ERP after the split commits. Card and bank-transfer orders wait for their own payment, which is the existing placement rule. No customer email is sent because the order was split.
- Loyalty: when a spend already exists on the original order, that spend moves to the one new order that receives the redemption, inside the same transaction.
