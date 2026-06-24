<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Banknote, CreditCard, Scale, Wallet } from 'lucide-vue-next';
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
    order_item_id: number;
    product_id: number;
    product_type_id: number | null;
    product_name: string;
    product_type_name: string | null;
    pack_size: number;
    delivered_qty: number;
    delivered_pack_qty: number;
    already_returned_qty: number;
    return_pack_qty: number;
    return_unit_qty: number;
    price: number;
    pack_price: number | null;
    unit: string | null;
};

type PaymentMode = 'balance' | 'cash' | 'card' | 'split';

const props = defineProps<{
    open: boolean;
    order: Order;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();
const unitLabel = useUnitLabel();
const { formatWithSymbol } = useCurrency();

const REASONS = computed(() => [
    { value: 'defective', label: t('pageDealer.orders.reasonDefective') },
    { value: 'expired', label: t('pageDealer.orders.reasonExpired') },
    { value: 'wrong_item', label: t('pageDealer.orders.reasonWrongItem') },
    { value: 'unsold', label: t('pageDealer.orders.reasonUnsold') },
    { value: 'damaged', label: t('pageDealer.orders.reasonDamaged') },
    { value: 'other', label: t('pageDealer.orders.reasonOther') },
]);

const PAYMENT_MODES = computed<
    { value: PaymentMode; label: string; icon: typeof Wallet }[]
>(() => [
    {
        value: 'balance',
        label: t('pageDealer.orders.modeBalance'),
        icon: Wallet,
    },
    { value: 'cash', label: t('pageDealer.orders.modeCash'), icon: Banknote },
    { value: 'card', label: t('pageDealer.orders.modeCard'), icon: CreditCard },
    { value: 'split', label: t('pageDealer.orders.modeSplit'), icon: Scale },
]);

const rows = reactive<ReturnRow[]>([]);
const reason = ref<string>('defective');
const note = ref('');
const paymentMode = ref<PaymentMode>('balance');
const customCash = ref(0);
const customCard = ref(0);
const cardholderName = ref('');
const processing = ref(false);
const error = ref<string | null>(null);

function buildRow(i: OrderItem): ReturnRow {
    const delivered = Number(i.delivered_qty ?? 0);
    const alreadyReturned = Number(
        (i as { returned_qty?: number }).returned_qty ?? 0,
    );
    const packSize = Math.max(1, i.pack_size ?? 1);

    return {
        order_item_id: i.id,
        product_id: i.product_id,
        product_type_id: i.product_type_id ?? null,
        product_name: i.product_name,
        product_type_name: i.product_type_name ?? null,
        pack_size: packSize,
        delivered_qty: delivered,
        delivered_pack_qty: Math.max(0, Number(i.delivered_pack_qty ?? 0)),
        already_returned_qty: alreadyReturned,
        return_pack_qty: 0,
        return_unit_qty: 0,
        price: Number(i.price ?? 0),
        pack_price:
            i.pack_price !== null && i.pack_price !== undefined
                ? Number(i.pack_price)
                : null,
        unit: i.unit ?? null,
    };
}

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        rows.splice(0, rows.length);
        reason.value = 'defective';
        note.value = '';
        paymentMode.value = 'balance';
        customCash.value = 0;
        customCard.value = 0;
        cardholderName.value = '';
        error.value = null;

        (props.order.items ?? []).forEach((i: OrderItem) => {
            const delivered = Number(i.delivered_qty ?? 0);

            if (delivered <= 0) {
                return;
            }

            const alreadyReturned = Number(
                (i as { returned_qty?: number }).returned_qty ?? 0,
            );

            if (delivered - alreadyReturned <= 0) {
                return;
            }

            rows.push(buildRow(i));
        });
    },
    { immediate: true },
);

function totalQty(r: ReturnRow): number {
    const packs = Math.max(0, Number(r.return_pack_qty) || 0);
    const units = Math.max(0, Number(r.return_unit_qty) || 0);

    return packs * Math.max(1, r.pack_size) + units;
}

function remainingQty(r: ReturnRow): number {
    return Math.max(0, r.delivered_qty - r.already_returned_qty);
}

