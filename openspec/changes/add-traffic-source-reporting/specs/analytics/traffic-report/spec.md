# Spec Delta

## Purpose

Gives the development and marketing team a report in the admin panel on storefront traffic: where visits come from, how many of them lead to carts and orders, and which pages visitors land on.

## ADDED Requirements

### Requirement: Only authorized staff can open the traffic report
When the shop's admin panel uses the analytics plugin, it SHALL offer the traffic sources report to admins and to staff holding the traffic report permission. Other staff SHALL NOT see the report in the navigation, and the panel SHALL refuse them when they open its URL.

#### Scenario: Marketing staff member
- **WHEN** a staff member holding the traffic report permission opens the admin panel
- **THEN** the traffic sources report is in the Analytics navigation group and opens

#### Scenario: Admin
- **WHEN** an admin without an explicit traffic report permission opens the report
- **THEN** it opens

#### Scenario: Staff member without the permission
- **WHEN** a staff member who is not an admin and lacks the permission opens the report URL
- **THEN** access is refused and the report is not in their navigation

### Requirement: The permission is available after migrating
Running the shop's migrations SHALL make the traffic report permission available, so admins can grant it to staff. The release SHALL NOT create or change staff roles.

#### Scenario: Upgrading a shop
- **WHEN** a shop installs the release and runs its migrations
- **THEN** the traffic report permission can be assigned to staff, and no role has changed

#### Scenario: Running migrations again
- **WHEN** the migrations run a second time
- **THEN** the permission exists once

### Requirement: The report covers a chosen period
Every figure in the report SHALL cover one period, given by a start date and an end date that are both included. The period SHALL default to the last 30 days, up to and including today.

#### Scenario: Default period
- **WHEN** a staff member opens the report without choosing dates
- **THEN** every figure covers the 30 days that end today

#### Scenario: Custom period
- **WHEN** the staff member chooses 1 to 7 October
- **THEN** visits, carts and orders from before 1 October or after 7 October are not counted

### Requirement: Visits by source
The report SHALL show the number of visits in the period, its split by source and medium, and the number of visits on each day of the period. Visits left out because of their orders, as "Carts and visits of orders that do not count are left out" defines, SHALL NOT be counted.

#### Scenario: Split of visits
- **WHEN** the period holds 60 `google` / `organic`, 30 `facebook` / `social` and 10 `direct` / `none` visits
- **THEN** the report shows 100 visits with that split

#### Scenario: Day without visits
- **WHEN** a day in the period has no visits
- **THEN** that day shows 0 visits

### Requirement: Carts and orders by source
For each source and medium, the report SHALL show:
- the visits in the period;
- the carts created in the period with that traffic source;
- the orders placed in the period with that traffic source;
- the order value of those orders;
- their number divided by the visits.

The order value SHALL be the order total without shipping, as in the dashboard sales figures. Visits and carts left out because of their orders SHALL NOT be counted.

#### Scenario: Facebook row
- **WHEN** the period holds 30 `facebook` / `social` visits, and 6 carts and 3 placed orders with that source
- **THEN** the Facebook row shows 30 visits, 6 carts, 3 orders, their order value and an order rate of 10%

### Requirement: Only reportable placed orders count
The report SHALL count an order only when it was placed and it counts, as "An order counts by its current status" defines. A split order SHALL count once, as the original order with its own placement date and order value, and the orders it was split into SHALL NOT be counted.

#### Scenario: Test order
- **WHEN** an order with a Facebook source is placed in the period and then moved to `test-order`
- **THEN** it is not counted

#### Scenario: Order never placed
- **WHEN** an order with a Facebook source was created in the period but never placed
- **THEN** it is not counted

#### Scenario: Split order
- **WHEN** a placed Facebook order is split into two orders whose status counts
- **THEN** it counts as one order, with the original order's placement date and order value, and the two new orders are not counted

#### Scenario: Ten visits with two split orders
- **WHEN** the period holds 10 visits, 7 of them with a cart and 5 with a placed order, and one order is split into two orders and another into three
- **THEN** the report shows 10 visits, 7 carts, 5 orders and an order rate of 50%

#### Scenario: Order split after the period
- **WHEN** an order placed on 30 September is split on 2 October
- **THEN** a report for September counts it once, and a report for 1 to 7 October does not count it

### Requirement: Carts and visits of orders that do not count are left out
The report SHALL NOT count a cart that has orders when none of them counts. It SHALL NOT count a visit in any figure, including visits by source, visits per day and landing pages, when orders carry it as their traffic source and none of them counts. Carts and visits without orders SHALL be counted. Which orders count is defined by "An order counts by its current status".

#### Scenario: Visit that led to a test order
- **WHEN** a visitor lands on `/sale` from `https://www.google.com/`, creates a cart and places an order, and the order is then moved to `test-order`
- **THEN** the visit, the cart and the order are not counted in any figure, and `/sale` does not get that visit

#### Scenario: Visit and cart without an order
- **WHEN** another visitor lands from `https://www.google.com/` and creates a cart but no order
- **THEN** the visit and the cart are counted

#### Scenario: Test order and counted order in one visit
- **WHEN** one cart of a visit becomes an order moved to `test-order`, and a second cart of the same visit becomes an order whose status counts
- **THEN** the visit and the second cart are counted, and the first cart is not

### Requirement: An order counts by its current status
An order SHALL count when its status is not excluded from reports, whether or not it has been placed. A split order SHALL count when at least one of the orders it was split into counts, also when that order was split again. The orders' current status SHALL decide, also for orders created after the period.

#### Scenario: Order not placed yet
- **WHEN** a visit's cart has an order awaiting payment that has not been placed
- **THEN** the visit and the cart are counted, and the order is not

#### Scenario: Split order keeps its visit and cart
- **WHEN** a Facebook visit's cart becomes an order that is split into two orders whose status counts
- **THEN** the report counts one visit, one cart and one order

#### Scenario: Split test order
- **WHEN** all orders a split order was split into are moved to `test-order`
- **THEN** neither the order, nor its visit, nor its cart is counted

#### Scenario: Order marked as test after the period
- **WHEN** a visit and its cart from 1 to 7 October led to an order placed on 9 October and later moved to `test-order`
- **THEN** a report for 1 to 7 October counts neither the visit nor the cart

### Requirement: Orders without a source are shown as unknown
The report SHALL count orders without a traffic source under an unknown source, with no visits, no carts and no order rate. Carts without a traffic source SHALL NOT be counted.

#### Scenario: Orders and carts without a source
- **WHEN** two orders placed in the period and three carts created in the period have no traffic source
- **THEN** the unknown row shows 2 orders, no carts and no order rate, and the cart total does not include the three carts

### Requirement: Landing pages
The report SHALL list the landing paths of the period's visits with their visit counts, most visited first. It SHALL NOT link landing pages to carts or orders.

#### Scenario: Most visited landing pages
- **WHEN** 40 visits in the period landed on `/` and 25 on `/sale`
- **THEN** `/` is listed first with 40 visits, and `/sale` second with 25
