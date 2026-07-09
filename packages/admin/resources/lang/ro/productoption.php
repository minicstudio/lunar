<?php

return [

    'label' => 'Opțiune produs',

    'plural_label' => 'Opțiuni produs',

    'table' => [
        'name' => [
            'label' => 'Nume',
        ],
        'label' => [
            'label' => 'Etichetă',
        ],
        'handle' => [
            'label' => 'Identificator',
        ],
        'shared' => [
            'label' => 'Partajată',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Nume',
        ],
        'label' => [
            'label' => 'Etichetă',
        ],
        'handle' => [
            'label' => 'Identificator',
        ],
    ],

    'values' => [
        'title' => 'Valori',
        'form' => [
            'name' => [
                'label' => 'Nume',
            ],
            'swatch' => [
                'label' => 'Mostră',
            ],
            'multicolor' => [
                'label' => 'Multicolor',
            ],
        ],
        'table' => [
            'name' => [
                'label' => 'Nume',
            ],
            'position' => [
                'label' => 'Poziție',
            ],
            'swatch' => [
                'label' => 'Mostră',
            ],
            'actions' => [
                'create' => [
                    'label' => 'Valoare nouă opțiune produs',
                    'heading' => 'Creare valoare opțiune produs',
                ],
                'edit' => [
                    'heading' => 'Editare valoare opțiune produs',
                ],
                'delete' => [
                    'heading' => 'Ștergere valoare opțiune produs',
                    'bulk' => [
                        'heading' => 'Ștergere valori selectate ale opțiunilor de produs',
                    ],
                ],
            ],
        ],
    ],

    'widgets' => [
        'product-options' => [
            'notifications' => [
                'save-variants' => [
                    'success' => [
                        'title' => 'Variantele de produs au fost salvate',
                    ],
                ],
            ],
            'actions' => [
                'cancel' => [
                    'label' => 'Anulează',
                ],
                'save-options' => [
                    'label' => 'Salvează opțiunile',
                ],
                'add-shared-option' => [
                    'label' => 'Adaugă opțiune partajată',
                    'form' => [
                        'product_option' => [
                            'label' => 'Opțiune produs',
                        ],
                        'no_shared_components' => [
                            'label' => 'Nu există opțiuni partajate disponibile.',
                        ],
                        'preselect' => [
                            'label' => 'Preselectează implicit toate valorile.',
                        ],
                    ],
                ],
                'add-restricted-option' => [
                    'label' => 'Adaugă opțiune',
                ],
            ],
            'options-list' => [
                'empty' => [
                    'heading' => 'Nu există opțiuni de produs configurate',
                    'description' => 'Adaugă o opțiune partajată sau restricționată pentru a începe generarea variantelor.',
                ],
            ],
            'options-table' => [
                'title' => 'Opțiuni produs',
                'configure-options' => [
                    'label' => 'Configurează opțiunile',
                ],
                'table' => [
                    'option' => [
                        'label' => 'Opțiune',
                    ],
                    'values' => [
                        'label' => 'Valori',
                    ],
                ],
            ],
            'variants-table' => [
                'title' => 'Variante de produs',
                'actions' => [
                    'create' => [
                        'label' => 'Creează variantă',
                    ],
                    'edit' => [
                        'label' => 'Editează',
                    ],
                    'delete' => [
                        'label' => 'Șterge',
                    ],
                ],
                'empty' => [
                    'heading' => 'Nu există variante configurate',
                ],
                'table' => [
                    'new' => [
                        'label' => 'NOU',
                    ],
                    'option' => [
                        'label' => 'Opțiune',
                    ],
                    'sku' => [
                        'label' => 'Cod stoc intern (SKU)',
                    ],
                    'price' => [
                        'label' => 'Preț',
                    ],
                    'stock' => [
                        'label' => 'Stoc',
                    ],
                ],
            ],
        ],
    ],

];
