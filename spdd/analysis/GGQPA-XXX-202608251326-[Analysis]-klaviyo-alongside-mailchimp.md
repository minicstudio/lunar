# SPDD Analysis: Klaviyo Alongside Existing Mailchimp Marketing Integration

## Original Business Requirement

Analyze the existing Mailchimp integration in this Laravel webshop engine.

I want to add Klaviyo as an additional marketing integration.

Before proposing implementation details:

- Find all existing Mailchimp-related services, actions, jobs, listeners,
  configuration, database models and migrations.
- Identify where customers are subscribed or unsubscribed.
- Identify where customer profile data is synchronized.
- Identify where orders are sent to Mailchimp, if applicable.
- Identify any existing events or domain events that should be reused.
- Identify whether the current implementation is tightly coupled to Mailchimp.
- Determine the smallest safe abstraction that would allow Klaviyo to coexist
  with Mailchimp.

Do not implement anything yet.

Preserve backwards compatibility with the existing Mailchimp integration.

Return:
1. Current architecture
2. Integration touchpoints
3. Recommended abstraction
4. Required database/configuration changes
5. Migration risks
6. Things that must not be changed

---

## Domain Concept Identification

### Existing Concepts (from codebase)

- **Mailchimp package (`packages/mailchimp`)**: Standalone Lunar addon providing Saloon HTTP client, services, queued jobs, one Eloquent observer, one order-placement listener class, Artisan commands, and config under `lunar.mailchimp`. Autoloaded via `Lunar\Mailchimp\MailchimpServiceProvider`. **No database models, migrations, or persistence layer** exist in this package.
- **MailchimpService**: Connector/config facade — API key, list ID, store ID, customer-ID hashing (`md5` of email), store create/get.
- **MailchimpSubscriberService**: Marketing-audience operations — subscribe (double opt-in / re-subscribe), sync subscriber + merge fields, language-only merge sync, track custom member events, merge-field setup/delete.
- **MailchimpEcommerceService**: Ecommerce API operations — product sync/delete, customer sync, order sync (includes customer + optional subscriber merge sync), cart sync/delete, order preference calculation for merge fields.
- **Sync jobs**: `SyncSubscriberToMailchimp`, `SyncCustomerToMailchimp`, `SyncOrderToMailchimp`, `SyncCartToMailchimp`, `SyncProductToMailchimp`, `SyncAllProductsToMailchimp` — all Mailchimp-named, config-gated, retry/backoff from `lunar.mailchimp.retry`.
- **CartLineObserver**: Engine-registered after-commit observer; dispatches cart sync for logged-in carts when `enabled` + `sync_carts`.
- **SyncOrderOnPlacement**: Queued listener for `Lunar\ERP\Events\OrderPlacedEvent`; **not** registered in the package provider — host (`lunar-frontend` `listeners.php`) must wire it.
- **OrderPlacedEvent**: Shared cross-package domain event (lives in ERP package); already used by ERP export and Mailchimp order sync — primary reuse candidate for Klaviyo order sync.
- **ProductEventType**: Core enum (CREATE/UPDATE/DELETE) used by product sync jobs; product job dispatch for live catalog changes is **not** registered inside this engine package (bulk via command; live triggers expected from host/admin if used).
- **TrackRemoveFromCart trait**: Engine-provided helper that calls Mailchimp `trackEvent`; consumed by host Livewire.
- **Host-side triggers (lunar-frontend, outside this repo)**: Email verification / OAuth → subscriber sync; Checkout / ProductView → event tracking; listener registration for order placement. Documented in skill/`MAILCHIMP_PLUGIN.md`; engine code is source of truth for package behavior.
- **Feature flags**: `enabled`, `automatic_subscription`, `sync_subscribers`, `sync_products`, `sync_orders`, `sync_carts`, `track_events`, merge/option field maps — all Mailchimp-prefixed env/config.
- **ERP multi-provider pattern**: Existing precedent for multi-vendor integrations via provider interfaces + config lists (`packages/ERP`), and shipping via `ShippingProviderInterface` — useful analogy, **not** currently applied to marketing.

### New Concepts Required

