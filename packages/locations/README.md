# Locations Plugin

The Lunar Locations plugin provides reusable `County` and `Locality` models for location-related workflows across packages.

## Included Models

- `Lunar\\Locations\\Models\\County`
- `Lunar\\Locations\\Models\\Locality`

## Locality Validation

`Lunar\Locations\Support\LocalityValidator` checks whether a given city/county pair matches seeded locality data — used by the ERP plugin's order sync (see `packages/ERP/ERP_PLUGIN.md`) and by consuming host apps to flag orders with an unrecognized shipping address.

```php
use Lunar\Locations\Support\LocalityValidator;

// Whether locality data is seeded at all for this shop.
LocalityValidator::isAvailable();

// Whether $city exists within $county.
LocalityValidator::matches($city, $county);
```

- `isAvailable()` returns `false` if the `counties`/`localities` tables don't exist yet, or if either is empty — callers should treat "not available" as "nothing to validate against", not as a failure.
- `matches()` looks up candidates with `Locality::where('name', $city)->whereHas('county', …)`, then **re-verifies each candidate in PHP with a strict (`===`) comparison** on both the locality name and its county name. This is required because MySQL's default collation is case-insensitive, so the initial `WHERE` query alone would report a false positive for a near-matching name (e.g. different casing) that isn't actually an exact match.

## Notes

- Uses existing `counties` and `localities` tables.
- Registers model classes through `ModelManifest`.
