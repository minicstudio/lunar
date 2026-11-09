# Spec Delta

## Purpose

Records where storefront visitors come from (search engines, social networks, ad campaigns, other sites or direct) in the shop database. Defines which requests count as visits, how a visit's source is classified, what is stored, and how shops tune recording and switch it on or off.

## ADDED Requirements

### Requirement: First page view of a session starts a visit
The storefront SHALL record one visit for the first storefront page view of a session, with the source of that page view, unless the visitor already has a cart with a traffic source. Later page views in the same session SHALL NOT record another visit unless a campaign requirement below applies.

#### Scenario: Landing from Google search
- **WHEN** a new visitor opens a product page with referrer `https://www.google.com/`
- **THEN** one visit is recorded with source `google`, medium `organic` and the product page path as landing path

#### Scenario: Browsing after landing
- **WHEN** the same visitor then opens three more storefront pages in the same session
- **THEN** no further visit is recorded

### Requirement: Campaign parameters start a new visit
A page view whose campaign parameters (`utm_source` or a configured click id) give a different source, medium or campaign than the session's current visit SHALL record a new visit, which becomes the current one, unless the visitor already has a cart with a traffic source. When they give the same source, medium and campaign, no visit SHALL be recorded.

#### Scenario: Returning through an ad in the same session
- **WHEN** a visitor without a cart whose current visit is `google` / `organic` opens a page with `?gclid=abc`
- **THEN** a new visit is recorded with source `google` and medium `cpc`

#### Scenario: Reloading a tagged landing page
- **WHEN** a visitor whose current visit came from `?utm_source=newsletter&utm_medium=email&utm_campaign=nov` reloads the same URL
- **THEN** no new visit is recorded

### Requirement: External returns mid-session do not start a visit
A page view with an external referrer and no campaign parameters SHALL NOT record a visit when the session already has one. This covers returns from payment pages and login providers.

#### Scenario: Return from a bank payment page
- **WHEN** a visitor with a current visit lands on the checkout confirmation page with a bank's referrer and no campaign parameters
- **THEN** no visit is recorded and the current visit is unchanged

### Requirement: Navigation from the shop itself is not a visit
A page view whose referrer host is the shop's own host, ignoring a leading `www.`, and that has no campaign parameters SHALL NOT record a visit, even when the session has no visit yet.

#### Scenario: New session after logout
- **WHEN** a visitor logs out and the next page view in the new session has the shop's own URL as referrer
- **THEN** no visit is recorded

### Requirement: Visitors who already have a cart with a source do not start a visit
A page view that would record a visit SHALL NOT record one when the visitor's current cart has a traffic source and no placed order. The current cart is the session's cart or, for a logged-in customer without one, their latest active cart. When the session has no current visit, that cart's visit SHALL become the session's current visit. Looking up the cart SHALL NOT create one. A cart without a traffic source SHALL NOT prevent a visit.

#### Scenario: Returning customer through Google
- **WHEN** a logged-in customer whose active cart came from a Facebook visit returns through `https://www.google.com/` after their session expired
- **THEN** no visit is recorded and the Facebook visit becomes the session's current visit

#### Scenario: Ad click while holding a cart
- **WHEN** a visitor whose cart came from a Facebook visit opens a page with `?gclid=abc`
- **THEN** no visit is recorded and the current visit is unchanged

#### Scenario: Ad click after the order is placed
- **WHEN** that visitor's order has been placed and they then open a page with `?gclid=abc`
- **THEN** a new visit is recorded with source `google` and medium `cpc`

#### Scenario: Restored cart without a source
- **WHEN** a logged-in customer whose active cart has no traffic source returns through `https://www.google.com/` in a new session
- **THEN** a `google` / `organic` visit is recorded

### Requirement: Only real page views are recorded
A visit SHALL be recorded only for GET requests to storefront page routes that get a 200 HTML response. Requests from user agents that are empty or match the bot pattern SHALL NOT be recorded, and neither SHALL browser prefetch requests.

#### Scenario: Search engine crawler
- **WHEN** a request with user agent `Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)` opens the home page
- **THEN** no visit is recorded

#### Scenario: Missing page
- **WHEN** a new visitor opens an unknown URL that renders the 404 page
- **THEN** no visit is recorded

#### Scenario: Redirect responses
- **WHEN** a new visitor's request to a storefront route answers with a redirect, such as a payment return or an OAuth callback
- **THEN** no visit is recorded for that request

#### Scenario: Non-HTML responses
- **WHEN** a new client requests an image proxy URL, the sitemap or `llms.txt`
- **THEN** no visit is recorded

#### Scenario: Prefetch and HEAD requests
- **WHEN** a browser sends a page request with `Sec-Purpose: prefetch`, or a client sends a HEAD request
- **THEN** no visit is recorded

### Requirement: The admin panel and staff sessions are not recorded
Requests served by the admin panel SHALL NOT record visits. While a staff member is logged in to the admin panel in the session, storefront page views SHALL NOT record visits either.

#### Scenario: Admin page view
- **WHEN** a staff member opens an admin panel page with an external referrer
- **THEN** no visit is recorded

#### Scenario: Staff member browsing the storefront
- **WHEN** a staff member logged in to the admin panel opens the storefront home page from Google in the same browser
- **THEN** no visit is recorded

### Requirement: Tagged links set the source
When `utm_source` is present, the visit's source SHALL be `utm_source`, its medium `utm_medium` (empty when absent), and its campaign `utm_campaign`. Source and medium SHALL be stored trimmed and lowercased. `utm_medium` and `utm_campaign` without `utm_source` SHALL NOT set the source.

