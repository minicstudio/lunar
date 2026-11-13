# Spec Delta

## Purpose

Lets an administrator split one order into two or more independent orders, with a preview, per-order shipping and payment, and a single home for any cart coupon or loyalty redemption.

## ADDED Requirements

### Requirement: Authorized staff can split an eligible order

The order screen MUST offer Split order to a staff member who may manage orders when every split gate passes. A staff member who may not manage orders MUST NOT be able to save a split.

#### Scenario: Action is offered

- **WHEN** an authorized administrator opens an order that passes every split gate
- **THEN** the order screen offers Split order

#### Scenario: Unauthorized save is rejected

- **WHEN** a staff member who may not manage orders submits a split
- **THEN** the system rejects it and creates no order

### Requirement: Unsafe orders cannot be split

The system MUST reject a split when the order status is `split` or `dispatched`, when the status is `payment-received` or the status history includes `payment-received`, when a successful capture exists, when an invoice series and number exist, when an AWB exists, or when the order has been sent to the ERP. Stock that was already taken when the order was received MUST NOT reject the split. An order that has none of the rejecting conditions MUST be allowed, including `payment-offline` and an order whose stock was already decreased.

#### Scenario: Paid order is rejected

- **WHEN** the status is `payment-received`, the status history includes `payment-received`, or the order has a successful capture
- **THEN** the split is rejected and no new order is created

#### Scenario: Bank transfer moved on from payment received is rejected

- **WHEN** the status was `payment-received` and is now another status, and the order has no successful capture
- **THEN** the split is rejected

#### Scenario: External document is rejected

- **WHEN** the order has an ERP send timestamp, or both an invoice series and number, or an AWB
- **THEN** the split is rejected

#### Scenario: Stock already taken is still allowed

- **WHEN** the order's latest stock-related status change decreased stock
- **THEN** the split gates do not reject it

#### Scenario: Unpaid order without those records is allowed

- **WHEN** the status is `awaiting-payment` or `payment-offline`, the status history does not include `payment-received`, and the order has no successful capture, invoice, AWB, or ERP send timestamp
- **THEN** the split gates do not reject it, whether or not stock was already decreased

#### Scenario: Closed order is rejected

- **WHEN** the status is `dispatched` or `split`
- **THEN** the split is rejected

### Requirement: Every unit goes to one new order

The administrator MUST be able to create two or more target orders and assign every product unit to one of them. The system MUST reject a confirmation that leaves a unit unassigned, assigns a unit more than once, or leaves fewer than two targets with products.

#### Scenario: Two orders receive every unit

- **WHEN** every unit is assigned to one of two targets and the administrator confirms a valid preview
- **THEN** two orders are created and each unit is on exactly one of them

#### Scenario: A third order is added

- **WHEN** the administrator adds a third target and assigns at least one unit to each target
- **THEN** three orders are created

#### Scenario: A unit is unassigned

- **WHEN** any unit has no target
- **THEN** confirmation is rejected and nothing is saved

#### Scenario: One target would hold everything

- **WHEN** every unit is assigned to a single target
- **THEN** confirmation is rejected

### Requirement: A line quantity can be divided

A product line with quantity greater than one MUST be divisible across targets. The assigned quantities for that line MUST sum to the original quantity. Each assigned part MUST keep that line's purchasable and line meta, including personalization.

#### Scenario: Quantity is divided

- **WHEN** a line of quantity 3 is assigned as 1 and 2
- **THEN** the new orders contain those quantities and no further quantity of that line

#### Scenario: Divided quantities do not match

- **WHEN** the assigned quantities for a line do not sum to its original quantity
- **THEN** confirmation is rejected

#### Scenario: Personalization is copied

- **WHEN** a personalized line is assigned to a target
- **THEN** the new order's line stores the same personalization meta

### Requirement: Preview before anything is saved

Before confirmation, the system MUST show each non-empty target with its products, quantities, and recalculated amounts. Changing the assignment, shipping cost, payment method, coupon target, loyalty target, or unit price choice MUST refresh those amounts. Confirmation MUST store the server calculation for that input. Opening or refreshing the preview MUST NOT create an order.

