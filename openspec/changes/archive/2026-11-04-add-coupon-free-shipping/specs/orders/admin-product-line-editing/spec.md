# Spec Delta

## MODIFIED Requirements

### Requirement: Save recalculates prices, discounts, shipping, and tax

A successful product-line change MUST store unit prices, line subtotals, discounts, coupons, loyalty redemption, shipping cost, taxes, and the order total from a fresh calculation for that order's customer, channel, currency, coupon, and current shipping option. The shipping option identifier MUST stay the same. If that option cannot be priced, the system MUST reject the change. When the fresh calculation waives shipping for a free-shipping discount, coupon or automatic, the shipping line MUST store the option's price as its unit price, the waived amount as its discount total, and 0 as its subtotal, tax total, and total.

#### Scenario: Totals follow the new lines

- **WHEN** a product-line change is saved
- **THEN** the stored line subtotals, discount total, shipping total, tax total, and order total equal the freshly calculated amounts

#### Scenario: Coupon no longer qualifies

- **WHEN** the changed lines no longer qualify for the order's coupon
- **THEN** the stored discount no longer includes that coupon and the order total is calculated without it

#### Scenario: Current shipping option cannot be priced

- **WHEN** the order's current shipping option cannot be priced for the changed lines
- **THEN** the system rejects the change and the stored order stays as it was

#### Scenario: Free-shipping coupon still qualifies

- **WHEN** an order placed with a free-shipping coupon has a product quantity changed and the coupon still qualifies
- **THEN** the stored shipping total is 0, and the shipping line keeps the option price as its unit price and the waived amount as its discount total

#### Scenario: Free-shipping coupon no longer qualifies

- **WHEN** an order placed with a free-shipping coupon has a product line changed so that the coupon's minimum cart amount is no longer met, and no automatic free-shipping discount qualifies
- **THEN** the stored shipping total is the option's full price with tax, the shipping line discount total is 0, and the order total includes that shipping

#### Scenario: Eligible product removed

- **WHEN** an order got free shipping from an automatic discount limited to one collection, and the admin removes the only line from that collection
- **THEN** the stored shipping total is the option's full price with tax and the shipping line discount total is 0
