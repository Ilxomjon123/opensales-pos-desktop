<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, HandCoins, RefreshCw } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';

type Row = {
    id: number;
    name: string;
    is_active: boolean;
    orders: number;
    turnover: number;
    fee_rate: number;
    fee: number;
    paid: number;
    balance: number;
};

type Summary = {
    dealers: number;
    turnover: number;
    fee: number;
    paid: number;
    owed: number;
};

type Meta = { date_from: string; date_to: string };
type Report = { summary: Summary; rows: Row[]; meta: Meta };

const props = defineProps<{ report: Report; filters: Meta }>();

const { t } = useI18n();

const localFilters = ref<Meta>({ ...props.filters });

watch(() => props.filters, (n) => { localFilters.value = { ...n }; }, { deep: true });

function apply() {
    router.get('/admin/reports/commission', { ...localFilters.value }, { preserveState: true, preserveScroll: true });
}

function reset() {
    const today = new Date();
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    const fmt = (d: Date) => d.toISOString().slice(0, 10);
    localFilters.value = { date_from: fmt(first), date_to: fmt(today) };
    apply();
}

function exportCsv() {
    const params = new URLSearchParams();
    params.set('date_from', localFilters.value.date_from);
    params.set('date_to', localFilters.value.date_to);
    window.location.href = `/admin/reports/commission/export?${params.toString()}`;
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
    <Head :title="t('pageAdmin.commissionReport.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-5 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <HandCoins class="h-4 w-4 text-primary" />
                </div>
                <div>
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl">{{ t('pageAdmin.commissionReport.title') }}</h1>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.commissionReport.subtitle') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="reset">
                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" />{{ t('pageAdmin.commissionReport.reset') }}
                </Button>
                <Button variant="default" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 h-3.5 w-3.5" />{{ t('pageAdmin.commissionReport.exportCsv') }}
                </Button>
            </div>
        </div>

        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 lg:items-end">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageAdmin.commissionReport.dateFrom') }}</label>
                    <Input v-model="localFilters.date_from" type="date" @change="apply()" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageAdmin.commissionReport.dateTo') }}</label>
                    <Input v-model="localFilters.date_to" type="date" @change="apply()" />
                </div>
            </CardContent>
        </Card>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.commissionReport.kpiDealers') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ report.summary.dealers }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.commissionReport.kpiTurnover') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatCompact(report.summary.turnover) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.commissionReport.kpiFee') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatCompact(report.summary.fee) }}</p>
                </CardContent>
            </Card>
            <Card class="border-emerald-200/60">
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.commissionReport.kpiPaid') }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ formatCompact(report.summary.paid) }}</p>
                </CardContent>
            </Card>
            <Card class="border-rose-200/60">
                <CardContent class="p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.commissionReport.kpiOwed') }}</p>
                    <p class="mt-1 text-xl font-bold text-rose-700 dark:text-rose-400">{{ formatCompact(report.summary.owed) }}</p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm">{{ t('pageAdmin.commissionReport.tableTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="report.rows.length === 0" class="p-10 text-center text-sm text-muted-foreground">
                    {{ t('pageAdmin.commissionReport.empty') }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[840px] text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ t('pageAdmin.commissionReport.thDealer') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.commissionReport.thOrders') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.commissionReport.thTurnover') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.commissionReport.thRate') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.commissionReport.thFee') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.commissionReport.thPaid') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.commissionReport.thBalance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in report.rows" :key="row.id" class="hover:bg-muted/20">
                                <td class="px-4 py-2 text-sm font-medium">
                                    <span :class="row.is_active ? '' : 'text-muted-foreground'">{{ row.name }}</span>
                                    <span v-if="!row.is_active" class="ml-2 text-[10px] text-muted-foreground">(o'chirilgan)</span>
                                </td>
                                <td class="px-4 py-2 text-right font-mono text-sm">{{ row.orders }}</td>
                                <td class="px-4 py-2 text-right font-mono text-sm">{{ formatMoney(row.turnover) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-xs">{{ row.fee_rate.toFixed(2) }}%</td>
                                <td class="px-4 py-2 text-right font-mono text-sm">{{ formatMoney(row.fee) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-sm text-emerald-700 dark:text-emerald-400">{{ formatMoney(row.paid) }}</td>
                                <td
                                    class="px-4 py-2 text-right font-mono text-sm font-semibold"
                                    :class="row.balance < 0 ? 'text-rose-600' : row.balance > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'"
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
