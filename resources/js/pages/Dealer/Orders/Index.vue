<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronDown,
    DoorOpen,
    Filter,
    Hand,
    MapPin,
    Plus,
    Search,
    Truck,
    Undo2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CancelOrderDialog from '@/components/dealer/orders/CancelOrderDialog.vue';
import LoadingRouteModal from '@/components/dealer/loading-route/LoadingRouteModal.vue';

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PaginationBar } from '@/components/ui/pagination-bar';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { confirm } from '@/composables/useConfirm';
import { useTableFilters } from '@/composables/useTableFilters';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import type { Order, Shop, StatusOption, Paginated, Filters } from '@/types';

const props = defineProps<{
    orders: Paginated<Order>;
    shops?: { data: Shop[] };
    statuses: StatusOption[];
    filters: Filters;
    sort: { column: string; direction: 'asc' | 'desc' };
}>();

function normalizeStatus(v: unknown): string[] {
    if (Array.isArray(v))
        return v
            .filter((x) => x !== null && x !== undefined && x !== '')
            .map(String);
    if (v === null || v === undefined || v === '') return [];

    return [String(v)];
}

const {
    filters,
    sortColumn,
    sortDirection,
    apply,
    applyDebounced,
    reset,
    toggleSort,
    goToPage,
    prefetchPage,
    exportCsv,
} = useTableFilters<Filters>({
    url: '/dealer/orders',
    exportUrl: '/dealer/orders/export',
    initialFilters: {
        shop_id: null,
        ...props.filters,
        status: normalizeStatus(props.filters.status),
    },
    initialSort: props.sort,
});

const filtersOpen = ref(false);

const page = usePage();
const role = computed(() => page.props.auth?.role);
const currentUserId = computed(
    () => (page.props.auth?.user as { id?: number } | undefined)?.id ?? null,
);
const canCreateOrder = computed(
    () => role.value === 'dealer' || role.value === 'warehouse',
);

function isMine(order: Order): boolean {
    return (
        role.value === 'deliveryman' &&
        currentUserId.value !== null &&
        order.deliveryman?.id === currentUserId.value
    );
}

onMounted(() => {
    filtersOpen.value = window.matchMedia('(min-width: 768px)').matches;
});

const activeFilterCount = computed(() => {
    const statusActive = Array.isArray(filters.value.status)
        ? filters.value.status.length > 0
        : filters.value.status !== null &&
          filters.value.status !== undefined &&
          filters.value.status !== '';

    return (
        [
            filters.value.search,
            filters.value.shop_id,
            filters.value.date_from,
            filters.value.date_to,
        ].filter((v) => v !== null && v !== undefined && v !== '').length +
        (statusActive ? 1 : 0)
    );
});

const statusStyles: Record<
    string,
    { badge: string; row: string; dot: string }
> = {
    pending: {
        badge: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30',
        row: 'bg-amber-500/[0.04] hover:bg-amber-500/10',
        dot: 'bg-amber-500',
    },
    assembling: {
        badge: 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30',
        row: 'bg-sky-500/[0.04] hover:bg-sky-500/10',
        dot: 'bg-sky-500',
    },
    delivering: {
        badge: 'bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-500/30',
        row: 'bg-blue-500/[0.04] hover:bg-blue-500/10',
        dot: 'bg-blue-500',
    },
    delivered: {
        badge: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30',
        row: 'bg-emerald-500/[0.04] hover:bg-emerald-500/10',
        dot: 'bg-emerald-500',
    },
    received: {
        badge: 'bg-teal-500/15 text-teal-700 dark:text-teal-300 border border-teal-500/30',
        row: 'bg-teal-500/[0.04] hover:bg-teal-500/10',
        dot: 'bg-teal-500',
    },
    cancelled: {
        badge: 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30',
        row: 'bg-rose-500/[0.04] hover:bg-rose-500/10',
        dot: 'bg-rose-500',
    },
};

function displayTotal(order: Order): number {
    if (order.status === 'delivered' || order.status === 'received') {
        return Math.max(
            0,
            (order.delivered_total ?? 0) - (order.discount ?? 0),
        );
    }

    if (order.status === 'assembling' || order.status === 'delivering') {
        // Sklad tayyorlagan summa (picked_qty asosida). Backend display_total = prepared_total.
        return order.display_total ?? order.prepared_total ?? order.total;
    }

    return order.total;
}

