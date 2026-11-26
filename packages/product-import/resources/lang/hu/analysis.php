<?php

return [
    'ai_line' => [
        'configured' => 'MI-elemzés: :provider · :model',
        'default_model' => 'alapértelmezett modell',
        'none' => 'Az MI-elemzés nincs beállítva.',
    ],
    'failures' => [
        'not_configured' => 'Az MI-elemzés nincs beállítva. Hiányzik a szolgáltató vagy az API-kulcs. Fordulj a fejlesztődhöz.',
        'auth_failed' => 'Az MI-szolgáltató elutasította az API-kulcsot. A beállításokat ellenőrizni kell.',
        'rate_limited' => 'Az MI-keret elfogyott, vagy túl sok a kérés. Próbáld újra néhány perc múlva.',
        'provider_timeout' => 'Az MI-szolgáltató nem válaszolt. Ez általában átmeneti. Próbáld újra.',
        'provider_error' => 'Az MI-szolgáltató hibával válaszolt. Ez általában átmeneti. Próbáld újra.',
        'invalid_response' => 'Az MI válasza nem használható: :reason. Próbáld újra.',
        'no_product_data' => 'Az MI nem talált terméknevet vagy cikkszámot tartalmazó oszlopot. Ellenőrizd, hogy a munkalapon termékek vannak-e, vagy próbáld újra.',
    ],
    'reasons' => [
        'no_columns' => 'a válaszban nincs oszloplista',
        'unknown_column' => 'a(z) :index. oszlop nem létezik',
        'unknown_target' => 'a(z) „:target” mező nem engedélyezett',
        'unknown_language' => 'a(z) „:locale” nyelv nem a bolt nyelve',
        'duplicate_target' => 'két oszlop lett :target mezőként megjelölve',
        'unknown_grouping' => 'a csoportosítás nem az engedélyezettek egyike',
        'missing_group_column' => 'a csoportosító oszlop nem létezik',
        'no_product_data' => 'egyik oszlop sem tartalmaz terméknevet vagy cikkszámot',
        'missing_pair' => 'a(z) „:column” oszlopnak nincs párszáma',
        'pair_without_value' => 'a(z) :pair. párnak van opciónév-oszlopa, de nincs opcióérték-oszlopa',
        'missing_parent_column' => 'a szülő szerinti csoportosításhoz szülőhivatkozás-oszlop kell',
    ],
];
