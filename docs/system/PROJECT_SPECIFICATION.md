# Lunar Fork Technical Specification

Last analyzed: 2026-06-03 (host integration verified against `../lunar-frontend`)

This document summarizes the repository at local HEAD. It is intended as working context for future coding agents and developers. Treat this repository as the source of truth. Upstream Lunar PHP documentation is useful orientation only; this fork is significantly customized.

## Project Overview

- This repository is a customized fork of Lunar PHP 1.x packaged as `lunarphp/lunar-minic` in `composer.json`.
- It provides the ecommerce engine and back-office/admin surface for a webshop stack: catalog, pricing, inventory, carts, checkout, orders, customers, discounts, shipping, payments, search, ERP, marketing integrations, and admin tooling.
- The separate `minic/lunar-frontend` package (`../lunar-frontend`) is the storefront layer. This repository contains reusable package code and the Filament admin panel only; all customer-facing HTTP routes, Livewire pages, checkout, and payment finalization live in `lunar-frontend`, not here.
- The fork is periodically synchronized with upstream Lunar. A local `upstream/1.x` remote branch exists, but this fork has large divergence, including custom packages, domain rules, migrations, payment/shipping/ERP integrations, and admin changes.
- High-level architecture:
  - `packages/core` contains domain models, contracts, managers, facades, pipelines, actions, migrations, config, jobs, observers, events, and commands.
  - `packages/admin` provides the Filament admin panel and search/admin extension hooks.
  - Add-on packages extend core behavior through Laravel service providers, model manifests, morph maps, observers, commands, config, scheduled tasks, and admin panel extensions.
  - Runtime extension is mostly config-driven: actions, validators, cart/order/pricing pipelines, payment types, tax/shipping drivers, ERP providers, search engines, and admin panel extensions.

## Technical Stack

| Area | Verified implementation |
| --- | --- |
| PHP | `composer.json` requires `php:^8.2`; project context states PHP 8.3. |
| Laravel | Root `composer.json` supports `laravel/framework:^11.0|^12.0`; `composer.lock` contains Laravel `v12.56.0`. |
| Admin/UI | Filament `v3.3.49`, Livewire `v3.7.12`, Spatie Permission, Filament Apex Charts, Filament 2FA plugin. |
| Admin assets | No root `package.json`; `packages/admin/package.json` uses Tailwind 3, PostCSS, esbuild, npm-run-all, and filament-purge. |
| Search | Laravel Scout `v10.25.0`, Meilisearch PHP `v1.16.1`, Typesense PHP `v4.9.3`; custom `packages/search` and `packages/meilisearch` for admin/indexing. Storefront (`lunar-frontend`) uses Algolia Scout Extended for catalog filter; default `SCOUT_DRIVER` there is `algolia`. |
| Payments | Core offline payment driver plus Stripe, PayPal, and Opayo packages. |
| Shipping | Core shipping config, table-rate shipping package, and custom carrier add-on with Sameday, DPD, Pickup, and InHouse providers. `fan` exists in `ShippingProviderEnum` but no default provider config/class was found. |
| ERP | Custom ERP package with Magister and Smartbill providers, Saloon HTTP clients, sync commands, jobs, and invoice/AWB-related order workflows. |
| Marketing | Custom Mailchimp package using Saloon requests for audience, subscriber, ecommerce store, cart, product, and order sync. |
| Media/documents | Spatie Media Library, DomPDF/barryvdh DomPDF. |
| HTTP/API clients | Saloon `v4.0.0`, Guzzle `v7.10.0`, Laravel HTTP client for PayPal. |
| Development tooling | Pest, Orchestra Testbench, Larastan/PHPStan, Laravel Pint, Mockery, Faker, Laravel Boost as a dev dependency. |

Core service providers are registered in root `composer.json` under Laravel package discovery. Important providers include:

- `Lunar\LunarServiceProvider`
- `Lunar\Admin\LunarPanelProvider`
- `Lunar\ERP\ErpServiceProvider`
- `Lunar\Blog\BlogServiceProvider`
- `Lunar\Review\ReviewServiceProvider`
- `Lunar\Locations\LocationsServiceProvider`
- `Lunar\Mailchimp\MailchimpServiceProvider`
- `Lunar\Addons\Shipping\ShippingServiceProvider`
- `Lunar\Shipping\ShippingServiceProvider`
- `Lunar\Search\SearchServiceProvider`
- `Lunar\Stripe\StripeServiceProvider`
- `Lunar\Paypal\PaypalServiceProvider`
- `Lunar\Opayo\OpayoServiceProvider`

