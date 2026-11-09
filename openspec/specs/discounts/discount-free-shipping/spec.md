# discount-free-shipping Specification

## Purpose

Lets an administrator mark a discount so that, when it qualifies for a cart, shipping is free. The discount may be a coupon or an automatic discount, and may also take an amount off products. This spec defines when the cart charges no shipping, which cart pipeline stage waives it, and how the waived shipping is stored on the order.

## Requirements

### Requirement: Admin can mark a discount as free shipping

The Advanced amount off discount form SHALL offer a Free shipping toggle. Its value SHALL be saved as `data.free_shipping` and shown again when the discount is reopened. The toggle SHALL be savable with or without a coupon code. Its helper text SHALL explain both uses: with a coupon code it works when the coupon is used, without a coupon code when any cart line is eligible. The label and helper text SHALL be translated in every admin locale.

#### Scenario: Toggle is saved with a coupon

- **WHEN** an admin turns Free shipping on for an Advanced amount off discount with coupon `SHIPFREE` and saves
- **THEN** the discount is stored with `data.free_shipping` true, and the toggle is on when the discount is opened again

#### Scenario: Toggle is saved without a coupon

- **WHEN** an admin turns Free shipping on for an Advanced amount off discount with no coupon code and an empty percentage, and saves
- **THEN** the discount is stored with `data.free_shipping` true and no validation error is shown

#### Scenario: Other discount types have no toggle

- **WHEN** an admin edits a Buy X Get Y discount
- **THEN** the form does not show the Free shipping toggle

### Requirement: A qualifying coupon grants free shipping

A cart SHALL get free shipping when its coupon code matches an Advanced amount off discount that has `data.free_shipping` true, that discount passes its coupon conditions (customer list, minimum cart amount, max uses, max uses per user), and at least one cart line is eligible under its product, variant, collection, and brand limitations and exclusions.

#### Scenario: Qualifying coupon

- **WHEN** the cart has coupon `SHIPFREE`, the discount has free shipping on, and the cart is above its minimum cart amount
- **THEN** the cart calculates with free shipping

#### Scenario: Below the minimum cart amount

- **WHEN** the cart has coupon `SHIPFREE` and its eligible lines are below the discount's minimum cart amount
- **THEN** the cart charges shipping, as it does today for a coupon below its minimum

#### Scenario: No eligible line

- **WHEN** the free-shipping coupon is limited to a collection and no cart line belongs to that collection
- **THEN** the cart charges shipping

#### Scenario: Coupon removed

- **WHEN** a cart that had free shipping from its coupon is recalculated after the coupon code is cleared
- **THEN** the cart charges shipping again, unless an automatic free-shipping discount qualifies

### Requirement: A qualifying automatic discount grants free shipping

A cart SHALL get free shipping when at least one active Advanced amount off discount without a coupon has `data.free_shipping` true, is in scope for the cart's channel and customer groups, passes its conditions (customer list, minimum cart amount on its eligible lines, max uses, max uses per user), and has at least one cart line eligible under its limitations and exclusions. This SHALL NOT depend on which discount sets the price of any line.

#### Scenario: One eligible product out of five

- **WHEN** a cart has five products and only one of them belongs to the collection of an automatic free-shipping discount
- **THEN** the cart calculates with free shipping

#### Scenario: Another discount sets the line price

- **WHEN** the only eligible line of an automatic 5% free-shipping discount is priced by a variant-level 20% discount
- **THEN** that line gets the 20% discount only, and the cart still calculates with free shipping

#### Scenario: Global automatic discount

- **WHEN** an active automatic free-shipping discount has no limitations
- **THEN** every cart in its channel and customer groups calculates with free shipping

#### Scenario: Not in scope

- **WHEN** the automatic free-shipping discount is not active, is outside the cart's channel or customer groups, or its eligible lines are below its minimum cart amount
- **THEN** that discount does not grant free shipping

#### Scenario: Per-user limit for a guest

- **WHEN** the automatic free-shipping discount has a per-user limit and the cart has no user
- **THEN** that discount does not grant free shipping

### Requirement: One discount is recorded as the free-shipping discount

