# Tasks

## 1. Admin Free shipping toggle

- [x] 1.1 In `DiscountResource::getAmountOffFormComponents()`, add `Toggle::make('data.free_shipping')` after the fixed value toggle, with `lunarpanel::discount.form.free_shipping.label` / `.helper_text`. The section is only visible for `AmountOff` and `AdvancedAmountOff`, so Buy X Get Y does not show it. Do not add a save check. Add both keys to all 16 `packages/admin/resources/lang/*/discount.php` files. Hungarian label: "Ingyenes szállítás". Hungarian helper: "Kuponkóddal: a kupon használatakor minden szállítási mód ingyenes. Kuponkód nélkül: ha a kosárban van legalább egy termék, amire a kedvezmény érvényes. A százalék vagy fix összeg üresen hagyható, ha csak ingyenes szállítást ad. Minden így kiszolgált rendelés egy felhasználásnak számít." Romanian: label "Livrare gratuită", with the same meaning for the helper. English for the other locales. Extend `tests/admin/Feature/Filament/Resources/DiscountResource/Pages/EditDiscountTest.php`: saving with free shipping and coupon `SHIPFREE` stores `data.free_shipping` true and the toggle is filled on reload; saving with free shipping, no coupon, and an empty percentage stores it with no error. Verify with `./vendor/bin/pest tests/admin/Feature/Filament/Resources/DiscountResource/Pages/EditDiscountTest.php`, and check that every locale file has the keys (`grep -L "'free_shipping'" packages/admin/resources/lang/*/discount.php` prints nothing).

## 2. Discounts grant free shipping

- [x] 2.1 Add `public ?DiscountContract $freeShippingDiscount = null` and `public ?Price $shippingDiscountTotal = null` to `Lunar\Models\Cart`, each with a PHPDoc block, and add both names to `cachableProperties`. In `ApplyDiscounts::handle()`, reset `$cart->freeShippingDiscount = null` next to `discounts` and `discountBreakdown`. Verify with `./vendor/bin/pest tests/core/Unit/Models/CartTest.php` and with a test in `tests/core/Unit/Pipelines/Cart/`: a cart whose `freeShippingDiscount` was set before `recalculate()` has it null afterwards when no free-shipping discount qualifies.

- [x] 2.2 In `AdvancedAmountOff`, add a public `grantsFreeShipping(CartContract $cart): bool` (PHPDoc). It is true when `data.free_shipping` is true, `checkDiscountConditions($cart)` passes, and `getEligibleLines($cart)` is not empty. Also add a protected helper that adds a 0 `Price` `DiscountBreakdown` with `collect()` lines, and pushes `$this` to `$cart->discounts`, when `$cart->discountBreakdown` has no entry for this discount. In `applyCouponForCart()`, right after `checkDiscountConditions()` passes and before the fixed value branch, set `$cart->freeShippingDiscount = $this->discount` when `data.free_shipping` is true and eligible lines exist. Do not call the conditions a second time after the amount is applied. After the amount path, call the helper when the coupon set the flag. Do the same in `apply()` when the discount has a coupon. Read `percentage` with `?? 0`. Extend `tests/core/Unit/DiscountTypes/AdvancedAmountOffTest.php`. Cover: a shipping-only coupon (empty percentage) sets the flag, has one 0 breakdown entry, and `couponTotal` 0; a 10% coupon with free shipping keeps its 10% breakdown and has no second entry; a fixed-value coupon with free shipping spreads its amount as before; a coupon below `min_prices` does not set the flag (and the coupon is cleared, as today); a coupon that passes `min_prices` before its own 10% but would not after it still sets the flag and keeps `coupon_code`; a collection-limited coupon with no eligible line does not set the flag. Verify with `./vendor/bin/pest tests/core/Unit/DiscountTypes/AdvancedAmountOffTest.php`.