## Repository Structure

| Path | Purpose |
| --- | --- |
| `packages/core` | Main Lunar domain package: models, contracts, managers, facades, actions, pipelines, modifiers, data types, migrations, config, events, listeners, jobs, commands, observers, taxes, payments, search indexing. |
| `packages/admin` | Filament admin panel, resources, relation managers, widgets, Livewire components, admin search helpers, panel extension manager, admin translations/assets. |
| `packages/table-rate-shipping` | Shipping zones, shipping methods/rates, rate resolvers, modifiers, drivers, admin resources, and shipping table migrations. |
| `packages/shipping` | Carrier/provider add-on for AWB generation, lockers, tracking, Sameday/DPD/Pickup/InHouse integrations, shipping commands and scheduled locker sync. |
| `packages/ERP` | ERP provider framework, Magister and Smartbill integrations, sync commands, import/export jobs, invoice generation, order observer. |
| `packages/search` | Search manager abstraction over database, Meilisearch, and Typesense; faceted product search support. |
| `packages/meilisearch` | Meilisearch setup command package. |
| `packages/stripe`, `packages/paypal`, `packages/opayo` | Payment integrations and routes/webhooks/payment type implementations. |
| `packages/blog` | Blog category/post models, migrations, URL generation, admin extensions, seed command. |
| `packages/review` | Review model, media handling, review requests, admin extensions, order/product/channel relations, reminder command. |
| `packages/locations` | County and locality models, migrations, `CountySeeder`, and `locality_insert.sql`. `LocationsServiceProvider` only registers models via `ModelManifest`; migrations are not auto-loaded and must be run by the host (see Host integration). |
| `packages/mailchimp` | Mailchimp connector, requests, services, jobs, observer, commands, and config. |
| `tests/*` | Pest/Testbench suites split by package/domain, including core, admin, ERP, shipping add-on, mailchimp, and payment packages. |

## Domain Model

### Core Entities

- Catalog:
  - `Lunar\Models\Product`
  - `Lunar\Models\ProductVariant`
  - `Lunar\Models\ProductType`
  - `Lunar\Models\ProductOption`
  - `Lunar\Models\ProductOptionValue`
  - `Lunar\Models\Collection`
  - `Lunar\Models\CollectionGroup`
  - `Lunar\Models\Brand`
  - `Lunar\Models\Tag`
- Pricing and tax:
  - `Lunar\Models\Price`
  - `Lunar\Models\Currency`
  - `Lunar\Models\TaxClass`
  - `Lunar\Models\TaxZone`
  - `Lunar\Models\TaxRate`
  - `Lunar\Models\TaxRateAmount`
- Commerce:
  - `Lunar\Models\Cart`
  - `Lunar\Models\CartLine`
  - `Lunar\Models\Order`
  - `Lunar\Models\OrderLine`
  - `Lunar\Models\Transaction`
  - `Lunar\Models\Address`
  - `Lunar\Models\AddressCustomerType`
- Customer/account:
  - `Lunar\Models\Customer`
  - `Lunar\Models\CustomerGroup`
  - `Lunar\Models\Staff`
  - `Lunar\Models\LunarUser`
- Promotions:
  - `Lunar\Models\Discount`
  - `Lunar\Models\Discountable`
- Platform:
  - `Lunar\Models\Channel`
  - `Lunar\Models\Language`
  - `Lunar\Models\Url`
  - `Lunar\Models\Attribute`
  - `Lunar\Models\AttributeGroup`

### Relationships and Aggregates

- Product aggregate:
  - `Product` owns/relates variants, product type, brand, collections, product options, customer groups, channels, URLs, media, prices through variants, tags, and product associations.
  - `ProductVariant` implements `Lunar\Base\Purchasable` and relates to product, prices, tax class, option values, media, and discounts.
- Cart aggregate:
  - `Cart` contains `CartLine` records, selected addresses, currency, channel, user/customer, selected shipping option, calculated totals, tax/discount/shipping breakdowns, and draft/completed order relations.
  - `CartLine` morphs to a purchasable, currently most commonly `ProductVariant`.
- Order aggregate:
  - `Order` is created from a cart through the order creation pipeline. It owns order lines, addresses, transactions, shipping/tax/discount breakdowns, and customer/user references.
  - `OrderLine` morphs to purchasable items and carries copied price/tax/discount/meta values from the cart.
