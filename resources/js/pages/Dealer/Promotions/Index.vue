<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Tag, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';

const { t } = useI18n();

type Promotion = {
    id: number;
    name: string;
    scope: 'all' | 'category' | 'product';
    scope_label: string;
    target_id: number | null;
    discount_percent: number;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
};

type Paginated<T> = {
    data: T[];
    meta: { total: number; last_page: number; current_page: number };
};

defineProps<{ promotions: Paginated<Promotion> }>();

async function destroy(p: Promotion) {
    const ok = await confirm({
        title: t('pageDealer.promotionsIndex.deleteTitle'),
        description: t('pageDealer.promotionsIndex.deleteDesc', { name: p.name }),
        confirmText: t('pageDealer.promotionsIndex.deleteConfirm'),
        variant: 'destructive',
    });

    if (!ok) {
return;
}

    router.delete(`/dealer/promotions/${p.id}`);
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.promotionsIndex.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.promotionsIndex.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ promotions.meta.total }} {{ t('pageDealer.promotionsIndex.countSuffix') }}</p>
            </div>
            <Button class="w-full sm:w-auto" @click="router.get('/dealer/promotions/create')">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('pageDealer.promotionsIndex.newOne') }}
            </Button>
        </div>

        <div v-if="promotions.data.length === 0" class="rounded-xl border border-dashed p-10 text-center">
            <Tag class="mx-auto h-10 w-10 text-muted-foreground/30" />
            <p class="mt-3 text-sm text-muted-foreground">{{ t('pageDealer.promotionsIndex.emptyDesc') }}</p>
        </div>

        <div v-else class="hidden overflow-x-auto rounded-xl border md:block">
            <table class="w-full min-w-[640px] text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('pageDealer.promotionsIndex.tableName') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('pageDealer.promotionsIndex.tableScope') }}</th>
                        <th class="px-4 py-3 font-medium text-right">{{ t('pageDealer.promotionsIndex.tableDiscount') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('pageDealer.promotionsIndex.tablePeriod') }}</th>
                        <th class="px-4 py-3 font-medium text-center">{{ t('pageDealer.promotionsIndex.tableStatus') }}</th>
                        <th class="w-24 px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="p in promotions.data" :key="p.id" class="hover:bg-muted/20">
                        <td class="px-4 py-3 font-medium">{{ p.name }}</td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">{{ p.scope_label }}</td>
                        <td class="px-4 py-3 text-right">
                            <Badge variant="secondary" class="font-mono">−{{ p.discount_percent }}%</Badge>
                        </td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            <template v-if="p.starts_at || p.ends_at">
                                {{ p.starts_at ? formatDateTime(p.starts_at) : '—' }}
                                <span class="mx-1">→</span>
                                {{ p.ends_at ? formatDateTime(p.ends_at) : '∞' }}
                            </template>
                            <template v-else>
                                <span class="italic">{{ t('pageDealer.promotionsIndex.noEnd') }}</span>
                            </template>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <Badge :variant="p.is_active ? 'default' : 'outline'">
                                {{ p.is_active ? t('pageDealer.promotionsIndex.active') : t('pageDealer.promotionsIndex.inactive') }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <Button size="icon" variant="ghost" @click="router.get(`/dealer/promotions/${p.id}/edit`)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button size="icon" variant="ghost" @click="destroy(p)">
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="promotions.data.length > 0" class="flex flex-col divide-y rounded-xl border md:hidden">
            <div v-for="p in promotions.data" :key="`m-${p.id}`" class="flex flex-col gap-1.5 p-3">
                <div class="flex items-start justify-between gap-2">
                    <p class="min-w-0 flex-1 font-medium">{{ p.name }}</p>
                    <Badge variant="secondary" class="shrink-0 font-mono">−{{ p.discount_percent }}%</Badge>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <Badge :variant="p.is_active ? 'default' : 'outline'" class="text-xs">
                        {{ p.is_active ? t('pageDealer.promotionsIndex.active') : t('pageDealer.promotionsIndex.inactive') }}
                    </Badge>
                    <span class="text-xs text-muted-foreground">{{ p.scope_label }}</span>
                </div>
                <p class="text-xs text-muted-foreground">
                    <template v-if="p.starts_at || p.ends_at">
                        {{ p.starts_at ? formatDateTime(p.starts_at) : '—' }}
                        <span class="mx-1">→</span>
                        {{ p.ends_at ? formatDateTime(p.ends_at) : '∞' }}
                    </template>
                    <template v-else>
                        <span class="italic">{{ t('pageDealer.promotionsIndex.noEnd') }}</span>
                    </template>
                </p>
                <div class="flex items-center justify-end gap-0.5 pt-1">
                    <Button size="icon" variant="ghost" class="h-8 w-8" @click="router.get(`/dealer/promotions/${p.id}/edit`)">
                        <Pencil class="h-4 w-4" />
                    </Button>
                    <Button size="icon" variant="ghost" class="h-8 w-8" @click="destroy(p)">
                        <Trash2 class="h-4 w-4 text-destructive" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
