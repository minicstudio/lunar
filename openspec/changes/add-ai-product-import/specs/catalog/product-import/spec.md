# Spec Delta

## Purpose

Lets staff bring products from supplier spreadsheets into the catalog through a reviewed, multi-step import. This capability covers who can use the import, where it starts, the statuses an import goes through, and how long its staging data is kept.

## ADDED Requirements

### Requirement: The import exists only when the feature is enabled

The admin panel MUST offer the product import only when the product import feature is enabled in configuration. While the feature is disabled, the panel MUST NOT show the import button, the imports navigation item, or the import pages.

#### Scenario: Feature disabled

- **WHEN** a staff member opens the product list while the product import feature is disabled
- **THEN** the page shows no **Import** button and the navigation has no imports item

#### Scenario: Feature enabled

- **WHEN** a staff member who may import products opens the product list while the feature is enabled
- **THEN** the page shows an **Import** button

### Requirement: Only staff with the import permission can import

Every import page and every import action MUST require the `catalog:import-products` permission. Admin staff MUST pass the same way as for other Lunar permissions. **Access Control** MUST list the permission with a label and description in English, Hungarian, and Romanian.

#### Scenario: Staff without the permission

- **WHEN** a staff member without `catalog:import-products` opens the product list or an import page URL
- **THEN** the product list has no **Import** button and the import page is refused

#### Scenario: Staff with the permission

- **WHEN** a staff member with `catalog:import-products` opens the imports list
- **THEN** the list is shown and the staff member can start a new import

#### Scenario: Permission shown in Access Control

- **WHEN** an administrator opens a staff member's permissions in English
- **THEN** the list includes "Import products" with its description

### Requirement: Imports start from the product list and are listed under Catalog

The product list MUST offer an **Import** header action that opens the upload form. The Catalog navigation group MUST contain an **Imports** item that lists past and current imports.

#### Scenario: Starting from the product list

- **WHEN** a staff member with the import permission clicks **Import** on the product list
- **THEN** the upload form opens

#### Scenario: Opening the history

- **WHEN** a staff member with the import permission opens **Imports** in the Catalog group
- **THEN** the system lists imports, newest first

### Requirement: Each upload is an independent import

Each upload MUST create its own import, with its own files, sheets, rows, mapping, and decisions. An upload of several files MUST create one import that holds all of them. Processing one import MUST NOT read, change, or re-import the rows of another import.

#### Scenario: Second file after a finished import

- **WHEN** staff finish an import of file A and then import file B
- **THEN** the second import processes only the rows of file B and leaves import A and its rows unchanged

#### Scenario: Two files in one upload

- **WHEN** staff upload a products file and its separate variants file together
- **THEN** one import is created that holds both files

#### Scenario: Rows that failed in an earlier import

- **WHEN** some rows of import A failed and staff then import file B
- **THEN** the failed rows of import A are not imported by import B

### Requirement: The catalog stays unchanged until staff approve the import

Before staff start the import, the system MUST NOT create, update, or delete any product, variant, price, brand, collection, product option, or option value. Uploading, analysis, mapping, value review, validation, and row corrections MUST write only to the import's own staging data.

#### Scenario: Import cancelled before approval

- **WHEN** staff upload a file, map it, review the values, and cancel before starting the import
- **THEN** the catalog contains exactly the records it had before the upload

#### Scenario: New categories during review

- **WHEN** staff choose to create a new category in the value review step
- **THEN** no collection exists for it until the import is started

### Requirement: An import moves through defined statuses

An import MUST always have exactly one of these statuses: uploaded, analyzing, analysis failed, awaiting mapping, staging, reviewing values, preview ready, importing, completed, completed with errors, failed, or cancelled. A retry of failed rows or failed images MUST move a finished import back to importing until the retry ends. The import page MUST show the current status and refresh itself while background work runs.

#### Scenario: Normal path

- **WHEN** staff upload a file, accept the mapping, decide all values, and start the import, and every row is imported
- **THEN** the import passes through uploaded, analyzing, awaiting mapping, staging, reviewing values, preview ready, and importing, and ends as completed

