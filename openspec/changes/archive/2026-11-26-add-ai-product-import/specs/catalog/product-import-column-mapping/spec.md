# Spec Delta

## Purpose

Defines how staff decide what each column of each sheet means for the catalog: the fields a column can map to, ignoring columns, the number and date format and the separators of each column, SEO columns and URL slugs, options named in cells, grouping rows into products and variants, the weight and length units, import options and defaults, and how a mapping change is applied without calling the AI.

## ADDED Requirements

### Requirement: Columns map to a fixed set of target fields

A column MUST map to exactly one target: a product field (name, description, status, URL slug, brand, categories, tags, properties, images, up-sells, cross-sells), a variant field (SKU, EAN, GTIN, MPN, price, price impact, sale price, sale start, sale end, discount amount, discount percent, stock, stock status, backorders, minimum quantity, shippable, weight, weight impact, length, width, height, image), an option target, a structure target, a product attribute, the group key, or ignore.

#### Scenario: Identifier columns

- **WHEN** a sheet has the columns "SKU", "GTIN, UPC, EAN, or ISBN", and "MPN"
- **THEN** they can be mapped to variant SKU, variant GTIN, and variant MPN

#### Scenario: Structure columns

- **WHEN** a sheet has the columns "ID", "Type", and "Parent"
- **THEN** they can be mapped to the row's ID in the source shop, the row type, and the parent reference

#### Scenario: URL slug column

- **WHEN** a Romanian export has the column "URL key" holding "rochie-vara"
- **THEN** it can be mapped to the URL slug in Romanian

### Requirement: Attributes of the product type and store languages are offered

Only the text, translated text, number, and toggle attributes of the sheet's product type MUST be offered as targets. Name, description, and translated text attributes MUST take a store language.

#### Scenario: Attribute of the chosen product type

- **WHEN** the sheet's product type has a translated text attribute "Material" and a file attribute "Manual"
- **THEN** the target list offers "Material" with a language choice and does not offer "Manual"

#### Scenario: Name in two languages

- **WHEN** staff map "Denumire" to product name in Romanian and "Megnevezés" to product name in Hungarian
- **THEN** both mappings are accepted

### Requirement: SEO columns map to the product type's attributes

Meta title, meta description, and meta keywords columns MUST be mappable, in their language, to the product type's text and translated text attributes that hold them, like any other attribute. Every attribute target MUST be labelled with its attribute group, so that an attribute named like another target can be told apart from it. The import MUST NOT name any SEO attribute in its code. When the product type has no attribute for such a column, the column MUST be ignored.

#### Scenario: SEO attributes of the product type

- **WHEN** the product type has the attributes "Title", "Description", and "Keywords" in the group "Search Engine Optimization", and a Romanian export has the columns "Meta titlu", "Meta descriere", and "Cuvinte cheie"
- **THEN** the target list offers "Search Engine Optimization › Title", "Search Engine Optimization › Description", and "Search Engine Optimization › Keywords", and the three columns are mapped to them in Romanian

#### Scenario: Product type without SEO attributes

- **WHEN** the product type has no attribute for meta titles and a sheet has the column "Meta title"
- **THEN** "Meta title" is ignored

### Requirement: Staff can change, ignore, and restore every column

The mapping step MUST show, for each sheet in its own tab, every column with its header, samples, target, and AI confidence. Staff MUST be able to change any target, ignore a column, and restore an ignored column. Ignored columns MUST NOT be staged or imported.

#### Scenario: Ignore a notes column

- **WHEN** staff ignore the column "Observatii" and continue
- **THEN** no value of "Observatii" reaches the normalized rows or the catalog

#### Scenario: Manual mapping without a proposal

- **WHEN** staff map a column that the AI left unmapped to variant stock
- **THEN** the mapping stores variant stock for that column

### Requirement: Single-value targets are used once per sheet

