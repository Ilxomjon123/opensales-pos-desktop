<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, Coins, Download, RefreshCw, TrendingUp } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';

type Row = {
    id: number;
    name: string;
    category: string | null;
    delivered_qty: number;
    avg_price: number;
    avg_cost: number;
    revenue: number;
    cogs: number;
    profit: number;
    margin: number;
    has_cost: boolean;
};

type Summary = {
    products: number;
    revenue: number;
    cogs: number;
    profit: number;
    margin: number;
    products_without_cost: number;
};

type Meta = {
    date_from: string;
    date_to: string;
    category_id: number | null;
    product_id: number | null;
};

type Report = {
    summary: Summary;
    rows: Row[];
    meta: Meta;
};

type Filters = {
    date_from: string;
    date_to: string;
    category_id: number | null;
    product_id: number | null;
};

type IdNameRef = { id: number; name: string };

const props = defineProps<{
    report: Report;
    filters: Filters;
    categories?: IdNameRef[];
    products?: IdNameRef[];
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

function buildQuery(): Record<string, string | number> {
    const f = localFilters.value;
    const q: Record<string, string | number> = {
        date_from: f.date_from,
        date_to: f.date_to,
    };

    if (f.category_id) {
        q.category_id = f.category_id;
    }

    if (f.product_id) {
        q.product_id = f.product_id;
    }

    return q;
}

function apply() {
    router.get('/dealer/reports/profit', buildQuery(), {
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
        category_id: null,
        product_id: null,
    };

    apply();
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

function exportCsv() {
    const params = new URLSearchParams();
    Object.entries(buildQuery()).forEach(([k, v]) => params.set(k, String(v)));
    window.location.href = `/dealer/reports/profit/export?${params.toString()}`;
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
    if (!Number.isFinite(n) || Math.abs(n) < 0.01) {
        return '0';
    }

    return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
}

function marginClass(m: number): string {
    if (m >= 30) {
        return 'text-emerald-700 dark:text-emerald-400';
    }

    if (m >= 10) {
        return 'text-emerald-600';
    }

    if (m >= 0) {
        return 'text-amber-600';
    }

    return 'text-rose-600';
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.profitReport.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-5 sm:p-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Coins class="h-4 w-4 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl">{{ t('pageDealer.profitReport.title') }}</h1>
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.profitReport.subtitle') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="reset">
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.profitReport.reset') }}
                </Button>
                <Button variant="default" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.profitReport.exportCsv') }}
                </Button>
            </div>
        </div>

        <!-- Filtrlar -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 sm:gap-4 lg:grid-cols-5 lg:items-end">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.profitReport.dateFrom') }}</label>
                    <Input v-model="localFilters.date_from" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.profitReport.dateTo') }}</label>
                    <Input v-model="localFilters.date_to" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.profitReport.category') }}</label>
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
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.profitReport.product') }}</label>
                    <SearchableSelect
                        v-model="localFilters.product_id"
                        :items="products ?? []"
                        value-key="id"
                        label-key="name"
                        :placeholder="products ? t('pageDealer.common.all') : t('pageDealer.common.loading')"
                        :disabled="!products"
                        @change="apply()"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" class="h-9 px-2 text-xs" @click="presetRange(7)">7 {{ t('pageDealer.profitReport.daysSuffix') }}</Button>
                    <Button variant="outline" size="sm" class="h-9 px-2 text-xs" @click="presetRange(30)">30 {{ t('pageDealer.profitReport.daysSuffix') }}</Button>
                    <Button variant="outline" size="sm" class="h-9 px-2 text-xs" @click="presetRange(90)">90 {{ t('pageDealer.profitReport.daysSuffix') }}</Button>
                </div>
            </CardContent>
        </Card>

        <!-- Cost warning -->
        <div
            v-if="report.summary.products_without_cost > 0"
            class="flex items-center gap-2 rounded-lg border border-amber-200/60 bg-amber-50/50 p-3 text-sm dark:border-amber-900/40 dark:bg-amber-950/20"
        >
            <AlertCircle class="h-4 w-4 shrink-0 text-amber-600" />
            <p class="text-xs text-amber-700 dark:text-amber-400">
                {{ t('pageDealer.profitReport.noCostWarning', { count: report.summary.products_without_cost }) }}
            </p>
        </div>

        <!-- KPI Summary -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5">
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.profitReport.kpiProducts') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ report.summary.products }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.profitReport.kpiRevenue') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatCompact(report.summary.revenue) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.profitReport.kpiCogs') }}</p>
                    <p class="mt-1 text-xl font-bold text-rose-600">−{{ formatCompact(report.summary.cogs) }}</p>
                </CardContent>
            </Card>
            <Card class="border-emerald-200/60">
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.profitReport.kpiProfit') }}</p>
                    <p
                        class="mt-1 text-xl font-bold"
                        :class="report.summary.profit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600'"
                    >
                        {{ formatCompact(report.summary.profit) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.profitReport.kpiMargin') }}</p>
                    <p class="mt-1 text-xl font-bold" :class="marginClass(report.summary.margin)">
                        {{ report.summary.margin.toFixed(1) }}%
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Jadval -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-sm">
                    <TrendingUp class="h-3.5 w-3.5 text-primary" />
                    {{ t('pageDealer.profitReport.tableTitle') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="report.rows.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    {{ t('pageDealer.profitReport.empty') }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[960px] text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ t('pageDealer.profitReport.thProduct') }}</th>
                                <th class="px-3 py-2 font-medium">{{ t('pageDealer.profitReport.thCategory') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.profitReport.thQty') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.profitReport.thAvgPrice') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.profitReport.thAvgCost') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.profitReport.thRevenue') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.profitReport.thCogs') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.profitReport.thProfit') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.profitReport.thMargin') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in report.rows" :key="row.id" class="hover:bg-muted/20">
                                <td class="px-3 py-2 text-sm font-medium">
                                    <div class="flex items-center gap-2">
                                        <span>{{ row.name }}</span>
                                        <Badge v-if="!row.has_cost" variant="outline" class="text-[10px] text-amber-600">
                                            {{ t('pageDealer.profitReport.noCostBadge') }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-xs text-muted-foreground">{{ row.category ?? '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono text-sm">{{ formatQty(row.delivered_qty) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs">{{ formatMoney(row.avg_price) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs">
                                    <template v-if="row.has_cost">{{ formatMoney(row.avg_cost) }}</template>
                                    <template v-else>—</template>
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-sm">{{ formatMoney(row.revenue) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs text-rose-600">−{{ formatMoney(row.cogs) }}</td>
                                <td
                                    class="px-3 py-2 text-right font-mono text-sm font-semibold"
                                    :class="row.profit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600'"
                                >
                                    {{ formatMoney(row.profit) }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-sm font-semibold" :class="marginClass(row.margin)">
                                    {{ row.margin.toFixed(1) }}%
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t bg-muted/30 text-xs">
                            <tr>
                                <td class="px-3 py-2 font-semibold" colspan="5">{{ t('pageDealer.profitReport.totalRow') }}</td>
                                <td class="px-3 py-2 text-right font-mono font-semibold">{{ formatMoney(report.summary.revenue) }}</td>
                                <td class="px-3 py-2 text-right font-mono font-semibold text-rose-600">
                                    −{{ formatMoney(report.summary.cogs) }}
                                </td>
                                <td
                                    class="px-3 py-2 text-right font-mono font-semibold"
                                    :class="report.summary.profit >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600'"
                                >
                                    {{ formatMoney(report.summary.profit) }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-semibold" :class="marginClass(report.summary.margin)">
                                    {{ report.summary.margin.toFixed(1) }}%
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
