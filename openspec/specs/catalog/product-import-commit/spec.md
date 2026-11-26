# product-import-commit Specification

## Purpose

Defines what happens when staff start an approved import: the order in which categories, brands, options, discounts, products, variants, prices, URLs, associations, and images are written to the catalog, how existing records are updated without duplicates, how failures are isolated and reported, and how failed rows and failed images are retried.

## Requirements

### Requirement: The import runs in the background

Starting the import MUST set the import to importing and process it on the queue. Staff MUST be able to leave the page. The import page and the imports list MUST show the share of product groups processed, and then the share of images processed.

#### Scenario: Staff leave the page

- **WHEN** staff start an import with 34 product groups and close the page
- **THEN** the import continues, and the imports list later shows its final status

### Requirement: Only one import writes to the catalog at a time

The system MUST refuse to start an import while another import is importing, with the message "Another import is running. Try again when it has finished." The refused import MUST stay in preview ready.

#### Scenario: Second start

- **WHEN** staff start import B while import A is importing
- **THEN** import B is refused with that message and stays in preview ready

### Requirement: Categories, brands, options, and discounts are written first

Before writing any product, the import MUST create the brands, categories (in the chosen collection group, under their decided parents), product options (from the mapping and from cells), option values, and discounts that staff decided to create. Every product MUST then use the records chosen or created in the review. If this step fails, no product MUST be written and the import MUST become failed.

#### Scenario: New category and brand

- **WHEN** the review decided to create the category "Accesorii" and the brand "Furnizor Textil", and 3 products use them
- **THEN** the import creates one collection "Accesorii" and one brand "Furnizor Textil", and the 3 products are in that collection and have that brand

#### Scenario: Partly existing path

- **WHEN** the review decided to create "Vară" under the existing "Femei > Rochii"
- **THEN** the import creates "Vară" as a child of "Femei > Rochii"

### Requirement: Created discounts apply to every customer

A created discount MUST take an amount or percent off, have no coupon, and take its name, kind, value, start, and end from the decision. An empty start MUST mean the moment the discount is created. An amount MUST be set for the default currency. The discount MUST be enabled in every channel and for every customer group.

#### Scenario: New discount

- **WHEN** the review decided to create the discount 30% with the end 31 December 2026 and no start
- **THEN** the import creates one discount of 30% without a coupon that starts when it is created, ends at the end of 31 December 2026, and is enabled in every channel and for every customer group

### Requirement: Each product group is written in its own transaction

Each product with its variants and prices MUST be written in one transaction. When a group fails, its changes MUST be rolled back, its rows MUST be marked as failed with the reason, and the other groups MUST continue.

#### Scenario: One group fails

- **WHEN** writing one of 34 groups fails with a database error
- **THEN** that group's product and variants do not exist or are unchanged, its rows are failed with the reason, and the other 33 groups are imported

### Requirement: New products are created from the mapped data

A new product MUST get the sheet's product type, the status that the status option gives it, its mapped name, description, attributes, and decided properties in their languages, its brand, categories, and tags. Its options MUST be attached. Each variant MUST get its SKU, EAN, GTIN, MPN, stock, purchasability, minimum quantity, shippability, weight and dimensions in the sheet's units, option values, the default tax class, and one base price for quantity 1 in the default currency.

#### Scenario: Product with three variants

- **WHEN** the group "Tricou basic" has three valid rows with sizes S, M, and L
- **THEN** one draft product "Tricou basic" exists with the options size and color and three variants with their SKUs, prices, and stock

#### Scenario: Product with variant rows

- **WHEN** the product row "Rochie vară" has the tags "vara, bumbac" and the brand "Furnizor Textil", and its two variant rows have the sizes S and M, prices, GTINs, and the weight 0.4 with the sheet's unit kg
- **THEN** one product with those tags and that brand exists with two variants, each with its size, price, GTIN, and a weight of 0.4 kg

### Requirement: Records are created as the store's model classes

When the store replaces a Lunar model with its own class, every record of that model that the import creates MUST be an instance of the store's class, so model events and their listeners receive the class they expect.

#### Scenario: The store replaces the product model

- **WHEN** the store replaces the product and collection models with its own classes, and an import creates the product "Classic Cotton T-Shirt" in the new category "T-Shirts"
- **THEN** the created events carry instances of the store's classes, and the product and the category are imported

### Requirement: Existing records change only in mapped fields

