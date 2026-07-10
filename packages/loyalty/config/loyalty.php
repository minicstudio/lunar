<?php

use Lunar\Loyalty\Calculators\FixedPointsCalculator;
use Lunar\Loyalty\Calculators\OrderTotalCalculator;

return [
    'enabled' => env('LOYALTY_ENABLED', true),

    'currency' => [
        'earn_ratio' => 100,
        'redeem_ratio' => 1,
        'min_redeem' => 100,
        'max_redeem_percent' => 50,
    ],

    'earn' => [
        'order_status' => 'completed',
    ],

    'events' => [
        'order_completed' => [
            'calculator' => OrderTotalCalculator::class,
            'multiplier' => 1,
        ],
        'registration' => [
            'calculator' => FixedPointsCalculator::class,
            'points' => 500,
        ],
    ],

    'scheduled_rewards' => [
        'birthday' => [
            'enabled' => false,
            'points' => 1000,
            'attribute_handle' => 'birthday',
            'command' => 'loyalty:award-birthday-points',
        ],
    ],

    'expiration' => [
        'months' => 12,
        'notify_windows' => [
            30 => '30_days',
            7 => '7_days',
        ],
        'notification_mailer' => null,
        'notification_mailable' => null,
    ],

    'cancel' => [
        'statuses' => ['canceled'],
        'reverse_spend' => true,
    ],

    'refund' => [
        'statuses' => ['returned'],
    ],

    'schedule' => [
        'expire' => '0 2 * * *',
        'notify' => '0 9 * * *',
        'birthday' => '0 8 * * *',
        'recalculate_balances' => '0 3 * * 0',
    ],
];
