<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';

const { t } = useI18n();

type Actor = { id?: number; name: string; username?: string };
type LogItem = {
    id: number;
    action: string;
    actor: Actor;
    subject_type: string | null;
    subject_id: number | null;
    changes: Record<string, any> | null;
    ip: string | null;
    created_at: string;
};

type Paginated<T> = {
    data: T[];
    meta: { total: number; last_page: number; current_page: number };
    links: { prev: string | null; next: string | null };
};

const props = defineProps<{
    logs: Paginated<LogItem>;
    actions: string[];
    filters: { action?: string; user_id?: string; date_from?: string; date_to?: string };
}>();

const filters = ref({ ...props.filters });
const expanded = ref<Set<number>>(new Set());

const actionKeys: Record<string, string> = {
    'dealer.created': 'dealerCreated',
    'dealer.deleted': 'dealerDeleted',
    'dealer.toggled': 'dealerToggled',
    'dealer.fee_rate.updated': 'dealerFeeRateUpdated',
    'dealer.commission.updated': 'dealerCommissionUpdated',
    'platform_payment.created': 'platformPaymentCreated',
    'platform_payment.deleted': 'platformPaymentDeleted',
    'bot_health.refresh': 'botHealthRefresh',
    'bot_health.refresh_all': 'botHealthRefreshAll',
    'impersonate.start': 'impersonateStart',
    'impersonate.stop': 'impersonateStop',
    'platform_broadcast.sent': 'platformBroadcastSent',
};

function actionLabel(code: string): string {
    const key = actionKeys[code];
    return key ? t(`pageAdmin.audit.actions.${key}`) : code;
}

function subjectLabel(type: string | null): string {
    if (!type) {
return '';
}

    const key = `pageAdmin.audit.subjects.${type}`;
    const label = t(key);

    return label === key ? type : label;
}

const actionItems = computed(() =>
    props.actions.map((a) => ({ value: a, label: actionLabel(a) })),
);

function apply() {
    router.get('/admin/audit-log', filters.value as Record<string, string>, {
        preserveState: true,
        preserveScroll: true,
    });
}

function reset() {
    filters.value = {};
    apply();
}

function toggle(id: number) {
    if (expanded.value.has(id)) {
expanded.value.delete(id);
} else {
expanded.value.add(id);
}
}

const actionMeta: Record<string, string> = {
    'dealer.created':          'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    'dealer.deleted':          'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    'dealer.toggled':          'bg-amber-500/10 text-amber-700 dark:text-amber-300',
    'dealer.fee_rate.updated': 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
    'dealer.commission.updated': 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
    'platform_payment.created':'bg-violet-500/10 text-violet-700 dark:text-violet-300',
    'platform_payment.deleted':'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    'bot_health.refresh':      'bg-neutral-500/10 text-neutral-700 dark:text-neutral-300',
    'bot_health.refresh_all':  'bg-neutral-500/10 text-neutral-700 dark:text-neutral-300',
    'impersonate.start':       'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
    'impersonate.stop':        'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300',
    'platform_broadcast.sent': 'bg-fuchsia-500/10 text-fuchsia-700 dark:text-fuchsia-300',
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.audit.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <ClipboardList class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.audit.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageAdmin.audit.subtitle') }}</p>
            </div>
        </div>

        <!-- Filtr -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 lg:grid-cols-[16rem_1fr_1fr_auto] lg:items-end">
                <div>
                    <Label class="mb-1 block text-xs">{{ t('pageAdmin.audit.filterAction') }}</Label>
                    <SearchableSelect
                        v-model="filters.action"
                        :items="actionItems"
                        :placeholder="t('pageAdmin.audit.filterActionPlaceholder')"
                        :search-placeholder="t('pageAdmin.audit.filterActionSearchPlaceholder')"
                        :empty-text="t('pageAdmin.audit.filterEmpty')"
                    />
                </div>
                <div>
                    <Label class="mb-1 block text-xs">{{ t('pageAdmin.audit.filterDateFrom') }}</Label>
                    <Input type="date" v-model="filters.date_from" />
                </div>
                <div>
                    <Label class="mb-1 block text-xs">{{ t('pageAdmin.audit.filterDateTo') }}</Label>
                    <Input type="date" v-model="filters.date_to" />
                </div>
                <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-1">
                    <Button class="flex-1 sm:flex-initial" @click="apply">{{ t('pageAdmin.audit.apply') }}</Button>
                    <Button variant="outline" class="flex-1 sm:flex-initial" @click="reset">{{ t('pageAdmin.audit.reset') }}</Button>
                </div>
            </CardContent>
        </Card>

        <!-- Jadval -->
        <Card>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.audit.tableTime') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.audit.tableAction') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.audit.tableActor') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.audit.tableSubject') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.audit.tableIp') }}</th>
                            <th class="w-10 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <template v-for="log in logs.data" :key="log.id">
                            <tr
                                class="cursor-pointer hover:bg-muted/20"
                                @click="toggle(log.id)"
                            >
                                <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ formatDateTime(log.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <Badge
                                        class="text-xs font-medium"
                                        :class="actionMeta[log.action] ?? 'bg-muted'"
                                        :title="log.action"
                                    >
                                        {{ actionLabel(log.action) }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <User class="h-3.5 w-3.5 text-muted-foreground" />
                                        <div>
                                            <p class="font-medium">{{ log.actor.name }}</p>
                                            <p v-if="log.actor.username" class="text-[10px] text-muted-foreground">@{{ log.actor.username }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">
                                    <template v-if="log.subject_type">
                                        {{ subjectLabel(log.subject_type) }}<template v-if="log.subject_id"> #{{ log.subject_id }}</template>
                                    </template>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-[10px] text-muted-foreground">{{ log.ip ?? '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-block text-muted-foreground transition-transform" :class="expanded.has(log.id) ? 'rotate-90' : ''">▸</span>
                                </td>
                            </tr>
                            <tr v-if="expanded.has(log.id) && log.changes" class="bg-muted/10">
                                <td colspan="6" class="px-4 py-3">
                                    <pre class="overflow-x-auto rounded-md bg-background p-3 text-[11px] font-mono">{{ JSON.stringify(log.changes, null, 2) }}</pre>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div v-if="logs.data.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                    {{ t('pageAdmin.audit.empty') }}
                </div>

                <!-- Pagination -->
                <div v-if="logs.meta.last_page > 1" class="flex items-center justify-between border-t px-4 py-3 text-xs">
                    <span class="text-muted-foreground">
                        {{ t('pageAdmin.audit.paginationSummary', { current: logs.meta.current_page, last: logs.meta.last_page, total: logs.meta.total }) }}
                    </span>
                    <div class="flex gap-2">
                        <Button size="sm" variant="outline" :disabled="!logs.links.prev" @click="logs.links.prev && router.get(logs.links.prev)">
                            {{ t('pageAdmin.audit.previous') }}
                        </Button>
                        <Button size="sm" variant="outline" :disabled="!logs.links.next" @click="logs.links.next && router.get(logs.links.next)">
                            {{ t('pageAdmin.audit.next') }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
