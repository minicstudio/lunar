<laravel-boost-guidelines>
=== .ai/base-guidelines rules ===

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

=== .ai/behavioral-guidelines rules ===

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

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

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

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

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
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== lunarphp/nestedset/core rules ===

## Nested Set (lunarphp/nestedset)

Store and query hierarchical data (menus, categories, org charts) in a relational
table using the Nested Set Model. Reads (ancestors, descendants, subtrees) are very
fast; writes renumber the tree and are comparatively expensive — best for trees that
change rarely. Use a transaction-capable engine (InnoDB / PostgreSQL).

### Critical rules

- Structural changes are **deferred until `save()`**. Wrap any move/insert in a DB
  transaction yourself — one is **not** started automatically.
- **Never** delete nodes with a query builder (`Model::where(...)->delete()`) — it
  corrupts the tree. Always delete the model instance: `$node->delete()` (descendants
  cascade).
- With scoped trees, custom/static queries **must** go through `Model::scoped([...])`,
  or you will read/write nodes from other scopes.

### Schema & model

Add the columns in a migration, then add the trait to the model.

<code-snippet name="Migration columns" lang="php">
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->nestedSet(); // adds _lft, _rgt, parent_id
});

// To drop them:
Schema::table('categories', fn (Blueprint $table) => $table->dropNestedSet());
</code-snippet>

<code-snippet name="Model" lang="php">
use Illuminate\Database\Eloquent\Model;
use Lunar\Nestedset\NodeTrait;

class Category extends Model
{
    use NodeTrait;
}
</code-snippet>

The trait adds the `parent`, `children`, `ancestors`, and `descendants` relations
(all eager-loadable).

### Inserting & moving nodes

<code-snippet name="Create and move nodes (wrap in a transaction)" lang="php">
DB::transaction(function () use ($parent, $neighbor) {
    // Root (no parent) — appended to the end of the tree
    Category::create(['name' => 'Root']);

    // Child of $parent
    Category::create(['name' => 'Child'], $parent);     // static helper
    $node->appendToNode($parent)->save();               // deferred, last child
    $node->prependToNode($parent)->save();              // deferred, first child
    $parent->appendNode($node);                          // implicit save

    // As a sibling of $neighbor
    $node->afterNode($neighbor)->save();
    $node->beforeNode($neighbor)->save();
    $node->insertAfterNode($neighbor);                   // implicit save

    // As a root
    $node->saveAsRoot();

    // A successful save() does not guarantee the node moved:
    if ($node->save()) {
        $moved = $node->hasMoved();
    }
});
</code-snippet>

Build or restructure whole trees from arrays with `Category::create([... 'children' => [...]])`,
`Category::rebuildTree($data, $delete)`, or `Category::rebuildSubtree($root, $data)`.

### Retrieving nodes

<code-snippet name="Querying the tree" lang="php">
// Ancestors / descendants of a key (use defaultOrder() for hierarchical order)
Category::defaultOrder()->ancestorsOf($id);     // also ancestorsAndSelf($id)
Category::descendantsOf($id);                    // also descendantsAndSelf($id)

// Depth and ordering
Category::withDepth()->find($id)->depth;         // root = 0
Category::defaultOrder()->get();                 // hierarchical order (reversed() to flip)
$node->up(); $node->down(2);                      // shift among siblings

// Constraints
Category::whereDescendantOf($node)->get();       // also whereAncestorOf, whereIsRoot, whereIsLeaf
Category::whereDescendantOrSelf($node)->get();

// Siblings
$node->getSiblings();
$node->getNextSibling();
$node->getPrevSiblings();

// Build an in-memory tree (fills parent/children on each node)
$tree = Category::get()->toTree();               // or ->toFlatTree()
$subtree = Category::descendantsAndSelf($rootId)->toTree()->first();
</code-snippet>

### Deleting

<code-snippet name="Delete a node (and its descendants)" lang="php">
$node->delete();                       // descendants are deleted too
// Category::where('id', $id)->delete(); // DON'T — corrupts the tree
</code-snippet>

`SoftDeletes` is supported.

### Scoping (multiple trees in one table)

When one table holds several independent trees (e.g. `MenuItem` keyed by `menu_id`),
declare the scope attributes and always query through `scoped()`.

<code-snippet name="Scoped trees" lang="php">
class MenuItem extends Model
{
    use NodeTrait;

    protected function getScopeAttributes(): array
    {
        return ['menu_id'];
    }
}

MenuItem::scoped(['menu_id' => 5])->withDepth()->get();   // OK
MenuItem::scoped(['menu_id' => 5])->fixTree();            // OK
MenuItem::descendantsOf($id)->get();                      // WRONG — leaks other scopes

// Queries from a loaded instance are scoped automatically:
MenuItem::findOrFail($id)->siblings()->get();             // OK
</code-snippet>

### Checking & fixing consistency

<code-snippet name="Consistency" lang="php">
Category::isBroken();        // bool
Category::countErrors();     // ['oddness', 'duplicates', 'wrong_parent', 'missing_parent']
Category::fixTree();         // rebuild _lft/_rgt from parent_id (fixSubtree($root) to constrain)
</code-snippet>

=== spatie/laravel-medialibrary/core rules ===

## Media Library

- `spatie/laravel-medialibrary` associates files with Eloquent models, with support for collections, conversions, and responsive images.
- Always activate the `medialibrary-development` skill when working with media uploads, conversions, collections, responsive images, or any code that uses the `HasMedia` interface or `InteractsWithMedia` trait.

=== spatie/guidelines-skills/core rules ===

# Project Coding Guidelines

- This codebase follows Spatie's coding guidelines.
- Always activate the `spatie-laravel-php` skill when writing, editing, reviewing, or formatting Laravel or PHP code.
- Always activate the `spatie-javascript` skill when writing, editing, reviewing, or formatting JavaScript or TypeScript code.
- Always activate the `spatie-version-control` skill when creating commits, branches, or managing Git operations.
- Always activate the `spatie-security` skill when configuring security, signing commits, reviewing authentication, or setting up servers and databases.

</laravel-boost-guidelines>
