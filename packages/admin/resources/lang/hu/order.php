<?php

return [

    'label' => 'Rendelés',

    'plural_label' => 'Rendelések',

    'breadcrumb' => [
        'manage' => 'Kezelés',
    ],

    'tabs' => [
        'all' => 'Mind',
    ],

    'transactions' => [
        'capture' => 'Lekönyvelve',
        'intent' => 'Fizetési szándék',
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
            'label' => 'Vásárlói azonosító',
        ],
        'customer' => [
            'label' => 'Vásárló',
        ],
        'tags' => [
            'label' => 'Címkék',
        ],
        'postcode' => [
            'label' => 'Irányítószám',
        ],
        'email' => [
            'label' => 'E-mail',
            'copy_message' => 'E-mail cím másolva',
        ],
        'phone' => [
            'label' => 'Telefon',
        ],
        'total' => [
            'label' => 'Végösszeg',
        ],
        'date' => [
            'label' => 'Dátum',
        ],
        'new_customer' => [
            'label' => 'Vásárló típusa',
        ],
        'placed_after' => [
            'label' => 'Rendelés ideje után',
        ],
        'placed_before' => [
            'label' => 'Rendelés ideje előtt',
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
                'label' => 'Adóazonosító',
            ],
            'contact_phone' => [
                'label' => 'Telefon',
            ],
            'contact_email' => [
                'label' => 'E-mail cím',
            ],
            'city' => [
                'label' => 'Város',
            ],
            'state' => [
                'label' => 'Megye',
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
                'less_than_total' => 'Ön kevesebb összeget készül lekönyvelni, mint a teljes tranzakció értéke',
            ],
        ],

        'notes' => [
            'label' => 'Megjegyzések',
        ],
        'confirm' => [
            'label' => 'Megerősítés',

            'alert' => 'Megerősítés szükséges',

            'hint' => [
                'capture' => 'Kérjük, erősítse meg, hogy le akarja könyvelni ezt a fizetést',
                'refund' => 'Kérjük, erősítse meg, hogy vissza kívánja téríteni ezt az összeget.',
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
            'label' => 'Szállítási díj',
        ],
        'paid' => [
            'label' => 'Kifizetve',
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
            'label' => 'Végösszeg',
        ],
        'current_stock_level' => [
            'message' => 'Jelenlegi készletszint: :count',
        ],
        'purchase_stock_level' => [
            'message' => 'rendelés idején: :count',
        ],
        'status' => [
            'label' => 'Állapot',
        ],
        'reference' => [
            'label' => 'Hivatkozás',
        ],
        'customer_reference' => [
            'label' => 'Vásárlói azonosító',
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
            'label' => 'Nincs cím megadva',
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
            'requires_capture' => 'Ennél a rendelésnél még fizetést kell lekönyvelni.',
            'partially_refunded' => 'Ennél a rendelésnél részleges visszatérítés történt.',
            'refunded' => 'Ennél a rendelésnél visszatérítés történt.',
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
                'label' => 'További tartalom',
            ],
            'additional_email_recipient' => [
                'label' => 'További e-mail címzett',
                'placeholder' => 'opcionális',
            ],
            'mailers' => [
                'label' => 'Levélküldők',
            ],
            'email_addresses' => [
                'label' => 'E-mail címek',
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
                    'saved' => 'Számlázási cím mentve',
                ],

                'shipping_address' => [
                    'saved' => 'Szállítási cím mentve',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Szerkesztés',
            'form' => [
                'tags' => [
                    'label' => 'Címkék',
                    'helper_text' => 'A címkék elválasztásához használjon Entert, Tabot vagy vesszőt (,)',
                ],
            ],
        ],
        'capture_payment' => [
            'label' => 'Fizetés lekönyvelése',

            'notification' => [
                'error' => 'Hiba történt a lekönyvelés során',
                'success' => 'Lekönyvelés sikeres',
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
