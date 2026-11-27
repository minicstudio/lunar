# Spec Delta

## Purpose

Lets staff review every category, brand, option name, option value, product property, and discount that an import would use, see which already exist in the catalog, and decide once per value whether to use an existing record, create a new one, or skip it, before any product is imported.

## ADDED Requirements

### Requirement: Values are collected from all included sheets

After staging, the system MUST collect every distinct category path, brand, option name that comes from cells, option value (per option), property name, and discount from all included sheets. For each value it MUST show the sheets it appears on and the number of products that use it. The review MUST show the count of products without a category and how many values exist, are similar, and are new.

#### Scenario: Same category on two sheets

- **WHEN** "Femei > Tricouri" appears on the sheets "Produse" and "Outlet" for 14 products in total
- **THEN** the categories tab lists it once with both sheet names and 14 products

#### Scenario: Products without a category

- **WHEN** one product on the sheet "Outlet" has an empty category cell
- **THEN** the review shows 1 product without a category, and that product is imported without a category

### Requirement: Values with the same normalized text are one value

Values MUST be compared after ignoring letter case, accents, repeated and surrounding spaces. Values that are equal after this MUST be collected as one value, shown with the first spelling found.

#### Scenario: Brand spellings

- **WHEN** the brand column holds "Nike" and "NIKE " in different rows
- **THEN** the brands tab lists one value "Nike" with the products of both rows

### Requirement: Each value is matched to the catalog

Each value MUST be compared with existing records of its type: brands by name, option values within their option, categories within the chosen collection group. An equal normalized name MUST be marked as existing and default to using that record. A similarity of at least the configured threshold (default 70%) MUST be marked as similar, with the closest record and its score, and no default. Otherwise the value MUST be marked as new and default to creating it.

#### Scenario: Existing brand

- **WHEN** the file holds "nike" and the catalog has the brand "Nike"
- **THEN** the value is marked as existing and set to use "Nike"

#### Scenario: Similar category

- **WHEN** the file holds "Femei > Hanorace" and the group has "Femei > Hanorac"
- **THEN** the value is marked as similar with "Femei > Hanorac", shows its score, and needs a decision

#### Scenario: New brand

- **WHEN** the file holds "Furnizor Textil" and no brand is similar
- **THEN** the value is marked as new and set to create "Furnizor Textil"

### Requirement: Option names from cells are matched to product options

An option name that comes from cells MUST be compared with the names of existing product options in every language. An equal name MUST default to that option, a similar one MUST need a decision, and a new one MUST default to creating an option with the file's spelling. Its option values MUST be matched within the option it resolves to, and again whenever that decision changes.

#### Scenario: Option in another language

- **WHEN** an export names the attribute "Mărime" and the option "Méret" is named "Mărime" in Romanian
- **THEN** the option name is marked as existing and set to use "Méret"

#### Scenario: Decision changes the values

- **WHEN** staff set the new option name "Culoare" to use the existing option "Szín"
- **THEN** the values of "Culoare" are matched again against the values of "Szín"

### Requirement: Properties go into attributes of the product type

A property name MUST be compared with the text and translated text attributes of its products' product type, in every language. An equal name MUST default to that attribute. Any other property MUST default to skip, because the import does not create attributes. Staff MUST be able to choose any such attribute or skip. Property decisions MUST NOT block the preview.

#### Scenario: Matching attribute

- **WHEN** a product row has the plain attribute "Material" with "Bumbac" and its product type has the translated text attribute "Material"
- **THEN** the property is set to use "Material" and the product gets "Bumbac" in the default language

#### Scenario: Property without an attribute

- **WHEN** a product has the property "Compoziție" and its product type has no such attribute
- **THEN** the property is set to skip, the review counts it as not imported, and staff can choose an attribute instead

### Requirement: Discounts are collected per value and dates

A row's discount amount or percent, with its start and end, MUST be collected as a discount. Rows with the same kind, value, start, and end MUST be one discount, shown with the products and variants that use it. A discount that ended before today MUST NOT be collected.

#### Scenario: New discounts

- **WHEN** today is 24 November 2026, the rows "OUT-RUHA-S" and "OUT-RUHA-M" give 30% and the row "OUT-TASKA" 50%, all with the end 31 December 2026, and the store has no such discount
- **THEN** the discounts tab lists 30% with 2 variants and 50% with 1 variant, both set to create

#### Scenario: Discount already over

- **WHEN** today is 24 November 2026 and a row's discount ended on 1 November 2026
- **THEN** the discount is not listed for review

### Requirement: Discounts are matched to existing discounts

Each discount MUST be compared with the store's discounts without a coupon that take an amount or percent off and are limited only to products or variants. One with the same percent, or the same amount in the default currency, and the same dates MUST be marked as existing and default to using it. Any other discount MUST be marked as new and default to creating it. A discount MUST NOT be marked as similar.