Each product field, variant field, and structure target, and the group key, MUST be mapped to at most one column per sheet. Name, description, URL slug, and attributes MUST be mapped at most once per language. Each option, and the option name and the option value of each pair number, MUST be mapped at most once. Saving a mapping that breaks this MUST be rejected with a message naming the duplicated target.

#### Scenario: Price mapped twice

- **WHEN** staff map two columns of one sheet to variant price and save
- **THEN** the save is rejected with a message that variant price is mapped twice

### Requirement: Number and date columns have a format

A column mapped to a number target MUST have a number format: a decimal separator (dot or comma) and a thousands separator (none, dot, comma, space, or apostrophe) that differ. A column mapped to a date target MUST have a date format. Formats MUST be prefilled from the AI or an applied profile, editable before continuing, and continuing MUST be refused while a mapped column has none.

#### Scenario: Staff correct the AI

- **WHEN** the AI gave the column "Greutate" a decimal comma, its cells hold "1.250", and staff change its decimal separator to a dot and continue
- **THEN** the weights are staged as 1.25

#### Scenario: Format per column

- **WHEN** "Regular price" has a decimal comma and "Weight (kg)" has a decimal dot, and a row holds "1256,59" and "1.250"
- **THEN** the row's price is 1256.59 and its weight is 1.25

#### Scenario: Column mapped by hand without a format

- **WHEN** staff map a column that the AI described as text to variant stock and continue
- **THEN** continuing is refused with a message naming the sheet and the column that needs a number format

#### Scenario: Date format

- **WHEN** the column "Date sale price ends" is mapped to the sale end with the date format "Y-m-d G:i:s"
- **THEN** "2026-12-24 23:59:59" is read as 24 December 2026, 23:59:59

### Requirement: List columns have separators

A column mapped to a list target MUST have a list separator, which MAY be "none". A category column MUST also have a path separator, and a column of packed items an item separator. Separators MUST be prefilled from the AI or an applied profile and editable per column, and continuing MUST be refused while a mapped column lacks one. They MUST NOT come from configuration or a default in the code, or be shared by the whole import.

#### Scenario: Two sheets, two conventions

- **WHEN** the category column of "Produse" holds "Femei > Rochii, Femei > Noutăți" with the separators "," and ">", and the category column of "Outlet" holds "Femei/Rochii|Femei/Noutăți" with the separators "|" and "/"
- **THEN** the products of both sheets are in "Femei > Rochii" and "Femei > Noutăți"

#### Scenario: Column mapped by hand without a separator

- **WHEN** staff map a column that the AI ignored to tags and continue
- **THEN** continuing is refused with a message naming the sheet and the column that needs a list separator

#### Scenario: Staff correct a separator

- **WHEN** the AI gave the tags column the list separator ",", its cells hold "vara; bumbac", and staff change the separator to ";" and continue
- **THEN** the product gets the tags "vara" and "bumbac" and no analysis request is sent

### Requirement: Staff can correct a column's conventions

The mapping step MUST show, for each column that has them, the meanings of its codes, the parts of its list items, and its image size suffix, prefilled from the AI or an applied profile. Staff MUST be able to change, add, and remove them before continuing. They MUST be saved with the mapping.

#### Scenario: Code added by staff

- **WHEN** the samples of the status column did not show "-1", staff add "-1" with the meaning draft, and continue
- **THEN** rows whose status is "-1" are staged as draft and no analysis request is sent

#### Scenario: Size suffix removed by staff

- **WHEN** staff clear the size suffix of an image column and continue
- **THEN** the image URLs are staged as they are in the file

### Requirement: Option columns name their option

A column mapped to a variant option MUST name either an existing product option or a new option. A new option's name MUST default to the column header and be editable. Each distinct cell value of an option column MUST become an option value for review.

#### Scenario: New option from the header

- **WHEN** staff map the column "Marime" to a new option and keep the default name
- **THEN** the mapping holds a new option named "Marime"

#### Scenario: Existing option

- **WHEN** staff map the column "Culoare" to the existing option "Szín"
- **THEN** the cell values of "Culoare" are reviewed as values of the option "Szín"

