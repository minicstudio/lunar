<?php

namespace Lunar\ProductImport\Taxonomy;

use Lunar\ProductImport\Enums\MatchType;
use Lunar\ProductImport\Enums\ValueAction;

/**
 * How a value compares to the catalog, and the default decision.
 */
final readonly class MatchResult
{
    public function __construct(
        public MatchType $matchType,
        public ?int $matchedId = null,
        public ?float $score = null,
        public ?ValueAction $action = null,
        public ?int $targetId = null,
        public ?string $createName = null,
        public ?int $parentId = null,
    ) {}
}
