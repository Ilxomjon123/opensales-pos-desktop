<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Clock, Pause, Pencil, Play, Send, XCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { confirm } from '@/composables/useConfirm';

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
    schedule_type: string;
    schedule_config: Record<string, unknown>;
    is_active: boolean;
    last_run_at: string | null;
    next_run_at: string | null;
    runs: Run[];
};

const props = defineProps<{ campaign: Campaign }>();

const { t } = useI18n();

function formatDt(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('uz-UZ', { dateStyle: 'short', timeStyle: 'short' });
}

async function toggle() {
    router.post(`/admin/broadcast-campaigns/${props.campaign.id}/toggle`, {}, { preserveScroll: true });
}

async function runNow() {
    const ok = await confirm({ title: t('pageAdmin.broadcastCampaignsShow.runNowConfirmTitle'), description: props.campaign.title, confirmText: t('pageAdmin.broadcastCampaignsShow.runNowConfirm') });
    if (!ok) return;
    router.post(`/admin/broadcast-campaigns/${props.campaign.id}/run-now`, {}, { preserveScroll: true });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="campaign.title" />

    <div class="mx-auto flex max-w-4xl flex-col gap-4 p-4 md:p-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="sm" @click="router.visit('/admin/broadcast-campaigns')">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ campaign.title }}</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" @click="runNow"><Send class="mr-1 h-3.5 w-3.5" /> {{ t('pageAdmin.broadcastCampaignsShow.now') }}</Button>
                <Button variant="outline" size="sm" @click="toggle">
                    <component :is="campaign.is_active ? Pause : Play" class="mr-1 h-3.5 w-3.5" />
                    {{ campaign.is_active ? t('pageAdmin.broadcastCampaignsShow.pause') : t('pageAdmin.broadcastCampaignsShow.enable') }}
                </Button>
                <Link :href="`/admin/broadcast-campaigns/${campaign.id}/edit`">
                    <Button variant="outline" size="sm"><Pencil class="mr-1 h-3.5 w-3.5" /> {{ t('pageAdmin.broadcastCampaignsShow.edit') }}</Button>
                </Link>
            </div>
        </div>

        <Card>
            <CardHeader><CardTitle class="text-base">{{ t('pageAdmin.broadcastCampaignsShow.message') }}</CardTitle></CardHeader>
            <CardContent>
                <pre class="whitespace-pre-wrap rounded-md bg-muted/30 p-3 text-sm">{{ campaign.message_text }}</pre>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle class="text-base">{{ t('pageAdmin.broadcastCampaignsShow.historyTitle') }}</CardTitle></CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2">{{ t('pageAdmin.broadcastCampaignsShow.colDate') }}</th>
                            <th class="px-3 py-2">{{ t('pageAdmin.broadcastCampaignsShow.colTotal') }}</th>
                            <th class="px-3 py-2">{{ t('pageAdmin.broadcastCampaignsShow.colOk') }}</th>
                            <th class="px-3 py-2">{{ t('pageAdmin.broadcastCampaignsShow.colError') }}</th>
                            <th class="px-3 py-2">{{ t('pageAdmin.broadcastCampaignsShow.colStatus') }}</th>
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
                                {{ t('pageAdmin.broadcastCampaignsShow.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>
</template>
