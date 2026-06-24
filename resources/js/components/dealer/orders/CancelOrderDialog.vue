<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { Order } from '@/types';

const props = defineProps<{
    open: boolean;
    order: Order;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();

const reason = ref('');
const processing = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (open) {
            reason.value = '';
            error.value = null;
        }
    },
);

function submit() {
    if (reason.value.trim().length < 3) {
        error.value = t('pageDealer.orders.cancelReasonMin');

        return;
    }

    processing.value = true;
    error.value = null;

    router.post(
        `/dealer/orders/${props.order.id}/cancel`,
        { reason: reason.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
            onError: (errors) => {
                error.value = errors.reason ?? errors.error ?? t('pageDealer.orders.errorGeneric');
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('pageDealer.orders.cancelOrderTitle', { number: order.number }) }}</DialogTitle>
            </DialogHeader>

            <div class="space-y-3 py-2">
                <Label for="cancel-reason">{{ t('pageDealer.orders.cancelReason') }} <span class="text-destructive">*</span></Label>
                <textarea
                    id="cancel-reason"
                    v-model="reason"
                    rows="4"
                    maxlength="500"
                    :placeholder="t('pageDealer.orders.cancelReasonPlaceholder')"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
                <p class="text-xs text-muted-foreground">{{ reason.length }} / 500</p>
                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">{{ t('pageDealer.orders.close') }}</Button>
                <Button variant="destructive" :disabled="processing" @click="submit">
                    {{ processing ? t('pageDealer.orders.sending') : t('pageDealer.orders.cancel') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
