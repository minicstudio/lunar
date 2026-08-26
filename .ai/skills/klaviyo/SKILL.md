# Klaviyo Integration

Activate this skill when:

- Changing `packages/klaviyo` (services, jobs, requests, listeners, config, commands)
- Debugging Klaviyo sync failures or queue jobs
- Writing or fixing tests in `tests/klaviyo`

## Before You Start

1. Treat **this repo’s code** as source of truth.
2. Host storefront emits provider-neutral marketing events from `lunar-frontend`; this package only listens and maps to Klaviyo APIs.
3. Product catalog sync is **provider-owned** in this package (listens to core `ProductPublished` / `ProductUpdatedEvent` / `ProductDeletedEvent`) — do **not** invent a neutral marketing catalog event.
4. Do **not** import `Lunar\Mailchimp\*` from Klaviyo code.

## Package Layout

| Area | Path |
|------|------|
| Config | `packages/klaviyo/config/klaviyo.php` → merged as `lunar.klaviyo` |
| Connector | `Connectors/KlaviyoConnector.php` (Saloon, `Klaviyo-API-Key` + `revision` header) |
| Requests | `Requests/*` — UpsertProfile, SubscribeProfiles, CreateEvent, Catalog category/item/variant |
| Services | `KlaviyoService`, `KlaviyoProfileService`, `KlaviyoOrderService`, `KlaviyoCatalogService` |
| Jobs | `SubscribeProfileToKlaviyo`, `SyncProfileToKlaviyo`, `TrackEventToKlaviyo`, `SyncOrderToKlaviyo`, `SyncProductToKlaviyo`, `SyncAllProductsToKlaviyo` |
| Listeners | Registered in `KlaviyoServiceProvider` on core marketing events + `OrderPlacedEvent` + product lifecycle events |
| Commands | `klaviyo:sync-all-products` |
| Tests | `tests/klaviyo/` (`klaviyo` testsuite in `phpunit.xml`) |

## Architecture

Host emits (marketing):

- `CustomerMarketingConsentGranted` → `SubscribeProfileToKlaviyo` (branches on `MarketingSubscriptionMode`)
- `CustomerMarketingProfileUpdated` → `SyncProfileToKlaviyo` (when `sync_subscribers`)
- `StorefrontMarketingEventOccurred` → `TrackEventToKlaviyo` (when `track_events`; `unique_id` = stable `eventId`)
- `OrderPlacedEvent` → `SyncOrderToKlaviyo` (when `sync_orders`; `unique_id` = order id; Placed Order metric only)

Core product lifecycle (catalog — package-owned):

- `ProductPublished` → `SyncProductToKlaviyo` (`CREATE`) when `sync_products`
- `ProductUpdatedEvent` → `SyncProductToKlaviyo` (`UPDATE`) when `sync_products` (skips status→published; handled by `ProductPublished`)
- `ProductDeletedEvent` → `SyncProductToKlaviyo` (`DELETE`) when `sync_products`
- `klaviyo:sync-all-products` → `SyncAllProductsToKlaviyo` → per-product `UPDATE` jobs for available published products

### Subscribe modes (`KlaviyoProfileService::subscribe`)

| Mode | Bulk Subscribe behavior |
|------|-------------------------|
| `ExplicitOptIn` | `consent=SUBSCRIBED`, **no** `historical_import` — list DOI sends confirmation email |
| `CustomerRegistration` | `consent=SUBSCRIBED`, `historical_import=true`, past `consented_at` (UTC `…Z`, clamped ≥5 min ago — Klaviyo rejects near-now) — immediate subscribed, no confirmation |

Subscribe upsert always maps `language` from `context.locale` → linked user `locale` → `app()->getLocale()`. Host should pass `context.locale` on ConsentGranted (newsletter/checkout/registration/order).

`MarketingConsentSource::Order` + `CustomerRegistration` is host-emitted when automatic order subscription policy is on.

### Catalog (`KlaviyoCatalogService`)

- Item `external_id` = first non-empty variant **SKU** (fallback: product id); variant `external_id` = variant id (**must differ**).
- Compound Klaviyo ids: `$custom:::$default:::{external_id}`.
- Ensure categories from collections (or config `catalog.default_category_external_id`).
- Create-or-update on 409/duplicate; unpublished / `!isAvailable()` → delete item (404 = success).
- API key needs `catalogs:write`.

## Configuration (`lunar.klaviyo`)

| Key | Env | Role |
|-----|-----|------|
| `enabled` | `KLAVIYO_ENABLED` | Master switch |
| `api_key` | `KLAVIYO_API_KEY` | Private API key |
| `api_revision` | `KLAVIYO_API_REVISION` | Default `2024-10-15` |
| `list_id` | `KLAVIYO_LIST_ID` | Required for list subscribe |
| `sync_subscribers` | `KLAVIYO_SYNC_SUBSCRIBERS` | Profile updates |
| `sync_orders` | `KLAVIYO_SYNC_ORDERS` | Placed Order metric |
| `sync_products` | `KLAVIYO_SYNC_PRODUCTS` | Catalog sync for product recommendations |
| `catalog.default_category_external_id` | `KLAVIYO_CATALOG_DEFAULT_CATEGORY` | Fallback category (default `uncategorized`) |
| `track_events` | `KLAVIYO_TRACK_EVENTS` | Behavioral events (default true) |
| `profile_attributes` | — | Neutral property → Klaviyo attribute map |
| `retry.*` | | Same shape as Mailchimp |

## Idempotency

- Behavioral events: always send API `unique_id` = job/event `eventId` (set once at construction; never regenerate in `handle()`).
- Placed Order: `ShouldBeUnique` `klaviyo-order-sync-{id}` **and** `unique_id = (string) order.id`.
- Catalog product: `ShouldBeUnique` `klaviyo-product-sync-{id}`; upsert by stable external ids.

## Artisan

```bash
php artisan klaviyo:sync-all-products --chunk=100
```

Requires `KLAVIYO_ENABLED=true` and `KLAVIYO_SYNC_PRODUCTS=true`.

## Excluded

Abandoned cart, store creation, Filament credential UI, Mailchimp-style 404 heal loop, provider-neutral catalog marketing events.
