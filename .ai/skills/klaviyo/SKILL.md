# Klaviyo Integration

Activate this skill when:

- Changing `packages/klaviyo` (services, jobs, requests, listeners, config, commands)
- Debugging Klaviyo sync failures or queue jobs
- Writing or fixing tests in `tests/klaviyo`

## Before You Start

1. Treat **this repo’s code** as source of truth.
2. Host storefront emits provider-neutral marketing events from `lunar-frontend`; this package only listens and maps to Klaviyo APIs.
3. Product catalog sync is **provider-owned** in this package (product + variant lifecycle events) — do **not** invent a neutral marketing catalog event.
4. Do **not** import `Lunar\Mailchimp\*` from Klaviyo code.
5. Never use live `historical_import` for registration/order subscribe.

## Package Layout

| Area | Path |
|------|------|
| Config | `packages/klaviyo/config/klaviyo.php` → merged as `lunar.klaviyo` |
| Connector | `Connectors/KlaviyoConnector.php` (Saloon, JSON:API media types + `revision`) |
| Requests | `Requests/*` — UpsertProfile, GetProfiles, SubscribeProfiles, CreateEvent, Catalog bulk create/update category/item/variant, `GetBulkCreateCatalogItemsJobRequest`, bulk delete, list |
| Services | `KlaviyoService`, `KlaviyoProfileService`, `KlaviyoOrderService`, `KlaviyoCatalogService` |
| Support | `Support/CatalogExternalIdStore`, `KlaviyoLogger` |
| Jobs | `SubscribeProfileToKlaviyo`, `SyncProfileToKlaviyo`, `TrackEventToKlaviyo`, `SyncOrderToKlaviyo`, `SyncProductToKlaviyo`, `SyncProductsBulkToKlaviyo`, `DeleteCatalogVariantFromKlaviyo`, `SyncAllProductsToKlaviyo` |
| Listeners | Registered in `KlaviyoServiceProvider` on core marketing events + `OrderPlacedEvent` + product/variant lifecycle + optional admin pricing/options/collections/media/urls/discounts |
| Commands | `klaviyo:sync-all-products` |
| Tests | `tests/klaviyo/` (`klaviyo` testsuite in `phpunit.xml`) |

## Architecture

Host emits (marketing):

- `CustomerMarketingConsentGranted` → `SubscribeProfileToKlaviyo` (branches on `MarketingSubscriptionMode`)
- `CustomerMarketingProfileUpdated` → `SyncProfileToKlaviyo` (when `sync_subscribers`)
- `StorefrontMarketingEventOccurred` → `TrackEventToKlaviyo` (when `track_events`; `unique_id` = stable `eventId`; rewrite `product_id` / `product_id_{n}` / `variant_id` to SKU — never leave Lunar DB ids)
- `OrderPlacedEvent` → `SyncOrderToKlaviyo` (when `sync_orders`; `Placed Order` + per-line `Ordered Product`; case-sensitive `ProductID`/`VariantID` = catalog identity)

Core product lifecycle (catalog — package-owned):

- `ProductPublished` → `SyncProductToKlaviyo` (`CREATE`) when `sync_products`
- `ProductUpdatedEvent` → `SyncProductToKlaviyo` (`UPDATE`) when `sync_products`
- `ProductDeleting` capture → `CatalogExternalIdStore` (SKU identity before variants gone)
- `ProductDeletedEvent` → `SyncProductToKlaviyo` (`DELETE`) with captured external ids; unique key `…-delete`
- `ProductVariantCreated/Updated` → parent `UPDATE`
- `ProductVariantDeleted` → `DeleteCatalogVariantFromKlaviyo` + capture SKU; optional parent re-sync
- Optional admin `ProductVariantPricingUpdated` → parent `UPDATE` (variant Pricing relation manager)
- Optional admin `ProductVariantOptionsUpdated` → parent `UPDATE` (Variants table “Save variants”; covers price-only edits that do not dirty the variant model)
- Optional admin `ProductCollectionsUpdated` → parent `UPDATE` (category attach/detach from product or collection admin pages)
- Optional admin `ModelMediaUpdated` → parent `UPDATE` when `$model` is a Product (primary image / gallery)
- Optional admin `ModelUrlsUpdated` → parent `UPDATE` when `$model` is a Product (storefront slug/url)
- Discount catalog parity (Meta/Google): skip coupon discounts; `DiscountUpdatedEvent` → affected products (or full sync if global); limitation attach/detach → related products; first limitation on former global / last limitation removed / global delete → `SyncAllProductsToKlaviyo`
- `klaviyo:sync-all-products` → `SyncAllProductsToKlaviyo` → `syncProductsBulk` per chunk (≤100 products)

