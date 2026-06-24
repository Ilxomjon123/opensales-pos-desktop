<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { currencySymbol } from '@/lib/format';

const props = defineProps<{
    dealerId: number;
    dealerName: string;
    suggestedAmount: number;
}>();

const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();

const amount = ref<number>(props.suggestedAmount);
const discount = ref<number>(0);
const note = ref<string>('');

const settled = computed(() => (Number(amount.value) || 0) + (Number(discount.value) || 0));
const canSubmit = computed(() => settled.value >= 1);

function save() {
    if (!canSubmit.value) {
        return;
    }

    router.post(`/admin/dealers/${props.dealerId}/platform-payments`, {
        amount: Number(amount.value) || 0,
        discount: Number(discount.value) || 0,
        note: note.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="$emit('close')"
    >
        <Card class="w-full max-w-md">
            <CardHeader>
                <CardTitle class="text-base">{{ t('pageAdmin.stats.paymentModal.title') }} — {{ dealerName }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label for="payment_amount" class="mb-1.5">
                        {{ t('pageAdmin.stats.paymentModal.amountLabel') }} ({{ currencySymbol() }})
                    </Label>
                    <Input
                        id="payment_amount"
                        v-model.number="amount"
                        type="number"
                        min="0"
                        step="1000"
                        autofocus
                    />
                </div>
                <div>
                    <Label for="payment_discount" class="mb-1.5">
                        {{ t('pageAdmin.stats.paymentModal.discountLabel') }} ({{ currencySymbol() }})
                    </Label>
                    <Input
                        id="payment_discount"
                        v-model.number="discount"
                        type="number"
                        min="0"
                        step="1000"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ t('pageAdmin.stats.paymentModal.discountHint') }}
                    </p>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ t('pageAdmin.stats.paymentModal.totalSettled') }}: <span class="font-mono font-semibold text-foreground">{{ settled.toLocaleString('ru-RU') }}</span> {{ currencySymbol() }}
                </p>
                <div>
                    <Label for="payment_note" class="mb-1.5">{{ t('pageAdmin.stats.paymentModal.noteLabel') }}</Label>
                    <Input id="payment_note" v-model="note" :placeholder="t('pageAdmin.stats.paymentModal.notePlaceholder')" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button variant="outline" type="button" @click="$emit('close')">{{ t('pageAdmin.stats.paymentModal.cancel') }}</Button>
                    <Button :disabled="!canSubmit" type="button" @click="save">
                        {{ t('pageAdmin.stats.paymentModal.save') }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
