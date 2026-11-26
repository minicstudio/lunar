<?php

return [
    'errors' => [
        'product_type_required' => 'Válassz terméktípust.',
        'unknown_target' => 'A(z) „:column” oszlop nem létező mezőhöz van rendelve.',
        'locale_required' => 'Válassz nyelvet a(z) „:column” oszlophoz.',
        'unknown_locale' => 'A(z) „:column” oszlop olyan nyelvet használ, amely nincs a boltban.',
        'duplicate_target' => ':target kétszer van hozzárendelve.',
        'duplicate_target_locale' => ':target (:locale) kétszer van hozzárendelve.',
        'duplicate_option' => 'A(z) „:option” opció kétszer van hozzárendelve.',
        'option_required' => 'Válassz meglévő opciót vagy adj nevet egy újnak a(z) „:column” oszlophoz.',
        'unknown_option' => 'A(z) „:column” oszlop opciója már nem létezik.',
        'group_column_required' => 'Az oszlop szerinti csoportosításhoz egy oszlopot a Csoportkulcshoz kell rendelni.',
        'weight_unit_required' => 'Válaszd ki a(z) ":sheet" munkalap súlyainak mértékegységét.',
        'number_format_required' => 'Válaszd ki a(z) ":sheet" munkalap ":column" oszlopának számformátumát.',
        'date_format_required' => 'Válaszd ki a(z) ":sheet" munkalap ":column" oszlopának dátumformátumát.',
        'item_parts_invalid' => 'A(z) „:column” oszlop elemrészei között legfeljebb egy név és egy érték lehet.',
        'image_suffix_invalid' => 'A(z) „:column” oszlop képméret-utótagja csak betűket, számjegyeket, „-” és „_” jelet tartalmazhat, legfeljebb 50 karakterben.',
        'pair_required' => 'Válaszd ki a(z) „:column” oszlop párszámát.',
        'duplicate_target_pair' => 'A(z) :pair. pár :target mezője kétszer van hozzárendelve.',
        'pair_without_value' => 'A(z) :pair. párnak van opciónév-oszlopa, de nincs opcióérték-oszlopa.',
        'parent_column_required' => 'A szülő szerinti csoportosításhoz egy oszlopot a Szülőhivatkozáshoz kell rendelni.',
        'length_unit_required' => 'Válaszd ki a(z) ":sheet" munkalap hosszmértékegységét.',
        'list_separator_required' => 'Válaszd ki a(z) ":sheet" munkalap ":column" oszlopának listaelválasztóját.',
        'path_separator_required' => 'Válaszd ki a(z) ":sheet" munkalap ":column" oszlopának kategóriaszint-elválasztóját.',
        'item_separator_required' => 'Válaszd ki a(z) ":sheet" munkalap ":column" oszlopának elemrész-elválasztóját.',
    ],
    'formats' => [
        'number' => ':decimal, :thousands',
        'missing' => 'nincs számformátum',
        'decimal' => [
            'dot' => 'tizedespont',
            'comma' => 'tizedesvessző',
        ],
        'thousands' => [
            'none' => 'ezres elválasztó nélkül',
            'dot' => 'pont mint ezres elválasztó',
            'comma' => 'vessző mint ezres elválasztó',
            'space' => 'szóköz mint ezres elválasztó',
            'apostrophe' => 'aposztróf mint ezres elválasztó',
        ],
    ],
];
