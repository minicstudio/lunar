# Commit, Push & PR Workflow

## Purpose

Review the staged changes, generate an appropriate commit message, create the commit, push the current branch, and open a pull request against the repository's `main` branch.

## Requirements

This workflow requires access to:

- Git CLI
- Repository remotes (`origin`, optionally `upstream`)
- Atlassian / Bitbucket tooling capable of creating pull requests

If pull request creation is unavailable, complete the commit and push steps and provide the user with the branch name and instructions for creating the pull request manually.

## Environment Validation

Before attempting to create a pull request:

1. Verify that the GitHub CLI is installed:

   ```bash
   gh --version
   ```

2. Verify that GitHub authentication is configured:

   ```bash
   gh auth status
   ```

3. Verify that the current repository is accessible:

   ```bash
   gh repo view
   ```

If any of the above checks fail:

- Complete the commit and push steps.
- Do not attempt to create a pull request.
- Explain why pull request creation is unavailable.
- Provide instructions for configuring GitHub CLI manually.

## Instructions

Before creating a commit:

1. Review all staged changes.
2. Understand the purpose of the modification.
3. Group related changes into a single logical change.
4. Identify:

   * What changed
   * Why it changed
   * Any user-visible impact
   * Any technical debt, refactoring, or bug fix involved

## Commit Message Rules

Use Conventional Commit format whenever applicable:

```text
<type>: <short summary>
```

Examples:

```text
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

## Safety Checks

Before committing:

1. Verify that all intended changes are staged.
2. If multiple unrelated changes are detected, suggest splitting them into separate commits.
3. If the purpose of a change is unclear, ask for clarification instead of guessing.

## Commit Workflow

After generating the commit message:

1. Create the git commit.
2. Determine the current branch.

### Main Branch Protection

If the current branch is:

```text
main
master
```

DO NOT:

* Push changes automatically.
* Create a pull request.

Instead:

* Stop and explain that commits on the main branch require manual review.
* Ask the user whether they want to continue manually.

### Feature Branch Workflow

If the current branch is not `main` or `master`:

1. Commit the staged changes.
2. Push the current branch to `origin`.
3. Create a pull request:

   * Source branch: current branch
   * Target branch: `main`
   * Remote: `origin`
4. Use the commit message as the pull request title unless a better title is required.
5. Generate a concise pull request description summarizing:

   * What changed
   * Why it changed
   * Any testing performed

## Pull Request Rules

* Create the pull request against the repository hosted on `origin`.
* Never create pull requests against `upstream`.
* Never merge the pull request automatically.
* Never delete branches automatically.

## Output

Return:

```text
Commit created: <commit hash>

Branch pushed:
<branch name>

Pull request:
<pull request url>
```

Or explain why the workflow was stopped.
