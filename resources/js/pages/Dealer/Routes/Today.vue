<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Map,
    MapPinOff,
    Package,
    Truck,
    Undo2,
    XCircle,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import LoadingRouteModal from '@/components/dealer/loading-route/LoadingRouteModal.vue';
// Leaflet `window` ga tayanadi → SSR'da yiqiladi. Faqat klientda lazy yuklanadi.
const OrdersMap = defineAsyncComponent(
    () => import('@/components/dealer/OrdersMap.vue'),
);
import RouteOrderCard from '@/components/dealer/RouteOrderCard.vue';

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';

type ShopInfo = {
    id: number;
    name: string;
    address: string | null;
    region: string | null;
    district: string | null;
    phone: string | null;
    latitude: number | null;
    longitude: number | null;
};

function formatLocation(shop: ShopInfo): string {
    return [shop.region, shop.district]
        .filter((v): v is string => Boolean(v && v.trim()))
        .join(', ');
}

type Order = {
    id: number;
    number: number;
    // Ombordan optimal yetkazib berish tartibi (null = marshrutda emas).
    delivery_position: number | null;
    status: string;
    status_label: string;
    total: number;
    delivered_total: number | null;
    prepared_total?: number | null;
    display_total?: number | null;
    discount: number | null;
    items_count: number;
    note: string | null;
    created_at: string;
    delivering_at: string | null;
    delivered_at: string | null;
    received_at: string | null;
    has_pending_return?: boolean;
    shop: ShopInfo | null;
};

type Deliveryman = { id: number; name: string };

type Stats = {
    total: number;
    total_amount: number;
    completed: number;
    completed_amount: number;
    remaining: number;
    cancelled: number;
    pending_return: number;
};

const props = defineProps<{
    orders: Order[];
    stats: Stats;
    date: string;
    deliverymen: Deliveryman[];
    selectedDeliverymanId: number | null;
}>();