- Customer aggregate:
  - `Customer` relates to users, customer groups, discounts, addresses, orders, and mapped attributes.
- Shipping aggregate:
  - Table-rate models include shipping zones, methods, rates, exclusions, and customer/customer-group availability.
  - Carrier add-on models include provider credentials, counties, cities, and lockers.

### Value Objects and Data Types

- `Lunar\DataTypes\Price` is the main money-like data type used on models and calculated cart/order properties.
- `Lunar\DataTypes\ShippingOption` represents resolved checkout shipping options.
- Pricing responses, shipping breakdowns, discount breakdowns, and tax breakdowns are represented by core support/value classes under the related namespaces rather than formal PHP enums.

### Important Enums

- `Lunar\Base\Enums\ProductAssociation`
- `Lunar\Enums\ProductEventType`
- `Lunar\ERP\Enums\ErpProviderEnum`
- `Lunar\Addons\Shipping\Enums\ShippingProviderEnum`
- `Lunar\Addons\Shipping\Enums\ShippingType`
- `Lunar\Stripe\Enums\CancellationReason`

### Important Statuses

- Product status is string-based; availability code checks for `published` in `Product::scopeAvailable`.
- `Product` has a custom `published_at` timestamp and product publishing behavior.
- Discount statuses are constants on `Lunar\Models\Discount`: `active`, `pending`, `expired`, `scheduled`.
- Core defaults in `packages/core/config/orders.php` include `awaiting-payment`, `payment-offline`, `payment-received`, and `dispatched`.
- The host application (`lunar-frontend`) publishes and extends `config/lunar/orders.php` with additional statuses and a different draft status (`order-received` instead of core `awaiting-payment`). Host-configured statuses include: `created`, `canceled`, `confirmed`, `awaiting-payment`, `payment-received`, `payment-offline`, `prepare-shipment`, `failed-awb-generation`, `ready-for-pickup`, `dispatched`, `returned`, `rejected`, and `completed`.
- Stock side effects in the host use `config/lunar-frontend/orders.php`: decrease on `prepare-shipment`, increase on `canceled`. Review reminders default to `completed` via `ORDER_STATUS_FOR_REVIEW_REMINDER`.
- Payment status transitions are payment-driver-specific and stored as order string statuses.

## Core Business Flows

### Product Management

- Product data is modeled in `Product`, `ProductVariant`, options, option values, attributes, brands, collections, URLs, channels, customer groups, media, and tags.
- Admin management is primarily through `packages/admin/src/Filament/Resources/*`, registered by `Lunar\Admin\LunarPanelManager`.
- Product availability is not just a status check. `Product::scopeAvailable()` requires customer group visibility/enabled/scheduling, `status = published`, and current storefront channel.
- Product variants dispatch custom create/update/delete events through `ProductVariant::$dispatchesEvents`.
- Product publishing is customized through `published_at` migrations and product observer/state-listener behavior.
- Product search indexing is handled through `Lunar\Base\Traits\Searchable`, `ProductIndexer`, Scout, and admin event listeners that call `sync_with_search($model)`.

### Pricing

- Pricing is resolved through `Lunar\Managers\PricingManager` and the `Pricing` facade.
- `PricingManager` selects currency, customer groups, base prices, customer-group prices, and quantity price breaks. `Price` has a custom `max_quantity` column used by shipping rate tier logic.
- Cart line unit prices are set by `Lunar\Pipelines\CartLine\GetUnitPrice`.
- Price calculation respects `packages/core/config/pricing.php`, especially inclusive/exclusive tax storage.
- Configured pricing pipelines can further transform `PricingResponse`.

### Inventory Management

- Inventory is stored on `ProductVariant` as `stock`, `backorder`, and `purchasable`.
- `ProductVariant::canBeFulfilledAtQuantity()` and `getTotalInventory()` determine stock/backorder behavior.
- `decreaseStock()` and `increaseStock()` adjust stock/backorder with support for negative backorder transitions.
- Cart stock validation is handled by `Lunar\Validation\CartLineStock` through `HasStock`. Stock checking is disabled by default unless `lunar.cart.stock_check.enabled` is true.
- Quantity validation is handled by `Lunar\Validation\CartLineQuantity`, including min quantity and quantity increment constraints.

### Cart Management

