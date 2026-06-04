# Commit Message Generator

## Purpose

Generate a clear, concise, and meaningful git commit message based on the current staged changes.

## Instructions

Before writing the commit message:

1. Review all staged changes.
2. Understand the purpose of the modification.
3. Group related changes into a single logical change.
4. Identify:

   * What changed
   * Why it changed
   * Any user-visible impact
   * Any technical debt, refactoring, or bug fix involved

## Commit Message Rules

* Use Conventional Commit format whenever applicable:

  ```
  <type>: <short summary>
  ```

  Examples:

  ```
  feat: add Algolia product filtering
  fix: prevent duplicate GTM events
  refactor: simplify pagination visibility logic
  test: add coverage for checkout validation
  docs: update installation instructions
  chore: remove unused configuration
  ```

* Keep the subject line under 72 characters.

* Use imperative mood.

* Do not end the subject line with a period.

* Explain the intent, not implementation details.

* Avoid vague messages such as:

  * update code
  * fixes
  * improvements
  * cleanup
  * changes

## Output Format

Return only:

```text
<commit message>
```

Or when additional context is valuable:

```text
<commit message>

- reason 1
- reason 2
```

## Examples

Good:

```text
fix: prevent checkout submission without shipping method
```

```text
feat: add Mailchimp subscriber tag synchronization
```

```text
refactor: extract GTM event dispatching into reusable trait
```

Bad:

```text
fix bug
```

```text
updates
```

```text
working on checkout
```

## Additional Guidance

* Prefer a specific commit type over `chore`.
* If multiple unrelated changes exist, suggest splitting them into separate commits.
* If the purpose of a change is unclear, ask for clarification instead of guessing.
