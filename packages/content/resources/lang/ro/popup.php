<?php

return [
    'label' => 'Popup',
    'plural_label' => 'Popup-uri',

    'edit' => [
        'label' => 'Editare popup',
    ],

    'sections' => [
        'content' => 'Conținut popup',
        'timing' => 'Temporizare și vizibilitate',
    ],

    'form' => [
        'title' => [
            'label' => 'Titlu',
        ],
        'body' => [
            'label' => 'Conținut',
        ],
        'discount_code' => [
            'label' => 'Cod de discount',
            'helper' => 'Cod opțional afișat în popup pe care vizitatorii îl pot copia.',
        ],
        'cta_label' => [
            'label' => 'Etichetă buton',
        ],
        'cta_url' => [
            'label' => 'URL buton',
        ],
        'display_on' => [
            'label' => 'Afișare pe pagini',
            'helper' => 'Popup-ul apare doar pe paginile selectate.',
            'options' => [
                'home' => 'Pagina principală',
                'collection' => 'Pagini de colecție / listă',
                'product' => 'Pagini de produs',
                'search' => 'Rezultate căutare',
                'cart' => 'Coș',
                'checkout' => 'Checkout',
                'blog' => 'Blog',
                'other' => 'Alte pagini',
            ],
        ],
        'delay_seconds' => [
            'label' => 'Întârziere (secunde)',
            'helper' => 'Cât timp să aștepte după încărcarea paginii înainte de a afișa popup-ul.',
        ],
        'width_percentage' => [
            'label' => 'Lățime popup',
            'helper' => 'Lățimea popup-ului ca procent din viewport (30-100%).',
        ],
        'show_once' => [
            'label' => 'Afișare o singură dată',
            'helper' => 'Dacă este activat, popup-urile închise rămân ascunse pentru acest vizitator.',
        ],
        'is_active' => [
            'label' => 'Activ',
        ],
        'sort_order' => [
            'label' => 'Ordine',
        ],
        'starts_at' => [
            'label' => 'Începe la',
            'helper' => 'Opțional. Popup-ul apare doar de la această dată/oră.',
        ],
        'ends_at' => [
            'label' => 'Se termină la',
            'helper' => 'Opțional. Popup-ul nu mai apare după această dată/oră.',
        ],
    ],

    'table' => [
        'title' => [
            'label' => 'Titlu',
        ],
        'delay_seconds' => [
            'label' => 'Întârziere',
        ],
        'display_on' => [
            'label' => 'Pagini',
            'none' => 'Niciuna',
        ],
        'is_active' => [
            'label' => 'Activ',
        ],
        'show_once' => [
            'label' => 'O singură dată',
        ],
        'starts_at' => [
            'label' => 'Începe la',
        ],
        'ends_at' => [
            'label' => 'Se termină la',
        ],
    ],
];