### Subscribe modes (`KlaviyoProfileService::subscribe`)

| Mode | List | Bulk Subscribe behavior |
|------|------|-------------------------|
| `ExplicitOptIn` | `list_id` (DOI) | `consent=SUBSCRIBED`, **no** `historical_import` — confirmation email via list DOI |
| `CustomerRegistration` | `automatic_list_id` (single opt-in) | `consent=SUBSCRIBED`, **no** `historical_import`; skip if profile unsubscribed / `UNSUBSCRIBE` / `SPAM_REPORT` / `USER_SUPPRESSED` |

Subscribe upsert always maps `language` from `context.locale` → linked user `locale` → `app()->getLocale()`.

### Catalog (`KlaviyoCatalogService`)

- Item `external_id` = first non-empty variant **SKU** (fallback: product id); variant `external_id` = variant **SKU** (fallback: variant id when no SKU); `/` replaced with `-`.
- Compound Klaviyo ids: `$custom:::$default:::{external_id}`.
- **All catalog upserts** use Klaviyo async bulk jobs — single-item create/update HTTP is forbidden in upsert orchestration.
- **Bulk update does not insert** missing items or variants. HTTP 202 only means the job was accepted; missing resources are not created by update jobs.
- **Create vs update routing** uses **Klaviyo remote state**, not `CatalogExternalIdStore` alone:
  - `resolveCatalogItemSyncContext` → GET `/catalog-items/{id}/relationships/variants/` (`fetchRemoteCatalogItemState`)
  - Item **404** → bulk **create** item (+ all variants via create after item job completes)
  - Item **200** → bulk **update** item; **per variant**: bulk **create** if absent remotely, bulk **update** if present
  - `CatalogExternalIdStore` is for **DELETE capture** and remembering identity after sync — insufficient alone for upsert routing
- **Orchestration order** (`syncProductsBulk`): item bulk create → item bulk update → **poll item bulk create jobs to `complete`** (`GetBulkCreateCatalogItemsJobRequest`) → variant bulk create → variant bulk update → orphan cleanup → `CatalogExternalIdStore::remember`
- **Variant bulk create** payloads include `relationships.item`; **variant bulk update** must **not** include `relationships` (Klaviyo rejects `item` on update).
- **Orphan cleanup:** after upsert, list remote variants; delete orphans not in current Lunar SKU set. Item **404** during list → skip (no orphans). Do not throw.
- Behavioral events: `mapEventProductIdentifiers()` rewrites `product_id` / `product_id_{n}` / `variant_id` / `variant_id_{n}` from Lunar DB ids → SKU (called from `TrackEventToKlaviyo`). Never leave raw Lunar DB ids on Klaviyo event properties when a SKU exists.
- Order events: case-sensitive `ProductID` / `VariantID` = same SKU algorithms as catalog item / variant `external_id`.
- DELETE uses captured external id strings — never re-derive SKU after variants are gone; upsert uniqueness must not discard DELETE.
- Authoritative `isAvailable() === false` may delete; availability exceptions must retry (never coerce to delete).
- Queue workers pin default channel + customer group before availability checks.
- API key needs `catalogs:write`. HTTP: `Accept` / body `Content-Type` = `application/vnd.api+json`.