- **Klaviyo integration**: Sibling marketing destination with its own credentials, sync flags, and API semantics (profiles, events, catalog/orders as applicable to Klaviyo).
- **Marketing lifecycle operations (conceptual)**: Shared business intents — subscribe/opt-in, unsubscribe/opt-out, profile sync, order placed sync, (optional) cart/product/event sync — currently expressed only as Mailchimp-specific jobs/services.
- **Coexistence / dual enablement**: Ability for a shop to run Mailchimp only, Klaviyo only, both, or neither without breaking existing Mailchimp shops.
- **Optional thin marketing fan-out (if adopted)**: A provider-agnostic way for the host to emit one intent and have each enabled destination react — without rewriting Mailchimp internals on day one.

### Key Business Rules

- **Master switch**: When `lunar.mailchimp.enabled` is false, jobs/observer no-op; Klaviyo must have an equivalent independent master switch.
- **Subscribe vs sync**: `subscribe()` uses double opt-in (`pending` / re-confirm for unsubscribed/cleaned). `syncSubscriber*` uses `status_if_new = subscribed` and merge fields. Host `automatic_subscription` controls opt-in policy outside the package.
- **No first-class unsubscribe API in this package**: Unsubscribe is only handled reactively inside Mailchimp resubscribe logic (status `unsubscribed`/`cleaned`). There is no engine job/command that unsubscribes a customer from Mailchimp.
- **Order sync path**: On placement → sync ecommerce customer → optionally sync subscriber merge fields (prefs/language) → PUT order → delete ecommerce cart if `cart_id` present.
- **Guest vs registered**: Guest orders use billing `contact_email`; registered use user email. Customer IDs for Mailchimp ecommerce are email MD5 hashes.
- **Cart sync**: Authenticated carts only; empty cart → delete remote cart.
- **Host ownership of wiring**: Order listener registration, registration/OAuth subscriber dispatch, and most event tracking live in lunar-frontend — coexistence changes must consider host, not only this monorepo.
- **Backwards compatibility**: Existing config keys, job class names, service APIs, observer behavior, and host dispatch sites for Mailchimp must continue to work unchanged for Mailchimp-only shops.

### Conceptual relationships

```
Customer/User ──subscribe/sync──► Marketing Audience (Mailchimp list today; Klaviyo profiles later)
Order (via OrderPlacedEvent) ──sync──► Ecommerce Order + Customer (+ optional audience prefs)
CartLine ──observer──► Abandoned Cart (Mailchimp ecommerce carts)
Product ──job/command──► Catalog/Product (Mailchimp ecommerce products)
Storefront events ──track──► Member events (begin_checkout, view_item, remove_from_cart)
```

Ownership: Engine owns API clients, jobs, cart observer, listener **class**; host owns when subscribe/track fire and which listeners are registered on `OrderPlacedEvent`.

---

## Strategic Approach

### 1. Current architecture

The Mailchimp integration is a **self-contained, vendor-named package** with a clear internal layering:

```
Host (lunar-frontend)                    Engine (packages/mailchimp)
─────────────────────                    ──────────────────────────
Verify email / OAuth ──dispatch job──►   SyncSubscriberToMailchimp → MailchimpSubscriberService
Livewire track events ──call/trait──►    MailchimpSubscriberService::trackEvent
listeners.php ──OrderPlacedEvent──►      SyncOrderOnPlacement → SyncOrderToMailchimp
                                         → MailchimpEcommerceService (+ subscriber merge)
CartLine Eloquent events ─────────────►  CartLineObserver → SyncCartToMailchimp
Artisan bulk commands ────────────────►  Services / Sync* jobs
                                         Saloon Requests → MailchimpConnector → Mailchimp API
```

- **HTTP**: Saloon connector + one request class per endpoint.
- **Services**: Split by concern (base config, subscriber/marketing, ecommerce).
- **Async**: Queued jobs with shared retry/backoff; order job is unique per order ID.
- **Config**: Merged as `lunar.mailchimp`; published as `config/lunar/mailchimp.php`.
- **Persistence**: None — no Mailchimp tables, no sync-state models, no provider_data columns.
- **Coupling**: **Tight to Mailchimp** — class names, config namespace, trait method names (`trackMailchimp*`), job FQCNs, and host dispatch sites are all vendor-specific. There is **no** marketing provider interface or multi-destination manager today. Coexistence with ERP already works by **independent listeners on the same event**, not by a shared marketing abstraction.

