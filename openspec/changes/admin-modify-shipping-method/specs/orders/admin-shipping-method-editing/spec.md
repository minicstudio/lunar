# Spec Delta

## Purpose

Lets an administrator change the shipping method on an order that is still open for product-line edits, and reprices that method, the tax, and the order total from the options checkout would offer.

## ADDED Requirements

### Requirement: Shipping method changes only when every product-line gate passes

The system MUST accept a shipping-method change only when a product-line change on that order would be accepted. If any product-line gate fails, the system MUST reject the shipping-method change and leave the order as it was.

#### Scenario: All gates pass

- **WHEN** an authorized administrator sets a shipping method on an order that would accept a product-line change
- **THEN** the system saves the method

#### Scenario: Any gate fails

- **WHEN** an authorized administrator sets a shipping method on an order that would reject a product-line change
- **THEN** the system rejects the change and the stored order stays as it was

### Requirement: Only staff who can manage orders can change the method

A user who cannot manage orders MUST NOT be able to save a shipping method. The stored order MUST stay as it was.

#### Scenario: User cannot manage orders

- **WHEN** a user who cannot manage orders submits a shipping method
- **THEN** the system rejects the change and the stored order stays as it was

### Requirement: The action sits under the method name

The order screen MUST show "Modify shipping method" on the shipping strip above the product list, directly under the shipping method name in the left column. It MUST show that action only when the viewer can manage orders, a product-line change would be accepted, and the order has exactly one shipping line. Other locales MUST use the same meaning.

#### Scenario: Editable order with one shipping line

- **WHEN** an authorized administrator opens an editable order that has one shipping line and the locale is English
- **THEN** the shipping strip offers "Modify shipping method" directly under the shipping method name

#### Scenario: Locked order

- **WHEN** an authorized administrator opens an order that would reject a product-line change
- **THEN** the shipping strip hides the action and the existing gate notice still names each failed gate

#### Scenario: No shipping line

- **WHEN** an authorized administrator opens an editable order that has no shipping line
- **THEN** the screen does not offer the action and no shipping line is created

#### Scenario: Several shipping lines

- **WHEN** an authorized administrator opens an editable order that has more than one shipping line
- **THEN** the screen does not offer the action

### Requirement: The choice is one option checkout would offer

The action MUST ask for one shipping option. The options MUST be only those checkout would offer for this order's current customer, shipping address, channel, currency, and product lines, including zone, weight, and exclusion rules, except a parcel locker. A parcel locker is an option whose identifier ends in `-locker`. A personal-pickup order uses that same stored shipping address. Checkout already saved the billing address as that shipping address. When the method already on the order is one of those options, the choice MUST start on that method. When two options share a name, the choice MUST show the zone with the name.

#### Scenario: Only eligible options are offered

- **WHEN** an authorized administrator opens the action and checkout would offer two shipping options for this order and not a third
- **THEN** the choice offers those two options and does not offer the third

#### Scenario: Current method is still offered

- **WHEN** an authorized administrator opens the action and the method already on the order would still be offered at checkout
- **THEN** the choice starts on that method

#### Scenario: Shared name shows the zone

- **WHEN** two offered options have the same name and different zones
- **THEN** the choice shows each name with its zone

#### Scenario: A locker is not offered

- **WHEN** an authorized administrator opens the action and checkout would offer a courier and a parcel locker
- **THEN** the choice offers the courier and does not offer the locker

#### Scenario: Personal pickup uses the stored shipping address

- **WHEN** an authorized administrator opens the action on a personal-pickup order and checkout would offer a courier for the stored shipping address
- **THEN** the choice offers that courier

### Requirement: The same method and an unoffered method are rejected

Submitting the shipping option identifier already stored on the order MUST be rejected with a message that the method is already on the order, and a shipping price saved by an administrator MUST stay. Submitting an identifier checkout would not offer, or a parcel-locker identifier, MUST be rejected with a message that the method is not available. In each case the stored order MUST stay as it was.

#### Scenario: Current method is submitted again

- **WHEN** an authorized administrator submits the shipping option identifier already on the order, and that order has a shipping price saved by an administrator
- **THEN** the system rejects the change, tells the administrator that the method is already on the order, the identifier stays, and the saved shipping price stays