function clamp(r: ReturnRow) {
    const remaining = remainingQty(r);

    if (r.pack_size > 1) {
        r.return_pack_qty = Math.min(
            Math.max(0, r.return_pack_qty),
            r.delivered_pack_qty,
        );
        const looseAfterPacks = Math.max(
            0,
            remaining - r.return_pack_qty * r.pack_size,
        );
        r.return_unit_qty = Math.min(
            Math.max(0, r.return_unit_qty),
            looseAfterPacks,
        );
    } else {
        r.return_unit_qty = Math.min(Math.max(0, r.return_unit_qty), remaining);
        r.return_pack_qty = 0;
    }
}

function fillMax(r: ReturnRow) {
    if (r.pack_size > 1) {
        r.return_pack_qty = r.delivered_pack_qty;
        const remaining = remainingQty(r);
        r.return_unit_qty = Math.max(
            0,
            remaining - r.delivered_pack_qty * r.pack_size,
        );
    } else {
        r.return_unit_qty = remainingQty(r);
    }
}

function clearRow(r: ReturnRow) {
    r.return_pack_qty = 0;
    r.return_unit_qty = 0;
}

function lineTotal(r: ReturnRow): number {
    const packs = Math.max(0, r.return_pack_qty);
    const packSize = Math.max(1, r.pack_size);

    if (packs > 0 && r.pack_price !== null && packSize > 1) {
        const loose = Math.max(0, totalQty(r) - packs * packSize);

        return Math.round(packs * r.pack_price + loose * r.price);
    }

    return Math.round(totalQty(r) * r.price);
}

const activeRows = computed(() => rows.filter((r) => totalQty(r) > 0));
const refundTotal = computed(() =>
    activeRows.value.reduce((sum, r) => sum + lineTotal(r), 0),
);

const paidCash = computed<number>({
    get: () => {
        if (paymentMode.value === 'cash') {
            return refundTotal.value;
        }

        if (paymentMode.value === 'balance' || paymentMode.value === 'card') {
            return 0;
        }

        return Math.max(0, Number(customCash.value) || 0);
    },
    set: (v: number) => {
        customCash.value = Math.max(0, Number(v) || 0);

        if (paymentMode.value !== 'split') {
            paymentMode.value = 'split';
        }
    },
});

const paidCard = computed<number>({
    get: () => {
        if (paymentMode.value === 'card') {
            return refundTotal.value;
        }

        if (paymentMode.value === 'balance' || paymentMode.value === 'cash') {
            return 0;
        }

        return Math.max(0, Number(customCard.value) || 0);
    },
    set: (v: number) => {
        customCard.value = Math.max(0, Number(v) || 0);

        if (paymentMode.value !== 'split') {
            paymentMode.value = 'split';
        }
    },
});

const previousBalance = computed(() => props.order.shop?.balance ?? 0);
const balanceCredit = computed(
    () => refundTotal.value - paidCash.value - paidCard.value,
);
const finalBalance = computed(
    () => previousBalance.value + balanceCredit.value,
);

function selectPaymentMode(mode: PaymentMode) {
    paymentMode.value = mode;

    if (mode !== 'split') {
        customCash.value = 0;
        customCard.value = 0;
    }
}

function balanceDeltaLabel(delta: number): string {
    if (delta === 0) {
        return '';
    }

    return delta > 0
        ? `+${formatWithSymbol(delta)}`
        : `−${formatWithSymbol(Math.abs(delta))}`;
}

