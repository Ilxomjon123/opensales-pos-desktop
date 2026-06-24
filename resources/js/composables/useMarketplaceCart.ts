import { computed, reactive } from 'vue';

/**
 * Birja savati — mijoz (diller-xaridor) tomonida saqlanadi.
 * Mini app katalogi bilan bir xil UX: paket (blok) + dona alohida hisoblanadi.
 * Backend `/dealer/marketplace/orders` har item uchun { product_id, qty, pack_qty }
 * kutadi: qty = jami dona (loose + packs×pack_size), pack_qty = paketlar soni.
 *
 * Birja bitta buyurtmada faqat bitta sotuvchi mahsulotlarini qabul qiladi
 * (backend resolveSeller bilan mos), shuning uchun boshqa sotuvchidan
 * qo'shilganda savat tozalanadi.
 */

export type MarketplaceProduct = {
    id: number;
    name: string;
    description: string | null;
    price: number;
    original_price: number | null;
    discount_percent: number;
    pack_size: number;
    pack_price: number | null;
    bulk_only: boolean;
    has_types: boolean;
    starting_price: number;
    unit: string;
    unit_label: string;
    currency: string;
    stock: number;
    images: { id: number; url: string; sort_order: number }[];
    image_url: string | null;
    category_id: number | null;
    category: { id: number; name: string } | null;
    types: unknown[];
    seller: { id: number; name: string };
};

export type MarketplaceCartLine = {
    product: MarketplaceProduct;
    packQty: number;
    looseQty: number;
};

// v2: mahsulot snapshot'iga `currency` qo'shilgani uchun eski keshni bekor qilamiz.
const STORAGE_KEY = 'birja_cart_v2';

type PersistShape = { lines: MarketplaceCartLine[] };

const state = reactive<{ lines: MarketplaceCartLine[] }>({ lines: [] });

let loaded = false;

function load(): void {
    if (loaded || typeof window === 'undefined') {
        return;
    }

    loaded = true;

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (raw) {
            const parsed = JSON.parse(raw) as PersistShape;

            if (Array.isArray(parsed.lines)) {
                state.lines = parsed.lines.filter(
                    (l) => l && l.product && (l.packQty > 0 || l.looseQty > 0),
                );
            }
        }
    } catch {
        // buzilgan kesh — e'tiborsiz
    }
}

function persist(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({ lines: state.lines } satisfies PersistShape),
        );
    } catch {
        // kvota to'lgan — e'tiborsiz
    }
}

export function totalUnits(line: MarketplaceCartLine): number {
    const packSize = Math.max(1, line.product.pack_size);

    return line.looseQty + line.packQty * packSize;
}

export function lineSubtotal(line: MarketplaceCartLine): number {
    const packSize = Math.max(1, line.product.pack_size);
    const packPrice = line.product.pack_price;

    if (line.packQty > 0 && packPrice !== null && packSize > 1) {
        return line.packQty * packPrice + line.looseQty * line.product.price;
    }

    return totalUnits(line) * line.product.price;
}

export function useMarketplaceCart() {
    load();

    const lines = computed(() => state.lines);
    const count = computed(() => state.lines.length);
    const isEmpty = computed(() => state.lines.length === 0);
    const seller = computed(() => state.lines[0]?.product.seller ?? null);
    const total = computed(() =>
        state.lines.reduce((sum, l) => sum + lineSubtotal(l), 0),
    );

    function find(productId: number): MarketplaceCartLine | undefined {
        return state.lines.find((l) => l.product.id === productId);
    }

    function lineOf(productId: number): MarketplaceCartLine | null {
        return find(productId) ?? null;
    }

    /** Boshqa sotuvchidan qo'shilganda savatni tozalash kerakmi? */
    function wouldSwitchSeller(product: MarketplaceProduct): boolean {
        return seller.value !== null && seller.value.id !== product.seller.id;
    }

    function clear(): void {
        state.lines = [];
        persist();
    }

    function remove(productId: number): void {
        state.lines = state.lines.filter((l) => l.product.id !== productId);
        persist();
    }

    /**
     * Savatdagi qatorni belgilangan paket/dona soniga o'rnatadi (qo'shmaydi).
     * 0/0 bo'lsa o'chiradi. Stok bo'yicha cheklaydi.
     */
    function setQty(
        product: MarketplaceProduct,
        packQty: number,
        looseQty: number,
    ): void {
        const packSize = Math.max(1, product.pack_size);
        const p = Math.max(0, Math.floor(packQty));
        let loose = Math.max(0, looseQty);

        // Stok chegarasi (jami dona).
        const maxUnits = Math.max(0, product.stock);
        let units = loose + p * packSize;

        let packs = p;

        if (units > maxUnits) {
            // Avval loose'ni kamaytiramiz, keyin paketni.
            const overflow = units - maxUnits;
            loose = Math.max(0, loose - overflow);
            units = loose + packs * packSize;

            while (units > maxUnits && packs > 0) {
                packs -= 1;
                units = loose + packs * packSize;
            }
        }

        const existing = find(product.id);

        if (packs <= 0 && loose <= 0) {
            if (existing) {
                remove(product.id);
            }

            return;
        }

        if (existing) {
            existing.packQty = packs;
            existing.looseQty = loose;
            existing.product = product;
        } else {
            state.lines.push({ product, packQty: packs, looseQty: loose });
        }

        persist();
    }

    /** Karta tugmasi — bitta dona qo'shadi (yoki paket-only bo'lsa bitta paket). */
    function quickAdd(product: MarketplaceProduct): void {
        const existing = find(product.id);
        const packSize = Math.max(1, product.pack_size);
        const packOnly = product.bulk_only && packSize > 1;

        if (existing) {
            if (packOnly) {
                setQty(product, existing.packQty + 1, existing.looseQty);
            } else {
                setQty(product, existing.packQty, existing.looseQty + 1);
            }

            return;
        }

        if (packOnly) {
            setQty(product, 1, 0);
        } else {
            setQty(product, 0, 1);
        }
    }

    /** Buyurtma yuborish uchun item ro'yxati. */
    function orderItems(): {
        product_id: number;
        qty: number;
        pack_qty: number | null;
    }[] {
        return state.lines.map((l) => ({
            product_id: l.product.id,
            qty: totalUnits(l),
            pack_qty: l.packQty > 0 ? l.packQty : null,
        }));
    }

    return {
        lines,
        count,
        isEmpty,
        seller,
        total,
        lineOf,
        wouldSwitchSeller,
        clear,
        remove,
        setQty,
        quickAdd,
        orderItems,
    };
}
