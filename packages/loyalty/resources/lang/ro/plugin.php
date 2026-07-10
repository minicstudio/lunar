<?php

return [
    'label' => 'Tranzacție loialitate',
    'plural_label' => 'Tranzacții loialitate',
    'navigation' => [
        'group' => 'Loialitate',
    ],
    'customer' => [
        'loyalty_title' => 'Loialitate',
        'transactions_heading' => 'Istoric tranzacții',
    ],
    'fields' => [
        'customer' => 'Client',
        'date' => 'Dată',
        'type' => 'Tip',
        'points' => 'Puncte',
        'remaining_points' => 'Rămase',
        'event_key' => 'Cheie eveniment',
        'expires_at' => 'Expiră la',
        'display_balance' => 'Sold afișat',
        'available_balance' => 'Sold disponibil',
        'lifetime_earned' => 'Total câștigate',
        'lifetime_spent' => 'Total cheltuite',
        'adjust_points' => 'Puncte (+ credit, − debit)',
        'adjust_points_help' => 'Folosiți valori pozitive pentru credit și negative pentru debit.',
        'reason' => 'Motiv',
    ],
    'actions' => [
        'create_account' => 'Creează cont loialitate',
        'adjust' => 'Ajustare manuală',
    ],
    'order' => [
        'loyalty_points' => 'Puncte loyalty',
        'loyalty_points_suffix' => ':points pct',
        'loyalty_redemption_note' => ':points pct (−:amount)',
    ],
];