An update MUST overwrite only fields that the sheet maps and whose cell is not empty. Unmapped fields MUST stay unchanged. Categories, tags, discounts, associations, and images MUST be added without removing existing ones. URLs MUST NOT be overwritten (see URL slugs). A product in the trash MUST be restored. New variants of an updated group MUST be added to the existing product.

#### Scenario: Stock-only file

- **WHEN** a sheet maps only SKU and stock, no additional collection is chosen, and a row matches an existing variant
- **THEN** only that variant's stock changes, and its price, name, and categories stay as they were

#### Scenario: Existing category kept

- **WHEN** an existing product is in "Noutăți" and the row's category is "Femei > Tricouri"
- **THEN** the product is in both collections after the import

### Requirement: URL slugs keep a product's address

When a sheet maps a URL slug, a new product MUST get the slug as its default URL in that language, instead of the URL generated from its name. Without a slug column, a new product MUST get the generated URL, as in the admin. An existing product MUST keep its URLs: the slug MUST be added only in a language where the product has no URL. A slug that another product uses in that language at write time MUST get a number suffix.

#### Scenario: New product with a slug

- **WHEN** the import creates "Rochie vară" with the Romanian slug "rochie-vara"
- **THEN** its default Romanian URL is "rochie-vara", and it has no URL generated from its name in Romanian

#### Scenario: No slug column

- **WHEN** the sheet maps no slug and the import creates "Rochie vară"
- **THEN** its URL is generated from its name, as for a product created in the admin

#### Scenario: Existing product

- **WHEN** a row with the slug "rochie-vara" updates product #1842, whose Romanian URL is "rochie-de-vara"
- **THEN** product #1842 keeps "rochie-de-vara" as its only Romanian URL

#### Scenario: Slug taken

- **WHEN** another product already has the Romanian URL "rochie-vara" and the import creates a product with that slug
- **THEN** the new product's Romanian URL is "rochie-vara-2"

### Requirement: The price is written without a compare price

A row that writes a price MUST write the row's price as the variant's base price. It MUST NOT write a sale price, or a price minus a discount, as the base price. The import MUST NOT write or clear a compare price.

#### Scenario: Sale price in the file

- **WHEN** a row has the price 199,00 and the sale price 149,00 without dates, and its variant is new
- **THEN** the variant's price is 199.00 and it has no compare price

#### Scenario: Discount percent

- **WHEN** a product row has the price 200.00 and the discount percent 25, without dates
- **THEN** the variant's price is 200.00, it has no compare price, and it is linked to a 25% discount

#### Scenario: Existing compare price

- **WHEN** a row with the price 199,00 updates a variant whose compare price is 249.00
- **THEN** the variant's price is 199.00 and its compare price stays 249.00

### Requirement: Variants are linked to their discounts

After a variant is written, it MUST be added to the discount that the review decided for its row's discount, as a variant the discount is limited to, unless it is already added. Other discounts of the variant MUST stay. A variant whose discount was skipped MUST NOT be added to any discount. Using an existing discount MUST NOT change its name, values, dates, channels, or customer groups.

#### Scenario: Discounts from a supplier sheet

- **WHEN** today is 24 November 2026, the rows "OUT-RUHA-S" and "OUT-RUHA-M" have the price 229.00 and the discount percent 30, the row "OUT-TASKA" has the price 59,00 and the discount percent 50, all with the sale end 31 December 2026, and the review decided to create both discounts
- **THEN** the variants' prices are 229.00, 229.00, and 59.00, none has a compare price, the 30% discount is limited to "OUT-RUHA-S" and "OUT-RUHA-M", the 50% discount to "OUT-TASKA", and both end at the end of 31 December 2026

#### Scenario: Existing discount used

- **WHEN** the review set a discount to use the existing discount "Iarna", which is limited to 4 variants, and 2 imported variants carry that discount
- **THEN** "Iarna" is limited to 6 variants and keeps its name, value, dates, channels, and customer groups

#### Scenario: Same file twice

- **WHEN** staff import a file with discounts and then import the same file again
- **THEN** the second import uses the discounts that the first one created, creates none, and adds no variant to a discount twice

### Requirement: Stock status and backorders set purchasability

When stock status or backorders are mapped and filled, allowed backorders or a stock status on backorder MUST make the variant purchasable in stock or on backorder. Otherwise a filled stock MUST make it purchasable only in stock, in stock without a stock number MUST make it always purchasable, and out of stock without a stock number MUST make it purchasable only in stock with stock 0.

