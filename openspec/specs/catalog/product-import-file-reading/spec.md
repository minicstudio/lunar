# product-import-file-reading Specification

## Purpose

Defines which files the product import accepts and how it reads them: several files per import, sheets, header rows, CSV delimiters and encodings, cell values and their kinds, and the column profile that mapping and AI analysis build on.

## Requirements

### Requirement: XLSX, XLS, and CSV files are accepted

The upload MUST accept one or more files with the extensions `.xlsx`, `.xls`, and `.csv`, each up to a configurable size (default 20 MB). It MUST reject the whole upload before creating an import when a file has another extension or is larger, with a message that names the file and the allowed formats or the size limit. Uploaded files MUST be stored on a private disk.

#### Scenario: Supported file

- **WHEN** staff upload a 2 MB `.xlsx` file
- **THEN** the system creates an import with status uploaded and starts reading it

#### Scenario: Two CSV files

- **WHEN** staff upload "products_import.csv" and "combinations_import.csv" together
- **THEN** the system creates one import whose sheets are "products_import.csv" and "combinations_import.csv"

#### Scenario: Unsupported format

- **WHEN** staff upload a `.pdf` file
- **THEN** the upload is rejected with a message listing XLSX, XLS, and CSV, and no import is created

#### Scenario: File too large

- **WHEN** staff upload a 25 MB `.csv` file with the default limit
- **THEN** the upload is rejected with a message naming the 20 MB limit, and no import is created

### Requirement: Imports have a row limit

When the non-skipped sheets of an import's files together hold more data rows than the configured limit (default 20,000), the import MUST become failed with a message that names the limit. The catalog MUST stay unchanged.

#### Scenario: Too many rows

- **WHEN** staff upload a file with 25,000 data rows with the default limit
- **THEN** the import becomes failed and its message names the 20,000 row limit

### Requirement: Every sheet is read and can be skipped

The system MUST read every sheet of every XLSX or XLS file and show each with its name and data row count. A CSV file MUST count as one sheet named after the file. When an import has several files, a workbook sheet's name MUST also show its file's name. Sheets without data rows MUST be skipped automatically. Staff MUST be able to skip any sheet and include it again before staging. Skipped sheets MUST NOT be analyzed, staged, or imported.

#### Scenario: Workbook with three sheets

- **WHEN** staff upload a workbook with the sheets "Produse" (128 rows), "Outlet" (40 rows), and "Info" (no data rows)
- **THEN** the import shows "Produse" and "Outlet" as included and "Info" as skipped

#### Scenario: Staff skip a sheet

- **WHEN** staff skip the sheet "Outlet" and continue
- **THEN** no row of "Outlet" is staged, validated, or imported

### Requirement: The header row is detected and can be changed

For each sheet, the system MUST detect the header row among the first 10 non-empty rows. Rows above the header row MUST be ignored. Staff MUST be able to choose another header row. A new header row MUST rebuild that sheet's column profile and request a new analysis for that sheet only.

#### Scenario: Title rows above the header

- **WHEN** a sheet starts with a title row and an empty row, and the column names are in row 3
- **THEN** the system uses row 3 as the header and treats row 4 onward as data

#### Scenario: Staff change the header row

- **WHEN** staff set the header row of one sheet from 1 to 2
- **THEN** that sheet's columns and profile are rebuilt from row 2, its analysis runs again, and other sheets keep their results

### Requirement: Row numbers match the file

Every staged row MUST keep the row number it has in the source sheet. Every message about a row MUST name the sheet and that row number. Completely empty rows MUST be ignored and MUST NOT shift the numbers of later rows.

#### Scenario: Empty row in the middle

- **WHEN** row 10 of a sheet is empty and row 11 holds a product with a missing price
- **THEN** the price error names that sheet and row 11

### Requirement: The CSV delimiter is detected

For CSV files, the system MUST detect the delimiter among comma, semicolon, and tab, and MUST strip a UTF-8 byte order mark.

#### Scenario: Semicolon CSV from Excel

