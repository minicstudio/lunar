# Proposal

## Why

Administrators can already sell a variant from stock, or from stock plus a limited backorder quantity, but they cannot mark a variant as preorder-only. A preorder variant must stay buyable without stock or backorder quantities, and the shop must be able to tell that mode apart from "Always" and from "In stock or on backorder".

## What Changes

- Add a Purchasability option labeled Backorder on the product Inventory page (single-variant products) and on the variant Inventory page. The stored value is a new purchasable mode for preorder, distinct from `in_stock_or_on_backorder`.
- The stock and backorder inputs stay visible. When Backorder is selected, each shows a short helper that the value is not used.
- Saving the option persists it on `product_variants.purchasable`. No new column.
- A variant in this mode can be fulfilled at any requested quantity. Stock and backorder quantities are ignored.
- Stock movement methods do not change stock or backorder for this mode. Existing modes keep their current stock behavior.
- The product feed reports this mode as preorder availability instead of in stock.
- Existing modes (`always`, `in_stock`, `in_stock_or_on_backorder`) stay unchanged.

## Capabilities

### New Capabilities

- `inventory/preorder-purchasability`: Admin selection, persistence, and sell-without-stock behavior for a preorder-only variant purchasability mode, including how catalog availability is reported.

### Modified Capabilities

- None. This repository has no existing OpenSpec capabilities.

## Impact

- Admin: `ProductVariantResource::getPurchasableFormComponent()`, used by `ManageProductInventory` and `ManageVariantInventory`, plus all 16 `productvariant` locale files.
- Core: `ProductVariant::canBeFulfilledAtQuantity()`, `decreaseStock()`, and `increaseStock()`. Cart stock checks already go through `canBeFulfilledAtQuantity()`, so they follow the new mode when stock checking is enabled.
- Product feed: `ProductFeedService` availability, which today maps any fulfillable variant to `in_stock`.
- Legacy data: `ConvertBackOrderPurchasability` still rewrites stored `purchasable = backorder` to `in_stock_or_on_backorder` after migrations. The new mode must not reuse that stored value.
- Storefront: customer-facing preorder labels, quantity caps, and Algolia availability live in `lunar-frontend`, not this repository. This change exposes the mode on the variant. Showing "preorder" in the shop is a follow-up in that repo. Bulk Mailchimp and Klaviyo sync still select variants with `stock > 0` or a truthy `backorder` quantity, so a zero-stock preorder variant is not included unless that follow-up is requested.
