<script setup lang="ts">
import { computed, nextTick, onActivated, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { AlertTriangle, Check, ChevronDown, MessageSquare, Phone, Quote, Receipt, X } from 'lucide-vue-next';
import { formatDateTime } from '@/lib/date';
import ProductDetailModal from '../components/ProductDetailModal.vue';
import type {ProductInfo} from '../components/ProductDetailModal.vue';
import { getActiveShopId, useApi } from '../composables/useApi';
import { readCache, writeCache } from '../composables/usePersistentCache';

const api = useApi();
const { t } = useI18n();

const ORDERS_TTL = 5 * 60 * 1000; // 5 daqiqa — buyurtmalar tez-tez o'zgaradi

const actionError = ref('');

async function receiveOrder(order: Order) {
    if (!confirm(t('miniappPages.orders.receiveConfirm', { id: order.number }))) {
        return;
    }

    // Optimistic — darhol "Qabul qilindi" badge ko'rinadi
    const target = orders.value.find((o) => o.id === order.id);
    const previous = target ? {
        received_at: target.received_at ?? null,
        status: target.status,
        status_label: target.status_label,
    } : null;

    if (target) {
        target.received_at = new Date().toISOString();
        target.status = 'received';
        target.status_label = t('miniappPages.orders.statusReceived');
    }

    try {
        const res: { order_id: number; status: string; status_label: string; received_at: string | null } =
            await api.post(`/orders/${order.id}/receive`);

        if (target) {
            target.received_at = res.received_at;
            target.status = res.status;
            target.status_label = res.status_label;
        }
    } catch (e: any) {
        // Qaytarib olamiz
        if (target && previous) {
            target.received_at = previous.received_at;
            target.status = previous.status;
            target.status_label = previous.status_label;
        }

        actionError.value = e.message ?? t('miniappPages.orders.actionError');
    }
}

type OrderItem = {
    id: number;
    product_id: number;
    product_name: string;
    price: number;
    qty: number;
    delivered_qty?: number | null;
    picked_qty?: number | null;
    picked_pack_qty?: number | null;
    unit?: string | null;
    pack_size?: number | null;
    pack_qty?: number | null;
    subtotal: number;
    delivered_subtotal?: number | null;
    prepared_subtotal?: number | null;
};

function itemDisplaySubtotal(order: Order, item: OrderItem): number {
    if (order.status === 'delivered' || order.status === 'received') {
        return item.delivered_subtotal ?? item.subtotal;
    }

    if (order.status === 'assembling' || order.status === 'delivering') {
        return item.prepared_subtotal ?? item.subtotal;
    }

    return item.subtotal;
}

function itemSubtotalChanged(order: Order, item: OrderItem): boolean {
    return itemDisplaySubtotal(order, item) !== item.subtotal;
}

type Deliveryman = { id: number; name: string; phone?: string | null };

type OrderMessage = {
    id: number;
    body: string;
    created_at: string | null;
    edited: boolean;
};

type Order = {
    id: number; number: number; status: string; status_label: string;
    total: number; created_at: string; items?: OrderItem[];
    received_at?: string | null;
    delivered_at?: string | null;
    delivered_total?: number | null;
    prepared_total?: number | null;
    display_total?: number | null;
    paid_amount?: number | null;
    discount?: number | null;
    note?: string | null;
    deliveryman?: Deliveryman | null;
};

const props = defineProps<{ deepLinkOrderId?: number | null; deepLinkMessageId?: number | null }>();

function orderDisplayTotal(order: Order): number {
    if (order.status === 'delivered' || order.status === 'received') {
        return order.delivered_total ?? order.display_total ?? order.total;
    }

    if (order.status === 'assembling' || order.status === 'delivering') {
        return order.display_total ?? order.prepared_total ?? order.total;
    }

    return order.total;
}

function orderTotalChanged(order: Order): boolean {
    return orderDisplayTotal(order) !== order.total;
}

type StatusFilter = '' | 'active' | 'pending' | 'assembling' | 'delivering' | 'delivered' | 'received' | 'cancelled';
type SortKey = 'newest' | 'oldest' | 'total_desc' | 'total_asc';

const orders = ref<Order[]>([]);
const loading = ref(true);
const loadingMore = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const total = ref(0);
const expandedId = ref<number | null>(null);

// Buyurtma detali (xabarlar) — ro'yxatdan MUSTAQIL reaktiv kesh.
// load() ro'yxatni qayta yozsa ham bu yo'qolmaydi; bir marta yuklanadi.
const messagesById = reactive<Record<number, OrderMessage[]>>({});
const detailLoading = reactive<Record<number, boolean>>({});

// Deep-link orqali kelgan xabar — scroll + vaqtinchalik highlight
const highlightedMsgId = ref<number | null>(null);
let highlightTimer: number | null = null;

const statusFilter = ref<StatusFilter>('active');
const sort = ref<SortKey>('newest');

const selectedProduct = ref<ProductInfo | null>(null);
const modalOpen = ref(false);
const loadingProduct = ref(false);

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const statusColors: Record<string, string> = {
    pending: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    assembling: 'bg-orange-500/15 text-orange-700 dark:text-orange-300',
    delivering: 'bg-blue-500/15 text-blue-700 dark:text-blue-300',
    delivered: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    received: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    cancelled: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
};

const statusOptions = computed<Array<{ value: StatusFilter; label: string }>>(() => [
    { value: 'active', label: t('miniappPages.orders.filterActive') },
    { value: '', label: t('miniappPages.orders.filterAll') },
    { value: 'pending', label: t('miniappPages.orders.filterPending') },
    { value: 'assembling', label: t('miniappPages.orders.filterAssembling') },
    { value: 'delivering', label: t('miniappPages.orders.filterDelivering') },
    { value: 'delivered', label: t('miniappPages.orders.filterDelivered') },
    { value: 'received', label: t('miniappPages.orders.filterReceived') },
    { value: 'cancelled', label: t('miniappPages.orders.filterCancelled') },
]);

const sortOptions = computed<Array<{ value: SortKey; label: string }>>(() => [
    { value: 'newest', label: t('miniappPages.orders.sortNewest') },
    { value: 'oldest', label: t('miniappPages.orders.sortOldest') },
    { value: 'total_desc', label: t('miniappPages.orders.sortTotalDesc') },
    { value: 'total_asc', label: t('miniappPages.orders.sortTotalAsc') },
]);

const hasMore = computed(() => currentPage.value < lastPage.value);

function buildUrl(page: number): string {
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('sort', sort.value);

    if (statusFilter.value) {
params.set('status', statusFilter.value);
}

    return `/orders?${params.toString()}`;
}

function ordersCacheKey(): string | null {
    const shopId = getActiveShopId();

    if (!shopId) {
        return null;
    }

    // Faqat default holat keshlanadi (Aktiv filter, newest tartib)
    if (statusFilter.value !== 'active' || sort.value !== 'newest') {
        return null;
    }

    return `orders:${shopId}:p1:active`;
}

type OrdersCachePayload = { orders: Order[]; lastPage: number; total: number };

// KeepAlive sabab onMounted + onActivated bir vaqtda load() chaqirishi mumkin —
// takror tarmoq so'rovini oldini olamiz.
let loadInflight = false;

async function load() {
    const cacheKey = ordersCacheKey();

    // 1) Keshdan darhol ko'rsatamiz
    if (cacheKey) {
        const cached = readCache<OrdersCachePayload>(cacheKey, ORDERS_TTL);

        if (cached) {
            orders.value = cached.orders;
            lastPage.value = cached.lastPage;
            total.value = cached.total;
            currentPage.value = 1;
            loading.value = false;
        }
    }

    if (loadInflight) {
        return;
    }

    loadInflight = true;

    // Faqat birinchi yuklashda skeleton
    if (orders.value.length === 0) {
        loading.value = true;
    }

    currentPage.value = 1;

    // 2) Foncha yangilaymiz
    try {
        const res: any = await api.get(buildUrl(1));
        orders.value = res.data ?? [];
        lastPage.value = res.meta?.last_page ?? 1;
        total.value = res.meta?.total ?? orders.value.length;

        if (cacheKey) {
            writeCache<OrdersCachePayload>(cacheKey, {
                orders: orders.value,
                lastPage: lastPage.value,
                total: total.value,
            });
        }
    } catch { /* */ }

    loadInflight = false;
    loading.value = false;

    // Ro'yxat tayyor — deep-link buyurtmaga detail (xabarlar) yuklaymiz (refreshsiz ko'rinsin)
    void applyDeepLink();
}

async function loadMore() {
    if (loadingMore.value || loading.value) {
return;
}

    if (!hasMore.value) {
return;
}

    loadingMore.value = true;
    const next = currentPage.value + 1;

    try {
        const res: any = await api.get(buildUrl(next));
        orders.value = [...orders.value, ...(res.data ?? [])];
        currentPage.value = next;
        lastPage.value = res.meta?.last_page ?? lastPage.value;
        total.value = res.meta?.total ?? total.value;
    } catch { /* */ }

    loadingMore.value = false;
}

// Bitta buyurtma detalini (xabarlarni) keshlab tortadi. Bir marta — qayta ochishda
// tarmoqqa bormaydi (force=true bo'lmasa).
async function loadDetail(id: number, force = false): Promise<void> {
    if (detailLoading[id] || (!force && messagesById[id] !== undefined)) {
        return;
    }

    detailLoading[id] = true;

    try {
        const res: any = await api.get(`/orders/${id}`);
        const data = res.data ?? res;
        messagesById[id] = data.messages ?? [];
    } catch { /* */ }
    finally {
        detailLoading[id] = false;
    }
}

function toggleExpand(order: Order): void {
    if (expandedId.value === order.id) {
        expandedId.value = null;

        return;
    }

    expandedId.value = order.id;
    void loadDetail(order.id);
}

// Botdagi "Buyurtmani ochish" deep-link.
// Detail kesh (messagesById) ro'yxatdan mustaqil — load() ro'yxatni qayta yozsa ham
// xabarlar yo'qolmaydi. Avtomatik ochish faqat bir marta (user collapse qilsa qaytmasin).
let autoExpanded = false;
let scrolledMsg: number | null = null;
const pinnedOrder = ref<Order | null>(null);

// Berilgan xabarga scroll qilib, qisqa vaqt highlight qiladi.
// Element hali DOM'da bo'lmasa false qaytaradi (keyinroq qayta urinamiz).
function highlightMessage(msgId: number): boolean {
    const el = document.querySelector(`[data-msg-id="${msgId}"]`) as HTMLElement | null;

    if (!el) {
        return false;
    }

    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    highlightedMsgId.value = msgId;

    if (highlightTimer) {
        clearTimeout(highlightTimer);
    }

    highlightTimer = window.setTimeout(() => {
        if (highlightedMsgId.value === msgId) {
            highlightedMsgId.value = null;
        }
    }, 2000);

    return true;
}

// Deep-link buyurtma joriy filtr ro'yxatida bo'lmasa, qatorni qo'shamiz (bir marta fetch).
async function ensureDeepLinkRow(id: number): Promise<boolean> {
    if (orders.value.some((o) => o.id === id)) {
        return true;
    }

    if (pinnedOrder.value?.id === id) {
        orders.value = [pinnedOrder.value, ...orders.value];

        return true;
    }

    try {
        const res: any = await api.get(`/orders/${id}`);
        const data = res.data ?? res;
        messagesById[id] = data.messages ?? [];
        pinnedOrder.value = data;
        orders.value = [data, ...orders.value];

        return true;
    } catch {
        return false;
    }
}

async function applyDeepLink(): Promise<void> {
    const id = props.deepLinkOrderId;

    if (!id || id <= 0) {
        return;
    }

    if (!(await ensureDeepLinkRow(id))) {
        return;
    }

    if (!autoExpanded) {
        expandedId.value = id;
        autoExpanded = true;
    }

    await loadDetail(id);

    // Aniq xabarga scroll + highlight (bir marta). Element render bo'lguncha qayta urinamiz
    // (skeleton/expand tugamaguncha DOM'da bo'lmasligi mumkin).
    // msg= bo'lmasa (eski tugmalar) — oxirgi xabarga scroll qilamiz.
    const thread = messagesById[id] ?? [];
    const msgId = (props.deepLinkMessageId && props.deepLinkMessageId > 0)
        ? props.deepLinkMessageId
        : (thread.length ? thread[thread.length - 1].id : null);

    if (msgId && msgId > 0 && scrolledMsg !== msgId) {
        await nextTick();

        for (let attempt = 0; attempt < 12; attempt++) {
            if (scrolledMsg === msgId) {
                break;
            }

            if (highlightMessage(msgId)) {
                scrolledMsg = msgId;
                break;
            }

            await new Promise((r) => setTimeout(r, 120));
        }
    }
}

watch(() => [props.deepLinkOrderId, props.deepLinkMessageId], () => {
    if (props.deepLinkOrderId && props.deepLinkOrderId > 0) {
        autoExpanded = false;
        scrolledMsg = null;
        void applyDeepLink();
    }
}, { immediate: true });

onMounted(() => {
    load();

    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) {
loadMore();
}
        },
        { rootMargin: '400px 0px' },
    );
});