### 2. Integration touchpoints

| Concern | Where it happens today | Notes for Klaviyo |
|--------|-------------------------|-------------------|
| **Subscribe** | `MailchimpSubscriberService::subscribe()`; host uses with `automatic_subscription` | Double opt-in semantics are Mailchimp-specific; Klaviyo consent model differs |
| **Unsubscribe** | **Not implemented** as outbound API; only resubscribe branch reads `unsubscribed`/`cleaned` | Klaviyo unsubscribe/suppression must be designed as new capability or left to Klaviyo UI/webhooks |
| **Profile / subscriber sync** | `syncSubscriber` / `syncSubscriberByEmail` / language-only; jobs + bulk commands; also during order sync when `sync_subscribers` | Merge fields (FNAME, LNAME, PREFCAT, LANGUAGE, option_fields) are Mailchimp concepts → map to Klaviyo profile properties |
| **Ecommerce customer** | `syncCustomer` / `syncCustomerByEmail` during order/cart; optional `SyncCustomerToMailchimp` (gated by undeclared `sync_customers.enabled`) | Email-hash customer IDs are Mailchimp ecommerce convention |
| **Orders** | `OrderPlacedEvent` → `SyncOrderOnPlacement` → `SyncOrderToMailchimp` → `syncOrder`; bulk `mailchimp:sync-all-orders` (`status = completed`) | **Reuse `OrderPlacedEvent`**; register parallel Klaviyo listener in host |
| **Carts** | `CartLineObserver` → `SyncCartToMailchimp` | Observer is Mailchimp-only; dual cart sync needs careful fan-out to avoid double-observer issues |
| **Products** | `SyncProductToMailchimp` + bulk command; no in-package product observer | Host/admin may dispatch; Klaviyo catalog is optional phase |
| **Events** | Host Checkout/ProductView + `TrackRemoveFromCart` trait | Vendor-named; abstraction or parallel Klaviyo track calls needed |
| **Shared event to reuse** | **`Lunar\ERP\Events\OrderPlacedEvent`** | Primary; do not invent a second order-placed path |
| **Not to invent as required** | New DB models for Mailchimp | Klaviyo also need not require DB unless sync-state/audit is a product requirement |

### 3. Recommended abstraction (smallest safe)

**Do not** force Mailchimp through a full ERP-style provider rewrite as a prerequisite for Klaviyo.

**Recommended direction — “parallel packages + shared lifecycle events (minimal)”:**

1. **Add `packages/klaviyo` as a sibling package** mirroring the proven Mailchimp shape (config `lunar.klaviyo`, Saloon connector, services, jobs, feature flags, optional listener class). Independent enablement. Zero change to Mailchimp public API.
2. **Reuse `OrderPlacedEvent`** for order sync: host registers both `SyncOrderOnPlacement` (Mailchimp) and a new Klaviyo listener when each package is enabled — same coexistence pattern as ERP + Mailchimp today.
3. **Introduce the smallest shared abstraction only where the host currently hardcodes Mailchimp job FQCNs** — i.e. subscribe / profile sync / behavioral events:
   - Prefer **provider-agnostic domain events** (e.g. conceptual intents: customer opted in, profile should sync, storefront event occurred) dispatched by the host, with **Mailchimp and Klaviyo each providing their own listeners/jobs**.
   - Alternatively (even smaller engine change, more host duplication): host dual-dispatches Mailchimp and Klaviyo jobs behind feature flags — no shared interface yet; acceptable for a first coexistence release if host change cost is acceptable.
4. **Cart observer**: Do **not** register two independent observers that both react to every CartLine change without a fan-out strategy. Prefer either (a) a single thin “marketing cart changed” dispatcher that notifies enabled providers, or (b) Klaviyo cart sync deferred until needed, leaving Mailchimp observer untouched.
5. **Defer** a heavyweight `MarketingProviderInterface` covering full ecommerce catalog/cart/order APIs until a third provider or shared admin UX demands it. ERP’s provider pattern is a **future** reference, not the day-one requirement.

**Trade-off summary**

