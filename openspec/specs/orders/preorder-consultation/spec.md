# preorder-consultation Specification

## Purpose

Holds label creation, invoicing, and ERP export for an order that contains a preorder product until staff record that they have called the customer.

## Requirements

### Requirement: A preorder line stays a preorder line
A product line written from a variant whose purchasability is `preorder` MUST be recorded as a preorder line. A later change to that variant's purchasability MUST NOT remove that record. A line written from a variant that is not `preorder` MUST NOT be recorded as a preorder line.

#### Scenario: Order created with a preorder variant
- **WHEN** an order is created from a cart line whose variant purchasability is `preorder`
- **THEN** that order line is recorded as a preorder line

#### Scenario: Variant later leaves preorder
- **WHEN** a variant's purchasability changes away from `preorder` after the line was written
- **THEN** the order line stays recorded as a preorder line

#### Scenario: Normal line
- **WHEN** an order line is written from a variant that is not `preorder`
- **THEN** that line is not recorded as a preorder line

### Requirement: The order waits while a preorder line has not been confirmed
An order MUST be waiting for phone consultation when it has a preorder line and staff have not marked the consultation complete. An order with no preorder line MUST NOT be waiting. Once the consultation date is stored, adding a preorder line MUST keep that date on the existing lines and on the new line, MUST leave the order status unchanged, and MUST NOT make the order waiting again.

#### Scenario: Placed order with a preorder line
- **WHEN** an order is created with a preorder line and staff have not marked the consultation complete
- **THEN** the order is waiting for phone consultation

#### Scenario: Placed order without a preorder line
- **WHEN** an order is created with no preorder line
- **THEN** the order is not waiting for phone consultation

#### Scenario: Split child keeps only its own lines
- **WHEN** a split puts a preorder line on one new order and only non-preorder lines on another
- **THEN** the new order with the preorder line is waiting and the other new order is not

#### Scenario: A preorder line added before completion
- **WHEN** staff add a preorder line and the consultation has not been marked complete
- **THEN** the order is waiting for phone consultation and its status is unchanged

#### Scenario: A preorder line added after completion
- **WHEN** staff add a preorder line after the consultation was marked complete
- **THEN** the order is not waiting for phone consultation, the consultation date is unchanged, and the order status is unchanged

### Requirement: Staff see the consultation notice and the customer phone
While an order is waiting, the admin order page MUST tell staff that the order contains a preorder product and that they need to call the customer, and MUST show the customer's phone. The phone MUST be the shipping contact phone when that value is filled, and otherwise the billing contact phone. When neither phone is filled, the notice MUST still show and MUST say that no phone number is stored. When the order is not waiting, that notice MUST NOT show.

#### Scenario: Shipping phone is shown
- **WHEN** a waiting order has a shipping contact phone
- **THEN** the order page shows the consultation notice and that shipping phone

#### Scenario: Billing phone is the fallback
- **WHEN** a waiting order has no shipping contact phone and has a billing contact phone
- **THEN** the order page shows the consultation notice and that billing phone

#### Scenario: No phone is stored
- **WHEN** a waiting order has no shipping contact phone and no billing contact phone
- **THEN** the order page shows the consultation notice and says that no phone number is stored

#### Scenario: A confirmed order hides the notice
- **WHEN** the order is not waiting
- **THEN** the order page does not show the consultation notice

### Requirement: Staff finish the consultation from a modal
An administrator who can manage orders MUST be able to open the consultation action while the order is waiting and its status is not `split`. The action MUST ask for a payment method of `bt-ipay`, `stripe-card`, `bank-transfer`, or `cash-on-delivery`. Unless delivery is store collection, it MUST also ask for the full shipping price. That shipping field MUST start from the price the order's shipping method would charge if every product line were a simple product. When that method charges by weight, the price MUST use the weight of every product line, including preorder lines. When that price cannot be resolved, the field MUST start from the shipping total already stored. Store collection MUST NOT show a shipping field and MUST keep the shipping total at 0. The action MUST NOT be offered when the order is not waiting or when its status is `split`. The consultation notice MUST NOT show when the status is `split`.

When the waiting order has a recorded preorder line and another product line, the action MUST first ask whether to keep the order together or split it, and the header split action MUST stay hidden. Choosing to keep the order MUST open the payment and shipping form. Choosing to split MUST open the order split screen. Confirming that split MUST record the consultation as complete on the new orders, MUST set each new order's status from the same payment map, and MUST leave neither new order waiting. The in-stock order's shipping field MUST start from its calculated price. A new order whose lines are all preorder MUST start from the price that method would charge if those lines were simple products. Store collection MUST keep each new order's shipping total at 0 and MUST NOT ask for a shipping price. A preorder-only waiting order MUST open the payment and shipping form directly and MUST still offer the header split action when the order can be split. A split that was not opened from this choice MUST leave a child that receives a preorder line waiting.

Submitting the action MUST store the agreed shipping total, then record the consultation as complete, then set `meta.payment_option` and the order status together. The status MUST be `awaiting-payment` for `bt-ipay` or `stripe-card`, `awaiting-bank-transfer` for `bank-transfer`, and `payment-offline` for `cash-on-delivery`. Until that submit, the order status MUST stay unchanged. The shipping total MUST be the agreed amount before the status changes, and the consultation timestamp MUST already be stored before the status changes. Saving a shipping price MUST remove `preorder_shipping_pending` from the shipping line. The submit MUST NOT generate an AWB and MUST NOT send the order to the ERP. Existing AWB, invoice identifiers, and ERP send meta MUST stay as they were.

