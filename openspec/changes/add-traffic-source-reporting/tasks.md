# Tasks

## 1. Package scaffold

- [x] 1.1 Create `packages/analytics` following `packages/feedback`:
  - **`composer.json`.** `lunarphp/analytics`, PSR-4 `Lunar\Analytics\` → `src`, `extra.lunar.name` "Analytics", provider `Lunar\Analytics\AnalyticsServiceProvider`, and a requirement on `lunarphp/core` and `lunarphp/admin` at `self.version`.
  - **Provider.** `src/AnalyticsServiceProvider.php`:
    - merges `config/analytics.php` as `lunar.analytics`;
    - loads migrations (unless `lunar.database.disable_migrations`) and translations as `lunarpanel.analytics`;
    - publishes `lunar.analytics.config` and `lunar.analytics.migrations`.
  - **Plugin.** `src/AnalyticsPlugin.php` (id `lunar-analytics`), which registers the "Analytics" navigation group. Its pages and widgets come in group 7.
  - **Config.** `config/analytics.php` with the `traffic` section from design.md and comment blocks like `config/feedback.php`.
  - **Translations.** `resources/lang/{en,hu,ro}/plugin.php` with the navigation group label.
  - **Root wiring:**
    - in `composer.json`, add the `autoload`, `autoload-dev` (`Lunar\Tests\Analytics\` → `tests/analytics`), `extra.lunar.name`, `extra.laravel.providers` and `replace` (`lunarphp/analytics: self.version`) entries;
    - in `phpunit.xml`, add an `analytics` test suite;
    - add `tests/analytics/TestCase.php`, like `tests/feedback/TestCase.php`.
  - **Test.** `tests/analytics/Feature/AnalyticsServiceProviderTest.php`: the config is merged, `lunar.analytics.traffic.enabled` is `false` (recording is off by default; the recording, stamping and split tests switch it on in their setup), and the plugin id is `lunar-analytics`.
  - **Docs:**
    - start `docs/changelog/<implementation date>.md` with an LFP-925 entry for the new Analytics package;
    - in `UPGRADE.md` `## ⬆️ Upcoming`, open `### Analytics Package` with the package, `config/lunar/analytics.php` and `ANALYTICS_TRAFFIC_ENABLED`;
    - in `docs/system/CODE_MAP.md`, add `packages/analytics` to the repository structure and the service provider list.

  Verify with `composer dump-autoload` and `vendor/bin/pest --testsuite=analytics`.

## 2. Visits table and model

