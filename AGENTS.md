<base-guidelines>
# Agent Instructions

## Before Starting Any Task

Read:

1. `docs/system/PROJECT_SPECIFICATION.md`
2. `docs/system/CODE_MAP.md`

Use these documents to understand the system before analyzing the code.

If documentation conflicts with implementation, the codebase is the source of truth.

## Additional Documentation

Read when working in the matching domain. Design docs describe **engine behavior in this repo only**; storefront UI and host orchestration live in `lunar-frontend`.

### System

- `docs/system/CHANGELOG.md` — recorded functional and architectural changes

### Design

- `docs/design/checkout.md` — cart, sessions, shipping resolution, order creation, payment infrastructure
- `docs/design/order_processing.md` — placement, status transitions, AWB, ERP invoicing, admin order handling
- `docs/design/pricing_and_discounts.md` — price resolution, discounts, coupons, cart totals

### Database

- `docs/database/schema.dbml` — schema reference (not runtime behavior)

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

- A changelog is maintained at `docs/system/CHANGELOG.md`.

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

</base-guidelines>

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3

## Skills Activation

This project has domain-specific skills available in `.ai/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

</laravel-boost-guidelines>

<behavioral-guidelines>
Behavioral guidelines to reduce common LLM coding mistakes. Merge with project-specific instructions as needed.

**Tradeoff:** These guidelines bias toward caution over speed. For trivial tasks, use judgment.

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

---

**These guidelines are working if:** fewer unnecessary changes in diffs, fewer rewrites due to overcomplication, and clarifying questions come before implementation rather than after mistakes.

## 5. Respect Existing Architecture

Before introducing a new service, action, trait, abstraction, pattern, or dependency:

- Search the codebase for an existing implementation.
- Prefer extending existing patterns over introducing new ones.
- If a similar solution already exists, explain why it cannot be reused before creating a new one.

The goal is consistency over personal preference.

## 6. Fork Awareness

This repository is a fork of Lunar PHP.

- Do not assume upstream Lunar behavior.
- Verify behavior in the current codebase.
- When modifying core functionality, identify whether the code originates from upstream Lunar or is custom project logic.
- Avoid changes that make future upstream merges unnecessarily difficult.

</behavioral-guidelines>