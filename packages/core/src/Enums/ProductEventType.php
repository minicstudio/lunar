<?php

namespace Lunar\Enums;

enum ProductEventType: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
}
