# Spec Delta

## Purpose

Lets an administrator create a new, independent order from an existing one, choose its payment method, shipping method, and shipping price, and leave the source order and fulfilment integrations untouched.

## ADDED Requirements

### Requirement: Authorized staff can duplicate an order
The order screen MUST offer Duplicate order to a staff member who may manage orders, for every order, including one that is paid, dispatched, or split. The action MUST open a modal. Confirming that modal MUST create exactly one new order. Cancelling MUST create nothing.

#### Scenario: Action is offered
- **WHEN** an authorized administrator opens any order
- **THEN** the order screen offers Duplicate order

#### Scenario: Confirmation creates one order
- **WHEN** the administrator confirms the modal
- **THEN** exactly one new order is created

#### Scenario: Cancel creates nothing
- **WHEN** the administrator cancels the modal
- **THEN** no order is created and the source order is unchanged

### Requirement: Unauthorized duplication is rejected
A staff member who may not manage orders MUST NOT be able to create a duplicate.

#### Scenario: Unauthorized save is rejected
- **WHEN** a staff member who may not manage orders submits a duplicate
- **THEN** the system rejects it and creates no order

### Requirement: The modal collects payment and shipping
The modal MUST require a payment method from the configured payment options, defaulting to the source method when that method is still configured. When the copied lines need shipping, the modal MUST require a shipping method those lines would be offered and a shipping price. When they do not need shipping, the modal MUST NOT ask for shipping. Opening the modal MUST NOT create an order.

#### Scenario: Payment defaults to the source method
- **WHEN** an authorized administrator opens Duplicate order and the source payment method is still configured
- **THEN** the modal shows that payment method selected

#### Scenario: A shippable order asks for a method and a price
- **WHEN** the copied lines need shipping
- **THEN** the modal shows a shipping method and a shipping price and does not create an order

#### Scenario: An order that does not ship hides shipping
- **WHEN** the copied lines do not need shipping
- **THEN** the modal asks for a payment method only

### Requirement: The shipping price follows the selected method
The shipping price MUST start as the amount calculated for the selected shipping method. Changing the method MUST replace that price with the amount calculated for the newly selected method. A non-negative price the administrator types MUST be the price stored on the new order. A negative price MUST be rejected. Changing the method MUST NOT create an order.

#### Scenario: The calculated price is shown
- **WHEN** the modal selects a shipping method whose calculated price is 15
- **THEN** the shipping price field shows 15

#### Scenario: Another method replaces the price
- **WHEN** the administrator selects a different method whose calculated price is 8
- **THEN** the shipping price field shows 8 and no order exists yet

#### Scenario: A typed price is stored
- **WHEN** the administrator replaces the calculated price with 5 and confirms
- **THEN** the new order's shipping total is 5

#### Scenario: A negative price is rejected
- **WHEN** the administrator confirms a shipping price below zero
- **THEN** no order is created

### Requirement: The source order stays unchanged
A successful or rejected duplication MUST leave the source order's status, lines, totals, addresses, notes, meta, and transactions unchanged. A rejected duplication MUST also leave variant stock unchanged.

#### Scenario: Paid source is unchanged
- **WHEN** an authorized administrator duplicates a paid order
- **THEN** the source order keeps its status, lines, totals, meta, and transactions

#### Scenario: Rejected duplicate leaves the source
- **WHEN** duplication is rejected
- **THEN** the source order is unchanged

### Requirement: The duplicate is a new placed order
The new order MUST have its own reference, MUST be placed at the time of duplication, and MUST start in the status configured for the chosen payment method. It MUST record the source order, and the order screen MUST show that source.

#### Scenario: Identity is new
- **WHEN** an order is duplicated
- **THEN** the new order has a different reference and its placed time is the time of duplication

#### Scenario: The chosen method sets the status
- **WHEN** the administrator chooses a payment method
- **THEN** the new order's status is the status configured for that method

