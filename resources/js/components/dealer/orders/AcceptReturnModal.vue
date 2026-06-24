<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrency } from '@/composables/useCurrency';
import { useUnitLabel } from '@/composables/useUnitLabel';
import type { Order, OrderItem } from '@/types';

type ReturnRow = {
    product_id: number;
    product_type_id: number | null;
    product_name: string;
    product_type_name: string | null;
    pack_size: number;
    carry_qty: number;
    carry_pack_qty: number;
    return_pack_qty: number;
    return_unit_qty: number;
    price: number;
    unit: string | null;
};

function totalReturn(r: ReturnRow): number {
    const packs = Math.max(0, Number(r.return_pack_qty) || 0);
    const units = Math.max(0, Number(r.return_unit_qty) || 0);

    return packs * Math.max(1, r.pack_size) + units;
}

function clamp(r: ReturnRow) {
    const maxPacks = r.carry_pack_qty;
    r.return_pack_qty = Math.min(Math.max(0, r.return_pack_qty), maxPacks);
    const looseAfterPacks = Math.max(
        0,
        r.carry_qty - r.return_pack_qty * r.pack_size,
    );
    r.return_unit_qty = Math.min(
        Math.max(0, r.return_unit_qty),
        looseAfterPacks,
    );
}

const props = defineProps<{
    open: boolean;
    order: Order;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();
const unitLabel = useUnitLabel();
const { formatWithSymbol } = useCurrency();

const rows = reactive<ReturnRow[]>([]);
const processing = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        rows.splice(0, rows.length);

        (props.order.items ?? []).forEach((i: OrderItem) => {
            const carry = Number(i.carry_qty ?? 0);

            if (carry <= 0) {
                return;
            }

            const packSize = Math.max(1, i.pack_size ?? 1);
            const carryPack = Math.max(0, Number(i.carry_pack_qty ?? 0));
            const carryLoose = Math.max(0, carry - carryPack * packSize);

            rows.push({
                product_id: i.product_id,
                product_type_id: i.product_type_id ?? null,
                product_name: i.product_name,
                product_type_name: i.product_type_name ?? null,
                pack_size: packSize,
                carry_qty: carry,
                carry_pack_qty: carryPack,
                return_pack_qty: carryPack,
                return_unit_qty: carryLoose,
                price: i.price,
                unit: i.unit,
            });
        });

        error.value = null;
    },
    { immediate: true },
);

const returnTotal = computed(() =>
    rows.reduce((sum, r) => sum + r.price * totalReturn(r), 0),
);

function fillAll() {
    rows.forEach((r) => {
        r.return_pack_qty = r.carry_pack_qty;
        r.return_unit_qty = Math.max(
            0,
            r.carry_qty - r.carry_pack_qty * r.pack_size,
        );
    });
}

function clearAll() {
    rows.forEach((r) => {
        r.return_pack_qty = 0;
        r.return_unit_qty = 0;
    });
}

