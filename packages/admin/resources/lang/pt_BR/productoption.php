<?php

return [

    'label' => 'Opção de produto',

    'plural_label' => 'Opções de produto',

    'table' => [
        'name' => [
            'label' => 'Nome',
        ],
        'label' => [
            'label' => 'Rótulo',
        ],
        'handle' => [
            'label' => 'Identificador',
        ],
        'shared' => [
            'label' => 'Compartilhada',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Nome',
        ],
        'label' => [
            'label' => 'Rótulo',
        ],
        'handle' => [
            'label' => 'Identificador',
        ],
    ],

    'delete' => [
        'error' => [
            'title' => 'Não é possível excluir a opção de produto',
            'body' => 'Esta opção de produto é usada por produtos e não pode ser excluída.',
        ],
    ],

    'values' => [
        'delete' => [
            'error' => [
                'title' => 'Não é possível excluir o valor da opção de produto',
                'body' => 'Este valor de opção de produto é usado por produtos e não pode ser excluído.',
            ],
        ],
    ],

    'widgets' => [
        'product-options' => [
            'notifications' => [
                'save-variants' => [
                    'success' => [
                        'title' => 'Variações de produto salvas',
                    ],
                ],
            ],
            'actions' => [
                'cancel' => [
                    'label' => 'Cancelar',
                ],
                'save-options' => [
                    'label' => 'Salvar opções',
                ],
                'add-shared-option' => [
                    'label' => 'Adicionar opção compartilhada',
                    'form' => [
                        'product_option' => [
                            'label' => 'Opção de produto',
                        ],
                        'no_shared_components' => [
                            'label' => 'Não há opções compartilhadas disponíveis.',
                        ],
                        'preselect' => [
                            'label' => 'Pré-selecionar todos os valores por padrão.',
                        ],
                    ],
                ],
                'add-restricted-option' => [
                    'label' => 'Adicionar opção',
                ],
            ],
            'options-list' => [
                'empty' => [
                    'heading' => 'Não há opções de produto configuradas',
                    'description' => 'Adicione uma opção de produto compartilhada ou restrita para começar a gerar variações.',
                ],
            ],
            'options-table' => [
                'title' => 'Opções de produto',
                'configure-options' => [
                    'label' => 'Configurar opções',
                ],
                'table' => [
                    'option' => [
                        'label' => 'Opção',
                    ],
                    'values' => [
                        'label' => 'Valores',
                    ],
                ],
            ],
            'variants-table' => [
                'title' => 'Variações de produto',
                'actions' => [
                    'create' => [
                        'label' => 'Criar variação',
                    ],
                    'edit' => [
                        'label' => 'Editar',
                    ],
                    'delete' => [
                        'label' => 'Excluir',
                    ],
                ],
                'empty' => [
                    'heading' => 'Nenhuma variação configurada',
                ],
                'table' => [
                    'new' => [
                        'label' => 'NOVO',
                    ],
                    'option' => [
                        'label' => 'Opção',
                    ],
                    'sku' => [
                        'label' => 'SKU',
                    ],
                    'price' => [
                        'label' => 'Preço',
                    ],
                    'stock' => [
                        'label' => 'Estoque',
                    ],
                ],
            ],
        ],
    ],

];
