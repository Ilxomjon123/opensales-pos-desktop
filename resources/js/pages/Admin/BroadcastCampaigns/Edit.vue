<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import ScheduleBuilder from '@/components/broadcasts/ScheduleBuilder.vue';
import MessageEditor from '@/components/broadcasts/MessageEditor.vue';
import ButtonsEditor from '@/components/broadcasts/ButtonsEditor.vue';
import InputError from '@/components/InputError.vue';

type Campaign = {
    id: number;
    title: string;
    message_text: string;
    media_url: string | null;
    media_type: string | null;
    buttons: { text: string; url: string }[][] | null;
    audience_type: string;
    schedule_type: string;
    schedule_config: Record<string, unknown>;
    timezone: string;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
};

type Options = {
    audience_types: { value: string; label: string }[];
    schedule_types: { value: string; label: string }[];
    media_types: { value: string; label: string }[];
    placeholders: string[];
    timezone: string;
};

const props = defineProps<{ campaign: Campaign | null; options: Options }>();

const { t } = useI18n();

const isEdit = computed(() => props.campaign !== null);

const form = useForm({
    title: props.campaign?.title ?? '',
    message_text: props.campaign?.message_text ?? '',
    media: null as File | null,
    media_type: props.campaign?.media_type ?? null,
    remove_media: false as boolean,
    buttons: (props.campaign?.buttons ?? []) as { text: string; url: string }[][],
    audience_type: props.campaign?.audience_type ?? 'platform_dealers',
    audience_config: {} as Record<string, unknown>,
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

function submit() {
    const url = isEdit.value
        ? `/admin/broadcast-campaigns/${props.campaign!.id}`
        : '/admin/broadcast-campaigns';

    if (isEdit.value) {
        form.transform((d) => ({ ...d, _method: 'put' })).post(url, { forceFormData: true });
    } else {
        form.post(url, { forceFormData: true });
    }
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="isEdit ? t('pageAdmin.broadcastCampaignsEdit.headTitleEdit') : t('pageAdmin.broadcastCampaignsEdit.headTitleCreate')" />

    <div class="mx-auto flex max-w-4xl flex-col gap-4 p-4 md:p-8">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="sm" @click="router.visit('/admin/broadcast-campaigns')">
                <ArrowLeft class="h-4 w-4" />
            </Button>
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">
                {{ isEdit ? t('pageAdmin.broadcastCampaignsEdit.titleEdit') : t('pageAdmin.broadcastCampaignsEdit.titleCreate') }}
            </h1>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <Card>
                <CardHeader><CardTitle class="text-base">{{ t('pageAdmin.broadcastCampaignsEdit.mainTitle') }}</CardTitle></CardHeader>
                <CardContent class="space-y-3">
                    <div>
                        <Label class="mb-1.5 block">{{ t('pageAdmin.broadcastCampaignsEdit.name') }}</Label>
                        <input
                            type="text"
                            v-model="form.title"
                            maxlength="120"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div>
                        <Label class="mb-1.5 block">{{ t('pageAdmin.broadcastCampaignsEdit.audience') }}</Label>
                        <select
                            v-model="form.audience_type"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option v-for="o in options.audience_types" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="form.is_active" class="h-4 w-4" />
                        {{ t('pageAdmin.broadcastCampaignsEdit.active') }}
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
                <Button type="button" variant="outline" @click="router.visit('/admin/broadcast-campaigns')">{{ t('pageAdmin.broadcastCampaignsEdit.cancel') }}</Button>
                <Button type="submit" :disabled="form.processing || !form.title.trim() || !form.message_text.trim()">
                    <Save class="mr-2 h-4 w-4" />
                    {{ isEdit ? t('pageAdmin.broadcastCampaignsEdit.save') : t('pageAdmin.broadcastCampaignsEdit.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