#### Scenario: Locker identifier is submitted

- **WHEN** an authorized administrator submits a parcel-locker identifier
- **THEN** the system rejects the change and the stored order stays as it was

#### Scenario: Identifier is not offered

- **WHEN** an authorized administrator submits a shipping option identifier checkout would not offer for this order
- **THEN** the system rejects the change and the stored order stays as it was

### Requirement: The chosen option replaces the method and its price

A successful save MUST store the chosen option's identifier and name on the single shipping line, and MUST store that option's zone. The shipping price, shipping tax, and order total MUST equal a fresh calculation of that option for the current lines, with no administrator shipping price applied. When store prices include tax, the order shipping total MUST equal that calculation's shipping total. The order MUST still have exactly one shipping line.

#### Scenario: Courier replaces personal pickup

- **WHEN** an authorized administrator selects a courier option whose fresh calculation charges 15.00 and store prices include tax
- **THEN** the shipping line identifier and name are that courier's, the order shipping total is 15.00, and the order total changes by the same difference as the shipping total

#### Scenario: Collection is free

- **WHEN** an authorized administrator selects a collection option whose fresh calculation charges 0
- **THEN** the shipping line name is that collection option and the shipping subtotal is 0

#### Scenario: One shipping line remains

- **WHEN** an authorized administrator saves a new shipping method
- **THEN** the order has exactly one shipping line and that line has the chosen identifier

### Requirement: A saved shipping price does not survive the method change

A successful save MUST clear a shipping price previously saved by an administrator. A later product-line change MUST price the new method again, and MUST NOT restore the cleared price.

#### Scenario: Saved price is cleared

- **WHEN** an authorized administrator changes the shipping method on an order whose shipping price was saved by an administrator
- **THEN** the stored shipping price is the fresh calculation of the new method and the saved-price marker is gone

#### Scenario: Later product edit follows the new method

- **WHEN** an authorized administrator changes a product quantity after the shipping method was changed
- **THEN** the shipping line keeps the new identifier and its shipping price is a fresh calculation of that method

### Requirement: A free-shipping discount can waive the new method

When a qualifying free-shipping discount, coupon or automatic, waives the chosen option, the shipping line MUST store the option's price as its unit price, the waived amount as its discount total, and 0 as its subtotal, tax total, and total. Product discounts from that discount MUST still apply.

#### Scenario: Free-shipping coupon still qualifies

- **WHEN** an authorized administrator selects a method whose rate is 12.00 and the order's free-shipping coupon still qualifies
- **THEN** the shipping line unit price is 12.00, its discount total is 12.00, and its subtotal and the order shipping total are 0

### Requirement: The stored option identifier follows the method

A successful save MUST store the chosen option's identifier as the shipping option on the linked cart. When the order shipping address stores a shipping option, that stored identifier MUST be the chosen option. The postal address MUST stay as it was.

#### Scenario: Cart uses the new option

- **WHEN** an authorized administrator saves a new shipping method
- **THEN** the linked cart's shipping option identifier is the chosen option and the postal address is unchanged

#### Scenario: Order address stores the new option

- **WHEN** an authorized administrator saves a new shipping method and the order shipping address already stores a shipping option
- **THEN** that stored identifier is the chosen option and the postal address is unchanged

### Requirement: Personal pickup to address delivery keeps the shipping address

Personal pickup means store collection or shipping type `pickup`. Address delivery means the chosen option is not store collection. That change MUST leave the postal address as it was and MUST price the new method for that address. Checkout already stored the billing address as the shipping address. The billing address MUST stay unchanged. A personal-pickup order with no billing address MUST still save.

#### Scenario: Shipping address stays

- **WHEN** an authorized administrator changes a personal-pickup order to an address delivery and the shipping address is Customer Street
- **THEN** the shipping address is Customer Street and the new method is priced for that address

#### Scenario: No billing address

- **WHEN** an authorized administrator changes a personal-pickup order to an address delivery and the order has no billing address
- **THEN** the system saves the method and the shipping address stays as it was

### Requirement: The save sets the shipping type from the chosen option

