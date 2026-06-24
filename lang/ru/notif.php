<?php

declare(strict_types=1);

return [
    'order_created' => [
        'title' => 'Заказ принят',
        'body' => '#:number — :amount сум',
    ],
    'order_status' => [
        'title' => 'Заказ #:number',
    ],
    'order_edited' => [
        'title' => 'Заказ изменён',
        'body' => '#:number обновлён',
    ],
    'order_message' => [
        'title' => 'Заказ #:number — новое сообщение',
    ],
    'product_new' => [
        'title' => 'Новый товар',
        'body' => ':name — :amount сум',
    ],
    'product_price' => [
        'title' => 'Цена изменилась',
        'body' => ':name: :old → :new сум',
    ],
];