#### Scenario: Same amount and dates

- **WHEN** rows give 20,00 off from 1 December 2026 to 31 December 2026, and the store has the discount "Decembrie" of 20.00 in the default currency with the same dates, no coupon, and a limit to products
- **THEN** the discount is marked as existing and set to use "Decembrie"

#### Scenario: Discount limited by a collection

- **WHEN** the only discount of 30% with the same dates is limited to the collection "Toamnă"
- **THEN** the file's discount is marked as new, because adding variants to that discount would change which products it reaches

### Requirement: Open discount dates match by their meaning

An empty start in the file MUST match a discount that has already started, and an empty end only a discount without an end.

#### Scenario: No start in the file

- **WHEN** rows give 30% with the end 31 December 2026 and no start, and the store has the discount "Iarna" of 30%, which started on 20 November 2026, ends at the end of 31 December 2026, has no coupon, and is limited to products
- **THEN** the discount is marked as existing and set to use "Iarna"

### Requirement: Category paths are matched level by level

A category value MUST be split into a path on its column's path separator. Each level MUST be matched under the record matched for the level above, starting at the root of the chosen collection group. When only the first levels exist, the default MUST be to create the missing levels under the deepest existing one.

#### Scenario: Partly existing path

- **WHEN** the file holds "Femei > Rochii > Vară", and "Femei > Rochii" exists but "Vară" does not
- **THEN** the value is marked as partly existing and set to create "Vară" under "Femei > Rochii"

#### Scenario: New root category

- **WHEN** the file holds "Accesorii" and no root category of the group is similar
- **THEN** the value is set to create "Accesorii" at the root of the group

### Requirement: Staff decide what each value becomes

For each category, brand, option name, and option value, staff MUST be able to use any existing record of its type, create a new record with an editable name (and, for a category, an editable parent), or skip it. Skipping a category or brand MUST import its products without it. Skipping an option name MUST import its variants without that option. Skipping an option value MUST skip the variants that use it, and the review MUST show how many.

#### Scenario: Use an existing category

- **WHEN** staff set "Barbati > Geci" to use the existing "Barbati > Geacă"
- **THEN** the products with "Barbati > Geci" will be added to "Barbati > Geacă"

#### Scenario: Skip an option value

- **WHEN** staff skip the option value "XXS", which 2 variants use
- **THEN** the review says 2 variants will be skipped and the preview marks those rows as skipped

### Requirement: Staff decide what each discount becomes

For a discount, staff MUST be able to use any discount that could be matched, create a discount with an editable name, kind (amount or percent), value, start, and end, prefilled from the file, or skip it. Skipping a discount MUST import its variants without a discount. Discount decisions MUST NOT block the preview.

#### Scenario: Use another discount

- **WHEN** the discount 30% until 31 December 2026 is set to create and staff set it to use the existing discount "Black Week"
- **THEN** its variants will be added to "Black Week" and no discount will be created

#### Scenario: Create a discount with other values

- **WHEN** staff set the discount 30% until 31 December 2026 to create a discount named "Outlet iarnă" of 25% ending on 15 January 2027
- **THEN** the import will create that discount and link the variants of the 30% discount to it

#### Scenario: Skip a discount

- **WHEN** staff skip the discount 50% until 31 December 2026
- **THEN** the row "OUT-TASKA" stays in the import and its variant gets no discount

### Requirement: Values with the same target become one record

Values that staff set to the same existing record MUST all use that record. Values set to create a record with the same normalized name, of the same type, and, for categories, under the same parent, or for option values, in the same option, MUST create one record. An option name from cells set to create an option MUST create one option, also when a column maps a new option with the same name. Discounts set to create a discount with the same kind, value, start, and end MUST create one discount.

#### Scenario: Merge brand spellings

- **WHEN** staff set "Nike Inc" to use the existing brand "Nike", which "Nike" already uses
- **THEN** the products of both values get the brand "Nike" and no new brand is created

#### Scenario: Two new values with one name

- **WHEN** staff set two different values to create the brand "Furnizor Textil"
- **THEN** the import creates exactly one brand "Furnizor Textil"

### Requirement: Open decisions block the preview

Staff MUST NOT be able to continue from the value review to the preview while any value is similar without a decision. The page MUST show how many decisions are open.

#### Scenario: One open decision

- **WHEN** the value "Barbati > Geci" is similar and has no decision
- **THEN** the continue action is disabled and the page shows 1 open decision

### Requirement: A decision applies to every row with that value

A decision MUST apply to every staged row that uses that value, on all sheets. Changing a decision MUST validate the affected rows again.

#### Scenario: Decision changed

- **WHEN** staff change "Accesorii" from create to skip
- **THEN** every row with "Accesorii" is validated again and the preview shows those products without a category