- [x] 2.3 In `DiscountManager::apply()`, after the per-line and Buy X Get Y passes and before the coupon branch, walk `$this->discounts` (already priority desc, id) for couponless `AdvancedAmountOff` discounts with `data.free_shipping` true. Keep the first whose `getType()->grantsFreeShipping($cart)` is true. After the coupon branch, if `$cart->freeShippingDiscount` is still null and there is a candidate, record it and call the breakdown helper for it. Extend `tests/core/Unit/Managers/DiscountManagerTest.php`. Cover: five lines, one in the collection of an automatic free-shipping discount, so the flag is that discount; that line priced by a variant-level 20% discount still gets 20% only, and the flag is still set; a global automatic free-shipping discount sets the flag for any cart; an inactive one, one outside the customer group, one below `min_prices`, and one with `max_uses_per_user` on a guest cart do not set it; a qualifying coupon and an automatic one, so the coupon wins; automatic priorities 5 and 2, so priority 5 wins; a shipping-only automatic discount has one 0 breakdown entry, and one that also priced a line has no second entry. Verify with `./vendor/bin/pest tests/core/Unit/Managers/DiscountManagerTest.php`.

- [x] 2.4 In `DiscountManager::filterDiscountsByPriority()`, skip a discount before tiering when its `data` takes nothing off products: `fixed_value` off and `percentage` empty or 0, or `fixed_value` on and no positive value in `fixed_values`. Add a short PHPDoc note on why. Extend `tests/core/Unit/Managers/DiscountManagerTest.php`: a variant-limited shipping-only discount next to a 10% collection discount leaves the cart line at 10%. Extend `tests/core/Unit/Base/Traits/HasDiscountTest.php`: the same variant has `hasDiscount()` true with the 10% price and label; a product whose only discount is shipping-only has `hasDiscount()` false, no `getDiscountLabels()`, empty `getDiscountAmounts()`, and discounted price equal to original. Verify with `./vendor/bin/pest tests/core/Unit/Managers/DiscountManagerTest.php tests/core/Unit/Base/Traits/HasDiscountTest.php`.

## 3. Waive shipping in its own cart pipeline stage

The waiver was first built as the action `Lunar\Actions\Carts\ApplyDiscountFreeShipping`, called at the start of `CalculateTax::handle()`. These tasks move it into a configured stage.

- [x] 3.1 Add `final class Lunar\Pipelines\Cart\ApplyDiscountFreeShipping` with `handle(CartContract $cart, Closure $next): mixed` and a PHPDoc saying it must run after both `ApplyDiscounts` and `ApplyShipping`, and is therefore listed directly before `CalculateTax`. Move the body of the action's `execute()` into it unchanged: it sets `shippingDiscountTotal` to null; when `freeShippingDiscount` is set, `shippingOptionOverride` is empty, and `shippingBreakdown` has items, it puts the summed item prices into `shippingDiscountTotal` (null when the sum is 0), replaces each item with a `ShippingBreakdownItem` at `new Price(0, $cart->currency, 1)`, and sets `shippingSubTotal`, `shippingAddress->shippingSubTotal`, and `shippingAddress->shippingTotal` to 0. It ends with `return $next($cart)`.
  - Delete `packages/core/src/Actions/Carts/ApplyDiscountFreeShipping.php`.
  - Remove the `ApplyDiscountFreeShipping::run($cart)` call, its comment, and its import from `CalculateTax::handle()`.
  - In `packages/core/config/cart.php`, import the stage and list `ApplyDiscountFreeShipping::class` in `pipelines.cart` directly before `CalculateTax::class`.

  Verify that `git diff main -- packages/core/src/Pipelines/Cart/CalculateTax.php` prints nothing and that `grep -rn "Actions.Carts.ApplyDiscountFreeShipping" packages` prints nothing.