function isFinalized(order: Order): boolean {
    return order.status === 'delivered' || order.status === 'received';
}

function hasTotalBreakdown(order: Order): boolean {
    if (isFinalized(order)) {
        const delivered = order.delivered_total ?? 0;
        return delivered !== order.total || (order.discount ?? 0) > 0;
    }

    // ASSEMBLING/DELIVERING'da prepared total ordered total'dan farq qilsa,
    // ordered'ni chizib o'tib ko'rsatamiz.
    if (order.status === 'assembling' || order.status === 'delivering') {
        return displayTotal(order) !== order.total;
    }

    return false;
}

function orderedDiffers(order: Order): boolean {
    return displayTotal(order) !== order.total;
}

function shopLocation(order: Order): string {
    const parts = [
        order.shop?.region,
        order.shop?.district,
        order.shop?.address,
    ]
        .map((p) => (p ?? '').trim())
        .filter((p) => p.length > 0);
    return parts.join(' · ');
}

const DELIVERY_ELIGIBLE_STATUSES = ['pending', 'assembling', 'delivering'];

const eligibleOrderIds = computed(() =>
    props.orders.data
        .filter((o) => DELIVERY_ELIGIBLE_STATUSES.includes(o.status))
        .map((o) => o.id),
);

const canOptimizeRoute = computed(
    () =>
        (role.value === 'dealer' ||
            role.value === 'warehouse' ||
            role.value === 'deliveryman') &&
        eligibleOrderIds.value.length > 0,
);

const loadingRouteOpen = ref(false);

function openLoadingRoute() {
    if (eligibleOrderIds.value.length === 0) {
        return;
    }

    loadingRouteOpen.value = true;
}

const cancelTarget = ref<Order | null>(null);
const cancelOpen = ref(false);

function openCancel(order: Order) {
    cancelTarget.value = order;
    cancelOpen.value = true;
}

async function releaseSelf(order: Order) {
    const ok = await confirm({
        title: t('pageDealer.ordersIndex.releaseTitle'),
        description: t('pageDealer.ordersIndex.releaseDesc', {
            id: order.number,
            shop: order.shop?.name ?? '—',
        }),
        confirmText: t('pageDealer.ordersIndex.releaseConfirm'),
        variant: 'destructive',
    });

    if (!ok) {
        return;
    }

    router.post(
        `/dealer/orders/${order.id}/release-self`,
        {},
        { preserveScroll: true },
    );
}

