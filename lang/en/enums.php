<?php

declare(strict_types=1);

return [

    'Currency' => [
        'UZS' => 'Sum',
        'RUB' => 'Ruble',
        'symbol' => [
            'UZS' => "so'm",
            'RUB' => '₽',
        ],
    ],

    'BotVisibility' => [
        'private' => 'Private (invite link only)',
        'public' => 'Public (anyone can register)',
    ],

    'BroadcastAudienceType' => [
        'all_active' => 'All active customers',
        'selected_shops' => 'Selected customers',
        'filter' => 'By filter',
        'platform_dealers' => 'All dealers',
        'platform_shop_members' => 'All customer members',
    ],

    'BroadcastMediaType' => [
        'photo' => 'Photo',
        'document' => 'Document',
        'video' => 'Video',
    ],

    'BroadcastMessageStatus' => [
        'queued' => 'Queued',
        'sent' => 'Sent',
        'failed' => 'Failed',
    ],

    'BroadcastRunStatus' => [
        'pending' => 'Pending',
        'running' => 'Sending',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ],

    'BroadcastScheduleType' => [
        'once' => 'One-time',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
    ],

    'CommissionType' => [
        'turnover_percentage' => 'Percentage of turnover',
        'fixed_per_shop' => 'Amount per customer',
        'fixed_per_order' => 'Amount per order',
        'fixed_per_deliveryman' => 'Amount per deliveryman',
        'fixed_monthly' => 'Fixed monthly amount',
        'short' => [
            'turnover_percentage' => 'Percentage',
            'fixed_per_shop' => 'Per customer',
            'fixed_per_order' => 'Per order',
            'fixed_per_deliveryman' => 'Per deliveryman',
            'fixed_monthly' => 'Monthly',
        ],
    ],

    'LeadStatus' => [
        'new' => 'New',
        'contacted' => 'Contacted',
        'converted' => 'Converted',
        'dropped' => 'Dropped',
    ],

    'OrderStatus' => [
        'pending' => 'Pending',
        'assembling' => 'Assembled',
        'delivering' => 'Delivering',
        'delivered' => 'Delivered',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'PaymentMethod' => [
        'cash' => 'Cash',
        'card' => 'Card',
    ],

    'PaymentType' => [
        'credit' => 'Payment',
        'debit' => 'Debt',
    ],

    'ProductUnit' => [
        'dona' => 'pcs',
        'kg' => 'kg',
    ],

    'PromotionScope' => [
        'all' => 'All products',
        'category' => 'Category',
        'product' => 'Specific product',
    ],

    'ReturnDisposition' => [
        'restock' => 'Restock to warehouse',
        'spoilage' => 'Write-off',
    ],

    'ReturnReason' => [
        'defective' => 'Defective',
        'expired' => 'Expired',
        'wrong_item' => 'Wrong item',
        'unsold' => 'Unsold',
        'damaged' => 'Damaged',
        'other' => 'Other',
    ],

    'TransactionType' => [
        'stock_in' => 'Stock in',
        'stock_out' => 'Stock out',
        'stock_adjust' => 'Adjustment',
        'shop_return' => 'Customer return',
        'supplier_return' => 'Supplier return',
    ],

    'UserRole' => [
        'super_admin' => 'Super Admin',
        'dealer' => 'Owner',
        'warehouse' => 'Warehouse',
        'deliveryman' => 'Deliveryman',
        'cashier' => 'Cashier',
    ],

];
