<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { FolderTree, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter,
    DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { confirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

type Category = {
    id: number;
    name: string;
    sort_order: number;
    is_active: boolean;
    products_count: number;
};

type Paginated<T> = {
    data: T[];
    meta: { total: number; last_page: number; current_page: number };
};

defineProps<{ categories: Paginated<Category> }>();

const { t } = useI18n();
const page = usePage();
const canEdit = computed(() => ['dealer', 'warehouse'].includes(page.props.auth?.role ?? ''));

const dialogOpen = ref(false);
const editing = ref<Category | null>(null);

const form = useForm({
    name: '',
    sort_order: 0,
    is_active: true,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.sort_order = 0;
    form.is_active = true;
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(c: Category) {
    editing.value = c;
    form.name = c.name;
    form.sort_order = c.sort_order;
    form.is_active = c.is_active;
    form.clearErrors();
    dialogOpen.value = true;
}

function submit() {
    if (editing.value) {
        form.put(`/dealer/categories/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
 dialogOpen.value = false; 
},
        });
    } else {
        form.post('/dealer/categories', {
            preserveScroll: true,
            onSuccess: () => {
 dialogOpen.value = false; 
},
        });
    }
}

async function destroy(c: Category) {
    const ok = await confirm({
        title: t('pageDealer.categories.deleteTitle'),
        description: c.products_count > 0
            ? t('pageDealer.categories.deleteDescWith', { name: c.name, count: c.products_count })
            : t('pageDealer.categories.deleteDescPlain', { name: c.name }),
        confirmText: t('pageDealer.categories.deleteConfirm'),
        variant: 'destructive',
    });

    if (!ok) {
return;
}

    router.delete(`/dealer/categories/${c.id}`, { preserveScroll: true });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.categories.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <FolderTree class="h-5 w-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.categories.title') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ categories.meta.total }} {{ t('pageDealer.categories.countSuffix') }}</p>
                </div>
            </div>
            <Button v-if="canEdit" class="w-full sm:w-auto" @click="openCreate">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('pageDealer.categories.newCategory') }}
            </Button>
        </div>

        <Card v-if="categories.data.length === 0" class="border-dashed">
            <CardContent class="py-12 text-center">
                <FolderTree class="mx-auto h-10 w-10 text-muted-foreground/30" />
                <p class="mt-3 font-medium">{{ t('pageDealer.categories.emptyTitle') }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('pageDealer.categories.emptyDesc') }}
                </p>
                <Button v-if="canEdit" class="mt-4" @click="openCreate">
                    <Plus class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.categories.createFirst') }}
                </Button>
            </CardContent>
        </Card>

        <Card v-else class="hidden md:block">
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full min-w-[480px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="w-14 px-4 py-3 font-medium text-center">#</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageDealer.categories.tableName') }}</th>
                            <th class="px-4 py-3 font-medium text-center">{{ t('pageDealer.categories.tableProducts') }}</th>
                            <th class="px-4 py-3 font-medium text-center">{{ t('pageDealer.categories.tableStatus') }}</th>
                            <th v-if="canEdit" class="w-28 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="c in categories.data" :key="c.id" class="hover:bg-muted/20">
                            <td class="px-4 py-3 text-center font-mono text-xs text-muted-foreground">{{ c.sort_order }}</td>
                            <td class="px-4 py-3 font-medium">{{ c.name }}</td>
                            <td class="px-4 py-3 text-center">
                                <Badge variant="secondary" class="font-mono">{{ c.products_count }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Badge :variant="c.is_active ? 'default' : 'outline'">
                                    {{ c.is_active ? t('pageDealer.categories.active') : t('pageDealer.categories.inactive') }}
                                </Badge>
                            </td>
                            <td v-if="canEdit" class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <Button size="icon" variant="ghost" @click="openEdit(c)">
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button size="icon" variant="ghost" @click="destroy(c)">
                                        <Trash2 class="h-4 w-4 text-destructive" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <div v-if="categories.data.length > 0" class="grid gap-2 md:hidden">
            <Card v-for="c in categories.data" :key="`m-${c.id}`">
                <CardContent class="flex items-center gap-3 p-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted font-mono text-xs text-muted-foreground">
                        {{ c.sort_order }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ c.name }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <Badge variant="secondary" class="font-mono text-xs">{{ c.products_count }} {{ t('pageDealer.categories.productsLabel') }}</Badge>
                            <Badge :variant="c.is_active ? 'default' : 'outline'" class="text-xs">
                                {{ c.is_active ? t('pageDealer.categories.active') : t('pageDealer.categories.inactive') }}
                            </Badge>
                        </div>
                    </div>
                    <div v-if="canEdit" class="flex shrink-0 items-center gap-0.5">
                        <Button size="icon" variant="ghost" class="h-8 w-8" @click="openEdit(c)">
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button size="icon" variant="ghost" class="h-8 w-8" @click="destroy(c)">
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? t('pageDealer.categories.dialogEditTitle') : t('pageDealer.categories.dialogCreateTitle') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ t('pageDealer.categories.dialogDesc') }}
                    </DialogDescription>
                </DialogHeader>

                <form id="categoryForm" @submit.prevent="submit" class="space-y-4">
                    <div>
                        <Label for="name" class="mb-1.5">{{ t('pageDealer.categories.name') }} <span class="text-destructive">*</span></Label>
                        <Input id="name" v-model="form.name" :placeholder="t('pageDealer.categories.namePlaceholder')" required autofocus />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div>
                        <Label for="sort_order" class="mb-1.5">{{ t('pageDealer.categories.sortOrder') }}</Label>
                        <Input id="sort_order" type="number" v-model.number="form.sort_order" min="0" />
                        <p class="mt-1 text-xs text-muted-foreground">{{ t('pageDealer.categories.sortOrderHint') }}</p>
                        <InputError :message="form.errors.sort_order" />
                    </div>

                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="h-4 w-4 rounded border-input"
                        />
                        <span class="text-sm">{{ t('pageDealer.categories.isActive') }}</span>
                    </label>
                </form>

                <DialogFooter>
                    <Button variant="outline" type="button" @click="dialogOpen = false">{{ t('pageDealer.common.cancel') }}</Button>
                    <Button type="submit" form="categoryForm" :disabled="form.processing">
                        <Spinner v-if="form.processing" class="mr-2" />
                        {{ editing ? t('pageDealer.categories.save') : t('pageDealer.categories.create') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