- **WHEN** staff upload a CSV that uses semicolons and decimal commas
- **THEN** the columns are split on semicolons and the decimal commas stay inside their cells

### Requirement: The CSV encoding is chosen at upload

The upload form MUST offer an optional encoding for CSV files: UTF-8 (the default), Windows-1250, Windows-1252, ISO-8859-2, or ISO-8859-16. It MUST apply to every CSV file of the upload, be stored with the files, and MUST NOT come from configuration. Valid UTF-8 input MUST be read as UTF-8, and other input converted from the chosen encoding. A non-UTF-8 CSV without a chosen encoding MUST be rejected before an import is created, naming the file.

#### Scenario: Windows-1250 file

- **WHEN** staff upload a Windows-1250 CSV containing "Rochie roşie" and "Hosszú ujjú póló" and choose Windows-1250
- **THEN** the staged values read "Rochie roşie" and "Hosszú ujjú póló" in UTF-8

#### Scenario: Two files with different encodings

- **WHEN** staff upload a Windows-1250 CSV with Windows-1250 chosen, and then an ISO-8859-16 CSV containing "Rochie roșie" with ISO-8859-16 chosen
- **THEN** each import reads its own values correctly

#### Scenario: Encoding missing

- **WHEN** staff upload a Windows-1250 CSV containing "Hosszú ujjú póló" without choosing an encoding
- **THEN** the upload is rejected with a message asking for the file's encoding, and no import is created

#### Scenario: UTF-8 file with an encoding chosen

- **WHEN** staff upload a UTF-8 CSV and choose Windows-1250
- **THEN** the values are read as UTF-8, unchanged

### Requirement: Cell values are read without losing data

The system MUST store each data row's original cells unchanged as staged raw values. Numeric cells MUST keep all their digits, without exponent notation. Formula cells MUST be read as their calculated values. Date cells MUST be read as ISO 8601 dates. Leading zeros in text cells MUST be kept.

#### Scenario: EAN stored as a number

- **WHEN** an XLSX cell holds the number 5941234567890
- **THEN** the raw value is "5941234567890"

#### Scenario: Code with leading zeros

- **WHEN** a text cell holds "000123"
- **THEN** the raw value is "000123"

#### Scenario: Row shorter than the header

- **WHEN** an export's header has 60 columns and an early row ends after 52 cells
- **THEN** that row reads as empty in columns 53 to 60

### Requirement: Cells stored as numbers or dates keep their kind

The system MUST record which cells the spreadsheet itself stores as numbers or dates. Those cells MUST be read as a number with a decimal dot or as an ISO 8601 date, whatever number or date format staff set for their column. Every CSV cell MUST count as text.

#### Scenario: Mixed price column in a workbook

- **WHEN** an XLSX price column holds the number cell 1256.59 and the text cell "49,90", and the column's format has a decimal comma
- **THEN** the prices read as 1256.59 and 49.90

### Requirement: Each column gets a profile

For each column of each included sheet, the system MUST compute over all data rows: the header text, the share of filled cells, the share of distinct values among filled cells, and up to 30 distinct samples of at most 100 characters, taken from text cells when the column has any. The profile MUST NOT give the column a type or say which decimal separator it uses: the AI reads what a column holds from its header and samples. Columns without a header MUST get a positional name.

#### Scenario: Price column with decimal commas

- **WHEN** a column holds "49,90", "129,00", and "15,5" in every row
- **THEN** its profile shows a fill rate of 100% and the samples "49,90", "129,00", and "15,5", without a type or a decimal separator

#### Scenario: Weight with a unit in the cells

- **WHEN** a weight column holds values such as "1,25 kg", and some cells hold "N/A"
- **THEN** its profile keeps up to 30 of those values as samples, like any other column

#### Scenario: Group column

- **WHEN** a 128-row column holds 34 distinct parent codes
- **THEN** its profile shows a fill rate of 100% and a distinct share of about 27%

#### Scenario: Column without a header

- **WHEN** the header cell of the fifth column is empty but the column has data
- **THEN** the column is listed as "Column 5" with its profile