| Decision | Trade-offs | Recommendation |
|----------|------------|----------------|
| Parallel Klaviyo package vs fold into Mailchimp package | Parallel = clear BC, some duplication; fold = false cohesion | **Parallel package** |
| Domain events for subscribe/profile vs dual host dispatch | Events = cleaner long-term, small host+engine work; dual dispatch = fastest, more host coupling | **Domain events if host will grow; dual dispatch OK for MVP** |
| Full MarketingProviderInterface now | Cleanest multi-provider, highest Mailchimp refactor risk | **Defer** |
| Shared cart fan-out now | Avoids double observers; touches working Mailchimp path | **Only if Klaviyo needs abandoned-cart parity in v1** |

### 4. Required database / configuration changes

**Database**

- **None required** for Mailchimp parity (Mailchimp itself has no migrations).
- Optional later: sync attempt logs, last-synced timestamps, or consent audit — only if product/compliance requires them; not needed for coexistence.

**Configuration**

- New `lunar.klaviyo` config (env-prefixed `KLAVIYO_*`): `enabled`, API credentials, list/segment or account identifiers as required by Klaviyo, and feature mirrors: `sync_subscribers`, `sync_orders`, `sync_carts`, `sync_products`, `track_events`, retry — independently of Mailchimp.
- Host `listeners.php`: register Klaviyo order listener alongside Mailchimp when desired.
- Host env: shops may enable one or both; defaults should keep Klaviyo **off** so existing Mailchimp deployments are unchanged.
- Composer/monorepo: register new package provider like other Lunar addons.
- **Do not** rename or nest existing `lunar.mailchimp.*` keys.

### Alternatives considered

- **Big-bang `packages/marketing` with Mailchimp as first provider**: Rejected for day one — high refactor surface, BC risk to jobs/config/host, delays Klaviyo.
- **Replace Mailchimp with Klaviyo**: Rejected — requirement is coexistence and BC.
- **Only host-side Klaviyo SDK with no engine package**: Rejected as primary — contradicts engine pattern (ERP, shipping, Mailchimp packages) and makes testing/reuse weaker; host may still call into engine package.

### Solution Direction (summary)

Ship Klaviyo as a **sibling Saloon-based package**, wire order sync through the **existing `OrderPlacedEvent`**, keep Mailchimp untouched for BC, and add only the minimal fan-out (domain events or dual host dispatch) for subscribe/profile/event paths that today name Mailchimp explicitly. Treat cart dual-write as an explicit scoped decision, not an automatic copy of the Mailchimp observer.

---

## Risk & Gap Analysis

### 5. Migration risks

- **Host coupling**: Most subscribe and event-track entry points live in lunar-frontend; engine-only changes cannot complete coexistence. Coordination risk across repos.
- **Double sync / double messaging**: If both providers enabled without clear consent mapping, customers may receive campaigns from both platforms or get inconsistent opt-in status.
- **Consent / GDPR**: Mailchimp double opt-in (`pending`) vs Klaviyo consent profiles differ; mapping `automatic_subscription` incorrectly could illegally or poorly subscribe users.
- **Cart observer collision**: Naively adding a second CartLine observer doubles queue load and can race; changing the existing observer risks Mailchimp regressions.
- **Order status bulk sync**: `mailchimp:sync-all-orders` filters `status = completed` which may not match host statuses — any Klaviyo bulk command inherits this ambiguity.
- **Undocumented config**: `sync_customers.enabled` is referenced by a job but absent from default Mailchimp config — avoid copying that footgun into Klaviyo.
- **Route dependency**: Cart `checkout_url` depends on host named routes — Klaviyo abandoned-cart URLs face the same host dependency.
- **Test/CI gap**: `tests/mailchimp` exists but is not in root phpunit/CI matrix — new package tests should be planned for inclusion deliberately.
- **Documentation drift**: `MAILCHIMP_PLUGIN.md` can be stale vs skill/CODE_MAP — implementers must trust code + skill.

### 6. Things that must not be changed

