# Spec Delta

## Purpose

Lets staff save an approved sheet mapping as a named profile and reuse it when a file with matching headers arrives. A reused profile replaces the AI analysis for that sheet.

## ADDED Requirements

### Requirement: Staff can save a sheet mapping as a profile

On the mapping step, staff MUST be able to save the current sheet's mapping as a profile with a name. The profile MUST store the sheet's headers, the column targets with their pair numbers, number or date formats, list, path, and item separators, code meanings, item parts, and image size suffixes, the grouping, the weight and length units, the product type, and the import options. Profile names MUST be unique. Saving under an existing name MUST be rejected with a message.

#### Scenario: Save a profile

- **WHEN** staff save the mapping of the sheet "Produse" as "Furnizor Textil SRL export"
- **THEN** a profile with that name holds the headers and mapping of "Produse"

#### Scenario: Duplicate name

- **WHEN** staff save a profile under a name that another profile already has
- **THEN** the save is rejected with a message that the name is taken

### Requirement: Matching profiles are recognized from the headers

After a file is read, the system MUST compare each included sheet's headers with every profile's headers. The comparison MUST ignore letter case, accents, surrounding spaces, and column order. The match share is the number of shared headers divided by the larger of the two header counts. When it reaches the configured share (default 80%), the profile with the highest share MUST be offered for that sheet.

#### Scenario: Same supplier, reordered columns

- **WHEN** a sheet has the same headers as a profile but in another order and with different letter case
- **THEN** the system offers that profile with a 100% match

#### Scenario: Partly matching headers

- **WHEN** a sheet and a profile each have 12 headers and 11 of them are shared
- **THEN** the system offers that profile and shows a 92% match

#### Scenario: Different file

- **WHEN** no profile reaches the matching share for a sheet
- **THEN** no profile is offered and the sheet is analyzed by the AI

### Requirement: Applying a profile replaces the AI analysis

Applying an offered profile MUST copy its mapping onto the sheet without an AI request. Profile columns that the sheet lacks MUST be dropped. Sheet columns that the profile lacks MUST stay unmapped. The profile's usage count and last use time MUST be updated. Dismissing the offer MUST start the AI analysis for that sheet.

#### Scenario: Apply

- **WHEN** staff apply the offered profile to the sheet "Produse"
- **THEN** the sheet's mapping equals the profile's mapping for the shared columns, no analysis request is sent for that sheet, and the profile's usage count goes up by one

#### Scenario: New column in the file

- **WHEN** the sheet has a column "Sezon" that the profile does not have
- **THEN** after applying the profile, "Sezon" is unmapped

#### Scenario: Formats come with the profile

- **WHEN** staff apply a profile whose "Regular price" has a decimal comma, whose "Weight (kg)" has a decimal dot, and whose "Published" gives the code "2" the meaning draft
- **THEN** the columns get those formats and that meaning without an analysis request

#### Scenario: Dismiss

- **WHEN** staff dismiss the offered profile
- **THEN** the system sends the analysis request for that sheet

### Requirement: Profile targets that no longer exist are dropped

When a profile refers to a product type, attribute, or product option that no longer exists, applying it MUST leave those columns unmapped and MUST show which targets were dropped.

#### Scenario: Deleted option

- **WHEN** a profile maps "Marime" to an option that has been deleted since
- **THEN** applying the profile leaves "Marime" unmapped and the page names the missing option

### Requirement: Staff can manage profiles

Staff with the import permission MUST be able to list profiles with their name, header count, usage count, and last use, rename a profile, and delete a profile. Deleting or renaming a profile MUST NOT change past imports.

#### Scenario: Delete a profile

- **WHEN** staff delete a profile that two past imports used
- **THEN** the profile is no longer offered and both imports still show their mappings
