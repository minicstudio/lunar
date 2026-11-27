<?php

namespace Lunar\ProductImport\Support;

/**
 * One target a column can map to.
 */
final readonly class TargetDefinition
{
    public const KIND_CORE = 'core';

    public const KIND_ATTRIBUTE = 'attribute';

    public const FORMAT_NUMBER = 'number';

    public const FORMAT_DATE = 'date';

    /**
     * @param  ?string  $format  The format a column mapped to the target needs: `number`, `date`, or null.
     */
    public function __construct(
        public string $target,
        public string $label,
        public bool $takesLocale,
        public string $kind = self::KIND_CORE,
        public ?string $format = null,
    ) {}
}
