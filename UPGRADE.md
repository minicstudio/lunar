# Upgrade Guide

This document outlines the key changes and new features in the Admin and Core packages.

## Unreleased (2026-03-04)

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

### Table-rate Shipping Package

- Translation updates for relation manager labels (EN/HU/RO).

### File Changes (from git)

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

---

**Note**: These changes are backward compatible with existing implementations. No breaking changes introduced.
