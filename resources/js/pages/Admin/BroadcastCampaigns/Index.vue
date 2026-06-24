<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarClock, Pause, Play, Plus, Send, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { confirm } from '@/composables/useConfirm';

type Campaign = {
    id: number;
    title: string;
    is_active: boolean;
    schedule_type: string;
    schedule_config: Record<string, unknown>;
    next_run_at: string | null;
    last_run_at: string | null;
    audience_type: string;
    runs_count: number | null;
};

defineProps<{ campaigns: { data: Campaign[] } }>();

const { t } = useI18n();
const page = usePage();
const flashStatus = computed(() => (page.props as any).flash?.status ?? null);

function formatDt(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('uz-UZ', { dateStyle: 'short', timeStyle: 'short' });
}

function scheduleLabel(c: Campaign): string {
    const cfg = c.schedule_config ?? {};
    const tt = (cfg.times as string[] | undefined)?.join(', ') ?? (cfg.time as string | undefined) ?? '';

    switch (c.schedule_type) {
        case 'once': return t('pageAdmin.broadcastCampaigns.scheduleOnce', { value: cfg.datetime ?? '' });
        case 'daily': return t('pageAdmin.broadcastCampaigns.scheduleDaily', { time: tt });
        case 'weekly': return t('pageAdmin.broadcastCampaigns.scheduleWeekly', { days: (cfg.days as number[] | undefined)?.join(',') ?? '', time: tt });
        case 'monthly': return t('pageAdmin.broadcastCampaigns.scheduleMonthly', { days: (cfg.days as number[] | undefined)?.join(',') ?? '', time: tt });
        default: return c.schedule_type;
    }
}

async function toggle(c: Campaign) {
    router.post(`/admin/broadcast-campaigns/${c.id}/toggle`, {}, { preserveScroll: true });
}

async function runNow(c: Campaign) {
    const ok = await confirm({ title: t('pageAdmin.broadcastCampaigns.runNowConfirmTitle'), description: c.title, confirmText: t('pageAdmin.broadcastCampaigns.runNowConfirm') });
    if (!ok) return;
    router.post(`/admin/broadcast-campaigns/${c.id}/run-now`, {}, { preserveScroll: true });
}

async function destroy(c: Campaign) {
    const ok = await confirm({ title: t('pageAdmin.broadcastCampaigns.deleteConfirmTitle'), description: c.title, confirmText: t('pageAdmin.broadcastCampaigns.deleteConfirm'), destructive: true });
    if (!ok) return;
    router.delete(`/admin/broadcast-campaigns/${c.id}`, { preserveScroll: true });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.broadcastCampaigns.headTitle')" />

    <div class="mx-auto flex max-w-6xl flex-col gap-4 p-4 md:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    <CalendarClock class="h-5 w-5 text-primary" />
                </div>
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.broadcastCampaigns.title') }}</h1>
            </div>
            <Link href="/admin/broadcast-campaigns/create">
                <Button><Plus class="mr-2 h-4 w-4" /> {{ t('pageAdmin.broadcastCampaigns.newCampaign') }}</Button>
            </Link>
        </div>

        <div
            v-if="flashStatus"
            class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
        >
            ✅ {{ flashStatus }}
        </div>

        <Card v-if="campaigns.data.length === 0">
            <CardContent class="py-12 text-center text-sm text-muted-foreground">
                {{ t('pageAdmin.broadcastCampaigns.empty') }}
            </CardContent>
        </Card>

        <div v-else class="grid gap-3">
            <Card v-for="c in campaigns.data" :key="c.id">
                <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link :href="`/admin/broadcast-campaigns/${c.id}`" class="font-medium hover:underline">{{ c.title }}</Link>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="c.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-muted text-muted-foreground'"
                            >
                                {{ c.is_active ? t('pageAdmin.broadcastCampaigns.statusActive') : t('pageAdmin.broadcastCampaigns.statusPaused') }}
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ scheduleLabel(c) }}</p>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1 text-xs text-muted-foreground">
                            <span>{{ t('pageAdmin.broadcastCampaigns.nextRun') }}: <span class="font-mono">{{ formatDt(c.next_run_at) }}</span></span>
                            <span>{{ t('pageAdmin.broadcastCampaigns.lastRun') }}: <span class="font-mono">{{ formatDt(c.last_run_at) }}</span></span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" @click="runNow(c)">
                            <Send class="mr-1 h-3.5 w-3.5" /> {{ t('pageAdmin.broadcastCampaigns.now') }}
                        </Button>
                        <Button type="button" variant="outline" size="sm" @click="toggle(c)">
                            <component :is="c.is_active ? Pause : Play" class="mr-1 h-3.5 w-3.5" />
                            {{ c.is_active ? t('pageAdmin.broadcastCampaigns.pause') : t('pageAdmin.broadcastCampaigns.enable') }}
                        </Button>
                        <Link :href="`/admin/broadcast-campaigns/${c.id}/edit`">
                            <Button type="button" variant="outline" size="sm">{{ t('pageAdmin.broadcastCampaigns.edit') }}</Button>
                        </Link>
                        <Button type="button" variant="ghost" size="sm" @click="destroy(c)">
                            <Trash2 class="h-3.5 w-3.5 text-red-600" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
