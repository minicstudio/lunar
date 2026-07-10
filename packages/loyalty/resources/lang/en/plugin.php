<?php

return [
    'label' => 'Loyalty Transaction',
    'plural_label' => 'Loyalty Transactions',
    'navigation' => [
        'group' => 'Loyalty',
    ],
    'customer' => [
        'loyalty_title' => 'Loyalty',
        'transactions_heading' => 'Transaction History',
    ],
    'fields' => [
        'customer' => 'Customer',
        'date' => 'Date',
        'type' => 'Type',
        'points' => 'Points',
        'remaining_points' => 'Remaining',
        'event_key' => 'Event Key',
        'expires_at' => 'Expires At',
        'display_balance' => 'Display Balance',
        'available_balance' => 'Available Balance',
        'lifetime_earned' => 'Lifetime Earned',
        'lifetime_spent' => 'Lifetime Spent',
        'adjust_points' => 'Points (+ credit, − debit)',
        'adjust_points_help' => 'Use positive values to credit and negative values to debit.',
        'reason' => 'Reason',
    ],
    'actions' => [
        'create_account' => 'Create Loyalty Account',
        'adjust' => 'Manual Adjust',
    ],
    'order' => [
        'loyalty_points' => 'Loyalty points',
        'loyalty_points_suffix' => ':points pts',
        'loyalty_redemption_note' => ':points pts (−:amount)',
    ],
];
