# Laravel Conventions

## Database

- Never use DB::table when an Eloquent model exists.
- Prefer relationships over manual joins.

## Events

- Prefer events for cross-domain side effects.

## Collections

- Prefer Laravel Collections over plain arrays when working with datasets.
- Use Collections for filtering, mapping, grouping, sorting, and transformations.
- Return Collections where appropriate to maintain a consistent API.

## Collection Helpers

- Prefer Laravel Collection methods over native PHP array functions when working with Collections.
- Use expressive Collection methods such as `map()`, `filter()`, `reject()`, `pluck()`, `groupBy()`, and `sortBy()`.
- Prioritize readability and consistency with Laravel conventions.