<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    Check,
    ChevronUp,
    ClipboardList,
    CreditCard,
    Loader2,
    LogIn,
    LogOut,
    Minus,
    Package,
    Plus,
    Printer,
    Receipt,
    Search,
    ShoppingBag,
    ShoppingCart,
    Tag,
    Trash2,
    User,
    UserCircle2,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import { currencySymbol, formatMoney, formatMoneySum } from '@/lib/format';
import type { Paginated, Product, ProductCategory } from '@/types';

type Customer = {
    id: number;
    name: string;
    phone: string | null;
    type: 'walk_in' | 'individual' | 'telegram';
    balance: number;
    is_walk_in: boolean;
};

type ActiveShift = {
    id: number;
    opened_at: string;
    opening_cash: number;
    cashier?: { id: number; name: string };
    live_totals: {
        total_sales: number;
        total_cash: number;
        total_card: number;
        total_debt: number;
        sales_count: number;
        expected_cash: number;
    };
};

type CartItem = {
    key: string;
    product_id: number;
    product_type_id: number | null;
    name: string;
    type_name: string | null;
    qty: number;
    pack_qty: number;
    unit: string;
    pack_size: number;
    price: number;
    pack_price: number | null;
    image_url: string | null;
    available_stock: number;
};

type ReceiptItem = {
    id: number;
    product_name: string;
    product_type_name: string | null;
    qty: number;
    price: number;
    pack_qty: number | null;
    pack_price: number | null;
    unit: string;
    subtotal: number;
};

type Sale = {
    id: number;
    receipt_number: string | null;
    payment_status: string | null;
    payment_status_label: string | null;
    total: number;
    discount: number;
    paid_amount: number;
    paid_cash: number;
    paid_card: number;
    debt_amount: number;
    note: string | null;
    created_at: string | null;
    shop?: { id: number; name: string; phone: string | null; type: string | null } | null;
    cashier?: { id: number; name: string } | null;
    items?: ReceiptItem[];
};

const props = defineProps<{
    activeShift: ActiveShift | null;
    products: Paginated<Product> | { data: Product[] };
    categories: ProductCategory[];
    customers: Customer[];
    lastSale?: Sale | null;
}>();

const { t } = useI18n();

const activeCategory = ref<number | null>(null);
const search = ref('');
const cart = ref<CartItem[]>([]);
const selectedCustomerId = ref<number>(props.customers.find((c) => c.is_walk_in)?.id ?? props.customers[0]?.id ?? 0);

const productList = computed<Product[]>(() => (props.products as { data: Product[] }).data);

const filteredProducts = computed(() => {
    let list = productList.value;
    if (activeCategory.value !== null) {
        list = list.filter((p) => p.category_id === activeCategory.value);
    }
    if (search.value.trim().length > 0) {
        const q = search.value.toLowerCase();
        list = list.filter((p) => p.name.toLowerCase().includes(q));
    }
    return list;
});

const customerItems = computed(() =>
    props.customers.map((c) => ({
        value: c.id,
        label: c.phone ? `${c.name} · ${c.phone}` : c.name,
    })),
);

const selectedCustomer = computed(() => props.customers.find((c) => c.id === selectedCustomerId.value) ?? null);

function addProduct(product: Product) {
    if ((product.has_types ?? false) && (product.types?.length ?? 0) > 0) {
        // Multi-type — har doim picker. Yagona variant ham bo'lsa, kassir
        // chalg'imasligi uchun ko'rsatamiz (faqat 1 ta tugma).
        typePickerProduct.value = product;
        return;
    }

    const key = `${product.id}:0`;
    const existing = cart.value.find((it) => it.key === key);
    const stock = Number(product.stock ?? 0);
    if (existing) {
        if (existing.qty + 1 > stock) {
            return;
        }
        existing.qty += 1;
        return;
    }
    cart.value.push({
        key,
        product_id: product.id,
        product_type_id: null,
        name: product.name,
        type_name: null,
        qty: 1,
        pack_qty: 0,
        unit: product.unit ?? 'dona',
        pack_size: Number(product.pack_size ?? 1) || 1,
        price: Number(product.price ?? 0),
        pack_price: product.pack_price !== null && product.pack_price !== undefined ? Number(product.pack_price) : null,
        image_url: product.image_url ?? null,
        available_stock: stock,
    });
}

function addType(product: Product, type: Product) {
    const key = `${product.id}:${type.id}`;
    const existing = cart.value.find((it) => it.key === key);
    const stock = Number(type.stock ?? 0);
    if (existing) {
        if (existing.qty + 1 > stock) {
            return;
        }
        existing.qty += 1;
        return;
    }
    cart.value.push({
        key,
        product_id: product.id,
        product_type_id: type.id,
        name: product.name,
        type_name: (type as unknown as { name?: string }).name ?? null,
        qty: 1,
        pack_qty: 0,
        unit: product.unit ?? 'dona',
        pack_size: Number((type as unknown as { pack_size?: number }).pack_size ?? product.pack_size ?? 1) || 1,
        price: Number((type as unknown as { price?: number }).price ?? product.price ?? 0),
        pack_price: (type as unknown as { pack_price?: number | null }).pack_price ?? null,
        image_url: product.image_url ?? null,
        available_stock: stock,
    });
}

