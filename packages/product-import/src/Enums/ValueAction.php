<?php

namespace Lunar\ProductImport\Enums;

enum ValueAction: string
{
    case UseExisting = 'use_existing';
    case Create = 'create';
    case Skip = 'skip';
}
