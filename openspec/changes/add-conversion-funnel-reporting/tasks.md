# Tasks

## 1. Shared report code (no behavior change)

- [x] 1.1 Extract the period and its grouping into `packages/analytics/src/Reports/ReportPeriod.php`:
  - the constructor swaps reversed dates and widens them to whole days;
  - `from()`, `to()`, `days()`, `interval()`;
  - `group(array $perDay)`;
  - `DAILY_MAX_DAYS = 31`, `WEEKLY_MAX_DAYS = 182`.

  `TrafficReportQuery` builds one in its constructor and delegates `from()`, `to()`, the day list in `visitsPerDay()`, `visitsOverTime()` and `visitsOverTimeInterval()` to it. Its own `DAILY_MAX_DAYS` / `WEEKLY_MAX_DAYS` stay as aliases. Add `tests/analytics/Unit/ReportPeriodTest.php`:
  - a reversed period is swapped;
  - the interval at 31 / 32 / 182 / 183 days;
  - weeks start on Monday, with a partial first bucket keyed by the period start;
  - months.

  Verify with `vendor/bin/pest tests/analytics/Unit/ReportPeriodTest.php tests/analytics/Feature/Traffic/TrafficReportQueryTest.php`. `TrafficReportQueryTest.php` must pass without edits.
- [x] 1.2 Extract the order rules into `packages/analytics/src/Reports/CountedOrders.php`, built for a `ReportPeriod`:
  - move `orderExclusions()`, `countingSplitOriginalIds()`, `splitIntoOrderIdsOf()`, `visitIdOf()` and `ids()` from `TrafficReportQuery`;
  - add `placedQuery()`, the reportable placed-order query that `ordersBySource()` builds today;
  - add `excludedCartIds()` and `excludedVisitIds()`.

  `TrafficReportQuery` uses it in `visitsQuery()`, `cartsBySource()` and `ordersBySource()`. Move the class docblock's order rules to `CountedOrders`. Add `tests/analytics/Feature/CountedOrdersTest.php` for what the conversions report relies on:
  - `placedQuery()` counts a split order once, on the original's `placed_at`;
  - an order placed outside the period is not counted;
  - `excludedCartIds()` holds the cart of a `test-order` order but not a cart that also has a counting order.

  Verify with `vendor/bin/pest tests/analytics/Feature/CountedOrdersTest.php tests/analytics/Feature/Traffic/TrafficReportQueryTest.php`. The traffic test must pass without edits.
- [x] 1.3 Extract the period filter into `packages/analytics/src/Filament/Concerns/HasPeriodFilters.php`:
  - `filtersForm()`;
  - `DEFAULT_PERIOD_DAYS = 30`;
  - `defaultFrom()`, `periodFromFilters()`, `parseDate()`.

  `TrafficSourcesPage` uses the trait and drops its own copies. Move the `from` / `to` labels from `resources/lang/{en,hu,ro}/traffic.php` (`filters.*`) to `plugin.php` (`filters.*`), and remove the old keys. Verify with `vendor/bin/pest tests/analytics/Feature/Traffic/TrafficSourcesPageTest.php`, unchanged and green.
- [x] 1.4 In `docs/system/CODE_MAP.md`'s Analytics section, add a "Shared" row for `Reports\ReportPeriod`, `Reports\CountedOrders` and `Filament\Concerns\HasPeriodFilters`. Point the Report row at them. Verify that the class names in the doc match the files (`ls packages/analytics/src/Reports packages/analytics/src/Filament/Concerns`).

## 2. Conversion events storage and switch

