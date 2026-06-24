<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Printer, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

type MonthRow = { month: string; fee_accrued: number; fee_paid: number; balance: number };
type CommissionType = 'turnover_percentage' | 'fixed_per_shop' | 'fixed_per_order' | 'fixed_per_deliveryman' | 'fixed_monthly';

type Dealer = {
    id: number;
    name: string;
    bot_username: string | null;
    shops_count: number;
    fee_rate: number;
    commission_type: CommissionType;
    fixed_commission_amount: number | null;
    is_active: boolean;
};

type Snapshot = {
    turnover: number;
    fee_rate: number;
    fee_owed: number;
    total_paid: number;
    total_discount: number;
    balance: number;
    commission_type: CommissionType;
    fixed_commission_amount: number | null;
};

const props = defineProps<{
    dealer: Dealer;
    snapshot: Snapshot;
    monthly: MonthRow[];
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
    ...props.monthly.flatMap(m => [m.fee_accrued, m.fee_paid]),
    1,
));
const totalAccrued = computed(() => props.monthly.reduce((s, m) => s + m.fee_accrued, 0));
const totalPaid = computed(() => props.monthly.reduce((s, m) => s + m.fee_paid, 0));

const commissionDescription = computed(() => {
    if (props.dealer.commission_type === 'fixed_per_shop') {
        return t('pageAdmin.billingDealer.commission.perShop', { amount: formatMoney(props.dealer.fixed_commission_amount ?? 0) });
    }

    if (props.dealer.commission_type === 'fixed_per_order') {
        return t('pageAdmin.billingDealer.commission.perOrder', { amount: formatMoney(props.dealer.fixed_commission_amount ?? 0) });
    }

    if (props.dealer.commission_type === 'fixed_per_deliveryman') {
        return t('pageAdmin.billingDealer.commission.perDeliveryman', { amount: formatMoney(props.dealer.fixed_commission_amount ?? 0) });
    }

    if (props.dealer.commission_type === 'fixed_monthly') {
        return t('pageAdmin.billingDealer.commission.monthly', { amount: formatMoney(props.dealer.fixed_commission_amount ?? 0) });
    }

    return t('pageAdmin.billingDealer.commission.turnover', { percent: Number(props.dealer.fee_rate).toFixed(2) });
});

function print() {
    window.print();
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.billingDealer.headTitle', { name: dealer.name })" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6 print:p-0">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" class="shrink-0" @click="router.get('/admin/billing')">
                    <ArrowLeft class="h-5 w-5" />
                </Button>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Wallet class="h-5 w-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">{{ dealer.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        @{{ dealer.bot_username ?? '—' }} · {{ t('pageAdmin.billingDealer.shopsCount', { n: dealer.shops_count }) }} · {{ commissionDescription }}
                    </p>
                </div>
            </div>
            <Button variant="outline" class="w-full sm:w-auto" @click="print">
                <Printer class="mr-2 h-4 w-4" />
                {{ t('pageAdmin.billingDealer.print') }}
            </Button>
        </div>

        <!-- Hisob -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.billingDealer.turnover') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatMoney(snapshot.turnover) }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.billingDealer.currency') }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.billingDealer.commissionAccrued') }}</p>
                    <p class="mt-1 text-xl font-bold text-sky-600">{{ formatMoney(snapshot.fee_owed) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.billingDealer.dealerPaid') }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-600">{{ formatMoney(snapshot.total_paid) }}</p>
                    <p v-if="snapshot.total_discount > 0" class="mt-1 font-mono text-xs text-amber-600">
                        + {{ formatMoney(snapshot.total_discount) }} {{ t('pageAdmin.billingDealer.discountSuffix') }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.billingDealer.balanceTitle') }}</p>
                    <p class="mt-1 text-xl font-bold" :class="snapshot.balance > 0 ? 'text-amber-600' : 'text-muted-foreground'">
                        {{ snapshot.balance > 0 ? '−' : '' }}{{ formatMoney(snapshot.balance) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Oylik breakdown -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('pageAdmin.billingDealer.monthlyTitle') }}</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="mb-5 print:hidden">
                    <div class="flex h-40 gap-2 overflow-hidden">
                        <div
                            v-for="m in monthly"
                            :key="m.month"
                            class="group relative flex flex-1 flex-col justify-end overflow-hidden"
                        >
                            <div class="flex h-full w-full items-end gap-0.5">
                                <div
                                    class="w-full rounded-t-sm bg-sky-500/80"
                                    :style="{ height: `${(m.fee_accrued / maxFee) * 100}%`, minHeight: m.fee_accrued > 0 ? '4px' : '0' }"
                                />
                                <div
                                    class="w-full rounded-t-sm bg-emerald-500/80"
                                    :style="{ height: `${(m.fee_paid / maxFee) * 100}%`, minHeight: m.fee_paid > 0 ? '4px' : '0' }"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="mt-1.5 flex gap-2">
                        <p v-for="m in monthly" :key="m.month" class="flex-1 text-center text-[10px] text-muted-foreground">
                            {{ formatMonth(m.month) }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                <table class="w-full min-w-[480px] text-left text-sm">
                    <thead class="border-b bg-muted/40 text-xs">
                        <tr>
                            <th class="px-4 py-2 font-medium">{{ t('pageAdmin.billingDealer.tableMonth') }}</th>
                            <th class="px-4 py-2 font-medium text-right">{{ t('pageAdmin.billingDealer.tableAccrued') }}</th>
                            <th class="px-4 py-2 font-medium text-right">{{ t('pageAdmin.billingDealer.tablePaid') }}</th>
                            <th class="px-4 py-2 font-medium text-right">{{ t('pageAdmin.billingDealer.tableMonthlyBalance') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="m in monthly" :key="m.month" class="hover:bg-muted/20">
                            <td class="px-4 py-2">{{ formatMonth(m.month) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-sky-600">{{ formatMoney(m.fee_accrued) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-emerald-600">{{ formatMoney(m.fee_paid) }}</td>
                            <td class="px-4 py-2 text-right font-mono" :class="m.balance > 0 ? 'text-amber-600' : 'text-muted-foreground'">
                                {{ m.balance > 0 ? '−' : m.balance < 0 ? '+' : '' }}{{ formatMoney(m.balance) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 font-semibold">
                        <tr>
                            <td class="px-4 py-2">{{ t('pageAdmin.billingDealer.tableTotal') }}</td>
                            <td class="px-4 py-2 text-right font-mono text-sky-700">{{ formatMoney(totalAccrued) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-emerald-700">{{ formatMoney(totalPaid) }}</td>
                            <td class="px-4 py-2 text-right font-mono">
                                {{ totalAccrued - totalPaid > 0 ? '−' : totalAccrued - totalPaid < 0 ? '+' : '' }}{{ formatMoney(totalAccrued - totalPaid) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style>
@media print {
    @page {
        size: A4;
        margin: 15mm;
    }
}
</style>
