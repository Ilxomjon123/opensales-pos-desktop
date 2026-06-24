<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { MapPinned } from 'lucide-vue-next';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const { t } = useI18n();

const props = defineProps<{
    open: boolean;
    shopId: number;
    shopName?: string;
    visitId?: number | null;
    initialNote?: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'submitted'): void;
}>();

const form = useForm({ note: '' });

watch(
    () => props.open,
    (v) => {
        if (v) {
            form.note = props.initialNote ?? '';
            form.clearErrors();
        }
    },
);

function submit() {
    const onSuccess = () => {
        form.reset();
        form.clearErrors();
        emit('update:open', false);
        emit('submitted');
    };

    if (props.visitId) {
        form.put(`/dealer/shops/${props.shopId}/visits/${props.visitId}`, { preserveScroll: true, onSuccess });
    } else {
        form.post(`/dealer/shops/${props.shopId}/visits`, { preserveScroll: true, onSuccess });
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <MapPinned class="h-5 w-5 text-primary" />
                    {{ visitId ? t('pageDealer.shops.visitEditTitle') : t('pageDealer.shops.visitTitle') }}
                </DialogTitle>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <p v-if="shopName" class="text-sm text-muted-foreground">{{ shopName }}</p>
                <div>
                    <Label for="visit-note" class="mb-2 block">
                        {{ t('pageDealer.shops.visitNote') }} <span class="text-destructive">*</span>
                    </Label>
                    <textarea
                        id="visit-note"
                        v-model="form.note"
                        rows="4"
                        :placeholder="t('pageDealer.shops.visitNotePlaceholder')"
                        class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    />
                    <p v-if="form.errors.note" class="mt-1 text-xs text-destructive">{{ form.errors.note }}</p>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="outline" type="button" @click="emit('update:open', false)">
                        {{ t('pageDealer.shops.visitCancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing || !form.note.trim()">
                        {{ t('pageDealer.shops.visitSubmit') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