- [x] 2.1 Add the table, model and factory:
  - **Migration.** `packages/analytics/database/migrations/<implementation date>_create_traffic_visits_table.php`, extending `Lunar\Base\Migration`, with the columns and indexes from design.md. `down()` calls `dropIfExists`.
  - **Model.** `src/Traffic/Models/TrafficVisit.php`. It extends `BaseModel` and uses `HasFactory` with `newFactory()`. It sets `UPDATED_AT = null`, `$fillable` for the six data columns, a `created_at` cast and PHPDoc `@property` lines.
  - **Factory.** `database/factories/TrafficVisitFactory.php`. Its default is a `direct` / `none` visit on `/`.
  - **Test.** `tests/analytics/Feature/Traffic/TrafficVisitTest.php`:
    - creating a visit stores every column;
    - there is no `updated_at`;
    - the table is `lunar_traffic_visits`;
    - `Schema::getColumnListing()` returns exactly the seven columns, which covers "no personal data".
  - **Docs.** Add `lunar_traffic_visits` to `docs/database/schema.dbml`. Add `php artisan migrate` to the `UPGRADE.md` host responsibilities.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/TrafficVisitTest.php`.

## 3. Source classification

- [x] 3.1 Add the classification:
  - `src/Traffic/Contracts/ClassifiesTrafficSource.php`, with `classify(Request): ?TrafficSource` and `hasCampaignParameters(Request): bool`.
  - `src/Traffic/DataTransferObjects/TrafficSource.php`, a readonly class with `source`, `?medium`, `?campaign` and `?referrerHost`.
  - `src/Traffic/Support/TrafficSourceClassifier.php`. It implements the precedence, host matching (`.*` suffix), same-host rule, trimming, lowercasing and 255-character cut from design.md, reading `referrer_hosts` and `click_ids` from config.
  - Bind the contract to `config('lunar.analytics.traffic.classifier')` in the provider.
  - Document every method.

  Add `tests/analytics/Unit/Traffic/TrafficSourceClassifierTest.php`, with datasets for every classification scenario in `specs/analytics/traffic-recording/spec.md`:
  - google.com and google.hu → organic;
  - lm.facebook.com → social;
  - arukereso.hu → referral, with `www.` stripped;
  - the UTM newsletter link (case);
  - the UTM tag overriding the Facebook referrer;
  - gclid with a Google referrer → cpc;
  - fbclid without referrer → facebook;
  - fbclid with l.instagram.com → instagram;
  - `utm_medium` without `utm_source` ignored;
  - direct;
  - a same-host referrer → `null`, but with `utm_source` → classified;
  - a 300-character campaign cut to 255;
  - config overrides: `compari.ro` → compari / comparison, `msclkid` with `before_referrer` → bing / cpc, and a custom classifier class resolved from config.

  Verify with `vendor/bin/pest tests/analytics/Unit/Traffic/TrafficSourceClassifierTest.php`.

## 4. Recording visits

- [x] 4.1 Add `src/Traffic/Services/TrafficVisitRecorder.php` with:
  - `SESSION_KEY = 'analytics_traffic_visit'`;
  - `shouldTrack()`, `record(Request)` and `current(): ?array`, as in design.md;
  - the cart lookup from design.md: the session's cart id, otherwise the logged-in user's latest active cart, without `CartSession::current()`;
  - PHPDoc with the array shape.

  Add `tests/analytics/Feature/Traffic/TrafficVisitRecorderTest.php`:
  - the first view records one visit;
  - repeat views record none;
  - gclid mid-session records `google` / `cpc` and makes it current;
  - reloading the same UTM URL records none;
  - an external bank referrer mid-session records none;
  - a same-host referrer in a new session records none;
  - a referrer with a path and query stores only the host and `/sale`;
  - a new session from Google for a logged-in user whose active cart has a Facebook `traffic_source` records no visit, the session then holds the cart's `traffic_source`, and a later bank referrer records nothing;
  - with that cart in the session, `?gclid` records nothing; once the cart's order is placed, it records `google` / `cpc`;
  - an active cart without `traffic_source` does not stop a `google` / `organic` visit;
  - when the session's cart has a placed order, the lookup creates no cart;
  - with `Filament::isServing()` true, or a user logged in on the `staff` guard, `record()` stores nothing and `current()` is `null` even with a visit in the session;
  - `current()` is `null` when disabled.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/TrafficVisitRecorderTest.php`.

