# Spec Delta

## Purpose

Defines how a cart carries the traffic source of the visit that created it, and how orders, including split orders, keep that source, so carts and orders can be reported per source.

## ADDED Requirements

### Requirement: A cart carries the source of the visit that created it
A cart created during a session with a current visit SHALL store that visit's id, source, medium and campaign, but not its landing path or referrer host. Later visits SHALL NOT change it, including visits in later sessions of the same customer. A cart created without a current visit SHALL have no source. A cart's source SHALL become the session's current visit only when the session has none and the cart prevents a new visit, as `analytics/traffic-recording` defines.

#### Scenario: Add to cart after landing from Facebook
- **WHEN** a visitor lands from `https://l.facebook.com/` and adds a product to a new cart
- **THEN** the cart's traffic source is that visit's id with source `facebook` and medium `social`

#### Scenario: Returning customer with a saved cart
- **WHEN** the customer comes back logged in through `https://www.google.com/` after their session expired, and the same cart is restored
- **THEN** no visit is recorded and the cart's traffic source still shows the Facebook visit

#### Scenario: Cart created outside a visit
- **WHEN** a cart is created in a console command or by a request that recorded no visit
- **THEN** the cart has no traffic source

### Requirement: Carts created in the admin panel have no source
A cart created while the admin panel serves the request SHALL have no traffic source, even when the session holds a storefront visit.

#### Scenario: Splitting an order in the admin
- **WHEN** a staff member whose session holds a storefront visit splits an order in the admin panel
- **THEN** the carts created for the split have no traffic source

### Requirement: An order keeps its cart's traffic source
Creating an order from a cart SHALL copy the cart's traffic source to the order unchanged. This SHALL also hold when a draft order is rebuilt from its cart. Order creation SHALL NOT store anything about the visit in which the order is placed.

#### Scenario: Cart from Facebook, order placed after a Google visit
- **WHEN** a customer's cart was created in a Facebook visit and the order is placed in a session whose current visit is `google` / `organic`
- **THEN** the order's traffic source is the Facebook visit and the order stores nothing about the Google visit

#### Scenario: Draft order rebuilt
- **WHEN** a draft order created from that cart is rebuilt from it
- **THEN** the order's traffic source is still the Facebook visit

#### Scenario: Cart without a source
- **WHEN** an order is created from a cart without a traffic source
- **THEN** the order has no traffic source

### Requirement: Split orders keep the traffic source
When an order is split, each new order SHALL get the original order's traffic source. An original order without a traffic source SHALL give new orders without one.

#### Scenario: Splitting a Facebook order
- **WHEN** an order whose traffic source is a Facebook visit is split into two orders
- **THEN** both new orders have that Facebook traffic source

#### Scenario: Splitting an order without a source
- **WHEN** an order without a traffic source is split
- **THEN** none of the new orders has a traffic source
