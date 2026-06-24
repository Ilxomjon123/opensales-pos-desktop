<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { confirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { currencySymbol } from '@/lib/format';
import type { DealerItem, Paginated } from '@/types';

defineProps<{
    dealers: Paginated<DealerItem>;
    totals: { dealers: number; active: number; self_registered: number };
}>();

const { t } = useI18n();

async function impersonate(dealer: DealerItem) {
    const ok = await confirm({
        title: t('admin.dealers.impersonateTitle'),
        description: t('admin.dealers.impersonateDescription', { name: dealer.name }),
        confirmText: t('admin.dealers.impersonateConfirm'),
    });

    if (!ok) {
        return;
    }

    router.post(`/admin/dealers/${dealer.id}/impersonate`);
}

function toggleActive(dealer: DealerItem) {
    router.patch(`/admin/dealers/${dealer.id}/toggle`, {}, {
        preserveScroll: true,
    });
}

async function deleteDealer(dealer: DealerItem) {
    const ok = await confirm({
        title: t('admin.dealers.deleteTitle'),
        description: t('admin.dealers.deleteDescription', { name: dealer.name }),
        confirmText: t('admin.dealers.deleteConfirm'),
        variant: 'destructive',
    });

    if (!ok) {
        return;
    }

    router.delete(`/admin/dealers/${dealer.id}`);
}

function formatMoney(amount: number | null | undefined): string {
    return String(Math.round(amount ?? 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ' + currencySymbol();
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('admin.dealers.title')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-bold sm:text-2xl">{{ t('admin.dealers.title') }}</h1>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" class="flex-1 sm:flex-initial" @click="router.get('/admin/stats')">{{ t('admin.dealers.stats') }}</Button>
                <Button class="flex-1 sm:flex-initial" @click="router.get('/admin/dealers/create')">{{ t('admin.dealers.newDealer') }}</Button>
            </div>
        </div>

        <!-- Umumiy kartalar -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-xs text-muted-foreground sm:text-sm">{{ t('admin.dealers.totalsTotal') }}</p>
                    <p class="text-2xl font-bold sm:text-3xl">{{ totals.dealers }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-xs text-muted-foreground sm:text-sm">{{ t('admin.dealers.totalsActive') }}</p>
                    <p class="text-2xl font-bold text-green-600 sm:text-3xl">{{ totals.active }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-xs text-muted-foreground sm:text-sm">{{ t('admin.dealers.totalsInactive') }}</p>
                    <p class="text-2xl font-bold text-muted-foreground sm:text-3xl">{{ totals.dealers - totals.active }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-xs text-muted-foreground sm:text-sm">{{ t('admin.dealers.totalsSelfRegistered') }}</p>
                    <p class="text-2xl font-bold text-violet-600 sm:text-3xl dark:text-violet-300">{{ totals.self_registered }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Jadval (md+) -->
        <Card class="hidden md:block">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[840px] text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ t('admin.dealers.tableName') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('admin.dealers.tableBot') }}</th>
                                <th class="px-4 py-3 text-center font-medium">{{ t('admin.dealers.tableShops') }}</th>
                                <th class="px-4 py-3 text-center font-medium">{{ t('admin.dealers.tableOrders') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ t('admin.dealers.tableRevenue') }}</th>
                                <th class="px-4 py-3 text-center font-medium">{{ t('admin.dealers.tableWebhook') }}</th>
                                <th class="px-4 py-3 text-center font-medium">{{ t('admin.dealers.tableStatus') }}</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="d in dealers.data" :key="d.id" class="hover:bg-muted/20">
                                <td class="px-4 py-3 font-medium">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span>{{ d.name }}</span>
                                        <Badge
                                            v-if="d.is_self_registered"
                                            variant="outline"
                                            class="border-violet-500/40 bg-violet-500/10 text-[10px] text-violet-600 dark:text-violet-300"
                                        >
                                            {{ t('admin.dealers.selfRegistered') }}
                                        </Badge>
                                        <Badge
                                            v-if="d.trial_days_left !== null"
                                            variant="outline"
                                            :class="d.trial_expired
                                                ? 'border-destructive/40 bg-destructive/10 text-[10px] text-destructive'
                                                : 'border-amber-500/40 bg-amber-500/10 text-[10px] text-amber-600 dark:text-amber-300'"
                                        >
                                            {{ d.trial_expired
                                                ? t('admin.dealers.trialExpired')
                                                : t('admin.dealers.trialDaysLeft', { days: d.trial_days_left }) }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-sm text-muted-foreground">
                                    @{{ d.bot_username }}
                                </td>
                                <td class="px-4 py-3 text-center">{{ d.shops_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">{{ d.orders_count ?? 0 }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ formatMoney(d.revenue) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <Badge :variant="d.webhook_active ? 'default' : 'outline'" class="text-xs">
                                        {{ d.webhook_active ? t('admin.dealers.webhookActive') : t('admin.dealers.webhookNotSet') }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge
                                        :variant="d.is_active ? 'default' : 'destructive'"
                                        class="cursor-pointer text-xs"
                                        @click="toggleActive(d)"
                                    >
                                        {{ d.is_active ? t('admin.dealers.statusActive') : t('admin.dealers.statusInactive') }}
                                    </Badge>
                                </td>
                                <td class="flex justify-end gap-1 px-4 py-3">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        :title="t('admin.dealers.impersonateTitle')"
                                        @click="impersonate(d)"
                                    >
                                        🎭
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="router.get(`/admin/dealers/${d.id}/edit`)"
                                    >
                                        {{ t('admin.dealers.edit') }}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="deleteDealer(d)"
                                    >
                                        {{ t('admin.dealers.delete') }}
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="dealers.data.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center text-muted-foreground">
                                    {{ t('admin.dealers.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Mobil card ro'yxat -->
        <div class="flex flex-col gap-3 md:hidden">
            <Card v-for="d in dealers.data" :key="`m-${d.id}`">
                <CardContent class="flex flex-col gap-2 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ d.name }}</p>
                            <p class="font-mono text-xs text-muted-foreground">@{{ d.bot_username }}</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Badge
                                    v-if="d.is_self_registered"
                                    variant="outline"
                                    class="border-violet-500/40 bg-violet-500/10 text-[10px] text-violet-600 dark:text-violet-300"
                                >
                                    {{ t('admin.dealers.selfRegistered') }}
                                </Badge>
                                <Badge
                                    v-if="d.trial_days_left !== null"
                                    variant="outline"
                                    :class="d.trial_expired
                                        ? 'border-destructive/40 bg-destructive/10 text-[10px] text-destructive'
                                        : 'border-amber-500/40 bg-amber-500/10 text-[10px] text-amber-600 dark:text-amber-300'"
                                >
                                    {{ d.trial_expired
                                        ? t('admin.dealers.trialExpired')
                                        : t('admin.dealers.trialDaysLeft', { days: d.trial_days_left }) }}
                                </Badge>
                            </div>
                        </div>
                        <Badge
                            :variant="d.is_active ? 'default' : 'destructive'"
                            class="cursor-pointer text-xs"
                            @click="toggleActive(d)"
                        >
                            {{ d.is_active ? t('admin.dealers.statusActive') : t('admin.dealers.statusInactive') }}
                        </Badge>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-md border p-2">
                            <p class="text-muted-foreground">{{ t('admin.dealers.mobileShops') }}</p>
                            <p class="text-base font-bold">{{ d.shops_count ?? 0 }}</p>
                        </div>
                        <div class="rounded-md border p-2">
                            <p class="text-muted-foreground">{{ t('admin.dealers.mobileOrders') }}</p>
                            <p class="text-base font-bold">{{ d.orders_count ?? 0 }}</p>
                        </div>
                        <div class="rounded-md border p-2">
                            <p class="text-muted-foreground">{{ t('admin.dealers.mobileWebhook') }}</p>
                            <Badge :variant="d.webhook_active ? 'default' : 'outline'" class="mt-1 text-[10px]">
                                {{ d.webhook_active ? t('admin.dealers.webhookActive') : '—' }}
                            </Badge>
                        </div>
                    </div>
                    <div class="flex items-center justify-between border-t pt-2 text-sm">
                        <span class="text-muted-foreground">{{ t('admin.dealers.mobileRevenue') }}</span>
                        <span class="font-mono font-semibold">{{ formatMoney(d.revenue) }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1 border-t pt-2">
                        <Button variant="ghost" size="sm" class="flex-1" @click="impersonate(d)">{{ t('admin.dealers.impersonateMobile') }}</Button>
                        <Button variant="ghost" size="sm" class="flex-1" @click="router.get(`/admin/dealers/${d.id}/edit`)">{{ t('admin.dealers.edit') }}</Button>
                        <Button variant="ghost" size="sm" class="flex-1 text-destructive" @click="deleteDealer(d)">{{ t('admin.dealers.delete') }}</Button>
                    </div>
                </CardContent>
            </Card>
            <Card v-if="dealers.data.length === 0">
                <CardContent class="p-8 text-center text-sm text-muted-foreground">
                    {{ t('admin.dealers.empty') }}
                </CardContent>
            </Card>
        </div>
    </div>
</template>
