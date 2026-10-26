# Tasks

## 1. Preorder fulfillment

- [x] 1.1 Treat `purchasable = preorder` as fulfillable at any positive quantity in `ProductVariant::canBeFulfilledAtQuantity()`, and make `decreaseStock()` and `increaseStock()` return without changing stock or backorder for that mode. Leave `always`, `in_stock`, and `in_stock_or_on_backorder` on their current paths, and do not change `ConvertBackOrderPurchasability`. Verify with `php artisan test tests/core/Unit/Models/ProductVariantTest.php`: a `preorder` variant with stock 0 and backorder 0 can be fulfilled at quantity 10; decrease and increase leave both quantities unchanged; an `always` variant with stock still decreases stock; an `in_stock_or_on_backorder` variant with stock 1 and backorder 2 can be fulfilled at 3 and not at 4.
- [x] 1.2 Add a `CartLineStock` case for `preorder` with stock 0, backorder 0, a positive quantity, and stock checking enabled, and expect no `OutOfStockException`. Keep the existing `in_stock` rejection case. Verify with `php artisan test tests/core/Unit/Validation/CartLine/CartLineStockTest.php`.
- [x] 1.3 Add a test that `ConvertBackOrderPurchasability` still rewrites `backorder` to `in_stock_or_on_backorder` and leaves `preorder` unchanged. Verify that test passes.
- [x] 1.4 Update the Inventory Management bullets in `docs/system/PROJECT_SPECIFICATION.md` so `preorder` is always fulfillable and excluded from stock movement, while the existing modes stay described as they work today. Verify the section names `preorder` and still describes `always`, `in_stock`, and `in_stock_or_on_backorder`.

## 2. Admin Preorder option

- [x] 2.1 Add `preorder` to `ProductVariantResource::getPurchasableFormComponent()` and add `purchasable.options.preorder` in all 16 `packages/admin/resources/lang/*/productvariant.php` files. English label: Preorder. Hungarian label: Előrendelhető. Other locales get a preorder label, not a copy of "In stock or on backorder". In the Polish file, also add the missing `in_stock_or_on_backorder` key and leave the unused `backorder` option key in place. Verify the select options include `preorder` and each locale file contains `options.preorder`.
- [x] 2.2 Keep the stock and backorder inputs visible. When Purchasability is `preorder`, show helper text on each of those inputs: English "This value is not used for Preorder.", Hungarian "Ez az érték nem számít előrendelésnél.", with the same meaning in the other 14 locales. Hide that helper for the other purchasability options. Verify `ManageProductInventoryTest` and `ManageVariantInventoryTest` see the helper when the saved value is `preorder` and do not see it when the value is `in_stock`.
- [x] 2.3 Extend `tests/admin/Feature/Filament/Resources/ProductResource/Pages/ManageProductInventoryTest.php` and `tests/admin/Feature/Filament/Resources/ProductVariantResource/Pages/ManageVariantInventoryTest.php` so saving Purchasability Preorder persists `preorder`, and a later save to `in_stock` replaces it. Verify both files pass with `php artisan test` on those paths.

## 3. Product feed availability

- [x] 3.1 In `ProductFeedService`, set availability to `preorder` when the variant purchasable is `preorder`, before the existing fulfillable `in_stock` / `out_of_stock` mapping. Verify with `php artisan test tests/product-feed/Feature/ProductFeedEndpointTest.php`: a `preorder` variant is `preorder` in the feed, and an `in_stock` variant with stock of at least 1 stays `in_stock`.
- [x] 3.2 Add a `docs/changelog/2026-10-23.md` entry, in the same bullet style as `docs/changelog/2026-10-22.md`, stating that Purchasability Preorder stores `preorder`, sells without stock movement, and is reported as preorder in the product feed. Verify the file exists and mentions those three points.
