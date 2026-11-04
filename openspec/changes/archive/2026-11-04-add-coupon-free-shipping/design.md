# Design

## Context

See proposal.md (Why) for the request. Facts from the code that shape the approach:

- A cart runs `lunar.cart.pipelines.cart`. This repository's default order is `CalculateLines → ApplyShipping → CalculateShippingSubTotal → ApplyDiscounts → CalculateTax → Calculate`. `lunar-frontend` and every host checked (awg, best-office, varga-mezes, webshop-demo, misstik, cuscadecaine) publish their own `config/lunar/cart.php` that runs **`ApplyDiscounts` before `ApplyShipping`**. misstik and cuscadecaine do not list `CalculateShippingSubTotal` at all. `CalculateTax` and `Calculate` come after both stages in every config.
- `LunarServiceProvider` loads `config/cart.php` with `mergeConfigFrom()`, which merges top-level keys only. A published `config/lunar/cart.php` therefore replaces the whole `pipelines` key, and a stage added to the package default does not reach a host until the host lists it.
- A cart stage is a class with `handle(CartContract $cart, Closure $next)`, run by `Cart::calculate()` from `lunar.cart.pipelines.cart`. `CalculateShippingSubTotal` is the closest example. `LoyaltyServiceProvider::registerPipelines()` finds `ApplyDiscounts` and `Calculate` by class in the configured list and inserts `ApplyLoyaltyRedemption` directly after the first and `AdjustCartTotalsForLoyalty` directly after the second.
- `ApplyShipping` builds a fresh `ShippingBreakdown` on each run from the selected option. It sets `shippingAddress->shippingSubTotal` only when the cart had no breakdown yet. `CalculateTax` prices shipping tax from `shippingBreakdown->items->sum('price.value')` and writes `shippingTotal`, `shippingTaxTotal`, and the address tax fields. `Calculate` adds `shippingSubTotal` to `total`.
- `DiscountManager::apply()` runs three passes: one automatic discount per line (`filterDiscountsByPriority()` → `applyPercentageForLine()` / `applyFixedValueForLine()`), then Buy X Get Y, then the cart's coupon (`applyCouponForCart()`). `queryDiscounts($cart)` loads active, usable discounts for the channel and customer groups. It includes discounts with no product, variant, or collection rows (`whereDoesntHave` branches), so global and brand-only discounts reach every cart. They are ordered by priority desc, then id.
- `filterDiscountsByPriority()` sorts candidates into tiers (variant → product → collection → global) and returns the best of the first non-empty tier. When every candidate in that tier is worth 0, `getHighestValueDiscount()` returns null and the result is `collect([null])`. A lower tier is never tried. Catalog display (`getDiscountForPurchasable()`, used by `HasDiscount::hasDiscount()`, labels, and discounted prices) uses the same method. So a 0-value discount in a higher tier would remove a real discount from the cart and from the catalog.
- `checkDiscountConditions()` measures the minimum cart amount on the eligible lines' current `subTotalDiscounted ?? subTotal`. For a coupon below its minimum, it clears `cart.coupon_code` as a side effect. For a couponless discount it has no side effect.
- `applyCouponForCart()` returns before it records anything when its amount is 0, and line paths do the same. A discount that takes nothing off never appears in `cart.discounts` or `discountBreakdown`. The order's `discount_breakdown`, `appliedCoupon`, and the storefront's `MarkDiscountsAsUsed` all read that breakdown.
- `Calculate` sums coupon breakdown prices into `couponTotal` and subtracts it from `total`. A shipping saving put into that breakdown would be subtracted a second time after shipping is already 0.
- `CreateShippingLine` and `UpdateOrderProductLines::writeShippingLine()` copy `unit_price` from the option and `sub_total` / `tax_total` / `total` from the shipping address. Both read `discount_total` from `shippingSubTotal->discountTotal`, which `Price` does not have, so it is always 0 today.
- `Cart` keeps runtime totals in public properties listed in `cachableProperties` (Blink, same request).
- `SplitOrder` prices each target's shipping through `shippingOptionOverride`, with the amount staff entered.