- `Cart` is the central mutable checkout object. Public methods delegate to configured actions in `packages/core/config/cart.php`.
- The default cart calculation pipeline is:
  - `CalculateLines`
  - `ApplyShipping`
  - `ApplyDiscounts`
  - `CalculateTax`
  - `Calculate`
- `CartSessionManager` stores the active cart in session key `lunar_cart`, can create carts, associates authenticated users, and handles completed-cart replacement.
- `CartSessionAuthListener` merges guest/authenticated carts on login using `lunar.cart.auth_policy`, defaulting to `merge`.
- `StorefrontSessionManager` stores channel, customer groups, currency, and customer in session key `lunar_storefront`.
- Cart session and storefront session bindings are scoped in `LunarServiceProvider`, not singletons.

### Checkout

- `Cart::createOrder()` validates the cart with configured validators and delegates to `Lunar\Actions\Carts\CreateOrder`.
- Order creation runs in a database transaction and uses the configured order pipeline in `packages/core/config/orders.php`.
- Draft orders are reused for the same cart when available. Completed orders block duplicate order creation unless the cart session allows multiple orders.
- Checkout shipping uses `ShippingManifest`, selected shipping options, shipping estimates, and table-rate shipping modifiers when enabled.

### Order Processing

- Order creation pipeline:
  - `FillOrderFromCart`
  - `CreateOrderLines`
  - `CreateOrderAddresses`
  - `CreateShippingLine`
  - `CleanUpOrderLines`
  - `MapDiscountBreakdown`
- `FillOrderFromCart` copies cart totals, user/customer/channel/currency/status/meta, and generates references with `GenerateOrderReference`.
- `Order` exposes custom computed accessors for coupon totals, non-coupon discounts, discounted subtotal excluding coupon, package weight, applied coupon, and status-related activity.
- `MarkAsNewCustomer` is dispatched after order creation.
- ERP, shipping, payment, and Mailchimp packages add order side effects through observers, events, listeners, and queued jobs.

### Customer Management

- `Customer` relates to users, groups, addresses, orders, discounts, and mapped attributes.
- Customer groups drive price selection, product visibility/purchasability, cart/storefront session defaults, discount eligibility, and shipping method eligibility.
- `StorefrontSessionManager` defaults authenticated users to their latest customer and validates selected customers against the authenticated `LunarUser`.

### Discounting

- Discounts are applied through `Lunar\Managers\DiscountManager` and the `Discounts` facade.
- Default enabled discount type is `Lunar\DiscountTypes\AdvancedAmountOff`; `AmountOff` and `BuyXGetY` exist but are commented as disabled by default.
- Discount retrieval filters by active/usable/channel/customer group and, when a cart is available, product, variant, collection, and brand scopes.
- Automatic discounts apply the best priority discount per cart line. Coupon discounts are applied separately when `cart->coupon_code` is present.
- This fork has coupon-aware totals on cart and order lines, including non-coupon discounted subtotals and coupon totals including tax.
- `AdvancedAmountOff` contains most active discount behavior, including percentage/fixed display calculation, automatic line discounting, coupon application, and limitation/exclusion checks.

### Shipping

- Core shipping options are resolved through `Lunar\Base\ShippingModifiers`, `Lunar\Base\ShippingManifest`, and `ShippingOption`.
- `packages/table-rate-shipping` adds `ShippingModifier`, shipping zones, methods, rates, exclusions, customer type eligibility, and customer-group eligibility.
- `ShippingRateResolver` filters zones by country/state/postcode, method enabled state, customer groups, cut-off time, stock availability, and postcode match.
- `ShipBy` calculates rates by discounted subtotal excluding coupons or total cart weight. It also rejects customer type mismatches, shipping exclusions, and locker shipments over 20kg.
- `packages/shipping` adds carrier behavior:
  - AWB generation on configured order status.
  - Tracking URL generation.
  - Locker/county/city synchronization.
  - Provider-specific request building for Sameday, DPD, Pickup, and InHouse.
- `Order::packageWeight` normalizes product weights in `kg`, `g`, and `lbs`; unsupported units throw `UnsupportedWeightUnitException`.

### Payments

