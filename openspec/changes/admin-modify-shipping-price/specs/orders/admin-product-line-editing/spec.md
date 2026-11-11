# Spec Delta

## MODIFIED Requirements

### Requirement: Save recalculates prices, discounts, shipping, and tax

A successful product-line change MUST store unit prices, line subtotals, discounts, coupons, loyalty redemption, shipping cost, taxes, and the order total from a fresh calculation for that order's customer, channel, currency, coupon, and current shipping option. The shipping option identifier MUST stay the same. If that option cannot be priced, the system MUST reject the change, unless the order already has a shipping price set by an administrator. When the order has a shipping price set by an administrator, the fresh calculation MUST use that price, MUST NOT replace it with a carrier price, and MUST NOT waive it for a free-shipping discount. When the fresh calculation waives shipping for a free-shipping discount, coupon or automatic, and the order has no administrator shipping price, the shipping line MUST store the option's price as its unit price, the waived amount as its discount total, and 0 as its subtotal, tax total, and total.

#### Scenario: Totals follow the new lines

- **WHEN** a product-line change is saved
- **THEN** the stored line subtotals, discount total, shipping total, tax total, and order total equal the freshly calculated amounts

#### Scenario: Coupon no longer qualifies

- **WHEN** the changed lines no longer qualify for the order's coupon
- **THEN** the stored discount no longer includes that coupon and the order total is calculated without it

#### Scenario: Current shipping option cannot be priced

- **WHEN** the order has no administrator shipping price and the order's current shipping option cannot be priced for the changed lines
- **THEN** the system rejects the change and the stored order stays as it was

#### Scenario: Administrator shipping price survives an unpriced option

- **WHEN** the order has a shipping price set by an administrator and the current shipping option cannot be priced for the changed lines
- **THEN** the product-line change is saved and the shipping line subtotal remains that price

#### Scenario: Free-shipping coupon still qualifies

- **WHEN** an order placed with a free-shipping coupon has a product quantity changed, the coupon still qualifies, and the order has no administrator shipping price
- **THEN** the stored shipping total is 0, and the shipping line keeps the option price as its unit price and the waived amount as its discount total

#### Scenario: Higher quantity becomes eligible for free shipping

- **WHEN** an order has no administrator shipping price, its shipping is still charged, and a higher product quantity makes a free-shipping coupon or automatic discount qualify
- **THEN** the stored shipping subtotal and shipping total are 0

#### Scenario: Added product becomes eligible for free shipping

- **WHEN** an order has no administrator shipping price, its shipping is still charged, and adding a product makes a free-shipping coupon or automatic discount qualify
- **THEN** the stored shipping subtotal and shipping total are 0

#### Scenario: Free-shipping coupon no longer qualifies

- **WHEN** an order placed with a free-shipping coupon has a product line changed so that the coupon's minimum cart amount is no longer met, no automatic free-shipping discount qualifies, and the order has no administrator shipping price
- **THEN** the stored shipping total is the option's full price with tax, the shipping line discount total is 0, and the order total includes that shipping

#### Scenario: Eligible product removed

- **WHEN** an order got free shipping from an automatic discount limited to one collection, the admin removes the only line from that collection, and the order has no administrator shipping price
- **THEN** the stored shipping total is the option's full price with tax and the shipping line discount total is 0

#### Scenario: Administrator shipping price is not waived

- **WHEN** an order has a shipping price set by an administrator, a free-shipping coupon still qualifies, and an administrator changes a product quantity
- **THEN** the shipping line subtotal remains the administrator price and the coupon's product discount still applies
