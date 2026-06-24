<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import PromotionForm from './PromotionForm.vue';
import type {FormShape} from './PromotionForm.vue';

const { t } = useI18n();

type PromotionPayload = {
    data: {
        id: number;
        name: string;
        scope: 'all' | 'category' | 'product';
        target_id: number | null;
        discount_percent: number;
        starts_at: string | null;
        ends_at: string | null;
        is_active: boolean;
    };
};

const props = defineProps<{
    promotion: PromotionPayload;
    products: { id: number; name: string }[];
    categories: { id: number; name: string }[];
}>();

const p = props.promotion.data;

function toLocal(iso: string | null): string {
    if (!iso) {
return '';
}

    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const form = useForm<FormShape>({
    name: p.name,
    scope: p.scope,
    target_id: p.target_id,
    discount_percent: p.discount_percent,
    starts_at: toLocal(p.starts_at),
    ends_at: toLocal(p.ends_at),
    is_active: p.is_active,
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            starts_at: data.starts_at || null,
            ends_at: data.ends_at || null,
        }))
        .put(`/dealer/promotions/${p.id}`);
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="`${t('pageDealer.promotionsEdit.headPrefix')} ${p.name}`" />

    <div class="mx-auto w-full max-w-5xl p-4 md:p-8">
        <div class="mb-6 flex items-center gap-3 sm:mb-8 sm:gap-4">
            <Button variant="ghost" size="icon" class="shrink-0" @click="router.get('/dealer/promotions')">
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.promotionsEdit.title') }}</h1>
                <p class="truncate text-sm text-muted-foreground">{{ p.name }}</p>
            </div>
        </div>

        <form @submit.prevent="submit">
            <PromotionForm
                v-model="form"
                :errors="(form.errors as Partial<Record<keyof FormShape, string>>)"
                :products="props.products"
                :categories="props.categories"
            />

            <div class="sticky bottom-0 z-10 mt-6 -mx-4 flex items-center justify-end gap-3 border-t bg-background/80 px-4 py-4 backdrop-blur md:-mx-8 md:px-8">
                <Button variant="outline" type="button" @click="router.get('/dealer/promotions')">{{ t('pageDealer.promotionsEdit.cancel') }}</Button>
                <Button type="submit" :disabled="form.processing" class="min-w-[140px]">
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? t('pageDealer.promotionsEdit.saving') : t('pageDealer.promotionsEdit.save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
