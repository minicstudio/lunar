# Proposal

## Why

LFP-924: the marketing team wants to see the shop's purchase funnel in the admin panel: how many products are added to carts, how many carts are started, how many of them reach checkout and how many end in a purchase.

Some of this already exists, and some of it is missing:
- Purchases: the **Traffic sources** report (`lunarphp/analytics`, LFP-925) already counts placed orders that count, with split orders counted once.
- Carts: the same report counts only carts with a traffic source. It needs `ANALYTICS_TRAFFIC_ENABLED` and also counts the empty cart `CartSessionManager` creates after a completed order.
- Add to cart and checkout start: they are not stored in the shop database at all. lunar-frontend sends `add_to_cart` and `begin_checkout` only to GA4/sGTM, Facebook CAPI, Algolia Insights and, for logged-in customers, Mailchimp and Klaviyo.

## What Changes

- **New feature folder** `packages/analytics/src/Conversions/`, with its own config section, permission, page and widgets in the existing package.
- **Conversion events.** A new `lunar_conversion_events` table and `ConversionEvent` model hold one row per event: the type, the cart id and the time. The table holds no other data about the shopper. There are three types:
  - `add_to_cart`: each add-to-cart action the storefront reports;
  - `cart_started`: a cart's first reported add to cart, so a cart counts once and only when a product was added to it;
  - `begin_checkout`: a cart's first checkout start, once per cart (user decision).
- **Recording API.** `ConversionEventRecorder::addToCart(Cart)` and `::beginCheckout(Cart)` are for the storefront to call where it already sends these GA4 events. Recording:
  - is off by default, and a shop switches it on with `ANALYTICS_CONVERSIONS_ENABLED`, separately from traffic recording;
  - skips the admin panel and sessions with a logged-in staff member, as traffic recording does;
  - does not depend on consent and sets no cookie;
  - never interrupts the shopper's action: a failed insert is reported and ignored.
- **Conversions report.** A new **Conversions** page in the Analytics group, separate from Traffic sources (user decision). It is open to admins and to staff with the new `analytics:conversions` permission (user decision). For a period (by default the last 30 days) it shows:
  - add to carts, carts, checkouts and purchases;
  - the checkout rate (checkouts / carts), the purchase rate (purchases from tracked carts / checkouts) and the cart purchase rate (purchases from tracked carts / carts). A purchase is from a tracked cart when its cart has recorded events. The purchases figure shows all purchases and how many are from tracked carts, as the traffic report shows all orders and those with a source (user decision);
  - the four steps over time, per day, week or month as on the traffic report (user decision).

  Purchases follow the traffic report's order rules, so both pages show the same purchase count. Events of carts whose orders all do not count, such as test orders, are left out, as the traffic report does with carts.
- **Shared report code.** The period filter, the day/week/month grouping and the order-counting rules move out of the traffic classes into shared classes. Both reports use them, and the traffic report's figures do not change.
- The migration state class also creates `analytics:conversions`. No role is created.

## Capabilities

### New Capabilities

- `analytics/conversion-recording`: Which storefront actions are recorded as add to cart, cart start and checkout start, when recording runs, and what an event stores.
- `analytics/conversion-report`: Who can open the admin conversions report, which period it covers and which figures and rates it shows.

### Modified Capabilities

- None. `analytics/traffic-report` keeps its requirements. Only its implementation moves to the shared period and order classes. `analytics/traffic-recording` and `analytics/traffic-attribution` are unchanged.

## Impact

- `packages/analytics`:
  - `config/analytics.php` (new `conversions` section);
  - a migration and factory for `lunar_conversion_events`;
  - `database/state/EnsureAnalyticsPermissions` (second permission);
  - `src/Conversions/…`;
  - new shared `src/Reports/ReportPeriod`, `src/Reports/CountedOrders` and `src/Filament/Concerns/HasPeriodFilters`;
  - `src/Traffic/Reports/TrafficReportQuery` and `src/Traffic/Filament/Pages/TrafficSourcesPage` use the shared classes;
  - `AnalyticsServiceProvider`, `AnalyticsPlugin`;
  - `resources/lang/{en,hu,ro}` (new `conversions.php`, filter labels moved to `plugin.php`, permission labels).
- Core, admin and other packages: not changed. No new Composer dependency.
- Tests in `tests/analytics/Feature/Conversions/` and `tests/analytics/Unit/`. The existing traffic tests must stay green.
- lunar-frontend needs a counterpart change, which is not part of this change. It calls the recorder:
  - `addToCart()` after each successful add: product page, product card, gift selection, wishlist and reorder;
  - `beginCheckout()` where the checkout page dispatches `CheckoutInitiatedEvent`.

  The counterpart can also add `lunar_conversion_events` to the AI assistant's allowed tables.
- Docs:
  - `docs/system/CODE_MAP.md`;
  - `docs/system/PROJECT_SPECIFICATION.md`;
  - `docs/database/schema.dbml`;
  - `docs/changelog/`;
  - `UPGRADE.md`.
- Out of scope:
  - per-source conversion figures, such as add to cart or checkout columns on the Traffic sources report;
  - add-to-cart quantities and values, and remove-from-cart;
  - following individual carts through the funnel (cohorts);
  - changing the traffic report's cart count;
  - pruning old events;
  - backfilling from GA4 or existing carts.
