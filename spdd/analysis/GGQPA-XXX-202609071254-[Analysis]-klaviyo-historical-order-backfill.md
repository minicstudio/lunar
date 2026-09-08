# SPDD Analysis: Klaviyo Historical Order Backfill Command

## Original Business Requirement

We need to add a new Laravel command to our webshop engine for backfilling historical order data to Klaviyo.

## Context

We are migrating from Mailchimp to Klaviyo.

The Mailchimp → Klaviyo integration successfully syncs customer profiles, but it does **not** migrate historical e-commerce orders. We need to implement a new command that can send historical orders from our webshop database to Klaviyo.

The webshop is a custom Laravel application.

## Task

Start with an **SPDD analysis only**. Do not implement anything yet.

Analyze the existing codebase and determine the best approach for adding a command that backfills historical orders to Klaviyo.

Please investigate:

1. **Existing Klaviyo integration**

   * How does the application currently communicate with Klaviyo?
   * Are there existing services, jobs, events, API clients, or commands?
   * How are new orders currently sent to Klaviyo, if at all?

2. **Order data model**

   * Identify the relevant Order and Order Item models.
   * Determine which fields are available for:

     * customer identification
     * order ID
     * order number
     * order total
     * currency
     * order timestamp
     * products/items
     * quantities
     * prices
     * discounts
     * shipping
     * taxes
     * order status

3. **Existing event tracking**

   * Check whether the application already sends Klaviyo events such as:

     * `Placed Order`
     * `Ordered Product`
   * Determine the exact event payload structure currently used.

4. **Klaviyo requirements**

   * Research the current Klaviyo API requirements for importing historical order events.
   * Determine the recommended approach for:

     * historical event timestamps
     * event uniqueness/idempotency
     * `Placed Order`
     * `Ordered Product`
     * associating events with existing profiles
     * API rate limits and batching

5. **Command design**
   Propose how the new Artisan command should work, including:

   * command signature and arguments/options
   * date range filtering
   * selecting eligible orders
   * processing large datasets safely
   * chunking/queueing
   * retry behavior
   * progress reporting
   * dry-run support
   * preventing duplicate Klaviyo events
   * resumability if the command fails halfway through

6. **Data integrity and edge cases**
   Consider:

   * guest orders
   * deleted or missing customers
   * cancelled orders
   * refunded orders
   * partially refunded orders
   * test orders
   * orders that may already exist in Klaviyo
   * historical orders created before the Klaviyo integration existed

## Important

Do not make assumptions.

Base the analysis on:

* the actual existing codebase
* the actual database models and relationships
* the current Klaviyo integration
* current official Klaviyo documentation/API documentation where necessary

Before proposing a solution, identify any existing infrastructure that should be reused rather than duplicated.

## Expected output

Provide a structured SPDD analysis containing:

1. Current state of the codebase
2. Relevant existing architecture
3. Order data mapping
4. Klaviyo API requirements
5. Proposed command design
6. Proposed implementation approach
7. Risks and edge cases
8. Open questions / decisions that need confirmation

Do not implement the command yet. The goal of this phase is to fully understand the existing system and produce a concrete implementation plan.

---

## Scope Refinement (stakeholder decision)

**Locked decision (2026-09-07):** Keep it simple. Ship a one-shot Artisan command that syncs the **current placed orders** in the database to Klaviyo. Operator intends to **run it once, now**.

**Out of scope for this delivery:**

- Date-range / status allow-deny filters
- Dry-run / resume (`--after-id`) / coordinator job fan-out
- Bulk Create Events API
- API revision bump solely for `backfill`
- Payload enrichment (tax, shipping, discounts, `reference`)
- Cancelled / Refunded Order metrics
- New DB sync ledger

**In scope:**

- `klaviyo:sync-all-orders` mirroring the simplicity of `mailchimp:sync-all-orders`
- Reuse existing `KlaviyoOrderService::syncPlacedOrder` payload + unique_ids
- Send Create Event `time` from `order.placed_at` so chronology is correct
- Chunked inline processing with progress reporting

