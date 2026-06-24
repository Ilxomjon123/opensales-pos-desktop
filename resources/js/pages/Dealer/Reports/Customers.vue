<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, RefreshCw, Store, Users } from 'lucide-vue-next';
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

type Tier = 'A' | 'B' | 'C';
type Activity = 'active' | 'at_risk' | 'inactive';

type Row = {
    id: number;
    name: string;
    region: string | null;
    district: string | null;
    orders: number;
    gross: number;
    discount: number;
    net: number;
    aov: number;
    frequency_per_month: number;
    last_order_at: string | null;
    days_since: number | null;
    balance: number;
    tier: Tier;
    activity: Activity;
};

type Summary = {
    shops: number;
    a_count: number;
    b_count: number;
    c_count: number;
    active_count: number;
    at_risk_count: number;
    inactive_count: number;
    revenue: number;
    debt: number;
};

type Meta = {
    date_from: string;
    date_to: string;
    activity: Activity | null;
    region: string | null;
    district: string | null;
};

type Report = {
    summary: Summary;
    rows: Row[];
    meta: Meta;
};

type Filters = Meta;

type Option = { value: string; label: string };

const props = defineProps<{
    report: Report;
    filters: Filters;
    activityOptions: Option[];
    regions?: Option[];
    districts?: Option[];
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

    if (f.activity) {
        q.activity = f.activity;
    }

    if (f.region) {
        q.region = f.region;
    }

    if (f.district) {
        q.district = f.district;
    }

    return q;
}

function apply() {
    router.get('/dealer/reports/customers', buildQuery(), {
        preserveState: true,
        preserveScroll: true,
    });
}

function reset() {
    const today = new Date();
    const start = new Date();
    start.setDate(today.getDate() - 89);

    const fmt = (d: Date) => d.toISOString().slice(0, 10);

    localFilters.value = {
        date_from: fmt(start),
        date_to: fmt(today),
        activity: null,
        region: null,
        district: null,
    };

    apply();
}

function exportCsv() {
    const params = new URLSearchParams();
    Object.entries(buildQuery()).forEach(([k, v]) => params.set(k, String(v)));
    window.location.href = `/dealer/reports/customers/export?${params.toString()}`;
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

const tierColor: Record<Tier, string> = {
    A: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-300/40',
    B: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-300/40',
    C: 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-300/40',
};

const activityColor: Record<Activity, string> = {
    active: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    at_risk: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    inactive: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.customersReport.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-5 sm:p-6">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Users class="h-4 w-4 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl">{{ t('pageDealer.customersReport.title') }}</h1>
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.customersReport.subtitle') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="reset">
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.customersReport.reset') }}
                </Button>
                <Button variant="default" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.customersReport.exportCsv') }}
                </Button>
            </div>
        </div>

        <!-- Filtrlar -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 sm:gap-4 lg:grid-cols-5 lg:items-end">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.customersReport.dateFrom') }}</label>
                    <Input v-model="localFilters.date_from" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.customersReport.dateTo') }}</label>
                    <Input v-model="localFilters.date_to" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.customersReport.activity') }}</label>
                    <SearchableSelect
                        v-model="localFilters.activity"
                        :items="activityOptions"
                        value-key="value"
                        label-key="label"
                        :placeholder="t('pageDealer.common.all')"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.customersReport.region') }}</label>
                    <SearchableSelect
                        v-model="localFilters.region"
                        :items="regions ?? []"
                        value-key="value"
                        label-key="label"
                        :placeholder="regions ? t('pageDealer.common.all') : t('pageDealer.common.loading')"
                        :disabled="!regions"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.customersReport.district') }}</label>
                    <SearchableSelect
                        v-model="localFilters.district"
                        :items="districts ?? []"
                        value-key="value"
                        label-key="label"
                        :placeholder="districts ? t('pageDealer.common.all') : t('pageDealer.common.loading')"
                        :disabled="!districts"
                        @change="apply()"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- KPI summary -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-6">
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.customersReport.kpiShops') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ report.summary.shops }}</p>
                    <p class="text-[10px] text-muted-foreground">
                        {{ report.summary.active_count }} {{ t('pageDealer.customersReport.activeAbbr') }} /
                        {{ report.summary.at_risk_count }} {{ t('pageDealer.customersReport.atRiskAbbr') }} /
                        {{ report.summary.inactive_count }} {{ t('pageDealer.customersReport.inactiveAbbr') }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">A</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ report.summary.a_count }}</p>
                    <p class="text-[10px] text-muted-foreground">top 80% revenue</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">B</p>
                    <p class="mt-1 text-xl font-bold text-amber-600">{{ report.summary.b_count }}</p>
                    <p class="text-[10px] text-muted-foreground">80–95%</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">C</p>
                    <p class="mt-1 text-xl font-bold text-muted-foreground">{{ report.summary.c_count }}</p>
                    <p class="text-[10px] text-muted-foreground">95–100%</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.customersReport.kpiRevenue') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatCompact(report.summary.revenue) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.customersReport.kpiDebt') }}</p>
                    <p class="mt-1 text-xl font-bold text-amber-700 dark:text-amber-400">−{{ formatCompact(report.summary.debt) }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Jadval -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-sm">
                    <Store class="h-3.5 w-3.5 text-primary" />
                    {{ t('pageDealer.customersReport.tableTitle') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="report.rows.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    {{ t('pageDealer.customersReport.empty') }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ t('pageDealer.customersReport.thShop') }}</th>
                                <th class="px-3 py-2 text-center font-medium">{{ t('pageDealer.customersReport.thTier') }}</th>
                                <th class="px-3 py-2 text-center font-medium">{{ t('pageDealer.customersReport.thActivity') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.customersReport.thOrders') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.customersReport.thRevenue') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.customersReport.thAov') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.customersReport.thFreq') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.customersReport.thLastOrder') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.customersReport.thBalance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in report.rows"
                                :key="row.id"
                                class="cursor-pointer hover:bg-muted/20"
                                @click="router.get(`/dealer/shops/${row.id}`)"
                            >
                                <td class="px-3 py-2 text-sm font-medium">
                                    <div class="truncate">{{ row.name }}</div>
                                    <div class="text-[10px] text-muted-foreground">
                                        <template v-if="row.region">{{ row.region }}</template>
                                        <template v-if="row.region && row.district"> · </template>
                                        <template v-if="row.district">{{ row.district }}</template>
                                    </div>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <Badge variant="outline" class="font-mono text-[10px]" :class="tierColor[row.tier]">{{ row.tier }}</Badge>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <Badge variant="outline" class="text-[10px]" :class="activityColor[row.activity]">
                                        {{ t('pageDealer.customersReport.activity_' + row.activity) }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-sm">{{ row.orders }}</td>
                                <td class="px-3 py-2 text-right font-mono text-sm font-semibold">{{ formatMoney(row.net) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs">{{ formatMoney(row.aov) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-xs">{{ row.frequency_per_month }}/oy</td>
                                <td class="px-3 py-2 text-right font-mono text-xs text-muted-foreground">
                                    <template v-if="row.last_order_at">
                                        {{ formatDate(row.last_order_at) }}
                                        <div class="text-[10px]">{{ row.days_since }} {{ t('pageDealer.customersReport.daysSuffix') }}</div>
                                    </template>
                                    <template v-else>—</template>
                                </td>
                                <td
                                    class="px-3 py-2 text-right font-mono text-sm font-semibold"
                                    :class="row.balance < 0 ? 'text-rose-600' : row.balance > 0 ? 'text-emerald-600' : 'text-muted-foreground'"
                                >
                                    {{ formatMoney(row.balance) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
