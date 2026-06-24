<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, Download, Package, RefreshCw, RotateCcw, Store } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';

type Summary = {
    ops_count: number;
    total_qty: number;
    total_value: number;
    shop_value: number;
    supplier_value: number;
    restock_value: number;
    spoilage_value: number;
};

type TopProduct = { product_id: number; name: string; qty: number; value: number; ops: number };
type TopShop = { shop_id: number; name: string; ops: number; qty: number; value: number };
type DispositionRow = { disposition: string | null; label: string; lines: number; qty: number; value: number };

type Meta = {
    date_from: string;
    date_to: string;
    source: string | null;
    disposition: string | null;
};

type Report = {
    summary: Summary;
    top_products: TopProduct[];
    top_shops: TopShop[];
    by_disposition: DispositionRow[];
    meta: Meta;
};

type Filters = Meta;
type Option = { value: string; label: string };

const props = defineProps<{
    report: Report;
    filters: Filters;
    sourceOptions: Option[];
    dispositionOptions: Option[];
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

    if (f.source) {
        q.source = f.source;
    }

    if (f.disposition) {
        q.disposition = f.disposition;
    }

    return q;
}

function apply() {
    router.get('/dealer/reports/returns', buildQuery(), {
        preserveState: true,
        preserveScroll: true,
    });
}