#### Scenario: Preview writes nothing

- **WHEN** the administrator changes an assignment and the preview refreshes
- **THEN** no new order has been stored

#### Scenario: Shipping edit refreshes the total

- **WHEN** the administrator changes a target's shipping cost
- **THEN** that target's displayed total includes the new shipping cost and the tax on it

#### Scenario: Stored totals match the server

- **WHEN** the administrator confirms
- **THEN** each stored total is the amount the server calculated for that assignment and those inputs

### Requirement: Stored unit price is kept unless staff choose the catalog price

Each product line MUST keep the unit price stored on the original order. That includes a bulk price the new quantity would no longer qualify for. When the catalog price for a quantity assigned to the line differs from that stored unit price, the split form MUST offer a choice on that product and say that the price has changed since the order was placed. "Original price" copies the stored unit price. "Current price" uses the catalog price at each assigned quantity. The choice MUST default to the original price. Discounts, the coupon, loyalty, and shipping MUST be recalculated for either choice.

#### Scenario: A bulk price survives a smaller quantity

- **WHEN** a line was bought at a bulk unit price and a split quantity no longer qualifies for that price
- **THEN** the preview and the stored line keep the original unit price

#### Scenario: The current catalog price is optional

- **WHEN** staff choose the current price for that line
- **THEN** each assigned quantity uses the catalog price for that quantity, and discounts, the coupon, loyalty, and shipping are still recalculated

#### Scenario: The choice is hidden when the prices match

- **WHEN** the catalog price for every quantity assigned to a line equals the stored unit price
- **THEN** the form does not show the price choice for that line

#### Scenario: A coupon uses the chosen unit price

- **WHEN** staff keep the original unit price and assign the coupon to that order
- **THEN** the coupon is calculated from those original unit prices

### Requirement: Shipping cost is calculated and editable

Each target MUST start with the shipping cost calculated for its products using the original order's shipping method. The administrator MUST be able to replace that cost with another non-negative amount. The stored shipping total MUST be the entered amount, and tax MUST include it. The new order MUST keep the original shipping method identity. When the method cannot be priced, the field MUST start at zero and the preview MUST say that automatic pricing was unavailable.

#### Scenario: Calculated cost is the starting value

- **WHEN** the preview is built for a target whose shipping method can be priced
- **THEN** the shipping field shows that calculated cost

#### Scenario: Entered cost is stored

- **WHEN** the administrator replaces the shipping cost and confirms
- **THEN** the new order's shipping total is the entered amount and its tax includes that amount

#### Scenario: Pricing is unavailable

- **WHEN** the shipping method cannot be priced for that target's products
- **THEN** the field starts at zero, the preview explains that automatic pricing was unavailable, and a cost can still be entered

### Requirement: Each new order has its own payment method

Each target MUST show a payment method, defaulting to the original order's method. The administrator MUST be able to change it before confirming. The new order MUST store the selected method and MUST NOT receive the original order's transactions.

#### Scenario: Default method is shown

- **WHEN** the preview opens
- **THEN** each target shows the original payment method

#### Scenario: A different method is stored

- **WHEN** the administrator selects another payment method for one target and confirms
- **THEN** that order stores the selected method and has no copied transaction

### Requirement: New orders start in the status for their payment method

A new order paid by cash on delivery, cash in hand, or offline MUST start as `payment-offline`. Every other new order MUST start as `awaiting-payment`. Every new order MUST be placed and MUST have its own reference. Customer billing and shipping details MUST be copied, and a later edit of one new order's address MUST leave the original order and the other new orders unchanged.

#### Scenario: Cash on delivery starts offline

- **WHEN** a new order is confirmed with cash on delivery
- **THEN** its status is `payment-offline`, it has its own reference, and it is placed

#### Scenario: Card payment waits for payment

- **WHEN** a new order is confirmed with a card payment method
- **THEN** its status is `awaiting-payment` and it has its own reference

#### Scenario: Addresses are independent copies

