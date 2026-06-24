<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, X } from 'lucide-vue-next';

const { t } = useI18n();

type Btn = { text: string; url: string };

const props = defineProps<{
    modelValue: Btn[][];
}>();

const emit = defineEmits<{
    'update:modelValue': [v: Btn[][]];
}>();

function addRow() {
    if (props.modelValue.length >= 5) return;
    emit('update:modelValue', [...props.modelValue, [{ text: '', url: '' }]]);
}

function removeRow(idx: number) {
    const next = props.modelValue.slice();
    next.splice(idx, 1);
    emit('update:modelValue', next);
}

function addButton(rowIdx: number) {
    const row = props.modelValue[rowIdx];

    if (!row || row.length >= 3) return;

    const next = props.modelValue.map((r, i) =>
        i === rowIdx ? [...r, { text: '', url: '' }] : r,
    );
    emit('update:modelValue', next);
}

function removeButton(rowIdx: number, btnIdx: number) {
    const next = props.modelValue.map((r, i) =>
        i === rowIdx ? r.filter((_, bi) => bi !== btnIdx) : r,
    );
    emit(
        'update:modelValue',
        next.filter((r) => r.length > 0),
    );
}

function patchButton(rowIdx: number, btnIdx: number, patch: Partial<Btn>) {
    const next = props.modelValue.map((r, i) =>
        i === rowIdx
            ? r.map((b, bi) => (bi === btnIdx ? { ...b, ...patch } : b))
            : r,
    );
    emit('update:modelValue', next);
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">{{
                t('component.broadcast.buttons.title')
            }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
            <div
                v-for="(row, rowIdx) in modelValue"
                :key="rowIdx"
                class="space-y-2 rounded-md border p-3"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground">{{
                        t('component.broadcast.buttons.row', { n: rowIdx + 1 })
                    }}</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removeRow(rowIdx)"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <div
                    v-for="(btn, btnIdx) in row"
                    :key="btnIdx"
                    class="flex flex-col gap-2 sm:flex-row"
                >
                    <input
                        type="text"
                        :value="btn.text"
                        @input="
                            patchButton(rowIdx, btnIdx, {
                                text: ($event.target as HTMLInputElement).value,
                            })
                        "
                        :placeholder="
                            t('component.broadcast.buttons.buttonText')
                        "
                        maxlength="64"
                        class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <input
                        type="url"
                        :value="btn.url"
                        @input="
                            patchButton(rowIdx, btnIdx, {
                                url: ($event.target as HTMLInputElement).value,
                            })
                        "
                        placeholder="https://..."
                        class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="removeButton(rowIdx, btnIdx)"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <Button
                    v-if="row.length < 3"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addButton(rowIdx)"
                >
                    <Plus class="mr-1 h-3 w-3" />
                    {{ t('component.broadcast.buttons.addButtonInRow') }}
                </Button>
            </div>

            <Button
                v-if="modelValue.length < 5"
                type="button"
                variant="outline"
                @click="addRow"
            >
                <Plus class="mr-1 h-4 w-4" />
                {{ t('component.broadcast.buttons.addRow') }}
            </Button>
        </CardContent>
    </Card>
</template>
