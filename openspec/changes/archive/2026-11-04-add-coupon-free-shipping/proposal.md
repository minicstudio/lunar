# Proposal

## Why

Marketing asked for a checkbox on a discount that also makes shipping free (LFP-863). Two examples: a customer enters a coupon and pays no shipping, or a cart that contains a promoted product ships free. Today a discount can only take a percentage or a fixed amount off products. The disabled `ShippingDiscount` type cannot do this either. A cart holds only one coupon, so a separate type cannot be combined with an amount-off coupon. An automatic discount is also chosen per line, so it cannot speak for the whole cart.

## What Changes

- The Advanced amount off discount form gets a **Free shipping** toggle, stored as `data.free_shipping`. It works with or without a coupon code. Buy X Get Y gets no toggle.
- A coupon with free shipping grants it when the coupon passes its conditions (customer, minimum cart amount, max uses, per-user limit) and at least one cart line is eligible under its limitations.
- An automatic (couponless) discount with free shipping grants it when it is active, in scope for the cart's channel and customer groups, passes the same conditions, and at least one cart line is eligible. Which discount prices that line does not matter. Example: one eligible product out of five is enough.
- When several discounts qualify, the cart records one as `freeShippingDiscount`: the coupon if it qualifies, otherwise the automatic discount with the highest priority (lowest id on a tie).
- A free-shipping discount may take nothing off products (empty or 0 percentage, empty fixed amount). If the recorded discount has no breakdown entry of its own, it gets one with amount 0 and no lines. `markAsUsed()` (max uses), `Order::appliedCoupon` for coupons, and reporting then see it.
- A shipping-only automatic discount is left out of the per-line and catalog discount choice. It cannot replace or block a lower-tier discount, and products show no discount, label, or discounted price for it. Today a 0-value discount in a higher tier makes `filterDiscountsByPriority()` return no discount at all.
- When the cart has free shipping, every shipping option is free for that calculation. Courier, locker, and personal pickup are all included. The cart shipping breakdown, `shippingSubTotal`, shipping tax, `shippingTotal`, and `total` are calculated with shipping at 0.
- Two new runtime cart properties: `freeShippingDiscount` (the recorded discount, or null) and `shippingDiscountTotal` (the shipping amount waived). The storefront reads them to label options and rows.
- **BREAKING (cart config):** The waiver is a new cart pipeline stage, `ApplyDiscountFreeShipping`, listed in `config/lunar/cart.php` directly before `CalculateTax`. That position works whether a host runs `ApplyDiscounts` before `ApplyShipping` (every storefront host and `lunar-frontend`) or after it (this repository's default config). `CalculateTax` does not waive shipping. Every host with a published `config/lunar/cart.php` must add the stage, and we make that edit in `lunar-frontend` and every host as part of the rollout. A host without the stage records the free-shipping discount but charges shipping. Running without the config change is not supported.
- The order shipping line keeps the option's price in `unit_price`. It stores the waived amount in `discount_total`, with `sub_total`, `tax_total`, and `total` at 0. A new `Order::shipping_discount_total` accessor sums it. This holds at order creation and after an admin product-line edit.
- A cart whose shipping option is set through `shippingOptionOverride` (order split) keeps the override price. The waiver does not change it.

## Capabilities

### New Capabilities

- `discounts/discount-free-shipping`: Admin setting, coupon and automatic eligibility, the choice of one free-shipping discount, the cart calculation of a discount that makes shipping free (alone or with an amount off), the cart pipeline stage that waives it, how shipping-only discounts stay out of product pricing, and how the waived shipping is stored on the order.

### Modified Capabilities

- `orders/admin-product-line-editing`: the "Save recalculates prices, discounts, shipping, and tax" requirement gains scenarios for a free-shipping discount. Shipping stays free while the discount still qualifies, and is charged again when it no longer does. The waived amount is stored on the shipping line.

## Impact

- Admin: `DiscountResource::getAmountOffFormComponents()` and all 16 `resources/lang/*/discount.php` files. `EditDiscount::mutateFormDataBeforeSave()` gets no new check.
- Core discounts: `AdvancedAmountOff` gets one check for whether a discount grants free shipping, called from the coupon entry points. `DiscountManager::apply()` gets a separate pass for automatic free-shipping discounts after the coupon. `DiscountManager::filterDiscountsByPriority()` skips discounts that take nothing off products, which also covers `getDiscountForPurchasable()` and the catalog `HasDiscount` getters.
- Core cart: `Cart` runtime properties and `cachableProperties`, `ApplyDiscounts` (resets the flag), a new pipeline stage `Lunar\Pipelines\Cart\ApplyDiscountFreeShipping` that zeroes the shipping breakdown, and the default `pipelines.cart` in `packages/core/config/cart.php`, which lists it directly before `CalculateTax`. `CalculateTax` and `ApplyShipping` are unchanged.
- Host configs: `lunar-frontend` (in its change `storefront-coupon-free-shipping`) and the hosts awg, best-office, varga-mezes, webshop-demo, misstik, and cuscadecaine each add `ApplyDiscountFreeShipping::class` to their published `config/lunar/cart.php`, directly before `CalculateTax::class`, in the same change that requires the new fork version.
- Orders: `CreateShippingLine`, `UpdateOrderProductLines::writeShippingLine()`, `Order` accessor.
- Unchanged: `Calculate` (coupon totals stay amount-only, because the zero-amount breakdown adds 0), `MapDiscountBreakdown`, `CouponValidator`, `BuyXGetY`, the table-rate drivers, and the disabled `ShippingDiscount` type. ERP exports already handle `shipping_total` 0, as they do today for pickup and threshold free shipping: SmartBill adds a shipping item only when `shipping_total` is above 0, and Magister sends product lines only.
- Storefront: option labels ("FREE"), the shipping and coupon rows, the Stripe wallet amounts, and hiding the free-shipping progress bar belong to `lunar-frontend`. That repo has a separate change, `storefront-coupon-free-shipping`, which depends on this one.
- Docs: `docs/design/pricing_and_discounts.md`, `docs/design/checkout.md`, `docs/changelog/`, `UPGRADE.md`.
