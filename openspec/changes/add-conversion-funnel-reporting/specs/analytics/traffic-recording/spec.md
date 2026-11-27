# Spec Delta

## ADDED Requirements

### Requirement: A visit counts once a browser confirms it
The first page view of a session SHALL store its visit unconfirmed. The visit SHALL be confirmed when the session shows a browser holds it: at the session's next storefront page view, when a product is added to a cart, or when the page's presence script reports that it ran. A visit started by a session that already holds a visit or a pending visit SHALL be stored confirmed.

#### Scenario: Visitor who browses
- **WHEN** a new visitor lands on a product page from `https://www.google.com/` and opens a category page
- **THEN** one `google` / `organic` visit is stored and confirmed

#### Scenario: Visitor who leaves after one page
- **WHEN** a new visitor lands on a product page from `https://l.facebook.com/`, the page's presence script runs, and the visitor leaves
- **THEN** one `facebook` / `social` visit is stored and confirmed

#### Scenario: Crawler that keeps no cookies and runs no scripts
- **WHEN** a client with a desktop Chrome user agent opens three product pages without a referrer, sending no cookies and running no scripts
- **THEN** three unconfirmed `direct` visits are stored

#### Scenario: Add to cart on the landing page
- **WHEN** a new visitor whose page ran no presence script adds a product to a cart on the landing page
- **THEN** the visit is confirmed and the cart carries it

#### Scenario: Prefetched landing page
- **WHEN** Chrome prefetches a product page from Google, the visitor opens it and its presence script runs
- **THEN** no visit is stored until the session's next request, as "A prefetched landing page starts its visit when it is opened" defines, and that visit is stored confirmed

### Requirement: The presence script asks only new sessions
A storefront page SHALL carry the presence script only while its session has no visit, recording is on, and no staff member is logged in. The presence call SHALL confirm the session's current visit without saving the session or setting a cookie, so it cannot overwrite what the page's other requests write to the session.

#### Scenario: Second page of a session
- **WHEN** a visitor whose session holds a visit opens another page
- **THEN** the page carries no presence script

#### Scenario: Presence call next to the page's other requests
- **WHEN** the presence call confirms a visit while a lazy component of the same page loads
- **THEN** the visit is confirmed, and the presence call leaves the session as the component saved it

### Requirement: Unconfirmed visits do not count and are deleted after 30 days
Reports SHALL count only confirmed visits. A visit no browser confirmed SHALL be deleted 30 days after it was stored, when the shop prunes models daily. Confirmed visits SHALL be kept.

#### Scenario: Traffic report with unconfirmed visits
- **WHEN** the period holds 3 confirmed `google` / `organic` visits and 5 unconfirmed `direct` visits
- **THEN** the traffic report shows 3 visits and only the `google` / `organic` source

#### Scenario: Daily prune
- **WHEN** the prune runs and the table holds an unconfirmed visit from 31 days ago, one from 29 days ago and a confirmed visit from 90 days ago
- **THEN** only the unconfirmed visit from 31 days ago is deleted

## MODIFIED Requirements

### Requirement: A prefetched landing page starts its visit when it is opened
A prefetch that would start a visit in a session without one SHALL keep that visit pending in the session, with the prefetched page as landing path. While the session has no visit, its next request that is not a prefetch SHALL record the pending visit when it is a page view without a source of its own (the shop's own referrer and no campaign parameters), or when it adds a product to a cart without a traffic source. A prefetch that is never opened SHALL NOT record a visit.

#### Scenario: Google result opened from a prefetch
- **WHEN** Chrome prefetches a product page from `https://www.google.com/` for a new visitor, the visitor opens it from the prefetch and then opens a category page from it
- **THEN** one visit is recorded with source `google`, medium `organic` and the product page path as landing path

#### Scenario: Prefetch never opened
- **WHEN** Chrome prefetches a page from Google search and the visitor never opens it
- **THEN** no visit is recorded

### Requirement: Visit records hold no personal data
Each visit SHALL store its source, medium, campaign, referrer host, landing path without query string, creation time and confirmation time. It SHALL NOT store the IP address, user agent, user or customer, session id, full referrer URL, or query string. Values over 255 characters SHALL be truncated.

#### Scenario: Referrer with a path and query
- **WHEN** a new visitor arrives from `https://blog.example.com/post?id=7` on `/sale?utm_source=blog&ref=x`
- **THEN** the visit stores referrer host `blog.example.com` and landing path `/sale`, and no other part of either URL

#### Scenario: Upgrading a shop
- **WHEN** a shop runs its migrations after installing the release
- **THEN** visits can be stored, the visits already stored count as confirmed at their creation time, and existing carts and orders are unchanged
