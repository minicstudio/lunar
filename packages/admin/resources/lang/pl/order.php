<?php

return [

    'label' => 'Zamówienie',

    'plural_label' => 'Zamówienia',

    'statuses' => [
        'split' => 'Podzielone',
    ],

    'breadcrumb' => [
        'manage' => 'Zarządzanie zamówieniami',
    ],

    'tabs' => [
        'all' => 'Wszelkie',
    ],

    'transactions' => [
        'capture' => 'Przechwycona',
        'intent' => 'Rozpoczęta',
        'refund' => 'Zwrócona',
        'failed' => 'Nieudana',
    ],

    'table' => [
        'status' => [
            'label' => 'Status',
        ],
        'reference' => [
            'label' => 'Numer zamówienia',
        ],
        'customer_reference' => [
            'label' => 'Numer klienta',
        ],
        'customer' => [
            'label' => 'Klient',
        ],
        'tags' => [
            'label' => 'Tagi',
            'no_options_message' => 'Nie znaleziono tagów',
        ],
        'postcode' => [
            'label' => 'Kod pocztowy',
        ],
        'email' => [
            'label' => 'Email',
            'copy_message' => 'Adres email skopiowany',
        ],
        'phone' => [
            'label' => 'Telefon',
        ],
        'total' => [
            'label' => 'Suma',
        ],
        'date' => [
            'label' => 'Data',
        ],
        'new_customer' => [
            'label' => 'Typ klienta',
        ],
        'payment_type' => [
            'label' => 'Typ płatności',
            'options' => [
                'offline' => 'Offline',
                'card' => 'Karta',
            ],
        ],
        'total_from' => [
            'label' => 'Suma od',
        ],
        'total_to' => [
            'label' => 'Suma do',
        ],
        'placed_after' => [
            'label' => 'Złożone po',
        ],
        'placed_before' => [
            'label' => 'Złożone przed',
        ],
        'show_draft_orders' => [
            'label' => 'Pokaż niesfinalizowane zamówienia',
        ],
    ],

    'form' => [
        'address' => [
            'first_name' => [
                'label' => 'Imię',
            ],
            'last_name' => [
                'label' => 'Nazwisko',
            ],
            'line_one' => [
                'label' => 'Adres',
            ],
            'line_two' => [
                'label' => 'Adres cd.',
            ],
            'line_three' => [
                'label' => 'Adres cd.',
            ],
            'company_name' => [
                'label' => 'Nazwa firmy',
            ],
            'contact_phone' => [
                'label' => 'Telefon',
            ],
            'contact_email' => [
                'label' => 'Email',
            ],
            'city' => [
                'label' => 'Miasto',
            ],
            'state' => [
                'label' => 'Województwo',
            ],
            'postcode' => [
                'label' => 'Kod pocztowy',
            ],
            'country_id' => [
                'label' => 'Kraj',
            ],
        ],

        'reference' => [
            'label' => 'Numer zamówienia',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'transaction' => [
            'label' => 'Transakcja',
        ],
        'amount' => [
            'label' => 'Kwota',

            'hint' => [
                'less_than_total' => 'Próbujesz pobrać kwotę mniejszą niż całkowita wartość transakcji.',
            ],
        ],

        'notes' => [
            'label' => 'Notatki',
        ],
        'confirm' => [
            'label' => 'Potwierdź',

            'alert' => 'Wymaga potwierdzenia',

            'hint' => [
                'capture' => 'Potwierdź, że chcesz przechwycić tę kwotę.',
                'refund' => 'Potwierdź, że chcesz zwrócić tę kwotę.',
            ],
        ],
    ],

    'infolist' => [
        'notes' => [
            'label' => 'Notatki',
            'placeholder' => 'Brak notatek',
        ],
        'delivery_instructions' => [
            'label' => 'Informacje do dostawy',
        ],
        'shipping_total' => [
            'label' => 'Koszty dostawy',
        ],
        'paid' => [
            'label' => 'Opłacone',
        ],
        'refund' => [
            'label' => 'Zwrócone',
        ],
        'unit_price' => [
            'label' => 'Cena jednostkowa',
        ],
        'quantity' => [
            'label' => 'Ilość',
        ],
        'sub_total' => [
            'label' => 'Suma produktów',
        ],
        'discount_total' => [
            'label' => 'Suma zniżek',
        ],
        'total' => [
            'label' => 'Suma',
        ],
        'preorder' => [
            'message' => 'Ten produkt jest dostępny tylko w przedsprzedaży.',
        ],
        'current_stock_level' => [
            'message' => 'Stan magazynowy: :count',
        ],
        'purchase_stock_level' => [
            'message' => 'w momencie zamówienia: :count',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'reference' => [
            'label' => 'Numer zamówienia',
        ],
        'customer_reference' => [
            'label' => 'Numer klienta',
        ],
        'channel' => [
            'label' => 'Kanał',
        ],
        'date_created' => [
            'label' => 'Data utworzenia',
        ],
        'date_placed' => [
            'label' => 'Data złożenia',
        ],
        'new_returning' => [
            'label' => 'Nowy/Klient powracający',
        ],
        'new_customer' => [
            'label' => 'Nowy klient',
        ],
        'returning_customer' => [
            'label' => 'Klient powracający',
        ],
        'shipping_address' => [
            'label' => 'Adres dostawy',
        ],
        'billing_address' => [
            'label' => 'Adres rozliczeniowy',
        ],
        'address_not_set' => [
            'label' => 'Adres nieustawiony',
        ],
        'billing_matches_shipping' => [
            'label' => 'Adres rozliczeniowy jest taki sam jak adres dostawy',
        ],
        'additional_info' => [
            'label' => 'Dodatkowe informacje',
        ],
        'no_additional_info' => [
            'label' => 'Brak dodatkowych informacji',
        ],
        'shipping_details' => [
            'label' => 'Szczegóły wysyłki',
        ],
        'billing_details' => [
            'label' => 'Szczegóły rozliczenia',
        ],
        'meta' => [
            'shippingType' => 'Rodzaj wysyłki',
            'shipping_zone' => 'Strefa wysyłki',
            'awb' => 'Numer AWB',
            'payment_option' => 'Metoda płatności',
            'payment_type' => 'Typ płatności',
            'isBillingSameAsShipping' => 'Adres rozliczeniowy taki sam jak adres wysyłki',
            'billing_series' => 'Seria faktury',
            'billing_number' => 'Numer faktury',
            'lunar_invoice_generated_at' => 'Faktura Lunar wygenerowana',
            'invoice_emailed_at' => 'Faktura wysłana e-mailem',
        ],
        'shipping_types' => [
            'courier' => 'Kurier',
            'locker' => 'Paczkomat',
            'pickup' => 'Odbiór osobisty',
        ],
        'payment_methods' => [
            'offline' => 'Offline',
            'cash-in-hand' => 'Gotówka',
            'cash-on-delivery' => 'Płatność za pobraniem',
            'bank-transfer' => 'Przelew bankowy',
            'stripe-card' => 'Karta kredytowa',
            'stripe-wallet-pay' => 'Apple Pay / Google Pay',
            'hosted-payment' => 'Płatność kartą online',
        ],
        'tags' => [
            'label' => 'Tagi',
        ],
        'timeline' => [
            'label' => 'Historia',
        ],
        'transactions' => [
            'label' => 'Transakcje',
            'placeholder' => 'Brak transakcji',
        ],
        'alert' => [
            'draft_order' => 'To zamówienie nie zostało jeszcze sfinalizowane.',
            'requires_capture' => 'To zamówienie nadal wymaga przechwycenia płatności.',
            'partially_refunded' => 'To zamówienie zostało częściowo zwrócone.',
            'refunded' => 'To zamówienie zostało zwrócone.',
            'product_line_gates' => [
                'paid' => 'Opłacone: tego zamówienia nie można zmienić, ponieważ zostało opłacone.',
                'erp' => 'ERP: tego zamówienia nie można zmienić, ponieważ zostało wysłane do ERP.',
                'invoice' => 'Faktura: tego zamówienia nie można zmienić, ponieważ ma fakturę.',
                'awb' => 'AWB: tego zamówienia nie można zmienić, ponieważ ma AWB.',
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
            'label' => 'Aktualizuj statusy',
            'notification' => 'Statusy zamówień zostały zaktualizowane',
        ],
        'update_status' => [
            'label' => 'Update Status',
            'notification' => 'Order status updated',
            'new_status' => [
                'label' => 'Nowy status',
            ],
            'additional_content' => [
                'label' => 'Dodatkowa treść',
            ],
            'additional_email_recipient' => [
                'label' => 'Dodatkowy odbiorca email',
                'placeholder' => 'opcjonalnie',
            ],
        ],
        'download_order_pdf' => [
            'label' => 'Pobierz fakturę',
            'notification' => 'Faktura została pobrana',
        ],
        'edit_address' => [
            'label' => 'Edytuj adres',

            'notification' => [
                'error' => 'Błąd',

                'billing_address' => [
                    'saved' => 'Adres rozliczeniowy zapisany',
                ],

                'shipping_address' => [
                    'saved' => 'Adres dostawy zapisany',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Edytuj tagi',
        ],
        'capture_payment' => [
            'label' => 'Przechwyć płatność',

            'notification' => [
                'error' => 'Wystąpił problem z przechwyceniem płatności',
                'success' => 'Płatność przechwycona',
            ],
        ],
        'refund_payment' => [
            'label' => 'Zwróć płatność',

            'notification' => [
                'error' => 'Wystąpił problem ze zwróceniem płatności',
                'success' => 'Płatność zwrócona',
            ],
        ],
        'add_product' => [
            'label' => 'Dodaj produkt',
            'product' => [
                'label' => 'Produkt',
            ],
            'variant' => [
                'label' => 'Wariant',
            ],
            'quantity' => [
                'label' => 'Ilość',
            ],
            'notification' => 'Produkt dodany',
        ],
        'update_quantity' => [
            'label' => 'Zmień ilość',
            'quantity' => [
                'label' => 'Ilość',
            ],
            'notification' => 'Ilość zaktualizowana',
        ],
        'remove_product' => [
            'label' => 'Usuń produkt',
            'confirmation' => 'Usunąć ten produkt z zamówienia?',
            'notification' => 'Produkt usunięty',
        ],
        'product_line_edit' => [
            'last_line' => 'Zamówienie musi zachować co najmniej jeden produkt.',
            'unauthorized' => 'Nie możesz zmieniać produktów w tym zamówieniu.',
            'cart' => 'To zamówienie nie ma koszyka, więc produktów nie można zmienić.',
            'line' => 'Nie znaleziono tej pozycji produktu.',
            'purchasable' => 'Nie znaleziono tego wariantu.',
            'shipping' => 'Opcji wysyłki nie można wycenić, więc zamówienie pozostało bez zmian.',
            'locked' => 'Tego zamówienia nie można już edytować.',
            'not_purchasable' => 'Tego wariantu nie można kupić.',
            'out_of_stock' => 'Ten wariant jest niedostępny.',
            'insufficient_stock' => 'Brak wystarczającego stanu dla tej ilości.',
            'missing_price' => 'Ten wariant nie ma ceny w walucie zamówienia.',
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
