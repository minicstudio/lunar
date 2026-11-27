<?php

namespace Lunar\ProductImport\Support;

/**
 * The store's units of one measurement: the keys of
 * `lunar.shipping.measurements.<measurement>`. A unit is always one of these
 * codes; other spellings are never translated into one.
 */
abstract class MeasurementUnits
{
    /**
     * The measurement under `lunar.shipping.measurements`.
     */
    protected const MEASUREMENT = '';

    /**
     * The store's unit codes.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        return array_map(
            fn (int|string $code): string => (string) $code,
            array_keys((array) config('lunar.shipping.measurements.'.static::MEASUREMENT, [])),
        );
    }

    /**
     * Whether the code is one of the store's units.
     */
    public function exists(?string $code): bool
    {
        return $code !== null && in_array($code, $this->codes(), true);
    }
}
