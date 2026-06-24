<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChevronDown, Filter } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PaginationBar } from '@/components/ui/pagination-bar';
import { SearchableSelect } from '@/components/ui/searchable-select';
import SortHeader from '@/components/ui/sort-header.vue';
import BotUserDetailModal from '@/components/dealer/bot-users/BotUserDetailModal.vue';
import { useTableFilters } from '@/composables/useTableFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import type { Paginated } from '@/types';

type ShopRef = {
    id: number;
    name: string;
    is_active: boolean;
};

type BotUserRow = {
    id: number;
    telegram_id: number;
    name: string | null;
    username: string | null;
    is_active: boolean;
    blocked_at: string | null;
    joined_at: string | null;
    last_seen_at: string | null;
    orders_count: number;
    last_order_at: string | null;
    shop: ShopRef | null;
};

type Kpi = {
    total: number;
    active: number;
    inactive: number;
    blocked: number;
    today: number;
    week: number;
    month: number;
    never_ordered: number;
};

type BotUserFilters = {
    search?: string | null;
    shop_id?: number | null;
    status?: string | null;
    activity?: string | null;
};

const props = defineProps<{
    members: Paginated<BotUserRow>;
    kpi: Kpi;
    shops: { id: number; name: string }[];
    filters: BotUserFilters;
    sort: { column: string; direction: 'asc' | 'desc' };
}>();

const { t } = useI18n();

const { filters, sortColumn, sortDirection, apply, applyDebounced, reset, toggleSort, goToPage, prefetchPage } =
    useTableFilters<BotUserFilters>({
        url: '/dealer/bot-users',
        initialFilters: props.filters,
        initialSort: props.sort,
        defaultSortColumn: 'last_seen_at',
    });

defineOptions({ layout: AppLayout });

function relativeTime(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    const diff = Date.now() - d.getTime();
    if (diff < 0) return formatDateTime(iso);
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) return t('pageDealer.botUsers.justNow');
    if (minutes < 60) return t('pageDealer.botUsers.minutesAgo', { n: minutes });
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return t('pageDealer.botUsers.hoursAgo', { n: hours });
    const days = Math.floor(hours / 24);
    if (days < 30) return t('pageDealer.botUsers.daysAgo', { n: days });
    return formatDateTime(iso);
}

const statusOptions = computed(() => [
    { value: 'active', label: t('pageDealer.botUsers.filters.statusActive') },
    { value: 'inactive', label: t('pageDealer.botUsers.filters.statusInactive') },
    { value: 'blocked', label: t('pageDealer.botUsers.filters.statusBlocked') },
]);

const activityOptions = computed(() => [
    { value: 'today', label: t('pageDealer.botUsers.filters.actToday') },
    { value: '7d', label: t('pageDealer.botUsers.filters.act7d') },
    { value: '30d', label: t('pageDealer.botUsers.filters.act30d') },
    { value: 'inactive', label: t('pageDealer.botUsers.filters.actInactive') },
    { value: 'never', label: t('pageDealer.botUsers.filters.actNever') },
]);

type KpiFilter = { status?: string; activity?: string };

const kpiCards = computed(() => [
    { key: 'total', label: t('pageDealer.botUsers.kpi.total'), value: props.kpi.total, filter: {} as KpiFilter, color: '' },
    { key: 'active', label: t('pageDealer.botUsers.kpi.active'), value: props.kpi.active, filter: { status: 'active' } as KpiFilter, color: 'text-green-600' },
    { key: 'blocked', label: t('pageDealer.botUsers.kpi.blocked'), value: props.kpi.blocked, filter: { status: 'blocked' } as KpiFilter, color: 'text-red-600' },
    { key: 'today', label: t('pageDealer.botUsers.kpi.today'), value: props.kpi.today, filter: { activity: 'today' } as KpiFilter, color: '' },
    { key: 'week', label: t('pageDealer.botUsers.kpi.week'), value: props.kpi.week, filter: { activity: '7d' } as KpiFilter, color: '' },
    { key: 'month', label: t('pageDealer.botUsers.kpi.month'), value: props.kpi.month, filter: { activity: '30d' } as KpiFilter, color: '' },
    { key: 'never', label: t('pageDealer.botUsers.kpi.neverOrdered'), value: props.kpi.never_ordered, filter: { activity: 'never' } as KpiFilter, color: 'text-amber-600' },
]);

