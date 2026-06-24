export type OrderStatus =
    | 'pending'
    | 'assembling'
    | 'delivering'
    | 'delivered'
    | 'received'
    | 'cancelled';

export type PaymentType = 'credit' | 'debit';

export type PaymentMethod = 'cash' | 'card';

export type OrderActor = {
    type: 'user' | 'shop_member';
    id: number;
    name: string;
    role?: string | null;
};

export type OrderStatusHistoryEntry = {
    id: number;
    from_status: OrderStatus | null;
    to_status: OrderStatus;
    to_status_label: string;
    changed_at: string | null;
    reason: string | null;
    actor: OrderActor | null;
};

export type OrderAbilities = {
    assemble: boolean;
    editPicked: boolean;
    dispatch: boolean;
    deliver: boolean;
    cancel: boolean;
    assignDeliveryman: boolean;
    selfAssign: boolean;
    releaseSelf: boolean;
    acceptReturn: boolean;
};

export type StatusOption = {
    value: string;
    label: string;
};

export type Shop = {
    id: number;
    name: string;
    legal_name?: string | null;
    phone: string;
    contact_person?: string | null;
    address: string | null;
    landmark?: string | null;
    region?: string | null;
    district?: string | null;
    photo?: string | null;
    photo_url?: string | null;
    latitude?: number | null;
    longitude?: number | null;
    map_provider?: 'yandex' | 'google' | 'osm';
    balance: number;
    is_active: boolean;
    deliveryman_id?: number | null;
    deliveryman?: { id: number; name: string } | null;
    has_members?: boolean;
    members_count?: number;
    created_at: string;
};

export type ShopMember = {
    id: number;
    telegram_id: number;
    name: string | null;
    username: string | null;
    is_active: boolean;
    joined_at: string | null;
};

export type ShopInvite = {
    id: number;
    token: string;
    link: string | null;
    bot_username: string | null;
    expires_at: string;
    used_at: string | null;
    is_valid: boolean;
};

export type Deliveryman = {
    id: number;
    name: string;
    username: string;
    phone: string | null;
    shops_count?: number;
};

export type OrderItem = {
    id: number;
    product_id: number;
    product_type_id: number | null;
    product_name: string;
    product_type_name: string | null;
    product_type_code: string | null;
    display_name: string;
    price: number;
    pack_price: number | null;
    qty: number;
    delivered_qty: number;
    delivered_pack_qty: number | null;
    picked_qty: number | null;
    picked_pack_qty: number | null;
    returned_qty: number;
    returned_pack_qty: number | null;
    carry_qty: number;
    carry_pack_qty: number;
    carry_subtotal: number;
    unit: string | null;
    pack_size: number | null;
    pack_qty: number | null;
    subtotal: number;
    delivered_subtotal: number;
    prepared_subtotal: number;
};

export type Order = {
    id: number;
    number: number;
    status: OrderStatus;
    status_label: string;
    total: number;
    paid_amount: number;
    discount: number;
    delivered_total: number | null;
    // Sklad tayyorlagan jami (picked_qty * narx). Items relation yuklanganda mavjud.
    prepared_total?: number | null;
    // Status'ga qarab ko'rsatiladigan jami — desktop/mobile/mini-app shuni ishlatadi.
    display_total?: number | null;
    note: string | null;

    // Lifecycle timestamps
    created_at: string;
    updated_at: string;
    assembling_at: string | null;
    delivering_at: string | null;
    delivered_at: string | null;
    received_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;

    // Yetkazib beruvchi biriktirish
    deliveryman_id: number | null;
    assigned_at: string | null;
    can_self_assign?: boolean;
    can_release_self?: boolean;
    can_cancel?: boolean;
    can_dispatch?: boolean;
    can_accept_return?: boolean;
    has_carry?: boolean;
    carry_total?: number;
    has_pending_return?: boolean;
    deliveryman?: { id: number; name: string; phone: string | null } | null;
    cancelled_by?: { id: number; name: string } | null;

    // Kanal va xaridor (bot/manual = shop, marketplace = diller)
    channel?: 'bot' | 'manual' | 'marketplace';
    channel_label?: string;
    customer_name?: string | null;
    buyer_dealer?: { id: number; name: string; phone: string | null } | null;

    // Relations
    shop?: Shop;
    items?: OrderItem[];
    status_history?: OrderStatusHistoryEntry[];
    messages?: OrderMessageEntry[];
};

export type OrderMessageEntry = {
    id: number;
    body: string;
    created_at: string | null;
    updated_at: string | null;
    edited: boolean;
    author: { id: number; name: string } | null;
};

export type ProductCategory = {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
    products_count?: number;
    created_at: string;
};

export type ProductImage = {
    id: number;
    url: string;
    sort_order: number;
};

export type ProductType = {
    id: number;
    product_id?: number;
    name: string;
    price: number;
    pack_size: number;
    pack_price: number;
    bulk_only: boolean;
    stock: number;
    min_stock: number | null;
    is_low_stock: boolean;
    stock_packs: number;
    sort_order: number;
    is_active: boolean;
    images: ProductImage[];
    image_url: string | null;
};

export type Product = {
    id: number;
    name: string;
    description: string | null;
    price: number;
    pack_size: number;
    pack_price: number;
    bulk_only: boolean;
    has_types: boolean;
    types_count?: number;
    total_stock?: number;
    starting_price?: number;
    stock: number;
    min_stock: number;
    is_low_stock: boolean;
    stock_packs: number;
    unit: string;
    unit_label: string;
    images: ProductImage[];
    image_url: string | null;
    is_active: boolean;
    visibility?: 'bot_only' | 'marketplace_only' | 'both';
    visibility_label?: string;
    category_id: number | null;
    category?: { id: number; name: string } | null;
    types?: ProductType[];
    created_at: string;
};

export type Payment = {
    id: number;
    order_id: number | null;
    amount: number;
    type: PaymentType;
    type_label: string;
    method: PaymentMethod;
    method_label: string;
    cardholder_name: string | null;
    note: string | null;
    created_at: string;
    shop?: Shop;
};

export type TransactionType = 'stock_in' | 'stock_out' | 'stock_adjust';

export type TransactionDetail = {
    id: number;
    product_id: number;
    product_type_id: number | null;
    product_name: string;
    product_type_name: string | null;
    display_name: string;
    qty: number;
    unit_cost: number | null;
    pack_unit_cost: number | null;
    line_total: number | null;
    stock_before: number;
    stock_after: number;
};

export type Transaction = {
    id: number;
    type: TransactionType;
    type_label: string;
    note: string | null;
    actor_name: string | null;
    created_at: string;
    items_count?: number;
    total_qty?: number;
    total_cost?: number | null;
    details?: TransactionDetail[];
};

export type Supplier = {
    id: number;
    name: string;
    phone: string | null;
    contact_person: string | null;
    address: string | null;
    note: string | null;
    balance: number;
    is_active: boolean;
    created_at?: string;
};

export type SupplierPayment = {
    id: number;
    amount: number;
    type: 'credit' | 'debit';
    type_label: string;
    method: 'cash' | 'card';
    method_label: string;
    cardholder_name: string | null;
    note: string | null;
    transaction_id: number | null;
    transaction_type?: 'stock_in' | 'stock_out' | 'stock_adjust' | 'shop_return' | 'supplier_return' | null;
    transaction_type_label?: string | null;
    created_at: string;
    supplier?: Supplier;
};

export type Paginated<T> = {
    data: T[];
    links: Record<string, string | null>;
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

export type Filters = Record<string, string | number | string[] | number[] | null | undefined>;
