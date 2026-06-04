# Testing Strategy

## Purpose

This document describes the testing approach used in this repository and provides guidance for developers and AI agents when adding or modifying functionality.

The storefront (`lunar-frontend`) is a separate package. Tests here cover the **ecommerce engine, admin panel, and integrations** shipped from this monorepo—not host Livewire checkout flows or payment authorization in the frontend.

---

## Testing Boundaries

This repository tests:

- Ecommerce engine behavior
- Admin functionality
- Package integrations
- Domain logic

This repository does NOT test:

- lunar-frontend Livewire checkout flows
- Frontend UI behavior
- Host application event wiring
- Host application payment orchestration
- Host-specific business logic

---

## Integration Testing Rules

External integrations are tested using mocks and fakes.

---

## Testing Principles

- Test behavior, not implementation details.
- Prefer regression tests for bug fixes.
- Reuse existing testing patterns.

---

## Tooling

| Tool | Role |
|------|------|
| [Pest](https://pestphp.com/) | Primary test runner and syntax (`it()`, `test()`, `expect()`) |
| [Orchestra Testbench](https://packages.tools/testbench) | Minimal Laravel application bootstrapped per suite |
| PHPUnit (`phpunit.xml`) | Test suite definitions, env defaults, coverage roots |
| [Larastan](https://github.com/larastan/larastan) | Static analysis on `packages/**` (`composer test:phpstan`) |

All test files use Pest. PHPUnit class-based tests are rare; the exception is `tests/core/Unit/Base/Extendable/ExtendableTestCase.php` (extends `TestCase` for extendability coverage).

**Composer scripts:**

```bash
composer test:pest      # run Pest (all suites)
composer test:phpstan   # static analysis
composer test           # Pest + PHPStan
```

**Run a single suite or file:**

```bash
./vendor/bin/pest --testsuite core
./vendor/bin/pest tests/core/Unit/Models/CartTest.php
./vendor/bin/pest --group=resource.product
```

`phpunit.xml` sets `stopOnFailure="true"`; the first failure stops the run unless you override Pest/PHPUnit options.

---

## Environment

Defaults from `phpunit.xml` and CI:

| Variable | Typical value | Meaning |
|----------|---------------|---------|
| `APP_ENV` | `testing` | Laravel testing environment |
| `DB_CONNECTION` | `testing` | SQLite (in-memory in CI via `DB_DATABASE=:memory:`) |
| `LUNAR_TESTING_REPLACE_MODELS` | `false` (also `true` in CI matrix) | When `true`, swaps Lunar models for stub models under `tests/core/Stubs/Models` to exercise model extending |

CI runs each testsuite on PHP 8.3/8.4 × Laravel 11/12 (PHP 8.2 is listed in the matrix but excluded for Laravel 11/12), with and without `LUNAR_TESTING_REPLACE_MODELS=true`. See `.github/workflows/tests.yml`.

---

## Layout and Test Suites

Tests live under `tests/`, grouped by domain. Suites are declared in `phpunit.xml`:

| Suite | Directories | Primary code under test |
|-------|-------------|-------------------------|
| `core` | `tests/core/Unit`, `Feature`, `Database` | `packages/core` — models, pipelines, pricing, cart/order actions, validation |
| `admin` | `tests/admin/Unit`, `Feature` | `packages/admin` — Filament resources, Livewire, panel extensions |
| `shipping` | `tests/shipping/Unit`, `Feature` | `packages/table-rate-shipping` — zones, modifiers, rate drivers |
| `shippingAddon` | `tests/shippingAddon/Unit` | `packages/shipping` — Sameday, DPD, AWB, carrier APIs |
| `ERP` | `tests/ERP/Unit` | `packages/ERP` — Magister, Smartbill, sync, listeners |
| `stripe` | `tests/stripe/Unit` | `packages/stripe` — webhooks, charges, middleware |
| `search` | `tests/search/Unit`, `Feature` | `packages/search`, Meilisearch integration |
| `blog` | `tests/blog/Unit`, `Feature` | `packages/blog` |
| `review` | `tests/review/Feature` | `packages/review` |

**Also present but not in `phpunit.xml` suites today:** `tests/mailchimp/` (Mailchimp Saloon/services/jobs), `tests/opayo/` (Opayo payment type). `tests/paypal/` exists as a placeholder directory with no tests yet. Add suite entries and CI jobs when extending those packages. 

Package-local tests: `packages/table-rate-shipping/tests/` (separate `Pest.php`; not part of root `phpunit.xml` suites).

For navigation by domain, see [CODE_MAP.md](./CODE_MAP.md) (tests row and “Tests for a change”).

---

## Base Test Cases

Each domain binds Pest to a Testbench `TestCase` via `uses(...)` at the top of the file:

```php
uses(\Lunar\Tests\Core\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);
```

| TestCase | Namespace | Use when |
|----------|-----------|----------|
| `Lunar\Tests\Core\TestCase` | Core domain | Cart, orders, discounts, taxes, models, pipelines, most engine logic |
| `Lunar\Tests\Admin\TestCase` | Admin (non-Filament-specific) | Panel helpers, `asStaff()` / `makeStaff()` |
| `Lunar\Tests\Admin\Feature\Filament\TestCase` | Filament feature tests | Resource pages, Livewire + HTTP against panel |
| `Lunar\Tests\Admin\Unit\Filament\TestCase` | Filament unit tests | Isolated Filament components |
| `Lunar\Tests\Shipping\TestCase` | Table-rate shipping | Modifiers, `ShippingRateResolver`, flat/ship-by drivers |
| `Lunar\Tests\shippingAddon\TestCase` | Carrier add-on | Sameday/DPD providers, AWB payloads |
| `Lunar\Tests\ERP\TestCase` | ERP | Magister/Smartbill, sync commands, locations migrations |
| `Lunar\Tests\Stripe\Unit\TestCase` | Stripe | Payment driver and webhook behavior |
| `Lunar\Tests\Search\TestCase` | Search | Indexers, search manager |
| `Lunar\Tests\Blog\TestCase` / `Review\TestCase` | Blog / review | Package-specific providers |

`Lunar\Tests\TestCase` is the shared base: Testbench boot, optional model replacement (`replaceModelsForTesting()`).

**Choosing a TestCase:** Register the same service providers the production code needs. Copy an existing test in the same package—e.g. ERP tests load `ErpServiceProvider`, locations migrations, and `ErpPanelTestServiceProvider`; shipping add-on tests load `ShippingServiceProvider` and panel test providers.

---

## Common Patterns

### Database

- Use `RefreshDatabase` on any test that creates or mutates Eloquent records (most `core`, `admin`, `shipping`, `ERP` tests).
- Prefer **`Lunar\Database\Factories\*`** (and package factories under `packages/*/database/factories`) over manual `Model::create([...])` with large attribute arrays.
- Core `TestCase` calls `loadLaravelMigrations()`; package tests often also `loadMigrationsFrom()` for add-on tables (e.g. locations in ERP/shipping add-on).

### Global helpers (`tests/Pest.php`)

- `buildCart(array $cartParams = [])` — currency, addresses, shipping option, line; use for cart/checkout scenarios.
- `setAuthUserConfig()` — points auth at `Lunar\Tests\Core\Stubs\User`.
- `modelsReplaced()` — reflects `LUNAR_TESTING_REPLACE_MODELS`.

File-local helpers (e.g. `createCalculateCartWithLine()` in `CalculateTest.php`) are acceptable when shared only within one test file; promote to `Pest.php` or a trait when reused across files.

### Traits and stubs

- `Lunar\Tests\Shipping\TestUtils` — `createCart()` with calculated totals for shipping tests.
- `tests/core/Stubs/` — test tax driver, URL generator, custom purchasables, optional **model replacements** for extending.
- `tests/admin/Stubs/`, `tests/*/Providers/*TestServiceProvider.php` — panel/config overrides for Filament and integrations.

### Admin / Filament

- Authenticate with `$this->asStaff(admin: true)` or `Livewire::actingAs($this->makeStaff(), 'staff')`.
- Assert UI with `Livewire::test(PageClass::class)`, `fillForm()`, `call('create')`, and `assertDatabaseHas`.
- Group related tests: `->group('resource.product')` for selective runs.

### External HTTP (Saloon)

ERP, Mailchimp, and carrier tests fake Saloon requests with `MockClient` / `MockResponse`—no real network calls. Example pattern in `tests/ERP/Unit/Providers/Magister/MagisterErpProviderTest.php`.

### Mockery

Use for narrow interface boundaries (e.g. `ErpApiClientInterface`, shipping API clients) when Saloon faking is not the right layer.

### Migrations

`tests/core/Unit/MigrationTest.php` uses `->group('migrations')` to verify core migrations apply and roll back. Add similar coverage only when migration behavior is non-trivial.

### Time

Several base test cases call `$this->freezeTime()` to avoid flaky timestamp assertions.

---

## Where to Add Tests

| Change type | Preferred location |
|-------------|-------------------|
| Cart pipeline, pricing, discounts, coupons | `tests/core/Unit/Pipelines/`, `Managers/`, `DiscountTypes/`, `Validation/` |
| Order creation / validators | `tests/core/Unit/Pipelines/Order/`, `Actions/Carts/` |
| New or changed Eloquent behavior | `tests/core/Unit/Models/` |
| Filament resource or admin action | `tests/admin/Feature/Filament/Resources/...` |
| Table-rate shipping rule | `tests/shipping/Unit/` |
| Sameday/DPD/AWB | `tests/shippingAddon/Unit/` |
| Magister/Smartbill/sync | `tests/ERP/Unit/` |
| Stripe webhook or charge storage | `tests/stripe/Unit/` |
| Search indexer | `tests/search/` |

Read the nearest existing test file first—it defines providers, factories, and assertion style for that area.

---

## What to Assert

**Good (behavior):**

- After `calculate()`, cart `subTotal` / `total` match expected `Price` values.
- Validation throws or returns the correct message for invalid cart lines.
- Applying a coupon changes totals and persists discount breakdown as documented in [pricing_and_discounts.md](../design/pricing_and_discounts.md).
- ERP provider maps API payload to the shape consumed by importers.
- Filament create/update persists expected database rows.

**Avoid when possible:**

- Asserting that an internal private method was called unless there is no observable outcome.
- Duplicating entire pipeline implementations in the test.
- Hard-coding unrelated production config; use `Config::set()` in `beforeEach` for the minimum needed.

---

## Static Analysis

`composer test:phpstan` analyzes `packages/` at level 0. It does not replace tests but catches type and Laravel issues. Run it before opening a PR when touching package APIs.

---

## Local Workbench

`workbench/` and `testbench.yml` support Orchestra Testbench during package development (e.g. Boost commands). Automated CI does not depend on the workbench app; prefer Pest suites for verification.

---

## Documentation references

When testing behavior in this repo, see:

- Checkout → [checkout.md](../design/checkout.md)
- Order processing → [order_processing.md](../design/order_processing.md)
- Pricing and discounts → [pricing_and_discounts.md](../design/pricing_and_discounts.md)
- Package integration notes → `packages/*/README.md` or `*_PLUGIN.md` where present (e.g. `packages/mailchimp/MAILCHIMP_PLUGIN.md`)