### Requirement: Option names can come from cells

Options MAY come from a pair of columns with the same pair number, one holding option names and one their values, or from one column of `Name:Value` items. A pair cell MAY hold one item or a list split on its column's list separator, and list items MUST pair by position. Pairs MUST be matched by option name, not by pair number. A value without a name MUST take the name of the same pair from its product row.

#### Scenario: Pair on a variant row

- **WHEN** a variant row holds "Size" in "Attribute 1 name" and "M" in "Attribute 1 value(s)"
- **THEN** the variant has the option "Size" with the value "M"

#### Scenario: Pair cells with lists

- **WHEN** a variant row holds "Size, Color" as option names and "S, Red" as option values
- **THEN** the variant has Size S and Color Red

#### Scenario: Packed options

- **WHEN** the column mapped to options holds "Color:Red, Size:M"
- **THEN** the variant has Color Red and Size M

### Requirement: Option pairs on product rows can be properties

On a row that is only a variant, option pairs MUST be options. On a row that is only a product, pair names that are options of its group MUST be skipped and the others MUST be properties. On a row that is both, pairs MUST be options unless the row's type means a product with one variant, whose pairs are properties. Items of a properties column MUST always be properties.

#### Scenario: Plain attribute on a product row

- **WHEN** a product row holds "Size" with "S, M, L" and "Material" with "Bumbac", and its variant rows hold sizes
- **THEN** Size is an option of the product and Material is a property

### Requirement: Staff choose how rows group into products

Each sheet MUST have one grouping: by a group column, by parent reference, by product name, or one product per row. Rows of the same sheet with the same group value MUST form one product. With grouping by parent reference, a row MUST join the product of the row its parent reference names, on any included sheet. The other groupings MUST NOT group rows across sheets. A group column MUST be mapped to the group key target.

#### Scenario: Group by parent code

- **WHEN** staff group by the column "Cod parinte" and three rows hold "TB-001"
- **THEN** those three rows form one product with three variants

#### Scenario: One product per row

- **WHEN** staff choose one product per row
- **THEN** every row becomes a product with one variant

#### Scenario: Variant rows far from their product row

- **WHEN** an export lists the product row "id:57" on row 3 and its variant rows on rows 4 and 90
- **THEN** rows 3, 4, and 90 form one product

#### Scenario: Variants on another sheet

- **WHEN** the variants sheet's rows name product ID 12 of the products sheet as their parent
- **THEN** the product row 12 and those variant rows form one product

### Requirement: Parent references are resolved in a fixed order

A parent reference `id:N` MUST name the row whose source ID is N. Another reference MUST name the row with that source ID, else the row with that SKU, both on any included sheet, else the product of the existing variant with that SKU. A row whose parent reference names nothing MUST have an error naming the reference.

#### Scenario: Parent by SKU

- **WHEN** a variant row's parent reference is "TB-001" and a product row of the file has the SKU "TB-001"
- **THEN** the variant row joins that product

#### Scenario: New variant of an existing product

- **WHEN** a variant row's parent reference is "HO-114-M", no row of the file has that ID or SKU, and the existing variant "HO-114-M" belongs to product #1842
- **THEN** the variant row becomes a new variant of product #1842

### Requirement: Rows are products, variants, or both

A row type column MAY give each row its role (see the export conventions). Otherwise, with grouping by parent reference, a row with a parent reference MUST be a variant, a row that another row names as its parent MUST be a product, and any other row MUST be both. With the other groupings every row MUST be both. A product row MUST NOT become a variant, and its fields MUST serve the product.

#### Scenario: Product row with variant rows

- **WHEN** a product row without a price has two variant rows with prices
- **THEN** the product has two variants and the parent row creates no variant

### Requirement: Variant rows take missing values from their product row

A variant row MUST take its product row's weight, dimensions, stock status, backorders, minimum quantity, and shippability when its own cell is empty. A variant row without a discount amount or percent of its own MUST take its product row's discount with its start and end. Identifiers, prices, and stock MUST NOT be taken. A price or weight impact MUST give the base value plus the impact, where the base is the row's own value or, when empty, its product row's.