function increment(item: CartItem) {
    if (item.qty + 1 > item.available_stock) {
        return;
    }
    item.qty += 1;
}

function decrement(item: CartItem) {
    if (item.qty - 1 <= 0) {
        cart.value = cart.value.filter((it) => it.key !== item.key);
        return;
    }
    item.qty -= 1;
}

function removeItem(item: CartItem) {
    cart.value = cart.value.filter((it) => it.key !== item.key);
}

function qtyStep(it: CartItem): string {
    return it.unit === 'dona' ? '1' : '0.001';
}

// Narx inputi bo'sh/noto'g'ri qoldirilsa 0 ga tushiramiz (NaN total'ni buzmasin).
function normalizePrice(it: CartItem): void {
    if (!Number.isFinite(it.price) || it.price < 0) {
        it.price = 0;
    }
}

function lineTotal(it: CartItem): number {
    if (it.pack_qty > 0 && it.pack_price !== null && it.pack_size > 1) {
        const loose = Math.max(0, it.qty - it.pack_qty * it.pack_size);
        return Math.round(it.pack_qty * it.pack_price + loose * it.price);
    }
    return Math.round(it.qty * it.price);
}

const subtotal = computed(() => cart.value.reduce((sum, it) => sum + lineTotal(it), 0));

const discount = ref<number>(0);
const discountOpen = ref<boolean>(false);
const paidCash = ref<number>(0);
const paidCard = ref<number>(0);
const cardholderName = ref<string>('');
const note = ref<string>('');

const cashQuickAmounts = computed<number[]>(() => {
    const t = total.value;
    if (t <= 0) {
        return [];
    }
    const round = (n: number) => Math.ceil(n / 1000) * 1000;
    const set = new Set<number>([round(t), round(t * 1.1), round(t + 5000), round(t + 10000), round(t + 50000)]);
    return Array.from(set).filter((n) => n >= t).sort((a, b) => a - b).slice(0, 4);
});

const total = computed(() => Math.max(0, subtotal.value - (discount.value || 0)));
const totalPaid = computed(() => (paidCash.value || 0) + (paidCard.value || 0));
const debt = computed(() => Math.max(0, total.value - totalPaid.value));
const advance = computed(() => Math.max(0, totalPaid.value - total.value));

// Manfiy yoki NaN qiymatlarni 0 ga clamp qilish — type=number min=0 atributi
// faqat spinnerga ta'sir qiladi, klaviatura orqali — kiritish mumkin.
watch(paidCash, (v) => {
    if (!Number.isFinite(v) || v < 0) {
        paidCash.value = 0;
    }
});
watch(paidCard, (v) => {
    if (!Number.isFinite(v) || v < 0) {
        paidCard.value = 0;
    }
});
watch(discount, (v) => {
    if (!Number.isFinite(v) || v < 0) {
        discount.value = 0;
    }
});

const isWalkIn = computed(() => selectedCustomer.value?.is_walk_in ?? true);
const debtBlocked = computed(() => debt.value > 0 && isWalkIn.value);
const canSubmit = computed(
    () => cart.value.length > 0
        && props.activeShift !== null
        && !debtBlocked.value
        && (paidCard.value === 0 || cardholderName.value.trim().length > 0),
);

function setExactCash() {
    paidCash.value = Math.max(0, total.value - (paidCard.value || 0));
}

function clearCart() {
    cart.value = [];
    discount.value = 0;
    paidCash.value = 0;
    paidCard.value = 0;
    cardholderName.value = '';
    note.value = '';
}

const submitting = ref(false);

// Mobile cart drawer
const cartDrawerOpen = ref<boolean>(false);
const cartItemCount = computed(() => cart.value.length);

// Multi-type tanlash modali
const typePickerProduct = ref<Product | null>(null);
function pickType(product: Product, type: Product) {
    addType(product, type);
    typePickerProduct.value = null;
}

// ─── Chek modal ─────────────────────────────────────────────────────────
const receiptSale = ref<Sale | null>(props.lastSale ?? null);
const receiptOpen = ref<boolean>(receiptSale.value !== null);
const receiptStatusStyles: Record<string, string> = {
    paid: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    partial: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    debt: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
    unpaid: 'bg-slate-500/15 text-slate-600 dark:text-slate-400',
};

watch(() => props.lastSale, (sale) => {
    if (sale) {
        receiptSale.value = sale;
        receiptOpen.value = true;
    }
});

function closeReceipt() {
    receiptOpen.value = false;
}

function printReceipt() {
    window.print();
}

