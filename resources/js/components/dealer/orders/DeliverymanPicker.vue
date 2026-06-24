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
import { SearchableSelect } from '@/components/ui/searchable-select';
import type { Order } from '@/types';

const props = defineProps<{
    open: boolean;
    order: Order;
    deliverymen: { id: number; name: string; phone: string | null }[];
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();

const selectedId = ref<number | null>(null);
const processing = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (open) {
            selectedId.value = props.order.deliveryman_id ?? null;
            error.value = null;
        }
    },
);

function submit() {
    processing.value = true;
    error.value = null;

    router.patch(
        `/dealer/orders/${props.order.id}/deliveryman`,
        { deliveryman_id: selectedId.value },
        {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
            onError: (errors) => {
                error.value = errors.deliveryman_id ?? errors.error ?? t('pageDealer.orders.errorGeneric');
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
        <DialogContent
            class="sm:max-w-md"
            @pointer-down-outside="(e: Event) => {
                const target = e.target as HTMLElement | null;
                if (target?.closest('[data-searchable-select-popup]')) {
                    e.preventDefault();
                }
            }"
            @interact-outside="(e: Event) => {
                const target = e.target as HTMLElement | null;
                if (target?.closest('[data-searchable-select-popup]')) {
                    e.preventDefault();
                }
            }"
        >
            <DialogHeader>
                <DialogTitle>{{ t('pageDealer.orders.assignDeliveryman') }}</DialogTitle>
            </DialogHeader>

            <div class="space-y-3 py-2">
                <SearchableSelect
                    v-model="selectedId"
                    :items="deliverymen"
                    value-key="id"
                    label-key="name"
                    :placeholder="t('pageDealer.orders.selectDeliveryman')"
                    :search-placeholder="t('pageDealer.orders.searchDeliveryman')"
                    :empty-text="t('pageDealer.orders.noDeliverymen')"
                    clearable
                />

                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
                <p v-if="!selectedId" class="text-xs text-muted-foreground">
                    {{ t('pageDealer.orders.leaveEmptyToUnassign') }}
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">{{ t('pageDealer.orders.cancelShort') }}</Button>
                <Button :disabled="processing" @click="submit">
                    {{ processing ? t('pageDealer.orders.saving') : t('pageDealer.orders.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