## Goals / Non-Goals

**Goals:**

- One check decides whether a discount grants free shipping. One pipeline stage waives it.
- Correct totals under both pipeline orders, with one placement rule for that stage in every cart config: directly before `CalculateTax`.
- Free shipping never changes which discount prices a line or what the catalog shows.
- The order keeps the original shipping price and the waived amount for support and reporting.

**Non-Goals:**

- Free shipping for Buy X Get Y discounts.
- Free shipping limited to some shipping methods or zones. The disabled `ShippingDiscount` type stays disabled.
- Changing option prices returned by `ShippingManifest::getOptions()`. The storefront labels options from the cart flag (see `lunar-frontend` change `storefront-coupon-free-shipping`).
- A "free shipping" badge or index field on products.
- Free shipping on cart-session shipping estimates (`CartSessionManager::estimateShipping()`) and on split targets. Both use `shippingOptionOverride`, and `lunar-frontend` does not call the estimate.
- Free shipping for a host whose published `config/lunar/cart.php` does not list the new stage. We update `lunar-frontend` and every host's config in the rollout instead.

## Decisions

### Flag in `data.free_shipping` on Advanced amount off

The toggle lives in the existing amount-off section of `DiscountResource` and is saved inside the `data` JSON, next to `fixed_value`. It needs no coupon. No migration. `queryDiscounts()` keeps loading a shipping-only discount because its `data` is not empty.

*Alternative:* a `free_shipping` column on `discounts`. Rejected: it needs a migration, and every other reward setting already lives in `data`.

*Alternative:* enable `ShippingDiscount`. Rejected: one cart holds one coupon, so "10% + free shipping" cannot be two discounts. Its breakdown entry would also be subtracted a second time by `Calculate` (see Context).

### One grant check on `AdvancedAmountOff`

`AdvancedAmountOff::grantsFreeShipping(CartContract $cart): bool` is true when `data.free_shipping` is true, `checkDiscountConditions($cart)` passes, and `getEligibleLines($cart)` is not empty. Both sources use it, so customer lists, minimum amount, usage limits, limitations, and exclusions behave the same for coupons and automatic discounts.

### Coupon: decided inside `applyCouponForCart()`, before its amount

The coupon is checked right after its own `checkDiscountConditions()` passes, before the fixed and percentage branches and their early returns. When it grants free shipping, it sets `$cart->freeShippingDiscount = $this->discount`. After the amount path, if the coupon added no breakdown entry, it is pushed to `$cart->discounts` and gets one `DiscountBreakdown` with price 0 and no lines.

The check must run before the coupon's own amount is applied. Checking afterwards would measure the minimum on lines the coupon has just reduced. That could clear `cart.coupon_code` for a coupon that passed a moment earlier.

### Automatic: a separate pass in `DiscountManager::apply()`

After the per-line and Buy X Get Y passes and before the coupon branch, `apply()` walks the loaded discounts that have no coupon, are `AdvancedAmountOff`, and have `data.free_shipping` true. The collection is already in priority desc, id order. It keeps the first one for which `grantsFreeShipping()` is true as a candidate. The minimum is then measured after automatic line discounts and before the coupon, which is how automatic discounts are measured today. After the coupon branch, if the coupon did not set `freeShippingDiscount`, the candidate is recorded. If the candidate has no breakdown entry of its own (it priced no line), it is pushed to `$cart->discounts` and gets one zero-price entry with no lines.

This pass does not touch line prices, so the per-line winner stays as it is.

*Alternative:* grant free shipping only when the flagged discount wins a line. Rejected: a stronger variant-level discount would silently remove the free shipping the promotion advertises. A 0% shipping-only discount would also never win.

### Shipping-only discounts skip the per-line and catalog choice