---

## Domain Concept Identification

### Current State of the Codebase (grounded)

This monorepo is a custom Lunar PHP ecommerce engine (`lunarphp/lunar-minic`) with a dedicated **`packages/klaviyo`** Saloon-based integration already shipping profiles, consent, behavioral events, live order placement sync, and catalog sync. Sibling packages include `packages/mailchimp` (which already has `mailchimp:sync-all-orders`) and `packages/ERP` (owns `OrderPlacedEvent`).

**Klaviyo communication stack (existing):**

| Layer | What exists |
|-------|-------------|
| HTTP | `KlaviyoConnector` → `https://a.klaviyo.com/api`, Saloon, revision default `2026-01-15`, `Authorization: Klaviyo-API-Key …`, JSON:API media types |
| Events API | `CreateEventRequest` → `POST /events/` |
| Profiles | `UpsertProfileRequest`, `GetProfilesRequest`, `SubscribeProfilesRequest` |
| Catalog | Bulk create/update/delete catalog jobs + synchronous PATCH for lifecycle updates |
| Config | `lunar.klaviyo` / `KLAVIYO_*` including `enabled`, `sync_orders`, metric name overrides, queue connection, retry |
| Gates | `KlaviyoAvailability::orderSyncEnabled()` = enabled ∧ sync_orders |

**How new orders reach Klaviyo today:**

1. Host/checkout fires `Lunar\ERP\Events\OrderPlacedEvent`
2. `SyncOrderOnPlacement` (registered in `KlaviyoServiceProvider`) gates on `orderSyncEnabled()`
3. Dispatches `SyncOrderToKlaviyo` on `lunar.klaviyo.queue_connection` (default `deferred`)
4. Job (`ShouldBeUnique` id `klaviyo-order-sync-{orderId}`, retries from config) calls `KlaviyoOrderService::syncPlacedOrder`
5. Service emits **one** `Placed Order` event + **one** `Ordered Product` per product line via `KlaviyoProfileService::trackEvent`

There is **no** Klaviyo order backfill Artisan command today. Existing commands: `klaviyo:sync-all-products`, `klaviyo:delete-all-products`.

**Sibling to mirror for this one-shot:** `mailchimp:sync-all-orders` — synchronous chunk loop, progress bar, success/failure summary. Adapt eligibility to Lunar reality (`placed_at` present), not Mailchimp’s `status = completed` (default Lunar statuses do not define `completed`).

**Relevant prior SPDD:** `spdd/analysis/GGQPA-XXX-202608251326-[Analysis]-klaviyo-alongside-mailchimp.md` and prompts under `spdd/prompt/` establishing order metric casing, catalog identity, and forbidding live subscribe `historical_import` (unrelated to event `time`).

#### Existing Concepts (from codebase)

- **KlaviyoOrderService**: Builds and sends Placed Order + Ordered Product for a Lunar `Order` — **primary reuse target**.
- **SyncOrderToKlaviyo**: Queued unique job for live placement — **not required for the one-shot command**; command can call the service directly (Mailchimp pattern).
- **SyncOrderOnPlacement / OrderPlacedEvent**: Live placement path only; one-shot command must not re-fire placement.
- **KlaviyoProfileService::trackEvent**: Create Event client; supports `unique_id`, `value`, `value_currency`, profile email — **does not currently set `time`**.
- **CreateEventRequest**: Thin Saloon POST `/events/` wrapper.
- **Order / OrderLine / OrderAddress**: Core Lunar models holding ecommerce data (see mapping below).
- **Catalog identity (KlaviyoCatalogService)**: ProductID/VariantID for order events match catalog `external_id` algorithms — already enforced in order service.
- **Mailchimp SyncAllOrdersToMailchimpCommand**: Closest UX template for a one-time operator run.

#### New Concepts Required

