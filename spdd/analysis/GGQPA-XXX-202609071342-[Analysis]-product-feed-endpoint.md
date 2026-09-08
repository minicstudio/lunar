# SPDD Analysis: Product Feed Endpoint

## Original Business Requirement

Product feed endpoint
Készíteni kell egy általános termék feed endpointot, amely a termékadatokat XML/CSV/JSON stb. formátumban szolgáltatja külső rendszerek számára (a megadott kiterjesztés alapján adja vissza a megfelelőt).

Elvárások:

A feed dinamikusan, kéréskor generálódjon az endpoint elérésekor

Az endpoint publikus legyen

A feed tartalmazza az alapvető termékadatokat:

azonosító

név

leírás

ár

pénznem

készletinformáció

termék URL

kép URL

márka

stb (inspirálódjunk a meta-tól, hogy az miket kér)

A feed struktúrája legyen kompatibilis külső feed-feldolgozó rendszerekkel

A feed célja:

külső rendszerek számára egységes termékadat-forrás biztosítása

termékadatok egyszerűbb szinkronizálása

külső crawler-ek és integrációk támogatása

PLease write the analysis in english. Dont overcomplicate it, I need product data in specific formats, thats all. But please look for real use cases, so we can actually use these endpoints somewhere if needed.

## Domain Concept Identification

### Existing Concepts (from codebase)

- **Product**: Sellable catalog parent in Lunar core (`products` table) — status, `published_at`, translated attributes (name/description), media, URLs, brand relation.
- **ProductVariant**: Purchasable SKU unit (`product_variants`) — `sku`, `gtin`/`mpn`/`ean`, `stock`/`backorder`/`purchasable`, prices. This is the natural feed row for external catalogs.
- **Brand**: Named brand attached to products — maps directly to feed `brand`.
- **Price / Currency**: Variant pricing with currency codes — maps to feed `price` (amount + ISO currency).
- **Url**: Storefront slug for products — needed to build absolute product `link`.
- **Media (images)**: Spatie media on products/variants — source for `image_link`.
- **Klaviyo catalog sync (boundary)**: Already resolves title, description, URL, image, SKU, inventory, price for outbound catalog push. Not a public feed, but proves the same domain data is already assemblable. Do not couple the public feed to Klaviyo.

### New Concepts Required

- **Product Feed**: A public, on-demand serialization of catalog items into Meta/Google-style attributes, returned as CSV / XML / JSON based on the requested extension.
- **Feed Item**: One sellable row (prefer variant/SKU-level) using a shared attribute set that external processors understand.

### Key Business Rules

- **Public + dynamic**: Unauthenticated GET; regenerate content on each request (no mandatory prebuilt file).
- **Format by extension**: Same data, different serialization (at minimum CSV, XML, JSON).
- **External compatibility**: Attribute names/values should match what Meta Commerce Manager and Google Merchant Center already fetch via scheduled URL — that is the practical definition of “compatible with external feed processors.”
- **Published catalog only**: Only products suitable for storefront/ads (published / publicly visible) should appear.
- **One row per sellable unit**: External catalogs expect SKU-level rows; parent product identity belongs in `item_group_id` when variants exist.

## Strategic Approach

### Solution Direction

Expose a simple public HTTP endpoint that streams a product feed built from Lunar products/variants at request time. Use the **Google / Meta product catalog attribute set** as the field contract (they are nearly identical and are the real scheduled-fetch use cases). Serialize that same attribute set to CSV, XML (RSS/`g:` namespace style), and JSON based on the URL extension.

**Intended real use cases (why these formats matter):**

| Consumer | How they use the endpoint | Preferred format |
|----------|---------------------------|------------------|
| **Meta Commerce Manager** | Add catalog data source → scheduled fetch from hosted URL (Facebook/Instagram Shops, catalog ads) | CSV or XML |
| **Google Merchant Center** | Products → Data sources → scheduled fetch from URL (Shopping / free listings) | XML (RSS 2.0 + `g:`) or TSV/CSV |
| **Internal / custom integrators** | Simple HTTP pull for sync, QA, or tooling | JSON |

Field baseline to cover Meta’s required set (and Google’s overlapping core set): `id`, `title`, `description`, `availability`, `condition`, `price`, `link`, `image_link`, `brand`, plus useful extras already in Lunar when present (`gtin`/`mpn`, `item_group_id`, optionally `sale_price` from compare/discounted price).

High-level flow: `public GET → load published products/variants → map to feed attributes → respond with content-type for requested format`.

### Key Design Decisions