// KeepAlive ostida — har faollashganda yangi buyurtmalar darhol ko'rinadi
onActivated(() => {
    load();
});

watch(sentinel, (el, _old) => {
    if (!observer) {
return;
}

    if (_old) {
observer.unobserve(_old);
}

    if (el) {
observer.observe(el);
}
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;

    if (highlightTimer) {
        clearTimeout(highlightTimer);
    }
});

watch([statusFilter, sort], () => {
    // Filtr/tartib o'zgarsa yig'amiz; oddiy refresh (load) ochiq holatni saqlaydi.
    expandedId.value = null;
    load();
});

async function openProduct(item: OrderItem) {
    // Modal darhol ochiladi — buyurtma elementidan ma'lumotlar bilan
    selectedProduct.value = {
        id: item.product_id,
        name: item.product_name,
        code: null,
        description: null,
        price: item.price,
        original_price: null,
        discount_percent: 0,
        unit: item.unit ?? 'dona',
        image_url: null,
        images: [],
        pack_size: item.pack_size ?? 1,
        pack_price: item.price * (item.pack_size ?? 1),
    };
    modalOpen.value = true;

    if (loadingProduct.value) {
        return;
    }

    loadingProduct.value = true;

    try {
        const res: any = await api.get(`/products/${item.product_id}`);
        const fullProduct = res.data ?? res;

        if (selectedProduct.value?.id === item.product_id) {
            selectedProduct.value = fullProduct;
        }
    } catch { /* */ }

    loadingProduct.value = false;
}

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

