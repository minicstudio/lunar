# Spec Delta

## Purpose

Defines how a cart carries the traffic source of the visit that created it, how orders, including split orders, keep that source, and how authorized staff see it on orders, so carts and orders can be reported per source.

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

### Requirement: A cart created on a prefetched landing page carries its visit
When the session holds a pending visit from a prefetched landing page, creating a cart SHALL first record that visit, as `analytics/traffic-recording` defines, and the cart SHALL carry it.

#### Scenario: Add to cart on a prefetched Google result
- **WHEN** a new visitor opens a product page that Chrome prefetched from `https://www.google.com/` and adds the product to a new cart before opening another page
- **THEN** a `google` / `organic` visit is recorded and the cart's traffic source is that visit

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

### Requirement: Authorized staff see an order's traffic source
When the shop's admin panel uses the analytics plugin, admins and staff holding the traffic report permission SHALL see each order's traffic source as a badge in the order list, and in its own section on the order page, as "The order page shows the traffic source in its own section" defines. Other staff SHALL NOT see the traffic source. The order page SHALL NOT list the raw traffic source under additional information for any staff member.

#### Scenario: Staff member with the permission
- **WHEN** a staff member holding the traffic report permission opens the order list and an order with a traffic source
- **THEN** the list shows the order's badge, the order page shows its traffic source section, and additional information does not list the traffic source

#### Scenario: Staff member without the permission
- **WHEN** a staff member who manages orders, is not an admin and lacks the traffic report permission opens the order list and an order with a traffic source
- **THEN** neither page shows the traffic source, as a badge, as a section or under additional information

#### Scenario: Panel without the analytics plugin
- **WHEN** the shop's admin panel does not use the analytics plugin
- **THEN** the order list and the order page are unchanged

### Requirement: The order page shows the traffic source in its own section
For staff who may see it, the order page SHALL show the traffic source in its own section, directly above additional information, and not in the order summary. The section SHALL show the badge and each stored detail that has a value: the campaign, and the referrer host, the landing page and the start time of the order's visit. The landing page SHALL link to that storefront page.

#### Scenario: Newsletter order
- **WHEN** a staff member holding the traffic report permission opens an order whose traffic source is `mailchimp` / `email` with campaign `Black-Friday`, from a visit that landed on `/sale`
- **THEN** the traffic source section above additional information shows the badge "Mailchimp · Email", the campaign `Black-Friday`, the landing page `/sale` linked to the storefront page and when the visit started, and the order summary shows no traffic source

#### Scenario: Order from a price comparison site
- **WHEN** that staff member opens an order whose visit came from `https://www.arukereso.hu/mobiltelefon-c3277/`
- **THEN** the section shows the badge "arukereso.hu · Referral" and the referrer `arukereso.hu`, and no campaign

#### Scenario: Order without a source on the order page
- **WHEN** that staff member opens an order without a traffic source
- **THEN** the section shows only the badge "Unknown"

### Requirement: The order badge names the source in words
The order badge SHALL name the source and the medium in words, such as "Google · Organic search", "Google · Paid ad", "Facebook · Social", "arukereso.hu · Referral" or "Direct". An order without a traffic source SHALL show "Unknown".

#### Scenario: Order from a Google ad
- **WHEN** an order's traffic source is `google` / `cpc`
- **THEN** its badge in the order list and on the order page reads "Google · Paid ad"

#### Scenario: Direct visit
- **WHEN** an order's traffic source is `direct` / `none`
- **THEN** its badge reads "Direct"

#### Scenario: Order without a source
- **WHEN** an order was placed before the release, or from a cart without a traffic source
- **THEN** its badge reads "Unknown"
