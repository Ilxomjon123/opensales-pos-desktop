<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AtSign, Plus, Pencil, Trash2, Truck, Phone } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { confirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

type Deliveryman = {
    id: number;
    name: string;
    username: string;
    phone: string | null;
    shops_count?: number;
    created_at?: string;
};

const props = defineProps<{
    deliverymen: { data: Deliveryman[] };
}>();

async function del(d: Deliveryman) {
    const ok = await confirm({
        title: t('pageDealer.deliverymen.deleteTitle'),
        description: t('pageDealer.deliverymen.deleteDesc', { name: d.name }),
        confirmText: t('pageDealer.deliverymen.deleteConfirm'),
        variant: 'destructive',
    });

    if (!ok) {
return;
}

    router.delete(`/dealer/deliverymen/${d.id}`);
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.deliverymen.indexHead')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.deliverymen.indexTitle') }}</h1>
                <p class="text-sm text-muted-foreground">{{ props.deliverymen.data.length }} {{ t('pageDealer.deliverymen.countSuffix') }}</p>
            </div>
            <Button class="w-full sm:w-auto" @click="router.get('/dealer/deliverymen/create')">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('pageDealer.deliverymen.newOne') }}
            </Button>
        </div>

        <div v-if="props.deliverymen.data.length > 0" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="d in props.deliverymen.data" :key="d.id" class="transition-shadow hover:shadow-md">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-primary/10 p-2 text-primary">
                                <Truck class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="font-semibold">{{ d.name }}</p>
                                <p class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <AtSign class="h-3 w-3" />
                                    {{ d.username }}
                                </p>
                                <p v-if="d.phone" class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <Phone class="h-3 w-3" />
                                    {{ d.phone }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ d.shops_count ?? 0 }} {{ t('pageDealer.deliverymen.shopsCount') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <Button variant="ghost" size="icon" class="h-8 w-8" @click="router.get(`/dealer/deliverymen/${d.id}/edit`)">
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive" @click="del(d)">
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed py-16">
            <Truck class="h-12 w-12 text-muted-foreground/40" />
            <div class="text-center">
                <p class="font-medium">{{ t('pageDealer.deliverymen.emptyTitle') }}</p>
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.deliverymen.emptyDesc') }}</p>
            </div>
            <Button @click="router.get('/dealer/deliverymen/create')">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('pageDealer.deliverymen.newOne') }}
            </Button>
        </div>
    </div>
</template>
