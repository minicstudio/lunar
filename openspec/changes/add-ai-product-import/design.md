# Design

## Context

See `proposal.md` for the motivation and `specs/catalog/product-import*/spec.md` for the required behavior. The approach follows the agreed technical plan for LFP-954 (third version, with the meeting decisions included). This section lists only the codebase facts that shape it.

- **Package conventions.** `packages/analytics` is the newest package and the template to follow. Its plugin is registered by the host app when a config flag is on (`lunar-frontend` `LunarFrontendServiceProvider::getLunarPanelPlugins()`). It creates its permission in a migration-state listener (`EnsureAnalyticsPermissions`) and merges permission labels into `lunarpanel::auth` after boot. It ships en, hu, and ro translations. Its tests live in `tests/analytics` with their own `TestCase` and a panel test provider that registers the plugin. Every package is wired in the root `composer.json` (autoload, autoload-dev, `extra.laravel.providers`, `extra.lunar.name`, `replace`) and has its own suite in `phpunit.xml`. Packages schedule their own commands in `booted()` (see `LoyaltyServiceProvider::registerSchedule()`).
- **Admin extension points.** `ListProducts` extends `BaseListRecords`, so a `ListPageExtension::headerActions()` registered with `LunarPanel::extensions()` can add the **Import** button. Resources gate on `protected static ?string $permission`. Lunar's gate lets admins pass every permission. The panel has no database notifications.
- **Catalog write conventions already in the code.** `ListProducts::createRecord()` resolves the name attribute's field type, uses `TaxClass::getDefault()` and `Currency::getDefault()`, and stores prices as `(int) bcmul($amount, $currency->factor)`. `CreatesChildCollections` creates a child collection with `appendNode()` in the parent's group. The ERP `CreateProductsAndVariantsJob` looks variants up by SKU, restores soft-deleted products, and attaches `ProductOption`s with a pivot `position`. It deletes and recreates prices, and it deletes its staging rows right after processing.
- **Catalog fields.** Variants have `sku`, `ean`, `gtin`, `mpn`, `stock`, `backorder`, `purchasable` (`always`, `in_stock`, `in_stock_or_on_backorder`, `preorder`), `shippable`, `min_quantity`, and a value and unit for `weight`, `length`, `width`, `height` (units are the keys of `lunar.shipping.measurements.weight` and `.length`). Prices have `compare_price`. Products have no SKU, tags through `HasTags`, associations (`cross-sell`, `up-sell`, `alternate`), and media through spatie/laravel-medialibrary. Lunar has no scheduled prices and no date attribute type, and `published_at` is set by `ProductObserver`.
- **Side effects of catalog writes.** `Product` and `ProductVariant` dispatch created, updated, and deleted events. `Product`, `ProductVariant`, `Brand`, and `Collection` are Scout-searchable. Klaviyo listens to the variant events, but its `SyncProductToKlaviyo` job is `ShouldBeUnique` per product and `ShouldQueueAfterCommit`. Mailchimp and the ERP do not observe products or variants.
- **AI.** `laravel/ai` v1.4.0 is installed only through `minic/laravel-ai-assistant`. An agent picks its provider and model from optional `provider()` and `model()` methods (`Promptable::getProvidersAndModels()`), and `null` means the provider's default model. `Ai::build([...])` registers an on-demand provider under a name that is not in `config/ai.php`. Agents implementing `HasStructuredOutput` return validated JSON-schema output, and `Agent::fake()` replaces the gateway in tests. Its exceptions include `RateLimitedException`, `InsufficientCreditsException`, `ProviderOverloadedException`, and `ProviderConnectionException`. The AI Assistant reads its provider and model from a settings table, but the key always comes from `config('ai.providers.<name>.key')`.
- **Spreadsheets.** No spreadsheet library is installed. `maatwebsite/excel` 4.0.x requires `php ^8.3`, `illuminate/support ^12.0 || ^13.0`, and `phpoffice/phpspreadsheet ^5.11`. The app runs Laravel 13.42. `spatie/laravel-data` ^4.13 is already required.
- **Shop exports** (researched 2026-11-23 against the current product exports of two widely used open-source shop platforms). They share a few conventions:
  - a row type column that tells single products, products with variant rows, and variants apart;
  - variant rows that name their product row by ID (`id:N`) or SKU, sometimes in a separate variants file;
  - option name and value column pairs that variant rows number again from 1;
  - packed list items with extra parts, such as `Name:Type:Position`;
  - coded status, stock, and backorder values;
  - price and weight impacts;
  - an apostrophe before cells that start like a formula;
  - translated headers and a column order that grows during the export;
  - prices in the shop's decimal separator next to weights with a dot;
  - product list exports with one row per product, a raw tax-excluded price (`1256.590000`), a locale-formatted tax-included price (`1.503,85 lei`), and thumbnail image URLs.

  §15 says how the import reads them without knowing the platform.

## Goals / Non-Goals

**Goals:**

- Keep each concern in its own class: reading, AI analysis, mapping, cleaning cells and applying a column's conventions, normalizing, structuring rows into products and variants, value matching, validation, and persisting. The file format must not leak past reading, and the AI must not leak past analysis. No class knows a platform.
- Use one mapping format everywhere: the AI proposal, staff edits, and saved profiles all become the same `SheetMapping` data object.
- Make every staged step repeatable from the stored raw rows, without the file and without the AI.
- Make the write step idempotent and safe to retry, one product group at a time.
- Decide number and date formats, separators, and units per column or sheet, from the AI or staff, never by guessing in code or falling back to a default.
- Read any export that uses the known conventions (§15), whichever platform wrote it, without platform-specific code, configuration, or choice.

**Non-Goals:**

- A generic import framework for other entities, such as customers or orders.
- Streaming imports above the row limit. Large catalogs are split into several files.
- Changing `ListProducts`, the admin product forms, or the ERP sync.
- Conventions that no supported export uses yet, such as continuation rows. §15 says how one is added.

## Decisions

### 1. A standalone package registered by the host app

The package is `packages/product-import`, namespace `Lunar\ProductImport`, composer name `lunarphp/product-import`, provider `ProductImportServiceProvider`, plugin `ProductImportPlugin` (id `lunar-product-import`), config key `lunar.product-import`, and translation namespace `lunarpanel.product-import`.

The host registers the plugin when `lunar.product-import.enabled` is true, as it does for Analytics, Feedback, and Review. The plan mentioned a `class_exists` guard in `LunarPanelManager`, as for the AI Assistant. That guard only fits an optional external composer package: inside the monorepo the class always exists, so the guard would never turn anything off. The config flag is the switch that actually works.

The plugin registers `ProductImportResource`, the import profiles page, and a `ListPageExtension` for `ListProducts` whose `headerActions()` appends **Import**. The extension is visible only with `catalog:import-products`. The permission is created by `EnsureProductImportPermissions` on `MigrationsEnded` and `NoPendingMigrations`. Its labels come from `resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php`, merged the way Analytics merges its labels.

*Alternative considered:* adding the feature to `packages/admin`. Rejected: it would ship the dependency and the tables to every shop, and the feature could not be turned off per shop.

### 2. Staging tables in the package, with the raw rows stored at read time

All tables have the `lunar_` prefix, like the other Lunar tables. The migrations extend `Lunar\Base\Migration` and create them with `$this->prefix`. The models extend `BaseModel`, which adds the prefix to the default table name, so they do not set `$table`: the class name already gives the table name (`ProductImportProfile` → `product_import_profiles`).

