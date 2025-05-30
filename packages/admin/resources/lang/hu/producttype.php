<?php

return [

    'label' => 'Terméktípus',

    'plural_label' => 'Terméktípusok',

    'table' => [
        'name' => [
            'label' => 'Név',
        ],
        'products_count' => [
            'label' => 'Termékek száma',
        ],
        'product_attributes_count' => [
            'label' => 'Termék attribútumok',
        ],
        'variant_attributes_count' => [
            'label' => 'Változat attribútumok',
        ],
    ],

    'tabs' => [
        'product_attributes' => [
            'label' => 'Termék attribútumok',
        ],
        'variant_attributes' => [
            'label' => 'Változat attribútumok',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Név',
        ],
    ],

    'attributes' => [
        'no_groups' => 'Nincsenek elérhető attribútum csoportok.',
        'no_attributes' => 'Nincsenek elérhető attribútumok.',
    ],

    'action' => [
        'delete' => [
            'notification' => [
                'error_protected' => 'Ezt a terméktípust nem lehet törölni, mert vannak hozzá kapcsolódó termékek.',
            ],
        ],
    ],

];
