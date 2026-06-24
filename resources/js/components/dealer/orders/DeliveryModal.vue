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
import type { Order, OrderItem, Product } from '@/types';

type DeliveryRow = {
    product_id: number;
    product_type_id: number | null;
    product_name: string;
    product_type_name: string | null;
    price: number;
    picked_qty: number;
    picked_pack_qty: number;
    pack_size: number;
    bulk_only: boolean;
    delivered_pack_qty: number;
    delivered_unit_qty: number;
    unit: string | null;
};

function totalDelivered(r: DeliveryRow): number {
    const packs = Math.max(0, Number(r.delivered_pack_qty) || 0);
    const units = r.bulk_only
        ? 0
        : Math.max(0, Number(r.delivered_unit_qty) || 0);

    return packs * Math.max(1, r.pack_size) + units;
}

function pickedLooseQty(r: DeliveryRow): number {
    return Math.max(
        0,
        r.picked_qty - r.picked_pack_qty * Math.max(1, r.pack_size),
    );
}

const props = defineProps<{
    open: boolean;
    order: Order;
    catalog: Product[];
    canAssemble?: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'request-assemble': [];
}>();

const { t } = useI18n();
const unitLabel = useUnitLabel();
const { formatWithSymbol, symbol } = useCurrency();

function requestAssemble() {
    emit('update:open', false);
    emit('request-assemble');
}

type PaymentMode = 'all-cash' | 'all-card' | 'split' | 'none';

const rows = reactive<DeliveryRow[]>([]);
const paymentMode = ref<PaymentMode>('none');
const customCash = ref<number>(0);
const customCard = ref<number>(0);
const cardholderName = ref<string>('');
const discountAmount = ref<number>(0);
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
            const packSize = Math.max(1, i.pack_size ?? 1);
            const product = props.catalog.find((p) => p.id === i.product_id);
            const typeRow =
                i.product_type_id !== null
                    ? product?.types?.find((t) => t.id === i.product_type_id)
                    : null;
            const bulkOnly =
                Boolean(typeRow?.bulk_only ?? product?.bulk_only) &&
                packSize > 1;
            const pickedQty = Number(i.picked_qty ?? 0);
            const pickedPackQty = Math.max(0, Number(i.picked_pack_qty ?? 0));

            if (pickedQty <= 0) {
                return;
            }

            const initialPackQty = packSize > 1 ? pickedPackQty : 0;
            const initialUnitQty =
                packSize > 1
                    ? Math.max(0, pickedQty - pickedPackQty * packSize)
                    : pickedQty;

            rows.push({
                product_id: i.product_id,
                product_type_id: i.product_type_id ?? null,
                product_name: i.product_name,
                product_type_name: i.product_type_name ?? null,
                price: i.price,
                picked_qty: pickedQty,
                picked_pack_qty: pickedPackQty,
                pack_size: packSize,
                bulk_only: bulkOnly,
                delivered_pack_qty: initialPackQty,
                delivered_unit_qty: initialUnitQty,
                unit: i.unit,
            });
        });
        paymentMode.value = 'none';
        customCash.value = 0;
        customCard.value = 0;
        cardholderName.value = '';
        discountAmount.value = 0;
        error.value = null;
    },
    { immediate: true },
);

function clampToPicked(r: DeliveryRow) {
    if (r.pack_size > 1) {
        r.delivered_pack_qty = Math.min(
            Math.max(0, r.delivered_pack_qty),
            r.picked_pack_qty,
        );
        const remainingLoose = Math.max(
            0,
            r.picked_qty - r.delivered_pack_qty * r.pack_size,
        );
        r.delivered_unit_qty = Math.min(
            Math.max(0, r.delivered_unit_qty),
            remainingLoose,
        );
    } else {
        r.delivered_unit_qty = Math.min(
            Math.max(0, r.delivered_unit_qty),
            r.picked_qty,
        );
        r.delivered_pack_qty = 0;
    }
}

const deliveredTotal = computed(() =>
    rows.reduce((sum, r) => sum + r.price * totalDelivered(r), 0),
);