function changeDeliveryman(id: string) {
    router.get(
        '/dealer/routes/today',
        id ? { deliveryman_id: id, date: props.date } : { date: props.date },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function changeDate(v: string) {
    const params: Record<string, string> = { date: v };

    if (props.selectedDeliverymanId) {
        params.deliveryman_id = String(props.selectedDeliverymanId);
    }

    router.get('/dealer/routes/today', params, {
        preserveState: true,
        preserveScroll: true,
    });
}

// Server ombordan optimal yetkazib berish tartibini bersa — shu tartibda
// chiqaramiz va OrdersMap'ga preordered=true uzatamiz.
const routeOptimized = computed(() =>
    props.orders.some((o) => o.delivery_position !== null),
);

const mapPoints = computed(() =>
    props.orders
        .filter(
            (o) =>
                o.shop !== null &&
                o.shop.latitude !== null &&
                o.shop.longitude !== null,
        )
        .slice()
        .sort(
            (a, b) =>
                (a.delivery_position ?? Infinity) -
                (b.delivery_position ?? Infinity),
        )
        .map((o) => {
            const loc = formatLocation(o.shop!);
            const addr = o.shop!.address;
            const fullAddress = [loc, addr]
                .filter((v): v is string => Boolean(v))
                .join(' · ');

            return {
                id: o.id,
                number: o.number,
                status: o.status,
                shopName: o.shop!.name,
                shopAddress: fullAddress || null,
                latitude: o.shop!.latitude as number,
                longitude: o.shop!.longitude as number,
            };
        }),
);

const page = usePage<{ auth: { role: string | null } }>();
const isDeliveryman = computed(() => page.props.auth?.role === 'deliveryman');

const orderCards = ref<Record<number, HTMLElement | null>>({});
const showMap = ref(true);
const showOrders = ref(false);

type StatFilter = 'remaining' | 'completed' | 'cancelled' | 'pendingReturn';

const activeFilter = ref<StatFilter | null>(null);

function filterOrders(filter: StatFilter): Order[] {
    switch (filter) {
        case 'remaining':
            return props.orders.filter((o) =>
                ['pending', 'assembling', 'delivering'].includes(o.status),
            );
        case 'completed':
            return props.orders.filter(
                (o) => o.status === 'delivered' || o.status === 'received',
            );
        case 'cancelled':
            return props.orders.filter((o) => o.status === 'cancelled');
        case 'pendingReturn':
            return props.orders.filter((o) => o.has_pending_return === true);
    }
}

const filteredOrders = computed<Order[]>(() =>
    activeFilter.value ? filterOrders(activeFilter.value) : [],
);

const filterTitle = computed<string>(() => {
    if (!activeFilter.value) {
        return '';
    }

    return t(
        `pageDealer.routesToday.filter${activeFilter.value.charAt(0).toUpperCase()}${activeFilter.value.slice(1)}`,
    );
});

function openStatFilter(filter: StatFilter) {
    activeFilter.value = filter;
}

function closeStatFilter(open: boolean) {
    if (!open) {
        activeFilter.value = null;
    }
}

const assemblingOrders = computed(() =>
    props.orders.filter((o) => o.status === 'assembling'),
);

const deliveryRouteOrderIds = computed(() =>
    props.orders
        .filter((o) =>
            ['pending', 'assembling', 'delivering'].includes(o.status),
        )
        .filter(
            (o) =>
                o.shop !== null &&
                o.shop.latitude !== null &&
                o.shop.longitude !== null,
        )
        .map((o) => o.id),
);
const loadingRouteOpen = ref(false);

function openLoadingRoute() {
    if (deliveryRouteOrderIds.value.length === 0) {
        return;
    }

    loadingRouteOpen.value = true;
}

const showStartRoute = ref(false);
const selectedIds = ref<Set<number>>(new Set());
const startRouteProcessing = ref(false);

function openStartRoute() {
    selectedIds.value = new Set(assemblingOrders.value.map((o) => o.id));
    showStartRoute.value = true;
}

function toggleOrderSelection(id: number, checked: boolean | 'indeterminate') {
    const next = new Set(selectedIds.value);

    if (checked === true) {
        next.add(id);
    } else {
        next.delete(id);
    }

    selectedIds.value = next;
}

const allSelected = computed(
    () =>
        assemblingOrders.value.length > 0 &&
        selectedIds.value.size === assemblingOrders.value.length,
);

function toggleAll(checked: boolean | 'indeterminate') {
    selectedIds.value =
        checked === true
            ? new Set(assemblingOrders.value.map((o) => o.id))
            : new Set();
}

function submitStartRoute() {
    if (selectedIds.value.size === 0) {
        return;
    }

    startRouteProcessing.value = true;

    router.post(
        '/dealer/routes/today/dispatch',
        { order_ids: Array.from(selectedIds.value) },
        {
            preserveScroll: true,
            onSuccess: () => {
                showStartRoute.value = false;
            },
            onFinish: () => {
                startRouteProcessing.value = false;
            },
        },
    );
}

function setOrderCardRef(orderId: number, el: unknown) {
    if (el instanceof HTMLElement) {
        orderCards.value[orderId] = el;

        return;
    }

    // Vue komponent instance — ichidagi DOM elementni olamiz
    const inst = el as { $el?: HTMLElement } | null;
    orderCards.value[orderId] =
        inst?.$el instanceof HTMLElement ? inst.$el : null;
}

function focusOrder(orderId: number) {
    showOrders.value = true;

    nextTick(() => {
        const el = orderCards.value[orderId];

        if (!el) {
            return;
        }

        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('ring-2', 'ring-primary', 'rounded-xl');
        setTimeout(
            () => el.classList.remove('ring-2', 'ring-primary', 'rounded-xl'),
            2000,
        );
    });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.routesToday.headTitle')" />

    <div class="flex flex-col gap-4 p-3 sm:gap-5 sm:p-4 lg:gap-6 lg:p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                >
                    <Map class="h-5 w-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1
                        class="text-lg font-bold tracking-tight sm:text-xl lg:text-2xl"
                    >
                        {{ t('pageDealer.routesToday.title') }}
                    </h1>
                    <p class="text-xs text-muted-foreground sm:text-sm">
                        {{ date }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <Button
                    v-if="deliveryRouteOrderIds.length > 0"
                    type="button"
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="openLoadingRoute"
                >
                    <Truck class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.routesToday.loadingRoute') }}
                    <span
                        class="ml-1 rounded bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold text-primary tabular-nums"
                    >
                        {{ deliveryRouteOrderIds.length }}
                    </span>
                </Button>
                <Button
                    v-if="isDeliveryman && assemblingOrders.length > 0"
                    type="button"
                    class="w-full sm:w-auto"
                    @click="openStartRoute"
                >
                    <Truck class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.routesToday.startRoute') }}
                    <span
                        class="ml-1 rounded bg-primary-foreground/20 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums"
                    >
                        {{ assemblingOrders.length }}
                    </span>
                </Button>
                <input
                    type="date"
                    :value="date"
                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm sm:h-9 sm:w-auto"
                    @change="
                        (e) => changeDate((e.target as HTMLInputElement).value)
                    "
                />
                <div v-if="deliverymen.length > 0" class="w-full sm:w-64">
                    <SearchableSelect
                        :model-value="selectedDeliverymanId"
                        :items="deliverymen"
                        value-key="id"
                        label-key="name"
                        :placeholder="
                            t('pageDealer.routesToday.deliverymanPlaceholder')
                        "
                        :search-placeholder="
                            t('pageDealer.routesToday.deliverymanSearch')
                        "
                        :empty-text="t('pageDealer.routesToday.notFound')"
                        @update:model-value="
                            (v) =>
                                changeDeliveryman(v !== null ? String(v) : '')
                        "
                    />
                </div>
            </div>
        </div>

        <Dialog
            :open="showStartRoute"
            @update:open="(v) => (showStartRoute = v)"
        >
            <DialogContent
                class="flex max-h-[calc(100dvh-2rem)] flex-col gap-3 p-4 sm:max-w-lg sm:gap-4 sm:p-6"
            >
                <DialogHeader>
                    <DialogTitle class="text-base sm:text-lg">
                        {{ t('pageDealer.routesToday.startRouteTitle') }}
                    </DialogTitle>
                </DialogHeader>

                <div class="-mx-4 flex-1 overflow-y-auto px-4 sm:-mx-6 sm:px-6">
                    <p class="mb-3 text-xs text-muted-foreground">
                        {{ t('pageDealer.routesToday.startRouteDesc') }}
                    </p>

                    <div
                        class="mb-2 flex items-center gap-2 rounded-md border bg-muted/30 px-3 py-2"
                    >
                        <Checkbox
                            :model-value="allSelected"
                            @update:model-value="toggleAll"
                        />
                        <span class="text-sm font-medium">
                            {{ t('pageDealer.routesToday.selectAll') }}
                            <span class="text-muted-foreground"
                                >({{ selectedIds.size }}/{{
                                    assemblingOrders.length
                                }})</span
                            >
                        </span>
                    </div>

                    <ul class="flex flex-col divide-y rounded-md border">
                        <li
                            v-for="o in assemblingOrders"
                            :key="o.id"
                            class="flex items-start gap-3 px-3 py-2.5"
                        >
                            <Checkbox
                                :model-value="selectedIds.has(o.id)"
                                class="mt-0.5"
                                @update:model-value="
                                    (v) => toggleOrderSelection(o.id, v)
                                "
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] font-medium text-muted-foreground"
                                    >
                                        #{{ o.number }}
                                    </span>
                                    <span
                                        class="truncate text-sm font-medium"
                                        >{{ o.shop?.name ?? '—' }}</span
                                    >
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ o.items_count }}
                                    {{ t('pageDealer.routesToday.items') }} ·
                                    <span class="font-mono">{{
                                        formatWithSymbol(
                                            o.display_total ?? o.total,
                                        )
                                    }}</span>
                                    <span
                                        v-if="
                                            (o.display_total ?? o.total) !==
                                            o.total
                                        "
                                        class="ml-1 font-mono text-[10px] text-muted-foreground line-through"
                                    >
                                        {{ formatWithSymbol(o.total) }}
                                    </span>
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showStartRoute = false">
                        {{ t('pageDealer.routesToday.cancel') }}
                    </Button>
                    <Button
                        :disabled="
                            startRouteProcessing || selectedIds.size === 0
                        "
                        @click="submitStartRoute"
                    >
                        {{
                            startRouteProcessing
                                ? t('pageDealer.routesToday.saving')
                                : t('pageDealer.routesToday.confirmStartRoute')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Stats kartochkalar -->
        <div class="grid grid-cols-4 gap-1.5 sm:gap-3">
            <Card
                role="button"
                tabindex="0"
                class="cursor-pointer gap-0 py-0 transition-all hover:border-amber-500/40 hover:shadow-sm active:scale-[0.98] sm:py-2"
                @click="openStatFilter('remaining')"
                @keydown.enter.prevent="openStatFilter('remaining')"
                @keydown.space.prevent="openStatFilter('remaining')"
            >
                <CardContent
                    class="flex flex-col items-center gap-0 px-2 py-1.5 sm:flex-row sm:items-center sm:gap-3 sm:px-4 sm:py-2"
                >
                    <div
                        class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 sm:flex"
                    >
                        <Package class="h-4 w-4 text-amber-600" />
                    </div>
                    <div class="min-w-0 text-center sm:text-left">
                        <p
                            class="text-[10px] leading-none text-muted-foreground sm:text-xs"
                        >
                            {{ t('pageDealer.routesToday.statRemaining') }}
                        </p>
                        <p
                            class="mt-0.5 text-sm leading-none font-bold text-amber-600 tabular-nums sm:text-lg"
                        >
                            {{ stats.remaining }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card
                role="button"
                tabindex="0"
                class="cursor-pointer gap-0 py-0 transition-all hover:border-emerald-500/40 hover:shadow-sm active:scale-[0.98] sm:py-2"
                @click="openStatFilter('completed')"
                @keydown.enter.prevent="openStatFilter('completed')"
                @keydown.space.prevent="openStatFilter('completed')"
            >
                <CardContent
                    class="flex flex-col items-center gap-0 px-2 py-1.5 sm:flex-row sm:items-center sm:gap-3 sm:px-4 sm:py-2"
                >
                    <div
                        class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 sm:flex"
                    >
                        <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                    </div>
                    <div class="min-w-0 text-center sm:text-left">
                        <p
                            class="text-[10px] leading-none text-muted-foreground sm:text-xs"
                        >
                            {{ t('pageDealer.routesToday.statCompleted') }}
                        </p>
                        <p
                            class="mt-0.5 text-sm leading-none font-bold text-emerald-600 tabular-nums sm:text-lg"
                        >
                            {{ stats.completed }}
                        </p>
                        <p
                            class="mt-0.5 truncate text-[9px] leading-none text-muted-foreground tabular-nums sm:text-[11px]"
                        >
                            {{ formatWithSymbol(stats.completed_amount) }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card
                role="button"
                tabindex="0"
                class="cursor-pointer gap-0 py-0 transition-all hover:border-rose-500/40 hover:shadow-sm active:scale-[0.98] sm:py-2"
                @click="openStatFilter('cancelled')"
                @keydown.enter.prevent="openStatFilter('cancelled')"
                @keydown.space.prevent="openStatFilter('cancelled')"
            >
                <CardContent
                    class="flex flex-col items-center gap-0 px-2 py-1.5 sm:flex-row sm:items-center sm:gap-3 sm:px-4 sm:py-2"
                >
                    <div
                        class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 sm:flex"
                    >
                        <XCircle class="h-4 w-4 text-rose-600" />
                    </div>
                    <div class="min-w-0 text-center sm:text-left">
                        <p
                            class="text-[10px] leading-none text-muted-foreground sm:text-xs"
                        >
                            {{ t('pageDealer.routesToday.statCancelled') }}
                        </p>
                        <p
                            class="mt-0.5 text-sm leading-none font-bold text-rose-600 tabular-nums sm:text-lg"
                        >
                            {{ stats.cancelled }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card
                role="button"
                tabindex="0"
                class="cursor-pointer gap-0 py-0 transition-all hover:border-orange-500/40 hover:shadow-sm active:scale-[0.98] sm:py-2"
                :class="{ 'opacity-60': stats.pending_return === 0 }"
                @click="
                    stats.pending_return > 0 && openStatFilter('pendingReturn')
                "
                @keydown.enter.prevent="
                    stats.pending_return > 0 && openStatFilter('pendingReturn')
                "
                @keydown.space.prevent="
                    stats.pending_return > 0 && openStatFilter('pendingReturn')
                "
            >
                <CardContent
                    class="flex flex-col items-center gap-0 px-2 py-1.5 sm:flex-row sm:items-center sm:gap-3 sm:px-4 sm:py-2"
                >
                    <div
                        class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-orange-500/10 sm:flex"
                    >
                        <Undo2 class="h-4 w-4 text-orange-600" />
                    </div>
                    <div class="min-w-0 text-center sm:text-left">
                        <p
                            class="text-[10px] leading-none text-muted-foreground sm:text-xs"
                        >
                            {{ t('pageDealer.routesToday.statPendingReturn') }}
                        </p>
                        <p
                            class="mt-0.5 text-sm leading-none font-bold text-orange-600 tabular-nums sm:text-lg"
                        >
                            {{ stats.pending_return }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Stat filter modal -->
        <Dialog :open="activeFilter !== null" @update:open="closeStatFilter">
            <DialogContent
                class="flex max-h-[calc(100dvh-2rem)] flex-col gap-3 p-4 sm:max-w-2xl sm:gap-4 sm:p-6"
            >
                <DialogHeader>
                    <DialogTitle class="text-base sm:text-lg">
                        {{ filterTitle }}
                        <span class="ml-1 font-normal text-muted-foreground"
                            >({{ filteredOrders.length }})</span
                        >
                    </DialogTitle>
                </DialogHeader>

                <div class="-mx-4 flex-1 overflow-y-auto px-4 sm:-mx-6 sm:px-6">
                    <div
                        v-if="filteredOrders.length === 0"
                        class="rounded-xl border border-dashed p-8 text-center"
                    >
                        <Package
                            class="mx-auto h-10 w-10 text-muted-foreground/30"
                        />
                        <p class="mt-3 text-sm text-muted-foreground">
                            {{ t('pageDealer.routesToday.noOrdersInFilter') }}
                        </p>
                    </div>
                    <div v-else class="space-y-2.5">
                        <RouteOrderCard
                            v-for="o in filteredOrders"
                            :key="o.id"
                            :order="o"
                            @navigate="activeFilter = null"
                        />
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Xarita -->
        <div v-if="orders.length > 0" class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-muted-foreground">
                    {{ t('pageDealer.routesToday.map') }}
                    <span class="font-normal"
                        >({{ mapPoints.length }}
                        {{ t('pageDealer.routesToday.pointsSuffix') }})</span
                    >
                    <span
                        v-if="mapPoints.length < orders.length"
                        class="block text-xs text-amber-600 sm:ml-1 sm:inline"
                    >
                        <span class="hidden sm:inline">· </span
                        >{{ orders.length - mapPoints.length }}
                        {{ t('pageDealer.routesToday.noCoords') }}
                    </span>
                </h2>
                <Button
                    v-if="mapPoints.length > 0"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2"
                    @click="showMap = !showMap"
                >
                    <ChevronUp v-if="showMap" class="mr-1 h-3.5 w-3.5" />
                    <ChevronDown v-else class="mr-1 h-3.5 w-3.5" />
                    {{
                        showMap
                            ? t('pageDealer.routesToday.hide')
                            : t('pageDealer.routesToday.show')
                    }}
                </Button>
            </div>

            <OrdersMap
                v-if="mapPoints.length > 0 && showMap"
                :orders="mapPoints"
                :preordered="routeOptimized"
                :lock-to-delivering="isDeliveryman"
                height="h-[340px] sm:h-[420px] lg:h-[520px]"
                @select-order="focusOrder"
            />

            <!-- Hech bir mijozda koordinata yo'q -->
            <div
                v-else-if="mapPoints.length === 0"
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed bg-muted/20 p-6 text-center sm:p-8"
            >
                <MapPinOff class="h-8 w-8 text-muted-foreground/40" />
                <p class="text-sm font-medium">
                    {{ t('pageDealer.routesToday.noMapData') }}
                </p>
                <p class="max-w-md text-xs text-muted-foreground">
                    {{ t('pageDealer.routesToday.noMapDataDesc') }}
                </p>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="mt-1"
                    @click="router.get('/dealer/shops')"
                >
                    {{ t('pageDealer.routesToday.goToShops') }}
                </Button>
            </div>
        </div>

        <!-- Buyurtmalar -->
        <div
            v-if="orders.length === 0"
            class="rounded-xl border border-dashed p-8 text-center sm:p-12"
        >
            <Package class="mx-auto h-10 w-10 text-muted-foreground/30" />
            <p class="mt-3 text-sm text-muted-foreground">
                {{ t('pageDealer.routesToday.noRoute') }}
            </p>
        </div>

        <div v-else class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-muted-foreground">
                    {{ t('pageDealer.routesToday.orders') }}
                    <span class="font-normal"
                        >({{ orders.length }}
                        {{ t('pageDealer.routesToday.ordersCount') }})</span
                    >
                </h2>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2"
                    :aria-expanded="showOrders"
                    @click="showOrders = !showOrders"
                >
                    <ChevronUp v-if="showOrders" class="mr-1 h-3.5 w-3.5" />
                    <ChevronDown v-else class="mr-1 h-3.5 w-3.5" />
                    {{
                        showOrders
                            ? t('pageDealer.routesToday.hide')
                            : t('pageDealer.routesToday.show')
                    }}
                </Button>
            </div>

            <div v-if="showOrders" class="space-y-2.5">
                <div
                    v-for="o in orders"
                    :key="o.id"
                    :ref="(el) => setOrderCardRef(o.id, el)"
                >
                    <RouteOrderCard :order="o" />
                </div>
            </div>
        </div>

        <LoadingRouteModal
            v-model:open="loadingRouteOpen"
            :order-ids="deliveryRouteOrderIds"
        />
    </div>
</template>
