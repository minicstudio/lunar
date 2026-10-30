# Proposal

## Why

Staff can open an order in the admin panel, but they cannot change its products. Quantity, added lines, and removed lines have to be fixed before the order is paid, sent to the ERP, invoiced, or given a shipping label (AWB). After any of those happens, the order must stay as the external systems already received it.

## What Changes

- On the order screen, an administrator with `sales:manage-orders` can change the quantity of a product line, add a product, or remove a product line. Adding a product asks for the product and a quantity. When that product has more than one variant, the administrator chooses one of those variants. A product with a single variant uses that variant without asking.
- Those actions are available only when the order is still unpaid, has not been sent to the ERP, has no invoice series and number, and has no AWB. If any one of those is true, the actions are hidden and the save is rejected.
- Each save recalculates product subtotals, discounts, coupons, the prices that apply, shipping cost for the order's current shipping option, taxes, loyalty redemption already stored on the order, and the order total. The order screen then shows the saved figures.
- The save is one database transaction. A failed save leaves the order, its lines, the linked cart, and any loyalty spend unchanged.
- A successful ERP send records `meta.erp_sent_at` on the order. That timestamp is what later edits use. If sending is enabled and the timestamp is missing, an order is treated as already sent unless its status is `awaiting-payment`, `failed-erp-sync`, or `invalid-address`. If no provider is enabled to send orders, a missing timestamp means the order was not sent.
- The order must keep at least one product line. Addresses, customer, status, notes, shipping method, and payment data are not part of this edit.

## Capabilities

### New Capabilities

- `orders/admin-product-line-editing`: When an admin may change an order's product lines, which line operations are allowed, and how totals stay consistent after a save.

### Modified Capabilities

- None. This repository has no existing OpenSpec capabilities.

## Impact

- Admin order screen (`ManageOrder`, `OrderItemsTable`) and all 16 `order` locale files. The list and the status action stay as they are.
- Core order and cart totals. Recalculation uses the existing cart pipelines. The order-creation pipeline is not reused: it resets status, reference, and meta, and it would spend loyalty points again.
- ERP: `ErpService::sendOrder` writes `meta.erp_sent_at` when a provider accepts the order. No ERP update call is added, because an order that was already sent cannot be edited.
- Loyalty: if a recalculation changes `loyalty_points_to_redeem` and a spend already exists for that order, the ledger is adjusted by the difference in the same transaction.
- Shipping labels, invoices, Mailchimp, and Klaviyo are not updated from this edit. An order that already has an AWB or an invoice cannot be edited, and placement sync is not repeated.
- Variant stock moves only when the order history shows stock was already taken, using the same status-history check as the storefront. A removed or reduced line returns that quantity. An added or increased line takes only the extra units, and only those extra units can fail the stock check. If stock was never taken, or a later status already restored it, the edit leaves stock unchanged so a later status change does not move it twice.
