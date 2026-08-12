# ERP Plugin

The Lunar ERP plugin provides a multi-provider ERP integration layer for product, stock, order status, and billing workflows. It supports scheduled sync commands, provider-specific configuration, and admin order actions such as invoice downloads.

## Overview

The ERP plugin extends your application with ERP integrations:

- Multi-provider ERP support (Magister, Smartbill)
- Sync products, order statuses, stock, localities, and attributes
- Send orders to ERP providers and generate invoices
- Scheduled syncs via configurable cron expressions
- Admin order view integration for invoice download
- Provider configuration isolation with separate config files

## Installation

### 1. Publish configuration and migrations

```bash
php artisan vendor:publish --tag="lunar.erp.config"
php artisan vendor:publish --tag="lunar.erp.migrations" # not required, migrations are auto-discovered
```

### 2. Run migrations

```bash
php artisan migrate
```

### 3. Enable the plugin and providers

In `config/lunar/erp.php`:

```php
return [
    'enabled' => env('ERP_ENABLED', false),
    'providers' => [
        'magister',
        'smartbill',
    ],
    'schedule' => [
        'products' => env('ERP_SYNC_PRODUCTS_SCHEDULE', '*/10 * * * *'),
        'orders' => env('ERP_SYNC_ORDERS_SCHEDULE', '*/5 * * * *'),
        'stock' => env('ERP_SYNC_STOCK_SCHEDULE', '*/1 * * * *'),
        'localities' => env('ERP_SYNC_LOCALITIES_SCHEDULE', '0 0 * * 0'),
        'attributes' => env('ERP_SYNC_ATTRIBUTES_SCHEDULE', '*/9 * * * *'),
    ],
    'sync' => [
        'products' => ['magister'],
        'orders' => ['magister'],
        'stock' => ['magister'],
        'localities' => ['magister'],
        'attributes' => ['magister'],
    ],
    'actions' => [
        'send_order' => ['magister'],
        'billing' => ['smartbill'],
    ],
];
```

Set the environment variables you need:

```env
ERP_ENABLED=true
```

### 4. Configure providers

Each provider has a separate config file published into:

- `config/lunar/erp/magister.php`
- `config/lunar/erp/smartbill.php`

Enable and configure each provider you use.

## Providers

### Magister

Magister supports product, stock, and order status sync, plus localities and attributes.

Key settings (from `config/lunar/erp/magister.php`):

```env
MAGISTER_ENABLED=true
MAGISTER_BASE_URL=...
MAGISTER_APP_ID=...
MAGISTER_SHOP_ID=...
```

### Smartbill

Smartbill provides billing and invoice generation with PDF downloads.

Key settings (from `config/lunar/erp/smartbill.php`):

```env
SMARTBILL_ENABLED=true
SMARTBILL_BASE_URL=...
SMARTBILL_EMAIL=...
SMARTBILL_TOKEN=...
SMARTBILL_COMPANY_VAT_CODE=...
SMARTBILL_SERIES_NAME=...
SMARTBILL_MEASURING_UNIT_NAME=pcs
SMARTBILL_SAVE_TO_DB=false
```

## Sync and Actions

### Sync Features

Enable sync features per provider in `config/lunar/erp.php`:

```php
'sync' => [
    'products' => ['magister'],
    'orders' => ['magister'],
    'stock' => ['magister'],
    'localities' => ['magister'],
    'attributes' => ['magister'],
],
```

### Action Features

Enable actions per provider in `config/lunar/erp.php`:

```php
'actions' => [
    'send_order' => ['magister'],
    'billing' => ['smartbill'],
],
```

## Scheduling

The ERP service provider schedules sync commands using the cron expressions from `config/lunar/erp.php`.

Default schedules:

- Products: every 10 minutes
- Orders: every 5 minutes
- Stock: every 1 minute
- Localities: weekly on Sunday at 00:00
- Attributes: every 9 minutes

## Order Sync Retry, Failure & Manual Resend

