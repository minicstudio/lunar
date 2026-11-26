# product-import-validation Specification

## Purpose

Defines how staged rows are normalized with their columns' formats and checked by deterministic rules, how the preview shows the products and variants an import will create or update, and how staff correct or skip single rows before importing.

## Requirements

### Requirement: Cell values are normalized by fixed rules

Staging MUST derive each row's normalized values from its raw cells and the mapping. Text MUST be trimmed, including no-break and zero-width spaces. Numbers and dates MUST be read with their column's format, without deciding a separator per value. Currency symbols and codes MUST be removed. Prices MUST be converted to the default currency's smallest unit. Weight and dimensions MUST keep the sheet's units. The raw cells MUST stay unchanged.

#### Scenario: Romanian price

- **WHEN** the price column's format has a decimal comma and a dot thousands separator, and the price cell holds "1.249,90 lei"
- **THEN** the normalized price is 1249.90 in the default currency and the raw cell still reads "1.249,90 lei"

#### Scenario: Price with a dot

- **WHEN** the price column's format has a decimal dot and no thousands separator, and the price cell holds "49.90"
- **THEN** the normalized price is 49.90

#### Scenario: Hungarian grouping

- **WHEN** the price column's format has a decimal comma and a space thousands separator, and the cell holds "1 503,85 lei" with a no-break space
- **THEN** the normalized price is 1503.85

#### Scenario: Weight in grams

- **WHEN** the sheet's weight unit is g and the weight cell holds "250"
- **THEN** the normalized weight is 250 with the unit g

#### Scenario: Space from Excel after a SKU

- **WHEN** a SKU cell holds "TB-001-S" followed by a no-break space, and the variant "TB-001-S" exists
- **THEN** the normalized SKU is "TB-001-S" and the row updates that variant

### Requirement: URL slugs are normalized like Lunar's

A URL slug MUST be made from its cell the way Lunar makes slugs, in the column's language.

#### Scenario: Slug from a title

- **WHEN** the Romanian URL slug cell holds "Rochie Vară Roșie"
- **THEN** the normalized slug is "rochie-vara-rosie"

### Requirement: Values that cannot be read are never dropped silently

A cell that cannot be read for its target MUST give its row an error or a warning that names the field and the raw value. This covers a number or a date in another format, an unknown code, an image that is not an HTTP or HTTPS URL, and a slug cell that gives an empty slug. A missing required value, such as the name of a new product, MUST be an error. The raw cell MUST stay unchanged. The row correction form MUST show the raw cell next to the field, so that staff can enter a value that reads.

#### Scenario: Image that is not a web address

- **WHEN** a product row's images cell holds "ftp://cdn.example/1.jpg"
- **THEN** the row has a warning naming the image "ftp://cdn.example/1.jpg", and after staff correct it to "https://cdn.example/1.jpg" the warning disappears and the raw cell is unchanged

#### Scenario: Unknown code

- **WHEN** the backorders column gives "maybe" no meaning and a row's backorders cell holds "maybe"
- **THEN** the row has an error naming backorders and "maybe"

### Requirement: Required values are checked

Every variant row MUST have a value for the match key. A row that creates a product MUST have a product name in the store's default language. A row that creates a variant MUST have a SKU and a price, or a price impact on a product whose row has a price. A missing required value MUST be an error on that row naming the field.

#### Scenario: Missing price on a new variant

- **WHEN** a row would create the variant "TB-001-L-BLU" and its price cell is empty
- **THEN** the row has the error "Missing price"

#### Scenario: Missing match key

- **WHEN** the match key is SKU and a variant row has no SKU
- **THEN** the row has the error "Missing SKU"

#### Scenario: Product row without a SKU and a price

- **WHEN** a product row whose variants are other rows has neither a SKU nor a price, and its variant rows have both
- **THEN** the product row has no error

### Requirement: Value types are checked

Values of number and date targets that do not read in their column's format MUST be errors naming the field, the raw value, and the expected format. A negative price, sale price, discount, or stock MUST be an error; an impact MAY be negative. A stock or minimum quantity with a fraction MUST be an error.

#### Scenario: Text in the stock column

- **WHEN** the stock cell holds "la comanda"
- **THEN** the row has an error that stock "la comanda" is not a whole number

#### Scenario: Value in another format

- **WHEN** the weight column's format has a decimal dot and no thousands separator, and a weight cell holds "1,25"
- **THEN** the row has an error that weight "1,25" does not match the column format with a decimal dot and no thousands separator, and the weight is not read as 125 or 1.25

#### Scenario: Date in another format

- **WHEN** the sale end column's format is "Y-m-d G:i:s" and a cell holds "24.12.2026"
- **THEN** the row has an error that the sale end "24.12.2026" does not match the format "Y-m-d G:i:s"

