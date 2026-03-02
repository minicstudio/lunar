# Upgrade Guide

## What to do at upgrade

1. Update config files if needed
2. Add new env variables (if any)
3. Run migrations
4. Re-publish provider configs if required
5. Review sync schedule and feature mapping

## From 0.1.0 to 1.0.0

Initial release of the Lunar ERP plugin.

### Features

- Multi-provider ERP integration (Magister, Smartbill)
- Sync products, order statuses, stock, localities, and attributes
- Send orders to ERP providers
- Billing and invoice generation with PDF downloads
- Scheduled syncs via configurable cron expressions
- Admin order view integration for invoice download

### Configuration

The following configuration files are available:

```
config/lunar/erp.php
config/lunar/erp/magister.php
config/lunar/erp/smartbill.php
```

### Environment Variables

Global:

```
ERP_ENABLED=true
ERP_SYNC_PRODUCTS_SCHEDULE=*/10 * * * *
ERP_SYNC_ORDERS_SCHEDULE=*/5 * * * *
ERP_SYNC_STOCK_SCHEDULE=*/1 * * * *
ERP_SYNC_LOCALITIES_SCHEDULE=0 0 * * 0
ERP_SYNC_ATTRIBUTES_SCHEDULE=*/9 * * * *
```

Provider specific (examples):

```
MAGISTER_ENABLED=true
MAGISTER_BASE_URL=...
MAGISTER_APP_ID=...
MAGISTER_SHOP_ID=...

SMARTBILL_ENABLED=true
SMARTBILL_BASE_URL=...
SMARTBILL_EMAIL=...
SMARTBILL_TOKEN=...
SMARTBILL_COMPANY_VAT_CODE=...
SMARTBILL_SERIES_NAME=...
SMARTBILL_MEASURING_UNIT_NAME=pcs
SMARTBILL_SAVE_TO_DB=false
```

### Commands

```
php artisan erp:sync-products
php artisan erp:sync-order-statuses
php artisan erp:sync-stock
php artisan erp:sync-localities
php artisan erp:sync-attributes
```

### Published Assets

```bash
php artisan vendor:publish --tag="lunar.erp.config"
php artisan vendor:publish --tag="lunar.erp.migrations"
```

### Database Tables and Columns

- `erp_sync_logs`
- `erp_sync_temp`
- `product_variants.erp_id`
- `counties`
- `localities`

### Dependencies

- `Lunar\Locations` package provides the `County` and `Locality` models.
  - Models available at: `Lunar\\Locations\\Models\\County`, `Lunar\\Locations\\Models\\Locality`

### Required Actions

After upgrading, run:

```bash
php artisan vendor:publish --tag="lunar.erp.config"
php artisan migrate
```

Review and adjust the provider feature mapping:

```php
// config/lunar/erp.php
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
```

---

## Further Documentation

For detailed information about the ERP Plugin, features, and usage, see [ERP_PLUGIN.md](ERP_PLUGIN.md).

## Important Notes

- Providers are registered only when `ERP_ENABLED=true` and they are listed in `lunar.erp.providers`.
- Each listed provider must have a published config file and `enabled=true` in that config.
- Localities sync expects Romania (`countries.iso2 = RO`) to exist in the database.
- Attributes sync creates product options and option values using the `ro` locale.
