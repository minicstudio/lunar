# Upgrade Guide

This document outlines the key changes and new features in the Admin, Core, Blog, Review, Shipping and ERP packages.

## ⬆️ Upcoming

### Analytics Package

#### ✨ New main features

- New `lunarphp/analytics` package (`Lunar\Analytics\`) for measuring and reporting features. It has one provider (`AnalyticsServiceProvider`), one Filament plugin (`AnalyticsPlugin`, "Analytics" navigation group), one config file with a section per feature, and one permission state. Each feature lives in its own folder, starting with `src/Traffic/`.
- **Traffic recording.** One `lunar_traffic_visits` row per visit (source, medium, campaign, referrer host, landing path, time; no IP, user agent, user, session id, full referrer or query string). A visit starts on the first storefront page view of a session, or when a later page view's campaign parameters (`utm_source` or a configured click id) give a different source, medium or campaign. Bots, non-HTML and non-200 responses, the admin panel and sessions with a logged-in staff member are not recorded. A browser prefetch or prerender (`Sec-Purpose` / `Purpose`) records nothing, but the visit it would start stays pending in the session and is recorded once the visitor opens the page, at the session's next page view or cart creation. A visitor whose active cart already has a traffic source starts no visit; the session continues that cart's visit. Recording does not depend on consent and sets no new cookie.
- **Classification**, in precedence order: `utm_source` (with `utm_medium`, `utm_campaign`); click ids that win over the referrer (default `gclid`, `gbraid`, `wbraid` → google / cpc); known referrer hosts (Google, Bing, Yahoo, DuckDuckGo → organic; Facebook, Instagram, TikTok, YouTube → social); the other click ids (default `fbclid` → facebook / social); any other external host (host / referral); direct / none.
- **Attribution.** A new cart gets `meta.traffic_source` (`visit_id`, `source`, `medium`, `campaign`) from the current visit, once (first touch). Orders keep it through `FillOrderFromCart` (whole cart meta copy) and split orders through `SplitOrder`.
- **Traffic sources report** (`Lunar\Analytics\Traffic\Filament\Pages\TrafficSourcesPage`, Analytics group) for admins and staff with `analytics:traffic-sources`: for a period (default: the last 30 days) visit, cart and order totals, order value and order rate; visits by source; visits over time (per day, week or month); per source and medium visits, carts, placed reportable orders, order value and order rate, with orders without a source as "Unknown"; the most visited landing pages. Visits and carts whose orders all have a status excluded from reports (for example `test-order`) are left out of every figure, by the orders' current status; visits and carts without orders still count. Unlike the dashboard, a split order counts once, as its original on its placement date and with its value, while one of its new orders counts.
- **Order pages.** Admins and staff with `analytics:traffic-sources` see each order's traffic source as a badge, named in words ("Google · Organic search", "Google · Paid ad", "Facebook · Social", "Direct", "Unknown" without a source): a toggleable "Traffic source" column in the order list, after the customer type, with the campaign as tooltip, and a "Traffic source" section on the order page, directly above "Additional info" (below Tags and Personalization), in place of an order summary entry. The section shows the badge and each stored detail that has a value: the campaign, and from the order's visit the referrer host, the landing page as a link to the storefront page (new tab) and when the visit started. It reads the visit with one query; without a visit row it shows only the badge and the campaign. The raw `traffic_source` no longer appears under "Additional info" for anyone. The report's visits by source and funnel table name sources and mediums the same way. `AnalyticsPlugin` adds both extensions, so panels without it are unchanged.

#### 🌍 Environment variables

- `ANALYTICS_TRAFFIC_ENABLED` (default `false`): switches visit recording and cart stamping on. Set it to `true` to start recording.

#### ⚙️ New/modified configs

- `config/lunar/analytics.php`, section `traffic`:
  - `enabled` (`ANALYTICS_TRAFFIC_ENABLED`);
  - `classifier`: class implementing `ClassifiesTrafficSource`, to replace the rules;
  - `referrer_hosts`: host → source / medium (an entry matches the host and its subdomains; a trailing `.*` matches any one- or two-label suffix);
  - `click_ids`: query parameter → source / medium, with `before_referrer`;
  - `bot_user_agent_pattern`: user agents never recorded.

#### 💻 Host responsibilities

- Run `php artisan migrate` to create `lunar_traffic_visits` and the `analytics:traffic-sources` permission. No role is created; grant the permission to the development and marketing staff. The permission opens the report and also shows the traffic source badges on the order list and order page.
- Register `Lunar\Analytics\AnalyticsPlugin` on the Lunar panel (`minic/lunar-frontend` does this).
- Set `ANALYTICS_TRAFFIC_ENABLED=true` to start recording. Recording is off by default: until the variable is set, no visits are recorded, new carts get no source, the report stays empty and the order badges show "Unknown".
- Attach `Lunar\Analytics\Traffic\Http\Middleware\RecordTrafficVisit` to the storefront page routes as route middleware, so it runs inside `StartSession` (the session must be readable and writable before it is saved). `minic/lunar-frontend` does this. It records after the response, only for 200 HTML GET page views. HTML served from an edge or full-page cache never reaches Laravel and is not recorded.
- Hosts with `lunar.cart_session.auto_create = true` create the cart before the landing visit is recorded, so those carts and their orders have no source (shown as "Unknown").

#### 📦 Published assets

- `php artisan vendor:publish --tag="lunar.analytics.config"`
- `php artisan vendor:publish --tag="lunar.analytics.migrations"`

### Feedback Package

#### ✨ New main features

- New `lunarphp/feedback` package (`Lunar\Feedback\`): stores one score (with an optional comment) per order and feedback type in `lunar_feedback`. The storefront (`minic/lunar-frontend`) collects it; the package owns the table, config, and admin.
- Read-only **Feedback** admin list under Sales (filter by type and score). Admins cannot create, edit, or delete feedback.
- Orders list column and order detail entries for the checkout shopping-experience **Feedback score** (and comment).
- `Order` relations `feedback` (all rows) and `shoppingExperienceFeedback` (checkout type).

#### 🌍 Environment variables

- `FEEDBACK_ENABLED` (default `false`)
- `FEEDBACK_SCALE_MIN`, `FEEDBACK_SCALE_MAX`, `FEEDBACK_THRESHOLD`

#### ⚙️ New/modified configs

- `config/lunar/feedback.php`

#### 💻 Host responsibilities

- Run `php artisan migrate` to create `lunar_feedback`.
- Register `Lunar\Feedback\FeedbackPlugin` on the Lunar panel only when `config('lunar.feedback.enabled')` is true (`minic/lunar-frontend` does this). The order list / order detail extensions are skipped while the flag is off.

#### 📦 Published assets

- `php artisan vendor:publish --tag="lunar.feedback.config"`
- `php artisan vendor:publish --tag="lunar.feedback.migrations"`

### Core Package

#### ✨ Free shipping discounts

- Advanced amount off discounts have a **Free shipping** toggle (`data.free_shipping`). It works with a coupon code (free shipping when the coupon qualifies) or without one (free shipping when any cart line is eligible). The percentage or fixed value may be left empty for a shipping-only discount. Buy X Get Y has no toggle.
- A cart with free shipping charges every shipping option at 0. New runtime cart properties: `freeShippingDiscount` (the recorded discount, coupon first) and `shippingDiscountTotal` (the waived amount). The order shipping line keeps the option price in `unit_price` and stores the waived amount in `discount_total`; `Order::shipping_discount_total` sums it.
- A shipping-only discount gets a 0 entry in the cart and order discount breakdown, so `markAsUsed()` counts the order as one use and a shipping-only coupon is the order's `appliedCoupon`.

#### ⚠️ Behavior changes

- Order split: new orders keep the original order's `traffic_source` meta (`SplitOrder::COPIED_META_KEYS`), written on carts by the analytics package. An original without it gives new orders without it.
- A 0-value automatic discount (empty or 0 percentage, or a fixed value with no positive amount) no longer hides lower-tier discounts. Products it is limited to now get the next tier's discount in the cart and the catalog.
- Order split targets and other carts with a `shippingOptionOverride` keep the entered shipping price. Staff can enter 0 on a split target to keep free shipping.

#### ⚙️ New/modified configs

- `pipelines.cart` in `config/lunar/cart.php` lists the new stage `Lunar\Pipelines\Cart\ApplyDiscountFreeShipping` directly before `CalculateTax`.

#### 💻 Host responsibilities

- Add `Lunar\Pipelines\Cart\ApplyDiscountFreeShipping::class` to the published `pipelines.cart` in `config/lunar/cart.php`, directly before `CalculateTax::class` (or before the host's own tax stage), in whatever order `ApplyDiscounts` and `ApplyShipping` run. Without it, free-shipping discounts are recorded and the storefront labels options "FREE", but shipping is charged.
- The storefront (`lunar-frontend`) labels shipping options and rows from `freeShippingDiscount` and `shippingDiscountTotal`; option prices from `ShippingManifest::getOptions()` are unchanged.

## ⬆️ From 1.15.1 → 2.0.0

### 📋 Summing up what to do at upgrade

1. Update published config files for Core/Admin and plugin packages.
2. Add new environment variables where needed (Review reminders, Shipping, ERP providers).
3. Run migrations.
4. Seed plugin attributes/data where applicable.
5. Review scheduler entries for Review and ERP sync commands.
6. Re-check published views/config overrides for compatibility.

### Highlights

- New discount type `AdvancedAmountOff`, plus priority-based automatic discounts (variant > product > collection > global) and coupon discounts applied separately.
- New discounted pricing helpers on products/variants, including labels and inc-tax price calculations.
- Cart and order totals now track coupon vs non-coupon discount totals and inc-tax totals separately.
- Optional stock validation with explicit exceptions for out-of-stock and insufficient stock.
- Customer group availability interface and new availability/purchasability scopes for products and collections.
- Review mixins now expose product rating averages and total review counts across variants.
- Shipping service adds AWB helpers and tracking URL generation.

### Admin Package

- Discounts: new `AdvancedAmountOff` UI section, priority helper text, and per-user max uses fields; translations updated (EN/HU/RO).
- Products: table columns for price inc/ex tax and stock labels; availability warnings clarified; translations updated (EN/HU/RO).

### Core Package

#### Discounts

- New discount type `AdvancedAmountOff` with coupon and line-percentage application.
- Discount manager now prioritizes discounts per purchasable and exposes `getDiscountForPurchasable()`.
- Coupon validation supports the new discount type and skips empty discount data.

#### Pricing and totals

- Cart lines now track prices and discounts with and without coupons.
- Cart totals include `subTotalDiscounted`, coupon totals, and inc-tax totals split by coupon vs non-coupon discounts.
- Orders expose coupon totals, discount totals without coupons, and subtotal discounted inc tax.

#### Stock handling

- Optional stock checking via `lunar.cart.stock_check.enabled`.
- New `HasStock` trait and stock validation now accounts for updates and existing cart lines.
- New exceptions: `OutOfStockException` and `InsufficientStockException`.

#### Availability and events

- New `HasCustomerGroupAvailability` interface.
- Products and collections add customer-group availability scopes; products add purchasable scope checks.
- New events: `ProductCreatedEvent`, `ProductUpdatedEvent`, `ProductDeletedEvent`, `DiscountUpdatedEvent`.

#### Shipping and orders

- Orders now calculate package weight in kg (supports g/lbs; throws `UnsupportedWeightUnitException` otherwise).
- Offline payments now return a redirect payload from `initiatePayment()`.
- Shipping service adds AWB storage, download, and tracking URL helpers.

### Review Package

- New product mixin for average rating and total review count across variants.
- Product variant mixin adds name helper and reviewable-variants lookup for orders.

### Blog Package

#### ✨ New main features

- Blog post management with multi-language support.
- Blog categories with hierarchical structure and channel assignments.
- Staff author association for blog posts.
- Attribute-driven blog content (thumbnail, title, content, SEO fields).
- Filament admin integration for blog management.

#### ⚙️ New/modified configs

- `config/lunar/blog.php`

#### 💻 Commands

- `php artisan lunar:seed-blog`

#### 📦 Published assets

- `php artisan vendor:publish --tag="lunar.blog.config"`
- `php artisan vendor:publish --tag="lunar.blog.migrations"`

### Review Plugin Package

#### ✨ New main features

- Customer reviews with ratings, images, moderation workflow and multi-language support.
- Review support for product variants and channels.
- Review request email system with first/second reminder delays.
- Model mixins on product variants/channels for review stats.
- Review lifecycle events (`created`, `updated`, `deleted`) and policy integration.

#### 🌍 Environment variables

- `REVIEW_UPLOAD_DISK`
- `REVIEW_MAX_FILES`
- `ORDER_STATUS_FOR_REVIEW_REMINDER`
- `FIRST_REMINDER_DELAY_MINUTES`
- `SECOND_REMINDER_DELAY_MINUTES`

#### ⚙️ New/modified configs

- `config/lunar/review.php`

#### 💻 Commands

- `php artisan lunar:seed-review`
- `php artisan review:request-email`

#### 📦 Published assets

- `php artisan vendor:publish --tag="lunar.review.config"`
- `php artisan vendor:publish --tag="lunar.review.migrations"`

### Shipping Package

#### ✨ New main features

- Multi-provider shipping integration (Sameday, DPD, Pickup, In-house).
- AWB generation and AWB document handling.
- Locker support with county/city/locker synchronization.
- Admin order integration for AWB visibility/download.

#### 🌍 Environment variables

- `SHIPPING_ENABLED`
- `SHIPPING_LOCKER_ENABLED`
- `SHIPPING_CONTACT_EMAIL`
- `SHIPPING_AWB_GENERATION_STATUS`
- Provider-specific variables for Sameday/DPD/Pickup/In-house.

#### ⚙️ New/modified configs

- `config/lunar/shipping.php`
- `config/lunar/shipping/sameday.php`
- `config/lunar/shipping/dpd.php`
- `config/lunar/shipping/pickup.php`
- `config/lunar/shipping/inhouse.php`

#### 💻 Commands

- `php artisan migrate`
- `php artisan lunar:sync-shipping-counties`
- `php artisan lunar:sync-shipping-cities`
- `php artisan lunar:sync-shipping-lockers`

#### 📦 Published assets

- `php artisan vendor:publish --tag="lunar.shipping.config"`
- `php artisan vendor:publish --tag="lunar.shipping.migrations"`

### ERP Package

#### ✨ New main features

- Multi-provider ERP integration (Magister, Smartbill).
- Product/order status/stock/locality/attribute synchronization.
- ERP order sending and invoice/billing support.
- Scheduled sync commands via configurable cron expressions.

#### 🌍 Environment variables

- `ERP_ENABLED`
- `ERP_SYNC_PRODUCTS_SCHEDULE`
- `ERP_SYNC_ORDERS_SCHEDULE`
- `ERP_SYNC_STOCK_SCHEDULE`
- `ERP_SYNC_LOCALITIES_SCHEDULE`
- `ERP_SYNC_ATTRIBUTES_SCHEDULE`
- Provider-specific variables for Magister/Smartbill.

#### ⚙️ New/modified configs

- `config/lunar/erp.php`
- `config/lunar/erp/magister.php`
- `config/lunar/erp/smartbill.php`

#### 💻 Commands

- `php artisan erp:sync-products`
- `php artisan erp:sync-order-statuses`
- `php artisan erp:sync-stock`
- `php artisan erp:sync-localities`
- `php artisan erp:sync-attributes`

#### 📦 Published assets

- `php artisan vendor:publish --tag="lunar.erp.config"`
- `php artisan vendor:publish --tag="lunar.erp.migrations"`

### File Changes

#### Added

- packages/core/src/Base/HasCustomerGroupAvailability.php
- packages/core/src/Base/Traits/HasDiscount.php
- packages/core/src/Base/Traits/HasStock.php
- packages/core/src/DiscountTypes/AdvancedAmountOff.php
- packages/core/src/Events/DiscountUpdatedEvent.php
- packages/core/src/Events/ProductCreatedEvent.php
- packages/core/src/Events/ProductDeletedEvent.php
- packages/core/src/Events/ProductUpdatedEvent.php
- packages/core/src/Exceptions/InsufficientStockException.php
- packages/core/src/Exceptions/OutOfStockException.php
- packages/core/src/Exceptions/UnsupportedWeightUnitException.php
- packages/review/src/Mixins/ProductMixin.php

#### Deleted

- None

#### Modified

- packages/admin/resources/lang/en/discount.php
- packages/admin/resources/lang/en/product.php
- packages/admin/resources/lang/hu/discount.php
- packages/admin/resources/lang/hu/product.php
- packages/admin/resources/lang/ro/discount.php
- packages/admin/resources/lang/ro/product.php
- packages/admin/src/Filament/Resources/DiscountResource.php
- packages/admin/src/Filament/Resources/ProductResource.php
- packages/core/config/cart.php
- packages/core/src/Actions/Carts/CreateOrder.php
- packages/core/src/Base/Casts/DiscountBreakdown.php
- packages/core/src/Base/DiscountManagerInterface.php
- packages/core/src/Base/Validation/CouponValidator.php
- packages/core/src/Managers/DiscountManager.php
- packages/core/src/Models/Cart.php
- packages/core/src/Models/CartLine.php
- packages/core/src/Models/Collection.php
- packages/core/src/Models/Discount.php
- packages/core/src/Models/Order.php
- packages/core/src/Models/OrderLine.php
- packages/core/src/Models/Product.php
- packages/core/src/Models/ProductVariant.php
- packages/core/src/PaymentTypes/OfflinePayment.php
- packages/core/src/Pipelines/Cart/Calculate.php
- packages/core/src/Pipelines/Cart/CalculateLines.php
- packages/core/src/Validation/CartLine/CartLineStock.php
- packages/review/src/Mixins/ProductVariantMixin.php
- packages/review/src/ReviewServiceProvider.php
- packages/shipping/src/Services/ShippingService.php
- packages/table-rate-shipping/resources/lang/en/relationmanagers.php
- packages/table-rate-shipping/resources/lang/hu/relationmanagers.php
- packages/table-rate-shipping/resources/lang/ro/relationmanagers.php

## Admin Package

### Field Types Enhancements

#### File Field Type

- **Storage Disk Configuration**: Added ability to configure which storage disk to use for file uploads (`disk` option)
- **Directory Configuration**: Added ability to specify upload directory (`directory` option)
- Translations added for new configuration options in all supported languages

#### TranslatedText Field Type

- **Disable Richtext Toolbar**: New option to disable the richtext editor toolbar while keeping richtext functionality (`disable_richtext_toolbar`)
- Useful for fields that need simple multi-line text with limited formatting

## Core Package

### Field Types Configuration

#### File Field Type

- Configuration schema updated to support `disk` and `directory` options
- Schema validation added for new string-type configuration fields

#### TranslatedText Field Type

- Configuration schema updated to support `disable_richtext_toolbar` option
- Allows fine-grained control over editor behavior

### URL Generator

Major improvements to the URL generator system:

- **Multi-language Support**: URL generation now supports multiple languages with per-language slug generation
- **Language Context**: Added `setLanguage()` and `getLanguage()` methods for language-specific URL handling
- **Attribute-based Generation**: New `generateUrlsForAttribute()` method generates URLs for all configured languages
- **Simple Property Support**: When a model has a simple `name` property, URL is generated only for the default language
- **Attribute Support**: When a model has translatable attributes (in `attribute_data`), URLs are generated for all languages using `translateAttribute()`