function reset() {
    const today = new Date();
    const start = new Date();
    start.setDate(today.getDate() - 29);

    const fmt = (d: Date) => d.toISOString().slice(0, 10);

    localFilters.value = {
        date_from: fmt(start),
        date_to: fmt(today),
        source: null,
        disposition: null,
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
    window.location.href = `/dealer/reports/returns/export?${params.toString()}`;
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

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.returnsReport.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-5 sm:p-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <RotateCcw class="h-4 w-4 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl">{{ t('pageDealer.returnsReport.title') }}</h1>
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.returnsReport.subtitle') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="reset">
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.returnsReport.reset') }}
                </Button>
                <Button variant="default" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.returnsReport.exportCsv') }}
                </Button>
            </div>
        </div>

        <!-- Filtrlar -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 sm:gap-4 lg:grid-cols-5 lg:items-end">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.returnsReport.dateFrom') }}</label>
                    <Input v-model="localFilters.date_from" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.returnsReport.dateTo') }}</label>
                    <Input v-model="localFilters.date_to" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.returnsReport.source') }}</label>
                    <SearchableSelect
                        v-model="localFilters.source"
                        :items="sourceOptions"
                        value-key="value"
                        label-key="label"
                        :placeholder="t('pageDealer.common.all')"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.returnsReport.disposition') }}</label>
                    <SearchableSelect
                        v-model="localFilters.disposition"
                        :items="dispositionOptions"
                        value-key="value"
                        label-key="label"
                        :placeholder="t('pageDealer.common.all')"
                        @change="apply()"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" class="h-9 px-2 text-xs" @click="presetRange(7)">7 {{ t('pageDealer.returnsReport.daysSuffix') }}</Button>
                    <Button variant="outline" size="sm" class="h-9 px-2 text-xs" @click="presetRange(30)">30 {{ t('pageDealer.returnsReport.daysSuffix') }}</Button>
                    <Button variant="outline" size="sm" class="h-9 px-2 text-xs" @click="presetRange(90)">90 {{ t('pageDealer.returnsReport.daysSuffix') }}</Button>
                </div>
            </CardContent>
        </Card>

        <!-- KPI -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-6">
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.returnsReport.kpiOps') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ report.summary.ops_count }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.returnsReport.kpiTotalValue') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatCompact(report.summary.total_value) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.returnsReport.kpiShopValue') }}</p>
                    <p class="mt-1 text-xl font-bold text-amber-600">{{ formatCompact(report.summary.shop_value) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.returnsReport.kpiSupplierValue') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatCompact(report.summary.supplier_value) }}</p>
                </CardContent>
            </Card>
            <Card class="border-emerald-200/60">
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.returnsReport.kpiRestock') }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ formatCompact(report.summary.restock_value) }}</p>
                </CardContent>
            </Card>
            <Card class="border-rose-200/60">
                <CardContent class="p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-muted-foreground">{{ t('pageDealer.returnsReport.kpiSpoilage') }}</p>
                        <AlertTriangle class="h-3.5 w-3.5 text-rose-600" />
                    </div>
                    <p class="mt-1 text-xl font-bold text-rose-700 dark:text-rose-400">{{ formatCompact(report.summary.spoilage_value) }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Disposition breakdown -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm">{{ t('pageDealer.returnsReport.dispositionTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ t('pageDealer.returnsReport.thDisposition') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.returnsReport.thLines') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.returnsReport.thQty') }}</th>
                            <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.returnsReport.thValue') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="row in report.by_disposition" :key="row.disposition ?? 'null'" class="hover:bg-muted/20">
                            <td class="px-4 py-2 text-sm font-medium">{{ row.label }}</td>
                            <td class="px-4 py-2 text-right font-mono text-sm">{{ row.lines }}</td>
                            <td class="px-4 py-2 text-right font-mono text-sm">{{ formatQty(row.qty) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-sm">{{ formatMoney(row.value) }}</td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <!-- Top products + Top shops -->
        <div class="grid gap-3 lg:grid-cols-2">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm">
                        <Package class="h-3.5 w-3.5 text-primary" />
                        {{ t('pageDealer.returnsReport.topProductsTitle') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="report.top_products.length === 0" class="p-6 text-center text-xs text-muted-foreground">
                        {{ t('pageDealer.returnsReport.empty') }}
                    </div>
                    <table v-else class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-3 py-1.5 font-medium">{{ t('pageDealer.returnsReport.thProduct') }}</th>
                                <th class="px-3 py-1.5 text-right font-medium">{{ t('pageDealer.returnsReport.thQty') }}</th>
                                <th class="px-3 py-1.5 text-right font-medium">{{ t('pageDealer.returnsReport.thValue') }}</th>
                                <th class="px-3 py-1.5 text-right font-medium">{{ t('pageDealer.returnsReport.thOps') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(p, i) in report.top_products" :key="p.product_id" class="hover:bg-muted/20">
                                <td class="px-3 py-1.5">
                                    <span class="mr-1.5 text-xs text-muted-foreground">{{ i + 1 }}</span>
                                    {{ p.name }}
                                </td>
                                <td class="px-3 py-1.5 text-right font-mono text-xs">{{ formatQty(p.qty) }}</td>
                                <td class="px-3 py-1.5 text-right font-mono text-sm font-semibold">{{ formatMoney(p.value) }}</td>
                                <td class="px-3 py-1.5 text-right font-mono text-xs text-muted-foreground">{{ p.ops }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm">
                        <Store class="h-3.5 w-3.5 text-primary" />
                        {{ t('pageDealer.returnsReport.topShopsTitle') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="report.top_shops.length === 0" class="p-6 text-center text-xs text-muted-foreground">
                        {{ t('pageDealer.returnsReport.empty') }}
                    </div>
                    <table v-else class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-3 py-1.5 font-medium">{{ t('pageDealer.returnsReport.thShop') }}</th>
                                <th class="px-3 py-1.5 text-right font-medium">{{ t('pageDealer.returnsReport.thOps') }}</th>
                                <th class="px-3 py-1.5 text-right font-medium">{{ t('pageDealer.returnsReport.thQty') }}</th>
                                <th class="px-3 py-1.5 text-right font-medium">{{ t('pageDealer.returnsReport.thValue') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(s, i) in report.top_shops"
                                :key="s.shop_id"
                                class="cursor-pointer hover:bg-muted/20"
                                @click="router.get(`/dealer/shops/${s.shop_id}`)"
                            >
                                <td class="px-3 py-1.5">
                                    <span class="mr-1.5 text-xs text-muted-foreground">{{ i + 1 }}</span>
                                    {{ s.name }}
                                </td>
                                <td class="px-3 py-1.5 text-right font-mono text-xs">{{ s.ops }}</td>
                                <td class="px-3 py-1.5 text-right font-mono text-xs">{{ formatQty(s.qty) }}</td>
                                <td class="px-3 py-1.5 text-right font-mono text-sm font-semibold">{{ formatMoney(s.value) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
