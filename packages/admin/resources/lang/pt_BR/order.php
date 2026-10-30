<?php

return [

    'label' => 'Pedido',

    'plural_label' => 'Pedidos',

    'statuses' => [
        'split' => 'Dividido',
    ],

    'breadcrumb' => [
        'manage' => 'Gerenciar',
    ],

    'tabs' => [
        'all' => 'Todos',
    ],

    'transactions' => [
        'capture' => 'Capturado',
        'intent' => 'Intenção',
        'refund' => 'Reembolsado',
        'failed' => 'Falhou',
    ],

    'table' => [
        'status' => [
            'label' => 'Status',
        ],
        'reference' => [
            'label' => 'Referência',
        ],
        'customer_reference' => [
            'label' => 'Referência do cliente',
        ],
        'customer' => [
            'label' => 'Cliente',
        ],
        'tags' => [
            'label' => 'Tags',
            'no_options_message' => 'Nenhuma tag encontrada',
        ],
        'postcode' => [
            'label' => 'CEP',
        ],
        'email' => [
            'label' => 'E-mail',
            'copy_message' => 'Endereço de e-mail copiado',
        ],
        'phone' => [
            'label' => 'Telefone',
        ],
        'total' => [
            'label' => 'Total',
        ],
        'date' => [
            'label' => 'Data',
        ],
        'new_customer' => [
            'label' => 'Tipo de cliente',
        ],
        'payment_type' => [
            'label' => 'Tipo de pagamento',
            'options' => [
                'offline' => 'Offline',
                'card' => 'Cartão',
            ],
        ],
        'total_from' => [
            'label' => 'Total a partir de',
        ],
        'total_to' => [
            'label' => 'Total até',
        ],
        'placed_after' => [
            'label' => 'Realizado após',
        ],
        'placed_before' => [
            'label' => 'Realizado antes',
        ],
        'show_draft_orders' => [
            'label' => 'Mostrar pedidos não finalizados',
        ],
    ],

    'form' => [
        'address' => [
            'first_name' => [
                'label' => 'Nome',
            ],
            'last_name' => [
                'label' => 'Sobrenome',
            ],
            'line_one' => [
                'label' => 'Endereço - linha 1',
            ],
            'line_two' => [
                'label' => 'Endereço - linha 2',
            ],
            'line_three' => [
                'label' => 'Endereço - linha 3',
            ],
            'company_name' => [
                'label' => 'Nome da empresa',
            ],
            'tax_identifier' => [
                'label' => 'Identificador fiscal',
            ],
            'contact_phone' => [
                'label' => 'Telefone',
            ],
            'contact_email' => [
                'label' => 'Endereço de e-mail',
            ],
            'city' => [
                'label' => 'Cidade',
            ],
            'state' => [
                'label' => 'Estado / Província',
            ],
            'postcode' => [
                'label' => 'CEP',
            ],
            'country_id' => [
                'label' => 'País',
            ],
        ],

        'reference' => [
            'label' => 'Referência',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'transaction' => [
            'label' => 'Transação',
        ],
        'amount' => [
            'label' => 'Valor',

            'hint' => [
                'less_than_total' => 'Você está prestes a capturar um valor menor que o total da transação',
            ],
        ],

        'notes' => [
            'label' => 'Notas',
        ],
        'confirm' => [
            'label' => 'Confirmar',

            'alert' => 'Confirmação necessária',

            'hint' => [
                'capture' => 'Confirme que deseja capturar este pagamento',
                'refund' => 'Confirme que deseja reembolsar este valor.',
            ],
        ],
    ],

    'infolist' => [
        'notes' => [
            'label' => 'Notas',
            'placeholder' => 'Sem notas neste pedido',
        ],
        'delivery_instructions' => [
            'label' => 'Instruções de entrega',
        ],
        'shipping_total' => [
            'label' => 'Total de frete',
        ],
        'paid' => [
            'label' => 'Pago',
        ],
        'refund' => [
            'label' => 'Reembolso',
        ],
        'unit_price' => [
            'label' => 'Preço unitário',
        ],
        'quantity' => [
            'label' => 'Quantidade',
        ],
        'sub_total' => [
            'label' => 'Subtotal',
        ],
        'discount_total' => [
            'label' => 'Total de desconto',
        ],
        'total' => [
            'label' => 'Total',
        ],
        'preorder' => [
            'message' => 'Este produto está disponível apenas para pré-venda.',
        ],
        'current_stock_level' => [
            'message' => 'Nível de estoque atual: :count',
        ],
        'purchase_stock_level' => [
            'message' => 'no momento do pedido: :count',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'reference' => [
            'label' => 'Referência',
        ],
        'customer_reference' => [
            'label' => 'Referência do cliente',
        ],
        'channel' => [
            'label' => 'Canal',
        ],
        'date_created' => [
            'label' => 'Data de criação',
        ],
        'date_placed' => [
            'label' => 'Data do pedido',
        ],
        'new_returning' => [
            'label' => 'Novo / Recorrente',
        ],
        'new_customer' => [
            'label' => 'Cliente novo',
        ],
        'returning_customer' => [
            'label' => 'Cliente recorrente',
        ],
        'shipping_address' => [
            'label' => 'Endereço de entrega',
        ],
        'billing_address' => [
            'label' => 'Endereço de cobrança',
        ],
        'address_not_set' => [
            'label' => 'Nenhum endereço definido',
        ],
        'billing_matches_shipping' => [
            'label' => 'Igual ao endereço de entrega',
        ],
        'additional_info' => [
            'label' => 'Informações adicionais',
        ],
        'no_additional_info' => [
            'label' => 'Sem informações adicionais',
        ],
        'shipping_details' => [
            'label' => 'Detalhes de envio',
        ],
        'billing_details' => [
            'label' => 'Detalhes de faturamento',
        ],
        'meta' => [
            'shippingType' => 'Tipo de envio',
            'shipping_zone' => 'Zona de envio',
            'awb' => 'Número AWB',
            'payment_option' => 'Forma de pagamento',
            'payment_type' => 'Tipo de pagamento',
            'isBillingSameAsShipping' => 'Endereço de faturamento igual ao de envio',
            'billing_series' => 'Série da fatura',
            'billing_number' => 'Número da fatura',
            'lunar_invoice_generated_at' => 'Fatura Lunar gerada em',
            'invoice_emailed_at' => 'Fatura enviada por e-mail em',
        ],
        'shipping_types' => [
            'courier' => 'Transportadora',
            'locker' => 'Armário de encomendas',
            'pickup' => 'Retirada na loja',
        ],
        'payment_methods' => [
            'offline' => 'Offline',
            'cash-in-hand' => 'Dinheiro',
            'cash-on-delivery' => 'Pagamento na entrega',
            'bank-transfer' => 'Transferência bancária',
            'stripe-card' => 'Cartão de crédito',
            'stripe-wallet-pay' => 'Apple Pay / Google Pay',
            'hosted-payment' => 'Pagamento online com cartão',
        ],
        'tags' => [
            'label' => 'Tags',
        ],
        'timeline' => [
            'label' => 'Linha do tempo',
        ],
        'transactions' => [
            'label' => 'Transações',
            'placeholder' => 'Sem transações',
        ],
        'alert' => [
            'draft_order' => 'Este pedido ainda não foi finalizado.',
            'requires_capture' => 'Este pedido ainda requer captura do pagamento.',
            'partially_refunded' => 'Este pedido foi parcialmente reembolsado.',
            'refunded' => 'Este pedido foi reembolsado.',
            'product_line_gates' => [
                'paid' => 'Pago: este pedido não pode ser modificado porque foi pago.',
                'erp' => 'ERP: este pedido não pode ser modificado porque foi enviado ao ERP.',
                'invoice' => 'Fatura: este pedido não pode ser modificado porque tem uma fatura.',
                'awb' => 'AWB: este pedido não pode ser modificado porque tem um AWB.',
                'split' => 'Split: this order has been split and cannot be modified.',
            ],
            'split_erp_warning' => 'This order may already have been sent to the ERP. Splitting it will not update that external order.',
        ],
        'split_from' => [
            'label' => 'Split from',
        ],
        'split_into' => [
            'label' => 'Split into',
        ],
    ],

    'action' => [
        'bulk_update_status' => [
            'label' => 'Atualizar status',
            'notification' => 'Status dos pedidos atualizado',
        ],
        'update_status' => [
            'new_status' => [
                'label' => 'Novo status',
            ],
            'additional_content' => [
                'label' => 'Conteúdo adicional',
            ],
            'additional_email_recipient' => [
                'label' => 'Destinatário de e-mail adicional',
                'placeholder' => 'opcional',
            ],
        ],
        'download_order_pdf' => [
            'label' => 'Baixar PDF',
            'notification' => 'Baixando PDF do pedido',
        ],
        'edit_address' => [
            'label' => 'Editar',

            'notification' => [
                'error' => 'Erro',

                'billing_address' => [
                    'saved' => 'Endereço de cobrança salvo',
                ],

                'shipping_address' => [
                    'saved' => 'Endereço de entrega salvo',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Editar',
            'form' => [
                'tags' => [
                    'label' => 'Tags',
                    'helper_text' => 'Separe tags pressionando Enter, Tab ou vírgula (,)',
                ],
            ],
        ],
        'capture_payment' => [
            'label' => 'Capturar pagamento',

            'notification' => [
                'error' => 'Houve um problema na captura',
                'success' => 'Captura realizada com sucesso',
            ],
        ],
        'refund_payment' => [
            'label' => 'Reembolsar',

            'notification' => [
                'error' => 'Houve um problema no reembolso',
                'success' => 'Reembolso realizado com sucesso',
            ],
        ],
        'add_product' => [
            'label' => 'Adicionar produto',
            'product' => [
                'label' => 'Produto',
            ],
            'variant' => [
                'label' => 'Variante',
            ],
            'quantity' => [
                'label' => 'Quantidade',
            ],
            'notification' => 'Produto adicionado',
        ],
        'update_quantity' => [
            'label' => 'Atualizar quantidade',
            'quantity' => [
                'label' => 'Quantidade',
            ],
            'notification' => 'Quantidade atualizada',
        ],
        'remove_product' => [
            'label' => 'Remover produto',
            'confirmation' => 'Remover este produto do pedido?',
            'notification' => 'Produto removido',
        ],
        'product_line_edit' => [
            'last_line' => 'Um pedido deve manter pelo menos um produto.',
            'unauthorized' => 'Você não pode alterar os produtos deste pedido.',
            'cart' => 'Este pedido não tem carrinho, então os produtos não podem ser alterados.',
            'line' => 'Essa linha de produto não foi encontrada.',
            'purchasable' => 'Essa variante não foi encontrada.',
            'shipping' => 'A opção de frete não pode ser precificada, então o pedido permaneceu inalterado.',
            'locked' => 'Este pedido não pode mais ser editado.',
            'not_purchasable' => 'Esta variante não pode ser comprada.',
            'out_of_stock' => 'Esta variante está sem estoque.',
            'insufficient_stock' => 'Não há estoque suficiente para esta quantidade.',
            'missing_price' => 'Esta variante não tem preço na moeda do pedido.',
        ],
        'split_order' => [
            'label' => 'Split order',
            'heading' => 'Split order',
            'helper' => 'Assign every product to one order. The preview on the right shows that order\'s products, total, shipping, and payment.',
            'products' => 'Products',
            'preview' => 'Preview',
            'empty_preview' => 'No products are assigned to this order.',
            'new_order' => 'New order',
            'move_to_order' => 'Move to order:',
            'divide_quantity' => 'Divide quantity',
            'price' => 'Price',
            'price_changed' => 'The price of this product has changed since the order was placed.',
            'original_price' => 'Original price',
            'current_price' => 'Current price',
            'shipping' => 'Shipping',
            'shipping_unavailable' => 'Automatic pricing was unavailable. Enter a shipping cost.',
            'payment_method' => 'Payment method',
            'order_total' => 'Order total',
            'coupon' => 'Coupon',
            'loyalty' => 'Loyalty redemption',
            'do_not_apply' => 'Do not apply',
            'confirm' => 'Split order',
            'notification' => 'Order split',
            'invalid' => 'The split is not ready to confirm.',
            'gates' => [
                'closed' => 'This order can no longer be split.',
                'paid' => 'This order has been paid and cannot be split.',
                'invoice' => 'This order has an invoice and cannot be split.',
                'awb' => 'This order has an AWB and cannot be split.',
                'erp' => 'This order has been sent to the ERP and cannot be split.',
                'stock' => 'Stock was already taken for this order. Splitting it does not take stock again.',
                'unauthorized' => 'You cannot split this order.',
                'unassigned' => 'Assign every unit before confirming.',
                'assigned_twice' => 'A unit is assigned to more than one order.',
                'lines_mismatch' => 'The assignment does not match the order.',
                'too_few_targets' => 'A split needs at least two orders.',
                'negative_shipping' => 'Shipping cannot be negative.',
                'coupon' => 'Choose where the coupon goes.',
                'loyalty' => 'Choose where the loyalty redemption goes.',
                'loyalty_spent' => 'Loyalty points were already spent, so they must stay on one order.',
                'loyalty_cannot_accept' => 'The chosen order cannot take all of the loyalty points that were already spent.',
                'shipping_required' => 'Enter a shipping cost for every order.',
                'payment_required' => 'Choose a payment method for every order.',
            ],
        ],
    ],

];
