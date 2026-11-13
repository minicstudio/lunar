# Proposal

## Why

Staff can set the shipping price on an order that is still open for product edits, but the shipping method stays whatever checkout chose. They need to switch that method under the same lock, for example from personal pickup to a courier the order actually qualifies for, and the price has to follow the new method instead of a price they typed earlier.

## What Changes

- On the order screen, an administrator who can manage orders can change the shipping method. The action is **Modify shipping method**, on the shipping strip, directly under the method name on the left. It opens a modal with one required choice. The choices are only the shipping options this order would be offered at checkout for its current customer, address, channel, currency, and product lines, including zone, weight, and exclusion rules. Parcel lockers, options whose identifier ends in `-locker`, are left out, and submitting one is rejected. The current method is preselected when it is still one of those options. Saving the method already on the order is rejected, and a saved shipping price stays. An identifier that is not currently offered is rejected.
- A successful save stores the chosen option's identifier, name, and zone, then prices that option with the same cart pipelines a product-line save uses when no administrator price is set. Any saved shipping-price override is cleared. A qualifying free-shipping discount, coupon or automatic, can set the shipping charge to 0 on this save. Tax and the order total follow. A later product-line save keeps the new method and prices it again, including a free-shipping waiver. The order still has exactly one shipping line.
- The action uses the same lock as product-line editing and the shipping-price action: the order is unpaid, has not been sent to the ERP, has no invoice series and number, and has no AWB. The same permission applies. When a gate fails, both shipping actions are hidden and the existing gate notice stays the explanation. The save is rejected if a gate fails. An order with no shipping line, or with more than one, has neither action.
- **Modify shipping price** moves from beside the price to directly under it, still end-aligned in the right column of the same strip. Its save behavior is unchanged: it still keeps the method and stores the typed amount.
- A successful save sets `meta.shippingType` from the chosen option: `pickup` for store collection, otherwise `courier`, on the order and the linked cart. Personal pickup is store collection or shipping type `pickup`. Address delivery is an option that is not store collection. A change from personal pickup to an address delivery leaves the postal address as checkout stored it. Checkout already saved the billing address as the shipping address, so the options offered on a personal-pickup order are the ones checkout would offer for that stored address, including a courier such as Sameday when that address is in a Sameday zone. An order with no billing address still saves. Any method change leaves the postal address as it was.
- After a personal-pickup order becomes an address delivery, and the stored shipping address matches the stored billing address, the order screen shows a warning that the shipping address corresponds to the billing address and that staff should edit the shipping address if the delivery address is different. They use the address edit already on the order. A differing address, a missing billing address, and other successful method changes do not show that warning. Submitting the method already on the order is rejected with its own message.
- The save does not change status, payment, the consultation, locker selection, or stock, and it does not create an AWB, an invoice, or an ERP send. The stored shipping-option identifier on the cart, and on the order address when one is stored, is updated to the chosen option so the next calculation uses it.

## Capabilities

### New Capabilities

- `orders/admin-shipping-method-editing`: When an admin may change an order's shipping method, which options are offered, and how the stored method, price, tax, and order total stay consistent.

### Modified Capabilities

- `orders/admin-shipping-price-editing`: **Modify shipping price** is shown under the shipping price. The requirement that places it beside the price changes. This capability is specified by the completed, unarchived change `admin-modify-shipping-price` and is not in `openspec/specs/` yet. Archive that change before this one so the modified requirement has a main spec to update.

## Impact

- Admin order screen: the shipping strip on `ManageOrder` (`DisplaysShippingInfo`), the strip CSS that currently keeps the price and its action on one line, the warning after a personal-pickup order becomes an address delivery, and all 16 `order` locale files.
- Core order recalculation. The new save points the linked cart at the chosen option, clears `shipping_price_override`, and reuses the cart pipelines already used for a product-line save. A personal-pickup order that moves to address delivery keeps the stored shipping address, so the courier price uses that address. The order-creation pipeline is not reused. `writeShippingLine()` must update the existing shipping line when the identifier changes, so the order does not gain a second shipping line.
- Shipping-price editing. The price action's placement changes. A price saved before a method change does not survive that method change. A price saved after it still sticks, including across a later product edit.
- Free-shipping discounts. Clearing the override means the existing waiver applies again. That spec does not change.
- Consultation done, order split, shipping labels, invoices, and the ERP are unchanged. This action does not complete a phone consultation and does not send the order anywhere.
