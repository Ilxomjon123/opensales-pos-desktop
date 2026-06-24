<script setup lang="ts">
import { Deferred, Head, router } from '@inertiajs/vue3';
import { BarChart3, Download, RefreshCw, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';

type GroupBy = 'day' | 'week' | 'month' | 'shop' | 'deliveryman' | 'category' | 'product';

type Option = { value: string; label: string };

type Row = {
    id?: number | null;
    label: string;
    orders: number;
    qty?: number;
    gross: number;
    discount: number;
    net: number;
    aov: number;
};

type Summary = {
    orders: number;
    gross: number;
    discount: number;
    net: number;
    aov: number;
};

type Report = {
    summary: Summary;
    rows: Row[];
    meta: { group_by: GroupBy; date_from: string; date_to: string; statuses: string[] };
};

type Filters = {
    date_from: string;
    date_to: string;
    group_by: GroupBy;
    shop_id: number | null;
    deliveryman_id: number | null;
    category_id: number | null;
    statuses: string[];
};

type IdNameRef = { id: number; name: string };

const props = defineProps<{
    report: Report;
    filters: Filters;
    groupByOptions: Option[];
    statusOptions: Option[];
    shops?: IdNameRef[];
    deliverymen?: IdNameRef[];
    categories?: IdNameRef[];
}>();

const { t } = useI18n();

const localFilters = ref<Filters>({ ...props.filters });

watch(
    () => props.filters,
    (next) => {
        localFilters.value = { ...next };
    },
    { deep: true },
);

const periodGroup = computed(
    () => localFilters.value.group_by === 'day' || localFilters.value.group_by === 'week' || localFilters.value.group_by === 'month',
);

const qtyVisible = computed(
    () => localFilters.value.group_by === 'product' || localFilters.value.group_by === 'category',
);

const maxNet = computed(() => Math.max(...(props.report.rows ?? []).map((r) => r.net), 1));

function buildQuery(): Record<string, string | number | string[]> {
    const f = localFilters.value;
    const query: Record<string, string | number | string[]> = {
        date_from: f.date_from,
        date_to: f.date_to,
        group_by: f.group_by,
    };

    if (f.shop_id) {
        query.shop_id = f.shop_id;
    }

    if (f.deliveryman_id) {
        query.deliveryman_id = f.deliveryman_id;
    }

    if (f.category_id) {
        query.category_id = f.category_id;
    }

    if (f.statuses && f.statuses.length > 0) {
        query.statuses = f.statuses;
    }

    return query;
}

function apply() {
    router.get('/dealer/reports/sales', buildQuery(), {
        preserveState: true,
        preserveScroll: true,
    });
}

function reset() {
    const today = new Date();
    const monthAgo = new Date();
    monthAgo.setDate(today.getDate() - 29);

    const fmt = (d: Date) => d.toISOString().slice(0, 10);

    localFilters.value = {
        date_from: fmt(monthAgo),
        date_to: fmt(today),
        group_by: 'day',
        shop_id: null,
        deliveryman_id: null,
        category_id: null,
        statuses: ['delivered', 'received'],
    };

    apply();
}

function exportCsv() {
    const params = new URLSearchParams();
    const query = buildQuery();

    Object.entries(query).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            value.forEach((v) => params.append(`${key}[]`, String(v)));

            return;
        }

        params.set(key, String(value));
    });

    window.location.href = `/dealer/reports/sales/export?${params.toString()}`;
}

function presetRange(days: number) {
    const today = new Date();
    const start = new Date();
    start.setDate(today.getDate() - (days - 1));

    const fmt = (d: Date) => d.toISOString().slice(0, 10);

    localFilters.value.date_from = fmt(start);
    localFilters.value.date_to = fmt(today);
    apply();
}

function presetMonth() {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth(), 1);
    const fmt = (d: Date) => d.toISOString().slice(0, 10);

    localFilters.value.date_from = fmt(first);
    localFilters.value.date_to = fmt(now);
    apply();
}

function formatCompact(n: number): string {
    const abs = Math.abs(n);

    if (abs >= 1_000_000_000) {
        return (n / 1_000_000_000).toFixed(1).replace(/\.0$/, '') + 'mlrd';
    }

    if (abs >= 1_000_000) {
        return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'mln';
    }

    if (abs >= 1_000) {
        return (n / 1_000).toFixed(0) + 'k';
    }

    return String(n);
}

