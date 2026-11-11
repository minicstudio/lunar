# Spec Delta

## Purpose

Lets an administrator set the shipping price on an order that is still open for product-line edits, and keeps that price, the tax, and the order total consistent afterwards.

## ADDED Requirements

### Requirement: Shipping price changes only when every product-line gate passes

The system MUST accept a shipping-price change only when a product-line change on that order would be accepted. If any product-line gate fails, the system MUST reject the shipping-price change and leave the order as it was.

#### Scenario: All gates pass

- **WHEN** an authorized administrator sets a shipping price on an order that would accept a product-line change
- **THEN** the system saves the price

#### Scenario: Any gate fails

- **WHEN** an authorized administrator sets a shipping price on an order that would reject a product-line change
- **THEN** the system rejects the change and the stored order stays as it was

### Requirement: Only staff who can manage orders can set the price

A user who cannot manage orders MUST NOT be able to save a shipping price. The stored order MUST stay as it was.

#### Scenario: User cannot manage orders

- **WHEN** a user who cannot manage orders submits a shipping price
- **THEN** the system rejects the change and the stored order stays as it was

### Requirement: The action sits on the shipping strip

The order screen MUST show "Modify shipping price" on the shipping strip above the product list, beside the price that strip shows. It MUST show that action only when the viewer can manage orders, a product-line change would be accepted, and the order has exactly one shipping line. Other locales MUST use the same meaning.

#### Scenario: Editable order with one shipping line

- **WHEN** an authorized administrator opens an editable order that has one shipping line and the locale is English
- **THEN** the shipping strip offers "Modify shipping price" beside the shipping price

#### Scenario: Locked order

- **WHEN** an authorized administrator opens an order that would reject a product-line change
- **THEN** the shipping strip hides the action and the existing gate notice still names each failed gate

#### Scenario: No shipping line

- **WHEN** an authorized administrator opens an editable order that has no shipping line
- **THEN** the screen does not offer the action and no shipping line is created

#### Scenario: Several shipping lines

- **WHEN** an authorized administrator opens an editable order that has more than one shipping line
- **THEN** the screen does not offer the action

### Requirement: The amount is zero or greater

The action MUST ask for one amount in the order currency, starting from the shipping line's current subtotal. The amount MUST be required and MUST be zero or greater. A negative amount MUST be rejected and the stored order MUST stay as it was.

#### Scenario: Field starts from the current subtotal

- **WHEN** an authorized administrator opens the action and the shipping line subtotal is 20.00
- **THEN** the amount starts at 20.00

#### Scenario: Zero is saved

- **WHEN** an authorized administrator submits 0
- **THEN** the system saves a shipping subtotal of 0

#### Scenario: Negative amount is rejected

- **WHEN** an authorized administrator submits an amount below 0
- **THEN** the system rejects the change and the stored order stays as it was

### Requirement: The entered amount becomes the shipping price

A successful save MUST store the entered amount as the shipping line unit price and subtotal. Shipping tax MUST be recalculated on that amount. When store prices include tax, the order shipping total MUST equal the entered amount. When store prices exclude tax, the order shipping total MUST equal the entered amount plus the recalculated shipping tax. The order total MUST change by the same difference as the shipping total. The shipping option identifier and description MUST stay the same.

#### Scenario: Prices include tax

- **WHEN** an authorized administrator sets the shipping price to 15.00 and store prices include tax
- **THEN** the shipping line unit price and subtotal are 15.00, the order shipping total is 15.00, and the order total changes by the same difference as the shipping total

#### Scenario: Prices exclude tax

- **WHEN** an authorized administrator sets the shipping price to 15.00, store prices exclude tax, and the recalculated shipping tax is 2.85
- **THEN** the shipping line unit price and subtotal are 15.00 and the order shipping total is 17.85

#### Scenario: Method stays

- **WHEN** an authorized administrator saves a new shipping price
- **THEN** the shipping line identifier and description are unchanged

### Requirement: A free-shipping discount does not waive the entered price

After a successful save, a qualifying free-shipping discount, coupon or automatic, MUST NOT replace the entered shipping price with a waived shipping total. Product discounts from that discount MUST still apply.

#### Scenario: Free-shipping coupon is on the order

- **WHEN** an authorized administrator sets the shipping price to 12.00 on an order whose free-shipping coupon still qualifies
- **THEN** the shipping line subtotal is 12.00 and the coupon's product discount still applies

### Requirement: The saved price remains until staff replace it

The saved shipping price MUST remain the order's shipping price until an authorized administrator saves a different one. A later product-line change MUST NOT replace it with a carrier price or a free-shipping waiver. An order whose shipping price was not saved by this action MUST still take a free-shipping waiver, including when the product-line change is what makes the order eligible.

#### Scenario: Quantity changes after the price was set

- **WHEN** an authorized administrator changes a product quantity after a shipping price was saved
- **THEN** the shipping line subtotal remains that saved price

### Requirement: Order identity stays in place

A shipping-price save MUST NOT change the order status, reference, placement time, customer, addresses, notes, payment metadata, or shipping option identifier. It MUST NOT mark a phone consultation complete, create an AWB, create an invoice, or send the order to the ERP. Variant stock MUST stay unchanged. A marker that the shipping amount is still to be agreed MUST be cleared.

#### Scenario: Waiting order keeps its consultation

- **WHEN** an authorized administrator saves a shipping price on an order that is still waiting for a phone consultation
- **THEN** the order status and payment option are unchanged, the consultation is still incomplete, and the shipping line is no longer marked as a price still to be agreed

#### Scenario: Stock is unchanged

- **WHEN** an authorized administrator saves a shipping price
- **THEN** every variant's stock is unchanged

### Requirement: A failed save leaves no partial order

If validation or persistence fails, the system MUST leave the order lines, order totals, linked cart, variant stock, and loyalty balance as they were. An editable order with no linked cart MUST be rejected.

#### Scenario: Order has no linked cart

- **WHEN** an editable order has no linked cart and an administrator submits a shipping price
- **THEN** the system rejects the change and the stored order stays as it was

#### Scenario: Save fails before it commits

- **WHEN** a shipping-price save fails before it commits
- **THEN** the stored order lines, totals, linked cart, variant stock, and loyalty balance match the pre-save state

### Requirement: Loyalty follows the saved order

When a successful save changes the loyalty points redeemed on an order that already has a loyalty spend, the system MUST adjust the customer's loyalty balance by that difference in the same save. A rejected save MUST NOT change the balance.

#### Scenario: Recalculation redeems fewer points

- **WHEN** a successful shipping-price save reduces the loyalty points redeemed on an order that already spent those points
- **THEN** the customer's loyalty balance increases by the returned points and the order stores the reduced redemption

#### Scenario: Save is rejected

- **WHEN** a shipping-price change is rejected for an order that already spent loyalty points
- **THEN** the customer's loyalty balance is unchanged

### Requirement: The order screen shows the saved price

After a successful save, the shipping strip and the order shipping total MUST show the stored amounts.

#### Scenario: Screen shows the new price

- **WHEN** an authorized administrator saves a shipping price
- **THEN** the shipping strip and the order shipping total show the stored amounts