| Table | Purpose and key columns |
| --- | --- |
| `lunar_product_imports` | `staff_id`, `status`, `options` (json: match key, existing strategy, new product status, collection group, additional collections), `resolved_options` (json: option key → product option id, written when the taxonomy is created), `stats` (json: counts, processed and total groups, processed and total images), `batch_id`, `ai_provider`, `ai_model`, `failure_message`, `started_at`, `finished_at`, `pruned_at` |
| `lunar_product_import_files` | `product_import_id`, `position`, `original_name`, `disk`, `path`, `size`, `encoding` (the CSV encoding chosen at upload; null means UTF-8) |
| `lunar_product_import_sheets` | `product_import_id`, `product_import_file_id`, `position`, `name`, `header_row`, `row_count`, `skipped`, `columns` (json profile per column), `analysis_status`, `ai_proposal` (json), `analysis_error` (json: code, message, raw), `analysis_attempts`, `mapping` (json), `offered_profile_id`, `offered_profile_score`, `profile_id` (the applied profile) |
| `lunar_product_import_rows` | `product_import_id`, `product_import_sheet_id`, `row_number`, `raw` (json, never changed), `typed` (json: the column indexes whose cells the spreadsheet stores as numbers or dates), `normalized` (json), `overrides` (json), `role` (product, variant, product_and_variant), `source_id` and `parent_ref` (the row's own id in the source shop and its parent reference, indexed with the import for resolving parents), `group_key`, `match_value` (the effective match key, for the grouped duplicate query), `status`, `skip_reason` (staff, existing, option value, row type; only a staff skip survives validating again), `messages` (json), `action`, `matched_product_id`, `matched_variant_id`, `product_id`, `product_variant_id` |
| `lunar_product_import_values` | `product_import_id`, `type` (category, brand, option, option_value, property), `option_key` (`''` for categories, brands, and options, the option's key for option values, `type:<product type id>` for properties, so the unique key also holds on MySQL), `source_value`, `normalized_key`, `path` (json), `sheets` (json), `group_keys` (json: the product groups that use the value, so a decision validates only those groups again), `product_count`, `variant_count`, `match_type` (exact, partial, similar, none), `matched_id`, `score`, `action` (use_existing, create, skip), `target_id` (for a property, the attribute id), `create_name`, `parent_id`. Unique on (`product_import_id`, `type`, `option_key`, `normalized_key`) |
| `lunar_product_import_images` | `product_import_id`, `product_import_row_id`, `product_id`, `product_variant_id`, `url` (staff can correct a failed image's URL before a retry; the row's raw cell keeps the original), `position`, `status` (pending, downloaded, existing, failed), `media_id`, `error` |
| `lunar_product_import_profiles` | unique `name`, `header_fingerprint`, `headers` (json, normalized), `mapping` (json), `options` (json), `usage_count`, `last_used_at`, `staff_id` |

The files are read once, by `ReadImportFile`, which stores every non-empty row of every sheet in `lunar_product_import_rows.raw`, keyed by column index and keeping its row number. The column profiles need statistics over all rows anyway. Storing every row also means that a header-row change, a mapping change, and a retry never read the files again.

*Alternative considered:* keeping the rows in the session or cache. Rejected: the data does not fit for large files, queue workers cannot see the session, and the import history would be lost.

*Alternative considered:* Filament's built-in `ImportAction`. Rejected: it writes directly to the database without staging or a preview, reads only CSV, and matches columns only by name.

### 3. Reading with Laravel Excel and a CSV pre-pass

An import holds one or more files. `CreateImportFromUpload` validates every file (extension and `max_file_size_kb`), stores them on the configured disk, and creates one `lunar_product_import_files` row per file. `ReadImportFile` reads the files in upload order. A CSV file is one sheet named after the file. A workbook's sheets keep their names, prefixed with the file name when the import has several files. `max_rows` counts the data rows of all files together.

`Reading\SpreadsheetReader` uses PhpSpreadsheet's `listWorksheetInfo()` for sheet names and sizes, without loading cells. It then reads each sheet in chunks through Laravel Excel (`WithMultipleSheets`, `WithChunkReading`, chunks of 500) into `lunar_product_import_rows`.

Cells are read as calculated values. Integral numbers become digit strings, other floats use a precision-safe decimal string, and cells with a date format become ISO 8601 dates. Text is kept as is, so leading zeros survive. The reader records the column indexes of cells that the spreadsheet stores as numbers or dates in `lunar_product_import_rows.typed`, because those cells are already in a fixed form (decimal dot, ISO 8601) and must not be read with a column's format (§14). CSV cells are never typed. Rows shorter than the header row read as empty in the missing columns, because some exporters write rows before they know every column.

For CSV, `Reading\CsvPreparer` first strips the BOM and detects the delimiter by counting `,`, `;`, and tab in the first lines. Input that is valid UTF-8 is read as is. Other input is converted into a temporary UTF-8 copy from the encoding stored on the file (`lunar_product_import_files.encoding`). Staff choose it in the upload form: UTF-8 (default), Windows-1250, Windows-1252, ISO-8859-2, or ISO-8859-16 (Romanian ș and ț with a comma below). The encoding applies to every CSV file of that upload. It belongs to the upload, not to the configuration, so files with different encodings can follow each other. `CreateImportFromUpload` checks a CSV uploaded without an encoding for valid UTF-8 and rejects the upload before creating the import, naming the file, so a wrong guess never garbles names.

`Reading\HeaderDetector` picks, among the first 10 non-empty rows, the first row whose filled cells are mostly non-numeric text and that has at least as many filled cells as the rows after it. `Analysis\ColumnProfiler` computes the fill rate, distinct share, and samples per column from the staged raw rows. It does not classify columns: the AI reads what a column holds from its header and samples, and the target that staff confirm decides how the column is read (§6, §14). A guessed type misled more than it helped: a weight column holding "1,25 kg", or one with more than 10% "N/A", was classified as text, got fewer samples, and reached the AI labelled text. Every column keeps up to `ai.sample_size` (30) distinct samples, taken from cells stored as text when the column has any, because only those are read with the column's format (§14).

### 4. A queued pipeline with explicit statuses

`Enums\ImportStatus` holds the 12 statuses and the allowed transitions in one place (`canTransitionTo()`, `isBusy()`). Every transition is a conditional update (`where status = <expected>`), so a double click cannot dispatch a job twice. Actions on a busy import are rejected (see the product-import spec).

Two refusals cover a status that does not allow the action:

- `GuardsImportStatus::ensureStatus()` reads the stored status first. When it is analyzing, staging, or importing, the action is refused with `busy` ("The import is being processed. Wait until it has finished."), because the stored status says so.
- When the conditional update itself changes nothing (`transitionTo()` returns false in `ContinueToStaging`, `ContinueToPreview`, `ReturnToMapping`, `ReturnToValueReview`, `StartImport`, and `RetryFailedRows`), another request changed the status between the read and the update: a second click, a cancel, or a finished job. The action cannot know which one, so it is refused with the general `action_not_completed` ("The action could not be completed. Reload the page and try again."), which names no cause.

```
upload ─► ReadImportFile (every file) ─► AnalyzeSheet × sheets ─► [mapping UI]
                                                      │ continue
                                                      ▼
                StageImport (clean → normalize → structure and group → collect values → match → validate)
                                                      ▼
                                   [value review UI] ─► [preview UI]
                                                      │ start (global commit lock)
                                                      ▼
Bus::chain([ CommitTaxonomy, Bus::batch(CommitProductGroups × ⌈groups/25⌉)->allowFailures(),
             CommitAssociations, Bus::batch(DownloadImages × ⌈images/20⌉)->allowFailures(), FinalizeImport ])
```

The jobs use the application's default queue connection and queue. `product-import.queue.name` and `product-import.queue.connection` (env `PRODUCT_IMPORT_QUEUE`, `PRODUCT_IMPORT_QUEUE_CONNECTION`) are null by default. When set, they move every job, the commit chain, and its batches to that queue or connection.

The "one import at a time" rule (commit spec) uses `Cache::lock('lunar-product-import:commit')` while switching to importing, plus a check that no other import is importing. `FinalizeImport` and failure handling end the importing status, and that ends the exclusivity. A retry of failed rows reuses `CommitProductGroups` for those groups, then `CommitAssociations` for those groups and `DownloadImages` for their pending images (and their failed images again), and then `FinalizeImport`. A retry of failed images (§9) moves a completed import, or one completed with errors, back to importing under the same lock, and runs only `DownloadImages` and `FinalizeImport`.

The image URLs are known only once the product groups are written, so `CommitAssociations` builds the `DownloadImages` batch at the end of its run and puts it in front of the rest of the chain (`prependToChain()`), before `FinalizeImport`. Each job gets about `images.per_job` images, without splitting one product's images across jobs, so a product's images arrive in file order.

### 5. The AI agent: structured output, no tools, configurable provider

`Analysis\ColumnMappingAgent` implements `Agent` and `HasStructuredOutput`, uses `Promptable`, and has no tools.

- `provider()` returns `product-import.ai.provider`. When an own key or URL is set, it first registers `Ai::build(['name' => 'product-import', 'driver' => <provider>, 'key' => …, 'url' => …])` and returns `'product-import'`. The chat assistant keeps using its own provider and keys.
- `model()` returns `product-import.ai.model`, or `null` for the provider's default.
- `instructions()` holds the general mapping rules, the separators and units that every answer must give, and the export conventions of §15 in general terms: row types, parent references, renumbered pairs, packed item parts, code meanings, image size suffixes, prices with and without tax, SEO columns and URL slugs, and the columns without a catalog counterpart. It names no platform and no platform's headers: the AI recognizes an export from its headers and samples.
- `schema()` builds the answer schema:
  - `columns[]`: `index`, `target` (an enum built from `Support\TargetFieldRegistry` for the sheet's product type, plus `ignore` and `group_key`), `confidence`, `locale` (an enum of the store's language codes), `option_name`, `pair` (the pair number of an option name or value column), `number_format` (`decimal_separator` `.` or `,`, `thousands_separator` none, `.`, `,`, space, or `'`; null for a column without numbers), `date_format` (a PHP date format; null for a column without dates), `list_separator` (the characters between the items of a list cell, or `none` when each cell holds one item; null for a column that holds no list), `path_separator` (for a category column, the characters between the levels of a path, or `none`), `item_separator` (for a column of packed items, the characters between an item's parts, usually `:`), `values` (a list of `value` and `meaning`, §15), `item_parts` (§15), and `image_suffix` (§15).
  - `grouping`: `strategy` (`by_column`, `by_parent`, `by_name`, `per_row`), `column`, and `confidence`.
  - `units`: `weight_unit` and `length_unit`, each an enum of the store's unit codes (the keys of `lunar.shipping.measurements.weight` and `.length`), null only when the sheet has no weight or dimension column.
  - There are no sheet-level `hints`. The `decimal_comma` hint went first, because number formats belong to columns (§14). The separator hints followed: they were optional, so the code filled in `>` and `,`, and only the first analyzed sheet's hints reached the import options, so the other sheets lost theirs and the queue decided which sheet won. The unit hints were free text that an alias list translated into the store's codes. Now separators belong to columns (§6), and units are chosen from the store's codes.
- `Analysis\SheetDescriptionBuilder` turns a sheet's column profiles into the prompt payload: the sheet name, the row count, each column's index, header, profile, and samples (§3), the allowed targets with their labels, and the language codes. No guessed type is sent. This class is where the "no full rows" rule (AI spec) is enforced and tested.

`Analysis\ProposalValidator` checks the answer and converts it into a `SheetMapping`. Besides the checks in the AI spec, it rejects an option name or value column without a pair number, a pair with a name column but no value column, and grouping by parent without a parent reference column. It keeps a column's number format only when the decimal and thousands separators differ, and a date format only when it consists of date format characters and separators. It keeps a list, path, or item separator of one to three characters without letters or digits, or `none`, and drops any other. It keeps a unit only when it is one of the store's codes. A dropped separator or unit leaves it empty and flags its column, so staff must choose one before continuing. `AnalyzeSheet` stores the proposal on its own sheet only and never changes the import options, so sheets analyzed in any order keep their own separators. It keys code meanings with `KeyNormalizer`, and drops a meaning that does not fit the column's target, item parts with more than one `name` or `value`, and an image suffix with characters other than letters, digits, `-`, and `_` or longer than 50 characters, and flags that column. A dropped format leaves the column without a format, so staff must choose one if they map it to a number or date target. A column whose text samples do not all read in its format is highlighted for review. `Analysis\AnalysisFailure` maps exceptions and validation errors to these codes:

| Code | Source | Automatic retry |
| --- | --- | --- |
| `not_configured` | `Analysis\AiAvailability` before upload | – |
| `auth_failed` | 401 or 403 from the provider | no |
| `rate_limited` | `RateLimitedException`, `InsufficientCreditsException`, 429 | yes |
| `provider_timeout` | timeout, `ProviderConnectionException` | yes |
| `provider_error` | `ProviderOverloadedException`, 5xx | yes |
| `invalid_response` | schema or `ProposalValidator` failure | no |
| `no_product_data` | no name column and no SKU column | no |

`AnalyzeSheet` uses `$tries = 3` and `backoff = [10, 30, 90]`, and calls `$this->fail()` immediately for codes that are not retried. The failed handler stores `analysis_error` and logs the raw exception without the key.

`AiAvailability` treats a provider as configured when it is set and has a key (own key, or `ai.providers.<name>.key`).

Tests use `ColumnMappingAgent::fake([...])` and never reach a provider.

*Alternative considered:* sending rows to the AI and letting it produce products. Rejected: it is costly, it sends customer data out, it is non-deterministic, and the ticket requires that validation and writes are deterministic backend logic.

*Alternative considered:* a column type, guessed by the code or given by the AI, that staff can edit per column. Rejected: the target already decides how a column is read (a number target with its number format, a date target with its date format, a list target with its separators), and a separate type could only contradict it. The AI already chooses the target and the format of every column, and staff correct a column by changing its target or its format.

*Alternative considered:* a second AI request per sheet for the number and date formats, after the mapping proposal, with more values of the mapped columns. Rejected: the mapping request already describes every column, so the format is one more field per column. A second request would add a status and a failure point, and columns that staff map by hand later would still have no format. Number and date columns get up to 30 samples instead of 10 in the existing request.

### 6. One mapping format and one normalized row format

`Data\SheetMapping` (spatie/laravel-data) holds `headerRow`, `productTypeId`, `grouping` (strategy `by_column`, `by_parent`, `by_name`, or `per_row`, plus a column), `weightUnit`, `lengthUnit`, and `columns[]`. Each column holds `index`, `header`, `target`, `locale`, `optionId`, `optionName`, `pair`, `format` (`Data\ColumnFormat`: the number or date format of §14, the list, path, and item separators, and the conventions of §15), `confidence`, `suggestedTarget`, and `flagged`.

Targets are string codes:

| Group | Targets |
| --- | --- |
| Product | `product.name`, `product.description`, `product.status`, `product.slug` (the URL slug, per language), `product.brand`, `product.category`, `product.tags`, `product.properties` (one cell of `Name:Value` items), `product.images`, `product.upsells`, `product.cross_sells` |
| Variant | `variant.sku`, `variant.ean`, `variant.gtin`, `variant.mpn`, `variant.price`, `variant.price_impact`, `variant.sale_price`, `variant.sale_starts`, `variant.sale_ends`, `variant.discount_amount`, `variant.discount_percent`, `variant.stock`, `variant.stock_status`, `variant.backorders`, `variant.min_quantity`, `variant.shippable`, `variant.weight`, `variant.weight_impact`, `variant.length`, `variant.width`, `variant.height`, `variant.image` |
| Options | `variant.option` (one column per option), `variant.option_name` and `variant.option_value` (a pair of columns with a pair number; each cell holds one item or a list), `variant.options` (one cell of `Name:Value` items) |
| Structure | `source.id`, `source.parent`, `source.type` (the row type, §15), `group_key` |
| Other | `attribute.<handle>`, `ignore` |

Number targets are the prices, impacts, discounts, stock, minimum quantity, weight, dimensions, and number attributes; stock and minimum quantity must be whole numbers. Date targets are `variant.sale_starts` and `variant.sale_ends`. List targets split their cell on their column's list separator, ignoring a separator after a backslash: categories, tags, images, up-sells, cross-sells, row types, option and property items, and option pair cells. A category column also has a path separator, and a column of packed items (`variant.options`, `product.properties`, or a pair column with item parts) an item separator. `none` is a valid separator: a cell that holds one item, a category without levels. `Mapping\MappingValidator` refuses a mapped column that lacks a separator its target needs, naming the sheet and the column, as it does for a missing number format (§14). There is no default separator in the code or the configuration.

`Data\ImportOptions` holds the import-level options: match key, existing variants, new product status (`draft`, `published`, or `from_column`), collection group, and additional collection IDs. Separators are not import options: they belong to columns, because two sheets, or two columns of one sheet, may separate their lists differently. The AI proposal, the mapping form, and profiles all read and write these two objects.

`Normalizing\RowNormalizer` turns raw cells plus a `SheetMapping` into `Data\NormalizedRow`, keyed by the same field paths (for example `variant.price` in minor units, or `product.name.ro`). It first cleans each cell (`Normalizing\CellCleaner`, §15) and applies the column's conventions (`Normalizing\ColumnConventions`: code meanings, item parts, and image suffix, §15). It then reads numbers and dates with the column's format (§14), or in the fixed form when the cell is typed. `overrides` uses the same field paths. The effective row is `normalized` merged with `overrides`, so re-normalizing never loses corrections.

**Weight and length units.** Lunar stores a unit next to each variant's weight and dimensions (`weight_value`, `weight_unit`, `length_value`, `length_unit`, …), and shipping converts each line from its variant's own unit. The import therefore stores weights and dimensions in the sheet's units and never converts them. Some exports write the shop's units into the headers ("Weight (kg)", "Length (cm)"), and others have no unit, because it is a shop setting. None has a unit per row, so the units are set per sheet in `SheetMapping.weightUnit` (a key of `lunar.shipping.measurements.weight`) and `SheetMapping.lengthUnit` (a key of `.length`). The AI chooses them from the store's unit codes, which the answer schema lists as an enum (§5), so no alias list translates its words. Staff can change them, and must choose one when a weight or dimension column is mapped and the unit is empty. There is no configured default unit.

**Status of new products.** With `from_column`, `RowNormalizer` reads `product.status` with the column's code meanings first (§15), and otherwise after `KeyNormalizer` (case and accents). It reads 1, yes, true, publish, published, active, aktiv, activ, igen, and da as published, and 0, -1, no, false, draft, private, pending, inactive, inaktiv, inactiv, nem, and nu as draft. A code such as `2` for pending review gets its meaning from the column (draft), not from these lists. Any other value is stored as null with a warning, and a null status gives a draft. The upserter sets the status only when it creates a product.

**Several categories.** `RowNormalizer` splits a category cell on its column's list separator, ignoring a separator after a backslash, and then splits each part into a path on its column's path separator. A list separator `none` keeps the cell as one category, and a path separator `none` keeps each category as one level. `product.category` is a list of paths. This reads lists of `>` paths such as `Femei > Rochii, Femei > Noutăți` (with `\,` inside names) as well as comma-separated lists of `/` paths. `ValueExtractor` collects each path as its own value. The upserter attaches the union of the group's paths, because variant rows often have no categories.

**Additional collections.** `ImportOptions.additionalCollectionIds` lists existing collections from any collection group. The upserter attaches them to every product it creates or updates, together with the categories, through `syncWithoutDetaching`, and ignores IDs that no longer exist.

**SEO fields.** Meta titles, descriptions, and keywords are product attributes, not fields of their own. `lunar-frontend` seeds `product-meta-title`, `product-meta-description`, and `product-meta-keywords` as translated text attributes in the "Search Engine Optimization" group and maps them to its product type. They are therefore offered as `attribute.<handle>` targets of the product type like any other text attribute, and the import names no SEO handle. Their names ("Title", "Description", "Keywords") repeat other targets' names, so `TargetFieldRegistry` labels every attribute target with its attribute group (`Search Engine Optimization › Description`), in the AI payload and in the mapping select. The AI maps a meta title, description, or keywords column to the attribute that holds it, in the column's language, and ignores the column when the product type has no such attribute. Collections have their own SEO attributes, but a product file names a category only by its path, so categories get none.

**URL slugs.** `product.slug` takes a language. `RowNormalizer` reads it with `Str::slug()` in that language. Lunar's `UrlGenerator` creates a new product's default URL from its name when the product is created (`HasUrls`). When the sheet maps a slug, the upserter replaces the generated slug in that language with the file's, so a shop that moves keeps its addresses. Without a slug column, the generated URL stays, as for products created in the admin. An existing product keeps its URLs, because changing a live URL breaks links and search rankings: the file's slug is added only when the product has no URL in that language. A slug must be unique per element type and language, as the admin's URL form requires. A slug that another product already uses in that language, or that another product group of the import uses, gets a number suffix the way `UrlGenerator` makes one (`rochie-vara-2`). `ImportValidator` checks the slugs against the existing URLs of their language, without the matched product's own, and the upserter makes the slug unique again at write time.

### 7. Value matching in memory per import

`Taxonomy\ValueExtractor` collects values from the effective rows: categories, brands, option names that come from cells (pairs and `Name:Value` items), option values, and property names. `Taxonomy\ValueMatcher` loads all brands, the chosen collection group's tree (nested set, default-language names), the product options with their names in every language, the relevant options' values, and the import's product types' text and translated text attributes once, then matches in memory.

The normalized key is `KeyNormalizer::key()`: `Str::of($value)->ascii()->lower()->squish()`. Similarity is `1 − levenshtein / longer length` on the normalized keys, with `product-import.matching.similar_threshold` defaulting to 0.70. Categories are matched level by level under the matched parent. The value's match type is its weakest level.

- **Option names from cells** match product options by name in any language. Exact names default to the existing option, similar ones need a decision, and new ones default to creating an option named as in the file. Their values carry the option key `name:<normalized name>` and are matched within the option the name resolves to. Changing the option decision matches its values again. Options mapped by column keep their `option:<id>` or `new:<name>` key from the mapping.
- **Property names** match the text and translated text attributes of the row's product type by name in any language. An exact name defaults to that attribute. Anything else defaults to skip, because the import does not create attributes. Properties never block the preview.

Re-staging upserts values by the unique key. Rows whose key still exists keep `action`, `target_id`, `create_name`, and `parent_id`. Values that no longer occur are deleted.

*Alternative considered:* letting the AI match categories. Rejected for now. Deterministic matching that staff confirm is predictable and free. AI value normalization is listed as a later phase.

### 8. Structure, validation, and grouping are set-based

`Structuring\RowStructurer` runs after normalizing. It decides each row's role, resolves parent references, assigns `group_key`, and completes variant rows from their product row:

1. **Role.** A row type column decides it when one of the row's type items has a role (`Structuring\RowTypes`, §15). A type that means not imported skips the row with a message naming the type. Otherwise, with grouping by parent, a row with a parent reference is a variant, a row that another row names as its parent is a product, and any other row is both. With the other groupings every row is both, as before.
2. **Parent references** are resolved across all included sheets of the import, in this order: `id:N` against the rows' `source.id`; a plain value against `source.id`, then against the SKU of a row in the import, then against an existing variant's SKU, whose product the row then joins. A reference that resolves to nothing is an error on the row.
3. **Groups.** With grouping by parent, a group is a root row and every row that names it. The other strategies work as before, within one sheet.
4. **Completion.** A variant row takes its product row's weight, dimensions, stock status, backorders, minimum quantity, and shippability when its own cell is empty. Identifiers, prices, and stock are never inherited: a product row's stock is reported as a warning on variant rows without stock, because Lunar keeps stock per variant. A price or weight impact gives the base value plus the impact, where the base is the row's own price or weight, or its product row's when empty. A pair value without a name takes the name of the same pair from the group's product row (some exports write option names only on a product's first row).
5. **Options and properties from cells.** On a variant-only row, pairs and `Name:Value` items are options. On a product-only row, names that are options of the group are skipped, and the others are properties. On a row that is both, they are options, unless the row's type means a product with one variant, whose pairs are properties (§15). `product.properties` items are always properties.

`Validation\ImportValidator` applies the validation spec's rules. Existing variants are loaded in chunks with `whereIn(<match key>, …)`, with their products including trashed ones, to set `action`, `matched_variant_id`, and `matched_product_id`. Only variant rows need a match key, and only they take part in the duplicate check. A product row without any variant row, in the file or matched, is an error.

Duplicate match keys are found with one grouped query over the import's variant rows. Saving a row correction calls the structurer and the validator for that row's group only. Row statuses are valid, warning, error, skipped, imported, and failed.

### 9. Writing to the catalog

`Persisting\TaxonomyCreator` creates brands, collections (`appendNode()` under the decided parent, or a root in the group), options from the mapping and from cells (`handle` slug, `shared` true), and option values. It then writes the new IDs to `lunar_product_import_values.target_id`, and runs in one transaction.

`Persisting\ProductUpserter` runs one `DB::transaction` per group. Inside it, the upserter:

1. Looks the variants up again by the match key with `lockForUpdate()`, so a variant created meanwhile is updated instead of duplicated.
2. Restores a trashed product.
3. Creates or updates the product from the group's product row, or from the first non-empty value among its rows. It resolves attribute field types the way `ListProducts::createRecord()` does, writes only mapped and non-empty fields, writes decided properties into their attributes in the default language, sets `brand_id`, sets the status only for a new product, adds tags without removing existing ones, attaches its categories and the additional collections with `syncWithoutDetaching`, and sets its URL slugs (§6).
4. Attaches options with a pivot `position`.
5. Creates or updates a variant for every variant row with the default tax class, stock, purchasable, minimum quantity, shippability, weight and dimensions with the sheet's units, EAN, GTIN, MPN, and option values.
6. Upserts the base price (quantity 1, default currency, no customer group) with `updateOrCreate`, together with its compare price.

It updates the price in place instead of deleting and recreating it as the ERP job does, so price IDs and history stay stable.

**Sale prices.** A sale price comes from `variant.sale_price`, or from the price minus `variant.discount_amount` or `variant.discount_percent`, rounded to the currency's minor units. It applies when today is between `variant.sale_starts` and `variant.sale_ends` (an empty bound is open) and it is lower than the regular price. Then the base price is the sale price and the compare price is the regular price. Otherwise the base price is the regular price and the compare price is cleared. Whenever a row writes a price, it writes the compare price with it. A sale that applies and has an end date gets a warning, because Lunar does not end it. A sale outside its dates and a sale price not below the regular price are reported in the preview.

**Purchasable.** When stock status or backorders are mapped and filled: backorders allowed, or a stock status on backorder, gives `in_stock_or_on_backorder`. Otherwise a filled stock gives `in_stock`, in stock without a stock number gives `always`, and out of stock without a stock number gives `in_stock` with stock 0.

**Associations.** `Jobs\CommitAssociations` runs after every product group and before the images. `Persisting\AssociationWriter` resolves each `product.upsells` and `product.cross_sells` reference the way parent references are resolved, and adds `up-sell` and `cross-sell` associations without removing existing ones and never to the product itself. An unresolved reference is a warning on the row.

**Images.** The upserter records the group's image URLs in `lunar_product_import_images`, in file order. `Jobs\DownloadImages` handles them in batches through `Persisting\ImageDownloader`:

- It skips a URL that the product already has as media with the same `source_url` custom property, so a second run downloads nothing.
- It fetches only `http` and `https` URLs whose host resolves only to public addresses, checks every redirect the same way (at most 3), and refuses responses over `images.max_size_kb`, slower than `images.timeout`, or with a type other than JPEG, PNG, GIF, or WebP. It never uses `addMediaFromUrl()`, which has no such checks.
- It adds the image to the product's `images` collection with `source_url`, marks the first image of a new product as primary, and links a variant's own image to that variant.
- A failed image is stored with its reason and shown as a warning. It never fails the row or the import.

**Retrying failed images.** A failed image used to be retried only as a side effect of retrying failed rows (`CommitChain::imageBatch()` with group keys), and never on a completed import, whose rows did not fail. Now the result page lists the failed images with their product, URL, and reason.

- `Actions\CorrectImageUrl` changes a failed image's URL in `lunar_product_import_images.url`, and accepts only HTTP and HTTPS. The row's raw cell keeps the original.
- `Actions\RetryFailedImages` takes the global commit lock and sets the import's failed images to pending. It moves the import from completed, or completed with errors, to importing, and dispatches `CommitChain::imageBatch()` followed by `FinalizeImport`.
- `FinalizeImport` recomputes the image counts and sets the status from the rows, because images never change it.
- The retry is refused once the import is pruned, when no image failed, and while another import is importing. It writes no product, variant, or row.

Writes run inside `Product::withoutSyncingToSearch()` and `ProductVariant::withoutSyncingToSearch()`. `FinalizeImport` then calls `searchable()` on the created and updated products in chunks. Model events stay on, so URL generation and the other observers behave as they do for admin-created products. Klaviyo collapses its jobs per product.

### 10. Admin UI as one stateful page per import

`ProductImportResource` sits in the Catalog navigation group and gates on `catalog:import-products`.

- `ListProductImports` shows the history and has a **New import** action. The action opens a modal with a multiple `FileUpload` to the configured private disk and an optional **Encoding** select for CSV files, and it is disabled with the not-configured notice when `AiAvailability` fails.
- `ViewProductImport` shows a step indicator and renders one Livewire component for the current status:
  - `SheetMappingStep`: a tab per sheet, a select per column (attribute targets labelled with their attribute group), the pair number for option name and value columns, the number format (decimal and thousands separator) or date format of each column mapped to a number or date target, the list, path, and item separators of each column whose target needs them, the code meanings, item parts, and image suffix of the columns that have them (§15), the confidence badge, the profile banner, the analysis error panel with **Retry**, the weight and length unit selects when a weight or dimension column is mapped, and the import options (the status of new products and the additional collections). It shows no column type (§3).
  - `ValueReviewStep`: a Filament table on `lunar_product_import_values` with tabs per type (categories, brands, options, option values, properties) and per-row decision actions. It has no separator controls: a wrong split is fixed on its column in the mapping step, which stages again.
  - `PreviewStep`: a Filament table on rows grouped by `group_key`, with product rows shown above their variants, filters, a row-correction modal, a bulk skip, and the start action.
  - `ImportProgress`: `wire:poll.3s` while the import is busy, with processed groups and then processed images.
  - `ImportResult`: the statistics, the created records, the associations added, the images downloaded and failed, the error-report download, **Retry failed rows**, and the failed images with their reason, **Correct URL**, and **Retry failed images**.
- `ManageProfiles` lists, renames, and deletes import profiles.

Every step component calls into actions in `src/Actions/` (for example `SaveSheetMapping`, `DecideValue`, `CorrectRow`, `SkipRows`, `StartImport`, `CancelImport`, `RetryFailedRows`, `CorrectImageUrl`, `RetryFailedImages`), so the rules are tested without the UI.

### 11. Pruning with a command instead of `MassPrunable`

`product-import:prune` runs daily (`product-import.schedule.prune`, default `0 3 * * *`). For each import past `retention_days` (default 30, counted from `finished_at`) or `abandoned_days` (default 7, counted from `created_at`, for imports never started), it deletes rows, values, and image records in chunks, deletes every file of the import, and sets `pruned_at`. It also marks abandoned imports as cancelled.

A command is used because each import needs per-import work that a mass delete cannot do: deleting the files and changing the status.

### 12. Configuration

`config/product-import.php`, published as `lunar/product-import.php`:

- `enabled`
- `disk`
- `max_file_size_kb` (20480, per file)
- `max_rows` (20000, for all files of an import)
- `queue.name`, `queue.connection` (both null: the application's default)
- `ai.provider`, `ai.model`, `ai.key`, `ai.url`, `ai.timeout`
- `ai.sample_size` (30, for every column), `ai.sample_length` (100)
- `ai.confidence.preselect` (0.60), `ai.confidence.review` (0.85)
- `matching.similar_threshold` (0.70)
- `profiles.match_threshold` (0.80)
- `commit.groups_per_job` (25)
- `images.max_size_kb` (10240), `images.timeout` (20), `images.per_job` (20)
- `retention_days`, `abandoned_days`
- `schedule.prune`

The env names are listed in the proposal.

### 13. Package layout

```
packages/product-import/
├─ composer.json, config/product-import.php
├─ database/migrations/, database/state/EnsureProductImportPermissions.php, database/factories/
├─ resources/lang/{en,hu,ro}/, resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php, resources/views/
└─ src/
   ├─ ProductImportServiceProvider.php, ProductImportPlugin.php
   ├─ Models/        ProductImport, ProductImportFile, ProductImportSheet, ProductImportRow, ProductImportValue,
   │                 ProductImportImage, ProductImportProfile
   ├─ Enums/         ImportStatus, SheetAnalysisStatus, RowStatus, RowAction, RowRole, ValueType, ValueAction,
   │                 MatchType, TargetField, ImageStatus, ValueMeaning, ItemPart
   ├─ Data/          SheetMapping, ColumnMapping, ColumnFormat, ImportOptions, NormalizedRow, AiProposal
   ├─ Reading/       SpreadsheetReader, CsvPreparer, HeaderDetector, CellValueFormatter
   ├─ Analysis/      ColumnProfiler, SheetDescriptionBuilder, ColumnMappingAgent,
   │                 ProposalValidator, AnalysisFailure, AiAvailability
   ├─ Mapping/       MappingValidator, ProfileMatcher, HeaderNormalizer
   ├─ Normalizing/   CellCleaner, ColumnConventions, RowNormalizer, NumberParser, DateParser, CategoryList
   ├─ Structuring/   RowStructurer, RowTypes, SkippedRow, ParentResolver
   ├─ Taxonomy/      ValueExtractor, ValueMatcher
   ├─ Validation/    ImportValidator
   ├─ Persisting/    TaxonomyCreator, ProductUpserter, AssociationWriter, ImageDownloader
   ├─ Actions/       CreateImportFromUpload, SaveSheetMapping, ApplyProfile, ContinueToStaging, DecideValue,
   │                 CorrectRow, SkipRows, StartImport, CancelImport, RetryFailedRows, CorrectImageUrl,
   │                 RetryFailedImages, …
   ├─ Queries/       PreviewSummary
   ├─ Exports/       ErrorReport
   ├─ Jobs/          ReadImportFile, AnalyzeSheet, StageImport, CommitTaxonomy, CommitProductGroups,
   │                 CommitAssociations, DownloadImages, FinalizeImport
   ├─ Console/       PruneProductImports
   ├─ Support/       Helpers used by every layer: KeyNormalizer (case-, accent- and space-insensitive comparison keys),
   │                 TargetFieldRegistry + TargetDefinition (the targets a column may map to), WeightUnits, LengthUnits
   │                 (the store's unit codes)
   └─ Filament/      Resources/ProductImportResource (+ Pages), Livewire steps, Extensions/ListProductsExtension
```

### 14. Number and date formats belong to the column

A number's separators belong to its column, not to the sheet. An export from a shop with a decimal comma shows why: its prices use the shop's separator, while its weights are stored with a dot. With one sheet-level `decimal_comma` hint, the weight `1.250` (1.25 kg) was read as 1250:

| Column | Value | Format from the AI | Result |
| --- | --- | --- | --- |
| Regular price | `1256,59` | decimal `,`, thousands none | 1256.59 |
| Weight (kg) | `1.250` | decimal `.`, thousands none | 1.25 |
| Price (tax excl.) | `1256.590000` | decimal `.`, thousands none | 1256.59 |
| Price (tax incl.) | `1.503,85 lei` | decimal `,`, thousands `.` | 1503.85 |

`Data\ColumnFormat` holds `decimalSeparator` (`.` or `,`), `thousandsSeparator` (none, `.`, `,`, space, or `'`), and `dateFormat` (a PHP date format such as `Y-m-d G:i:s` or `d.m.Y`), next to the column's separators (§6) and conventions (§15). It comes from the AI's answer for each column (§5), from an applied profile, or from staff, who can change it in the mapping step before continuing. `Mapping\MappingValidator` refuses a mapping in which a column mapped to a number target has no number format, or a column mapped to a date target has no date format, naming the sheet and the column.

The code never decides a separator per value:

- `Normalizing\NumberParser::parse($text, $format)` removes currency symbols and codes and surrounding spaces. It accepts the thousands separator only between groups of three digits after a first group of one to three digits. A space thousands separator also matches the no-break and narrow no-break spaces that locale-formatted prices contain, for example Hungarian ones. It accepts the decimal separator at most once, followed by digits. Anything else is not a number, and validation reports the raw value and the expected format.
- `Normalizing\DateParser` reads a date strictly with `Carbon::createFromFormat()` and refuses a value with parse errors or warnings.
- A typed cell (§3) is read with the fixed form, a dot decimal or ISO 8601, whatever the column format is. A hand-edited workbook whose price column mixes number cells and text cells is therefore read correctly in both kinds of cells.
- `ColumnProfiler` gives a column no type at all (§3), and the sheet-level `decimal_comma` hint is removed from the AI answer and `RowNormalizer`, as are the other sheet-level hints (§5).

### 15. Export conventions instead of platform classes

The mapping says which field a column holds. An export also has its own row structure and value syntax. The import reads them through a small set of conventions that exports share, never through a platform. There is no platform list, no platform choice per sheet, and no platform name in the code or in the AI instructions. Any export that uses these conventions is read the same way, whichever platform wrote it.

**Cleaning every cell.** `Normalizing\CellCleaner` runs on every cell of every sheet before anything else reads it:

- It removes an apostrophe before `=`, `+`, `-`, `@`, tab, or carriage return at the start of a cell. Exporters add it so that spreadsheets do not run the cell as a formula.
- In descriptions and text attributes, it turns a literal `\n` (and `\r\n`) into a line break.
- In names of brands, categories, tags, options, option values, and properties, it decodes HTML entities until nothing changes, because some exporters escape a name twice (`&amp;amp;`).

The same rules hold for supplier spreadsheets: none of these sequences is meaningful data in a product cell.

**Conventions of a column.** The rest belongs to a column, like its number format (§14). `Data\ColumnFormat` holds them next to the separators and the date format. The AI proposes them (§5), staff can change them in the mapping step (§10), and profiles store them. Changing them never calls the AI. `Normalizing\ColumnConventions` applies them after cleaning and before the general reading:

| Field | Used by | Meaning |
| --- | --- | --- |
| `values` | `product.status`, `variant.stock_status`, `variant.backorders`, `variant.shippable`, `source.type` | The meaning (`Enums\ValueMeaning`) of each code, keyed by its `KeyNormalizer` key: `published` and `draft`; `in_stock`, `out_of_stock`, and `on_backorder`; `yes` and `no`; `empty`, for a code that leaves the field unset; and for row types `single`, `parent`, `variant`, `skip`, and `not_shippable`. A code's meaning comes before the built-in words of §6. A code that neither names is an unknown value of the field. |
| `itemParts` | `variant.option_name`, `variant.option_value`, `variant.options`, `product.properties` | The parts of each list item, split on the column's item separator (`itemSeparator`, usually `:`), each `name`, `value`, or `skip` (`Enums\ItemPart`). An item splits into at most that many parts, the last keeping any further separator. Without parts, a `Name:Value` item splits on its first item separator. `Size:select:0` with `name, skip, skip` gives `Size`; `Compoziție:Bumbac 100%:1` with `name, value, skip` gives one property. |
| `imageSuffix` | `product.images`, `variant.image` | A size suffix (letters, digits, `-`, and `_`, at most 50 characters) that URLs carry to point at a resized copy. It is removed before the file extension or the next `/`: `…/12-small_default.jpg` becomes `…/12.jpg`, and `…/12-small_default/name.jpg` becomes `…/12/name.jpg`. |

Examples of meanings:

- A status column where `2` means pending review gives `2` → `draft`.
- A backorders column where `notify` means allowed gives `notify` → `yes`. One where `2` means the shop's default gives `2` → `empty`.
- A "Virtual product" column mapped to `variant.shippable` gives `1` → `no` and `0` → `yes`.

**Row types.** `source.type` is offered on every sheet. A row type cell holds one item or a list (`simple, virtual`). `Structuring\RowTypes` reads its items with the column's meanings:

- The first item with a role decides the row's role: `single` is product and variant, `parent` is a product, `variant` is a variant, and `skip` skips the row with `RowSkipReason::RowType` and a message naming the type.
- `not_shippable` makes the variant not shippable, unless a shippable cell is filled.
- A row without a role item follows the general rule (§8).
- On a `single` row, pairs and `Name:Value` items are properties, because a single variant has no options to choose between.

**Conventions that need no column field.** They follow from the targets, the grouping, and the structuring rules (§6, §8):

- Parent references by source ID or SKU, with `id:N` naming only a source ID, are resolved across every included sheet, so a separate variants file joins its products file.
- Option name and value pairs are matched by name, because variant rows may number their pairs again from 1. Columns that only say how an attribute is displayed (visible, global, default) are ignored.
- Price and weight impacts are added to the product row's price and weight.
- One row per variant with the product's columns repeated is grouped by column on the product ID.
- Of a price with tax and a price without tax, the one that matches `lunar.pricing.stored_inclusive_of_tax` is mapped and the other is ignored.
- Headers in any language and order and rows shorter than the header are read as they are (§3). Two columns that share a header are told apart by the AI from their position and samples.
- Columns without a catalog counterpart are mapped to ignore: tax and shipping classes, visibility, featured and sold-individually flags, reviews, purchase notes, downloads, positions, stock thresholds, external URLs, customs data, cost and wholesale prices, supplier data, delivery times, condition, customization, shops, warehouses, default-variant flags, and custom field columns unless staff map them to attributes. SEO fields and URL slugs have a counterpart and are imported (§6).

**The AI instructions** describe these conventions in general terms (§5). They name no platform and no platform's headers. The AI recognizes an export from its headers and samples, and staff confirm the mapping.

**Adding a convention.** A new convention is a general rule or a new column field, never a platform class. Two examples from the research:

- Rows that hold only an image of the product above them would need a role for such rows.
- Continuation rows, where the rows after a product's first row leave every product column empty, would need a new grouping strategy (`continuation`: a row without product fields belongs to the previous row's product) in `GroupingStrategy` and `RowStructurer`.

*Alternative considered:* one class per platform, listed in configuration, with the AI naming the platform of each sheet. Rejected: it tied the import to the listed platforms, hid the conventions inside platform classes, so that another platform using the same conventions needed its own class, and asked the AI and staff for a choice that the columns already show.

### 16. Strings go through `Str`

Package code handles strings with `Illuminate\Support\Str` (or `Str::of()`) whenever `Str` has the operation, instead of PHP's native string functions. Native functions stay only where `Str` has no equivalent, such as `levenshtein()`, `ctype_digit()`, and `bcmul()`.

| Instead of | Use | Where today |
| --- | --- | --- |
| `trim()` | `Str::trim()`. This is not only style: native `trim()` keeps the no-break and zero-width spaces that cells from Excel often carry, and `Str::trim()` removes them. | every trim of a cell or name |
| `KeyNormalizer::clean()` | `Str::squish()`, which does exactly the same, so `clean()` is removed | `RowNormalizer`, value collection |
| the body of `KeyNormalizer::key()` | `Str::of($value)->ascii()->lower()->squish()`. `key()` stays the one definition of the comparison key. | `KeyNormalizer` |
| `substr($key, strlen('option:'))` | `Str::after($key, 'option:')` | `ValueCollector.php:34`, `ValueMatcher` |
| BOM check and `substr()` | `Str::chopStart($content, "\xEF\xBB\xBF")` | `CsvPreparer.php:118` |
| `strtolower(trim(...))` | `Str::of(...)->trim()->lower()` | `ProposalValidator.php:257` |
| `preg_match()`, `preg_replace()` | `Str::isMatch()`, `Str::match()`, `Str::replaceMatches()` (the regex stays, the call reads better) | `RowNormalizer.php:117`, `ColumnProfiler`, `NumberParser` |
| `mb_substr($value, 0, $length)` | `Str::limit($value, $length, '')` or `Str::substr()` | `ColumnProfiler`, `SheetDescriptionBuilder` |
| `str_starts_with()`, `str_contains()`, `strtolower()`, `mb_strtolower()`, `ltrim()`, `rtrim()`, `explode()` on a string | `Str::startsWith()`, `Str::contains()`, `Str::lower()`, `Str::ltrim()`, `Str::rtrim()`, `Str::of()->explode()` | across `src/` |

## Risks / Trade-offs

- [`maatwebsite/excel` 4.x has a different API from the 3.1 examples most people know] → The first reading task begins with fixture tests for XLSX, XLS, and CSV against the installed version. If chunked multi-sheet reading does not work as described, the fallback is PhpSpreadsheet readers with a `ReadFilter`, which is already a dependency. *Outcome:* 4.0.7 reads multi-sheet workbooks in chunks as described (imports must also implement the `Import` marker interface), so no fallback was needed. Two details: `excel.imports.read_only` defaults to true, which drops number formats, so `SpreadsheetReader` turns it off while reading to tell date cells from numbers; and chunks are pushed, so `SpreadsheetReader::read()` hands each chunk to a callback instead of yielding rows.
- [XLS (BIFF) files cannot be read in chunks efficiently, and large workbooks use a lot of worker memory] → The 20 MB file limit and the 20,000 row limit apply, `ReadImportFile` gets a configured timeout and memory note, and the read is a single job that fails cleanly with a message.
- [Providers support JSON schema differently, for example enum handling or local Ollama models] → `ProposalValidator` checks every answer anyway, and `invalid_response` offers **Retry**. The provider and model are env settings, so a shop can switch without a deploy of code.
- [The AI picks a wrong number or date format or a wrong separator for a column] → The parser is strict, so a wrong format shows up as errors on the rows instead of silently wrong values, and staff correct the column's format or separator in the mapping step. A column whose samples do not read in the AI's format is highlighted before staging.
- [Every column sends 30 samples instead of 10 for text columns, so a request costs more tokens] → The samples stay at most 100 characters, and there is still one request per sheet.
- [A list, category, or packed-items column that staff map by hand has no separator] → The mapping step asks for it and refuses to continue while it is missing, naming the column, as for number formats.
- [A slug from a file can collide with another product's URL] → It gets a number suffix, as Lunar's URL generator gives one, and the preview warns with the slug used. Existing products never change their URLs.
- [An image fails after the products are written, for example because its host is down] → Staff correct its URL if needed and retry the failed images from the result page, without writing the products again.
- [Similarity matching can suggest wrong categories or options] → Similar values never get a default and always need a staff decision. The threshold is configurable.
- [An ERP sync can write the same SKU while an import is running] → Variants are looked up again with `lockForUpdate()` inside each group transaction. The global lock covers only import against import, and this limit is documented.
- [Grouping by column or name does not span sheets, so the same product on two sheets becomes two products unless its SKUs already exist] → This is stated in the column-mapping spec and shown in the mapping step. Grouping by parent spans sheets.
- [Variant rows without their own SKU, and the SKU of a product row with variant rows, have no place in Lunar, which keeps SKUs only on variants] → A variant row without a SKU is an error that staff correct or skip, and a parent SKU is used only to resolve references within the import.
- [Stock managed on a product row is one pool for all its variants] → It is not copied to each variant. Variant rows without stock get a warning naming the product row's stock.
- [A sale with an end date stays after that date, because Lunar has no scheduled prices] → The preview warns on every such row, and a sale outside its dates is not applied.
- [Images are downloaded from URLs in a file that staff upload] → Only public HTTP and HTTPS addresses are fetched, redirects are checked again, size, time, and type are limited, and downloads run after the products are written, so a slow host never holds a product transaction open. A failed image is a warning, not a failed row.
- [Headers depend on the exporting admin's language, and an export's own sample files may use other headers than its screens] → The AI maps by header and samples, guided by the general conventions, and staff confirm the mapping.
- [Without platform-specific instructions, the AI may miss a quirk of one export, such as a column headed "Status" whose values mark stock availability, or a code that its samples do not show] → Mappings below the review confidence are highlighted, staff confirm every column and can add or change code meanings, and an unknown status code is a warning that gives a draft. A quirk that recurs becomes a general convention (§15).
- [Cleaning every cell could change a supplier value that really starts with an apostrophe before `-`, or a description with a literal `\n`] → Such values do not occur in product data in practice, and one rule for every sheet keeps the reading predictable.
- [New products need a name in the store's default language] → Staff can map any name column to the default language. The error message says so.
- [A large import queues Klaviyo jobs and search updates] → Klaviyo jobs are unique per product and dispatched after commit. Search runs once per import in chunks.
- [The UI is a sizeable Filament and Livewire surface] → The rules live in `Actions/` and are tested directly. The Livewire tests cover only wiring and visibility.

## Migration Plan

1. `composer require maatwebsite/excel:^4.0 laravel/ai:^1.4` in the monorepo root, and add the package entries to the root `composer.json`.
2. Deploy. `php artisan migrate` creates the seven tables, and the state listener creates `catalog:import-products`. No existing table changes.
3. Per shop: set `PRODUCT_IMPORT_ENABLED=true`, `PRODUCT_IMPORT_AI_PROVIDER`, and the key or own key. Register `ProductImportPlugin` in `lunar-frontend` (follow-up there). Grant the permission to the staff roles that need it.
4. Make sure the scheduler runs (for `product-import:prune`) and that a worker listens on the queue the jobs use: the default queue, unless `PRODUCT_IMPORT_QUEUE` or `PRODUCT_IMPORT_QUEUE_CONNECTION` moves them.

Rollback: set `PRODUCT_IMPORT_ENABLED=false`. The tables can stay, or be dropped by rolling back the package migrations. Catalog records that imports created are ordinary records and are not rolled back.

## Open Questions

- Should categories created by an import be hidden from the storefront until staff review them? The plan's mockup showed "create, hidden". This design creates them the way the admin's create-collection action does. A visibility option can be added later without changing the flow.
- Should a finished import also notify the staff member (database notification or e-mail)? The panel has no database notifications today, so the result is shown on the import pages and the list.