#### Scenario: Card payment
- **WHEN** an administrator submits the consultation with payment method `bt-ipay` or `stripe-card`
- **THEN** the consultation is complete and the order status is `awaiting-payment`

#### Scenario: Bank transfer
- **WHEN** an administrator submits the consultation with payment method `bank-transfer`
- **THEN** the consultation is complete and the order status is `awaiting-bank-transfer`

#### Scenario: Cash on delivery
- **WHEN** an administrator submits the consultation with payment method `cash-on-delivery`
- **THEN** the consultation is complete and the order status is `payment-offline`

#### Scenario: Shipping is stored before the status changes
- **WHEN** an administrator submits a shipping price for an order that is not store collection
- **THEN** the shipping line and the order shipping total already hold that price, without `preorder_shipping_pending`, before the status changes

#### Scenario: The shipping field starts from the full weight
- **WHEN** an administrator opens the consultation on an order whose shipping method charges by weight and the order has a preorder line
- **THEN** the shipping field starts from the price that method charges for the weight of every product line

#### Scenario: Store collection
- **WHEN** an administrator opens the consultation on a store-collection order
- **THEN** the modal has no shipping field, and submitting it leaves the shipping total at 0

#### Scenario: The submit does not label or export the order
- **WHEN** an administrator submits the consultation
- **THEN** the order's AWB, invoice identifiers, and ERP send meta stay as they were

#### Scenario: Action is hidden when the order is not waiting
- **WHEN** the order is not waiting
- **THEN** the order page does not offer the action to mark the consultation complete

#### Scenario: A mixed order asks to keep or split
- **WHEN** an administrator opens the consultation on a waiting order that has a preorder line and another product line
- **THEN** the page asks whether to keep the order together or split it, and does not show the header split action

#### Scenario: Keeping a mixed order together
- **WHEN** an administrator keeps that mixed order together and submits bank transfer with a shipping price
- **THEN** the order stays one order, the consultation is complete, the shipping total is the submitted price, and the status is `awaiting-bank-transfer`

#### Scenario: Splitting from the consultation finishes the call
- **WHEN** an administrator splits that mixed order from the consultation, with bank transfer on the preorder order and cash on delivery on the other order
- **THEN** the original order status is `split`, the preorder order status is `awaiting-bank-transfer`, the other order status is `payment-offline`, and neither new order is waiting

#### Scenario: A preorder-only order skips the choice
- **WHEN** an administrator opens the consultation on a waiting order whose product lines are all preorder
- **THEN** the payment form opens directly, and the header split action is still offered when the order has more than one unit

#### Scenario: A split parent hides the consultation
- **WHEN** the order status is `split` and a product line is still recorded as a preorder line without a consultation timestamp
- **THEN** the order page does not show the consultation notice or the action to mark the consultation complete

### Requirement: Updating status while waiting asks for confirmation
While an order is waiting, choosing Update status MUST ask the administrator to confirm before the status form opens. The confirmation MUST say that the phone consultation, payment option, and shipping price are not confirmed yet. It MUST offer proceed and cancel. Cancel MUST leave the order unchanged. Proceed MUST open the existing status form. When the order is not waiting, Update status MUST open the status form without that confirmation.

#### Scenario: Confirmation before the status form
- **WHEN** an administrator chooses Update status on a waiting order
- **THEN** the page asks them to confirm before the status form opens, and offers proceed and cancel

#### Scenario: Cancel leaves the order unchanged
- **WHEN** an administrator cancels that confirmation
- **THEN** the order status stays unchanged

#### Scenario: Proceed opens the status form
- **WHEN** an administrator proceeds from that confirmation and submits a new status
- **THEN** the order status changes and the order is still waiting for phone consultation

#### Scenario: No confirmation when the order is not waiting
- **WHEN** an administrator chooses Update status on an order that is not waiting
- **THEN** the status form opens without that confirmation

### Requirement: Waiting orders do not start label, invoice, or ERP export
While an order is waiting, a status change MUST NOT generate an AWB and MUST NOT generate an ERP invoice, and a placement or split export MUST NOT send that order to the ERP. An order that is not waiting MUST keep the current AWB, invoice, and ERP behavior for the same events.

#### Scenario: AWB status while waiting
- **WHEN** a waiting order changes to the status that generates an AWB
- **THEN** no AWB is generated

#### Scenario: Invoice status while waiting
- **WHEN** a waiting order changes to a status that generates an ERP invoice
- **THEN** no invoice is generated

#### Scenario: Placement while waiting
- **WHEN** a waiting order is placed
- **THEN** it is not sent to the ERP

#### Scenario: Offline split child that is waiting
- **WHEN** a split creates an offline-payment order that is waiting
- **THEN** that order is not sent to the ERP

#### Scenario: The same events after consultation
- **WHEN** an order is not waiting and its status changes to the AWB status or an invoice status, or it is placed or split as an offline-payment order
- **THEN** AWB generation, invoice generation, and ERP export follow the current rules for that event