### Requirement: Match keys are unique within an import

When two or more variant rows in the import, on any sheets, have the same match key value, each of those rows MUST have an error naming the other rows' sheet and row numbers.

#### Scenario: SKU on two sheets

- **WHEN** "TB-001-S-RED" appears on "Produse" row 12 and on "Outlet" row 5
- **THEN** both rows have a duplicate SKU error naming the other row

### Requirement: Variants inside a product group are checked

When two variant rows of a group have the same combination of option values, both MUST have an error. A group with more than one variant row and no option MUST have that error on every variant row. When a group's rows match variants of more than one existing product, the whole group MUST have a conflict error. A variant row with an option name but no value MUST have an error naming the option.

#### Scenario: Same size and color twice

- **WHEN** two rows of the group "TB-001" both have size "M" and color "Red"
- **THEN** both rows have an error that the option combination M / Red is repeated

#### Scenario: Variants of different products

- **WHEN** the group "Geacă puf" has two rows whose SKUs belong to two different existing products
- **THEN** the group has the error "The variants belong to 2 different existing products"

#### Scenario: Variant for any value

- **WHEN** a variant row names the option "Size" with an empty value
- **THEN** the row has an error that the variant needs one value for "Size"

### Requirement: A product row needs variants

A row that is only a product MUST have at least one variant row in its group, or its group MUST match an existing product. Otherwise the row MUST have an error that the product has no variants in the import.

#### Scenario: Product row without its variant rows

- **WHEN** an export holds the product row "Rochie vară", whose type means a product with variant rows, but none of its variant rows
- **THEN** the row has an error that the product has no variants in the import

### Requirement: Rows are matched to existing variants

A variant row whose match key equals an existing variant's value MUST be marked as an update of that variant and its product, including a product in the trash. Other variant rows of the same group MUST become new variants of that product. When existing variants are set to be skipped, matched rows MUST be marked as skipped with a message.

#### Scenario: New size of an existing product

- **WHEN** a group has the existing SKU "HO-114-M" of product #1842 and the new SKU "HO-114-XL"
- **THEN** the group is shown as an update of product #1842 and "HO-114-XL" as a new variant of it

#### Scenario: Product in the trash

- **WHEN** a row matches a variant whose product is in the trash
- **THEN** the row is an update and shows that the product will be restored

### Requirement: Warnings do not block the import

The system MUST show warnings, without blocking, for: stock of 0, a product without a category, an EAN that is not 8, 12, 13, or 14 digits, a status value that is not recognized, and an empty mapped cell on an update row. That empty cell MUST leave the stored value unchanged.

#### Scenario: Empty price on an update

- **WHEN** a row updates an existing variant and its price cell is empty
- **THEN** the row has a warning that the price stays unchanged

### Requirement: URL slugs are checked

A slug that another product already uses in its language, or that another product group of the import uses, MUST give a warning that names the slug with a number suffix that will be used instead. A slug that differs from an existing product's URL in its language MUST give a warning that the product keeps its URL. These warnings MUST NOT block the import.

#### Scenario: Slug taken by another product

- **WHEN** a new product's Romanian slug is "rochie-vara" and another product already has the Romanian URL "rochie-vara"
- **THEN** the row has a warning that the URL "rochie-vara-2" will be used

#### Scenario: Existing product with another URL

- **WHEN** a row updates product #1842, whose Romanian URL is "rochie-de-vara", and its slug cell holds "rochie-vara"
- **THEN** the row has a warning that the product keeps the URL "rochie-de-vara"

### Requirement: Sale prices are reported, not imported

A row with a sale price that differs from its price MUST give a warning that its price was imported and that the file also has the sale price, with the sale's dates when it has them, so that staff can correct the price. The warning MUST disappear when the row's price equals the sale price.

#### Scenario: Sale price next to the price

- **WHEN** a row has the price 199,00, the sale price 149,00, and the sale end 24 December 2026
- **THEN** the row has the warning "The price 199.00 was imported. The file also has the sale price 149.00 (until 24 December 2026). Correct the price to use it."

#### Scenario: Price corrected to the sale price

- **WHEN** staff correct the price of that row to 149,00
- **THEN** the warning disappears and the variant will be imported at 149.00 without a compare price

### Requirement: Discounts are checked

A row with both a discount amount and a discount percent above zero MUST have an error naming both. A discount percent above 100 and a discount whose start is after its end MUST be errors.

#### Scenario: Amount and percent

- **WHEN** a row has the discount amount 20,00 and the discount percent 10
- **THEN** the row has an error that it gives both a discount amount and a discount percent

### Requirement: Discount dates cover whole days

A discount date without a time MUST read as the start of its day for a start and as the end of its day for an end.

#### Scenario: End date without a time

