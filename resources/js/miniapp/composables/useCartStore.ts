import { reactive } from 'vue';
import { useApi } from './useApi';
import { readCache, writeCache } from './usePersistentCache';

const CART_TTL = 24 * 60 * 60 * 1000; // 1 kun

export type CartItem = {
    product_id: number;
    product_type_id?: number | null;
    product_name: string;
    product_type_name?: string | null;
    price: number;
    qty: number;
    unit?: string;
    pack_size?: number;
    pack_qty?: number | null;
    bulk_only?: boolean;
};

export type AddProductInfo = {
    id: number;
    name: string;
    price: number;
    unit: string;
    pack_size: number;
    bulk_only?: boolean;
    product_type_id?: number | null;
    product_type_name?: string | null;
};

type ServerCart = { items: CartItem[]; total: number; count: number; min_order_amount?: number };

const state = reactive({
    items: [] as CartItem[],
    total: 0,
    count: 0,
    minOrderAmount: 0,
    loaded: false,
    shopId: null as number | null,
});

const pendingPayloads = new Map<string, Record<string, number | null>>();
const syncTimers = new Map<string, ReturnType<typeof setTimeout>>();
// Har bir item uchun mahalliy o'zgarishlar versiyasi. Server javobi kelganda,
// shu vaqt ichida foydalanuvchi yana o'zgartirgan bo'lsa (versiya katta bo'lsa),
// eski javobni qabul qilmaymiz — qty orqaga "qaytib qolmasin" uchun.
const syncSequence = new Map<string, number>();

const SYNC_DEBOUNCE_MS = 350;

function itemKey(productId: number, productTypeId?: number | null): string {
    return `${productId}:${productTypeId ?? 0}`;
}

function cartCacheKey(shopId: number): string {
    return `cart:${shopId}`;
}

function persist(): void {
    if (state.shopId === null) {
        return;
    }

    writeCache(cartCacheKey(state.shopId), {
        items: state.items,
        total: state.total,
        count: state.count,
    });
}

function hasPendingFor(productId: number, productTypeId: number | null): boolean {
    const key = itemKey(productId, productTypeId);

    return syncSequence.has(key) || pendingPayloads.has(key) || syncTimers.has(key);
}

function applyServerCart(cart: ServerCart): void {
    const incoming = cart.items ?? [];
    let preservedAny = false;

    state.items = incoming.map((srv) => {
        const typeId = srv.product_type_id ?? null;

        if (hasPendingFor(srv.product_id, typeId)) {
            const local = state.items.find(
                (i) => i.product_id === srv.product_id && (i.product_type_id ?? null) === typeId,
            );

            if (local) {
                preservedAny = true;

                return local;
            }
        }

        return srv;
    });

    if (typeof cart.min_order_amount === 'number') {
        state.minOrderAmount = cart.min_order_amount;
    }

    state.loaded = true;

    if (preservedAny) {
        recalcTotals();
    } else {
        state.total = cart.total ?? 0;
        state.count = cart.count ?? 0;
        persist();
    }
}

function recalcTotals(): void {
    state.total = state.items.reduce((sum, i) => sum + i.price * i.qty, 0);
    state.count = state.items.length;
    persist();
}

function cancelPending(key: string): void {
    const timer = syncTimers.get(key);

    if (timer) {
        clearTimeout(timer);
    }

    syncTimers.delete(key);
    pendingPayloads.delete(key);
    syncSequence.delete(key);
}

