<?php

namespace Lunar\ProductImport\Mapping;

use Lunar\ProductImport\Models\ProductImportProfile;

/**
 * A saved profile whose headers match a sheet, with the share that matches.
 */
final readonly class ProfileMatch
{
    public function __construct(
        public ProductImportProfile $profile,
        public float $score,
    ) {}
}
