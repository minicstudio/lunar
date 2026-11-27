# product-import-ai-analysis Specification

## Purpose

Defines how the product import asks an AI provider to suggest a column mapping, the column formats and separators, the units, and the export conventions of the columns for each sheet: what is sent, what may come back, how the answer is checked and used, how the provider is configured, and what staff see when the analysis fails.

## Requirements

### Requirement: Each included sheet gets one analysis request

After a file is read, the system MUST send one analysis request for each included sheet, unless an import profile is offered or applied for that sheet. It MUST NOT send requests for skipped sheets or for individual rows.

#### Scenario: Two included sheets

- **WHEN** a file with two included sheets and no matching profile is read
- **THEN** the system sends exactly two analysis requests, one per sheet

#### Scenario: Sheet with an offered profile

- **WHEN** a sheet matches a saved import profile
- **THEN** no analysis request is sent for that sheet while the profile is offered

### Requirement: Only column descriptions are sent

An analysis request MUST contain only the sheet name, its data row count, each column's index, header, and profile (fill rate, distinct share, and up to 30 samples of at most 100 characters), the allowed target fields with their labels, and the store's language codes. It MUST NOT contain a type guessed by the system, full rows, other sheets, catalog records, or credentials.

#### Scenario: Large sheet

- **WHEN** a sheet with 5,000 rows is analyzed
- **THEN** the request holds at most 30 sample values per column, no column type, and no complete row

### Requirement: The AI cannot change data

The AI MUST receive no tools and no access to the database, the file, or the staging data. Its answer MUST only be stored as a proposal for staff to review. The answer MUST NOT trigger any write other than storing that proposal.

#### Scenario: Analysis finishes

- **WHEN** the AI answers for a sheet
- **THEN** the system stores the proposal on that sheet and no staging row, catalog record, or other sheet changes

### Requirement: The answer has a fixed structure

The answer MUST follow a structure defined by the system. For each column it MUST give a target from the allowed list or "ignore", a confidence between 0 and 1, and, where relevant, a language code, an option name, a pair number, a format, separators, code meanings, item parts, or an image size suffix. It MUST give a grouping with a confidence and the sheet's units. It MUST NOT name a platform or give hints for the whole sheet.

#### Scenario: Structured answer

- **WHEN** the AI suggests that column 0 is the Romanian product name with 0.96 confidence and column 1 groups the rows with 0.74 confidence
- **THEN** the stored proposal holds the target product name, language "ro", confidence 0.96 for column 0, and a grouping by column 1 with confidence 0.74

### Requirement: The AI gives each number and date column its format

For every column that holds numbers, the answer MUST give the decimal separator (dot or comma) and the thousands separator (none, dot, comma, space, or apostrophe). For every column that holds dates, it MUST give the date format. A format belongs to one column; the answer MUST NOT give a number format for the whole sheet.

#### Scenario: Export with a decimal comma

- **WHEN** the AI analyzes an export whose "Regular price" holds "1256,59" and whose "Weight (kg)" holds "1.250"
- **THEN** the proposal gives "Regular price" a decimal comma and no thousands separator, and "Weight (kg)" a decimal dot and no thousands separator

#### Scenario: Prices with and without tax

- **WHEN** the AI analyzes an export whose "Price (tax excl.)" holds "1256.590000" and whose "Price (tax incl.)" holds "1.503,85 lei"
- **THEN** the proposal gives the first a decimal dot and no thousands separator, and the second a decimal comma and a dot thousands separator

#### Scenario: Sale dates

- **WHEN** the column "Date sale price starts" holds "2026-12-01 0:00:00"
- **THEN** the proposal gives it the date format "Y-m-d G:i:s"

### Requirement: The AI gives each list column its separators

For every column mapped to a list target, the answer MUST give the list separator, or "none" when each cell holds one item. For a category column it MUST also give the path separator, or "none" when categories have no levels, and for a column of packed items the item separator. A separator belongs to one column, so the columns and sheets of one import may differ.

#### Scenario: Category list

- **WHEN** the AI analyzes a "Categories" column whose samples hold "Women > Dresses, Women > New"
- **THEN** the proposal gives that column the list separator "," and the path separator ">"

#### Scenario: One category per cell

- **WHEN** the AI analyzes a category column whose samples hold "Rochii" and "Tricouri"
- **THEN** the proposal gives that column the list separator "none" and the path separator "none"

#### Scenario: Packed options

- **WHEN** the AI analyzes an options column whose samples hold "Color:Red, Size:M"
- **THEN** the proposal gives that column the list separator "," and the item separator ":"

#### Scenario: Images separated by a pipe

- **WHEN** the AI analyzes an image column whose samples hold "https://cdn.example/1.jpg|https://cdn.example/2.jpg"
- **THEN** the proposal gives that column the list separator "|"

### Requirement: The AI chooses units from the store's units

When a sheet has a weight column, the answer MUST give the weight unit as one of the store's weight units. When it has a length, width, or height column, the answer MUST give the length unit as one of the store's length units. A unit MAY be null only when the sheet has no such column. The system MUST NOT translate other spellings of a unit into one of the store's units.

