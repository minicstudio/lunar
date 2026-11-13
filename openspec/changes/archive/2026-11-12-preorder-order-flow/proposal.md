# Proposal

## Why

A cart that contains a preorder product is still priced, placed, invoiced, and shipped like a normal order. Preorder shipping and payment are agreed on the phone, so the engine must charge shipping only for the other products and must not start a label, an invoice, or an ERP send until staff have made that call.

## What Changes

- Automatic shipping prices use only product lines that are not preorder. A cart or order made only of preorder products still receives its shipping option, at price 0, and that option records that the shipping amount is still to be agreed. A cart with no preorder lines keeps today's price.
- Creating an order from a cart that contains a preorder line records that the order is waiting for a phone consultation. The admin order page shows that notice and the customer's phone number.
- While that wait is open, the engine does not generate an AWB, does not generate an ERP invoice, and does not send the order to the ERP. Staff end the wait from a modal on the order page. The modal records the payment method and, unless delivery is store collection, the full shipping price, then sets the status from that payment method. After that, the existing status-driven label, invoice, and ERP behavior applies. The modal itself does not create an AWB or send the order to the ERP.
- Product-line edits and order splits already recalculate shipping and already set a payment method per new order. They keep doing that. A new order created by a split waits for consultation only when that new order itself still contains a preorder line.

This change does not add the checkout sentences, the payment-link email, or the proforma and bank-details email. Those stay in lunar-frontend. This repo already queues whatever mailers a status lists. Marking a variant preorder, editing product lines, and splitting an order stay as they are.

## Capabilities

### New Capabilities

- `orders/preorder-shipping-charge`: Automatic shipping is priced from non-preorder lines, and a preorder-only shipment is offered at price 0 with the amount still to be agreed.
- `orders/preorder-consultation`: An order that contains a preorder line waits for a phone consultation, staff see the customer's phone number, and label, invoice, and ERP export stay off until staff end that wait.

### Modified Capabilities

- None. `inventory/preorder-purchasability` already keeps a preorder variant buyable without moving stock. `orders/admin-product-line-editing` already recalculates the current shipping option and rejects the save when that option cannot be priced. A preorder-only option priced at 0 is still priced, so that requirement stays as written.

## Impact

- Table-rate shipping methods that price from the cart (`ShipBy` by cart total and by weight, flat rate, and free-shipping minimum spend). Collection stays a zero-price pickup and is not treated as shipping still to be agreed. Orders with no preorder lines are unchanged.
- Order creation copies the cart shipping total, so a placed order stores the reduced shipping charge without a second pricing rule. Admin product-line edits and splits pick up the same prices because they already recalculate through these methods. On a split, staff can still replace the calculated shipping amount.
- Admin order page: a notice when the order is waiting, showing the shipping contact phone, or the billing contact phone when the shipping phone is empty. Consultation done opens a modal. On a mixed order it first asks whether to keep the order together or split it. The header Split action stays hidden while that mixed order is waiting. Keep, and a preorder-only order, asks staff to choose the payment method and, unless delivery is store collection, the full shipping price. Splitting from that choice uses the same payment list and status map on each new order and records the consultation as complete, so neither new order is waiting. The shipping field starts from the price that method would charge for every product line as a simple product. When the method charges by weight, that price uses the weight of every product line, including preorder lines. Store collection stays at 0 and has no shipping field. Card (`bt-ipay` or `stripe-card`) becomes Awaiting Payment. Bank transfer becomes Awaiting Bank Transfer. Cash on delivery becomes Payment Offline. The agreed shipping total and the consultation timestamp are stored before that status change. Checkout still places the order as `awaiting-phone-consultation`, and the order stays there until this submit. Update Status on a waiting order asks staff to confirm before the status form opens. Cancel leaves the order unchanged.
- Carrier AWB observer, ERP invoice observer, and the placement and split ERP listeners skip an order that is still waiting. A normal order, and a split child that has no preorder line, keep the current paths.
- Storefront change `preorder-checkout-flow` in lunar-frontend shows the phone-call and deferred-payment text, and a sentence that preorder shipping is agreed by phone whenever a preorder line is present and delivery is not store collection. It displays this engine's shipping total. `preorder_shipping_pending` is set only on a preorder-only courier or locker option. A mixed cart has a shipping total for the other products and no flag. That storefront places the order as `awaiting-phone-consultation` with `placed_at` set, does not authorize card, bank transfer, or cash on delivery, and does not dispatch placement. This modal's save of `awaiting-payment` on a card order emails a payment link. Checkout card payment does not. This modal's save of `awaiting-bank-transfer` sends the same bank-transfer email checkout sends, with the invoice attached. `payment-offline` sends no payment email.
