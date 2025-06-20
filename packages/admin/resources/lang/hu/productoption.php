<?php

return [

    'label' => 'Termékopció',

    'plural_label' => 'Termékopciók',

    'table' => [
        'name' => [
            'label' => 'Név',
        ],
        'label' => [
            'label' => 'Címke',
        ],
        'handle' => [
            'label' => 'Azonosító',
        ],
        'shared' => [
            'label' => 'Megosztott',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Név',
        ],
        'label' => [
            'label' => 'Címke',
        ],
        'handle' => [
            'label' => 'Azonosító',
        ],
    ],

    'values' => [
        'title' => 'Értékek',
        'form' => [
            'name' => [
                'label' => 'Név',
            ],
        ],
        'table' => [
            'name' => [
                'label' => 'Név',
            ],
            'position' => [
                'label' => 'Pozíció',
            ],
            'actions' => [
                'create' => [
                    'label' => 'Új termékopció érték',
                    'heading' => 'Termékopció érték létrehozása',
                ],
                'edit' => [
                    'heading' => 'Termékopció érték szerkesztése',
                ],
                'delete' => [
                    'heading' => 'Termékopció érték törlése',
                    'bulk' => [
                        'heading' => 'Kiválasztott termékopció értékek törlése',
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
                        'title' => 'Termékváltozatok elmentve',
                    ],
                ],
            ],
            'actions' => [
                'cancel' => [
                    'label' => 'Mégse',
                ],
                'save-options' => [
                    'label' => 'Opciók mentése',
                ],
                'add-shared-option' => [
                    'label' => 'Megosztott opció hozzáadása',
                    'form' => [
                        'product_option' => [
                            'label' => 'Termékopció',
                        ],
                        'no_shared_components' => [
                            'label' => 'Nincsenek elérhető megosztott opciók.',
                        ],
                    ],
                    'modal' => [
                        'heading' => 'Megosztott opció hozzáadása',
                    ],
                ],
                'add-restricted-option' => [
                    'label' => 'Opció hozzáadása',
                ],
            ],
            'options-list' => [
                'empty' => [
                    'heading' => 'Nincsenek beállított termékopciók',
                    'description' => 'Adj hozzá megosztott vagy korlátozott termékopciót a változatok létrehozásához.',
                ],
            ],
            'options-table' => [
                'title' => 'Termékopciók',
                'configure-options' => [
                    'label' => 'Opciók konfigurálása',
                ],
                'table' => [
                    'option' => [
                        'label' => 'Opció',
                    ],
                    'values' => [
                        'label' => 'Értékek',
                    ],
                ],
            ],
            'variants-table' => [
                'title' => 'Termékváltozatok',
                'actions' => [
                    'create' => [
                        'label' => 'Változat létrehozása',
                    ],
                    'edit' => [
                        'label' => 'Szerkesztés',
                    ],
                    'delete' => [
                        'label' => 'Törlés',
                    ],
                ],
                'empty' => [
                    'heading' => 'Nincsenek beállított változatok',
                ],
                'table' => [
                    'new' => [
                        'label' => 'ÚJ',
                    ],
                    'option' => [
                        'label' => 'Opció',
                    ],
                    'sku' => [
                        'label' => 'SKU (Egyedi azonosító)',
                    ],
                    'price' => [
                        'label' => 'Ár',
                    ],
                    'stock' => [
                        'label' => 'Készlet',
                    ],
                ],
            ],
        ],
    ],

];
