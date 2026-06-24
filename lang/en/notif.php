<?php

declare(strict_types=1);

return [
    'order_created' => [
        'title' => 'Order received',
        'body' => '#:number — :amount so\'m',
    ],
    'order_status' => [
        'title' => 'Order #:number',
    ],
    'order_edited' => [
        'title' => 'Order updated',
        'body' => '#:number updated',
    ],
    'order_message' => [
        'title' => 'Order #:number — new message',
    ],
    'product_new' => [
        'title' => 'New product',
        'body' => ':name — :amount so\'m',
    ],
    'product_price' => [
        'title' => 'Price changed',
        'body' => ':name: :old → :new so\'m',
    ],
];
