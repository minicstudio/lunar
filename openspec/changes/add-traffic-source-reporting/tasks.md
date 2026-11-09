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
  - **Test.** `tests/analytics/Feature/AnalyticsServiceProviderTest.php`: the config is merged, `lunar.analytics.traffic.enabled` is `true`, and the plugin id is `lunar-analytics`.
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

## 8. Integration check

- [ ] 8.1 Run `composer test` (Pest and PHPStan) and `vendor/bin/pint --test`. All must pass, with no new failures or baseline entries. Then check that `openspec validate add-traffic-source-reporting --strict` passes.

## Workflow follow-up

- Release lunar-minic before the lunar-frontend change `track-visitor-traffic-sources` is implemented. That change bumps the dependency and holds the end-to-end browser check.
- After review, archive the change. That creates `openspec/specs/analytics/traffic-recording`, `traffic-attribution` and `traffic-report`.
- Visit pruning, a carts `created_at` index and a crawler-detection library are follow-up tickets if needed.