function submit() {
    processing.value = true;
    error.value = null;

    const payload = {
        items: rows
            .filter((r) => totalReturn(r) > 0)
            .map((r) => ({
                product_id: r.product_id,
                product_type_id: r.product_type_id,
                returned_qty: totalReturn(r),
                returned_pack_qty:
                    r.pack_size > 1
                        ? Math.max(0, Number(r.return_pack_qty) || 0)
                        : 0,
            })),
    };

    if (payload.items.length === 0) {
        error.value = t('pageDealer.orders.noReturnMarked');
        processing.value = false;

        return;
    }

    router.post(`/dealer/orders/${props.order.id}/return`, payload, {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
        onError: (e) => {
            error.value =
                Object.values(e).join(', ') ||
                t('pageDealer.orders.errorGeneric');
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex max-h-[calc(100dvh-2rem)] flex-col gap-3 p-4 sm:max-w-2xl sm:gap-4 sm:p-6"
            @open-auto-focus="(e: Event) => e.preventDefault()"
        >
            <DialogHeader>
                <DialogTitle class="pr-8 text-base sm:text-lg">
                    {{
                        t('pageDealer.orders.acceptReturnTitle', {
                            number: order.number,
                        })
                    }}
                </DialogTitle>
            </DialogHeader>

            <div
                class="-mx-4 flex-1 space-y-3 overflow-y-auto px-4 sm:-mx-6 sm:px-6"
            >
                <div
                    class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                >
                    <span>{{ t('pageDealer.orders.acceptReturnHint') }}</span>
                    <div class="flex shrink-0 gap-1.5">
                        <button
                            type="button"
                            class="rounded border px-2 py-1 hover:bg-muted"
                            @click="fillAll"
                        >
                            {{ t('pageDealer.orders.all') }}
                        </button>
                        <button
                            type="button"
                            class="rounded border px-2 py-1 hover:bg-muted"
                            @click="clearAll"
                        >
                            {{ t('pageDealer.orders.clear') }}
                        </button>
                    </div>
                </div>

                <div class="rounded-lg border">
                    <table class="hidden w-full text-left text-sm sm:table">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 font-medium">
                                    {{ t('pageDealer.orders.productCol') }}
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    {{ t('pageDealer.orders.remaining') }}
                                </th>
                                <th
                                    class="w-56 px-3 py-2 text-right font-medium"
                                >
                                    {{ t('pageDealer.orders.returnedCol') }}
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    {{ t('pageDealer.orders.amount') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(r, idx) in rows" :key="idx">
                                <td class="px-3 py-2">
                                    {{ r.product_name }}
                                    <span
                                        v-if="r.product_type_name"
                                        class="text-muted-foreground"
                                        >— {{ r.product_type_name }}</span
                                    >
                                </td>
                                <td
                                    class="px-3 py-2 text-right text-muted-foreground"
                                >
                                    {{ r.carry_qty }} {{ unitLabel(r.unit) }}
                                </td>
                                <td class="px-3 py-2">
                                    <div
                                        v-if="r.pack_size > 1"
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <div class="flex items-center gap-1">
                                            <Input
                                                type="number"
                                                min="0"
                                                :max="r.carry_pack_qty"
                                                v-model.number="
                                                    r.return_pack_qty
                                                "
                                                class="h-8 w-16 text-right"
                                                @change="clamp(r)"
                                            />
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    t(
                                                        'pageDealer.orders.blockUnit',
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <span class="text-muted-foreground"
                                            >+</span
                                        >
                                        <div class="flex items-center gap-1">
                                            <Input
                                                type="number"
                                                min="0"
                                                v-model.number="
                                                    r.return_unit_qty
                                                "
                                                class="h-8 w-16 text-right"
                                                @change="clamp(r)"
                                            />
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{ unitLabel(r.unit) }}</span
                                            >
                                        </div>
                                    </div>
                                    <Input
                                        v-else
                                        type="number"
                                        min="0"
                                        :max="r.carry_qty"
                                        v-model.number="r.return_unit_qty"
                                        class="h-8 text-right"
                                        @change="clamp(r)"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right font-mono">
                                    {{
                                        formatWithSymbol(
                                            r.price * totalReturn(r),
                                        )
                                    }}
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="4"
                                    class="px-3 py-4 text-center text-muted-foreground"
                                >
                                    {{
                                        t('pageDealer.orders.noReturnProducts')
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex flex-col divide-y sm:hidden">
                        <div
                            v-for="(r, idx) in rows"
                            :key="`m-${idx}`"
                            class="flex flex-col gap-2 p-3"
                        >
                            <div>
                                <p class="font-medium break-words">
                                    {{ r.product_name }}
                                    <span
                                        v-if="r.product_type_name"
                                        class="text-muted-foreground"
                                        >— {{ r.product_type_name }}</span
                                    >
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        t('pageDealer.orders.remainingColon', {
                                            qty: r.carry_qty,
                                            unit: unitLabel(r.unit),
                                        })
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="r.pack_size > 1"
                                class="grid grid-cols-2 gap-2"
                            >
                                <div>
                                    <Label
                                        :for="`r-pack-${idx}`"
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t('pageDealer.orders.returnBlock')
                                        }}</Label
                                    >
                                    <Input
                                        :id="`r-pack-${idx}`"
                                        type="number"
                                        min="0"
                                        :max="r.carry_pack_qty"
                                        v-model.number="r.return_pack_qty"
                                        class="mt-1 h-9 text-right"
                                        @change="clamp(r)"
                                    />
                                </div>
                                <div>
                                    <Label
                                        :for="`r-unit-${idx}`"
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t('pageDealer.orders.returnUnit', {
                                                unit: unitLabel(
                                                    r.unit ?? 'dona',
                                                ),
                                            })
                                        }}</Label
                                    >
                                    <Input
                                        :id="`r-unit-${idx}`"
                                        type="number"
                                        min="0"
                                        v-model.number="r.return_unit_qty"
                                        class="mt-1 h-9 text-right"
                                        @change="clamp(r)"
                                    />
                                </div>
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <Label
                                    :for="`r-qty-${idx}`"
                                    class="text-xs text-muted-foreground"
                                    >{{
                                        t('pageDealer.orders.returnLabel')
                                    }}</Label
                                >
                                <Input
                                    :id="`r-qty-${idx}`"
                                    type="number"
                                    min="0"
                                    :max="r.carry_qty"
                                    v-model.number="r.return_unit_qty"
                                    class="h-9 flex-1 text-right"
                                    @change="clamp(r)"
                                />
                                <span
                                    v-if="r.unit"
                                    class="shrink-0 text-xs text-muted-foreground"
                                    >{{ unitLabel(r.unit) }}</span
                                >
                            </div>
                            <div
                                class="text-right text-xs text-muted-foreground"
                            >
                                {{ t('pageDealer.orders.amountColon') }}
                                <span
                                    class="font-mono font-medium text-foreground"
                                >
                                    {{
                                        formatWithSymbol(
                                            r.price * totalReturn(r),
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                        <div
                            v-if="rows.length === 0"
                            class="p-4 text-center text-sm text-muted-foreground"
                        >
                            {{ t('pageDealer.orders.noReturnProducts') }}
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border bg-muted/20 p-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">{{
                            t('pageDealer.orders.returnTotal')
                        }}</span>
                        <span class="font-mono font-semibold">{{
                            formatWithSymbol(returnTotal)
                        }}</span>
                    </div>
                </div>

                <p
                    v-if="error"
                    class="rounded bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ error }}
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">{{
                    t('pageDealer.orders.cancelShort')
                }}</Button>
                <Button
                    :disabled="processing || rows.length === 0"
                    @click="submit"
                >
                    {{
                        processing
                            ? t('pageDealer.orders.saving')
                            : t('pageDealer.orders.accept')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
