<?php

declare(strict_types=1);

return [

    'Currency' => [
        'UZS' => "So'm",
        'RUB' => 'Rubl',
        'symbol' => [
            'UZS' => "so'm",
            'RUB' => '₽',
        ],
    ],

    'BotVisibility' => [
        'private' => 'Yopiq (taklif link orqali)',
        'public' => 'Ochiq (har kim ro\'yxatdan o\'ta oladi)',
    ],

    'BroadcastAudienceType' => [
        'all_active' => 'Barcha faol mijozlar',
        'selected_shops' => 'Tanlangan mijozlar',
        'filter' => 'Filtr orqali',
        'platform_dealers' => 'Barcha dillerlar',
        'platform_shop_members' => 'Barcha mijoz a\'zolari',
    ],

    'BroadcastMediaType' => [
        'photo' => 'Rasm',
        'document' => 'Hujjat',
        'video' => 'Video',
    ],

    'BroadcastMessageStatus' => [
        'queued' => 'Navbatda',
        'sent' => 'Yuborildi',
        'failed' => 'Xato',
    ],

    'BroadcastRunStatus' => [
        'pending' => 'Kutilmoqda',
        'running' => 'Yuborilmoqda',
        'completed' => 'Yakunlandi',
        'failed' => 'Xato',
    ],

    'BroadcastScheduleType' => [
        'once' => 'Bir martalik',
        'daily' => 'Har kuni',
        'weekly' => 'Hafta kunlari',
        'monthly' => 'Oyning kunlari',
    ],

    'CommissionType' => [
        'turnover_percentage' => 'Aylanmadan foiz',
        'fixed_per_shop' => 'Har mijoz uchun summa',
        'fixed_per_order' => 'Har buyurtma uchun summa',
        'fixed_per_deliveryman' => 'Har yetkazib beruvchi uchun summa',
        'fixed_monthly' => 'Oylik belgilangan summa',
        'short' => [
            'turnover_percentage' => 'Foiz',
            'fixed_per_shop' => 'Mijozdan',
            'fixed_per_order' => 'Buyurtmadan',
            'fixed_per_deliveryman' => 'Yetkazib beruvchidan',
            'fixed_monthly' => 'Oylik',
        ],
    ],

    'LeadStatus' => [
        'new' => 'Yangi',
        'contacted' => 'Aloqaga chiqildi',
        'converted' => 'Mijoz oldim',
        'dropped' => 'Rad etildi',
    ],

    'OrderStatus' => [
        'pending' => 'Kutilmoqda',
        'assembling' => 'Tayyorlandi',
        'delivering' => 'Yetkazilmoqda',
        'delivered' => 'Yetkazildi',
        'received' => 'Qabul qilindi',
        'cancelled' => 'Bekor qilindi',
    ],

    'PaymentMethod' => [
        'cash' => 'Naqd',
        'card' => 'Karta',
    ],

    'PaymentType' => [
        'credit' => 'To\'lov',
        'debit' => 'Qarz',
    ],

    'ProductUnit' => [
        'dona' => 'dona',
        'kg' => 'kg',
    ],

    'PromotionScope' => [
        'all' => 'Barcha mahsulotlar',
        'category' => 'Kategoriya',
        'product' => 'Aniq mahsulot',
    ],

    'ReturnDisposition' => [
        'restock' => 'Sklad qaytaradi',
        'spoilage' => 'Yo\'qotish',
    ],

    'ReturnReason' => [
        'defective' => 'Yaroqsiz',
        'expired' => 'Muddati o\'tgan',
        'wrong_item' => 'Noto\'g\'ri tovar',
        'unsold' => 'Sotilmadi',
        'damaged' => 'Shikastlangan',
        'other' => 'Boshqa',
    ],

    'TransactionType' => [
        'stock_in' => 'Prixod',
        'stock_out' => 'Chiqim',
        'stock_adjust' => 'Tuzatish',
        'shop_return' => 'Mijozdan vozvrat',
        'supplier_return' => 'Ta\'minotchiga vozvrat',
    ],

    'UserRole' => [
        'super_admin' => 'Super Admin',
        'dealer' => 'Owner',
        'warehouse' => 'Skladchi',
        'deliveryman' => 'Yetkazib beruvchi',
        'cashier' => 'Kassir',
    ],

];