function formatQty(n: number): string {
    if (!Number.isFinite(n)) {
        return '0';
    }

    return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.salesReport.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-5 sm:p-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <BarChart3 class="h-4 w-4 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl">{{ t('pageDealer.salesReport.title') }}</h1>
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.salesReport.subtitle') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="reset">
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.salesReport.reset') }}
                </Button>
                <Button variant="default" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.salesReport.exportCsv') }}
                </Button>
            </div>
        </div>

        <!-- Filtrlar -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 sm:gap-4 lg:grid-cols-6 lg:items-end">
                <div class="lg:col-span-1">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.salesReport.dateFrom') }}</label>
                    <Input v-model="localFilters.date_from" type="date" @change="apply()" />
                </div>
                <div class="lg:col-span-1">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.salesReport.dateTo') }}</label>
                    <Input v-model="localFilters.date_to" type="date" @change="apply()" />
                </div>
                <div class="lg:col-span-1">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.salesReport.groupBy') }}</label>
                    <SearchableSelect
                        v-model="localFilters.group_by"
                        :items="groupByOptions"
                        value-key="value"
                        label-key="label"
                        :clearable="false"
                        :searchable="false"
                        @change="apply()"
                    />
                </div>
                <div class="lg:col-span-1">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.salesReport.shop') }}</label>
                    <SearchableSelect
                        v-model="localFilters.shop_id"
                        :items="shops ?? []"
                        value-key="id"
                        label-key="name"
                        :placeholder="shops ? t('pageDealer.common.all') : t('pageDealer.common.loading')"
                        :disabled="!shops"
                        @change="apply()"
                    />
                </div>
                <div class="lg:col-span-1">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.salesReport.deliveryman') }}</label>
                    <SearchableSelect
                        v-model="localFilters.deliveryman_id"
                        :items="deliverymen ?? []"
                        value-key="id"
                        label-key="name"
                        :placeholder="deliverymen ? t('pageDealer.common.all') : t('pageDealer.common.loading')"
                        :disabled="!deliverymen"
                        @change="apply()"
                    />
                </div>
                <div class="lg:col-span-1">
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.salesReport.category') }}</label>
                    <SearchableSelect
                        v-model="localFilters.category_id"
                        :items="categories ?? []"
                        value-key="id"
                        label-key="name"
                        :placeholder="categories ? t('pageDealer.common.all') : t('pageDealer.common.loading')"
                        :disabled="!categories"
                        @change="apply()"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2 lg:col-span-6">
                    <span class="text-xs text-muted-foreground">{{ t('pageDealer.salesReport.presets') }}:</span>
                    <Button variant="outline" size="sm" class="h-7 px-2 text-xs" @click="presetRange(7)">7 {{ t('pageDealer.salesReport.daysSuffix') }}</Button>
                    <Button variant="outline" size="sm" class="h-7 px-2 text-xs" @click="presetRange(30)">30 {{ t('pageDealer.salesReport.daysSuffix') }}</Button>
                    <Button variant="outline" size="sm" class="h-7 px-2 text-xs" @click="presetRange(90)">90 {{ t('pageDealer.salesReport.daysSuffix') }}</Button>
                    <Button variant="outline" size="sm" class="h-7 px-2 text-xs" @click="presetMonth">{{ t('pageDealer.salesReport.thisMonth') }}</Button>
                    <span class="mx-2 hidden h-4 w-px bg-border sm:inline-block" />
                    <span class="text-xs text-muted-foreground">{{ t('pageDealer.salesReport.statuses') }}:</span>
                    <Badge
                        v-for="s in statusOptions"
                        :key="s.value"
                        :variant="localFilters.statuses.includes(s.value) ? 'default' : 'outline'"
                        class="cursor-pointer text-xs"
                        @click="
                            localFilters.statuses = localFilters.statuses.includes(s.value)
                                ? localFilters.statuses.filter((v) => v !== s.value)
                                : [...localFilters.statuses, s.value];
                            apply();
                        "
                    >
                        {{ s.label }}
                    </Badge>
                </div>
            </CardContent>
        </Card>

        <!-- KPI Summary -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5">
            <Card>
                <CardContent class="p-3">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ t('pageDealer.salesReport.kpiOrders') }}</span>
                    </div>
                    <p class="mt-1 text-lg font-bold leading-tight sm:text-xl">{{ report.summary.orders }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ t('pageDealer.salesReport.kpiGross') }}</span>
                        <TrendingUp class="h-3.5 w-3.5" />
                    </div>
                    <p class="mt-1 text-lg font-bold leading-tight sm:text-xl">{{ formatCompact(report.summary.gross) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ t('pageDealer.salesReport.kpiDiscount') }}</span>
                    </div>
                    <p class="mt-1 text-lg font-bold leading-tight text-rose-600 sm:text-xl">
                        −{{ formatCompact(report.summary.discount) }}
                    </p>
                </CardContent>
            </Card>
            <Card class="border-emerald-200/60">
                <CardContent class="p-3">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ t('pageDealer.salesReport.kpiNet') }}</span>
                        <Wallet class="h-3.5 w-3.5 text-emerald-600" />
                    </div>
                    <p class="mt-1 text-lg font-bold leading-tight text-emerald-700 sm:text-xl">
                        {{ formatCompact(report.summary.net) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ t('pageDealer.salesReport.kpiAov') }}</span>
                    </div>
                    <p class="mt-1 text-lg font-bold leading-tight sm:text-xl">{{ formatCompact(report.summary.aov) }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Chart (period group_by) -->
        <Card v-if="periodGroup && report.rows.length > 0">
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center justify-between text-sm">
                    <span>{{ t('pageDealer.salesReport.chartTitle') }}</span>
                    <span class="text-xs font-normal text-muted-foreground">
                        {{ report.rows.length }} {{ t('pageDealer.salesReport.bucketsSuffix') }}
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent class="pt-0">
                <div class="flex h-32 gap-[2px]">
                    <div
                        v-for="row in report.rows"
                        :key="row.label"
                        class="group relative flex flex-1 flex-col justify-end"
                    >
                        <div
                            class="w-full rounded-t bg-primary transition-all hover:bg-primary/80"
                            :style="{ height: `${(row.net / maxNet) * 100}%`, minHeight: row.net > 0 ? '3px' : '0' }"
                        />
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1.5 hidden -translate-x-1/2 whitespace-nowrap rounded bg-popover px-2 py-1 text-[11px] shadow-md group-hover:block">
                            <div class="font-medium">{{ row.label }}</div>
                            <div>{{ row.orders }} {{ t('pageDealer.salesReport.ordersWord') }} — {{ formatMoney(row.net) }}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-1.5 flex justify-between text-[10px] text-muted-foreground">
                    <span>{{ report.rows[0]?.label }}</span>
                    <span>{{ report.rows[report.rows.length - 1]?.label }}</span>
                </div>
            </CardContent>
        </Card>

        <!-- Jadval -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm">{{ t('pageDealer.salesReport.tableTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="report.rows.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    {{ t('pageDealer.salesReport.empty') }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ t('pageDealer.salesReport.thLabel') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.salesReport.thOrders') }}</th>
                                <th v-if="qtyVisible" class="px-4 py-2 text-right font-medium">{{ t('pageDealer.salesReport.thQty') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.salesReport.thGross') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.salesReport.thDiscount') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.salesReport.thNet') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.salesReport.thAov') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in report.rows" :key="row.label + (row.id ?? '')" class="hover:bg-muted/20">
                                <td class="px-4 py-2 text-sm font-medium">{{ row.label }}</td>
                                <td class="px-4 py-2 text-right font-mono text-sm">{{ row.orders }}</td>
                                <td v-if="qtyVisible" class="px-4 py-2 text-right font-mono text-sm">{{ formatQty(row.qty ?? 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-sm">{{ formatMoney(row.gross) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs text-rose-600">
                                    <template v-if="row.discount > 0">−{{ formatMoney(row.discount) }}</template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                                    {{ formatMoney(row.net) }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-xs text-muted-foreground">{{ formatMoney(row.aov) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t bg-muted/30 text-xs">
                            <tr>
                                <td class="px-4 py-2 font-semibold">{{ t('pageDealer.salesReport.totalRow') }}</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold">{{ report.summary.orders }}</td>
                                <td v-if="qtyVisible" class="px-4 py-2 text-right font-mono font-semibold">—</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold">{{ formatMoney(report.summary.gross) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold text-rose-600">
                                    −{{ formatMoney(report.summary.discount) }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">
                                    {{ formatMoney(report.summary.net) }}
                                </td>
                                <td class="px-4 py-2 text-right font-mono font-semibold">{{ formatMoney(report.summary.aov) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Deferred prefetch markers for filter dropdowns -->
        <Deferred data="shops">
            <template #fallback>
                <div class="hidden" />
            </template>
            <div class="hidden" />
        </Deferred>
        <Deferred data="deliverymen">
            <template #fallback>
                <div class="hidden" />
            </template>
            <div class="hidden" />
        </Deferred>
        <Deferred data="categories">
            <template #fallback>
                <div class="hidden" />
            </template>
            <div class="hidden" />
        </Deferred>
    </div>
</template>
