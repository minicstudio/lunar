<?php

namespace Lunar\Loyalty\Enums;

enum LoyaltyTransactionType: string
{
    case Earn = 'earn';
    case Spend = 'spend';
    case Expire = 'expire';
    case Adjust = 'adjust';
}
