<?php

return [

    'label' => 'Marque',

    'plural_label' => 'Marques',

    'table' => [
        'name' => [
            'label' => 'Nom',
        ],
        'products_count' => [
            'label' => 'Nbre de produits',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Nom',
        ],
    ],

    'action' => [
        'delete' => [
            'notification' => [
                'error_protected' => 'Cette marque ne peut pas être supprimée car des produits y sont associés.',
            ],
        ],
    ],
    'pages' => [
        'edit' => [
            'title' => 'Informations de base',
        ],
        'products' => [
            'label' => 'Produits',
            'actions' => [
                'attach' => [
                    'label' => 'Associer un produit',
                    'modal' => [
                        'heading' => 'Associer un produit',
                    ],
                    'form' => [
                        'record_id' => [
                            'label' => 'Produit',
                        ],
                    ],
                    'notification' => [
                        'success' => 'Produit associé à la marque',
                    ],
                ],
                'detach' => [
                    'notification' => [
                        'success' => 'Produit dissocié.',
                    ],
                    'modal' => [
                        'heading' => 'Dissocier le produit',
                    ],
                ],
            ],
        ],
        'collections' => [
            'label' => 'Collections',
            'table' => [
                'header_actions' => [
                    'attach' => [
                        'record_select' => [
                            'placeholder' => 'Sélectionner une collection',
                        ],
                    ],
                ],
            ],
            'actions' => [
                'attach' => [
                    'label' => 'Associer une collection',
                    'modal' => [
                        'heading' => 'Associer la collection',
                    ],
                ],
                'detach' => [
                    'modal' => [
                        'heading' => 'Dissocier la collection',
                    ],
                ],
            ],
        ],
    ],

];