- Core payments are managed by `Lunar\Managers\PaymentManager`.
- Default core payment type is `cash-in-hand`, implemented by `OfflinePayment`. It can create/reuse a draft order, merge payment meta into order meta, set authorized status, set `placed_at`, and dispatch `PaymentAttemptEvent`.
- Stripe:
  - Registered by `StripeServiceProvider` through `Payments::extend('stripe')`.
  - Adds payment intents, webhook route at `config('lunar.stripe.webhook_path')`, Livewire component, and queued webhook processing.
  - `StripePaymentType` creates/reuses orders, retrieves/captures PaymentIntents, updates order status from config mapping, and marks processed intents.

### Payment and checkout meta (authoritative for integrations)

The storefront (`lunar-frontend`) owns checkout meta. `FillOrderFromCart` copies `cart.meta` onto `order.meta` unchanged.

| Key | Set on | Values / notes |
| --- | --- | --- |
| `payment_option` | Cart during checkout; copied to order | Must be a key in merged `lunar.payments.types`. Host defaults: `cash-on-delivery`, `stripe-card`. Also referenced: `offline`, `hosted-payment` (via `minic/lunar-hosted-payment`). Validated in `PaymentOptions` Livewire. Used by payment drivers, `AuthorizeOrderPayment`, Stripe webhooks, Smartbill `PaymentSlugMapper` (`ramburs` / `card`), Magister (`TYPEOF_PAYMENT`), and carrier AWB builders (COD amount). |
| `shippingType` | Cart shipping step | `courier` or `locker` (`Lunar\Addons\Shipping\Enums\ShippingType`). Drives locker UI and Sameday/DPD AWB logic. |
| `isBillingSameAsShipping` | Cart billing/shipping | Boolean. |
| `is_guest` | Checkout identity | Boolean guest checkout flag. |
| `language_locale` | Order placement (`Summary`) | App locale at checkout; used by order emails. |
| `gtm_purchase_event_id` | Post-authorization | Set by `AuthorizeOrderPayment` for GTM deduplication. |

Address meta (not order-level): shipping addresses for lockers store `locker_id` (and related locker fields) on the address `meta` JSON.

Host payment driver registration is in `lunar-frontend` `config/lunar-frontend/payment.php` (`Payments::extend` for `cash-on-delivery`, `stripe-card`). Status mapping is in published `config/lunar/payments.php` (`payment-offline` for COD, `payment-received` for card).

## Host application integration (`lunar-frontend`)

Verified against `../lunar-frontend` (package `minic/lunar-frontend`, depends on `lunarphp/lunar-minic`).

### How the host consumes this repository

There is no separate REST API layer for storefront commerce. The host Laravel app composes:

1. **Direct domain access** — Eloquent models (`Lunar\Models\*`, manifest replacements), and facades/managers: `CartSession`, `StorefrontSession`, `Payments`, `Pricing`, `Discounts`, shipping modifiers/manifest, ERP services, Mailchimp services.
2. **Package HTTP surface** — Payment webhooks from Lunar packages (e.g. Stripe) plus `lunar-frontend` routes in `routes/web.php`, `routes/web-localized.php`, and `routes/webhook.php` (Livewire full-page components, OAuth controllers, signed checkout URLs).
3. **Config and pipelines** — Published `config/lunar/*` overrides (orders, cart, payments) and `config/lunar-frontend/*`; custom order pipeline stage `OrderCreatedPipeline` appended after core creation stages.
4. **Algolia storefront search** — Collection/catalog filtering uses `algolia/algoliasearch-client-php` and `AlgoliaFilterService`, not the admin `packages/search` query engines. Scout still indexes models per `lunar.search` / `SCOUT_DRIVER`.

Storefront routes/controllers are intentionally absent from this repo; `lunar-frontend` owns all customer-facing HTTP behavior. The host must remove any default `routes/web.php` home route that would override the package home URL (`lfp:install` documents this).

### `OrderPlacedEvent` and Mailchimp order sync

- `Lunar\ERP\Events\OrderPlacedEvent` is **dispatched by the host**, not by core Lunar or the ERP package provider. `Minic\LunarFrontend\Domains\Payment\Actions\AuthorizeOrderPayment` dispatches it after successful payment authorization (Stripe webhook, COD on summary, idempotent retry paths).
- Listeners are registered in the host via `config/lunar-frontend/listeners.php` (wired in `LunarFrontendServiceProvider::registerListeners()`), including:
  - `SendOrderPlacedNotification`
  - `Lunar\ERP\Listeners\SendOrderToERP`
  - `Lunar\Mailchimp\Listeners\SyncOrderOnPlacement`
