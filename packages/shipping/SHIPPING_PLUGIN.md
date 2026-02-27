# Shipping Plugin

The Lunar Shipping plugin provides a multi-provider shipping integration for Lunar orders, including AWB generation, provider-specific configuration, and optional locker support. It integrates with the Lunar admin order view to show AWB information and allow PDF downloads.

## Overview

The Shipping plugin extends your application with shipping provider integrations:

- Multi-provider shipping support (Sameday, DPD, Pickup, In-house)
- AWB generation and PDF downloads per provider
- Optional locker support (counties, cities, lockers) with sync commands
- Admin panel order view enhancements (AWB display and download action)
- Provider configuration isolation with separate config files

## Installation

### 1. Publish configuration and migrations

```bash
php artisan vendor:publish --tag="lunar.shipping.config"
php artisan vendor:publish --tag="lunar.shipping.migrations"
```

### 2. Run migrations

```bash
php artisan migrate
```

### 3. Enable the plugin and providers

In `config/lunar/shipping.php`:

```php
return [
    'enabled' => env('SHIPPING_ENABLED', false),
    'locker_enabled' => env('SHIPPING_LOCKER_ENABLED', false),
    'contact_recipients' => explode(',', env('SHIPPING_CONTACT_EMAIL', '')),
    'providers' => [
        'sameday',
        'dpd',
        'pickup',
        'inhouse',
    ],
];
```

Set the environment variables you need:

```env
SHIPPING_ENABLED=true
SHIPPING_LOCKER_ENABLED=true
SHIPPING_CONTACT_EMAIL=ops@example.com
SHIPPING_AWB_GENERATION_STATUS=prepare-shipment
```

### 4. Configure providers

Each provider has a separate config file published into:

- `config/lunar/shipping/sameday.php`
- `config/lunar/shipping/dpd.php`
- `config/lunar/shipping/pickup.php`
- `config/lunar/shipping/inhouse.php`

Set the provider-specific environment variables and mark `enabled` for each provider you use.

## Providers

### Sameday

The Sameday provider supports AWB generation and locker data sync.

Key settings (from `config/lunar/shipping/sameday.php`):

```env
SAMEDAY_ENABLED=true
SAMEDAY_BASE_URL=https://api.sameday.ro
SAMEDAY_USERNAME=...
SAMEDAY_PASSWORD=...
SAMEDAY_PICKUP_POINT_ID=...
SAMEDAY_CONTACT_PERSON_ID=...
SAMEDAY_PROVIDER_PAGE_URL=https://sameday.ro/#awb=
SAMEDAY_PDO=false
SAMEDAY_HOME_SHIPPING_ID=7
SAMEDAY_LOCKER_SHIPPING_ID=15
```

### DPD

The DPD provider supports AWB generation and PDF downloads.

Key settings (from `config/lunar/shipping/dpd.php`):

```env
DPD_ENABLED=true
DPD_BASE_URL=...
DPD_USERNAME=...
DPD_PASSWORD=...
DPD_SERVICE_ID=...
DPD_CONTENTS=Books
DPD_PACKAGE=BOX
DPD_PAPER_SIZE=A4
```

### Pickup (Personal pickup)

Pickup is a local, no-API shipping provider. Enable it to allow in-store pickup.

```env
PERSONAL_PICKUP_ENABLED=true
```

### In-house

In-house shipping is a local, no-API provider for internal couriers.

```env
IN_HOUSE_SHIPPING_ENABLED=true
```

## Locker Support

Locker support is optional and controlled by `SHIPPING_LOCKER_ENABLED`. When enabled, the plugin can sync counties, cities, and lockers from supported providers.

### Commands

```bash
php artisan lunar:sync-shipping-counties
php artisan lunar:sync-shipping-cities
php artisan lunar:sync-shipping-lockers
```

### Schedule

The package schedules locker sync daily at 03:00 when locker support is enabled.

## AWB Generation

The plugin automatically generates AWBs when an order moves to a configured status (default: `prepare-shipment`) and the order does not already have an AWB in `order.meta['awb']`.

- Generated AWB numbers are stored in `order.meta['awb']`
- The order view in the admin panel shows the AWB and shipping method
- A "Download AWB PDF" action is available when an AWB exists
- The trigger status can be configured via `SHIPPING_AWB_GENERATION_STATUS` environment variable

## Services and APIs

The main integration surface is `Lunar\Addons\Shipping\Services\ShippingService`:

```php
use Lunar\Addons\Shipping\Enums\ShippingProviderEnum;
use Lunar\Addons\Shipping\Services\ShippingService;

$service = app(ShippingService::class);

$name = $service->getName(ShippingProviderEnum::sameday);
$description = $service->getDescription(ShippingProviderEnum::sameday);

$counties = $service->getCounties(ShippingProviderEnum::sameday);
$cities = $service->getCities(ShippingProviderEnum::sameday, $countyId);
$lockers = $service->getLockers(ShippingProviderEnum::sameday, $countyId, $cityId);
```

## Database Schema

The package creates the following tables:

- `shipping_provider_credentials` - Provider tokens and expiry
- `shipping_counties` - Provider county list (soft deletes)
- `shipping_cities` - Provider city list (soft deletes)
- `shipping_lockers` - Provider locker list (soft deletes)

## Configuration

- `config/lunar/shipping.php` - Global enablement, provider list, locker toggle
- `config/lunar/shipping/{provider}.php` - Provider-specific config

## Console Commands

- `lunar:sync-shipping-counties` - Sync counties for locker shipping
- `lunar:sync-shipping-cities` - Sync cities for locker shipping
- `lunar:sync-shipping-lockers` - Sync locker locations

## Notes

- Providers are registered only when `SHIPPING_ENABLED=true` and they are listed in `lunar.shipping.providers`.
- Each listed provider must have a published config file and `enabled=true` in that config.
- Locker sync relies on provider APIs; ensure credentials are correct before running sync commands.