export function useCartStore() {
    const api = useApi();

    async function load(): Promise<void> {
        try {
            const cart: ServerCart = await api.get('/cart');
            applyServerCart(cart);
        } catch {
            /* */
        }
    }

    async function reset(shopId: number): Promise<void> {
        if (state.shopId === shopId && state.loaded) {
            return;
        }

        state.shopId = shopId;
        syncTimers.forEach((t) => clearTimeout(t));
        syncTimers.clear();
        pendingPayloads.clear();
        syncSequence.clear();

        const cached = readCache<ServerCart>(cartCacheKey(shopId), CART_TTL);

        if (cached) {
            state.items = cached.items ?? [];
            state.total = cached.total ?? 0;
            state.count = cached.count ?? 0;
            state.loaded = true;
        } else {
            state.items = [];
            state.total = 0;
            state.count = 0;
            state.loaded = false;
        }

        await load();
    }

    function findItem(productId: number, productTypeId?: number | null): CartItem | undefined {
        const tid = productTypeId ?? null;

        return state.items.find(
            (i) => i.product_id === productId && (i.product_type_id ?? null) === tid,
        );
    }

    function findItemsByProduct(productId: number): CartItem[] {
        return state.items.filter((i) => i.product_id === productId);
    }

    function scheduleSync(productId: number, productTypeId: number | null, payload: Record<string, number | null>): void {
        const key = itemKey(productId, productTypeId);
        pendingPayloads.set(key, payload);
        const seq = (syncSequence.get(key) ?? 0) + 1;
        syncSequence.set(key, seq);
        const existing = syncTimers.get(key);

        if (existing) {
            clearTimeout(existing);
        }

        syncTimers.set(key, setTimeout(async () => {
            const latest = pendingPayloads.get(key);
            pendingPayloads.delete(key);
            syncTimers.delete(key);

            if (!latest) {
                return;
            }

            try {
                const cart: ServerCart = await api.patch(`/cart/${productId}`, latest);

                // Faqat shu seq oxirgi bo'lsa (so'rov ketgandan keyin user qo'shimcha
                // o'zgartirmagan bo'lsa) javobni qabul qilamiz. Aks holda, yangi
                // scheduleSync javobini kutamiz.
                if (syncSequence.get(key) === seq) {
                    syncSequence.delete(key);
                    applyServerCart(cart);
                }
            } catch {
                if (syncSequence.get(key) === seq) {
                    syncSequence.delete(key);
                    await load();
                }
            }
        }, SYNC_DEBOUNCE_MS));
    }

    function updateQtyOptimistic(
        productId: number,
        payload: { qty: number; pack_qty?: number | null; product_type_id?: number | null },
    ): void {
        const typeId = payload.product_type_id ?? null;
        const item = findItem(productId, typeId);

        if (!item) {
            return;
        }

        const packQty = payload.pack_qty ?? 0;

        if (payload.qty <= 0) {
            void remove(productId, typeId);

            return;
        }

        item.qty = payload.qty;
        item.pack_qty = packQty > 0 ? packQty : null;

        recalcTotals();
        scheduleSync(productId, typeId, {
            qty: payload.qty,
            pack_qty: packQty,
            product_type_id: typeId,
        });
    }

    async function add(product: AddProductInfo, payload: { qty: number; pack_qty?: number | null }): Promise<void> {
        const typeId = product.product_type_id ?? null;

        if (findItem(product.id, typeId)) {
            updateQtyOptimistic(product.id, { ...payload, product_type_id: typeId });

            return;
        }

        const packQty = payload.pack_qty ?? 0;
        const newItem: CartItem = {
            product_id: product.id,
            product_type_id: typeId,
            product_name: product.name,
            product_type_name: product.product_type_name ?? null,
            price: product.price,
            unit: product.unit,
            pack_size: product.pack_size,
            bulk_only: product.bulk_only ?? false,
            qty: payload.qty,
            pack_qty: packQty > 0 ? packQty : null,
        };

        const key = itemKey(product.id, typeId);
        const seq = (syncSequence.get(key) ?? 0) + 1;
        syncSequence.set(key, seq);

        state.items.push(newItem);
        recalcTotals();

        try {
            const cart: ServerCart = await api.post('/cart/add', {
                product_id: product.id,
                product_type_id: typeId,
                qty: payload.qty,
                pack_qty: packQty,
            });

            // Bu add javob keldi-yu, foydalanuvchi shu vaqt ichida yana
            // o'zgartirgan bo'lsa — eski javob bilan local qty ni bosmaslik
            if (syncSequence.get(key) === seq) {
                syncSequence.delete(key);
                applyServerCart(cart);
            }
        } catch (e) {
            if (syncSequence.get(key) === seq) {
                syncSequence.delete(key);
                const idx = state.items.findIndex(
                    (i) => i.product_id === product.id && (i.product_type_id ?? null) === typeId,
                );

                if (idx >= 0) {
                    state.items.splice(idx, 1);
                }

                recalcTotals();

                throw e;
            }
        }
    }

    async function remove(productId: number, productTypeId?: number | null): Promise<void> {
        const typeId = productTypeId ?? null;
        const key = itemKey(productId, typeId);
        cancelPending(key);
        const idx = state.items.findIndex(
            (i) => i.product_id === productId && (i.product_type_id ?? null) === typeId,
        );

        if (idx >= 0) {
            state.items.splice(idx, 1);
            recalcTotals();
        }

        try {
            const cart: ServerCart = await api.del(`/cart/${productId}`, {
                product_type_id: typeId,
            });
            applyServerCart(cart);
        } catch {
            await load();
        }
    }

    async function clear(): Promise<void> {
        syncTimers.forEach((t) => clearTimeout(t));
        syncTimers.clear();
        pendingPayloads.clear();
        syncSequence.clear();

        state.items = [];
        recalcTotals();

        try {
            const cart: ServerCart = await api.del('/cart');
            applyServerCart(cart);
        } catch {
            await load();
        }
    }

    function clearLocal(): void {
        syncTimers.forEach((t) => clearTimeout(t));
        syncTimers.clear();
        pendingPayloads.clear();
        syncSequence.clear();
        state.items = [];
        recalcTotals();
    }

    function applyServer(cart: ServerCart): void {
        applyServerCart(cart);
    }

    return {
        state,
        load,
        reset,
        findItem,
        findItemsByProduct,
        updateQtyOptimistic,
        add,
        remove,
        clear,
        clearLocal,
        applyServer,
    };
}
