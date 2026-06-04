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
  <type>: <task-number> <short summary>
  ```

  Examples:

  ```
  feat: LFP-678 add Algolia product filtering
  fix: LFP-712 prevent duplicate GTM events
  refactor: LFP-643 simplify pagination visibility logic
  test: LFP-701 add coverage for checkout validation
  docs: LFP-655 update installation instructions
  chore: LFP-689 remove unused configuration
  ```

### Task Number

- Always include the task number when available.
- Prefer extracting the task number from:
  - The current branch name
  - The issue or ticket referenced by the user
- If no task number can be determined automatically, ask the user for it before generating the commit message.

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
fix: LFP-678 prevent checkout submission without shipping method
```

```text
feat: LFP-532 add Mailchimp subscriber tag synchronization
```

```text
refactor: LFP-834 extract GTM event dispatching into reusable trait
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
