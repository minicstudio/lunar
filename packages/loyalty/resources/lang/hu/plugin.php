<?php

return [
    'label' => 'Hűségpont tranzakció',
    'plural_label' => 'Hűségpont tranzakciók',
    'navigation' => [
        'group' => 'Hűségprogram',
    ],
    'customer' => [
        'loyalty_title' => 'Hűségprogram',
        'transactions_heading' => 'Tranzakcióelőzmények',
    ],
    'fields' => [
        'customer' => 'Ügyfél',
        'date' => 'Dátum',
        'type' => 'Típus',
        'points' => 'Pontok',
        'remaining_points' => 'Fennmaradó',
        'event_key' => 'Esemény kulcs',
        'expires_at' => 'Lejár',
        'display_balance' => 'Megjelenített egyenleg',
        'available_balance' => 'Elérhető egyenleg',
        'lifetime_earned' => 'Összes megszerzett',
        'lifetime_spent' => 'Összes elköltött',
        'adjust_points' => 'Pontok (+ jóváírás, − terhelés)',
        'adjust_points_help' => 'Pozitív értékeket jóváíráshoz, negatív értékeket terheléshez használjon.',
        'reason' => 'Indok',
    ],
    'actions' => [
        'create_account' => 'Hűségprogram fiók létrehozása',
        'adjust' => 'Kézi módosítás',
    ],
    'order' => [
        'loyalty_points' => 'Hűségpontok',
        'loyalty_points_suffix' => ':points pt',
        'loyalty_redemption_note' => ':points pt (−:amount)',
    ],
];