- Mailchimp order sync on placement is therefore **host-wired**, not registered inside `packages/mailchimp` alone. Bulk/historical sync remains available via `mailchimp:sync-all-orders` (filters `status = completed`).

### Locations package in production

- `LocationsServiceProvider` does not call `loadMigrationsFrom`. The host must apply `packages/locations/database/migrations` (via publish/manual migrate or testbench-style load in CI).
- `lunar-frontend` documents seeding counties (`Lunar\Locations\Database\Seeders\CountySeeder`) and loading localities from `packages/locations/database/locality_insert.sql` (too large for a seeder). Address dropdowns in checkout use `Lunar\Locations\Models\County` / `Locality` when tables exist.

### ERP and shipping (production configuration)

Actual production toggles live in each deployed host’s `.env` and published `config/lunar/erp.php` / `config/lunar/shipping.php` (not committed in either repo). Documented deployment expectations from `lunar-frontend` README:

| Area | Documented host setup |
| --- | --- |
| ERP | `ERP_ENABLED=true` with scheduled sync for products, order statuses, stock, localities, attributes. Providers **Magister** (`MAGISTER_ENABLED`) and **Smartbill** (`SMARTBILL_ENABLED`) with credentials via env. Provider list and per-feature `sync` / `actions` arrays are empty in package defaults and must be filled in published `config/lunar/erp.php` (e.g. Magister for import sync, Smartbill for `billing` / invoice generation on `awaiting-payment`). |
| Shipping | `SHIPPING_ENABLED`, `SHIPPING_LOCKER_ENABLED`. Default provider keys in `packages/shipping/config/shipping.php`: `sameday`, `dpd`, `pickup`, `inhouse`. Per-provider env blocks in README (`SAMEDAY_*`, `DPD_*`). AWB generation status default: `prepare-shipment`. |
| FAN | `ShippingProviderEnum::fan` exists and locker migration comments mention `fan_courier`, but **no FAN provider class or default config entry** is registered. Not used by `lunar-frontend`; treat as reserved/planned, not production-supported. |

### Scout and merged `lunar.search`

| Layer | Driver / engine |
| --- | --- |
| Laravel Scout (`SCOUT_DRIVER`) | Host `.env`; `lunar-frontend` publishes `config/lunar-frontend/scout.php` defaulting to **`algolia`**. README requires Algolia credentials. Product variant reimport: `scout:reimport` on `Minic\LunarFrontend\Domains\Product\Models\ProductVariant`. |
| `lunar.search` (this repo `packages/search`) | Merged by `SearchServiceProvider`; defines `models`, `engine_map`, and indexers for admin/global indexing (`lunar:search:index`). Faceted DB/Meilisearch/Typesense engines apply to admin search helpers, not the Algolia collection filter UI. |
| Storefront catalog filter | **Algolia** via `AlgoliaFilterService` and dedicated sync listeners/commands — parallel to Scout indexing. |

Verify `config('lunar.search')` and `config('scout')` on the running host after all providers boot; both namespaces are extended by multiple packages.

## Customizations Compared To Upstream Lunar

The fork is materially different from local `upstream/1.x`. A local diff summary shows hundreds of changed files and large insertions/deletions. Agents should inspect this repo directly before assuming upstream behavior.

### Added or Extended Packages

- `packages/ERP`: Magister and Smartbill ERP sync, stock/product/order-status/locality/attribute sync, invoice generation, provider manager.
- `packages/blog`: blog categories/posts, URLs, admin extensions.
- `packages/review`: reviews, review media, reminder emails, admin/order/product/channel extensions.
- `packages/locations`: Romanian-style county/locality models and seed data.
- `packages/mailchimp`: Mailchimp ecommerce/subscriber/cart/order/product sync.
- `packages/shipping`: carrier add-on for AWB, lockers, tracking, Sameday/DPD/Pickup/InHouse.
- `packages/search`: database/Meilisearch/Typesense faceted search abstraction.

### Modified Core Behavior

- Root package name is `lunarphp/lunar-minic`; root `composer.json` replaces local package names.
- Product model includes custom `published_at` handling and product publishing events/listeners.
- Product variants dispatch explicit create/update/delete events.
- Cart session, storefront session, discount manager, and pricing manager bindings differ from typical singleton assumptions.
- Discount defaults are restricted to `AdvancedAmountOff`.
- Cart/order calculations include custom coupon vs non-coupon totals.
- Customer/address tax identifier and VAT-related schema was customized through multiple migrations.
- `AddressCustomerType` is part of core address/order/cart address modeling and is used by shipping methods.
- `Price` has a custom `max_quantity` column.
- Product variant default weight unit was migrated to `kg`.
- Several migrations convert fields to JSONB and add custom indexes.
- `Order::packageWeight` and Smartbill/Shipping logic rely on custom weight and meta conventions.