- **Existing `lunar.mailchimp` config keys and env names** — shops’ `.env` must keep working.
- **Public FQCNs of Mailchimp jobs/services/listener** used by host — renaming breaks lunar-frontend without a coordinated release.
- **`CartLineObserver` behavior for Mailchimp-only shops** — no-op when disabled; sync when enabled + logged-in cart.
- **`OrderPlacedEvent` contract** (order payload) — shared with ERP; do not fork a Mailchimp-only placement event.
- **Saloon + queue + feature-flag patterns** for Mailchimp — do not rewrite Mailchimp internals to “prove” abstraction.
- **Default `MAILCHIMP_ENABLED` / sync flags remaining false-safe** for environments that rely on current defaults.
- **Customer ID hashing and merge-field semantics inside Mailchimp services** — vendor-specific; do not generalize by breaking Mailchimp payloads.
- **No forced database migration** for existing Mailchimp installs as a prerequisite for Klaviyo.

### Requirement Ambiguities

- **Scope of Klaviyo v1**: Full Mailchimp parity (subscribers + ecommerce products/carts/orders + events) vs orders+profiles only? Not specified.
- **Coexistence policy**: Both active simultaneously for the same shop, or mutually exclusive selection? Affects consent and cart fan-out design.
- **Unsubscribe**: Required outbound capability for Klaviyo/Mailchimp, or platform-managed only?
- **Product live sync**: Who dispatches `SyncProductToMailchimp` today in production host — and is Klaviyo catalog in scope?
- **`automatic_subscription` parity**: Exact consent UX for Klaviyo not defined.
- **Admin UI**: Filament settings for Klaviyo credentials in this engine’s admin package, or host-only env config like Mailchimp today?

### Edge Cases

- Guest checkout with billing email only.
- Guest → register later (Mailchimp relies on email-hash customer ID continuity).
- Previously unsubscribed Mailchimp member re-opting in (`pending`) while also syncing to Klaviyo.
- Empty cart deletes; order placement deletes cart.
- Product unavailable → delete from Mailchimp catalog.
- Event tracking when subscriber missing (Mailchimp auto-syncs then retries).
- Both providers enabled: one fails, one succeeds — partial marketing state.
- Locale/language merge field sync (`SyncAllUserLanguagesToMailchimpCommand`) — Klaviyo profile property equivalent?

### Technical Risks

- **Tight host coupling to Mailchimp job names** — primary blocker for clean coexistence without host changes.
- **API rate limits / queue volume** — dual providers roughly double outbound jobs for shared triggers.
- **Idempotency** — Mailchimp order job is unique; Klaviyo needs equivalent uniqueness strategy.
- **Silent failures** on event tracking (`SilentException`) — easy to miss dual-provider bugs.

### Acceptance Criteria Coverage

Derived from the stated analysis goals (no formal AC list was provided):

| AC# | Description | Addressable? | Gaps/Notes |
|-----|-------------|--------------|------------|
| 1 | Inventory Mailchimp services, jobs, listeners, config, models, migrations | Yes | Complete: no DB models/migrations; full package map above |
| 2 | Identify subscribe/unsubscribe points | Partial | Subscribe mapped; **unsubscribe outbound absent** — product decision needed |
| 3 | Identify customer profile sync | Yes | Subscriber merge fields + ecommerce customer + language sync |
| 4 | Identify order send paths | Yes | `OrderPlacedEvent` + bulk command |
| 5 | Identify reusable events | Yes | **`OrderPlacedEvent`**; product sync uses `ProductEventType` enum, not a domain event bus |
| 6 | Assess Mailchimp coupling | Yes | Tight; host FQCNs + vendor naming |
| 7 | Smallest safe abstraction for coexistence | Yes | Parallel package + event reuse; defer full provider interface |
| 8 | Preserve Mailchimp BC | Yes | No rename/restructure of Mailchimp public surface in recommended approach |
| 9 | No implementation in this phase | Yes | Analysis only |

---

## Appendix: Deliverable map (requested return sections)

| # | Section | Where covered |
|---|---------|---------------|
| 1 | Current architecture | Strategic Approach §1 |
| 2 | Integration touchpoints | Strategic Approach §2 |
| 3 | Recommended abstraction | Strategic Approach §3 |
| 4 | Required database/configuration changes | Strategic Approach §4 |
| 5 | Migration risks | Risk & Gap Analysis §5 |
| 6 | Things that must not be changed | Risk & Gap Analysis §6 |
)