#### Scenario: Source is visible
- **WHEN** an administrator opens the new order
- **THEN** the order screen shows which order it was duplicated from

### Requirement: Customer and addresses are copied
The new order MUST copy the source customer, user, channel, and currency, and MUST copy the billing and shipping addresses. A later change to the new order's address MUST leave the source order unchanged.

#### Scenario: Customer and addresses match the source
- **WHEN** an order with a customer and both addresses is duplicated
- **THEN** the new order has the same customer, user, channel, currency, and address details

#### Scenario: Editing the copy leaves the source
- **WHEN** an administrator later changes the new order's shipping address
- **THEN** the source order's shipping address is unchanged

### Requirement: Chosen payment and shipping details are stored
The new order MUST store the payment method chosen in the modal. It MUST copy language, the guest flag, and whether billing matches shipping. It MUST set the shipping type from the chosen shipping method. It MUST keep the source payment type only when the chosen payment method is the source method.

#### Scenario: A different payment method is stored
- **WHEN** the administrator chooses a payment method other than the source method
- **THEN** the new order stores the chosen method and does not keep the source payment type

#### Scenario: Collection sets the shipping type
- **WHEN** the administrator chooses store collection
- **THEN** the new order's shipping type is pickup

### Requirement: Products are copied at current prices
Each product line MUST be copied with the same purchasable and quantity. Line notes and line details such as personalization MUST be copied. The unit price MUST be the current catalog price for the new order's channel and currency.

#### Scenario: Quantity and notes are kept
- **WHEN** a product line has a quantity above one, a line note, and personalization
- **THEN** the new line has that quantity, note, and personalization

#### Scenario: Catalog price replaces the stored price
- **WHEN** the current catalog price differs from the unit price stored on the source line
- **THEN** the new line uses the current catalog price

### Requirement: Notes, customer reference, and tags are copied
The new order MUST copy the source order's notes, customer reference, and tags.

#### Scenario: Order notes and tags are kept
- **WHEN** the source order has notes, a customer reference, and tags
- **THEN** the new order has the same notes, customer reference, and tags

### Requirement: The confirmed shipping method and price are stored
The new order MUST use the shipping method confirmed in the modal. When the confirmed price is the calculated price, the new order MUST store that calculated amount, including zero when shipping is free. When the confirmed price differs, the new order MUST store the typed price, and a free-shipping discount MUST NOT replace it. When the copied lines do not need shipping, the new order MUST NOT gain a shipping line.

#### Scenario: The calculated price is kept
- **WHEN** the administrator confirms a method without changing its calculated price
- **THEN** the new order uses that method and stores the calculated price

#### Scenario: A typed price wins over free shipping
- **WHEN** the copied lines qualify for free shipping and the administrator confirms a price greater than zero
- **THEN** the new order stores that typed price

#### Scenario: An order without shipping stays without it
- **WHEN** the copied lines do not need shipping and the administrator confirms
- **THEN** the new order has no shipping line

### Requirement: Automatic discounts are recalculated
A discount that is not a coupon MUST be recalculated from the copied lines. A discount the new lines no longer qualify for MUST be omitted. The administrator MUST NOT choose these discounts by hand.

#### Scenario: A qualifying discount is applied
- **WHEN** the copied lines qualify for an automatic discount
- **THEN** the new order includes that discount at the amount calculated for those lines

#### Scenario: A discount that no longer qualifies is omitted
- **WHEN** the copied lines no longer qualify for an automatic discount that was on the source order
- **THEN** the new order does not include that discount

### Requirement: A coupon is applied only when it currently qualifies
When the source order has a coupon, the new order MUST include it only when current coupon rules accept it, including active dates, use limits, minimum spend, and eligibility. When those rules reject it, the new order MUST be created without that coupon.

#### Scenario: A valid coupon is applied
- **WHEN** the source coupon still passes the current coupon rules
- **THEN** the new order includes that coupon

