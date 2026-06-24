<?php

declare(strict_types=1);

return [

    'Currency' => [
        'UZS' => 'Сўм',
        'RUB' => 'Рубл',
        'symbol' => [
            'UZS' => 'сўм',
            'RUB' => '₽',
        ],
    ],

    'BotVisibility' => [
        'private' => 'Ёпиқ (таклиф линк орқали)',
        'public' => 'Очиқ (ҳар ким рўйхатдан ўта олади)',
    ],

    'BroadcastAudienceType' => [
        'all_active' => 'Барча фаол мижозлар',
        'selected_shops' => 'Танланган мижозлар',
        'filter' => 'Фильтр орқали',
        'platform_dealers' => 'Барча диллерлар',
        'platform_shop_members' => 'Барча мижоз аъзолари',
    ],

    'BroadcastMediaType' => [
        'photo' => 'Расм',
        'document' => 'Ҳужжат',
        'video' => 'Видео',
    ],

    'BroadcastMessageStatus' => [
        'queued' => 'Навбатда',
        'sent' => 'Юборилди',
        'failed' => 'Хато',
    ],

    'BroadcastRunStatus' => [
        'pending' => 'Кутилмоқда',
        'running' => 'Юборилмоқда',
        'completed' => 'Якунланди',
        'failed' => 'Хато',
    ],

    'BroadcastScheduleType' => [
        'once' => 'Бир марталик',
        'daily' => 'Ҳар куни',
        'weekly' => 'Ҳафта кунлари',
        'monthly' => 'Ойнинг кунлари',
    ],

    'CommissionType' => [
        'turnover_percentage' => 'Айланмадан фоиз',
        'fixed_per_shop' => 'Ҳар мижоз учун сумма',
        'fixed_per_order' => 'Ҳар буюртма учун сумма',
        'fixed_per_deliveryman' => 'Ҳар етказиб берувчи учун сумма',
        'fixed_monthly' => 'Ойлик белгиланган сумма',
        'short' => [
            'turnover_percentage' => 'Фоиз',
            'fixed_per_shop' => 'Мижоздан',
            'fixed_per_order' => 'Буюртмадан',
            'fixed_per_deliveryman' => 'Етказиб берувчидан',
            'fixed_monthly' => 'Ойлик',
        ],
    ],

    'LeadStatus' => [
        'new' => 'Янги',
        'contacted' => 'Алоқага чиқилди',
        'converted' => 'Мижоз олдим',
        'dropped' => 'Рад этилди',
    ],

    'OrderStatus' => [
        'pending' => 'Кутилмоқда',
        'assembling' => 'Тайёрланди',
        'delivering' => 'Етказилмоқда',
        'delivered' => 'Етказилди',
        'received' => 'Қабул қилинди',
        'cancelled' => 'Бекор қилинди',
    ],

    'PaymentMethod' => [
        'cash' => 'Нақд',
        'card' => 'Карта',
    ],

    'PaymentType' => [
        'credit' => 'Тўлов',
        'debit' => 'Қарз',
    ],

    'ProductUnit' => [
        'dona' => 'дона',
        'kg' => 'кг',
    ],

    'PromotionScope' => [
        'all' => 'Барча маҳсулотлар',
        'category' => 'Категория',
        'product' => 'Аниқ маҳсулот',
    ],

    'ReturnDisposition' => [
        'restock' => 'Склад қайтаради',
        'spoilage' => 'Йўқотиш',
    ],

    'ReturnReason' => [
        'defective' => 'Яроқсиз',
        'expired' => 'Муддати ўтган',
        'wrong_item' => 'Нотўғри товар',
        'unsold' => 'Сотилмади',
        'damaged' => 'Шикастланган',
        'other' => 'Бошқа',
    ],

    'TransactionType' => [
        'stock_in' => 'Приход',
        'stock_out' => 'Чиқим',
        'stock_adjust' => 'Тузатиш',
        'shop_return' => 'Мижоздан возврат',
        'supplier_return' => 'Таъминотчига возврат',
    ],

    'UserRole' => [
        'super_admin' => 'Супер админ',
        'dealer' => 'Эгаси',
        'warehouse' => 'Складчи',
        'deliveryman' => 'Етказиб берувчи',
        'cashier' => 'Кассир',
    ],

];