- [x] 4.2 Add `src/Traffic/Http/Middleware/RecordTrafficVisit.php`. It calls `$next` first, then applies the checks from design.md, reading the bot pattern from config, and calls `record()`.

  Add `tests/analytics/Feature/Traffic/RecordTrafficVisitTest.php`, with test routes that use `StartSession` and the middleware:
  - an HTML 200 from Google records `google` / `organic`;
  - a Googlebot user agent and an empty user agent record nothing;
  - a pattern extended through config drops a custom user agent;
  - a 404, a 302 and an `image/jpeg` response record nothing;
  - `Sec-Purpose: prefetch` and a HEAD request record nothing;
  - POST records nothing;
  - disabled config records nothing;
  - a request with a consent cookie that denies analytics (`cf_consent`) still records, and the response queues no cookie besides the session and XSRF cookies;
  - a request from a logged-in `staff` user records nothing;
  - a request from Google by a logged-in customer whose active cart has a `traffic_source` records nothing.

  In `UPGRADE.md`, note that the middleware must be attached inside `StartSession`, and that edge-cached HTML is not recorded.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/RecordTrafficVisitTest.php`.

## 5. Cart and order sources

- [x] 5.1 Add `src/Traffic/Observers/StampCartWithTrafficSource.php` (`creating(Cart $cart)`, as in design.md), and register it in the provider with `Cart::observe()`.

  Add `tests/analytics/Feature/Traffic/StampCartWithTrafficSourceTest.php`:
  - with a Facebook visit in the session, `Cart::factory()->create()` stores `meta.traffic_source` with `visit_id`, `source`, `medium` and `campaign`, and no landing path or referrer host;
  - meta passed to `create()` is kept;
  - after the session's visit changes to Google, saving the cart leaves the Facebook source;
  - with no visit, recording disabled, `Filament::isServing()` true or a `staff` user logged in, the cart has no `traffic_source`.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/StampCartWithTrafficSourceTest.php`.

- [x] 5.2 Pin the order copy without changing `FillOrderFromCart`. Extend `tests/core/Unit/Pipelines/Order/Creation/FillOrderFromCartTest.php`:
  - a cart with `meta.traffic_source` gives an order with the identical value;
  - running the stage again on the draft keeps it;
  - a cart without the key gives an order without it.

  Verify with `vendor/bin/pest tests/core/Unit/Pipelines/Order/Creation/FillOrderFromCartTest.php`.

- [x] 5.3 Add `traffic_source` to `SplitOrder::COPIED_META_KEYS`. Extend `tests/core/Unit/Actions/Orders/SplitOrderTest.php`:
  - an original with a Facebook `traffic_source` gives new orders with the same value;
  - an original without one gives none;
  - with the analytics package loaded and a storefront visit in the session, the split's carts get no `traffic_source`, because the panel is serving the request.

  Verify with `vendor/bin/pest tests/core/Unit/Actions/Orders/SplitOrderTest.php tests/loyalty/Unit/OrderSplitTest.php tests/ERP/Unit/Listeners/SendSplitOrdersToERPTest.php`.

- [x] 5.4 Document the meta key:
  - In `docs/system/PROJECT_SPECIFICATION.md` § "Payment and checkout meta", add `traffic_source` (`visit_id`, `source`, `medium`, `campaign`). Record that the analytics package writes it on cart creation, that `FillOrderFromCart` copies it with the whole cart meta and that splits keep it.
  - In `docs/design/order_processing.md`, note both copies.
  - In `UPGRADE.md`, under `### Core Package`, note that split orders keep `traffic_source`.

  Verify that both docs mention `traffic_source`.

## 6. Permission

- [x] 6.1 Add the permission:
  - Add `packages/analytics/database/state/EnsureAnalyticsPermissions.php` (`prepare()` and `run()`, creating `analytics:traffic-sources`). Register it on `MigrationsEnded` and `NoPendingMigrations` in the provider, as `LoyaltyServiceProvider` does.
  - Add the label and description to `resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php`, as in the review package.
  - Add `tests/analytics/Feature/Traffic/AnalyticsPermissionsTest.php`:
    - `run()` creates the permission on the `staff` guard;
    - a second run leaves one row;
    - the label resolves in en, hu and ro.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/AnalyticsPermissionsTest.php`.

## 7. Report

- [x] 7.1 Add `src/Traffic/Reports/TrafficReportQuery.php` with the methods from design.md and array-shape PHPDoc.

  Add `tests/analytics/Feature/Traffic/TrafficReportQueryTest.php` for the scenarios in `specs/analytics/traffic-report/spec.md`:
  - the 60 / 30 / 10 split;
  - a day without visits gives 0;
  - with a 1–7 October period, rows on 30 September and 8 October are excluded;
  - the Facebook row shows 30 visits, 6 carts, 3 orders and 10%;
  - the order value is `total - shipping_total`;
  - a `test-order` order and an unplaced order are not counted;
  - a split original is not counted, and its new orders are;
  - orders without a source form the unknown row, and carts without a source are not counted;
  - landing pages are ordered by visits.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/TrafficReportQueryTest.php`.