- [x] 2.1 Add the `conversions` section to `packages/analytics/config/analytics.php`, with a comment block like `traffic`: `'enabled' => env('ANALYTICS_CONVERSIONS_ENABLED', false)`. Extend `tests/analytics/Feature/AnalyticsServiceProviderTest.php` to assert that `lunar.analytics.conversions.enabled` is `false` by default. Verify with `vendor/bin/pest tests/analytics/Feature/AnalyticsServiceProviderTest.php`.
- [x] 2.2 Add the storage:
  - **Enum.** `src/Conversions/Enums/ConversionEventType.php`: `AddToCart = 'add_to_cart'`, `CartStarted = 'cart_started'`, `BeginCheckout = 'begin_checkout'`.
  - **Migration.** `database/migrations/2026_11_26_000000_create_conversion_events_table.php`. It extends `Lunar\Base\Migration` and creates `$this->prefix.'conversion_events'` with:
    - `id`;
    - `type` (string 32);
    - `cart_id` (unsigned big integer, no foreign key);
    - `created_at` (nullable timestamp, indexed);
    - an index on (`cart_id`, `type`).

    It has `shouldRun()` and `down()` like the traffic migration.
  - **Model.** `src/Conversions/Models/ConversionEvent.php`: `BaseModel`, `HasFactory` with `newFactory()`, `UPDATED_AT = null`, `$table = 'conversion_events'`, fillable `type` / `cart_id` / `created_at`, the enum and datetime casts, and `@property` docs.
  - **Factory.** `database/factories/ConversionEventFactory.php`, with an `add_to_cart` default and states `cartStarted()` and `beginCheckout()`.
  - **Test.** `tests/analytics/Feature/Conversions/ConversionEventTest.php`:
    - the table is `lunar_conversion_events`;
    - `Schema::getColumnListing()` is exactly `id`, `type`, `cart_id`, `created_at`, which covers "no personal data";
    - the type casts to the enum.
  - **Docs.** Add `lunar_conversion_events` to `docs/database/schema.dbml`.

  Verify with `vendor/bin/pest tests/analytics/Feature/Conversions/ConversionEventTest.php`.

## 3. Recording

- [x] 3.1 Add `src/Conversions/Services/ConversionEventRecorder.php`, with `TrafficVisitRecorder` injected only for `shouldTrack()`:
  - `enabled()` reads `lunar.analytics.conversions.enabled`.
  - `addToCart(Cart $cart)` inserts a `cart_started` event when the cart has none, then an `add_to_cart` event, both with the same time.
  - `beginCheckout(Cart $cart)` inserts a `begin_checkout` event only when the cart has none.
  - Both return early when recording is off, `shouldTrack()` is false or the cart is not saved.
  - Both wrap the lookups and inserts in `try`, and on a `Throwable` call `report()` and return.

  Add `tests/analytics/Feature/Conversions/ConversionEventRecorderTest.php`, covering every scenario in `specs/analytics/conversion-recording/spec.md`:
  - the first add records a start and an add; later adds record only adds;
  - checkout is recorded once per cart, and again for a new cart;
  - recording off records nothing;
  - recording on with traffic off records events and no visit;
  - with `Filament::serving` / a logged-in staff user, nothing is recorded;
  - an unsaved cart records nothing;
  - a guest-cart merge on login (`MergeCart`) and `Cart::updateLine()` record nothing;
  - a failed insert (drop the table first) is reported (`Exceptions::fake()`) and does not throw;
  - no cookie is queued.

  Verify with `vendor/bin/pest tests/analytics/Feature/Conversions/ConversionEventRecorderTest.php`.
- [x] 3.2 Document recording:
  - **`UPGRADE.md`** `### Analytics Package`:
    - the `ANALYTICS_CONVERSIONS_ENABLED` variable (default `false`, independent of traffic) and the `conversions` config section;
    - the host's duty to call `ConversionEventRecorder::addToCart()` after each successful add and `::beginCheckout()` when checkout opens for a valid cart;
    - that figures differ from GA4: no consent dependency, checkouts once per cart.
  - **`docs/system/PROJECT_SPECIFICATION.md`**: extend the `packages/analytics` bullet with the conversions feature.

  Verify that both docs name the variable and the two recorder methods exactly as in the code (`grep -n "ANALYTICS_CONVERSIONS_ENABLED\|addToCart\|beginCheckout" UPGRADE.md docs/system/PROJECT_SPECIFICATION.md`).

## 4. Report query

