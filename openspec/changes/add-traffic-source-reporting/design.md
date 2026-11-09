# Design

## Context

See proposal.md (Why). Facts from this repository and lunar-frontend that shape the approach:

- **Package precedents.**
  - `packages/feedback` and `packages/loyalty` show how a fork package is wired:
    - its own `composer.json` requiring `lunarphp/core` and `lunarphp/admin` at `self.version`;
    - root `composer.json` entries under `autoload`, `autoload-dev`, `extra.lunar.name`, `extra.laravel.providers` and `replace`;
    - a `phpunit.xml` test suite, with `tests/<package>/TestCase.php`;
    - a provider that merges `config/<package>.php` as `lunar.<package>`, loads migrations unless `lunar.database.disable_migrations` is set, loads translations as `lunarpanel.<package>` (en, hu, ro) and publishes config and migrations.
  - The Filament plugin is registered by lunar-frontend in `getLunarPanelPlugins()`.
  - Review ships permission labels in `resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php`.
  - `split_packages.yml` does not split fork packages.
- **Naming.** Core already has a `Lunar\Marketing` namespace (`MarketingAvailability`, `TracksStorefrontMarketingEvents`) for Mailchimp and Klaviyo. That is why the package is called `analytics` (user decision).
- **Storefront requests.**
  - lunar-frontend's storefront routes sit in two groups with route middleware from its `middlewares.php` aliases (`handle-ga4-fpid`). These run inside its global `StartSession`, so a route middleware reads and writes the session before it is saved.
  - Livewire update requests (`/livewire/update`) do not pass through those groups but do carry the session.
  - Carts are created by `CartSession` on the first call that needs one, usually inside a Livewire request.
  - `CartSessionManager::fetchOrCreate()` uses the session's cart id, otherwise the logged-in user's latest `unmerged()->active()` cart. When that cart has a placed order, it returns a new cart even with `auto_create` off.
  - On login, including a remember-me login, `CartSessionAuthListener` puts the user's active cart in the session.
- **Admin and session.**
  - The admin panel runs on the same session cookie as the storefront, with the `staff` guard.
  - `SplitOrder` creates and saves carts inside admin requests.
  - Filament's `isServing()` is true for panel routes and for Livewire requests from panel pages.
- **Orders.**
  - `FillOrderFromCart` sets `'meta' => $cart->meta` on every creation run. Order processing depends on this copy. lunar-frontend reads `payment_option` from order meta 17 times, and `shippingType`, `isBillingSameAsShipping`, `language_locale`, `is_guest`, `locker_id`, the loyalty keys and `wallet_type` reach the order the same way.
  - `SplitOrder` builds new orders' meta from `COPIED_META_KEYS` (`language_locale`, `shippingType`, `is_guest`, `isBillingSameAsShipping`), which are storefront-written keys. It drops every other key. The original becomes `split`, which is excluded from reports, and lists its new orders in `meta.split_into_order_ids`. It keeps its lines, totals and `placed_at`. The new orders get `placed_at` at the split and `meta.split_from_order_id`, and can be split again. `GetOrderSplitGates` does not block test orders.
  - An order points to its cart through `cart_id`, and a cart can have several orders (`Cart::orders()`). An order's `traffic_source` holds the visit id, also on split orders.
- **Models.**
  - `HasModelExtending::observe()` registers an observer on the model-manifest replacement when one exists. lunar-frontend replaces `Order` but not `Cart`.
  - `Order::reportable()` skips statuses flagged `exclude_from_reports`. Core flags `test-order` and `split`, and hosts can flag more. Every dashboard order chart uses it, so a split counts as its new orders. `OrderStatsOverview` combines it with `whereNotNull('placed_at')` and sums `total - shipping_total` without currency conversion.
- **Filament v4.**
  - A page with a `filters` property passes them to its widgets as `pageFilters`.
  - A table can take array records through `records()`.
  - The dashboard charts are `ApexChartWidget`s.
- **Indexes.** `lunar_carts` has no `created_at` index. `lunar_orders.placed_at` is indexed.

## Goals / Non-Goals

**Goals:**