- [x] 7.2 Add the page and widgets:
  - Add `src/Traffic/Filament/Pages/TrafficSourcesPage.php` and the five widgets in `src/Traffic/Filament/Widgets/`, as in design.md. Register them in `AnalyticsPlugin::register()`.
  - Add `resources/lang/{en,hu,ro}/traffic.php` (page title, navigation label, filters, widget headings, columns, "unknown").
  - Add `tests/analytics/Feature/Traffic/TrafficSourcesPageTest.php`, with the plugin on the `lunar` panel and the staff setup as in `AiAssistantAccessTest`:
    - an admin and a staff member with `analytics:traffic-sources` can access the page, and a GET returns 200;
    - a staff member without the permission gets `canAccess()` false, a 403 and no navigation item;
    - the default filters are today minus 29 days and today;
    - each widget renders seeded figures through `Livewire::test(..., ['pageFilters' => [...]])`;
    - Lunar's `Dashboard::getWidgets()` contains none of the traffic widgets.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/TrafficSourcesPageTest.php`.

- [x] 7.3 Document the report and the package:
  - In `docs/system/CODE_MAP.md`:
    - add a `### Analytics (packages/analytics)` section to the Domain Map (Traffic: model, classifier and config, recorder, middleware, observer, report query, page, widgets, permission);
    - add a Quick Navigation row "Traffic sources".
  - In `docs/system/PROJECT_SPECIFICATION.md`, add the package under "Added or Extended Packages".
  - Complete the `UPGRADE.md` `### Analytics Package` entry:
    - new main features;
    - host responsibilities: register `AnalyticsPlugin`, attach `RecordTrafficVisit` to the storefront routes inside the session (lunar-frontend does both), and grant `analytics:traffic-sources` to the development and marketing staff;
    - the configurable keys.

  Verify that `CODE_MAP.md` mentions `TrafficVisitRecorder` and `TrafficSourcesPage`.

