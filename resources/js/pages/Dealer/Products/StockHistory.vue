<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    ChevronRight,
    History,
    Package,
    Plus,
    Search,
    Store,
    Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ShopReturnFreeFormModal from '@/components/dealer/orders/ShopReturnFreeFormModal.vue';
import SupplierReturnModal from '@/components/dealer/suppliers/SupplierReturnModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated, Transaction } from '@/types';

type SupplierOpt = { id: number; name: string; balance?: number };
type ShopOpt = { id: number; name: string; balance?: number };

type ProductTypeOpt = {
    id: number;
    name: string;
    stock: number;
    price: number;
    pack_price: number | null;
    pack_size?: number;
    bulk_only?: boolean;
};

type ProductOpt = {
    id: number;
    name: string;
    unit: string;
    stock: number;
    has_types: boolean;
    price: number;
    pack_price: number | null;
    pack_size: number;
    bulk_only?: boolean;
    types: ProductTypeOpt[];
};

type TxType =
    | 'stock_in'
    | 'stock_out'
    | 'stock_adjust'
    | 'shop_return'
    | 'supplier_return';

type TransactionWithSupplier = Transaction & {
    supplier?: { id: number; name: string } | null;
    supplier_id?: number | null;
    shop?: { id: number; name: string } | null;
    shop_id?: number | null;
    order?: { id: number; number: number | null } | null;
    order_id?: number | null;
    reason?: string | null;
};

const props = defineProps<{
    transactions: Paginated<TransactionWithSupplier>;
    suppliers: SupplierOpt[];
    shops?: ShopOpt[];
    products?: ProductOpt[];
    filters: { type: string };
}>();

const { t } = useI18n();
const { symbol } = useCurrency();

const showPickShop = ref(false);
const showShopReturn = ref(false);
const pickedShop = ref<ShopOpt | null>(null);
const shopSearch = ref('');

const showPickSupplier = ref(false);
const showSupplierReturn = ref(false);
const pickedSupplier = ref<SupplierOpt | null>(null);
const supplierSearch = ref('');

const filteredSuppliers = computed<SupplierOpt[]>(() => {
    const q = supplierSearch.value.trim().toLowerCase();

    if (!q) {
        return props.suppliers;
    }

    return props.suppliers.filter((s) => s.name.toLowerCase().includes(q));
});

const filteredShops = computed<ShopOpt[]>(() => {
    const q = shopSearch.value.trim().toLowerCase();

    if (!q) {
        return props.shops ?? [];
    }

    return (props.shops ?? []).filter((s) => s.name.toLowerCase().includes(q));
});

function openShopPicker() {
    pickedShop.value = null;
    shopSearch.value = '';
    showPickShop.value = true;
}

function pickShop(s: ShopOpt) {
    pickedShop.value = s;
    showPickShop.value = false;
    showShopReturn.value = true;
}

function openSupplierPicker() {
    pickedSupplier.value = null;
    supplierSearch.value = '';
    showPickSupplier.value = true;
}

function pickSupplier(s: SupplierOpt) {
    pickedSupplier.value = s;
    showPickSupplier.value = false;
    showSupplierReturn.value = true;
}

const expanded = ref<Set<number>>(new Set());

const TYPE_OPTIONS = computed<
    { value: TxType; label: string; title: string; emptyText: string }[]
>(() => [
    {
        value: 'stock_in',
        label: t('pageDealer.products.stockHistory.stockInLabel'),
        title: t('pageDealer.products.stockHistory.stockInTitle'),
        emptyText: t('pageDealer.products.stockHistory.stockInEmpty'),
    },
    {
        value: 'stock_out',
        label: t('pageDealer.products.stockHistory.stockOutLabel'),
        title: t('pageDealer.products.stockHistory.stockOutTitle'),
        emptyText: t('pageDealer.products.stockHistory.stockOutEmpty'),
    },
    {
        value: 'stock_adjust',
        label: t('pageDealer.products.stockHistory.adjustLabel'),
        title: t('pageDealer.products.stockHistory.adjustTitle'),
        emptyText: t('pageDealer.products.stockHistory.adjustEmpty'),
    },
    {
        value: 'shop_return',
        label: t('pageDealer.products.stockHistory.shopReturnLabel'),
        title: t('pageDealer.products.stockHistory.shopReturnTitle'),
        emptyText: t('pageDealer.products.stockHistory.returnEmptyText'),
    },
    {
        value: 'supplier_return',
        label: t('pageDealer.products.stockHistory.supplierReturnLabel'),
        title: t('pageDealer.products.stockHistory.supplierReturnTitle'),
        emptyText: t('pageDealer.products.stockHistory.returnEmptyText'),
    },
]);