- [x] 3.2 Move `tests/core/Unit/Actions/Carts/ApplyDiscountFreeShippingTest.php` to `tests/core/Unit/Pipelines/Cart/ApplyDiscountFreeShippingTest.php`. Call the stage as `app(ApplyDiscountFreeShipping::class)->handle($cart, fn ($cart) => $cart)`, as `CalculateShippingSubTotalTest.php` does, and keep its four cases: waive and report, no discount, explicit override, 0-priced option and empty breakdown. Add a case that the stage returns what `$next` returns. In `tests/core/Unit/Pipelines/Cart/DiscountFreeShippingTest.php`, list `ApplyDiscountFreeShipping::class` directly before `CalculateTax::class` in all three entries of the `free shipping pipelines` dataset, and add:
  - the default `config('lunar.cart.pipelines.cart')` lists `ApplyDiscountFreeShipping` directly before `CalculateTax`;
  - with the host order and no `ApplyDiscountFreeShipping` stage, a cart with coupon `SHIPFREE` and the 19.90 courier reports `SHIPFREE` as `freeShippingDiscount`, has `shippingDiscountTotal` null and `shippingSubTotal` 19.90, and its `total` includes the shipping and its tax. This shows `CalculateTax` does not waive shipping.

  The existing cases in that file stay and must pass with the stage configured:
  - With a 19.90 courier option and coupon `SHIPFREE`, the cart has `shippingSubTotal` 0, `shippingTaxTotal` 0, `shippingTotal` 0, `shippingDiscountTotal` 19.90, and `total` equal to products after discount and tax.
  - The same result with an automatic free-shipping discount instead of the coupon.
  - Both results in the fork order, the host order (`CalculateLines, ApplyDiscounts, ApplyShipping, CalculateShippingSubTotal, ApplyDiscountFreeShipping, CalculateTax, Calculate`), and the host order without `CalculateShippingSubTotal`.
  - Changing the option and recalculating in the same request waives the new option.
  - Clearing the coupon, or removing the only eligible line of the automatic discount, and recalculating charges shipping again.
  - A 0-priced pickup option leaves `shippingDiscountTotal` null.
  - With `shippingOptionOverride` at 12.00, the shipping stays 12.00.

  Verify that `grep -rn "Actions.Carts.ApplyDiscountFreeShipping" tests` prints nothing, then run `./vendor/bin/pest tests/core/Unit/Pipelines/Cart/ApplyDiscountFreeShippingTest.php tests/core/Unit/Pipelines/Cart/DiscountFreeShippingTest.php` and `./vendor/bin/pest --testsuite core`.

## 4. Store the waiver on the order

- [x] 4.1 In `CreateShippingLine` and `UpdateOrderProductLines::writeShippingLine()`, write `discount_total` as `$cart->shippingDiscountTotal?->value ?? 0` instead of `shippingSubTotal->discountTotal`. Add a `shippingDiscountTotal()` Eloquent attribute to `Lunar\Models\Order` that returns a `Price` with the sum of `shippingLines` `discount_total` values, plus a `@property-read` line.
  - Extend `tests/core/Unit/Pipelines/Order/Creation/CreateShippingLineTest.php`: with a free-shipping discount and a 19.90 option, the line has `unit_price` 19.90, `discount_total` 19.90, and `sub_total`, `tax_total`, `total` 0; without one, `discount_total` is 0.
  - Extend `tests/core/Unit/Actions/Carts/CreateOrderTest.php`. An order from a shipping-only coupon cart has `shipping_total` 0, `shipping_discount_total` 19.90, `appliedCoupon` `SHIPFREE`, `coupon_total` 0, and one `discount_breakdown` entry for it. An order from a shipping-only automatic discount has `shipping_discount_total` 19.90, no `appliedCoupon`, and one breakdown entry for that discount. Calling `getType()->markAsUsed($cart)` on each entry's discount increases `uses`.
  - Extend `tests/core/Unit/Actions/Orders/UpdateOrderProductLinesTest.php` with the three scenarios from the `orders/admin-product-line-editing` delta: the coupon still qualifies, so shipping stays 0 with the line discount; the quantity is lowered below `min_prices`, so full shipping is charged with discount 0; the only line eligible for an automatic free-shipping discount is removed, so full shipping is charged.

  Verify with `./vendor/bin/pest tests/core/Unit/Pipelines/Order/Creation/CreateShippingLineTest.php tests/core/Unit/Actions/Carts/CreateOrderTest.php tests/core/Unit/Actions/Orders/UpdateOrderProductLinesTest.php`.

## 5. Docs

- [x] 5.1 Update `docs/design/pricing_and_discounts.md`:
  - under the discount model, add `data.free_shipping`;
  - add a free-shipping section covering coupon and automatic eligibility, coupon-first precedence, and the zero-amount breakdown entry;
  - under multiple discounts interaction and business rules, add that discounts taking nothing off products are skipped by `filterDiscountsByPriority()`.

  Update `docs/design/checkout.md` with the shipping line `discount_total` and the `shippingOptionOverride` exception. Add `docs/changelog/2026-11-03.md` (or the day of implementation) per `docs/changelog/CHANGELOG.md`. In the `⬆️ Upcoming` section of `UPGRADE.md`, add a Core entry:
  - free shipping works with a coupon or without one;
  - a 0-value automatic discount no longer hides lower-tier discounts;
  - split targets keep their entered shipping.

  Verify that the three files mention `free_shipping`.

