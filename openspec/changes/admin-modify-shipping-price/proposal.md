# Proposal

## Why

Staff can already change the products on an unpaid order that has no AWB, invoice, or ERP send, but the shipping charge stays whatever checkout or the last product edit calculated. They need to set that charge themselves, under the same lock, when the carrier price is wrong or was agreed with the customer.

## What Changes

- On the order screen, an administrator who can manage orders can set a new shipping price. The action is **Modify shipping price**, on the shipping strip above the product list, beside the price that strip already shows. It opens a modal with one amount, starting from the current shipping subtotal. Zero is allowed. A negative amount is rejected.
- The action is offered only while product-line editing is allowed: the order is unpaid, has not been sent to the ERP, has no invoice series and number, and has no AWB. The same permission applies. When a gate fails, the action is hidden and the existing gate notice stays the explanation. The save is rejected if a gate fails.
- The shipping method, its identifier, and its name stay as they are. The entered amount replaces the calculated shipping price. Tax is recalculated on that amount, and the order shipping total and order total follow. A free-shipping discount does not waive this amount.
- A price saved by this action stays in place when staff later add, remove, or change a product quantity. A product-line save still recalculates products, discounts, tax, and loyalty. It keeps that saved price instead of asking the carrier again, and a free-shipping discount does not waive it. An order with no saved price keeps today's carrier price. If a higher quantity or an added product makes that order eligible for free shipping, the shipping price becomes 0.
- The save does not change status, payment, the consultation, addresses, or stock, and it does not create an AWB, an invoice, or an ERP send. An order with no shipping line, or with more than one, has no action.

## Capabilities

### New Capabilities

- `orders/admin-shipping-price-editing`: When an admin may set an order's shipping price, where that action appears, and how the stored price, tax, and order total stay consistent.

### Modified Capabilities

- `orders/admin-product-line-editing`: A product-line save keeps a shipping price that staff already set, instead of replacing it with a fresh carrier price or a free-shipping waiver.

## Impact

- Admin order screen: the shipping strip on `ManageOrder` (`DisplaysShippingInfo`), and all 16 `order` locale files. Product actions, the gate notice, Update Status, and Download PDF stay where they are.
- Core order recalculation. The new save and a later product-line save both use the existing cart pipelines, with the entered price as a shipping override. The order-creation pipeline is not reused.
- Free-shipping discounts. The existing rule that an explicit shipping override is not waived already covers this price. That spec does not change.
- Consultation done, order split, shipping labels, invoices, and the ERP are unchanged. This action does not complete a phone consultation and does not send the order anywhere.