- **`klaviyo:sync-all-orders` command**: One-shot operator entry point to sync all currently placed orders.
- **Event `time` from `placed_at`**: Minimal extension so historical chronology is correct in Klaviyo (shared with live sync if the same service path is updated).

#### Order Data Mapping (Lunar → Klaviyo concepts)

| Business need | Lunar source | Used by current Klaviyo order sync? |
|---------------|--------------|-------------------------------------|
| Customer identification | `user.email` if `user_id`; else `billingAddress.contact_email` | Yes (`resolveOrderEmail`) |
| Order ID | `orders.id` | Yes as `OrderId` + Placed Order `unique_id` |
| Order number | `orders.reference` | **No** (available but unused — leave unused) |
| Order total | `orders.total` (Price cast) → decimal | Yes as event `value` |
| Currency | `orders.currency_code` / `currency` relation | Yes as `value_currency` |
| Order timestamp | `orders.placed_at` | **Available; not sent as event `time` today — must add** |
| Order status | `orders.status` | Not sent; not used for eligibility in this simplified scope |
| Products/items | `productLines` (`type != shipping`) → purchasable morph | Yes |
| Quantities | `order_lines.quantity` | Yes |
| Unit / row prices | Derived unit from `total/quantity`; `RowTotal` from line total | Yes |
| Discounts / shipping / taxes | Present on order/lines | Unused — keep unused for parity |
| Guest vs registered | nullable `user_id` / `customer_id` | Email resolution covers guests |
| Refunds | `transactions` type `refund` | Out of scope |
| Catalog ProductID / VariantID | Catalog external_id via `KlaviyoCatalogService` | Yes (case-sensitive) |

**Current live event payload (exact structure used today — keep for one-shot):**

- **Placed Order** (default metric name `Placed Order`):
  - `unique_id` = `(string) order.id`
  - `value` / `value_currency` = order total decimal + currency code
  - properties: `OrderId`, `Items[]` (`ProductID`, `VariantID`, `ProductName`, `SKU`, `Quantity`, `ItemPrice`, `RowTotal`), `ItemNames[]`
  - profile: `{ email }`
  - **no `time` today → add `placed_at`**
- **Ordered Product** (default metric name `Ordered Product`), one per product line:
  - `unique_id` = `order:{orderId}:line:{lineId}`
  - `value` = line `RowTotal`
  - properties: `OrderId`, `ProductID`, `VariantID`, `SKU`, `ProductName`, `Quantity`, `ItemPrice`, `RowTotal`
  - **no `time` today → add same `placed_at`**

#### Key Business Rules

- **Reuse live order payload builder** — do not invent a second exporter.
- **Idempotency via `unique_id`** — re-run is safe; Klaviyo keeps the first event for the same profile + metric + unique_id.
- **Email required** — skip/fail per order if neither user email nor billing `contact_email` exists.
- **Eligibility = placed orders** — `whereNotNull('placed_at')` (aligned with `Order::isPlaced()`), not `status = completed`.
- **One-shot, inline** — process in the Artisan process with chunking; no new queue coordinator for this delivery.
- **Consent path untouched** — event create is not Bulk Subscribe; never use subscribe `historical_import`.

#### Conceptual relationships

- Order owns OrderLines and OrderAddresses; optional User/Customer.
- Live path: Order placement event → job → order service → Events API.
- One-shot path: Artisan command → order service (same) → Events API with `time = placed_at`.

---

## Strategic Approach

### Solution Direction

Add a minimal **`klaviyo:sync-all-orders`** command in `packages/klaviyo`, modeled on `mailchimp:sync-all-orders`: gate on Klaviyo enabled + `sync_orders`, select all orders with `placed_at`, chunk, call **`KlaviyoOrderService::syncPlacedOrder`**, show a progress bar and a short success/failure summary.

Extend `trackEvent` / `syncPlacedOrder` only enough to set Create Event **`time`** from `placed_at` (so this one-time import is chronologically correct). Keep the existing property payload and unique_id scheme unchanged.