- [x] 4.1 Add `src/Conversions/Reports/ConversionReportQuery.php`, with a `ReportPeriod` and `CountedOrders` built in the constructor:
  - **Events query.** By `created_at` in the period, with `whereNotIn('cart_id', excludedCartIds())`.
  - **`totals()`.** `add_to_cart`, `carts`, `checkouts`, `purchases`, `checkout_rate`, `purchase_rate`, `cart_purchase_rate`:
    - one grouped event count;
    - `placedQuery()->count()`;
    - `null` rates on a 0 divisor.
  - **`overTime()`.** `{interval, steps}`:
    - one query on events grouped by type and `DATE(created_at)`;
    - one on `placedQuery()` grouped by `DATE(placed_at)`;
    - zero-filled days, grouped with `ReportPeriod::group()`.

  Add `tests/analytics/Feature/Conversions/ConversionReportQueryTest.php`, covering every scenario in `specs/analytics/conversion-report/spec.md` except access:
  - the default and custom period boundaries;
  - the 120 / 50 / 20 / 8 totals;
  - purchases with and without a source, and a `test-order` order, compared with `TrafficReportQuery::totals()['orders']`;
  - a split counted once;
  - the 40% / 40% / 16% rates, a `null` purchase rate, and a 150% cart purchase rate;
  - a test-order cart's events left out while a cart without an order counts;
  - a day without checkouts shows 0;
  - 14 weekly buckets for 4 August to 8 November;
  - recording switched on mid-period (events from 4 October, purchases from the whole period).

  Verify with `vendor/bin/pest tests/analytics/Feature/Conversions/ConversionReportQueryTest.php`.

## 5. Permission, page and widgets

- [x] 5.1 Make `EnsureAnalyticsPermissions::run()` create both `analytics:traffic-sources` and `analytics:conversions` (`firstOrCreate` in a loop). Add the label and description of `analytics:conversions` to `resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php`. Extend `tests/analytics/Feature/Traffic/AnalyticsPermissionsTest.php`, or add `tests/analytics/Feature/Conversions/ConversionsPermissionTest.php`, to check that:
  - the run and the migration event create the permission once on the staff guard;
  - the traffic permission still exists;
  - the role count is unchanged;
  - the label and description resolve in en, hu and ro.

  Verify with `vendor/bin/pest tests/analytics/Feature`.
- [x] 5.2 Add `resources/lang/{en,hu,ro}/conversions.php`:
  - the page title and navigation label;
  - the stat labels ("Add to cart", "Carts", "Checkouts", "Purchases");
  - the rate descriptions;
  - the chart heading per `day` / `week` / `month`;
  - the four series names.

  Settle the Hungarian and Romanian step names (design.md, Open Questions) here. Verify that every key used in the code resolves in all three locales, with a test that loops over the keys of `en/conversions.php` and checks `hu` and `ro` have them.
- [x] 5.3 Add the report:
  - **Page.** `src/Conversions/Filament/Pages/ConversionsPage.php`:
    - extends `Dashboard`, with `HasFiltersForm` and `HasPeriodFilters`;
    - route and slug `analytics/conversions`;
    - `PERMISSION = 'analytics:conversions'`;
    - `canAccess()` as on the traffic page;
    - navigation sort 2, Analytics group, `Heroicon::OutlinedFunnel`;
    - two columns and a static `conversionWidgets()`.
  - **Widgets:**
    - `Filament/Widgets/Concerns/InteractsWithConversionReport.php`;
    - `ConversionStatsOverview`: four stats, with the checkout rate on "Checkouts", and the purchase rate and cart purchase rate on "Purchases"; a rate without a value shows "—";
    - `ConversionsOverTimeChart`: an ApexCharts line with four series, labels as in `VisitsOverTimeChart`, and a heading per interval.
  - **Plugin.** Register the page and widgets in `AnalyticsPlugin::register()`.

  Add `tests/analytics/Feature/Conversions/ConversionsPageTest.php`:
  - an admin opens the page;
  - a holder of `analytics:conversions` opens it and sees it in the Analytics navigation;
  - a staff member with only `analytics:traffic-sources` is refused and does not see it;
  - `ConversionsPage::periodFromFilters(null)` gives the last 30 days;
  - the stats widget renders seeded totals and rates;
  - the chart heading follows the interval.

  Verify with `vendor/bin/pest tests/analytics/Feature/Conversions/ConversionsPageTest.php`.
