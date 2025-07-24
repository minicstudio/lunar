<?php

return [

    'label' => 'Rendelés',

    'plural_label' => 'Rendelések',

    'breadcrumb' => [
        'manage' => 'Kezelés',
    ],

    'transactions' => [
        'capture' => 'Lekötve',
        'intent' => 'Szándék',
        'refund' => 'Visszatérítve',
        'failed' => 'Sikertelen',
    ],

    'table' => [
        'status' => [
            'label' => 'Állapot',
        ],
        'reference' => [
            'label' => 'Hivatkozás',
        ],
        'customer_reference' => [
            'label' => 'Vásárlói hivatkozás',
        ],
        'customer' => [
            'label' => 'Vevő',
        ],
        'tags' => [
            'label' => 'Címkék',
        ],
        'postcode' => [
            'label' => 'Irányítószám',
        ],
        'email' => [
            'label' => 'Email',
            'copy_message' => 'Email cím másolva',
        ],
        'phone' => [
            'label' => 'Telefon',
        ],
        'total' => [
            'label' => 'Összesen',
        ],
        'date' => [
            'label' => 'Dátum',
        ],
        'new_customer' => [
            'label' => 'Vevő típusa',
        ],
        'placed_after' => [
            'label' => 'Megrendelve után',
        ],
        'placed_before' => [
            'label' => 'Megrendelve előtt',
        ],
    ],

    'form' => [
        'address' => [
            'first_name' => [
                'label' => 'Keresztnév',
            ],
            'last_name' => [
                'label' => 'Vezetéknév',
            ],
            'line_one' => [
                'label' => 'Cím 1. sor',
            ],
            'line_two' => [
                'label' => 'Cím 2. sor',
            ],
            'line_three' => [
                'label' => 'Cím 3. sor',
            ],
            'company_name' => [
                'label' => 'Cégnév',
            ],
            'tax_identifier' => [
                'label' => 'Adószám',
            ],
            'contact_phone' => [
                'label' => 'Telefon',
            ],
            'contact_email' => [
                'label' => 'Email cím',
            ],
            'city' => [
                'label' => 'Város',
            ],
            'state' => [
                'label' => 'Állam / Megye',
            ],
            'postcode' => [
                'label' => 'Irányítószám',
            ],
            'country_id' => [
                'label' => 'Ország',
            ],
        ],

        'reference' => [
            'label' => 'Hivatkozás',
        ],
        'status' => [
            'label' => 'Állapot',
        ],
        'transaction' => [
            'label' => 'Tranzakció',
        ],
        'amount' => [
            'label' => 'Összeg',

            'hint' => [
                'less_than_total' => 'Kevesebb összeget szeretnél lekötni, mint a teljes tranzakció értéke',
            ],
        ],

        'notes' => [
            'label' => 'Megjegyzések',
        ],
        'confirm' => [
            'label' => 'Megerősítés',

            'alert' => 'Megerősítés szükséges',

            'hint' => [
                'capture' => 'Kérjük, erősítsd meg a fizetés lekötését',
                'refund' => 'Kérjük, erősítsd meg a visszatérítést',
            ],
        ],
    ],

    'infolist' => [
        'notes' => [
            'label' => 'Megjegyzések',
            'placeholder' => 'Nincsenek megjegyzések ehhez a rendeléshez',
        ],
        'delivery_instructions' => [
            'label' => 'Szállítási utasítások',
        ],
        'shipping_total' => [
            'label' => 'Szállítási összeg',
        ],
        'paid' => [
            'label' => 'Fizetve',
        ],
        'refund' => [
            'label' => 'Visszatérítés',
        ],
        'unit_price' => [
            'label' => 'Egységár',
        ],
        'quantity' => [
            'label' => 'Mennyiség',
        ],
        'sub_total' => [
            'label' => 'Részösszeg',
        ],
        'discount_total' => [
            'label' => 'Kedvezmény összege',
        ],
        'total' => [
            'label' => 'Összesen',
        ],
        'current_stock_level' => [
            'message' => 'Jelenlegi készletszint: :count',
        ],
        'purchase_stock_level' => [
            'message' => 'megrendeléskor: :count',
        ],
        'status' => [
            'label' => 'Állapot',
        ],
        'reference' => [
            'label' => 'Hivatkozás',
        ],
        'customer_reference' => [
            'label' => 'Vásárlói hivatkozás',
        ],
        'channel' => [
            'label' => 'Csatorna',
        ],
        'date_created' => [
            'label' => 'Létrehozás dátuma',
        ],
        'date_placed' => [
            'label' => 'Rendelés dátuma',
        ],
        'new_returning' => [
            'label' => 'Új / Visszatérő',
        ],
        'new_customer' => [
            'label' => 'Új vásárló',
        ],
        'returning_customer' => [
            'label' => 'Visszatérő vásárló',
        ],
        'shipping_address' => [
            'label' => 'Szállítási cím',
        ],
        'billing_address' => [
            'label' => 'Számlázási cím',
        ],
        'address_not_set' => [
            'label' => 'Nincs beállított cím',
        ],
        'billing_matches_shipping' => [
            'label' => 'Megegyezik a szállítási címmel',
        ],
        'additional_info' => [
            'label' => 'További információ',
        ],
        'no_additional_info' => [
            'label' => 'Nincs további információ',
        ],
        'tags' => [
            'label' => 'Címkék',
        ],
        'timeline' => [
            'label' => 'Idővonal',
        ],
        'transactions' => [
            'label' => 'Tranzakciók',
            'placeholder' => 'Nincsenek tranzakciók',
        ],
        'alert' => [
            'requires_capture' => 'Ez a rendelés még fizetés lekötést igényel.',
            'partially_refunded' => 'Ez a rendelés részben visszatérített.',
            'refunded' => 'Ez a rendelés visszatérített.',
        ],
    ],

    'action' => [
        'bulk_update_status' => [
            'label' => 'Állapot frissítése',
            'notification' => 'Rendelések állapota frissítve',
        ],
        'update_status' => [
            'new_status' => [
                'label' => 'Új állapot',
            ],
            'additional_content' => [
                'label' => 'Kiegészítő tartalom',
            ],
            'additional_email_recipient' => [
                'label' => 'További email címzett',
                'placeholder' => 'opcionális',
            ],
            'mailers' => [
                'label' => 'Levélküldők',
            ],
            'email_addresses' => [
                'label' => 'Email címek',
            ],
        ],
        'download_order_pdf' => [
            'label' => 'PDF letöltése',
            'notification' => 'Rendelés PDF letöltése folyamatban',
        ],
        'edit_address' => [
            'label' => 'Szerkesztés',

            'notification' => [
                'error' => 'Hiba',

                'billing_address' => [
                    'saved' => 'Számlázási cím elmentve',
                ],

                'shipping_address' => [
                    'saved' => 'Szállítási cím elmentve',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Szerkesztés',
            'form' => [
                'tags' => [
                    'label' => 'Címkék',
                    'helper_text' => 'A címkék elválasztásához használj Entert, Tabot vagy vesszőt (,)',
                ],
            ],
        ],
        'capture_payment' => [
            'label' => 'Fizetés lekötése',

            'notification' => [
                'error' => 'Hiba történt a lekötés során',
                'success' => 'Lekötés sikeres',
            ],
        ],
        'refund_payment' => [
            'label' => 'Visszatérítés',

            'notification' => [
                'error' => 'Hiba történt a visszatérítés során',
                'success' => 'Visszatérítés sikeres',
            ],
        ],
    ],

];