#### Scenario: Weight from the parent

- **WHEN** a variant row's weight cell is empty and its product row's weight is 0.5
- **THEN** the variant's weight is 0.5

#### Scenario: Price impact

- **WHEN** a product row's price is 100.00 and its variant row's price impact is 15.50
- **THEN** the variant's price is 115.50

#### Scenario: Discount from the products file

- **WHEN** the products file gives product 12 the discount percent 10 until 31 December 2026, and its two variant rows in the variants file have no discount
- **THEN** both variants carry the discount 10% until 31 December 2026

### Requirement: A price impact is without tax

A price impact MUST be read as an amount without tax. When the store keeps prices with tax, the impact MUST be multiplied by 1 plus the rate of the default tax class in the default tax zone before it is added to the base price. Only the sum MUST be rounded to minor units.

#### Scenario: Price impact on a price with tax

- **WHEN** the store keeps prices with tax, the default tax class has 21% in the default tax zone, a product row's price is 349.00, and its variant row's price impact is 8.264463
- **THEN** the variant's price is 359.00, not 357.26

#### Scenario: Price impact on a price without tax

- **WHEN** the store keeps prices without tax, a product row's price is 288.43, and its variant row's price impact is 8.264463
- **THEN** the variant's price is 296.69

### Requirement: Each sheet has a product type

Each included sheet MUST have a product type before staff can continue. The product type MUST decide which attributes the target list offers. Changing it MUST unmap attribute targets that the new type does not have.

#### Scenario: Product type changed

- **WHEN** a column is mapped to the attribute "Material" and staff change the product type to one without "Material"
- **THEN** the column becomes unmapped and the page says why

### Requirement: Import options apply to all sheets

The import MUST store these options: the match key (SKU by default, or EAN), what to do with existing variants (update by default, or skip), the status of new products (draft by default, published, or from the status column), the collection group for categories, additional collections (none by default), and whether the product images of variant rows are also linked to their variants (off by default). Separators MUST NOT be import options: they belong to columns. The currency MUST be the store's default currency.

#### Scenario: Defaults

- **WHEN** staff reach the mapping step without changing options
- **THEN** the import matches by SKU, updates existing variants, creates new products as drafts, adds no additional collections, and links no product image to a variant

### Requirement: New products take their status from the status option

The status option MUST set the status of every product the import creates: draft, published, or from the status column. With the status column, a new product MUST take the first status value among its rows, read as published or draft regardless of case. An unknown or missing value MUST make it a draft, and an unknown value MUST give a warning. This option MUST require a status column on an included sheet. Existing products MUST keep their status.

#### Scenario: Published column

- **WHEN** the option is from the status column and the column "Published" holds 1 for "Tricou basic" and -1 for "Rochie vară"
- **THEN** "Tricou basic" is created as published and "Rochie vară" as a draft

#### Scenario: Values in the store's languages

- **WHEN** the option is from the status column and the status cells of three new products hold "Igen", "DA", and "nu"
- **THEN** the first two products are created as published and the third as a draft

#### Scenario: All drafts despite a status column

- **WHEN** the option is draft and the file has a "Published" column holding 1
- **THEN** every new product is created as a draft

#### Scenario: Existing product

- **WHEN** the option is from the status column and a row with status 1 updates product #1842, which is a draft
- **THEN** product #1842 stays a draft

#### Scenario: Unknown status value

- **WHEN** the option is from the status column and the status cell of a new product holds "maybe"
- **THEN** the row has a warning and the product is created as a draft

#### Scenario: Status column missing

- **WHEN** staff choose the status column option and no included sheet maps a status column
- **THEN** continuing is refused with a message that no status column is mapped

### Requirement: A product can have several categories

A category cell MAY hold several categories, split on its column's list separator. A list separator "none" MUST read the cell as one category. A list separator after a backslash MUST belong to the category name. Each category MUST be a path split on its column's path separator, or one level when the path separator is "none", and MUST be reviewed as its own value. A product MUST get the categories of all its rows.