#### Scenario: Backorders allowed by a code

- **WHEN** a variant row has stock 0 and its backorders cell holds "notify", which the column's meanings read as yes
- **THEN** the variant is purchasable in stock or on backorder

#### Scenario: Stock not managed

- **WHEN** a product with one variant has an empty stock cell and its stock status cell holds 1
- **THEN** the variant is always purchasable

### Requirement: Up-sells and cross-sells are added after the products

After every product group is written, the import MUST add each product's up-sells and cross-sells as associations of that type. A reference MUST be resolved like a parent reference. Associations MUST be added without removing existing ones and never to the product itself. An unresolved reference MUST be a warning on its row.

#### Scenario: Up-sells by ID and SKU

- **WHEN** "Rochie vară" has the up-sells "id:57, TB-001-S", row 57 of the file is "Geacă puf", and "TB-001-S" is a variant of "Tricou basic"
- **THEN** "Rochie vară" has the up-sells "Geacă puf" and "Tricou basic"

#### Scenario: Cross-sells by source ID

- **WHEN** a product row lists the cross-sells "12, 14" and the file has products with the source IDs 12 and 14
- **THEN** the product gets those two products as cross-sells

### Requirement: Images are downloaded after the products

After the associations, the import MUST download each product's image URLs in file order, the first as the primary image of a new product, and link a variant's own image to that variant. Every image MUST keep its own file, named after the product like an image uploaded in the admin, with a number suffix when another image of the product has that name. When the import links images to variants, the product images of a variant row MUST be linked to its variant, and added to the product when the product does not have them yet. The images of the product row, and of a product with one variant, MUST stay on the product only. An image whose URL the product already has MUST NOT be downloaded again. A failed image MUST be a warning on its row and MUST NOT fail the row or the import.

#### Scenario: Images in file order

- **WHEN** a product row's images hold three URLs
- **THEN** the product has the three images in that order, with the first as its primary image

#### Scenario: URLs that end in the same file name

- **WHEN** the images of "Classic Cotton T-Shirt" are "https://picsum.photos/id/1/800/1000.jpg" and "https://picsum.photos/id/2/800/1000.jpg"
- **THEN** the product has two image files, "classic-cotton-t-shirt.jpg" and "classic-cotton-t-shirt-2.jpg", each with its own picture

#### Scenario: Images linked to variants

- **WHEN** the import links images to variants, the product row "TEE-CLASSIC" has a white and a black image, and its variant rows "TEE-CLASSIC-WHT-S" and "TEE-CLASSIC-BLK-M" repeat the white and the black image
- **THEN** the product has each image once, the white image is linked to "TEE-CLASSIC-WHT-S", and the black image to "TEE-CLASSIC-BLK-M"

#### Scenario: A variant image the product row lacks

- **WHEN** the import links images to variants and the variant row "CHINO-SLIM-30" has an image that its product row does not have
- **THEN** the image is added to the product and linked to "CHINO-SLIM-30"

#### Scenario: Images not linked to variants

- **WHEN** the import does not link images to variants
- **THEN** the product images of the variant rows are added to the product and linked to no variant

#### Scenario: Same file twice

- **WHEN** the same file is imported again
- **THEN** no image is downloaded again and the products keep one copy of each image

#### Scenario: Missing image

- **WHEN** one image URL answers 404
- **THEN** the row is imported with a warning naming that URL, and the result counts one failed image

### Requirement: Images come only from public web addresses

The import MUST fetch images only over HTTP or HTTPS from hosts that resolve to public addresses, MUST check every redirect the same way, and MUST refuse images over the configured size, slower than the configured time, or of a type other than JPEG, PNG, GIF, or WebP.

#### Scenario: Internal address

- **WHEN** an image URL is "http://127.0.0.1/admin.png" or its host resolves to a private network address
- **THEN** nothing is fetched and the image fails with a message that the address is not allowed

#### Scenario: Redirect to an internal address

- **WHEN** a public image URL redirects to "http://169.254.169.254/latest"
- **THEN** the redirect is not followed and the image fails

### Requirement: Additional collections are added to every imported product

Every product that the import creates or updates MUST also be added to the import's additional collections, without removing it from other collections. A chosen collection that was deleted before the import is written MUST be ignored.

#### Scenario: All products in one collection

- **WHEN** staff choose the additional collection "Toate categoriile" and the import creates 21 products and updates 13
- **THEN** all 34 products are in "Toate categoriile" and in their own categories

