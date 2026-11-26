<?php

namespace Lunar\ProductImport\Structuring;

/**
 * A row that is not imported because its row type means so, with the type
 * as the file writes it, for the message.
 */
final readonly class SkippedRow
{
    public function __construct(public string $type) {}
}
