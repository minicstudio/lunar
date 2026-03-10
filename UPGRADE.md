# Upgrade Guide

This document outlines the key changes and new features in the Admin, Core, Blog, Review, Shipping and ERP packages.

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
