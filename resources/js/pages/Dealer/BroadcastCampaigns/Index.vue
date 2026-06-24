<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarClock, Megaphone, Pause, Play, Plus, Send, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { confirm } from '@/composables/useConfirm';

const { t } = useI18n();

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

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};

defineProps<{ campaigns: Paginated<Campaign> }>();

const page = usePage();
const flashStatus = computed(() => (page.props as any).flash?.status ?? null);

function scheduleLabel(c: Campaign): string {
    const cfg = c.schedule_config ?? {};
    const tt = (cfg.times as string[] | undefined)?.join(', ') ?? (cfg.time as string | undefined) ?? '';

    switch (c.schedule_type) {
        case 'once':
            return t('pageDealer.broadcastCampaigns.scheduleOnce', { datetime: cfg.datetime ?? '' });
        case 'daily':
            return t('pageDealer.broadcastCampaigns.scheduleDaily', { times: tt });
        case 'weekly':
            return t('pageDealer.broadcastCampaigns.scheduleWeekly', { days: (cfg.days as number[] | undefined)?.join(',') ?? '', times: tt });
        case 'monthly':
            return t('pageDealer.broadcastCampaigns.scheduleMonthly', { days: (cfg.days as number[] | undefined)?.join(',') ?? '', times: tt });
        default:
            return c.schedule_type;
    }
}

function audienceLabel(type: string): string {
    return {
        all_active: t('pageDealer.broadcastCampaigns.audienceAllActive'),
        selected_shops: t('pageDealer.broadcastCampaigns.audienceSelected'),
        filter: t('pageDealer.broadcastCampaigns.audienceFilter'),
    }[type] ?? type;
}

function formatDt(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('uz-UZ', { dateStyle: 'short', timeStyle: 'short' });
}

async function toggle(c: Campaign) {
    router.post(`/dealer/broadcast-campaigns/${c.id}/toggle`, {}, { preserveScroll: true });
}

async function runNow(c: Campaign) {
    const ok = await confirm({
        title: t('pageDealer.broadcastCampaigns.runNowTitle'),
        description: t('pageDealer.broadcastCampaigns.runNowDesc', { title: c.title }),
        confirmText: t('pageDealer.broadcastCampaigns.runNowConfirm'),
    });

    if (!ok) return;

    router.post(`/dealer/broadcast-campaigns/${c.id}/run-now`, {}, { preserveScroll: true });
}

async function destroy(c: Campaign) {
    const ok = await confirm({
        title: t('pageDealer.broadcastCampaigns.deleteTitle'),
        description: t('pageDealer.broadcastCampaigns.deleteDesc', { title: c.title }),
        confirmText: t('pageDealer.broadcastCampaigns.deleteConfirm'),
        destructive: true,
    });

    if (!ok) return;

    router.delete(`/dealer/broadcast-campaigns/${c.id}`, { preserveScroll: true });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.broadcastCampaigns.headTitle')" />

    <div class="mx-auto flex max-w-6xl flex-col gap-4 p-4 md:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    <CalendarClock class="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.broadcastCampaigns.title') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ t('pageDealer.broadcastCampaigns.subtitle') }}</p>
                </div>
            </div>
            <Link href="/dealer/broadcast-campaigns/create">
                <Button>
                    <Plus class="mr-2 h-4 w-4" /> {{ t('pageDealer.broadcastCampaigns.newCampaign') }}
                </Button>
            </Link>
        </div>

        <div
            v-if="flashStatus"
            class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
        >
            ✅ {{ flashStatus }}
        </div>

        <Card v-if="campaigns.data.length === 0">
            <CardContent class="flex flex-col items-center justify-center gap-3 py-12 text-center">
                <Megaphone class="h-10 w-10 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.broadcastCampaigns.empty') }}</p>
                <Link href="/dealer/broadcast-campaigns/create">
                    <Button>
                        <Plus class="mr-2 h-4 w-4" /> {{ t('pageDealer.broadcastCampaigns.createFirst') }}
                    </Button>
                </Link>
            </CardContent>
        </Card>

        <div v-else class="grid gap-3">
            <Card v-for="c in campaigns.data" :key="c.id">
                <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                :href="`/dealer/broadcast-campaigns/${c.id}`"
                                class="font-medium hover:text-primary hover:underline"
                            >
                                {{ c.title }}
                            </Link>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="c.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-muted text-muted-foreground'"
                            >
                                {{ c.is_active ? t('pageDealer.broadcastCampaigns.statusActive') : t('pageDealer.broadcastCampaigns.statusPaused') }}
                            </span>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ scheduleLabel(c) }}</p>
                        <p class="text-xs text-muted-foreground">{{ audienceLabel(c.audience_type) }}</p>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1 text-xs text-muted-foreground">
                            <span>{{ t('pageDealer.broadcastCampaigns.next') }}: <span class="font-mono">{{ formatDt(c.next_run_at) }}</span></span>
                            <span>{{ t('pageDealer.broadcastCampaigns.last') }}: <span class="font-mono">{{ formatDt(c.last_run_at) }}</span></span>
                            <span v-if="c.runs_count !== null">{{ t('pageDealer.broadcastCampaigns.sent') }}: {{ c.runs_count }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" size="sm" @click="runNow(c)">
                            <Send class="mr-1 h-3.5 w-3.5" /> {{ t('pageDealer.broadcastCampaigns.runNow') }}
                        </Button>
                        <Button type="button" variant="outline" size="sm" @click="toggle(c)">
                            <component :is="c.is_active ? Pause : Play" class="mr-1 h-3.5 w-3.5" />
                            {{ c.is_active ? t('pageDealer.broadcastCampaigns.pause') : t('pageDealer.broadcastCampaigns.enable') }}
                        </Button>
                        <Link :href="`/dealer/broadcast-campaigns/${c.id}/edit`">
                            <Button type="button" variant="outline" size="sm">{{ t('pageDealer.broadcastCampaigns.edit') }}</Button>
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
