<?php

namespace Lunar\ProductImport\Taxonomy;

use Lunar\ProductImport\Enums\ValueType;

/**
 * One record the import will create. Values that create the same record
 * share it.
 */
final class PlannedRecord
{
    /**
     * @param  ?string  $parentKey  For a category: the key of the planned parent, if the parent is planned too.
     * @param  ?int  $parentId  For a category: the existing parent, or null at the root.
     * @param  list<int>  $valueIds  The values whose target this record is.
     */
    public function __construct(
        public string $key,
        public ValueType $type,
        public string $name,
        public string $optionKey = '',
        public ?string $parentKey = null,
        public ?int $parentId = null,
        public array $valueIds = [],
    ) {}
}
