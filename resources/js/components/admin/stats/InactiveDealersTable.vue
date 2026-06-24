<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/date';

export type InactiveDealer = {
    id: number;
    name: string;
    last_order_at: string | null;
    days_since: number | null;
    shops: number;
};

defineProps<{
    dealers: InactiveDealer[];
}>();

const { t } = useI18n();
</script>

<template>
    <Card v-if="dealers.length > 0">
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                ⚠️ {{ t('pageAdmin.stats.inactiveTable.title') }}
            </CardTitle>
        </CardHeader>
        <CardContent class="overflow-x-auto p-0">
            <table class="w-full min-w-[480px] text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="px-4 py-2 font-medium">{{ t('pageAdmin.stats.inactiveTable.colDealer') }}</th>
                        <th class="px-4 py-2 text-center font-medium">{{ t('pageAdmin.stats.inactiveTable.colShops') }}</th>
                        <th class="px-4 py-2 font-medium">{{ t('pageAdmin.stats.inactiveTable.colLastOrder') }}</th>
                        <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.stats.inactiveTable.colDays') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="d in dealers" :key="d.id" class="hover:bg-muted/20">
                        <td class="px-4 py-2 font-medium">{{ d.name }}</td>
                        <td class="px-4 py-2 text-center">{{ d.shops }}</td>
                        <td class="px-4 py-2 text-xs text-muted-foreground">
                            <template v-if="d.last_order_at">{{ formatDate(d.last_order_at) }}</template>
                            <span v-else class="italic">{{ t('pageAdmin.stats.inactiveTable.never') }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <Badge variant="outline" class="text-xs">
                                <template v-if="d.days_since === null">—</template>
                                <template v-else>{{ t('pageAdmin.stats.inactiveTable.daysPlus', { days: d.days_since }) }}</template>
                            </Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </CardContent>
    </Card>
</template>
