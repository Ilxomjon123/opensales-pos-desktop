<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { X, Image as ImageIcon } from 'lucide-vue-next';

const { t } = useI18n();

const props = defineProps<{
    modelText: string;
    modelMedia: File | null;
    modelMediaType: string | null;
    existingMediaUrl: string | null;
    existingMediaType: string | null;
    placeholders: string[];
    mediaTypes: { value: string; label: string }[];
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{
    'update:modelText': [v: string];
    'update:modelMedia': [v: File | null];
    'update:modelMediaType': [v: string | null];
    'remove-existing': [];
}>();

const textareaRef = ref<HTMLTextAreaElement | null>(null);
const removeExisting = ref(false);

function insertPlaceholder(ph: string) {
    const el = textareaRef.value;

    if (!el) {
        emit('update:modelText', props.modelText + ph);
        return;
    }

    const start = el.selectionStart;
    const end = el.selectionEnd;
    const newText =
        props.modelText.slice(0, start) + ph + props.modelText.slice(end);
    emit('update:modelText', newText);

    requestAnimationFrame(() => {
        el.focus();
        el.selectionStart = el.selectionEnd = start + ph.length;
    });
}

function onMediaChange(e: Event) {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    emit('update:modelMedia', file);

    if (file) {
        const inferred = file.type.startsWith('image/')
            ? 'photo'
            : file.type.startsWith('video/')
              ? 'video'
              : 'document';
        emit('update:modelMediaType', inferred);
    }
}

function clearMedia() {
    emit('update:modelMedia', null);
    emit('update:modelMediaType', null);
}

function markRemoveExisting() {
    removeExisting.value = true;
    emit('remove-existing');
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">{{
                t('component.broadcast.message.title')
            }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div>
                <Label class="mb-1.5 block">{{
                    t('component.broadcast.message.text')
                }}</Label>
                <textarea
                    ref="textareaRef"
                    :value="modelText"
                    @input="
                        emit(
                            'update:modelText',
                            ($event.target as HTMLTextAreaElement).value,
                        )
                    "
                    rows="6"
                    maxlength="4000"
                    :placeholder="
                        t('component.broadcast.message.textPlaceholder')
                    "
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <div
                    class="mt-1 flex items-center justify-between text-xs text-muted-foreground"
                >
                    <span>{{
                        t('component.broadcast.message.markdownHint')
                    }}</span>
                    <span>{{ modelText.length }}/4000</span>
                </div>
                <p
                    v-if="errors?.message_text"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ errors.message_text }}
                </p>
            </div>

            <div>
                <Label class="mb-2 block">{{
                    t('component.broadcast.message.placeholdersLabel')
                }}</Label>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="ph in placeholders"
                        :key="ph"
                        type="button"
                        class="rounded-md border border-input bg-muted/40 px-2 py-1 font-mono text-xs hover:bg-muted"
                        @click="insertPlaceholder(ph)"
                    >
                        {{ ph }}
                    </button>
                </div>
            </div>

            <div>
                <Label class="mb-2 block">{{
                    t('component.broadcast.message.mediaLabel')
                }}</Label>
                <div
                    v-if="existingMediaUrl && !removeExisting"
                    class="mb-2 flex items-center gap-3 rounded-md border p-2"
                >
                    <ImageIcon
                        v-if="existingMediaType === 'photo'"
                        class="h-5 w-5 text-muted-foreground"
                    />
                    <a
                        :href="existingMediaUrl"
                        target="_blank"
                        class="flex-1 truncate text-sm text-primary hover:underline"
                    >
                        {{ existingMediaUrl }}
                    </a>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="markRemoveExisting"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <input
                    type="file"
                    accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx"
                    @change="onMediaChange"
                    class="w-full text-sm"
                />

                <div
                    v-if="modelMedia"
                    class="mt-2 flex items-center gap-2 rounded-md border bg-muted/30 p-2 text-sm"
                >
                    <span class="flex-1 truncate"
                        >{{ modelMedia.name }} ({{
                            (modelMedia.size / 1024).toFixed(0)
                        }}
                        KB)</span
                    >
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="clearMedia"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
