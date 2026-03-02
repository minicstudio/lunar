# Upgrade Guide

## What to do at upgrade

1. Update config files if needed
2. Add new env variables (if any)
3. Run migrations
4. Re-publish provider configs if required
5. Run locker sync commands (if using lockers)

## From 0.1.0 to 1.0.0

Initial release of the Lunar Shipping plugin.

### Features

- Multi-provider shipping integration (Sameday, DPD, Pickup, In-house)
- AWB generation and PDF downloads per provider
- Locker support with counties, cities, and lockers sync
- Admin order view integration for AWB display and download
- Provider configuration isolation with separate config files

### Configuration

The following configuration files are available:

```
config/lunar/shipping.php
config/lunar/shipping/sameday.php
config/lunar/shipping/dpd.php
config/lunar/shipping/pickup.php
config/lunar/shipping/inhouse.php
```

### Environment Variables

Global:

```
SHIPPING_ENABLED=true
SHIPPING_LOCKER_ENABLED=true
SHIPPING_CONTACT_EMAIL=ops@example.com
SHIPPING_AWB_GENERATION_STATUS=prepare-shipment
```

Provider specific (examples):

```
SAMEDAY_ENABLED=true
SAMEDAY_BASE_URL=...
SAMEDAY_USERNAME=...
SAMEDAY_PASSWORD=...
SAMEDAY_PICKUP_POINT_ID=...
SAMEDAY_CONTACT_PERSON_ID=...
SAMEDAY_PROVIDER_PAGE_URL=https://sameday.ro/#awb=
SAMEDAY_PDO=false
SAMEDAY_HOME_SHIPPING_ID=7
SAMEDAY_LOCKER_SHIPPING_ID=15

DPD_ENABLED=true
DPD_BASE_URL=...
DPD_USERNAME=...
DPD_PASSWORD=...
DPD_SERVICE_ID=...
DPD_CONTENTS=Books
DPD_PACKAGE=BOX
DPD_PAPER_SIZE=A4

PERSONAL_PICKUP_ENABLED=true
IN_HOUSE_SHIPPING_ENABLED=true
```

### Commands

```
php artisan migrate
php artisan lunar:sync-shipping-counties
php artisan lunar:sync-shipping-cities
php artisan lunar:sync-shipping-lockers
```

### Published Assets

```bash
php artisan vendor:publish --tag="lunar.shipping.config"
php artisan vendor:publish --tag="lunar.shipping.migrations"
```

### Database Tables

- `shipping_provider_credentials`
- `shipping_counties`
- `shipping_cities`
- `shipping_lockers`

### Required Actions

After upgrading, run:

```bash
php artisan vendor:publish --tag="lunar.shipping.config"
php artisan migrate
```

If you use locker shipping, run the sync commands:

```bash
php artisan lunar:sync-shipping-counties
php artisan lunar:sync-shipping-cities
php artisan lunar:sync-shipping-lockers
```

---

## Further Documentation

For detailed information about the Shipping Plugin, features, and usage, see [SHIPPING_PLUGIN.md](SHIPPING_PLUGIN.md).

## Important Notes

- Providers are registered only when `SHIPPING_ENABLED=true` and they are listed in `lunar.shipping.providers`.
- Each listed provider must have a published config file and `enabled=true` in that config.
- Locker sync uses provider APIs; verify credentials before running sync commands.