function submit() {
    if (!canSubmit.value || submitting.value) {
        return;
    }
    submitting.value = true;
    router.post(
        '/dealer/pos/sales',
        {
            customer_id: selectedCustomerId.value,
            items: cart.value.map((it) => ({
                product_id: it.product_id,
                product_type_id: it.product_type_id,
                qty: it.qty,
                pack_qty: it.pack_qty,
                price: it.price,
                pack_price: it.pack_price,
            })),
            paid_cash: paidCash.value || 0,
            paid_card: paidCard.value || 0,
            discount: discount.value || 0,
            cardholder_name: cardholderName.value || null,
            note: note.value || null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                submitting.value = false;
            },
            onSuccess: () => {
                clearCart();
                cartDrawerOpen.value = false;
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                toast.error(typeof first === 'string' ? first : 'Sotuvni saqlashda xatolik');
            },
        },
    );
}

// ─── Smena modal ─────────────────────────────────────────────────────────
const shiftDialogOpen = ref(false);
const shiftForm = useForm({ opening_cash: 0, opening_note: '' });

function openShift() {
    shiftForm.post('/dealer/pos/shifts/open', {
        preserveScroll: true,
        onSuccess: () => {
            shiftDialogOpen.value = false;
            shiftForm.reset();
        },
    });
}

const closeDialogOpen = ref(false);
const closeForm = useForm({ closing_cash: 0, closing_note: '' });

watch(closeDialogOpen, (open) => {
    if (open && props.activeShift) {
        closeForm.closing_cash = props.activeShift.live_totals.expected_cash;
    }
});

function closeShift() {
    if (!props.activeShift) {
        return;
    }
    closeForm.post(`/dealer/pos/shifts/${props.activeShift.id}/close`, {
        preserveScroll: true,
        onSuccess: () => {
            closeDialogOpen.value = false;
            closeForm.reset();
        },
    });
}

// Sessiyaga savat saqlash — sahifa yangilanganda yoki tasodifan yopilganda
// kassir ishini yo'qotmaydi. Smena ID o'zgarganda kalit boshqacha bo'lib, eski
// smena savati avtomatik tashlanadi.
const cartStorageKey = computed(() =>
    props.activeShift ? `pos:cart:shift:${props.activeShift.id}` : null,
);

onMounted(() => {
    if (!cartStorageKey.value) {
        return;
    }

    try {
        const raw = sessionStorage.getItem(cartStorageKey.value);

        if (!raw) {
            return;
        }

        const saved = JSON.parse(raw) as {
            cart?: CartItem[];
            customerId?: number;
            discount?: number;
            paidCash?: number;
            paidCard?: number;
            cardholderName?: string;
            note?: string;
        };

        if (Array.isArray(saved.cart)) {
            cart.value = saved.cart;
        }

        if (
            typeof saved.customerId === 'number'
            && props.customers.some((c) => c.id === saved.customerId)
        ) {
            selectedCustomerId.value = saved.customerId;
        }

        if (typeof saved.discount === 'number') {
            discount.value = saved.discount;
        }

        if (typeof saved.paidCash === 'number') {
            paidCash.value = saved.paidCash;
        }

        if (typeof saved.paidCard === 'number') {
            paidCard.value = saved.paidCard;
        }

        if (typeof saved.cardholderName === 'string') {
            cardholderName.value = saved.cardholderName;
        }

        if (typeof saved.note === 'string') {
            note.value = saved.note;
        }
    } catch {
        // corrupt JSON — ignore
    }
});

