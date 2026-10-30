<?php

return [

    'label' => 'Commande',

    'plural_label' => 'Commandes',

    'statuses' => [
        'split' => 'Divisée',
    ],

    'breadcrumb' => [
        'manage' => 'Gérer',
    ],

    'tabs' => [
        'all' => 'Toutes',
    ],

    'transactions' => [
        'capture' => 'Capturé',
        'intent' => 'Intention',
        'refund' => 'Remboursé',
        'failed' => 'Échoué',
    ],

    'table' => [
        'status' => [
            'label' => 'Statut',
        ],
        'reference' => [
            'label' => 'Référence',
        ],
        'customer_reference' => [
            'label' => 'Référence client',
        ],
        'customer' => [
            'label' => 'Client',
        ],
        'tags' => [
            'label' => 'Étiquettes',
            'no_options_message' => 'Aucune étiquette trouvée',
        ],
        'postcode' => [
            'label' => 'Code postal',
        ],
        'email' => [
            'label' => 'Email',
            'copy_message' => 'Adresse email copiée',
        ],
        'phone' => [
            'label' => 'Téléphone',
        ],
        'total' => [
            'label' => 'Total',
        ],
        'date' => [
            'label' => 'Date',
        ],
        'new_customer' => [
            'label' => 'Type de client',
        ],
        'payment_type' => [
            'label' => 'Type de paiement',
            'options' => [
                'offline' => 'Hors ligne',
                'card' => 'Carte',
            ],
        ],
        'total_from' => [
            'label' => 'Total à partir de',
        ],
        'total_to' => [
            'label' => 'Total jusqu\'à',
        ],
        'placed_after' => [
            'label' => 'Placée après',
        ],
        'placed_before' => [
            'label' => 'Placée avant',
        ],
        'show_draft_orders' => [
            'label' => 'Afficher les commandes non finalisées',
        ],
    ],

    'form' => [
        'address' => [
            'first_name' => [
                'label' => 'Prénom',
            ],
            'last_name' => [
                'label' => 'Nom de famille',
            ],
            'line_one' => [
                'label' => 'Adresse ligne 1',
            ],
            'line_two' => [
                'label' => 'Adresse ligne 2',
            ],
            'line_three' => [
                'label' => 'Adresse ligne 3',
            ],
            'company_name' => [
                'label' => 'Nom de l\'entreprise',
            ],
            'tax_identifier' => [
                'label' => 'Identifiant fiscal',
            ],
            'contact_phone' => [
                'label' => 'Téléphone',
            ],
            'contact_email' => [
                'label' => 'Adresse email',
            ],
            'city' => [
                'label' => 'Ville',
            ],
            'state' => [
                'label' => 'État / Province',
            ],
            'postcode' => [
                'label' => 'Code postal',
            ],
            'country_id' => [
                'label' => 'Pays',
            ],
        ],

        'reference' => [
            'label' => 'Référence',
        ],
        'status' => [
            'label' => 'Statut',
        ],
        'transaction' => [
            'label' => 'Transaction',
        ],
        'amount' => [
            'label' => 'Montant',

            'hint' => [
                'less_than_total' => 'Vous êtes sur le point de capturer un montant inférieur à la valeur totale de la transaction',
            ],
        ],

        'notes' => [
            'label' => 'Notes',
        ],
        'confirm' => [
            'label' => 'Confirmer',

            'alert' => 'Confirmation requise',

            'hint' => [
                'capture' => 'Veuillez confirmer que vous souhaitez capturer ce paiement',
                'refund' => 'Veuillez confirmer que vous souhaitez rembourser ce montant.',
            ],
        ],
    ],

    'infolist' => [
        'notes' => [
            'label' => 'Notes',
            'placeholder' => 'Pas de notes sur cette commande',
        ],
        'delivery_instructions' => [
            'label' => 'Instructions de livraison',
        ],
        'shipping_total' => [
            'label' => 'Total livraison',
        ],
        'paid' => [
            'label' => 'Payé',
        ],
        'refund' => [
            'label' => 'Remboursement',
        ],
        'unit_price' => [
            'label' => 'Prix unitaire',
        ],
        'quantity' => [
            'label' => 'Quantité',
        ],
        'sub_total' => [
            'label' => 'Sous-total',
        ],
        'discount_total' => [
            'label' => 'Total réduction',
        ],
        'total' => [
            'label' => 'Total',
        ],
        'preorder' => [
            'message' => 'Ce produit est disponible uniquement en précommande.',
        ],
        'current_stock_level' => [
            'message' => 'Niveau de stock actuel : :count',
        ],
        'purchase_stock_level' => [
            'message' => 'au moment de la commande : :count',
        ],
        'status' => [
            'label' => 'Statut',
        ],
        'reference' => [
            'label' => 'Référence',
        ],
        'customer_reference' => [
            'label' => 'Référence client',
        ],
        'channel' => [
            'label' => 'Canal',
        ],
        'date_created' => [
            'label' => 'Date de création',
        ],
        'date_placed' => [
            'label' => 'Date de placement',
        ],
        'new_returning' => [
            'label' => 'Nouveau / Récurrent',
        ],
        'new_customer' => [
            'label' => 'Nouveau client',
        ],
        'returning_customer' => [
            'label' => 'Client récurrent',
        ],
        'shipping_address' => [
            'label' => 'Adresse de livraison',
        ],
        'billing_address' => [
            'label' => 'Adresse de facturation',
        ],
        'address_not_set' => [
            'label' => 'Pas d\'adresse définie',
        ],
        'billing_matches_shipping' => [
            'label' => 'Identique à l\'adresse de livraison',
        ],
        'additional_info' => [
            'label' => 'Informations supplémentaires',
        ],
        'no_additional_info' => [
            'label' => 'Pas d\'informations supplémentaires',
        ],
        'shipping_details' => [
            'label' => 'Détails de livraison',
        ],
        'billing_details' => [
            'label' => 'Détails de facturation',
        ],
        'meta' => [
            'shippingType' => 'Type de livraison',
            'shipping_zone' => 'Zone de livraison',
            'awb' => 'Numéro AWB',
            'payment_option' => 'Mode de paiement',
            'payment_type' => 'Type de paiement',
            'isBillingSameAsShipping' => 'Adresse de facturation identique à l\'adresse de livraison',
            'billing_series' => 'Série de facture',
            'billing_number' => 'Numéro de facture',
            'lunar_invoice_generated_at' => 'Facture Lunar générée le',
            'invoice_emailed_at' => 'Facture envoyée par e-mail le',
        ],
        'shipping_types' => [
            'courier' => 'Coursier',
            'locker' => 'Consigne automatique',
            'pickup' => 'Retrait en magasin',
        ],
        'payment_methods' => [
            'offline' => 'Hors ligne',
            'cash-in-hand' => 'Espèces',
            'cash-on-delivery' => 'Paiement à la livraison',
            'bank-transfer' => 'Virement bancaire',
            'stripe-card' => 'Carte bancaire',
            'stripe-wallet-pay' => 'Apple Pay / Google Pay',
            'hosted-payment' => 'Paiement en ligne par carte',
        ],
        'tags' => [
            'label' => 'Étiquettes',
        ],
        'timeline' => [
            'label' => 'Chronologie',
        ],
        'transactions' => [
            'label' => 'Transactions',
            'placeholder' => 'Aucune transaction',
        ],
        'alert' => [
            'draft_order' => 'Cette commande n\'a pas encore été finalisée.',
            'requires_capture' => 'Cette commande nécessite toujours que le paiement soit capturé.',
            'partially_refunded' => 'Cette commande a été partiellement remboursée.',
            'refunded' => 'Cette commande a été remboursée.',
            'product_line_gates' => [
                'paid' => 'Payée : cette commande ne peut pas être modifiée, car elle a été payée.',
                'erp' => 'ERP : cette commande ne peut pas être modifiée, car elle a été envoyée vers ERP.',
                'invoice' => 'Facture : cette commande ne peut pas être modifiée, car elle a une facture.',
                'awb' => 'AWB : cette commande ne peut pas être modifiée, car elle a un AWB.',
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
            'label' => 'Mettre à jour le statut',
            'notification' => 'Statut des commandes mis à jour',
        ],
        'update_status' => [
            'label' => 'Update Status',
            'notification' => 'Order status updated',
            'new_status' => [
                'label' => 'Nouveau statut',
            ],
            'additional_content' => [
                'label' => 'Contenu supplémentaire',
            ],
            'additional_email_recipient' => [
                'label' => 'Destinataire email supplémentaire',
                'placeholder' => 'facultatif',
            ],
        ],
        'download_order_pdf' => [
            'label' => 'Télécharger le PDF',
            'notification' => 'Téléchargement du PDF de commande',
        ],
        'edit_address' => [
            'label' => 'Modifier',

            'notification' => [
                'error' => 'Erreur',

                'billing_address' => [
                    'saved' => 'Adresse de facturation enregistrée',
                ],

                'shipping_address' => [
                    'saved' => 'Adresse de livraison enregistrée',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Modifier',
        ],
        'capture_payment' => [
            'label' => 'Capturer le paiement',

            'notification' => [
                'error' => 'Il y a eu un problème avec la capture',
                'success' => 'Capture réussie',
            ],
        ],
        'refund_payment' => [
            'label' => 'Rembourser',

            'notification' => [
                'error' => 'Il y a eu un problème avec le remboursement',
                'success' => 'Remboursement réussi',
            ],
        ],
        'add_product' => [
            'label' => 'Ajouter un produit',
            'product' => [
                'label' => 'Produit',
            ],
            'variant' => [
                'label' => 'Variante',
            ],
            'quantity' => [
                'label' => 'Quantité',
            ],
            'notification' => 'Produit ajouté',
        ],
        'update_quantity' => [
            'label' => 'Modifier la quantité',
            'quantity' => [
                'label' => 'Quantité',
            ],
            'notification' => 'Quantité mise à jour',
        ],
        'remove_product' => [
            'label' => 'Retirer le produit',
            'confirmation' => 'Retirer ce produit de la commande ?',
            'notification' => 'Produit retiré',
        ],
        'product_line_edit' => [
            'last_line' => 'Une commande doit conserver au moins un produit.',
            'unauthorized' => 'Vous ne pouvez pas modifier les produits de cette commande.',
            'cart' => 'Cette commande n a pas de panier, ses produits ne peuvent donc pas être modifiés.',
            'line' => 'Cette ligne de produit est introuvable.',
            'purchasable' => 'Cette variante est introuvable.',
            'shipping' => 'Le mode de livraison ne peut pas être tarifé, la commande est donc inchangée.',
            'locked' => 'Cette commande ne peut plus être modifiée.',
            'not_purchasable' => 'Cette variante ne peut pas être achetée.',
            'out_of_stock' => 'Cette variante est en rupture de stock.',
            'insufficient_stock' => 'Le stock est insuffisant pour cette quantité.',
            'missing_price' => 'Cette variante n a pas de prix dans la devise de la commande.',
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