High-level data flow:

**`php artisan klaviyo:sync-all-orders` → query placed orders → chunk → `KlaviyoOrderService::syncPlacedOrder` → Create Event (`Placed Order` + `Ordered Product`) with `time`.**

### Klaviyo API Requirements (relevant to this simple delivery)

| Concern | Requirement for this command |
|---------|------------------------------|
| Endpoint | Existing `POST /api/events` via `CreateEventRequest` |
| Timestamp | Set `attributes.time` to ISO 8601 of `placed_at` (without it, Klaviyo uses request time — wrong for past orders) |
| Metrics | Existing `Placed Order` + per-line `Ordered Product` |
| Profile | Email on event profile attributes (existing) |
| Idempotency | Existing stable `unique_id`s |
| Rate limits | Create Event: burst 350/s, steady 3500/m — fine for a one-time chunked run |
| Flow suppression | `backfill: true` exists on newer Events API docs (emphasized with revision **2026-07-15**) — **deferred** for this simple delivery; operator may briefly pause Placed Order flows in Klaviyo UI during the one-time run if needed |
| Bulk Create Events | **Not used** |

### Proposed Command Design

**Signature:**

```text
klaviyo:sync-all-orders
  {--chunk=50 : Number of orders to process at a time}
```

**Gates:** Fail unless `KlaviyoAvailability::enabled()` and `syncOrders()`.

**Eligible orders:** `Order::query()->whereNotNull('placed_at')`.

**Processing:** Synchronous (inline in the command), like Mailchimp:

1. Count eligible orders; exit early if zero.
2. Confirm proceed (default yes; respects `--no-interaction`).
3. Eager-load `user`, `billingAddress`, `currency`, `productLines.purchasable.product.variants`.
4. `chunk($chunkSize)` → foreach → `syncPlacedOrder`; catch per-order failures; advance progress bar.
5. Print summary table (total / success / failed) and up to ~10 error rows.

**Duplicates:** Existing `unique_id`s — safe if re-run accidentally.

**Resumability:** Not built in. If the run fails halfway, re-run the same command; already-synced orders are no-ops at Klaviyo via unique_id. (Optional manual recovery: operator could filter by id later if ever needed — not in v1.)

**Dry-run / date filters / queue coordinator:** Not in scope.

### Key Design Decisions

- **Simple Mailchimp-style sync command** → **Chosen** for a one-time “run it now” operator need.
- **Reuse `KlaviyoOrderService`** → **Chosen**; do not duplicate payload logic.
- **Call service from the command** (not fan-out `SyncOrderToKlaviyo`) → **Chosen** for interactive progress and zero dependency on queue workers for this one-shot.
- **Eligibility = `placed_at` not null** → **Chosen**; do not copy Mailchimp `status = completed`.
- **Add Create Event `time` from `placed_at`** → **Chosen**; required for correct history.
- **Skip `backfill` flag / revision bump for now** → **Chosen** for simplicity; document optional UI flow pause.
- **Keep current event property set** → **Chosen**; no enrichment.

### Alternatives Considered

- **Full queued coordinator + date/status/dry-run/resume options:** Rejected — overbuilt for a one-time run.
- **Bulk Create Events API:** Rejected — unnecessary complexity for this delivery.
- **Re-dispatch `OrderPlacedEvent`:** Rejected — would trigger ERP and other listeners.
- **Hardcode `status = completed` like Mailchimp:** Rejected — incorrect for default Lunar statuses.

### Proposed Implementation Approach (when implementing later)