When several discounts would grant free shipping, the cart SHALL record exactly one of them as its free-shipping discount. The cart's coupon discount SHALL be chosen when it qualifies. Otherwise the qualifying automatic discount with the highest priority SHALL be chosen, and the lowest id SHALL break a tie.

#### Scenario: Coupon and automatic discount both qualify

- **WHEN** coupon `SHIPFREE` and an automatic free-shipping discount both qualify
- **THEN** `SHIPFREE` is the cart's free-shipping discount

#### Scenario: Two automatic discounts qualify

- **WHEN** automatic free-shipping discounts with priority 5 and priority 2 both qualify
- **THEN** the priority 5 discount is the cart's free-shipping discount

### Requirement: Free shipping waives every shipping option

When a cart has free shipping, the free-shipping pipeline stage SHALL make the calculation treat the selected shipping option as costing 0. This covers courier, locker, and personal pickup options. The shipping subtotal, shipping tax, shipping total, and the shipping part of the cart total SHALL all be 0. The product lines, product discounts, and product tax SHALL be calculated as they would be without free shipping.

#### Scenario: Courier option

- **WHEN** a cart with a free-shipping discount has a courier option priced 19.90 selected
- **THEN** the cart shipping subtotal, shipping tax, and shipping total are 0, and the cart total equals the products total after discounts and tax

#### Scenario: Option changed in the same request

- **WHEN** the shipping option of a cart with free shipping is changed and the cart is recalculated
- **THEN** the new option is also charged at 0

#### Scenario: No option selected yet

- **WHEN** a cart with a free-shipping discount has no shipping option
- **THEN** the cart still reports its free-shipping discount, and its shipping totals are empty, as they are today without an option

### Requirement: A dedicated cart pipeline stage waives shipping

Shipping SHALL be waived by its own cart pipeline stage, `ApplyDiscountFreeShipping`, and SHALL NOT be waived by the tax stage. The default cart pipeline SHALL list this stage directly before the tax stage. A cart pipeline that lists the stage directly before the tax stage SHALL waive shipping whether it runs the discount stage before or after the shipping stage, and whether or not it has a shipping subtotal stage.

#### Scenario: Default cart pipeline

- **WHEN** a cart with coupon `SHIPFREE` and a courier option priced 19.90 is calculated with the default cart pipeline
- **THEN** the free-shipping stage runs directly before the tax stage, and the cart is charged 0 shipping

#### Scenario: Discounts before shipping

- **WHEN** a host's cart pipeline runs the discount stage before the shipping stage, with or without a shipping subtotal stage, and lists the free-shipping stage directly before the tax stage
- **THEN** a cart with a free-shipping discount and a courier option priced 19.90 is charged 0 shipping and reports 19.90 as the waived amount

#### Scenario: Pipeline without the free-shipping stage

- **WHEN** a cart pipeline does not list the free-shipping stage
- **THEN** the cart still reports its free-shipping discount, the selected option is charged at its full price with tax, and no waived amount is reported

### Requirement: The cart reports the free-shipping discount and the waived amount

After calculation, a cart SHALL expose the discount recorded as its free-shipping discount (null when none qualified) and the shipping amount waived (null when nothing was waived). The waived amount SHALL equal the selected option's price before the waiver, in the stored price basis. Both values SHALL be available again from the same request's cart cache, like the other calculated cart totals.

#### Scenario: Waived amount

- **WHEN** a cart with coupon `SHIPFREE` has an option priced 19.90 selected and is calculated
- **THEN** the cart reports the `SHIPFREE` discount as the free-shipping discount, and 19.90 as the waived shipping amount

#### Scenario: Already free option

- **WHEN** a cart with a free-shipping discount has a personal pickup option priced 0 selected
- **THEN** the cart reports that discount as the free-shipping discount, and no waived amount

#### Scenario: Cart without free shipping

- **WHEN** a cart that no free-shipping discount qualifies for is calculated
- **THEN** the free-shipping discount and the waived amount are both null

### Requirement: A free-shipping discount may take nothing off products

A free-shipping discount SHALL grant free shipping even when it takes nothing off products. When the recorded free-shipping discount has no breakdown entry of its own, it SHALL be listed among the cart's applied discounts with one breakdown entry of amount 0 and no lines. A discount that also takes an amount off SHALL keep its usual entry and SHALL NOT get a second one. The coupon total SHALL not change.