- [x] 5.2 Document the waiver as its own cart stage, replacing the text written for the `CalculateTax` call:
  - `docs/design/pricing_and_discounts.md`: add `ApplyDiscountFreeShipping` to the "Price calculation flow" mermaid chart between `ApplyDiscounts` and `CalculateTax`. In the stage table, add an `ApplyDiscountFreeShipping` row directly before `CalculateTax`, and set the `CalculateTax` row back to "Tax on `subTotalDiscounted` when set; builds line/cart/shipping tax". Rewrite the "Free-shipping waiver" paragraph to name the stage `Lunar\Pipelines\Cart\ApplyDiscountFreeShipping`. Rewrite the paragraph after it: the stage needs this pass's discount and breakdown, hosts order `ApplyDiscounts` and `ApplyShipping` either way, and directly before `CalculateTax` is after both in every order and needs no `CalculateShippingSubTotal`. Every published `config/lunar/cart.php` must list it there, because a published config replaces the package's `pipelines` key. Without it, the discount is recorded but shipping is charged.
  - `docs/design/checkout.md`: in the cart calculation pipeline table, add a `Pipelines\Cart\ApplyDiscountFreeShipping` row between `ApplyDiscounts` and `CalculateTax`, renumber the rows after it, and set the `CalculateTax` row back to "Tax breakdown on lines and cart". In the "Free-shipping discount" bullet under the core manifest, say that the `ApplyDiscountFreeShipping` cart stage charges the option at 0.
  - `docs/changelog/2026-11-03.md`: this entry is not merged yet, so correct it instead of adding a contradicting entry on a later day. Replace "Hosts do not need to change their published cart pipeline config; a host with its own tax stage must call `ApplyDiscountFreeShipping` first." with a sentence saying that free shipping is waived by the new cart stage `ApplyDiscountFreeShipping`, which hosts must add to their published `config/lunar/cart.php` directly before `CalculateTax`.
  - `UPGRADE.md`, Core Package entry: add a `#### ⚙️ New/modified configs` subsection, as the Feedback entry has, saying that `pipelines.cart` in `config/lunar/cart.php` lists `Lunar\Pipelines\Cart\ApplyDiscountFreeShipping` directly before `CalculateTax`. Under `#### 💻 Host responsibilities`, replace the "Published `config/lunar/cart.php` needs no change" line and the custom tax stage line with one required step: add `Lunar\Pipelines\Cart\ApplyDiscountFreeShipping::class` to the published `pipelines.cart`, directly before `CalculateTax::class` (or before the host's own tax stage), in whatever order `ApplyDiscounts` and `ApplyShipping` run. Without it, free-shipping discounts are recorded and the storefront labels options "FREE", but shipping is charged.

  Verify that `grep -rn "Actions.Carts.ApplyDiscountFreeShipping" docs UPGRADE.md` prints nothing, and that `grep -ln "Pipelines.Cart.ApplyDiscountFreeShipping" docs/design/pricing_and_discounts.md docs/design/checkout.md UPGRADE.md` lists all three files.

## 6. Integration check

- [x] 6.1 After 3.1, 3.2, and 5.2, run `./vendor/bin/pest --testsuite core`, `./vendor/bin/pest --testsuite admin`, `./vendor/bin/pest --testsuite shipping`, `./vendor/bin/pest --testsuite loyalty`, and `vendor/bin/pint --test` on the changed files. All pass with no new failures against `main`.

## Workflow follow-up

- Release the fork version that contains this change before starting `lunar-frontend` change `storefront-coupon-free-shipping` (it reads `freeShippingDiscount` and `shippingDiscountTotal`).
- Add to `storefront-coupon-free-shipping` the edit of `lunar-frontend`'s published `config/lunar/cart.php`: `Lunar\Pipelines\Cart\ApplyDiscountFreeShipping::class` directly before `CalculateTax::class`, in the same step that requires the new fork version.
- In each host (awg, best-office, varga-mezes, webshop-demo, misstik, cuscadecaine), add the same line to the published `config/lunar/cart.php` in the change that bumps the fork version. Before the release, check for hosts not on this list.
- Archive the change after review.
