<?php

return [
    'errors' => [
        'product_type_required' => 'Alege un tip de produs.',
        'unknown_target' => 'Coloana „:column” este mapată la un câmp care nu există.',
        'locale_required' => 'Alege o limbă pentru coloana „:column”.',
        'unknown_locale' => 'Coloana „:column” folosește o limbă pe care magazinul nu o are.',
        'duplicate_target' => ':target este mapat de două ori.',
        'duplicate_target_locale' => ':target (:locale) este mapat de două ori.',
        'duplicate_option' => 'Opțiunea „:option” este mapată de două ori.',
        'option_required' => 'Alege o opțiune existentă sau denumește una nouă pentru coloana „:column”.',
        'unknown_option' => 'Opțiunea coloanei „:column” nu mai există.',
        'group_column_required' => 'Gruparea după coloană necesită o coloană mapată la Cheie de grupare.',
        'weight_unit_required' => 'Alege unitatea de greutate a foii ":sheet".',
        'number_format_required' => 'Alege formatul numeric al coloanei „:column” din foaia „:sheet”.',
        'date_format_required' => 'Alege formatul de dată al coloanei „:column” din foaia „:sheet”.',
        'item_parts_invalid' => 'Părțile elementelor coloanei „:column” pot conține cel mult un nume și o valoare.',
        'image_suffix_invalid' => 'Sufixul de dimensiune al imaginilor din coloana „:column” poate conține doar litere, cifre, „-” și „_”, cel mult 50 de caractere.',
        'pair_required' => 'Alege numărul perechii pentru coloana „:column”.',
        'duplicate_target_pair' => ':target din perechea :pair este mapat de două ori.',
        'pair_without_value' => 'Perechea :pair are o coloană cu numele opțiunii, dar nicio coloană cu valoarea opțiunii.',
        'parent_column_required' => 'Gruparea după părinte necesită o coloană mapată la Referință părinte.',
        'length_unit_required' => 'Alege unitatea de lungime a foii ":sheet".',
        'list_separator_required' => 'Alege separatorul de listă al coloanei „:column” din foaia „:sheet”.',
        'path_separator_required' => 'Alege separatorul de niveluri al coloanei „:column” din foaia „:sheet”.',
        'item_separator_required' => 'Alege separatorul părților elementului al coloanei „:column” din foaia „:sheet”.',
    ],
    'formats' => [
        'number' => ':decimal și :thousands',
        'missing' => 'niciun format numeric',
        'decimal' => [
            'dot' => 'punct zecimal',
            'comma' => 'virgulă zecimală',
        ],
        'thousands' => [
            'none' => 'fără separator de mii',
            'dot' => 'punct ca separator de mii',
            'comma' => 'virgulă ca separator de mii',
            'space' => 'spațiu ca separator de mii',
            'apostrophe' => 'apostrof ca separator de mii',
        ],
    ],
];
