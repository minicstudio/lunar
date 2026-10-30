# admin-product-line-editing Specification

## Purpose

Lets an administrator change the product lines of an order that is still unpaid and has not been handed to the ERP, invoicing, or a shipping label, and keeps the order totals consistent with that change.

## Requirements

### Requirement: Product lines change only when every gate passes

The system MUST accept a product-line change only when the order is unpaid, has not been sent to the ERP, has no issued invoice, and has no AWB. If any gate fails, the system MUST reject the change and leave the order as it was.

#### Scenario: All gates pass

- **WHEN** an authorized administrator changes a product line on an order that is unpaid, not sent to the ERP, has no invoice series and number, and has no AWB
- **THEN** the system saves the change

#### Scenario: Any single gate fails

- **WHEN** an authorized administrator changes a product line and the order is paid, or has been sent to the ERP, or has an invoice series and number, or has an AWB
- **THEN** the system rejects the change and the stored order lines and totals stay as they were

### Requirement: Paid orders cannot be edited

The system MUST treat an order as paid when its status is `payment-received` or it has a successful capture. Cash on delivery with no successful capture, and an intent that has not been captured, MUST be treated as unpaid. A later refund MUST NOT make a captured order unpaid.

#### Scenario: Captured order is locked

- **WHEN** an order has a successful capture
- **THEN** product-line changes are rejected

#### Scenario: Authorized but not captured order is unpaid

- **WHEN** an order has a successful intent and no successful capture, and its status is not `payment-received`
- **THEN** the payment gate does not block a product-line change

#### Scenario: Cash on delivery is unpaid

- **WHEN** an order has no successful capture and its status is not `payment-received`
- **THEN** the payment gate does not block a product-line change

### Requirement: ERP send blocks editing

The system MUST treat an order as sent to the ERP when `meta.erp_sent_at` is set. When that value is empty and an enabled provider can send orders, the system MUST also treat the order as sent unless its status is `awaiting-payment`, `failed-erp-sync`, or `invalid-address`. When no enabled provider can send orders and `meta.erp_sent_at` is empty, the system MUST treat the order as not sent.

#### Scenario: Timestamp is set

- **WHEN** `meta.erp_sent_at` is set
- **THEN** product-line changes are rejected

#### Scenario: Historical order outside the pre-send statuses

- **WHEN** `meta.erp_sent_at` is empty, an enabled provider can send orders, and the status is not `awaiting-payment`, `failed-erp-sync`, or `invalid-address`
- **THEN** product-line changes are rejected

#### Scenario: Failed or not-yet-sent status

- **WHEN** `meta.erp_sent_at` is empty and the status is `awaiting-payment`, `failed-erp-sync`, or `invalid-address`
- **THEN** the ERP gate does not block a product-line change

#### Scenario: Sending is not enabled

- **WHEN** no enabled provider can send orders and `meta.erp_sent_at` is empty
- **THEN** the ERP gate does not block a product-line change

### Requirement: Issued invoice blocks editing

The system MUST treat an invoice as issued when both `meta.billing_series` and `meta.billing_number` are filled.

#### Scenario: Invoice identifiers are present

- **WHEN** both `meta.billing_series` and `meta.billing_number` are filled
- **THEN** product-line changes are rejected

#### Scenario: Only one invoice identifier is present

- **WHEN** only one of `meta.billing_series` and `meta.billing_number` is filled
- **THEN** the invoice gate does not block a product-line change

### Requirement: AWB blocks editing

The system MUST treat an AWB as generated when `meta.awb` is filled.

#### Scenario: AWB is present

- **WHEN** `meta.awb` is filled
- **THEN** product-line changes are rejected

#### Scenario: AWB is absent

- **WHEN** `meta.awb` is empty
- **THEN** the AWB gate does not block a product-line change

### Requirement: Successful ERP send stores a timestamp

When a provider accepts an order, the system MUST set `meta.erp_sent_at` without changing the order status. A failed send MUST NOT set `meta.erp_sent_at`.

#### Scenario: Provider accepts the order

- **WHEN** an ERP provider accepts the order
- **THEN** `meta.erp_sent_at` is stored and the order status is unchanged