const currentType = computed<TxType>(
    () =>
        TYPE_OPTIONS.value.find((o) => o.value === props.filters.type)?.value ??
        'stock_in',
);
const currentMeta = computed(
    () =>
        TYPE_OPTIONS.value.find((o) => o.value === currentType.value) ??
        TYPE_OPTIONS.value[0],
);
const isIncoming = computed(
    () =>
        currentType.value === 'stock_in' || currentType.value === 'shop_return',
);
const isOutgoing = computed(
    () =>
        currentType.value === 'stock_out' ||
        currentType.value === 'supplier_return',
);
const qtySign = computed(() =>
    isOutgoing.value ? '−' : isIncoming.value ? '+' : '±',
);
const counterpartLabel = computed(() => {
    if (currentType.value === 'shop_return') {
        return t('pageDealer.products.stockHistory.counterpartShop');
    }

    return t('pageDealer.products.stockHistory.counterpartSupplier');
});

function toggle(id: number) {
    if (expanded.value.has(id)) {
        expanded.value.delete(id);
    } else {
        expanded.value.add(id);
    }

    expanded.value = new Set(expanded.value);
}

function formatMoney(amount: number | null | undefined): string {
    if (amount === null || amount === undefined) {
        return '—';
    }

    return String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function formatDate(iso: string): string {
    const d = new Date(iso);

    return d.toLocaleString('uz-UZ', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function goToPage(page: number) {
    router.get(
        '/dealer/stock-transactions',
        { ...props.filters, page },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function counterpartName(t: TransactionWithSupplier): string | null {
    if (currentType.value === 'shop_return') {
        return t.shop?.name ?? null;
    }

    return t.supplier?.name ?? null;
}

function counterpartLink(t: TransactionWithSupplier): string | null {
    if (currentType.value === 'shop_return' && t.shop?.id) {
        return `/dealer/shops/${t.shop.id}`;
    }

    if (t.supplier?.id) {
        return `/dealer/suppliers/${t.supplier.id}`;
    }

    return null;
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="currentMeta.title" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <Button
                variant="ghost"
                size="icon"
                @click="router.get('/dealer/products')"
            >
                <ArrowLeft class="h-4 w-4" />
            </Button>
            <div
                class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 sm:flex"
            >
                <History class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0 flex-1">
                <h1
                    class="truncate text-lg font-bold tracking-tight sm:text-2xl"
                >
                    {{ currentMeta.title }}
                </h1>
                <p class="text-xs text-muted-foreground sm:text-sm">
                    {{
                        t('pageDealer.products.stockHistory.recordCount', {
                            n: transactions.meta.total,
                        })
                    }}
                </p>
            </div>
            <Button
                v-if="currentType === 'shop_return' && (shops?.length ?? 0) > 0"
                size="sm"
                @click="openShopPicker"
            >
                <Plus class="h-4 w-4 sm:mr-1" />
                <span class="hidden sm:inline">{{
                    t('pageDealer.products.stockHistory.addReturn')
                }}</span>
                <span class="sm:hidden">{{
                    t('pageDealer.products.stockHistory.add')
                }}</span>
            </Button>
            <Button
                v-if="currentType === 'supplier_return' && suppliers.length > 0"
                size="sm"
                @click="openSupplierPicker"
            >
                <Plus class="h-4 w-4 sm:mr-1" />
                <span class="hidden sm:inline">{{
                    t('pageDealer.products.stockHistory.addReturn')
                }}</span>
                <span class="sm:hidden">{{
                    t('pageDealer.products.stockHistory.add')
                }}</span>
            </Button>
        </div>

        <div
            v-if="transactions.data.length === 0"
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed py-16"
        >
            <Package class="h-12 w-12 text-muted-foreground/40" />
            <div class="text-center">
                <p class="font-medium">{{ currentMeta.emptyText }}</p>
            </div>
        </div>

        <div v-else class="hidden overflow-x-auto rounded-xl border md:block">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="w-10 px-3 py-3"></th>
                        <th class="px-3 py-3 font-medium">
                            {{ t('pageDealer.products.stockHistory.date') }}
                        </th>
                        <th class="px-3 py-3 font-medium">
                            {{ t('pageDealer.products.stockHistory.who') }}
                        </th>
                        <th class="px-3 py-3 font-medium">
                            {{ counterpartLabel }}
                        </th>
                        <th class="px-3 py-3 text-right font-medium">
                            {{ t('pageDealer.products.stockHistory.products') }}
                        </th>
                        <th class="px-3 py-3 text-right font-medium">
                            {{ t('pageDealer.products.stockHistory.totalQty') }}
                        </th>
                        <th class="px-3 py-3 text-right font-medium">
                            {{
                                t('pageDealer.products.stockHistory.totalCost')
                            }}
                        </th>
                        <th class="px-3 py-3 font-medium">
                            {{ t('pageDealer.products.stockHistory.note') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <template v-for="tx in transactions.data" :key="tx.id">
                        <tr
                            class="cursor-pointer hover:bg-muted/20"
                            @click="toggle(tx.id)"
                        >
                            <td class="px-3 py-2 text-center">
                                <ChevronDown
                                    v-if="expanded.has(tx.id)"
                                    class="h-4 w-4 text-muted-foreground"
                                />
                                <ChevronRight
                                    v-else
                                    class="h-4 w-4 text-muted-foreground"
                                />
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">
                                {{ formatDate(tx.created_at) }}
                            </td>
                            <td class="px-3 py-2">
                                {{ tx.actor_name ?? '—' }}
                            </td>
                            <td class="px-3 py-2">
                                <button
                                    v-if="
                                        counterpartName(tx) &&
                                        counterpartLink(tx)
                                    "
                                    class="text-primary hover:underline"
                                    @click.stop="
                                        router.get(counterpartLink(tx)!)
                                    "
                                >
                                    {{ counterpartName(tx) }}
                                </button>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-3 py-2 text-right">
                                {{ tx.items_count ?? 0 }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono">
                                {{ tx.total_qty ?? 0 }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono">
                                {{ formatMoney(tx.total_cost) }}
                                <span
                                    v-if="
                                        tx.total_cost !== null &&
                                        tx.total_cost !== undefined
                                    "
                                    class="text-xs text-muted-foreground"
                                    >{{ symbol }}</span
                                >
                            </td>
                            <td
                                class="line-clamp-1 px-3 py-2 text-xs text-muted-foreground"
                            >
                                {{ tx.note ?? '—' }}
                            </td>
                        </tr>
                        <tr
                            v-if="expanded.has(tx.id) && tx.details"
                            class="bg-muted/10"
                        >
                            <td></td>
                            <td colspan="7" class="px-3 py-3">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-muted-foreground">
                                            <th
                                                class="py-1 text-left font-normal"
                                            >
                                                {{
                                                    t(
                                                        'pageDealer.products.stockHistory.detailProduct',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="py-1 text-right font-normal"
                                            >
                                                {{
                                                    t(
                                                        'pageDealer.products.stockHistory.detailQty',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="py-1 text-right font-normal"
                                            >
                                                {{
                                                    t(
                                                        'pageDealer.products.stockHistory.detailCost',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="py-1 text-right font-normal"
                                            >
                                                {{
                                                    t(
                                                        'pageDealer.products.stockHistory.detailTotal',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="py-1 text-right font-normal"
                                            >
                                                {{
                                                    t(
                                                        'pageDealer.products.stockHistory.detailStock',
                                                    )
                                                }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="d in tx.details"
                                            :key="d.id"
                                            class="border-t"
                                        >
                                            <td class="py-1.5">
                                                {{ d.product_name }}
                                                <span
                                                    v-if="d.product_type_name"
                                                    class="text-muted-foreground"
                                                >
                                                    —
                                                    {{
                                                        d.product_type_name
                                                    }}</span
                                                >
                                            </td>
                                            <td
                                                class="py-1.5 text-right font-mono"
                                            >
                                                {{ qtySign }}{{ d.qty }}
                                            </td>
                                            <td
                                                class="py-1.5 text-right font-mono"
                                            >
                                                {{ formatMoney(d.unit_cost) }}
                                            </td>
                                            <td
                                                class="py-1.5 text-right font-mono"
                                            >
                                                {{ formatMoney(d.line_total) }}
                                            </td>
                                            <td
                                                class="py-1.5 text-right font-mono text-muted-foreground"
                                            >
                                                {{ d.stock_before }} →
                                                <span
                                                    class="font-semibold text-foreground"
                                                    >{{ d.stock_after }}</span
                                                >
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div
            v-if="transactions.data.length > 0"
            class="flex flex-col divide-y rounded-xl border md:hidden"
        >
            <div
                v-for="tx in transactions.data"
                :key="`m-${tx.id}`"
                class="flex flex-col gap-2 p-3"
            >
                <button
                    type="button"
                    class="flex items-start gap-2 text-left"
                    @click="toggle(tx.id)"
                >
                    <ChevronDown
                        v-if="expanded.has(tx.id)"
                        class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
                    />
                    <ChevronRight
                        v-else
                        class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-mono text-xs text-muted-foreground">
                                {{ formatDate(tx.created_at) }}
                            </p>
                            <span
                                v-if="
                                    tx.total_cost !== null &&
                                    tx.total_cost !== undefined
                                "
                                class="shrink-0 font-mono text-sm font-semibold"
                            >
                                {{ formatMoney(tx.total_cost) }}
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >{{ symbol }}</span
                                >
                            </span>
                        </div>
                        <p class="mt-1 text-sm font-medium">
                            {{
                                t(
                                    'pageDealer.products.stockHistory.itemsCount',
                                    { n: tx.items_count ?? 0 },
                                )
                            }}
                            <span class="text-muted-foreground"
                                >· {{ tx.total_qty ?? 0 }}
                                {{
                                    t(
                                        'pageDealer.products.stockHistory.unitPiece',
                                    )
                                }}</span
                            >
                        </p>
                        <div
                            class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground"
                        >
                            <span v-if="tx.actor_name">{{
                                tx.actor_name
                            }}</span>
                            <button
                                v-if="
                                    counterpartName(tx) && counterpartLink(tx)
                                "
                                class="text-primary hover:underline"
                                @click.stop="router.get(counterpartLink(tx)!)"
                            >
                                {{ counterpartName(tx) }}
                            </button>
                        </div>
                        <p
                            v-if="tx.note"
                            class="mt-1 line-clamp-2 text-xs text-muted-foreground"
                        >
                            {{ tx.note }}
                        </p>
                    </div>
                </button>
                <div
                    v-if="expanded.has(tx.id) && tx.details"
                    class="space-y-1.5 rounded-md bg-muted/40 p-2 text-xs"
                >
                    <div
                        v-for="d in tx.details"
                        :key="d.id"
                        class="flex flex-col gap-0.5 border-b border-border/50 pb-1.5 last:border-0 last:pb-0"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="min-w-0 flex-1 font-medium">
                                {{ d.product_name
                                }}<span
                                    v-if="d.product_type_name"
                                    class="text-muted-foreground"
                                >
                                    — {{ d.product_type_name }}</span
                                >
                            </span>
                            <span class="shrink-0 font-mono"
                                >{{ qtySign }}{{ d.qty }}</span
                            >
                        </div>
                        <div class="flex justify-between text-muted-foreground">
                            <span class="font-mono"
                                >{{ formatMoney(d.unit_cost) }} ×
                                {{ d.qty }}</span
                            >
                            <span class="font-mono">{{
                                formatMoney(d.line_total)
                            }}</span>
                        </div>
                        <div class="text-[11px] text-muted-foreground">
                            {{
                                t('pageDealer.products.stockHistory.stockLabel')
                            }}
                            {{ d.stock_before }} →
                            <span class="font-semibold text-foreground">{{
                                d.stock_after
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="transactions.meta.last_page > 1"
            class="flex flex-wrap justify-center gap-1"
        >
            <Button
                v-for="page in transactions.meta.last_page"
                :key="page"
                :variant="
                    page === transactions.meta.current_page
                        ? 'default'
                        : 'outline'
                "
                size="sm"
                @click="goToPage(page)"
            >
                {{ page }}
            </Button>
        </div>

        <Dialog
            v-if="currentType === 'shop_return'"
            v-model:open="showPickShop"
        >
            <DialogContent
                class="flex h-[100dvh] max-h-[100dvh] w-screen max-w-none flex-col gap-0 rounded-none border-0 p-0 sm:h-[min(720px,calc(100dvh-2rem))] sm:max-h-[calc(100dvh-2rem)] sm:w-auto sm:max-w-2xl sm:rounded-lg sm:border lg:max-w-3xl"
                @open-auto-focus="(e: Event) => e.preventDefault()"
            >
                <DialogHeader class="border-b p-4 sm:p-6 sm:pb-4">
                    <DialogTitle class="pr-8 text-base sm:text-lg">{{
                        t('pageDealer.products.stockHistory.pickShopTitle')
                    }}</DialogTitle>
                    <p class="text-xs text-muted-foreground">
                        {{ t('pageDealer.products.stockHistory.pickShopHint') }}
                    </p>
                </DialogHeader>

                <div class="flex flex-1 flex-col overflow-hidden">
                    <div class="border-b px-4 py-3 sm:px-6">
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <input
                                v-model="shopSearch"
                                type="text"
                                :placeholder="
                                    t(
                                        'pageDealer.products.stockHistory.shopSearchPlaceholder',
                                    )
                                "
                                class="h-10 w-full rounded-md border border-input bg-background pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-3 sm:p-4">
                        <div
                            v-if="filteredShops.length === 0"
                            class="rounded-lg border border-dashed bg-muted/20 px-3 py-8 text-center text-sm text-muted-foreground"
                        >
                            {{
                                t(
                                    'pageDealer.products.stockHistory.shopNotFound',
                                )
                            }}
                        </div>
                        <div v-else class="space-y-1.5">
                            <button
                                v-for="s in filteredShops"
                                :key="s.id"
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg border bg-background p-3 text-left transition-colors hover:border-primary/60 hover:bg-primary/[0.04]"
                                @click="pickShop(s)"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10"
                                >
                                    <Store class="h-4 w-4 text-primary" />
                                </div>
                                <span
                                    class="min-w-0 flex-1 truncate text-sm font-medium"
                                    >{{ s.name }}</span
                                >
                            </button>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t p-3 sm:p-6 sm:pt-4">
                    <Button
                        variant="outline"
                        class="w-full sm:w-auto"
                        @click="showPickShop = false"
                        >{{
                            t('pageDealer.products.stockHistory.cancel')
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ShopReturnFreeFormModal
            v-if="currentType === 'shop_return' && pickedShop !== null"
            v-model:open="showShopReturn"
            :shop-id="pickedShop.id"
            :shop-name="pickedShop.name"
            :shop-balance="pickedShop.balance ?? 0"
            :products="products ?? []"
        />

        <Dialog
            v-if="currentType === 'supplier_return'"
            v-model:open="showPickSupplier"
        >
            <DialogContent
                class="flex h-[100dvh] max-h-[100dvh] w-screen max-w-none flex-col gap-0 rounded-none border-0 p-0 sm:h-auto sm:max-h-[calc(100dvh-2rem)] sm:w-auto sm:max-w-md sm:rounded-lg sm:border"
                @open-auto-focus="(e: Event) => e.preventDefault()"
            >
                <DialogHeader class="border-b p-4 sm:p-6 sm:pb-4">
                    <DialogTitle class="pr-8 text-base sm:text-lg">{{
                        t('pageDealer.products.stockHistory.pickSupplierTitle')
                    }}</DialogTitle>
                    <p class="text-xs text-muted-foreground">
                        {{
                            t(
                                'pageDealer.products.stockHistory.pickSupplierHint',
                            )
                        }}
                    </p>
                </DialogHeader>

                <div class="flex flex-1 flex-col overflow-hidden">
                    <div class="border-b px-4 py-3 sm:px-6">
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <input
                                v-model="supplierSearch"
                                type="text"
                                :placeholder="
                                    t(
                                        'pageDealer.products.stockHistory.supplierSearchPlaceholder',
                                    )
                                "
                                class="h-10 w-full rounded-md border border-input bg-background pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-ring"
                            />
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto p-3 sm:p-4">
                        <div
                            v-if="filteredSuppliers.length === 0"
                            class="rounded-lg border border-dashed bg-muted/20 px-3 py-8 text-center text-sm text-muted-foreground"
                        >
                            {{
                                t(
                                    'pageDealer.products.stockHistory.supplierNotFound',
                                )
                            }}
                        </div>
                        <div v-else class="space-y-1.5">
                            <button
                                v-for="s in filteredSuppliers"
                                :key="s.id"
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg border bg-background p-3 text-left transition-colors hover:border-primary/60 hover:bg-primary/[0.04]"
                                @click="pickSupplier(s)"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10"
                                >
                                    <Truck class="h-4 w-4 text-primary" />
                                </div>
                                <span
                                    class="min-w-0 flex-1 truncate text-sm font-medium"
                                    >{{ s.name }}</span
                                >
                            </button>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t p-3 sm:p-6 sm:pt-4">
                    <Button
                        variant="outline"
                        class="w-full sm:w-auto"
                        @click="showPickSupplier = false"
                        >{{
                            t('pageDealer.products.stockHistory.cancel')
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <SupplierReturnModal
            v-if="currentType === 'supplier_return' && pickedSupplier !== null"
            v-model:open="showSupplierReturn"
            :supplier-id="pickedSupplier.id"
            :supplier-name="pickedSupplier.name"
            :supplier-balance="pickedSupplier.balance ?? 0"
            :products="products ?? []"
        />
    </div>
</template>