- One package holds every analytics rule and the data. A later analytics feature adds a folder, a config section, a permission and plugin entries, without new wiring.
- The parts most likely to differ between shops (referrer hosts, click ids, bot pattern, classifier) change through config, without code.
- No extra queries on ordinary page views: one session read. A page view that would start a visit adds one cart lookup and, when it records, one insert.
- Nothing is recorded or stamped in the admin panel or in staff sessions.

**Non-Goals:**

- Matching GA4 session counts. A visit here lives as long as the Laravel session.
- Server-side bot detection beyond a configurable user-agent pattern.
- Per-visit conversion. The funnel compares totals for the same period.
- Last-touch attribution on orders (user decision: the order keeps the cart's first-touch source).
- Changing which cart meta reaches the order (user decision: `FillOrderFromCart` stays as it is).

## Decisions

### New package `lunarphp/analytics`, one folder per feature

```
packages/analytics/
  composer.json
  config/analytics.php               merged as lunar.analytics, one section per feature
  database/migrations/…_create_traffic_visits_table.php
  database/factories/TrafficVisitFactory.php
  database/state/EnsureAnalyticsPermissions.php
  resources/lang/{en,hu,ro}/{plugin,traffic}.php
  resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php
  src/AnalyticsServiceProvider.php
  src/AnalyticsPlugin.php
  src/Traffic/
    Contracts/ClassifiesTrafficSource.php
    DataTransferObjects/TrafficSource.php
    Models/TrafficVisit.php
    Support/TrafficSourceClassifier.php
    Services/TrafficVisitRecorder.php
    Http/Middleware/RecordTrafficVisit.php
    Observers/StampCartWithTrafficSource.php
    Reports/TrafficReportQuery.php
    Filament/Pages/TrafficSourcesPage.php
    Filament/Widgets/…
```

- **Provider.** `AnalyticsServiceProvider`:
  - merges the config;
  - loads migrations and translations;
  - publishes `lunar.analytics.config` and `lunar.analytics.migrations`;
  - binds `ClassifiesTrafficSource` to `config('lunar.analytics.traffic.classifier')`;
  - registers the cart observer with `Cart::observe()`;
  - registers the permission state on `MigrationsEnded` and `NoPendingMigrations`, as `LoyaltyServiceProvider` does.
- **Plugin.** `AnalyticsPlugin` registers each feature's pages and widgets and the "Analytics" navigation group.
- **Permission state.** `EnsureAnalyticsPermissions` holds the list of `analytics:<feature>` permissions.

*Alternative:* `marketing` as the name. Rejected (user decision): it overlaps core's `Lunar\Marketing` namespace and the mailchimp and klaviyo packages.

*Alternative:* core plus admin, without a package (the previous plan). Rejected (user decision).

*Alternative:* one package per feature. Rejected: later measuring and reporting features share the plugin, navigation group, permission state and config file.

### Recording lives in the package; the storefront only attaches it

The middleware, classifier, recorder and cart observer are package code (user decision, as for loyalty's rules). lunar-frontend:
- adds the middleware to its storefront route groups through an alias in its `middlewares.php`, so a host can still swap the class;
- registers `AnalyticsPlugin`.

The middleware must sit inside `StartSession`. A route middleware on the storefront groups does, and `UPGRADE.md` says so for other hosts.

*Alternative:* recording in lunar-frontend, with only data and the report here (the feedback pattern). Rejected (user decision): future analytics features in the package could not reuse the classification.

*Alternative:* the middleware in lunar-frontend, calling the package recorder. Rejected: the request checks are not storefront-specific, and bot and prefetch rules would then change in a different repo than the classification.

### A visit lives in the Laravel session

The recorder keeps the current visit in the session under `analytics_traffic_visit` as `{visit_id, source, medium, campaign}`. The session cookie already exists, so recording needs no new cookie and no consent (user decision). The session lifecycle then behaves as follows:

- logging in keeps the session data, because `regenerate()` keeps its attributes;
- logging out invalidates it. The next page view has a same-host referrer, so it records no visit;
- an expired session gives a returning visitor a new visit, unless their restored cart already has a source (next section).

*Alternative:* a persistent visitor cookie. Rejected: it needs consent and would skew the split toward consenting visitors.

### A visitor with a sourced cart starts no visit

A visitor who already holds an active cart with `traffic_source` creates no new cart in this visit, and their order keeps the cart's source. Counting their arrival would add visits without carts or orders to the arrival's source and lower its order rate. For example, Facebook-cart customers returning through Google would make Google look worse (user decision). So, before inserting, the recorder looks up the visitor's cart:

- it takes the session's cart id (`lunar.cart_session.session_key`), otherwise the default guard user's latest `unmerged()->active()` cart, as `CartSessionManager` picks it;
- `Cart::active()` drops carts with a placed order, so the rule ends once the order is placed;
- it reads the cart directly. `CartSession::current()` would calculate the cart and can create a new one.

When that cart has `meta.traffic_source`, `record()` records nothing. If the session has no visit, it writes the cart's `traffic_source` as the session payload, so the session continues that visit:

- external returns and same-source campaign hits record nothing;
- carts created later in the session get the same source.

A cart without `traffic_source` (created before the release or with recording off) does not stop a visit.

*Alternative:* record the visit with a flag and leave flagged visits out of the report. Rejected: it needs an extra column and a filter in every report query, and carts created later in the session would point to a visit the report hides.

*Alternative:* skip only the first visit of a returning session. Rejected: a returning customer who reloads a tagged link or clicks an ad mid-session would still count.

*Alternative:* the previous plan, a new visit for every returning session. Rejected (user decision): returning customers with saved carts lower the order rate of the source they return through.

### Record after the response

`RecordTrafficVisit` calls `$next($request)` first. It then records only when every condition holds:

- the method is GET, so HEAD does not count;
- the status is 200;
- `Content-Type` starts with `text/html`;
- the user agent is non-empty and does not match `lunar.analytics.traffic.bot_user_agent_pattern`;
- neither `Sec-Purpose` nor `Purpose` contains `prefetch`;
- recording is enabled;
- `TrafficVisitRecorder::shouldTrack()` is true.

The response checks drop redirects, 404s, images, XML and text without a route list. `wire:navigate` fetches are 200 HTML GETs with a same-host referrer, so they never start a visit without campaign parameters.

*Alternative:* a JS beacon. Rejected: it adds a public POST endpoint, and ad blockers drop it.

### The admin panel and staff sessions are excluded in one place

`TrafficVisitRecorder::shouldTrack()` is false when either of these holds:

- `Filament::isServing()`;
- the panel guard (`LunarPanel::getPanel()->getAuthGuard()`) has a logged-in user.

`record()` and `current()` both check it. As a result:

- admin routes never carry the middleware, and even if a host attached it there, nothing would be recorded;
- carts created in admin requests, such as `SplitOrder`'s carts, get no source, because the observer gets `null` from `current()`;
- a staff member browsing the storefront in the same browser records nothing.

*Alternative:* excluding only admin routes. Rejected: admin actions that create carts run without the middleware but still see the session's storefront visit.

### Classification is configurable

`config/analytics.php`:

```php
'traffic' => [
    'enabled' => env('ANALYTICS_TRAFFIC_ENABLED', true),
    'classifier' => TrafficSourceClassifier::class,
    'referrer_hosts' => [
        'google.*' => ['source' => 'google', 'medium' => 'organic'],
        'bing.com' => ['source' => 'bing', 'medium' => 'organic'],
        // yahoo.com, duckduckgo.com: organic
        // facebook.com, fb.com, fb.me, instagram.com, tiktok.com, youtube.com, youtu.be: social
    ],
    'click_ids' => [
        'gclid' => ['source' => 'google', 'medium' => 'cpc', 'before_referrer' => true],
        // gbraid, wbraid: same as gclid
        'fbclid' => ['source' => 'facebook', 'medium' => 'social', 'before_referrer' => false],
    ],
    'bot_user_agent_pattern' => '/(?<!cu)bot|crawl|spider|slurp|facebookexternalhit|facebookcatalog|preview|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python-requests|go-http-client|java\//i',
],
```

- **Precedence.** `utm_source`, then click ids with `before_referrer`, then `referrer_hosts`, then the other click ids, then another external host (host / referral), then direct / none. `utm_source` and every configured click id count as campaign parameters for the new-visit rule.
- **Host matching.** A host entry matches that host and its subdomains. A trailing `.*` matches any one- or two-label suffix (`google.com`, `google.co.uk`). The first matching entry wins.
- **Same-host referrer.** It gives `null` (no visit) unless a campaign parameter is present, ignoring `www.`.
- **Values.** They are trimmed. Source and medium are lowercased. Every value is cut to 255 characters. An unparsable referrer counts as none.
- **Classifier.** `TrafficSourceClassifier` implements `ClassifiesTrafficSource::classify(Request): ?TrafficSource` and `hasCampaignParameters(Request): bool`. A shop replaces it through `traffic.classifier`.
- **Not configurable.** The recorder's session rules and the report do not vary by shop. They can still be rebound in the container.

*Alternative:* hard-coded host and click-id constants. Rejected: price comparison sites, TikTok or Microsoft Ads click ids and monitoring bots differ per shop (user decision: configurable where change is likely).

### `TrafficVisitRecorder` owns the session and the synchronous insert

- `record(Request)`:
  - returns when `shouldTrack()` is false;
  - classifies the request and returns on `null`;
  - returns when the session has a visit and either the request has no campaign parameter, or its source/medium/campaign equal the current visit's;
  - when the visitor's cart has a `traffic_source` (previous section), writes it as the session payload if the session has no visit, and returns;
  - otherwise creates a `TrafficVisit` with `landing_path` from `'/'.ltrim($request->path(), '/')` and writes the session payload.
- `current(): ?array` returns the payload, or `null` when recording is disabled, `shouldTrack()` is false or no session is available.

The insert is synchronous so that the id is in the session before the next request creates a cart.

*Alternative:* a queued insert with a generated id. Rejected: a cart could reference a visit that never landed.

*Alternative:* daily counters. Rejected: carts need a visit id and campaign.

### Visits table and model

`Lunar\Analytics\Traffic\Models\TrafficVisit` extends `Lunar\Base\BaseModel` (table prefix) and uses `HasFactory` with `newFactory()`. It sets `UPDATED_AT = null`, fills the six data columns and casts `created_at`. The migration extends `Lunar\Base\Migration` and creates `$this->prefix.'traffic_visits'`:

| Column | Type |
| --- | --- |
| `id` | id |
| `source` | string 255 |
| `medium` | nullable string 255 |
| `campaign` | nullable string 255 |
| `referrer_host` | nullable string 255 |
| `landing_path` | string 255 |
| `created_at` | timestamp, indexed |

There is also a composite index on `source` and `medium`. The table has no foreign keys, because carts and orders hold a snapshot.

### Cart stamping with `Cart::observe()`

`Observers/StampCartWithTrafficSource::creating(Cart $cart)` merges `traffic_source` (`visit_id`, `source`, `medium`, `campaign`) into the cart meta when `current()` returns a visit. It keeps the other meta and does not save. `Cart::observe()` follows a model-manifest replacement, so shops that replace `Cart` are covered.

- The observer handles only `creating`, so later visits never overwrite the source (user decision: the first stays).
- Lunar's login merge keeps the surviving cart's own source.
- Referrer host and landing path stay on the visit only (user decision).

*Alternative:* stamp in the storefront's add-to-cart action. Rejected: carts are also created by gift selection, wallet pay and other `CartSession` calls.

### Orders: `FillOrderFromCart` stays, split copies one more key

- `FillOrderFromCart` is unchanged (user decision). The full cart meta copy is upstream behavior that order processing depends on, and `traffic_source` rides along. `FillOrderFromCartTest` pins the key, including a rebuilt draft.
- `SplitOrder::COPIED_META_KEYS` gains `traffic_source`. The copy happens inside the split transaction, like the other storefront-written keys in that list.
- No placement source is stored (user decision: copy only).

*Alternative:* copy only listed keys in `FillOrderFromCart`. Rejected (user decision): every key used by checkout, payment, ERP, invoicing, emails, loyalty and hosts would have to be listed. That is high risk and outside LFP-925.

*Alternative:* `traffic_*` columns on `lunar_carts` and `lunar_orders`. Rejected (user decision): it means migrations on core tables and changes to `FillOrderFromCart` and `SplitOrder`, and analytics data would leak into core.

*Alternative:* copy the key in an `OrdersSplit` listener in this package. Rejected: the event fires after the commit, which leaves a window where new orders lack the source, and it needs an extra query per order.

### The report page is a second Filament dashboard

`Traffic/Filament/Pages/TrafficSourcesPage` extends `Filament\Pages\Dashboard`:

- `$routePath = 'analytics/traffic-sources'`;
- the plugin's "Analytics" navigation group;
- `HasFiltersForm` with `from` and `to` date pickers, defaulting to today minus 29 days and today;
- `canAccess()` returns whether the staff user `can('analytics:traffic-sources')`, the same check `AiAssistantChatPage` uses. Admins pass through `Gate::after`.

The widgets live in `Traffic/Filament/Widgets/`. Each uses `InteractsWithPageFilters` and sets no polling interval:

- `TrafficStatsOverview`: visits, carts with a source, orders, order value and order rate;
- `VisitsBySourceChart` (`ApexChartWidget`, donut): the top 8 sources plus "other";
- `VisitsPerDayChart` (`ApexChartWidget`, line): 0 for days without visits;
- `TrafficSourceFunnelTable` (`TableWidget` with `records()`): source, medium, visits, carts, orders, order value and rate, with the unknown row last;
- `LandingPagesTable` (`TableWidget` with `records()`): most visited first, paginated.

*Alternatives rejected:*
- a Filament resource: the report shows aggregates, with no records to edit;
- widgets on the main dashboard: every staff member sees it;
- Looker Studio: user decision.

### `TrafficReportQuery` computes every figure

`Traffic/Reports/TrafficReportQuery` takes `CarbonInterface $from, $to` and widens them to whole days. It computes:

- `visitsBySource()`, `visitsPerDay()` and `landingPages()`: `TrafficVisit` by `created_at`, without the visits left out because of their orders (next section);
- `cartsBySource()`: carts by `created_at` whose `meta->traffic_source->source` is not null, grouped by source and medium. Carts without a source are left out, because they include admin-created carts, for example from splits. So are the carts left out because of their orders (next section);
- `ordersBySource()`: placed orders by `placed_at`, grouped the same way, with count and `sum(total - shipping_total)`. It takes the `Order::reportable()` orders without `meta.split_from_order_id`, plus the split originals that count (next section), so a split is one order with the original's date and value;
- `funnel()`: merges these by source and medium. Orders without a source form the unknown row, with no visits, carts or rate.

Laravel JSON paths compile on MySQL and SQLite.

*Alternative:* join to visits through `visit_id`. Rejected: JSON joins over carts, and orders from older carts would drop out.

### Carts and visits of orders that do not count are left out

Visits and carts whose orders all have a status excluded from reports, for example `test-order`, drop out of every figure (user decision). The rules:

- An order counts when `Order::reportable()` would keep its status. `placed_at` does not matter, so an unplaced order awaiting payment keeps its cart and visit.
- A `split` original counts when one of the orders in its `split_into_order_ids` counts, following new orders that were split again. In the order figures it counts once in place of its new orders, with its own `placed_at` and order value (user decision: a split order stays one order, unlike on the dashboard). Its visit and cart count once too, because the new orders' carts are created in the admin panel and have no source.
- A cart is left out when it has orders and none of them counts. It is linked to them through `cart_id`.
- A visit is left out when orders carry its id in `traffic_source` and none of them counts.

`TrafficReportQuery` finds the left-out ids and the split originals that count once per instance:

1. It loads the orders with an excluded status created or placed from the period start, with `id`, `cart_id`, `status`, `placed_at` and `meta`. An order is created after its cart and visit, so this covers every order of the period's carts and visits, and every split original placed in the period.
2. For the `split` originals among them, it loads the statuses of their new orders, one query per split level. An original counts when one of them counts.
3. The orders that do not count give the candidate cart ids (`cart_id`) and visit ids (`meta.traffic_source.visit_id`). The split originals that count take their cart id and visit id out of the candidates.
4. One query each drops the candidates that also have a reportable order: by `cart_id` for carts, and by `meta->traffic_source->visit_id` for visits. Split new orders carry the visit id, so they keep their original's visit.
5. The visit queries and `cartsBySource()` add `whereNotIn('id', …)` for the ids that remain. `ordersBySource()` adds the split originals that count.

The current status decides, so marking an order as a test later also changes past periods.

*Alternative:* a `NOT EXISTS` subquery on `lunar_orders.meta->traffic_source->visit_id` for every visit. Rejected: the JSON path has no index, and it would scan orders for every visit of the period. Orders with an excluded status are few.

*Alternative:* flag the visit and the cart when an order's status changes. Rejected: it needs a column, a status listener and a backfill, and a status can change back.

*Alternative:* treat `split` like the other excluded statuses. Rejected: every split order would drop out of the report with its visit and cart.

*Alternative:* ignore `split` originals. Rejected: a split test order's cart would still count.

*Alternative:* count a split's new orders, like the dashboard (the previous plan). Rejected (user decision): a split into three orders would count three times, raise the order rate and move the order to the split date.

*Alternative:* one order per split, valued at the sum of the new orders that count. Rejected: it groups new orders by their original, and the value differs from the original's only when a new order is edited or marked as a test later.

### Permission `analytics:traffic-sources`

`EnsureAnalyticsPermissions::run()` creates the permission for the panel guard when the permissions table exists, as `EnsureLoyaltyPermissions` does. The label and description go in `resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php`, as in the review package. Without them, Lunar shows the handle. No role is created. Admins grant the permission to the development and marketing staff.

*Alternative:* reuse `sales:manage-orders`. Rejected: marketing staff would need order management rights.

## Risks / Trade-offs

- [Edge or full-page caching serves HTML without reaching Laravel] → Cached hits are not recorded. lunar-frontend renders pages dynamically today. A host that adds HTML caching loses visits, and `UPGRADE.md` says so.
- [Bots with browser user agents are counted] → Only 200 HTML GETs count, and shops can extend the pattern. A crawler-detection library can replace it later without a spec change.
- [Table growth: one row per session] → Rows are small and indexed on `created_at`. Pruning can come later.
- [Report queries group JSON paths on `lunar_carts`, which has no `created_at` index] → The period limits the scan, and the widgets do not poll. An index can come later.
- [Multi-currency shops] → Order value is summed without conversion, like the dashboard.
- [The traffic report and the dashboard count splits differently] → The dashboard counts the new orders on the split date. The traffic report counts the original once, on its placement date.
- [A split's new orders are edited or partly marked as a test] → The report keeps the original's value, and counts it while one new order counts.
- [Marking an order as a test later changes past figures] → A period's visits and carts drop when one of their orders is marked later, even after the period. This is intended: the report follows the current status, like its order figures.
- [Orders with an excluded status accumulate (test orders, split originals)] → The lookup reads only those created from the period start. If they grow large, it can move to a subquery without a spec change.
- [Hosts with `lunar.cart_session.auto_create = true` create the cart on the landing request, before the visit is recorded] → That cart and its order have no source. The order counts as unknown. lunar-frontend's default is `false`.
- [Two simultaneous first requests in a new session] → They can record two visits. Low impact, and not worth a lock.
- [A customer who logs in only after landing] → The visit is recorded before their cart is known, and it counts. Remember-me logins are known on the landing request.
- [A returning customer orders and then starts a new cart in the same session] → The new cart gets the earlier visit's source, because the session continued that visit.
- [Campaign visits are fewer than ad clicks] → Clicks from visitors who already hold a sourced cart are not visits. This is intended: the report counts arrivals that can start a cart.
- [Referrer stripped by in-app browsers] → Such visits count as direct unless tagged. Click ids recover the common ad cases.
- [Hosts with `lunar.database.disable_migrations`] → They copy the migration, as for other packages.

## Migration Plan

1. Release lunar-minic with the new package, the `SplitOrder` key and the tests.
2. Hosts run `php artisan migrate`, which creates `lunar_traffic_visits` and `analytics:traffic-sources`. An admin grants the permission to the development and marketing staff.
3. Release the lunar-frontend change. It requires this version, registers `AnalyticsPlugin` and attaches the middleware, and recording starts.

Rollback: set `ANALYTICS_TRAFFIC_ENABLED=false` to stop recording and stamping at once. Reverting the release leaves the table, the permission and the meta keys, which nothing else reads. The migration's `down()` drops the table.
