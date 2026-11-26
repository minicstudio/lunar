<?php

return [
    'ai_line' => [
        'configured' => 'Analiză AI: :provider · :model',
        'default_model' => 'model implicit',
        'none' => 'Analiza AI nu este configurată.',
    ],
    'failures' => [
        'not_configured' => 'Analiza AI nu este configurată. Lipsește furnizorul sau cheia API. Contactează dezvoltatorul.',
        'auth_failed' => 'Furnizorul AI a respins cheia API. Configurația trebuie verificată.',
        'rate_limited' => 'Cota AI este epuizată sau sunt prea multe cereri. Încearcă din nou peste câteva minute.',
        'provider_timeout' => 'Furnizorul AI nu a răspuns. De obicei este temporar. Încearcă din nou.',
        'provider_error' => 'Furnizorul AI a răspuns cu o eroare. De obicei este temporar. Încearcă din nou.',
        'invalid_response' => 'Răspunsul AI nu a putut fi folosit: :reason. Încearcă din nou.',
        'no_product_data' => 'AI nu a găsit nicio coloană cu nume de produs sau SKU. Verifică dacă foaia conține produse sau încearcă din nou.',
    ],
    'reasons' => [
        'no_columns' => 'răspunsul nu are lista de coloane',
        'unknown_column' => 'coloana :index nu există',
        'unknown_target' => 'câmpul „:target” nu este permis',
        'unknown_language' => 'limba „:locale” nu este o limbă a magazinului',
        'duplicate_target' => 'două coloane au fost marcate ca :target',
        'unknown_grouping' => 'gruparea nu este una dintre cele permise',
        'missing_group_column' => 'coloana de grupare nu există',
        'no_product_data' => 'nicio coloană nu conține nume de produs sau SKU',
        'missing_pair' => 'coloana „:column” nu are număr de pereche',
        'pair_without_value' => 'perechea :pair are o coloană cu numele opțiunii, dar nicio coloană cu valoarea opțiunii',
        'missing_parent_column' => 'gruparea după părinte necesită o coloană cu referința părintelui',
    ],
];