- **WHEN** a sale end column has the date format "Y-m-d" and a row's discount ends on "2026-12-31"
- **THEN** the discount ends on 31 December 2026 at 23:59:59

### Requirement: Discounts that will not apply are reported

A discount whose end is before today MUST give a warning that it is not imported. A discount amount that leaves less of the price than the store's minimum remaining share (10% by default) MUST give a warning that the shop does not apply it to that variant.

#### Scenario: Discount already over

- **WHEN** today is 24 November 2026 and a row's discount of 30% ended on 1 November 2026
- **THEN** the row has a warning that the discount ended on 1 November 2026 and is not imported, and the row is imported without it

#### Scenario: Amount that leaves too little

- **WHEN** a row has the price 59,00 and the discount amount 55,00
- **THEN** the row has a warning that the shop does not apply the discount 55.00 to this variant, because less than 10% of the price would remain

### Requirement: Unresolved references and stock pools are warnings

The system MUST warn, without blocking, about an up-sell or cross-sell reference that names no row of the import and no existing variant, about an image that is not an HTTP or HTTPS URL, and about a variant row without stock whose product row has stock, because each variant keeps its own stock.

#### Scenario: Up-sell outside the file

- **WHEN** a row's up-sells hold "id:310" and no row of the import has the ID 310
- **THEN** the row has a warning that up-sell "id:310" was not found

#### Scenario: Stock managed on the product row

- **WHEN** a product row has stock 10 and its variant rows have empty stock cells
- **THEN** each variant row has a warning that the product's stock of 10 is not split among its variants

### Requirement: The preview shows what the import will do

The preview MUST list product groups with their action (new, or update of a named product), their product row, their variants, and each variant's SKU, options, price, discount, stock, status, and messages. It MUST show totals for products, new products, updated products, variants, warnings, and errors, and filters for all, new, update, with problems, and skipped.

#### Scenario: Totals

- **WHEN** an import stages 34 product groups, 21 new and 13 existing, with 118 variants, 9 warnings, and 6 errors
- **THEN** the preview shows 34 products, 21 new, 13 updated, 118 variants, 9 warnings, and 6 errors

### Requirement: Staff can correct a single row

Staff MUST be able to edit a row's normalized values in the preview. The form MUST show the original cell next to each field. The correction MUST be stored apart from the raw cells and MUST override the normalized value. Saving MUST validate that row's group again and nothing else.

#### Scenario: Add a missing price

- **WHEN** staff enter 49,90 as the price of "Produse" row 37, whose price cell was empty
- **THEN** the row's error disappears, its raw price stays empty, and the form shows "Original cell: empty"

### Requirement: A corrected discount is reviewed again

A correction that changes a row's discount MUST collect the import's discounts again: a new discount is matched and gets its default, a discount that no row uses any more is removed, and the other discounts keep their decisions.

#### Scenario: Change a discount

- **WHEN** staff change the discount percent of the only row with 50% to 40
- **THEN** the discount 50% disappears from the review, and 40% appears with that row, matched and with its default decision

### Requirement: Staff can skip rows

Staff MUST be able to skip a single row, skip all rows with errors at once, and include a skipped row again before the import starts (see "Staff can include skipped rows"). Skipped rows MUST NOT be imported.

#### Scenario: Skip all error rows

- **WHEN** staff skip all rows with errors in a preview with 6 error rows
- **THEN** those 6 rows are marked as skipped and the error total becomes 0

### Requirement: Staff can include skipped rows

Staff MUST be able to include a row that staff skipped, and a row that its type skips. For a row that its type skips, staff MUST choose whether it is a product with one variant, a product whose variants are other rows, or a variant. The row MUST then be validated like any other row, and the choice MUST be kept when the import is staged again. A row skipped because existing variants are left alone, or because its option value is skipped, MUST say in its message where that decision is changed.

#### Scenario: Include a row that its type skips

- **WHEN** the "grouped" row "Set vară" is skipped by its type, and staff include it as a product with one variant
- **THEN** the row is no longer skipped, the preview shows "Set vară" as a new product, and its missing SKU and price are errors that staff correct or skip

#### Scenario: A row skipped as an existing variant

- **WHEN** the import leaves existing variants alone and the row "TEE-CLASSIC-WHT-S" matches an existing variant
- **THEN** the row is skipped, offers no include action, and its message says that the "Existing variants" import option in the mapping step decides this

### Requirement: The import starts only with something to import

The start action MUST state how many variants will be imported. When error rows remain, it MUST say they will be skipped and require confirmation. The start MUST be refused when no row can be imported. Rows with only warnings MUST be imported.

#### Scenario: Start with remaining errors

- **WHEN** 118 variants are staged, 6 rows have errors, and staff start the import
- **THEN** the action reads "Skip rows with errors and import (112 variants)", asks for confirmation, and marks the 6 rows as skipped
