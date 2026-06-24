export type DealerItem = {
    id: number;
    name: string;
    bot_username: string;
    telegram_chat_id: number | null;
    is_active: boolean;
    is_self_registered: boolean;
    trial_days_left: number | null;
    trial_expired: boolean | null;
    min_order_amount: number;
    sells_on_marketplace?: boolean;
    marketplace_commission_type?: string | null;
    marketplace_platform_fee_rate?: number | null;
    marketplace_fixed_commission_amount?: number | null;
    webhook_set_at: string | null;
    webhook_active: boolean;
    created_at: string;
    shops_count?: number;
    orders_count?: number;
    products_count?: number;
    revenue?: number | null;
    discount?: number | null;
};

export type ChartPoint = {
    date: string;
    count: number;
    total: number;
};

export type CurrencyTotals = {
    currency: string;
    symbol: string;
    revenue: number;
    discount: number;
    payments: number;
};

export type AdminTotals = {
    dealers: number;
    active_dealers: number;
    shops: number;
    orders: number;
    pending_orders: number;
    revenue: number;
    discount: number;
    total_payments: number;
    by_currency: CurrencyTotals[];
};
