<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { confirm } from '@/composables/useConfirm';
import { formatDateTime } from '@/lib/date';
import { formatMoneySum } from '@/lib/format';

export type PlatformPayment = {
    id: number;
    dealer_id: number;
    dealer_name: string;
    amount: number;
    discount: number;
    note: string | null;
    created_at: string;
};

defineProps<{
    payments: PlatformPayment[];
}>();

const { t } = useI18n();

async function deletePayment(id: number) {
    const ok = await confirm({
        title: t('pageAdmin.stats.recentPayments.deleteTitle'),
        description: t('pageAdmin.stats.recentPayments.deleteDescription'),
        confirmText: t('pageAdmin.stats.recentPayments.deleteConfirm'),
        variant: 'destructive',
    });

    if (!ok) {
return;
}

    router.delete(`/admin/platform-payments/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Card v-if="payments.length > 0">
        <CardHeader>
            <CardTitle>{{ t('pageAdmin.stats.recentPayments.title') }}</CardTitle>
        </CardHeader>
        <CardContent class="overflow-x-auto p-0">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('pageAdmin.stats.recentPayments.colDate') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('pageAdmin.stats.recentPayments.colDealer') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.stats.recentPayments.colAmount') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.stats.recentPayments.colDiscount') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('pageAdmin.stats.recentPayments.colNote') }}</th>
                        <th class="w-20 px-4 py-3 text-center font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="p in payments" :key="p.id" class="hover:bg-muted/20">
                        <td class="px-4 py-3 text-xs text-muted-foreground">{{ formatDateTime(p.created_at) }}</td>
                        <td class="px-4 py-3">{{ p.dealer_name }}</td>
                        <td class="px-4 py-3 text-right font-mono text-emerald-600">
                            {{ formatMoneySum(p.amount) }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-amber-600">
                            <span v-if="p.discount > 0">−{{ formatMoneySum(p.discount) }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">{{ p.note ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-7 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="deletePayment(p.id)"
                            >
                                {{ t('pageAdmin.stats.recentPayments.delete') }}
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </CardContent>
    </Card>
</template>
