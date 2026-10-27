<?php

return [

    'label' => 'Narudžba',

    'plural_label' => 'Narudžbe',

    'breadcrumb' => [
        'manage' => 'Upravljanje',
    ],

    'transactions' => [
        'capture' => 'Naplaćeno',
        'intent' => 'Namjera',
        'refund' => 'Vraćeno',
        'failed' => 'Neuspješno',
    ],

    'table' => [
        'status' => [
            'label' => 'Status',
        ],
        'reference' => [
            'label' => 'Referenca',
        ],
        'customer_reference' => [
            'label' => 'Referenca kupca',
        ],
        'customer' => [
            'label' => 'Kupac',
        ],
        'tags' => [
            'label' => 'Oznake',
            'no_options_message' => 'Nema pronađenih oznaka',
        ],
        'postcode' => [
            'label' => 'Poštanski broj',
        ],
        'email' => [
            'label' => 'E-mail',
            'copy_message' => 'E-mail adresa kopirana',
        ],
        'phone' => [
            'label' => 'Telefon',
        ],
        'total' => [
            'label' => 'Ukupno',
        ],
        'date' => [
            'label' => 'Datum',
        ],
        'new_customer' => [
            'label' => 'Tip kupca',
        ],
        'payment_type' => [
            'label' => 'Način plaćanja',
            'options' => [
                'offline' => 'Offline',
                'card' => 'Kartica',
            ],
        ],
        'total_from' => [
            'label' => 'Ukupno od',
        ],
        'total_to' => [
            'label' => 'Ukupno do',
        ],
        'placed_after' => [
            'label' => 'Naručeno nakon',
        ],
        'placed_before' => [
            'label' => 'Naručeno prije',
        ],
    ],

    'form' => [
        'address' => [
            'first_name' => [
                'label' => 'Ime',
            ],
            'last_name' => [
                'label' => 'Prezime',
            ],
            'line_one' => [
                'label' => 'Adresa redak 1',
            ],
            'line_two' => [
                'label' => 'Adresa redak 2',
            ],
            'line_three' => [
                'label' => 'Adresa redak 3',
            ],
            'company_name' => [
                'label' => 'Naziv tvrtke',
            ],
            'contact_phone' => [
                'label' => 'Telefon',
            ],
            'contact_email' => [
                'label' => 'E-mail adresa',
            ],
            'city' => [
                'label' => 'Grad',
            ],
            'state' => [
                'label' => 'Županija',
            ],
            'postcode' => [
                'label' => 'Poštanski broj',
            ],
            'country_id' => [
                'label' => 'Zemlja',
            ],
        ],

        'reference' => [
            'label' => 'Referenca',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'transaction' => [
            'label' => 'Transakcija',
        ],
        'amount' => [
            'label' => 'Iznos',

            'hint' => [
                'less_than_total' => 'Želite naplatiti iznos koji je manji od ukupne vrijednosti transakcije',
            ],
        ],

        'notes' => [
            'label' => 'Bilješke',
        ],
        'confirm' => [
            'label' => 'Potvrdi',

            'alert' => 'Potrebna je potvrda',

            'hint' => [
                'capture' => 'Molimo potvrdite da želite naplatiti ovu uplatu',
                'refund' => 'Molimo potvrdite da želite vratiti ovaj iznos.',
            ],
        ],
    ],

    'infolist' => [
        'notes' => [
            'label' => 'Bilješke',
            'placeholder' => 'Nema bilješki za ovu narudžbu',
        ],
        'delivery_instructions' => [
            'label' => 'Upute za dostavu',
        ],
        'shipping_total' => [
            'label' => 'Ukupni troškovi dostave',
        ],
        'paid' => [
            'label' => 'Plaćeno',
        ],
        'refund' => [
            'label' => 'Povrat',
        ],
        'unit_price' => [
            'label' => 'Jedinična cijena',
        ],
        'quantity' => [
            'label' => 'Količina',
        ],
        'sub_total' => [
            'label' => 'Međuzbroj',
        ],
        'discount_total' => [
            'label' => 'Ukupni popust',
        ],
        'total' => [
            'label' => 'Ukupno',
        ],
        'preorder' => [
            'message' => 'Ovaj je proizvod dostupan samo za prednarudžbu.',
        ],
        'current_stock_level' => [
            'message' => 'Trenutna razina zaliha: :count',
        ],
        'purchase_stock_level' => [
            'message' => 'U trenutku narudžbe: :count',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'reference' => [
            'label' => 'Referenca',
        ],
        'customer_reference' => [
            'label' => 'Referenca kupca',
        ],
        'channel' => [
            'label' => 'Kanal',
        ],
        'date_created' => [
            'label' => 'Datum stvaranja',
        ],
        'date_placed' => [
            'label' => 'Datum narudžbe',
        ],
        'new_returning' => [
            'label' => 'Novi / Stalni',
        ],
        'new_customer' => [
            'label' => 'Novi kupac',
        ],
        'returning_customer' => [
            'label' => 'Stalni kupac',
        ],
        'shipping_address' => [
            'label' => 'Adresa dostave',
        ],
        'billing_address' => [
            'label' => 'Adresa naplate',
        ],
        'address_not_set' => [
            'label' => 'Adresa nije postavljena',
        ],
        'billing_matches_shipping' => [
            'label' => 'Jednako kao adresa dostave',
        ],
        'additional_info' => [
            'label' => 'Dodatne informacije',
        ],
        'no_additional_info' => [
            'label' => 'Nema dodatnih informacija',
        ],
        'shipping_details' => [
            'label' => 'Podaci o dostavi',
        ],
        'billing_details' => [
            'label' => 'Podaci o naplati',
        ],
        'meta' => [
            'shippingType' => 'Način dostave',
            'shipping_zone' => 'Zona dostave',
            'awb' => 'AWB broj',
            'payment_option' => 'Način plaćanja',
            'payment_type' => 'Vrsta plaćanja',
            'isBillingSameAsShipping' => 'Adresa za naplatu ista je kao adresa za dostavu',
            'billing_series' => 'Serija računa',
            'billing_number' => 'Broj računa',
        ],
        'shipping_types' => [
            'courier' => 'Kurirska služba',
            'locker' => 'Paketomat',
            'pickup' => 'Osobno preuzimanje',
        ],
        'payment_methods' => [
            'offline' => 'Izvanmrežno',
            'cash-in-hand' => 'Gotovina',
            'cash-on-delivery' => 'Plaćanje pouzećem',
            'bank-transfer' => 'Bankovni prijenos',
            'stripe-card' => 'Kreditna kartica',
            'stripe-wallet-pay' => 'Apple Pay / Google Pay',
            'hosted-payment' => 'Online plaćanje karticom',
        ],
        'tags' => [
            'label' => 'Oznake',
        ],
        'timeline' => [
            'label' => 'Vremenska crta',
        ],
        'transactions' => [
            'label' => 'Transakcije',
            'placeholder' => 'Nema transakcija',
        ],
        'alert' => [
            'requires_capture' => 'Za ovu narudžbu još uvijek je potrebno evidentirati plaćanje.',
            'partially_refunded' => 'Za ovu narudžbu je djelomično izvršen povrat sredstava',
            'refunded' => 'Ova narudžba je vraćena.',
            'product_line_gates' => [
                'paid' => 'Plaćeno: ova narudžba ne može se izmijeniti jer je plaćena.',
                'erp' => 'ERP: ova narudžba ne može se izmijeniti jer je poslana u ERP.',
                'invoice' => 'Račun: ova narudžba ne može se izmijeniti jer ima račun.',
                'awb' => 'AWB: ova narudžba ne može se izmijeniti jer ima AWB.',
            ],
        ],
    ],

    'action' => [
        'bulk_update_status' => [
            'label' => 'Ažuriraj status',
            'notification' => 'Status narudžbe ažuriran',
        ],
        'update_status' => [
            'label' => 'Ažuriraj status',
            'notification' => 'Status narudžbe ažuriran',
            'new_status' => [
                'label' => 'Novi status',
            ],
            'additional_content' => [
                'label' => 'Dodatni sadržaj',
            ],
            'additional_email_recipient' => [
                'label' => 'Dodatni primatelj e-maila',
                'placeholder' => 'neobavezno',
            ],
        ],
        'download_order_pdf' => [
            'label' => 'Preuzmi PDF',
            'notification' => 'PDF narudžbe se preuzima',
        ],
        'edit_address' => [
            'label' => 'Uredi',

            'notification' => [
                'error' => 'Greška',

                'billing_address' => [
                    'saved' => 'Adresa naplate spremljena',
                ],

                'shipping_address' => [
                    'saved' => 'Adresa dostave spremljena',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Uredi',
        ],
        'capture_payment' => [
            'label' => 'Naplati uplatu',

            'notification' => [
                'error' => 'Došlo je do problema prilikom naplate',
                'success' => 'Naplata uspješna',
            ],
        ],
        'refund_payment' => [
            'label' => 'Povrat',

            'notification' => [
                'error' => 'Došlo je do problema prilikom povrata',
                'success' => 'Povrat uspješan',
            ],
        ],
        'add_product' => [
            'label' => 'Dodaj proizvod',
            'product' => [
                'label' => 'Proizvod',
            ],
            'variant' => [
                'label' => 'Varijanta',
            ],
            'quantity' => [
                'label' => 'Količina',
            ],
            'notification' => 'Proizvod dodan',
        ],
        'update_quantity' => [
            'label' => 'Ažuriraj količinu',
            'quantity' => [
                'label' => 'Količina',
            ],
            'notification' => 'Količina ažurirana',
        ],
        'remove_product' => [
            'label' => 'Ukloni proizvod',
            'confirmation' => 'Ukloniti ovaj proizvod iz narudžbe?',
            'notification' => 'Proizvod uklonjen',
        ],
        'product_line_edit' => [
            'last_line' => 'Narudžba mora zadržati barem jedan proizvod.',
            'unauthorized' => 'Ne možete mijenjati proizvode ove narudžbe.',
            'cart' => 'Ova narudžba nema košaricu, pa se proizvodi ne mogu mijenjati.',
            'line' => 'Ta stavka proizvoda nije pronađena.',
            'purchasable' => 'Ta varijanta nije pronađena.',
            'shipping' => 'Opcija dostave ne može se cijeniti, pa je narudžba ostala nepromijenjena.',
            'locked' => 'Ova narudžba se više ne može uređivati.',
            'not_purchasable' => 'Ova varijanta se ne može kupiti.',
            'out_of_stock' => 'Ova varijanta nije na zalihi.',
            'insufficient_stock' => 'Nema dovoljno zalihe za ovu količinu.',
            'missing_price' => 'Ova varijanta nema cijenu u valuti narudžbe.',
        ],
    ],

];
