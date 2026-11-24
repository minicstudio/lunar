# Spec Delta

## Purpose

Lets staff review every category, brand, option name, option value, and product property that an import would use, see which already exist in the catalog, and decide once per value whether to use an existing record, create a new one, or skip it, before any product is imported.

## ADDED Requirements

### Requirement: Values are collected from all included sheets

After staging, the system MUST collect every distinct category path, brand, option name that comes from cells, option value (per option), and property name from all included sheets. For each value it MUST show the sheets it appears on and the number of products that use it. The review MUST show the count of products without a category and how many values exist, are similar, and are new.

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

### Requirement: Values with the same target become one record

Values that staff set to the same existing record MUST all use that record. Values set to create a record with the same normalized name, of the same type, and, for categories, under the same parent, or for option values, in the same option, MUST create one record. An option name from cells set to create an option MUST create one option, also when a column maps a new option with the same name.

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