#### Scenario: An ineligible coupon is omitted
- **WHEN** the source coupon is expired, over its use limit, below its minimum spend, or no longer eligible
- **THEN** the new order is created without that coupon

### Requirement: Loyalty is not copied
The new order MUST NOT receive the source order's loyalty redemption, and duplication MUST NOT spend or restore points.

#### Scenario: Points stay where they are
- **WHEN** the source order redeems loyalty points
- **THEN** the new order has no loyalty redemption and the point balance is unchanged

### Requirement: A duplicate that breaks current rules is rejected
The system MUST reject the duplicate and create no order when the order has no product line, a product cannot be bought, a quantity rule rejects a line, a stock-tracked variant cannot cover the quantity, a required address is incomplete, the payment method is not configured, a shippable order is given a shipping method it would not be offered, or a shippable order has no offered shipping method. A preorder or always-purchasable variant MUST NOT be rejected for lack of stock.

#### Scenario: An unavailable product is rejected
- **WHEN** a copied product is missing or cannot be bought
- **THEN** no order is created and the source order is unchanged

#### Scenario: Insufficient stock is rejected
- **WHEN** a stock-tracked variant cannot cover the copied quantity
- **THEN** no order is created

#### Scenario: A preorder does not need stock
- **WHEN** every copied variant is preorder or always purchasable, including one with no stock
- **THEN** the stock check does not reject the duplicate

#### Scenario: A missing address is rejected
- **WHEN** an address required for the new order is incomplete
- **THEN** no order is created

#### Scenario: An unoffered shipping method is rejected
- **WHEN** the administrator confirms a shipping method the copied lines would not be offered
- **THEN** no order is created

### Requirement: Duplication takes stock for the new order
A successful duplication MUST decrease each copied variant by that line's quantity. A preorder variant MUST keep its stock. The new order MUST record that stock was taken, so a later shipment status does not take the same units again and a later cancel can restore them. Setting the initial status MUST NOT itself be the stock movement. A rejected duplication MUST leave stock unchanged.

#### Scenario: Stock decreases for the copied quantity
- **WHEN** an order is duplicated and a copied variant tracks stock
- **THEN** that variant's stock is lower by the copied quantity

#### Scenario: A preorder variant is not decreased
- **WHEN** a copied variant is a preorder
- **THEN** that variant's stock is the same as before the duplicate

### Requirement: Payment and fulfilment do not start
Duplication MUST NOT create a transaction, capture, or payment intent. It MUST NOT generate an invoice or an AWB, MUST NOT send the order to the ERP, and MUST NOT send a marketing order sync. This MUST hold when the chosen payment method's status is `payment-offline`.

#### Scenario: No payment record is created
- **WHEN** an order that has a successful capture is duplicated
- **THEN** the new order has no transaction, capture, or payment intent

#### Scenario: Offline status does not start export
- **WHEN** the administrator chooses cash on delivery and confirms
- **THEN** the new order starts as `payment-offline` and no invoice, AWB, ERP send, or marketing order sync is started

### Requirement: Operational records are not copied
The new order MUST NOT copy invoice identifiers, an AWB, an ERP send time, loyalty data, traffic source, or a completed preorder consultation. A new order that has a preorder line and no completed consultation MUST be waiting for consultation.

#### Scenario: Invoice and shipping records stay behind
- **WHEN** the source order has invoice identifiers, an AWB, an ERP send time, and a traffic source
- **THEN** the new order has none of those values

#### Scenario: A preorder copy waits for consultation
- **WHEN** the source order has a preorder line whose consultation is complete
- **THEN** the new order's preorder line has no completed consultation and the new order is waiting for consultation

### Requirement: Staff open the new order
After a successful duplicate, the order screen MUST open the new order.

#### Scenario: The new order is shown
- **WHEN** duplication succeeds
- **THEN** the administrator is taken to the new order