const effectiveDiscount = computed(() =>
    Math.min(
        Math.max(0, Number(discountAmount.value) || 0),
        deliveredTotal.value,
    ),
);

const payableTotal = computed(
    () => deliveredTotal.value - effectiveDiscount.value,
);

const paidCash = computed<number>({
    get: () => {
        const mode = paymentMode.value;

        if (mode === 'all-cash') {
            return Math.max(0, payableTotal.value);
        }

        if (mode === 'all-card' || mode === 'none') {
            return 0;
        }

        return Math.max(0, Number(customCash.value) || 0);
    },
    set: (v: number) => {
        const next = Math.max(0, Number(v) || 0);

        if (paymentMode.value !== 'split') {
            const currentCard = paidCard.value;
            paymentMode.value = 'split';
            customCard.value = currentCard;
        }

        customCash.value = next;
    },
});

const paidCard = computed<number>({
    get: () => {
        const mode = paymentMode.value;

        if (mode === 'all-card') {
            return Math.max(0, payableTotal.value);
        }

        if (mode === 'all-cash' || mode === 'none') {
            return 0;
        }

        return Math.max(0, Number(customCard.value) || 0);
    },
    set: (v: number) => {
        const next = Math.max(0, Number(v) || 0);

        if (paymentMode.value !== 'split') {
            const currentCash = paidCash.value;
            paymentMode.value = 'split';
            customCash.value = currentCash;
        }

        customCard.value = next;
    },
});

const paidAmount = computed<number>(() => paidCash.value + paidCard.value);

const balanceDelta = computed(() => payableTotal.value - paidAmount.value);

// Buyurtma yaratilganda mijoz saldosiga tegilmaydi — qarz faqat shu yerda
// yoziladi. Demak `shop.balance` shu paytdagi haqiqiy "oldingi" qoldiq.
const previousBalance = computed(() => props.order.shop?.balance ?? 0);
const previousDebt = computed(() => Math.max(0, -previousBalance.value));
const previousCredit = computed(() => Math.max(0, previousBalance.value));
const finalBalance = computed(
    () => previousBalance.value - payableTotal.value + paidAmount.value,
);

// Berilgan jami va to'langan pul orasidagi farq — bosilganda chegirma shu farqga
// tenglashtiriladi va shu buyurtma bo'yicha qarz nolga tushadi.
const orderDebtGap = computed(() =>
    Math.min(
        deliveredTotal.value,
        Math.max(0, deliveredTotal.value - paidAmount.value),
    ),
);

