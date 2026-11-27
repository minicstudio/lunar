# Design

## Context

See proposal.md (Why). Facts from this repository and lunar-frontend that shape the approach:

- **Analytics package.** `packages/analytics` has one provider, one plugin (`AnalyticsPlugin`, "Analytics" group), one config file with a section per feature and one permission state (`EnsureAnalyticsPermissions`). The only feature so far is `src/Traffic/`. The admin's session replays (LFP-957) also sit in the Analytics group, at navigation sort 2. The traffic page is at sort 1.
- **Traffic report code.** `TrafficReportQuery` holds three things in one class:
  - the period: whole days, swapped when reversed;
  - the day/week/month grouping (`visitsOverTime()`, `visitsOverTimeInterval()`, `DAILY_MAX_DAYS`, `WEEKLY_MAX_DAYS`);
  - the order rules: `orderExclusions()`, `countingSplitOriginalIds()` and the reportable placed-order query inside `ordersBySource()`.

  `TrafficSourcesPage` holds the period filter form, `DEFAULT_PERIOD_DAYS`, `defaultFrom()` and `periodFromFilters()`. `InteractsWithTrafficReport` and `TrafficSourcesPageTest` call `periodFromFilters()`.
- **Admin and staff exclusion.** `TrafficVisitRecorder::shouldTrack()` is false while Filament serves the request or a staff member is logged in on the panel guard. It does not read the traffic switch.
- **Where the storefront adds to cart (lunar-frontend).**
  - `AddToCart` (product page), `ProductCard`, `GiftSelectionModal` and `WishlistProductCard` call `CartSession::manager()->add()`. On success they send the GA4 `add_to_cart`.
  - The order history reorder (`OrderHistory\DetailView`) calls `$cart->add()` and sends no GA4 event.
  - All of these run in Livewire requests, which carry the session.
- **Where checkout starts (lunar-frontend).** `Checkout::mount()` validates the cart (exists, has items, has a guest or user, gift lines checked). It then calls `dispatchCheckoutInitiatedEvent()` and the storefront marketing event. This runs on every full load of the checkout details page. The Stripe wallet buttons sit only on checkout pages, so every storefront order passes through it.
- **Cart line changes in core.** `Cart::add()` dispatches no event. Cart lines are also created or changed by:
  - `UpdateCartLine` (cart page quantity);
  - `MergeCart` (`lines()->create()` on login);
  - `SplitOrder` (admin).
- **Empty carts.** `CartSessionManager::fetchOrCreate()` creates a new, empty cart when the session's cart has completed orders, even with `auto_create` off. So `lunar_carts` holds carts without products, and the traffic report counts them when they carry a source.
- **Carts.** `Cart` uses soft deletes. `lunar:prune:carts` is off by default (`prune_tables.enabled = false`). When it is on, it deletes carts without orders after 90 days.
- **Order meta.** `FillOrderFromCart` copies the whole cart meta to the order, and `ManageOrder` lists unhidden meta keys under "Additional info".

## Goals / Non-Goals

**Goals:**

- One code path counts purchases for both reports, so the two pages always agree.
- Recording costs at most one lookup and two small inserts per add to cart, one lookup and one insert per checkout open, and nothing on other requests.
- No change to core, admin or other packages.
- The traffic report's behavior and tests stay as they are.

**Non-Goals:**

- Matching GA4 numbers. Server-side recording also counts shoppers who decline consent or block GA.
- Following individual carts through the funnel. The report compares period totals, like the traffic report.
- Recording cart changes the storefront does not report.

## Decisions

### A `Conversions` feature folder in the analytics package

```
packages/analytics/
  config/analytics.php                          + 'conversions' => ['enabled' => …]
  database/migrations/2026_11_26_000000_create_conversion_events_table.php
  database/factories/ConversionEventFactory.php
  database/state/EnsureAnalyticsPermissions.php  + analytics:conversions
  resources/lang/{en,hu,ro}/conversions.php
  resources/lang/{en,hu,ro}/plugin.php          + filters.from / filters.to (moved from traffic.php)
  resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php  + analytics:conversions label, description
  src/Reports/ReportPeriod.php                  shared
  src/Reports/CountedOrders.php                 shared
  src/Filament/Concerns/HasPeriodFilters.php    shared
  src/Conversions/
    Enums/ConversionEventType.php
    Models/ConversionEvent.php
    Services/ConversionEventRecorder.php
    Reports/ConversionReportQuery.php
    Filament/Pages/ConversionsPage.php
    Filament/Widgets/Concerns/InteractsWithConversionReport.php
    Filament/Widgets/ConversionStatsOverview.php
    Filament/Widgets/ConversionFunnelChart.php
    Filament/Widgets/ConversionsOverTimeChart.php
```