### Modified Admin Behavior

- Admin panel is branded with Wone assets in `packages/admin/public`.
- Admin uses Filament panel path/id `lunar`, guard `staff`, custom widgets/resources, and a panel extension manager.
- Admin event listeners sync changed models to search by calling `sync_with_search`.
- Add-ons extend admin resources/pages by resolving `lunar-panel` and registering extensions.

### Areas Where Standard Lunar Assumptions Are Unsafe

- Do not assume all upstream discount types are enabled.
- Do not assume product availability only depends on product status.
- Do not assume search uses only Scout indexing; `packages/search` adds separate database/Meilisearch/Typesense query engines.
- Do not assume shipping is table-rate only; carrier AWB/status/meta workflows are present.
- Do not assume order statuses are exhaustive in `packages/core/config/orders.php`; the host publishes a larger set (see Important Statuses and Host application integration).
- Do not assume `OrderPlacedEvent` or Mailchimp `SyncOrderOnPlacement` are auto-registered in this repo; the host dispatches the event and registers listeners in `lunar-frontend`.
- Do not assume `packages/locations` migrations are auto-loaded; the host must migrate/seed counties and load locality SQL.
- Do not assume `lunar.shipping` and `lunar.search` config keys contain only core config; add-ons merge into the same namespaces.

## Architecture and Extension Points

### Service Providers

- `LunarServiceProvider` merges core config, registers manifests, managers, modifiers, session managers, payment/discount/pricing/tax services, observers, commands, state listeners, blueprint macros, and scheduled cart pruning.
- `LunarPanelProvider` registers the Filament admin panel services, admin commands, gate behavior, admin event listeners, Livewire synths, views, translations, and migrations.
- Add-on providers register package config, model directories, morph maps, observers, commands, scheduled jobs, external provider clients, and admin extensions.

### Manifests and Contracts

- `Lunar\Base\ModelManifest` discovers model classes, binds contracts, registers route model bindings, and maps morph aliases.
- Core model contracts live under `packages/core/src/Models/Contracts`.
- Add-ons call `ModelManifest::addDirectory()` to register their model classes.
- Field, attribute, addon, and shipping manifests are central extension points.

### Actions and Pipelines

- Cart actions are configured in `packages/core/config/cart.php`.
- Order creation pipelines are configured in `packages/core/config/orders.php`.
- Pricing pipelines are configured in `packages/core/config/pricing.php`.
- Shipping modifiers are registered through `ShippingModifiers`.
- Payment drivers are registered through `Payments::extend()`.
- Tax drivers are selected through `packages/core/config/taxes.php`.

### Events, Observers, Listeners, Jobs

- Core observers are registered for addresses, cart lines, channels, collections, currencies, customer groups, customers, discounts, languages, orders, order lines, prices, products, product options/values, product variants, transactions, URLs, and media.
- Important core jobs include collection tree/position jobs, product association jobs, currency price sync jobs, tag sync, and `MarkAsNewCustomer`.
- Admin listens to many model-changed events and syncs search indexes.
- ERP uses scheduled sync commands, provider jobs, and an order observer for invoice generation.
- Shipping add-on uses an order observer for AWB generation and scheduled locker sync.
- Mailchimp observes `CartLine` after commit and dispatches cart sync jobs when enabled.

### Queues and Scheduled Tasks

- `lunar:prune:carts` can be scheduled daily from core config.
- ERP schedules product/order-status/stock/locality/attribute sync commands when `lunar.erp.enabled` is true and the relevant provider lists are configured.
- Shipping add-on schedules `lunar:sync-shipping-lockers` daily at 03:00 when shipping and lockers are enabled.
- Review package includes `review:request-email`; scheduling was not found in the package provider.
- Mailchimp sync jobs are queued; package commands can dispatch bulk sync jobs.

## APIs and Integrations

### Internal APIs

