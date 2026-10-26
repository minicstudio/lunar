# Design

## Context

See proposal.md for why this mode exists. Inventory is already a string on `product_variants.purchasable` (default `always`), plus integer `stock` and `backorder`. The admin Purchasability select offers `always`, `in_stock`, and `in_stock_or_on_backorder`. Both the single-variant product Inventory page and the variant Inventory page use that same select.

`canBeFulfilledAtQuantity()` returns true only for `always`. Every other value is limited to `getTotalInventory()`: stock alone for `in_stock`, otherwise stock plus backorder. Cart stock checks call that method when `lunar.cart.stock_check.enabled` is true. `decreaseStock()` and `increaseStock()` still adjust quantities for `always` and for `in_stock_or_on_backorder`. Only `in_stock` skips the backorder side of those adjustments.

`ConvertBackOrderPurchasability` still runs after migrations and when there are no pending migrations. It rewrites stored `purchasable = backorder` to `in_stock_or_on_backorder`. The storefront in `lunar-frontend` still has tests for the old `backorder` string and treats only `always` as an unlimited quantity.

The product feed maps `canBeFulfilledAtQuantity(1)` to `in_stock` or `out_of_stock`.

## Goals / Non-Goals

**Goals:**

- Add one purchasable mode the admin can save and later change.
- Make that mode fulfillable at any positive quantity, with stock methods leaving quantities unchanged.
- Report it as preorder in the product feed.
- Leave `always`, `in_stock`, and `in_stock_or_on_backorder` on their current paths.

**Non-Goals:**

- Storefront labels, quantity caps, and Algolia availability in `lunar-frontend`.
- Mailchimp and Klaviyo bulk sync filters.
- A schema change or a data backfill.

## Decisions

### Store `preorder`, label it Preorder

The dropdown label is Preorder (Hungarian: Előrendelhető). The stored value is `preorder`.

Reusing the string `backorder` would be rewritten to `in_stock_or_on_backorder` the next time `ConvertBackOrderPurchasability` runs. Removing that converter is unnecessary: historical rows should already have been rewritten, and the converter is what protects any leftover rows. `preorder` is also distinct from `always`, so the storefront can show a preorder state later without guessing.

The stock and backorder number fields stay on the form. They are not read for fulfillment. When Purchasability is Preorder, each of those fields shows a short helper that the value is not used. Other purchasability options do not show that helper. English: "This value is not used for Preorder." Hungarian: "Ez az érték nem számít előrendelésnél."

### Fulfillment ignores quantities; stock methods no-op

`canBeFulfilledAtQuantity()` returns true for `preorder` the same way it does for `always`. Cart validation then allows any positive quantity while stock checking is on.

`decreaseStock()` and `increaseStock()` return without writing when the variant is `preorder`. `always` and `in_stock_or_on_backorder` keep moving stock and backorder. `getTotalInventory()` stays a quantity sum. It is not the purchasability signal for this mode.

### Product feed uses the preorder token

If purchasable is `preorder`, feed availability is `preorder` before the fulfillable check. Other variants keep the current `in_stock` / `out_of_stock` mapping. That avoids advertising a zero-stock preorder variant as in stock once fulfillment always succeeds.

### Translations

Add `purchasable.options.preorder` in all 16 `productvariant` locale files. English is Preorder. Hungarian is Előrendelhető. Other locales get a preorder label, not a copy of "In stock or on backorder". Add the stock and backorder helper string in the same 16 files.

The Polish file is the only locale missing `in_stock_or_on_backorder`; it has an unused `backorder` option key instead. While editing that file, add the missing existing key so the current dropdown option keeps a label. Leave the unused `backorder` key in place.

## Risks / Trade-offs

- [Storefront quantity selector still caps anything except `always` at `getTotalInventory()`] → Engine cart checks will allow the line. The shop UI will not, until `lunar-frontend` treats `preorder` as unlimited and shows a preorder label. That follow-up is outside this repo.
- [Rollback makes `preorder` rows stock-limited] → Unknown values use stock plus backorder. To keep them unlimited after a rollback, set those rows to `always` before or with the revert.
- [A feed consumer that only accepts `in_stock` and `out_of_stock` may drop `preorder`] → `preorder` is the standard product-feed availability for this case. Do not map it back to `in_stock`.
- [A later edit points the legacy converter at `preorder`] → The new value must stay out of `ConvertBackOrderPurchasability`.

## Migration Plan

No migration. Deploy the code. Existing variants are unchanged until an administrator selects Preorder.

Rollback is a code revert. Variants already saved as `preorder` then follow the stock-plus-backorder path. If they must stay unlimited without this code, update them to `always`.

## Open Questions

None.