#### Scenario: Collection deleted meanwhile

- **WHEN** the chosen additional collection is deleted after the preview and staff start the import
- **THEN** the products are imported without it and the import does not fail

### Requirement: Imports do not create duplicates

At write time, the import MUST look up each variant by the match key again, not only use the result of the preview. Importing the same file twice, retrying, or importing a SKU that another import created in the meantime MUST update the existing variant instead of creating another one.

#### Scenario: Same file twice

- **WHEN** staff import a file and then import the same file again with update enabled
- **THEN** the second import updates the same products and variants and the number of variants does not change

#### Scenario: Created by another import meanwhile

- **WHEN** a SKU was new in the preview, but another import created it before this import ran
- **THEN** this import updates that variant instead of creating a second one

### Requirement: Existing variants can be left alone

When the import is set to skip existing variants, matched rows MUST NOT change any record, and they MUST be counted as skipped.

#### Scenario: Create-only import

- **WHEN** the import skips existing variants and 13 groups match existing products
- **THEN** none of those products or variants change and 13 groups are counted as skipped

### Requirement: An import never removes catalog records

The import MUST NOT delete, unpublish, or move to the trash any product, variant, price, brand, collection, option, option value, or discount, including records that the file does not contain, and MUST NOT remove a variant from a discount.

#### Scenario: Product missing from the file

- **WHEN** a product exists in the catalog and is not in the imported file
- **THEN** the product is unchanged after the import

### Requirement: Search is updated once per import

The import MUST NOT update the search index once per row. After the products are written, the search index MUST be updated once for every product the import created or updated. Other product events MUST still fire.

#### Scenario: Products searchable after import

- **WHEN** an import creates 21 products
- **THEN** after it finishes, all 21 products are in the admin search index

### Requirement: The result is recorded on the import

When all groups and images are processed, the import MUST become completed when every included row was imported or skipped by staff, completed with errors when some rows failed, and failed when no row was imported. Failed images MUST NOT change the status. The import MUST store counts of new and updated products and variants, skipped and failed rows, the records it created of each type, the variants added to discounts, the associations added, and the images downloaded and failed.

#### Scenario: Some rows failed

- **WHEN** 112 variants are imported and 7 rows fail
- **THEN** the import is completed with errors and shows 112 imported variants and 7 failed rows

### Requirement: Failed rows can be downloaded

While an import's rows are kept, staff MUST be able to download a CSV of its failed rows, rows skipped because of errors, and rows with failed images. Each line MUST hold the sheet name, the row number, the original cells under their headers, and the messages.

#### Scenario: Error report

- **WHEN** staff download the error report of an import with 7 failed rows
- **THEN** the CSV has 7 data lines with sheet, row number, original cells, and messages

### Requirement: Failed rows can be retried

While an import's rows are kept, staff MUST be able to correct failed rows and retry them. A retry MUST process only the groups that contain failed rows, including their associations and images, follow the same rules as the first run, and update the import's result and counts.

#### Scenario: Retry after correction

- **WHEN** staff correct the price of 2 failed rows and retry
- **THEN** only their groups are processed, the 2 rows become imported, and the import's failed count drops by 2

### Requirement: Failed images are listed and can be corrected

While an import's rows are kept, the import page MUST list the images that failed with their product, URL, and reason. Staff MUST be able to correct the URL of a failed image to another HTTP or HTTPS URL. The row's raw cell MUST keep the original URL.

#### Scenario: URL that is not a web address

- **WHEN** staff correct a failed image's URL to "ftp://cdn.example/1.jpg"
- **THEN** the correction is refused and the image keeps its URL

### Requirement: Failed images can be retried

Staff MUST be able to retry the failed images of an import that is completed or completed with errors. A retry MUST download only the failed images by the rules of the first run, write no product, variant, or row, and update the image counts without changing the import's status. It MUST be refused once the import is pruned, when no image failed, and while another import is importing.

#### Scenario: Corrected URL

- **WHEN** an image of "Rochie vară" failed with 404, staff correct its URL, and retry the failed images
- **THEN** "Rochie vară" gets the image, the failed image count drops by one, and the import keeps its status

#### Scenario: Completed import with a failed image

- **WHEN** every row of an import was imported and one image failed because its host did not answer
- **THEN** the import is completed, and staff can retry that image without correcting it

#### Scenario: Another import running

- **WHEN** staff retry the failed images of import A while import B is importing
- **THEN** the retry is refused with "Another import is running. Try again when it has finished."
