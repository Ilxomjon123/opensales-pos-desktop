<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Download, RefreshCw } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';

type GroupBy = 'day' | 'week' | 'month' | 'dealer';

type Row = {
    id?: number;
    label: string;
    orders: number;
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

type Meta = {
    date_from: string;
    date_to: string;
    group_by: GroupBy;
    dealer_id: number | null;
};

type Report = { summary: Summary; rows: Row[]; meta: Meta };
type Option = { value: string; label: string };
type IdNameRef = { id: number; name: string };

const props = defineProps<{
    report: Report;
    filters: Meta;
    groupByOptions: Option[];
    dealers?: IdNameRef[];
}>();

const { t } = useI18n();

const localFilters = ref<Meta>({ ...props.filters });

watch(
    () => props.filters,
    (n) => {
        localFilters.value = { ...n };
    },
    { deep: true },
);

function buildQuery(): Record<string, string | number> {
    const f = localFilters.value;
    const q: Record<string, string | number> = {
        date_from: f.date_from,
        date_to: f.date_to,
        group_by: f.group_by,
    };

    if (f.dealer_id) {
        q.dealer_id = f.dealer_id;
    }

    return q;
}

function apply() {
    router.get('/admin/reports/sales', buildQuery(), { preserveState: true, preserveScroll: true });
}

function reset() {
    const today = new Date();
    const ago = new Date();
    ago.setDate(today.getDate() - 29);
    const fmt = (d: Date) => d.toISOString().slice(0, 10);
    localFilters.value = { date_from: fmt(ago), date_to: fmt(today), group_by: 'day', dealer_id: null };
    apply();
}

function exportCsv() {
    const params = new URLSearchParams();
    Object.entries(buildQuery()).forEach(([k, v]) => params.set(k, String(v)));
    window.location.href = `/admin/reports/sales/export?${params.toString()}`;
}

function formatCompact(n: number): string {
    const abs = Math.abs(n);
    if (abs >= 1_000_000_000) return (n / 1_000_000_000).toFixed(1).replace(/\.0$/, '') + 'mlrd';
    if (abs >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'mln';
    if (abs >= 1_000) return (n / 1_000).toFixed(0) + 'k';
    return String(n);
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.platformSalesReport.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-5 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <BarChart3 class="h-4 w-4 text-primary" />
                </div>
                <div>
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl">{{ t('pageAdmin.platformSalesReport.title') }}</h1>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.platformSalesReport.subtitle') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="reset">
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />{{ t('pageAdmin.platformSalesReport.reset') }}
                </Button>
                <Button variant="default" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 h-3.5 w-3.5" />{{ t('pageAdmin.platformSalesReport.exportCsv') }}
                </Button>
            </div>
        </div>

        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageAdmin.platformSalesReport.dateFrom') }}</label>
                    <Input v-model="localFilters.date_from" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageAdmin.platformSalesReport.dateTo') }}</label>
                    <Input v-model="localFilters.date_to" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageAdmin.platformSalesReport.groupBy') }}</label>
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
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageAdmin.platformSalesReport.dealer') }}</label>
                    <SearchableSelect
                        v-model="localFilters.dealer_id"
                        :items="dealers ?? []"
                        value-key="id"
                        label-key="name"
                        :placeholder="dealers ? t('pageAdmin.common.all') : t('pageAdmin.common.loading')"
                        :disabled="!dealers"
                        @change="apply()"
                    />
                </div>
            </CardContent>
        </Card>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.platformSalesReport.kpiOrders') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ report.summary.orders }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.platformSalesReport.kpiGross') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatCompact(report.summary.gross) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.platformSalesReport.kpiDiscount') }}</p>
                    <p class="mt-1 text-xl font-bold text-rose-600">−{{ formatCompact(report.summary.discount) }}</p>
                </CardContent>
            </Card>
            <Card class="border-emerald-200/60">
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.platformSalesReport.kpiNet') }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ formatCompact(report.summary.net) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.platformSalesReport.kpiAov') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatCompact(report.summary.aov) }}</p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm">{{ t('pageAdmin.platformSalesReport.tableTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="report.rows.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    {{ t('pageAdmin.platformSalesReport.empty') }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ t('pageAdmin.platformSalesReport.thLabel') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.platformSalesReport.thOrders') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.platformSalesReport.thGross') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.platformSalesReport.thDiscount') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.platformSalesReport.thNet') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.platformSalesReport.thAov') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in report.rows" :key="row.label + (row.id ?? '')" class="hover:bg-muted/20">
                                <td class="px-4 py-2 text-sm font-medium">{{ row.label }}</td>
                                <td class="px-4 py-2 text-right font-mono text-sm">{{ row.orders }}</td>
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
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
