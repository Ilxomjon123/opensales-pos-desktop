<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Activity, Download, RefreshCw } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/date';
import { formatMoney } from '@/lib/format';

type Status = 'active' | 'at_risk' | 'inactive';

type Row = {
    id: number;
    name: string;
    is_active: boolean;
    shops_count: number;
    mau_shops: number;
    orders: number;
    revenue: number;
    frequency_per_month: number;
    last_order_at: string | null;
    days_since: number | null;
    status: Status;
};

type Summary = {
    dealers: number;
    active: number;
    at_risk: number;
    inactive: number;
    total_revenue: number;
    total_orders: number;
};

type Meta = { date_from: string; date_to: string; status: Status | null };
type Report = { summary: Summary; rows: Row[]; meta: Meta };
type Option = { value: string; label: string };

const props = defineProps<{ report: Report; filters: Meta; statusOptions: Option[] }>();

const { t } = useI18n();

const localFilters = ref<Meta>({ ...props.filters });

watch(() => props.filters, (n) => { localFilters.value = { ...n }; }, { deep: true });

function buildQuery(): Record<string, string | number> {
    const f = localFilters.value;
    const q: Record<string, string | number> = { date_from: f.date_from, date_to: f.date_to };
    if (f.status) q.status = f.status;
    return q;
}

function apply() {
    router.get('/admin/reports/dealer-activity', buildQuery(), { preserveState: true, preserveScroll: true });
}

function reset() {
    const today = new Date();
    const ago = new Date();
    ago.setDate(today.getDate() - 29);
    const fmt = (d: Date) => d.toISOString().slice(0, 10);
    localFilters.value = { date_from: fmt(ago), date_to: fmt(today), status: null };
    apply();
}

function exportCsv() {
    const params = new URLSearchParams();
    Object.entries(buildQuery()).forEach(([k, v]) => params.set(k, String(v)));
    window.location.href = `/admin/reports/dealer-activity/export?${params.toString()}`;
}

function formatCompact(n: number): string {
    const abs = Math.abs(n);
    if (abs >= 1_000_000_000) return (n / 1_000_000_000).toFixed(1).replace(/\.0$/, '') + 'mlrd';
    if (abs >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'mln';
    if (abs >= 1_000) return (n / 1_000).toFixed(0) + 'k';
    return String(n);
}

const statusColor: Record<Status, string> = {
    active: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    at_risk: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    inactive: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.dealerActivityReport.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-5 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Activity class="h-4 w-4 text-primary" />
                </div>
                <div>
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl">{{ t('pageAdmin.dealerActivityReport.title') }}</h1>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.dealerActivityReport.subtitle') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="reset">
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />{{ t('pageAdmin.dealerActivityReport.reset') }}
                </Button>
                <Button variant="default" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 h-3.5 w-3.5" />{{ t('pageAdmin.dealerActivityReport.exportCsv') }}
                </Button>
            </div>
        </div>

        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-3 lg:items-end">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageAdmin.dealerActivityReport.dateFrom') }}</label>
                    <Input v-model="localFilters.date_from" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageAdmin.dealerActivityReport.dateTo') }}</label>
                    <Input v-model="localFilters.date_to" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageAdmin.dealerActivityReport.status') }}</label>
                    <SearchableSelect
                        v-model="localFilters.status"
                        :items="statusOptions"
                        value-key="value"
                        label-key="label"
                        :placeholder="t('pageAdmin.common.all')"
                        @change="apply()"
                    />
                </div>
            </CardContent>
        </Card>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.dealerActivityReport.kpiDealers') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ report.summary.dealers }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.dealerActivityReport.kpiActive') }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ report.summary.active }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.dealerActivityReport.kpiAtRisk') }}</p>
                    <p class="mt-1 text-xl font-bold text-amber-600">{{ report.summary.at_risk }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.dealerActivityReport.kpiInactive') }}</p>
                    <p class="mt-1 text-xl font-bold text-rose-600">{{ report.summary.inactive }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.dealerActivityReport.kpiOrders') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ report.summary.total_orders }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.dealerActivityReport.kpiRevenue') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatCompact(report.summary.total_revenue) }}</p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm">{{ t('pageAdmin.dealerActivityReport.tableTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="report.rows.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    {{ t('pageAdmin.dealerActivityReport.empty') }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ t('pageAdmin.dealerActivityReport.thDealer') }}</th>
                                <th class="px-3 py-2 text-center font-medium">{{ t('pageAdmin.dealerActivityReport.thStatus') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageAdmin.dealerActivityReport.thShops') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageAdmin.dealerActivityReport.thMau') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageAdmin.dealerActivityReport.thOrders') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageAdmin.dealerActivityReport.thRevenue') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageAdmin.dealerActivityReport.thFreq') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageAdmin.dealerActivityReport.thLastOrder') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in report.rows" :key="row.id" class="hover:bg-muted/20">
                                <td class="px-3 py-2 text-sm font-medium">
                                    <span :class="row.is_active ? '' : 'text-muted-foreground'">{{ row.name }}</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <Badge variant="outline" class="text-[10px]" :class="statusColor[row.status]">
                                        {{ t('pageAdmin.dealerActivityReport.status_' + row.status) }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-sm">{{ row.shops_count }}</td>
                                <td class="px-3 py-2 text-right font-mono text-sm">{{ row.mau_shops }}</td>
                                <td class="px-3 py-2 text-right font-mono text-sm">{{ row.orders }}</td>
                                <td class="px-3 py-2 text-right font-mono text-sm font-semibold">{{ formatMoney(row.revenue) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs">{{ row.frequency_per_month }}/oy</td>
                                <td class="px-3 py-2 text-right font-mono text-xs text-muted-foreground">
                                    <template v-if="row.last_order_at">
                                        {{ formatDate(row.last_order_at) }}
                                        <div class="text-[10px]">{{ row.days_since }} {{ t('pageAdmin.dealerActivityReport.daysSuffix') }}</div>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
