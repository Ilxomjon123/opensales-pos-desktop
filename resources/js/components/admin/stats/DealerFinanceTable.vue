<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatMoney } from '@/lib/format';
import PlatformPaymentModal from './PlatformPaymentModal.vue';

export type CommissionType = 'turnover_percentage' | 'fixed_per_shop' | 'fixed_per_order' | 'fixed_per_deliveryman' | 'fixed_monthly';

export type DealerFinance = {
    id: number;
    name: string;
    is_active: boolean;
    shops_count: number;
    orders_count: number;
    turnover: number;
    fee_rate: number;
    fee_owed: number;
    total_paid: number;
    total_discount: number;
    balance: number;
    commission_type: CommissionType;
    fixed_commission_amount: number | null;
};

defineProps<{
    dealers: DealerFinance[];
}>();

const { t } = useI18n();

const payingDealer = ref<{ id: number; name: string; suggestedAmount: number } | null>(null);

function openPayment(d: DealerFinance) {
    payingDealer.value = {
        id: d.id,
        name: d.name,
        suggestedAmount: Math.max(0, -d.balance),
    };
}

function commissionLabel(d: DealerFinance): string {
    if (d.commission_type === 'fixed_per_shop') {
        return `${formatMoney(d.fixed_commission_amount ?? 0)} / ${t('pageAdmin.stats.financeTable.perShop')}`;
    }

    if (d.commission_type === 'fixed_per_order') {
        return `${formatMoney(d.fixed_commission_amount ?? 0)} / ${t('pageAdmin.stats.financeTable.perOrder')}`;
    }

    if (d.commission_type === 'fixed_per_deliveryman') {
        return `${formatMoney(d.fixed_commission_amount ?? 0)} / ${t('pageAdmin.stats.financeTable.perDeliveryman')}`;
    }

    if (d.commission_type === 'fixed_monthly') {
        return `${formatMoney(d.fixed_commission_amount ?? 0)} / ${t('pageAdmin.stats.financeTable.perMonth')}`;
    }

    return `${Number(d.fee_rate).toFixed(2)}%`;
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('pageAdmin.stats.financeTable.title') }}</CardTitle>
        </CardHeader>
        <CardContent class="overflow-x-auto p-0">
            <table class="w-full min-w-[800px] text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('pageAdmin.stats.financeTable.colDealer') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.stats.financeTable.colShops') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.stats.financeTable.colOrders') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.stats.financeTable.colTurnover') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.stats.financeTable.colCommission') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.stats.financeTable.colDebt') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.stats.financeTable.colPaid') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.stats.financeTable.colDiscount') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.stats.financeTable.colBalance') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.stats.financeTable.colActions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="d in dealers" :key="d.id" class="hover:bg-muted/20">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ d.name }}</span>
                                <Badge v-if="!d.is_active" variant="outline" class="text-[10px]">{{ t('pageAdmin.stats.financeTable.inactive') }}</Badge>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right font-mono">{{ d.shops_count }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ d.orders_count }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ formatMoney(d.turnover) }}</td>
                        <td class="px-4 py-3 text-center font-mono text-xs">{{ commissionLabel(d) }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ formatMoney(d.fee_owed) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-emerald-600">{{ formatMoney(d.total_paid) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-amber-600">
                            <span v-if="d.total_discount > 0">{{ formatMoney(d.total_discount) }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td
                            class="px-4 py-3 text-right font-mono font-semibold"
                            :class="d.balance < 0 ? 'text-rose-600' : (d.balance > 0 ? 'text-emerald-600' : '')"
                        >
                            {{ d.balance < 0 ? '−' : (d.balance > 0 ? '+' : '') }}{{ formatMoney(Math.abs(d.balance)) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <Button size="sm" variant="outline" class="h-7 text-xs" @click="openPayment(d)">
                                {{ t('pageAdmin.stats.financeTable.addPayment') }}
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="dealers.length === 0">
                        <td colspan="10" class="px-4 py-8 text-center text-muted-foreground">{{ t('pageAdmin.stats.financeTable.empty') }}</td>
                    </tr>
                </tbody>
            </table>
        </CardContent>
    </Card>

    <PlatformPaymentModal
        v-if="payingDealer"
        :dealer-id="payingDealer.id"
        :dealer-name="payingDealer.name"
        :suggested-amount="payingDealer.suggestedAmount"
        @close="payingDealer = null"
    />
</template>