#### Scenario: Category list

- **WHEN** the category cell holds "Femei > Rochii, Femei > Noutăți"
- **THEN** the review lists "Femei > Rochii" and "Femei > Noutăți" as two values and the product is imported into both

#### Scenario: Variant rows without categories

- **WHEN** the group "TB-001" has three rows, the first holds "Femei > Tricouri", and the other two have empty category cells
- **THEN** the product is imported into "Femei > Tricouri" and is not counted as a product without a category

#### Scenario: Comma inside a name

- **WHEN** the category cell holds "Rochii\, fuste"
- **THEN** the review lists one category "Rochii, fuste"

### Requirement: Prices follow the store's tax setting

A mapped price MUST be read as an amount in the store's default currency. It MUST be stored as including tax or excluding tax according to the store's pricing configuration, without conversion. The price target's label MUST say which one applies.

#### Scenario: Prices stored with tax

- **WHEN** the store saves prices including tax and staff map the column "Pret"
- **THEN** the target reads "Variant price (RON, incl. tax)" and 49,90 is stored as 49.90 including tax

### Requirement: A weight column has a unit per sheet

When a sheet maps a weight column, the sheet MUST have a weight unit chosen from the store's weight units. The unit MUST be prefilled from the AI, which chooses it from those units, and staff MUST be able to change it. Continuing MUST be refused while a sheet maps a weight column without a unit. The unit MUST NOT come from configuration, and it MUST be saved with the mapping.

#### Scenario: Unit in the header

- **WHEN** the AI reads the header "Weight (g)" and returns the weight unit g
- **THEN** the sheet's weight unit is prefilled with g

#### Scenario: Unit unknown

- **WHEN** staff map "Weight" by hand to variant weight on a sheet for which the AI gave no weight unit
- **THEN** continuing is refused with a message asking for that sheet's weight unit until staff choose one

### Requirement: Dimension columns have a length unit per sheet

When a sheet maps a length, width, or height column, the sheet MUST have a length unit chosen from the store's length units. The unit MUST be prefilled from the AI, which chooses it from those units, staff MUST be able to change it, and continuing MUST be refused while it is missing. It MUST be saved with the mapping and MUST NOT come from configuration.

#### Scenario: Unit in a translated header

- **WHEN** the AI reads the header "Hosszúság (cm)" of a Hungarian export and returns the length unit cm
- **THEN** the sheet's length unit is prefilled with cm

#### Scenario: Dimensions without a unit

- **WHEN** a products file maps "Width", "Height", and "Depth" and the sheet has no length unit
- **THEN** continuing is refused with a message asking for that sheet's length unit until staff choose one

### Requirement: Continuing stages the rows without calling the AI

Continuing from the mapping step MUST validate the mapping, store it, and start staging in the background: normalizing every row, structuring rows into products and variants, collecting values for review, and validating. Staging MUST NOT call the AI. A later mapping change, including a changed format, separator, code meaning, item parts, image size suffix, or unit, MUST stage again from the stored raw rows without reading the file again.

#### Scenario: Mapping changed after staging

- **WHEN** staff go back from the preview, map the column "Greutate" to variant weight, and continue
- **THEN** the rows are staged again with weights, no analysis request is sent, and the file is not read again

#### Scenario: Format changed after staging

- **WHEN** staff go back from the preview, change the decimal separator of "Pret" from a dot to a comma, and continue
- **THEN** the prices are read again with a decimal comma and no analysis request is sent

#### Scenario: Corrections survive a mapping change

- **WHEN** staff corrected the price of row 37 and then change an unrelated column's target
- **THEN** row 37 keeps the corrected price after staging again

#### Scenario: Value decisions survive a mapping change

- **WHEN** staff decided that the category "Femei > Hanorace" uses the existing "Femei > Hanorac" and then change the stock column
- **THEN** that decision is kept after staging again
