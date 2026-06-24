<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Boxes, Download, RefreshCw } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/AppLayout.vue';

type Row = {
    id: number;
    name: string;
    category: string | null;
    unit: string;
    start_stock: number;
    in_qty: number;
    out_qty: number;
    shop_return_qty: number;
    supplier_return_qty: number;
    adjust_delta: number;
    net_change: number;
    end_stock: number;
    current_stock: number;
};

type Summary = {
    products: number;
    in_qty: number;
    out_qty: number;
    shop_return_qty: number;
    supplier_return_qty: number;
    adjust_delta: number;
    net_change: number;
    current_stock: number;
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
    router.get('/dealer/reports/inventory', buildQuery(), {
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
    window.location.href = `/dealer/reports/inventory/export?${params.toString()}`;
}

function formatQty(n: number): string {
    if (!Number.isFinite(n)) {
        return '0';
    }

    if (Math.abs(n) < 0.01) {
        return '0';
    }

    return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
}

function signedQty(n: number, prefix = ''): string {
    if (Math.abs(n) < 0.01) {
        return '—';
    }

    return (n > 0 ? '+' : '') + prefix + formatQty(n);
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.inventoryReport.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-5 sm:p-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Boxes class="h-4 w-4 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl">{{ t('pageDealer.inventoryReport.title') }}</h1>
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.inventoryReport.subtitle') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="reset">
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.inventoryReport.reset') }}
                </Button>
                <Button variant="default" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.inventoryReport.exportCsv') }}
                </Button>
            </div>
        </div>

        <!-- Filtrlar -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 sm:gap-4 lg:grid-cols-5 lg:items-end">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.inventoryReport.dateFrom') }}</label>
                    <Input v-model="localFilters.date_from" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.inventoryReport.dateTo') }}</label>
                    <Input v-model="localFilters.date_to" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.inventoryReport.category') }}</label>
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
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.inventoryReport.product') }}</label>
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
                    <Button variant="outline" size="sm" class="h-9 px-2 text-xs" @click="presetRange(7)">7 {{ t('pageDealer.inventoryReport.daysSuffix') }}</Button>
                    <Button variant="outline" size="sm" class="h-9 px-2 text-xs" @click="presetRange(30)">30 {{ t('pageDealer.inventoryReport.daysSuffix') }}</Button>
                    <Button variant="outline" size="sm" class="h-9 px-2 text-xs" @click="presetRange(90)">90 {{ t('pageDealer.inventoryReport.daysSuffix') }}</Button>
                </div>
            </CardContent>
        </Card>

        <!-- KPI Summary -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-6">
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.inventoryReport.kpiProducts') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ report.summary.products }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.inventoryReport.kpiIn') }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">+{{ formatQty(report.summary.in_qty) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.inventoryReport.kpiOut') }}</p>
                    <p class="mt-1 text-xl font-bold">−{{ formatQty(report.summary.out_qty) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.inventoryReport.kpiShopReturn') }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-600">+{{ formatQty(report.summary.shop_return_qty) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.inventoryReport.kpiNet') }}</p>
                    <p
                        class="mt-1 text-xl font-bold"
                        :class="report.summary.net_change >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600'"
                    >
                        {{ report.summary.net_change >= 0 ? '+' : '' }}{{ formatQty(report.summary.net_change) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.inventoryReport.kpiCurrent') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatQty(report.summary.current_stock) }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Jadval -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm">{{ t('pageDealer.inventoryReport.tableTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="report.rows.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    {{ t('pageDealer.inventoryReport.empty') }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ t('pageDealer.inventoryReport.thProduct') }}</th>
                                <th class="px-3 py-2 font-medium">{{ t('pageDealer.inventoryReport.thCategory') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.inventoryReport.thStart') }}</th>
                                <th class="px-3 py-2 text-right font-medium text-emerald-700 dark:text-emerald-400">
                                    {{ t('pageDealer.inventoryReport.thIn') }}
                                </th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.inventoryReport.thShopReturn') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.inventoryReport.thOut') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.inventoryReport.thSupplierReturn') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.inventoryReport.thAdjust') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.inventoryReport.thNet') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.inventoryReport.thEnd') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.inventoryReport.thCurrent') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in report.rows" :key="row.id" class="hover:bg-muted/20">
                                <td class="px-3 py-2 text-sm font-medium">{{ row.name }}</td>
                                <td class="px-3 py-2 text-xs text-muted-foreground">{{ row.category ?? '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs text-muted-foreground">{{ formatQty(row.start_stock) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs text-emerald-700 dark:text-emerald-400">{{ signedQty(row.in_qty) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs text-emerald-600">{{ signedQty(row.shop_return_qty) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs">{{ row.out_qty > 0 ? '−' + formatQty(row.out_qty) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs">{{ row.supplier_return_qty > 0 ? '−' + formatQty(row.supplier_return_qty) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs">{{ signedQty(row.adjust_delta) }}</td>
                                <td
                                    class="px-3 py-2 text-right font-mono text-xs font-semibold"
                                    :class="row.net_change >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600'"
                                >
                                    {{ signedQty(row.net_change) }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-sm">{{ formatQty(row.end_stock) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-sm font-semibold">{{ formatQty(row.current_stock) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
