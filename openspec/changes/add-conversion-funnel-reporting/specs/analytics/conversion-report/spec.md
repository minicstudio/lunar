# Spec Delta

## Purpose

Gives the marketing team a report in the admin panel on the shop's purchase funnel: how many products are added to carts, how many carts are started, how many reach checkout and how many end in a purchase, with the rates between the steps and their trend over time.

## ADDED Requirements

### Requirement: Only authorized staff can open the conversions report
When the shop's admin panel uses the analytics plugin, it SHALL offer the conversions report to admins and to staff holding the conversions report permission. Other staff SHALL NOT see the report in the navigation, and the panel SHALL refuse them when they open its URL. The traffic report permission SHALL NOT open the conversions report.

#### Scenario: Marketing staff member
- **WHEN** a staff member holding the conversions report permission opens the admin panel
- **THEN** the conversions report is in the Analytics navigation group and opens

#### Scenario: Admin
- **WHEN** an admin without an explicit conversions report permission opens the report
- **THEN** it opens

#### Scenario: Staff member with only the traffic report permission
- **WHEN** a staff member who is not an admin and holds the traffic report permission but not the conversions report permission opens the conversions report URL
- **THEN** access is refused and the conversions report is not in their navigation

### Requirement: The conversions permission is available after migrating
Running the shop's migrations SHALL make the conversions report permission available, so admins can grant it to staff. The release SHALL NOT create or change staff roles, and SHALL keep the traffic report permission.

#### Scenario: Upgrading a shop
- **WHEN** a shop installs the release and runs its migrations
- **THEN** the conversions report permission can be assigned to staff, the traffic report permission still exists, and no role has changed

#### Scenario: Running migrations again
- **WHEN** the migrations run a second time
- **THEN** the conversions report permission exists once

### Requirement: The conversions report covers a chosen period
Every figure in the conversions report SHALL cover one period, given by a start date and an end date that are both included. The period SHALL default to the last 30 days, up to and including today.

#### Scenario: Default period
- **WHEN** a staff member opens the report without choosing dates
- **THEN** every figure covers the 30 days that end today

#### Scenario: Custom period
- **WHEN** the staff member chooses 1 to 7 October
- **THEN** events and orders from before 1 October or after 7 October are not counted

### Requirement: Funnel totals
The report SHALL show for the period the number of add-to-cart events, cart starts, checkout starts and purchases. The first three SHALL count the recorded conversion events by their creation time.

#### Scenario: A week of shopping
- **WHEN** the period holds 120 add-to-cart events, 50 cart starts, 20 checkout starts and 8 purchases
- **THEN** the report shows 120 adds to cart, 50 carts, 20 checkouts and 8 purchases

### Requirement: Funnel chart
The report SHALL show the period's funnel totals as a funnel chart, between the totals and the funnel over time: adds to cart, carts, checkouts and purchases from tracked carts from top to bottom, each labelled with its name and count. Purchases from carts that are not tracked SHALL NOT be shown in the chart.

#### Scenario: A week of shopping as a funnel
- **WHEN** the period holds 120 add-to-cart events, 50 cart starts, 20 checkout starts and 10 purchases, 8 of them from tracked carts
- **THEN** the funnel chart shows "Add to cart: 120", "Carts: 50", "Checkouts: 20" and "Purchases from tracked carts: 8" from top to bottom

### Requirement: Purchases count as in the traffic report
Purchases SHALL be the orders placed in the period that count, by the rules of "Only reportable placed orders count" and "An order counts by its current status" in `analytics/traffic-report`, whatever their traffic source. The report SHALL show the same number as the traffic sources report's orders for the same period.

#### Scenario: Orders with and without a traffic source
- **WHEN** the period holds one placed order with a Facebook source, one placed order without a source and one placed order moved to `test-order`
- **THEN** the report shows 2 purchases, as the traffic sources report shows 2 orders

#### Scenario: Split order
- **WHEN** a placed order is split into two orders whose status counts
- **THEN** it counts as one purchase on the original order's placement date

### Requirement: Purchases from tracked carts
A purchase SHALL be from a tracked cart when its order's cart has at least one recorded conversion event, from any time. The report SHALL show, with the purchases, how many of them are from tracked carts. The purchases figure SHALL still count every purchase.

