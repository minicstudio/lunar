# Proposal

## Why

LFP-925: the webshop has to record where its visitors come from and report which source leads to carts and orders. Nothing in the shop database holds this today. The storefront only forwards the referrer to GA4/sGTM. The development and marketing team want the report in the admin panel.

The feature lives in a new monorepo package, `lunarphp/analytics`, built so that later measuring and reporting features can join it. The package holds the rules and the data:
- which requests are visits;
- how a source is classified;
- the visits table;
- cart stamping;
- the admin report.

lunar-frontend (change `track-visitor-traffic-sources`) only attaches the recording middleware to its storefront routes and registers the admin plugin.

## What Changes

- **New package** `packages/analytics` (`lunarphp/analytics`, `Lunar\Analytics\`). It has one provider, one Filament plugin (`AnalyticsPlugin`, navigation group "Analytics"), one config file (`config/lunar/analytics.php`) with a section per feature, and one permission state. Each feature gets its own folder, starting with `src/Traffic/`.
- **Visits.** A new `lunar_traffic_visits` table and `Lunar\Analytics\Traffic\Models\TrafficVisit` model hold one row per visit:
  - `source`, `medium`, `campaign`;
  - `referrer_host`;
  - `landing_path`;
  - `created_at`.

  The table holds no IP address, user agent, user, customer, session id, full referrer URL or query string.
- **Recording.** A route middleware records a visit on the first storefront page view of a Laravel session. It also records one when a later page view carries campaign parameters that give a different source, medium or campaign.
  - Only GET requests answered with 200 and HTML count. Bots do not.
  - A prefetch records nothing. A visit it would start stays pending in the session and is recorded once the visitor opens the page, at the session's next page view or cart creation (user decision). Without this, a visitor who opens a Google result that Chrome prefetched would never get a visit, and their cart and order would be unknown.
  - Recording does not depend on consent and sets no new cookie.
  - Requests served by the admin panel are never recorded, and neither are sessions where a staff member is logged in.
  - A visitor whose active cart already has a traffic source records no visit, so returning customers with a saved cart do not lower the order rate of the source they return through (user decision). A new session then continues that cart's visit.
- **Classification**, in precedence order:
  1. `utm_source`;
  2. click ids that win over the referrer (default: Google `gclid` / `gbraid` / `wbraid` → google / cpc);
  3. known referrer hosts (default: Google, Bing, Yahoo, DuckDuckGo as organic; Facebook, Instagram, TikTok, YouTube as social);
  4. the other click ids (default: `fbclid` → facebook / social);
  5. any other external host (its host / referral);
  6. direct / none.
- **Configurable per shop:**
  - the referrer host map;
  - the click-id map;
  - the bot user-agent pattern;
  - the classifier class;
  - the on/off switch (`ANALYTICS_TRAFFIC_ENABLED`, default `false`: a shop switches recording on, user decision).
- **Carts and orders.**
  - A cart created during a visit gets `meta.traffic_source` (`visit_id`, `source`, `medium`, `campaign`). It is set once (first touch) and never set in the admin panel.
  - Order creation keeps copying the whole cart meta. `FillOrderFromCart` is unchanged (user decision), and tests pin that `traffic_source` reaches the order.
  - The order split copies `traffic_source` to every new order.
  - The order stores nothing about the visit in which it was placed.
- **Admin report.** A **Traffic sources** page in the Analytics group, for admins and for staff with the new `analytics:traffic-sources` permission. For a chosen period (by default the last 30 days) it shows:
  - visit, cart and order totals, order value and order rate;
  - visits by source;
  - visits over time: per day for periods of up to 31 days, per week up to 182 days and per month beyond, so long periods stay readable (user decision);
  - per source and medium: visits, carts, placed and reportable orders, order value and order rate. Orders without a source appear as "unknown";
  - sources and mediums in words, as on the order badge, for example "Google · Paid ad" (user decision);
  - visits and carts whose orders all have a status excluded from reports, for example `test-order`, are left out of every figure (user decision). A split order counts as one order, the original, with its own placement date and value, unlike the dashboard, which counts the new orders (user decision). Its visit and cart count once. Visits and carts without orders still count;
  - the most visited landing pages, not linked to carts or orders.
- **Order pages.** Admins and staff with `analytics:traffic-sources` see each order's traffic source. Other staff do not see it (user decision).
  - The admin order list has a "Traffic source" badge column, with the campaign as tooltip.
  - The order page has its own "Traffic source" section in the sidebar, directly above "Additional info", below Tags and Personalization. It is not in the order summary (user decision). It shows the badge and every stored detail that has a value: the campaign, and from the order's visit the referrer host, the landing page as a link to the storefront page, and when the visit started (user decision). The page reads the visit with one query. The list reads only the order.
  - The badge reads like "Google · Organic search", "Google · Paid ad", "Facebook · Social" or "Direct", and "Unknown" for orders without a source.
  - The raw `traffic_source` no longer appears under "Additional info" for anyone.
  - The analytics plugin adds both, so panels without it are unchanged.
- A migration state class creates the permission on `php artisan migrate`. No role is created. Admins grant the permission to the development and marketing staff.

## Capabilities

### New Capabilities

- `analytics/traffic-recording`: Which storefront requests count as visits, how a visit's source is classified, what a visit stores, and how shops tune recording and switch it on or off.
- `analytics/traffic-attribution`: How a cart carries the source of the visit that created it, how orders, including split orders, keep it, and how authorized staff see it on the admin order pages.
- `analytics/traffic-report`: Who can open the admin traffic report, which period it covers and which figures it shows.

### Modified Capabilities

- None. `orders/admin-order-split` is not archived yet (change `split-admin-orders`), so `analytics/traffic-attribution` states that split orders keep the source. `orders/admin-product-line-editing` is unrelated.

## Impact

- New package `packages/analytics`:
  - `composer.json`;
  - `config/analytics.php`;
  - the migration and factory;
  - `database/state/EnsureAnalyticsPermissions`;
  - `src/AnalyticsServiceProvider`, `src/AnalyticsPlugin`, `src/Traffic/…`;
  - `resources/lang/{en,hu,ro}`.
- Monorepo wiring in the root `composer.json` (autoload, autoload-dev, `extra.lunar.name`, `extra.laravel.providers`, `replace`) and in `phpunit.xml` (an `analytics` test suite). Tests go in `tests/analytics/`.
- Core: `SplitOrder::COPIED_META_KEYS` gains `traffic_source`. `FillOrderFromCart` is not changed.
- Admin: `lunarphp/admin` is not changed. The order list column and the order page section use its `OrderResource` and `ManageOrder` extension hooks.
- No new Composer dependency. The charts use `leandrocfe/filament-apex-charts`, which `lunarphp/admin` already requires.
- lunar-frontend needs this release. It attaches the middleware, registers `AnalyticsPlugin` and seeds the AI assistant table.
- Out of scope:
  - a last-touch (placement) source on orders;
  - whitelisting the cart meta copied to orders (a separate ticket if wanted);
  - unique-visitor counting across sessions;
  - storing `utm_term` / `utm_content`;
  - export, real-time figures, per-visitor drill-down;
  - sorting or filtering orders by traffic source, and showing the visit's referrer host or landing page in the order list;
  - pruning old visits;
  - backfilling existing carts and orders;
  - creating staff roles;
  - sending sources to GTM, Facebook CAPI or the ERP.
- Docs:
  - `docs/system/CODE_MAP.md`;
  - `docs/system/PROJECT_SPECIFICATION.md`;
  - `docs/database/schema.dbml`;
  - `docs/design/order_processing.md`;
  - `docs/changelog/`;
  - `UPGRADE.md`.
