# Spec Delta

## MODIFIED Requirements

### Requirement: A prefetched landing page starts its visit when it is opened
A prefetch that would start a visit in a session without one SHALL keep that visit pending in the session, with the prefetched page as landing path. While the session has no visit, its next request that is not a prefetch SHALL record the pending visit when it is a page view without a source of its own (the shop's own referrer and no campaign parameters), or when it adds a product to a cart without a traffic source. A prefetch that is never opened SHALL NOT record a visit.

#### Scenario: Google result opened from a prefetch
- **WHEN** Chrome prefetches a product page from `https://www.google.com/` for a new visitor, the visitor opens it from the prefetch and then opens a category page from it
- **THEN** one visit is recorded with source `google`, medium `organic` and the product page path as landing path

#### Scenario: Prefetch never opened
- **WHEN** Chrome prefetches a page from Google search and the visitor never opens it
- **THEN** no visit is recorded
