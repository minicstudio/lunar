<?php

namespace Lunar\ProductImport\Enums;

/**
 * What happened to an image URL of an imported product.
 */
enum ImageStatus: string
{
    case Pending = 'pending';
    case Downloaded = 'downloaded';
    case Existing = 'existing';
    case Failed = 'failed';
}
