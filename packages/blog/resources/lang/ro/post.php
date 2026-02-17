<?php

return [
    'label' => 'Postare',
    'plural_label' => 'Postări',

    'edit' => [
        'label' => 'Informații de bază',
    ],

    'form' => [
        'title' => [
            'label' => 'Titlu',
        ],
        'handle' => [
            'label' => 'Identificator',
        ],
        'status' => [
            'label' => 'Status ',
            'options' => [
                'published' => [
                    'label' => 'Publicat',
                    'description' => 'Această postare va fi disponibilă pe toate canalele activate',
                ],
                'draft' => [
                    'label' => 'Ciornă',
                    'description' => 'Această postare va fi ascunsă pe toate canalele',
                ],
            ],
        ],
        'categories' => [
            'title' => [
                'label' => 'Categorii',
            ],
        ],
    ],

    'section' => [
        'categories' => [
            'title' => 'Categorii de blog',
            'description' => 'Gestionați categoriile pentru această postare pe blog. Selectați o categorie existentă sau creați una nouă, dacă este necesar.',
            'action' => [
                'label' => 'Categorie nouă',
                'modal' => [
                    'heading' => 'Crearea categoriei',
                    'submit' => 'Creare',
                ],
                'notification' => [
                    'title' => 'Categorie Creată',
                    'body' => 'Categoria a fost creată cu succes.',
                ],
            ],
        ],
    ],

    'table' => [
        'title' => [
            'label' => 'Titlu',
        ],
        'status' => [
            'label' => 'Status',
            'states' => [
                'draft' => 'Ciornă',
                'published' => 'Publicat',
            ],
        ],
        'author' => [
            'label' => 'Autor',
        ],
    ],

    'actions' => [
        'edit_status' => [
            'label' => 'Actualizare Status',
            'heading' => 'Actualizare Status',
        ],
        'preview' => [
            'label' => 'Previzualizare',
        ],
    ],

    'pages' => [
        'availability' => [
            'label' => 'Disponibilitate',
        ],
    ],

    'filters' => [
        'status' => [
            'label' => 'Status',
            'placeholder' => 'Selectează status',
            'options' => [
                'draft' => 'Ciornă',
                'published' => 'Publicat',
            ],
        ],
        'author' => [
            'label' => 'Autor',
            'placeholder' => 'Selectează un autor',
        ],
    ],
];
