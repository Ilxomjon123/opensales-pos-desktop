<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/date';

export type ActivityStatus = 'thriving' | 'active' | 'new' | 'dormant' | 'disabled';

export type DealerActivity = {
    id: number;
    name: string;
    is_active: boolean;
    has_webhook: boolean;
    shops: number;
    orders_30d: number;
    last_order_at: string | null;
    days_since: number | null;
    status: ActivityStatus;
};

defineProps<{
    dealers: DealerActivity[];
}>();

const { t } = useI18n();

const statusMeta = computed<Record<ActivityStatus, { label: string; cls: string; dot: string }>>(() => ({
    thriving: { label: t('pageAdmin.stats.activityTable.statusThriving'), cls: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300', dot: 'bg-emerald-500' },
    active:   { label: t('pageAdmin.stats.activityTable.statusActive'), cls: 'bg-sky-500/15 text-sky-700 dark:text-sky-300', dot: 'bg-sky-500' },
    new:      { label: t('pageAdmin.stats.activityTable.statusNew'), cls: 'bg-violet-500/15 text-violet-700 dark:text-violet-300', dot: 'bg-violet-500' },
    dormant:  { label: t('pageAdmin.stats.activityTable.statusDormant'), cls: 'bg-amber-500/15 text-amber-700 dark:text-amber-300', dot: 'bg-amber-500' },
    disabled: { label: t('pageAdmin.stats.activityTable.statusDisabled'), cls: 'bg-neutral-500/15 text-neutral-700 dark:text-neutral-300', dot: 'bg-neutral-400' },
}));
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('pageAdmin.stats.activityTable.title') }}</CardTitle>
        </CardHeader>
        <CardContent class="overflow-x-auto p-0">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('pageAdmin.stats.activityTable.colDealer') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.stats.activityTable.colShops') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.stats.activityTable.col30d') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('pageAdmin.stats.activityTable.colLastOrder') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.stats.activityTable.colBot') }}</th>
                        <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.stats.activityTable.colStatus') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="d in dealers" :key="d.id" class="hover:bg-muted/20">
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                class="text-left font-medium hover:underline"
                                @click="router.get(`/admin/dealers/${d.id}/edit`)"
                            >
                                {{ d.name }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-center font-mono">{{ d.shops }}</td>
                        <td class="px-4 py-3 text-center">
                            <Badge v-if="d.orders_30d > 0" variant="secondary" class="font-mono">
                                {{ d.orders_30d }}
                            </Badge>
                            <span v-else class="text-muted-foreground">—</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            <template v-if="d.last_order_at">
                                {{ formatDate(d.last_order_at) }}
                                <span class="ml-1 text-[10px]">({{ t('pageAdmin.stats.activityTable.daysAgo', { days: d.days_since }) }})</span>
                            </template>
                            <span v-else class="italic">{{ t('pageAdmin.stats.activityTable.never') }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="d.has_webhook" class="inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                            <span v-else class="inline-flex h-2 w-2 rounded-full bg-rose-500" />
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="statusMeta[d.status].cls"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="statusMeta[d.status].dot" />
                                {{ statusMeta[d.status].label }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </CardContent>
    </Card>
</template>
