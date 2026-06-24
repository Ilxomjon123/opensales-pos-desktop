<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowUpRight, Receipt, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CommissionEditModal from '@/components/admin/stats/CommissionEditModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

type MonthRow = { month: string; fee_accrued: number; fee_paid: number; balance: number };
type PlatformTotals = { turnover: number; fee_owed: number; total_paid: number; total_discount: number; balance: number };
type CommissionType = 'turnover_percentage' | 'fixed_per_shop' | 'fixed_per_order' | 'fixed_per_deliveryman' | 'fixed_monthly';
type DealerRow = {
    id: number; name: string; is_active: boolean;
    shops_count: number;
    fee_rate: number;
    commission_type: CommissionType;
    fixed_commission_amount: number | null;
    turnover: number; fee_owed: number; total_paid: number; total_discount: number; balance: number;
};

const props = defineProps<{
    platformTotals: PlatformTotals;
    platformMonthly: MonthRow[];
    dealers: DealerRow[];
}>();

function formatMoney(n: number): string {
    return String(Math.abs(Math.round(n))).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function formatMonth(ym: string): string {
    if (!ym) {
return '';
}

    const [y, m] = ym.split('-');
    const monthKeys = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    const monthLabel = t(`pageAdmin.billingDealer.months.${monthKeys[parseInt(m, 10) - 1]}`);

    return `${monthLabel} ${y.slice(2)}`;
}

const maxFee = computed(() => Math.max(
    ...props.platformMonthly.flatMap(m => [m.fee_accrued, m.fee_paid]),
    1,
));

function commissionLabel(d: DealerRow): string {
    if (d.commission_type === 'fixed_per_shop') {
        return t('pageAdmin.billingDealer.commissionLabel.perShop', { amount: formatMoney(d.fixed_commission_amount ?? 0) });
    }

    if (d.commission_type === 'fixed_per_order') {
        return t('pageAdmin.billingDealer.commissionLabel.perOrder', { amount: formatMoney(d.fixed_commission_amount ?? 0) });
    }

    if (d.commission_type === 'fixed_per_deliveryman') {
        return t('pageAdmin.billingDealer.commissionLabel.perDeliveryman', { amount: formatMoney(d.fixed_commission_amount ?? 0) });
    }

    if (d.commission_type === 'fixed_monthly') {
        return t('pageAdmin.billingDealer.commissionLabel.monthly', { amount: formatMoney(d.fixed_commission_amount ?? 0) });
    }

    return t('pageAdmin.billingDealer.commissionLabel.turnover', { percent: Number(d.fee_rate).toFixed(2) });
}

const editingDealer = ref<DealerRow | null>(null);

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.billing.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Wallet class="h-5 w-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.billing.title') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.billing.subtitle') }}</p>
                </div>
            </div>
            <Button variant="outline" class="w-full sm:w-auto" @click="router.get('/admin/platform-payments')">
                <Receipt class="mr-2 h-4 w-4" />
                {{ t('pageAdmin.billing.paymentsHistory') }}
            </Button>
        </div>

        <!-- Platforma umumiy -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.billing.totalTurnover') }}</p>
                    <p class="mt-1 text-2xl font-bold">{{ formatMoney(platformTotals.turnover) }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.billing.currency') }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.billing.totalCommission') }}</p>
                    <p class="mt-1 text-2xl font-bold text-sky-600">{{ formatMoney(platformTotals.fee_owed) }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.billing.currency') }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.billing.paid') }}</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600">{{ formatMoney(platformTotals.total_paid) }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.billing.currency') }}</p>
                    <p v-if="platformTotals.total_discount > 0" class="mt-1 font-mono text-xs text-amber-600">
                        + {{ formatMoney(platformTotals.total_discount) }} {{ t('pageAdmin.billing.discount') }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.billing.balanceTitle') }}</p>
                    <p class="mt-1 text-2xl font-bold" :class="platformTotals.balance > 0 ? 'text-amber-600' : 'text-muted-foreground'">
                        {{ platformTotals.balance > 0 ? '−' : '' }}{{ formatMoney(platformTotals.balance) }}
                    </p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.billing.currency') }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- 12 oylik trend -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <TrendingUp class="h-4 w-4 text-primary" />
                    {{ t('pageAdmin.billing.trendTitle') }}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex h-48 gap-2 overflow-hidden">
                    <div
                        v-for="m in platformMonthly"
                        :key="m.month"
                        class="group relative flex flex-1 flex-col justify-end overflow-hidden"
                    >
                        <div class="flex h-full w-full items-end gap-0.5">
                            <div
                                class="w-full rounded-t-sm bg-sky-500/80"
                                :style="{ height: `${(m.fee_accrued / maxFee) * 100}%`, minHeight: m.fee_accrued > 0 ? '4px' : '0' }"
                                :title="t('pageAdmin.billing.legendAccrued')"
                            />
                            <div
                                class="w-full rounded-t-sm bg-emerald-500/80"
                                :style="{ height: `${(m.fee_paid / maxFee) * 100}%`, minHeight: m.fee_paid > 0 ? '4px' : '0' }"
                                :title="t('pageAdmin.billing.legendPaid')"
                            />
                        </div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-popover px-3 py-2 text-xs shadow-lg group-hover:block">
                            <p class="font-medium">{{ formatMonth(m.month) }}</p>
                            <p>{{ t('pageAdmin.billing.tooltipAccrued') }}: <span class="font-mono font-semibold">{{ formatMoney(m.fee_accrued) }}</span></p>
                            <p>{{ t('pageAdmin.billing.tooltipPaid') }}: <span class="font-mono font-semibold">{{ formatMoney(m.fee_paid) }}</span></p>
                            <p>{{ t('pageAdmin.billing.tooltipBalance') }}: <span class="font-mono font-semibold">{{ m.balance > 0 ? '−' : '' }}{{ formatMoney(m.balance) }}</span></p>
                        </div>
                    </div>
                </div>
                <div class="mt-1.5 flex gap-2">
                    <p v-for="m in platformMonthly" :key="m.month" class="flex-1 text-center text-[10px] text-muted-foreground">
                        {{ formatMonth(m.month) }}
                    </p>
                </div>
                <div class="mt-3 flex items-center gap-4 text-xs text-muted-foreground">
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-sky-500" /> {{ t('pageAdmin.billing.legendAccrued') }}</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-emerald-500" /> {{ t('pageAdmin.billing.legendPaid') }}</span>
                </div>
            </CardContent>
        </Card>

        <!-- Dillerlar bo'yicha -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('pageAdmin.billing.dealersTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.billing.tableDealer') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ t('pageAdmin.billing.tableShops') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ t('pageAdmin.billing.tableTurnover') }}</th>
                            <th class="px-4 py-3 font-medium text-center">{{ t('pageAdmin.billing.tableCommission') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ t('pageAdmin.billing.tableAccrued') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ t('pageAdmin.billing.tablePaid') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ t('pageAdmin.billing.tableDiscount') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ t('pageAdmin.billing.tableBalance') }}</th>
                            <th class="w-12 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="d in dealers" :key="d.id" class="hover:bg-muted/20">
                            <td class="px-4 py-3">
                                <button class="text-left font-medium hover:underline" @click="router.get(`/admin/billing/${d.id}`)">
                                    {{ d.name }}
                                </button>
                                <Badge v-if="!d.is_active" variant="outline" class="ml-2 text-[10px]">{{ t('pageAdmin.billing.inactive') }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ d.shops_count }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney(d.turnover) }}</td>
                            <td class="px-4 py-3 text-center">
                                <button
                                    type="button"
                                    class="rounded px-2 py-0.5 font-mono text-xs hover:bg-muted"
                                    @click.stop="editingDealer = d"
                                >
                                    {{ commissionLabel(d) }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-sky-600">{{ formatMoney(d.fee_owed) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-emerald-600">{{ formatMoney(d.total_paid) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-amber-600">
                                <span v-if="d.total_discount > 0">{{ formatMoney(d.total_discount) }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono" :class="d.balance > 0 ? 'text-amber-600' : 'text-muted-foreground'">
                                {{ d.balance > 0 ? '−' : '' }}{{ formatMoney(d.balance) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button class="text-muted-foreground hover:text-foreground" @click="router.get(`/admin/billing/${d.id}`)">
                                    <ArrowUpRight class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <CommissionEditModal
            v-if="editingDealer"
            :dealer-id="editingDealer.id"
            :dealer-name="editingDealer.name"
            :commission-type="editingDealer.commission_type"
            :fee-rate="editingDealer.fee_rate"
            :fixed-amount="editingDealer.fixed_commission_amount"
            @close="editingDealer = null"
        />
    </div>
</template>