- Facades/managers: `Pricing`, `Payments`, `Discounts`, `CartSession`, `StorefrontSession`, `Shipping`, `Taxes`, ERP and search managers.
- Admin extension API: `Lunar\Admin\LunarPanelManager::extensions()` and `callHook()`.
- Search helpers: `sync_with_search()` and `get_search_builder()` in admin support files.
- Config-driven action/pipeline APIs for cart, order creation, pricing, payments, shipping, and taxes.

### External APIs

- Stripe:
  - Webhook route configured by `lunar.stripe.webhook_path`.
  - PaymentIntent retrieval/capture/update and charge persistence.
- Sameday and DPD:
  - AWB, locker/county/city, and tracking-related provider APIs through custom clients.
- Magister:
  - Product, stock, order status, locality, and attribute sync.
- Smartbill:
  - Invoice generation and PDF download.
- Mailchimp:
  - Audience subscriber sync, merge field management, ecommerce store, product, customer, cart, order, and event tracking APIs.
- Search engines:
  - Meilisearch and Typesense integrations through Scout and `packages/search`.
- Nominatim:
  - Shipping service exposes geocoding through Nominatim request classes.

### Synchronization Processes

- Core Scout indexing via `lunar:search:index`.
- Meilisearch setup via `lunar:meilisearch:setup`.
- ERP sync commands:
  - `erp:sync-products`
  - `erp:sync-order-statuses`
  - `erp:sync-stock`
  - `erp:sync-localities`
  - `erp:sync-attributes`
- Mailchimp commands:
  - `mailchimp:create-store`
  - `mailchimp:setup-merge-fields`
  - `mailchimp:sync-all-users`
  - `mailchimp:sync-all-orders`
  - `mailchimp:sync-all-products`
- Shipping command:
  - `lunar:sync-shipping-lockers`
  - `lunar:sync-shipping-counties`
  - `lunar:sync-shipping-cities`
- Review command:
  - `review:request-email`
  - `lunar:seed-review`

## Important Development Conventions

- Follow existing package-local conventions before adding abstractions. Similar behavior is usually already expressed as an action, manager, observer, command, pipeline stage, or service provider extension.
- Use model contracts and `ModelManifest` when adding or replacing models.
- Use action classes for configurable cart/order operations rather than embedding flow logic in controllers.
- Use Laravel pipelines for cart calculation, order creation, and pricing modifications.
- Use observers and events for model side effects, but verify event registration. Some listener classes exist without proven provider registration.
- Use config namespaces under `lunar.*`; check final merged config before relying on a key because add-ons merge into shared namespaces.
- Use queued jobs for external sync and long-running provider operations.
- Use Saloon request/client patterns for ERP, Mailchimp, and carrier integrations.
- Use Filament resources and `lunar-panel` extensions for admin UI changes.
- Use Pest/Testbench tests where possible. Do not create ad hoc verification scripts when existing tests can cover the behavior.
- Preserve custom totals, coupon handling, customer group visibility, shipping thresholds, and order meta conventions when changing checkout logic.

## Known Risks and Technical Debt

- Upstream divergence is large. Synchronizing with upstream Lunar requires careful review of local package additions, migrations, config namespaces, and overwritten behavior.
- Current worktree has a pre-existing local modification in `packages/core/src/Managers/DiscountManager.php`; at analysis time it appeared to be whitespace only.
- `lunar.shipping` is used by core shipping measurement config and the custom shipping add-on provider config. This shared namespace can make final config shape surprising.
- `lunar.search` is used by core indexing config and the custom search add-on. Verify merged config before changing search behavior.
- `packages/locations/LocationsServiceProvider.php` only registers the model directory; it does not load/publish migrations in the provider.
- `OrderPlacedEvent` dispatch and Mailchimp `SyncOrderOnPlacement` registration live in `lunar-frontend`, not in package providers here.
- `CreateShippingLine` appears to read `discount_total` from `$shippingAddress->shippingSubTotal->discountTotal`; this should be reviewed before changing shipping/order total code.
- Mailchimp bulk order sync filters orders by `status = completed`, but core default statuses do not include `completed`.
- `ShippingProviderEnum` contains `fan`, but no FAN provider is implemented or enabled in default/host-documented shipping config (see Host application integration).
- Several add-ons rely on env/config being complete. Enabling ERP/shipping providers with missing provider configuration may break boot or runtime flows.
- Smartbill/Magister/shipping/localities code contains Romanian/RON-specific assumptions and tax naming conventions.
- Review reminder emails are command-based; package-level scheduling was not found.

