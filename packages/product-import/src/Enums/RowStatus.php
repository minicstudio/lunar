<?php

namespace Lunar\ProductImport\Enums;

enum RowStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Warning = 'warning';
    case Error = 'error';
    case Skipped = 'skipped';
    case Imported = 'imported';
    case Failed = 'failed';
}