function kpiActive(filter: KpiFilter): boolean {
    const st = filters.value.status ?? null;
    const ac = filters.value.activity ?? null;
    if (!filter.status && !filter.activity) return !st && !ac;
    if (filter.status) return st === filter.status && !ac;
    return ac === filter.activity && !st;
}

function selectKpi(filter: KpiFilter): void {
    if (kpiActive(filter)) {
        filters.value.status = null;
        filters.value.activity = null;
    } else {
        filters.value.status = filter.status ?? null;
        filters.value.activity = filter.activity ?? null;
    }
    apply();
}

const activeFilterCount = computed(() => {
    let n = 0;
    if (filters.value.search) n++;
    if (filters.value.shop_id) n++;
    if (filters.value.status) n++;
    if (filters.value.activity) n++;
    return n;
});

const filtersOpen = ref(false);

const detailOpen = ref(false);
const selectedMemberId = ref<number | null>(null);

function openMember(id: number): void {
    selectedMemberId.value = id;
    detailOpen.value = true;
}

onMounted(() => {
    filtersOpen.value = activeFilterCount.value > 0 || window.matchMedia('(min-width: 768px)').matches;
});
</script>

<template>
    <Head :title="t('pageDealer.botUsers.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
            <h1 class="text-xl font-bold sm:text-2xl">{{ t('pageDealer.botUsers.title') }}</h1>
            <p class="text-xs text-muted-foreground sm:text-sm">{{ t('pageDealer.botUsers.subtitle') }}</p>
        </div>

        <!-- KPI (clickable filters) -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-7">
            <button
                v-for="c in kpiCards"
                :key="c.key"
                type="button"
                class="rounded-lg border bg-card p-3 text-left transition-colors hover:bg-muted/30 sm:p-4"
                :class="kpiActive(c.filter) ? 'border-primary ring-2 ring-primary/50' : ''"
                @click="selectKpi(c.filter)"
            >
                <p class="truncate text-xs text-muted-foreground">{{ c.label }}</p>
                <p class="text-lg font-bold sm:text-2xl" :class="c.color">{{ c.value }}</p>
            </button>
        </div>

        <!-- Filters (collapsible) -->
        <Card class="gap-0 py-0">
            <button
                type="button"
                class="flex h-9 w-full items-center justify-between gap-2 px-4 text-left text-sm transition-colors hover:bg-muted/40"
                :class="{ 'border-b': filtersOpen }"
                @click="filtersOpen = !filtersOpen"
            >
                <div class="flex items-center gap-2">
                    <Filter class="h-4 w-4 text-muted-foreground" />
                    <span class="font-medium">{{ t('pageDealer.botUsers.filters.advanced') }}</span>
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
                class="grid grid-cols-1 gap-3 px-4 py-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-[1fr_12rem_10rem_10rem_auto] lg:items-end"
            >
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('pageDealer.botUsers.filters.search') }}</label>
                    <Input
                        type="text"
                        v-model="filters.search as any"
                        :placeholder="t('pageDealer.botUsers.filters.searchPlaceholder')"
                        @input="applyDebounced()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('pageDealer.botUsers.filters.shop') }}</label>
                    <SearchableSelect
                        v-model="filters.shop_id as any"
                        :items="shops"
                        value-key="id"
                        label-key="name"
                        :placeholder="t('pageDealer.botUsers.filters.allShops')"
                        :search-placeholder="t('pageDealer.botUsers.filters.searchShop')"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('pageDealer.botUsers.filters.status') }}</label>
                    <SearchableSelect
                        v-model="filters.status as any"
                        :items="statusOptions"
                        :searchable="false"
                        :placeholder="t('pageDealer.botUsers.filters.allStatuses')"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('pageDealer.botUsers.filters.activity') }}</label>
                    <SearchableSelect
                        v-model="filters.activity as any"
                        :items="activityOptions"
                        :searchable="false"
                        :placeholder="t('pageDealer.botUsers.filters.allActivity')"
                        @change="apply()"
                    />
                </div>
                <Button variant="outline" size="sm" class="w-full sm:col-span-2 sm:w-auto lg:col-span-1" @click="reset">
                    {{ t('pageDealer.botUsers.filters.clear') }}
                </Button>
            </CardContent>
        </Card>

        <!-- Table -->
        <Card>
            <CardHeader>
                <CardTitle>{{ t('pageDealer.botUsers.listTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <!-- Desktop -->
                <div class="hidden md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    <SortHeader column="name" :active-column="sortColumn" :direction="sortDirection" @toggle="(c) => toggleSort(c)">
                                        {{ t('pageDealer.botUsers.table.user') }}
                                    </SortHeader>
                                </th>
                                <th class="px-4 py-3 font-medium">{{ t('pageDealer.botUsers.table.shop') }}</th>
                                <th class="px-4 py-3 font-medium">
                                    <SortHeader column="joined_at" :active-column="sortColumn" :direction="sortDirection" @toggle="(c) => toggleSort(c)">
                                        {{ t('pageDealer.botUsers.table.joinedAt') }}
                                    </SortHeader>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <SortHeader column="last_seen_at" :active-column="sortColumn" :direction="sortDirection" @toggle="(c) => toggleSort(c)">
                                        {{ t('pageDealer.botUsers.table.lastSeen') }}
                                    </SortHeader>
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    <SortHeader column="orders_count" :active-column="sortColumn" :direction="sortDirection" @toggle="(c) => toggleSort(c)">
                                        {{ t('pageDealer.botUsers.table.orders') }}
                                    </SortHeader>
                                </th>
                                <th class="px-4 py-3 font-medium">{{ t('pageDealer.botUsers.table.lastOrder') }}</th>
                                <th class="w-24 px-4 py-3 text-center font-medium">{{ t('pageDealer.botUsers.table.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="m in members.data"
                                :key="m.id"
                                class="cursor-pointer transition-colors hover:bg-muted/20"
                                @click="openMember(m.id)"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ m.name || t('pageDealer.botUsers.unnamed') }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            <span v-if="m.username">@{{ m.username }}</span>
                                            <span v-else>ID: {{ m.telegram_id }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ m.shop?.name ?? '—' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ formatDateTime(m.joined_at) }}</td>
                                <td class="px-4 py-3">{{ relativeTime(m.last_seen_at) }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ m.orders_count }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ formatDateTime(m.last_order_at) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <Badge
                                        v-if="m.blocked_at"
                                        variant="destructive"
                                        :title="t('pageDealer.botUsers.blockedSince', { date: formatDateTime(m.blocked_at) })"
                                    >
                                        {{ t('pageDealer.botUsers.statusBlocked') }}
                                    </Badge>
                                    <Badge v-else :variant="m.is_active ? 'secondary' : 'destructive'">
                                        {{ m.is_active ? t('pageDealer.botUsers.statusActive') : t('pageDealer.botUsers.statusInactive') }}
                                    </Badge>
                                </td>
                            </tr>
                            <tr v-if="members.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                                    {{ t('pageDealer.botUsers.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile -->
                <div class="flex flex-col divide-y md:hidden">
                    <div
                        v-for="m in members.data"
                        :key="`m-${m.id}`"
                        class="flex cursor-pointer flex-col gap-2 p-4 transition-colors hover:bg-muted/20"
                        @click="openMember(m.id)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">{{ m.name || t('pageDealer.botUsers.unnamed') }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    <span v-if="m.username">@{{ m.username }}</span>
                                    <span v-else>ID: {{ m.telegram_id }}</span>
                                </p>
                                <p v-if="m.shop" class="truncate text-xs text-muted-foreground">{{ m.shop.name }}</p>
                            </div>
                            <Badge v-if="m.blocked_at" variant="destructive" class="shrink-0">
                                {{ t('pageDealer.botUsers.statusBlocked') }}
                            </Badge>
                            <Badge v-else :variant="m.is_active ? 'secondary' : 'destructive'" class="shrink-0">
                                {{ m.is_active ? t('pageDealer.botUsers.statusActive') : t('pageDealer.botUsers.statusInactive') }}
                            </Badge>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">{{ t('pageDealer.botUsers.table.lastSeen') }}</span>
                            <span>{{ relativeTime(m.last_seen_at) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">{{ t('pageDealer.botUsers.table.orders') }}</span>
                            <span class="font-mono">{{ m.orders_count }}</span>
                        </div>
                        <div v-if="m.last_order_at" class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">{{ t('pageDealer.botUsers.table.lastOrder') }}</span>
                            <span>{{ formatDateTime(m.last_order_at) }}</span>
                        </div>
                    </div>
                    <div v-if="members.data.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                        {{ t('pageDealer.botUsers.empty') }}
                    </div>
                </div>

                <div class="border-t p-4">
                    <PaginationBar :meta="members.meta" @change="(p) => goToPage(p)" @prefetch="(p) => prefetchPage(p)" />
                </div>
            </CardContent>
        </Card>

        <BotUserDetailModal v-model:open="detailOpen" :member-id="selectedMemberId" />
    </div>
</template>