- **WHEN** an administrator later changes one new order's shipping address
- **THEN** the original order and the other new orders keep the addresses they had

### Requirement: A coupon is applied to one chosen order

When the original order has a coupon, the administrator MUST choose one target to receive it, or explicitly apply it to none. The coupon MUST NOT be applied to more than one new order. Minimum spend MUST be taken from the original order, so a chosen target below that minimum MUST still receive the coupon when the original order qualified. When that coupon already appeared on the original order, the chosen target MUST still receive it even if the discount has since expired, reached its use limit because the original order consumed it, or no longer lists the assigned products as eligible. Applying the coupon to none MUST price every new order without it.

#### Scenario: Coupon is only on the chosen order

- **WHEN** the administrator assigns the coupon to one target and confirms
- **THEN** only that order's discount includes the coupon

#### Scenario: The chosen order is below the minimum spend

- **WHEN** the original order qualified for the coupon and the chosen target's products are below the coupon minimum
- **THEN** that order's discount includes the coupon and confirmation succeeds

#### Scenario: The original coupon has expired

- **WHEN** the original order used a coupon that has since expired, and the administrator assigns it to one target and confirms
- **THEN** that order's discount still includes the coupon

#### Scenario: The original order already consumed the coupon's use limit

- **WHEN** the original order used a coupon that allows one use per customer, and the administrator assigns it to one target and confirms
- **THEN** that order's discount still includes the coupon

#### Scenario: The original coupon no longer matches the assigned products

- **WHEN** the original order used a coupon that is limited to other products, and the administrator assigns it to a target whose products are outside that limitation and confirms
- **THEN** that order's discount still includes the coupon on those assigned products

#### Scenario: Coupon is explicitly not applied

- **WHEN** the administrator chooses to apply the coupon to no target and confirms
- **THEN** every new order is priced without that coupon

### Requirement: The coupon amount follows the chosen order

A percentage coupon MUST be calculated from the chosen target's products. A fixed coupon larger than those products MUST be applied up to that product total. The preview MUST show the amount that will be stored.

#### Scenario: A percentage coupon uses the chosen products

- **WHEN** the administrator assigns a percentage coupon to one target and confirms
- **THEN** the discount is that percentage of the chosen target's products

#### Scenario: A fixed coupon is larger than the chosen order

- **WHEN** the administrator assigns a fixed coupon to a target whose products total less than the coupon amount
- **THEN** the discount equals that product total, the preview shows that amount, and confirmation succeeds

### Requirement: Automatic discounts follow the assigned products

A discount that is not a coupon MUST be recalculated for each target from the products assigned to it. The administrator MUST NOT assign those discounts by hand. A cart offer that the split breaks apart MUST be omitted from a target that no longer qualifies, and the preview MUST show that total.

#### Scenario: A product discount stays with its product

- **WHEN** a product with an automatic line discount is assigned to one target
- **THEN** that new order includes the discount and the others do not

#### Scenario: A cart offer no longer qualifies

- **WHEN** a cart offer required products that the split places on different targets
- **THEN** a target keeps the offer only when its own products still qualify, and the preview shows the totals without a lost offer

### Requirement: Loyalty redemption is applied to one chosen order

When the original order redeems loyalty points, the administrator MUST assign that redemption to one target. It MUST NOT apply to more than one new order. Points that were already spent MUST move in full onto a target that can accept all of them. Points that were not spent MAY be explicitly applied to none. A failed split MUST leave an existing spend on the original order.

#### Scenario: Only the chosen order is reduced

- **WHEN** the administrator assigns the redemption to one target and that target can accept it
- **THEN** only that order's total includes the loyalty discount

#### Scenario: A spent redemption moves

- **WHEN** the original order already spent the points and the split succeeds
- **THEN** the spend belongs to the chosen order and no longer belongs to the original order

#### Scenario: Spent points do not fit

- **WHEN** the points were already spent and the chosen target cannot accept all of them
- **THEN** confirmation is rejected and the original spend stays in place

#### Scenario: Unspent points are explicitly not applied