`AnalyticsPlugin::register()` adds `ConversionsPage` and its widgets next to the traffic ones. The provider needs no change: config, migrations and translations are already loaded for the whole package. The permission list lives in the state class.

*Alternative:* a new package. Rejected: the package was built so that later analytics features join it (LFP-925 design).

### The storefront reports events; the package does not watch cart lines

`ConversionEventRecorder` has two public methods for the storefront: `addToCart(Cart $cart)` and `beginCheckout(Cart $cart)`. The lunar-frontend counterpart calls them where it already sends the GA4 events: after each successful add (product page, product card, gift selection, wishlist, and also reorder) and in `Checkout::mount()` next to `dispatchCheckoutInitiatedEvent()`. The traffic feature works the same way: the package holds the rules and the host attaches them.

*Alternative:* a `CartLine` observer (`created`, and `updated` when the quantity grows). Rejected: it also counts login merges (`MergeCart`) and quantity changes on the cart page, and it cannot tell a shopper's add from other line writes.

*Alternative:* a new core event in `Cart::add()`. Rejected: it changes an upstream file, which makes merges harder. The checkout start would still need a storefront call.

*Alternative:* listen to lunar-frontend's `ProductAddedToCartEvent`. Rejected: only the product page dispatches it.

*Alternative:* a route middleware on the checkout page, like `RecordTrafficVisit`. Rejected for now: adds to cart still need direct calls, and one mechanism for both events is simpler. `Checkout::mount()` has already checked the cart when it calls the recorder.

### One events table with a type column

`ConversionEvent` extends `Lunar\Base\BaseModel` (table prefix), with `UPDATED_AT = null`, like `TrafficVisit`. The table is `$this->prefix.'conversion_events'`:

| Column | Type |
| --- | --- |
| `id` | id |
| `type` | string 32 (`ConversionEventType`: `add_to_cart`, `cart_started`, `begin_checkout`) |
| `cart_id` | unsigned big integer, no foreign key |
| `created_at` | timestamp, indexed |

There is also a composite index on `cart_id` and `type`, for the once-per-cart lookups and the left-out carts. The migration has `shouldRun()` with `Schema::hasTable()`, like the traffic migration.

The table has no foreign key. Pruned or deleted carts then keep their history, and past periods do not change. Events of a deleted cart can no longer be left out because of its orders, but the pruning pipeline deletes only carts without orders.

*Alternative:* flags or counters in the cart meta. Rejected: `FillOrderFromCart` copies them to every order, where they show under "Additional info". A counter also cannot spread adds over time.

*Alternative:* count `lunar_carts` by `created_at`. Rejected:
- it counts the empty carts created after completed orders, and the carts `SplitOrder` builds in the admin;
- soft-deleted carts drop out;
- carts from before recording was switched on would count.

*Alternative:* one table per event type. Rejected: three tables that differ only in name.

### A cart starts at its first reported add

`addToCart()` looks up whether the cart already has a `cart_started` event. If it does not, it inserts one, then the `add_to_cart` event, both with the same time. The carts figure is then a plain `COUNT(*)` of `cart_started` in the period. It counts only carts that got a product.

*Alternative:* a `Cart::created` observer. Rejected: it counts the empty carts `CartSessionManager` creates after completed orders.

*Alternative:* derive the start from the cart's earliest `add_to_cart` (`MIN(created_at)` per cart). Rejected: every report would scan all add-to-cart events from the start.

### Checkout start once per cart

`beginCheckout()` inserts a `begin_checkout` event only when the cart has none (user decision: once per cart). Two simultaneous first calls can insert two. The risk is negligible, and it is not worth a lock.

*Alternative:* a unique index. Rejected: MySQL has no partial index, and a unique (`cart_id`, `type`) pair would forbid repeated `add_to_cart` rows.

*Alternative:* record every open and count distinct carts. Rejected: a larger table for the same figure.

### Recording guard and failures