#### Scenario: Unit in the header

- **WHEN** the AI analyzes a sheet with the header "Weight (g)" in a store whose weight units include g
- **THEN** the proposal gives the weight unit g

#### Scenario: Sheet without dimensions

- **WHEN** the AI analyzes a sheet that has a weight column and no length, width, or height column
- **THEN** the proposal gives a weight unit and no length unit

### Requirement: The answer is checked before use

The system MUST reject an answer as unusable when it names a column that does not exist, uses a target or language outside the allowed lists, gives one single-value target to two columns, groups by a missing column, groups by parent without a parent reference column, or has an option name or value column without a pair number or a pair without its value column. An answer without a product name column and without a SKU column MUST be rejected as having no product data.

#### Scenario: Two SKU columns

- **WHEN** the AI marks columns 2 and 3 as the variant SKU
- **THEN** the sheet's analysis fails as unusable and the message says that two columns were marked as SKU

#### Scenario: No product data

- **WHEN** the AI maps no column to the product name and none to the SKU
- **THEN** the sheet's analysis fails with the no-product-data message

#### Scenario: Option name without its value

- **WHEN** the AI maps "Attribute 1 name" to an option name with pair 1 and maps no column to the option value of pair 1
- **THEN** the sheet's analysis fails as unusable and the message names pair 1

### Requirement: Column formats from the AI are checked

A number format whose decimal and thousands separators are the same, and a date format that holds anything but date format characters and separators, MUST be dropped, leaving that column without a format and the rest of the answer in use. A column with a dropped format, and a column whose text samples do not all read in its format, MUST be highlighted for review.

#### Scenario: Same separator twice

- **WHEN** the AI gives a price column a comma as both the decimal and the thousands separator
- **THEN** the proposal is used, and the price column has no number format until staff choose one

#### Scenario: Samples that do not fit

- **WHEN** the AI gives the column "Greutate" a decimal dot without thousands separator and one of its samples is "1,25"
- **THEN** the column's format is kept and the column is highlighted for review

### Requirement: Separators and units from the AI are checked

A separator that is longer than three characters or holds a letter or digit, and a unit that is not one of the store's units, MUST be dropped, leaving the rest of the answer in use. A column with a dropped separator MUST be highlighted for review.

#### Scenario: Unusable separator

- **WHEN** the AI gives a tags column the list separator "and"
- **THEN** the proposal is used, the tags column has no list separator until staff choose one, and the column is highlighted for review

#### Scenario: Unit the store does not have

- **WHEN** the AI gives the weight unit "grams" and the store's weight units are kg, g, and lbs
- **THEN** the proposal is used and the sheet has no weight unit until staff choose one

### Requirement: The AI describes each column's conventions

For a column mapped to the status, stock status, backorders, shippable, or row type target, the answer MUST give the meaning of each code that the samples show or that such a column is known to hold. For a column of packed list items whose items hold more than a name and a value, it MUST give the parts. For an image column whose URLs point at resized copies, it MUST give the size suffix.

#### Scenario: Status codes

- **WHEN** the AI analyzes a "Published" column whose samples are "1", "0", "-1", and "2"
- **THEN** the proposal gives "1" the meaning published, and "0", "-1", and "2" the meaning draft

#### Scenario: Packed names with types and positions

- **WHEN** the AI analyzes an option name column whose samples hold "Size:select:0"
- **THEN** the proposal describes its parts as name, dropped, dropped

#### Scenario: Thumbnail URLs

- **WHEN** the AI analyzes an image column whose samples end in "12-small_default.jpg"
- **THEN** the proposal gives the column the size suffix "-small_default"

### Requirement: Conventions from the AI are checked

A code meaning that does not fit the column's target, item parts with more than one name or value part, and an image size suffix with characters other than letters, digits, "-", and "_" MUST be dropped, leaving the rest of the answer in use. A column with a dropped convention MUST be highlighted for review.

#### Scenario: Meaning for another field

- **WHEN** the AI gives the backorders column the meaning published for "1"
- **THEN** that meaning is dropped, the proposal is used, and the backorders column is highlighted for review

#### Scenario: Unsafe suffix

- **WHEN** the AI gives an image column the size suffix "../thumbs"
- **THEN** the suffix is dropped and the image URLs are used as they are

### Requirement: The proposal prefills the mapping by confidence

A valid proposal MUST prefill the sheet's mapping. A column target with confidence of at least the preselect threshold (default 0.60) MUST be selected. A lower one MUST be left unmapped, with the suggestion still visible. A selected target below the review threshold (default 0.85) MUST be highlighted for review.

#### Scenario: Confidence levels

- **WHEN** the proposal gives the name column 0.96, the group column 0.74, and a notes column "ignore" with 0.40
- **THEN** the name is selected without highlight, the group column is selected and highlighted, and the notes column is unmapped

### Requirement: The proposal prefills the formats and the conventions

