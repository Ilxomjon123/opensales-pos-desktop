<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Activity, AlertTriangle, CheckCircle2, RefreshCw, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';

const { t } = useI18n();

type Health = 'healthy' | 'error' | 'backed_up' | 'no_webhook' | 'unknown' | 'disabled';

type DealerRow = {
    id: number;
    name: string;
    is_active: boolean;
    webhook_set_at: string | null;
    webhook_checked_at: string | null;
    webhook_url: string | null;
    webhook_pending_updates: number;
    webhook_last_error_message: string | null;
    webhook_last_error_at: string | null;
    error_age_minutes: number | null;
    health: Health;
};

type Summary = { total: number; healthy: number; error: number; no_webhook: number; disabled: number };

defineProps<{ dealers: DealerRow[]; summary: Summary }>();

const refreshing = ref<Set<number>>(new Set());
const refreshAllBusy = ref(false);

function refreshOne(id: number) {
    refreshing.value.add(id);
    router.post(`/admin/bot-health/${id}/refresh`, {}, {
        preserveScroll: true,
        onFinish: () => refreshing.value.delete(id),
    });
}

function refreshAll() {
    refreshAllBusy.value = true;
    router.post('/admin/bot-health/refresh', {}, {
        preserveScroll: true,
        onFinish: () => {
 refreshAllBusy.value = false; 
},
    });
}

const healthMeta = computed<Record<Health, { label: string; cls: string; icon: any }>>(() => ({
    healthy:    { label: t('pageAdmin.botHealth.health.healthy'),    cls: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300', icon: CheckCircle2 },
    error:      { label: t('pageAdmin.botHealth.health.error'),      cls: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',         icon: XCircle },
    backed_up:  { label: t('pageAdmin.botHealth.health.backedUp'),   cls: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',      icon: AlertTriangle },
    no_webhook: { label: t('pageAdmin.botHealth.health.noWebhook'),  cls: 'bg-neutral-500/15 text-neutral-700 dark:text-neutral-300',icon: AlertTriangle },
    unknown:    { label: t('pageAdmin.botHealth.health.unknown'),    cls: 'bg-neutral-500/15 text-neutral-700 dark:text-neutral-300',icon: Activity },
    disabled:   { label: t('pageAdmin.botHealth.health.disabled'),   cls: 'bg-neutral-500/15 text-neutral-700 dark:text-neutral-300',icon: Activity },
}));

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.botHealth.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Activity class="h-5 w-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.botHealth.title') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.botHealth.subtitle') }}</p>
                </div>
            </div>

            <Button class="w-full sm:w-auto" :disabled="refreshAllBusy" @click="refreshAll">
                <RefreshCw class="mr-2 h-4 w-4" :class="refreshAllBusy ? 'animate-spin' : ''" />
                {{ t('pageAdmin.botHealth.refreshAll') }}
            </Button>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.botHealth.summaryTotal') }}</p>
                    <p class="mt-1 text-2xl font-bold sm:text-3xl">{{ summary.total }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-2">
                        <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                        <p class="text-sm text-muted-foreground">{{ t('pageAdmin.botHealth.summaryHealthy') }}</p>
                    </div>
                    <p class="mt-1 text-2xl font-bold text-emerald-600 sm:text-3xl">{{ summary.healthy }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-2">
                        <XCircle class="h-4 w-4 text-rose-500" />
                        <p class="text-sm text-muted-foreground">{{ t('pageAdmin.botHealth.summaryError') }}</p>
                    </div>
                    <p class="mt-1 text-2xl font-bold text-rose-600 sm:text-3xl">{{ summary.error }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="h-4 w-4 text-amber-500" />
                        <p class="text-sm text-muted-foreground">{{ t('pageAdmin.botHealth.summaryNoWebhook') }}</p>
                    </div>
                    <p class="mt-1 text-2xl font-bold text-amber-600 sm:text-3xl">{{ summary.no_webhook }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Jadval -->
        <Card>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.botHealth.tableDealer') }}</th>
                            <th class="px-4 py-3 font-medium text-center">{{ t('pageAdmin.botHealth.tableStatus') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.botHealth.tableLastCheck') }}</th>
                            <th class="px-4 py-3 font-medium text-center">{{ t('pageAdmin.botHealth.tablePending') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.botHealth.tableLastError') }}</th>
                            <th class="w-24 px-4 py-3 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="d in dealers" :key="d.id" class="hover:bg-muted/20">
                            <td class="px-4 py-3">
                                <button class="text-left font-medium hover:underline" @click="router.get(`/admin/dealers/${d.id}/edit`)">
                                    {{ d.name }}
                                </button>
                                <p v-if="!d.is_active" class="text-[10px] text-muted-foreground">{{ t('pageAdmin.botHealth.disabled') }}</p>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="healthMeta[d.health].cls">
                                    <component :is="healthMeta[d.health].icon" class="h-3 w-3" />
                                    {{ healthMeta[d.health].label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                <template v-if="d.webhook_checked_at">{{ formatDateTime(d.webhook_checked_at) }}</template>
                                <span v-else class="italic">—</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Badge v-if="d.webhook_pending_updates > 0" variant="outline"
                                    :class="d.webhook_pending_updates > 100 ? 'border-amber-500 text-amber-600' : ''"
                                >
                                    {{ d.webhook_pending_updates }}
                                </Badge>
                                <span v-else class="text-xs text-muted-foreground">0</span>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <template v-if="d.webhook_last_error_message">
                                    <p class="line-clamp-1 text-rose-600" :title="d.webhook_last_error_message">
                                        {{ d.webhook_last_error_message }}
                                    </p>
                                    <p v-if="d.webhook_last_error_at" class="text-[10px] text-muted-foreground">
                                        {{ formatDateTime(d.webhook_last_error_at) }}
                                    </p>
                                </template>
                                <span v-else class="text-muted-foreground italic">{{ t('pageAdmin.botHealth.errorEmpty') }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Button size="icon" variant="ghost" :disabled="refreshing.has(d.id)" @click="refreshOne(d.id)">
                                    <RefreshCw class="h-4 w-4" :class="refreshing.has(d.id) ? 'animate-spin' : ''" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