#### Scenario: Shipping-only coupon

- **WHEN** the cart uses coupon `SHIPFREE` whose percentage is empty and whose fixed value is off
- **THEN** the cart has free shipping, the discount breakdown has one `SHIPFREE` entry with amount 0, and the coupon total is 0

#### Scenario: Shipping-only automatic discount

- **WHEN** an automatic discount with free shipping and an empty percentage qualifies for the cart
- **THEN** the cart has free shipping and the discount breakdown has one entry for that discount with amount 0

#### Scenario: Percentage coupon with free shipping

- **WHEN** the cart uses coupon `SHIP10` with 10% off and free shipping
- **THEN** eligible lines get 10% off as they do today, the cart has free shipping, and the breakdown has one `SHIP10` entry with the 10% amount

#### Scenario: Fixed-value coupon with free shipping

- **WHEN** the cart uses a fixed-value coupon with free shipping and the eligible lines are above its fixed amount
- **THEN** the fixed amount is spread across eligible lines as it is today, and the cart has free shipping

### Requirement: Shipping-only automatic discounts stay out of product pricing

An automatic discount that takes nothing off products SHALL NOT take part in choosing a line's price discount, in the cart or in the catalog. It SHALL NOT replace or block a discount from a lower priority tier. Product pages, product cards, discount labels, and indexed catalog prices SHALL treat it as no discount.

#### Scenario: Lower tier discount still applies

- **WHEN** a variant has a shipping-only automatic discount limited to that variant and a 10% discount on its collection
- **THEN** the cart line and the product page both show the 10% discount

#### Scenario: Catalog without any other discount

- **WHEN** a product's only discount is a shipping-only automatic discount
- **THEN** the product has no discount, no discount label, and its discounted price equals its original price

### Requirement: Explicit shipping overrides keep their price

When a cart's shipping option is set as an explicit override, such as the price staff choose for an order split, the free-shipping waiver SHALL NOT change that price. The discount's product amount SHALL still apply.

#### Scenario: Split target with the coupon

- **WHEN** a split target gets the free-shipping coupon and staff set its shipping to 12.00
- **THEN** that new order is charged 12.00 shipping and keeps the coupon's product discount

### Requirement: Placed orders store the waived shipping

When an order is created from a cart with free shipping, the shipping line SHALL keep the option's price as its unit price. It SHALL store the waived amount as its discount total, and 0 as its subtotal, tax total, and total. The order shipping total SHALL be 0. The order SHALL expose the sum of its shipping lines' discount totals as its shipping discount total.

#### Scenario: Order from a free-shipping cart

- **WHEN** an order is created from a cart with a free-shipping discount and a courier option priced 19.90
- **THEN** the shipping line has unit price 19.90, discount total 19.90, and subtotal, tax total, and total 0, the order shipping total is 0, and the order shipping discount total is 19.90

#### Scenario: Order without free shipping

- **WHEN** an order is created from a cart without a free-shipping discount
- **THEN** the shipping line discount total is 0 and the order shipping discount total is 0

### Requirement: Orders record the free-shipping discount as used

An order placed with free shipping SHALL list its free-shipping discount in its discount breakdown, including when that discount took nothing off products. A free-shipping coupon SHALL be the order's applied coupon. The discount SHALL be marked as used, and recorded against the customer, when the host marks the order's discounts as used.

#### Scenario: Applied coupon on the order

- **WHEN** an order is created from a cart with the shipping-only coupon `SHIPFREE`
- **THEN** the order's applied coupon is `SHIPFREE` and its coupon total is 0

#### Scenario: Shipping-only coupon is counted as used

- **WHEN** an order paid with the shipping-only coupon `SHIPFREE` has its discounts marked as used
- **THEN** `SHIPFREE` uses increase by 1 and the customer is recorded against it

#### Scenario: Shipping-only automatic discount is counted as used

- **WHEN** an order whose free shipping came from a shipping-only automatic discount with `max_uses` 100 has its discounts marked as used
- **THEN** that discount's uses increase by 1 and the order has no applied coupon
