# Spec Delta

## Purpose

Prices automatic shipping from the products that are not preorder, and keeps a preorder-only shipment available at no automatic charge until the amount is agreed by phone.

## ADDED Requirements

### Requirement: Automatic shipping ignores preorder lines
A shipping price that depends on the cart total, the cart weight, or a minimum spend MUST ignore product lines whose variant purchasability is `preorder`. Lines that are not preorder MUST keep the amounts and weights used today.

#### Scenario: Mixed cart priced by cart total
- **WHEN** a cart has one preorder line and one line that is not preorder, and the shipping method charges by cart total
- **THEN** the price tier uses only the non-preorder line

#### Scenario: Mixed cart priced by weight
- **WHEN** a cart has one preorder line and one line that is not preorder, and the shipping method charges by weight
- **THEN** the weight tier uses only the non-preorder line

#### Scenario: Free-shipping minimum ignores preorder spend
- **WHEN** a free-shipping method has a minimum spend and the only spend above that minimum comes from a preorder line
- **THEN** the method is not offered

#### Scenario: Cart without preorder lines
- **WHEN** every product line is not preorder
- **THEN** the shipping price is the same price that method produces today

### Requirement: A preorder product does not exclude shipping
A preorder product on a shipping exclusion list MUST NOT remove a shipping option when the cart also contains a product line that is not preorder and is not excluded. A non-preorder product that is excluded MUST still remove the option.

#### Scenario: Only the preorder product is excluded
- **WHEN** the preorder product is on the exclusion list and another product line is not excluded
- **THEN** the shipping option is still offered

#### Scenario: A normal product is excluded
- **WHEN** a product line that is not preorder is on the exclusion list
- **THEN** the shipping option is not offered

### Requirement: A preorder-only shipment has no automatic charge
When every product line is preorder, each shipping method that would be offered for those products MUST still be offered, with price 0, and MUST record that the shipping amount is still to be agreed. That result MUST be used when the option is priced again for the same lines, including an order recalculation.

#### Scenario: Preorder-only cart
- **WHEN** every product line is preorder and the method is available for that cart apart from the empty non-preorder total
- **THEN** the option is offered at price 0 and records that the shipping amount is still to be agreed

#### Scenario: Recalculating a preorder-only order
- **WHEN** an order whose product lines are all preorder has its current shipping option priced again
- **THEN** that option is priced at 0 and the recalculation is not rejected for a missing price

### Requirement: The order stores the pending shipping marker
Creating an order from a shipping option that records a shipping amount still to be agreed MUST store that marker on the order's shipping line.

#### Scenario: Order created from a preorder-only cart
- **WHEN** an order is created from a cart whose selected shipping option records that the shipping amount is still to be agreed
- **THEN** the order shipping line stores that marker and the shipping total is 0

### Requirement: Collection is not a pending shipping charge
Store collection MUST stay price 0 and MUST NOT record that a shipping amount is still to be agreed, including when the cart contains a preorder line.

#### Scenario: Collection with a preorder line
- **WHEN** the customer selects store collection and the cart contains a preorder line
- **THEN** the option price is 0 and it does not record a shipping amount still to be agreed