`filterDiscountsByPriority()` skips a discount whose `data` takes nothing off products. That is `fixed_value` off and an empty or 0 `percentage`, or `fixed_value` on and no positive `fixed_values`. It is skipped before tiering, so it cannot fill a tier and block a lower one. The same method serves the cart lines and `getDiscountForPurchasable()`, so product pages, cards, labels, discounted prices, and the Algolia price fields built from them treat it as no discount.

*Alternative:* let a tier whose best candidate is worth 0 fall through to the next tier. Rejected: it also changes the documented rule that a rejected fixed-value discount wins nothing in its tier. That is a separate behavior change.

### Waive in its own pipeline stage, directly before `CalculateTax`

A new `Lunar\Pipelines\Cart\ApplyDiscountFreeShipping` (a `final` stage with `handle(CartContract $cart, Closure $next)`, like `CalculateShippingSubTotal`) does the waiver. It sets `shippingDiscountTotal` to null. Then, if `freeShippingDiscount` is set, `shippingOptionOverride` is empty, and the breakdown has items:

- it sums the item prices into `shippingDiscountTotal`, or leaves it null when the sum is 0;
- it replaces each item with a 0-priced `ShippingBreakdownItem`;
- it sets `shippingSubTotal` and the address `shippingSubTotal` / `shippingTotal` to 0.

It then passes the cart on. `CalculateTax` is not changed. It prices shipping tax from the breakdown, so it prices it on 0 and writes `shippingTotal` 0, and `Calculate` adds 0 shipping to `total`.

The default `pipelines.cart` in `packages/core/config/cart.php` becomes `CalculateLines → ApplyShipping → CalculateShippingSubTotal → ApplyDiscounts → ApplyDiscountFreeShipping → CalculateTax → Calculate`. Host configs put the stage in the same place: `CalculateLines → ApplyDiscounts → ApplyShipping → [CalculateShippingSubTotal →] ApplyDiscountFreeShipping → CalculateTax → Calculate`.

Directly before `CalculateTax` is after both `ApplyDiscounts` and `ApplyShipping` in every pipeline order in use. In the host order, `ApplyShipping` has already rebuilt this pass's breakdown. In the fork order, `ApplyDiscounts` has already set this pass's flag. `ApplyDiscounts` resets `freeShippingDiscount` together with `discounts` and `discountBreakdown`. So the stage reads one breakdown and one flag, both from the current pass. It sets `shippingSubTotal` itself, so it does not depend on `CalculateShippingSubTotal` being configured. The loyalty package inserts `ApplyLoyaltyRedemption` directly after `ApplyDiscounts`, so the new stage stays directly before `CalculateTax` when loyalty is installed. One placement rule covers every config, and the configured list shows every stage that changes shipping totals.

*Alternative:* call the waiver as an action at the start of `CalculateTax::handle()`, so hosts keep their published config. Rejected: the tax stage would change shipping totals without the config showing it, and a host that replaces `CalculateTax` would lose the waiver without noticing. We update every host's published config, so working without a config change is not a requirement.

*Alternative:* keep `ApplyDiscountFreeShipping` as an action and add a stage that only calls it. Rejected: nothing else calls the waiver, so the action would be a second class with the same name and no second use.

*Alternative:* zero the breakdown at the end of `DiscountManager::apply()`, like `ShippingDiscount`. Rejected: in every host `ApplyShipping` runs afterwards and rebuilds the breakdown at full price.

*Alternative:* zero it in `ApplyShipping`. Rejected: in this repository's default order the flag is not set yet, and a flag left from the previous pass would waive shipping for a discount that no longer qualifies.

### New runtime properties on `Cart`

`public ?DiscountContract $freeShippingDiscount = null` and `public ?Price $shippingDiscountTotal = null`, both added to `cachableProperties` so a Blink-restored cart in the same request still reports them. `Contracts\Cart` declares no property docs today and stays unchanged. The storefront needs nothing else from the engine to label options and rows. It can tell a coupon source from `filled($cart->freeShippingDiscount->coupon)`.