#### Scenario: Order whose cart was not recorded
- **WHEN** the period holds 3 purchases, two from carts with recorded events and one from a cart filled before conversion recording was switched on
- **THEN** the report shows 3 purchases, 2 of them from tracked carts

#### Scenario: Cart recorded before the period
- **WHEN** a purchase in the period comes from a cart whose cart start and checkout start were recorded before the period
- **THEN** the purchase is from a tracked cart

### Requirement: Step rates
The report SHALL show the checkout rate (checkout starts divided by cart starts), the purchase rate (purchases from tracked carts divided by checkout starts) and the cart purchase rate (purchases from tracked carts divided by cart starts), as the traffic sources report divides only orders with a traffic source. A rate whose divisor is zero SHALL show no value. The rates compare totals of the period and SHALL NOT follow individual carts.

#### Scenario: Rates of a week
- **WHEN** the period holds 50 cart starts, 20 checkout starts and 8 purchases, all from tracked carts
- **THEN** the report shows a checkout rate of 40%, a purchase rate of 40% and a cart purchase rate of 16%

#### Scenario: Purchases from carts that were not recorded
- **WHEN** the period holds 20 checkout starts and 10 purchases, 8 of them from tracked carts
- **THEN** the report shows 10 purchases and a purchase rate of 40%

#### Scenario: No checkout starts
- **WHEN** the period holds cart starts and purchases but no checkout start
- **THEN** the purchase rate shows no value

#### Scenario: Carts from before the period
- **WHEN** the period holds 2 cart starts and 3 purchases from tracked carts whose cart starts were recorded before the period
- **THEN** the cart purchase rate shows 150%

### Requirement: Events of carts whose orders do not count are left out
The report SHALL NOT count the add-to-cart events, cart start or checkout start of a cart that has orders when none of them counts, as "An order counts by its current status" in `analytics/traffic-report` defines. Events of carts without orders SHALL be counted.

#### Scenario: Cart that led to a test order
- **WHEN** a shopper adds two products to a cart, opens checkout and places an order, and the order is then moved to `test-order`
- **THEN** neither the two adds to cart, nor the cart start, nor the checkout start, nor the order is counted

#### Scenario: Cart without an order
- **WHEN** another shopper adds a product and opens checkout but places no order
- **THEN** the add to cart, the cart start and the checkout start are counted

### Requirement: Funnel over time
The report SHALL show the period's add-to-cart events, cart starts, checkout starts and purchases from tracked carts over time, using the same days, weeks and months as "Visits over time" in `analytics/traffic-report`. Purchases from carts that are not tracked SHALL NOT be shown over time. A day, week or month without events or purchases SHALL show 0 for that step.

#### Scenario: Purchase from a cart that was not recorded
- **WHEN** the period 1 to 7 October holds a purchase on 3 October from a tracked cart and one on 4 October from a cart without recorded events
- **THEN** purchases over time show 1 on 3 October and 0 on 4 October

#### Scenario: Day without checkouts
- **WHEN** a day in the period 1 to 7 October has adds to cart but no checkout start
- **THEN** the four steps are shown per day, and that day shows 0 checkouts

#### Scenario: Three-month period
- **WHEN** the staff member chooses 4 August to 8 November
- **THEN** the four steps are shown for the same 14 weeks as the traffic sources report's visits

### Requirement: Recorded steps cover only time with recording on
Add to carts, carts and checkouts SHALL show only the events recorded while conversion recording was on. Purchases SHALL be counted whether or not recording was on, and the purchase rates, the funnel chart and the funnel over time SHALL use only the purchases from tracked carts.

#### Scenario: Recording switched on mid-period
- **WHEN** conversion recording is switched on on 4 October, the staff member chooses 1 to 7 October, two orders were placed on 1 and 2 October from carts filled before 4 October, and one on 6 October from a cart recorded on 6 October
- **THEN** adds to cart, carts and checkouts come from 4 to 7 October only, the report shows 3 purchases from the whole period, and the purchase rates and both charts use only the purchase of 6 October