Each recorder method returns without doing anything when:
- `lunar.analytics.conversions.enabled` is false (`ANALYTICS_CONVERSIONS_ENABLED`, default `false`, following the traffic switch);
- `TrafficVisitRecorder::shouldTrack()` is false;
- the cart is not saved.

The recorder gets `TrafficVisitRecorder` injected only for `shouldTrack()`, which does not depend on the traffic switch. The lookups and inserts run in a `try` block. On a `Throwable` the method calls `report()` and returns, so an add to cart or a checkout never fails because of analytics. The insert is synchronous: it is two small rows, and a queued job would lose the request context the guard needs.

*Alternative:* move `shouldTrack()` to a shared class. Rejected for now: the method has no traffic state, so reusing it does not couple anything, and moving it would mean editing a tested class for nothing.

*Alternative:* tie conversions to `ANALYTICS_TRAFFIC_ENABLED`. Rejected: the conversions report must work without traffic recording, and each feature has its own config section.

### Shared report code, so both reports count alike

The order rules, the period and the grouping move out of the traffic classes, unchanged in behavior.

- **`Reports\ReportPeriod`**:
  - takes `from` and `to`, swaps them when reversed and widens them to whole days;
  - `from()`, `to()`;
  - `days()`: every `Y-m-d` in the period;
  - `interval()`: `day`, `week` or `month` (`DAILY_MAX_DAYS = 31`, `WEEKLY_MAX_DAYS = 182`);
  - `group(array $perDay)`: sums per bucket, keyed by the bucket's first day inside the period.
- **`Reports\CountedOrders`** (built for a `ReportPeriod`):
  - `placedQuery()`: the orders placed in the period that count. That is reportable orders without `meta.split_from_order_id`, plus the split originals that count;
  - `excludedCartIds()` and `excludedVisitIds()`: from today's once-per-instance lookup;
  - the moved methods: `orderExclusions()`, `countingSplitOriginalIds()`, `splitIntoOrderIdsOf()`, `visitIdOf()`, `ids()`.
- **`Filament\Concerns\HasPeriodFilters`** (trait for `Dashboard` pages with `HasFiltersForm`):
  - `filtersForm()`, `DEFAULT_PERIOD_DAYS`, `defaultFrom()`, `periodFromFilters()`, `parseDate()`;
  - the labels move from `traffic.filters.*` to `plugin.filters.*`.

`TrafficReportQuery` builds both shared classes in its constructor and delegates to them. Its public methods and constants stay: `from()`, `to()`, `visitsPerDay()`, `visitsOverTime()`, `visitsOverTimeInterval()`, `cartsBySource()`, `ordersBySource()`, `funnel()`, `totals()`, and `DAILY_MAX_DAYS` / `WEEKLY_MAX_DAYS` as aliases. `TrafficSourcesPage` uses the trait, so `TrafficSourcesPage::periodFromFilters()` keeps working. `TrafficReportQueryTest` and `TrafficSourcesPageTest` run unchanged as the refactor's safety net.

*Alternative:* the conversions query builds a `TrafficReportQuery` and reads `ordersBySource()` and a new public exclusions accessor. Rejected: purchases per day would need another order method on the traffic class, and the conversions page would depend on traffic internals.

*Alternative:* copy the order rules. Rejected: two copies drift, and the spec requires the same purchase count on both pages.

### `ConversionReportQuery` computes every figure

`Conversions\Reports\ConversionReportQuery` takes `from` and `to`, and builds a `ReportPeriod` and a `CountedOrders`.

- **Events.** `ConversionEvent` rows by `created_at` in the period, with `whereNotIn('cart_id', $orders->excludedCartIds())`.
- **`totals()`.** It returns `add_to_cart`, `carts`, `checkouts`, `purchases`, `tracked_purchases`, `checkout_rate`, `purchase_rate` and `cart_purchase_rate`:
  - one grouped `COUNT(*) … GROUP BY type` on the events;
  - `placedQuery()->count()` for the purchases;
  - the same query restricted to carts with a conversion event of any time (`whereIn('cart_id', …)` on the events table, served by the `cart_id, type` index) for the tracked purchases;
  - the purchase rate and the cart purchase rate divide the tracked purchases, as the traffic report's rates divide only orders with a source;
  - rates are `null` when the divisor is 0.
- **`overTime()`.** It returns `{interval, steps}`. `steps` maps each step to `group()` of its days, with 0 for days without events:
  - one query on events grouped by `type` and `DATE(created_at)`;
  - one query on the tracked purchases grouped by `DATE(placed_at)`, so the series holds only purchases from tracked carts, as the rates use (user decision 2026-11-27; the step is named `tracked_purchases`).

