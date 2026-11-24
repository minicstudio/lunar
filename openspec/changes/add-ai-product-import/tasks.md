# Tasks

Run single test files with `php -d memory_limit=-1 vendor/bin/pest <path>` and the whole package with `php -d memory_limit=-1 vendor/bin/pest --testsuite product-import`. Business rules live in `src/Actions`, `src/Jobs`, and the domain folders, and are tested there. The Filament group (10) tests only wiring, visibility, and texts. `PRODUCT_IMPORT_ENABLED` stays off in every shop until group 12 passes.

## 1. Package foundation

- [x] 1.1 Add the dependencies the plan approved: run `composer require maatwebsite/excel:^4.0 laravel/ai:^1.4` in the monorepo root. Verify that `composer show maatwebsite/excel` reports 4.0.x, that `composer show phpoffice/phpspreadsheet` reports 5.x, and that `composer validate` passes.
- [x] 1.2 Scaffold `packages/product-import` the way `packages/analytics` is built. Add `composer.json` (`lunarphp/product-import`, requiring core, admin, `maatwebsite/excel`, and `laravel/ai`), `config/product-import.php` with every key from design §12 and `enabled` false by default, `ProductImportServiceProvider` (merge config into `lunar.product-import`, load translations as `lunarpanel.product-import`, load migrations unless `lunar.database.disable_migrations` is set, publish config and migrations), and `ProductImportPlugin` (id `lunar-product-import`). Wire the root `composer.json`: psr-4 `Lunar\ProductImport\` and factories, autoload-dev `Lunar\Tests\ProductImport\` → `tests/product-import`, `extra.laravel.providers`, `extra.lunar.name` "Product Import", and `replace`. Add a `product-import` suite to `phpunit.xml`. Add `tests/product-import/TestCase.php` and `Providers/ProductImportPanelTestServiceProvider.php`, copying the analytics ones. Verify with `tests/product-import/Feature/ProductImportServiceProviderTest.php`: the config is merged with `enabled` false, the plugin id is `lunar-product-import`, and the provider is registered.
- [x] 1.3 Add the five migrations and models from design §2 (`ProductImport`, `ProductImportSheet`, `ProductImportRow`, `ProductImportValue`, `MappingProfile`), with JSON casts, relations, cascading deletes from the import to its sheets, rows, and values, and factories. Add the enums from design §13. `ImportStatus` holds the 12 statuses, `canTransitionTo()`, `isBusy()` (analyzing, staging, importing), and `isCancellable()` (every status before importing). Add a `transitionTo()` on `ProductImport` that updates only when the stored status is the expected one. Verify with `tests/product-import/Unit/Enums/ImportStatusTest.php` (every allowed and refused transition, busy and cancellable sets) and `tests/product-import/Feature/Models/ProductImportModelsTest.php`: the tables exist with the Lunar prefix, deleting an import deletes its sheets, rows, and values, and a transition from an unexpected status changes nothing. (`MappingProfile` and its table are renamed to `ProductImportProfile` and `lunar_product_import_profiles` in 13.8. The models' `$table` overrides are removed in 13.10.)
- [x] 1.4 Add `EnsureProductImportPermissions` (creates `catalog:import-products` for the panel guard), registered on `MigrationsStarted`, `MigrationsEnded`, and `NoPendingMigrations`. Add permission labels in `resources/lang/vendor/lunarpanel/{en,hu,ro}/auth.php`, merged after boot like `AnalyticsServiceProvider::registerPermissionTranslations()`: English "Import products" / "Allow the staff member to import products from Excel and CSV files". Add a `ProductImportResource` skeleton in the Catalog group with `$permission = 'catalog:import-products'`, and an `Extensions/ListProductsExtension` whose `headerActions()` appends **Import** only for staff with that permission. The plugin registers both. Verify with `tests/product-import/Feature/AccessTest.php`: the permission exists after migrating; staff without it see no **Import** on `ListProducts` and are refused on the resource; staff with it see the button and the list; admins pass; the English Access Control label reads "Import products"; and without the plugin registered, `ListProducts` has no **Import** action.
- [x] 1.5 Document the package. Add a `packages/product-import` row to the package table in `docs/system/CODE_MAP.md`, and add the five tables with their indexes to `docs/database/schema.dbml`. Verify that both files mention `lunar_product_import_rows` and `packages/product-import`.

## 2. Reading files and profiling columns

- [x] 2.1 Add `tests/product-import/Support/SpreadsheetFixtures.php`, which builds XLSX, XLS, and CSV fixtures with PhpSpreadsheet writers at test time, so no binary fixtures are committed. Implement `Reading\CsvPreparer` (BOM strip, conversion from `csv.fallback_encoding` for non-UTF-8 input, delimiter detection among `,`, `;`, and tab), `Reading\CellValueFormatter` (integral numbers as digit strings without exponent, precision-safe decimals, date-formatted cells as ISO 8601, formulas as calculated values, text unchanged), and `Reading\HeaderDetector` (design §3). Verify with `tests/product-import/Unit/Reading/CsvPreparerTest.php`, `CellValueFormatterTest.php`, and `HeaderDetectorTest.php`: a semicolon CSV with decimal commas keeps "49,90" in one cell; Windows-1250 input reads "Rochie roşie" and "Hosszú ujjú póló"; 5941234567890 becomes "5941234567890"; "000123" stays; a formula cell gives its result; a date cell gives "2026-11-18"; a sheet with a title row and an empty row gets header row 3. (The configured fallback encoding is replaced by the encoding chosen at upload in 13.2.)
- [x] 2.2 Implement `Reading\SpreadsheetReader`: sheet list via `listWorksheetInfo()`, then chunked reading of 500 rows with `maatwebsite/excel` 4.x (`WithMultipleSheets`, `WithChunkReading`). It yields non-empty rows with their file row number and cells keyed by column index. Start with a reading test against the installed 4.x API. If chunked multi-sheet reading behaves differently, switch to PhpSpreadsheet readers with a `ReadFilter` (design, first risk) and note it in design.md. Verify with `tests/product-import/Unit/Reading/SpreadsheetReaderTest.php`: XLSX with three sheets, XLS, and CSV are read; the empty row 10 is not yielded and row 11 keeps number 11; and a 1,200-row sheet is read in three chunks.
- [x] 2.3 Implement `Analysis\ColumnProfiler`. Over all data rows below the header row, it computes the type (text, integer, decimal with a dot, decimal with a comma, date, boolean, URL, or empty), the fill rate, the distinct share, up to `ai.sample_size` distinct samples truncated to `ai.sample_length`, and "Column N" for an empty header. Verify with `tests/product-import/Unit/Analysis/ColumnProfilerTest.php`: a column holding "49,90", "129,00", and "15,5" is decimal with a comma at 100% fill; 128 rows with 34 parent codes give a distinct share of about 27%; the samples are capped at 10 and 100 characters; and an empty fifth header becomes "Column 5". (The decimal types become `number`, and number and date columns keep 30 samples, in 15.1. The type is removed and every column keeps 30 samples in 18.3.)
- [x] 2.4 Implement `Actions\CreateImportFromUpload` (validates the extension and `max_file_size_kb`, stores the file on `product-import.disk`, creates an import in uploaded with the staff member and the original name, and dispatches `ReadImportFile` on the configured queue) and `Jobs\ReadImportFile`. The job prepares the CSV, reads every sheet into `lunar_product_import_rows.raw`, creates sheets with name, position, header row, row count, and column profiles, auto-skips sheets without data rows, fails the import with a message naming the limit above `max_rows`, and moves the import to analyzing. Verify with `tests/product-import/Feature/Actions/CreateImportFromUploadTest.php` (`.pdf` and a 25 MB file are rejected with no import created; an XLSX creates an import and queues the job on the configured queue) and `tests/product-import/Feature/Jobs/ReadImportFileTest.php`: "Produse" has 128 rows and "Outlet" 40, "Info" is skipped, raw cells and row numbers are stored, 25,000 rows fail the import with "20,000", and the catalog tables are unchanged. (An upload may hold several files, and the file columns move to `lunar_product_import_files`, in 16.1.)
- [x] 2.5 Implement `Actions\SkipSheet`, `Actions\IncludeSheet`, and `Actions\ChangeHeaderRow`. Changing the header row rebuilds that sheet's column profiles from the stored raw rows without reading the file, and clears that sheet's proposal and mapping. All three are refused while the import is busy or past mapping. Verify with `tests/product-import/Feature/Actions/SheetSelectionTest.php`: a skipped sheet is excluded and can be included again; a header-row change rebuilds only that sheet's columns with the file deleted from disk; and both are refused while staging.

## 3. Mapping model

- [x] 3.1 Implement `Support\TargetFieldRegistry`. For a product type it returns the core targets from design §6, the type's text, translated text, number, and toggle attributes (`attribute.<handle>`), the existing product options, which targets take a language, and the store's language codes. Verify with `tests/product-import/Unit/Support/TargetFieldRegistryTest.php`: a translated text attribute is offered with a language and a file attribute is not; options are listed; and the price label reads "Variant price (RON, incl. tax)" or "Variant price (RON, excl. tax)" according to `lunar.pricing.stored_inclusive_of_tax` and the default currency.
- [x] 3.2 Add `Data\SheetMapping`, `Data\ColumnMapping`, and `Data\ImportOptions` (spatie/laravel-data), with defaults SKU, update, draft, and ">" path separator, and the default collection group. Add `Mapping\MappingValidator`. Single-value targets may be used once per sheet, and name, description, and attributes once per language. Every option column needs an existing option or a new name. A by-column grouping needs a `group_key` column. A product type is required. Every target must exist in the registry. Verify with `tests/product-import/Unit/Mapping/MappingValidatorTest.php`: price mapped twice is rejected naming variant price; names in "ro" and "hu" are accepted; a new option without a name is rejected; and a missing product type is rejected.
- [x] 3.3 Implement `Actions\SaveSheetMapping`, which validates and stores a sheet's mapping and the import options, and refuses while the import is busy, and `Actions\ChangeSheetProductType`, which unmaps attribute targets that the new type lacks and returns their names. A new option's name defaults to the column header. Verify with `tests/product-import/Feature/Actions/SaveSheetMappingTest.php`: the mapping and options round-trip; changing the product type unmaps "Material" and reports it; a save during staging is refused; and "Marime" mapped to a new option is named "Marime" by default.

## 4. AI column analysis

- [x] 4.1 Implement `Analysis\AiAvailability` (design §5: configured when a provider is set and an own key or the `ai.providers.<name>.key` exists, plus the provider and model label for the UI) and `Analysis\SheetDescriptionBuilder` (sheet name, row count, column index, header, profile, the allowed targets, and the language codes, and nothing else). Verify with `tests/product-import/Unit/Analysis/AiAvailabilityTest.php` (no provider, "openai" without any key, the shared provider key, and an own key) and `SheetDescriptionBuilderTest.php`: for a 5,000-row sheet the payload has at most 10 samples per column, no complete row, and no key or catalog data.
- [x] 4.2 Implement `Analysis\ColumnMappingAgent` (`Agent`, `HasStructuredOutput`, `Promptable`, no tools). `provider()` uses `Ai::build()` under the name `product-import` when an own key or URL is set. `model()` returns the configured model or `null`. Its `schema()` has an enum of targets from the registry and an enum of language codes. Implement `Analysis\ProposalValidator`, which converts a valid answer into a `SheetMapping` and rejects unknown columns, unknown targets or languages, a duplicated single-value target ("two columns were marked as SKU"), a missing grouping column, and no name and no SKU (`no_product_data`). Verify with `tests/product-import/Unit/Analysis/ProposalValidatorTest.php` for each rejection, and `tests/product-import/Feature/Analysis/ColumnMappingAgentTest.php` with `ColumnMappingAgent::fake()`: the agent sends one prompt, declares no tools, and with an own key the AI Assistant's provider config is unchanged. (The answer gains column formats in 15.2, and the source format and pair numbers in 16.2 and 16.3. The `decimal_comma` hint is removed in 15.2.)
- [x] 4.3 Implement `Jobs\AnalyzeSheet` and `Analysis\AnalysisFailure`. Map exceptions to the codes in design §5. Use `$tries = 3` and `backoff [10, 30, 90]` for `rate_limited`, `provider_timeout`, and `provider_error`, and fail immediately for the other codes. On success, store `ai_proposal`, the attempts, and the import's `ai_provider` and `ai_model`, and prefill `mapping`: targets at or above `confidence.preselect` are selected, lower ones are left unmapped with the suggestion kept, and targets below `confidence.review` are flagged. On failure, store `analysis_error` (code, translated message, raw text without the key) and log it. Move the import to awaiting mapping once no included sheet is pending, or to analysis failed when one failed. Have `ReadImportFile` and `ChangeHeaderRow` dispatch it per included sheet. Add `Actions\RetrySheetAnalysis` for one sheet. Add the failure messages in en, hu, and ro with the English texts from the AI spec. Verify with `tests/product-import/Feature/Jobs/AnalyzeSheetTest.php`, using the fake gateway: two sheets give two prompts and a skipped sheet gives none; confidences 0.96, 0.74, and 0.40 give selected, selected and flagged, and unmapped; a timeout then a success leaves no error; three timeouts give `provider_timeout` with 3 attempts; a 401 gives `auth_failed` after one attempt; a stored message never contains the key; a retry of "Produse" keeps the proposal of "Outlet"; and no catalog or staging row changes.
- [x] 4.4 Make `Actions\ContinueToStaging`, the step that leaves mapping, refuse while any included sheet has neither a valid proposal nor an applied profile, while any mapping is invalid, or while AI is not configured for a pending analysis. Make `CreateImportFromUpload` refuse when `AiAvailability` fails. Verify with `tests/product-import/Feature/Actions/ContinueToStagingTest.php`: a failed "Produse" blocks continuing until it is retried or skipped, and an upload with no provider configured is refused with the not-configured message.

## 5. Import profiles

- [x] 5.1 Implement `Mapping\HeaderNormalizer` (case, accents, and surrounding spaces) and `Mapping\ProfileMatcher` (shared headers divided by the larger header count, `profiles.match_threshold`, best match first, and a fingerprint of the sorted normalized headers). Verify with `tests/product-import/Unit/Mapping/ProfileMatcherTest.php`: reordered headers in another case give 100%; 11 shared of 12 and 12 give 92%; and below 80% gives no match.
- [x] 5.2 Implement `Actions\SaveMappingProfile` (unique name; stores headers, fingerprint, mapping, product type, and options), `Actions\ApplyMappingProfile` (copies the mapping for shared columns, leaves new columns unmapped, drops missing product types, attributes, and options and reports them, sets `mapping_profile_id`, and updates the usage count and last use), `Actions\DismissMappingProfile` (dispatches `AnalyzeSheet` for that sheet), and rename and delete. In `ReadImportFile`, a sheet with a matching profile gets `offered_profile_id` and score, and no analysis is dispatched for it. Verify with `tests/product-import/Feature/Actions/MappingProfilesTest.php`: a duplicate name is refused; a matching file is offered the profile and the fake gateway receives no prompt for that sheet; applying it copies the mapping, leaves "Sezon" unmapped, and increments the usage; a deleted option is reported; dismissing it sends one prompt; and deleting a profile keeps past imports' mappings. (The actions, `mapping_profile_id`, and the test file are renamed in 13.8.)

## 6. Staging: normalization, grouping, validation

- [x] 6.1 Implement `Normalizing\NumberParser` (decimal comma or dot decided per value, thousands separators, currency symbols and codes removed, and the mapping hint used when a value is ambiguous) and `Normalizing\RowNormalizer` (raw cells + `SheetMapping` → `Data\NormalizedRow` keyed by field path, prices in minor units via the default currency factor, stock as an integer, weight in `defaults.weight_unit`, category paths split on the separator, and option values per option key), plus the effective-row merge of `normalized` and `overrides`. Verify with `tests/product-import/Unit/Normalizing/NumberParserTest.php` ("1.249,90 lei" gives 1249.90, "49.90" gives 49.90, and "1,249.90" gives 1249.90) and `RowNormalizerTest.php`: ignored columns never appear, an override wins over the normalized value, and the raw value stays unchanged. (Weights stop being converted to `defaults.weight_unit` in 13.3, and category cells become lists in 13.5. The per-value separator decision is replaced by the column's format in 15.3.)
- [x] 6.2 Implement `Grouping\ProductGrouper` (by column, by name, or per row, within one sheet) and `Validation\ImportValidator` with every rule in the validation spec: the match key on every row; a name in the default language and a SKU and price for creates; number and integer checks with the raw value in the message; duplicate match keys across sheets naming the other row; repeated option combinations; several rows without an option; groups matching variants of several existing products; matching existing variants including trashed products; skip-existing marking; and the warnings for stock 0, no category, EAN length, and an empty mapped cell on an update. Load existing variants in chunks with `whereIn`. Verify with `tests/product-import/Feature/Validation/ImportValidatorTest.php`, one test per spec scenario, including "Missing price", "Missing SKU", "la comanda", the SKU on "Produse" row 12 and "Outlet" row 5, M / Red repeated, "The variants belong to 2 different existing products", product #1842 getting "HO-114-XL" as a new variant, and the trashed product.
- [x] 6.3 Implement `Jobs\StageImport` and wire `ContinueToStaging` to it. The job normalizes every included row in chunks from the stored raw cells, assigns groups, collects and matches values (group 7 fills these in; until then this step is a no-op), validates, and moves the import to reviewing values when there are values, otherwise to preview ready. It never calls the AI or reads the file. Add `Actions\ReturnToMapping` (from reviewing values and preview ready) and `Actions\ReturnToValueReview` (from preview ready). Verify with `tests/product-import/Feature/Jobs/StageImportTest.php`: the import ends in preview ready for a mapping without category, brand, or option columns; the fake gateway receives no prompt and the deleted file is not needed; going back, adding a weight column, and continuing stages weights while keeping the row-37 correction; and the catalog tables are unchanged.

## 7. Category, brand, and option value review

- [x] 7.1 Implement `Support\KeyNormalizer`, `Taxonomy\ValueExtractor` (distinct values per type and option key across included sheets, with their sheets and product counts, and the count of products without a category), and `Taxonomy\ValueMatcher` (in-memory match against brands, the chosen group's collection tree, and option values; exact, partial, similar at or above `matching.similar_threshold` with the closest record and score, or none; defaults of use existing for exact, create missing levels under the deepest match for partial, nothing for similar, and create for none). Upsert values by the unique key so that decisions survive re-staging and values that vanished are removed, and call this from `StageImport`. Verify with `tests/product-import/Unit/Support/KeyNormalizerTest.php` and `tests/product-import/Feature/Taxonomy/ValueMatcherTest.php`: "Nike" and "NIKE " are one value; "nike" matches the brand "Nike"; "Femei > Hanorace" is similar to "Femei > Hanorac" with no default; "Femei > Rochii > Vară" creates "Vară" under "Femei > Rochii"; "Accesorii" creates a root; "Femei > Tricouri" on two sheets lists both with 14 products; and a decision survives a stock-column change.
- [x] 7.2 Implement `Actions\DecideValue` (use any existing record of the type, create with an editable name and, for categories, a parent within the group, or skip), refused while busy. After a decision, validate the affected rows again: a skipped category or brand gives a no-category or no-brand state, and a skipped option value marks its variants as skipped with a count. Make `Actions\ContinueToPreview` refuse while a similar value has no decision and report the open count. Verify with `tests/product-import/Feature/Actions/DecideValueTest.php`: "Barbati > Geci" set to use "Barbati > Geacă"; skipping "XXS" skips 2 variants; "Accesorii" changed to skip leaves those products without a category; continuing with 1 open decision is refused; and two values set to create "Furnizor Textil" resolve to one planned record.

## 8. Preview, corrections, and starting the import

- [x] 8.1 Implement `Actions\CorrectRow`, which validates the edited fields, stores them in `overrides`, keeps `raw`, and validates only that row's group again, and `Actions\SkipRows` / `Actions\IncludeRows` for one row, a selection, or all error rows. Add a `Queries\PreviewSummary` for the totals (products, new, updated, variants, warnings, errors) and the filters (all, new, update, with problems, skipped). Verify with `tests/product-import/Feature/Actions/RowCorrectionTest.php`: entering 49,90 on "Produse" row 37 clears its error, keeps its raw price empty, and does not touch other groups; skipping all 6 error rows makes the error total 0; and an included row is validated again. Verify with `tests/product-import/Feature/Queries/PreviewSummaryTest.php`: 34 products, 21 new, 13 updated, 118 variants, 9 warnings, and 6 errors.
- [x] 8.2 Implement `Actions\StartImport`. It is refused when no row can be imported. It requires confirmation when error rows remain and then marks them as skipped. It takes the global commit lock (design §4) and refuses with "Another import is running. Try again when it has finished." while another import is importing. It moves the import to importing and dispatches the commit chain (group 9 provides the jobs). Verify with `tests/product-import/Feature/Actions/StartImportTest.php`: the confirmation text reads "Skip rows with errors and import (112 variants)" and 6 rows become skipped; an import with only error rows is refused; a second import is refused and stays in preview ready; and `Bus::fake()` sees the chain on the configured queue.

## 9. Writing to the catalog

- [x] 9.1 Implement `Persisting\TaxonomyCreator` and `Jobs\CommitTaxonomy`, in one transaction. It creates brands, collections (`appendNode()` under the decided parent, or as a root in the chosen group, with the name attribute type resolved as in `CreatesChildCollections`), product options from the mapping (handle slug, shared), and option values. It writes `target_id` back, and creates one record per normalized name, type, and parent or option. On failure, the import becomes failed and no product is written. Verify with `tests/product-import/Feature/Persisting/TaxonomyCreatorTest.php`: "Accesorii" and "Furnizor Textil" are created once each; "Vară" is a child of "Femei > Rochii"; two values set to create "Furnizor Textil" create one brand; "Nike Inc" set to the existing "Nike" creates none; and an exception rolls back everything and fails the import.
- [x] 9.2 Implement `Persisting\ProductUpserter` (design §9) and `Jobs\CommitProductGroups`, with `commit.groups_per_job` groups per job and one transaction per group. The upserter looks variants up again with `lockForUpdate()`, restores trashed products, writes only mapped non-empty fields, attaches collections without detaching, attaches options with position, sets the default tax class, upserts the base price in place, and writes nothing for skip-existing. Rows become imported with `product_id` and `product_variant_id`, or failed with the reason when their group throws. Writes run without search syncing. Verify with `tests/product-import/Feature/Persisting/ProductUpserterTest.php`: "Tricou basic" becomes one draft product with three variants, options, prices, and stock; a stock-only sheet changes only stock; an existing collection "Noutăți" is kept; a trashed product is restored; a SKU that was created after the preview is updated, not duplicated; and skip-existing leaves 13 matched groups unchanged. Verify with `tests/product-import/Feature/Jobs/CommitProductGroupsTest.php`: one group throwing leaves its product absent, marks its rows as failed, and the other 33 groups are imported.
- [x] 9.3 Implement `Jobs\FinalizeImport` and the full chain from `StartImport`: `Bus::chain([CommitTaxonomy, Bus::batch(CommitProductGroups…)->allowFailures(), FinalizeImport])`. Finalizing computes the stats (new and updated products and variants, skipped and failed rows, created brands, categories, and option values, and processed and total groups for progress), sets completed, completed with errors, or failed, sets `finished_at`, and calls `searchable()` on the touched products in chunks. Verify with `tests/product-import/Feature/Jobs/CommitImportTest.php`, running the chain on the sync queue: the same file imported twice leaves the variant count unchanged; a product not in the file is unchanged; 112 imported and 7 failed give completed with errors; no imported row gives failed; the touched products are made searchable once (assert with a Scout fake or a spy); and a Klaviyo `SyncProductToKlaviyo` dispatch for a 3-variant product is queued once when Klaviyo catalog sync is on.
- [x] 9.4 Implement `Exports\ErrorReport`, a streamed CSV with sheet, row number, original cells under their headers, and messages for failed rows and rows skipped because of errors, and `Actions\RetryFailedRows`, which runs `CommitProductGroups` for the groups with failed rows and then `FinalizeImport`. Both are refused once the import is pruned. Verify with `tests/product-import/Feature/ErrorReportTest.php` (7 failed rows give 7 data lines) and `tests/product-import/Feature/Actions/RetryFailedRowsTest.php`: correcting 2 failed rows and retrying processes only their groups, imports them, and lowers the failed count by 2; and a pruned import refuses both.

## 10. Admin pages

- [x] 10.1 Build `ListProductImports`. Its columns are file, staff member, uploaded at, status badge, progress while importing, and a short result. It sorts newest first and has a **New import** action that opens a modal with a `FileUpload` limited to xlsx, xls, and csv and the configured size. The action is disabled when AI is not configured and then shows "AI analysis is not configured. The provider or API key is missing. Contact your developer.". The page shows the AI provider and model line. Wire **Import** on `ListProducts` to the same modal. Verify with `tests/product-import/Feature/Filament/ListProductImportsTest.php` (Livewire): the list shows "completed with errors", "88 new", and "7 errors" for a seeded import and a percentage for an importing one; an upload creates an import and redirects to it; and the not-configured notice disables the upload.
- [x] 10.2 Build `ViewProductImport` with the step indicator (Upload, Mapping, Categories and brands, Preview, Import) and `SheetMappingStep`. The step has a tab per sheet with row counts and skipped state, the header-row select, the column table (header, samples, target select from the registry, confidence badge highlighted below the review threshold, ignore and restore), grouping and product type, the import options, the profile banner (apply or dismiss, match share), the analysis error panel (message, code, attempt, **Retry**), **Save as profile**, and **Continue**, disabled with the reason when blocked. The step polls while busy. Verify with `tests/product-import/Feature/Filament/SheetMappingStepTest.php`: the flagged badge shows on the 74% column; the "Produse" error panel shows `provider_timeout`, attempt 3, and **Retry**; **Continue** is disabled until the sheet is retried or skipped; applying the banner fills the mapping; and the page refreshes while staging.
- [x] 10.3 Build `ValueReviewStep`, a table on `lunar_product_import_values` with tabs for categories, brands, and option values, the stats (existing, similar, new, and without category), the collection group and separator controls, and per-row decision actions. Build `PreviewStep`, a table grouped by product with the totals, filters, row status and messages, a **Correct row** modal that shows each original cell, single, bulk, and all-errors skip, and the start action with confirmation. Verify with `tests/product-import/Feature/Filament/ValueReviewStepTest.php` (1 open decision disables continuing; setting it enables it) and `PreviewStepTest.php`: the totals 34, 21, 13, 118, 9, and 6; correcting row 37 shows "Original cell: empty" and clears the error; and the start button reads "Skip rows with errors and import (112 variants)".
- [x] 10.4 Build `ImportProgress` (`wire:poll.3s`, processed and total groups) and `ImportResult` (stats, created brands, categories, and option values, failed rows, **Download error report**, **Retry failed rows**, **Cancel** where allowed, and the "row data was removed" notice for pruned imports). Build `ManageMappingProfiles` (list with name, header count, usage, and last use, plus rename and delete). Add every UI string in en, hu, and ro under `lunarpanel.product-import::`. Verify with `tests/product-import/Feature/Filament/ImportResultTest.php` (a pruned import hides the download and retry and shows the notice; an importing import has no **Cancel**) and `ManageMappingProfilesTest.php`. Also verify that the en, hu, and ro language files have the same keys, with a test that compares their flattened key sets. (`ManageMappingProfiles` and its test are renamed to `ManageProfiles` in 13.8.)

## 11. Cancelling, retention, and changelog

- [x] 11.1 Implement `Actions\CancelImport`, allowed in every status before importing, including during analysis and staging, where running jobs check the status and stop. Cancelled imports offer no further step. Verify with `tests/product-import/Feature/Actions/CancelImportTest.php`: a cancel during value review gives cancelled; a cancel while importing is refused; a cancelled `StageImport` stops without changing rows further; and an import cancelled after a "create category" decision leaves the catalog unchanged.
- [x] 11.2 Implement `Console\PruneProductImports` (`product-import:prune`) and schedule it from the provider with `schedule.prune` when the feature is enabled, as `LoyaltyServiceProvider::registerSchedule()` does. It deletes rows and values in chunks, deletes the file, and sets `pruned_at` for imports finished more than `retention_days` ago, and for imports never started and created more than `abandoned_days` ago, which also become cancelled. The import record, stats, sheets, mappings, and proposals stay. Verify with `tests/product-import/Feature/Console/PruneProductImportsTest.php`: a completed import is pruned after 31 days but not after 29; an 8-day-old unstarted import is pruned and cancelled; the file is gone and the mapping remains; and the command is scheduled only when the feature is enabled.
- [x] 11.3 Add today's bullet to `docs/changelog/<implementation date>.md`, following `docs/changelog/CHANGELOG.md`, for example: "Added an AI-assisted product import (Excel/CSV) to the admin, with column mapping review, category and brand review, preview, and queued import into the catalog." Verify the file contains the bullet.

## 12. Integration checks

- [x] 12.1 Run `php -d memory_limit=-1 vendor/bin/pest --testsuite product-import --parallel --passthru-php="-d memory_limit=-1"` and the `core` and `admin` suites one at a time. Compare failures against the known pre-existing ones in the project memory before calling any of them new. Run `vendor/bin/pint --test` on the changed paths and `composer test:phpstan` (the 25 errors it already reports are expected). Verify that the product-import suite is green and nothing else changed.
- [ ] 12.2 After groups 14–18, on the lerd site `webshop-demo` (the one that uses the local lunar packages) with `PRODUCT_IMPORT_ENABLED=true`, a real provider key, and the plugin registered locally, run end-to-end imports of:
  - a supplier-style XLSX with two sheets, Romanian headers, decimal commas, and category path columns that use different separators on each sheet;
  - a shop export from a shop with a decimal comma, with a row type column, parent references, renumbered attribute pairs, sale prices, up-sells, images (one of them unreachable), meta title, description, and keywords columns, and a URL slug column;
  - a products file with a separate variants file joined by product ID, with packed `Name:Type:Position` cells and price impacts.

  Turn on lerd `diag` browser logging, and check the request lenses for N+1 queries and errors. Verify that:
  - the products appear in the admin as drafts with variants, prices, compare prices, categories, brand, tags, associations, and images;
  - weights such as "1.250" read 1.25 while prices read with the decimal comma;
  - the rows of the variants file sit under their products with the impacts applied;
  - each sheet's categories are split on its own separators;
  - the new products' SEO attributes and URLs come from the file, and the storefront product page uses them;
  - the unreachable image shows on the result page, and after correcting its URL, **Retry failed images** downloads it;
  - a second run of each file only updates the products, keeps their URLs, and downloads no image again.

## 13. Plan revisions

- [x] 13.1 Default `queue.name` and `queue.connection` to null (`env('PRODUCT_IMPORT_QUEUE')`, `env('PRODUCT_IMPORT_QUEUE_CONNECTION')`) and rewrite the config comment: the jobs use the application's default queue and connection unless these are set. `UsesImportQueue` and `CommitChain` already skip empty values. Update the tests that expect the `product-import` queue. Verify with `CreateImportFromUploadTest.php` and `StartImportTest.php`: without an override, `ReadImportFile` and the commit chain are pushed to the default queue and connection; with `queue.name` set to `product-import` and `queue.connection` set to `database`, they go there.
- [x] 13.2 Move the CSV encoding from the configuration to the upload (design §3). Remove the `csv` config key. Add a nullable `encoding` column to the `lunar_product_imports` create migration (not released yet), the model, and the factory. `CreateImportFromUpload::handle()` takes an optional encoding from UTF-8, Windows-1250, Windows-1252, ISO-8859-2, and ISO-8859-16, stores it, and rejects a CSV without an encoding that is not valid UTF-8 after the BOM. `CsvPreparer::toUtf8()` takes the encoding and keeps valid UTF-8 as is. `ReadImportFile` passes the import's encoding. The **New import** modal gets an optional **Encoding** select for CSV files, with en, hu, and ro labels. Add the column to `docs/database/schema.dbml`. Verify with `CsvPreparerTest.php`, `CreateImportFromUploadTest.php`, and `ReadImportFileTest.php`: a Windows-1250 CSV with Windows-1250 chosen reads "Rochie roşie" and "Hosszú ujjú póló"; a Windows-1250 import followed by an ISO-8859-16 import with "Rochie roșie" both read correctly; a Windows-1250 CSV without an encoding is rejected and no import is created; a UTF-8 CSV with Windows-1250 chosen stays unchanged; an unknown encoding is rejected; and the merged config has no `csv` key. (The encoding moves to `lunar_product_import_files` and applies to every CSV of an upload in 16.1.)
- [x] 13.3 Store weights in the sheet's unit (design §6, weight units). Remove `defaults.weight_unit` and the kilogram conversion in `RowNormalizer`. Add `weightUnit` to `SheetMapping`. `ProposalValidator` sets it from the `weight_unit` hint when the hint resolves to a key of `lunar.shipping.measurements.weight`, and applying a profile copies it. `MappingValidator` refuses a mapped weight column without a unit and names the sheet. `ProductUpserter` writes `weight_unit` from the sheet's mapping. The weight target label loses the configured unit, and `SheetMappingStep` shows a weight unit select of the store's units when a weight column is mapped. Add the texts in en, hu, and ro. Verify with `RowNormalizerTest.php` ("250" with unit g stays 250), `ProductUpserterTest.php` (a variant gets `weight_value` 250 and `weight_unit` g), `ProposalValidatorTest.php` (the hint "grams" gives g; "oz" leaves the unit empty with the default measurements), `MappingValidatorTest.php` (a weight column without a unit is refused), `SheetMappingStepTest.php` (the select offers kg, g, and lbs), and `ProductImportServiceProviderTest.php` (the merged config has no `defaults` key). (The unit comes from the store's codes in the AI answer, without an alias list, in 18.2.)
- [x] 13.4 Let the status option set the status of new products (design §6, status of new products). Add `NewProductStatus::FromColumn` and `TargetField::ProductStatus` (`product.status`) to the enums, the registry, the single-value rule, and the AI schema. `ContinueToStaging` refuses the column option when no included sheet maps a status column. `RowNormalizer` reads the values listed in the design, `ImportValidator` warns about an unknown value, and `ProductUpserter` applies the option, or the group's first status value, only when it creates a product. The status option in the mapping step offers draft, published, and from the status column. Add the texts in en, hu, and ro. Verify with `RowNormalizerTest.php` ("1", "Igen", and "DA" are published; "-1", "0", and "nu" are draft; "maybe" is null), `ImportValidatorTest.php` ("maybe" gives a warning, not an error), `ProductUpserterTest.php` (with the column option, a group with 1 is published and one with -1 is a draft; with the draft option, 1 still gives a draft; an existing draft product stays a draft), and `ContinueToStagingTest.php` (the column option without a status column is refused).
- [x] 13.5 Allow several categories per product (design §6, several categories). Add `categoryListSeparator` (",") to `ImportOptions`, and a `category_list_separator` hint to the AI schema and `ProposalValidator` that prefills it like the path separator. `RowNormalizer` writes `product.category` as a list of paths: it splits on the list separator except after a backslash, and keeps the cell as one category when the separator is empty. Update `ValueExtractor` (including the count of products without a category), `CorrectRow`, `ErrorReport` where it shows categories, and `ProductUpserter` to handle the list and attach the union of the group's paths. Show the list separator next to the path separator in the import options. Verify with `RowNormalizerTest.php` ("Femei > Rochii, Femei > Noutăți" gives two paths; "Rochii\, fuste" gives one category "Rochii, fuste"; an empty separator gives one path), `ValueMatcherTest.php` (both paths are reviewed as their own values), and `ProductUpserterTest.php` (the product is in both collections; a group whose variation rows have no category gets the first row's categories and is not counted as without a category). (The category list separator becomes the list separator of every list cell in 16.3. Both separators move to the columns in 18.1.)
- [x] 13.6 Add additional collections (design §6, additional collections). Add `additionalCollectionIds` (empty by default) to `ImportOptions`, and a searchable multi-select of existing collections to the import options. `ProductUpserter` attaches them to every product it creates or updates with `syncWithoutDetaching`, and ignores IDs that no longer exist. Add the texts in en, hu, and ro. Verify with `ProductUpserterTest.php`: with "Toate categoriile" chosen, 21 created and 13 updated products are all in it and in their own categories; an existing collection membership is kept; a product skipped by skip-existing is not added; and a chosen collection deleted before the import is ignored without failing it.
- [x] 13.7 Run the product-import suite, `vendor/bin/pint --test` on the changed paths, and `composer test:phpstan` as in 12.1, and verify that no code reads `csv.fallback_encoding` or `defaults.weight_unit`.
- [x] 13.8 Rename mapping profiles to import profiles. The migrations are not released yet, so edit them in place, as in 13.2. Rename the table `lunar_product_import_mapping_profiles` to `lunar_product_import_profiles` and its create migration to `2026_11_19_000000_create_product_import_profiles_table.php`, including the `shouldRun()` check. In the `lunar_product_import_sheets` create migration, rename `mapping_profile_id` to `profile_id`, and point it and `offered_profile_id` at the new table. Rename the model `MappingProfile` to `ProductImportProfile` and `MappingProfileFactory` to `ProductImportProfileFactory`. On `ProductImportSheet`, rename `mappingProfile()` to `profile()`. Rename the actions `SaveMappingProfile`, `ApplyMappingProfile`, `DismissMappingProfile`, `RenameMappingProfile`, and `DeleteMappingProfile` to `SaveProfile`, `ApplyProfile`, `DismissProfile`, `RenameProfile`, and `DeleteProfile`. Rename the page `ManageMappingProfiles` to `ManageProfiles` and its view to `manage-profiles.blade.php`. Change the page title to "Import profiles" (hu "Importálási profilok", ro "Profiluri de import") and the config section comment to "Import profiles". Rename `MappingProfilesTest.php` to `ProfilesTest.php` and `ManageMappingProfilesTest.php` to `ManageProfilesTest.php`, and update every reference. Update the table, the `profile_id` column, and the foreign keys in `docs/database/schema.dbml`. A local database that already ran the old migration needs `migrate:fresh`. Verify with `ProductImportModelsTest.php` (`lunar_product_import_profiles` exists and `ProductImportProfile` uses it) and `ProfilesTest.php` (applying a profile sets `profile_id`, and deleting the profile clears it while the sheet keeps its mapping).
- [x] 13.9 Run the product-import suite, `vendor/bin/pint --test` on the changed paths, and `composer test:phpstan` as in 12.1, and verify that `grep -rniE 'mapping_profile|MappingProfile|mapping-profile|mapping profile' packages/product-import tests/product-import docs` finds nothing.
- [x] 13.10 Remove `protected $table` from `ProductImport`, `ProductImportSheet`, `ProductImportRow`, `ProductImportValue`, and `ProductImportProfile` (design §2). The class name already gives the table name without the prefix (`ProductImportProfile` → `product_import_profiles`), and `BaseModel` adds `lunar_`, as on the other Lunar models. The migrations and the table names do not change. Verify with `ProductImportModelsTest.php`: every model's `getTable()` returns its `lunar_` table (`lunar_product_imports`, `lunar_product_import_sheets`, `lunar_product_import_rows`, `lunar_product_import_values`, and `lunar_product_import_profiles`), and `grep -rnF 'protected $table' packages/product-import/src` finds nothing.
- [x] 13.11 Run the product-import suite, `vendor/bin/pint --test` on the changed paths, and `composer test:phpstan` as in 12.1.

## 14. Strings and refusal messages

- [x] 14.1 Handle strings through `Str` (design §16). In `src/`, replace the native calls as the table says:
  - `Str::trim()` for every `trim()` of a cell or name.
  - `Str::squish()` for `KeyNormalizer::clean()`; remove `clean()` and update its callers.
  - `Str::of($value)->ascii()->lower()->squish()` as the body of `KeyNormalizer::key()`.
  - `Str::after()` in `ValueCollector` and `ValueMatcher`, and `Str::chopStart()` in `CsvPreparer`.
  - `Str::of()->trim()->lower()` in `ProposalValidator`.
  - `Str::isMatch()`, `Str::match()`, and `Str::replaceMatches()` for `preg_match()` and `preg_replace()`.
  - `Str::limit($value, $length, '')` for `mb_substr()`.
  - The `Str` methods for `str_starts_with()`, `str_contains()`, `strtolower()`, `mb_strtolower()`, `ltrim()`, `rtrim()`, and string `explode()`.

  Keep native calls only where `Str` has no equivalent. Verify with:
  - `RowNormalizerTest.php`: a SKU "TB-001-S" followed by U+00A0, and a name with a leading U+200B, normalize without them.
  - `KeyNormalizerTest.php`: the existing keys are unchanged, and "Nike" followed by U+00A0 and "NIKE" give one key.
  - `CsvPreparerTest.php`: the BOM is still stripped.
  - `grep -rnE '(^|[^>:a-zA-Z_])(trim|ltrim|rtrim|strtolower|mb_strtolower|substr|mb_substr|preg_match|preg_replace|str_starts_with|str_contains)\(' packages/product-import/src` finds nothing, and the product-import suite stays green.
- [x] 14.2 Refuse with a general message where the cause is unknown (design §4).
  - Add `action_not_completed` to `errors.php` in en ("The action could not be completed. Reload the page and try again."), hu ("A művelet nem sikerült. Töltsd újra az oldalt, és próbáld újra."), and ro ("Acțiunea nu a putut fi finalizată. Reîncarcă pagina și încearcă din nou.").
  - Use it instead of `busy` where `transitionTo()` returns false: `ContinueToStaging`, `ContinueToPreview`, `ReturnToMapping`, `ReturnToValueReview`, `StartImport`, and `RetryFailedRows`.
  - Keep `busy` in `GuardsImportStatus::ensureStatus()`, where the stored status was read and is busy.

  Verify with:
  - A test per action that lets the conditional update lose, by changing the stored status between the guard and the update (for example in a model `updating` listener): the action gives the general message and dispatches no job.
  - A test that returning to mapping while staging still gives "The import is being processed. Wait until it has finished.".

## 15. Number and date formats per column

- [x] 15.1 Record cell kinds and profile columns without guessing separators (design §3, §14).
  - Add the `typed` json column to the `lunar_product_import_rows` create migration (not released yet), the model cast, and the factory.
  - `SpreadsheetReader` and `ReadImportFile` store the indexes of cells that PhpSpreadsheet holds as numbers or dates. CSV rows store none.
  - `ColumnProfiler` returns the types text, number, date, boolean, URL, and empty; `number` replaces `integer`, `decimal_dot`, and `decimal_comma`. Typed cells count as their kind.
  - Number and date columns keep up to `ai.format_sample_size` (a new config key, 30) distinct samples, from untyped cells when the column has any. Other columns keep `ai.sample_size`. `SheetDescriptionBuilder` sends them.

  Verify with:
  - `ColumnProfilerTest.php`: "49,90", "129,00", and "15,5" give `number`; "1.503,85 lei" and "1 503,85 lei" with U+00A0 give `number`; a column with 50 distinct prices keeps 30 samples; a column with number cells and text cells samples only the text cells.
  - `ReadImportFileTest.php`: an XLSX row with the number 1256.59 and the text "49,90" stores the first index in `typed`; a CSV row stores none.
  - `SheetDescriptionBuilderTest.php`: a number column has at most 30 samples and a text column at most 10.
- [x] 15.2 Let the AI give each column its format (design §5, §14).
  - Add `Data\ColumnFormat` (`decimalSeparator`, `thousandsSeparator`, `dateFormat`) and `ColumnMapping.format`.
  - Extend `ColumnMappingAgent::schema()` with `number_format` and `date_format` per column. Remove the `decimal_comma` hint from the schema, the instructions, `ProposalValidator::hints()`, and the `SheetMapping::$hints` shape.
  - Tell the AI in its instructions that a format belongs to one column, and that an export may write prices with the shop's separator but weights with a dot.
  - `ProposalValidator` stores each column's format. It drops a number format with the same separator twice, and a date format holding anything other than date format characters and separators. It flags a column whose text samples do not all read in its format.
  - `ApplyProfile` and `SaveProfile` copy the formats.

  Verify with:
  - `ProposalValidatorTest.php`: an answer for an export with a decimal comma gives "Regular price" a decimal comma and no thousands separator, and "Weight (kg)" a decimal dot and no thousands separator; "," as both separators is dropped and the rest of the answer kept; "Y-m-d G:i:s" is kept and "Y-m-d; DROP" is dropped; a column with the sample "1,25" and a decimal dot without thousands separator is flagged.
  - `ColumnMappingAgentTest.php`: the schema has `number_format` and `date_format` per column and no `decimal_comma`.
  - `ProfilesTest.php`: applying a profile copies the formats.
- [x] 15.3 Read numbers and dates strictly in the column's format (design §14).
  - Rewrite `NumberParser::parse($text, ColumnFormat $format)`. It removes currency symbols and codes. It accepts the thousands separator only between groups of three digits after a first group of one to three, accepts a space separator as U+0020, U+00A0, or U+202F, and accepts the decimal separator at most once. Anything else returns null. Remove the per-value decision.
  - Add `Normalizing\DateParser`, a strict `Carbon::createFromFormat()`.
  - `RowNormalizer` reads number targets with the column's number format and date targets with its date format, reads typed cells in the fixed form, and keeps the raw text of a value that does not fit, for the message.
  - `MappingValidator` refuses a mapped number column without a number format and a mapped date column without a date format, naming the sheet and the column.
  - `ImportValidator` reports the raw value and the expected format.
  - Add the texts in en, hu, and ro.

  Verify with:
  - `NumberParserTest.php`: the four rows of the table in design §14; "1,25" with a decimal dot and no thousands separator is null, not 125; "1.250" with a decimal comma and a dot thousands separator is 1250; "1 503,85 lei" with U+00A0, a decimal comma, and a space thousands separator is 1503.85; "12.34.56" is null.
  - `DateParserTest.php`: "2026-12-01 0:00:00" reads with `Y-m-d G:i:s`; "24.12.2026" with that format is null.
  - `RowNormalizerTest.php`: a typed cell "1256.59" in a column with a decimal comma reads 1256.59.
  - `MappingValidatorTest.php`: a price column without a format is refused, naming the sheet and the column.
  - `ImportValidatorTest.php`: weight "1,25" gives the error with the expected format.
- [x] 15.4 Show and edit the formats in the mapping step (design §10).
  - `SheetMappingStep` shows, for each column mapped to a number target, a decimal separator select (dot, comma) and a thousands separator select (none, dot, comma, space, apostrophe).
  - For a date target it shows a date format input offering the common formats (`Y-m-d`, `Y-m-d H:i:s`, `Y-m-d G:i:s`, `d.m.Y`, `d/m/Y`, `m/d/Y`, `Y. m. d.`).
  - Both are prefilled from the mapping, saved with it, and highlighted when flagged.
  - **Continue** is disabled, with the reason, while a mapped column has no format.
  - Add the texts in en, hu, and ro.

  Verify with `SheetMappingStepTest.php`: a proposal for an export with a decimal comma shows "Regular price" with a comma and "Weight (kg)" with a dot; changing the weight's decimal separator saves it; a flagged format is highlighted; and **Continue** is disabled while the stock column has no format.

## 16. Shop exports

- [x] 16.1 Several files per import (design §2, §3).
  - Edit the unreleased migrations in place:
    - add `lunar_product_import_files` (`product_import_id`, `position`, `original_name`, `disk`, `path`, `size`, `encoding`);
    - move `original_name`, `disk`, `path`, and `encoding` out of `lunar_product_imports`;
    - add `product_import_file_id` to `lunar_product_import_sheets`.
  - Add the `ProductImportFile` model, its relation, and its factory.
  - `CreateImportFromUpload` takes several files. It validates each one (extension, size, and UTF-8 when no encoding is chosen), names the failing file, and stores one file row per file.
  - `ReadImportFile` reads every file in order. It names a CSV sheet after its file, prefixes workbook sheets with the file name when there are several files, and counts `max_rows` over all files.
  - The **New import** modal and **Import** on the product list accept several files.
  - `ListProductImports` shows the first file name and the number of other files.
  - `PruneProductImports` deletes every file.
  - Update `docs/database/schema.dbml`. A local database needs `migrate:fresh`.

  Verify with:
  - `CreateImportFromUploadTest.php`: two CSVs create one import with two file rows; a `.pdf` among them rejects the upload, naming it.
  - `ReadImportFileTest.php`: two CSVs give the sheets "products_import.csv" and "combinations_import.csv"; 12,000 rows in each fail the import with "20,000".
  - `PruneProductImportsTest.php`: both files are deleted.
  - `ListProductImportsTest.php`.
- [x] 16.2 A source format per sheet, chosen from a configured list of platform classes, with its key in the AI answer, the mapping, the mapping step, and profiles. (Replaced in 17.1 by conventions per column. The list, the `sources` config key, and the choice are removed.)
- [x] 16.3 The new targets (design §6).
  - Add these targets to `TargetField`, the registry with labels, the single-value rules, and `MappingValidator`:
    - product: `product.tags`, `product.properties`, `product.images`, `product.upsells`, `product.cross_sells`;
    - variant: `variant.gtin`, `variant.mpn`, `variant.price_impact`, `variant.sale_price`, `variant.sale_starts`, `variant.sale_ends`, `variant.discount_amount`, `variant.discount_percent`, `variant.stock_status`, `variant.backorders`, `variant.min_quantity`, `variant.shippable`, `variant.weight_impact`, `variant.length`, `variant.width`, `variant.height`, `variant.image`;
    - options: `variant.option_name`, `variant.option_value`, `variant.options`;
    - structure: `source.id`, `source.parent`, and `source.type` (offered on every sheet since 17.1).
  - Add `ColumnMapping.pair`, allowing one name and one value per pair number.
  - Add `SheetMapping.lengthUnit` with `Support\LengthUnits` and the `length_unit` hint. A mapped dimension without a length unit is refused, as for the weight unit.
  - Add the `by_parent` grouping strategy, which needs a `source.parent` column.
  - Rename `ImportOptions::$categoryListSeparator` to `listSeparator` and the hint to `list_separator`, and split every list target on it. (Moved to the columns in 18.1.)
  - `RowNormalizer` normalizes each target: lists, booleans (shippable, backorders), the stock status words, and number and date targets with their formats.
  - Add the labels in en, hu, and ro.

  Verify with:
  - `TargetFieldRegistryTest.php`: the new targets and their labels.
  - `MappingValidatorTest.php`: option name pair 1 mapped twice is refused; a pair 1 name without its value is refused; a dimension without a length unit is refused; `by_parent` without a parent column is refused.
  - `RowNormalizerTest.php`: tags "vara, bumbac" give two tags; "Rochii\, fuste" stays one item; the list separator splits images.
  - `ProposalValidatorTest.php`: "Length (cm)" gives cm.
- [x] 16.4 Structure rows into products and variants (design §8).
  - Add `Enums\RowRole`, and the `role`, `source_id`, and `parent_ref` columns to the `lunar_product_import_rows` create migration, indexed with the import.
  - Add `Structuring\RowStructurer` and `Structuring\ParentResolver`:
    - roles come from the row type, or from the general rule (the row type is read with column meanings since 17.4);
    - parent references are resolved across included sheets (`id:N`, source ID, SKU in the import, existing variant SKU);
    - rows are grouped by parent;
    - variant rows are completed (inherited fields, impacts, pair names from the product row);
    - pairs and items from cells become options or properties.
  - Wire it into `StageImport` after normalizing, and into `CorrectRow` for one group. It replaces `ProductGrouper` for the `by_parent` strategy.
  - `ImportValidator`:
    - the match key and the duplicate check apply only to variant rows;
    - a product row without variants is an error, and so are an unresolved parent and an option name without a value;
    - a product row's stock gives a warning on its variant rows that have no stock;
    - the sale and reference warnings of the validation spec.
  - `PreviewStep` shows the product row above its variants. `PreviewSummary` counts only variant rows as variants.

  Verify with:
  - `RowStructurerTest.php`:
    - variant rows 4 and 90 join "id:57", and a variant row on another sheet joins product 12;
    - a parent "HO-114-M" joins product #1842, and a reference to nothing gives an error;
    - a variant row without weight gets 0.5, and an impact of 15.50 on 100.00 gives 115.50;
    - a pair value without a name takes "Size" from the product row;
    - a parent's "Material" is a property and its "Size" is skipped.
  - `ImportValidatorTest.php`:
    - a product row with variant rows and without SKU and price has no error, and a product row without variant rows has the error;
    - an empty "Size" on a variant row is an error;
    - a sale ending on 24 December 2026 warns, and the up-sell "id:310" warns;
    - the product row's stock 10 warns on its variant rows.
- [x] 16.5 Options named in cells and properties in the review (design §7).
  - Add the `option` and `property` value types to `ValueType`, `ValueExtractor`, and the other classes below.
  - `ValueMatcher`:
    - option names are matched against the option names in every language;
    - property names are matched against the text and translated text attributes of the product type;
    - an exact match gives use; a similar option needs a decision; any other property defaults to skip.
  - `DecideValue`: for an option name, use an existing option, create, or skip; for a property, use an attribute or skip.
  - `ContinueToPreview`: open similar option names block continuing; properties never block.
  - `TaxonomyCreator` creates options from cells, merged with a mapped new option of the same name.
  - Option values of an option named in cells use the key `name:<normalized name>`, and are matched again when the option's decision changes.
  - `ValueReviewStep` gets the options and properties tabs.
  - Add the texts in en, hu, and ro.

  Verify with:
  - `ValueMatcherTest.php`: "Mărime" uses "Méret" through its Romanian name; "Material" uses the attribute "Material"; "Compoziție" defaults to skip.
  - `DecideValueTest.php`: setting "Culoare" to use "Szín" matches its values against "Szín"; skipping an option imports its variants without it; a cell-named "Marime" and a mapped new option "Marime" create one option.
- [x] 16.6 Read the first researched export (row types, `id:N` parents, renumbered attribute pairs, escapes, and status, stock, and backorder codes) through a platform class, with platform fixtures and tests. (Replaced in 17.2–17.6 by cell cleaning, column conventions, row types, and neutral fixtures.)
- [x] 16.7 Read the second researched export family (a product list, a products file with a variants file, and one row per variant) through a platform class, with platform fixtures and tests. (Replaced in 17.2–17.6.)
- [x] 16.8 Write the new fields, associations, and images (design §9).
  - `ProductUpserter` writes:
    - tags (added to the existing ones), and decided properties into their attributes in the default language;
    - GTIN, MPN, minimum quantity, shippable, and purchasable by the rule;
    - dimensions with the sheet's length unit, and the sale and compare price by the rule.
  - `ProductUpserter` also records the group's image URLs in a new `lunar_product_import_images` table, with its migration, the `ProductImportImage` model, the `ImageStatus` enum, and a factory.
  - Add `Jobs\CommitAssociations` with `Persisting\AssociationWriter`.
  - Add `Jobs\DownloadImages` with `Persisting\ImageDownloader`, and the config keys `images.*`. The downloader:
    - fetches only from public HTTP and HTTPS hosts, and checks redirects again, at most 3;
    - enforces `images.max_size_kb` and `images.timeout`, and accepts only JPEG, PNG, GIF, and WebP;
    - deduplicates by `source_url`, makes the first image primary on a new product, and links variant images to their variants.
  - Extend the classes that run, finish, and report the commit:
    - `CommitChain` and `RetryFailedRows`;
    - `FinalizeImport`: stats for associations and images; failed images do not change the status;
    - `ImportProgress`: images after groups;
    - `ImportResult`, and `ErrorReport` (rows with failed images);
    - `PruneProductImports`: image records.
  - Add the texts in en, hu, and ro, and the table to `docs/database/schema.dbml`.

  Verify with:
  - `ProductUpserterTest.php`:
    - tags are added to existing ones, and "Material" gets "Bumbac";
    - a running sale gives 149.00 with 199.00, and a sale that is over clears the compare price 249.00;
    - "notify" gives in stock or on backorder, and an empty stock with in stock gives always;
    - dimensions get cm.
  - `CommitAssociationsTest.php`: up-sells "id:57, TB-001-S"; cross-sells "12, 14" by source ID; no association to the product itself; existing associations kept.
  - `ImageDownloaderTest.php`, with `Http::fake()`:
    - three images arrive in order, with the first primary, and a second run downloads nothing;
    - a 404 is a failed image and a warning;
    - "http://127.0.0.1/admin.png", a host resolving to 10.0.0.5, and a redirect to 169.254.169.254 are refused without a request to them;
    - an 11 MB image and a `text/html` response are refused.
  - `CommitImportTest.php`: the chain order, progress over groups and then images, and a failed image leaving the import completed.
- [x] 16.9 Update the docs.
  - Add to `docs/database/schema.dbml` the new tables and columns that 16.1, 16.4, and 16.8 did not already add.
  - Mention the shop export support in the package row of `docs/system/CODE_MAP.md`.
  - Add a bullet to the changelog of the implementation date about per-column number and date formats, shop exports (variants, sale prices, up-sells and cross-sells, images), and several files per import.

  (Both are reworded in 17.7.)
- [x] 16.10 Run the product-import suite, `vendor/bin/pint --test` on the changed paths, and `composer test:phpstan` as in 12.1. Verify that `grep -rnE 'decimal_comma|categoryListSeparator|category_list_separator' packages/product-import tests/product-import` finds nothing.

## 17. Export conventions instead of platform classes

- [x] 17.1 Remove the platform list (design §15).
  - Delete `src/Sources/`: the contract, the registry, the generic class, and the two platform classes. Move `SkippedRow` to `Structuring\`.
  - Remove the `sources` config key and its comment.
  - Remove `SheetMapping::$source`, the source select in `SheetMappingStep`, and the `source` field of `ColumnMappingAgent::schema()`.
  - Remove the source formats from `SheetDescriptionBuilder`, `ProposalValidator`, `ApplyProfile`, `SaveProfile`, `TargetFieldRegistry`, `MappingValidator`, `RowNormalizer`, `RowStructurer`, and `AnalyzeSheet`.
  - Offer `source.type` on every sheet.
  - Delete the `sources.php` language files in en, hu, and ro.

  Verify with:
  - `ProductImportServiceProviderTest.php`: the merged config has no `sources` key.
  - `ColumnMappingAgentTest.php`: the schema has no `source` field.
  - `TargetFieldRegistryTest.php`: `source.type` is offered without any other setting.
  - `grep -rnE 'SourceFormat|GenericSource|product-import\.sources' packages/product-import tests/product-import` finds nothing.
- [x] 17.2 Clean every cell (design §15). Add `Normalizing\CellCleaner`, and call it from `RowNormalizer` for every cell and for staff corrections. It handles:
  - the apostrophe before `=`, `+`, `-`, `@`, tab, or carriage return at the start of a cell;
  - a literal `\n` or `\r\n` in the description and in text attributes;
  - HTML entities in brands, categories, tags, option names and values, and property names, decoded until nothing changes.

  Verify with `tests/product-import/Unit/Normalizing/CellCleanerTest.php`, and with `RowNormalizerTest.php` on a sheet without any column convention:
  - "'-20%" reads "-20%", "'-3" in stock reads -3, and "'abc" stays "'abc";
  - "Rochie lejeră.\nBumbac 100%." has two lines;
  - "Dolce &amp;amp; Gabbana" reads "Dolce & Gabbana".
- [x] 17.3 Column conventions (design §5, §14, §15).
  - Add `Enums\ValueMeaning` and `Enums\ItemPart`, and `values`, `itemParts`, and `imageSuffix` to `Data\ColumnFormat`.
  - Extend `ColumnMappingAgent::schema()` with `values` (a list of `value` and `meaning`), `item_parts`, and `image_suffix` per column.
  - `ProposalValidator` keys the codes with `KeyNormalizer`. It drops a meaning that does not fit the column's target, item parts with more than one `name` or `value`, and an image suffix with characters other than letters, digits, `-`, and `_` or over 50 characters, and flags that column.
  - Add `Normalizing\ColumnConventions`, which `RowNormalizer` calls after cleaning. It applies code meanings before the built-in words, item parts to pair cells, `variant.options`, and `product.properties`, and the image suffix to image cells.
  - `SheetMappingStep` shows, for each column that has them, the code meanings (rows of code and meaning that staff can add and remove), the item parts, and the image suffix. They are prefilled, saved with the mapping, and highlighted when flagged.
  - `SaveProfile` and `ApplyProfile` copy them.
  - Add the texts in en, hu, and ro.

  Verify with:
  - `ProposalValidatorTest.php`: "2" → draft is kept under its key; published on a backorders column is dropped and flagged; the parts `name, name` are dropped; the suffix "-small_default" is kept and "../thumbs" is dropped.
  - `RowNormalizerTest.php`:
    - with "2" → draft, "2" reads draft without an invalid value; without it, "2" is invalid;
    - "notify" → yes allows backorders; "2" → empty on backorders reads as empty;
    - "Virtual product" "1" → no gives not shippable;
    - "Size:select:0, Color:color:1" with `name, skip, skip` gives "Size" and "Color", and "S:0, Alb:1" with `value, skip` gives "S" and "Alb";
    - "Compoziție:Bumbac 100%:1" with `name, value, skip` gives one property, and "Format:16:9" without parts gives the value "16:9";
    - ".../12-small_default.jpg" and ".../12-small_default/rochie.jpg" lose the suffix.
  - `SheetMappingStepTest.php`: adding the code "-1" as draft saves it and sends no prompt; clearing the suffix saves it.
  - `ProfilesTest.php`: applying a profile copies the meanings, the parts, and the suffix.
- [x] 17.4 Row types (design §8, §15).
  - Add `Structuring\RowTypes`, which reads a row type cell as a list with the column's meanings. The first item with a role decides the role (`single`, `parent`, `variant`, or `skip` with a message naming the type). `not_shippable` makes the variant not shippable unless a shippable cell is filled.
  - `RowStructurer` uses it instead of the platform class's role, derived values, and pairs rule. On a `single` row, pairs and `Name:Value` items are properties.
  - Rename `RowSkipReason::Source` to `RowType` (`row_type`). The skip message reads "Rows of the type “:type” are not imported." Add it in en, hu, and ro.

  Verify with `tests/product-import/Unit/Structuring/RowTypesTest.php` and `RowStructurerTest.php`:
  - "variable" → parent and "variation" → variant give one product with two variants;
  - "grouped" → skip skips that row with the message naming "grouped", and its members are imported;
  - "simple, virtual", with "simple" → single and "virtual" → not shippable, gives a product with one variant that is not shippable;
  - the "Color: Roșu" pair of a `single` row is a property;
  - a type without a meaning follows the general rule.
- [x] 17.5 General AI instructions (design §5, §15). Rewrite `ColumnMappingAgent::instructions()` without platform names or platform headers. It describes:
  - row types and their meanings, parent references, and renumbered pairs;
  - packed item parts, code meanings, and image size suffixes;
  - prices with and without tax (map the one that matches the price target and ignore the other);
  - columns that only say how an attribute is displayed, and the columns without a catalog counterpart;
  - a number format example of an export that writes prices with a decimal comma and weights with a dot.

  Tell the AI to give a meaning for each code that the samples show and for codes that such a column is known to hold.

  Verify with `ColumnMappingAgentTest.php`: the instructions describe each convention, and `grep -rniE 'woocommerce|prestashop' packages/product-import/src` finds nothing.
- [x] 17.6 Neutral fixtures and tests. Replace the two platform fixture classes in `tests/product-import/Support/` with `ExportFixtures.php`, and the tests under `tests/product-import/{Unit,Feature}/Sources/` with tests under `tests/product-import/Feature/Conventions/`. Each test stages its fixture with a faked AI answer that gives the conventions:
  - `RowTypeExportTest.php`: an export with a BOM, a row type column, `id:N` parents, variant rows on rows 4 and 90 with renumbered pairs, a single product with attributes, a "grouped" and an "external" row, prices like "1256,59" and weights like "1.250", sale prices with dates, escaped cells, entities in brands, coded status, stock, and backorders, and an early row shorter than the header. It also covers the same export with Hungarian headers, including the repeated "Tulajdonság neve 1".
  - `ProductListExportTest.php`: `;` delimiters, "1256.590000", "1.503,85 lei", "1 503,85 lei" with U+00A0, thumbnail URLs with a size suffix, and "'-3".
  - `ProductAndVariantFilesTest.php`: a products file and a variants file with `Name:Type:Position` and `Value:Position` cells, impacts, features, a discount percent, and cross-sells by source ID.
  - `VariantRowsExportTest.php`: one row per variant with the product columns repeated and packed `Name:Value` options.

  Verify the outcomes that the replaced tests verified:
  - the product with variant rows has its variants and none of its own, and the skipped types have their messages;
  - weights read 1.25 and prices 1256.59;
  - "'-20%" reads "-20%", and "Dolce &amp;amp; Gabbana" reads "Dolce & Gabbana";
  - status "2" gives a draft without a warning, and "backorder" makes the variant purchasable on backorder;
  - the Hungarian export maps the same way;
  - a tax-inclusive store reads 1503.85, and the image becomes ".../12.jpg";
  - "Cămașă" has S / Alb at 100.00 and M / Alb at 110.00, each weighing 0.3;
  - product 14 gets one variant "CUR-14", and a variant row of product 99 has the parent error;
  - "Compoziție:Bumbac 100%:1" is a property, and 25% off 200.00 gives 150.00 with the compare price 200.00;
  - product 7 has variants at 50.00, 50.00, and 55.00.

  Also verify that `grep -rniE 'woocommerce|prestashop' tests/product-import` finds nothing.
- [x] 17.7 Docs and checks.
  - Reword the package row of `docs/system/CODE_MAP.md`: export conventions per column instead of source formats.
  - Reword the bullet in `docs/changelog/2026-11-23.md` without platform names and without `product-import.sources`, describing the conventions the import reads.
  - Run the product-import suite, `vendor/bin/pint --test` on the changed paths, and `composer test:phpstan` as in 12.1.
  - Verify that `grep -rniE 'woocommerce|prestashop|SourceFormat|source format' packages/product-import tests/product-import docs/system/CODE_MAP.md docs/changelog/2026-11-23.md` finds nothing.

## 18. Review revisions (2026-11-24)

- [x] 18.1 Separators per column (design §5, §6, §15).
  - Add `listSeparator`, `pathSeparator`, and `itemSeparator` to `Data\ColumnFormat`, with `none` as a valid value. Remove `pathSeparator` and `listSeparator` from `ImportOptions`, together with the `restore()` fallback to ">", and remove `SheetMapping::$hints`.
  - `ColumnMappingAgent::schema()`: add `list_separator`, `path_separator`, and `item_separator` per column, and remove `hints.path_separator` and `hints.list_separator`. In the instructions, replace "Optionally give hints" with the separators every list, category, and packed-items column must have, and say that `none` means one item or one level.
  - `ProposalValidator` keeps a separator of one to three characters without letters or digits, or `none`, drops any other, and flags the column.
  - `AnalyzeSheet` no longer writes the import options from an answer.
  - `MappingValidator` refuses, naming the sheet and the column:
    - a list target without a list separator;
    - a category column without a path separator;
    - a packed-items column without an item separator: `variant.options`, `product.properties`, or a pair column with item parts.
  - These classes read the column's separators instead of the import options and the fixed `:`: `RowNormalizer`, `CategoryList`, `ColumnConventions`, `ListItems`, `RowTypes`, `RowStructurer`, `ProductUpserter`, `CorrectRow`, `CorrectRowAction`, and `ErrorReport`.
  - `SheetMappingStep` shows the separators of each mapped list, category, or packed-items column. They are prefilled, saved with the mapping, and highlighted when flagged. **Continue** is disabled while one is missing. Remove the separator inputs from the import options and from `ValueReviewStep`.
  - `SaveProfile` and `ApplyProfile` copy the separators with the format.
  - Add the texts in en, hu, and ro, and remove the unused ones.

  Verify with:
  - `ProposalValidatorTest.php`: "Women > Dresses, Women > New" gives "," and ">"; "Color:Red, Size:M" gives "," and ":"; one category per cell gives `none`; the list separator "and" is dropped and flagged.
  - `AnalyzeSheetTest.php`: two sheets whose answers give "," with ">" and "|" with "/" keep their own separators, whichever sheet finishes first, and the import options do not change.
  - `RowNormalizerTest.php`: "Femei/Rochii|Femei/Noutăți" with "|" and "/" gives two paths; "Size=M; Color=Red" with ";" and "=" gives two options.
  - `MappingValidatorTest.php`: tags without a list separator are refused, naming the sheet and the column; `none` is accepted.
  - `SheetMappingStepTest.php` and `ValueReviewStepTest.php`: separators show per column, and neither the import options nor the value review has them.
  - `ProfilesTest.php`: applying a profile copies the separators.
  - `grep -rnE 'hints|pathSeparator|listSeparator' packages/product-import/src/Data/ImportOptions.php packages/product-import/src/Data/SheetMapping.php packages/product-import/src/Jobs/AnalyzeSheet.php` finds nothing.
- [x] 18.2 Units from the store's codes (design §5, §6).
  - `ColumnMappingAgent::schema()` gets `units` (`weight_unit`, `length_unit`). Each is an enum of the keys of `lunar.shipping.measurements.weight` or `.length`, and nullable.
  - The instructions say to give a unit whenever the sheet has a weight or dimension column, and null only otherwise.
  - `ProposalValidator` keeps a unit that is one of the store's codes and drops any other.
  - Remove `MeasurementUnits::resolve()` and the `ALIASES` of `WeightUnits` and `LengthUnits`. They keep only the store's codes.

  Verify with:
  - `ColumnMappingAgentTest.php`: the unit enums equal the store's keys, and the schema has no `hints`.
  - `ProposalValidatorTest.php`: "g" gives g; "grams" is dropped and leaves the unit empty.
  - `grep -rnE 'ALIASES|function resolve' packages/product-import/src/Support` finds nothing.
- [x] 18.3 No guessed column type (design §3).
  - `ColumnProfiler` returns no `type` and loses the type rules. Every column keeps up to `ai.sample_size` distinct samples, taken from cells stored as text when it has any.
  - Config: `ai.sample_size` defaults to 30. Remove `ai.format_sample_size`.
  - `SheetDescriptionBuilder` sends no type and the same number of samples for every column. The instructions no longer mention a column type.
  - `SheetMappingStep` drops the type badge, and its texts go from en, hu, and ro.

  Verify with:
  - `ColumnProfilerTest.php`:
    - profiles have no `type`;
    - a text column with 50 distinct values keeps 30 samples;
    - a column with number cells and text cells samples the text cells;
    - samples such as "1,25 kg" and "N/A" stay as they are.
  - `SheetDescriptionBuilderTest.php`: no column has a `type`, and no column has more than 30 samples.
  - `grep -rnE 'format_sample_size|isFormattedType|TYPE_NUMBER' packages/product-import tests/product-import` finds nothing.
- [x] 18.4 SEO fields and URL slugs (design §6, §9).
  - `TargetFieldRegistry` labels every attribute target with its attribute group ("Search Engine Optimization › Title"), in the AI payload and in the mapping select.
  - Rewrite the instructions:
    - a meta title, description, or keywords column maps to the product type's attribute that holds it, in its language, and is ignored when there is none;
    - a URL slug column maps to `product.slug`, and a column of full product URLs is ignored;
    - "SEO fields … and URL slugs" are removed from the ignored columns.
  - Add `TargetField::ProductSlug` (`product.slug`, with a language, once per language) to the enum, to the registry with its label, and to `MappingValidator`.
  - `RowNormalizer` reads it with `Str::slug()` in the column's language.
  - `ImportValidator` warns:
    - about a cell that gives no slug;
    - about a slug that another product or another group of the import uses, naming the suffixed slug;
    - about a slug that differs from an existing product's URL in that language.
  - `ProductUpserter`:
    - replaces the generated slug of a new product in that language with the file's, made unique with a number suffix at write time;
    - adds the slug to an existing product only when it has no URL in that language.
  - Add the texts in en, hu, and ro.

  Verify with:
  - `TargetFieldRegistryTest.php`: `attribute.product-meta-title` in the group "Search Engine Optimization" reads "Search Engine Optimization › Title"; `product.slug` takes a language.
  - `ColumnMappingAgentTest.php`: the instructions describe SEO columns and slugs, and no longer list them as ignored.
  - `RowNormalizerTest.php`: "Rochie Vară Roșie" gives "rochie-vara-rosie".
  - `ImportValidatorTest.php`: each of the three warnings.
  - `ProductUpserterTest.php`:
    - a new product with the slug "rochie-vara" in "ro" has that default URL and no generated one;
    - a new product without a slug column keeps the generated URL;
    - an existing product keeps "rochie-de-vara";
    - a slug that another product holds becomes "rochie-vara-2";
    - the meta title, description, and keywords are written into their attributes in "ro".
- [x] 18.5 Retry failed images (design §4, §9, §10).
  - `ImportStatus` allows completed → importing, for an image retry.
  - Add `Actions\CorrectImageUrl`. It accepts only HTTP and HTTPS, stores the URL in `lunar_product_import_images.url`, and leaves the raw cell unchanged.
  - Add `Actions\RetryFailedImages`. It takes the global lock, sets the failed images to pending, moves the import to importing, and dispatches `CommitChain::imageBatch()` followed by `FinalizeImport`.
  - Both actions are refused once the import is pruned. The retry is also refused when no image failed and while another import is importing.
  - `FinalizeImport` recomputes the image counts and sets the status from the rows.
  - `ImportResult` lists the failed images with product, URL, and reason, with a **Correct URL** action and **Retry failed images**. Add the texts in en, hu, and ro.

  Verify with `tests/product-import/Feature/Actions/RetryFailedImagesTest.php`, using `Http::fake()`:
  - a 404 image corrected to a working URL is downloaded on retry, the failed count drops by one, and a completed import stays completed;
  - without a correction, the retry tries every failed image again, and the products, variants, and rows are not written;
  - "ftp://cdn.example/1.jpg" is refused as a correction, and the raw cell is unchanged;
  - a pruned import, an import without failed images, and a retry while another import is importing are refused.

  Also verify with `ImportResultTest.php`: the failed images and the button show only when images failed and the rows are kept.
- [x] 18.6 Values that cannot be read (validation spec). Add the missing tests, and fix any path that does not comply:
  - each of these gives a message naming the field and the raw value: a number or date in another format, an unknown code, an image that is not an HTTP or HTTPS URL, and a cell that gives no slug;
  - the raw cell is unchanged, and the correction form shows it as the original cell;
  - correcting the value clears the message.

  Verify with `ImportValidatorTest.php` and `RowCorrectionTest.php`.
- [x] 18.7 The row correction form shows a correction the way it reads it.
  - A review of `Filament/Actions/CorrectRowAction.php`, done by reading the code only, found that `currentValues()`:
    - divides only the price by the currency factor, so a corrected sale price, discount amount, or price impact shows in minor units;
    - writes the price with a dot, even on a decimal-comma column;
    - writes every list as category paths.
  - Saving the form again after a correction would then change those values. First confirm this with a test in `tests/product-import/Feature/Filament/PreviewStepTest.php` that saves the form twice.
  - Then show money fields in major units in the column's number format, and lists with the column's separators.

  Verify that saving the form twice leaves these unchanged: the sale price 149,00, the price 49,90 on a decimal-comma column, and the tags "vara" and "bumbac".
- [x] 18.8 Simplify without changing behavior (code review of 2026-11-24, about 150 lines).
  - Delete the methods that have no caller:
    - `ImportStatus::isFinished()` and `isBeforeImport()`;
    - `ImageStatus::isProcessed()`;
    - `TargetField::takesMeanings()` and `isBoolean()`;
    - `ProductImportRow::isTyped()` and `ReadRow::isTyped()`;
    - `AnalysisFailure::notConfigured()`;
    - `ColumnFormat::numberFormat()`.
  - Inline `ImportValidator::includedRows()`.
  - Remove the branches that cannot run:
    - the `is_string()` decode in `SheetProfileBuilder`;
    - the enum mapping after `pluck()` in `AnalysisCoordinator`;
    - the `is_array($input)` branch in `CorrectRow`.
  - Add one `NormalizedRow` method that builds the named options map. Use it in `ProductUpserter::namedOptions()`, `ImportValidator::checkOptions()` and `skippedOptionValue()`, and `PreviewStep`. When a row names an option twice, validation then keeps the same value as the write.
  - Share `optionOf()` and the "text attributes of a product type" filter between `ValueReviewStep`, `DecideValue`, and `CatalogSnapshot`.
  - `ProductUpserter`: read a variant's images as the list they are stored as, without splitting them again, and sort the group's rows once. `TaxonomyCreator`: load the named options once, before the loop.
  - Drop the redundant `?? []` in `(array) ($row->get(...) ?? [])`.

  Verify that the product-import suite stays green and that `grep -rnE 'isFinished|isBeforeImport|isProcessed|takesMeanings|notConfigured|function includedRows' packages/product-import/src` finds nothing.
- [x] 18.9 Docs and checks.
  - `docs/database/schema.dbml`: the `options` note loses the separators, and the image `url` note says that staff can correct it.
  - `docs/system/CODE_MAP.md`: mention SEO fields, URL slugs, and retrying failed images in the package row.
  - Add a bullet to the changelog of the implementation date: separators and units per column from the AI, SEO fields and URL slugs, and retrying failed images.
  - Run the product-import suite, `vendor/bin/pint --test` on the changed paths, and `composer test:phpstan` as in 12.1.

## Workflow follow-up

- In `lunar-frontend`, register `ProductImportPlugin` in `LunarFrontendServiceProvider::getLunarPanelPlugins()` when `config('lunar.product-import.enabled')` is true, and add the `PRODUCT_IMPORT_*` keys to its `.env.example`. This is a separate repository and a separate PR.
- Grant `catalog:import-products` to the staff roles that need it in each shop after deploy.
- Archive this change after review.
