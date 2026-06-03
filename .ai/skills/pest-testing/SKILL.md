# Pest Testing

Activate this skill when:

* Writing new tests
* Modifying existing tests
* Fixing failing tests

## Before You Start

1. Read `docs/system/TESTING.md`.
2. For business-rule changes, also read the relevant design documentation:

   * `docs/design/checkout.md`
   * `docs/design/order_processing.md`
   * `docs/design/pricing_and_discounts.md`
3. Locate an existing test in the same package and follow its structure.
4. Reuse the existing `TestCase`, factories, helpers, and assertion style.

## Testing Principles

* Test behavior, not implementation details.
* Prefer regression tests for bug fixes.
* Reuse existing testing patterns before introducing new ones.
* Prefer extending existing test files over creating new ones.
* Run the narrowest relevant test suite before running the full test suite.

## Repository Rules

* Use Pest for all tests.
* Follow the conventions documented in `docs/system/TESTING.md`.
* Verify fork-specific behavior.
* Do not assume upstream Lunar PHP tests or behavior apply to this repository.
* If modifying custom Lunar behavior, ensure that behavior is covered by tests.

## External Integrations

Never call real external services from automated tests.

Mock or fake external dependencies, including:

* Mailchimp
* SmartBill
* Magister
* Sameday
* DPD
* Stripe
* Algolia

Verify:

* Generated payloads
* Dispatched jobs
* Fired events
* Persisted state

Do not verify behavior through real API responses.

## Test Maintenance

* Add regression tests in the same change set as bug fixes whenever practical.
* If adding a new top-level test directory, register a corresponding testsuite in `phpunit.xml`.
* If a new testsuite is added, update `.github/workflows/tests.yml`.
* Do not create standalone verification scripts when Pest coverage is sufficient.
