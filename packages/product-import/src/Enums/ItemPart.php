<?php

namespace Lunar\ProductImport\Enums;

/**
 * What one `:`-separated part of a packed list item holds: the name, the
 * value, or something the import drops, such as a type or a position.
 * `Size:select:0` is read with `name, skip, skip`.
 */
enum ItemPart: string
{
    case Name = 'name';
    case Value = 'value';
    case Skip = 'skip';
}