The exclusion lookup runs once per instance, as today.

*Alternative:* rates from all purchases. Rejected (user decision). Orders whose carts were never recorded raise the purchase rates: carts filled before recording was switched on, staff sessions, failed inserts, a host flow without the recorder call. The traffic report already divides only orders with a source.

*Alternative:* count only purchases from tracked carts. Rejected: the purchases would differ from the traffic report's orders and the order list, and a flow without the recorder would drop its orders silently. Showing both numbers makes such a gap visible.

*Alternative:* require the cart's events to fall in the period. Rejected: a cart that reached checkout before the period and buys in it would drop out. That is following carts (cohorts), a non-goal.

### The report page is a third Analytics dashboard

`Conversions\Filament\Pages\ConversionsPage` extends `Filament\Pages\Dashboard` with `HasFiltersForm` and `HasPeriodFilters`:
- route and slug `analytics/conversions`;
- `PERMISSION = 'analytics:conversions'`;
- `canAccess()` as on the traffic page (admins pass through `Gate::after`);
- navigation sort 2, the Analytics group and a funnel icon;
- two columns.

Its widgets use `InteractsWithConversionReport`, which gives the page's report for the filters, `canView()` through `ConversionsPage::canAccess()` and rate formatting. No widget polls.

- `ConversionStatsOverview`, full width, seven stats, the counts in a row of four and the rates in a row of three below, as on the Traffic sources page:
  - "Add to cart";
  - "Carts";
  - "Checkouts";
  - "Purchases", with the number of purchases from tracked carts as description;
  - "Checkout rate", "Purchase rate" and "Cart purchase rate", each with what it divides as description.

  A rate without a value shows "—".
- `ConversionFunnelChart` (`ApexChartWidget`, full width, user decision 2026-11-27): adds to cart, carts, checkouts and tracked purchases from `totals()` as one bar series with `plotOptions.bar.isFunnel` and the trapezoid funnel shape, one colour per step. The funnel hides the axis labels, so an `extraJsOptions()` data label formatter writes "Step: count" on each bar, and overflowing labels stay visible. Heading "Conversion funnel".

  Both charts show only purchases from tracked carts (user decision 2026-11-27), labelled "Purchases from tracked carts", so a chart never compares carts with purchases whose carts it never counted. Their step names come from the shared `widgets.steps` translations. The "Purchases" stat keeps counting every purchase.
- `ConversionsOverTimeChart` (`ApexChartWidget`, grouped bars, full width):
  - four series, side by side in each day, week or month, so each bucket shows its own funnel (user decision; a line showed only dots for short periods);
  - labels as in `VisitsOverTimeChart` (`D MMM` for days and weeks, `MMM YYYY` for months);
  - heading "Funnel per day / week / month".

*Alternative:* the figures on the Traffic sources page. Rejected (user decision). That page answers which source works, while this one shows where shoppers drop out. It also counts only carts with a source, and it needs traffic recording.

*Alternative:* a single funnel bar chart of the period's totals instead of the chart over time. Rejected: the chart per bucket shows the trend (user decision: numbers plus a chart). The funnel chart was later added next to it (user decision 2026-11-27), as the quickest view of where shoppers drop out.

*Alternative:* a custom Blade funnel component. Rejected: the installed `leandrocfe/filament-apex-charts` (ApexCharts 6.10) draws funnels natively, with Filament's theme and dark mode.

### Permission `analytics:conversions`

`EnsureAnalyticsPermissions::run()` loops over `['analytics:traffic-sources', 'analytics:conversions']` with `firstOrCreate` for the panel guard. Labels and descriptions go in `resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php`, which the provider already merges. No role is created (user decision: its own permission).

*Alternative:* `analytics:traffic-sources` also opens this page. Rejected (user decision).

### Contract with the lunar-frontend counterpart

These calls are the counterpart's work, listed here so it matches the spec:
- After each successful add, it calls `app(ConversionEventRecorder::class)->addToCart(CartSession::manager())`, which returns the cart just added to without recalculating it. This applies to the four GA4 sites and to the reorder, which passes its own cart once, however many lines it adds. Unlike GA4, the reorder counts, because it is a shopper's add.
- In `Checkout::lfpMount()`, after the cart checks, it calls `->beginCheckout($this->cart)`.
- It can add `lunar_conversion_events` to the AI assistant's table lookup seeder.