async function selfAssign(order: Order) {
    const ok = await confirm({
        title: t('pageDealer.ordersIndex.selfAssignTitle'),
        description: t('pageDealer.ordersIndex.selfAssignDesc', {
            id: order.number,
            shop: order.shop?.name ?? '—',
        }),
        confirmText: t('pageDealer.ordersIndex.selfAssignConfirm'),
    });

    if (!ok) {
        return;
    }

    router.post(
        `/dealer/orders/${order.id}/self-assign`,
        {},
        { preserveScroll: true },
    );
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.ordersIndex.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <h1 class="text-xl font-bold sm:text-2xl">
                {{ t('pageDealer.ordersIndex.title') }}
            </h1>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <Button
                    v-if="canOptimizeRoute"
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="openLoadingRoute"
                >
                    <Truck class="mr-1 h-4 w-4" />
                    {{ t('pageDealer.ordersIndex.loadingRoute') }}
                    <span
                        class="ml-1 rounded bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold text-primary tabular-nums"
                    >
                        {{ eligibleOrderIds.length }}
                    </span>
                </Button>
                <Button
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="exportCsv"
                    >{{ t('pageDealer.ordersIndex.exportExcel') }}</Button
                >
                <Button
                    v-if="canCreateOrder"
                    class="w-full sm:w-auto"
                    @click="router.get('/dealer/orders/create')"
                >
                    <Plus class="mr-1 h-4 w-4" />
                    {{ t('pageDealer.ordersIndex.newOrder') }}
                </Button>
            </div>
        </div>

        <!-- Buyurtma raqami bo'yicha qidirish -->
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="filters.search"
                type="search"
                inputmode="numeric"
                :placeholder="t('pageDealer.ordersIndex.searchPlaceholder')"
                class="pl-10"
                @input="applyDebounced()"
            />
        </div>

        <!-- Filtrlar -->
        <Card class="gap-0 py-0">
            <button
                type="button"
                class="flex h-9 w-full items-center justify-between gap-2 px-4 text-left text-sm transition-colors hover:bg-muted/40"
                :class="{ 'border-b': filtersOpen }"
                @click="filtersOpen = !filtersOpen"
            >
                <div class="flex items-center gap-2">
                    <Filter class="h-4 w-4 text-muted-foreground" />
                    <span class="font-medium">{{
                        t('pageDealer.ordersIndex.filters')
                    }}</span>
                    <span
                        v-if="activeFilterCount > 0"
                        class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary/10 px-1.5 text-xs font-medium text-primary"
                    >
                        {{ activeFilterCount }}
                    </span>
                </div>
                <ChevronDown
                    class="h-4 w-4 text-muted-foreground transition-transform"
                    :class="{ 'rotate-180': filtersOpen }"
                />
            </button>
            <CardContent
                v-show="filtersOpen"
                class="grid grid-cols-1 gap-3 px-4 py-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-[12rem_14rem_1fr_1fr_auto] lg:items-end"
            >
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.ordersIndex.status')
                    }}</label>
                    <SearchableSelect
                        v-model="filters.status"
                        :items="statuses"
                        value-key="value"
                        label-key="label"
                        multiple
                        :placeholder="t('pageDealer.ordersIndex.all')"
                        :empty-text="t('pageDealer.ordersIndex.statusEmpty')"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.ordersIndex.shop')
                    }}</label>
                    <SearchableSelect
                        v-model="filters.shop_id"
                        :items="shops?.data ?? []"
                        value-key="id"
                        label-key="name"
                        :placeholder="
                            shops
                                ? t('pageDealer.ordersIndex.all')
                                : t('pageDealer.ordersIndex.loading')
                        "
                        :search-placeholder="
                            t('pageDealer.ordersIndex.shopSearch')
                        "
                        :empty-text="t('pageDealer.ordersIndex.shopEmpty')"
                        :disabled="!shops"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.ordersIndex.from')
                    }}</label>
                    <Input
                        type="date"
                        v-model="filters.date_from"
                        @change="applyDebounced()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.ordersIndex.to')
                    }}</label>
                    <Input
                        type="date"
                        v-model="filters.date_to"
                        @change="applyDebounced()"
                    />
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full sm:col-span-2 sm:w-auto lg:col-span-1"
                    @click="reset"
                    >{{ t('pageDealer.ordersIndex.clear') }}</Button
                >
            </CardContent>
        </Card>

        <!-- Jadval (md+) -->
        <Card class="hidden md:block">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 hover:text-foreground"
                                        @click="toggleSort('id')"
                                    >
                                        #
                                        <component
                                            :is="
                                                sortColumn === 'id'
                                                    ? sortDirection === 'asc'
                                                        ? ArrowUp
                                                        : ArrowDown
                                                    : ArrowUpDown
                                            "
                                            class="h-3 w-3"
                                            :class="
                                                sortColumn === 'id'
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground/50'
                                            "
                                        />
                                    </button>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{ t('pageDealer.ordersIndex.tableShop') }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{
                                        t(
                                            'pageDealer.ordersIndex.tableDeliveryman',
                                        )
                                    }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 hover:text-foreground"
                                        @click="toggleSort('status')"
                                    >
                                        {{
                                            t(
                                                'pageDealer.ordersIndex.tableStatus',
                                            )
                                        }}
                                        <component
                                            :is="
                                                sortColumn === 'status'
                                                    ? sortDirection === 'asc'
                                                        ? ArrowUp
                                                        : ArrowDown
                                                    : ArrowUpDown
                                            "
                                            class="h-3 w-3"
                                            :class="
                                                sortColumn === 'status'
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground/50'
                                            "
                                        />
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 hover:text-foreground"
                                        @click="toggleSort('total')"
                                    >
                                        {{
                                            t(
                                                'pageDealer.ordersIndex.tableTotal',
                                            )
                                        }}
                                        <component
                                            :is="
                                                sortColumn === 'total'
                                                    ? sortDirection === 'asc'
                                                        ? ArrowUp
                                                        : ArrowDown
                                                    : ArrowUpDown
                                            "
                                            class="h-3 w-3"
                                            :class="
                                                sortColumn === 'total'
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground/50'
                                            "
                                        />
                                    </button>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 hover:text-foreground"
                                        @click="toggleSort('created_at')"
                                    >
                                        {{
                                            t(
                                                'pageDealer.ordersIndex.tableDate',
                                            )
                                        }}
                                        <component
                                            :is="
                                                sortColumn === 'created_at'
                                                    ? sortDirection === 'asc'
                                                        ? ArrowUp
                                                        : ArrowDown
                                                    : ArrowUpDown
                                            "
                                            class="h-3 w-3"
                                            :class="
                                                sortColumn === 'created_at'
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground/50'
                                            "
                                        />
                                    </button>
                                </th>
                                <th class="w-28 px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="order in orders.data"
                                :key="order.id"
                                class="cursor-pointer transition-colors"
                                :class="[
                                    statusStyles[order.status]?.row ??
                                        'hover:bg-muted/20',
                                    isMine(order)
                                        ? 'border-l-4 border-l-primary bg-primary/[0.06] hover:bg-primary/10'
                                        : '',
                                ]"
                                @click="
                                    router.get(`/dealer/orders/${order.id}`)
                                "
                            >
                                <td class="px-4 py-3 font-mono">
                                    {{ order.number }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">
                                        {{
                                            order.customer_name ??
                                            order.shop?.name ??
                                            '—'
                                        }}
                                    </div>
                                    <div
                                        v-if="shopLocation(order)"
                                        class="mt-0.5 flex items-start gap-1 text-xs text-muted-foreground"
                                    >
                                        <MapPin
                                            class="mt-0.5 h-3 w-3 shrink-0"
                                        />
                                        <span>{{ shopLocation(order) }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <span
                                            v-if="order.deliveryman"
                                            class="text-sm"
                                            >{{ order.deliveryman.name }}</span
                                        >
                                        <span
                                            v-else
                                            class="text-xs text-muted-foreground italic"
                                            >{{
                                                t(
                                                    'pageDealer.ordersIndex.notAssigned',
                                                )
                                            }}</span
                                        >
                                        <span
                                            v-if="isMine(order)"
                                            class="inline-flex items-center rounded-full border border-primary/30 bg-primary/15 px-2 py-0.5 text-[11px] font-semibold text-primary"
                                        >
                                            {{
                                                t(
                                                    'pageDealer.ordersIndex.yours',
                                                )
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex flex-wrap items-center gap-1.5"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="
                                                statusStyles[order.status]
                                                    ?.badge ??
                                                'border bg-muted text-muted-foreground'
                                            "
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="
                                                    statusStyles[order.status]
                                                        ?.dot ??
                                                    'bg-muted-foreground'
                                                "
                                            />
                                            {{ order.status_label }}
                                        </span>
                                        <span
                                            v-if="order.has_pending_return"
                                            class="inline-flex items-center gap-1 rounded-full border border-rose-500/30 bg-rose-500/15 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:text-rose-300"
                                            :title="
                                                t(
                                                    'pageDealer.ordersIndex.pendingReturn',
                                                )
                                            "
                                        >
                                            <Undo2 class="h-3 w-3" />
                                            {{
                                                t(
                                                    'pageDealer.ordersIndex.pendingReturnShort',
                                                )
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-mono">
                                    <div class="flex flex-col items-end">
                                        <span class="font-semibold">{{
                                            formatWithSymbol(
                                                displayTotal(order),
                                            )
                                        }}</span>
                                        <div
                                            v-if="hasTotalBreakdown(order)"
                                            class="mt-0.5 flex flex-wrap justify-end gap-x-1.5 text-[11px] font-normal text-muted-foreground"
                                        >
                                            <span v-if="orderedDiffers(order)"
                                                >{{
                                                    t(
                                                        'pageDealer.ordersIndex.orderedShort',
                                                    )
                                                }}:
                                                {{
                                                    formatWithSymbol(
                                                        order.total,
                                                    )
                                                }}</span
                                            >
                                            <span
                                                v-if="(order.discount ?? 0) > 0"
                                                class="text-rose-600"
                                                >−{{
                                                    formatWithSymbol(
                                                        order.discount,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ formatDateTime(order.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <Button
                                            v-if="order.can_self_assign"
                                            variant="outline"
                                            size="sm"
                                            :title="
                                                t(
                                                    'pageDealer.ordersIndex.selfAssign',
                                                )
                                            "
                                            @click.stop="selfAssign(order)"
                                        >
                                            <Hand class="h-3.5 w-3.5" />
                                            <span class="hidden lg:inline">{{
                                                t(
                                                    'pageDealer.ordersIndex.selfAssignShort',
                                                )
                                            }}</span>
                                        </Button>
                                        <Button
                                            v-if="order.can_release_self"
                                            variant="outline"
                                            size="sm"
                                            class="border-rose-500/40 text-rose-700 hover:bg-rose-500/10 hover:text-rose-700 dark:text-rose-300 dark:hover:text-rose-300"
                                            :title="
                                                t(
                                                    'pageDealer.ordersIndex.release',
                                                )
                                            "
                                            @click.stop="releaseSelf(order)"
                                        >
                                            <DoorOpen class="h-3.5 w-3.5" />
                                            <span class="hidden lg:inline">{{
                                                t(
                                                    'pageDealer.ordersIndex.release',
                                                )
                                            }}</span>
                                        </Button>
                                        <Button
                                            v-if="order.can_cancel"
                                            variant="outline"
                                            size="sm"
                                            class="border-rose-500/40 text-rose-700 hover:bg-rose-500/10 hover:text-rose-700 dark:text-rose-300 dark:hover:text-rose-300"
                                            :title="
                                                t(
                                                    'pageDealer.ordersIndex.cancel',
                                                )
                                            "
                                            @click.stop="openCancel(order)"
                                        >
                                            <X class="h-3.5 w-3.5" />
                                            <span class="hidden lg:inline">{{
                                                t(
                                                    'pageDealer.ordersIndex.cancelShort',
                                                )
                                            }}</span>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="orders.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    {{ t('pageDealer.ordersIndex.noOrders') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Mobil card ro'yxat -->
        <div class="flex flex-col gap-2.5 md:hidden">
            <Card
                v-for="order in orders.data"
                :key="`m-${order.id}`"
                class="relative cursor-pointer overflow-hidden py-0 transition-all active:scale-[0.99]"
                :class="[
                    statusStyles[order.status]?.row ?? 'hover:bg-muted/20',
                    isMine(order) ? 'ring-2 ring-primary/40' : '',
                ]"
                @click="router.get(`/dealer/orders/${order.id}`)"
            >
                <span
                    class="absolute inset-y-0 left-0 w-1"
                    :class="
                        statusStyles[order.status]?.dot ?? 'bg-muted-foreground'
                    "
                />
                <CardContent class="flex flex-col gap-2 py-3 pr-3 pl-4">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <span
                                class="shrink-0 rounded-md bg-muted px-1.5 py-0.5 font-mono text-[11px] font-medium text-muted-foreground"
                                >#{{ order.number }}</span
                            >
                            <span
                                class="truncate text-base leading-tight font-semibold"
                                >{{
                                    order.customer_name ??
                                    order.shop?.name ??
                                    '—'
                                }}</span
                            >
                        </div>
                        <div
                            class="flex shrink-0 flex-wrap items-center justify-end gap-1"
                        >
                            <span
                                v-if="order.has_pending_return"
                                class="inline-flex items-center gap-1 rounded-full border border-rose-500/30 bg-rose-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 dark:text-rose-300"
                                :title="
                                    t('pageDealer.ordersIndex.pendingReturn')
                                "
                            >
                                <Undo2 class="h-3 w-3" />
                            </span>
                            <span
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="
                                    statusStyles[order.status]?.badge ??
                                    'border bg-muted text-muted-foreground'
                                "
                            >
                                {{ order.status_label }}
                            </span>
                        </div>
                    </div>
                    <div
                        v-if="shopLocation(order)"
                        class="flex items-start gap-2 rounded-lg bg-muted/40 px-2.5 py-2"
                    >
                        <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                        <span
                            class="text-[13px] leading-snug font-medium text-foreground"
                            >{{ shopLocation(order) }}</span
                        >
                    </div>
                    <div class="flex items-end justify-between gap-2 pt-0.5">
                        <div class="flex min-w-0 flex-col gap-0.5">
                            <div class="flex items-center gap-1.5 text-xs">
                                <span
                                    v-if="order.deliveryman"
                                    class="truncate text-foreground"
                                    >{{ order.deliveryman.name }}</span
                                >
                                <span
                                    v-else
                                    class="text-muted-foreground italic"
                                    >{{
                                        t(
                                            'pageDealer.ordersIndex.notAssignedShort',
                                        )
                                    }}</span
                                >
                                <span
                                    v-if="isMine(order)"
                                    class="inline-flex shrink-0 items-center rounded-full border border-primary/30 bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold text-primary"
                                >
                                    {{ t('pageDealer.ordersIndex.yours') }}
                                </span>
                            </div>
                            <span class="text-[11px] text-muted-foreground">{{
                                formatDateTime(order.created_at)
                            }}</span>
                        </div>
                        <div
                            class="flex shrink-0 flex-col items-end gap-0.5 font-mono"
                        >
                            <span class="text-sm font-bold text-foreground">{{
                                formatWithSymbol(displayTotal(order))
                            }}</span>
                            <div
                                v-if="hasTotalBreakdown(order)"
                                class="flex flex-wrap justify-end gap-x-1.5 text-[10px] font-normal text-muted-foreground"
                            >
                                <span v-if="orderedDiffers(order)"
                                    >{{
                                        t(
                                            'pageDealer.ordersIndex.orderedShort',
                                        )
                                    }}:
                                    {{ formatWithSymbol(order.total) }}</span
                                >
                                <span
                                    v-if="(order.discount ?? 0) > 0"
                                    class="text-rose-600"
                                    >−{{
                                        formatWithSymbol(order.discount)
                                    }}</span
                                >
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="
                            order.can_self_assign ||
                            order.can_release_self ||
                            order.can_cancel
                        "
                        class="flex items-stretch gap-2 border-t pt-2"
                    >
                        <Button
                            v-if="order.can_self_assign"
                            variant="outline"
                            size="sm"
                            class="h-9 flex-1 text-xs"
                            @click.stop="selfAssign(order)"
                        >
                            <Hand class="mr-1 h-3.5 w-3.5" />
                            {{ t('pageDealer.ordersIndex.selfAssign') }}
                        </Button>
                        <Button
                            v-if="order.can_release_self"
                            variant="outline"
                            size="sm"
                            class="h-9 flex-1 border-rose-500/40 text-xs text-rose-700 hover:bg-rose-500/10 hover:text-rose-700 dark:text-rose-300 dark:hover:text-rose-300"
                            @click.stop="releaseSelf(order)"
                        >
                            <DoorOpen class="mr-1 h-3.5 w-3.5" />
                            {{ t('pageDealer.ordersIndex.release') }}
                        </Button>
                        <Button
                            v-if="order.can_cancel"
                            variant="outline"
                            size="sm"
                            class="h-9 flex-1 border-rose-500/40 text-xs text-rose-700 hover:bg-rose-500/10 hover:text-rose-700 dark:text-rose-300 dark:hover:text-rose-300"
                            @click.stop="openCancel(order)"
                        >
                            <X class="mr-1 h-3.5 w-3.5" />
                            {{ t('pageDealer.ordersIndex.cancel') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
            <Card v-if="orders.data.length === 0">
                <CardContent
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    {{ t('pageDealer.ordersIndex.noOrders') }}
                </CardContent>
            </Card>
        </div>

        <!-- Pagination -->
        <PaginationBar
            :meta="orders.meta"
            @change="goToPage"
            @prefetch="prefetchPage"
        />

        <CancelOrderDialog
            v-if="cancelTarget"
            v-model:open="cancelOpen"
            :order="cancelTarget"
        />

        <LoadingRouteModal
            v-model:open="loadingRouteOpen"
            :order-ids="eligibleOrderIds"
        />
    </div>
</template>
