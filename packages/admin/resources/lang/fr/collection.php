<?php

return [

    'label' => 'Collection',

    'plural_label' => 'Collections',

    'form' => [
        'name' => [
            'label' => 'Nom',
        ],
    ],

    'pages' => [
        'children' => [
            'label' => 'Collections enfants',
            'actions' => [
                'create_child' => [
                    'label' => 'Créer une collection enfant',
                    'name' => [
                        'label' => 'Nom',
                    ],
                ],
            ],
            'table' => [
                'children_count' => [
                    'label' => 'Nbre d\'enfants',
                ],
                'name' => [
                    'label' => 'Nom',
                ],
            ],
        ],
        'edit' => [
            'label' => 'Informations de base',
            'actions' => [
                'delete' => [
                    'select' => 'Collection cible',
                    'helper_text' => 'Choisissez vers quelle collection les enfants de cette collection doivent être transférés.'
                ],
            ]
        ],
        'products' => [
            'label' => 'Produits',
            'actions' => [
                'attach' => [
                    'label' => 'Associer un produit',
                    'select' => 'Produit',
                ],
                'detach' => [
                    'modal' => [
                        'heading' => 'Détacher le produit',
                    ]
                ],
            ],
        ],
    ],
    'nested_set_item' => [
        'more_actions' => 'Plus de propositions',
    ],
];