const formatDate = formatDateTime;
</script>

<template>
    <div class="mx-auto w-full max-w-4xl p-3 sm:p-4">
        <h2 class="mb-3 text-xl font-bold tracking-tight">{{ t('miniappPages.orders.title') }}</h2>

        <!-- Status filter chips (horizontally scrollable) -->
        <div class="-mx-3 mb-2 overflow-x-auto px-3 sm:-mx-4 sm:px-4">
            <div class="flex gap-2 pb-1">
                <button
                    v-for="opt in statusOptions"
                    :key="opt.value"
                    type="button"
                    class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="statusFilter === opt.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-background text-foreground/80 hover:bg-muted'"
                    @click="statusFilter = opt.value"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <!-- Sort + count -->
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-xs text-muted-foreground">
                <template v-if="!loading">{{ t('miniappPages.orders.totalCountLabel') }} <span class="font-medium text-foreground">{{ total }}</span> {{ t('miniappPages.orders.totalCountSuffix') }}</template>
                <template v-else>{{ t('miniappPages.orders.loading') }}</template>
            </p>
            <div class="relative">
                <select
                    v-model="sort"
                    class="rounded-lg border bg-background py-1.5 pl-3 pr-8 text-xs outline-none focus:ring-2 focus:ring-primary"
                >
                    <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>
        </div>

        <!-- Skeleton -->
        <div v-if="loading" class="space-y-3">
            <div
                v-for="n in 5"
                :key="n"
                class="flex items-center justify-between rounded-xl border p-3"
            >
                <div class="flex-1 space-y-2">
                    <div class="h-4 w-16 animate-pulse rounded bg-muted" />
                    <div class="h-3 w-24 animate-pulse rounded bg-muted" />
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-4 w-20 animate-pulse rounded bg-muted" />
                    <div class="h-5 w-16 animate-pulse rounded-full bg-muted" />
                </div>
            </div>
        </div>

        <!-- List -->
        <div v-else-if="orders.length" class="space-y-2.5">
            <div
                v-for="order in orders"
                :key="order.id"
                class="overflow-hidden rounded-2xl border bg-card transition-shadow hover:shadow-sm"
            >
                <button
                    class="flex w-full items-center justify-between gap-3 p-3 text-left transition-colors hover:bg-muted/30"
                    @click="toggleExpand(order)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm font-bold">#{{ order.number }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="statusColors[order.status] ?? 'bg-muted text-muted-foreground'">
                                {{ order.status_label }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">{{ formatDate(order.created_at) }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <div class="flex flex-col items-end leading-tight">
                            <span class="font-mono text-sm font-bold tabular-nums">{{ formatMoney(orderDisplayTotal(order)) }}</span>
                            <span
                                v-if="orderTotalChanged(order)"
                                class="font-mono text-[10px] font-normal text-muted-foreground line-through tabular-nums"
                            >
                                {{ formatMoney(order.total) }}
                            </span>
                        </div>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="expandedId === order.id ? 'rotate-180' : ''"
                        />
                    </div>
                </button>

                <div v-if="expandedId === order.id && order.items?.length" class="divide-y border-t bg-muted/20">
                    <button
                        v-for="item in order.items"
                        :key="item.id"
                        type="button"
                        class="flex w-full items-start justify-between gap-3 p-3 text-left text-sm transition-colors hover:bg-muted/40"
                        @click="openProduct(item)"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ item.product_name }}</p>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="item.pack_qty && item.pack_size && item.pack_size > 1 && item.qty > item.pack_qty * item.pack_size">
                                    {{ t('miniappPages.cart.blockPlusUnits', { packs: item.pack_qty, total: item.pack_qty * item.pack_size, loose: item.qty - item.pack_qty * item.pack_size, unit: item.unit, qty: item.qty }) }}
                                </template>
                                <template v-else-if="item.pack_qty && item.pack_size && item.pack_size > 1">
                                    {{ item.pack_qty }} {{ t('miniappPages.cart.blockLabel').toLowerCase() }} × {{ item.pack_size }} {{ item.unit }}
                                </template>
                                <template v-else>
                                    {{ item.qty }} {{ item.unit ?? '' }}
                                </template>
                            </p>
                            <p
                                v-if="order.delivered_at && item.delivered_qty != null && item.delivered_qty !== item.qty"
                                class="mt-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300"
                            >
                                {{ t('miniappPages.orders.delivered', { qty: item.delivered_qty, unit: item.unit ?? '' }) }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-mono text-sm">{{ formatMoney(itemDisplaySubtotal(order, item)) }}</p>
                            <p
                                v-if="itemSubtotalChanged(order, item)"
                                class="font-mono text-[11px] text-muted-foreground line-through"
                            >
                                {{ formatMoney(item.subtotal) }}
                            </p>
                        </div>
                    </button>

                    <!-- Buyurtma xulosasi: yetkazib beruvchi va to'lov ma'lumotlari -->
                    <div class="space-y-1.5 border-t bg-background/60 p-3 text-xs">
                        <div v-if="order.deliveryman" class="flex items-start justify-between gap-2">
                            <span class="text-muted-foreground">{{ t('miniappPages.orders.deliveryman') }}</span>
                            <div class="text-right">
                                <span class="font-medium">{{ order.deliveryman.name }}</span>
                                <a
                                    v-if="order.deliveryman.phone"
                                    :href="`tel:${order.deliveryman.phone}`"
                                    class="mt-0.5 flex items-center justify-end gap-1 font-mono text-[11px] text-primary hover:underline"
                                    @click.stop
                                >
                                    <Phone class="h-3 w-3" />
                                    {{ order.deliveryman.phone }}
                                </a>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-muted-foreground">{{ t('miniappPages.orders.orderTotal') }}</span>
                            <span class="text-right">
                                <span class="font-mono font-semibold">{{ formatMoney(orderDisplayTotal(order)) }}&nbsp;so'm</span>
                                <span
                                    v-if="orderTotalChanged(order)"
                                    class="block font-mono text-[10px] font-normal text-muted-foreground line-through"
                                >
                                    {{ formatMoney(order.total) }}&nbsp;so'm
                                </span>
                            </span>
                        </div>
                        <template v-if="order.delivered_at">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-muted-foreground">{{ t('miniappPages.orders.deliveredAmount') }}</span>
                                <span class="font-mono font-semibold">{{ formatMoney(order.delivered_total ?? 0) }}&nbsp;so'm</span>
                            </div>
                            <div
                                v-if="(order.discount ?? 0) > 0"
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="text-muted-foreground">{{ t('miniappPages.orders.discount') }}</span>
                                <span class="font-mono font-semibold text-rose-600 dark:text-rose-400">
                                    −{{ formatMoney(order.discount ?? 0) }}&nbsp;so'm
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-muted-foreground">{{ t('miniappPages.orders.paid') }}</span>
                                <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ formatMoney(order.paid_amount ?? 0) }}&nbsp;so'm
                                </span>
                            </div>
                            <div
                                v-if="(order.delivered_total ?? 0) - (order.discount ?? 0) - (order.paid_amount ?? 0) !== 0"
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="text-muted-foreground">{{ t('miniappPages.orders.debt') }}</span>
                                <span
                                    class="font-mono font-semibold"
                                    :class="(order.delivered_total ?? 0) - (order.discount ?? 0) - (order.paid_amount ?? 0) > 0
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : 'text-emerald-600 dark:text-emerald-400'"
                                >
                                    {{ formatMoney((order.delivered_total ?? 0) - (order.discount ?? 0) - (order.paid_amount ?? 0)) }}&nbsp;so'm
                                </span>
                            </div>
                        </template>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t bg-background p-2">
                        <button
                            v-if="order.status === 'delivered' && !order.received_at"
                            type="button"
                            class="flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition-all hover:bg-emerald-700 active:scale-95"
                            @click.stop="receiveOrder(order)"
                        >
                            <Check class="h-3.5 w-3.5" />
                            {{ t('miniappPages.orders.receiveBtn') }}
                        </button>
                        <span
                            v-else-if="order.received_at"
                            class="flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300"
                        >
                            <Check class="h-3 w-3" />
                            {{ t('miniappPages.orders.receivedBadge') }}
                        </span>
                    </div>

                    <!-- Mijoz izohi -->
                    <div v-if="order.note" class="border-t bg-background p-3">
                        <div class="flex items-start gap-2 rounded-xl bg-muted/30 px-3 py-2">
                            <Quote class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground/60" />
                            <p class="text-sm italic leading-snug text-muted-foreground">{{ order.note }}</p>
                        </div>
                    </div>

                    <!-- Diller xabarlari — note ostida -->
                    <div v-if="detailLoading[order.id] && !messagesById[order.id]" class="border-t bg-background p-3">
                        <div class="h-10 w-full animate-pulse rounded-lg bg-muted" />
                    </div>
                    <div v-else-if="messagesById[order.id]?.length" class="space-y-2 border-t bg-background p-3">
                        <p class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                            <MessageSquare class="h-3.5 w-3.5" />
                            {{ t('miniappPages.orders.messagesTitle') }}
                        </p>
                        <div
                            v-for="m in messagesById[order.id]"
                            :key="m.id"
                            :data-msg-id="m.id"
                            class="relative overflow-hidden rounded-xl bg-muted/40 px-3 py-2"
                        >
                            <span
                                v-if="highlightedMsgId === m.id"
                                class="tg-flash pointer-events-none absolute inset-0"
                            />
                            <p class="relative whitespace-pre-wrap text-sm leading-snug">{{ m.body }}</p>
                            <p class="relative mt-1 text-[10px] text-muted-foreground">
                                {{ m.created_at ? formatDate(m.created_at) : '' }}
                                <span v-if="m.edited" class="italic"> · {{ t('miniappPages.orders.messageEdited') }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Infinite scroll sentinel -->
            <div
                v-if="hasMore"
                ref="sentinel"
                class="flex items-center justify-center py-6"
            >
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-primary/30 border-t-primary" />
                    {{ t('miniappPages.orders.loadingMore') }}
                </div>
            </div>

            <p v-else class="py-4 text-center text-xs text-muted-foreground">
                {{ t('miniappPages.orders.allShown', { shown: orders.length, total: total }) }}
            </p>
        </div>

        <!-- Empty -->
        <div v-else class="flex flex-col items-center py-20 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                <Receipt class="h-7 w-7" />
            </div>
            <p class="mt-4 text-base font-semibold">
                <template v-if="statusFilter === 'active'">{{ t('miniappPages.orders.emptyActive') }}</template>
                <template v-else-if="statusFilter">{{ t('miniappPages.orders.emptyStatus') }}</template>
                <template v-else>{{ t('miniappPages.orders.emptyAll') }}</template>
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ t('miniappPages.orders.emptyHint') }}
            </p>
        </div>

        <ProductDetailModal
            :product="selectedProduct"
            :open="modalOpen"
            :show-add-to-cart="false"
            @close="modalOpen = false"
        />

        <Teleport to="body">
            <button
                v-if="actionError"
                type="button"
                class="fixed left-1/2 top-4 z-50 flex -translate-x-1/2 items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-medium text-rose-700 shadow-lg dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200"
                @click="actionError = ''"
            >
                <AlertTriangle class="h-4 w-4" />
                {{ actionError }}
                <X class="ml-1 h-3.5 w-3.5 opacity-60" />
            </button>
        </Teleport>
    </div>
</template>

<style scoped>
/* Telegram reply-jump kabi: xabar foni qisqa porlab so'nadi */
.tg-flash {
    background-color: color-mix(in srgb, var(--primary, #3390ec) 24%, transparent);
    animation: tg-flash 2s ease-out forwards;
}

@keyframes tg-flash {
    0% { opacity: 0; }
    12% { opacity: 1; }
    35% { opacity: 1; }
    100% { opacity: 0; }
}
</style>