### Order snapshot on the shipping line

`CreateShippingLine` and `UpdateOrderProductLines::writeShippingLine()` write `discount_total` from `$cart->shippingDiscountTotal?->value ?? 0`. This replaces the always-0 `shippingSubTotal->discountTotal` read. `unit_price` keeps the option price, so the line reads "19.90, discount 19.90, total 0". `Order` gets a `shippingDiscountTotal()` attribute (`shipping_discount_total`, a `Price`) summing `shippingLines.discount_total`. Order-level `shipping_total` comes from `cart.shippingTotal` and is 0 with no extra code.

*Alternative:* store the saving in `order.meta`. Rejected: the shipping line already has a `discount_total` column, and admin edits rewrite the line from the cart anyway.

## Risks / Trade-offs

- [A global automatic free-shipping discount makes all shipping free] → Intended. The helper text says that without a coupon code, the discount applies when any cart line is eligible. Admins limit it with dates, a minimum cart amount, limitations, or customer groups.
- [Existing 0-value automatic discounts no longer block lower tiers] → Today such a discount (empty percentage) hides every lower-tier discount for its products. After this change those products get the lower-tier discount. `UPGRADE.md` and the changelog say so. A test covers a variant-level 0% discount next to a 10% collection discount.
- [`max_uses` on an automatic free-shipping discount] → Each order whose free shipping it granted uses one, as do orders it priced a line for today. The helper text says so.
- [A host's published `config/lunar/cart.php` does not list the stage] → That host records `freeShippingDiscount`, so the storefront labels options "FREE", but shipping is charged and `shippingDiscountTotal` is null. We add the stage to `lunar-frontend` and every host config in the change that requires the new fork version. `UPGRADE.md` lists it as a required step. A test pins the default config order, and a pipeline test shows that a pipeline without the stage charges shipping.
- [The stage is listed in the wrong place] → Before `ApplyShipping` in the host order, `ApplyShipping` rebuilds the breakdown at full price afterwards. Before `ApplyDiscounts` in the fork order, it reads a flag left from an earlier pass in the same request. The stage's PHPDoc, `docs/design/pricing_and_discounts.md`, and `UPGRADE.md` give one rule: directly before `CalculateTax`, or before the host's own tax stage. A pipeline test runs the same cart through the fork order, the host order, and the host order without `CalculateShippingSubTotal`.
- [Zero-amount breakdown entries reach code that assumes `lines` is not empty or `price > 0`] → `MapDiscountBreakdown`, `Order::couponDiscountTotals()`, and the breakdown cast iterate lines and handle an empty collection. Tests place orders with a shipping-only coupon and a shipping-only automatic discount, and read `coupon_total`, `appliedCoupon`, and `discount_breakdown`.
- [Extra work per calculation] → The automatic pass checks only discounts already loaded for the cart that have `data.free_shipping` true. It stops at the first that qualifies.
- [Option prices from `ShippingManifest` still show the full price] → By design. `lunar-frontend` labels options from `cart.freeShippingDiscount`. Admin screens that list options are not part of the request.
- [Order split targets do not get free shipping automatically] → The split preview suggests the option's full price. Staff can enter 0. Documented in `UPGRADE.md` and the changelog.

## Migration Plan

No migration or backfill. Existing discounts have no `data.free_shipping`, so their shipping behaves as today. The only change for existing data is the 0-value tier fix above. Release the fork first. `lunar-frontend` and each host then require that version and, in the same change, add `ApplyDiscountFreeShipping::class` to their published `config/lunar/cart.php` directly before `CalculateTax::class`. `lunar-frontend` also reads the new cart properties. Rollback: revert the fork release and remove the stage from the host configs in the same step, because a config that lists a class the fork no longer has breaks every cart calculation. Stored `data.free_shipping` values are then ignored, and existing orders keep their stored shipping lines.