`UPGRADE.md` documents the calls for other hosts.

### Traffic source on the first product line (user decision, 2026-11-27)

After a completed order, `CartSessionManager::fetchOrCreate()` creates a new cart on the next `CartSession::current()` call, even with `$create = false`. `StampCartWithTrafficSource` stamped it on `Cart` `creating`, so the Traffic sources report counted an empty cart per order. For a logged-in customer, the empty cart also stayed their active cart in later sessions, so it held back new visits and passed the old visit to the next order.

`StampCartWithTrafficSource` now observes `CartLine` `created`: when the line's cart has no source, it stores the session's current visit and saves the cart without events. `Cart::add()` refreshes the cart after the line is created, so the instance the storefront holds has the source. Login merges (`MergeCart`) create lines the same way, so a merged customer cart gets the current visit. The report query is unchanged. Hosts with `lunar.cart_session.auto_create = true` no longer lose the source: the cart created on the landing request gets the visit with its first product, so the `UPGRADE.md` note about them is removed.

*Alternative:* count only carts with lines in the report. Rejected: the empty cart would still hold back visits and pass the old source on.

*Alternative:* count only carts with a `cart_started` event. Rejected: the traffic report would depend on `ANALYTICS_CONVERSIONS_ENABLED`.

*Alternative:* stop `CartSessionManager` from creating the cart. Rejected: it changes upstream core code and every `current()` caller after an order.

## Risks / Trade-offs

- [A host does not call the recorder] → Adds to cart, carts and checkouts stay 0, while purchases are still counted. `UPGRADE.md` lists the calls among host responsibilities.
- [Carts were started before the period] → Rates can exceed 100% because they compare totals. The spec states this, and the stats show the counts next to the rates. Recording switched on mid-period does not raise the purchase rates, which divide only purchases from tracked carts.
- [The tracked-purchase lookup] → One more count per report, a semi-join on the events table's `cart_id, type` index.
- [The figures differ from GA4] → Server-side recording counts shoppers without consent or with ad blockers. Checkouts count once per cart, where GA4 counts every page load. Reorders count. This is intended, and the changelog and `UPGRADE.md` say so.
- [A guest cart merged on login into an existing customer cart] → The guest cart counted as started, and the checkout is counted on the customer cart, which may have started earlier. The total is slightly higher than the number of shoppers. This is rare, and following merges is not worth the complexity.
- [Two simultaneous first adds or checkout opens for one cart] → Duplicate `cart_started` or `begin_checkout` rows. This is negligible.
- [Table growth] → At most two rows per cart plus one per add, each small, with `created_at` indexed. Pruning can come later.
- [The exclusion lookup reads only orders with an excluded status created or placed from the period start] → An event in the period whose cart has a test order created before the period and never placed is not left out. This is the same edge the traffic report accepts.
- [Moving the traffic report's order rules] → It is a mechanical move behind the unchanged public methods, and the existing traffic tests must pass without edits.
- [Conversions and session replays share navigation sort 2] → Their order follows Filament's tie handling. `lunarphp/admin` is not changed for it.
- [Staff test on the storefront without logging in to the admin] → Their events are recorded. They are left out once the order is moved to `test-order`.
- [Empty carts stamped before the release] → They keep their source and are still counted for their periods. For a logged-in customer, such a cart still holds back new visits until it becomes an order. Stored data is not changed (user decision).
- [A cart created empty and filled later] → The traffic report counts carts by `created_at`, so it counts that cart on the day it was created.

## Migration Plan

1. Release lunar-minic with this change.
2. Hosts run `php artisan migrate`, which creates `lunar_conversion_events` and `analytics:conversions`. An admin grants the permission to the marketing staff.
3. Release the lunar-frontend counterpart, which calls the recorder.
4. Set `ANALYTICS_CONVERSIONS_ENABLED=true`. Recording starts.

Rollback: set `ANALYTICS_CONVERSIONS_ENABLED=false` to stop recording at once. Reverting the release leaves the table and the permission, which nothing else reads. The migration's `down()` drops the table.

## Open Questions

- The Hungarian and Romanian names of the steps ("Kosárba helyezés", "Kosarak", "Pénztár megkezdése" or "Checkout", "Vásárlások") can be settled with marketing during implementation. They are translations only.
