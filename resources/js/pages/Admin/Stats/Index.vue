<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import DealerActivityTable from '@/components/admin/stats/DealerActivityTable.vue';
import type {DealerActivity} from '@/components/admin/stats/DealerActivityTable.vue';
import DealerFinanceTable from '@/components/admin/stats/DealerFinanceTable.vue';
import type {DealerFinance} from '@/components/admin/stats/DealerFinanceTable.vue';
import InactiveDealersTable from '@/components/admin/stats/InactiveDealersTable.vue';
import type {InactiveDealer} from '@/components/admin/stats/InactiveDealersTable.vue';
import RecentPlatformPayments from '@/components/admin/stats/RecentPlatformPayments.vue';
import type {PlatformPayment} from '@/components/admin/stats/RecentPlatformPayments.vue';
import DailyOrdersChart from '@/components/charts/DailyOrdersChart.vue';
import MonthlyRevenueChart from '@/components/charts/MonthlyRevenueChart.vue';
import type {MonthPoint} from '@/components/charts/MonthlyRevenueChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import type { AdminTotals, ChartPoint, DealerItem } from '@/types';

const { t } = useI18n();

type GrowthBlock = { current: number; previous: number; delta_pct: number };
type Growth = { dealers: GrowthBlock; shops: GrowthBlock; orders: GrowthBlock };

type FinanceCurrencyRow = { currency: string; symbol: string; turnover: number; fee_owed: number; total_paid: number; total_discount: number; balance: number };

type Finance = {
    totals: { turnover: number; fee_owed: number; total_paid: number; total_discount: number; balance: number };
    totals_by_currency: FinanceCurrencyRow[];
    dealers: DealerFinance[];
    recent_payments: PlatformPayment[];
};

const props = defineProps<{
    totals: AdminTotals;
    chart: ChartPoint[];
    topDealers: DealerItem[];
    finance: Finance;
    monthlyRevenue: MonthPoint[];
    growth: Growth;
    dealerActivity: DealerActivity[];
    inactiveDealers: InactiveDealer[];
}>();

const revenueRows = computed(() => props.totals.by_currency ?? []);
const financeRows = computed(() => props.finance.totals_by_currency ?? []);
const multiCurrency = computed(() => financeRows.value.length > 1);

const growthBlocks = computed(() => [
    { label: t('pageAdmin.stats.growthDealers'), block: props.growth.dealers },
    { label: t('pageAdmin.stats.growthShops'), block: props.growth.shops },
    { label: t('pageAdmin.stats.growthOrders'), block: props.growth.orders },
]);

function deltaClass(pct: number): string {
    if (pct > 0) {
return 'text-emerald-600';
}

    if (pct < 0) {
return 'text-rose-600';
}

    return 'text-muted-foreground';
}