function submit() {
    const cardAmount = Math.max(0, Number(paidCard.value) || 0);
    const trimmedCardholder = cardholderName.value.trim();

    if (cardAmount > 0 && trimmedCardholder === '') {
        error.value = t('pageDealer.orders.cardholderNameRequired');

        return;
    }

    processing.value = true;
    error.value = null;

    const payload = {
        items: rows.map((r) => ({
            product_id: r.product_id,
            product_type_id: r.product_type_id,
            price: Math.max(0, Number(r.price) || 0),
            delivered_qty: totalDelivered(r),
            delivered_pack_qty:
                r.pack_size > 1
                    ? Math.max(0, Number(r.delivered_pack_qty) || 0)
                    : 0,
        })),
        paid_amount: paidAmount.value,
        paid_card: cardAmount,
        cardholder_name: cardAmount > 0 ? trimmedCardholder : null,
        discount: effectiveDiscount.value,
    };

    router.post(`/dealer/orders/${props.order.id}/deliver`, payload, {
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
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent
            class="flex max-h-[calc(100dvh-2rem)] flex-col gap-3 p-4 sm:max-w-3xl sm:gap-4 sm:p-6"
            @open-auto-focus="(e: Event) => e.preventDefault()"
        >
            <DialogHeader>
                <DialogTitle class="pr-8 text-base sm:text-lg">
                    {{
                        t('pageDealer.orders.deliverTitle', {
                            number: order.number,
                        })
                    }}
                </DialogTitle>
            </DialogHeader>

            <div
                class="-mx-4 flex-1 space-y-4 overflow-y-auto px-4 sm:-mx-6 sm:px-6"
            >
                <div class="rounded-lg border">
                    <!-- Desktop table -->
                    <table class="hidden w-full text-left text-sm sm:table">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 font-medium">
                                    {{ t('pageDealer.orders.productCol') }}
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    {{ t('pageDealer.orders.price') }}
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    {{ t('pageDealer.orders.pickedOut') }}
                                </th>
                                <th
                                    class="w-56 px-3 py-2 text-right font-medium"
                                >
                                    {{ t('pageDealer.orders.given') }}
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    {{ t('pageDealer.orders.total') }}
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
                                <td class="px-3 py-2 text-right">
                                    <Input
                                        type="number"
                                        min="0"
                                        v-model.number="r.price"
                                        class="h-8 w-28 text-right font-mono"
                                    />
                                </td>
                                <td
                                    class="px-3 py-2 text-right text-muted-foreground"
                                >
                                    <div>
                                        {{ r.picked_qty }}
                                        {{ unitLabel(r.unit) }}
                                    </div>
                                    <div
                                        v-if="
                                            r.pack_size > 1 &&
                                            r.picked_pack_qty > 0
                                        "
                                        class="text-xs"
                                    >
                                        <template v-if="pickedLooseQty(r) > 0">
                                            {{ r.picked_pack_qty }}
                                            {{
                                                t('pageDealer.orders.blockUnit')
                                            }}
                                            + {{ pickedLooseQty(r) }}
                                            {{ unitLabel(r.unit) }}
                                        </template>
                                        <template v-else>
                                            {{ r.picked_pack_qty }}
                                            {{
                                                t('pageDealer.orders.blockUnit')
                                            }}
                                            × {{ r.pack_size }}
                                        </template>
                                    </div>
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
                                                :max="r.picked_pack_qty"
                                                v-model.number="
                                                    r.delivered_pack_qty
                                                "
                                                class="h-8 w-16 text-right"
                                                @change="clampToPicked(r)"
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
                                        <template v-if="!r.bulk_only">
                                            <span class="text-muted-foreground"
                                                >+</span
                                            >
                                            <div
                                                class="flex items-center gap-1"
                                            >
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    v-model.number="
                                                        r.delivered_unit_qty
                                                    "
                                                    class="h-8 w-16 text-right"
                                                    @change="clampToPicked(r)"
                                                />
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                    >{{
                                                        unitLabel(r.unit)
                                                    }}</span
                                                >
                                            </div>
                                        </template>
                                    </div>
                                    <Input
                                        v-else
                                        type="number"
                                        min="0"
                                        :max="r.picked_qty"
                                        v-model.number="r.delivered_unit_qty"
                                        class="h-8 text-right"
                                        @change="clampToPicked(r)"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right font-mono">
                                    <div>
                                        {{
                                            formatWithSymbol(
                                                r.price * totalDelivered(r),
                                            )
                                        }}
                                    </div>
                                    <div
                                        v-if="r.pack_size > 1"
                                        class="text-xs font-normal text-muted-foreground"
                                    >
                                        = {{ totalDelivered(r) }}
                                        {{ unitLabel(r.unit) }}
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="5"
                                    class="px-3 py-4 text-center text-muted-foreground"
                                >
                                    <div>
                                        {{
                                            t('pageDealer.orders.noStockTaken')
                                        }}
                                    </div>
                                    <Button
                                        v-if="canAssemble"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="mt-2"
                                        @click="requestAssemble"
                                    >
                                        {{
                                            t('pageDealer.orders.takeFromStock')
                                        }}
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Mobile card list -->
                    <div class="flex flex-col divide-y sm:hidden">
                        <div
                            v-for="(r, idx) in rows"
                            :key="`m-${idx}`"
                            class="flex flex-col gap-2 p-3"
                        >
                            <div class="min-w-0">
                                <p class="font-medium break-words">
                                    {{ r.product_name }}
                                    <span
                                        v-if="r.product_type_name"
                                        class="text-muted-foreground"
                                        >— {{ r.product_type_name }}</span
                                    >
                                </p>
                            </div>
                            <div
                                class="grid grid-cols-2 gap-x-3 gap-y-1 text-sm"
                            >
                                <div
                                    class="col-span-2 flex items-center justify-between gap-2"
                                >
                                    <Label
                                        :for="`m-price-${idx}`"
                                        class="text-muted-foreground"
                                        >{{
                                            t('pageDealer.orders.price')
                                        }}</Label
                                    >
                                    <Input
                                        :id="`m-price-${idx}`"
                                        type="number"
                                        min="0"
                                        v-model.number="r.price"
                                        class="h-8 w-32 text-right font-mono"
                                    />
                                </div>
                                <div class="flex justify-between gap-2">
                                    <span class="text-muted-foreground">{{
                                        t('pageDealer.orders.pickedOut')
                                    }}</span>
                                    <span class="text-right">
                                        {{ r.picked_qty }}
                                        {{ unitLabel(r.unit) }}
                                        <span
                                            v-if="
                                                r.pack_size > 1 &&
                                                r.picked_pack_qty > 0
                                            "
                                            class="block text-xs text-muted-foreground"
                                        >
                                            <template
                                                v-if="pickedLooseQty(r) > 0"
                                            >
                                                {{ r.picked_pack_qty }}
                                                {{
                                                    t(
                                                        'pageDealer.orders.blockUnit',
                                                    )
                                                }}
                                                + {{ pickedLooseQty(r) }}
                                                {{ unitLabel(r.unit) }}
                                            </template>
                                            <template v-else>
                                                {{ r.picked_pack_qty }}
                                                {{
                                                    t(
                                                        'pageDealer.orders.blockUnit',
                                                    )
                                                }}
                                                × {{ r.pack_size }}
                                            </template>
                                        </span>
                                    </span>
                                </div>
                                <div
                                    class="col-span-2 flex justify-between gap-2 font-medium"
                                >
                                    <span class="text-muted-foreground">{{
                                        t('pageDealer.orders.total')
                                    }}</span>
                                    <span class="text-right">
                                        <span class="font-mono">{{
                                            formatWithSymbol(
                                                r.price * totalDelivered(r),
                                            )
                                        }}</span>
                                        <span
                                            v-if="r.pack_size > 1"
                                            class="block text-xs font-normal text-muted-foreground"
                                        >
                                            = {{ totalDelivered(r) }}
                                            {{ unitLabel(r.unit) }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <div
                                v-if="r.pack_size > 1"
                                class="grid gap-2"
                                :class="
                                    r.bulk_only ? 'grid-cols-1' : 'grid-cols-2'
                                "
                            >
                                <div>
                                    <Label
                                        :for="`m-pack-${idx}`"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ t('pageDealer.orders.givenBlock') }}
                                    </Label>
                                    <Input
                                        :id="`m-pack-${idx}`"
                                        type="number"
                                        min="0"
                                        :max="r.picked_pack_qty"
                                        v-model.number="r.delivered_pack_qty"
                                        class="mt-1 h-9 text-right"
                                        @change="clampToPicked(r)"
                                    />
                                </div>
                                <div v-if="!r.bulk_only">
                                    <Label
                                        :for="`m-unit-${idx}`"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            t('pageDealer.orders.givenUnit', {
                                                unit: unitLabel(
                                                    r.unit ?? 'dona',
                                                ),
                                            })
                                        }}
                                    </Label>
                                    <Input
                                        :id="`m-unit-${idx}`"
                                        type="number"
                                        min="0"
                                        v-model.number="r.delivered_unit_qty"
                                        class="mt-1 h-9 text-right"
                                        @change="clampToPicked(r)"
                                    />
                                </div>
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <Label
                                    :for="`m-qty-${idx}`"
                                    class="text-xs text-muted-foreground"
                                    >{{ t('pageDealer.orders.given') }}</Label
                                >
                                <Input
                                    :id="`m-qty-${idx}`"
                                    type="number"
                                    min="0"
                                    :max="r.picked_qty"
                                    v-model.number="r.delivered_unit_qty"
                                    class="h-9 flex-1 text-right"
                                    @change="clampToPicked(r)"
                                />
                                <span
                                    v-if="r.unit"
                                    class="shrink-0 text-xs text-muted-foreground"
                                    >{{ unitLabel(r.unit) }}</span
                                >
                            </div>
                        </div>
                        <div
                            v-if="rows.length === 0"
                            class="flex flex-col items-center gap-2 p-4 text-center text-sm text-muted-foreground"
                        >
                            <span>{{
                                t('pageDealer.orders.noStockTaken')
                            }}</span>
                            <Button
                                v-if="canAssemble"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="requestAssemble"
                            >
                                {{ t('pageDealer.orders.takeFromStock') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    class="grid grid-cols-1 gap-4 rounded-lg border bg-muted/20 p-3 sm:grid-cols-2 sm:p-4"
                >
                    <div class="space-y-1.5">
                        <div
                            v-if="previousDebt > 0"
                            class="flex justify-between text-sm"
                        >
                            <span class="text-muted-foreground">{{
                                t('pageDealer.orders.previousDebt')
                            }}</span>
                            <span
                                class="font-mono font-medium text-amber-600"
                                >{{ formatWithSymbol(previousDebt) }}</span
                            >
                        </div>
                        <div
                            v-else-if="previousCredit > 0"
                            class="flex justify-between text-sm"
                        >
                            <span class="text-muted-foreground">{{
                                t('pageDealer.orders.previousOverpay')
                            }}</span>
                            <span
                                class="font-mono font-medium text-emerald-600"
                                >{{ formatWithSymbol(previousCredit) }}</span
                            >
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.orders.orderTotal')
                            }}</span>
                            <span class="font-mono">{{
                                formatWithSymbol(order.total)
                            }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.orders.givenTotal')
                            }}</span>
                            <span class="font-mono font-medium">{{
                                formatWithSymbol(deliveredTotal)
                            }}</span>
                        </div>
                        <div
                            v-if="effectiveDiscount > 0"
                            class="flex justify-between text-sm"
                        >
                            <span class="text-muted-foreground">{{
                                t('pageDealer.orders.discount')
                            }}</span>
                            <span class="font-mono font-medium text-rose-600"
                                >−{{
                                    formatWithSymbol(effectiveDiscount)
                                }}</span
                            >
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.orders.paymentAmount')
                            }}</span>
                            <span class="font-mono font-medium">{{
                                formatWithSymbol(payableTotal)
                            }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.orders.paidLabel')
                            }}</span>
                            <span
                                class="font-mono font-medium text-emerald-600"
                                >{{ formatWithSymbol(paidAmount) }}</span
                            >
                        </div>
                        <div
                            v-if="paidCard > 0"
                            class="flex justify-between text-xs text-muted-foreground"
                        >
                            <span class="pl-3"
                                >— {{ t('pageDealer.orders.cash') }}</span
                            >
                            <span class="font-mono">{{
                                formatWithSymbol(paidCash || 0)
                            }}</span>
                        </div>
                        <div
                            v-if="paidCard > 0"
                            class="flex justify-between text-xs text-muted-foreground"
                        >
                            <span class="pl-3"
                                >— {{ t('pageDealer.orders.card') }}</span
                            >
                            <span class="font-mono">{{
                                formatWithSymbol(paidCard || 0)
                            }}</span>
                        </div>
                        <div
                            class="flex justify-between border-t pt-1.5 text-sm"
                        >
                            <span class="font-medium">{{
                                t('pageDealer.orders.orderDebt')
                            }}</span>
                            <span
                                class="font-mono font-bold"
                                :class="
                                    balanceDelta > 0
                                        ? 'text-amber-600'
                                        : 'text-emerald-600'
                                "
                            >
                                {{ formatWithSymbol(balanceDelta) }}
                            </span>
                        </div>
                        <div class="flex justify-between pt-1 text-sm">
                            <span class="font-medium">{{
                                t('pageDealer.orders.finalDebt')
                            }}</span>
                            <span
                                class="font-mono font-bold"
                                :class="
                                    finalBalance < 0
                                        ? 'text-amber-600'
                                        : 'text-emerald-600'
                                "
                            >
                                {{
                                    formatWithSymbol(
                                        finalBalance < 0
                                            ? -finalBalance
                                            : finalBalance,
                                    )
                                }}
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                >
                                    {{
                                        finalBalance < 0
                                            ? t('pageDealer.orders.debtor')
                                            : finalBalance > 0
                                              ? t('pageDealer.orders.overpaid')
                                              : ''
                                    }}
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <Label for="discount_amount">{{
                                t('pageDealer.orders.discountWithCurrency', {
                                    currency: symbol,
                                })
                            }}</Label>
                            <Input
                                id="discount_amount"
                                type="number"
                                min="0"
                                :max="deliveredTotal"
                                v-model.number="discountAmount"
                                class="mt-1.5"
                            />
                            <div
                                class="mt-1 flex flex-wrap items-center gap-1.5 text-xs"
                            >
                                <button
                                    type="button"
                                    class="rounded border px-2 py-0.5 hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="orderDebtGap <= 0"
                                    :title="
                                        t(
                                            'pageDealer.orders.discountGapTitle',
                                            {
                                                amount: formatWithSymbol(
                                                    orderDebtGap,
                                                ),
                                            },
                                        )
                                    "
                                    @click="discountAmount = orderDebtGap"
                                >
                                    {{
                                        t('pageDealer.orders.equalsGap', {
                                            amount: formatWithSymbol(
                                                orderDebtGap,
                                            ),
                                        })
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded border px-2 py-0.5 hover:bg-muted"
                                    @click="discountAmount = 0"
                                >
                                    0
                                </button>
                                <span
                                    v-if="discountAmount > deliveredTotal"
                                    class="text-amber-600"
                                >
                                    {{
                                        t('pageDealer.orders.exceededGiven', {
                                            amount: formatWithSymbol(
                                                effectiveDiscount,
                                            ),
                                        })
                                    }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <Label>{{
                                t('pageDealer.orders.paidMoney', {
                                    currency: symbol,
                                })
                            }}</Label>
                            <div class="mt-1.5 grid grid-cols-2 gap-2">
                                <div>
                                    <Label
                                        for="paid_cash"
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t('pageDealer.orders.cash')
                                        }}</Label
                                    >
                                    <Input
                                        id="paid_cash"
                                        type="number"
                                        min="0"
                                        v-model.number="paidCash"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label
                                        for="paid_card"
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t('pageDealer.orders.card')
                                        }}</Label
                                    >
                                    <Input
                                        id="paid_card"
                                        type="number"
                                        min="0"
                                        v-model.number="paidCard"
                                        class="mt-1"
                                    />
                                </div>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-1.5 text-xs">
                                <button
                                    type="button"
                                    class="rounded border px-2 py-0.5 hover:bg-muted"
                                    :class="
                                        paymentMode === 'all-cash'
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : ''
                                    "
                                    @click="paymentMode = 'all-cash'"
                                >
                                    {{ t('pageDealer.orders.allCash') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded border px-2 py-0.5 hover:bg-muted"
                                    :class="
                                        paymentMode === 'all-card'
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : ''
                                    "
                                    @click="paymentMode = 'all-card'"
                                >
                                    {{ t('pageDealer.orders.allCard') }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded border px-2 py-0.5 hover:bg-muted"
                                    :class="
                                        paymentMode === 'none'
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : ''
                                    "
                                    @click="paymentMode = 'none'"
                                >
                                    0
                                </button>
                            </div>
                            <div v-if="paidCard > 0" class="mt-3">
                                <Label
                                    for="cardholder_name"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        t(
                                            'pageDealer.orders.cardholderRequired',
                                        )
                                    }}
                                    <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="cardholder_name"
                                    type="text"
                                    v-model="cardholderName"
                                    :placeholder="
                                        t('pageDealer.orders.cardholderExample')
                                    "
                                    class="mt-1"
                                />
                            </div>
                        </div>
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
                <Button
                    variant="outline"
                    type="button"
                    @click="$emit('update:open', false)"
                    >{{ t('pageDealer.orders.cancel') }}</Button
                >
                <Button
                    :disabled="processing || rows.length === 0"
                    @click="submit"
                >
                    {{
                        processing
                            ? t('pageDealer.orders.saving')
                            : t('pageDealer.orders.markDelivered')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