## Configuration (`lunar.klaviyo`)

| Key | Env | Role |
|-----|-----|------|
| `enabled` | `KLAVIYO_ENABLED` | Master switch |
| `api_key` | `KLAVIYO_API_KEY` | Private API key |
| `api_revision` | `KLAVIYO_API_REVISION` | Default **`2026-01-15`** (not `2024-10-15`) |
| `list_id` | `KLAVIYO_LIST_ID` | ExplicitOptIn DOI list |
| `automatic_list_id` | `KLAVIYO_AUTOMATIC_LIST_ID` | CustomerRegistration single opt-in list |
| `sync_subscribers` | `KLAVIYO_SYNC_SUBSCRIBERS` | Profile updates |
| `sync_orders` | `KLAVIYO_SYNC_ORDERS` | Placed Order + Ordered Product |
| `sync_products` | `KLAVIYO_SYNC_PRODUCTS` | Catalog sync |
| `catalog.default_category_external_id` | `KLAVIYO_CATALOG_DEFAULT_CATEGORY` | Fallback category |
| `track_events` | `KLAVIYO_TRACK_EVENTS` | Behavioral events (default true) |
| `queue_connection` | `KLAVIYO_QUEUE_CONNECTION` | Default **`deferred`** for profiles, subscribe, events, orders, and single-product catalog lifecycle sync. Batch backfill / discount re-syncs use the app default queue (bare `dispatch()`) |
| `profile_attributes` | — | Neutral property → Klaviyo attribute map |
| `retry.*` | | Same shape as Mailchimp |

## Queue connections

| Path | Connection |
|------|------------|
| Profiles, subscribe, storefront events, orders | `lunar.klaviyo.queue_connection` (default `deferred`) via `dispatch(...)->onConnection(config(...))` |
| Single-product catalog from product/variant/admin lifecycle | same — `dispatch(...)->onConnection(...)` → `SyncProductToKlaviyo` → bulk jobs (size 1) |
| `SyncAllProductsToKlaviyo` | application default queue — calls `syncProductsBulk` per chunk (no per-product fan-out) |
| Discount-driven `ResolvesDiscountables` re-syncs | application default queue — `SyncProductsBulkToKlaviyo` in chunks of ≤100 |
| Catalog wipe (`klaviyo:delete-all-products`) | sync Saloon `BulkDeleteCatalogItemsRequest` in command (not a Laravel queue job) |

`KlaviyoServiceProvider` registers `queue.connections.deferred` when missing (same pattern as lunar-frontend GTM).

## Idempotency

- Behavioral events: API `unique_id` = job/event `eventId` (set once; never regenerate in `handle()`).
- Placed Order: `ShouldBeUnique` `klaviyo-order-sync-{id}` **and** `unique_id = (string) order.id`.
- Ordered Product: `unique_id = order:{orderId}:line:{lineId}`.
- Catalog upsert: `ShouldBeUnique` `klaviyo-product-sync-{id}`.
- Catalog delete: `ShouldBeUnique` `klaviyo-product-sync-{id}-delete` (separate from upsert).
- Catalog jobs (`SyncProductToKlaviyo`, `DeleteCatalogVariantFromKlaviyo`) implement `ShouldQueueAfterCommit` so admin variant saves inside a DB transaction cannot race uncommitted soft-deletes (upsert recreating a just-deleted remote variant).

## Artisan

```bash
php artisan klaviyo:sync-all-products --chunk=100
php artisan klaviyo:delete-all-products --force
```

`sync-all-products` requires `KLAVIYO_ENABLED=true` and `KLAVIYO_SYNC_PRODUCTS=true`.
`delete-all-products` requires `KLAVIYO_ENABLED=true`; lists remote catalog items then spawns Klaviyo bulk-delete jobs (variants deleted with parent items).
## Excluded

Abandoned cart, store creation, Filament credential UI, Mailchimp-style 404 heal loop, provider-neutral catalog marketing events, live `historical_import` for consent.
