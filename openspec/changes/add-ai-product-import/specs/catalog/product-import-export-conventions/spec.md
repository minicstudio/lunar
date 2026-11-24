# Spec Delta

## Purpose

Defines the conventions of shop exports that the product import reads: row types, parent references across rows and files, option pairs and packed list items, coded values, cell escapes, image size suffixes, prices with and without tax, translated headers, SEO columns and URL slugs, and the columns that have no catalog counterpart. The import knows no platform: any export that uses these conventions is read the same way, whichever platform wrote it.

## ADDED Requirements

### Requirement: No platform is configured or chosen

The import MUST NOT keep a list of platforms, offer a platform choice, or ask the AI which platform exported a sheet. A sheet's conventions MUST be described per column, in the column's format: the meaning of its codes, the separators and parts of its list items, and the size suffix of its image URLs. The AI MUST propose them, staff MUST be able to change them, and changing them MUST NOT call the AI.

#### Scenario: Export of any platform

- **WHEN** a sheet uses a row type column, parent references, and status codes, and no configuration names the platform that wrote it
- **THEN** the sheet is read with the conventions that its mapping describes for its columns

#### Scenario: Staff change a convention

- **WHEN** staff change the meaning of the status code "2" from published to draft and continue
- **THEN** the rows are staged with the new meaning and no analysis request is sent

### Requirement: Cells are cleaned the same way on every sheet

On every sheet, an apostrophe before "=", "+", "-", "@", a tab, or a carriage return at the start of a cell MUST be removed, because exporters add it so that spreadsheets do not run the cell as a formula. In descriptions and text attributes, a literal "\n" MUST become a line break. In names of brands, categories, tags, options, option values, and properties, HTML entities MUST be decoded, also when they are escaped twice.

#### Scenario: Escaped value

- **WHEN** an option value cell holds "'-20%"
- **THEN** the option value is "-20%"

#### Scenario: Escaped negative stock

- **WHEN** a stock cell holds "'-3"
- **THEN** the stock is read as -3 and the row has an error that stock cannot be negative

#### Scenario: Description with line breaks

- **WHEN** a description cell holds "Rochie lejeră.\nBumbac 100%."
- **THEN** the description has two lines

#### Scenario: Brand with an ampersand

- **WHEN** a brand cell holds "Dolce &amp;amp; Gabbana"
- **THEN** the brand is reviewed as "Dolce & Gabbana"

### Requirement: Coded values are read with the column's meanings

A column mapped to the status, stock status, backorders, or shippable target MAY give its codes a meaning: published or draft; in stock, out of stock, or on backorder; yes or no; or empty, for a code that leaves the field unset. A code's meaning MUST be used before the built-in words. A code that neither names MUST be an unknown value of that field.

#### Scenario: Pending review

- **WHEN** the status option is from the status column, the "Published" column gives "2" the meaning draft, and a new product's cell holds "2"
- **THEN** the product is created as a draft without a warning

#### Scenario: Code without a meaning

- **WHEN** the status option is from the status column, the status column gives "2" no meaning, and a new product's cell holds "2"
- **THEN** the status is unknown, the row has a warning, and the product is created as a draft

#### Scenario: On backorder

- **WHEN** the stock status column gives "backorder" the meaning on backorder and a variant row's cell holds "backorder"
- **THEN** the variant is purchasable in stock or on backorder

#### Scenario: Inverted flag

- **WHEN** the column "Virtual product" is mapped to shippable with "1" meaning no and "0" meaning yes, and a row holds "1"
- **THEN** the variant is not shippable

#### Scenario: Code that leaves the field unset

- **WHEN** the backorders column gives "2" the meaning empty, a row holds "2", and the row updates an existing variant
- **THEN** the backorders cell counts as empty and the variant keeps its purchasability

### Requirement: A row type column decides the rows' roles