`Lunar\ERP\Listeners\SendOrderToERP` is a queued listener on `OrderPlacedEvent` (registered by the host app's `listeners.php`). It retries transparently before surfacing a failure to admins:

- `tries = 3`, `backoff = [60, 300, 900]` seconds (1 min, 5 min, 15 min).
- On final failure (`failed()`), the order is stashed and moved to `status = failed-erp-sync`:
  - `status_before_erp_failure` is saved to `order.meta` — the last "real" status before any `invalid-address`/`failed-erp-sync` chain (found via `Order::getActivitiesByStatuses()`), so a later resend can restore it.
  - The exception is reported (`report($exception)`).

### Manual resend

When an order is `failed-erp-sync`, the Filament order view shows a **"Resend to ERP"** action (`Lunar\ERP\Filament\Actions\ResendOrderToErpAction`, added by `ShippingExtension::headerActions()`):

1. Re-validates the shipping address against seeded localities (`Lunar\Locations\Support\LocalityValidator` — see `packages/locations/README.md`). If invalid, the order is set to `invalid-address` then `failed-erp-sync` again, with a notification asking the admin to correct the address first.
2. Otherwise, calls `ErpService::sendOrder()` for each enabled provider.
   - **Success:** restores `order.meta['status_before_erp_failure']` as the order status (falls back to `confirmed`), clears the stashed meta key, shows a success notification.
   - **Failure:** stays/returns to `failed-erp-sync`, shows a failure notification. Exceptions are caught and reported, not thrown to the UI.

### Related order statuses

Both statuses are defined in `packages/core/config/orders.php` and are **not** included in the host app's customer-visible status list — the storefront resolves the order-history display back to the last customer-visible status instead of showing these internal ones.

| Status | Meaning |
|--------|---------|
| `invalid-address` | Shipping city/county doesn't match a seeded locality (see `packages/locations`) |
| `failed-erp-sync` | `SendOrderToERP` exhausted its retries, or a manual resend failed |

## Invoice Generation

Invoices are generated when an order status changes to a status listed in the provider config. For Smartbill, this is controlled by `generate_invoice`:

```php
// config/lunar/erp/smartbill.php
'generate_invoice' => ['awaiting-payment'],
```

When an invoice is created, the plugin stores:

- `order.meta['billing_series']`
- `order.meta['billing_number']`

The admin order view shows a "Download invoice PDF" action when those values exist.

## Console Commands

```bash
php artisan erp:sync-products
php artisan erp:sync-order-statuses
php artisan erp:sync-stock
php artisan erp:sync-localities
php artisan erp:sync-attributes
```

Each command prompts you to pick a provider from those enabled and allowed for that feature.

## Services

The primary integration surface is `Lunar\ERP\Services\ErpService`:

```php
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Services\ErpService;

$service = app(ErpService::class);

$service->syncProducts(ErpProviderEnum::magister);
$service->syncStock(ErpProviderEnum::magister);
$service->syncOrderStatuses(ErpProviderEnum::magister);
$service->sendOrder(ErpProviderEnum::magister, $order);

$service->generateInvoice(ErpProviderEnum::smartbill, $order);
$service->downloadInvoicePDF(ErpProviderEnum::smartbill, $order);
```

## Database Schema

The package creates the following tables and columns:

- `lunar_erp_sync_logs` - Sync job logs and status
- `lunar_erp_sync_temp` - Temporary ERP product data
- `lunar_product_variants.erp_id` - ERP product identifier
- `lunar_counties` - ERP localities counties
- `lunar_localities` - ERP localities cities

## Notes

- Providers are registered only when `ERP_ENABLED=true` and they are listed in `lunar.erp.providers`.
- Each listed provider must have a published config file and `enabled=true` in that config.
- Localities sync expects Romania (`countries.iso2 = RO`) to exist in the database.
- County and Locality models are provided by `lunarphp/locations` (`Lunar\\Locations\\Models\\County`, `Lunar\\Locations\\Models\\Locality`).
- Attributes sync creates product options and option values using the `ro` locale.
- Magister's `INVOICE_COUNTRY_CODE`/`DELIVERY_COUNTRY_CODE` use the order's actual billing/shipping address country (falling back to `RO`) rather than being hard-coded.
- `Order::getActivitiesByStatuses()` and the admin `ActivityLogFeed` order by `created_at` then `id` (both descending), so same-second status changes (e.g. a resend that fails immediately) sort deterministically instead of by insertion order.