A valid proposal MUST prefill each column's number or date format, separators, code meanings, item parts, and image size suffix, and the sheet's weight and length units. A proposal MUST NOT change the import options or another sheet's mapping.

#### Scenario: Formats and meanings prefilled

- **WHEN** the proposal gives "Regular price" a decimal comma and gives the code "2" of "Published" the meaning draft
- **THEN** the mapping step shows "Regular price" with a decimal comma and the code "2" of "Published" as draft

#### Scenario: Two sheets with different separators

- **WHEN** the proposal for "Produse" gives its category column "," and ">", the proposal for "Outlet" gives its category column "|" and "/", and "Outlet" is analyzed first
- **THEN** each category column keeps the separators of its own sheet's proposal

### Requirement: Proposals are kept for audit

For each analyzed sheet, the system MUST store the AI's raw answer, the number of attempts, and the last error. The import MUST record the provider and model that produced the proposals.

#### Scenario: Audit after mapping changes

- **WHEN** staff change three targets that the AI proposed and continue
- **THEN** the sheet still holds the original proposal next to the changed mapping

### Requirement: The provider comes from configuration

The provider, the model, an optional own API key, and an optional base URL MUST come from environment configuration. An empty model MUST mean the provider's default model. An empty own key MUST fall back to that provider's key in the AI configuration. Staff MUST NOT be able to change these in the admin. Import pages MUST show the provider and model in use.

#### Scenario: Provider shown

- **WHEN** the configuration sets the provider "gemini" and no model
- **THEN** the import pages show the AI line "gemini" with the provider's default model

#### Scenario: Own key for imports

- **WHEN** the configuration sets an own API key for imports
- **THEN** analysis requests use that key and the AI assistant chat keeps its own key

### Requirement: Uploads are blocked while the AI is not configured

When no provider is set, or the provider needs an API key and none is available, the upload form MUST show that AI analysis is not configured and MUST NOT accept a file.

#### Scenario: Missing key

- **WHEN** the provider is "openai" and neither an own key nor the OpenAI key is set
- **THEN** the upload form shows "AI analysis is not configured. The provider or API key is missing. Contact your developer." and the upload is disabled

### Requirement: Temporary failures are retried automatically

When a request times out, cannot connect, or the provider answers with a rate limit or a server error, the system MUST retry that sheet's analysis up to 3 attempts in total with increasing delays before reporting a failure. Rejected credentials and unusable answers MUST be reported without automatic retries.

#### Scenario: One timeout

- **WHEN** the first request for a sheet times out and the second succeeds
- **THEN** the sheet shows the proposal and no error

#### Scenario: Rejected key

- **WHEN** the provider answers 401 for a sheet
- **THEN** the sheet's analysis fails after one attempt

### Requirement: Failures are reported per sheet with a retry

A failed sheet MUST show an understandable message, the error code, the attempt count, and a **Retry** action. Retry MUST analyze only that sheet and keep other sheets' results. Messages MUST NOT contain API keys or raw provider responses. The raw error MUST be logged and stored on the sheet.

#### Scenario: Provider timeout after all attempts

- **WHEN** all three attempts for the sheet "Produse" time out
- **THEN** that sheet shows "The AI provider did not respond. This is usually temporary. Try again.", code `provider_timeout`, attempt 3, and **Retry**, and the sheet "Outlet" keeps its proposal

#### Scenario: Retry succeeds

- **WHEN** staff click **Retry** on a failed sheet and the provider answers with a valid proposal
- **THEN** the sheet shows the proposal and the import can continue once every included sheet has a mapping

#### Scenario: Message per failure type

- **WHEN** the analysis fails because the key was rejected, the quota is used up, or the answer is unusable
- **THEN** the sheet shows respectively "The AI provider rejected the API key. The configuration needs to be checked.", "The AI quota is used up or there are too many requests. Try again in a few minutes.", or "The AI answer could not be used: <reason>. Try again."

### Requirement: Mapping cannot continue past an unmapped sheet

Staff MUST NOT be able to continue from mapping to staging while any included sheet has neither a valid proposal, an applied import profile, nor a mapping staff made by hand. A failed analysis MUST NOT keep staff from mapping the sheet by hand: the sheet MUST start with the product type the analysis would have used (the first one), so the target list offers that type's attributes, and once staff map its columns, the import MUST be able to continue with that mapping, even though the import's status stays "analysis failed" until then. Skipping the failed sheet MUST also allow continuing.

#### Scenario: One sheet failed

- **WHEN** the sheet "Produse" failed and the sheet "Outlet" has a proposal
- **THEN** the continue action is disabled with "The sheet "Produse" has no mapping yet. Map its columns, retry its analysis, apply a profile, or skip the sheet." until "Produse" is retried successfully, mapped by hand, or skipped

#### Scenario: Failed sheet mapped by hand

- **WHEN** the analysis of "Produse" failed, and staff open its tab and map "Cod" to variant SKU and "Denumire" to product name
- **THEN** the product type is already the first one, the target list offers its attributes such as "Details › Short description", the continue action is enabled, and staging uses the staff's mapping
