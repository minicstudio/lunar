# Fix Bug

Use this command when investigating or fixing an existing bug.

## Initial Investigation

Before making any code changes:

1. Read the relevant documentation and skills for the affected domain.
2. Understand the reported behavior.
3. Identify the expected behavior.

## Production Awareness

Assume the bug was observed in a production environment.

Do not assume that the issue can be reproduced locally.

Consider differences such as:

* Environment configuration
* Database contents
* Queue workers
* Scheduled tasks
* Caching
* Third-party integrations
* Data inconsistencies
* Race conditions
* Concurrency issues

## Reproduction

Before implementing a fix:

* Determine whether the issue can be reproduced locally.
* Identify the exact steps required to reproduce it.
* Verify that the observed behavior matches the reported issue.

If reproduction steps are unclear:

* Investigate the codebase.
* Review available logs, stack traces, screenshots, or bug reports.
* Ask the user specific questions required to reproduce the issue.

Do not guess.

## Root Cause Analysis

Before implementing a fix:

* Identify the root cause.
* Explain why the issue occurs.
* Verify that the proposed fix addresses the root cause rather than only the symptoms.

Avoid speculative fixes.

## Implementation

When implementing the fix:

* Make the smallest change necessary.
* Follow existing architecture and patterns.
* Reuse existing services, actions, helpers, and abstractions.
* Do not introduce new architecture unless required.

## Testing

* Add or update regression tests whenever practical.
* Follow the Pest Testing skill.
* Verify fork-specific behavior where applicable.
* Run the narrowest relevant test suite first.

## Documentation

If the fix changes behavior:

* Update affected documentation.
* Update changelog. Changelog entries are maintained in `docs/changelog`, with a separate file for each day. Follow the conventions documented in `docs/changelog/CHANGELOG.md`.

## When Information Is Missing

Stop and ask questions if any of the following are unclear:

* How the issue is triggered
* Expected behavior
* Actual behavior
* Environment-specific conditions
* Required business rules
* Missing logs or error messages

Do not make assumptions when critical information is missing.