#### Scenario: Provider rejects the order

- **WHEN** an ERP provider does not accept the order
- **THEN** `meta.erp_sent_at` stays empty

### Requirement: Authorized staff can change a product quantity

An administrator who can manage orders MUST be able to set a new quantity on an existing product line when the order is editable. The stored quantity MUST be the submitted quantity after a successful save.

#### Scenario: Quantity is updated

- **WHEN** an authorized administrator sets an editable order line to a new valid quantity
- **THEN** that line is stored with the new quantity and the order totals match the recalculated order

#### Scenario: User cannot manage orders

- **WHEN** a user who cannot manage orders submits a product-line change
- **THEN** the system rejects the change and the stored order stays as it was

### Requirement: Authorized staff can add a product

An administrator who can manage orders MUST be able to add a purchasable product variant to an editable order. The add-product action MUST ask for a product and a quantity. When the chosen product has more than one variant, the action MUST ask the administrator to choose one of those variants and MUST NOT offer variants of another product. When the chosen product has exactly one variant, the action MUST use that variant and MUST NOT ask for a variant. Adding a variant that already has a product line with the same line meta MUST increase that line's quantity instead of creating a second line.

#### Scenario: New variant is added

- **WHEN** an authorized administrator adds a purchasable variant that is not already on the editable order
- **THEN** the order gains one product line for that variant and the order totals match the recalculated order

#### Scenario: Product has several variants

- **WHEN** an authorized administrator opens add product and chooses a product that has more than one variant
- **THEN** the action offers those variants and no variants of another product

#### Scenario: Product has one variant

- **WHEN** an authorized administrator adds a product that has exactly one purchasable variant, and a quantity, without choosing a variant
- **THEN** the order gains one product line for that variant

#### Scenario: Existing variant is added again

- **WHEN** an authorized administrator adds a variant that already has a product line with the same line meta
- **THEN** that line's quantity increases by the added quantity and no second line is created

### Requirement: Authorized staff can remove a product

An administrator who can manage orders MUST be able to remove a product line from an editable order when at least one other product line remains. The system MUST reject removal of the last product line.

#### Scenario: One of several lines is removed

- **WHEN** an authorized administrator removes a product line and another product line remains
- **THEN** the removed line is gone and the order totals match the recalculated order

#### Scenario: Last product line is removed

- **WHEN** an authorized administrator removes the only product line
- **THEN** the system rejects the change and that line remains

### Requirement: Quantity rules are enforced

A product quantity MUST be a whole number of at least 1 and MUST satisfy the variant's minimum and increment rules. The system MUST reject a variant that is not purchasable. Shipping lines MUST NOT be added, removed, or quantity-edited by these actions.

#### Scenario: Quantity is below one

- **WHEN** an authorized administrator submits a quantity below 1
- **THEN** the system rejects the change and the stored order stays as it was

#### Scenario: Variant is not purchasable

- **WHEN** an authorized administrator adds a variant that is not purchasable
- **THEN** the system rejects the change and the stored order stays as it was

### Requirement: Stock moves only while it is taken

The system MUST treat stock as taken only when the latest status-update activity whose new status is in the configured stock decrease or increase lists has a decrease status. That lookup MUST use the same activity history and the same two lists as the storefront stock actions. When stock is not taken, a product-line save MUST leave variant stock unchanged and MUST NOT reject the change for available stock.

#### Scenario: History has no stock decrease

- **WHEN** the order has no stock decrease or increase in its status history and an administrator removes a product line
- **THEN** variant stock is unchanged

#### Scenario: Stock was already restored

- **WHEN** the latest stock operation is an increase and an administrator removes a product line
- **THEN** variant stock is unchanged

### Requirement: Taken stock changes with the line difference

When stock is taken, removing a product line MUST increase that variant's stock by the removed quantity, and lowering a quantity MUST increase stock by the difference. Raising a quantity or adding a variant MUST decrease stock by the added quantity, and MUST reject the save when the variant cannot fulfill that added quantity. A rejected save MUST leave stock unchanged.

#### Scenario: Line removed after stock was taken

