<?php

return [
    'label' => 'Felugró ablak',
    'plural_label' => 'Felugró ablakok',

    'edit' => [
        'label' => 'Felugró ablak szerkesztése',
    ],

    'sections' => [
        'content' => 'Felugró tartalom',
        'timing' => 'Időzítés és láthatóság',
    ],

    'form' => [
        'title' => [
            'label' => 'Cím',
        ],
        'body' => [
            'label' => 'Szövegtörzs',
        ],
        'discount_code' => [
            'label' => 'Kedvezménykód',
            'helper' => 'Opcionális kód, amelyet a látogatók a felugró ablakból másolhatnak.',
        ],
        'cta_label' => [
            'label' => 'Gomb felirat',
        ],
        'cta_url' => [
            'label' => 'Gomb URL',
        ],
        'display_on' => [
            'label' => 'Megjelenítés ezeken az oldalakon',
            'helper' => 'A felugró ablak csak a kiválasztott oldalakon jelenik meg.',
            'options' => [
                'home' => 'Kezdőoldal',
                'collection' => 'Kollekció / listázó oldalak',
                'product' => 'Termékoldalak',
                'search' => 'Keresési eredmények',
                'cart' => 'Kosár',
                'checkout' => 'Pénztár',
                'blog' => 'Blog',
                'other' => 'Egyéb oldalak',
            ],
        ],
        'delay_seconds' => [
            'label' => 'Késleltetés (másodperc)',
            'helper' => 'Mennyi ideig várjon az oldal betöltése után a felugró megjelenítése előtt.',
        ],
        'show_once' => [
            'label' => 'Csak egyszer',
            'helper' => 'Ha be van kapcsolva, az elutasított felugró ablak rejtve marad ennél a látogatónál.',
        ],
        'is_active' => [
            'label' => 'Aktív',
        ],
        'sort_order' => [
            'label' => 'Sorrend',
        ],
        'starts_at' => [
            'label' => 'Kezdés',
            'helper' => 'Opcionális. A felugró ablak csak ettől a dátumtól/időponttól jelenik meg.',
        ],
        'ends_at' => [
            'label' => 'Befejezés',
            'helper' => 'Opcionális. A felugró ablak ezután a dátum/időpont után nem jelenik meg.',
        ],
    ],

    'table' => [
        'title' => [
            'label' => 'Cím',
        ],
        'delay_seconds' => [
            'label' => 'Késleltetés',
        ],
        'display_on' => [
            'label' => 'Oldalak',
            'none' => 'Nincs',
        ],
        'is_active' => [
            'label' => 'Aktív',
        ],
        'show_once' => [
            'label' => 'Csak egyszer',
        ],
        'starts_at' => [
            'label' => 'Kezdés',
        ],
        'ends_at' => [
            'label' => 'Befejezés',
        ],
    ],
];