function submit() {
    const cardAmount = Math.max(0, Number(paidCard.value) || 0);
    const trimmedCardholder = cardholderName.value.trim();

    if (cardAmount > 0 && trimmedCardholder === '') {
        error.value = t('pageDealer.orders.cardholderNameRequired');

        return;
    }

    const items = activeRows.value.map((r) => ({
        order_item_id: r.order_item_id,
        qty: totalQty(r),
        pack_qty:
            r.pack_size > 1
                ? Math.max(0, Number(r.return_pack_qty) || 0)
                : null,
        disposition: 'restock',
    }));

    if (items.length === 0) {
        error.value = t('pageDealer.orders.atLeastOneQty');

        return;
    }

    processing.value = true;
    error.value = null;

    router.post(
        `/dealer/orders/${props.order.id}/shop-return`,
        {
            reason: reason.value,
            note: note.value || null,
            paid_cash: Math.max(0, Math.round(Number(paidCash.value) || 0)),
            paid_card: Math.max(0, Math.round(cardAmount)),
            cardholder_name: cardAmount > 0 ? trimmedCardholder : null,
            items,
        },
        {
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
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex h-[100dvh] max-h-[100dvh] w-screen max-w-none flex-col gap-0 rounded-none border-0 p-0 sm:h-auto sm:max-h-[calc(100dvh-2rem)] sm:w-auto sm:max-w-3xl sm:rounded-lg sm:border"
            @open-auto-focus="(e: Event) => e.preventDefault()"
        >
            <DialogHeader class="border-b p-4 sm:p-6 sm:pb-4">
                <DialogTitle class="pr-8 text-base sm:text-lg">{{
                    t('pageDealer.orders.shopReturnTitle')
                }}</DialogTitle>
                <p class="text-xs text-muted-foreground">
                    {{
                        t('pageDealer.orders.orderNumber', {
                            number: order.number,
                        })
                    }}
                    <span v-if="order.shop"> · {{ order.shop.name }}</span>
                </p>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto p-4 sm:p-6">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <Label
                            class="mb-1.5 block text-xs text-muted-foreground"
                            >{{ t('pageDealer.orders.returnReason') }}</Label
                        >
                        <select
                            v-model="reason"
                            class="block h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                        >
                            <option
                                v-for="r in REASONS"
                                :key="r.value"
                                :value="r.value"
                            >
                                {{ r.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label
                            class="mb-1.5 block text-xs text-muted-foreground"
                            >{{ t('pageDealer.orders.note') }}</Label
                        >
                        <Input
                            v-model="note"
                            class="h-9"
                            :placeholder="t('pageDealer.orders.optional')"
                        />
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <Label class="text-xs text-muted-foreground">{{
                            t('pageDealer.orders.returnableProducts')
                        }}</Label>
                        <span class="text-xs text-muted-foreground">
                            {{
                                t('pageDealer.orders.selectedCount', {
                                    selected: activeRows.length,
                                    total: rows.length,
                                })
                            }}
                        </span>
                    </div>

                    <div
                        v-if="rows.length === 0"
                        class="rounded-lg border bg-muted/20 px-3 py-4 text-center text-sm text-muted-foreground"
                    >
                        {{ t('pageDealer.orders.noReturnableProducts') }}
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="r in rows"
                            :key="r.order_item_id"
                            class="rounded-lg border p-3 transition-colors"
                            :class="
                                totalQty(r) > 0
                                    ? 'border-primary/40 bg-primary/[0.04]'
                                    : 'bg-background'
                            "
                        >
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-3"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium break-words">
                                        {{ r.product_name
                                        }}<span
                                            v-if="r.product_type_name"
                                            class="text-muted-foreground"
                                        >
                                            — {{ r.product_type_name }}</span
                                        >
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            t(
                                                'pageDealer.orders.deliveredColon',
                                                {
                                                    qty: r.delivered_qty,
                                                    unit: unitLabel(r.unit),
                                                },
                                            )
                                        }}
                                        <span v-if="r.already_returned_qty > 0">
                                            ·
                                            {{
                                                t(
                                                    'pageDealer.orders.previouslyReturned',
                                                    {
                                                        qty: r.already_returned_qty,
                                                    },
                                                )
                                            }}</span
                                        >
                                    </p>
                                </div>
                                <span
                                    v-if="totalQty(r) > 0"
                                    class="shrink-0 font-mono text-sm font-semibold tabular-nums"
                                >
                                    {{ formatWithSymbol(lineTotal(r)) }}
                                </span>
                            </div>

                            <div
                                class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3"
                            >
                                <div
                                    class="flex flex-1 flex-wrap items-center gap-2"
                                >
                                    <template v-if="r.pack_size > 1">
                                        <div class="flex items-center gap-1">
                                            <Input
                                                type="number"
                                                min="0"
                                                v-model.number="
                                                    r.return_pack_qty
                                                "
                                                class="h-9 w-16 text-right tabular-nums"
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
                                                class="h-9 w-16 text-right tabular-nums"
                                                @change="clamp(r)"
                                            />
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    unitLabel(r.unit ?? 'dona')
                                                }}</span
                                            >
                                        </div>
                                    </template>
                                    <template v-else>
                                        <Input
                                            type="number"
                                            min="0"
                                            v-model.number="r.return_unit_qty"
                                            class="h-9 w-24 text-right tabular-nums"
                                            @change="clamp(r)"
                                        />
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >{{
                                                unitLabel(r.unit ?? 'dona')
                                            }}</span
                                        >
                                    </template>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                                        @click="fillMax(r)"
                                    >
                                        {{ t('pageDealer.orders.all') }}
                                    </Button>
                                    <Button
                                        v-if="totalQty(r) > 0"
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                                        @click="clearRow(r)"
                                    >
                                        {{ t('pageDealer.orders.clear') }}
                                    </Button>
                                </div>
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

            <div class="border-t bg-muted/30 p-4 sm:p-6">
                <div class="mb-3 flex items-baseline justify-between">
                    <span class="text-sm text-muted-foreground">{{
                        t('pageDealer.orders.refundAmount')
                    }}</span>
                    <span
                        class="font-mono text-lg font-semibold tabular-nums"
                        >{{ formatWithSymbol(refundTotal) }}</span
                    >
                </div>

                <div class="mb-3">
                    <Label class="mb-1.5 block text-xs text-muted-foreground">{{
                        t('pageDealer.orders.returnMethod')
                    }}</Label>
                    <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                        <button
                            v-for="m in PAYMENT_MODES"
                            :key="m.value"
                            type="button"
                            class="flex items-center justify-center gap-1.5 rounded-md border px-2 py-2 text-xs font-medium transition-colors sm:flex-col sm:gap-1"
                            :class="
                                paymentMode === m.value
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-input text-muted-foreground hover:bg-muted'
                            "
                            @click="selectPaymentMode(m.value)"
                        >
                            <component :is="m.icon" class="h-4 w-4 shrink-0" />
                            <span class="truncate">{{ m.label }}</span>
                        </button>
                    </div>
                </div>

                <div
                    v-if="paymentMode === 'cash' || paymentMode === 'split'"
                    class="mb-2"
                >
                    <Label class="mb-1 block text-xs text-muted-foreground">{{
                        t('pageDealer.orders.cashRefunded')
                    }}</Label>
                    <div class="relative">
                        <Banknote
                            class="pointer-events-none absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            type="number"
                            min="0"
                            v-model.number="paidCash"
                            class="h-9 pl-8 text-right tabular-nums"
                            placeholder="0"
                        />
                    </div>
                </div>

                <div
                    v-if="paymentMode === 'card' || paymentMode === 'split'"
                    class="mb-2"
                >
                    <Label class="mb-1 block text-xs text-muted-foreground">{{
                        t('pageDealer.orders.cardRefunded')
                    }}</Label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="relative">
                            <CreditCard
                                class="pointer-events-none absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                type="number"
                                min="0"
                                v-model.number="paidCard"
                                class="h-9 pl-8 text-right tabular-nums"
                                placeholder="0"
                            />
                        </div>
                        <Input
                            v-model="cardholderName"
                            class="h-9"
                            :placeholder="t('pageDealer.orders.cardholderFio')"
                        />
                    </div>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-t pt-2 text-sm"
                >
                    <span class="text-muted-foreground">{{
                        t('pageDealer.orders.shopBalance')
                    }}</span>
                    <span class="font-mono tabular-nums">
                        {{ formatWithSymbol(previousBalance) }}
                        <span class="text-muted-foreground">→</span>
                        <span class="font-semibold">{{
                            formatWithSymbol(finalBalance)
                        }}</span>
                        <span
                            v-if="balanceCredit !== 0"
                            class="ml-1 inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium"
                            :class="
                                balanceCredit > 0
                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
                            "
                        >
                            {{ balanceDeltaLabel(balanceCredit) }}
                        </span>
                    </span>
                </div>
            </div>

            <DialogFooter class="flex-row gap-2 border-t p-3 sm:p-6 sm:pt-4">
                <Button
                    variant="outline"
                    class="flex-1 sm:flex-none"
                    @click="emit('update:open', false)"
                    >{{ t('pageDealer.orders.cancelShort') }}</Button
                >
                <Button
                    class="flex-1 sm:flex-none"
                    :disabled="processing || activeRows.length === 0"
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