A column mapped to the row type holds one item or a list of items per row. Each item MAY mean a product with one variant, a product whose variants are other rows, a variant, a row that is not imported, or not shippable. The first item with a role MUST decide the row's role. A row that is not imported MUST be skipped with a message naming its type. A row without a role item MUST follow the general role rule.

#### Scenario: Product with variant rows

- **WHEN** "variable" means a product with variant rows and "variation" a variant, and the sheet holds the "variable" row "Rochie vară" with ID 57 and two "variation" rows whose parent is "id:57"
- **THEN** the preview shows one product "Rochie vară" with two variants

#### Scenario: Type that is not imported

- **WHEN** "grouped" means not imported, and the sheet holds the "grouped" row "Set vară" and its members as rows whose type means a product with one variant
- **THEN** the "Set vară" row is skipped with a message that rows of the type "grouped" are not imported, and the members are imported as products

#### Scenario: Not shippable

- **WHEN** "simple" means a product with one variant and "virtual" means not shippable, and a row's type is "simple, virtual"
- **THEN** the row is a product with one variant, and the variant is not shippable

#### Scenario: Type without a meaning

- **WHEN** the sheet groups by parent reference and a row's type is "bundle", which has no meaning
- **THEN** the row's role follows the general rule

### Requirement: A single product's pairs are properties

On a row whose type means a product with one variant, option pairs and `Name:Value` items MUST be properties of the product, because a single variant has no options to choose between.

#### Scenario: Single product with attributes

- **WHEN** a row whose type means a product with one variant holds the attribute "Color" with the value "Roșu"
- **THEN** "Color" is reviewed as a property of the product, not as an option

### Requirement: Variant rows may number their pairs differently

Option name and value columns MUST be read as pairs matched by option name, because a variant row may number its pairs differently from its product row. Columns that only say how an attribute is displayed, such as visible, global, or default flags, MUST be mapped to ignore.

#### Scenario: Different numbering

- **WHEN** the product row holds "Material" as pair 1 and "Size" as pair 2, and its variant rows hold "Size" as pair 1
- **THEN** the variants have the option "Size" and the product has the property "Material"

### Requirement: Packed list items keep only their name and value

A column of packed list items MUST have an item separator, usually ":", and MAY describe the parts of each item split on it: which part is the name, which is the value, and which are dropped, such as types, positions, and flags. An item MUST be split into at most as many parts as described, the last part keeping any further separator. Without a description, a `Name:Value` item MUST be split on its first item separator.

#### Scenario: Names and values with positions

- **WHEN** the option name column describes its parts as name, dropped, dropped and holds "Size:select:0, Color:color:1", and the option value column describes its parts as value, dropped and holds "S:0, Alb:1"
- **THEN** the variant has Size S and Color Alb

#### Scenario: Property with a position

- **WHEN** the properties column describes its parts as name, value, dropped and holds "Compoziție:Bumbac 100%:1"
- **THEN** "Compoziție" is reviewed as a property with the value "Bumbac 100%"

#### Scenario: Value with a colon

- **WHEN** a properties column with the item separator ":" and without a description holds "Format:16:9"
- **THEN** "Format" is reviewed as a property with the value "16:9"

#### Scenario: Another item separator

- **WHEN** an options column has the list separator ";" and the item separator "=", and holds "Size=M; Color=Red"
- **THEN** the variant has Size M and Color Red

### Requirement: Variants can come from a separate file

A file of products and a file of their variants MUST be importable together as two files of one import. A variant row MUST join the product that its parent reference names by source ID or SKU. Its price and weight impacts MUST be added to the product's price and weight.

#### Scenario: Shirt with variants in a second file

- **WHEN** the products file holds product ID 12 "Cămașă" with the price 100.00 and the weight 0.3, and the variants file holds two rows for product 12 with Size S / Color Alb and Size M / Color Alb, and the price impacts 0 and 10.00
- **THEN** "Cămașă" has two variants, S / Alb at 100.00 and M / Alb at 110.00, each with the weight 0.3

#### Scenario: Product without variant rows

