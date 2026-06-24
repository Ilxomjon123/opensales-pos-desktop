<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BookUser, FileDown, Pencil, Plus, Search, Trash2, Upload } from 'lucide-vue-next';
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
import {
    create as createRoute,
    destroy as destroyEntry,
    edit as editRoute,
    importMethod,
    template as templateRoute,
} from '@/routes/admin/directory';

const { t } = useI18n();

type Entry = {
    id: number;
    name: string;
    legal_name: string | null;
    inn: string | null;
    phone: string | null;
    contact_person: string | null;
    address: string | null;
    landmark: string | null;
    region: string | null;
    district: string | null;
    latitude: number | null;
    longitude: number | null;
    source: string;
    linked_count: number;
    activated_count: number;
};

type Paginated<T> = {
    data: T[];
    meta: { total: number; last_page: number; current_page: number; per_page: number };
    links: { prev: string | null; next: string | null };
};

const props = defineProps<{
    entries: Paginated<Entry>;
    filters: { search?: string | null; source?: string | null };
    totals: { total: number; by_source: Record<string, number>; linked: number; activated: number };
}>();

const filters = ref({
    search: props.filters.search ?? '',
    source: props.filters.source ?? 'all',
});

function apply(): void {
    router.get(
        '/admin/directory',
        {
            search: filters.value.search || undefined,
            source: filters.value.source === 'all' ? undefined : filters.value.source,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function reset(): void {
    filters.value = { search: '', source: 'all' };
    apply();
}

async function remove(entry: Entry): Promise<void> {
    const ok = await confirm({
        title: t('pageAdmin.directory.deleteConfirmTitle'),
        description: t('pageAdmin.directory.deleteConfirmDescription', { name: entry.name }),
        confirmText: t('pageAdmin.directory.delete'),
        variant: 'destructive',
    });
    if (!ok) {
        return;
    }
    router.delete(destroyEntry(entry.id).url, { preserveScroll: true });
}

// --- CSV import ---
const fileInput = ref<HTMLInputElement | null>(null);
const importing = ref(false);

function onFilePicked(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    if (!file) {
        return;
    }
    importing.value = true;
    router.post(
        importMethod().url,
        { file },
        {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                importing.value = false;
                if (fileInput.value) {
                    fileInput.value.value = '';
                }
            },
        },
    );
}

function sourceBadgeClass(source: string): string {
    switch (source) {
        case 'manual':
            return 'bg-primary/10 text-primary';
        case 'backfill':
            return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'shop_sync':
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.directory.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <BookUser class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.directory.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageAdmin.directory.subtitle') }}</p>
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                <input
                    ref="fileInput"
                    type="file"
                    accept=".csv,text/csv"
                    class="hidden"
                    @change="onFilePicked"
                />
                <a :href="templateRoute().url">
                    <Button variant="ghost" size="sm">
                        <FileDown class="mr-2 size-4" />
                        {{ t('pageAdmin.directory.template') }}
                    </Button>
                </a>
                <Button variant="outline" :disabled="importing" @click="fileInput?.click()">
                    <Upload class="mr-2 size-4" />
                    {{ t('pageAdmin.directory.import') }}
                </Button>
                <Button @click="router.get(createRoute().url)">
                    <Plus class="mr-2 size-4" />
                    {{ t('pageAdmin.directory.add') }}
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <div class="text-xs text-muted-foreground">{{ t('pageAdmin.directory.statTotal') }}</div>
                    <div class="text-2xl font-bold">{{ totals.total }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="text-xs text-muted-foreground">{{ t('pageAdmin.directory.statLinked') }}</div>
                    <div class="text-2xl font-bold">{{ totals.linked }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="text-xs text-muted-foreground">{{ t('pageAdmin.directory.statActivated') }}</div>
                    <div class="text-2xl font-bold text-emerald-600">{{ totals.activated }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="text-xs text-muted-foreground">{{ t('pageAdmin.directory.statConversion') }}</div>
                    <div class="text-2xl font-bold">
                        {{ totals.total > 0 ? Math.round((totals.activated / totals.total) * 100) : 0 }}%
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 lg:grid-cols-[1fr_16rem_auto] lg:items-end">
                <div>
                    <Label class="mb-1 block text-xs">{{ t('pageAdmin.directory.filterSearch') }}</Label>
                    <div class="relative">
                        <Search class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="filters.search"
                            :placeholder="t('pageAdmin.directory.filterSearchPlaceholder')"
                            class="pl-8"
                            @keydown.enter="apply"
                        />
                    </div>
                </div>
                <div>
                    <Label class="mb-1 block text-xs">{{ t('pageAdmin.directory.filterSource') }}</Label>
                    <Select v-model="filters.source">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('pageAdmin.directory.filterAll') }}</SelectItem>
                            <SelectItem value="manual">{{ t('pageAdmin.directory.sourceManual') }}</SelectItem>
                            <SelectItem value="backfill">{{ t('pageAdmin.directory.sourceBackfill') }}</SelectItem>
                            <SelectItem value="shop_sync">{{ t('pageAdmin.directory.sourceShopSync') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button class="flex-1 sm:flex-initial" @click="apply">{{ t('pageAdmin.directory.apply') }}</Button>
                    <Button variant="outline" class="flex-1 sm:flex-initial" @click="reset">{{ t('pageAdmin.directory.reset') }}</Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.directory.tableName') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.directory.tableInn') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.directory.tablePhone') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.directory.tableRegion') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.directory.tableSource') }}</th>
                            <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.directory.tableLinked') }}</th>
                            <th class="px-4 py-3 text-center font-medium">{{ t('pageAdmin.directory.tableActivated') }}</th>
                            <th class="w-24 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-muted/20">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ entry.name }}</div>
                                <div v-if="entry.legal_name" class="text-xs text-muted-foreground">{{ entry.legal_name }}</div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs">{{ entry.inn ?? '—' }}</td>
                            <td class="px-4 py-3">{{ entry.phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ [entry.region, entry.district].filter(Boolean).join(', ') || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge class="text-xs font-medium" :class="sourceBadgeClass(entry.source)">{{ entry.source }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-center">{{ entry.linked_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="entry.activated_count > 0 ? 'font-semibold text-emerald-600' : 'text-muted-foreground'">
                                    {{ entry.activated_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" @click="router.get(editRoute(entry.id).url)">
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" @click="remove(entry)">
                                        <Trash2 class="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="entries.data.length === 0" class="p-12 text-center text-sm text-muted-foreground">
                    {{ t('pageAdmin.directory.empty') }}
                </div>

                <div v-if="entries.meta.last_page > 1" class="flex items-center justify-between border-t px-4 py-3 text-xs">
                    <span class="text-muted-foreground">
                        {{ t('pageAdmin.directory.paginationSummary', { current: entries.meta.current_page, last: entries.meta.last_page, total: entries.meta.total }) }}
                    </span>
                    <div class="flex gap-2">
                        <Button size="sm" variant="outline" :disabled="!entries.links.prev" @click="entries.links.prev && router.get(entries.links.prev)">
                            {{ t('pageAdmin.directory.previous') }}
                        </Button>
                        <Button size="sm" variant="outline" :disabled="!entries.links.next" @click="entries.links.next && router.get(entries.links.next)">
                            {{ t('pageAdmin.directory.next') }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
