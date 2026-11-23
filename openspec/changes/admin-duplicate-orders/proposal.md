# Proposal

## Why

Staff can edit or split an order, but they cannot start a new order from one that already exists. Repeating a past purchase means entering the customer, addresses, products, and shipping method again. Duplication should do that from the order screen and leave the original order untouched.

## What Changes

- On the order screen, a staff member who may manage orders can duplicate any order, including one that is paid, dispatched, or already split. The action opens a modal. A staff member who may not manage orders cannot create the duplicate.
- The modal requires a payment method. It starts on the source order's method when that method is still configured. When the copied lines need shipping, the modal also requires a shipping method from the methods those lines would be offered, and a shipping price. The price starts as the amount calculated for the selected method. Changing the method replaces the price with that method's calculated amount. Staff can type a different non-negative price. Cancelling creates nothing. Opening the modal or changing the method does not save an order.
- The new order is its own order: a new reference, `placed_at` set to the time of duplication, and the status configured for the chosen payment method. Cash on delivery starts as `payment-offline` under the current configuration. The chosen method does not start payment, invoicing, an AWB, the ERP, or marketing sync. The original order's status, lines, totals, discounts, and meta do not change. The new order takes stock for its own lines. The link back to the source is stored only on the new order.
- Customer, user, channel, and currency are copied. Billing and shipping addresses are copied. Product lines are copied with the same purchasable and quantity, and with that line's notes. Order notes, the customer reference, and tags are copied. The confirmed shipping method and price are stored. Product prices are the current catalog prices for that channel and currency, not the prices stored on the original lines.
- Automatic discounts are calculated again for the new lines. A coupon is applied only when the current coupon rules accept it. A coupon that is inactive, expired, over its use limit, below its minimum, or no longer eligible is left off, and the order is still created. A typed shipping price is kept even when a free-shipping discount would otherwise waive it. Loyalty redemption is not copied and points are not spent.
- The duplicate is rejected, and nothing is saved, when a product cannot be bought, a quantity or stock rule rejects a line, a required address is incomplete, the payment method is not configurable, a shippable order has no offered shipping method, or the confirmed method is not offered. A preorder or always-purchasable variant still does not need stock. No partial order is created.
- Duplication checks stock, then decreases it for the copied quantities. A preorder variant is left unchanged. A later shipment status does not take those units again. It does not create a payment, capture, or transaction. It does not dispatch order placement. Invoice identifiers, AWB, ERP send time, loyalty meta, traffic source, and a completed preorder consultation are not copied. A new order that contains a preorder line therefore waits for consultation under the existing rule.

## Capabilities

### New Capabilities

- `orders/admin-order-duplication`: When staff may duplicate an order, what the modal collects, what is copied, how prices, discounts, shipping, and stock are decided, and which integrations stay idle.

### Modified Capabilities

- None. Split, product-line editing, preorder purchasability, preorder consultation, preorder shipping, free-shipping discounts, and traffic reporting keep their current requirements. This change uses those rules for a newly created order and does not change them.

## Impact

- Admin order screen (`ManageOrder`) and all 16 `order` locale files. A header action opens a modal for payment, shipping method, and shipping price, then redirects to the new order.
- A core action that previews the offered shipping methods and their calculated prices without saving, then builds a new cart from the source order and runs the existing order-creation pipeline with the confirmed method and price. It does not use the customer's storefront cart and does not run the split action.
- No schema change. The source order id is stored in the new order's meta. No new status and no change to payment, ERP, invoice, or shipping listeners. The initial status is the one already configured for the chosen payment method.
