<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle, Megaphone } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AudienceBuilder from '@/components/broadcasts/AudienceBuilder.vue';
import MessageEditor from '@/components/broadcasts/MessageEditor.vue';
import ButtonsEditor from '@/components/broadcasts/ButtonsEditor.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { confirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

type AudienceConfig = Record<string, unknown>;
type Option = { value: string; label: string };
type Shop = { id: number; name: string; phone?: string; region?: string | null; balance?: number };
type Category = { id: number; name: string };

const props = defineProps<{
    options: {
        audience_types: Option[];
        shops: Shop[];
        categories: Category[];
        regions: string[];
        placeholders: string[];
        media_types: Option[];
    };
}>();

const page = usePage();

const form = useForm<{
    message: string;
    media: File | null;
    media_type: string | null;
    buttons: { text: string; url: string }[][];
    audience_type: string;
    audience_config: AudienceConfig;
}>({
    message: '',
    media: null,
    media_type: null,
    buttons: [],
    audience_type: 'all_active',
    audience_config: {},
});

const previewCount = ref<number | null>(null);
const previewLoading = ref(false);
let previewTimer: number | null = null;

function loadPreview() {
    if (previewTimer) {
        clearTimeout(previewTimer);
    }

    previewTimer = window.setTimeout(async () => {
        previewLoading.value = true;

        try {
            const params = new URLSearchParams();
            params.append('audience_type', form.audience_type);
            const cfg = form.audience_config ?? {};

            Object.entries(cfg).forEach(([k, v]) => {
                if (Array.isArray(v)) {
                    v.forEach((val) => params.append(`audience_config[${k}][]`, String(val)));
                } else if (v !== null && v !== undefined && v !== '') {
                    params.append(`audience_config[${k}]`, String(v));
                }
            });

            const res = await fetch(`/dealer/broadcasts/preview?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            previewCount.value = data.count;
        } catch {
            previewCount.value = null;
        }

        previewLoading.value = false;
    }, 400);
}

watch(() => [form.audience_type, JSON.stringify(form.audience_config)], loadPreview, { immediate: true });

const flashStatus = computed(() => (page.props as any).flash?.status ?? null);

async function submit() {
    const ok = await confirm({
        title: t('pageDealer.broadcasts.confirmTitle'),
        description: t('pageDealer.broadcasts.confirmDesc', { count: previewCount.value ?? '?' }),
        confirmText: t('pageDealer.broadcasts.send'),
    });

    if (!ok) {
        return;
    }

    form.post('/dealer/broadcasts', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('message', 'media', 'media_type', 'buttons'),
    });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.broadcasts.headTitle')" />

    <div class="mx-auto flex max-w-3xl flex-col gap-4 p-4 sm:gap-6 md:p-8">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <Megaphone class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.broadcasts.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.broadcasts.subtitle') }}</p>
            </div>
        </div>

        <div
            v-if="flashStatus"
            class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
        >
            ✅ {{ flashStatus }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <AudienceBuilder
                v-model:modelType="form.audience_type"
                v-model:modelConfig="form.audience_config"
                :options="props.options.audience_types"
                :shops="props.options.shops"
                :categories="props.options.categories"
                :regions="props.options.regions"
                :previewCount="previewCount"
                :previewLoading="previewLoading"
            />

            <!-- Xabar: matn + shablon o'zgaruvchilari + media -->
            <MessageEditor
                v-model:modelText="form.message"
                v-model:modelMedia="form.media"
                v-model:modelMediaType="form.media_type"
                :existingMediaUrl="null"
                :existingMediaType="null"
                :placeholders="props.options.placeholders"
                :mediaTypes="props.options.media_types"
                :errors="{ message_text: form.errors.message }"
            />

            <ButtonsEditor v-model="form.buttons" />

            <!-- Preview + action -->
            <div class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-2">
                    <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                    <p class="text-sm">
                        <template v-if="previewLoading">{{ t('pageDealer.broadcasts.calculating') }}</template>
                        <template v-else-if="previewCount !== null">
                            <span class="font-bold">{{ previewCount }}</span> {{ t('pageDealer.broadcasts.willSendTo') }}
                        </template>
                    </p>
                </div>
                <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing || !form.message.trim() || previewCount === 0">
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? t('pageDealer.broadcasts.sending') : t('pageDealer.broadcasts.send') }}
                </Button>
            </div>
        </form>
    </div>
</template>