1. Extend `KlaviyoProfileService::trackEvent` with optional `?DateTimeInterface $time = null`; when set, add `attributes.time` (ISO 8601).
2. Update `KlaviyoOrderService::syncPlacedOrder` to pass `$order->placed_at` as `time` for both Placed Order and Ordered Product events (also improves live sync chronology).
3. Add `SyncAllOrdersToKlaviyoCommand` (`klaviyo:sync-all-orders --chunk=50`) mirroring Mailchimp’s chunk/progress/summary UX; register in `KlaviyoServiceProvider`.
4. Tests: command gates; syncs placed orders via service; event payload includes `time` when `placed_at` set; unique_ids unchanged.
5. Brief skill note: new Artisan command.

**Reuse:** `KlaviyoConnector`, `CreateEventRequest`, `KlaviyoProfileService::trackEvent`, `KlaviyoOrderService`, `KlaviyoAvailability`, `KlaviyoLogger`, catalog ID resolvers. Do **not** add Bulk Events requests, coordinator jobs, or config flags beyond existing `enabled` / `sync_orders`.

---

## Risk & Gap Analysis

### Requirement Ambiguities (resolved vs remaining)

| Topic | Status |
|-------|--------|
| Scope = one-shot sync of current placed orders | **Resolved** |
| No date/status/dry-run/resume/bulk API | **Resolved** |
| Cancelled / refunded / test-order special handling | **Accepted as-is** — included if `placed_at` is set; no extra filters |
| Payload enrichment | **Resolved** — keep live parity |
| `backfill` / revision bump | **Deferred** — optional operator pause of flows during run |

### Edge Cases (accepted behavior for this delivery)

- **Guest orders:** Sync via billing `contact_email`; fail/skip if missing email.
- **Missing user/customer:** Still sync if billing email exists.
- **Cancelled / refunded with `placed_at`:** Still synced as Placed Order (original totals); no Refunded Order events.
- **Already in Klaviyo:** unique_id makes re-send a no-op.
- **Orders before Klaviyo existed:** Handled by setting `time` = `placed_at`.
- **Test orders:** No core flag — included if placed; no special filter.

### Technical Risks

- **Missing `time` if skipped:** Would stamp all history as “now” — must implement the small `trackEvent` extension.
- **Flow re-trigger:** Without `backfill`, Placed Order flows may fire; mitigate by pausing those flows in Klaviyo for the duration of the one-time run if automations are live.
- **Long Artisan run:** Chunk to limit memory; Create Event rate limits are generous for a one-time pass.
- **Partial failure:** Command continues per-order; summary lists failures; re-run is idempotent via unique_id.
- **Idempotency first-wins:** If a bad payload lands first, re-run will not overwrite — keep payload identical to live sync.

### Acceptance Criteria Coverage

| AC# | Description | Addressable? | Gaps/Notes |
|-----|-------------|--------------|------------|
| 1 | Analyze existing Klaviyo integration | Yes | Live path exists; no order backfill command yet |
| 2 | Map Order / OrderLine fields | Yes | Only current live fields used |
| 3 | Confirm Placed Order / Ordered Product payload | Yes | Add `time` only |
| 4 | Klaviyo historical import requirements | Yes | `time` + `unique_id`; `backfill` deferred |
| 5 | Propose simple command design | Yes | Mailchimp-like one-shot |
| 6 | Edge cases considered | Yes | Accepted defaults; no extra filters |
| 7 | Prefer reuse | Yes | Reuse order service |
| 8 | Analysis only — no implementation yet | Yes | This document only |

### Open Questions / Decisions Needing Confirmation

None blocking for the simplified delivery. Optional ops note only:

1. Will the operator pause Placed Order–triggered flows in Klaviyo during the one-time run? (Recommended if those flows are active.)

---

## Appendix: Search Concepts Used (exploration scope)

Domain nouns: Klaviyo, Order, OrderLine, Placed Order, Ordered Product, Mailchimp sync-all-orders.  
Actions: trackEvent, syncPlacedOrder, Create Event.  
Surfaces: `POST /api/events`, `OrderPlacedEvent`, `klaviyo:sync-all-*`.  
Boundary: host-specific status customizations; lunar-frontend placement wiring (outside this repo).