- **WHEN** the latest stock operation is a decrease and an administrator removes a product line
- **THEN** that variant's stock increases by the removed quantity

#### Scenario: Quantity lowered after stock was taken

- **WHEN** the latest stock operation is a decrease and an administrator lowers a line quantity
- **THEN** that variant's stock increases by the difference

#### Scenario: Added quantity cannot be fulfilled

- **WHEN** the latest stock operation is a decrease and the variant cannot fulfill the added quantity
- **THEN** the system rejects the change and variant stock is unchanged

#### Scenario: Added quantity is available

- **WHEN** the latest stock operation is a decrease and the variant can fulfill the added quantity
- **THEN** that variant's stock decreases by the added quantity

### Requirement: Save recalculates prices, discounts, shipping, and tax

A successful product-line change MUST store unit prices, line subtotals, discounts, coupons, loyalty redemption, shipping cost, taxes, and the order total from a fresh calculation for that order's customer, channel, currency, coupon, and current shipping option. The shipping option identifier MUST stay the same. If that option cannot be priced, the system MUST reject the change.

#### Scenario: Totals follow the new lines

- **WHEN** a product-line change is saved
- **THEN** the stored line subtotals, discount total, shipping total, tax total, and order total equal the freshly calculated amounts

#### Scenario: Coupon no longer qualifies

- **WHEN** the changed lines no longer qualify for the order's coupon
- **THEN** the stored discount no longer includes that coupon and the order total is calculated without it

#### Scenario: Current shipping option cannot be priced

- **WHEN** the order's current shipping option cannot be priced for the changed lines
- **THEN** the system rejects the change and the stored order stays as it was

### Requirement: Order identity and line meta stay in place

A product-line change MUST NOT change the order status, reference, placement time, customer, addresses, notes, payment metadata, or shipping option identifier. Existing product-line meta, including personalization, MUST be kept on a line whose quantity changes. The order screen MUST show the stored totals after a successful save.

#### Scenario: Status and reference survive a quantity change

- **WHEN** a product quantity is saved
- **THEN** the order status, reference, and shipping option identifier are unchanged

#### Scenario: Personalization survives a quantity change

- **WHEN** a product line with personalization meta has its quantity saved
- **THEN** that line still has the same personalization meta

### Requirement: A failed save leaves no partial order

If validation, recalculation, or persistence fails, the system MUST leave the order lines, order totals, linked cart lines, variant stock, and loyalty balance as they were before the save.

#### Scenario: Persistence fails after lines were staged

- **WHEN** a product-line save fails before it commits
- **THEN** the stored order lines, totals, linked cart lines, variant stock, and loyalty balance match the pre-save state

#### Scenario: Order has no linked cart

- **WHEN** an editable order has no linked cart and an administrator submits a product-line change
- **THEN** the system rejects the change and the stored order stays as it was

### Requirement: Loyalty spend matches the saved redemption

When a successful save changes the loyalty points redeemed on an order that already has a loyalty spend, the system MUST adjust the customer's loyalty balance by that difference in the same save. A rejected save MUST NOT change the balance.

#### Scenario: Recalculation redeems fewer points

- **WHEN** a successful save reduces the loyalty points redeemed on an order that already spent those points
- **THEN** the customer's loyalty balance increases by the returned points and the order stores the reduced redemption

#### Scenario: Save is rejected

- **WHEN** a product-line change is rejected for an order that already spent loyalty points
- **THEN** the customer's loyalty balance is unchanged

### Requirement: The order screen offers the three actions only while editing is allowed

The order screen MUST show quantity change, add product, and remove product only while the order is editable and the viewer can manage orders. When the order is not editable, the screen MUST hide those actions and show which gate failed, and MUST say the order cannot be modified because of that gate.

#### Scenario: Editable order shows the actions

- **WHEN** an authorized administrator opens an editable order
- **THEN** the order screen offers quantity change, add product, and remove product

#### Scenario: Locked order explains the gate

- **WHEN** an authorized administrator opens an order that fails one or more gates
- **THEN** the order screen hides the three actions, names each failed gate, and says the order cannot be modified because of it
