# Changelog

This file documents **how** changelogs are maintained. It is not the changelog itself.

## Daily files

Create **one markdown file per calendar day** under `docs/changelog/`.

| Rule | Detail |
| --- | --- |
| **Location** | `docs/changelog/` |
| **Filename** | `YYYY-MM-DD.md` (ISO date, e.g. `2026-06-04.md`) |
| **New day** | Create a new file when the first notable change of that day is recorded |
| **Same day** | Append to that day’s file; do not create a second file for the same date |

Example layout:

```text
docs/changelog/
  CHANGELOG.md          ← conventions (this file)
  2026-06-03.md         ← changes on 3 June 2026
  2026-06-04.md         ← changes on 4 June 2026
```

## When to add an entry

Add or update the **current day’s** file when you finish work that is worth tracking, including:

- User-facing or integration-impacting behavior
- Functional, architectural, API, database, or config changes
- Notable fixes or regressions

Skip trivial-only work (formatting, comments, renames with no behavior change) unless it materially affects maintainability.

## Entry format

Each daily file should start with a level-2 heading for the date, then bullet entries:

```markdown
## 2026-06-04

- Added Mailchimp audience sync for customer profile updates.
- Fixed AWB generation when locker shipping is selected.
```

Guidelines for bullets:

- **Concise** — what changed, not step-by-step implementation
- **Outcome-focused** — prefer “Added X” / “Fixed Y” over class or file names alone
- **Grouped** — related changes can share one bullet
- **Breaking or behavior changes** — state what changed for consumers of the engine or host app

### Good examples

- Added Mailchimp audience synchronization for customer registrations and profile updates.
- Introduced Smartbill invoice generation after successful order payment.
- Added support for Sameday locker delivery and locker synchronization.

### Bad examples

- Updated `MailchimpService`.
- Refactored order processing.
- Fixed some bugs.

Optional: link to a PR or issue at the end of a bullet when helpful.

## Who updates it

Developers and AI agents should update the daily file **as part of the same task** that introduced the change (see `.ai/guidelines/base-guidelines.md`, `commands/bug-fix.md`, `commands/review.md`).
