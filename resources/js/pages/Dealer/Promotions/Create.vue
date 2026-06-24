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

const props = defineProps<{
    products: { id: number; name: string }[];
    categories: { id: number; name: string }[];
}>();

const form = useForm<FormShape>({
    name: '',
    scope: 'all',
    target_id: null,
    discount_percent: null,
    starts_at: '',
    ends_at: '',
    is_active: true,
});

function submit() {
    form
        .transform((data) => ({
            ...data,
            starts_at: data.starts_at || null,
            ends_at: data.ends_at || null,
        }))
        .post('/dealer/promotions');
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.promotionsCreate.headTitle')" />

    <div class="mx-auto w-full max-w-5xl p-4 md:p-8">
        <div class="mb-6 flex items-center gap-3 sm:mb-8 sm:gap-4">
            <Button variant="ghost" size="icon" class="shrink-0" @click="router.get('/dealer/promotions')">
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.promotionsCreate.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.promotionsCreate.subtitle') }}</p>
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
                <Button variant="outline" type="button" @click="router.get('/dealer/promotions')">{{ t('pageDealer.promotionsCreate.cancel') }}</Button>
                <Button type="submit" :disabled="form.processing" class="min-w-[140px]">
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? t('pageDealer.promotionsCreate.creating') : t('pageDealer.promotionsCreate.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