#### Scenario: Newsletter link
- **WHEN** a new visitor opens `/?utm_source=Mailchimp&utm_medium=Email&utm_campaign=Black-Friday`
- **THEN** the visit has source `mailchimp`, medium `email` and campaign `Black-Friday`

#### Scenario: Tag overrides referrer
- **WHEN** a new visitor arrives from `https://l.facebook.com/` with `?utm_source=fb_ads&utm_medium=paid_social`
- **THEN** the visit has source `fb_ads` and medium `paid_social`

### Requirement: Ad click ids set the source
Without `utm_source`, a configured click id SHALL give its configured source and medium. A click id marked to win over the referrer SHALL decide even with a known referrer host. The other click ids SHALL decide only when the referrer is not a known host. By default, `gclid`, `gbraid` and `wbraid` give `google` / `cpc` and win over the referrer, and `fbclid` gives `facebook` / `social`.

#### Scenario: Google Ads auto-tagging
- **WHEN** a new visitor opens `/?gclid=Cj0KCQ` with referrer `https://www.google.com/`
- **THEN** the visit has source `google` and medium `cpc`

#### Scenario: Facebook link without referrer
- **WHEN** a new visitor opens `/?fbclid=IwAR0` from the Facebook in-app browser, which sends no referrer
- **THEN** the visit has source `facebook` and medium `social`

#### Scenario: Instagram link with fbclid
- **WHEN** a new visitor opens `/?fbclid=PAZX` with referrer `https://l.instagram.com/`
- **THEN** the visit has source `instagram` and medium `social`

### Requirement: Referrer host sets the source
Without tags or a click id that wins over the referrer, a known referrer host SHALL give its configured source and medium. By default, Google, Bing, Yahoo and DuckDuckGo give medium `organic`, and Facebook, Instagram, TikTok and YouTube give medium `social`. Any other external host SHALL give that host, without a leading `www.`, as source with medium `referral`.

#### Scenario: Country Google domain
- **WHEN** a new visitor arrives from `https://www.google.hu/`
- **THEN** the visit has source `google` and medium `organic`

#### Scenario: Facebook link shim
- **WHEN** a new visitor arrives from `https://lm.facebook.com/`
- **THEN** the visit has source `facebook` and medium `social`

#### Scenario: Price comparison site
- **WHEN** a new visitor arrives from `https://www.arukereso.hu/mobiltelefon-c3277/`
- **THEN** the visit has source `arukereso.hu`, medium `referral` and referrer host `arukereso.hu`

### Requirement: Untagged visits without referrer are direct
A visit with no campaign parameters and no referrer SHALL have source `direct` and medium `none`.

#### Scenario: Typed URL or bookmark
- **WHEN** a new visitor opens the home page with no referrer and no query parameters
- **THEN** the visit has source `direct` and medium `none`

### Requirement: Shops can tune the source rules
A shop SHALL be able to add, change or remove known referrer hosts, click ids and the bot pattern through configuration. It SHALL also be able to replace the classification with its own. Without such configuration, the default rules above SHALL apply.

#### Scenario: Price comparison site as a named source
- **WHEN** a shop maps `compari.ro` to source `compari` and medium `comparison`, and a new visitor arrives from `https://www.compari.ro/`
- **THEN** the visit has source `compari` and medium `comparison`

#### Scenario: Microsoft Ads click id
- **WHEN** a shop adds `msclkid` as a click id giving `bing` / `cpc` that wins over the referrer, and a new visitor opens `/?msclkid=x` from `https://www.bing.com/`
- **THEN** the visit has source `bing` and medium `cpc`

#### Scenario: Own monitoring tool
- **WHEN** a shop adds its uptime checker's user agent to the bot pattern
- **THEN** requests from that checker record no visit

### Requirement: Visit records hold no personal data
Each visit SHALL store its source, medium, campaign, referrer host, landing path without query string, and creation time. It SHALL NOT store the IP address, user agent, user or customer, session id, full referrer URL, or query string. Values over 255 characters SHALL be truncated.

#### Scenario: Referrer with a path and query
- **WHEN** a new visitor arrives from `https://blog.example.com/post?id=7` on `/sale?utm_source=blog&ref=x`
- **THEN** the visit stores referrer host `blog.example.com` and landing path `/sale`, and no other part of either URL

#### Scenario: Upgrading a shop
- **WHEN** a shop runs its migrations after installing the release
- **THEN** visits can be stored, and existing carts and orders are unchanged

### Requirement: Recording does not depend on consent or new cookies
Visits SHALL be recorded whether or not the visitor accepted analytics or marketing cookies. Recording SHALL NOT set any cookie beyond the existing session cookie.

#### Scenario: Visitor declined analytics cookies
- **WHEN** a new visitor whose consent cookie denies analytics opens the home page from Google
- **THEN** a `google` / `organic` visit is recorded and the response sets no new cookie for tracking

### Requirement: Shops switch recording on
Visit recording SHALL be off by default and SHALL be switchable on and off per shop. While off, no visits are recorded and new carts get no traffic source. The other requirements of this capability apply while recording is on.

#### Scenario: Recording not switched on
- **WHEN** a shop has not set `ANALYTICS_TRAFFIC_ENABLED` and a new visitor arrives from Google and adds a product to a new cart
- **THEN** no visit is recorded and the cart has no traffic source

#### Scenario: Recording switched on
- **WHEN** a shop sets `ANALYTICS_TRAFFIC_ENABLED=true` and a new visitor arrives from Google
- **THEN** a `google` / `organic` visit is recorded

#### Scenario: Recording switched off again
- **WHEN** a shop that recorded visits sets `ANALYTICS_TRAFFIC_ENABLED=false`
- **THEN** no further visits are recorded, new carts get no traffic source, and the report keeps showing the stored data
