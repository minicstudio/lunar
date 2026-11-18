<?php

return [

    'label' => 'Productoptie',

    'plural_label' => 'Productopties',

    'table' => [
        'name' => [
            'label' => 'Naam',
        ],
        'label' => [
            'label' => 'Label',
        ],
        'handle' => [
            'label' => 'Handvat',
        ],
        'shared' => [
            'label' => 'Gedeeld',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Naam',
        ],
        'label' => [
            'label' => 'Label',
        ],
        'handle' => [
            'label' => 'Handvat',
        ],
    ],

    'delete' => [
        'error' => [
            'title' => 'Productoptie kan niet worden verwijderd',
            'body' => 'Deze productoptie wordt door producten gebruikt en kan niet worden verwijderd.',
        ],
    ],

    'values' => [
        'delete' => [
            'error' => [
                'title' => 'Productoptiewaarde kan niet worden verwijderd',
                'body' => 'Deze productoptiewaarde wordt door producten gebruikt en kan niet worden verwijderd.',
            ],
        ],
    ],

    'widgets' => [
        'product-options' => [
            'notifications' => [
                'save-variants' => [
                    'success' => [
                        'title' => 'Productvarianten opgeslagen',
                    ],
                ],
            ],
            'actions' => [
                'cancel' => [
                    'label' => 'Annuleren',
                ],
                'save-options' => [
                    'label' => 'Opties opslaan',
                ],
                'add-shared-option' => [
                    'label' => 'Gedeelde optie toevoegen',
                    'form' => [
                        'product_option' => [
                            'label' => 'Productoptie',
                        ],
                        'no_shared_components' => [
                            'label' => 'Er zijn geen gedeelde opties beschikbaar.',
                        ],
                    ],
                ],
                'add-restricted-option' => [
                    'label' => 'Optie toevoegen',
                ],
            ],
            'options-list' => [
                'empty' => [
                    'heading' => 'Er zijn geen productopties geconfigureerd',
                    'description' => 'Voeg een gedeelde of beperkte productoptie toe om enkele varianten te genereren.',
                ],
            ],
            'options-table' => [
                'title' => 'Productopties',
                'configure-options' => [
                    'label' => 'Opties configureren',
                ],
                'table' => [
                    'option' => [
                        'label' => 'Optie',
                    ],
                    'values' => [
                        'label' => 'Waarden',
                    ],
                ],
            ],
            'variants-table' => [
                'title' => 'Productvarianten',
                'actions' => [
                    'create' => [
                        'label' => 'Variant maken',
                    ],
                    'edit' => [
                        'label' => 'Bewerken',
                    ],
                    'delete' => [
                        'label' => 'Verwijderen',
                    ],
                ],
                'empty' => [
                    'heading' => 'Geen varianten geconfigureerd',
                ],
                'table' => [
                    'new' => [
                        'label' => 'NIEUW',
                    ],
                    'option' => [
                        'label' => 'Optie',
                    ],
                    'sku' => [
                        'label' => 'SKU',
                    ],
                    'price' => [
                        'label' => 'Prijs',
                    ],
                    'stock' => [
                        'label' => 'Voorraad',
                    ],
                ],
            ],
        ],
    ],

];