- [x] 5.4 Document the report:
  - **`docs/system/CODE_MAP.md`** Analytics section: Conversions rows (recorder, model and table, report query, page and widgets, permission). Add `src/Conversions/` to the package's repository-structure line and to the "where to find" table.
  - **`UPGRADE.md`**: the Conversions page, the `analytics:conversions` permission, which `php artisan migrate` creates, and `lunar_conversion_events`.
  - **`docs/changelog/<implementation date>.md`**: an LFP-924 entry, for example "Added recording of add-to-cart, cart start and checkout start events and a Conversions report in the admin Analytics group (add to carts, carts, checkouts, purchases, step rates and trend), behind `ANALYTICS_CONVERSIONS_ENABLED` and the `analytics:conversions` permission."

  Verify that each doc names the same route, permission, table and env variable as the code.

## 6. Integration checks

- [x] 6.1 Run `php -d memory_limit=-1 vendor/bin/pest --testsuite analytics` and verify it is fully green.
- [x] 6.2 Run `vendor/bin/pint --dirty --format agent` on the changed PHP files. Run `vendor/bin/phpstan analyse --memory-limit=-1 --no-progress`, and verify it reports no new errors in `packages/analytics` beyond the known pre-existing ones.
- [ ] 6.3 On `webshop-demo.test`, which uses this checkout through a path repository:
  - take a lerd `db snapshot`;
  - run `php artisan migrate` through lerd `exec`;
  - verify that `lunar_conversion_events` exists and the `analytics:conversions` permission is present once (`podman exec lerd-mysql mysql -uroot -plerd webshop_demo`).

  Opening the page in the browser needs a user-provided admin login, so ask the user before that step.
- [x] 6.4 Run `openspec validate add-conversion-funnel-reporting --strict` and verify it passes.

## 7. Purchase rates from tracked carts (user decision, 2026-11-26)

- [x] 7.1 In `ConversionReportQuery::totals()`:
  - add `tracked_purchases`: `placedQuery()` restricted to `cart_id`s that have a conversion event of any time;
  - divide `purchase_rate` and `cart_purchase_rate` by checkouts and carts using `tracked_purchases`;
  - leave `purchases` and the `overTime()` purchases series as all purchases.

  In `tests/analytics/Feature/Conversions/ConversionReportQueryTest.php`:
  - give the purchases of the week and rate tests tracked carts (`trackedPurchase()`, events recorded before the period);
  - the 150% test uses tracked carts recorded before the period, which covers "Cart recorded before the period";
  - add one test for "Order whose cart was not recorded" and "Purchases from carts that were not recorded": 10 purchases, 8 tracked, a 40% purchase rate, the same 10 as the traffic report's orders and in the purchases series;
  - extend the mid-period test: 3 purchases, 1 tracked, rates from the tracked one.

  Verify with `vendor/bin/pest tests/analytics/Feature/Conversions/ConversionReportQueryTest.php`.
- [x] 7.2 In `ConversionStatsOverview`, put `:count from tracked carts` first in the "Purchases" description, then the two rates. Add `stats.tracked_purchases` to `resources/lang/{en,hu,ro}/conversions.php`. In `ConversionsPageTest`, seed tracked purchases and assert the new line. Verify with `vendor/bin/pest tests/analytics/Feature/Conversions`.
- [x] 7.3 Update the conversions report in `UPGRADE.md`, `docs/changelog/2026-11-26.md` and `docs/system/PROJECT_SPECIFICATION.md`: the purchase rates divide purchases from tracked carts, and the stat shows that count. Verify that the three docs and the code use the same wording for "tracked carts".
- [x] 7.4 Rerun `php -d memory_limit=-1 vendor/bin/pest --testsuite analytics`, `vendor/bin/pint --dirty --format agent` and `openspec validate add-conversion-funnel-reporting --strict`.

## Workflow follow-up

- The lunar-frontend counterpart is implemented on its `feature/LFP-924` branch without its own OpenSpec change. It calls `addToCart()` in `AddToCart`, `ProductCard`, `GiftSelectionModal`, `WishlistProductCard` and the order history reorder, and `beginCheckout()` in `Checkout::lfpMount()`. It also adds `lunar_conversion_events` to the AI assistant table seeder.
- Archive this change after review and release.
