<?php

declare(strict_types=1);

return [

    'Currency' => [
        'UZS' => 'Сум',
        'RUB' => 'Рубль',
        'symbol' => [
            'UZS' => 'сум',
            'RUB' => '₽',
        ],
    ],

    'BotVisibility' => [
        'private' => 'Закрытый (по ссылке-приглашению)',
        'public' => 'Открытый (любой может зарегистрироваться)',
    ],

    'BroadcastAudienceType' => [
        'all_active' => 'Все активные клиенты',
        'selected_shops' => 'Выбранные клиенты',
        'filter' => 'По фильтру',
        'platform_dealers' => 'Все дилеры',
        'platform_shop_members' => 'Все участники клиентов',
    ],

    'BroadcastMediaType' => [
        'photo' => 'Фото',
        'document' => 'Документ',
        'video' => 'Видео',
    ],

    'BroadcastMessageStatus' => [
        'queued' => 'В очереди',
        'sent' => 'Отправлено',
        'failed' => 'Ошибка',
    ],

    'BroadcastRunStatus' => [
        'pending' => 'Ожидается',
        'running' => 'Отправляется',
        'completed' => 'Завершено',
        'failed' => 'Ошибка',
    ],

    'BroadcastScheduleType' => [
        'once' => 'Однократно',
        'daily' => 'Ежедневно',
        'weekly' => 'По дням недели',
        'monthly' => 'По дням месяца',
    ],

    'CommissionType' => [
        'turnover_percentage' => 'Процент с оборота',
        'fixed_per_shop' => 'Сумма с клиента',
        'fixed_per_order' => 'Сумма с заказа',
        'fixed_per_deliveryman' => 'Сумма с курьера',
        'fixed_monthly' => 'Фиксированная ежемесячная сумма',
        'short' => [
            'turnover_percentage' => 'Процент',
            'fixed_per_shop' => 'С клиента',
            'fixed_per_order' => 'С заказа',
            'fixed_per_deliveryman' => 'С курьера',
            'fixed_monthly' => 'Ежемесячно',
        ],
    ],

    'LeadStatus' => [
        'new' => 'Новый',
        'contacted' => 'Связались',
        'converted' => 'Стал клиентом',
        'dropped' => 'Отклонён',
    ],

    'OrderStatus' => [
        'pending' => 'Ожидается',
        'assembling' => 'Собран',
        'delivering' => 'Доставляется',
        'delivered' => 'Доставлен',
        'received' => 'Принят',
        'cancelled' => 'Отменён',
    ],

    'PaymentMethod' => [
        'cash' => 'Наличные',
        'card' => 'Карта',
    ],

    'PaymentType' => [
        'credit' => 'Оплата',
        'debit' => 'Долг',
    ],

    'ProductUnit' => [
        'dona' => 'шт',
        'kg' => 'кг',
    ],

    'PromotionScope' => [
        'all' => 'Все товары',
        'category' => 'Категория',
        'product' => 'Конкретный товар',
    ],

    'ReturnDisposition' => [
        'restock' => 'Возврат на склад',
        'spoilage' => 'Списание',
    ],

    'ReturnReason' => [
        'defective' => 'Брак',
        'expired' => 'Просрочен',
        'wrong_item' => 'Неверный товар',
        'unsold' => 'Не продан',
        'damaged' => 'Повреждён',
        'other' => 'Другое',
    ],

    'TransactionType' => [
        'stock_in' => 'Приход',
        'stock_out' => 'Расход',
        'stock_adjust' => 'Корректировка',
        'shop_return' => 'Возврат от клиента',
        'supplier_return' => 'Возврат поставщику',
    ],

    'UserRole' => [
        'super_admin' => 'Супер-админ',
        'dealer' => 'Владелец',
        'warehouse' => 'Складчик',
        'deliveryman' => 'Курьер',
        'cashier' => 'Кассир',
    ],

];
