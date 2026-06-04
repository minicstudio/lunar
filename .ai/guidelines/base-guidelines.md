# Agent Instructions

## Before Starting Any Task

Read `README.md`.

Use this document to understand the system before analyzing the code.

If documentation conflicts with implementation, the codebase is the source of truth.

## Documentation Rules

- Repository code is the source of truth.
- Documentation may be incomplete or outdated. If so, suggest documentation updates.
- Verify critical assumptions in code.
- Do not assume upstream Lunar PHP behavior.
- Prefer existing patterns over introducing new ones.
- Match existing architecture and conventions.

## This Repository

- This repository is a fork of Lunar PHP.
- It provides the ecommerce engine and admin functionality.
- The frontend webshop is implemented in a separate package: lunar-frontend.
- Upstream Lunar updates are periodically merged.
- Customizations may override standard Lunar behavior.

## After finishing an implementation

- Changelog entries are maintained in `docs/changelog`, with a separate file for each day. Follow the conventions documented in `docs/changelog/CHANGELOG.md`.

### After completing a task:
  - Add an entry describing the implemented changes.
  - Keep entries concise and focused on what changed, not how it was implemented.
  - Include all significant functional, architectural, API, database, integration, or behavior changes.
  - Do not include trivial refactors, formatting changes, comments, or other non-functional modifications unless they affect maintainability in a meaningful way.
  - Group related changes into a single entry when appropriate.
  - If a change affects existing behavior, clearly state what changed.

### Good example:

- Added Mailchimp audience synchronization for customer registrations and profile updates.
- Introduced SmartBill invoice generation after successful order payment.
- Added support for Sameday locker delivery and locker synchronization.

### Bad example:

- Updated MailchimpService.
- Refactored order processing.
- Fixed some bugs.

## Documentation Maintenance

When implementing changes:

- Update any affected documentation.
- If documentation becomes inaccurate because of your changes, update it as part of the task.
- Do not create new documentation unless requested.