# Spec Delta

## Purpose

Lets an administrator mark a variant as preorder-only so it stays buyable without stock or backorder quantities, and so other systems can tell that mode apart from unlimited and stock-limited sale.

## ADDED Requirements

### Requirement: Administrator can select Preorder purchasability
The inventory Purchasability field SHALL offer Preorder, stored as `preorder`. The English label SHALL be Preorder. The Hungarian label SHALL be Előrendelhető.

#### Scenario: Save on a single-variant product
- **WHEN** an administrator sets Purchasability to Preorder on the product Inventory page and saves
- **THEN** that product's variant stores purchasable `preorder`

#### Scenario: Save on a variant
- **WHEN** an administrator sets Purchasability to Preorder on a variant Inventory page and saves
- **THEN** that variant stores purchasable `preorder`

#### Scenario: Change away from preorder
- **WHEN** an administrator changes Purchasability from Preorder to another existing option and saves
- **THEN** the variant stores that other option and is no longer `preorder`

### Requirement: Preorder keeps stock inputs and explains they are unused
When Purchasability is Preorder, the stock and backorder fields SHALL stay visible and each SHALL show helper text that the value is not used. Other purchasability options SHALL NOT show that helper.

#### Scenario: Helper appears for Preorder
- **WHEN** Purchasability is Preorder
- **THEN** the stock and backorder inputs are visible
- **AND** each shows helper text that the value is not used

#### Scenario: Helper is absent for other modes
- **WHEN** Purchasability is In Stock
- **THEN** the stock and backorder inputs are visible
- **AND** that helper text is not shown

### Requirement: Preorder variants are fulfillable without stock
A variant with purchasable `preorder` SHALL be fulfillable at any positive quantity, including when stock and backorder are zero and stock checking is enabled.

#### Scenario: Zero stock and zero backorder
- **WHEN** stock checking is enabled and a cart requests a positive quantity of a `preorder` variant with stock 0 and backorder 0
- **THEN** the stock check allows the quantity

#### Scenario: Quantity above recorded stock
- **WHEN** a `preorder` variant has stock 2 and the requested quantity is 10
- **THEN** the variant can be fulfilled at quantity 10

### Requirement: Preorder variants do not move stock
Increasing or decreasing stock for a `preorder` variant SHALL leave its stock and backorder quantities unchanged.

#### Scenario: Decrease leaves quantities unchanged
- **WHEN** stock is decreased for a `preorder` variant
- **THEN** its stock and backorder stay at their previous values

#### Scenario: Increase leaves quantities unchanged
- **WHEN** stock is increased for a `preorder` variant
- **THEN** its stock and backorder stay at their previous values

### Requirement: Existing purchasability modes stay the same
A variant that is not `preorder` SHALL keep its current fulfillment and stock-movement behavior.

#### Scenario: In stock rejects an empty variant
- **WHEN** stock checking is enabled for an `in_stock` variant with stock 0
- **THEN** a positive quantity is rejected

#### Scenario: In stock or on backorder uses both quantities
- **WHEN** a variant is `in_stock_or_on_backorder` with stock 1 and backorder 2
- **THEN** it can be fulfilled at quantity 3 and cannot be fulfilled at quantity 4

#### Scenario: Always still moves stock
- **WHEN** stock is decreased for an `always` variant that has stock
- **THEN** its stock quantity decreases

### Requirement: Product feed reports preorder availability
The product feed SHALL report availability `preorder` for a `preorder` variant. Other variants SHALL stay `in_stock` or `out_of_stock` from their current fulfillment result.

#### Scenario: Preorder variant in the feed
- **WHEN** the feed includes a `preorder` variant
- **THEN** its availability is `preorder`

#### Scenario: Fulfillable in-stock variant
- **WHEN** the feed includes an `in_stock` variant with stock of at least 1
- **THEN** its availability is `in_stock`
