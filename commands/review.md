# Review Changes

Review the current changes before they are finalized.

## Preparation

1. Read any relevant documentation for the affected domain.
2. Understand the purpose of the change.

## Review Checklist

### Correctness

Verify:

* The implementation satisfies the requested change.
* The implementation addresses the root cause (for bug fixes).
* No obvious edge cases were introduced.
* Existing behavior is preserved unless intentionally changed.

### Architecture

Verify:

* Existing architecture and patterns are respected.
* Existing services, actions, traits, and abstractions are reused where appropriate.
* No unnecessary abstractions were introduced.
* No unnecessary dependencies were added.
* The change is proportional to the problem being solved.

### Lunar Fork Awareness

Verify:

* The implementation works with this repository's custom Lunar behavior.
* Upstream Lunar assumptions were not introduced without verification.
* Custom fork behavior remains intact.
* Future upstream merges are not made unnecessarily difficult.

### Testing

Verify:

* Appropriate tests exist.
* Regression tests were added for bug fixes when practical.
* Existing test conventions are followed.
* External services are properly mocked or faked.

### Documentation

Verify:

* Documentation remains accurate.
* Relevant documentation was updated if behavior changed.
* `docs/system/CHANGELOG.md` was updated when appropriate.

### Maintainability

Identify:

* Code duplication
* Dead code
* Overly complex logic
* Hidden side effects
* Unclear naming
* Tight coupling

## Review Output

Summarize findings using:

### Critical Issues

Problems that should be fixed before merging.

### Recommendations

Improvements that are beneficial but not required.

### Positive Findings

Things that are implemented correctly or particularly well.

### Final Assessment

Choose one:

* Approved
* Approved with Recommendations
* Changes Requested

Provide a short explanation for the decision.

## Review Principles

* Do not suggest changes solely based on personal preference.
* Prefer consistency with the existing codebase.
* Prioritize correctness, maintainability, and clarity.
* Avoid recommending architectural changes unless they provide clear value.
* Verify assumptions before raising concerns.