- [x] 7.4 Leave out carts and visits of orders that do not count:
  - In `TrafficReportQuery`, add the lookup from design.md ("Carts and visits of orders that do not count are left out"), run once per instance. Apply it to `visitsBySource()`, `visitsPerDay()`, the landing page query and `cartsBySource()`. In `ordersBySource()`, leave out orders with `meta.split_from_order_id` and add the split originals that count. Update the class PHPDoc.
  - Extend `tests/analytics/Feature/Traffic/TrafficReportQueryTest.php` for the scenarios of "Carts and visits of orders that do not count are left out" and "An order counts by its current status" in `specs/analytics/traffic-report/spec.md`:
    - a Google visit on `/sale` whose cart's order is moved to `test-order` is missing from visits by source, visits per day, landing pages, the funnel and the totals, and its cart is not counted;
    - a visit and a cart without an order are counted;
    - an unplaced `awaiting-payment` order keeps its visit and cart;
    - one visit with a `test-order` cart and a counted cart: the visit and only the counted cart count;
    - replace the 7.1 test "a split original is not counted and its new orders are": a split into two orders that count gives one visit, one cart and one order with the original's value; with all new orders in `test-order`, the order, the visit and the cart are left out;
    - 10 visits, 7 carts and 5 orders, one split into two and one into three, give 5 orders and an order rate of 50%;
    - an order placed on 30 September and split on 2 October counts in September and not in 1–7 October;
    - a new order that is split again still counts its original once;
    - an order created after the period and moved to `test-order` leaves out the period's visit and cart;
    - a status flagged `exclude_from_reports` through config leaves out its visit and cart.
  - Docs: in the report bullet of `UPGRADE.md` `### Analytics Package` and in the report entry of `docs/changelog/2026-11-05.md`, add that visits and carts whose orders do not count are left out, and that a split order counts once, as its original on its placement date, unlike the dashboard.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/TrafficReportQueryTest.php`.

## 8. Integration check

- [x] 8.1 Run `composer test` (Pest and PHPStan) and `vendor/bin/pint --test`. All must pass, with no new failures or baseline entries. Then check that `openspec validate add-traffic-source-reporting --strict` passes.

## 9. Visits over time

- [x] 9.1 Group the visits chart by period length:
  - In `TrafficReportQuery`, add `visitsOverTime()` as in design.md ("Visits over time are grouped by period length"). It is built from `visitsPerDay()` without another query and has array-shape PHPDoc.
  - Rename `VisitsPerDayChart` to `VisitsOverTimeChart` (chart id `trafficVisitsOverTimeChart`). It plots `visitsOverTime()` with the labels and heading from design.md. Update `TrafficSourcesPage::trafficWidgets()`.
  - In `resources/lang/{en,hu,ro}/traffic.php`, replace `widgets.visits_per_day` with `widgets.visits_over_time`: `heading.day`, `heading.week`, `heading.month` and `series` ("Visits per day / week / month", "Napi / Heti / Havi látogatások", "Vizite pe zi / săptămână / lună").
  - Extend `tests/analytics/Feature/Traffic/TrafficReportQueryTest.php` for the scenarios of "Visits over time" in `specs/analytics/traffic-report/spec.md`:
    - 1–7 October gives 7 days, with 0 for a day without visits;
    - 4 August – 8 November gives 14 weeks. The first is keyed `2026-08-04` and holds the visits of 4–9 August, and the second is keyed `2026-08-10`;
    - 1 January – 31 December gives 12 months;
    - 31 days give days and 32 days give weeks. 182 days give weeks and 183 days give months;
    - a visit left out because of its `test-order` order is not counted.
  - In `tests/analytics/Feature/Traffic/TrafficSourcesPageTest.php`, render `VisitsOverTimeChart` for 1–7 October and for 4 August – 8 November, and check the heading.
  - Docs:
    - in `docs/system/CODE_MAP.md`, rename the widget;
    - replace "visits per day" with "visits over time (per day, week or month)" in `UPGRADE.md`, `docs/changelog/2026-11-05.md` and `docs/system/PROJECT_SPECIFICATION.md`.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/TrafficReportQueryTest.php tests/analytics/Feature/Traffic/TrafficSourcesPageTest.php`.

## 10. Source names and order badges

- [x] 10.1 Add `src/Traffic/Support/TrafficSourceLabel.php` as in design.md ("Order list and order page show the source as a badge"). It gives the source name, medium name, label, color and campaign for a `traffic_source` array, and the names for a source and medium. Add the names to `resources/lang/{en,hu,ro}/traffic.php`:
  - `direct`: "Direct" / "Közvetlen" / "Direct";
  - `organic`: "Organic search" / "Organikus keresés" / "Căutare organică";
  - `cpc`: "Paid ad" / "Fizetett hirdetés" / "Reclamă plătită";
  - `social`: "Social" / "Közösségi média" / "Rețele sociale";
  - `referral`: "Referral" / "Hivatkozó oldal" / "Recomandare";
  - `email`: "Email" / "E-mail" / "Email".

  Add `tests/analytics/Unit/Traffic/TrafficSourceLabelTest.php`, covering "The order badge names the source in words" in `specs/analytics/traffic-attribution/spec.md`, with a dataset:
  - `google` / `organic` → "Google · Organic search", success;
  - `google` / `cpc` → "Google · Paid ad", warning;
  - `facebook` / `social` → "Facebook · Social", info, and `tiktok` / `social` → "TikTok · Social";
  - `mailchimp` / `email` with campaign `Black-Friday` → "Mailchimp · Email", primary, campaign `Black-Friday`;
  - `arukereso.hu` / `referral` → "arukereso.hu · Referral", gray;
  - `direct` / `none` → "Direct", gray;
  - `fb_ads` / `paid_social` → "Fb Ads · Paid Social", gray;
  - `null`, and an array without a source → "Unknown", gray;
  - the hu and ro names.

  Verify with `vendor/bin/pest tests/analytics/Unit/Traffic/TrafficSourceLabelTest.php`.

