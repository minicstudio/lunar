<?php

return [

    'label' => 'Sipariş',

    'plural_label' => 'Siparişler',

    'statuses' => [
        'split' => 'Bölündü',
    ],

    'breadcrumb' => [
        'manage' => 'Yönet',
    ],

    'tabs' => [
        'all' => 'Tümü',
    ],

    'transactions' => [
        'capture' => 'Tahsil Edildi',
        'intent' => 'Ödeme Niyeti',
        'refund' => 'İade Edildi',
        'failed' => 'Başarısız',
    ],

    'table' => [
        'status' => [
            'label' => 'Durum',
        ],
        'reference' => [
            'label' => 'Referans',
        ],
        'customer_reference' => [
            'label' => 'Müşteri Referansı',
        ],
        'customer' => [
            'label' => 'Müşteri',
        ],
        'tags' => [
            'label' => 'Etiketler',
            'no_options_message' => 'Etiket bulunamadı',
        ],
        'postcode' => [
            'label' => 'Posta Kodu',
        ],
        'email' => [
            'label' => 'E-posta',
            'copy_message' => 'E-posta adresi kopyalandı',
        ],
        'phone' => [
            'label' => 'Telefon',
        ],
        'total' => [
            'label' => 'Toplam',
        ],
        'date' => [
            'label' => 'Tarih',
        ],
        'new_customer' => [
            'label' => 'Müşteri Türü',
        ],
        'payment_type' => [
            'label' => 'Ödeme türü',
            'options' => [
                'offline' => 'Çevrimdışı',
                'card' => 'Kart',
            ],
        ],
        'total_from' => [
            'label' => 'Toplam başlangıç',
        ],
        'total_to' => [
            'label' => 'Toplam bitiş',
        ],
        'placed_after' => [
            'label' => 'Bu Tarihten Sonra',
        ],
        'placed_before' => [
            'label' => 'Bu Tarihten Önce',
        ],
        'show_draft_orders' => [
            'label' => 'Tamamlanmamış siparişleri göster',
        ],
    ],

    'form' => [
        'address' => [
            'first_name' => [
                'label' => 'Ad',
            ],
            'last_name' => [
                'label' => 'Soyad',
            ],
            'line_one' => [
                'label' => 'Adres Satırı 1',
            ],
            'line_two' => [
                'label' => 'Adres Satırı 2',
            ],
            'line_three' => [
                'label' => 'Adres Satırı 3',
            ],
            'company_name' => [
                'label' => 'Şirket Adı',
            ],
            'tax_identifier' => [
                'label' => 'Vergi Kimlik Numarası',
            ],
            'contact_phone' => [
                'label' => 'Telefon',
            ],
            'contact_email' => [
                'label' => 'E-posta Adresi',
            ],
            'city' => [
                'label' => 'Şehir',
            ],
            'state' => [
                'label' => 'Eyalet / İl',
            ],
            'postcode' => [
                'label' => 'Posta Kodu',
            ],
            'country_id' => [
                'label' => 'Ülke',
            ],
        ],

        'reference' => [
            'label' => 'Referans',
        ],
        'status' => [
            'label' => 'Durum',
        ],
        'transaction' => [
            'label' => 'İşlem',
        ],
        'amount' => [
            'label' => 'Miktar',

            'hint' => [
                'less_than_total' => 'Toplam işlem tutarından daha az bir miktar tahsil etmek üzeresiniz',
            ],
        ],

        'notes' => [
            'label' => 'Notlar',
        ],
        'confirm' => [
            'label' => 'Onayla',

            'alert' => 'Onay gerekli',

            'hint' => [
                'capture' => 'Lütfen bu ödemeyi tahsil etmek istediğinizi onaylayın',
                'refund' => 'Lütfen bu tutarı iade etmek istediğinizi onaylayın.',
            ],
        ],
    ],

    'infolist' => [
        'notes' => [
            'label' => 'Notlar',
            'placeholder' => 'Bu siparişte not yok',
        ],
        'delivery_instructions' => [
            'label' => 'Teslimat Talimatları',
        ],
        'shipping_total' => [
            'label' => 'Kargo Toplamı',
        ],
        'paid' => [
            'label' => 'Ödendi',
        ],
        'refund' => [
            'label' => 'İade',
        ],
        'unit_price' => [
            'label' => 'Birim Fiyat',
        ],
        'quantity' => [
            'label' => 'Miktar',
        ],
        'sub_total' => [
            'label' => 'Ara Toplam',
        ],
        'discount_total' => [
            'label' => 'İndirim Toplamı',
        ],
        'total' => [
            'label' => 'Toplam',
        ],
        'preorder' => [
            'message' => 'Bu ürün yalnızca ön sipariş için mevcuttur.',
        ],
        'current_stock_level' => [
            'message' => 'Mevcut Stok Seviyesi: :count',
        ],
        'purchase_stock_level' => [
            'message' => 'sipariş anında: :count',
        ],
        'status' => [
            'label' => 'Durum',
        ],
        'reference' => [
            'label' => 'Referans',
        ],
        'customer_reference' => [
            'label' => 'Müşteri Referansı',
        ],
        'channel' => [
            'label' => 'Kanal',
        ],
        'date_created' => [
            'label' => 'Oluşturulma Tarihi',
        ],
        'date_placed' => [
            'label' => 'Sipariş Tarihi',
        ],
        'new_returning' => [
            'label' => 'Yeni / Geri Dönen',
        ],
        'new_customer' => [
            'label' => 'Yeni Müşteri',
        ],
        'returning_customer' => [
            'label' => 'Geri Dönen Müşteri',
        ],
        'shipping_address' => [
            'label' => 'Kargo Adresi',
        ],
        'billing_address' => [
            'label' => 'Fatura Adresi',
        ],
        'address_not_set' => [
            'label' => 'Adres ayarlanmamış',
        ],
        'billing_matches_shipping' => [
            'label' => 'Kargo adresi ile aynı',
        ],
        'additional_info' => [
            'label' => 'Ek Bilgiler',
        ],
        'no_additional_info' => [
            'label' => 'Ek Bilgi Yok',
        ],
        'shipping_details' => [
            'label' => 'Kargo Detayları',
        ],
        'billing_details' => [
            'label' => 'Fatura Detayları',
        ],
        'meta' => [
            'shippingType' => 'Gönderim türü',
            'shipping_zone' => 'Gönderim bölgesi',
            'awb' => 'AWB numarası',
            'payment_option' => 'Ödeme yöntemi',
            'payment_type' => 'Ödeme türü',
            'isBillingSameAsShipping' => 'Fatura adresi teslimat adresiyle aynı',
            'billing_series' => 'Fatura serisi',
            'billing_number' => 'Fatura numarası',
            'lunar_invoice_generated_at' => 'Lunar fatura oluşturulma zamanı',
            'invoice_emailed_at' => 'Fatura e-posta ile gönderilme zamanı',
        ],
        'shipping_types' => [
            'courier' => 'Kurye',
            'locker' => 'Kargo dolabı',
            'pickup' => 'Mağazadan teslim alma',
        ],
        'payment_methods' => [
            'offline' => 'Çevrimdışı',
            'cash-in-hand' => 'Nakit',
            'cash-on-delivery' => 'Kapıda ödeme',
            'bank-transfer' => 'Banka havalesi',
            'stripe-card' => 'Kredi kartı',
            'stripe-wallet-pay' => 'Apple Pay / Google Pay',
            'hosted-payment' => 'Online kartla ödeme',
        ],
        'tags' => [
            'label' => 'Etiketler',
        ],
        'timeline' => [
            'label' => 'Zaman Çizelgesi',
        ],
        'transactions' => [
            'label' => 'İşlemler',
            'placeholder' => 'İşlem yok',
        ],
        'alert' => [
            'draft_order' => 'Bu sipariş henüz tamamlanmadı.',
            'requires_capture' => 'Bu sipariş hala ödemenin tahsil edilmesini gerektiriyor.',
            'partially_refunded' => 'Bu sipariş kısmen iade edildi.',
            'refunded' => 'Bu sipariş iade edildi.',
            'product_line_gates' => [
                'paid' => 'Ödendi: bu sipariş değiştirilemez, çünkü ödendi.',
                'erp' => 'ERP: bu sipariş değiştirilemez, çünkü ERP sistemine gönderildi.',
                'invoice' => 'Fatura: bu sipariş değiştirilemez, çünkü bir faturası var.',
                'awb' => 'AWB: bu sipariş değiştirilemez, çünkü bir AWB belgesi var.',
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
            'label' => 'Durumu Güncelle',
            'notification' => 'Siparişlerin durumu güncellendi',
        ],
        'update_status' => [
            'new_status' => [
                'label' => 'Yeni durum',
            ],
            'additional_content' => [
                'label' => 'Ek içerik',
            ],
            'additional_email_recipient' => [
                'label' => 'Ek e-posta alıcısı',
                'placeholder' => 'opsiyonel',
            ],
        ],
        'download_order_pdf' => [
            'label' => 'PDF İndir',
            'notification' => 'Sipariş PDF\'i indiriliyor',
        ],
        'edit_address' => [
            'label' => 'Düzenle',

            'notification' => [
                'error' => 'Hata',

                'billing_address' => [
                    'saved' => 'Fatura adresi kaydedildi',
                ],

                'shipping_address' => [
                    'saved' => 'Kargo adresi kaydedildi',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Düzenle',
            'form' => [
                'tags' => [
                    'label' => 'Etiketler',
                    'helper_text' => 'Etiketleri Enter, Tab veya virgül (,) tuşuna basarak ayırın',
                ],
            ],
        ],
        'capture_payment' => [
            'label' => 'Ödemeyi Tahsil Et',

            'notification' => [
                'error' => 'Yakalama ile ilgili bir sorun oluştu',
                'success' => 'Yakalama başarılı',
            ],
        ],
        'refund_payment' => [
            'label' => 'İade',

            'notification' => [
                'error' => 'İade ile ilgili bir sorun oluştu',
                'success' => 'İade başarılı',
            ],
        ],
        'add_product' => [
            'label' => 'Ürün ekle',
            'product' => [
                'label' => 'Ürün',
            ],
            'variant' => [
                'label' => 'Varyant',
            ],
            'quantity' => [
                'label' => 'Adet',
            ],
            'notification' => 'Ürün eklendi',
        ],
        'update_quantity' => [
            'label' => 'Adedi güncelle',
            'quantity' => [
                'label' => 'Adet',
            ],
            'notification' => 'Adet güncellendi',
        ],
        'remove_product' => [
            'label' => 'Ürünü kaldır',
            'confirmation' => 'Bu ürün siparişten kaldırılsın mı?',
            'notification' => 'Ürün kaldırıldı',
        ],
        'product_line_edit' => [
            'last_line' => 'Bir sipariş en az bir ürün içermelidir.',
            'unauthorized' => 'Bu siparişin ürünlerini değiştiremezsiniz.',
            'cart' => 'Bu siparişin sepeti yok, bu nedenle ürünler değiştirilemez.',
            'line' => 'Bu ürün satırı bulunamadı.',
            'purchasable' => 'Bu varyant bulunamadı.',
            'shipping' => 'Kargo seçeneği fiyatlandırılamadı, bu nedenle sipariş değişmedi.',
            'locked' => 'Bu sipariş artık düzenlenemez.',
            'not_purchasable' => 'Bu varyant satın alınamaz.',
            'out_of_stock' => 'Bu varyant stokta yok.',
            'insufficient_stock' => 'Bu miktar için yeterli stok yok.',
            'missing_price' => 'Bu varyantın sipariş para biriminde fiyatı yok.',
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
