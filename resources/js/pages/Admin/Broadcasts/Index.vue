<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { AlertCircle, Megaphone } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AudienceBuilder from '@/components/broadcasts/AudienceBuilder.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { confirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

type AudienceConfig = Record<string, unknown>;
type Option = { value: string; label: string };
type Dealer = { id: number; name: string };
type Category = { id: number; name: string };

const props = defineProps<{
    options: {
        audience_types: Option[];
        dealers: Dealer[];
        shops: never[];
        categories: Category[];
        regions: string[];
    };
}>();

const page = usePage();

const form = useForm<{ message: string; audience_type: string; audience_config: AudienceConfig }>({
    message: '',
    audience_type: 'platform_dealers',
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

            const res = await fetch(`/admin/broadcasts/preview?${params.toString()}`, {
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
        title: t('pageAdmin.broadcasts.confirmTitle'),
        description: t('pageAdmin.broadcasts.confirmDescription', { count: previewCount.value ?? '?' }),
        confirmText: t('pageAdmin.broadcasts.confirmText'),
    });

    if (!ok) {
        return;
    }

    form.post('/admin/broadcasts', {
        preserveScroll: true,
        onSuccess: () => form.reset('message'),
    });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.broadcasts.headTitle')" />

    <div class="mx-auto flex max-w-3xl flex-col gap-4 p-4 sm:gap-6 md:p-8">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <Megaphone class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.broadcasts.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageAdmin.broadcasts.subtitle') }}</p>
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
                :dealers="props.options.dealers"
                :previewCount="previewCount"
                :previewLoading="previewLoading"
            />

            <!-- Xabar -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('pageAdmin.broadcasts.messageTitle') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <Label class="mb-1.5 sr-only">{{ t('pageAdmin.broadcasts.messageLabel') }}</Label>
                    <textarea
                        v-model="form.message"
                        rows="6"
                        maxlength="4000"
                        :placeholder="t('pageAdmin.broadcasts.messagePlaceholder')"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <div class="mt-1 flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ t('pageAdmin.broadcasts.markdownHint') }}</span>
                        <span>{{ form.message.length }}/4000</span>
                    </div>
                    <InputError :message="form.errors.message" />
                </CardContent>
            </Card>

            <div class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-2">
                    <AlertCircle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                    <p class="text-sm">
                        <template v-if="previewLoading">{{ t('pageAdmin.broadcasts.calculating') }}</template>
                        <template v-else-if="previewCount !== null">
                            <span class="font-bold">{{ previewCount }}</span> {{ t('pageAdmin.broadcasts.willSendTo') }}
                        </template>
                    </p>
                </div>
                <Button type="submit" class="w-full sm:w-auto" :disabled="form.processing || !form.message.trim() || previewCount === 0">
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ form.processing ? t('pageAdmin.broadcasts.sending') : t('pageAdmin.broadcasts.send') }}
                </Button>
            </div>
        </form>
    </div>
</template>