- [x] 10.2 Add the order extensions:
  - Add `src/Traffic/Filament/Extensions/OrderResourceExtension.php` and `ManageOrderExtension.php` as in design.md. The column and the entries are visible only when `TrafficSourcesPage::canAccess()` is true, and the raw key is hidden for everyone. Register both in `AnalyticsPlugin::register()` with `LunarPanel::extensions()`.
  - Add `order.traffic_source` ("Traffic source" / "Forgalmi forrás" / "Sursă de trafic") and `order.campaign` ("Campaign" / "Kampány" / "Campanie") to `resources/lang/{en,hu,ro}/traffic.php`.
  - In `resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php`, extend the permission description to cover the traffic source on orders.
  - Add `tests/analytics/Feature/Traffic/OrderTrafficSourceTest.php` for the scenarios of "Authorized staff see an order's traffic source" and "The order badge names the source in words" in `specs/analytics/traffic-attribution/spec.md`. Use the plugin on the `lunar` panel and the staff setup of `TrafficSourcesPageTest`:
    - for an admin, and for a staff member with `sales:manage-orders` and `analytics:traffic-sources`, `ListOrders` shows the toggleable column with "Google · Paid ad" and "Unknown";
    - `ManageOrder` shows "Mailchimp · Email" and `Black-Friday` in the summary, and "Additional info" does not list `traffic_source`;
    - a staff member with only `sales:manage-orders` sees neither the column, nor the entries, nor the raw key;
    - without the plugin, the `OrderResource` table has no `traffic_source` column.
  - Docs:
    - in `UPGRADE.md` `### Analytics Package`, add an "Order pages" bullet to the new main features, and in host responsibilities, note that the permission also shows the order badges;
    - add the badges to the report entry in `docs/changelog/2026-11-05.md`;
    - in `docs/system/CODE_MAP.md`, add `TrafficSourceLabel` and the two extensions to the Analytics section;
    - in `docs/system/PROJECT_SPECIFICATION.md`, mention the badges in the package entry.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/OrderTrafficSourceTest.php`.

- [x] 10.3 Name sources and mediums in the report with `TrafficSourceLabel`:
  - `VisitsBySourceChart` labels its slices with the badge label, in place of `InteractsWithTrafficReport::sourceLabel()`;
  - `TrafficSourceFunnelTable` formats the source and medium columns with their names. The unknown row stays "Unknown".

  Extend `tests/analytics/Feature/Traffic/TrafficSourcesPageTest.php` for the scenario of "Sources and mediums are named in words" in `specs/analytics/traffic-report/spec.md`: "Google · Paid ad", "Facebook · Social" and "Direct" in the donut, and source "Google" with medium "Paid ad" in the table.

  Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/TrafficSourcesPageTest.php`.

## 11. Integration check

- [x] 11.1 Run `composer test` (Pest and PHPStan) and `vendor/bin/pint --test`. All must pass, with no new failures or baseline entries. Then check that `openspec validate add-traffic-source-reporting --strict` passes.

## Workflow follow-up

- Release lunar-minic before the lunar-frontend change `track-visitor-traffic-sources` is implemented. That change bumps the dependency and holds the end-to-end browser check.
- After review, archive the change. That creates `openspec/specs/analytics/traffic-recording`, `traffic-attribution` and `traffic-report`.
- Visit pruning, a carts `created_at` index and a crawler-detection library are follow-up tickets if needed.