#### Scenario: Retrying failed images

- **WHEN** staff retry the failed images of a completed import whose rows were all imported
- **THEN** the import is importing while the images download, and is completed again afterwards

#### Scenario: No values to review

- **WHEN** staging finishes for a mapping that has no category, brand, or option column
- **THEN** the import goes from staging directly to preview ready

#### Scenario: Background work in progress

- **WHEN** an import page is open while the import is analyzing, staging, or importing
- **THEN** the page updates the status and progress without a manual reload

### Requirement: Staff can step back before importing

From reviewing values and from preview ready, staff MUST be able to return to the mapping step. From preview ready, they MUST be able to return to the value review step. Stepping back MUST keep row corrections and value decisions that still apply.

#### Scenario: Back from preview to mapping

- **WHEN** staff go back from the preview to the mapping, change a column target, and continue
- **THEN** the system stages the rows again, keeps earlier row corrections, and does not call the AI

### Requirement: Changes are refused while background work runs

While an import is analyzing, staging, or importing, the system MUST reject any action that changes that import's mapping, value decisions, row corrections, or status, except cancelling during analysis or staging.

#### Scenario: Mapping change during staging

- **WHEN** staff submit a mapping change while the import is staging
- **THEN** the system rejects the change and the stored mapping stays as it was

### Requirement: A refusal names only a cause the system knows

The system MUST say that an import is being processed only when its stored status is analyzing, staging, or importing. When an action finds that the import's status changed while the action ran, so that it cannot take its step, the system MUST refuse it with a general message that the action could not be completed, without naming a cause.

#### Scenario: Two requests to continue at once

- **WHEN** two requests to continue from the mapping step arrive together and the first one moves the import to staging
- **THEN** the second is refused with "The action could not be completed. Reload the page and try again." and no second staging is started

#### Scenario: Action while staging

- **WHEN** staff try to return to the mapping step while the stored status is staging
- **THEN** the action is refused with "The import is being processed. Wait until it has finished."

### Requirement: Staff can cancel an import before it starts importing

Staff MUST be able to cancel an import in any status before importing. A cancelled import MUST NOT be resumed or imported. Cancelling MUST NOT change the catalog. An import that is importing or has finished MUST NOT be cancellable.

#### Scenario: Cancel during value review

- **WHEN** staff cancel an import that is reviewing values
- **THEN** the import becomes cancelled and offers no further steps

#### Scenario: Cancel while importing

- **WHEN** staff open an import that is importing
- **THEN** the page offers no cancel action

### Requirement: Import history keeps the outcome of each import

The imports list MUST show for each import the original file names, the staff member who uploaded them, the upload time, the status, the progress while importing, and a short result. The import detail page MUST show the statistics, the mapping of each sheet, the categories, brands, options, and option values it created, the images that failed, and its failed rows while they are kept.

#### Scenario: Finished import in the list

- **WHEN** staff open the imports list after an import completed with 88 new products and 7 failed rows
- **THEN** that import's row shows completed with errors, 88 new, and 7 errors

#### Scenario: Import in progress in the list

- **WHEN** staff open the imports list while an import is importing
- **THEN** that import's row shows the share of product groups already processed

### Requirement: Staging data is pruned after a retention period

Each import's record, statistics, sheet mappings, and AI proposals MUST be kept permanently. Its staged rows, collected values, image records, and uploaded files MUST be deleted once the retention period after it finished passes (default 30 days). An import that was cancelled, or not started within the abandoned period (default 7 days), MUST lose them after that shorter period.

#### Scenario: Finished import past retention

- **WHEN** the scheduled prune runs 31 days after an import completed, with the default retention
- **THEN** the import's rows, values, and files are deleted and its record, statistics, and mappings remain

#### Scenario: Pruned import detail

- **WHEN** staff open a pruned import
- **THEN** the page shows its statistics and mappings, says the row data was removed, and offers no error report or retry

#### Scenario: Abandoned import

- **WHEN** the scheduled prune runs 8 days after an upload that was never imported, with the default abandoned period
- **THEN** the import's rows, values, and files are deleted and the import becomes cancelled