watch(
    [cart, selectedCustomerId, discount, paidCash, paidCard, cardholderName, note],
    () => {
        if (!cartStorageKey.value) {
            return;
        }

        try {
            if (
                cart.value.length === 0
                && discount.value === 0
                && paidCash.value === 0
                && paidCard.value === 0
            ) {
                sessionStorage.removeItem(cartStorageKey.value);

                return;
            }

            sessionStorage.setItem(
                cartStorageKey.value,
                JSON.stringify({
                    cart: cart.value,
                    customerId: selectedCustomerId.value,
                    discount: discount.value,
                    paidCash: paidCash.value,
                    paidCard: paidCard.value,
                    cardholderName: cardholderName.value,
                    note: note.value,
                }),
            );
        } catch {
            // sessionStorage to'lgan — ignore
        }
    },
    { deep: true },
);

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('nav.posTerminal')" />

    <div class="flex flex-1 flex-col">
        <!-- Smena ochiq emas — barrier UI -->
        <div v-if="!activeShift" class="flex flex-1 items-center justify-center p-6">
            <Card class="w-full max-w-lg">
                <CardContent class="p-8 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                        <LogIn class="h-8 w-8 text-primary" />
                    </div>
                    <h2 class="mb-2 text-2xl font-semibold">Smenani oching</h2>
                    <p class="mb-6 text-sm text-muted-foreground">
                        POS kassada sotuv qilish uchun avval ish smenasini boshlash kerak. Kassada qancha pul borligini kiriting.
                    </p>
                    <div v-if="!shiftDialogOpen">
                        <Button class="w-full" size="lg" @click="shiftDialogOpen = true">
                            <LogIn class="mr-2 h-4 w-4" />
                            Smenani ochish
                        </Button>
                    </div>
                    <div v-else class="space-y-4 text-left">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Boshlang'ich naqd (so'm)</label>
                            <Input v-model.number="shiftForm.opening_cash" type="number" min="0" />
                            <div v-if="shiftForm.errors.opening_cash" class="mt-1 text-sm text-destructive">{{ shiftForm.errors.opening_cash }}</div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Izoh (ixtiyoriy)</label>
                            <Textarea v-model="shiftForm.opening_note" rows="2" />
                        </div>
                        <div class="flex gap-2">
                            <Button class="flex-1" :disabled="shiftForm.processing" @click="openShift">
                                <Loader2 v-if="shiftForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                                Ochish
                            </Button>
                            <Button variant="outline" @click="shiftDialogOpen = false">Bekor</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Aktiv smena bor — terminal -->
        <div v-else class="grid flex-1 grid-cols-1 gap-4 p-4 pb-24 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start lg:gap-6 lg:p-6 lg:pb-6">
            <!-- Chap: mahsulotlar -->
            <div class="flex min-w-0 flex-col gap-4">
                <!-- Top bar: smena info + qidiruv -->
                <div class="flex flex-col gap-3 rounded-xl border bg-card p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3 text-sm">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                            <ClipboardList class="h-4 w-4" />
                        </div>
                        <div>
                            <div class="font-medium">Smena #{{ activeShift.id }}</div>
                            <div class="text-xs text-muted-foreground">
                                Sotuv: {{ activeShift.live_totals.sales_count }} ta · {{ formatMoneySum(activeShift.live_totals.total_sales) }}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1 sm:w-72 sm:flex-initial">
                            <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="search" placeholder="Mahsulot qidirish…" class="h-10 pl-9 sm:h-9" />
                        </div>
                        <!-- Mobile: icon-only; Desktop: text -->
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-10 w-10 shrink-0 p-0 sm:h-9 sm:w-auto sm:px-3"
                            @click="closeDialogOpen = true"
                        >
                            <LogOut class="h-4 w-4 sm:mr-2" />
                            <span class="hidden sm:inline">Smenani yopish</span>
                        </Button>
                    </div>
                </div>

                <!-- Kategoriya tabs -->
                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
                    <button
                        type="button"
                        class="shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition"
                        :class="activeCategory === null ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:bg-muted'"
                        @click="activeCategory = null"
                    >
                        Barchasi
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        type="button"
                        class="shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition"
                        :class="activeCategory === cat.id ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:bg-muted'"
                        @click="activeCategory = cat.id"
                    >
                        {{ cat.name }}
                    </button>
                </div>

                <!-- Mahsulotlar grid -->
                <div class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-3">
                    <button
                        v-for="product in filteredProducts"
                        :key="product.id"
                        type="button"
                        class="group flex flex-col overflow-hidden rounded-xl border bg-card text-left transition hover:border-primary/40 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="(product.total_stock ?? product.stock ?? 0) <= 0"
                        @click="addProduct(product)"
                    >
                        <div class="relative aspect-square w-full bg-muted">
                            <img
                                v-if="product.image_url"
                                :src="product.image_url"
                                :alt="product.name"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center text-muted-foreground">
                                <Package class="h-10 w-10" />
                            </div>
                            <div
                                v-if="(product.total_stock ?? product.stock ?? 0) <= 0"
                                class="absolute inset-0 flex items-center justify-center bg-rose-500/85 text-sm font-semibold text-white"
                            >
                                Tugadi
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col gap-1 p-2.5">
                            <div class="line-clamp-2 text-sm font-medium leading-tight">{{ product.name }}</div>
                            <div class="mt-auto flex items-end justify-between">
                                <div class="text-base font-semibold">{{ formatMoneySum(Number(product.starting_price ?? product.price ?? 0)) }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ Number(product.total_stock ?? product.stock ?? 0).toFixed(0) }} {{ product.unit_label ?? product.unit }}
                                </div>
                            </div>
                        </div>
                    </button>
                </div>

                <div v-if="filteredProducts.length === 0" class="rounded-xl border border-dashed bg-muted/30 p-12 text-center text-muted-foreground">
                    Mahsulot topilmadi
                </div>
            </div>

            <!-- Mobile backdrop -->
            <div
                v-if="cartDrawerOpen"
                class="fixed inset-0 z-40 bg-black/50 lg:hidden"
                @click="cartDrawerOpen = false"
            ></div>

            <!-- O'ng: savat va to'lov — mobile bottom-sheet, desktop fixed sidebar -->
            <aside
                class="fixed inset-x-0 bottom-0 z-40 flex max-h-[88vh] flex-col rounded-t-2xl border-t bg-card pb-[calc(4rem+env(safe-area-inset-bottom))] shadow-2xl transition-transform duration-200 md:pb-0 lg:sticky lg:inset-x-auto lg:bottom-auto lg:top-4 lg:z-auto lg:max-h-[calc(100vh-2rem)] lg:translate-y-0 lg:self-start lg:rounded-xl lg:border lg:shadow-sm"
                :class="cartDrawerOpen ? 'translate-y-0' : 'translate-y-full lg:translate-y-0'"
            >
                <!-- Mobile header bar — drag handle + close -->
                <div class="flex shrink-0 items-center justify-between border-b px-4 py-2 lg:hidden">
                    <div class="text-sm font-semibold">Savat va to'lov</div>
                    <button
                        type="button"
                        class="rounded-md p-2 hover:bg-muted"
                        @click="cartDrawerOpen = false"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Mijoz -->
                <div class="shrink-0 space-y-2 border-b p-4">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <UserCircle2 class="h-3.5 w-3.5" />
                            Mijoz
                        </span>
                        <a href="/dealer/pos/customers" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                            <UserPlus class="h-3 w-3" />
                            Yangi
                        </a>
                    </div>
                    <SearchableSelect
                        v-model="selectedCustomerId"
                        :items="customerItems"
                        placeholder="Mijozni tanlang"
                        search-placeholder="Ism yoki telefon…"
                        empty-text="Mijoz topilmadi"
                        :clearable="false"
                    />
                    <div
                        v-if="selectedCustomer && !selectedCustomer.is_walk_in"
                        class="flex items-center justify-between rounded-md bg-muted/50 px-2.5 py-1.5 text-xs"
                    >
                        <span class="text-muted-foreground">Saldo</span>
                        <span
                            class="font-semibold"
                            :class="selectedCustomer.balance < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                        >
                            {{ selectedCustomer.balance < 0 ? '−' : '' }}{{ formatMoney(Math.abs(selectedCustomer.balance)) }} so'm
                        </span>
                    </div>
                </div>

                <!-- Savat header -->
                <div class="flex shrink-0 items-center justify-between border-b px-4 py-2.5">
                    <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Savat
                    </span>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-muted-foreground">{{ cart.length }} ta</span>
                        <Button
                            v-if="cart.length > 0"
                            variant="ghost"
                            size="sm"
                            class="h-6 px-2 text-xs text-muted-foreground hover:text-destructive"
                            @click="clearCart"
                        >
                            <X class="h-3 w-3" /> Tozalash
                        </Button>
                    </div>
                </div>

                <!-- Cart items -->
                <div class="min-h-0 flex-1 overflow-auto">
                    <div v-if="cart.length === 0" class="flex flex-col items-center px-4 py-12 text-center text-muted-foreground">
                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                            <Package class="h-7 w-7 opacity-50" />
                        </div>
                        <div class="text-sm font-medium">Savat bo'sh</div>
                        <div class="mt-1 text-xs opacity-75">Mahsulotlardan tanlang</div>
                    </div>
                    <ul v-else class="divide-y">
                        <li v-for="item in cart" :key="item.key" class="group px-3 py-1.5 transition hover:bg-muted/40">
                            <div class="flex items-center gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="line-clamp-1 text-[13px] font-medium leading-tight">
                                        {{ item.name }}<span v-if="item.type_name" class="text-muted-foreground"> · {{ item.type_name }}</span>
                                    </div>
                                    <div class="mt-0.5 flex items-center gap-1 text-[11px] leading-tight text-muted-foreground">
                                        <input
                                            v-model.number="item.price"
                                            type="number"
                                            min="0"
                                            step="any"
                                            inputmode="decimal"
                                            class="h-6 w-20 rounded border bg-background px-1.5 text-[11px] text-foreground tabular-nums focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                            @focus="($event.target as HTMLInputElement).select()"
                                            @blur="normalizePrice(item)"
                                        />
                                        <span class="whitespace-nowrap">{{ currencySymbol() }} /{{ item.unit }}</span>
                                    </div>
                                </div>
                                <div class="inline-flex shrink-0 items-center rounded-md border bg-background">
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 items-center justify-center text-muted-foreground transition hover:bg-muted sm:h-7 sm:w-7"
                                        @click="decrement(item)"
                                    >
                                        <Minus class="h-4 w-4 sm:h-3 sm:w-3" />
                                    </button>
                                    <Input
                                        v-model.number="item.qty"
                                        type="number"
                                        min="0"
                                        :max="item.available_stock"
                                        :step="qtyStep(item)"
                                        class="h-9 w-12 border-0 px-0 text-center text-sm shadow-none focus-visible:ring-0 sm:h-7 sm:w-10 sm:text-xs"
                                    />
                                    <button
                                        type="button"
                                        class="flex h-9 w-9 items-center justify-center text-muted-foreground transition hover:bg-muted sm:h-7 sm:w-7"
                                        @click="increment(item)"
                                    >
                                        <Plus class="h-4 w-4 sm:h-3 sm:w-3" />
                                    </button>
                                </div>
                                <div class="shrink-0 whitespace-nowrap pl-1 text-right text-[13px] font-semibold tabular-nums">
                                    {{ formatMoneySum(lineTotal(item)) }}
                                </div>
                                <button
                                    type="button"
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded text-muted-foreground/70 transition hover:bg-rose-500/10 hover:text-rose-600 sm:h-7 sm:w-7 sm:opacity-0 sm:group-hover:opacity-100 dark:hover:text-rose-400"
                                    @click="removeItem(item)"
                                >
                                    <Trash2 class="h-4 w-4 sm:h-3.5 sm:w-3.5" />
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- To'lov -->
                <div v-if="cart.length > 0" class="shrink-0 space-y-3 border-t bg-muted/30 p-3">
                    <!-- Hero total -->
                    <div class="rounded-lg border bg-card px-4 py-3 shadow-sm">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Jami to'lash</span>
                            <span class="text-2xl font-bold leading-none tabular-nums">{{ formatMoneySum(total) }}</span>
                        </div>
                        <div v-if="discount > 0" class="mt-1 flex items-center justify-between text-[11px] text-muted-foreground">
                            <span>Oraliq: {{ formatMoneySum(subtotal) }}</span>
                            <span class="text-emerald-600 dark:text-emerald-400">−{{ formatMoneySum(discount) }} chegirma</span>
                        </div>
                    </div>

                    <!-- Discount toggle -->
                    <div v-if="!discountOpen && discount === 0">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                            @click="discountOpen = true"
                        >
                            <Tag class="h-3 w-3" />
                            Chegirma qo'shish
                        </button>
                    </div>
                    <div v-else class="flex items-center gap-2 rounded-md border bg-card px-2 py-1.5">
                        <Tag class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                        <Input
                            v-model.number="discount"
                            type="number"
                            min="0"
                            :max="subtotal"
                            placeholder="Chegirma summasi"
                            class="h-7 border-0 px-1 text-sm shadow-none focus-visible:ring-0"
                        />
                        <button
                            type="button"
                            class="shrink-0 rounded p-0.5 text-muted-foreground hover:bg-muted"
                            @click="discount = 0; discountOpen = false"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <!-- Naqd (asosiy) -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <Banknote class="h-3.5 w-3.5" />
                                Naqd
                            </label>
                            <button
                                type="button"
                                class="text-xs font-medium text-primary hover:underline"
                                @click="setExactCash"
                            >
                                = Aniq
                            </button>
                        </div>
                        <Input
                            v-model.number="paidCash"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="h-11 text-base font-semibold tabular-nums"
                        />
                        <div v-if="cashQuickAmounts.length > 0" class="flex flex-wrap gap-1.5">
                            <button
                                v-for="amount in cashQuickAmounts"
                                :key="amount"
                                type="button"
                                class="rounded-md border bg-card px-3 py-1.5 text-sm font-medium tabular-nums hover:bg-muted sm:px-2.5 sm:py-1 sm:text-xs"
                                @click="paidCash = amount"
                            >
                                {{ formatMoney(amount) }}
                            </button>
                        </div>
                    </div>

                    <!-- Karta -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                <CreditCard class="h-3.5 w-3.5" />
                                Karta
                            </label>
                            <button
                                type="button"
                                class="text-xs font-medium text-primary hover:underline"
                                @click="paidCard = Math.max(0, total - (paidCash || 0))"
                            >
                                = Qolgan
                            </button>
                        </div>
                        <Input
                            v-model.number="paidCard"
                            type="number"
                            min="0"
                            placeholder="0"
                            class="h-10 text-sm font-medium tabular-nums"
                        />
                        <Input v-if="paidCard > 0" v-model="cardholderName" placeholder="Karta egasi (F.I.O.)" class="h-9 text-sm" />
                    </div>

                    <!-- Status row -->
                    <div
                        v-if="debt > 0"
                        class="flex items-center justify-between rounded-md px-3 py-2 text-sm"
                        :class="debtBlocked
                            ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                            : 'bg-amber-500/10 text-amber-700 dark:text-amber-400'"
                    >
                        <span>{{ debtBlocked ? 'Yo\'lakay xaridorga qarzga bo\'lmaydi' : 'Qolgan qarz' }}</span>
                        <strong v-if="!debtBlocked" class="tabular-nums">{{ formatMoneySum(debt) }}</strong>
                    </div>
                    <div
                        v-else-if="advance > 0"
                        class="flex items-center justify-between rounded-md bg-sky-500/10 px-3 py-2 text-sm text-sky-700 dark:text-sky-400"
                    >
                        <span>{{ isWalkIn ? 'Qaytim' : 'Avans (balansga)' }}</span>
                        <strong class="tabular-nums">{{ formatMoneySum(advance) }}</strong>
                    </div>
                    <div
                        v-else-if="totalPaid >= total && total > 0"
                        class="flex items-center gap-2 rounded-md bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400"
                    >
                        <Check class="h-4 w-4" />
                        <span>To'liq to'landi</span>
                    </div>

                    <Button class="h-12 w-full text-base" :disabled="!canSubmit || submitting" @click="submit">
                        <Loader2 v-if="submitting" class="mr-2 h-5 w-5 animate-spin" />
                        <Check v-else class="mr-2 h-5 w-5" />
                        Sotuvni yakunlash
                    </Button>
                </div>
            </aside>

            <!-- Mobile FAB: savatni ochish -->
            <button
                v-if="cart.length > 0 && !cartDrawerOpen"
                type="button"
                class="fixed inset-x-4 bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-50 flex items-center justify-between gap-3 rounded-xl bg-primary px-4 py-3 text-primary-foreground shadow-lg transition active:scale-[0.98] lg:hidden"
                @click="cartDrawerOpen = true"
            >
                <span class="flex items-center gap-2 text-sm font-medium">
                    <ShoppingCart class="h-5 w-5" />
                    <span class="rounded-full bg-primary-foreground/20 px-2 py-0.5 text-xs font-semibold">{{ cartItemCount }}</span>
                    <span>Savat</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="text-base font-bold tabular-nums">{{ formatMoneySum(total) }}</span>
                    <ChevronUp class="h-4 w-4" />
                </span>
            </button>
        </div>

        <!-- Multi-type picker modal -->
        <div
            v-if="typePickerProduct"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
            @click.self="typePickerProduct = null"
        >
            <Card class="w-full max-w-md rounded-b-none sm:rounded-b-xl">
                <CardContent class="space-y-3 p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold">{{ typePickerProduct.name }}</h3>
                            <p class="text-xs text-muted-foreground">Variantni tanlang</p>
                        </div>
                        <button class="rounded-md p-2 hover:bg-muted" @click="typePickerProduct = null">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="grid max-h-[60vh] grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2">
                        <button
                            v-for="t in (typePickerProduct.types ?? [])"
                            :key="(t as Product).id"
                            type="button"
                            :disabled="!(t as Product).is_active || ((t as Product).stock ?? 0) <= 0"
                            class="flex items-center justify-between rounded-lg border bg-card px-3 py-3 text-left transition hover:border-primary/40 hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                            @click="pickType(typePickerProduct, t as unknown as Product)"
                        >
                            <div class="min-w-0">
                                <div class="line-clamp-1 text-sm font-medium">{{ (t as unknown as { name?: string }).name ?? '—' }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ Number((t as Product).stock ?? 0).toFixed(0) }} {{ typePickerProduct.unit_label ?? typePickerProduct.unit }}
                                </div>
                            </div>
                            <div class="shrink-0 text-sm font-semibold tabular-nums">
                                {{ formatMoneySum(Number((t as unknown as { price?: number }).price ?? typePickerProduct.price ?? 0)) }}
                            </div>
                        </button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Smena yopish modal -->
        <div
            v-if="closeDialogOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="closeDialogOpen = false"
        >
            <Card class="w-full max-w-md">
                <CardContent class="space-y-4 p-6">
                    <div>
                        <h3 class="text-lg font-semibold">Smenani yopish</h3>
                        <p class="text-sm text-muted-foreground">Smena #{{ activeShift?.id }}</p>
                    </div>

                    <div class="space-y-2 rounded-md bg-muted/50 p-3 text-sm">
                        <div class="flex justify-between"><span>Boshlang'ich naqd</span><strong>{{ formatMoneySum(activeShift?.opening_cash ?? 0) }}</strong></div>
                        <div class="flex justify-between"><span>Sotuvlar (naqd)</span><strong>{{ formatMoneySum(activeShift?.live_totals.total_cash ?? 0) }}</strong></div>
                        <div class="flex justify-between"><span>Sotuvlar (karta)</span><strong>{{ formatMoneySum(activeShift?.live_totals.total_card ?? 0) }}</strong></div>
                        <div class="flex justify-between"><span>Qarzga sotuv</span><strong>{{ formatMoneySum(activeShift?.live_totals.total_debt ?? 0) }}</strong></div>
                        <div class="flex justify-between border-t pt-2"><span>Kutilgan naqd</span><strong>{{ formatMoneySum(activeShift?.live_totals.expected_cash ?? 0) }}</strong></div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Yopilish naqdi (sanagan summa)</label>
                        <Input v-model.number="closeForm.closing_cash" type="number" min="0" />
                        <div v-if="closeForm.errors.closing_cash" class="mt-1 text-sm text-destructive">{{ closeForm.errors.closing_cash }}</div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Izoh (ixtiyoriy)</label>
                        <Textarea v-model="closeForm.closing_note" rows="2" />
                    </div>

                    <div class="flex gap-2">
                        <Button variant="outline" class="flex-1" @click="closeDialogOpen = false">Bekor</Button>
                        <Button class="flex-1" :disabled="closeForm.processing" @click="closeShift">
                            <Loader2 v-if="closeForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Smenani yopish
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Chek modal -->
        <div
            v-if="receiptOpen && receiptSale"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 print:static print:bg-transparent print:p-0"
            @click.self="closeReceipt"
        >
            <Card class="max-h-[90vh] w-full max-w-md overflow-y-auto print:max-h-none print:max-w-full print:overflow-visible print:border-0 print:shadow-none">
                <CardContent class="space-y-4 p-6">
                    <div class="flex items-start gap-2 print:hidden">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold">Chek #{{ receiptSale.receipt_number }}</h3>
                            <p class="text-xs text-muted-foreground">{{ receiptSale.created_at ? formatDateTime(receiptSale.created_at) : '' }}</p>
                        </div>
                        <button class="rounded-md p-1 hover:bg-muted" @click="closeReceipt">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="text-center">
                        <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary print:hidden">
                            <Receipt class="h-6 w-6" />
                        </div>
                        <span
                            v-if="receiptSale.payment_status"
                            class="inline-flex rounded-full px-2 py-0.5 text-xs"
                            :class="receiptStatusStyles[receiptSale.payment_status] ?? ''"
                        >
                            {{ receiptSale.payment_status_label }}
                        </span>
                    </div>

                    <div class="grid gap-3 border-y py-3 text-sm sm:grid-cols-2">
                        <div class="flex items-center gap-2">
                            <User class="h-4 w-4 text-muted-foreground" />
                            <div>
                                <div class="text-xs text-muted-foreground">Mijoz</div>
                                <div>{{ receiptSale.shop?.name ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <User class="h-4 w-4 text-muted-foreground" />
                            <div>
                                <div class="text-xs text-muted-foreground">Kassir</div>
                                <div>{{ receiptSale.cashier?.name ?? '—' }}</div>
                            </div>
                        </div>
                    </div>

                    <table class="w-full text-sm">
                        <thead class="border-b text-left text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="py-2">Mahsulot</th>
                                <th class="py-2 text-right">Miqdor</th>
                                <th class="py-2 text-right">Narx</th>
                                <th class="py-2 text-right">Jami</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in receiptSale.items" :key="item.id">
                                <td class="py-2">
                                    <div>{{ item.product_name }}</div>
                                    <div v-if="item.product_type_name" class="text-xs text-muted-foreground">{{ item.product_type_name }}</div>
                                </td>
                                <td class="py-2 text-right">{{ Number(item.qty).toFixed(item.qty % 1 === 0 ? 0 : 3) }} {{ item.unit }}</td>
                                <td class="py-2 text-right">{{ formatMoney(item.price) }}</td>
                                <td class="py-2 text-right font-medium">{{ formatMoneySum(item.subtotal ?? Math.round(item.qty * item.price)) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="space-y-2 border-t pt-3 text-sm">
                        <div v-if="receiptSale.discount > 0" class="flex justify-between text-muted-foreground">
                            <span>Chegirma</span>
                            <span>− {{ formatMoneySum(receiptSale.discount) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-semibold">
                            <span>Jami</span>
                            <span>{{ formatMoneySum(receiptSale.total) }}</span>
                        </div>
                        <div v-if="receiptSale.paid_cash > 0" class="flex items-center justify-between">
                            <span class="flex items-center gap-1 text-muted-foreground"><Banknote class="h-3 w-3" /> Naqd</span>
                            <span>{{ formatMoneySum(receiptSale.paid_cash) }}</span>
                        </div>
                        <div v-if="receiptSale.paid_card > 0" class="flex items-center justify-between">
                            <span class="flex items-center gap-1 text-muted-foreground"><CreditCard class="h-3 w-3" /> Karta</span>
                            <span>{{ formatMoneySum(receiptSale.paid_card) }}</span>
                        </div>
                        <div v-if="receiptSale.debt_amount > 0" class="flex items-center justify-between font-semibold text-rose-600 dark:text-rose-400">
                            <span>Qarz</span>
                            <span>{{ formatMoneySum(receiptSale.debt_amount) }}</span>
                        </div>
                        <div v-if="receiptSale.paid_amount > receiptSale.total" class="flex items-center justify-between font-semibold text-sky-600 dark:text-sky-400">
                            <span>Qaytim / avans</span>
                            <span>{{ formatMoneySum(receiptSale.paid_amount - receiptSale.total) }}</span>
                        </div>
                    </div>

                    <div v-if="receiptSale.note" class="rounded-md bg-muted/50 p-2 text-xs text-muted-foreground">
                        {{ receiptSale.note }}
                    </div>

                    <div class="flex gap-2 print:hidden">
                        <Button variant="outline" class="flex-1" @click="closeReceipt">
                            <ShoppingBag class="mr-1 h-4 w-4" /> Yangi sotuv
                        </Button>
                        <Button class="flex-1" @click="printReceipt">
                            <Printer class="mr-1 h-4 w-4" /> Chiqarish
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