- **Attribute contract = Meta/Google catalog fields, not a custom schema**: Trade-off is less “app-specific” naming vs. drop-in usability for scheduled fetches. → **Recommend Meta/Google field names** so the URL can be pasted into Commerce Manager / Merchant Center without a transform layer.
- **Granularity = variant/SKU rows**: Trade-off is more rows vs. product-level simplicity. → **Recommend variant rows** with `item_group_id` = parent product identity; Meta/Google ads and inventory sync need SKU-level availability/price.
- **Formats = CSV + XML + JSON in v1**: Trade-off is breadth vs. scope. → **Recommend these three**; TSV can wait unless Google specifically needs it later. XML should follow the common RSS/`g:` pattern both platforms accept.
- **Placement = thin Lunar package or host route + small service**: Core has the models but no public product HTTP API today; packages like payments already register routes. → **Recommend a small dedicated surface** (package or clearly named module) that depends on core models — keep it separate from Klaviyo/Mailchimp push sync.
- **Keep v1 thin**: No feed auth tokens, no per-channel field mapping UI. Prefer TTL response-body cache over scheduled static files unless ops later require offline pre-generation.

### Alternatives Considered

- **Reuse Klaviyo/Mailchimp catalog push as the “feed”**: Rejected — those are provider APIs, not a public pull URL for Meta/Google.
- **Invent a custom JSON-only API**: Rejected as primary contract — does not satisfy Meta/Google scheduled fetch without a middleman.
- **Pre-generate and store feed files on a schedule**: Deferred — on-request generation remains primary; **TTL-cached encoded bodies** address crawler repeat hits without requiring disk files. Offline file rebuild can still be added later for very large catalogs.
- **Product-level rows only**: Rejected as default — weak fit for stock/price accuracy on multi-variant products in ad catalogs.

## Risk & Gap Analysis

### Requirement Ambiguities

- **Product vs variant row**: Requirement lists “product” fields; Meta/Google need SKU rows. Clarify default = variant rows.
- **Availability vocabulary**: Meta often expects `in stock` / `out of stock`; Google expects `in_stock` / `out_of_stock`. Pick one primary dialect (or document which platform the default targets).
- **Absolute URLs**: Feed `link` / `image_link` must be publicly reachable HTTPS URLs. Storefront base URL and media URL generation depend on host config (same gap already seen in Klaviyo catalog URL resolution).
- **Which products**: “Published” is implied but not specified (channels, customer groups, soft-deleted, draft).
- **Currency**: Multi-currency exists in Lunar; feeds usually need one default currency per feed URL.
- **`condition`**: Required by Meta; Lunar has no product condition field — defaulting to `new` is the usual e-commerce assumption and should be explicit.

### Edge Cases

- Product with no URL slug → invalid/broken `link` for Meta/Google.
- Product with no image → Meta/Google reject or degrade the item.
- Variant without SKU → need a stable `id` fallback (variant id).
- Zero/missing price → item typically rejected by catalogs.
- Very large catalogs → on-request generation may be slow on **cache miss**; TTL body cache mitigates repeat crawls; **lazyById + streamed encode (cache off)** bounds memory; very large shops may still want offline file rebuild later.
- HTML in descriptions → feeds expect plain text (strip tags), same as Klaviyo catalog already does.

### Technical Risks

- **Performance**: Full-catalog serialize on cache miss (Meta/Google often fetch daily or more). Mitigation: eager-load relations, **TTL cache of encoded bodies per format**, **lazyById chunked queries**, **streamed HTTP when cache disabled**; event-driven invalidation can follow.
- **URL correctness lives partly outside this package**: Absolute product links depend on storefront routing/base URL configuration in the host app.
- **No existing public feed route**: Greenfield HTTP surface — must register routes and content types carefully without auth middleware.
- **Do not conflate with marketing push sync**: Parallel concept to Klaviyo catalog, different delivery model (pull URL vs push API).

### Acceptance Criteria Coverage

| AC# | Description | Addressable? | Gaps/Notes |
|-----|-------------|--------------|------------|
| 1 | Feed generated dynamically on request | Yes | TTL-cached encoded body per format; rebuild on miss / when cache disabled uses chunked DB + streamed HTTP; offline file rebuild optional later |
| 2 | Endpoint is public | Yes | Confirm no accidental auth middleware |
| 3 | Includes id, name, description, price, currency, stock, product URL, image URL, brand (+ Meta-inspired extras) | Yes | Map to Meta/Google attribute names; `condition` default; currency = default currency |
| 4 | Structure compatible with external feed processors | Yes | Target Meta + Google scheduled fetch field contract |
| 5 | Format selected by extension (XML/CSV/JSON etc.) | Yes | v1: csv, xml, json |
| 6 | Serves as unified source for external sync / crawlers | Yes | Primary concrete consumers: Meta Commerce Manager, Google Merchant Center, custom JSON clients |
