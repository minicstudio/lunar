<?php

return [

    'label' => 'Comandă',

    'plural_label' => 'Comenzi',

    'statuses' => [
        'split' => 'Împărțită',
    ],

    'breadcrumb' => [
        'manage' => 'Gestionează',
    ],

    'tabs' => [
        'all' => 'Toate',
    ],

    'transactions' => [
        'capture' => 'Capturat',
        'intent' => 'Intenție',
        'refund' => 'Rambursat',
        'failed' => 'Eșuat',
        'meta' => [
            'bt_order_number' => 'Număr comandă BT',
            'register_error_code' => 'Cod eroare înregistrare',
            'installments' => 'Număr de rate',
            'payment_way' => 'Mod de plată',
            'action_code' => 'Cod de răspuns (răspunsul băncii la operațiunea cu cardul)',
            'approved_amount' => 'Sumă aprobată',
            'loyalty_amount' => 'Plătit cu puncte StarBT',
            'loyalty_action_code' => 'Cod de răspuns puncte StarBT',
            'reverse_requested_at' => 'Deblocare sumă solicitată la',
            'reverse_trigger' => 'Motivul deblocării sumei',
        ],
    ],

    'table' => [
        'status' => [
            'label' => 'Stare',
        ],
        'reference' => [
            'label' => 'Referință',
        ],
        'customer_reference' => [
            'label' => 'Referință client',
        ],
        'customer' => [
            'label' => 'Client',
        ],
        'tags' => [
            'label' => 'Etichete',
            'no_options_message' => 'Nu s-au găsit etichete',
        ],
        'postcode' => [
            'label' => 'Cod poștal',
        ],
        'email' => [
            'label' => 'E-mail',
            'copy_message' => 'Adresă e-mail copiată',
        ],
        'phone' => [
            'label' => 'Telefon',
        ],
        'total' => [
            'label' => 'Total',
        ],
        'date' => [
            'label' => 'Data',
        ],
        'new_customer' => [
            'label' => 'Tip client',
        ],
        'payment_type' => [
            'label' => 'Tip de plată',
            'options' => [
                'offline' => 'Offline',
                'card' => 'Card',
            ],
        ],
        'total_from' => [
            'label' => 'Total de la',
        ],
        'total_to' => [
            'label' => 'Total până la',
        ],
        'placed_after' => [
            'label' => 'Plasată după',
        ],
        'placed_before' => [
            'label' => 'Plasată înainte de',
        ],
        'show_draft_orders' => [
            'label' => 'Afișează comenzile nefinalizate',
        ],
    ],

    'form' => [
        'address' => [
            'first_name' => [
                'label' => 'Prenume',
            ],
            'last_name' => [
                'label' => 'Nume',
            ],
            'line_one' => [
                'label' => 'Adresă linia 1',
            ],
            'line_two' => [
                'label' => 'Adresă linia 2',
            ],
            'line_three' => [
                'label' => 'Adresă linia 3',
            ],
            'company_name' => [
                'label' => 'Nume companie',
            ],
            'tax_identifier' => [
                'label' => 'Cod fiscal',
            ],
            'contact_phone' => [
                'label' => 'Telefon',
            ],
            'contact_email' => [
                'label' => 'Adresă e-mail',
            ],
            'city' => [
                'label' => 'Oraș',
            ],
            'state' => [
                'label' => 'Județ / Provincie',
            ],
            'postcode' => [
                'label' => 'Cod poștal',
            ],
            'country_id' => [
                'label' => 'Țară',
            ],
        ],

        'reference' => [
            'label' => 'Referință',
        ],
        'status' => [
            'label' => 'Stare',
        ],
        'transaction' => [
            'label' => 'Tranzacție',
        ],
        'amount' => [
            'label' => 'Sumă',

            'hint' => [
                'less_than_total' => 'Urmează să capturezi o sumă mai mică decât valoarea totală a tranzacției',
            ],
        ],

        'notes' => [
            'label' => 'Note',
        ],
        'confirm' => [
            'label' => 'Confirmă',

            'alert' => 'Este necesară confirmarea',

            'hint' => [
                'capture' => 'Confirmă că dorești să capturezi această plată',
                'refund' => 'Confirmă că dorești să rambursezi această sumă.',
            ],
        ],
    ],

    'infolist' => [
        'notes' => [
            'label' => 'Note',
            'placeholder' => 'Nu există note pentru această comandă',
        ],
        'delivery_instructions' => [
            'label' => 'Instrucțiuni de livrare',
        ],
        'shipping_total' => [
            'label' => 'Total livrare',
        ],
        'paid' => [
            'label' => 'Plătit',
        ],
        'refund' => [
            'label' => 'Rambursare',
        ],
        'unit_price' => [
            'label' => 'Preț unitar',
        ],
        'quantity' => [
            'label' => 'Cantitate',
        ],
        'sub_total' => [
            'label' => 'Subtotal',
        ],
        'discount_total' => [
            'label' => 'Total reducere',
        ],
        'discount_breakdown' => [
            'coupon' => 'Cupon',
            'coupon_with_name' => 'Cupon (:name)',
            'discount' => 'Reducere',
            'named' => 'Reducere :name',
        ],
        'total' => [
            'label' => 'Total',
        ],
        'preorder' => [
            'message' => 'Acest produs este disponibil doar pentru precomandă.',
        ],
        'current_stock_level' => [
            'message' => 'Stoc curent: :count',
        ],
        'purchase_stock_level' => [
            'message' => 'la momentul comenzii: :count',
        ],
        'status' => [
            'label' => 'Stare',
        ],
        'reference' => [
            'label' => 'Referință',
        ],
        'customer_reference' => [
            'label' => 'Referință client',
        ],
        'channel' => [
            'label' => 'Canal',
        ],
        'date_created' => [
            'label' => 'Data creării',
        ],
        'date_placed' => [
            'label' => 'Data plasării',
        ],
        'new_returning' => [
            'label' => 'Nou / Revenit',
        ],
        'new_customer' => [
            'label' => 'Client nou',
        ],
        'returning_customer' => [
            'label' => 'Client revenit',
        ],
        'shipping_address' => [
            'label' => 'Adresă de livrare',
        ],
        'billing_address' => [
            'label' => 'Adresă de facturare',
        ],
        'address_not_set' => [
            'label' => 'Nicio adresă setată',
        ],
        'billing_matches_shipping' => [
            'label' => 'La fel ca adresa de livrare',
        ],
        'additional_info' => [
            'label' => 'Informații suplimentare',
        ],
        'no_additional_info' => [
            'label' => 'Nu există informații suplimentare',
        ],
        'shipping_details' => [
            'label' => 'Detalii livrare',
        ],
        'billing_details' => [
            'label' => 'Detalii facturare',
        ],
        'personalization' => [
            'label' => 'Personalizare',
            'sku' => 'SKU',
            'value' => 'Text personalizat',
        ],
        'meta' => [
            'is_guest' => 'Comandă fără cont',
            'language_locale' => 'Limbă',
            'shippingType' => 'Tip livrare',
            'shipping_zone' => 'Zonă de livrare',
            'awb' => 'Număr AWB',
            'payment_option' => 'Metodă de plată',
            'payment_type' => 'Tip plată',
            'isBillingSameAsShipping' => 'Adresa de facturare este aceeași cu cea de livrare',
            'billing_series' => 'Serie factură',
            'billing_number' => 'Număr factură',
            'lunar_invoice_generated_at' => 'Factură Lunar generată la',
            'invoice_emailed_at' => 'Factură trimisă pe e-mail la',
        ],
        'shipping_types' => [
            'courier' => 'Curier',
            'locker' => 'Locker',
            'pickup' => 'Ridicare personală',
        ],
        'payment_methods' => [
            'offline' => 'Offline',
            'cash-in-hand' => 'Numerar',
            'cash-on-delivery' => 'Ramburs',
            'bank-transfer' => 'Transfer bancar',
            'stripe-card' => 'Card bancar',
            'stripe-wallet-pay' => 'Apple Pay / Google Pay',
            'hosted-payment' => 'Plată online cu cardul',
        ],
        'tags' => [
            'label' => 'Etichete',
        ],
        'timeline' => [
            'label' => 'Cronologie',
        ],
        'transactions' => [
            'label' => 'Tranzacții',
            'placeholder' => 'Nicio tranzacție',
        ],
        'alert' => [
            'draft_order' => 'Această comandă nu a fost plasată încă.',
            'requires_capture' => 'Această comandă necesită în continuare capturarea plății.',
            'partially_refunded' => 'Această comandă a fost rambursată parțial.',
            'refunded' => 'Această comandă a fost rambursată.',
            'product_line_gates' => [
                'paid' => 'Plătit: această comandă nu poate fi modificată, deoarece a fost plătită.',
                'erp' => 'ERP: această comandă nu poate fi modificată, deoarece a fost trimisă către ERP.',
                'invoice' => 'Factură: această comandă nu poate fi modificată, deoarece are o factură.',
                'awb' => 'AWB: această comandă nu poate fi modificată, deoarece are un AWB.',
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
            'label' => 'Actualizează starea',
            'notification' => 'Starea comenzilor a fost actualizată',
        ],
        'update_status' => [
            'new_status' => [
                'label' => 'Stare nouă',
            ],
            'additional_content' => [
                'label' => 'Conținut suplimentar',
            ],
            'additional_email_recipient' => [
                'label' => 'Destinatar e-mail suplimentar',
                'placeholder' => 'opțional',
            ],
        ],
        'download_order_pdf' => [
            'label' => 'Descarcă PDF',
            'notification' => 'Descărcarea PDF-ului comenzii',
        ],
        'edit_address' => [
            'label' => 'Editează',

            'notification' => [
                'error' => 'Eroare',

                'billing_address' => [
                    'saved' => 'Adresa de facturare a fost salvată',
                ],

                'shipping_address' => [
                    'saved' => 'Adresa de livrare a fost salvată',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Editează',
            'form' => [
                'tags' => [
                    'label' => 'Etichete',
                    'helper_text' => 'Separați etichetele apăsând Enter, Tab sau virgulă (,)',
                ],
            ],
        ],
        'capture_payment' => [
            'label' => 'Capturează plata',

            'notification' => [
                'error' => 'A apărut o problemă la capturare',
                'success' => 'Capturare reușită',
            ],
        ],
        'refund_payment' => [
            'label' => 'Rambursare',

            'notification' => [
                'error' => 'A apărut o problemă la rambursare',
                'success' => 'Rambursare reușită',
            ],
        ],
        'add_product' => [
            'label' => 'Adaugă produs',
            'product' => [
                'label' => 'Produs',
            ],
            'variant' => [
                'label' => 'Variantă',
            ],
            'quantity' => [
                'label' => 'Cantitate',
            ],
            'notification' => 'Produs adăugat',
        ],
        'update_quantity' => [
            'label' => 'Actualizează cantitatea',
            'quantity' => [
                'label' => 'Cantitate',
            ],
            'notification' => 'Cantitate actualizată',
        ],
        'remove_product' => [
            'label' => 'Elimină produsul',
            'confirmation' => 'Elimini acest produs din comandă?',
            'notification' => 'Produs eliminat',
        ],
        'product_line_edit' => [
            'last_line' => 'O comandă trebuie să păstreze cel puțin un produs.',
            'unauthorized' => 'Nu puteți modifica produsele acestei comenzi.',
            'cart' => 'Această comandă nu are un coș, deci produsele nu pot fi modificate.',
            'line' => 'Linia de produs nu a fost găsită.',
            'purchasable' => 'Varianta nu a fost găsită.',
            'shipping' => 'Opțiunea de livrare nu poate fi tarifată, așa că comanda a rămas neschimbată.',
            'locked' => 'Această comandă nu mai poate fi editată.',
            'not_purchasable' => 'Această variantă nu poate fi cumpărată.',
            'out_of_stock' => 'Această variantă nu este în stoc.',
            'insufficient_stock' => 'Nu există stoc suficient pentru această cantitate.',
            'missing_price' => 'Această variantă nu are preț în moneda comenzii.',
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
