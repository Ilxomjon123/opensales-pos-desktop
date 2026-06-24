<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Inbox, Phone, Search, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { confirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import { destroy as destroyLead, update as updateLead } from '@/routes/admin/leads';

const { t } = useI18n();

type Lead = {
    id: number;
    name: string;
    phone: string;
    company: string | null;
    message: string | null;
    status: string;
    status_label: string;
    ip: string | null;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    meta: { total: number; last_page: number; current_page: number; per_page: number };
    links: { prev: string | null; next: string | null };
};

type StatusOption = { value: string; label: string };

const props = defineProps<{
    leads: Paginated<Lead>;
    filters: { status?: string; search?: string };
    statuses: StatusOption[];
    totals: { all: number; new: number };
}>();

const filters = ref({
    status: props.filters.status ?? 'all',
    search: props.filters.search ?? '',
});

const expanded = ref<Set<number>>(new Set());

function toggleRow(id: number): void {
    if (expanded.value.has(id)) {
        expanded.value.delete(id);
    } else {
        expanded.value.add(id);
    }
}

function apply(): void {
    router.get(
        '/admin/leads',
        {
            status: filters.value.status === 'all' ? undefined : filters.value.status,
            search: filters.value.search || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function reset(): void {
    filters.value = { status: 'all', search: '' };
    apply();
}

function changeStatus(lead: Lead, status: string): void {
    if (lead.status === status) {
        return;
    }
    router.patch(
        updateLead(lead.id).url,
        { status },
        { preserveScroll: true, preserveState: true },
    );
}

async function remove(lead: Lead): Promise<void> {
    const ok = await confirm({
        title: t('pageAdmin.leads.deleteConfirmTitle'),
        description: t('pageAdmin.leads.deleteConfirmDescription', { name: lead.name, phone: lead.phone }),
        confirmText: t('pageAdmin.leads.deleteConfirmText'),
        variant: 'destructive',
    });
    if (!ok) {
        return;
    }
    router.delete(destroyLead(lead.id).url, { preserveScroll: true });
}

function statusBadgeClass(status: string): string {
    switch (status) {
        case 'new':
            return 'bg-primary/10 text-primary';
        case 'contacted':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'converted':
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        case 'dropped':
            return 'bg-muted text-muted-foreground';
        default:
            return 'bg-muted';
    }
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.leads.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex items-center gap-3">
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10"
            >
                <Inbox class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.leads.title') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('pageAdmin.leads.subtitle') }}
                </p>
            </div>
            <div class="hidden gap-4 sm:flex">
                <div class="text-right">
                    <div class="text-xs text-muted-foreground">{{ t('pageAdmin.leads.totalAll') }}</div>
                    <div class="text-lg font-semibold">{{ totals.all }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-muted-foreground">{{ t('pageAdmin.leads.totalNew') }}</div>
                    <div class="text-lg font-semibold text-primary">{{ totals.new }}</div>
                </div>
            </div>
        </div>

        <Card>
            <CardContent
                class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 lg:grid-cols-[1fr_16rem_auto] lg:items-end"
            >
                <div>
                    <Label class="mb-1 block text-xs">{{ t('pageAdmin.leads.filterSearch') }}</Label>
                    <div class="relative">
                        <Search
                            class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="filters.search"
                            :placeholder="t('pageAdmin.leads.filterSearchPlaceholder')"
                            class="pl-8"
                            @keydown.enter="apply"
                        />
                    </div>
                </div>
                <div>
                    <Label class="mb-1 block text-xs">{{ t('pageAdmin.leads.filterStatus') }}</Label>
                    <Select v-model="filters.status">
                        <SelectTrigger>
                            <SelectValue :placeholder="t('pageAdmin.leads.filterStatusPlaceholder')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('pageAdmin.leads.filterAll') }}</SelectItem>
                            <SelectItem
                                v-for="s in statuses"
                                :key="s.value"
                                :value="s.value"
                            >
                                {{ s.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button class="flex-1 sm:flex-initial" @click="apply">
                        {{ t('pageAdmin.leads.apply') }}
                    </Button>
                    <Button
                        variant="outline"
                        class="flex-1 sm:flex-initial"
                        @click="reset"
                    >
                        {{ t('pageAdmin.leads.reset') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.leads.tableTime') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.leads.tableName') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.leads.tablePhone') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.leads.tableCompany') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.leads.tableStatus') }}</th>
                            <th class="w-10 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <template v-for="lead in leads.data" :key="lead.id">
                            <tr
                                class="cursor-pointer hover:bg-muted/20"
                                @click="toggleRow(lead.id)"
                            >
                                <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                                    {{ formatDateTime(lead.created_at) }}
                                </td>
                                <td class="px-4 py-3 font-medium">{{ lead.name }}</td>
                                <td class="px-4 py-3">
                                    <a
                                        :href="`tel:${lead.phone}`"
                                        class="inline-flex items-center gap-1.5 text-primary hover:underline"
                                        @click.stop
                                    >
                                        <Phone class="size-3.5" />
                                        {{ lead.phone }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ lead.company ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        class="text-xs font-medium"
                                        :class="statusBadgeClass(lead.status)"
                                    >
                                        {{ lead.status_label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-block text-muted-foreground transition-transform"
                                        :class="expanded.has(lead.id) ? 'rotate-90' : ''"
                                    >
                                        ▸
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="expanded.has(lead.id)" class="bg-muted/10">
                                <td colspan="6" class="px-4 py-4">
                                    <div class="grid gap-4 md:grid-cols-[1fr_16rem]">
                                        <div>
                                            <div class="mb-2 text-xs font-medium uppercase text-muted-foreground">
                                                {{ t('pageAdmin.leads.messageHeading') }}
                                            </div>
                                            <div class="rounded-md border bg-background p-3 text-sm">
                                                <span v-if="lead.message" class="whitespace-pre-wrap">{{ lead.message }}</span>
                                                <span v-else class="text-muted-foreground">{{ t('pageAdmin.leads.messageEmpty') }}</span>
                                            </div>
                                            <div v-if="lead.ip" class="mt-2 font-mono text-[10px] text-muted-foreground">
                                                IP: {{ lead.ip }}
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-2">
                                            <div class="text-xs font-medium uppercase text-muted-foreground">
                                                {{ t('pageAdmin.leads.changeStatus') }}
                                            </div>
                                            <Select
                                                :model-value="lead.status"
                                                @update:model-value="changeStatus(lead, String($event))"
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="s in statuses"
                                                        :key="s.value"
                                                        :value="s.value"
                                                    >
                                                        {{ s.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                class="mt-2"
                                                @click="remove(lead)"
                                            >
                                                <Trash2 class="mr-2 size-4" />
                                                {{ t('pageAdmin.leads.delete') }}
                                            </Button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <div
                    v-if="leads.data.length === 0"
                    class="p-12 text-center text-sm text-muted-foreground"
                >
                    {{ t('pageAdmin.leads.empty') }}
                </div>

                <div
                    v-if="leads.meta.last_page > 1"
                    class="flex items-center justify-between border-t px-4 py-3 text-xs"
                >
                    <span class="text-muted-foreground">
                        {{ t('pageAdmin.leads.paginationSummary', { current: leads.meta.current_page, last: leads.meta.last_page, total: leads.meta.total }) }}
                    </span>
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="!leads.links.prev"
                            @click="leads.links.prev && router.get(leads.links.prev)"
                        >
                            {{ t('pageAdmin.leads.previous') }}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="!leads.links.next"
                            @click="leads.links.next && router.get(leads.links.next)"
                        >
                            {{ t('pageAdmin.leads.next') }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
