# Spec Delta

## RENAMED Requirements

- FROM: `### Requirement: A cart carries the source of the visit that created it`
- TO: `### Requirement: A cart carries the source of the visit in which it got its first product`

## MODIFIED Requirements

### Requirement: A cart carries the source of the visit in which it got its first product
When a product line is added to a cart without a traffic source during a session with a current visit, the cart SHALL store that visit's id, source, medium and campaign, but not its landing path or referrer host. A cart without product lines SHALL have no source. Later visits SHALL NOT change a cart's source, including visits in later sessions of the same customer. A cart whose products were all added without a current visit SHALL have no source. A cart's source SHALL become the session's current visit only when the session has none and the cart prevents a new visit, as `analytics/traffic-recording` defines.

#### Scenario: Add to cart after landing from Facebook
- **WHEN** a visitor lands from `https://l.facebook.com/` and adds a product to a new cart
- **THEN** the cart's traffic source is that visit's id with source `facebook` and medium `social`

#### Scenario: Empty cart after an order
- **WHEN** a customer places an order and the storefront then creates a new, empty cart for the session
- **THEN** the new cart has no traffic source, so the traffic report does not count it

#### Scenario: Empty cart filled in a later visit
- **WHEN** that customer returns logged in through `https://www.google.com/` in a new session and adds a product to that cart
- **THEN** the cart's traffic source is the Google visit

#### Scenario: Returning customer with a saved cart
- **WHEN** the customer comes back logged in through `https://www.google.com/` after their session expired, and the same cart is restored
- **THEN** no visit is recorded and the cart's traffic source still shows the Facebook visit

#### Scenario: Guest cart merged at login
- **WHEN** a visitor who landed from `https://l.facebook.com/` fills a guest cart and logs in, and the guest cart's products are merged into the customer's empty cart
- **THEN** the customer's cart gets the Facebook visit as its traffic source

#### Scenario: Cart created outside a visit
- **WHEN** a cart is created and gets its products in a console command or by a request that recorded no visit
- **THEN** the cart has no traffic source

### Requirement: A cart created on a prefetched landing page carries its visit
When the session holds a pending visit from a prefetched landing page, adding a product to a cart without a traffic source SHALL first record that visit, as `analytics/traffic-recording` defines, and the cart SHALL carry it. Creating an empty cart SHALL NOT record the pending visit.

#### Scenario: Add to cart on a prefetched Google result
- **WHEN** a new visitor opens a product page that Chrome prefetched from `https://www.google.com/` and adds the product to a new cart before opening another page
- **THEN** a `google` / `organic` visit is recorded and the cart's traffic source is that visit

### Requirement: Carts created in the admin panel have no source
A cart SHALL NOT get a traffic source from products added while the admin panel serves the request, even when the session holds a storefront visit.

#### Scenario: Splitting an order in the admin
- **WHEN** a staff member whose session holds a storefront visit splits an order in the admin panel
- **THEN** the carts created for the split have no traffic source
