<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import ScheduleBuilder from '@/components/broadcasts/ScheduleBuilder.vue';
import AudienceBuilder from '@/components/broadcasts/AudienceBuilder.vue';
import MessageEditor from '@/components/broadcasts/MessageEditor.vue';
import ButtonsEditor from '@/components/broadcasts/ButtonsEditor.vue';
import InputError from '@/components/InputError.vue';

type Shop = { id: number; name: string; phone?: string; region?: string | null; balance?: number };
type Category = { id: number; name: string };

type Campaign = {
    id: number;
    title: string;
    message_text: string;
    media_url: string | null;
    media_type: string | null;
    buttons: { text: string; url: string }[][] | null;
    audience_type: string;
    audience_config: Record<string, unknown> | null;
    schedule_type: string;
    schedule_config: Record<string, unknown>;
    timezone: string;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
};

type Options = {
    shops: Shop[];
    categories: Category[];
    regions: string[];
    placeholders: string[];
    audience_types: { value: string; label: string }[];
    schedule_types: { value: string; label: string }[];
    media_types: { value: string; label: string }[];
    timezone: string;
};

const props = defineProps<{
    campaign: Campaign | null;
    options: Options;
}>();

const { t } = useI18n();
const page = usePage();
const isEdit = computed(() => props.campaign !== null);

const form = useForm({
    title: props.campaign?.title ?? '',
    message_text: props.campaign?.message_text ?? '',
    media: null as File | null,
    media_type: props.campaign?.media_type ?? null,
    remove_media: false as boolean,
    buttons: (props.campaign?.buttons ?? []) as { text: string; url: string }[][],
    audience_type: props.campaign?.audience_type ?? 'all_active',
    audience_config: (props.campaign?.audience_config ?? {}) as Record<string, unknown>,
    schedule_type: props.campaign?.schedule_type ?? 'daily',
    schedule_config: (props.campaign?.schedule_config ?? { time: '09:00' }) as Record<string, unknown>,
    timezone: props.campaign?.timezone ?? props.options.timezone,
    starts_at: props.campaign?.starts_at ? toLocalInput(props.campaign.starts_at) : null,
    ends_at: props.campaign?.ends_at ? toLocalInput(props.campaign.ends_at) : null,
    is_active: props.campaign?.is_active ?? true,
});

function toLocalInput(iso: string): string {
    const d = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

// Audience preview count
const previewCount = ref<number | null>(null);
const previewLoading = ref(false);
let previewTimer: number | null = null;

async function loadPreview() {
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

            const res = await fetch(`/dealer/broadcast-campaigns/preview?${params.toString()}`, {
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

function submit() {
    const url = isEdit.value
        ? `/dealer/broadcast-campaigns/${props.campaign!.id}`
        : '/dealer/broadcast-campaigns';

    if (isEdit.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(url, {
            forceFormData: true,
        });
    } else {
        form.post(url, { forceFormData: true });
    }
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="isEdit ? t('pageDealer.broadcastCampaigns.editTitle') : t('pageDealer.broadcastCampaigns.createTitle')" />

    <div class="mx-auto flex max-w-4xl flex-col gap-4 p-4 md:p-8">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" @click="router.visit('/dealer/broadcast-campaigns')">
                <ArrowLeft class="h-4 w-4" />
            </Button>
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">
                {{ isEdit ? t('pageDealer.broadcastCampaigns.editTitle') : t('pageDealer.broadcastCampaigns.createTitle') }}
            </h1>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ t('pageDealer.broadcastCampaigns.basicInfo') }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div>
                        <Label class="mb-1.5 block">{{ t('pageDealer.broadcastCampaigns.campaignName') }}</Label>
                        <input
                            type="text"
                            v-model="form.title"
                            maxlength="120"
                            :placeholder="t('pageDealer.broadcastCampaigns.campaignNamePlaceholder')"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_active" class="h-4 w-4" />
                        {{ t('pageDealer.broadcastCampaigns.campaignActive') }}
                    </label>
                </CardContent>
            </Card>

            <MessageEditor
                v-model:modelText="form.message_text"
                v-model:modelMedia="form.media"
                v-model:modelMediaType="form.media_type"
                :existingMediaUrl="campaign?.media_url ?? null"
                :existingMediaType="campaign?.media_type ?? null"
                :placeholders="options.placeholders"
                :mediaTypes="options.media_types"
                :errors="{ message_text: form.errors.message_text }"
                @remove-existing="form.remove_media = true"
            />

            <ButtonsEditor v-model="form.buttons" />

            <AudienceBuilder
                v-model:modelType="form.audience_type"
                v-model:modelConfig="form.audience_config"
                :options="options.audience_types"
                :shops="options.shops"
                :categories="options.categories"
                :regions="options.regions"
                :previewCount="previewCount"
                :previewLoading="previewLoading"
            />

            <ScheduleBuilder
                v-model:modelType="form.schedule_type"
                v-model:modelConfig="form.schedule_config"
                v-model:timezone="form.timezone"
                v-model:startsAt="form.starts_at"
                v-model:endsAt="form.ends_at"
                :options="options.schedule_types"
                :errors="form.errors"
            />

            <div class="sticky bottom-4 z-10 flex justify-end gap-2 rounded-lg border bg-background p-3 shadow-md">
                <Button type="button" variant="outline" @click="router.visit('/dealer/broadcast-campaigns')">
                    {{ t('pageDealer.broadcastCampaigns.cancel') }}
                </Button>
                <Button type="submit" :disabled="form.processing || !form.title.trim() || !form.message_text.trim()">
                    <Save class="mr-2 h-4 w-4" />
                    {{ isEdit ? t('pageDealer.broadcastCampaigns.save') : t('pageDealer.broadcastCampaigns.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