function deltaIcon(pct: number): string {
    if (pct > 0) {
return '↑';
}

    if (pct < 0) {
return '↓';
}

    return '→';
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.stats.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-bold sm:text-2xl">{{ t('pageAdmin.stats.title') }}</h1>
            <Button variant="outline" class="w-full sm:w-auto" @click="router.get('/admin/dealers')">{{ t('pageAdmin.stats.dealersButton') }}</Button>
        </div>

        <!-- KPI kartalar -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.stats.kpiDealers') }}</p>
                    <p class="text-2xl font-bold sm:text-3xl">{{ totals.dealers }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.stats.kpiDealersActive', { count: totals.active_dealers }) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.stats.kpiShops') }}</p>
                    <p class="text-2xl font-bold sm:text-3xl">{{ totals.shops }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.stats.kpiOrders') }}</p>
                    <p class="text-2xl font-bold sm:text-3xl">{{ totals.orders }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.stats.kpiOrdersPending', { count: totals.pending_orders }) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.stats.kpiRevenue') }}</p>
                    <!-- Bir nechta valyuta — har biri alohida; bitta bo'lsa avvalgidek -->
                    <template v-if="revenueRows.length > 1">
                        <p v-for="r in revenueRows" :key="r.currency" class="text-lg font-bold sm:text-xl">
                            {{ formatMoney(r.revenue) }} <span class="text-sm font-normal text-muted-foreground">{{ r.symbol }}</span>
                        </p>
                    </template>
                    <p v-else class="text-xl font-bold sm:text-2xl">
                        {{ formatMoney(totals.revenue) }} <span class="text-sm font-normal text-muted-foreground">{{ revenueRows[0]?.symbol ?? '' }}</span>
                    </p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.stats.kpiRevenueSubtitle') }}</p>
                    <p v-if="totals.discount > 0 && revenueRows.length <= 1" class="mt-1 font-mono text-xs text-rose-600">
                        {{ t('pageAdmin.stats.kpiRevenueDiscount', { amount: formatMoney(totals.discount) }) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Oy bo'yicha o'sish -->
        <div class="grid gap-3 sm:gap-4 md:grid-cols-3">
            <Card v-for="item in growthBlocks" :key="item.label">
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ item.label }} {{ t('pageAdmin.stats.growthMonthSuffix') }}</p>
                    <div class="mt-1 flex items-baseline gap-2">
                        <p class="text-3xl font-bold">{{ item.block.current }}</p>
                        <span class="text-sm" :class="deltaClass(item.block.delta_pct)">
                            {{ deltaIcon(item.block.delta_pct) }} {{ Math.abs(item.block.delta_pct).toFixed(1) }}%
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.stats.growthPrevious', { n: item.block.previous }) }}</p>
                </CardContent>
            </Card>
        </div>

        <MonthlyRevenueChart :data="monthlyRevenue" />

        <DealerActivityTable :dealers="dealerActivity" />

        <InactiveDealersTable :dealers="inactiveDealers" />

        <DailyOrdersChart :data="chart" />

        <!-- Tizim moliyasi — umumiy -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.stats.financeTotalTurnover') }}</p>
                    <template v-if="multiCurrency">
                        <p v-for="r in financeRows" :key="r.currency" class="text-lg font-bold">{{ formatMoney(r.turnover) }} <span class="text-xs font-normal text-muted-foreground">{{ r.symbol }}</span></p>
                    </template>
                    <p v-else class="text-2xl font-bold">{{ formatMoney(finance.totals.turnover) }} <span class="text-sm font-normal text-muted-foreground">{{ financeRows[0]?.symbol ?? '' }}</span></p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.stats.financeTotalTurnoverSubtitle') }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.stats.financeCommission') }}</p>
                    <template v-if="multiCurrency">
                        <p v-for="r in financeRows" :key="r.currency" class="text-lg font-bold">{{ formatMoney(r.fee_owed) }} <span class="text-xs font-normal text-muted-foreground">{{ r.symbol }}</span></p>
                    </template>
                    <p v-else class="text-2xl font-bold">{{ formatMoney(finance.totals.fee_owed) }} <span class="text-sm font-normal text-muted-foreground">{{ financeRows[0]?.symbol ?? '' }}</span></p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.stats.financeCommissionSubtitle') }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.stats.financePaid') }}</p>
                    <template v-if="multiCurrency">
                        <p v-for="r in financeRows" :key="r.currency" class="text-lg font-bold text-emerald-600">{{ formatMoney(r.total_paid) }} <span class="text-xs font-normal text-muted-foreground">{{ r.symbol }}</span></p>
                    </template>
                    <p v-else class="text-2xl font-bold text-emerald-600">{{ formatMoney(finance.totals.total_paid) }} <span class="text-sm font-normal text-muted-foreground">{{ financeRows[0]?.symbol ?? '' }}</span></p>
                    <p v-if="!multiCurrency && finance.totals.total_discount > 0" class="mt-1 font-mono text-xs text-amber-600">
                        {{ t('pageAdmin.stats.financeDiscount', { amount: formatMoney(finance.totals.total_discount) }) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.stats.financeBalance') }}</p>
                    <template v-if="multiCurrency">
                        <p
                            v-for="r in financeRows"
                            :key="r.currency"
                            class="text-lg font-bold"
                            :class="r.balance < 0 ? 'text-rose-600' : 'text-emerald-600'"
                        >
                            {{ formatMoney(Math.abs(r.balance)) }} <span class="text-xs font-normal text-muted-foreground">{{ r.symbol }}</span>
                        </p>
                    </template>
                    <template v-else>
                        <p
                            class="text-2xl font-bold"
                            :class="finance.totals.balance < 0 ? 'text-rose-600' : 'text-emerald-600'"
                        >
                            {{ formatMoney(Math.abs(finance.totals.balance)) }} <span class="text-sm font-normal text-muted-foreground">{{ financeRows[0]?.symbol ?? '' }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ finance.totals.balance < 0 ? t('pageAdmin.stats.financeBalanceDebt') : (finance.totals.balance > 0 ? t('pageAdmin.stats.financeBalanceCredit') : t('pageAdmin.stats.financeBalanceEqual')) }}
                        </p>
                    </template>
                </CardContent>
            </Card>
        </div>

        <DealerFinanceTable :dealers="finance.dealers" />

        <RecentPlatformPayments :payments="finance.recent_payments" />

        <!-- Top dillerlar (buyurtma soni bo'yicha) -->
        <Card>
            <CardHeader>
                <CardTitle>{{ t('pageAdmin.stats.topTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.stats.topRank') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.stats.topDealer') }}</th>
                            <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.stats.topShops') }}</th>
                            <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.stats.topOrders') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.stats.topRevenue') }}</th>
                            <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.stats.topStatus') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(d, i) in topDealers" :key="d.id" class="hover:bg-muted/20">
                            <td class="px-4 py-3 font-mono">{{ i + 1 }}</td>
                            <td class="px-4 py-3 font-medium">{{ d.name }}</td>
                            <td class="px-4 py-3 text-center">{{ d.shops_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-center">{{ d.orders_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="font-mono">{{ formatMoney(d.revenue ?? 0) }} {{ t('pageAdmin.stats.currency') }}</div>
                                <div v-if="(d.discount ?? 0) > 0" class="font-mono text-xs text-rose-600">
                                    {{ t('pageAdmin.stats.topRevenueDiscount', { amount: formatMoney(d.discount ?? 0) }) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Badge :variant="d.is_active ? 'default' : 'destructive'" class="text-xs">
                                    {{ d.is_active ? t('pageAdmin.stats.topActive') : t('pageAdmin.stats.topInactive') }}
                                </Badge>
                            </td>
                        </tr>
                        <tr v-if="topDealers.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-muted-foreground">
                                {{ t('pageAdmin.stats.topEmpty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