- **WHEN** the products file holds product ID 14 with the SKU "CUR-14" and no variant row names it
- **THEN** product 14 has one variant with the SKU "CUR-14"

#### Scenario: Variant of a missing product

- **WHEN** a variant row names product ID 99 and neither file has product 99
- **THEN** the row has an error that its parent "99" was not found

### Requirement: One row per variant is grouped by product

A sheet with one row per variant that repeats the product's columns on every row MUST be grouped by its product ID column. Packed items such as "Color:Red, Size:M" MUST be options, and the base price plus the price impact MUST be the variant price.

#### Scenario: Variant rows

- **WHEN** three rows hold product ID 7 with the base price 50.000000, the options "Size:S", "Size:M", and "Size:L", and the price impacts 0.000000, 0.000000, and 5.000000
- **THEN** product 7 has three variants at 50.00, 50.00, and 55.00

### Requirement: The price column matches the store's tax setting

When a sheet has a price with tax and a price without tax, the one that matches the store's pricing configuration MUST be mapped to the price, and the other MUST be ignored.

#### Scenario: Store with tax-inclusive prices

- **WHEN** the store saves prices including tax and the sheet holds "1256.590000" as "Price (tax excl.)" and "1.503,85 lei" as "Price (tax incl.)"
- **THEN** the variant's price is 1503.85

### Requirement: Image URLs lose their size suffix

An image column MAY name a size suffix, made of letters, digits, "-", and "_", that its URLs carry to point at a resized copy. The suffix MUST be removed where it stands before the file extension or before the next "/", so that the original image is downloaded.

#### Scenario: Thumbnail file name

- **WHEN** the image column's size suffix is "-small_default" and a cell holds ".../img/p/1/2/12-small_default.jpg"
- **THEN** the import downloads ".../img/p/1/2/12.jpg"

#### Scenario: Thumbnail folder

- **WHEN** the image column's size suffix is "-small_default" and a cell holds ".../12-small_default/rochie.jpg"
- **THEN** the import downloads ".../12/rochie.jpg"

### Requirement: Headers are read in any language and order

An export MUST be read the same way whatever language its headers are in, whatever order its columns are in, and when early rows hold fewer cells than the header. When two columns share a header, each MUST be mapped by its position and samples.

#### Scenario: Romanian export

- **WHEN** an export has the headers "Nume", "Preț obișnuit", "Preț promoțional", "Greutate (kg)", and "Părinte"
- **THEN** they are mapped to the name, the price, the sale price, the weight with the unit kg, and the parent reference

#### Scenario: Repeated header

- **WHEN** a Hungarian export has two columns with the header "Tulajdonság neve 1", the first holding "Méret" and the second holding "M"
- **THEN** the first is mapped to the option name of pair 1 and the second is ignored

### Requirement: SEO fields and URL slugs are read

An export's meta title, meta description, and meta keywords columns MUST be mapped to the product type's attributes that hold them, and its URL slug column to the URL slug, each in its language. A column that holds full product URLs instead of slugs MUST be ignored.

#### Scenario: Export with SEO columns

- **WHEN** the product type has SEO attributes for the title, description, and keywords, and an export has the columns "Meta title", "Meta description", "Meta keywords", and "URL key"
- **THEN** the first three are mapped to those attributes and "URL key" to the URL slug

### Requirement: Columns without a catalog counterpart are not imported

Columns for which the catalog has no field MUST be mapped to ignore, such as tax and shipping classes, visibility, featured and sold-individually flags, reviews, purchase notes, downloads, positions, stock thresholds, external URLs, customs data, cost and wholesale prices, supplier data, delivery times, condition, customization, shops, warehouses, and default-variant flags. Custom field columns MUST be ignored unless staff map them to attributes.

#### Scenario: Ignored columns

- **WHEN** the AI analyzes an export with the columns "Tax class", "Shipping class", "Purchase note", "Wholesale price", and "Low stock amount"
- **THEN** they are ignored and no value of them reaches the catalog
