# Spec Delta

## Purpose

Records the shop's purchase funnel events (adds to cart, started carts and checkout starts) in the shop database, so the admin can report how far shoppers get before they buy. It defines which storefront actions are recorded, what an event stores, and how shops switch recording on or off.

## ADDED Requirements

### Requirement: Each reported add to cart is recorded
Each add to cart that the storefront reports for a cart SHALL record one add-to-cart event for that cart, whatever the quantity added. An add that fails, for example on a stock or quantity check, SHALL NOT be reported.

#### Scenario: Adding three units of a product
- **WHEN** a shopper adds 3 units of a product to their cart from the product page
- **THEN** one add-to-cart event is recorded for that cart

#### Scenario: Adding a second product
- **WHEN** the same shopper then adds another product from a product card
- **THEN** a second add-to-cart event is recorded for that cart

#### Scenario: Add refused by a stock check
- **WHEN** a shopper tries to add more units than are in stock and the add is refused
- **THEN** no add-to-cart event is recorded

### Requirement: A cart starts with its first product
The first add to cart recorded for a cart SHALL also record that the cart started. Later adds to the same cart SHALL NOT record another start. A cart that never gets a reported add to cart SHALL NOT start, even when it exists in the database.

#### Scenario: First product in a new cart
- **WHEN** a new visitor adds their first product to a new cart
- **THEN** one cart start and one add-to-cart event are recorded for that cart

#### Scenario: Further products
- **WHEN** the visitor adds two more products to the same cart
- **THEN** two more add-to-cart events are recorded and no other cart start

#### Scenario: Empty cart after a completed order
- **WHEN** the storefront creates a new, empty cart for a customer whose previous cart has a completed order, and the customer adds nothing to it
- **THEN** no cart start is recorded for the empty cart

### Requirement: A checkout start is recorded once per cart
The first checkout start that the storefront reports for a cart SHALL record one checkout-start event for that cart. Later checkout starts for the same cart SHALL NOT record another event.

#### Scenario: Opening checkout
- **WHEN** a shopper with a cart opens the checkout page
- **THEN** one checkout-start event is recorded for that cart

#### Scenario: Returning to checkout
- **WHEN** the same shopper reloads the checkout page, goes back to the cart and opens checkout again
- **THEN** no further checkout-start event is recorded for that cart

#### Scenario: Checkout with a new cart after an order
- **WHEN** that shopper places the order, then fills a new cart and opens checkout
- **THEN** one checkout-start event is recorded for the new cart

### Requirement: Only actions the storefront reports are recorded
Conversion events SHALL be recorded only for adds to cart and checkout starts that the storefront reports. Other changes to cart lines, such as quantity changes on the cart page, merging a guest cart on login, or carts built by an order split, SHALL NOT record events.

#### Scenario: Guest cart merged on login
- **WHEN** a guest with a cart logs in and their guest cart is merged into their customer cart
- **THEN** no add-to-cart event and no cart start are recorded for the merge

#### Scenario: Quantity raised on the cart page
- **WHEN** a shopper raises a line's quantity on the cart page
- **THEN** no add-to-cart event is recorded

### Requirement: The admin panel and staff sessions are not recorded
No conversion event SHALL be recorded while the admin panel serves the request, or while a staff member is logged in to the admin panel in the session.

#### Scenario: Staff member shopping on the storefront
- **WHEN** a staff member logged in to the admin panel adds a product to a cart and opens checkout on the storefront in the same browser
- **THEN** no conversion event is recorded

#### Scenario: Carts built in the admin panel
- **WHEN** a staff member splits an order in the admin panel
- **THEN** no conversion event is recorded for the carts the split creates

### Requirement: A failed recording does not interrupt the shopper
When a conversion event cannot be stored, the shopper's add to cart or checkout SHALL go on unchanged and the failure SHALL be reported to the error log.

#### Scenario: Database error while recording
- **WHEN** storing an add-to-cart event fails with a database error
- **THEN** the product is in the cart, the shopper sees the usual confirmation, and the error is reported

### Requirement: Conversion events hold no personal data
Each conversion event SHALL store only its type, the cart id and its creation time. It SHALL NOT store the user, customer, email address, IP address, user agent, session id, products, quantities or prices.

#### Scenario: Logged-in customer adds to cart
- **WHEN** a logged-in customer adds a product to their cart
- **THEN** the event stores the type, the cart id and the time, and nothing else

#### Scenario: Upgrading a shop
- **WHEN** a shop runs its migrations after installing the release
- **THEN** conversion events can be stored, and existing carts and orders are unchanged

### Requirement: Recording does not depend on consent or new cookies
Conversion events SHALL be recorded whether or not the shopper accepted analytics or marketing cookies. Recording SHALL NOT set any cookie.

#### Scenario: Shopper declined analytics cookies
- **WHEN** a shopper whose consent cookie denies analytics adds a product to their cart
- **THEN** an add-to-cart event is recorded and the response sets no new cookie for tracking

### Requirement: Shops switch conversion recording on
Conversion recording SHALL be off by default and SHALL be switched on and off per shop with `ANALYTICS_CONVERSIONS_ENABLED`, independently of traffic recording. While it is off, no conversion event is recorded. The other requirements of this capability apply while it is on.

#### Scenario: Recording not switched on
- **WHEN** a shop has not set `ANALYTICS_CONVERSIONS_ENABLED` and a shopper adds a product and opens checkout
- **THEN** no conversion event is recorded

#### Scenario: Recording on without traffic recording
- **WHEN** a shop sets `ANALYTICS_CONVERSIONS_ENABLED=true` and leaves `ANALYTICS_TRAFFIC_ENABLED` unset, and a shopper adds a product to a new cart
- **THEN** a cart start and an add-to-cart event are recorded, and no visit is recorded

#### Scenario: Recording switched off again
- **WHEN** a shop that recorded conversion events sets `ANALYTICS_CONVERSIONS_ENABLED=false`
- **THEN** no further events are recorded and the conversions report keeps showing the stored events