A successful save MUST set `meta.shippingType` to `pickup` when the chosen option is store collection, and to `courier` otherwise. The linked cart MUST store the same value. A change from personal pickup to address delivery MUST still leave the postal address as it was.

#### Scenario: Pickup becomes courier

- **WHEN** an authorized administrator changes a personal-pickup order to an address delivery
- **THEN** `meta.shippingType` on the order and the linked cart is `courier`

#### Scenario: Locker becomes courier

- **WHEN** an authorized administrator changes an order whose shipping type is `locker` to a courier
- **THEN** `meta.shippingType` on the order and the linked cart is `courier`

#### Scenario: Courier becomes collection

- **WHEN** an authorized administrator changes a courier order to a store-collection option
- **THEN** `meta.shippingType` on the order and the linked cart is `pickup`

### Requirement: The screen warns when the shipping address matches the billing address

After a successful change from personal pickup to address delivery, when the stored shipping address matches the stored billing address, the order screen MUST warn that the shipping address corresponds to the billing address and that staff should update the shipping address when the delivery address differs. When those addresses differ, or the order has no billing address, the order screen MUST NOT show that warning. Any other successful method change MUST NOT show that warning.

#### Scenario: Warning when the addresses match

- **WHEN** an authorized administrator successfully changes a personal-pickup order to an address delivery, the stored shipping address matches the stored billing address, and the locale is English
- **THEN** the order screen warns that the shipping address corresponds to the billing address and that the shipping address should be updated when the delivery address differs

#### Scenario: No warning when the addresses differ

- **WHEN** an authorized administrator successfully changes a personal-pickup order to an address delivery and the stored shipping street differs from the billing street
- **THEN** the order screen does not show that warning and the shipping street stays as it was

#### Scenario: No warning for another method change

- **WHEN** an authorized administrator successfully changes the shipping method and the order is not personal pickup
- **THEN** the order screen does not show that warning

### Requirement: Order identity stays in place

A shipping-method save MUST NOT change the order status, reference, placement time, customer, notes, or payment metadata. It MUST NOT mark a phone consultation complete, create an AWB, create an invoice, or send the order to the ERP. Variant stock MUST stay unchanged.

#### Scenario: Waiting order keeps its consultation

- **WHEN** an authorized administrator saves a shipping method on an order that is still waiting for a phone consultation
- **THEN** the order status and payment option are unchanged, and the consultation is still incomplete

#### Scenario: Stock is unchanged

- **WHEN** an authorized administrator saves a shipping method
- **THEN** every variant's stock is unchanged

### Requirement: A failed save leaves no partial order

If validation or persistence fails, the system MUST leave the order lines, order totals, linked cart, variant stock, and loyalty balance as they were. An editable order with no linked cart MUST be rejected. A chosen option that cannot be priced at save time MUST be rejected.

#### Scenario: Order has no linked cart

- **WHEN** an editable order has no linked cart and an administrator submits a shipping method
- **THEN** the system rejects the change and the stored order stays as it was

#### Scenario: Chosen option cannot be priced

- **WHEN** the chosen option cannot be priced for the current lines at save time
- **THEN** the system rejects the change and the stored order stays as it was

#### Scenario: Save fails before it commits

- **WHEN** a shipping-method save fails before it commits
- **THEN** the stored order lines, totals, linked cart, variant stock, and loyalty balance match the pre-save state

### Requirement: Loyalty follows the new shipping total

When a successful save changes the loyalty points redeemed on an order that already has a loyalty spend, the system MUST adjust the customer's loyalty balance by that difference in the same save. A rejected save MUST NOT change the balance.

#### Scenario: Recalculation redeems fewer points

- **WHEN** a successful shipping-method save reduces the loyalty points redeemed on an order that already spent those points
- **THEN** the customer's loyalty balance increases by the returned points and the order stores the reduced redemption

#### Scenario: Save is rejected

- **WHEN** a shipping-method change is rejected for an order that already spent loyalty points
- **THEN** the customer's loyalty balance is unchanged

### Requirement: The order screen shows the new method and price

After a successful save, the shipping strip MUST show the stored method name and the stored shipping price.

#### Scenario: Screen shows the new method

- **WHEN** an authorized administrator saves a shipping method
- **THEN** the shipping strip shows the stored method name and the stored shipping price
