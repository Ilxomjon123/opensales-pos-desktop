<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Clock, Pause, Pencil, Play, Send, XCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { confirm } from '@/composables/useConfirm';

const { t } = useI18n();

type Run = {
    id: number;
    scheduled_for: string | null;
    started_at: string | null;
    completed_at: string | null;
    total_recipients: number;
    success_count: number;
    failed_count: number;
    status: string;
};

type Campaign = {
    id: number;
    title: string;
    message_text: string;
    media_url: string | null;
    media_type: string | null;
    buttons: { text: string; url: string }[][] | null;
    audience_type: string;
    schedule_type: string;
    schedule_config: Record<string, unknown>;
    timezone: string;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    last_run_at: string | null;
    next_run_at: string | null;
    runs: Run[];
};

const props = defineProps<{ campaign: Campaign }>();

function formatDt(iso: string | null): string {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('uz-UZ', { dateStyle: 'short', timeStyle: 'short' });
}

function scheduleLabel(): string {
    const cfg = props.campaign.schedule_config ?? {};
    const tt = (cfg.times as string[] | undefined)?.join(', ') ?? (cfg.time as string | undefined) ?? '';

    switch (props.campaign.schedule_type) {
        case 'once': return t('pageDealer.broadcastCampaigns.scheduleOnce', { datetime: cfg.datetime ?? '' });
        case 'daily': return t('pageDealer.broadcastCampaigns.scheduleDaily', { times: tt });
        case 'weekly': return t('pageDealer.broadcastCampaigns.scheduleWeekly', { days: (cfg.days as number[] | undefined)?.join(',') ?? '', times: tt });
        case 'monthly': return t('pageDealer.broadcastCampaigns.scheduleMonthly', { days: (cfg.days as number[] | undefined)?.join(',') ?? '', times: tt });
        default: return props.campaign.schedule_type;
    }
}

async function toggle() {
    router.post(`/dealer/broadcast-campaigns/${props.campaign.id}/toggle`, {}, { preserveScroll: true });
}

async function runNow() {
    const ok = await confirm({
        title: t('pageDealer.broadcastCampaigns.runNowTitle'),
        description: t('pageDealer.broadcastCampaigns.runNowDescShort', { title: props.campaign.title }),
        confirmText: t('pageDealer.broadcastCampaigns.runNowConfirm'),
    });

    if (!ok) return;
    router.post(`/dealer/broadcast-campaigns/${props.campaign.id}/run-now`, {}, { preserveScroll: true });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="campaign.title" />

    <div class="mx-auto flex max-w-4xl flex-col gap-4 p-4 md:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="sm" @click="router.visit('/dealer/broadcast-campaigns')">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ campaign.title }}</h1>
                    <p class="text-sm text-muted-foreground">{{ scheduleLabel() }}</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" @click="runNow">
                    <Send class="mr-1 h-3.5 w-3.5" /> {{ t('pageDealer.broadcastCampaigns.runNow') }}
                </Button>
                <Button variant="outline" size="sm" @click="toggle">
                    <component :is="campaign.is_active ? Pause : Play" class="mr-1 h-3.5 w-3.5" />
                    {{ campaign.is_active ? t('pageDealer.broadcastCampaigns.pause') : t('pageDealer.broadcastCampaigns.enable') }}
                </Button>
                <Link :href="`/dealer/broadcast-campaigns/${campaign.id}/edit`">
                    <Button variant="outline" size="sm"><Pencil class="mr-1 h-3.5 w-3.5" /> {{ t('pageDealer.broadcastCampaigns.edit') }}</Button>
                </Link>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('pageDealer.broadcastCampaigns.messageContent') }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <pre class="whitespace-pre-wrap rounded-md bg-muted/30 p-3 text-sm">{{ campaign.message_text }}</pre>

                <div v-if="campaign.media_url">
                    <img
                        v-if="campaign.media_type === 'photo'"
                        :src="campaign.media_url"
                        class="max-h-64 rounded-md border"
                        alt=""
                    />
                    <a v-else :href="campaign.media_url" target="_blank" class="text-sm text-primary hover:underline">
                        {{ campaign.media_url }}
                    </a>
                </div>

                <div v-if="campaign.buttons?.length" class="space-y-1">
                    <div v-for="(row, ri) in campaign.buttons" :key="ri" class="flex gap-1.5">
                        <span
                            v-for="(btn, bi) in row"
                            :key="bi"
                            class="rounded-md border bg-muted/40 px-3 py-1.5 text-xs"
                        >
                            {{ btn.text }} →
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('pageDealer.broadcastCampaigns.statusTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.broadcastCampaigns.statusTitle') }}</p>
                    <p class="font-medium">{{ campaign.is_active ? t('pageDealer.broadcastCampaigns.statusActive') : t('pageDealer.broadcastCampaigns.statusPaused') }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.broadcastCampaigns.nextRun') }}</p>
                    <p class="font-mono text-sm">{{ formatDt(campaign.next_run_at) }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.broadcastCampaigns.lastRun') }}</p>
                    <p class="font-mono text-sm">{{ formatDt(campaign.last_run_at) }}</p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{ t('pageDealer.broadcastCampaigns.historyTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">{{ t('pageDealer.broadcastCampaigns.colDate') }}</th>
                            <th class="px-3 py-2">{{ t('pageDealer.broadcastCampaigns.colTotal') }}</th>
                            <th class="px-3 py-2">{{ t('pageDealer.broadcastCampaigns.colSuccess') }}</th>
                            <th class="px-3 py-2">{{ t('pageDealer.broadcastCampaigns.colFailed') }}</th>
                            <th class="px-3 py-2">{{ t('pageDealer.broadcastCampaigns.colStatus') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in campaign.runs" :key="r.id" class="border-b last:border-b-0">
                            <td class="px-3 py-2 font-mono text-xs">{{ formatDt(r.scheduled_for) }}</td>
                            <td class="px-3 py-2">{{ r.total_recipients }}</td>
                            <td class="px-3 py-2 text-emerald-700">{{ r.success_count }}</td>
                            <td class="px-3 py-2 text-red-600">{{ r.failed_count }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex items-center gap-1">
                                    <CheckCircle2 v-if="r.status === 'completed'" class="h-3.5 w-3.5 text-emerald-600" />
                                    <XCircle v-else-if="r.status === 'failed'" class="h-3.5 w-3.5 text-red-600" />
                                    <Clock v-else class="h-3.5 w-3.5 text-amber-500" />
                                    {{ r.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="campaign.runs.length === 0">
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-muted-foreground">
                                {{ t('pageDealer.broadcastCampaigns.noRuns') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
