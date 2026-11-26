<?php

namespace Lunar\ProductImport\Data;

use Lunar\ProductImport\Enums\GroupingStrategy;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * How the rows of a sheet form products, with the AI's confidence.
 */
#[MapName(SnakeCaseMapper::class)]
class Grouping extends Data
{
    public function __construct(
        public GroupingStrategy $strategy = GroupingStrategy::PerRow,
        public ?int $column = null,
        public ?float $confidence = null,
    ) {}
}