- **WHEN** the points have not been spent and the administrator applies the redemption to no target
- **THEN** no new order receives the loyalty discount

### Requirement: The original order becomes split

A successful split MUST set the original order's status to `split` and MUST keep its product lines. Status changes, product-line edits, and payment capture on that order MUST be rejected. Report revenue MUST exclude the original order and include the new orders.

#### Scenario: Status and lines remain

- **WHEN** a split succeeds
- **THEN** the original status is `split` and its product lines are still stored

#### Scenario: Normal processing is rejected

- **WHEN** an administrator changes the status, edits product lines, or captures payment on a split order
- **THEN** the system rejects that action

#### Scenario: Revenue is not counted twice

- **WHEN** reports sum order totals
- **THEN** the split order is excluded and the new orders are included

### Requirement: The split stays traceable

Each new order MUST record the original order, and the original order MUST record every new order. The order screen MUST show that relationship from either side.

#### Scenario: Both directions are stored

- **WHEN** a split succeeds
- **THEN** each new order points at the original order and the original order points at each new order

#### Scenario: The screen shows the relationship

- **WHEN** an administrator opens the original order or a new order
- **THEN** the screen links to the related orders

### Requirement: A failed split leaves no partial result

The product quantities on the new orders MUST sum to the original quantities. If confirmation fails, the system MUST leave no new order, MUST leave the original order unchanged, and MUST leave stock unchanged.

#### Scenario: Quantities are conserved

- **WHEN** a split succeeds
- **THEN** the new orders together contain the same purchasable quantities as the original order

#### Scenario: A failure rolls back

- **WHEN** saving a new order fails
- **THEN** no new order remains, the original status is unchanged, and stock is unchanged

### Requirement: The split does not move stock

The split MUST NOT decrease or increase variant stock. A preorder variant MUST keep its stock and backorder. Creating the new orders MUST NOT take stock. When the original order already decreased stock, a later status change on a new order that would normally decrease stock MUST NOT decrease it again. Canceling that new order MUST restore the quantities on its product lines.

#### Scenario: An in-stock variant is unchanged

- **WHEN** a split succeeds, including when stock for that order was already taken
- **THEN** each variant's stock and backorder equal the values from before the split

#### Scenario: A preorder variant is unchanged

- **WHEN** a split assigns a preorder variant beside an in-stock variant whose stock was already decreased
- **THEN** that preorder variant's stock and backorder stay the same, and the in-stock variant is not decreased again

#### Scenario: A later decrease status does not take stock again

- **WHEN** a new order was split from an order whose stock was already taken, and that new order later enters a status that decreases stock
- **THEN** variant stock stays unchanged

#### Scenario: Canceling a new order restores stock that was already taken

- **WHEN** a new order was split from an order whose stock was already taken, and that new order is canceled
- **THEN** stock increases by the quantities on that new order

### Requirement: External systems are not run for the original order

The split MUST NOT email the customer because the order was split. It MUST NOT generate an invoice or an AWB for the original order, and it MUST NOT send the original order to the ERP. After the split has committed, a new order whose payment method is cash on delivery, cash in hand, or offline MUST be queued on the same ERP send as a placed order, including its retries. After that send's last failure, the new order's status MUST be `failed-erp-sync`. A failed send MUST NOT delete the new order. A new order with any other payment method MUST NOT be sent by the split.

#### Scenario: No split email

- **WHEN** a split succeeds
- **THEN** the customer is not emailed because the order was split

#### Scenario: An offline new order is sent

- **WHEN** a new order's payment method is cash on delivery and the split has committed
- **THEN** that order is queued on the same ERP send as a placed order

#### Scenario: A failed offline send becomes failed-erp-sync

- **WHEN** the queued ERP send for an offline new order fails on its last attempt
- **THEN** the new order remains and its status is `failed-erp-sync`

#### Scenario: A card new order is not sent

- **WHEN** a new order's payment method is a card and the split has committed
- **THEN** the split does not send that order to the ERP

#### Scenario: The original gains no invoice or label

- **WHEN** the original order becomes `split`
- **THEN** the split does not add an invoice or an AWB to it
