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
The report SHALL show the number of visits in the period, its split by source and medium, and the number of visits on each day of the period.

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

The order value SHALL be the order total without shipping, as in the dashboard sales figures.

#### Scenario: Facebook row
- **WHEN** the period holds 30 `facebook` / `social` visits, and 6 carts and 3 placed orders with that source
- **THEN** the Facebook row shows 30 visits, 6 carts, 3 orders, their order value and an order rate of 10%

### Requirement: Only reportable placed orders count
The report SHALL count an order only when it was placed and its status is not excluded from reports.

#### Scenario: Test order
- **WHEN** an order with a Facebook source is placed in the period and then moved to `test-order`
- **THEN** it is not counted

#### Scenario: Order never placed
- **WHEN** an order with a Facebook source was created in the period but never placed
- **THEN** it is not counted

#### Scenario: Split order
- **WHEN** a placed Facebook order is split into two orders in the period
- **THEN** the two new orders are counted and the original order is not

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
